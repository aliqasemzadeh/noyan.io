<?php

namespace App\Actions\Transactions;

use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProcessTransactionAction
{
    /**
     * @param  array{
     *     type: string|TransactionType,
     *     account_id: int,
     *     destination_account_id?: int|null,
     *     party_id?: int|null,
     *     invoice_id?: int|null,
     *     category_id?: int|null,
     *     transaction_date: string,
     *     currency?: string|null,
     *     exchange_rate?: string|null,
     *     amount: string|float|int,
     *     base_amount?: string|float|int|null,
     *     reference_number?: string|null,
     *     note?: string|null,
     *     meta?: array<string, mixed>|null
     * }  $data
     */
    public function handle(User $user, array $data): Transaction
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to process a transaction.');
        }

        return DB::transaction(function () use ($user, $data, $businessId): Transaction {
            $type = $data['type'] instanceof TransactionType
                ? $data['type']
                : TransactionType::from((string) $data['type']);

            $amount = $this->normalizeDecimal((string) $data['amount']);

            if (bccomp($amount, '0', 18) !== 1) {
                throw ValidationException::withMessages([
                    'amount' => [__('general.transaction_amount_must_be_positive')],
                ]);
            }

            $account = Account::query()
                ->where('business_id', $businessId)
                ->whereKey($data['account_id'])
                ->lockForUpdate()
                ->with('currency')
                ->first();

            if ($account === null) {
                throw ValidationException::withMessages([
                    'account_id' => [__('general.transaction_account_invalid')],
                ]);
            }

            $destinationAccount = null;
            $destinationAccountId = $data['destination_account_id'] ?? null;

            if ($type === TransactionType::Transfer) {
                if ($destinationAccountId === null) {
                    throw ValidationException::withMessages([
                        'destination_account_id' => [__('general.transaction_destination_required')],
                    ]);
                }

                if ((int) $destinationAccountId === (int) $account->id) {
                    throw ValidationException::withMessages([
                        'destination_account_id' => [__('general.transaction_destination_must_differ')],
                    ]);
                }

                $destinationAccount = Account::query()
                    ->where('business_id', $businessId)
                    ->whereKey($destinationAccountId)
                    ->lockForUpdate()
                    ->first();

                if ($destinationAccount === null) {
                    throw ValidationException::withMessages([
                        'destination_account_id' => [__('general.transaction_destination_invalid')],
                    ]);
                }
            } else {
                $destinationAccountId = null;
            }

            $party = null;
            $partyId = $data['party_id'] ?? null;

            if ($partyId !== null && $type !== TransactionType::Transfer) {
                $party = Party::query()
                    ->where('business_id', $businessId)
                    ->whereKey($partyId)
                    ->lockForUpdate()
                    ->first();

                if ($party === null) {
                    throw ValidationException::withMessages([
                        'party_id' => [__('general.transaction_party_invalid')],
                    ]);
                }
            } else {
                $partyId = null;
            }

            $invoice = null;
            $invoiceId = $data['invoice_id'] ?? null;

            if ($invoiceId !== null && $type !== TransactionType::Transfer) {
                $invoice = Invoice::query()
                    ->where('business_id', $businessId)
                    ->whereKey($invoiceId)
                    ->lockForUpdate()
                    ->first();

                if ($invoice === null) {
                    throw ValidationException::withMessages([
                        'invoice_id' => [__('general.transaction_invoice_invalid')],
                    ]);
                }
            } else {
                $invoiceId = null;
            }

            $categoryId = $data['category_id'] ?? null;

            if ($categoryId !== null && $type !== TransactionType::Transfer) {
                $categoryType = CategoryType::tryFrom($type->value);

                if ($categoryType === null) {
                    throw ValidationException::withMessages([
                        'category_id' => [__('general.transaction_category_invalid')],
                    ]);
                }

                $category = Category::query()
                    ->availableToBusiness($businessId)
                    ->ofType($categoryType)
                    ->whereKey($categoryId)
                    ->first();

                if ($category === null) {
                    throw ValidationException::withMessages([
                        'category_id' => [__('general.transaction_category_invalid')],
                    ]);
                }

                if (! $category->isLeaf()) {
                    throw ValidationException::withMessages([
                        'category_id' => [__('general.transaction_category_must_be_leaf')],
                    ]);
                }
            } else {
                $categoryId = null;
            }

            $currency = $data['currency'] ?? $account->currency?->code;

            if ($currency === null || $currency === '') {
                throw ValidationException::withMessages([
                    'currency' => [__('general.transaction_currency_required')],
                ]);
            }

            $exchangeRate = $this->normalizeDecimal((string) ($data['exchange_rate'] ?? '1'));

            if (bccomp($exchangeRate, '0', 18) !== 1) {
                throw ValidationException::withMessages([
                    'exchange_rate' => [__('general.transaction_exchange_rate_must_be_positive')],
                ]);
            }

            $baseAmount = array_key_exists('base_amount', $data) && $data['base_amount'] !== null
                ? $this->normalizeDecimal((string) $data['base_amount'])
                : $this->normalizeDecimal(bcmul($amount, $exchangeRate, 18));

            $transaction = Transaction::query()->create([
                'business_id' => $businessId,
                'created_by' => $user->id,
                'account_id' => $account->id,
                'destination_account_id' => $destinationAccountId,
                'party_id' => $partyId,
                'invoice_id' => $invoiceId,
                'category_id' => $categoryId,
                'type' => $type,
                'transaction_date' => $data['transaction_date'],
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'amount' => $amount,
                'base_amount' => $baseAmount,
                'reference_number' => $data['reference_number'] ?? null,
                'note' => $data['note'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);

            match ($type) {
                TransactionType::Income => $this->applyIncome($account, $party, $amount),
                TransactionType::Expense => $this->applyExpense($account, $party, $amount),
                TransactionType::Transfer => $this->applyTransfer($account, $destinationAccount, $amount),
            };

            if ($invoice !== null) {
                $this->applyInvoicePayment($invoice, $amount);
            }

            return $transaction->fresh(['account', 'destinationAccount', 'party', 'invoice', 'category']);
        });
    }

    protected function applyIncome(Account $account, ?Party $party, string $amount): void
    {
        $account->forceFill([
            'current_balance' => bcadd((string) $account->current_balance, $amount, 18),
        ])->save();

        if ($party !== null) {
            $party->forceFill([
                'balance' => bcsub((string) $party->balance, $amount, 18),
            ])->save();
        }
    }

    protected function applyExpense(Account $account, ?Party $party, string $amount): void
    {
        $account->forceFill([
            'current_balance' => bcsub((string) $account->current_balance, $amount, 18),
        ])->save();

        if ($party !== null) {
            $party->forceFill([
                'balance' => bcadd((string) $party->balance, $amount, 18),
            ])->save();
        }
    }

    protected function applyTransfer(Account $source, Account $destination, string $amount): void
    {
        $source->forceFill([
            'current_balance' => bcsub((string) $source->current_balance, $amount, 18),
        ])->save();

        $destination->forceFill([
            'current_balance' => bcadd((string) $destination->current_balance, $amount, 18),
        ])->save();
    }

    protected function applyInvoicePayment(Invoice $invoice, string $amount): void
    {
        $paidAmount = bcadd((string) $invoice->paid_amount, $amount, 18);
        $totalAmount = (string) $invoice->total_amount;

        $paymentStatus = bccomp($paidAmount, $totalAmount, 18) >= 0
            ? InvoicePaymentStatus::Paid
            : InvoicePaymentStatus::PartiallyPaid;

        $invoice->forceFill([
            'paid_amount' => $paidAmount,
            'payment_status' => $paymentStatus,
        ])->save();
    }

    protected function normalizeDecimal(string $amount): string
    {
        $amount = trim($amount);

        if ($amount === '' || ! is_numeric($amount)) {
            return '0';
        }

        if (! str_contains($amount, '.')) {
            return $amount;
        }

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }
}
