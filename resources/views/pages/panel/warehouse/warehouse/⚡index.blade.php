<?php

use App\Actions\Inventory\SetDefaultWarehouseAction;
use App\Enums\Business\BusinessPermission;
use App\Enums\Catalog\WarehouseType;
use App\Models\Catalog\Warehouse;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
    public string $typeFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::WarehouseView), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.warehouse.warehouse.index.table')]
    public function refreshTable(): void
    {
        unset($this->warehouses);
    }

    /**
     * @return LengthAwarePaginator<int, Warehouse>
     */
    #[Computed]
    public function warehouses(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Warehouse::query()
            ->withCount('stocks')
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('code', 'like', $search);
                });
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15);
    }

    public function setDefault(int $warehouseId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::WarehouseEdit), 403);

        $business = Auth::user()?->currentBusiness;
        abort_if($business === null, 403);

        $warehouse = Warehouse::query()
            ->where('business_id', $business->id)
            ->whereKey($warehouseId)
            ->firstOrFail();

        app(SetDefaultWarehouseAction::class)->handle($business, $warehouse);

        unset($this->warehouses);
        Flux::toast(__('general.default_warehouse_updated'));
    }

    public function delete(int $warehouseId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::WarehouseDelete), 403);

        $warehouse = Warehouse::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->whereKey($warehouseId)
            ->firstOrFail();

        if ($warehouse->is_default) {
            Flux::toast(__('general.cannot_delete_default_warehouse'), variant: 'danger');

            return;
        }

        if ($warehouse->stocks()->where('physical_quantity', '>', 0)->exists()) {
            Flux::toast(__('general.cannot_delete_warehouse_with_stock'), variant: 'danger');

            return;
        }

        $warehouse->delete();
        unset($this->warehouses);
        Flux::toast(__('general.warehouse_deleted'));
    }
};
?>

<x-slot name="title">{{ __('general.warehouses') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('warehouse.dashboard')" wire:navigate>
                {{ __('general.warehouse') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('general.warehouses') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.warehouses') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.warehouses_page_hint') }}</flux:text>
            </div>

            @if (auth()->user()?->canInBusiness(BusinessPermission::WarehouseCreate))
                <flux:modal.trigger name="warehouse.create">
                    <flux:button variant="primary" color="teal" icon="plus">
                        {{ __('general.create_warehouse') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="search" placeholder="{{ __('general.search') }}..." clearable />
            <flux:select wire:model.live="typeFilter" searchable variant="listbox" placeholder="{{ __('general.all_types') }}">
                <flux:select.option value="">{{ __('general.all_types') }}</flux:select.option>
                @foreach (WarehouseType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->warehouses">
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.code') }}</flux:table.column>
                <flux:table.column>{{ __('general.warehouse_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->warehouses as $warehouse)
                    <flux:table.row wire:key="warehouse-{{ $warehouse->id }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span>{{ $warehouse->name }}</span>
                                @if ($warehouse->is_default)
                                    <flux:badge size="sm" color="teal">{{ __('general.default') }}</flux:badge>
                                @endif
                                @if ($warehouse->is_sellable)
                                    <flux:badge size="sm" color="sky">{{ __('general.sellable') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell dir="ltr">{{ $warehouse->code }}</flux:table.cell>
                        <flux:table.cell>{{ $warehouse->type->label() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$warehouse->is_active ? 'green' : 'zinc'">
                                {{ $warehouse->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                @if (auth()->user()?->canInBusiness(BusinessPermission::WarehouseEdit) && ! $warehouse->is_default)
                                    <flux:tooltip content="{{ __('general.set_as_default') }}">
                                        <flux:button size="xs" variant="primary" color="amber" icon="star" icon:variant="outline" wire:click="setDefault({{ $warehouse->id }})" />
                                    </flux:tooltip>
                                @endif
                                @if (auth()->user()?->canInBusiness(BusinessPermission::WarehouseEdit))
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button size="xs" variant="primary" color="blue" icon="pencil" icon:variant="outline" wire:click="$dispatch('panels.warehouse.warehouse.edit.assign-data', { warehouseId: {{ $warehouse->id }} })" />
                                    </flux:tooltip>
                                @endif
                                @if (auth()->user()?->canInBusiness(BusinessPermission::WarehouseDelete) && ! $warehouse->is_default)
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button size="xs" variant="primary" color="red" icon="trash" icon:variant="outline" wire:click="delete({{ $warehouse->id }})" wire:confirm="{{ __('general.are_you_sure') }}" />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('general.no_warehouses') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:warehouse.warehouse.create :key="'warehouse-create'" />
    <livewire:warehouse.warehouse.edit :key="'warehouse-edit'" />
</div>
