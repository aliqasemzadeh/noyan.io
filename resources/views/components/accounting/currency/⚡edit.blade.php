<?php

use App\Livewire\Forms\BusinessCurrencyForm;
use App\Models\Accounting\BusinessCurrency;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public BusinessCurrencyForm $form;

    public ?BusinessCurrency $businessCurrency = null;

    #[On('panels.accounting.currency.edit.assign-data')]
    public function assignData(BusinessCurrency $businessCurrency): void
    {
        abort_unless(
            (int) $businessCurrency->business_id === (int) Auth::user()?->current_business_id,
            403,
        );

        $this->businessCurrency = $businessCurrency->load('currency');
        $this->form->setModel($businessCurrency);
        $this->resetValidation();

        Flux::modal('accounting.currency.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();

        $this->reset('businessCurrency');

        $this->dispatch('panels.accounting.currency.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_currency_updated'));
    }
};
?>

<flux:modal name="accounting.currency.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_business_currency') }}</flux:heading>
    </div>

    @if ($businessCurrency?->currency)
        <flux:callout icon="coins" variant="secondary" inline>
            {{ $businessCurrency->currency->code }} — {{ $businessCurrency->currency->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field variant="inline">
            <flux:label>{{ __('general.base_currency') }}</flux:label>
            <flux:switch wire:model="form.is_base" />
            <flux:error name="form.is_base" />
        </flux:field>

        @unless ($form->is_base)
            <flux:field>
                <flux:label>{{ __('general.exchange_rate_to_base') }}</flux:label>
                <flux:input wire:model="form.exchange_rate_to_base" placeholder="1" dir="ltr" />
                <flux:description>{{ __('general.exchange_rate_to_base_hint') }}</flux:description>
                <flux:error name="form.exchange_rate_to_base" />
            </flux:field>
        @endunless

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
