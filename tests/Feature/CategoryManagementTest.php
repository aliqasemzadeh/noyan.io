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

class CategoryManagementTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_admin_can_create_system_category(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('category.create')
            ->set('form.type', CategoryType::Expense->value)
            ->set('form.code', 'office_rent')
            ->set('form.name', 'اجاره دفتر')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'business_id' => null,
            'is_system' => true,
            'type' => CategoryType::Expense->value,
            'code' => 'office_rent',
            'name' => 'اجاره دفتر',
        ]);
    }

    public function test_admin_can_update_system_category(): void
    {
        $admin = User::factory()->create();
        $category = Category::factory()->system()->expense()->create([
            'code' => 'rent',
            'name' => 'اجاره',
            'slug' => 'rent',
        ]);

        Livewire::actingAs($admin)
            ->test('category.edit')
            ->call('assignData', $category)
            ->set('form.name', 'اجاره دفتر')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'اجاره دفتر',
        ]);
    }

    public function test_business_user_cannot_edit_system_category(): void
    {
        [$user] = $this->actingBusinessUser();
        $category = Category::factory()->system()->expense()->create([
            'code' => 'rent',
            'slug' => 'rent',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.category.edit')
            ->call('assignData', $category)
            ->assertForbidden();
    }

    public function test_business_can_create_custom_child_under_system_parent(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $parent = Category::factory()->system()->expense()->create([
            'code' => 'operating',
            'name' => 'هزینه‌های عملیاتی',
            'slug' => 'operating',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.category.create')
            ->set('form.type', CategoryType::Expense->value)
            ->set('form.parent_id', $parent->id)
            ->set('form.name', 'غذای ماهی')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'business_id' => $business->id,
            'parent_id' => $parent->id,
            'type' => CategoryType::Expense->value,
            'is_system' => false,
            'name' => 'غذای ماهی',
        ]);
    }

    public function test_available_to_business_includes_system_and_own_excludes_others(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $other = Business::factory()->create();

        $system = Category::factory()->system()->expense()->create([
            'code' => 'utilities',
            'slug' => 'utilities',
        ]);
        $own = Category::factory()->expense()->create([
            'business_id' => $business->id,
            'slug' => 'own-expense',
        ]);
        Category::factory()->expense()->create([
            'business_id' => $other->id,
            'slug' => 'other-expense',
        ]);

        $ids = Category::query()
            ->availableToBusiness($business->id)
            ->ofType(CategoryType::Expense)
            ->pluck('id')
            ->all();

        $this->assertContains($system->id, $ids);
        $this->assertContains($own->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $admin = User::factory()->create();
        $parent = Category::factory()->system()->expense()->create([
            'code' => 'parent',
            'slug' => 'parent',
        ]);
        Category::factory()->system()->expense()->childOf($parent)->create([
            'code' => 'child',
            'slug' => 'child',
        ]);

        Livewire::actingAs($admin)
            ->test('category.delete')
            ->call('assignData', $parent)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $parent->id,
            'deleted_at' => null,
        ]);
    }

    public function test_system_categories_index_is_accessible(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        Category::factory()->system()->expense()->create([
            'code' => 'rent',
            'name' => 'اجاره',
            'slug' => 'rent',
        ]);

        $this->actingAs($admin)
            ->get(route('system.categories.index'))
            ->assertOk()
            ->assertSee('اجاره');
    }

    public function test_assign_data_prefills_parent_and_type_for_system_parent(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $parent = Category::factory()->system()->expense()->create([
            'code' => 'operating',
            'name' => 'هزینه‌های عملیاتی',
            'slug' => 'operating',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.category.create')
            ->call('assignData', $parent->id)
            ->assertSet('form.parent_id', $parent->id)
            ->assertSet('form.type', CategoryType::Expense->value)
            ->set('form.name', 'غذای ماهی')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'business_id' => $business->id,
            'parent_id' => $parent->id,
            'type' => CategoryType::Expense->value,
            'is_system' => false,
            'name' => 'غذای ماهی',
        ]);
    }

    public function test_assign_data_works_for_own_business_parent(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $parent = Category::factory()->expense()->create([
            'business_id' => $business->id,
            'name' => 'هزینه‌های فروش',
            'slug' => 'sales-costs',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.category.create')
            ->call('assignData', $parent->id)
            ->assertSet('form.parent_id', $parent->id)
            ->assertSet('form.type', CategoryType::Expense->value)
            ->set('form.name', 'حمل‌ونقل')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'business_id' => $business->id,
            'parent_id' => $parent->id,
            'name' => 'حمل‌ونقل',
        ]);
    }

    public function test_assign_data_forbidden_for_other_business_or_inactive_parent(): void
    {
        [$user] = $this->actingBusinessUser();
        $otherBusiness = Business::factory()->create();
        $foreignParent = Category::factory()->expense()->create([
            'business_id' => $otherBusiness->id,
            'slug' => 'foreign-parent',
        ]);
        $inactiveParent = Category::factory()->system()->expense()->create([
            'code' => 'inactive-parent',
            'slug' => 'inactive-parent',
            'is_active' => false,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.category.create')
            ->call('assignData', $foreignParent->id)
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test('accounting.category.create')
            ->call('assignData', $inactiveParent->id)
            ->assertForbidden();
    }

    public function test_assign_data_with_null_resets_parent(): void
    {
        [$user] = $this->actingBusinessUser();
        $parent = Category::factory()->system()->expense()->create([
            'code' => 'operating',
            'slug' => 'operating',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.category.create', ['type' => CategoryType::Expense->value])
            ->call('assignData', $parent->id)
            ->assertSet('form.parent_id', $parent->id)
            ->call('assignData', null)
            ->assertSet('form.parent_id', null)
            ->assertSet('parent', null)
            ->assertSet('form.type', CategoryType::Expense->value);
    }

    public function test_index_renders_create_subcategory_button_for_system_row(): void
    {
        [$user] = $this->actingBusinessUser();
        $system = Category::factory()->system()->expense()->create([
            'code' => 'rent',
            'name' => 'اجاره',
            'slug' => 'rent',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.category.index')
            ->assertOk()
            ->assertSee('panels.accounting.category.create.assign-data')
            ->assertSee((string) $system->id);
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
