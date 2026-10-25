<?php

use App\Livewire\Forms\CustomSettlementTypeForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public CustomSettlementTypeForm $form;

    public function save(): void
    {
        $this->form->store();

        $this->dispatch('panels.accounting.settlement-type.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.custom_settlement_type_created'));
    }
};
?>

<flux:modal name="accounting.settlement-type.create-custom" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_custom_settlement_type') }}</flux:heading>
        <flux:text class="mt-2">{{ __('general.create_custom_settlement_type_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" clearable />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.settlement_type_code') }}</flux:label>
            <flux:input wire:model="form.code" clearable dir="ltr" placeholder="optional-code" />
            <flux:description>{{ __('general.settlement_type_code_hint') }}</flux:description>
            <flux:error name="form.code" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
