<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketFile>
 */
class TicketFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'ticket_reply_id' => null,
            'user_id' => User::factory(),
            'path' => 'tickets/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime' => 'image/jpeg',
        ];
    }
}
