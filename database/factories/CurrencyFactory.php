<?php

namespace Database\Factories;

use App\Enums\CurrencyType;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => fake()->words(2, true).' Currency',
            'symbol' => $code[0],
            'type' => CurrencyType::Fiat,
            'is_system' => true,
            'business_id' => null,
            'decimal_places' => fake()->numberBetween(0, 8),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function custom(int $businessId): static
    {
        return $this->state(fn (): array => [
            'type' => CurrencyType::Custom,
            'is_system' => false,
            'business_id' => $businessId,
        ]);
    }
}
