<?php

namespace Database\Factories;

use App\Models\Accounting\Party;
use App\Models\Accounting\PartyContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyContact>
 */
class PartyContactFactory extends Factory
{
    protected $model = PartyContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'name' => fake()->name(),
            'position' => fake()->optional()->jobTitle(),
            'phone' => null,
            'mobile' => '0912'.fake()->numerify('#######'),
            'email' => fake()->optional()->safeEmail(),
            'note' => null,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => [
            'is_primary' => true,
        ]);
    }
}
