<?php

use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.invoice.index.table')]
    public function refreshTable(): void
    {
        unset($this->invoices);
    }

    public function delete(Invoice $invoice): void
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || (int) $invoice->business_id !== (int) $businessId) {
            return;
        }

        if ($invoice->isFinalized()) {
            Flux::toast(text: __('general.invoice_cannot_delete_finalized'), variant: 'danger');

            return;
        }

        $invoice->delete();
        unset($this->invoices);
        Flux::toast(__('general.invoice_deleted'));
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Invoice::query()
            ->with('party')
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'like', $search)
                        ->orWhere('party_name', 'like', $search)
                        ->orWhereHas('party', function ($query) use ($search): void {
                            $query->where('name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter === 'draft', fn ($query) => $query->whereNull('finalized_at'))
            ->when($this->statusFilter === 'finalized', fn ($query) => $query->whereNotNull('finalized_at'))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(config('general.per_page', 15));
    }

    public function formatDate(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Jalalian::fromDateTime($date)->format('Y/m/d');
    }

    public function formatAmount(string $amount): string
    {
        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized);
    }
};
?>

<x-slot name="title">{{ __('general.invoices') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.invoices') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.invoices') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.invoices_page_hint') }}</flux:text>
            </div>

            <flux:dropdown>
                <flux:button variant="primary" color="teal" icon:trailing="chevron-down">
                    {{ __('general.create_invoice') }}
                </flux:button>
                <flux:menu>
                    <flux:menu.item icon="trending-up" :href="route('accounting.invoices.create', ['type' => 'sale'])" wire:navigate>
                        {{ __('general.new_sale_invoice') }}
                    </flux:menu.item>
                    <flux:menu.item icon="trending-down" :href="route('accounting.invoices.create', ['type' => 'purchase'])" wire:navigate>
                        {{ __('general.new_purchase_invoice') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />

            <flux:select wire:model.live="typeFilter" searchable variant="listbox" placeholder="{{ __('general.all_invoice_types') }}">
                <flux:select.option value="">{{ __('general.all_invoice_types') }}</flux:select.option>
                @foreach (InvoiceType::creatable() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="filter-invoice-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" searchable variant="listbox" placeholder="{{ __('general.all_statuses') }}">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                <flux:select.option value="draft">{{ __('general.draft') }}</flux:select.option>
                <flux:select.option value="finalized">{{ __('general.finalized') }}</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->invoices">
            <flux:table.columns>
                <flux:table.column>{{ __('general.invoice_number') }}</flux:table.column>
                <flux:table.column>{{ __('general.type') }}</flux:table.column>
                <flux:table.column>{{ __('general.party') }}</flux:table.column>
                <flux:table.column>{{ __('general.issue_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.total_amount') }}</flux:table.column>
                <flux:table.column>{{ __('general.status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell class="font-medium">{{ $invoice->invoice_number }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$invoice->type->badgeColor()">
                                {{ $invoice->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->displayPartyName() }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatDate($invoice->issue_date) }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $invoice->total_amount) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($invoice->isFinalized())
                                <flux:badge size="sm" color="green">{{ __('general.finalized') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('general.draft') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @if ($invoice->isDraft())
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="blue"
                                            icon="pencil"
                                            icon:variant="outline"
                                            :href="route('accounting.invoices.edit', $invoice)"
                                            wire:navigate
                                        />
                                    </flux:tooltip>

                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:modal.trigger name="invoice.delete.{{ $invoice->id }}">
                                            <flux:button size="xs" variant="danger" icon="trash" icon:variant="outline" />
                                        </flux:modal.trigger>
                                    </flux:tooltip>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <flux:text class="py-6 text-center">{{ __('general.no_invoices') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @foreach ($this->invoices as $invoice)
        @if ($invoice->isDraft())
            <flux:modal name="invoice.delete.{{ $invoice->id }}" class="min-w-[22rem]">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>
                        <flux:text class="mt-2">
                            {{ __('general.delete_invoice_warning') }}<br>
                            {{ __('general.action_cannot_be_reversed') }}
                        </flux:text>
                    </div>

                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" wire:click="delete({{ $invoice->id }})">
                            {{ __('general.delete') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    @endforeach
</div>
