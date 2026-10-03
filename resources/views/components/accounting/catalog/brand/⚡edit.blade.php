<?php

use App\Livewire\Forms\Catalog\BrandForm;
use App\Models\Catalog\Brand;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public BrandForm $form;

    public ?Brand $brand = null;

    #[On('panels.accounting.catalog.brand.edit.assign-data')]
    public function assignData(Brand $brand): void
    {
        abort_unless(
            (int) $brand->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->brand = $brand;
        $this->form->setModel($this->brand);
        $this->resetValidation();

        Flux::modal('catalog.brand.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();
        $this->reset('brand');

        $this->dispatch('panels.accounting.catalog.brand.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.brand_updated'));
    }
};
?>

<flux:modal name="catalog.brand.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_brand') }}</flux:heading>
    </div>

    @if ($brand)
        <flux:callout icon="tags" variant="secondary" inline>
            {{ $brand->name }}
        </flux:callout>
    @endif

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
