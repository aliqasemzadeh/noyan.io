<?php

use App\Livewire\Forms\Accounting\CostCenterForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public CostCenterForm $form;

    public function save(): void
    {
        $costCenter = $this->form->store();

        $this->dispatch('panels.accounting.cost-center.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.cost_center_created', ['name' => $costCenter->name]));
    }
};
?>

<flux:modal name="cost-center.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_cost_center') }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.code') }}</flux:label>
            <flux:input wire:model="form.code" clearable dir="ltr" />
            <flux:error name="form.code" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model.live="form.is_active" />
            <flux:error name="form.is_active" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
