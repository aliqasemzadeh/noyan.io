<?php

use App\Enums\Catalog\ProductType;
use App\Enums\Catalog\ProductUnit;
use App\Livewire\Forms\Catalog\ProductForm;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductCategory;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ProductForm $form;

    public ?Product $product = null;

    /**
     * @return Collection<int, ProductCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return ProductCategory::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Brand>
     */
    #[Computed]
    public function brands(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Brand::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[On('panels.accounting.catalog.product.edit.assign-data')]
    public function assignData(Product $product): void
    {
        abort_unless(
            (int) $product->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->product = $product;
        $this->form->setModel($this->product);
        $this->resetValidation();

        Flux::modal('catalog.product.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();
        $this->reset('product');

        $this->dispatch('panels.accounting.catalog.product.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.product_updated'));
    }
};
?>

<flux:modal name="catalog.product.edit" flyout position="right" class="md:w-xl space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_product') }}</flux:heading>
    </div>

    @if ($product)
        <flux:callout icon="package" variant="secondary" inline>
            {{ $product->name }} — {{ $product->sku }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.product_type') }}</flux:label>
            <flux:select wire:model.live="form.type" searchable variant="listbox">
                @foreach (ProductType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="edit-product-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" placeholder="{{ __('general.product_name_placeholder') }}" />
            <flux:error name="form.name" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.sku') }}</flux:label>
                <flux:input wire:model="form.sku" dir="ltr" />
                <flux:error name="form.sku" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.barcode') }}</flux:label>
                <flux:input wire:model="form.barcode" clearable dir="ltr" />
                <flux:error name="form.barcode" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('general.slug') }}</flux:label>
            <flux:input wire:model="form.slug" clearable dir="ltr" />
            <flux:error name="form.slug" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.category') }}</flux:label>
                <flux:select wire:model="form.category_id" searchable variant="listbox" clearable>
                    <flux:select.option value="">{{ __('general.select_category') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}" wire:key="edit-product-category-{{ $category->id }}">
                            {{ $category->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.category_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.brand') }}</flux:label>
                <flux:select wire:model="form.brand_id" searchable variant="listbox" clearable>
                    <flux:select.option value="">{{ __('general.select_brand') }}</flux:select.option>
                    @foreach ($this->brands as $brand)
                        <flux:select.option value="{{ $brand->id }}" wire:key="edit-product-brand-{{ $brand->id }}">
                            {{ $brand->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.brand_id" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('general.unit') }}</flux:label>
            <flux:select wire:model="form.unit" searchable variant="listbox">
                @foreach (ProductUnit::cases() as $unit)
                    <flux:select.option value="{{ $unit->value }}" wire:key="edit-product-unit-{{ $unit->value }}">
                        {{ $unit->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.unit" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.purchase_price') }}</flux:label>
                <flux:input wire:model="form.purchase_price" dir="ltr" />
                <flux:error name="form.purchase_price" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.sale_price') }}</flux:label>
                <flux:input wire:model="form.sale_price" dir="ltr" />
                <flux:error name="form.sale_price" />
            </flux:field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.min_stock') }}</flux:label>
                <flux:input wire:model="form.min_stock" dir="ltr" :disabled="! $form->track_inventory" />
                <flux:error name="form.min_stock" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.max_stock') }}</flux:label>
                <flux:input wire:model="form.max_stock" clearable dir="ltr" :disabled="! $form->track_inventory" />
                <flux:error name="form.max_stock" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('general.tax_rate') }}</flux:label>
            <flux:input wire:model="form.tax_rate" clearable dir="ltr" />
            <flux:error name="form.tax_rate" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.description') }}</flux:label>
            <flux:textarea wire:model="form.description" rows="3" />
            <flux:error name="form.description" />
        </flux:field>

        <div class="space-y-3">
            <flux:field variant="inline">
                <flux:label>{{ __('general.track_inventory') }}</flux:label>
                <flux:switch wire:model.live="form.track_inventory" :disabled="$form->type === 'service'" />
                <flux:error name="form.track_inventory" />
            </flux:field>

            <flux:field variant="inline">
                <flux:label>{{ __('general.is_active') }}</flux:label>
                <flux:switch wire:model.live="form.is_active" />
                <flux:error name="form.is_active" />
            </flux:field>
        </div>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
