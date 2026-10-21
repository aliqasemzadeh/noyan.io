<?php

use App\Actions\Business\UpdateBusinessAction;
use App\Actions\Business\UpdateBusinessInvoiceSettingsAction;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Livewire\Forms\BusinessInvoiceSettingsForm;
use App\Livewire\Forms\UserBusinessForm;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Business $business;

    public UserBusinessForm $form;

    public BusinessInvoiceSettingsForm $invoiceSettingsForm;

    #[Url]
    public string $tab = 'general';

    public function mount(Business $business): void
    {
        abort_unless(Auth::user()?->belongsToBusiness($business), 403);

        $this->business = $business;
        $this->form->setModel($business);
        $this->invoiceSettingsForm->setModel($business);
    }

    public function save(UpdateBusinessAction $action): void
    {
        $this->form->update($action);

        Flux::toast(__('general.business_updated'));

        $this->redirect(route('user.businesses.view', $this->business), navigate: true);
    }

    public function saveInvoiceSettings(UpdateBusinessInvoiceSettingsAction $action): void
    {
        $this->invoiceSettingsForm->update($action);

        Flux::toast(__('general.invoice_settings_saved'));
    }
};
?>

<x-slot name="title">{{ __('general.edit_business') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.index')" wire:navigate>
                {{ __('general.my_businesses') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.view', $business)" wire:navigate>
                {{ $business->name }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.edit_business') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.edit_business') }}
            </flux:heading>
        </div>
    </div>

    <div class="mx-auto max-w-3xl">
        <flux:tab.group>
            <flux:tabs wire:model="tab" variant="segmented" scrollable>
                <flux:tab name="general" icon="building-storefront">
                    {{ __('general.business_tab_general') }}
                </flux:tab>
                <flux:tab name="branding" icon="paint-brush">
                    {{ __('general.business_tab_branding') }}
                </flux:tab>
                <flux:tab name="invoice" icon="document-text">
                    {{ __('general.business_tab_invoice') }}
                </flux:tab>
                <flux:tab name="notifications" icon="bell">
                    {{ __('general.business_tab_notifications') }}
                </flux:tab>
            </flux:tabs>

            <flux:tab.panel name="general" class="pt-6">
                <flux:card>
                    <form wire:submit="save" class="space-y-6">
                        <flux:field>
                            <flux:label>{{ __('general.name') }}</flux:label>
                            <flux:input
                                wire:model="form.name"
                                placeholder="{{ __('general.business_name_placeholder') }}"
                                clearable
                            />
                            <flux:error name="form.name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('general.business_type') }}</flux:label>
                            <flux:radio.group wire:model="form.type" variant="cards" class="flex max-sm:flex-col">
                                @foreach (BusinessType::cases() as $type)
                                    <flux:radio :value="$type->value" :label="$type->label()" />
                                @endforeach
                            </flux:radio.group>
                            <flux:error name="form.type" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('general.business_category') }}</flux:label>
                            <flux:select
                                wire:model="form.category"
                                variant="listbox"
                                searchable
                                placeholder="{{ __('general.select_business_category') }}"
                            >
                                @foreach (BusinessCategory::cases() as $category)
                                    <flux:select.option :value="$category->value">
                                        {{ $category->label() }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.category" />
                        </flux:field>

                        <flux:button type="submit" variant="primary" color="teal" class="w-full">
                            {{ __('general.save') }}
                        </flux:button>
                    </form>
                </flux:card>
            </flux:tab.panel>

            <flux:tab.panel name="branding" class="pt-6">
                <flux:card>
                    <form wire:submit="save" class="space-y-6">
                        <x-business.branding-fields
                            :logo="$form->logo"
                            :existing-logo-url="$business->logoUrl()"
                            :remove-logo="$form->remove_logo"
                            :business="$business"
                        />

                        <flux:button type="submit" variant="primary" color="teal" class="w-full">
                            {{ __('general.save') }}
                        </flux:button>
                    </form>
                </flux:card>
            </flux:tab.panel>

            <flux:tab.panel name="invoice" class="pt-6">
                <flux:card>
                    <form wire:submit="saveInvoiceSettings" class="space-y-6">
                        <x-business.invoice-numbering-fields form-prefix="invoiceSettingsForm" />
                        <x-business.invoice-footer-field form-prefix="invoiceSettingsForm" />

                        <flux:button type="submit" variant="primary" color="teal" class="w-full">
                            {{ __('general.save') }}
                        </flux:button>
                    </form>
                </flux:card>
            </flux:tab.panel>

            <flux:tab.panel name="notifications" class="pt-6">
                <flux:card>
                    <div class="mb-6 flex items-center justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ __('general.sms_wallet_balance') }}</flux:heading>
                            <flux:text class="mt-1" dir="ltr">
                                {{ number_format((float) $business->sms_wallet_balance) }}
                            </flux:text>
                        </div>
                    </div>

                    <form wire:submit="saveInvoiceSettings" class="space-y-6">
                        <x-business.invoice-notification-fields form-prefix="invoiceSettingsForm" />

                        <flux:button type="submit" variant="primary" color="teal" class="w-full">
                            {{ __('general.save') }}
                        </flux:button>
                    </form>
                </flux:card>
            </flux:tab.panel>
        </flux:tab.group>
    </div>
</div>
