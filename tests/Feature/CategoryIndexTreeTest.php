<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class CategoryIndexTreeTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_accounting_index_shows_roots_then_expands_children_in_dfs_order(): void
    {
        [$user] = $this->actingBusinessUser();

        $root = Category::factory()->system()->expense()->create([
            'code' => 'operating',
            'name' => 'عملیاتی',
            'slug' => 'operating',
            'sort_order' => 1,
        ]);
        $child = Category::factory()->system()->expense()->childOf($root)->create([
            'code' => 'payroll',
            'name' => 'حقوق',
            'slug' => 'payroll',
            'sort_order' => 1,
        ]);
        $grandchild = Category::factory()->system()->expense()->childOf($child)->create([
            'code' => 'base-salary',
            'name' => 'حقوق پایه',
            'slug' => 'base-salary',
            'sort_order' => 1,
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::panel.accounting.category.index')
            ->assertOk();

        $this->assertSame(
            [$root->id],
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );
        $this->assertSame(0, $component->instance()->rows[0]['depth']);
        $this->assertSame(1, $component->instance()->rows[0]['children_count']);

        $component->call('toggleExpand', $root->id);

        $this->assertSame(
            [$root->id, $child->id],
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );
        $this->assertSame(1, $component->instance()->rows[1]['depth']);

        $component->call('toggleExpand', $child->id);

        $this->assertSame(
            [$root->id, $child->id, $grandchild->id],
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );
        $this->assertSame(2, $component->instance()->rows[2]['depth']);
    }

    public function test_accounting_index_paginates_roots_only(): void
    {
        [$user] = $this->actingBusinessUser();

        $roots = [];

        for ($i = 1; $i <= 16; $i++) {
            $root = Category::factory()->system()->expense()->create([
                'code' => 'root-'.$i,
                'name' => sprintf('RootCat-%02d', $i),
                'slug' => 'root-'.$i,
                'sort_order' => $i,
            ]);
            Category::factory()->system()->expense()->childOf($root)->create([
                'code' => 'child-'.$i,
                'name' => sprintf('ChildCat-%02d', $i),
                'slug' => 'child-'.$i,
                'sort_order' => 1,
            ]);
            $roots[] = $root;
        }

        $component = Livewire::actingAs($user)
            ->test('pages::panel.accounting.category.index')
            ->assertOk();

        $this->assertSame(16, $component->instance()->categories->total());
        $this->assertCount(15, $component->instance()->rows);
        $this->assertSame($roots[0]->id, $component->instance()->rows[0]['category']->id);
        $this->assertNotContains(
            $roots[15]->id,
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );

        $component->call('gotoPage', 2);

        $this->assertCount(1, $component->instance()->rows);
        $this->assertSame($roots[15]->id, $component->instance()->rows[0]['category']->id);

        $component->call('toggleExpand', $roots[15]->id);

        $this->assertCount(2, $component->instance()->rows);
        $this->assertSame(1, $component->instance()->rows[1]['depth']);
    }

    public function test_accounting_search_shows_ancestors_of_matching_grandchild(): void
    {
        [$user] = $this->actingBusinessUser();

        $root = Category::factory()->system()->expense()->create([
            'code' => 'operating',
            'name' => 'عملیاتی',
            'slug' => 'operating',
        ]);
        $child = Category::factory()->system()->expense()->childOf($root)->create([
            'code' => 'payroll',
            'name' => 'حقوق',
            'slug' => 'payroll',
        ]);
        $grandchild = Category::factory()->system()->expense()->childOf($child)->create([
            'code' => 'bonus',
            'name' => 'پاداش ویژه',
            'slug' => 'bonus',
        ]);
        $unrelated = Category::factory()->system()->expense()->create([
            'code' => 'unrelated',
            'name' => 'متفرقه',
            'slug' => 'unrelated',
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::panel.accounting.category.index')
            ->set('search', 'پاداش');

        $this->assertSame(
            [$root->id, $child->id, $grandchild->id],
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );
        $this->assertTrue($component->instance()->rows[0]['is_context']);
        $this->assertTrue($component->instance()->rows[1]['is_context']);
        $this->assertFalse($component->instance()->rows[2]['is_context']);
        $this->assertNotContains(
            $unrelated->id,
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );
    }

    public function test_administrator_index_supports_expand_and_search(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());

        $root = Category::factory()->system()->expense()->create([
            'code' => 'operating',
            'name' => 'عملیاتی',
            'slug' => 'operating',
        ]);
        $child = Category::factory()->system()->expense()->childOf($root)->create([
            'code' => 'payroll',
            'name' => 'حقوق',
            'slug' => 'payroll',
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::panel.administrator.category.index')
            ->assertOk();

        $this->assertSame(
            [$root->id],
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );

        $component->call('toggleExpand', $root->id);

        $this->assertSame(
            [$root->id, $child->id],
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );

        $component->set('search', 'حقوق');

        $this->assertSame(
            [$root->id, $child->id],
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );
        $this->assertTrue($component->instance()->rows[0]['is_context']);
        $this->assertFalse($component->instance()->rows[1]['is_context']);
    }

    public function test_orphan_child_of_other_type_is_promoted_to_root(): void
    {
        [$user] = $this->actingBusinessUser();

        $incomeParent = Category::factory()->system()->income()->create([
            'code' => 'income-root',
            'name' => 'درآمد والد',
            'slug' => 'income-root',
        ]);
        $orphan = Category::factory()->system()->expense()->create([
            'parent_id' => $incomeParent->id,
            'code' => 'orphan-expense',
            'name' => 'هزینه یتیم',
            'slug' => 'orphan-expense',
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::panel.accounting.category.index')
            ->set('type', CategoryType::Expense->value);

        $this->assertSame(
            [$orphan->id],
            array_map(fn (array $row): int => $row['category']->id, $component->instance()->rows),
        );
        $this->assertSame(0, $component->instance()->rows[0]['depth']);
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingBusinessUser(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        return [$user, $business];
    }
}
