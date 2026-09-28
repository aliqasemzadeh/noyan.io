<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Enums\Currency;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'default_currency' => Currency::Irr,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Business $business): void {
            BusinessUser::query()->firstOrCreate(
                [
                    'business_id' => $business->id,
                    'user_id' => $business->owner_id,
                ],
                [
                    'role' => BusinessRole::Owner,
                ],
            );

            $owner = $business->owner;

            if ($owner !== null && $owner->current_business_id === null) {
                $owner->forceFill([
                    'current_business_id' => $business->id,
                ])->save();
            }
        });
    }
}
