<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Concerns\AppliesTransactionEffects;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Category;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProcessTransactionAction
{
    use AppliesTransactionEffects;

    public function __construct(
        protected AutoPostingEngine $autoPostingEngine,
    ) {}

    /**
     * @param  array{
     *     type: string|TransactionType,
     *     account_id: int,
     *     destination_account_id?: int|null,
     *     party_id?: int|null,
     *     invoice_id?: int|null,
     *     category_id?: int|null,
     *     loan_id?: int|null,
     *     cheque_id?: int|null,
     *     skip_party_balance?: bool,
     *     apply_loan_payment?: bool,
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

            $loan = null;
            $loanId = $data['loan_id'] ?? null;

            if ($loanId !== null && $type !== TransactionType::Transfer) {
                $loan = Loan::query()
                    ->where('business_id', $businessId)
                    ->whereKey($loanId)
                    ->lockForUpdate()
                    ->first();

                if ($loan === null) {
                    throw ValidationException::withMessages([
                        'loan_id' => [__('general.transaction_loan_invalid')],
                    ]);
                }
            } else {
                $loanId = null;
            }

            $chequeId = $data['cheque_id'] ?? null;

            if ($chequeId !== null && $type !== TransactionType::Transfer) {
                $cheque = Cheque::query()
                    ->where('business_id', $businessId)
                    ->whereKey($chequeId)
                    ->first();

                if ($cheque === null) {
                    throw ValidationException::withMessages([
                        'cheque_id' => [__('general.transaction_cheque_invalid')],
                    ]);
                }
            } else {
                $chequeId = null;
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

            $skipPartyBalance = (bool) ($data['skip_party_balance'] ?? false) || $chequeId !== null;
            $applyLoanPayment = (bool) ($data['apply_loan_payment'] ?? false);

            $meta = $data['meta'] ?? [];
            $meta = is_array($meta) ? $meta : [];

            if ($skipPartyBalance) {
                $meta['skip_party_balance'] = true;
            }

            if ($applyLoanPayment) {
                $meta['loan_payment'] = true;
            }

            if ($applyLoanPayment && $loan !== null) {
                $remaining = $loan->remainingAmount();

                if (bccomp($amount, $remaining, 18) === 1) {
                    throw ValidationException::withMessages([
                        'amount' => [__('general.loan_payment_exceeds_remaining')],
                    ]);
                }
            }

            $transaction = Transaction::query()->create([
                'business_id' => $businessId,
                'created_by' => $user->id,
                'account_id' => $account->id,
                'destination_account_id' => $destinationAccountId,
                'party_id' => $partyId,
                'invoice_id' => $invoiceId,
                'category_id' => $categoryId,
                'loan_id' => $loanId,
                'cheque_id' => $chequeId,
                'type' => $type,
                'transaction_date' => $data['transaction_date'],
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'amount' => $amount,
                'base_amount' => $baseAmount,
                'reference_number' => $data['reference_number'] ?? null,
                'note' => $data['note'] ?? null,
                'meta' => $meta === [] ? null : $meta,
            ]);

            $balanceParty = $skipPartyBalance ? null : $party;

            $this->applyBalanceEffects($type, $account, $destinationAccount, $balanceParty, $amount);

            if ($invoice !== null) {
                $this->applyInvoicePayment($invoice, $amount);
            }

            if ($applyLoanPayment && $loan !== null) {
                $this->applyLoanPayment($loan, $amount);
            }

            $fresh = $transaction->fresh(['account', 'destinationAccount', 'party', 'invoice', 'category', 'loan', 'cheque']);

            try {
                $this->autoPostingEngine->postTransaction($fresh ?? $transaction, $user);
            } catch (Throwable $exception) {
                Log::error('Treasury auto-post failed.', [
                    'transaction_id' => $transaction->id,
                    'message' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            return $fresh ?? $transaction;
        });
    }
}
