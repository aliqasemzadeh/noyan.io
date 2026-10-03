<?php

use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Models\Accounting\Cheque;
use App\Support\LocaleDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    #[Url]
    public string $typeFilter = 'received';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if (! in_array($this->typeFilter, ChequeType::values(), true)) {
            $this->typeFilter = ChequeType::Received->value;
        }
    }

    public function updatedTypeFilter(): void
    {
        if (! in_array($this->typeFilter, ChequeType::values(), true)) {
            $this->typeFilter = ChequeType::Received->value;
        }

        unset($this->cheques, $this->columnTotals);
    }

    public function updatedSearch(): void
    {
        unset($this->cheques, $this->columnTotals);
    }

    #[On('panels.accounting.cheque.index.table')]
    public function refreshBoard(): void
    {
        unset($this->cheques, $this->columnTotals);
    }

    public function chequeType(): ChequeType
    {
        return ChequeType::from($this->typeFilter);
    }

    /**
     * @return Collection<string, Collection<int, Cheque>>
     */
    #[Computed]
    public function cheques(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        $cheques = Cheque::query()
            ->with(['party:id,name,legal_name', 'account:id,name'])
            ->where('business_id', $businessId)
            ->where('type', $this->typeFilter)
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('cheque_number', 'like', $search)
                        ->orWhere('sayad_number', 'like', $search)
                        ->orWhere('bank_name', 'like', $search)
                        ->orWhereHas('party', function ($partyQuery) use ($search): void {
                            $partyQuery->where('name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(250)
            ->get();

        return $cheques->groupBy(fn (Cheque $cheque): string => $cheque->status->value);
    }

    /**
     * @return Collection<string, object{status: string, total_count: int, total_amount: string}>
     */
    #[Computed]
    public function columnTotals(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Cheque::query()
            ->selectRaw('status, count(*) as total_count, coalesce(sum(amount), 0) as total_amount')
            ->where('business_id', $businessId)
            ->where('type', $this->typeFilter)
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('cheque_number', 'like', $search)
                        ->orWhere('sayad_number', 'like', $search)
                        ->orWhere('bank_name', 'like', $search)
                        ->orWhereHas('party', function ($partyQuery) use ($search): void {
                            $partyQuery->where('name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            })
            ->groupBy('status')
            ->get()
            ->keyBy('status');
    }

    public function formatDate(\DateTimeInterface|string|null $date): string
    {
        return LocaleDate::formatDate($date);
    }

    public function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }

    public function dueBadgeColor(Cheque $cheque): string
    {
        if ($cheque->status->isTerminal()) {
            return 'zinc';
        }

        if ($cheque->isOverdue()) {
            return 'rose';
        }

        return $cheque->daysUntilDue() <= 7 ? 'amber' : 'zinc';
    }
};
?>

<x-slot name="title">{{ __('general.cheques') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.cheques') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.cheques') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.cheques_page_hint') }}</flux:text>
            </div>

            <flux:modal.trigger name="cheque.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_cheque') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center">
            <flux:radio.group wire:model.live="typeFilter" variant="segmented" class="w-full md:w-auto">
                @foreach (ChequeType::cases() as $type)
                    <flux:radio
                        :value="$type->value"
                        :label="$type->label()"
                        wire:key="cheque-type-filter-{{ $type->value }}"
                    />
                @endforeach
            </flux:radio.group>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.cheque_search_placeholder') }}"
                clearable
                class="flex-1"
            />
        </div>

        @php
            $columns = ChequeStatus::forType($this->chequeType());
            $hasAny = $this->cheques->flatten(1)->isNotEmpty();
        @endphp

        @if (! $hasAny && $this->search === '')
            <div class="rounded-xl border border-dashed border-zinc-200 px-6 py-12 text-center dark:border-zinc-700">
                <flux:heading size="lg">{{ __('general.no_cheques') }}</flux:heading>
                <flux:text class="mt-2">{{ __('general.cheques_empty_state_hint') }}</flux:text>
                <div class="mt-4">
                    <flux:modal.trigger name="cheque.create">
                        <flux:button variant="primary" color="teal" icon="plus">
                            {{ __('general.create_cheque') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <flux:kanban>
                    @foreach ($columns as $status)
                        @php
                            $cards = $this->cheques->get($status->value, collect());
                            $totals = $this->columnTotals->get($status->value);
                            $count = (int) ($totals->total_count ?? $cards->count());
                            $totalAmount = (string) ($totals->total_amount ?? '0');
                        @endphp

                        <flux:kanban.column wire:key="cheque-column-{{ $status->value }}">
                            <flux:kanban.column.header
                                :heading="$status->label()"
                                :count="$count"
                                :badge="$this->formatAmount($totalAmount)"
                            />

                            <flux:kanban.column.cards>
                                @forelse ($cards as $cheque)
                                    <flux:kanban.card :heading="$cheque->party?->displayName() ?? '—'" wire:key="cheque-card-{{ $cheque->id }}">
                                        <x-slot name="header">
                                            <div class="flex items-center justify-between gap-2">
                                                <flux:badge size="sm" :color="$status->badgeColor()">
                                                    {{ $status->label() }}
                                                </flux:badge>

                                                @if (! $cheque->status->isTerminal() || $cheque->status === ChequeStatus::Registered)
                                                    <flux:dropdown>
                                                        <flux:button variant="subtle" icon="ellipsis-horizontal" size="sm" />
                                                        <flux:menu>
                                                            @if (! $cheque->status->isTerminal())
                                                                <flux:menu.item
                                                                    icon="arrow-right-left"
                                                                    wire:click="$dispatch('panels.accounting.cheque.change-status.assign-data', { chequeId: {{ $cheque->id }} })"
                                                                >
                                                                    {{ __('general.change_cheque_status') }}
                                                                </flux:menu.item>
                                                            @endif

                                                            @if (in_array($cheque->status, [ChequeStatus::Registered, ChequeStatus::Deposited], true))
                                                                <flux:menu.item
                                                                    icon="pencil"
                                                                    wire:click="$dispatch('panels.accounting.cheque.edit.assign-data', { chequeId: {{ $cheque->id }} })"
                                                                >
                                                                    {{ __('general.edit') }}
                                                                </flux:menu.item>
                                                            @endif

                                                            @if ($cheque->status === ChequeStatus::Registered)
                                                                <flux:menu.separator />
                                                                <flux:menu.item
                                                                    variant="danger"
                                                                    icon="trash"
                                                                    wire:click="$dispatch('panels.accounting.cheque.delete.assign-data', { chequeId: {{ $cheque->id }} })"
                                                                >
                                                                    {{ __('general.delete') }}
                                                                </flux:menu.item>
                                                            @endif
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                @endif
                                            </div>
                                        </x-slot>

                                        <div class="mt-2 space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                                            <div>{{ __('general.cheque_number') }}: <span dir="ltr">{{ $cheque->cheque_number }}</span></div>
                                            <div>{{ $cheque->bank_name }}</div>
                                            <div dir="ltr" class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $this->formatAmount((string) $cheque->amount) }}
                                            </div>
                                        </div>

                                        <x-slot name="footer">
                                            <flux:badge size="sm" :color="$this->dueBadgeColor($cheque)">
                                                {{ __('general.due_date') }}: {{ $this->formatDate($cheque->due_date) }}
                                            </flux:badge>
                                        </x-slot>
                                    </flux:kanban.card>
                                @empty
                                    <flux:text class="px-1 py-2 text-sm text-zinc-500">
                                        {{ __('general.no_cheques_in_column') }}
                                    </flux:text>
                                @endforelse
                            </flux:kanban.column.cards>
                        </flux:kanban.column>
                    @endforeach
                </flux:kanban>
            </div>
        @endif
    </flux:card>

    <livewire:accounting.cheque.create :key="'cheque-create-index'" />
    <livewire:accounting.cheque.change-status :key="'cheque-change-status-index'" />
    <livewire:accounting.cheque.edit :key="'cheque-edit-index'" />
    <livewire:accounting.cheque.delete :key="'cheque-delete-index'" />
</div>
