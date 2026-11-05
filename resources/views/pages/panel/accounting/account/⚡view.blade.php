<?php

use App\Actions\Ledger\CreateJournalsFromSelectedTransactionsAction;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\Currency;
use App\Support\Accounting\AccountLedgerBalance;
use App\Support\LocaleDate;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Account $account;

    public string $search = '';

    public string $typeFilter = '';

    public string $directionFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    /** @var list<string> */
    public array $selected = [];

    /** @var array{mode: string, transfer: array<string, mixed>|null, items: list<array<string, mixed>>}|null */
    public ?array $bulkJournalPreview = null;

    public function mount(Account $account): void
    {
        Auth::user()?->ensureCurrentBusiness();

        abort_unless(
            (int) $account->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->account = $account->loadMissing('currency');
    }

    public function updatedSearch(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedDirectionFilter(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->bulkJournalPreview = null;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->ledgerTransactions
            ->getCollection()
            ->pluck('id')
            ->map(fn (int|string $id): string => (string) $id)
            ->all();

        if ($pageIds === []) {
            return;
        }

        $allSelected = collect($pageIds)->every(
            fn (string $id): bool => in_array($id, $this->selected, true)
        );

        if ($allSelected) {
            $this->selected = array_values(array_diff($this->selected, $pageIds));

            return;
        }

        $this->selected = array_values(array_unique([...$this->selected, ...$pageIds]));
    }

    public function openBulkCreateJournalModal(CreateJournalsFromSelectedTransactionsAction $action): void
    {
        if ($this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $user = Auth::user();

        if ($user === null) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $this->bulkJournalPreview = $action->preview(
            $user,
            array_map(intval(...), $this->selected),
        );

        Flux::modal('account.transaction.bulk-create-journal')->show();
    }

    public function applyBulkCreateJournal(CreateJournalsFromSelectedTransactionsAction $action): void
    {
        $user = Auth::user();

        if ($user === null || $this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        try {
            $result = $action->handle(
                $user,
                array_map(intval(...), $this->selected),
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? __('general.bulk_create_journal_failed');
            Flux::toast((string) $message);

            return;
        } catch (\Throwable $exception) {
            Flux::toast($exception->getMessage() ?: __('general.bulk_create_journal_failed'));

            return;
        }

        if ($result['mode'] === 'transfer' && is_array($result['transfer'])) {
            $message = __('general.bulk_create_journal_transfer_created', [
                'number' => $result['transfer']['voucher_number'],
            ]);
        } else {
            $message = __('general.bulk_create_journal_created', [
                'count' => $result['created'],
            ]);
        }

        if ($result['failed'] > 0) {
            $message .= ' '.__('general.bulk_create_journal_partial_failed', [
                'count' => $result['failed'],
            ]);
        }

        if ($result['skipped'] > 0) {
            $message .= ' '.__('general.bulk_skipped_incompatible', [
                'count' => $result['skipped'],
            ]);
        }

        $this->clearSelection();
        unset($this->ledgerTransactions, $this->formattedCurrentBalance, $this->pageSelected);
        $this->account->refresh()->loadMissing('currency');
        Flux::modals()->close();
        Flux::toast($message);
        $this->dispatch('panels.accounting.transaction.index.table');
    }

    public function linkedJournal(Transaction $transaction): ?JournalEntry
    {
        return $transaction->journalEntries
            ->first(fn (JournalEntry $entry): bool => ! str_contains((string) $entry->voucher_number, '__del_'));
    }

    #[Computed]
    public function pageSelected(): bool
    {
        $pageIds = $this->ledgerTransactions
            ->getCollection()
            ->pluck('id')
            ->map(fn (int|string $id): string => (string) $id)
            ->all();

        if ($pageIds === []) {
            return false;
        }

        return collect($pageIds)->every(
            fn (string $id): bool => in_array($id, $this->selected, true)
        );
    }

    #[On('panels.accounting.account.view.refresh')]
    public function refreshAccount(): void
    {
        $this->account->refresh()->loadMissing('currency');
        unset($this->formattedOpeningBalance, $this->formattedCurrentBalance, $this->formattedCreatedAt, $this->ledgerTransactions);
    }

    #[On('panels.accounting.account.index.table')]
    public function handleAccountDeleted(): void
    {
        if (! Account::query()->whereKey($this->account->id)->exists()) {
            $this->redirect(route('accounting.accounts.index'), navigate: true);
        }
    }

    #[On('panels.accounting.transaction.index.table')]
    #[On('panels.accounting.loan.index.table')]
    public function refreshLedger(): void
    {
        $this->account->refresh()->loadMissing('currency');
        unset($this->ledgerTransactions, $this->formattedCurrentBalance);
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    #[Computed]
    public function ledgerTransactions(): LengthAwarePaginator
    {
        $accountId = (int) $this->account->id;
        $dateFrom = LocaleDate::parseFilterDate($this->dateFrom);
        $dateTo = LocaleDate::parseFilterDate($this->dateTo);

        $paginator = Transaction::query()
            ->with([
                'party',
                'invoice:id,invoice_number',
                'loan:id,title',
                'account',
                'destinationAccount',
                'journalEntries' => fn ($query) => $query
                    ->where('voucher_number', 'not like', '%__del_%')
                    ->select(['id', 'business_id', 'voucher_number', 'referenceable_type', 'referenceable_id', 'status']),
            ])
            ->where(function ($query) use ($accountId): void {
                $query->where('account_id', $accountId)
                    ->orWhere('destination_account_id', $accountId);
            })
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('reference_number', 'like', $search)
                        ->orWhere('note', 'like', $search)
                        ->orWhereHas('party', function ($partyQuery) use ($search): void {
                            $partyQuery->where('name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->directionFilter === 'in', function ($query) use ($accountId): void {
                $query->where(function ($query) use ($accountId): void {
                    $query->where(function ($query) use ($accountId): void {
                        $query->where('account_id', $accountId)
                            ->where('type', TransactionType::Income);
                    })->orWhere(function ($query) use ($accountId): void {
                        $query->where('destination_account_id', $accountId)
                            ->where('type', TransactionType::Transfer);
                    });
                });
            })
            ->when($this->directionFilter === 'out', function ($query) use ($accountId): void {
                $query->where(function ($query) use ($accountId): void {
                    $query->where(function ($query) use ($accountId): void {
                        $query->where('account_id', $accountId)
                            ->whereIn('type', [TransactionType::Expense, TransactionType::Transfer]);
                    });
                });
            })
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('transaction_date', '<=', $dateTo))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(config('general.per_page', 15));

        AccountLedgerBalance::attachForAccount($this->account, $paginator->getCollection());

        return $paginator;
    }

    public function directionFor(Transaction $transaction): string
    {
        $accountId = (int) $this->account->id;

        return match ($transaction->type) {
            TransactionType::Income => 'in',
            TransactionType::Expense => 'out',
            TransactionType::Transfer => (int) $transaction->destination_account_id === $accountId ? 'in' : 'out',
        };
    }

    #[Computed]
    public function formattedOpeningBalance(): string
    {
        return $this->formatCurrencyAmount((string) $this->account->opening_balance);
    }

    #[Computed]
    public function formattedCurrentBalance(): string
    {
        return $this->formatCurrencyAmount((string) $this->account->current_balance);
    }

    #[Computed]
    public function formattedCreatedAt(): string
    {
        return LocaleDate::formatDateTime($this->account->created_at);
    }

    public function formatDate(\DateTimeInterface|string|null $date): string
    {
        return LocaleDate::formatDate($date);
    }

    public function formatAmount(string $amount): string
    {
        return $this->formatCurrencyAmount($amount);
    }

    public function dateFilterPlaceholder(): string
    {
        return LocaleDate::filterPlaceholder();
    }

    public function dateFilterInputType(): string
    {
        return LocaleDate::filterInputType();
    }

    protected function formatCurrencyAmount(string $amount): string
    {
        /** @var Currency|null $currency */
        $currency = $this->account->currency;

        if ($currency !== null) {
            return $currency->formatAmount($amount);
        }

        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }
};
?>

<x-slot name="title">{{ $account->name }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.accounts.index')" wire:navigate>
                {{ __('general.cash_and_bank_accounts') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $account->name }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div class="space-y-2">
                <flux:heading size="xl" level="1">
                    {{ $account->name }}
                </flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$account->sub_type->badgeColor()">
                        {{ $account->sub_type->label() }}
                    </flux:badge>
                    <flux:badge size="sm" color="zinc">
                        {{ $account->type->label() }}
                    </flux:badge>
                    <flux:badge size="sm" :color="$account->is_active ? 'green' : 'zinc'">
                        {{ $account->is_active ? __('general.active') : __('general.inactive') }}
                    </flux:badge>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <flux:button
                    variant="primary"
                    color="teal"
                    icon="plus"
                    wire:click="$dispatch('panels.accounting.transaction.create.open', { type: 'expense', accountId: {{ $account->id }} })"
                >
                    {{ __('general.create_transaction') }}
                </flux:button>

                <flux:dropdown>
                    <flux:button icon:trailing="chevron-down" variant="primary" color="zinc">
                        {{ __('general.options') }}
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item
                            icon="pencil"
                            wire:click="$dispatch('panels.accounting.account.edit.assign-data', { account: {{ $account->id }} })"
                        >
                            {{ __('general.edit') }}
                        </flux:menu.item>
                        <flux:menu.item
                            icon="refresh-cw"
                            wire:click="$dispatch('panels.accounting.account.rebalance.assign-data', { account: {{ $account->id }} })"
                        >
                            {{ __('general.rebalance_account') }}
                        </flux:menu.item>
                        <flux:menu.separator />
                        <flux:menu.item
                            variant="danger"
                            icon="trash"
                            wire:click="$dispatch('panels.accounting.account.delete.assign-data', { account: {{ $account->id }} })"
                        >
                            {{ __('general.delete') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.account_details') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.currency') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">
                        {{ $account->currency?->code }} — {{ $account->currency?->name }}
                    </flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.opening_balance') }}</flux:text>
                    <x-accounting.signed-amount :amount="$account->opening_balance" :currency="$account->currency" />
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.current_balance') }}</flux:text>
                    <x-accounting.signed-amount :amount="$account->current_balance" :currency="$account->currency" />
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.account_sub_type') }}</flux:text>
                    <flux:text class="font-medium">{{ $account->sub_type->label() }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.account_type') }}</flux:text>
                    <flux:text class="font-medium">{{ $account->type->label() }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.created_at') }}</flux:text>
                    <flux:text class="font-medium">{{ $this->formattedCreatedAt }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.bank_details') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.bank_name') }}</flux:text>
                    <flux:text class="font-medium">{{ $account->bank_name ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.account_number') }}</flux:text>
                    @if ($account->account_number)
                        <flux:input readonly copyable :value="$account->account_number" dir="ltr" class="max-w-56" />
                    @else
                        <flux:text class="font-medium">—</flux:text>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.card_number') }}</flux:text>
                    @if ($account->card_number)
                        <flux:input readonly copyable :value="$account->card_number" dir="ltr" class="max-w-56" />
                    @else
                        <flux:text class="font-medium">—</flux:text>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.iban') }}</flux:text>
                    @if ($account->iban)
                        <flux:input readonly copyable :value="$account->iban" dir="ltr" class="max-w-64" />
                    @else
                        <flux:text class="font-medium">—</flux:text>
                    @endif
                </div>
            </div>
        </flux:card>
    </div>

    @if ($account->note)
        <flux:card class="space-y-2">
            <flux:heading size="lg">{{ __('general.note') }}</flux:heading>
            <flux:text>{{ $account->note }}</flux:text>
        </flux:card>
    @endif

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('general.account_ledger') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.account_ledger_hint') }}</flux:text>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
                class="md:col-span-2"
            />

            <flux:select wire:model.live="typeFilter" searchable variant="listbox" placeholder="{{ __('general.all_transaction_types') }}">
                <flux:select.option value="">{{ __('general.all_transaction_types') }}</flux:select.option>
                @foreach (TransactionType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="account-tx-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="directionFilter" searchable variant="listbox" placeholder="{{ __('general.all_directions') }}">
                <flux:select.option value="">{{ __('general.all_directions') }}</flux:select.option>
                <flux:select.option value="in">{{ __('general.direction_in') }}</flux:select.option>
                <flux:select.option value="out">{{ __('general.direction_out') }}</flux:select.option>
            </flux:select>

            @if (\App\Support\LocaleDate::usesJalali())
                <x-jalali-date-range
                    wire:model.start="dateFrom"
                    wire:model.end="dateTo"
                    :label="false"
                    :placeholder="__('general.date_range_placeholder')"
                />
            @else
                <flux:input
                    type="date"
                    wire:model.live="dateFrom"
                    placeholder="{{ __('general.date_from') }}"
                    clearable
                />
                <flux:input
                    type="date"
                    wire:model.live="dateTo"
                    placeholder="{{ __('general.date_to') }}"
                    clearable
                />
            @endif
        </div>

        @if (count($selected) > 0)
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 dark:border-teal-900 dark:bg-teal-950/40">
                <flux:text class="font-medium text-teal-800 dark:text-teal-200">
                    {{ __('general.selected_count', ['count' => count($selected)]) }}
                </flux:text>

                <flux:spacer />

                <flux:button size="sm" variant="primary" color="teal" icon="book-plus" wire:click="openBulkCreateJournalModal">
                    {{ __('general.bulk_create_journal') }}
                </flux:button>

                <flux:button size="sm" variant="ghost" wire:click="clearSelection">
                    {{ __('general.bulk_clear_selection') }}
                </flux:button>
            </div>
        @endif

        <flux:table :paginate="$this->ledgerTransactions">
            <flux:table.columns>
                <flux:table.column class="w-10">
                    <flux:checkbox
                        wire:click.prevent="toggleSelectPage"
                        :checked="$this->pageSelected"
                    />
                </flux:table.column>
                <flux:table.column>{{ __('general.transaction_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.transaction_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.direction') }}</flux:table.column>
                <flux:table.column>{{ __('general.party') }}</flux:table.column>
                <flux:table.column>{{ __('general.amount') }}</flux:table.column>
                <flux:table.column>{{ __('general.balance_after') }}</flux:table.column>
                <flux:table.column>{{ __('general.linked_journal_entry') }}</flux:table.column>
                <flux:table.column>{{ __('general.reference_number') }}</flux:table.column>
                <flux:table.column>{{ __('general.note') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->ledgerTransactions as $transaction)
                    @php
                        $direction = $this->directionFor($transaction);
                        $linkedJournal = $this->linkedJournal($transaction);
                    @endphp
                    <flux:table.row :key="'account-tx-'.$transaction->id">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" value="{{ $transaction->id }}" />
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatDate($transaction->transaction_date) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$transaction->type->badgeColor()">
                                {{ $transaction->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$direction === 'in' ? 'green' : 'rose'">
                                {{ $direction === 'in' ? __('general.direction_in') : __('general.direction_out') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $transaction->party?->displayName() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <x-accounting.signed-amount
                                :amount="$transaction->amount"
                                :direction="$direction"
                                :currency="$account->currency"
                            />
                        </flux:table.cell>
                        <flux:table.cell>
                            <x-accounting.signed-amount
                                :amount="$transaction->balance_after ?? '0'"
                                :currency="$account->currency"
                            />
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($linkedJournal)
                                <a
                                    href="{{ route('accounting.journal-entries.view', $linkedJournal) }}"
                                    wire:navigate
                                    class="text-teal-700 underline decoration-teal-300 underline-offset-2 dark:text-teal-300"
                                    dir="ltr"
                                >
                                    {{ $linkedJournal->voucher_number }}
                                </a>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('general.no_linked_journal_entry') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $transaction->reference_number ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->note ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10">{{ __('general.no_transactions') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="account.transaction.bulk-create-journal" flyout position="right" class="min-w-full sm:min-w-lg">
        <form wire:submit="applyBulkCreateJournal" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.bulk_create_journal') }}</flux:heading>
                <flux:text class="mt-2">{{ __('general.bulk_create_journal_hint') }}</flux:text>
                <flux:text class="mt-1">{{ __('general.selected_count', ['count' => count($selected)]) }}</flux:text>
            </div>

            @if (is_array($bulkJournalPreview) && ($bulkJournalPreview['mode'] ?? null) === 'transfer' && is_array($bulkJournalPreview['transfer'] ?? null))
                <flux:callout icon="arrow-left-right" variant="secondary">
                    <flux:callout.heading>{{ __('general.bulk_create_journal_transfer_title') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('general.bulk_create_journal_transfer_summary', [
                            'amount' => number_format((float) $bulkJournalPreview['transfer']['amount']),
                            'from' => $bulkJournalPreview['transfer']['from_account'],
                            'to' => $bulkJournalPreview['transfer']['to_account'],
                        ]) }}
                        <span class="ms-1" dir="ltr">{{ $bulkJournalPreview['transfer']['currency'] }}</span>
                    </flux:callout.text>
                </flux:callout>
            @elseif (is_array($bulkJournalPreview))
                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('general.bulk_create_journal_independent_title') }}</flux:heading>

                    @foreach (($bulkJournalPreview['items'] ?? []) as $item)
                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="account-bulk-journal-item-{{ $item['id'] }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-medium">#{{ $item['id'] }} — {{ $item['type'] }}</div>
                                    <flux:text size="sm" class="mt-0.5">{{ $item['account'] }}</flux:text>
                                </div>
                                <div class="text-end">
                                    <div dir="ltr" class="font-medium">{{ number_format((float) $item['amount']) }} {{ $item['currency'] }}</div>
                                    <flux:badge size="sm" :color="$item['status'] === 'already_linked' ? 'zinc' : 'teal'" class="mt-1">
                                        {{ $item['status'] === 'already_linked'
                                            ? __('general.bulk_create_journal_already_linked')
                                            : __('general.bulk_create_journal_will_create') }}
                                    </flux:badge>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex">
                <flux:button type="submit" variant="primary" color="teal" class="w-full" icon="book-plus">
                    {{ __('general.bulk_create_journal') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <livewire:accounting.account.edit :key="'account-edit-view-'.$account->id" />
    <livewire:accounting.account.rebalance :key="'account-rebalance-view-'.$account->id" />
    <livewire:accounting.account.delete :key="'account-delete-view-'.$account->id" />
    <livewire:accounting.transaction.create :key="'account-transaction-create-'.$account->id" />
    <livewire:accounting.transaction.edit :key="'account-transaction-edit-'.$account->id" />
    <livewire:accounting.transaction.delete :key="'account-transaction-delete-'.$account->id" />
</div>
