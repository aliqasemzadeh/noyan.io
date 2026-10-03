<?php

namespace Database\Factories;

use App\Enums\PartyType;
use App\Models\Accounting\Party;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => null,
            'type' => PartyType::Individual,
            'name' => fake()->name(),
            'legal_name' => null,
            'economic_code' => null,
            'national_id' => null,
            'phone' => null,
            'mobile' => '0912'.fake()->numerify('#######'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'postal_code' => null,
            'balance' => '0',
            'credit_limit' => '0',
            'is_customer' => true,
            'is_supplier' => false,
            'is_active' => true,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (): array => [
            'type' => PartyType::Company,
            'name' => fake()->company(),
            'legal_name' => fake()->company().' Ltd',
            'is_customer' => true,
            'is_supplier' => true,
        ]);
    }

    public function supplier(): static
    {
        return $this->state(fn (): array => [
            'is_customer' => false,
            'is_supplier' => true,
        ]);
    }
}
