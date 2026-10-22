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

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.category.index')
            ->assertOk()
            ->assertSee('عملیاتی')
            ->assertDontSee('حقوق پایه')
            ->assertSeeHtml('data-depth="0"')
            ->call('toggleExpand', $root->id)
            ->assertSee('حقوق')
            ->assertDontSee('حقوق پایه')
            ->call('toggleExpand', $child->id)
            ->assertSeeInOrder(['عملیاتی', 'حقوق', 'حقوق پایه'])
            ->assertSeeHtml('data-depth="2"')
            ->assertSeeHtml('data-category-id="'.$grandchild->id.'"');
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
        $component
            ->assertSee('RootCat-01')
            ->assertDontSee('RootCat-16')
            ->assertDontSee('ChildCat-01')
            ->call('gotoPage', 2)
            ->assertSee('RootCat-16')
            ->assertDontSee('RootCat-01')
            ->call('toggleExpand', $roots[15]->id)
            ->assertSee('ChildCat-16');
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
        Category::factory()->system()->expense()->childOf($child)->create([
            'code' => 'bonus',
            'name' => 'پاداش ویژه',
            'slug' => 'bonus',
        ]);
        Category::factory()->system()->expense()->create([
            'code' => 'unrelated',
            'name' => 'متفرقه',
            'slug' => 'unrelated',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.category.index')
            ->set('search', 'پاداش')
            ->assertSeeInOrder(['عملیاتی', 'حقوق', 'پاداش ویژه'])
            ->assertDontSee('متفرقه');
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

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.category.index')
            ->assertOk()
            ->assertSee('عملیاتی')
            ->assertDontSee('حقوق')
            ->call('toggleExpand', $root->id)
            ->assertSee('حقوق')
            ->set('search', 'حقوق')
            ->assertSeeInOrder(['عملیاتی', 'حقوق'])
            ->assertSeeHtml('data-category-id="'.$child->id.'"');
    }

    public function test_orphan_child_of_other_type_is_promoted_to_root(): void
    {
        [$user] = $this->actingBusinessUser();

        $incomeParent = Category::factory()->system()->income()->create([
            'code' => 'income-root',
            'name' => 'درآمد والد',
            'slug' => 'income-root',
        ]);
        Category::factory()->system()->expense()->create([
            'parent_id' => $incomeParent->id,
            'code' => 'orphan-expense',
            'name' => 'هزینه یتیم',
            'slug' => 'orphan-expense',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.category.index')
            ->set('type', CategoryType::Expense->value)
            ->assertSee('هزینه یتیم')
            ->assertSeeHtml('data-depth="0"')
            ->assertDontSee('درآمد والد');
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
