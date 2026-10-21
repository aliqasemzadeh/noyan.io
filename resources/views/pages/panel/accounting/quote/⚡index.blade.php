<?php

use App\Actions\Quotes\ConvertQuoteToInvoiceAction;
use App\Actions\Quotes\CreateQuoteShareLinkAction;
use App\Actions\Quotes\DeleteQuoteAction;
use App\Enums\Accounting\QuoteStatus;
use App\Models\Accounting\Quote;
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
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || (int) $quote->business_id !== (int) $businessId) {
            return;
        }

        $invoice = $action->handle($quote, Auth::user());

        Flux::modals()->close();
        Flux::toast(__('general.quote_converted', ['number' => $invoice->invoice_number]));

        $this->redirect(route('accounting.invoices.edit', $invoice), navigate: true);
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
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.quotes') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.quotes') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.quotes_page_hint') }}</flux:text>
            </div>

            <flux:button variant="primary" color="teal" icon="plus" :href="route('accounting.quotes.create')" wire:navigate>
                {{ __('general.create_quote') }}
            </flux:button>
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
                                    <flux:link :href="route('accounting.invoices.edit', $quote->invoice)" wire:navigate>
                                        {{ $quote->invoice->invoice_number }}
                                    </flux:link>
                                @else
                                    <flux:link :href="route('accounting.invoices.index', ['search' => $quote->invoice->invoice_number])" wire:navigate>
                                        {{ $quote->invoice->invoice_number }}
                                    </flux:link>
                                @endif
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @if ($quote->isEditable())
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="blue"
                                            icon="pencil"
                                            icon:variant="outline"
                                            :href="route('accounting.quotes.edit', $quote)"
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

                                @if ($quote->canBeConverted())
                                    <flux:tooltip content="{{ __('general.convert_to_invoice') }}">
                                        <flux:modal.trigger name="quote.convert.{{ $quote->id }}">
                                            <flux:button size="xs" variant="primary" color="green" icon="file-check" icon:variant="outline" />
                                        </flux:modal.trigger>
                                    </flux:tooltip>
                                @endif

                                @if ($quote->isDeletable())
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
