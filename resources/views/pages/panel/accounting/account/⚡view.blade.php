<?php

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Currency;
use App\Support\LocaleDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDirectionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
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

        return Transaction::query()
            ->with(['party', 'invoice:id,invoice_number', 'loan:id,title', 'account', 'destinationAccount'])
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
                    <flux:text class="font-medium" dir="ltr">{{ $this->formattedOpeningBalance }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.current_balance') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formattedCurrentBalance }}</flux:text>
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

        <flux:table :paginate="$this->ledgerTransactions">
            <flux:table.columns>
                <flux:table.column>{{ __('general.transaction_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.transaction_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.direction') }}</flux:table.column>
                <flux:table.column>{{ __('general.party') }}</flux:table.column>
                <flux:table.column>{{ __('general.amount') }}</flux:table.column>
                <flux:table.column>{{ __('general.reference_number') }}</flux:table.column>
                <flux:table.column>{{ __('general.note') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->ledgerTransactions as $transaction)
                    @php($direction = $this->directionFor($transaction))
                    <flux:table.row :key="'account-tx-'.$transaction->id">
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
                        <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $transaction->amount) }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->reference_number ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->note ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">{{ __('general.no_transactions') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.account.edit :key="'account-edit-view-'.$account->id" />
    <livewire:accounting.account.delete :key="'account-delete-view-'.$account->id" />
</div>
