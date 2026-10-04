<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
            'voucher_number' => str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'entry_date' => now()->toDateString(),
            'description' => fake()->sentence(),
            'referenceable_type' => null,
            'referenceable_id' => null,
            'status' => JournalEntryStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => JournalEntryStatus::Draft,
        ]);
    }

    public function posted(): static
    {
        return $this->state(fn (): array => [
            'status' => JournalEntryStatus::Posted,
        ]);
    }
}
