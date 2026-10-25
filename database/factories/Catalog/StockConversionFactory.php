<?php

namespace Database\Factories\Catalog;

use App\Enums\Catalog\StockConversionStatus;
use App\Models\Business;
use App\Models\Catalog\StockConversion;
use App\Models\Catalog\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockConversion>
 */
class StockConversionFactory extends Factory
{
    protected $model = StockConversion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'warehouse_id' => Warehouse::factory(),
            'project_id' => null,
            'cost_center_id' => null,
            'conversion_number' => 'SC-'.fake()->unique()->numerify('####'),
            'status' => StockConversionStatus::Draft,
            'note' => null,
            'occurred_at' => now(),
            'posted_at' => null,
            'created_by' => null,
        ];
    }
}
