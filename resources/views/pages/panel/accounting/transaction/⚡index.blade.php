<?php

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
    public string $tagFilter = '';

    #[Url]
    public string $accountFilter = '';

    #[Url]
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'desc';
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

    public function updatedTagFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortDirection(): void
    {
        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    #[On('panels.accounting.transaction.index.table')]
    public function refreshTable(): void
    {
        unset($this->transactions, $this->filterTags, $this->filterAccounts);
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function filterTags(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Transaction::tagNamesForBusiness($businessId);
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function filterAccounts(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Account::cachedOptionsForBusiness($businessId);
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return Transaction::query()
            ->with(['account', 'destinationAccount', 'party', 'category:id,name'])
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('reference_number', 'like', $search)
                        ->orWhere('note', 'like', $search)
                        ->orWhere('currency', 'like', $search)
                        ->orWhereHas('account', fn ($accountQuery) => $accountQuery->where('name', 'like', $search))
                        ->orWhereHas('party', function ($partyQuery) use ($search): void {
                            $partyQuery->where('name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when(
                $this->tagFilter !== '' && $businessId !== null,
                fn ($query) => $query->withAnyTags([$this->tagFilter], Transaction::tagTypeForBusiness($businessId))
            )
            ->when($this->accountFilter !== '', function ($query): void {
                $accountId = (int) $this->accountFilter;

                $query->where(function ($query) use ($accountId): void {
                    $query->where('account_id', $accountId)
                        ->orWhere('destination_account_id', $accountId);
                });
            })
            ->orderBy('transaction_date', $direction)
            ->orderBy('created_at', $direction)
            ->paginate(config('general.per_page', 15));
    }

    public function formatDate(\DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Jalalian::fromDateTime($date)->format('Y/m/d');
    }

    public function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }
};
?>

<x-slot name="title">{{ __('general.transactions') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.transactions') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.transactions') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.transactions_page_hint') }}</flux:text>
            </div>

            <flux:modal.trigger name="transaction.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_transaction') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if (auth()->user()->current_business_id === null)
        <flux:callout icon="building" variant="secondary">
            {{ __('general.no_business_yet') }}
        </flux:callout>
    @endif

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-6">
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
                    <flux:select.option value="{{ $type->value }}" wire:key="filter-tx-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="accountFilter" searchable variant="listbox" placeholder="{{ __('general.all_accounts') }}" clearable>
                <flux:select.option value="">{{ __('general.all_accounts') }}</flux:select.option>
                @foreach ($this->filterAccounts as $account)
                    <flux:select.option value="{{ $account->id }}" wire:key="filter-tx-account-{{ $account->id }}">
                        {{ $account->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="tagFilter" searchable variant="listbox" placeholder="{{ __('general.all_tags') }}" clearable>
                <flux:select.option value="">{{ __('general.all_tags') }}</flux:select.option>
                @foreach ($this->filterTags as $tagName)
                    <flux:select.option value="{{ $tagName }}" wire:key="filter-tx-tag-{{ $tagName }}">
                        {{ $tagName }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="sortDirection" variant="listbox" placeholder="{{ __('general.sort_newest_first') }}">
                <flux:select.option value="desc">{{ __('general.sort_newest_first') }}</flux:select.option>
                <flux:select.option value="asc">{{ __('general.sort_oldest_first') }}</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->transactions">
            <flux:table.columns>
                <flux:table.column>{{ __('general.transaction_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.transaction_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.account') }}</flux:table.column>
                <flux:table.column>{{ __('general.party') }}</flux:table.column>
                <flux:table.column>{{ __('general.category') }}</flux:table.column>
                <flux:table.column>{{ __('general.amount') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->transactions as $transaction)
                    <flux:table.row :key="$transaction->id">
                        <flux:table.cell>{{ $this->formatDate($transaction->transaction_date) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$transaction->type->badgeColor()">
                                {{ $transaction->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->account?->name ?? '—' }}
                            @if ($transaction->type === TransactionType::Transfer && $transaction->destinationAccount)
                                <flux:text size="sm" class="mt-0.5 block text-zinc-500">
                                    → {{ $transaction->destinationAccount->name }}
                                </flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->party?->displayName() ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->category?->name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $this->formatAmount((string) $transaction->amount) }} {{ $transaction->currency }}</span>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.transaction.edit.assign-data', { transaction: {{ $transaction->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.transaction.delete.assign-data', { transaction: {{ $transaction->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            {{ __('general.no_transactions') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.transaction.create :key="'transaction-create'" />
    <livewire:accounting.transaction.edit :key="'transaction-edit'" />
    <livewire:accounting.transaction.delete :key="'transaction-delete'" />
</div>
