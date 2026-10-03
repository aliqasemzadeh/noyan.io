<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_options_mark_leaf_status_per_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $parent = Category::factory()->system()->expense()->create([
            'code' => 'operating',
            'name' => 'عملیاتی',
            'slug' => 'operating',
        ]);
        $systemLeaf = Category::factory()->system()->expense()->childOf($parent)->create([
            'code' => 'payroll',
            'name' => 'حقوق',
            'slug' => 'payroll',
        ]);

        Category::factory()->expense()->create([
            'business_id' => $businessA->id,
            'parent_id' => $systemLeaf->id,
            'name' => 'پاداش',
            'slug' => 'bonus',
        ]);

        $optionsA = Category::selectOptionsForBusiness($businessA->id, CategoryType::Expense)->keyBy('id');
        $optionsB = Category::selectOptionsForBusiness($businessB->id, CategoryType::Expense)->keyBy('id');

        $this->assertFalse($optionsA[$parent->id]['is_leaf']);
        $this->assertFalse($optionsA[$systemLeaf->id]['is_leaf']);
        $this->assertSame('عملیاتی › حقوق', $optionsA[$systemLeaf->id]['path']);
        $this->assertCount(3, $optionsA);

        $this->assertFalse($optionsB[$parent->id]['is_leaf']);
        $this->assertTrue($optionsB[$systemLeaf->id]['is_leaf']);
        $this->assertCount(2, $optionsB);
    }

    public function test_cache_invalidation_on_system_and_business_save(): void
    {
        $business = Business::factory()->create();

        $system = Category::factory()->system()->expense()->create([
            'code' => 'cached',
            'name' => 'Cached',
            'slug' => 'cached',
        ]);

        Category::selectOptionsForBusiness($business->id, CategoryType::Expense);

        $system->update(['name' => 'Cached Updated']);

        $options = Category::selectOptionsForBusiness($business->id, CategoryType::Expense);
        $this->assertTrue($options->contains(fn (array $option): bool => $option['name'] === 'Cached Updated'));

        Category::factory()->expense()->create([
            'business_id' => $business->id,
            'name' => 'Custom Cached',
            'slug' => 'custom-cached',
        ]);

        $options = Category::selectOptionsForBusiness($business->id, CategoryType::Expense);
        $this->assertTrue($options->contains(fn (array $option): bool => $option['name'] === 'Custom Cached'));
    }
}
