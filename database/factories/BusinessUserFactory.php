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
            'roles' => [BusinessRole::Viewer->value],
            'permissions' => [
                BusinessRole::Viewer->value => BusinessRole::Viewer->defaultPermissions(),
            ],
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'roles' => [BusinessRole::Owner->value],
            'permissions' => [
                BusinessRole::Owner->value => BusinessRole::Owner->defaultPermissions(),
            ],
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'roles' => [BusinessRole::Admin->value],
            'permissions' => [
                BusinessRole::Admin->value => BusinessRole::Admin->defaultPermissions(),
            ],
        ]);
    }

    public function seller(): static
    {
        return $this->state(fn (): array => [
            'roles' => [BusinessRole::Seller->value],
            'permissions' => [
                BusinessRole::Seller->value => BusinessRole::Seller->defaultPermissions(),
            ],
        ]);
    }

    public function accountant(): static
    {
        return $this->state(fn (): array => [
            'roles' => [BusinessRole::Accountant->value],
            'permissions' => [
                BusinessRole::Accountant->value => BusinessRole::Accountant->defaultPermissions(),
            ],
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function withPermissions(array $permissions, BusinessRole $role = BusinessRole::Seller): static
    {
        return $this->state(fn (): array => [
            'roles' => [$role->value],
            'permissions' => [
                $role->value => $permissions,
            ],
        ]);
    }
}
