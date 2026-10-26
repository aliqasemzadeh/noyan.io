<?php

use App\Actions\Inventory\AcceptInboundDiscrepancyAction;
use App\Enums\Business\BusinessPermission;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Models\Catalog\StockDocument;
use App\Models\Catalog\Warehouse;
use App\Support\LocaleDate;
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

    public string $search = '';

    #[Url]
    public string $warehouseFilter = '';

    #[Url]
    public bool $openOnly = true;

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

    public function updatedOpenOnly(): void
    {
        $this->resetPage();
    }

    #[On('panels.warehouse.documents.refresh')]
    public function refreshTable(): void
    {
        unset($this->documents);
    }

    public function acceptDiscrepancy(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockReceive), 403);

        $document = StockDocument::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->where('type', StockDocumentType::Inbound)
            ->findOrFail($documentId);

        app(AcceptInboundDiscrepancyAction::class)->handle($document);
        unset($this->documents);
        Flux::toast(__('general.discrepancy_accepted'));
    }

    /**
     * @return LengthAwarePaginator<int, StockDocument>
     */
    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return StockDocument::query()
            ->with(['party:id,name,legal_name', 'warehouse:id,name', 'invoice:id,invoice_number'])
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->where('type', StockDocumentType::Inbound)
            ->when($this->openOnly, fn ($query) => $query->whereIn('status', [StockDocumentStatus::Expected, StockDocumentStatus::Discrepancy]))
            ->when($this->warehouseFilter !== '', fn ($query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', $search)
                        ->orWhere('counterparty_name', 'like', $search)
                        ->orWhereHas('invoice', fn ($query) => $query->where('invoice_number', 'like', $search));
                });
            })
            ->orderBy('expected_at')
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

<x-slot name="title">{{ __('general.receipts') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('warehouse.dashboard')" wire:navigate>{{ __('general.warehouse') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('general.receipts') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="mt-4">
            <flux:heading size="xl">{{ __('general.receipts') }}</flux:heading>
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
            <flux:checkbox wire:model.live="openOnly" label="{{ __('general.open_documents') }}" />
        </div>

        <flux:table :paginate="$this->documents">
            <flux:table.columns>
                <flux:table.column>{{ __('general.document_number') }}</flux:table.column>
                <flux:table.column>{{ __('general.counterparty') }}</flux:table.column>
                <flux:table.column>{{ __('general.warehouse') }}</flux:table.column>
                <flux:table.column>{{ __('general.items_count') }}</flux:table.column>
                <flux:table.column>{{ __('general.quantity') }}</flux:table.column>
                <flux:table.column>{{ __('general.expected_at') }}</flux:table.column>
                <flux:table.column>{{ __('general.status') }}</flux:table.column>
                <flux:table.column>{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->documents as $document)
                    <flux:table.row wire:key="receipt-{{ $document->id }}">
                        <flux:table.cell>
                            <div dir="ltr">{{ $document->number }}</div>
                            @if ($document->invoice)
                                <div class="text-xs text-zinc-500" dir="ltr">{{ $document->invoice->invoice_number }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $document->counterpartyLabel() }}</flux:table.cell>
                        <flux:table.cell>{{ $document->warehouse?->name }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ $document->lines_count }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ Quantity::format((string) $document->lines_sum_quantity) }}</flux:table.cell>
                        <flux:table.cell>{{ LocaleDate::formatDate($document->expected_at) }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$document->status->badgeColor()">{{ $document->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @if (auth()->user()?->canInBusiness(BusinessPermission::StockReceive) && $document->status === StockDocumentStatus::Expected)
                                    <flux:tooltip content="{{ __('general.confirm_receipt') }}">
                                        <flux:button size="xs" variant="primary" color="teal" icon="log-in" wire:click="$dispatch('panels.warehouse.receipt.confirm.open', { documentId: {{ $document->id }} })" />
                                    </flux:tooltip>
                                    <flux:tooltip content="{{ __('general.record_discrepancy') }}">
                                        <flux:button size="xs" variant="primary" color="amber" icon="triangle-alert" wire:click="$dispatch('panels.warehouse.receipt.confirm.open', { documentId: {{ $document->id }} })" />
                                    </flux:tooltip>
                                @endif
                                @if (auth()->user()?->canInBusiness(BusinessPermission::StockReceive) && $document->status === StockDocumentStatus::Discrepancy)
                                    <flux:tooltip content="{{ __('general.accept_discrepancy') }}">
                                        <flux:button size="xs" variant="primary" color="rose" icon="check" wire:click="acceptDiscrepancy({{ $document->id }})" wire:confirm="{{ __('general.are_you_sure') }}" />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">{{ __('general.no_open_receipts') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:warehouse.receipt.confirm :key="'warehouse-receipt-confirm'" />
</div>
