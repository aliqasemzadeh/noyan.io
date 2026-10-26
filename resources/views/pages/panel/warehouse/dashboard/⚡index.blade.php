<?php

use App\Actions\Inventory\AcceptInboundDiscrepancyAction;
use App\Actions\Inventory\ConfirmOutboundDispatchAction;
use App\Actions\Inventory\ProgressStockDocumentAction;
use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Enums\Business\BusinessPermission;
use App\Enums\Catalog\StockDocumentPriority;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Catalog\StockDocument;
use App\Models\Catalog\Warehouse;
use App\Support\LocaleDate;
use App\Support\Warehouse\Quantity;
use App\Support\Warehouse\WarehouseDashboardData;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $warehouseId = '';

    public string $lookup = '';

    public int $trendDays = 7;

    public int $deadStockDays = 90;

    /** @var list<array{date: string, inbound: float, outbound: float}> */
    public array $trendData = [];

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        $stored = session('warehouse.active_id');
        $this->warehouseId = filled($stored) ? (string) $stored : '';
        $this->guardWarehouse();
        $this->refreshTrend();
    }

    public function updatedWarehouseId(): void
    {
        $this->guardWarehouse();
        session(['warehouse.active_id' => $this->warehouseId !== '' ? (int) $this->warehouseId : null]);
        $this->resetPage('outboundPage');
        $this->resetPage('inboundPage');
        $this->resetPage('reorderPage');
        $this->resetPage('deadPage');
        $this->forgetDashboard();
    }

    public function updatedLookup(): void
    {
        unset($this->lookupMatches);
    }

    public function setTrendDays(int $days): void
    {
        $this->trendDays = in_array($days, [7, 30], true) ? $days : 7;
        $this->refreshTrend();
    }

    public function setDeadStockDays(int $days): void
    {
        $this->deadStockDays = in_array($days, [90, 180], true) ? $days : 90;
        $this->resetPage('deadPage');
        unset($this->deadStock);
    }

    #[On('panels.warehouse.documents.refresh')]
    #[On('panels.warehouse.stock.refresh')]
    public function refreshFromActions(): void
    {
        $this->forgetDashboard();
    }

    public function confirmDispatch(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockIssue), 403);
        app(ConfirmOutboundDispatchAction::class)->handle($this->outboundDocument($documentId), Auth::user());
        $this->forgetDashboard();
        Flux::toast(__('general.dispatch_confirmed'));
    }

    public function markPacked(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockIssue), 403);
        app(ProgressStockDocumentAction::class)->markPacked($this->outboundDocument($documentId));
        $this->forgetDashboard();
        Flux::toast(__('general.dispatch_marked_packed'));
    }

    public function receiveTransfer(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockTransfer), 403);

        $document = StockDocument::query()
            ->where('business_id', $this->businessId())
            ->where('type', StockDocumentType::Transfer)
            ->findOrFail($documentId);

        app(ReceiveStockTransferAction::class)->handle($document, Auth::user());
        $this->forgetDashboard();
        Flux::toast(__('general.transfer_received'));
    }

    public function acceptDiscrepancy(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockReceive), 403);

        $document = StockDocument::query()
            ->where('business_id', $this->businessId())
            ->where('type', StockDocumentType::Inbound)
            ->findOrFail($documentId);

        app(AcceptInboundDiscrepancyAction::class)->handle($document);
        $this->forgetDashboard();
        Flux::toast(__('general.discrepancy_accepted'));
    }

    /**
     * @return array{dispatches: int, receipts: int, low_stock: int, transfers: int, discrepancies: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $businessId = $this->businessId();

        if ($businessId === null) {
            return ['dispatches' => 0, 'receipts' => 0, 'low_stock' => 0, 'transfers' => 0, 'discrepancies' => 0];
        }

        return app(WarehouseDashboardData::class)->kpis($businessId, $this->selectedWarehouseId());
    }

    /**
     * @return LengthAwarePaginator<int, StockDocument>
     */
    #[Computed]
    public function outboundQueue(): LengthAwarePaginator
    {
        return StockDocument::query()
            ->with(['party:id,name,legal_name', 'warehouse:id,name'])
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->where('business_id', $this->businessId() ?? 0)
            ->where('type', StockDocumentType::Outbound)
            ->whereIn('status', [StockDocumentStatus::PendingPick, StockDocumentStatus::Packed])
            ->when($this->selectedWarehouseId() !== null, fn ($query) => $query->where('warehouse_id', $this->selectedWarehouseId()))
            ->orderByRaw("case when priority = 'urgent' then 0 else 1 end")
            ->orderBy('id')
            ->paginate(8, pageName: 'outboundPage');
    }

    /**
     * @return LengthAwarePaginator<int, StockDocument>
     */
    #[Computed]
    public function inboundQueue(): LengthAwarePaginator
    {
        return StockDocument::query()
            ->with(['party:id,name,legal_name', 'warehouse:id,name', 'invoice:id,invoice_number'])
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->where('business_id', $this->businessId() ?? 0)
            ->where('type', StockDocumentType::Inbound)
            ->whereIn('status', [StockDocumentStatus::Expected, StockDocumentStatus::Discrepancy])
            ->when($this->selectedWarehouseId() !== null, fn ($query) => $query->where('warehouse_id', $this->selectedWarehouseId()))
            ->orderBy('expected_at')
            ->orderBy('id')
            ->paginate(8, pageName: 'inboundPage');
    }

    /**
     * @return LengthAwarePaginator<int, InventoryStock>
     */
    #[Computed]
    public function reorderAlerts(): LengthAwarePaginator
    {
        return InventoryStock::query()
            ->with(['product:id,name,sku,unit,min_stock', 'warehouse:id,name'])
            ->where('business_id', $this->businessId() ?? 0)
            ->when($this->selectedWarehouseId() !== null, fn ($query) => $query->where('warehouse_id', $this->selectedWarehouseId()))
            ->whereHas('product', fn ($query) => $query->where('track_inventory', true)->where('min_stock', '>', 0))
            ->whereRaw('physical_quantity - reserved_quantity < (select min_stock from products where products.id = inventory_stocks.product_id)')
            ->orderBy('physical_quantity')
            ->paginate(8, pageName: 'reorderPage');
    }

    /**
     * @return LengthAwarePaginator<int, InventoryStock>
     */
    #[Computed]
    public function deadStock(): LengthAwarePaginator
    {
        $businessId = $this->businessId() ?? 0;
        $moved = ProductStockMovement::query()
            ->where('business_id', $businessId)
            ->where('occurred_at', '>=', now()->subDays($this->deadStockDays))
            ->whereIn('movement_type', [
                StockMovementType::Sale,
                StockMovementType::TransferOut,
                StockMovementType::ReturnOut,
                StockMovementType::Consume,
            ])
            ->when($this->selectedWarehouseId() !== null, fn ($query) => $query->where('warehouse_id', $this->selectedWarehouseId()))
            ->distinct()
            ->pluck('product_id');

        return InventoryStock::query()
            ->with(['product:id,name,sku,unit', 'warehouse:id,name'])
            ->where('business_id', $businessId)
            ->where('physical_quantity', '>', 0)
            ->when($this->selectedWarehouseId() !== null, fn ($query) => $query->where('warehouse_id', $this->selectedWarehouseId()))
            ->when($moved->isNotEmpty(), fn ($query) => $query->whereNotIn('product_id', $moved))
            ->orderByDesc('physical_quantity')
            ->paginate(8, pageName: 'deadPage');
    }

    /**
     * @return list<array{name: string, sku: string, moves: int}>
     */
    #[Computed]
    public function fastMovers(): array
    {
        $businessId = $this->businessId();

        if ($businessId === null) {
            return [];
        }

        return app(WarehouseDashboardData::class)->fastMovers($businessId, $this->selectedWarehouseId());
    }

    /**
     * @return list<array{name: string, quantity: float}>
     */
    #[Computed]
    public function distribution(): array
    {
        $businessId = $this->businessId();

        if ($businessId === null) {
            return [];
        }

        return app(WarehouseDashboardData::class)->distribution($businessId, $this->selectedWarehouseId());
    }

    /**
     * @return list<array{name: string, sku: string, unit: string, rows: list<array{warehouse: string, physical: string, reserved: string, available: string}>}>
     */
    #[Computed]
    public function lookupMatches(): array
    {
        $term = trim($this->lookup);
        $businessId = $this->businessId();

        if ($term === '' || $businessId === null) {
            return [];
        }

        $columns = ['id', 'name', 'sku', 'barcode', 'unit'];
        $exact = Product::query()
            ->where('business_id', $businessId)
            ->where('track_inventory', true)
            ->where(fn ($query) => $query->where('barcode', $term)->orWhere('sku', $term))
            ->first($columns);

        $products = $exact !== null
            ? collect([$exact])
            : Product::query()
                ->where('business_id', $businessId)
                ->where('track_inventory', true)
                ->where('name', 'like', '%'.$term.'%')
                ->orderBy('name')
                ->limit(5)
                ->get($columns);

        if ($products->isEmpty()) {
            return [];
        }

        $stocks = InventoryStock::query()
            ->with('warehouse:id,name')
            ->whereIn('product_id', $products->pluck('id'))
            ->when($this->selectedWarehouseId() !== null, fn ($query) => $query->where('warehouse_id', $this->selectedWarehouseId()))
            ->get()
            ->groupBy('product_id');

        return $products->map(function (Product $product) use ($stocks): array {
            $rows = ($stocks->get($product->id) ?? collect())->map(function (InventoryStock $stock): array {
                $available = bcsub((string) $stock->physical_quantity, (string) $stock->reserved_quantity, 18);

                return [
                    'warehouse' => (string) ($stock->warehouse?->name ?? ''),
                    'physical' => Quantity::format((string) $stock->physical_quantity),
                    'reserved' => Quantity::format((string) $stock->reserved_quantity),
                    'available' => Quantity::format($available),
                ];
            })->all();

            return [
                'name' => $product->name,
                'sku' => (string) $product->sku,
                'unit' => $product->unit?->label() ?? '',
                'rows' => $rows,
            ];
        })->all();
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouseOptions(): Collection
    {
        return Warehouse::query()
            ->where('business_id', $this->businessId())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function refreshTrend(): void
    {
        $businessId = $this->businessId();
        $this->trendData = $businessId === null
            ? []
            : app(WarehouseDashboardData::class)->trend($businessId, $this->selectedWarehouseId(), $this->trendDays);
    }

    protected function forgetDashboard(): void
    {
        unset(
            $this->kpis,
            $this->outboundQueue,
            $this->inboundQueue,
            $this->reorderAlerts,
            $this->deadStock,
            $this->fastMovers,
            $this->distribution,
            $this->lookupMatches,
        );
        $this->refreshTrend();
    }

    protected function guardWarehouse(): void
    {
        if ($this->warehouseId === '') {
            return;
        }

        $exists = Warehouse::query()
            ->where('business_id', $this->businessId())
            ->whereKey($this->warehouseId)
            ->exists();

        if (! $exists) {
            $this->warehouseId = '';
        }
    }

    protected function businessId(): ?int
    {
        $id = Auth::user()?->current_business_id;

        return $id === null ? null : (int) $id;
    }

    protected function selectedWarehouseId(): ?int
    {
        return $this->warehouseId === '' ? null : (int) $this->warehouseId;
    }

    protected function outboundDocument(int $documentId): StockDocument
    {
        return StockDocument::query()
            ->where('business_id', $this->businessId())
            ->where('type', StockDocumentType::Outbound)
            ->findOrFail($documentId);
    }
};
?>

<x-slot name="title">{{ __('general.warehouse') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>{{ __('general.dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('general.warehouse') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="mt-4">
            <flux:heading size="xl">{{ __('general.warehouse') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.warehouse_dashboard_hint') }}</flux:text>
        </div>
    </div>

    <flux:card class="space-y-4">
        <div class="grid gap-3 lg:grid-cols-2">
            <flux:select wire:model.live="warehouseId" variant="listbox" searchable>
                <flux:select.option value="">{{ __('general.all_warehouses') }}</flux:select.option>
                @foreach ($this->warehouseOptions as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="lookup" icon="scan-barcode" placeholder="{{ __('general.quick_stock_lookup_placeholder') }}" clearable />
        </div>

        @if (trim($lookup) !== '')
            <div class="space-y-3">
                @forelse ($this->lookupMatches as $match)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="lookup-{{ $match['sku'] }}">
                        <div class="font-medium">{{ $match['name'] }} <span class="text-sm text-zinc-500" dir="ltr">{{ $match['sku'] }}</span></div>
                        <div class="mt-2 space-y-1 text-sm">
                            @forelse ($match['rows'] as $row)
                                <div class="flex flex-wrap gap-3">
                                    <span>{{ $row['warehouse'] }}</span>
                                    <span>{{ __('general.physical_quantity') }}: <span dir="ltr">{{ $row['physical'] }}</span> {{ $match['unit'] }}</span>
                                    <span>{{ __('general.reserved_quantity') }}: <span dir="ltr">{{ $row['reserved'] }}</span></span>
                                    <span>{{ __('general.deliverable_quantity') }}: <span dir="ltr">{{ $row['available'] }}</span></span>
                                </div>
                            @empty
                                <div>{{ __('general.no_inventory_stocks') }}</div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('general.stock_lookup_empty') }}</flux:text>
                @endforelse
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            @if (auth()->user()?->canInBusiness(BusinessPermission::StockReceive))
                <flux:button size="sm" variant="primary" color="teal" icon="arrow-down-to-line" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'receive' })">{{ __('general.stock_receive') }}</flux:button>
            @endif
            @if (auth()->user()?->canInBusiness(BusinessPermission::StockIssue))
                <flux:button size="sm" variant="primary" color="rose" icon="arrow-up-from-line" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'issue' })">{{ __('general.stock_issue') }}</flux:button>
            @endif
            @if (auth()->user()?->canInBusiness(BusinessPermission::StockTransfer))
                <flux:button size="sm" variant="primary" color="sky" icon="arrow-left-right" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'transfer' })">{{ __('general.stock_transfer') }}</flux:button>
            @endif
            @if (auth()->user()?->canInBusiness(BusinessPermission::StockAdjust))
                <flux:button size="sm" variant="primary" color="amber" icon="sliders-horizontal" wire:click="$dispatch('panels.warehouse.stock.operation.open', { mode: 'adjust' })">{{ __('general.stock_adjust') }}</flux:button>
            @endif
            @if (auth()->user()?->canInBusiness(BusinessPermission::StockCount))
                <flux:button size="sm" variant="primary" color="violet" icon="clipboard-list" wire:click="$dispatch('panels.warehouse.count.record.open')">{{ __('general.record_stock_count') }}</flux:button>
            @endif
        </div>
    </flux:card>

    @php($kpis = $this->kpis)
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('warehouse.dispatches.index') }}" wire:navigate class="block"><flux:card class="h-full"><flux:text>{{ __('general.pending_dispatches') }}</flux:text><flux:heading size="xl" class="mt-2">{{ $kpis['dispatches'] }}</flux:heading></flux:card></a>
        <a href="{{ route('warehouse.receipts.index') }}" wire:navigate class="block"><flux:card class="h-full"><flux:text>{{ __('general.expected_receipts') }}</flux:text><flux:heading size="xl" class="mt-2">{{ $kpis['receipts'] }}</flux:heading></flux:card></a>
        <a href="{{ route('warehouse.stocks.index') }}" wire:navigate class="block"><flux:card class="h-full"><flux:text>{{ __('general.low_stock_items') }}</flux:text><flux:heading size="xl" class="mt-2">{{ $kpis['low_stock'] }}</flux:heading></flux:card></a>
        <a href="{{ route('warehouse.transfers.index') }}" wire:navigate class="block"><flux:card class="h-full"><flux:text>{{ __('general.pending_transfers') }}</flux:text><flux:heading size="xl" class="mt-2">{{ $kpis['transfers'] }}</flux:heading></flux:card></a>
        <a href="{{ route('warehouse.counts.index') }}" wire:navigate class="block"><flux:card class="h-full"><flux:text>{{ __('general.stock_count_discrepancies') }}</flux:text><flux:heading size="xl" class="mt-2">{{ $kpis['discrepancies'] }}</flux:heading></flux:card></a>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <flux:card class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.outbound_queue') }}</flux:heading>
                <a href="{{ route('warehouse.dispatches.index') }}" wire:navigate class="text-sm text-teal-700 dark:text-teal-400">{{ __('general.dispatches') }}</a>
            </div>
            <flux:table :paginate="$this->outboundQueue">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.document_number') }}</flux:table.column>
                    <flux:table.column>{{ __('general.counterparty') }}</flux:table.column>
                    <flux:table.column>{{ __('general.quantity') }}</flux:table.column>
                    <flux:table.column>{{ __('general.status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->outboundQueue as $document)
                        <flux:table.row wire:key="dash-out-{{ $document->id }}">
                            <flux:table.cell dir="ltr">{{ $document->number }}</flux:table.cell>
                            <flux:table.cell>{{ $document->counterpartyLabel() }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ Quantity::format((string) $document->lines_sum_quantity) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$document->priority === StockDocumentPriority::Urgent ? 'rose' : $document->status->badgeColor()">{{ $document->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-1">
                                    <flux:tooltip content="{{ __('general.print_pick_list') }}">
                                        <flux:button size="xs" variant="primary" color="zinc" icon="printer" :href="route('warehouse.dispatches.pick-list', $document)" target="_blank" />
                                    </flux:tooltip>
                                    @if (auth()->user()?->canInBusiness(BusinessPermission::StockIssue))
                                        @if ($document->status === StockDocumentStatus::PendingPick)
                                            <flux:tooltip content="{{ __('general.mark_packed') }}">
                                                <flux:button size="xs" variant="primary" color="sky" icon="package" wire:click="markPacked({{ $document->id }})" />
                                            </flux:tooltip>
                                        @endif
                                        <flux:tooltip content="{{ __('general.confirm_dispatch') }}">
                                            <flux:button size="xs" variant="primary" color="teal" icon="log-out" wire:click="confirmDispatch({{ $document->id }})" wire:confirm="{{ __('general.are_you_sure') }}" />
                                        </flux:tooltip>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="5">{{ __('general.no_open_dispatches') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.inbound_queue') }}</flux:heading>
                <a href="{{ route('warehouse.receipts.index') }}" wire:navigate class="text-sm text-teal-700 dark:text-teal-400">{{ __('general.receipts') }}</a>
            </div>
            <flux:table :paginate="$this->inboundQueue">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.document_number') }}</flux:table.column>
                    <flux:table.column>{{ __('general.counterparty') }}</flux:table.column>
                    <flux:table.column>{{ __('general.expected_at') }}</flux:table.column>
                    <flux:table.column>{{ __('general.status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->inboundQueue as $document)
                        <flux:table.row wire:key="dash-in-{{ $document->id }}">
                            <flux:table.cell>
                                <div dir="ltr">{{ $document->number }}</div>
                                @if ($document->invoice)
                                    <div class="text-xs text-zinc-500" dir="ltr">{{ $document->invoice->invoice_number }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $document->counterpartyLabel() }}</flux:table.cell>
                            <flux:table.cell>{{ LocaleDate::formatDate($document->expected_at) }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$document->status->badgeColor()">{{ $document->status->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>
                                @if (auth()->user()?->canInBusiness(BusinessPermission::StockReceive) && $document->status === StockDocumentStatus::Expected)
                                    <div class="flex gap-1">
                                        <flux:tooltip content="{{ __('general.confirm_receipt') }}">
                                            <flux:button size="xs" variant="primary" color="teal" icon="log-in" wire:click="$dispatch('panels.warehouse.receipt.confirm.open', { documentId: {{ $document->id }} })" />
                                        </flux:tooltip>
                                        <flux:tooltip content="{{ __('general.record_discrepancy') }}">
                                            <flux:button size="xs" variant="primary" color="amber" icon="triangle-alert" wire:click="$dispatch('panels.warehouse.receipt.confirm.open', { documentId: {{ $document->id }} })" />
                                        </flux:tooltip>
                                    </div>
                                @endif
                                @if (auth()->user()?->canInBusiness(BusinessPermission::StockReceive) && $document->status === StockDocumentStatus::Discrepancy)
                                    <flux:tooltip content="{{ __('general.accept_discrepancy') }}">
                                        <flux:button size="xs" variant="primary" color="rose" icon="check" wire:click="acceptDiscrepancy({{ $document->id }})" wire:confirm="{{ __('general.are_you_sure') }}" />
                                    </flux:tooltip>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="5">{{ __('general.no_open_receipts') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('general.reorder_alerts') }}</flux:heading>
            <flux:table :paginate="$this->reorderAlerts">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.sku') }}</flux:table.column>
                    <flux:table.column>{{ __('general.product') }}</flux:table.column>
                    <flux:table.column>{{ __('general.warehouse') }}</flux:table.column>
                    <flux:table.column>{{ __('general.physical_quantity') }}</flux:table.column>
                    <flux:table.column>{{ __('general.reserved_quantity') }}</flux:table.column>
                    <flux:table.column>{{ __('general.deliverable_quantity') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->reorderAlerts as $stock)
                        <flux:table.row wire:key="reorder-{{ $stock->id }}">
                            <flux:table.cell dir="ltr">{{ $stock->product?->sku }}</flux:table.cell>
                            <flux:table.cell>{{ $stock->product?->name }}</flux:table.cell>
                            <flux:table.cell>{{ $stock->warehouse?->name }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ Quantity::format((string) $stock->physical_quantity) }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ Quantity::format((string) $stock->reserved_quantity) }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ Quantity::format($stock->availableQuantity()) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="6">{{ __('general.no_reorder_alerts') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('general.dead_stock') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('general.dead_stock_hint') }}</flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:button size="sm" :variant="$deadStockDays === 90 ? 'primary' : 'outline'" color="zinc" wire:click="setDeadStockDays(90)">{{ __('general.days_90') }}</flux:button>
                    <flux:button size="sm" :variant="$deadStockDays === 180 ? 'primary' : 'outline'" color="zinc" wire:click="setDeadStockDays(180)">{{ __('general.days_180') }}</flux:button>
                </div>
            </div>
            <flux:table :paginate="$this->deadStock">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.sku') }}</flux:table.column>
                    <flux:table.column>{{ __('general.product') }}</flux:table.column>
                    <flux:table.column>{{ __('general.warehouse') }}</flux:table.column>
                    <flux:table.column>{{ __('general.physical_quantity') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->deadStock as $stock)
                        <flux:table.row wire:key="dead-{{ $stock->id }}">
                            <flux:table.cell dir="ltr">{{ $stock->product?->sku }}</flux:table.cell>
                            <flux:table.cell>{{ $stock->product?->name }}</flux:table.cell>
                            <flux:table.cell>{{ $stock->warehouse?->name }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ Quantity::format((string) $stock->physical_quantity) }} {{ $stock->product?->unit?->label() }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4">{{ __('general.no_dead_stock') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('general.stock_movement_trend') }}</flux:heading>
            <div class="flex gap-2">
                <flux:button size="sm" :variant="$trendDays === 7 ? 'primary' : 'outline'" color="teal" wire:click="setTrendDays(7)">{{ __('general.last_7_days') }}</flux:button>
                <flux:button size="sm" :variant="$trendDays === 30 ? 'primary' : 'outline'" color="teal" wire:click="setTrendDays(30)">{{ __('general.last_30_days') }}</flux:button>
            </div>
        </div>
        <flux:chart wire:model="trendData" class="aspect-[3/1]">
            <flux:chart.viewport class="aspect-[3/1]">
                <flux:chart.svg>
                    <flux:chart.group>
                        <flux:chart.bar field="inbound" class="text-teal-500" width="80%" />
                        <flux:chart.bar field="outbound" class="text-rose-500" width="80%" />
                    </flux:chart.group>
                    <flux:chart.axis axis="x" field="date">
                        <flux:chart.axis.tick />
                        <flux:chart.axis.line />
                    </flux:chart.axis>
                    <flux:chart.axis axis="y" :format="['useGrouping' => true]">
                        <flux:chart.axis.grid />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>
                    <flux:chart.cursor type="area" />
                </flux:chart.svg>
            </flux:chart.viewport>
            <div class="flex gap-4">
                <flux:chart.legend :label="__('general.inbound_quantity')"><flux:chart.legend.indicator class="bg-teal-500" /></flux:chart.legend>
                <flux:chart.legend :label="__('general.outbound_quantity')"><flux:chart.legend.indicator class="bg-rose-500" /></flux:chart.legend>
            </div>
            <flux:chart.tooltip>
                <flux:chart.tooltip.heading field="date" />
                <flux:chart.tooltip.value field="inbound" :label="__('general.inbound_quantity')" />
                <flux:chart.tooltip.value field="outbound" :label="__('general.outbound_quantity')" />
            </flux:chart.tooltip>
        </flux:chart>
    </flux:card>

    <div class="grid gap-4 xl:grid-cols-2">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('general.fast_moving_items') }}</flux:heading>
            @php($maxMoves = max(1, (int) collect($this->fastMovers)->max('moves')))
            <div class="space-y-2">
                @forelse ($this->fastMovers as $item)
                    <div>
                        <div class="flex justify-between text-sm"><span>{{ $item['name'] }}</span><span dir="ltr">{{ $item['moves'] }}</span></div>
                        <div class="mt-1 h-2 rounded bg-zinc-100 dark:bg-zinc-800"><div class="h-2 rounded bg-sky-500" style="width: {{ (int) round(($item['moves'] / $maxMoves) * 100) }}%"></div></div>
                    </div>
                @empty
                    <flux:text>{{ __('general.no_stock_movements') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('general.warehouse_distribution') }}</flux:heading>
            @php($maxQuantity = max(1, (float) collect($this->distribution)->max('quantity')))
            <div class="space-y-2">
                @forelse ($this->distribution as $item)
                    <div>
                        <div class="flex justify-between text-sm"><span>{{ $item['name'] }}</span><span dir="ltr">{{ Quantity::format((string) $item['quantity']) }}</span></div>
                        <div class="mt-1 h-2 rounded bg-zinc-100 dark:bg-zinc-800"><div class="h-2 rounded bg-teal-500" style="width: {{ (int) round(($item['quantity'] / $maxQuantity) * 100) }}%"></div></div>
                    </div>
                @empty
                    <flux:text>{{ __('general.no_inventory_stocks') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>

    <livewire:warehouse.stock.operation :key="'warehouse-stock-operation'" />
    <livewire:warehouse.receipt.confirm :key="'warehouse-receipt-confirm'" />
    <livewire:warehouse.count.record :key="'warehouse-count-record'" />
</div>
