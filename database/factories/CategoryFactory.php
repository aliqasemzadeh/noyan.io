<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'business_id' => Business::factory(),
            'parent_id' => null,
            'type' => CategoryType::Expense,
            'code' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'is_system' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => [
            'business_id' => null,
            'is_system' => true,
            'code' => strtoupper(fake()->unique()->lexify('???')),
        ]);
    }

    public function product(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Product,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Income,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->id,
            'type' => $parent->type,
            'business_id' => $parent->business_id,
            'is_system' => $parent->is_system,
        ]);
    }
}
