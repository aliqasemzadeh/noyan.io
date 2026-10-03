<?php

use App\Enums\Catalog\ProductType;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Product;
use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $brandFilter = '';

    #[Url]
    public string $statusFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedBrandFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.catalog.product.index.table')]
    public function refreshTable(): void
    {
        unset($this->products);
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('sku', 'like', $search)
                        ->orWhere('barcode', 'like', $search)
                        ->orWhere('slug', 'like', $search);
                });
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category_id', $this->categoryFilter))
            ->when($this->brandFilter !== '', fn ($query) => $query->where('brand_id', $this->brandFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(15);
    }

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
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function formatCreatedAt(Product $product): string
    {
        return Jalalian::fromDateTime($product->created_at)->format('Y/m/d H:i');
    }

    public function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }
};
?>

<x-slot name="title">{{ __('general.products') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.products') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.products') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.products_page_hint') }}</flux:text>
            </div>

            <flux:modal.trigger name="catalog.product.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_product') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if (auth()->user()->current_business_id === null)
        <flux:callout icon="building" variant="secondary">
            {{ __('general.no_business_yet') }}
        </flux:callout>
    @endif

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-5">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />

            <flux:select wire:model.live="typeFilter" searchable variant="listbox" placeholder="{{ __('general.all_product_types') }}">
                <flux:select.option value="">{{ __('general.all_product_types') }}</flux:select.option>
                @foreach (ProductType::sorted() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="filter-product-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="categoryFilter" searchable variant="listbox" placeholder="{{ __('general.all_categories') }}">
                <flux:select.option value="">{{ __('general.all_categories') }}</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option value="{{ $category->id }}" wire:key="filter-product-category-{{ $category->id }}">
                        {{ $category->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="brandFilter" searchable variant="listbox" placeholder="{{ __('general.all_brands') }}">
                <flux:select.option value="">{{ __('general.all_brands') }}</flux:select.option>
                @foreach ($this->brands as $brand)
                    <flux:select.option value="{{ $brand->id }}" wire:key="filter-product-brand-{{ $brand->id }}">
                        {{ $brand->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" searchable variant="listbox" placeholder="{{ __('general.all_statuses') }}">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('general.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('general.inactive') }}</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->products">
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.sku') }}</flux:table.column>
                <flux:table.column>{{ __('general.product_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.category') }}</flux:table.column>
                <flux:table.column>{{ __('general.brand') }}</flux:table.column>
                <flux:table.column>{{ __('general.purchase_price') }}</flux:table.column>
                <flux:table.column>{{ __('general.sale_price') }}</flux:table.column>
                <flux:table.column>{{ __('general.stock_quantity') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_active') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $product->name }}</div>
                            <flux:text size="sm" class="mt-0.5 block text-zinc-500" dir="ltr">{{ $product->unit->label() }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $product->sku }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$product->type->badgeColor()">
                                {{ $product->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $product->category?->name ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $product->brand?->name ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $this->formatAmount((string) $product->purchase_price) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $this->formatAmount((string) $product->sale_price) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">
                                {{ $product->track_inventory ? $this->formatAmount((string) $product->stock_quantity) : '—' }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$product->is_active ? 'green' : 'zinc'">
                                {{ $product->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.catalog.product.edit.assign-data', { product: {{ $product->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.catalog.product.delete.assign-data', { product: {{ $product->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10">
                            {{ __('general.no_products') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.catalog.product.create :key="'catalog-product-create'" />
    <livewire:accounting.catalog.product.edit :key="'catalog-product-edit'" />
    <livewire:accounting.catalog.product.delete :key="'catalog-product-delete'" />
</div>
