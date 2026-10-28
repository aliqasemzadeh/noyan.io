<?php

namespace App\Services\Accounting;

use App\Actions\Ledger\CreateJournalEntryAction;
use App\Actions\Ledger\PostJournalEntryAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\AutoPostQueueStatus;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\AutoPostQueue;
use App\Models\Accounting\BusinessCurrency;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\BusinessLedgerSetting;
use App\Models\User;
use App\Support\Accounting\CategoryGlAccountMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AutoPostingEngine
{
    /** @var array<int, string|null> */
    protected array $baseCurrencyCodes = [];

    public function __construct(
        private BusinessLedgerSettingsService $ledgerSettings,
        private CreateJournalEntryAction $createJournalEntry,
        private PostJournalEntryAction $postJournalEntry,
    ) {}

    /**
     * @param  bool  $forceDraft  When true, create a draft voucher even if auto-post is Off (backfill).
     */
    public function postInvoice(Invoice $invoice, User $user, bool $forceDraft = false, bool $forcePost = false): ?JournalEntry
    {
        $settings = $this->ledgerSettings->get((int) $invoice->business_id);

        if (! $forceDraft && ! $forcePost && $settings->auto_post_mode === AutoPostMode::Off) {
            return null;
        }

        $existing = $this->findLinkedEntry($invoice);

        if ($existing !== null) {
            return $existing;
        }

        $invoice->loadMissing(['items', 'party']);

        if ($invoice->party_id === null) {
            Log::warning('Auto-post skipped: invoice has no party for AR/AP detail.', [
                'invoice_id' => $invoice->id,
                'business_id' => $invoice->business_id,
            ]);

            return null;
        }

        $entryDate = $invoice->issue_date?->toDateString() ?? now()->toDateString();

        if (! $forceDraft && ! $forcePost && $settings->auto_post_mode === AutoPostMode::DailyBatch) {
            $this->enqueue($invoice, $user, $entryDate);

            return null;
        }

        $lines = match ($invoice->type) {
            InvoiceType::Sale => $this->saleLines($invoice, $settings),
            InvoiceType::Purchase => $this->purchaseLines($invoice, $settings),
            InvoiceType::Return => $this->returnLines($invoice, $settings),
        };

        return $this->createAndOptionallyPost(
            $user,
            (int) $invoice->business_id,
            $entryDate,
            __('general.auto_post_invoice_description', ['number' => $invoice->invoice_number]),
            $invoice,
            $lines,
            $settings,
            ['invoice_id' => $invoice->id],
            forceDraft: $forceDraft,
            forcePost: $forcePost,
        );
    }

    /**
     * @param  bool  $forceDraft  When true, create a draft voucher even if auto-post is Off (backfill).
     */
    public function postTransaction(Transaction $transaction, User $user, bool $forceDraft = false, bool $forcePost = false): ?JournalEntry
    {
        $settings = $this->ledgerSettings->get((int) $transaction->business_id);

        if (! $forceDraft && ! $forcePost && $settings->auto_post_mode === AutoPostMode::Off) {
            return null;
        }

        $existing = $this->findLinkedEntry($transaction);

        if ($existing !== null) {
            return $existing;
        }

        $transaction->loadMissing(['account', 'destinationAccount', 'party', 'category', 'invoice']);

        $this->ensureDefaultCategory($transaction);
        $this->ensureCashAccountDetail($transaction->account);
        $this->ensureCashAccountDetail($transaction->destinationAccount);

        $entryDate = $transaction->transaction_date?->toDateString() ?? now()->toDateString();

        if (! $forceDraft && ! $forcePost && $settings->auto_post_mode === AutoPostMode::DailyBatch) {
            $this->enqueue($transaction, $user, $entryDate);

            return null;
        }

        $lines = match ($transaction->type) {
            TransactionType::Income => $this->incomeTransactionLines($transaction, $settings),
            TransactionType::Expense => $this->expenseTransactionLines($transaction, $settings),
            TransactionType::Transfer => $this->transferTransactionLines($transaction, $settings),
        };

        $label = $transaction->reference_number
            ?: '#'.$transaction->id;

        return $this->createAndOptionallyPost(
            $user,
            (int) $transaction->business_id,
            $entryDate,
            __('general.auto_post_transaction_description', ['number' => $label]),
            $transaction,
            $lines,
            $settings,
            ['transaction_id' => $transaction->id],
            forceDraft: $forceDraft,
            forcePost: $forcePost,
        );
    }

    /**
     * Replace any linked voucher then create a fresh auto-post for the transaction.
     */
    public function syncTransaction(Transaction $transaction, User $user, bool $forcePost = false): ?JournalEntry
    {
        $settings = $this->ledgerSettings->get((int) $transaction->business_id);

        if (! $forcePost && $settings->auto_post_mode === AutoPostMode::Off) {
            $this->voidLinkedEntries($transaction);

            return null;
        }

        $this->voidLinkedEntries($transaction);

        return $this->postTransaction(
            $transaction,
            $user,
            forceDraft: ! $forcePost && $settings->auto_post_mode === AutoPostMode::Off,
            forcePost: $forcePost,
        );
    }

    /**
     * Replace any linked voucher then create a fresh auto-post for the invoice.
     */
    public function syncInvoice(Invoice $invoice, User $user, bool $forcePost = false): ?JournalEntry
    {
        $settings = $this->ledgerSettings->get((int) $invoice->business_id);

        if (! $forcePost && $settings->auto_post_mode === AutoPostMode::Off) {
            $this->voidLinkedEntries($invoice);

            return null;
        }

        $this->voidLinkedEntries($invoice);

        return $this->postInvoice(
            $invoice,
            $user,
            forceDraft: ! $forcePost && $settings->auto_post_mode === AutoPostMode::Off,
            forcePost: $forcePost,
        );
    }

    public function voidTransaction(Transaction $transaction): void
    {
        $this->voidLinkedEntries($transaction);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function incomeTransactionLines(Transaction $transaction, BusinessLedgerSetting $settings): array
    {
        $cashId = $this->requireCashAccountId($settings);
        $amount = $this->transactionAmount($transaction);
        $description = $this->transactionDescription($transaction);

        if ($transaction->invoice_id !== null) {
            $ar = $settings->default_accounts_receivable_id;

            if ($ar === null) {
                throw new RuntimeException('Accounts receivable default GL account is not mapped.');
            }

            [$counterparty, $fxLines] = $this->settlementFx($transaction, $settings, $amount, receipt: true);

            return [
                $this->cashLine($cashId, $transaction->account_id, $amount, '0', $description),
                [
                    'gl_account_id' => $ar,
                    'party_id' => $transaction->party_id,
                    'debit' => '0',
                    'credit' => $counterparty,
                    'description' => $description,
                ],
                ...$fxLines,
            ];
        }

        $contraId = $this->requireCategoryGlAccountId($transaction);

        return [
            $this->cashLine($cashId, $transaction->account_id, $amount, '0', $description),
            [
                'gl_account_id' => $contraId,
                'category_id' => $transaction->category_id,
                'party_id' => $transaction->party_id,
                'debit' => '0',
                'credit' => $amount,
                'description' => $description,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function expenseTransactionLines(Transaction $transaction, BusinessLedgerSetting $settings): array
    {
        $cashId = $this->requireCashAccountId($settings);
        $amount = $this->transactionAmount($transaction);
        $description = $this->transactionDescription($transaction);

        if ($transaction->invoice_id !== null) {
            $ap = $settings->default_accounts_payable_id;

            if ($ap === null) {
                throw new RuntimeException('Accounts payable default GL account is not mapped.');
            }

            [$counterparty, $fxLines] = $this->settlementFx($transaction, $settings, $amount, receipt: false);

            return [
                [
                    'gl_account_id' => $ap,
                    'party_id' => $transaction->party_id,
                    'debit' => $counterparty,
                    'credit' => '0',
                    'description' => $description,
                ],
                ...$fxLines,
                $this->cashLine($cashId, $transaction->account_id, '0', $amount, $description),
            ];
        }

        $contraId = $this->requireCategoryGlAccountId($transaction);

        return [
            [
                'gl_account_id' => $contraId,
                'category_id' => $transaction->category_id,
                'party_id' => $transaction->party_id,
                'debit' => $amount,
                'credit' => '0',
                'description' => $description,
            ],
            $this->cashLine($cashId, $transaction->account_id, '0', $amount, $description),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function transferTransactionLines(Transaction $transaction, BusinessLedgerSetting $settings): array
    {
        $cashId = $this->requireCashAccountId($settings);
        $amount = $this->transactionAmount($transaction);
        $description = $this->transactionDescription($transaction);

        if ($transaction->destination_account_id === null) {
            throw new RuntimeException('Transfer destination account is required for auto-post.');
        }

        return [
            $this->cashLine($cashId, $transaction->destination_account_id, $amount, '0', $description),
            $this->cashLine($cashId, $transaction->account_id, '0', $amount, $description),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function cashLine(
        int $cashGlAccountId,
        ?int $accountId,
        string $debit,
        string $credit,
        string $description,
    ): array {
        return [
            'gl_account_id' => $cashGlAccountId,
            'account_id' => $accountId,
            'debit' => $debit,
            'credit' => $credit,
            'description' => $description,
        ];
    }

    protected function requireCashAccountId(BusinessLedgerSetting $settings): int
    {
        if ($settings->default_cash_and_bank_id === null) {
            throw new RuntimeException('Cash and bank default GL account is not mapped.');
        }

        return (int) $settings->default_cash_and_bank_id;
    }

    protected function requireCategoryGlAccountId(Transaction $transaction): int
    {
        $account = CategoryGlAccountMap::resolve(
            (int) $transaction->business_id,
            $transaction->category,
        );

        if ($account === null) {
            throw ValidationException::withMessages([
                'category_id' => [__('general.auto_post_category_needs_gl_account')],
            ]);
        }

        return (int) $account->id;
    }

    protected function transactionAmount(Transaction $transaction): string
    {
        $amount = (string) ($transaction->base_amount ?: $transaction->amount);

        if (bccomp($amount, '0', 4) !== 1) {
            throw new RuntimeException('Transaction amount must be positive for auto-post.');
        }

        return $amount;
    }

    protected function transactionDescription(Transaction $transaction): string
    {
        return (string) ($transaction->note
            ?: $transaction->reference_number
            ?: ('TX-'.$transaction->id));
    }

    protected function ensureDefaultCategory(Transaction $transaction): void
    {
        if ($transaction->category_id !== null || $transaction->invoice_id !== null) {
            return;
        }

        if (! in_array($transaction->type, [TransactionType::Income, TransactionType::Expense], true)) {
            return;
        }

        $leaf = CategoryGlAccountMap::defaultLeafCategory(
            (int) $transaction->business_id,
            $transaction->type,
        );

        if ($leaf === null) {
            return;
        }

        $transaction->forceFill(['category_id' => $leaf->id])->saveQuietly();
        $transaction->setRelation('category', $leaf);
    }

    protected function ensureCashAccountDetail(?Account $account): void
    {
        if ($account === null) {
            return;
        }

        $account->loadMissing('glDetail');

        if ($account->glDetail === null) {
            $account->syncFloatingDetail();
            $account->load('glDetail');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $logContext
     */
    protected function createAndOptionallyPost(
        User $user,
        int $businessId,
        string $entryDate,
        string $description,
        Model $reference,
        array $lines,
        BusinessLedgerSetting $settings,
        array $logContext = [],
        bool $forceDraft = false,
        bool $forcePost = false,
    ): ?JournalEntry {
        if (count($lines) < 2) {
            Log::warning('Auto-post skipped: incomplete journal lines.', [
                ...$logContext,
                'business_id' => $businessId,
            ]);

            return null;
        }

        $fiscalYear = $this->resolveFiscalYear($businessId, $entryDate);

        $previousBusinessId = $user->current_business_id;
        $user->forceFill(['current_business_id' => $businessId])->saveQuietly();

        try {
            $status = JournalEntryStatus::Draft;

            if (! $forceDraft && ! $forcePost && $settings->auto_post_mode === AutoPostMode::ManualReview) {
                $status = JournalEntryStatus::PendingReview;
            }

            $entry = $this->createJournalEntry->handle($user, [
                'fiscal_year_id' => $fiscalYear->id,
                'entry_date' => $entryDate,
                'description' => $description,
                'status' => $status,
                'referenceable_type' => $reference->getMorphClass(),
                'referenceable_id' => $reference->getKey(),
            ], $lines);

            if ($forcePost || (! $forceDraft && $settings->auto_post_mode === AutoPostMode::Realtime)) {
                $entry = $this->postJournalEntry->handle($user, $entry);
            }

            return $entry;
        } finally {
            $user->forceFill(['current_business_id' => $previousBusinessId])->saveQuietly();
        }
    }

    protected function findLinkedEntry(Model $model): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('business_id', $model->getAttribute('business_id'))
            ->where('referenceable_type', $model->getMorphClass())
            ->where('referenceable_id', $model->getKey())
            ->where('voucher_number', 'not like', '%__del_%')
            ->first();
    }

    protected function voidLinkedEntries(Model $model): void
    {
        $entries = JournalEntry::query()
            ->where('business_id', $model->getAttribute('business_id'))
            ->where('referenceable_type', $model->getMorphClass())
            ->where('referenceable_id', $model->getKey())
            ->where('voucher_number', 'not like', '%__del_%')
            ->get();

        foreach ($entries as $entry) {
            // System vouchers may be posted; posting does not mutate cashbook balances,
            // so soft-deleting the linked voucher is the correct void path for auto-post sync.
            $entry->lines()->each(fn ($line) => $line->delete());
            $entry->delete();
        }

        $this->voidQueue($model);
    }

    protected function enqueue(Model $model, User $user, string $entryDate): void
    {
        AutoPostQueue::query()->updateOrCreate(
            [
                'business_id' => $model->getAttribute('business_id'),
                'source_type' => $model->getMorphClass(),
                'source_id' => $model->getKey(),
            ],
            [
                'entry_date' => $entryDate,
                'status' => AutoPostQueueStatus::Pending,
                'requested_by' => $user->id,
                'journal_entry_id' => null,
            ],
        );
    }

    protected function voidQueue(Model $model): void
    {
        AutoPostQueue::query()
            ->where('business_id', $model->getAttribute('business_id'))
            ->where('source_type', $model->getMorphClass())
            ->where('source_id', $model->getKey())
            ->where('status', AutoPostQueueStatus::Pending)
            ->update(['status' => AutoPostQueueStatus::Voided->value]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function saleLines(Invoice $invoice, BusinessLedgerSetting $settings): array
    {
        $receivable = $settings->default_accounts_receivable_id;
        $sales = $settings->default_sales_id;
        $discount = $settings->default_sales_discount_id;
        $vat = $settings->default_vat_payable_id;

        if ($receivable === null || $sales === null) {
            throw new RuntimeException('Sale default GL accounts are not mapped.');
        }

        [$itemTax, $netSales, $globalDiscount, $globalTax, $total] = $this->invoiceAmounts($invoice);

        $lines = [];

        $lines[] = [
            'gl_account_id' => $receivable,
            'party_id' => $invoice->party_id,
            'debit' => $total,
            'credit' => '0',
            'description' => $invoice->invoice_number,
        ];

        if (bccomp($netSales, '0', 4) === 1) {
            $lines[] = [
                'gl_account_id' => $sales,
                'debit' => '0',
                'credit' => $netSales,
                'description' => $invoice->invoice_number,
            ];
        }

        $vatAmount = bcadd($itemTax, $globalTax, 4);
        if ($vat !== null && bccomp($vatAmount, '0', 4) === 1) {
            $lines[] = [
                'gl_account_id' => $vat,
                'debit' => '0',
                'credit' => $vatAmount,
                'description' => $invoice->invoice_number,
            ];
        }

        if ($discount !== null && bccomp($globalDiscount, '0', 4) === 1) {
            $lines[] = [
                'gl_account_id' => $discount,
                'debit' => $globalDiscount,
                'credit' => '0',
                'description' => $invoice->invoice_number,
            ];
        }

        return $this->ensureBalancedFallback($lines, $total, $receivable, $invoice);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function purchaseLines(Invoice $invoice, BusinessLedgerSetting $settings): array
    {
        $payable = $settings->default_accounts_payable_id;
        $inventory = $settings->default_inventory_id;
        $vat = $settings->default_vat_receivable_id;

        if ($payable === null || $inventory === null) {
            throw new RuntimeException('Purchase default GL accounts are not mapped.');
        }

        [$itemTax, $netSales, $globalDiscount, $globalTax, $total] = $this->invoiceAmounts($invoice);
        $inventoryAmount = bcsub($netSales, $globalDiscount, 4);
        if (bccomp($inventoryAmount, '0', 4) === -1) {
            $inventoryAmount = '0';
        }

        $lines = [];

        if (bccomp($inventoryAmount, '0', 4) === 1) {
            $lines[] = [
                'gl_account_id' => $inventory,
                'debit' => $inventoryAmount,
                'credit' => '0',
                'description' => $invoice->invoice_number,
            ];
        }

        $vatAmount = bcadd($itemTax, $globalTax, 4);
        if ($vat !== null && bccomp($vatAmount, '0', 4) === 1) {
            $lines[] = [
                'gl_account_id' => $vat,
                'debit' => $vatAmount,
                'credit' => '0',
                'description' => $invoice->invoice_number,
            ];
        }

        $lines[] = [
            'gl_account_id' => $payable,
            'party_id' => $invoice->party_id,
            'debit' => '0',
            'credit' => $total,
            'description' => $invoice->invoice_number,
        ];

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function returnLines(Invoice $invoice, BusinessLedgerSetting $settings): array
    {
        $lines = $this->saleLines($invoice, $settings);

        return array_map(function (array $line): array {
            $debit = $line['debit'];
            $line['debit'] = $line['credit'];
            $line['credit'] = $debit;

            return $line;
        }, $lines);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    protected function invoiceAmounts(Invoice $invoice): array
    {
        $itemTax = '0';

        foreach ($invoice->items as $item) {
            $itemTax = bcadd($itemTax, (string) $item->tax_amount, 4);
        }

        $itemsTotal = (string) $invoice->items_total;
        $globalDiscount = (string) $invoice->global_discount;
        $globalTax = (string) $invoice->global_tax;
        $total = (string) $invoice->total_amount;
        $netSales = bcsub($itemsTotal, $itemTax, 4);

        if (bccomp($netSales, '0', 4) === -1) {
            $netSales = '0';
        }

        $rate = $this->invoiceBaseRate($invoice);

        if (bccomp($rate, '1', 8) !== 0) {
            $itemTax = bcmul($itemTax, $rate, 4);
            $netSales = bcmul($netSales, $rate, 4);
            $globalDiscount = bcmul($globalDiscount, $rate, 4);
            $globalTax = bcmul($globalTax, $rate, 4);
            $total = bcmul($total, $rate, 4);
        }

        return [$itemTax, $netSales, $globalDiscount, $globalTax, $total];
    }

    /**
     * Book the receivable or payable at the invoice rate and park the rate
     * difference on the mapped foreign-exchange gain or loss account.
     *
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    protected function settlementFx(
        Transaction $transaction,
        BusinessLedgerSetting $settings,
        string $cashBase,
        bool $receipt,
    ): array {
        $transaction->loadMissing('invoice');
        $invoice = $transaction->invoice;

        if (! $invoice instanceof Invoice) {
            return [$cashBase, []];
        }

        $invoiceCurrency = strtoupper(trim((string) ($invoice->currency ?? '')));
        $transactionCurrency = strtoupper(trim((string) ($transaction->currency ?? '')));
        $baseCurrency = $this->baseCurrencyCode((int) $transaction->business_id);

        if ($invoiceCurrency === '' || $baseCurrency === null || $invoiceCurrency === $baseCurrency || $invoiceCurrency !== $transactionCurrency) {
            return [$cashBase, []];
        }

        $invoiceRate = (string) ($invoice->exchange_rate ?: '1');
        $paymentRate = (string) ($transaction->exchange_rate ?: '1');

        if (bccomp($invoiceRate, '0', 8) !== 1 || bccomp($invoiceRate, $paymentRate, 8) === 0) {
            return [$cashBase, []];
        }

        $booked = bcmul((string) $transaction->amount, $invoiceRate, 4);
        $difference = bcsub($cashBase, $booked, 4);

        if (bccomp($difference, '0', 4) === 0) {
            return [$booked, []];
        }

        $gainId = $settings->default_fx_gain_id;
        $lossId = $settings->default_fx_loss_id;

        if ($gainId === null || $lossId === null) {
            throw new RuntimeException('Foreign exchange gain and loss accounts are not mapped.');
        }

        $description = $this->transactionDescription($transaction);
        $absolute = ltrim($difference, '-');
        $isGain = bccomp($difference, '0', 4) === 1;

        if ($receipt) {
            $line = $isGain
                ? ['gl_account_id' => $gainId, 'debit' => '0', 'credit' => $absolute, 'description' => $description]
                : ['gl_account_id' => $lossId, 'debit' => $absolute, 'credit' => '0', 'description' => $description];
        } else {
            $line = $isGain
                ? ['gl_account_id' => $lossId, 'debit' => $absolute, 'credit' => '0', 'description' => $description]
                : ['gl_account_id' => $gainId, 'debit' => '0', 'credit' => $absolute, 'description' => $description];
        }

        return [$booked, [$line]];
    }

    protected function invoiceBaseRate(Invoice $invoice): string
    {
        $currency = strtoupper(trim((string) ($invoice->currency ?? '')));
        $rate = (string) ($invoice->exchange_rate ?: '1');

        if ($currency === '' || bccomp($rate, '0', 8) !== 1) {
            return '1';
        }

        $base = $this->baseCurrencyCode((int) $invoice->business_id);

        if ($base !== null && $currency === $base) {
            return '1';
        }

        return $rate;
    }

    protected function baseCurrencyCode(int $businessId): ?string
    {
        if (array_key_exists($businessId, $this->baseCurrencyCodes)) {
            return $this->baseCurrencyCodes[$businessId];
        }

        $code = BusinessCurrency::query()
            ->where('business_id', $businessId)
            ->where('is_base', true)
            ->with('currency:id,code')
            ->first()
            ?->currency
            ?->code;

        $this->baseCurrencyCodes[$businessId] = $code !== null ? strtoupper((string) $code) : null;

        return $this->baseCurrencyCodes[$businessId];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function ensureBalancedFallback(
        array $lines,
        string $total,
        int $receivableId,
        Invoice $invoice,
    ): array {
        $debit = '0';
        $credit = '0';

        foreach ($lines as $line) {
            $debit = bcadd($debit, (string) $line['debit'], 4);
            $credit = bcadd($credit, (string) $line['credit'], 4);
        }

        if (bccomp($debit, $credit, 4) === 0) {
            return $lines;
        }

        return [
            [
                'gl_account_id' => $receivableId,
                'party_id' => $invoice->party_id,
                'debit' => $total,
                'credit' => '0',
                'description' => $invoice->invoice_number,
            ],
            [
                'gl_account_id' => $lines[1]['gl_account_id'] ?? $receivableId,
                'debit' => '0',
                'credit' => $total,
                'description' => $invoice->invoice_number,
            ],
        ];
    }

    protected function resolveFiscalYear(int $businessId, string $entryDate): FiscalYear
    {
        $fiscalYear = FiscalYear::query()
            ->where('business_id', $businessId)
            ->where('is_closed', false)
            ->whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->orderByDesc('start_date')
            ->first();

        if ($fiscalYear === null) {
            throw ValidationException::withMessages([
                'transaction_date' => [__('general.auto_post_needs_fiscal_year')],
            ]);
        }

        return $fiscalYear;
    }
}
