<?php

use App\Livewire\Forms\ActivateBusinessSaleKindForm;
use App\Models\Accounting\SaleKind;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ActivateBusinessSaleKindForm $form;

    /**
     * @return Collection<int, SaleKind>
     */
    #[Computed]
    public function availableSaleKinds(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return SaleKind::query()->whereRaw('1 = 0')->get();
        }

        $activatedIds = Business::query()
            ->find($businessId)
            ?->businessSaleKinds()
            ->pluck('sale_kind_id')
            ->all() ?? [];

        return SaleKind::query()
            ->availableToBusiness($businessId)
            ->whereNotIn('id', $activatedIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $this->form->store();

        unset($this->availableSaleKinds);

        $this->dispatch('panels.accounting.sale-kind.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.sale_kind_activated'));
    }
};
?>

<flux:modal name="accounting.sale-kind.activate" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.activate_sale_kind') }}</flux:heading>
        <flux:text class="mt-2">{{ __('general.activate_sale_kind_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.sale_kind') }}</flux:label>
            <flux:select wire:model="form.sale_kind_id" searchable variant="listbox" placeholder="{{ __('general.select_sale_kind') }}">
                @foreach ($this->availableSaleKinds as $saleKind)
                    <flux:select.option value="{{ $saleKind->id }}" wire:key="activate-sale-kind-{{ $saleKind->id }}">
                        {{ $saleKind->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.sale_kind_id" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
