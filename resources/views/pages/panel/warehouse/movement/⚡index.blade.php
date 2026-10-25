<?php

use App\Enums\Business\BusinessPermission;
use App\Enums\Catalog\StockMovementType;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Catalog\Warehouse;
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
    public string $warehouseFilter = '';

    #[Url]
    public string $typeFilter = '';

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

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.warehouse.stock.refresh')]
    public function refreshTable(): void
    {
        unset($this->movements);
    }

    /**
     * @return LengthAwarePaginator<int, ProductStockMovement>
     */
    #[Computed]
    public function movements(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return ProductStockMovement::query()
            ->with(['product', 'warehouse', 'project', 'costCenter', 'createdBy'])
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->warehouseFilter !== '', fn ($query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->when($this->typeFilter !== '', fn ($query) => $query->where('movement_type', $this->typeFilter))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('note', 'like', $search)
                        ->orWhereHas('product', fn ($q) => $q->where('name', 'like', $search)->orWhere('sku', 'like', $search));
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
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

    public function formatOccurredAt(ProductStockMovement $movement): string
    {
        return Jalalian::fromDateTime($movement->occurred_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.stock_movements') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('warehouse.dashboard')" wire:navigate>{{ __('general.warehouse') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('general.stock_movements') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="mt-4">
            <flux:heading size="xl">{{ __('general.stock_movements') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.stock_movements_hint') }}</flux:text>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="search" placeholder="{{ __('general.search') }}..." clearable />
            <flux:select wire:model.live="warehouseFilter" searchable variant="listbox">
                <flux:select.option value="">{{ __('general.all_warehouses') }}</flux:select.option>
                @foreach ($this->warehouseOptions as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="typeFilter" searchable variant="listbox">
                <flux:select.option value="">{{ __('general.all_types') }}</flux:select.option>
                @foreach (StockMovementType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->movements">
            <flux:table.columns>
                <flux:table.column>{{ __('general.date') }}</flux:table.column>
                <flux:table.column>{{ __('general.product') }}</flux:table.column>
                <flux:table.column>{{ __('general.warehouse') }}</flux:table.column>
                <flux:table.column>{{ __('general.type') }}</flux:table.column>
                <flux:table.column>{{ __('general.quantity') }}</flux:table.column>
                <flux:table.column>{{ __('general.balance_after') }}</flux:table.column>
                <flux:table.column>{{ __('general.note') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->movements as $movement)
                    <flux:table.row wire:key="movement-{{ $movement->id }}">
                        <flux:table.cell>{{ $this->formatOccurredAt($movement) }}</flux:table.cell>
                        <flux:table.cell>{{ $movement->product?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $movement->warehouse?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $movement->movement_type->label() }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ rtrim(rtrim(number_format((float) $movement->quantity, 4, '.', ''), '0'), '.') }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ rtrim(rtrim(number_format((float) $movement->balance_after, 4, '.', ''), '0'), '.') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="space-y-1">
                                <div>{{ $movement->note }}</div>
                                @if ($movement->project)
                                    <flux:badge size="sm" color="violet">{{ $movement->project->name }}</flux:badge>
                                @endif
                                @if ($movement->costCenter)
                                    <flux:badge size="sm" color="zinc">{{ $movement->costCenter->name }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">{{ __('general.no_stock_movements') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
