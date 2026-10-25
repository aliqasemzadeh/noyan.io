<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\SettlementType;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SettlementType>
 */
class SettlementTypeFactory extends Factory
{
    protected $model = SettlementType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'name' => $name,
            'is_system' => false,
            'business_id' => Business::factory(),
            'is_active' => true,
            'sort_order' => 100,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => [
            'is_system' => true,
            'business_id' => null,
        ]);
    }
}
