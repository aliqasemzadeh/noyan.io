<?php

namespace Tests\Unit\Accounting;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Transaction;
use App\Support\Accounting\AccountLedgerBalance;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountLedgerBalanceTest extends TestCase
{
    #[Test]
    public function it_computes_signed_effects_for_an_account(): void
    {
        $income = new Transaction([
            'account_id' => 1,
            'type' => TransactionType::Income,
            'amount' => '200',
        ]);

        $expense = new Transaction([
            'account_id' => 1,
            'type' => TransactionType::Expense,
            'amount' => '50',
        ]);

        $transferOut = new Transaction([
            'account_id' => 1,
            'destination_account_id' => 2,
            'type' => TransactionType::Transfer,
            'amount' => '10',
        ]);

        $transferIn = new Transaction([
            'account_id' => 9,
            'destination_account_id' => 1,
            'type' => TransactionType::Transfer,
            'amount' => '25',
        ]);

        $this->assertSame('200', AccountLedgerBalance::effectForAccount($income, 1));
        $this->assertSame('0', AccountLedgerBalance::effectForAccount($income, 99));
        $this->assertSame('-50', AccountLedgerBalance::effectForAccount($expense, 1));
        $this->assertSame('-10', AccountLedgerBalance::effectForAccount($transferOut, 1));
        $this->assertSame('10', AccountLedgerBalance::effectForAccount($transferOut, 2));
        $this->assertSame('25', AccountLedgerBalance::effectForAccount($transferIn, 1));
        $this->assertSame('-25', AccountLedgerBalance::effectForAccount($transferIn, 9));
    }

    #[Test]
    public function it_reads_stored_balances_for_source_and_destination(): void
    {
        $income = new Transaction([
            'account_id' => 1,
            'type' => TransactionType::Income,
            'amount' => '200',
            'balance_after' => '1200',
        ]);

        $transfer = new Transaction([
            'account_id' => 1,
            'destination_account_id' => 2,
            'type' => TransactionType::Transfer,
            'amount' => '10',
            'balance_after' => '1190',
            'destination_balance_after' => '510',
        ]);

        $this->assertSame('1200', AccountLedgerBalance::storedForAccount($income, 1));
        $this->assertNull(AccountLedgerBalance::storedForAccount($income, 2));
        $this->assertSame('1190', AccountLedgerBalance::storedForAccount($transfer, 1));
        $this->assertSame('510', AccountLedgerBalance::storedForAccount($transfer, 2));
    }
}
