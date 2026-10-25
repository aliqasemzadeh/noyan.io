<?php

use App\Enums\Catalog\WarehouseType;
use App\Livewire\Forms\Catalog\WarehouseForm;
use App\Models\Catalog\Warehouse;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public WarehouseForm $form;

    #[On('panels.warehouse.warehouse.edit.assign-data')]
    public function assignData(int $warehouseId): void
    {
        $warehouse = Warehouse::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->whereKey($warehouseId)
            ->firstOrFail();

        $this->form->setModel($warehouse);
        Flux::modal('warehouse.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();
        $this->dispatch('panels.warehouse.warehouse.index.table');
        Flux::modals()->close();
        Flux::toast(__('general.warehouse_updated'));
    }
};
?>

<flux:modal name="warehouse.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_warehouse') }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.code') }}</flux:label>
            <flux:input wire:model="form.code" dir="ltr" />
            <flux:error name="form.code" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.warehouse_type') }}</flux:label>
            <flux:select wire:model="form.type" variant="listbox" searchable>
                @foreach (WarehouseType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.type" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_default') }}</flux:label>
            <flux:switch wire:model="form.is_default" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_sellable') }}</flux:label>
            <flux:switch wire:model="form.is_sellable" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model="form.is_active" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="orange" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
