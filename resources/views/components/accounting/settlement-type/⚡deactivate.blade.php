<?php

use App\Models\Accounting\BusinessSettlementType;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?BusinessSettlementType $businessSettlementType = null;

    #[On('panels.accounting.settlement-type.deactivate.assign-data')]
    public function assignData(BusinessSettlementType $businessSettlementType): void
    {
        abort_unless(
            (int) $businessSettlementType->business_id === (int) Auth::user()?->current_business_id,
            403,
        );

        $this->businessSettlementType = $businessSettlementType->load('settlementType');
        $this->resetValidation();

        Flux::modal('accounting.settlement-type.deactivate')->show();
    }

    public function deactivate(): void
    {
        if ($this->businessSettlementType === null) {
            return;
        }

        $business = Business::query()->findOrFail($this->businessSettlementType->business_id);
        $settlementType = $this->businessSettlementType->settlementType;

        if ($settlementType !== null) {
            $business->deactivateSettlementType($settlementType);
        }

        $this->reset('businessSettlementType');

        $this->dispatch('panels.accounting.settlement-type.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.settlement_type_deactivated'));
    }
};
?>

<flux:modal name="accounting.settlement-type.deactivate" class="min-w-[22rem]">
    <form wire:submit="deactivate" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.deactivate_settlement_type') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.deactivate_settlement_type_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($businessSettlementType?->settlementType)
            <flux:callout icon="tag" variant="secondary" inline>
                {{ $businessSettlementType->settlementType->name }}
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
