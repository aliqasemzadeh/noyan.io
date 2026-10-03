<?php

namespace Tests\Unit;

use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Enums\Accounting\TransactionType;
use PHPUnit\Framework\TestCase;

class ChequeEnumTest extends TestCase
{
    public function test_received_columns_include_deposited(): void
    {
        $statuses = ChequeStatus::forType(ChequeType::Received);

        $this->assertSame([
            ChequeStatus::Registered,
            ChequeStatus::Deposited,
            ChequeStatus::Cleared,
            ChequeStatus::Bounced,
            ChequeStatus::Returned,
        ], $statuses);
    }

    public function test_issued_columns_exclude_deposited(): void
    {
        $statuses = ChequeStatus::forType(ChequeType::Issued);

        $this->assertSame([
            ChequeStatus::Registered,
            ChequeStatus::Cleared,
            ChequeStatus::Bounced,
            ChequeStatus::Returned,
        ], $statuses);
    }

    public function test_received_transition_matrix(): void
    {
        $this->assertTrue(ChequeStatus::Registered->canTransitionTo(ChequeStatus::Deposited, ChequeType::Received));
        $this->assertTrue(ChequeStatus::Registered->canTransitionTo(ChequeStatus::Cleared, ChequeType::Received));
        $this->assertTrue(ChequeStatus::Deposited->canTransitionTo(ChequeStatus::Bounced, ChequeType::Received));
        $this->assertFalse(ChequeStatus::Cleared->canTransitionTo(ChequeStatus::Bounced, ChequeType::Received));
        $this->assertFalse(ChequeStatus::Registered->canTransitionTo(ChequeStatus::Deposited, ChequeType::Issued));
    }

    public function test_type_helpers(): void
    {
        $this->assertSame(TransactionType::Income, ChequeType::Received->transactionType());
        $this->assertSame(TransactionType::Expense, ChequeType::Issued->transactionType());
        $this->assertSame(0, bccomp(ChequeType::Received->applyRegisterPartyBalance('1000', '100'), '900', 18));
        $this->assertSame(0, bccomp(ChequeType::Issued->applyRegisterPartyBalance('1000', '100'), '1100', 18));
        $this->assertSame(0, bccomp(ChequeType::Received->applyReversePartyBalance('900', '100'), '1000', 18));
        $this->assertSame(0, bccomp(ChequeType::Issued->applyReversePartyBalance('1100', '100'), '1000', 18));
    }
}
