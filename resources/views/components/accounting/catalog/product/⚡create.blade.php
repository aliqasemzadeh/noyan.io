<?php

use App\Enums\Catalog\ProductType;
use App\Enums\Catalog\ProductUnit;
use App\Enums\CategoryType;
use App\Livewire\Forms\Catalog\ProductForm;
use App\Models\Catalog\Brand;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ProductForm $form;

    public string $categorySearch = '';

    public string $brandSearch = '';

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Category::query()
            ->availableToBusiness($businessId)
            ->ofType(CategoryType::Product)
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

    public function createCategory(): void
    {
        $name = trim($this->categorySearch);
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || mb_strlen($name) < 2) {
            throw ValidationException::withMessages([
                'form.category_id' => __('general.business_required'),
            ]);
        }

        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 1;

        while (
            Category::withTrashed()
                ->where('business_id', $businessId)
                ->where('type', CategoryType::Product)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        $category = Category::create([
            'business_id' => $businessId,
            'type' => CategoryType::Product,
            'name' => $name,
            'slug' => $slug,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Category::forgetBusinessCache($businessId, CategoryType::Product);

        $this->form->category_id = $category->id;
        $this->categorySearch = '';
        unset($this->categories);

        Flux::toast(__('general.product_category_created', ['name' => $category->name]));
    }

    public function createBrand(): void
    {
        $name = trim($this->brandSearch);
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || mb_strlen($name) < 2) {
            throw ValidationException::withMessages([
                'form.brand_id' => __('general.business_required'),
            ]);
        }

        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $suffix = 1;

        while (
            Brand::withTrashed()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        $brand = Brand::create([
            'business_id' => $businessId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);

        $this->form->brand_id = $brand->id;
        $this->brandSearch = '';
        unset($this->brands);

        Flux::toast(__('general.brand_created', ['name' => $brand->name]));
    }

    public function save(): void
    {
        $product = $this->form->store();

        $this->dispatch('panels.accounting.catalog.product.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.product_created', ['name' => $product->name]));
    }
};
?>

<flux:modal name="catalog.product.create" flyout position="right" class="md:w-xl space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_product') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_product_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.product_type') }}</flux:label>
            <flux:select wire:model.live="form.type" searchable variant="listbox">
                @foreach (ProductType::sorted() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="create-product-type-{{ $type->value }}">
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
                <flux:input wire:model="form.sku" placeholder="SSD-SP-512G" dir="ltr" />
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
            <flux:input wire:model="form.slug" placeholder="{{ __('general.slug_auto_hint') }}" clearable dir="ltr" />
            <flux:error name="form.slug" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.category') }}</flux:label>
                <flux:select wire:model="form.category_id" variant="combobox" clearable placeholder="{{ __('general.select_category') }}">
                    <x-slot name="input">
                        <flux:select.input wire:model="categorySearch" placeholder="{{ __('general.select_category') }}" />
                    </x-slot>
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}" wire:key="create-product-category-{{ $category->id }}">
                            {{ $category->name }}
                        </flux:select.option>
                    @endforeach
                    <flux:select.option.create wire:click="createCategory" min-length="2">
                        {{ __('general.create_category_option') }} "<span wire:text="categorySearch"></span>"
                    </flux:select.option.create>
                </flux:select>
                <flux:error name="form.category_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.brand') }}</flux:label>
                <flux:select wire:model="form.brand_id" variant="combobox" clearable placeholder="{{ __('general.select_brand') }}">
                    <x-slot name="input">
                        <flux:select.input wire:model="brandSearch" placeholder="{{ __('general.select_brand') }}" />
                    </x-slot>
                    @foreach ($this->brands as $brand)
                        <flux:select.option value="{{ $brand->id }}" wire:key="create-product-brand-{{ $brand->id }}">
                            {{ $brand->name }}
                        </flux:select.option>
                    @endforeach
                    <flux:select.option.create wire:click="createBrand" min-length="2">
                        {{ __('general.create_brand_option') }} "<span wire:text="brandSearch"></span>"
                    </flux:select.option.create>
                </flux:select>
                <flux:error name="form.brand_id" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('general.unit') }}</flux:label>
            <flux:select wire:model="form.unit" searchable variant="listbox">
                @foreach (ProductUnit::cases() as $unit)
                    <flux:select.option value="{{ $unit->value }}" wire:key="create-product-unit-{{ $unit->value }}">
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
                <flux:label>{{ __('general.stock_quantity') }}</flux:label>
                <flux:input wire:model="form.stock_quantity" dir="ltr" :disabled="! $form->track_inventory" />
                <flux:error name="form.stock_quantity" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.min_stock') }}</flux:label>
                <flux:input wire:model="form.min_stock" dir="ltr" :disabled="! $form->track_inventory" />
                <flux:error name="form.min_stock" />
            </flux:field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.max_stock') }}</flux:label>
                <flux:input wire:model="form.max_stock" clearable dir="ltr" :disabled="! $form->track_inventory" />
                <flux:error name="form.max_stock" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.tax_rate') }}</flux:label>
                <flux:input wire:model="form.tax_rate" clearable dir="ltr" placeholder="9" />
                <flux:error name="form.tax_rate" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('general.description') }}</flux:label>
            <flux:textarea wire:model="form.description" rows="3" />
            <flux:error name="form.description" />
        </flux:field>

        <div class="space-y-3">
            <flux:field variant="inline">
                <flux:label>{{ __('general.track_inventory') }}</flux:label>
                <flux:switch
                    wire:model.live="form.track_inventory"
                    :disabled="in_array($form->type, [ProductType::Service->value, ProductType::Digital->value], true)"
                />
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
