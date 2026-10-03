<?php

namespace Database\Factories;

use App\Enums\AccountSubType;
use App\Enums\AccountType;
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
            'type' => AccountType::Asset,
            'sub_type' => AccountSubType::Cash,
            'bank_name' => null,
            'account_number' => null,
            'card_number' => null,
            'iban' => null,
            'note' => null,
            'opening_balance' => '0',
            'current_balance' => '0',
            'is_active' => true,
        ];
    }

    public function bank(): static
    {
        return $this->state(fn (): array => [
            'sub_type' => AccountSubType::Bank,
            'bank_name' => 'Bank Melli',
            'account_number' => '0100324200001',
            'card_number' => '6037997599422129',
            'iban' => 'IR062960000000100324200001',
        ]);
    }
}
