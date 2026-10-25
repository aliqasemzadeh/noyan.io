<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Concerns\AppliesTransactionEffects;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeleteTransactionAction
{
    use AppliesTransactionEffects;

    public function __construct(
        protected AutoPostingEngine $autoPostingEngine,
    ) {}

    public function handle(User $user, Transaction $transaction): void
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to delete a transaction.');
        }

        DB::transaction(function () use ($transaction, $businessId): void {
            $locked = Transaction::query()
                ->where('business_id', $businessId)
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'transaction' => [__('general.transaction_not_found')],
                ]);
            }

            $amount = $this->normalizeDecimal((string) $locked->amount);
            $skipParty = $this->transactionSkipsPartyBalance($locked);
            $appliesLoanPayment = $this->transactionAppliesLoanPayment($locked);

            $account = Account::query()
                ->where('business_id', $businessId)
                ->whereKey($locked->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            $destination = null;

            if ($locked->destination_account_id !== null) {
                $destination = Account::query()
                    ->where('business_id', $businessId)
                    ->whereKey($locked->destination_account_id)
                    ->lockForUpdate()
                    ->first();
            }

            $party = null;

            if ($locked->party_id !== null && ! $skipParty) {
                $party = Party::query()
                    ->where('business_id', $businessId)
                    ->whereKey($locked->party_id)
                    ->lockForUpdate()
                    ->first();
            }

            $invoice = null;

            if ($locked->invoice_id !== null) {
                $invoice = Invoice::query()
                    ->where('business_id', $businessId)
                    ->whereKey($locked->invoice_id)
                    ->lockForUpdate()
                    ->first();
            }

            $loan = null;

            if ($locked->loan_id !== null && $appliesLoanPayment) {
                $loan = Loan::query()
                    ->where('business_id', $businessId)
                    ->whereKey($locked->loan_id)
                    ->lockForUpdate()
                    ->first();
            }

            $this->reverseBalanceEffects($locked->type, $account, $destination, $party, $amount);

            if ($invoice !== null) {
                $this->reverseInvoicePayment($invoice, $amount);
            }

            if ($loan !== null) {
                $this->reverseLoanPayment($loan, $amount);
            }

            $this->autoPostingEngine->voidTransaction($locked);

            $locked->delete();
        });
    }
}
