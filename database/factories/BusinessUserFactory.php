<?php

namespace Database\Factories;

use App\Enums\Business\BusinessRole;
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
            'permissions' => BusinessRole::Viewer->defaultPermissions(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => BusinessRole::Owner,
            'permissions' => BusinessRole::Owner->defaultPermissions(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => BusinessRole::Admin,
            'permissions' => BusinessRole::Admin->defaultPermissions(),
        ]);
    }

    public function seller(): static
    {
        return $this->state(fn (): array => [
            'role' => BusinessRole::Seller,
            'permissions' => BusinessRole::Seller->defaultPermissions(),
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn (): array => [
            'permissions' => $permissions,
        ]);
    }
}
