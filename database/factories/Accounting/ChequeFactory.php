<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cheque>
 */
class ChequeFactory extends Factory
{
    protected $model = Cheque::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'party_id' => Party::factory(),
            'invoice_id' => null,
            'account_id' => Account::factory(),
            'created_by' => User::factory(),
            'type' => ChequeType::Received,
            'status' => ChequeStatus::Registered,
            'cheque_number' => (string) fake()->numerify('########'),
            'sayad_number' => null,
            'bank_name' => fake()->randomElement(['ملی', 'ملت', 'صادرات', 'پارسیان']),
            'bank_branch' => null,
            'amount' => '1000000',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'cleared_at' => null,
            'status_changed_at' => now(),
            'party_reversed_at' => null,
            'note' => null,
        ];
    }

    public function received(): static
    {
        return $this->state(fn (): array => [
            'type' => ChequeType::Received,
        ]);
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'type' => ChequeType::Issued,
        ]);
    }

    public function registered(): static
    {
        return $this->state(fn (): array => [
            'status' => ChequeStatus::Registered,
            'cleared_at' => null,
            'party_reversed_at' => null,
        ]);
    }

    public function deposited(): static
    {
        return $this->state(fn (): array => [
            'status' => ChequeStatus::Deposited,
            'cleared_at' => null,
            'party_reversed_at' => null,
        ]);
    }

    public function cleared(): static
    {
        return $this->state(fn (): array => [
            'status' => ChequeStatus::Cleared,
            'cleared_at' => now()->toDateString(),
            'party_reversed_at' => null,
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn (): array => [
            'status' => ChequeStatus::Bounced,
            'party_reversed_at' => now(),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (): array => [
            'status' => ChequeStatus::Returned,
            'party_reversed_at' => now(),
        ]);
    }

    public function dueIn(int $days): static
    {
        return $this->state(fn (): array => [
            'due_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => ChequeStatus::Registered,
            'due_date' => now()->subDay()->toDateString(),
        ]);
    }
}
