<?php

use App\Actions\Inventory\ConfirmOutboundDispatchAction;
use App\Actions\Inventory\ProgressStockDocumentAction;
use App\Enums\Business\BusinessPermission;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Models\Catalog\StockDocument;
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

    public function confirmDispatch(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockIssue), 403);

        app(ConfirmOutboundDispatchAction::class)->handle($this->findDocument($documentId), Auth::user());
        unset($this->documents);
        Flux::toast(__('general.dispatch_confirmed'));
    }

    public function markPacked(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockIssue), 403);

        app(ProgressStockDocumentAction::class)->markPacked($this->findDocument($documentId));
        unset($this->documents);
        Flux::toast(__('general.dispatch_marked_packed'));
    }

    public function markUrgent(int $documentId): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockIssue), 403);

        app(ProgressStockDocumentAction::class)->markUrgent($this->findDocument($documentId));
        unset($this->documents);
        Flux::toast(__('general.dispatch_marked_urgent'));
    }

    /**
     * @return LengthAwarePaginator<int, StockDocument>
     */
    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return StockDocument::query()
            ->with(['party:id,name,legal_name', 'warehouse:id,name'])
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->where('type', StockDocumentType::Outbound)
            ->when($this->openOnly, fn ($query) => $query->whereIn('status', [StockDocumentStatus::PendingPick, StockDocumentStatus::Packed]))
            ->when($this->warehouseFilter !== '', fn ($query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', $search)
                        ->orWhere('counterparty_name', 'like', $search)
                        ->orWhereHas('party', fn ($query) => $query->where('name', 'like', $search)->orWhere('legal_name', 'like', $search));
                });
            })
            ->orderByRaw("case when priority = 'urgent' then 0 else 1 end")
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

    protected function findDocument(int $documentId): StockDocument
    {
        return StockDocument::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->where('type', StockDocumentType::Outbound)
            ->findOrFail($documentId);
    }
};
?>

<x-slot name="title">{{ __('general.dispatches') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('warehouse.dashboard')" wire:navigate>{{ __('general.warehouse') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('general.dispatches') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="mt-4">
            <flux:heading size="xl">{{ __('general.dispatches') }}</flux:heading>
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
                <flux:table.column>{{ __('general.priority') }}</flux:table.column>
                <flux:table.column>{{ __('general.status') }}</flux:table.column>
                <flux:table.column>{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->documents as $document)
                    <flux:table.row wire:key="dispatch-{{ $document->id }}">
                        <flux:table.cell dir="ltr">{{ $document->number }}</flux:table.cell>
                        <flux:table.cell>{{ $document->counterpartyLabel() }}</flux:table.cell>
                        <flux:table.cell>{{ $document->warehouse?->name }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ $document->lines_count }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ Quantity::format((string) $document->lines_sum_quantity) }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$document->priority->badgeColor()">{{ $document->priority->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$document->status->badgeColor()">{{ $document->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                <flux:tooltip content="{{ __('general.print_pick_list') }}">
                                    <flux:button size="xs" variant="primary" color="zinc" icon="printer" :href="route('warehouse.dispatches.pick-list', $document)" target="_blank" />
                                </flux:tooltip>
                                @if (auth()->user()?->canInBusiness(BusinessPermission::StockIssue) && in_array($document->status, [StockDocumentStatus::PendingPick, StockDocumentStatus::Packed], true))
                                    @if ($document->status === StockDocumentStatus::PendingPick)
                                        <flux:tooltip content="{{ __('general.mark_packed') }}">
                                            <flux:button size="xs" variant="primary" color="sky" icon="package" wire:click="markPacked({{ $document->id }})" />
                                        </flux:tooltip>
                                    @endif
                                    @if ($document->priority !== \App\Enums\Catalog\StockDocumentPriority::Urgent)
                                        <flux:tooltip content="{{ __('general.mark_urgent') }}">
                                            <flux:button size="xs" variant="primary" color="rose" icon="triangle-alert" wire:click="markUrgent({{ $document->id }})" />
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
                    <flux:table.row>
                        <flux:table.cell colspan="8">{{ __('general.no_open_dispatches') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
