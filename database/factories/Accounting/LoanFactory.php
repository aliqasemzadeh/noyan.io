<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\LoanStatus;
use App\Enums\Accounting\LoanType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $principal = '1000000';
        $interest = '0';

        return [
            'business_id' => Business::factory(),
            'party_id' => Party::factory(),
            'account_id' => Account::factory(),
            'type' => LoanType::Received,
            'title' => fake()->sentence(3),
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'total_amount' => bcadd($principal, $interest, 18),
            'paid_amount' => '0',
            'installments_count' => null,
            'installment_amount' => null,
            'issue_date' => now()->toDateString(),
            'first_installment_date' => null,
            'status' => LoanStatus::Active,
            'description' => null,
        ];
    }

    public function given(): static
    {
        return $this->state(fn (): array => [
            'type' => LoanType::Given,
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (): array => [
            'type' => LoanType::Received,
        ]);
    }
}
