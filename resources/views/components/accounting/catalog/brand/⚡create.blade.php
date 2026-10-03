<?php

use App\Livewire\Forms\Catalog\BrandForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public BrandForm $form;

    public function save(): void
    {
        $brand = $this->form->store();

        $this->dispatch('panels.accounting.catalog.brand.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.brand_created', ['name' => $brand->name]));
    }
};
?>

<flux:modal name="catalog.brand.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_brand') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_brand_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" placeholder="{{ __('general.brand_name_placeholder') }}" />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.slug') }}</flux:label>
            <flux:input wire:model="form.slug" placeholder="{{ __('general.slug_auto_hint') }}" clearable dir="ltr" />
            <flux:error name="form.slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.logo') }}</flux:label>
            <flux:input wire:model="form.logo" placeholder="{{ __('general.logo_path_placeholder') }}" clearable dir="ltr" />
            <flux:error name="form.logo" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.description') }}</flux:label>
            <flux:textarea wire:model="form.description" rows="3" />
            <flux:error name="form.description" />
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
