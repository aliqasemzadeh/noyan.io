<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\BusinessCurrency;
use App\Models\Business;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCurrency>
 */
class BusinessCurrencyFactory extends Factory
{
    protected $model = BusinessCurrency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'currency_id' => Currency::factory(),
            'is_base' => false,
            'exchange_rate_to_base' => 1,
        ];
    }

    public function base(): static
    {
        return $this->state(fn (): array => [
            'is_base' => true,
            'exchange_rate_to_base' => 1,
        ]);
    }
}
