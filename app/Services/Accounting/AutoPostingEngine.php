<?php

namespace App\Services\Accounting;

use App\Actions\Ledger\CreateJournalEntryAction;
use App\Actions\Ledger\PostJournalEntryAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\BusinessLedgerSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AutoPostingEngine
{
    public function __construct(
        private BusinessLedgerSettingsService $ledgerSettings,
        private CreateJournalEntryAction $createJournalEntry,
        private PostJournalEntryAction $postJournalEntry,
    ) {}

    public function postInvoice(Invoice $invoice, User $user): ?JournalEntry
    {
        $settings = $this->ledgerSettings->get((int) $invoice->business_id);

        if ($settings->auto_post_mode === AutoPostMode::Off) {
            return null;
        }

        $existing = JournalEntry::query()
            ->where('business_id', $invoice->business_id)
            ->where('referenceable_type', $invoice->getMorphClass())
            ->where('referenceable_id', $invoice->id)
            ->where('voucher_number', 'not like', '%__del_%')
            ->first();

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
        $fiscalYear = $this->resolveFiscalYear((int) $invoice->business_id, $entryDate);

        $lines = match ($invoice->type) {
            InvoiceType::Sale => $this->saleLines($invoice, $settings),
            InvoiceType::Purchase => $this->purchaseLines($invoice, $settings),
            InvoiceType::Return => $this->returnLines($invoice, $settings),
        };

        if (count($lines) < 2) {
            Log::warning('Auto-post skipped: unbalanced or incomplete invoice mapping.', [
                'invoice_id' => $invoice->id,
                'business_id' => $invoice->business_id,
            ]);

            return null;
        }

        $previousBusinessId = $user->current_business_id;
        $user->forceFill(['current_business_id' => $invoice->business_id])->saveQuietly();

        try {
            $entry = $this->createJournalEntry->handle($user, [
                'fiscal_year_id' => $fiscalYear->id,
                'entry_date' => $entryDate,
                'description' => __('general.auto_post_invoice_description', [
                    'number' => $invoice->invoice_number,
                ]),
                'status' => JournalEntryStatus::Draft,
                'referenceable_type' => $invoice->getMorphClass(),
                'referenceable_id' => $invoice->id,
            ], $lines);

            if ($settings->auto_post_mode === AutoPostMode::Realtime) {
                $entry = $this->postJournalEntry->handle($user, $entry);
            }

            return $entry;
        } finally {
            $user->forceFill(['current_business_id' => $previousBusinessId])->saveQuietly();
        }
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
        // Reverse of a sale: credit AR, debit sales / VAT.
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

        return [$itemTax, $netSales, $globalDiscount, $globalTax, $total];
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

        // If tax/discount mapping missing, collapse to AR/Sales only.
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
                'invoice' => [__('general.auto_post_needs_fiscal_year')],
            ]);
        }

        return $fiscalYear;
    }
}
