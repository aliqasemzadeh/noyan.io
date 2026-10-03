<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_seeder_is_idempotent_and_system_only(): void
    {
        $this->seed(CategorySeeder::class);
        $firstCount = Category::query()->count();

        $this->seed(CategorySeeder::class);
        $secondCount = Category::query()->count();

        $this->assertSame($firstCount, $secondCount);
        $this->assertGreaterThan(0, $firstCount);

        $this->assertSame(
            0,
            Category::query()->whereNotNull('business_id')->count()
        );

        $this->assertSame(
            $firstCount,
            Category::query()->system()->count()
        );

        foreach (CategoryType::accountingCases() as $type) {
            $this->assertTrue(
                Category::query()->system()->ofType($type)->exists(),
                "Missing system categories for {$type->value}"
            );
        }
    }
}
