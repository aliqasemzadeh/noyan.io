<?php

use App\Models\Accounting\BusinessSaleKind;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?BusinessSaleKind $businessSaleKind = null;

    #[On('panels.accounting.sale-kind.deactivate.assign-data')]
    public function assignData(BusinessSaleKind $businessSaleKind): void
    {
        abort_unless(
            (int) $businessSaleKind->business_id === (int) Auth::user()?->current_business_id,
            403,
        );

        $this->businessSaleKind = $businessSaleKind->load('saleKind');
        $this->resetValidation();

        Flux::modal('accounting.sale-kind.deactivate')->show();
    }

    public function deactivate(): void
    {
        if ($this->businessSaleKind === null) {
            return;
        }

        $business = Business::query()->findOrFail($this->businessSaleKind->business_id);
        $saleKind = $this->businessSaleKind->saleKind;

        if ($saleKind !== null) {
            $business->deactivateSaleKind($saleKind);
        }

        $this->reset('businessSaleKind');

        $this->dispatch('panels.accounting.sale-kind.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.sale_kind_deactivated'));
    }
};
?>

<flux:modal name="accounting.sale-kind.deactivate" class="min-w-[22rem]">
    <form wire:submit="deactivate" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.deactivate_sale_kind') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.deactivate_sale_kind_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($businessSaleKind?->saleKind)
            <flux:callout icon="tag" variant="secondary" inline>
                {{ $businessSaleKind->saleKind->name }}
            </flux:callout>
        @endif

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="danger">{{ __('general.deactivate') }}</flux:button>
        </div>
    </form>
</flux:modal>
