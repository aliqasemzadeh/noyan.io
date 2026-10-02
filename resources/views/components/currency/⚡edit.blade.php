<?php

use App\Livewire\Forms\CurrencyForm;
use App\Models\Currency;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public CurrencyForm $form;

    public ?Currency $currency = null;

    #[On('panels.administrator.currency.edit.assign-data')]
    public function assignData(Currency $currency): void
    {
        $this->currency = $currency;
        $this->form->setModel($currency);
        $this->resetValidation();

        Flux::modal('currency.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();

        $this->reset('currency');

        $this->dispatch('panels.administrator.currency.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.currency_updated'));
    }
};
?>

<flux:modal name="currency.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_currency') }}</flux:heading>
    </div>

    @if ($currency)
        <flux:callout icon="coins" variant="secondary" inline>
            {{ $currency->code }} — {{ $currency->name }}
        </flux:callout>
    @endif

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

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model.live="form.is_active" />
            <flux:error name="form.is_active" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
