<?php

use App\Livewire\Forms\CustomCurrencyForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public CustomCurrencyForm $form;

    public function save(): void
    {
        $currency = $this->form->store();

        $this->dispatch('panels.accounting.currency.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.custom_asset_created', ['code' => $currency->code]));
    }
};
?>

<flux:modal name="accounting.currency.create-custom" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_custom_asset') }}</flux:heading>
        <flux:text class="mt-2">{{ __('general.create_custom_asset_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.currency_code') }}</flux:label>
            <flux:input
                wire:model="form.code"
                placeholder="{{ __('general.currency_code_placeholder') }}"
                dir="ltr"
            />
            <flux:error name="form.code" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" placeholder="{{ __('general.currency_name_placeholder') }}" />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.currency_symbol') }}</flux:label>
            <flux:input
                wire:model="form.symbol"
                placeholder="{{ __('general.currency_symbol_placeholder') }}"
                dir="ltr"
            />
            <flux:error name="form.symbol" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.decimal_places') }}</flux:label>
            <flux:input type="number" wire:model="form.decimal_places" min="0" max="18" dir="ltr" />
            <flux:description>{{ __('general.decimal_places_hint') }}</flux:description>
            <flux:error name="form.decimal_places" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.exchange_rate_to_base') }}</flux:label>
            <flux:input wire:model="form.exchange_rate_to_base" placeholder="1" dir="ltr" />
            <flux:description>{{ __('general.exchange_rate_to_base_hint') }}</flux:description>
            <flux:error name="form.exchange_rate_to_base" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
