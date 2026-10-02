<?php

use App\Models\Currency;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Currency $currency = null;

    #[On('panels.administrator.currency.delete.assign-data')]
    public function assignData(Currency $currency): void
    {
        $this->currency = $currency;
        $this->resetValidation();

        Flux::modal('currency.delete')->show();
    }

    public function delete(): void
    {
        if ($this->currency === null) {
            return;
        }

        $this->currency->delete();

        Currency::forgetActiveCache();

        $this->reset('currency');

        $this->dispatch('panels.administrator.currency.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.currency_deleted'));
    }
};
?>

<flux:modal name="currency.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_currency_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($currency)
            <flux:callout icon="coins" variant="secondary" inline>
                {{ $currency->code }} — {{ $currency->name }}
            </flux:callout>
        @endif

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="danger">{{ __('general.delete') }}</flux:button>
        </div>
    </form>
</flux:modal>
