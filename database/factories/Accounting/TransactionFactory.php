<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'created_by' => User::factory(),
            'account_id' => Account::factory(),
            'destination_account_id' => null,
            'party_id' => null,
            'invoice_id' => null,
            'category_id' => null,
            'loan_id' => null,
            'type' => TransactionType::Income,
            'transaction_date' => now()->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'amount' => '1000',
            'base_amount' => '1000',
            'reference_number' => null,
            'note' => null,
            'meta' => null,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => TransactionType::Expense,
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (): array => [
            'type' => TransactionType::Transfer,
        ]);
    }
}
