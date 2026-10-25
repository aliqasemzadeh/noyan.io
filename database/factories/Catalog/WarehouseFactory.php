<?php

namespace Database\Factories\Catalog;

use App\Enums\Catalog\WarehouseType;
use App\Models\Business;
use App\Models\Catalog\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('WH-###')),
            'type' => WarehouseType::Main,
            'is_default' => false,
            'is_sellable' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => [
            'type' => WarehouseType::Main,
            'is_default' => true,
            'is_sellable' => true,
            'code' => WarehouseType::Main->defaultCode(),
            'name' => 'انبار اصلی',
        ]);
    }
}
