<?php

namespace Database\Factories;

use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessInvitation>
 */
class BusinessInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'mobile' => fake()->unique()->numerify('09#########'),
            'roles' => [BusinessRole::Viewer->value],
            'permissions' => [
                BusinessRole::Viewer->value => BusinessRole::Viewer->defaultPermissions(),
            ],
            'token' => Str::random(64),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'accepted_user_id' => null,
        ];
    }

    public function accepted(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'accepted_at' => now(),
            'accepted_user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, list<string>>  $permissions
     */
    public function withAccess(array $roles, array $permissions): static
    {
        return $this->state(fn (): array => [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }
}
