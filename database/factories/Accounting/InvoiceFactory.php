<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

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
            'invoice_number' => 'SL-'.fake()->unique()->numerify('1405-01-####'),
            'type' => InvoiceType::Sale,
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'finalized_at' => null,
            'items_total' => '0',
            'global_discount' => '0',
            'global_tax' => '0',
            'total_amount' => '0',
            'paid_amount' => '0',
            'meta' => null,
            'payment_status' => InvoicePaymentStatus::Unpaid,
            'note' => null,
        ];
    }

    public function purchase(): static
    {
        return $this->state(fn (): array => [
            'type' => InvoiceType::Purchase,
            'invoice_number' => 'PR-'.fake()->unique()->numerify('1405-01-####'),
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => [
            'finalized_at' => now(),
        ]);
    }
}
