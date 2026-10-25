<?php

use App\Enums\Catalog\WarehouseType;
use App\Livewire\Forms\Catalog\WarehouseForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public WarehouseForm $form;

    public function save(): void
    {
        $warehouse = $this->form->store();

        $this->dispatch('panels.warehouse.warehouse.index.table');
        Flux::modals()->close();
        Flux::toast(__('general.warehouse_created', ['name' => $warehouse->name]));
    }

    public function updatedFormType(string $value): void
    {
        $type = WarehouseType::tryFrom($value);

        if ($type !== null) {
            $this->form->is_sellable = $type->isSellableByDefault();
        }
    }
};
?>

<flux:modal name="warehouse.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_warehouse') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_warehouse_hint') }}</flux:text>
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
            <flux:select wire:model.live="form.type" variant="listbox" searchable>
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

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
