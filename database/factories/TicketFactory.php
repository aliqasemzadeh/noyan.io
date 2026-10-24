<?php

namespace Database\Factories;

use App\Enums\Support\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_id' => null,
            'ticket_category_id' => TicketCategory::factory(),
            'title' => fake()->sentence(4),
            'status' => TicketStatus::New,
            'assigned_user_id' => null,
            'ip' => fake()->ipv4(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Closed,
        ]);
    }

    public function waitingStaff(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::WaitingStaff,
        ]);
    }
}
