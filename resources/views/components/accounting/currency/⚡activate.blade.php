<?php

use App\Livewire\Forms\ActivateBusinessCurrencyForm;
use App\Models\Business;
use App\Models\Currency;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ActivateBusinessCurrencyForm $form;

    /**
     * @return Collection<int, Currency>
     */
    #[Computed]
    public function availableCurrencies(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return Currency::query()->whereRaw('1 = 0')->get();
        }

        $activatedIds = Business::query()
            ->find($businessId)
            ?->businessCurrencies()
            ->pluck('currency_id')
            ->all() ?? [];

        return Currency::query()
            ->system()
            ->active()
            ->whereNotIn('id', $activatedIds)
            ->orderBy('code')
            ->get();
    }

    public function save(): void
    {
        $this->form->store();

        unset($this->availableCurrencies);

        $this->dispatch('panels.accounting.currency.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.currency_activated'));
    }
};
?>

<flux:modal name="accounting.currency.activate" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.activate_currency') }}</flux:heading>
        <flux:text class="mt-2">{{ __('general.activate_currency_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.currency') }}</flux:label>
            <flux:select wire:model="form.currency_id" searchable variant="listbox" placeholder="{{ __('general.select_currency') }}">
                @foreach ($this->availableCurrencies as $currency)
                    <flux:select.option value="{{ $currency->id }}" wire:key="activate-currency-{{ $currency->id }}">
                        {{ $currency->code }} — {{ $currency->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.currency_id" />
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
