<?php

use App\Actions\Inventory\ResolveStockCountLineAction;
use App\Enums\Business\BusinessPermission;
use App\Enums\Catalog\StockCountResolution;
use App\Enums\Catalog\StockCountStatus;
use App\Models\Catalog\StockCountLine;
use App\Models\Catalog\Warehouse;
use App\Support\Warehouse\Quantity;
use Flux\Flux;
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

    #[Url]
    public string $warehouseFilter = '';

    #[Url]
    public bool $openOnly = true;

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockCount), 403);
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOpenOnly(): void
    {
        $this->resetPage();
    }

    #[On('panels.warehouse.documents.refresh')]
    public function refreshTable(): void
    {
        unset($this->lines);
    }

    public function resolveLine(int $lineId, bool $adjust): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockCount), 403);

        if ($adjust) {
            abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockAdjust), 403);
        }

        $line = StockCountLine::query()
            ->whereHas('stockCount', fn ($query) => $query->where('business_id', Auth::user()?->current_business_id))
            ->findOrFail($lineId);

        app(ResolveStockCountLineAction::class)->handle($line, Auth::user(), $adjust);
        unset($this->lines);
        Flux::toast(__('general.stock_count_resolved'));
    }

    /**
     * @return LengthAwarePaginator<int, StockCountLine>
     */
    #[Computed]
    public function lines(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return StockCountLine::query()
            ->with(['product:id,name,sku,unit', 'stockCount.warehouse:id,name'])
            ->whereHas('stockCount', function ($query) use ($businessId): void {
                $query->where('business_id', $businessId)
                    ->when($this->warehouseFilter !== '', fn ($query) => $query->where('warehouse_id', $this->warehouseFilter))
                    ->when($this->openOnly, fn ($query) => $query->where('status', StockCountStatus::Open));
            })
            ->when($this->openOnly, fn ($query) => $query->where('resolution', StockCountResolution::Pending))
            ->orderByDesc('id')
            ->paginate(15);
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

<x-slot name="title">{{ __('general.stock_counts') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('warehouse.dashboard')" wire:navigate>{{ __('general.warehouse') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('general.stock_counts') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="xl">{{ __('general.stock_counts') }}</flux:heading>
            <flux:button variant="primary" color="amber" icon="clipboard-list" wire:click="$dispatch('panels.warehouse.count.record.open')">
                {{ __('general.record_stock_count') }}
            </flux:button>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-2">
            <flux:select wire:model.live="warehouseFilter" searchable variant="listbox">
                <flux:select.option value="">{{ __('general.all_warehouses') }}</flux:select.option>
                @foreach ($this->warehouseOptions as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:checkbox wire:model.live="openOnly" label="{{ __('general.open_documents') }}" />
        </div>

        <flux:table :paginate="$this->lines">
            <flux:table.columns>
                <flux:table.column>{{ __('general.document_number') }}</flux:table.column>
                <flux:table.column>{{ __('general.warehouse') }}</flux:table.column>
                <flux:table.column>{{ __('general.product') }}</flux:table.column>
                <flux:table.column>{{ __('general.system_quantity') }}</flux:table.column>
                <flux:table.column>{{ __('general.counted_quantity') }}</flux:table.column>
                <flux:table.column>{{ __('general.difference') }}</flux:table.column>
                <flux:table.column>{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->lines as $line)
                    <flux:table.row wire:key="count-line-{{ $line->id }}">
                        <flux:table.cell dir="ltr">{{ $line->stockCount?->number }}</flux:table.cell>
                        <flux:table.cell>{{ $line->stockCount?->warehouse?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $line->product?->name }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ Quantity::format((string) $line->system_quantity) }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ Quantity::format((string) $line->counted_quantity) }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ Quantity::format((string) $line->difference) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($line->resolution === StockCountResolution::Pending)
                                <div class="flex flex-wrap gap-1">
                                    @if (auth()->user()?->canInBusiness(BusinessPermission::StockAdjust))
                                        <flux:tooltip content="{{ __('general.resolve_by_adjustment') }}">
                                            <flux:button size="xs" variant="primary" color="amber" icon="sliders-horizontal" wire:click="resolveLine({{ $line->id }}, true)" wire:confirm="{{ __('general.are_you_sure') }}" />
                                        </flux:tooltip>
                                    @endif
                                    <flux:tooltip content="{{ __('general.accept_system_quantity') }}">
                                        <flux:button size="xs" variant="primary" color="zinc" icon="check" wire:click="resolveLine({{ $line->id }}, false)" wire:confirm="{{ __('general.are_you_sure') }}" />
                                    </flux:tooltip>
                                </div>
                            @else
                                <flux:badge size="sm" color="zinc">{{ $line->resolution->label() }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">{{ __('general.no_stock_counts') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:warehouse.count.record :key="'warehouse-count-record'" />
</div>
