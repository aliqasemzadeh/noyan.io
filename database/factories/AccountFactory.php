<?php

namespace Database\Factories;

use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'currency_id' => Currency::factory(),
            'name' => fake()->words(2, true),
            'account_number' => null,
            'note' => null,
            'opening_balance' => '0',
            'is_active' => true,
        ];
    }
}
