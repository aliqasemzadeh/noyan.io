<?php

namespace Tests\Feature;

use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Models\User;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessLedgerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_business_seeds_ledger_settings(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();

        // Factory may not run CreateBusinessAction; seed explicitly like production path.
        $settings = BusinessLedgerSetting::forBusiness($business);

        $this->assertSame(VoucherNumberingMode::Yearly, $settings->voucher_numbering_mode);
        $this->assertSame(JournalEntryStatus::Draft, $settings->default_voucher_status);
        $this->assertSame(AutoPostMode::Off, $settings->auto_post_mode);
        $this->assertSame(4, $settings->voucher_number_pad);
    }

    public function test_ledger_settings_service_caches_and_forgets_on_update(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $service = app(BusinessLedgerSettingsService::class);

        $first = $service->get($business);
        $this->assertTrue(Cache::has($service->cacheKey($business->id)));

        $service->update($business, [
            'voucher_numbering_mode' => VoucherNumberingMode::Monthly,
            'voucher_prefix' => 'JE',
            'voucher_number_pad' => 5,
            'default_voucher_status' => JournalEntryStatus::Draft,
            'auto_post_mode' => AutoPostMode::ManualReview,
        ]);

        $this->assertFalse(Cache::has($service->cacheKey($business->id)));

        $fresh = $service->get($business);
        $this->assertSame(VoucherNumberingMode::Monthly, $fresh->voucher_numbering_mode);
        $this->assertSame('JE', $fresh->voucher_prefix);
        $this->assertSame(5, $fresh->voucher_number_pad);
        $this->assertSame(AutoPostMode::ManualReview, $fresh->auto_post_mode);
        $this->assertSame($user->id, $business->owner_id);
    }

    public function test_ledger_settings_round_trip_through_cache_as_attributes(): void
    {
        [, $business] = $this->actingBusinessUser();
        $service = app(BusinessLedgerSettingsService::class);

        $first = $service->get($business);
        $cached = Cache::get($service->cacheKey($business->id));
        $second = $service->get($business);

        $this->assertIsArray($cached);
        $this->assertInstanceOf(BusinessLedgerSetting::class, $second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->business_id, $second->business_id);
        $this->assertSame($first->auto_post_mode, $second->auto_post_mode);
        $this->assertSame($first->voucher_numbering_mode, $second->voucher_numbering_mode);
        $this->assertTrue($second->exists);
    }

    public function test_ledger_settings_cache_rebuilds_when_payload_is_not_an_array(): void
    {
        [, $business] = $this->actingBusinessUser();
        BusinessLedgerSetting::forBusiness($business);
        $service = app(BusinessLedgerSettingsService::class);

        Cache::put($service->cacheKey($business->id), new \stdClass, now()->addHour());

        $settings = $service->get($business);

        $this->assertInstanceOf(BusinessLedgerSetting::class, $settings);
        $this->assertSame($business->id, $settings->business_id);
        $this->assertIsArray(Cache::get($service->cacheKey($business->id)));
    }

    public function test_user_can_update_ledger_settings_from_page(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        BusinessLedgerSetting::forBusiness($business);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.ledger-setting.index')
            ->set('form.voucher_numbering_mode', VoucherNumberingMode::Daily->value)
            ->set('form.voucher_prefix', 'GL')
            ->set('form.voucher_number_pad', 3)
            ->set('form.default_voucher_status', JournalEntryStatus::Posted->value)
            ->set('form.auto_post_mode', AutoPostMode::Realtime->value)
            ->call('save')
            ->assertHasNoErrors();

        $settings = BusinessLedgerSetting::query()->where('business_id', $business->id)->first();

        $this->assertNotNull($settings);
        $this->assertSame(VoucherNumberingMode::Daily, $settings->voucher_numbering_mode);
        $this->assertSame('GL', $settings->voucher_prefix);
        $this->assertSame(3, $settings->voucher_number_pad);
        $this->assertSame(JournalEntryStatus::Posted, $settings->default_voucher_status);
        $this->assertSame(AutoPostMode::Realtime, $settings->auto_post_mode);
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
