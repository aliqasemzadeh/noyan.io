<?php

use App\Livewire\Forms\ActivateBusinessSettlementTypeForm;
use App\Models\Accounting\SettlementType;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ActivateBusinessSettlementTypeForm $form;

    /**
     * @return Collection<int, SettlementType>
     */
    #[Computed]
    public function availableSettlementTypes(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return SettlementType::query()->whereRaw('1 = 0')->get();
        }

        $activatedIds = Business::query()
            ->find($businessId)
            ?->businessSettlementTypes()
            ->pluck('settlement_type_id')
            ->all() ?? [];

        return SettlementType::query()
            ->availableToBusiness($businessId)
            ->whereNotIn('id', $activatedIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $this->form->store();

        unset($this->availableSettlementTypes);

        $this->dispatch('panels.accounting.settlement-type.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.settlement_type_activated'));
    }
};
?>

<flux:modal name="accounting.settlement-type.activate" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.activate_settlement_type') }}</flux:heading>
        <flux:text class="mt-2">{{ __('general.activate_settlement_type_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.settlement_type') }}</flux:label>
            <flux:select wire:model="form.settlement_type_id" searchable variant="listbox" placeholder="{{ __('general.select_settlement_type') }}">
                @foreach ($this->availableSettlementTypes as $settlementType)
                    <flux:select.option value="{{ $settlementType->id }}" wire:key="activate-settlement-type-{{ $settlementType->id }}">
                        {{ $settlementType->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.settlement_type_id" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
