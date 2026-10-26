<?php

use App\Actions\Invoices\CreateInvoiceShareLinkAction;
use App\Actions\Notifications\SendInvoiceNotificationAction;
use App\Enums\Accounting\InvoiceAging;
use App\Enums\Accounting\InvoiceNotificationChannel;
use App\Enums\Accounting\InvoiceNotificationType;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessPermission;
use App\Models\Accounting\Invoice;
use App\Support\Business\BusinessPanelContext;
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

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $aging = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if (BusinessPanelContext::isSale()) {
            abort_unless(Auth::user()?->canInBusiness(BusinessPermission::InvoiceView), 403);
        }
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

    public function updatedAging(): void
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
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::InvoiceDelete), 403);

        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || (int) $invoice->business_id !== (int) $businessId) {
            return;
        }

        if (BusinessPanelContext::isSale() && ! in_array($invoice->type, InvoiceType::creatable(), true)) {
            abort(403);
        }

        if ($invoice->isFinalized()) {
            Flux::toast(text: __('general.invoice_cannot_delete_finalized'), variant: 'danger');

            return;
        }

        $invoice->delete();
        unset($this->invoices);
        Flux::toast(__('general.invoice_deleted'));
    }

    public function copyShareLink(Invoice $invoice, CreateInvoiceShareLinkAction $action): void
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || (int) $invoice->business_id !== (int) $businessId) {
            return;
        }

        if (! $invoice->isFinalized()) {
            Flux::toast(text: __('general.invoice_share_requires_finalized'), variant: 'danger');

            return;
        }

        $url = $action->handle($invoice->loadMissing('business'));

        $this->js('navigator.clipboard.writeText('.json_encode($url).')');

        Flux::toast(__('general.invoice_link_copied'));
    }

    public function sendEmail(Invoice $invoice, SendInvoiceNotificationAction $action): void
    {
        $this->sendNotification($invoice, $action, InvoiceNotificationChannel::Email);
    }

    public function sendSms(Invoice $invoice, SendInvoiceNotificationAction $action): void
    {
        $this->sendNotification($invoice, $action, InvoiceNotificationChannel::Sms);
    }

    protected function sendNotification(
        Invoice $invoice,
        SendInvoiceNotificationAction $action,
        InvoiceNotificationChannel $channel,
    ): void {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null || (int) $invoice->business_id !== (int) $businessId) {
            return;
        }

        if (! $invoice->isFinalized()) {
            Flux::toast(text: __('general.invoice_share_requires_finalized'), variant: 'danger');

            return;
        }

        $result = $action->handle(
            $invoice->loadMissing(['party', 'business']),
            InvoiceNotificationType::NewInvoice,
            [$channel],
            force: true,
        );

        $sent = $channel === InvoiceNotificationChannel::Email ? $result['email'] : $result['sms'];

        if ($sent) {
            Flux::toast(__('general.invoice_notification_sent'));

            return;
        }

        $message = $result['errors'][0] ?? __('general.invoice_notification_failed');
        Flux::toast(text: $message, variant: 'danger');
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Invoice::query()
            ->with(['party', 'quote:id,quote_number'])
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
            ->when(
                BusinessPanelContext::isSale(),
                fn ($query) => $query
                    ->whereIn('type', array_map(
                        fn (InvoiceType $type): string => $type->value,
                        InvoiceType::creatable(),
                    ))
                    ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter)),
                fn ($query) => $query->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter)),
            )
            ->when($this->statusFilter === 'draft', fn ($query) => $query->whereNull('finalized_at'))
            ->when($this->statusFilter === 'finalized', fn ($query) => $query->whereNotNull('finalized_at'))
            ->when(InvoiceAging::tryFrom($this->aging) !== null, function ($query): void {
                $bucket = InvoiceAging::from($this->aging);
                $today = now()->toDateString();
                $days30 = now()->subDays(30)->toDateString();
                $days60 = now()->subDays(60)->toDateString();

                $query->whereNotNull('finalized_at')
                    ->where('payment_status', '!=', InvoicePaymentStatus::Paid->value)
                    ->whereColumn('total_amount', '>', 'paid_amount');

                match ($bucket) {
                    InvoiceAging::Current => $query->where(function ($query) use ($today): void {
                        $query->whereNull('due_date')->orWhereDate('due_date', '>=', $today);
                    }),
                    InvoiceAging::Days1To30 => $query
                        ->whereDate('due_date', '<', $today)
                        ->whereDate('due_date', '>=', $days30),
                    InvoiceAging::Days31To60 => $query
                        ->whereDate('due_date', '<', $days30)
                        ->whereDate('due_date', '>=', $days60),
                    InvoiceAging::Over60 => $query->whereDate('due_date', '<', $days60),
                };
            })
            ->latest('issue_date')
            ->latest('id')
            ->paginate(config('general.per_page', 15));
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
            <flux:breadcrumbs.item :href="$this->panelRoute('dashboard')" wire:navigate>
                {{ $this->panelLabel() }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.invoices') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <flux:heading size="xl">{{ __('general.invoices') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.invoices_page_hint') }}</flux:text>
            </div>

            @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceCreate) || ! $this->isSalePanel())
                <flux:dropdown class="w-full sm:w-auto">
                    <flux:button variant="primary" color="teal" icon:trailing="chevron-down" class="w-full sm:w-auto">
                        {{ __('general.create_invoice') }}
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item icon="trending-up" :href="$this->panelRoute('invoices.create', ['type' => 'sale'])" wire:navigate>
                            {{ __('general.new_sale_invoice') }}
                        </flux:menu.item>
                        <flux:menu.item icon="trending-down" :href="$this->panelRoute('invoices.create', ['type' => 'purchase'])" wire:navigate>
                            {{ __('general.new_purchase_invoice') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
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

            <flux:select wire:model.live="aging" searchable variant="listbox" placeholder="{{ __('general.all_aging') }}">
                <flux:select.option value="">{{ __('general.all_aging') }}</flux:select.option>
                @foreach (InvoiceAging::cases() as $bucket)
                    <flux:select.option value="{{ $bucket->value }}" wire:key="filter-invoice-aging-{{ $bucket->value }}">
                        {{ $bucket->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        {{-- Mobile cards --}}
        <div class="space-y-3 md:hidden">
            @forelse ($this->invoices as $invoice)
                <div wire:key="invoice-mobile-{{ $invoice->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <div class="font-medium">{{ $invoice->invoice_number }}</div>
                            <flux:text class="truncate">{{ $invoice->displayPartyName() }}</flux:text>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($invoice->isFinalized())
                                <flux:badge size="sm" color="green">{{ __('general.finalized') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('general.draft') }}</flux:badge>
                            @endif

                            <flux:dropdown>
                                <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" />
                                <flux:menu>
                                    @if ($invoice->isDraft())
                                        @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceEdit) || ! $this->isSalePanel())
                                            <flux:menu.item icon="pencil" :href="$this->panelRoute('invoices.edit', $invoice)" wire:navigate>
                                                {{ __('general.edit') }}
                                            </flux:menu.item>
                                        @endif

                                        @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceDelete) || ! $this->isSalePanel())
                                            <flux:modal.trigger name="invoice.delete.{{ $invoice->id }}">
                                                <flux:menu.item variant="danger" icon="trash">
                                                    {{ __('general.delete') }}
                                                </flux:menu.item>
                                            </flux:modal.trigger>
                                        @endif
                                    @else
                                        @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceEditFinalized))
                                            <flux:menu.item icon="pencil" :href="$this->panelRoute('invoices.edit', $invoice)" wire:navigate>
                                                {{ __('general.edit') }}
                                            </flux:menu.item>
                                        @endif
                                        <flux:menu.item icon="link" wire:click="copyShareLink({{ $invoice->id }})">
                                            {{ __('general.copy_invoice_link') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="mail" wire:click="sendEmail({{ $invoice->id }})">
                                            {{ __('general.send_invoice_email') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="message-square" wire:click="sendSms({{ $invoice->id }})">
                                            {{ __('general.send_invoice_sms') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$invoice->type->badgeColor()">
                            {{ $invoice->type->label() }}
                        </flux:badge>
                        @if ($invoice->quote)
                            <flux:badge size="sm" color="sky">
                                <flux:link :href="$this->panelRoute('quotes.index', ['search' => $invoice->quote->quote_number])" wire:navigate>
                                    {{ __('general.from_quote', ['number' => $invoice->quote->quote_number]) }}
                                </flux:link>
                            </flux:badge>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                        <flux:text>{{ $this->formatDate($invoice->issue_date) }}</flux:text>
                        <span class="font-medium" dir="ltr">{{ $this->formatAmount((string) $invoice->total_amount) }}</span>
                    </div>
                </div>
            @empty
                <flux:text class="py-6 text-center">{{ __('general.no_invoices') }}</flux:text>
            @endforelse

            @if ($this->invoices->hasPages())
                <div class="pt-2">
                    <flux:pagination :paginator="$this->invoices" />
                </div>
            @endif
        </div>

        {{-- Desktop table --}}
        <div class="hidden md:block">
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
                            <flux:table.cell class="font-medium">
                                <div>{{ $invoice->invoice_number }}</div>
                                @if ($invoice->quote)
                                    <flux:badge size="sm" color="sky" class="mt-1">
                                        <flux:link :href="$this->panelRoute('quotes.index', ['search' => $invoice->quote->quote_number])" wire:navigate>
                                            {{ __('general.from_quote', ['number' => $invoice->quote->quote_number]) }}
                                        </flux:link>
                                    </flux:badge>
                                @endif
                            </flux:table.cell>
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
                                        @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceEdit) || ! $this->isSalePanel())
                                            <flux:tooltip content="{{ __('general.edit') }}">
                                                <flux:button
                                                    size="xs"
                                                    variant="primary"
                                                    color="blue"
                                                    icon="pencil"
                                                    icon:variant="outline"
                                                    :href="$this->panelRoute('invoices.edit', $invoice)"
                                                    wire:navigate
                                                />
                                            </flux:tooltip>
                                        @endif

                                        @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceDelete) || ! $this->isSalePanel())
                                            <flux:modal.trigger name="invoice.delete.{{ $invoice->id }}">
                                                <flux:tooltip content="{{ __('general.delete') }}">
                                                    <flux:button size="xs" variant="danger" icon="trash" icon:variant="outline" />
                                                </flux:tooltip>
                                            </flux:modal.trigger>
                                        @endif
                                    @else
                                        @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceEditFinalized))
                                            <flux:tooltip content="{{ __('general.edit') }}">
                                                <flux:button
                                                    size="xs"
                                                    variant="primary"
                                                    color="orange"
                                                    icon="pencil"
                                                    icon:variant="outline"
                                                    :href="$this->panelRoute('invoices.edit', $invoice)"
                                                    wire:navigate
                                                />
                                            </flux:tooltip>
                                        @endif
                                        <flux:tooltip content="{{ __('general.copy_invoice_link') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="teal"
                                                icon="link"
                                                icon:variant="outline"
                                                wire:click="copyShareLink({{ $invoice->id }})"
                                            />
                                        </flux:tooltip>
                                        <flux:tooltip content="{{ __('general.send_invoice_email') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="blue"
                                                icon="mail"
                                                icon:variant="outline"
                                                wire:click="sendEmail({{ $invoice->id }})"
                                            />
                                        </flux:tooltip>
                                        <flux:tooltip content="{{ __('general.send_invoice_sms') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="amber"
                                                icon="message-square"
                                                icon:variant="outline"
                                                wire:click="sendSms({{ $invoice->id }})"
                                            />
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
        </div>
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
