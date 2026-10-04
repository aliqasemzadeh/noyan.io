<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\FiscalYear;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) now()->format('Y');

        return [
            'business_id' => Business::factory(),
            'name' => (string) $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_closed' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'is_closed' => true,
        ]);
    }
}
