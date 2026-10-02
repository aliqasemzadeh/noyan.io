<?php

use App\Models\Accounting\BusinessCurrency;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?BusinessCurrency $businessCurrency = null;

    #[On('panels.accounting.currency.deactivate.assign-data')]
    public function assignData(BusinessCurrency $businessCurrency): void
    {
        abort_unless(
            (int) $businessCurrency->business_id === (int) Auth::user()?->current_business_id,
            403,
        );

        $this->businessCurrency = $businessCurrency->load('currency');
        $this->resetValidation();

        Flux::modal('accounting.currency.deactivate')->show();
    }

    public function deactivate(): void
    {
        if ($this->businessCurrency === null) {
            return;
        }

        $business = Business::query()->findOrFail($this->businessCurrency->business_id);
        $currency = $this->businessCurrency->currency;

        $hasAccounts = $business->accounts()
            ->where('currency_id', $currency->id)
            ->exists();

        if ($hasAccounts) {
            Flux::toast(__('general.currency_has_accounts'), variant: 'danger');

            return;
        }

        $business->deactivateCurrency($currency);

        $this->reset('businessCurrency');

        $this->dispatch('panels.accounting.currency.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.currency_deactivated'));
    }
};
?>

<flux:modal name="accounting.currency.deactivate" class="min-w-[22rem]">
    <form wire:submit="deactivate" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.deactivate_currency') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.deactivate_currency_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($businessCurrency?->currency)
            <flux:callout icon="coins" variant="secondary" inline>
                {{ $businessCurrency->currency->code }} — {{ $businessCurrency->currency->name }}
            </flux:callout>
        @endif

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="danger">{{ __('general.deactivate_currency') }}</flux:button>
        </div>
    </form>
</flux:modal>
