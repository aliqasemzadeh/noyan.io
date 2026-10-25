<?php

use App\Enums\Business\BusinessPermission;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url]
    public string $warehouseFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockMovementView), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.warehouse.stock.refresh')]
    public function refreshTable(): void
    {
        unset($this->stocks);
    }

    /**
     * @return LengthAwarePaginator<int, InventoryStock>
     */
    #[Computed]
    public function stocks(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return InventoryStock::query()
            ->with(['product', 'warehouse'])
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->warehouseFilter !== '', fn ($query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';
                $query->whereHas('product', function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('sku', 'like', $search);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouseOptions(): Collection
    {
        return Warehouse::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
};
?>

<x-slot name="title">{{ __('general.inventory_stocks') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('warehouse.dashboard')" wire:navigate>{{ __('general.warehouse') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('general.inventory_stocks') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('general.inventory_stocks') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.inventory_stocks_hint') }}</flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (auth()->user()?->canInBusiness(BusinessPermission::StockReceive))
                    <flux:button size="sm" variant="primary" color="teal" icon="arrow-down-to-line" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'receive' })">
                        {{ __('general.stock_receive') }}
                    </flux:button>
                @endif
                @if (auth()->user()?->canInBusiness(BusinessPermission::StockIssue))
                    <flux:button size="sm" variant="primary" color="rose" icon="arrow-up-from-line" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'issue' })">
                        {{ __('general.stock_issue') }}
                    </flux:button>
                @endif
                @if (auth()->user()?->canInBusiness(BusinessPermission::StockTransfer))
                    <flux:button size="sm" variant="primary" color="sky" icon="arrow-left-right" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'transfer' })">
                        {{ __('general.stock_transfer') }}
                    </flux:button>
                @endif
                @if (auth()->user()?->canInBusiness(BusinessPermission::StockAdjust))
                    <flux:button size="sm" variant="primary" color="amber" icon="sliders-horizontal" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'adjust' })">
                        {{ __('general.stock_adjust') }}
                    </flux:button>
                @endif
                @if (auth()->user()?->canInBusiness(BusinessPermission::StockAllocate))
                    <flux:button size="sm" variant="primary" color="violet" icon="folder-kanban" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'allocate' })">
                        {{ __('general.stock_allocate') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="search" placeholder="{{ __('general.search') }}..." clearable />
            <flux:select wire:model.live="warehouseFilter" searchable variant="listbox" placeholder="{{ __('general.all_warehouses') }}">
                <flux:select.option value="">{{ __('general.all_warehouses') }}</flux:select.option>
                @foreach ($this->warehouseOptions as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->stocks">
            <flux:table.columns>
                <flux:table.column>{{ __('general.product') }}</flux:table.column>
                <flux:table.column>{{ __('general.warehouse') }}</flux:table.column>
                <flux:table.column>{{ __('general.physical_quantity') }}</flux:table.column>
                <flux:table.column>{{ __('general.reserved_quantity') }}</flux:table.column>
                <flux:table.column>{{ __('general.available_quantity') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->stocks as $stock)
                    <flux:table.row wire:key="stock-{{ $stock->id }}">
                        <flux:table.cell>{{ $stock->product?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $stock->warehouse?->name }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ rtrim(rtrim(number_format((float) $stock->physical_quantity, 4, '.', ''), '0'), '.') }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ rtrim(rtrim(number_format((float) $stock->reserved_quantity, 4, '.', ''), '0'), '.') }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ rtrim(rtrim(number_format((float) $stock->availableQuantity(), 4, '.', ''), '0'), '.') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('general.no_inventory_stocks') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:warehouse.stock.operation :key="'warehouse-stock-operation'" />
</div>
