<?php

namespace Tests\Feature;

use App\Enums\Accounting\InvoiceNumberFormat;
use App\Models\Business;
use App\Models\BusinessInvoiceSetting;
use App\Models\User;
use App\Settings\InvoiceSystemSettings;
use App\Support\InvoiceDocumentFooter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class InvoiceSettingsAndWalletTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_user_can_save_invoice_settings(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $owner->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($owner)
            ->test('pages::panel.user.business.edit', ['business' => $business])
            ->set('invoiceSettingsForm.number_format', InvoiceNumberFormat::Year->value)
            ->set('invoiceSettingsForm.sale_prefix', 'INV')
            ->set('invoiceSettingsForm.email_enabled', false)
            ->set('invoiceSettingsForm.sms_enabled', true)
            ->set('invoiceSettingsForm.new_invoice_sms_body', 'Invoice {invoice_number}')
            ->call('saveInvoiceSettings')
            ->assertHasNoErrors();

        $settings = BusinessInvoiceSetting::query()->where('business_id', $business->id)->first();

        $this->assertNotNull($settings);
        $this->assertSame(InvoiceNumberFormat::Year, $settings->number_format);
        $this->assertSame('INV', $settings->sale_prefix);
        $this->assertTrue($settings->sms_enabled);
    }

    public function test_admin_can_charge_sms_wallet(): void
    {
        $admin = User::factory()->create();
        $this->grantAdministratorAccess($admin);

        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create([
            'sms_wallet_balance' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test('business.charge-sms-wallet')
            ->call('assignData', $business)
            ->set('amount', '25000')
            ->set('reason', 'manual')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('25000.00', (string) $business->fresh()->sms_wallet_balance);
    }

    public function test_system_settings_footer_appears_on_document_helper(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        $settings = app(InvoiceSystemSettings::class);
        $settings->invoice_global_footer = 'Global thank you note';
        $settings->sms_unit_cost = '1000';
        $settings->save();

        BusinessInvoiceSetting::forBusiness($business)->update([
            'invoice_footer' => 'Business local note',
        ]);

        $footer = InvoiceDocumentFooter::forBusiness($business);

        $this->assertNotNull($footer);
        $this->assertStringContainsString('Global thank you note', $footer->toHtml());
        $this->assertStringContainsString('Business local note', $footer->toHtml());
    }

    public function test_admin_can_save_system_invoice_settings(): void
    {
        $admin = User::factory()->create();
        $this->grantAdministratorAccess($admin);

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.system-management.setting.index')
            ->set('invoice_global_footer', 'Powered by Noyan')
            ->set('sms_unit_cost', '1500')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(InvoiceSystemSettings::class);

        $this->assertSame('Powered by Noyan', $settings->invoice_global_footer);
        $this->assertSame('1500', $settings->sms_unit_cost);
    }
}
