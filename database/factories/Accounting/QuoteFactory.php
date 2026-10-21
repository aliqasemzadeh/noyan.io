<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\Quote;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'created_by' => User::factory(),
            'party_id' => null,
            'party_name' => fake()->name(),
            'quote_number' => 'QT-'.fake()->unique()->numerify('1405-01-####'),
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'finalized_at' => null,
            'converted_at' => null,
            'invoice_id' => null,
            'items_total' => '0',
            'global_discount' => '0',
            'global_tax' => '0',
            'total_amount' => '0',
            'meta' => null,
            'note' => null,
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => [
            'finalized_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'finalized_at' => now()->subDays(10),
            'issue_date' => now()->subDays(14)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
    }

    public function converted(?Invoice $invoice = null): static
    {
        return $this->state(function () use ($invoice): array {
            $resolved = $invoice ?? Invoice::factory()->create();

            return [
                'finalized_at' => now()->subDay(),
                'converted_at' => now(),
                'invoice_id' => $resolved->id,
                'business_id' => $resolved->business_id,
                'created_by' => $resolved->created_by,
            ];
        });
    }
}
