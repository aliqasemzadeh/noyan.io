<?php

namespace App\Actions\Ledger;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SyncTreasuryFromPostedJournalAction
{
    public function __construct(
        protected ProcessTransactionAction $processTransaction,
    ) {}

    /**
     * Link a posted unlinked journal to treasury transaction(s) and apply balances.
     * Preserves the voucher (soft-create only). Returns the primary transaction, or null when skipped.
     */
    public function handle(User $user, JournalEntry $entry): ?Transaction
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to sync a journal entry.');
        }

        return DB::transaction(function () use ($user, $entry, $businessId): ?Transaction {
            $locked = JournalEntry::query()
                ->with(['lines.glAccount'])
                ->whereKey($entry->id)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'entry' => [__('general.journal_entry_not_found')],
                ]);
            }

            if (! $locked->isPosted()) {
                return null;
            }

            if ($locked->referenceable_type !== null && $locked->referenceable_id !== null) {
                $existing = $locked->referenceable;

                return $existing instanceof Transaction ? $existing : null;
            }

            $business = Business::query()->find($businessId);

            if ($business === null) {
                return null;
            }

            $settings = BusinessLedgerSetting::forBusiness($business);
            $pattern = $this->detect($locked, $settings);

            if ($pattern === null) {
                return null;
            }

            return match ($pattern['kind']) {
                'settlement' => $this->syncSettlement($user, $locked, $pattern),
                'transfer' => $this->syncTransfer($user, $locked, $pattern),
            };
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function detect(JournalEntry $entry, BusinessLedgerSetting $settings): ?array
    {
        $lines = $entry->lines
            ->filter(fn (JournalLine $line) => $line->deleted_at === null)
            ->values();

        if ($lines->count() < 2) {
            return null;
        }

        $settlement = $this->detectSettlement($lines, $settings, (int) $entry->business_id);

        if ($settlement !== null) {
            return $settlement;
        }

        return $this->detectTransfer($lines, $settings);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, JournalLine>  $lines
     * @return array<string, mixed>|null
     */
    protected function detectSettlement($lines, BusinessLedgerSetting $settings, int $businessId): ?array
    {
        if ($lines->count() !== 2) {
            return null;
        }

        $arId = $settings->default_accounts_receivable_id;
        $apId = $settings->default_accounts_payable_id;
        $cashId = $settings->default_cash_and_bank_id;

        $cashLine = $lines->first(function (JournalLine $line) use ($cashId): bool {
            if ($line->account_id === null) {
                return false;
            }

            $systemKey = $line->glAccount?->system_key;

            return $systemKey === 'cash_and_bank'
                || ($cashId !== null && (int) $line->gl_account_id === (int) $cashId);
        });

        $partyLine = $lines->first(function (JournalLine $line) use ($arId, $apId): bool {
            if ($line->party_id === null) {
                return false;
            }

            $systemKey = $line->glAccount?->system_key;

            return in_array($systemKey, ['accounts_receivable', 'accounts_payable'], true)
                || ($arId !== null && (int) $line->gl_account_id === (int) $arId)
                || ($apId !== null && (int) $line->gl_account_id === (int) $apId);
        });

        if ($cashLine === null || $partyLine === null || $cashLine->is($partyLine)) {
            return null;
        }

        $cashDebit = $this->normalize((string) $cashLine->debit);
        $cashCredit = $this->normalize((string) $cashLine->credit);
        $partyDebit = $this->normalize((string) $partyLine->debit);
        $partyCredit = $this->normalize((string) $partyLine->credit);

        $type = null;
        $amount = null;

        if (
            bccomp($cashDebit, '0', 4) === 1
            && bccomp($cashCredit, '0', 4) === 0
            && bccomp($partyCredit, '0', 4) === 1
            && bccomp($partyDebit, '0', 4) === 0
            && bccomp($cashDebit, $partyCredit, 4) === 0
        ) {
            $type = TransactionType::Income;
            $amount = $cashDebit;
        }

        if (
            $type === null
            && bccomp($cashCredit, '0', 4) === 1
            && bccomp($cashDebit, '0', 4) === 0
            && bccomp($partyDebit, '0', 4) === 1
            && bccomp($partyCredit, '0', 4) === 0
            && bccomp($cashCredit, $partyDebit, 4) === 0
        ) {
            $type = TransactionType::Expense;
            $amount = $cashCredit;
        }

        if ($type === null || $amount === null) {
            return null;
        }

        return [
            'kind' => 'settlement',
            'type' => $type,
            'account_id' => (int) $cashLine->account_id,
            'party_id' => (int) $partyLine->party_id,
            'amount' => $amount,
            'invoice_id' => $this->matchingOpenInvoiceId(
                $businessId,
                (int) $partyLine->party_id,
                $amount,
                $type,
            ),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, JournalLine>  $lines
     * @return array<string, mixed>|null
     */
    protected function detectTransfer($lines, BusinessLedgerSetting $settings): ?array
    {
        $cashId = $settings->default_cash_and_bank_id;

        $cashLines = $lines->filter(function (JournalLine $line) use ($cashId): bool {
            if ($line->account_id === null) {
                return false;
            }

            $systemKey = $line->glAccount?->system_key;

            return $systemKey === 'cash_and_bank'
                || ($cashId !== null && (int) $line->gl_account_id === (int) $cashId);
        })->values();

        if ($cashLines->count() !== 2) {
            return null;
        }

        $debitLine = $cashLines->first(function (JournalLine $line): bool {
            return bccomp($this->normalize((string) $line->debit), '0', 4) === 1
                && bccomp($this->normalize((string) $line->credit), '0', 4) === 0;
        });

        $creditLine = $cashLines->first(function (JournalLine $line): bool {
            return bccomp($this->normalize((string) $line->credit), '0', 4) === 1
                && bccomp($this->normalize((string) $line->debit), '0', 4) === 0;
        });

        if ($debitLine === null || $creditLine === null || $debitLine->is($creditLine)) {
            return null;
        }

        if ((int) $debitLine->account_id === (int) $creditLine->account_id) {
            return null;
        }

        $debitAmount = $this->normalize((string) $debitLine->debit);
        $creditAmount = $this->normalize((string) $creditLine->credit);
        $transferAmount = bccomp($debitAmount, $creditAmount, 4) <= 0 ? $debitAmount : $creditAmount;
        $feeAmount = $this->normalize(bcsub(
            bccomp($debitAmount, $creditAmount, 4) >= 0 ? $debitAmount : $creditAmount,
            $transferAmount,
            18,
        ));

        $otherLines = $lines->reject(
            fn (JournalLine $line) => $line->is($debitLine) || $line->is($creditLine),
        )->values();

        if (bccomp($feeAmount, '0', 4) === 1) {
            if ($otherLines->count() !== 1) {
                return null;
            }

            $feeLine = $otherLines->first();
            $feeDebit = $this->normalize((string) $feeLine->debit);
            $feeCredit = $this->normalize((string) $feeLine->credit);
            $feeLineAmount = bccomp($feeDebit, '0', 4) === 1 ? $feeDebit : $feeCredit;

            if (bccomp($feeLineAmount, $feeAmount, 4) !== 0) {
                return null;
            }
        } elseif ($otherLines->isNotEmpty()) {
            return null;
        } else {
            $feeLine = null;
        }

        return [
            'kind' => 'transfer',
            'source_account_id' => (int) $creditLine->account_id,
            'destination_account_id' => (int) $debitLine->account_id,
            'amount' => $transferAmount,
            'fee_amount' => bccomp($feeAmount, '0', 4) === 1 ? $feeAmount : null,
            'fee_gl_account_id' => $feeLine?->gl_account_id !== null ? (int) $feeLine->gl_account_id : null,
            'fee_category_id' => $feeLine?->category_id !== null ? (int) $feeLine->category_id : null,
            'fee_description' => $feeLine?->description,
        ];
    }

    /**
     * @param  array<string, mixed>  $pattern
     */
    protected function syncSettlement(User $user, JournalEntry $entry, array $pattern): Transaction
    {
        $transaction = $this->processTransaction->handle($user, [
            'type' => $pattern['type']->value,
            'account_id' => $pattern['account_id'],
            'party_id' => $pattern['party_id'],
            'invoice_id' => $pattern['invoice_id'],
            'transaction_date' => $entry->entry_date->toDateString(),
            'amount' => $pattern['amount'],
            'note' => $entry->description,
            'reference_number' => $entry->voucher_number,
            'skip_auto_post' => true,
            'meta' => [
                'converted_from_journal_entry_id' => $entry->id,
            ],
        ]);

        $this->linkJournal($entry, $transaction);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $pattern
     */
    protected function syncTransfer(User $user, JournalEntry $entry, array $pattern): Transaction
    {
        $transaction = $this->processTransaction->handle($user, [
            'type' => TransactionType::Transfer->value,
            'account_id' => $pattern['source_account_id'],
            'destination_account_id' => $pattern['destination_account_id'],
            'transaction_date' => $entry->entry_date->toDateString(),
            'amount' => $pattern['amount'],
            'note' => $entry->description,
            'reference_number' => $entry->voucher_number,
            'skip_auto_post' => true,
            'meta' => [
                'converted_from_journal_entry_id' => $entry->id,
            ],
        ]);

        if ($pattern['fee_amount'] !== null) {
            $categoryId = $pattern['fee_category_id']
                ?? $this->resolveCategoryForGl(
                    (int) $entry->business_id,
                    $pattern['fee_gl_account_id'],
                );

            $this->processTransaction->handle($user, [
                'type' => TransactionType::Expense->value,
                'account_id' => $pattern['source_account_id'],
                'category_id' => $categoryId,
                'transaction_date' => $entry->entry_date->toDateString(),
                'amount' => $pattern['fee_amount'],
                'note' => $pattern['fee_description'] ?: $entry->description,
                'reference_number' => $entry->voucher_number,
                'skip_auto_post' => true,
                'meta' => [
                    'converted_from_journal_entry_id' => $entry->id,
                    'fee_of_transaction_id' => $transaction->id,
                ],
            ]);
        }

        $this->linkJournal($entry, $transaction);

        return $transaction;
    }

    protected function linkJournal(JournalEntry $entry, Transaction $transaction): void
    {
        $entry->forceFill([
            'referenceable_type' => $transaction->getMorphClass(),
            'referenceable_id' => $transaction->id,
            'status' => JournalEntryStatus::Posted,
        ])->save();
    }

    protected function resolveCategoryForGl(int $businessId, ?int $glAccountId): ?int
    {
        if ($glAccountId === null) {
            return null;
        }

        $category = Category::query()
            ->availableToBusiness($businessId)
            ->where('gl_account_id', $glAccountId)
            ->whereDoesntHave('children')
            ->orderBy('id')
            ->first();

        return $category?->id;
    }

    protected function matchingOpenInvoiceId(int $businessId, int $partyId, string $amount, TransactionType $type): ?int
    {
        $invoiceType = $type === TransactionType::Income ? 'sale' : 'purchase';

        $invoice = Invoice::query()
            ->where('business_id', $businessId)
            ->where('party_id', $partyId)
            ->where('type', $invoiceType)
            ->whereNotNull('finalized_at')
            ->whereIn('payment_status', [
                InvoicePaymentStatus::Unpaid->value,
                InvoicePaymentStatus::PartiallyPaid->value,
            ])
            ->orderBy('id')
            ->get()
            ->first(function (Invoice $invoice) use ($amount): bool {
                $remaining = bcsub((string) $invoice->total_amount, (string) $invoice->paid_amount, 18);

                return bccomp($remaining, $amount, 4) === 0;
            });

        return $invoice?->id;
    }

    protected function normalize(string $value): string
    {
        $normalized = preg_replace('/[^0-9.]/', '', $value) ?? '0';

        return $normalized === '' ? '0' : $normalized;
    }
}
