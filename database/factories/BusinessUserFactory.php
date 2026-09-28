<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessUser>
 */
class BusinessUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'role' => BusinessRole::Viewer,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => BusinessRole::Owner,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => BusinessRole::Admin,
        ]);
    }
}
