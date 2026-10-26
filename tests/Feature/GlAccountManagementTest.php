<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Business\CreateBusinessAction;
use App\Enums\Accounting\AccountLevel;
use App\Enums\Accounting\DetailType;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Enums\CurrencyType;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\GlAccountDetailLevel;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_business_seeds_chart_of_accounts(): void
    {
        $user = User::factory()->create();

        $currency = Currency::query()->create([
            'code' => 'IRT',
            'name' => 'Toman',
            'symbol' => 'T',
            'type' => CurrencyType::Fiat,
            'is_system' => true,
            'business_id' => null,
            'is_active' => true,
            'decimal_places' => 0,
        ]);

        $business = app(CreateBusinessAction::class)->handle($user, [
            'name' => 'COA Biz',
            'type' => BusinessType::Store->value,
            'category' => BusinessCategory::Computers->value,
            'currency_ids' => [$currency->id],
        ]);

        $this->assertTrue(
            GlAccount::query()->where('business_id', $business->id)->exists(),
        );

        $this->assertNotNull(GlAccount::findBySystemKey($business, 'sales'));
        $this->assertNotNull(GlAccount::findBySystemKey($business, 'accounts_receivable'));
        $this->assertSame(
            DetailType::Party,
            GlAccount::findBySystemKey($business, 'accounts_receivable')?->detailTypeForLevel(4),
        );
        $this->assertSame(
            DetailType::Account,
            GlAccount::findBySystemKey($business, 'cash_and_bank')?->detailTypeForLevel(4),
        );
    }

    public function test_seed_is_idempotent_and_does_not_duplicate_accounts(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $action = app(SeedBusinessChartOfAccountsAction::class);
        $first = $action->handle($business);
        $count = GlAccount::query()->where('business_id', $business->id)->count();

        $second = $action->handle($business);

        $this->assertSame($count, count($second));
        $this->assertSame($count, GlAccount::query()->where('business_id', $business->id)->count());
        $this->assertNotEmpty($first);
        $this->assertSame(
            GlAccount::findBySystemKey($business, 'sales')?->id,
            GlAccount::query()->where('business_id', $business->id)->where('system_key', 'sales')->value('id'),
        );
        $this->assertSame($user->id, $business->owner_id);

        $detailCount = GlAccountDetailLevel::query()
            ->whereIn('gl_account_id', GlAccount::query()->where('business_id', $business->id)->select('id'))
            ->count();

        $this->assertGreaterThan(0, $detailCount);
        $action->handle($business);

        $this->assertSame(
            $detailCount,
            GlAccountDetailLevel::query()
                ->whereIn('gl_account_id', GlAccount::query()->where('business_id', $business->id)->select('id'))
                ->count(),
        );
    }

    public function test_seed_restores_soft_deleted_system_account_without_duplicate(): void
    {
        [, $business] = $this->actingBusinessUser();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        $sales = GlAccount::findBySystemKey($business, 'sales');
        $this->assertNotNull($sales);
        $salesId = $sales->id;
        $sales->delete();

        $this->assertSoftDeleted('gl_accounts', ['id' => $salesId]);

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        $this->assertSame(1, GlAccount::query()->where('business_id', $business->id)->where('system_key', 'sales')->count());
        $this->assertNotSoftDeleted('gl_accounts', ['id' => $salesId]);
        $this->assertSame($salesId, GlAccount::findBySystemKey($business, 'sales')?->id);
    }

    public function test_user_can_create_custom_group_account(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        Livewire::actingAs($user)
            ->test('accounting.gl-account.create')
            ->call('assignData', null)
            ->set('form.code', '9')
            ->set('form.name', 'Memorandum group')
            ->set('form.nature', 'debit')
            ->set('form.statement_type', 'memorandum')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gl_accounts', [
            'business_id' => $business->id,
            'full_code' => '9',
            'name' => 'Memorandum group',
            'level' => AccountLevel::Group->value,
            'is_system' => false,
        ]);
    }

    public function test_user_can_add_child_under_control_account(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        $control = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('level', AccountLevel::Control)
            ->where('full_code', '11')
            ->firstOrFail();

        Livewire::actingAs($user)
            ->test('accounting.gl-account.create')
            ->call('assignData', $control->id)
            ->set('form.code', '99')
            ->set('form.name', 'Custom receivable')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gl_accounts', [
            'business_id' => $business->id,
            'parent_id' => $control->id,
            'full_code' => '1199',
            'level' => AccountLevel::Subsidiary->value,
            'is_postable' => true,
        ]);
    }

    public function test_system_account_cannot_be_deleted(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        $sales = GlAccount::findBySystemKey($business, 'sales');
        $this->assertNotNull($sales);

        Livewire::actingAs($user)
            ->test('accounting.gl-account.delete')
            ->call('assignData', $sales->id)
            ->call('destroy')
            ->assertHasErrors();

        $this->assertDatabaseHas('gl_accounts', [
            'id' => $sales->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_can_define_more_than_three_detail_levels_including_a_repeated_type(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        $control = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('level', AccountLevel::Control)
            ->where('full_code', '11')
            ->firstOrFail();

        Livewire::actingAs($user)
            ->test('accounting.gl-account.create')
            ->call('assignData', $control->id)
            ->set('form.code', '98')
            ->set('form.name', 'Seven level expense')
            ->set('form.detailLevels', [
                ['level' => 4, 'detail_type' => DetailType::Party->value],
                ['level' => 5, 'detail_type' => DetailType::Project->value],
                ['level' => 6, 'detail_type' => DetailType::CostCenter->value],
                ['level' => 7, 'detail_type' => DetailType::Party->value],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $account = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('full_code', '1198')
            ->firstOrFail();

        $this->assertSame(DetailType::Party, $account->detailTypeForLevel(4));
        $this->assertSame(DetailType::Project, $account->detailTypeForLevel(5));
        $this->assertSame(DetailType::CostCenter, $account->detailTypeForLevel(6));
        $this->assertSame(DetailType::Party, $account->detailTypeForLevel(7));
    }

    public function test_system_subsidiary_detail_levels_can_change_without_renaming(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        $sales = GlAccount::findBySystemKey($business, 'sales');
        $this->assertNotNull($sales);
        $originalName = $sales->name;

        Livewire::actingAs($user)
            ->test('accounting.gl-account.edit')
            ->call('assignData', $sales->id)
            ->set('form.name', 'Changed sales')
            ->call('addDetailLevel')
            ->set('form.detailLevels.0.detail_type', DetailType::Project->value)
            ->call('save')
            ->assertHasNoErrors();

        $sales->refresh();

        $this->assertSame($originalName, $sales->name);
        $this->assertSame(DetailType::Project, $sales->detailTypeForLevel(4));
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
