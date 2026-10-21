<?php

namespace App\Actions\Transactions\Concerns;

use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\LoanStatus;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;

trait AppliesTransactionEffects
{
    protected function applyBalanceEffects(
        TransactionType $type,
        Account $account,
        ?Account $destinationAccount,
        ?Party $party,
        string $amount,
    ): void {
        match ($type) {
            TransactionType::Income => $this->applyIncome($account, $party, $amount),
            TransactionType::Expense => $this->applyExpense($account, $party, $amount),
            TransactionType::Transfer => $this->applyTransfer($account, $destinationAccount, $amount),
        };
    }

    protected function reverseBalanceEffects(
        TransactionType $type,
        Account $account,
        ?Account $destinationAccount,
        ?Party $party,
        string $amount,
    ): void {
        match ($type) {
            TransactionType::Income => $this->applyExpense($account, $party, $amount),
            TransactionType::Expense => $this->applyIncome($account, $party, $amount),
            TransactionType::Transfer => $this->applyTransfer($destinationAccount, $account, $amount),
        };
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

    protected function applyTransfer(?Account $source, ?Account $destination, string $amount): void
    {
        if ($source === null || $destination === null) {
            return;
        }

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
            : (bccomp($paidAmount, '0', 18) === 1
                ? InvoicePaymentStatus::PartiallyPaid
                : InvoicePaymentStatus::Unpaid);

        $invoice->forceFill([
            'paid_amount' => $paidAmount,
            'payment_status' => $paymentStatus,
        ])->save();
    }

    protected function reverseInvoicePayment(Invoice $invoice, string $amount): void
    {
        $paidAmount = bcsub((string) $invoice->paid_amount, $amount, 18);

        if (bccomp($paidAmount, '0', 18) === -1) {
            $paidAmount = '0';
        }

        $totalAmount = (string) $invoice->total_amount;

        $paymentStatus = bccomp($paidAmount, '0', 18) !== 1
            ? InvoicePaymentStatus::Unpaid
            : (bccomp($paidAmount, $totalAmount, 18) >= 0
                ? InvoicePaymentStatus::Paid
                : InvoicePaymentStatus::PartiallyPaid);

        $invoice->forceFill([
            'paid_amount' => $paidAmount,
            'payment_status' => $paymentStatus,
        ])->save();
    }

    protected function applyLoanPayment(Loan $loan, string $amount): void
    {
        $paidAmount = bcadd((string) $loan->paid_amount, $amount, 18);
        $status = bccomp($paidAmount, (string) $loan->total_amount, 18) >= 0
            ? LoanStatus::Completed
            : LoanStatus::Active;

        if ($loan->status === LoanStatus::Defaulted) {
            $status = LoanStatus::Defaulted;
        }

        $loan->forceFill([
            'paid_amount' => $paidAmount,
            'status' => $status,
        ])->save();
    }

    protected function reverseLoanPayment(Loan $loan, string $amount): void
    {
        $paidAmount = bcsub((string) $loan->paid_amount, $amount, 18);

        if (bccomp($paidAmount, '0', 18) === -1) {
            $paidAmount = '0';
        }

        $status = $loan->status === LoanStatus::Defaulted
            ? LoanStatus::Defaulted
            : (bccomp($paidAmount, (string) $loan->total_amount, 18) >= 0
                ? LoanStatus::Completed
                : LoanStatus::Active);

        $loan->forceFill([
            'paid_amount' => $paidAmount,
            'status' => $status,
        ])->save();
    }

    protected function transactionSkipsPartyBalance(Transaction $transaction): bool
    {
        if ($transaction->cheque_id !== null) {
            return true;
        }

        $meta = $transaction->meta ?? [];

        return (bool) ($meta['skip_party_balance'] ?? false);
    }

    protected function transactionAppliesLoanPayment(Transaction $transaction): bool
    {
        $meta = $transaction->meta ?? [];

        return (bool) ($meta['loan_payment'] ?? false);
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
