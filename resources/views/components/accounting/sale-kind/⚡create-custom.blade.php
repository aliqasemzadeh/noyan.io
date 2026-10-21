<?php

use App\Livewire\Forms\CustomSaleKindForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public CustomSaleKindForm $form;

    public function save(): void
    {
        $this->form->store();

        $this->dispatch('panels.accounting.sale-kind.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.custom_sale_kind_created'));
    }
};
?>

<flux:modal name="accounting.sale-kind.create-custom" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_custom_sale_kind') }}</flux:heading>
        <flux:text class="mt-2">{{ __('general.create_custom_sale_kind_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" clearable />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.sale_kind_code') }}</flux:label>
            <flux:input wire:model="form.code" clearable dir="ltr" placeholder="optional-code" />
            <flux:description>{{ __('general.sale_kind_code_hint') }}</flux:description>
            <flux:error name="form.code" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
