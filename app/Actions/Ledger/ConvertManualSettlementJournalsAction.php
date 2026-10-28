<?php

namespace App\Actions\Ledger;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConvertManualSettlementJournalsAction
{
    public function __construct(
        protected ProcessTransactionAction $processTransaction,
        protected AutoPostingEngine $autoPostingEngine,
    ) {}

    /**
     * Convert unlinked cash↔AR/AP settlement journals into treasury transactions.
     *
     * @return array{converted: int, skipped: int, failed: int}
     */
    public function handle(Business $business, User $user): array
    {
        $stats = ['converted' => 0, 'skipped' => 0, 'failed' => 0];
        $settings = BusinessLedgerSetting::forBusiness($business);

        $previousBusinessId = $user->current_business_id;
        $user->forceFill(['current_business_id' => $business->id])->saveQuietly();

        try {
            JournalEntry::query()
                ->where('business_id', $business->id)
                ->whereNull('referenceable_type')
                ->whereNull('referenceable_id')
                ->where('status', JournalEntryStatus::Posted)
                ->where('voucher_number', 'not like', '%__del_%')
                ->with(['lines.glAccount'])
                ->orderBy('id')
                ->each(function (JournalEntry $entry) use ($business, $user, $settings, &$stats): void {
                    $settlement = $this->detectSettlement($entry, $settings);

                    if ($settlement === null) {
                        $stats['skipped']++;

                        return;
                    }

                    try {
                        $this->convertOne($business, $user, $entry, $settlement);
                        $stats['converted']++;
                    } catch (Throwable) {
                        $stats['failed']++;
                    }
                });
        } finally {
            $user->forceFill(['current_business_id' => $previousBusinessId])->saveQuietly();
        }

        return $stats;
    }

    /**
     * @param  array{
     *     type: TransactionType,
     *     account_id: int,
     *     party_id: int,
     *     amount: string,
     *     invoice_id: int|null
     * }  $settlement
     */
    protected function convertOne(Business $business, User $user, JournalEntry $entry, array $settlement): void
    {
        DB::transaction(function () use ($business, $user, $entry, $settlement): void {
            $locked = JournalEntry::query()
                ->where('business_id', $business->id)
                ->whereKey($entry->id)
                ->whereNull('referenceable_type')
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'entry' => [__('general.journal_entry_not_found')],
                ]);
            }

            $locked->lines()->each(fn (JournalLine $line) => $line->delete());
            $locked->delete();

            $transaction = $this->processTransaction->handle($user, [
                'type' => $settlement['type']->value,
                'account_id' => $settlement['account_id'],
                'party_id' => $settlement['party_id'],
                'invoice_id' => $settlement['invoice_id'],
                'transaction_date' => $entry->entry_date->toDateString(),
                'amount' => $settlement['amount'],
                'note' => $entry->description,
                'reference_number' => $entry->voucher_number,
                'meta' => [
                    'converted_from_journal_entry_id' => $entry->id,
                ],
            ]);

            $hasLinked = JournalEntry::query()
                ->where('business_id', $business->id)
                ->where('referenceable_type', $transaction->getMorphClass())
                ->where('referenceable_id', $transaction->id)
                ->where('voucher_number', 'not like', '%__del_%')
                ->exists();

            if (! $hasLinked) {
                $this->autoPostingEngine->postTransaction($transaction, $user, forcePost: true);
            }
        });
    }

    /**
     * @return array{
     *     type: TransactionType,
     *     account_id: int,
     *     party_id: int,
     *     amount: string,
     *     invoice_id: int|null
     * }|null
     */
    protected function detectSettlement(JournalEntry $entry, BusinessLedgerSetting $settings): ?array
    {
        $lines = $entry->lines
            ->filter(fn (JournalLine $line) => $line->deleted_at === null)
            ->values();

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

        // Receipt: Debit cash, Credit AR
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

        // Payment: Debit AP, Credit cash
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
            'type' => $type,
            'account_id' => (int) $cashLine->account_id,
            'party_id' => (int) $partyLine->party_id,
            'amount' => $amount,
            'invoice_id' => $this->matchingOpenInvoiceId(
                (int) $entry->business_id,
                (int) $partyLine->party_id,
                $amount,
                $type,
            ),
        ];
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
