<?php

use App\Actions\Quotes\ConvertQuoteToInvoiceAction;
use App\Actions\Quotes\CreateQuoteShareLinkAction;
use App\Actions\Quotes\DeleteQuoteAction;
use App\Enums\Accounting\QuoteStatus;
use App\Enums\Business\BusinessPermission;
use App\Models\Accounting\Quote;
use App\Support\Business\BusinessPanelContext;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url as LivewireUrl;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    #[LivewireUrl]
    public string $search = '';

    #[LivewireUrl]
    public string $statusFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if (BusinessPanelContext::isSale()) {
            abort_unless(Auth::user()?->canInBusiness(BusinessPermission::QuoteView), 403);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.quote.index.table')]
    public function refreshTable(): void
    {
        unset($this->quotes);
    }

    public function delete(Quote $quote, DeleteQuoteAction $action): void
    {
        if (BusinessPanelContext::isSale()) {
            abort_unless(Auth::user()?->canInBusiness(BusinessPermission::QuoteDelete), 403);
        }

        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || (int) $quote->business_id !== (int) $businessId) {
            return;
        }

        $action->handle($quote, Auth::user());
        unset($this->quotes);
        Flux::modals()->close();
        Flux::toast(__('general.quote_deleted'));
    }

    public function copyShareLink(Quote $quote, CreateQuoteShareLinkAction $action): void
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || (int) $quote->business_id !== (int) $businessId) {
            return;
        }

        if (! $quote->isFinalized()) {
            Flux::toast(text: __('general.quote_share_requires_finalized'), variant: 'danger');

            return;
        }

        $url = $action->handle($quote->loadMissing('business'));

        $this->js('navigator.clipboard.writeText('.json_encode($url).')');

        Flux::toast(__('general.quote_link_copied'));
    }

    public function previewUrl(Quote $quote): string
    {
        return URL::signedRoute('quotes.public', ['quote' => $quote]);
    }

    public function convert(Quote $quote, ConvertQuoteToInvoiceAction $action): void
    {
        if (BusinessPanelContext::isSale()) {
            abort_unless(Auth::user()?->canInBusiness(BusinessPermission::InvoiceCreate), 403);
        }

        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || (int) $quote->business_id !== (int) $businessId) {
            return;
        }

        $invoice = $action->handle($quote, Auth::user());

        Flux::modals()->close();
        Flux::toast(__('general.quote_converted', ['number' => $invoice->invoice_number]));

        $this->redirect(BusinessPanelContext::route('invoices.edit', $invoice), navigate: true);
    }

    public function panelRoute(string $name, mixed $parameters = []): string
    {
        return BusinessPanelContext::route($name, $parameters);
    }

    public function panelLabel(): string
    {
        return BusinessPanelContext::current()->label();
    }

    public function isSalePanel(): bool
    {
        return BusinessPanelContext::isSale();
    }

    /**
     * @return LengthAwarePaginator<int, Quote>
     */
    #[Computed]
    public function quotes(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Quote::query()
            ->with([
                'party:id,name,legal_name',
                'invoice:id,invoice_number,finalized_at',
            ])
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('quote_number', 'like', $search)
                        ->orWhere('party_name', 'like', $search)
                        ->orWhereHas('party', function ($query) use ($search): void {
                            $query->where('name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            })
            ->when($this->statusFilter === QuoteStatus::Draft->value, fn ($query) => $query->whereNull('finalized_at'))
            ->when($this->statusFilter === QuoteStatus::Finalized->value, function ($query): void {
                $query->whereNotNull('finalized_at')
                    ->whereNull('converted_at')
                    ->whereDate('valid_until', '>=', now()->toDateString());
            })
            ->when($this->statusFilter === QuoteStatus::Expired->value, function ($query): void {
                $query->whereNotNull('finalized_at')
                    ->whereNull('converted_at')
                    ->whereDate('valid_until', '<', now()->toDateString());
            })
            ->when($this->statusFilter === QuoteStatus::Converted->value, fn ($query) => $query->whereNotNull('converted_at'))
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

<x-slot name="title">{{ __('general.quotes') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="$this->panelRoute('dashboard')" wire:navigate>
                {{ $this->panelLabel() }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.quotes') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <flux:heading size="xl">{{ __('general.quotes') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.quotes_page_hint') }}</flux:text>
            </div>

            @if (auth()->user()?->canInBusiness(BusinessPermission::QuoteCreate) || ! $this->isSalePanel())
                <flux:button variant="primary" color="teal" icon="plus" class="w-full sm:w-auto" :href="$this->panelRoute('quotes.create')" wire:navigate>
                    {{ __('general.create_quote') }}
                </flux:button>
            @endif
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />

            <flux:select wire:model.live="statusFilter" searchable variant="listbox" placeholder="{{ __('general.all_quote_statuses') }}">
                <flux:select.option value="">{{ __('general.all_quote_statuses') }}</flux:select.option>
                @foreach (QuoteStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}" wire:key="filter-quote-status-{{ $status->value }}">
                        {{ $status->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        {{-- Mobile cards --}}
        <div class="space-y-3 md:hidden">
            @forelse ($this->quotes as $quote)
                @php($status = $quote->status())
                <div wire:key="quote-mobile-{{ $quote->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <div class="font-medium">{{ $quote->quote_number }}</div>
                            <flux:text class="truncate">{{ $quote->displayPartyName() }}</flux:text>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <flux:badge size="sm" :color="$status->badgeColor()">{{ $status->label() }}</flux:badge>

                            <flux:dropdown>
                                <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" />
                                <flux:menu>
                                    @if ($quote->isEditable() && (auth()->user()?->canInBusiness(BusinessPermission::QuoteEdit) || ! $this->isSalePanel()))
                                        <flux:menu.item icon="pencil" :href="$this->panelRoute('quotes.edit', $quote)" wire:navigate>
                                            {{ __('general.edit') }}
                                        </flux:menu.item>
                                    @endif

                                    @if ($quote->isFinalized())
                                        <flux:menu.item icon="link" wire:click="copyShareLink({{ $quote->id }})">
                                            {{ __('general.copy_quote_link') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="eye" :href="$this->previewUrl($quote)" target="_blank">
                                            {{ __('general.preview') }}
                                        </flux:menu.item>
                                    @endif

                                    @if ($quote->canBeConverted() && (auth()->user()?->canInBusiness(BusinessPermission::InvoiceCreate) || ! $this->isSalePanel()))
                                        <flux:modal.trigger name="quote.convert.{{ $quote->id }}">
                                            <flux:menu.item icon="file-check">
                                                {{ __('general.convert_to_invoice') }}
                                            </flux:menu.item>
                                        </flux:modal.trigger>
                                    @endif

                                    @if ($quote->isDeletable() && (auth()->user()?->canInBusiness(BusinessPermission::QuoteDelete) || ! $this->isSalePanel()))
                                        <flux:modal.trigger name="quote.delete.{{ $quote->id }}">
                                            <flux:menu.item variant="danger" icon="trash">
                                                {{ __('general.delete') }}
                                            </flux:menu.item>
                                        </flux:modal.trigger>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <flux:text class="text-xs">{{ __('general.issue_date') }}</flux:text>
                            <div>{{ $this->formatDate($quote->issue_date) }}</div>
                        </div>
                        <div>
                            <flux:text class="text-xs">{{ __('general.valid_until') }}</flux:text>
                            <div class="flex items-center gap-1">
                                @if ($quote->isExpired())
                                    <flux:icon.clock-alert class="size-4 shrink-0 text-rose-500" />
                                @endif
                                <span>{{ $this->formatDate($quote->valid_until) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            @if ($quote->invoice)
                                <flux:text class="text-xs">{{ __('general.converted_invoice') }}</flux:text>
                                @if ($quote->invoice->isDraft())
                                    <flux:link :href="$this->panelRoute('invoices.edit', $quote->invoice)" wire:navigate>
                                        {{ $quote->invoice->invoice_number }}
                                    </flux:link>
                                @else
                                    <flux:link :href="$this->panelRoute('invoices.index', ['search' => $quote->invoice->invoice_number])" wire:navigate>
                                        {{ $quote->invoice->invoice_number }}
                                    </flux:link>
                                @endif
                            @else
                                <flux:text>—</flux:text>
                            @endif
                        </div>
                        <span class="shrink-0 font-medium" dir="ltr">{{ $this->formatAmount((string) $quote->total_amount) }}</span>
                    </div>
                </div>
            @empty
                <flux:text class="py-6 text-center">{{ __('general.no_quotes') }}</flux:text>
            @endforelse

            @if ($this->quotes->hasPages())
                <div class="pt-2">
                    <flux:pagination :paginator="$this->quotes" />
                </div>
            @endif
        </div>

        {{-- Desktop table --}}
        <div class="hidden md:block">
            <flux:table :paginate="$this->quotes">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.quote_number') }}</flux:table.column>
                    <flux:table.column>{{ __('general.party') }}</flux:table.column>
                    <flux:table.column>{{ __('general.issue_date') }}</flux:table.column>
                    <flux:table.column>{{ __('general.valid_until') }}</flux:table.column>
                    <flux:table.column>{{ __('general.total_amount') }}</flux:table.column>
                    <flux:table.column>{{ __('general.status') }}</flux:table.column>
                    <flux:table.column>{{ __('general.converted_invoice') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->quotes as $quote)
                        @php($status = $quote->status())
                        <flux:table.row :key="$quote->id">
                            <flux:table.cell class="font-medium">{{ $quote->quote_number }}</flux:table.cell>
                            <flux:table.cell>{{ $quote->displayPartyName() }}</flux:table.cell>
                            <flux:table.cell>{{ $this->formatDate($quote->issue_date) }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-1">
                                    @if ($quote->isExpired())
                                        <flux:icon.clock-alert class="size-4 text-rose-500" />
                                    @endif
                                    <span>{{ $this->formatDate($quote->valid_until) }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $quote->total_amount) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$status->badgeColor()">{{ $status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($quote->invoice)
                                    @if ($quote->invoice->isDraft())
                                        <flux:link :href="$this->panelRoute('invoices.edit', $quote->invoice)" wire:navigate>
                                            {{ $quote->invoice->invoice_number }}
                                        </flux:link>
                                    @else
                                        <flux:link :href="$this->panelRoute('invoices.index', ['search' => $quote->invoice->invoice_number])" wire:navigate>
                                            {{ $quote->invoice->invoice_number }}
                                        </flux:link>
                                    @endif
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    @if ($quote->isEditable() && (auth()->user()?->canInBusiness(BusinessPermission::QuoteEdit) || ! $this->isSalePanel()))
                                        <flux:tooltip content="{{ __('general.edit') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="blue"
                                                icon="pencil"
                                                icon:variant="outline"
                                                :href="$this->panelRoute('quotes.edit', $quote)"
                                                wire:navigate
                                            />
                                        </flux:tooltip>
                                    @endif

                                    @if ($quote->isFinalized())
                                        <flux:tooltip content="{{ __('general.copy_quote_link') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="teal"
                                                icon="link"
                                                icon:variant="outline"
                                                wire:click="copyShareLink({{ $quote->id }})"
                                            />
                                        </flux:tooltip>

                                        <flux:tooltip content="{{ __('general.preview') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="zinc"
                                                icon="eye"
                                                icon:variant="outline"
                                                :href="$this->previewUrl($quote)"
                                                target="_blank"
                                            />
                                        </flux:tooltip>
                                    @endif

                                    @if ($quote->canBeConverted() && (auth()->user()?->canInBusiness(BusinessPermission::InvoiceCreate) || ! $this->isSalePanel()))
                                        <flux:tooltip content="{{ __('general.convert_to_invoice') }}">
                                            <flux:modal.trigger name="quote.convert.{{ $quote->id }}">
                                                <flux:button size="xs" variant="primary" color="green" icon="file-check" icon:variant="outline" />
                                            </flux:modal.trigger>
                                        </flux:tooltip>
                                    @endif

                                    @if ($quote->isDeletable() && (auth()->user()?->canInBusiness(BusinessPermission::QuoteDelete) || ! $this->isSalePanel()))
                                        <flux:tooltip content="{{ __('general.delete') }}">
                                            <flux:modal.trigger name="quote.delete.{{ $quote->id }}">
                                                <flux:button size="xs" variant="danger" icon="trash" icon:variant="outline" />
                                            </flux:modal.trigger>
                                        </flux:tooltip>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8">
                                <flux:text class="py-6 text-center">{{ __('general.no_quotes') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    @foreach ($this->quotes as $quote)
        @if ($quote->canBeConverted())
            <flux:modal name="quote.convert.{{ $quote->id }}" class="min-w-[22rem]">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('general.convert_quote_confirmation') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('general.convert_quote_warning') }}</flux:text>
                        @if ($quote->isExpired())
                            <flux:text class="mt-2 text-rose-600 dark:text-rose-400">
                                {{ __('general.convert_quote_expired_warning') }}
                            </flux:text>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" color="green" wire:click="convert({{ $quote->id }})">
                            {{ __('general.convert_to_invoice') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        @if ($quote->isDeletable())
            <flux:modal name="quote.delete.{{ $quote->id }}" class="min-w-[22rem]">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>
                        <flux:text class="mt-2">
                            {{ __('general.delete_quote_warning') }}<br>
                            {{ __('general.action_cannot_be_reversed') }}
                        </flux:text>
                    </div>

                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" wire:click="delete({{ $quote->id }})">
                            {{ __('general.delete') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    @endforeach
</div>
