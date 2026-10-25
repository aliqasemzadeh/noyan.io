<?php

use App\Models\Accounting\Account;
use App\Models\Currency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.account.index.table')]
    public function refreshTable(): void
    {
        unset($this->accounts, $this->balancesByCurrency);
    }

    #[Computed]
    public function accounts(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Account::query()
            ->with('currency')
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('bank_name', 'like', $search)
                        ->orWhere('account_number', 'like', $search)
                        ->orWhere('card_number', 'like', $search)
                        ->orWhere('iban', 'like', $search)
                        ->orWhere('sub_type', 'like', $search)
                        ->orWhere('note', 'like', $search)
                        ->orWhereHas('currency', function ($query) use ($search): void {
                            $query->where('code', 'like', $search)
                                ->orWhere('name', 'like', $search);
                        });
                });
            })
            ->latest()
            ->paginate(15);
    }

    /**
     * @return Collection<int, array{currency: Currency, total: string, formatted: string}>
     */
    #[Computed]
    public function balancesByCurrency(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        $totals = Account::query()
            ->where('business_id', $businessId)
            ->selectRaw('currency_id, SUM(current_balance) as total')
            ->groupBy('currency_id')
            ->pluck('total', 'currency_id');

        if ($totals->isEmpty()) {
            return collect();
        }

        $currencies = Currency::query()
            ->whereIn('id', $totals->keys())
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        return $totals
            ->map(function (string|float $total, int|string $currencyId) use ($currencies): ?array {
                $currency = $currencies->get((int) $currencyId);

                if ($currency === null) {
                    return null;
                }

                $amount = (string) $total;

                return [
                    'currency' => $currency,
                    'total' => $amount,
                    'formatted' => $currency->formatAmount($amount),
                ];
            })
            ->filter()
            ->values();
    }

    public function formatCreatedAt(Account $account): string
    {
        return Jalalian::fromDateTime($account->created_at)->format('Y/m/d H:i');
    }

    public function formatBalance(Account $account): string
    {
        /** @var Currency|null $currency */
        $currency = $account->currency;

        if ($currency === null) {
            return (string) $account->current_balance;
        }

        return $currency->formatAmount((string) $account->current_balance);
    }
};
?>

<x-slot name="title">{{ __('general.cash_and_bank_accounts') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.cash_and_bank_accounts') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <flux:heading size="xl" level="1">
                {{ __('general.cash_and_bank_accounts') }}
            </flux:heading>

            <flux:modal.trigger name="account.create">
                <flux:button variant="primary" color="teal" icon="plus" :disabled="auth()->user()->current_business_id === null">
                    {{ __('general.create_account') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if (auth()->user()->current_business_id === null)
        <flux:callout icon="building" variant="secondary">
            {{ __('general.no_business_yet') }}
        </flux:callout>
    @endif

    @if ($this->balancesByCurrency->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->balancesByCurrency as $balance)
                <flux:card class="space-y-2">
                    <flux:text class="text-zinc-500">
                        {{ __('general.total_accounts_balance') }}
                        <span dir="ltr">({{ $balance['currency']->code }})</span>
                    </flux:text>
                    <flux:heading size="lg" class="font-semibold text-teal-700 dark:text-teal-400" dir="ltr">
                        {{ $balance['formatted'] }}
                    </flux:heading>
                </flux:card>
            @endforeach
        </div>
    @endif

    <flux:card>
        <div class="mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />
        </div>

        <flux:table :paginate="$this->accounts">
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.account_sub_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.account_number') }}</flux:table.column>
                <flux:table.column>{{ __('general.currency') }}</flux:table.column>
                <flux:table.column>{{ __('general.current_balance') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_active') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->accounts as $account)
                    <flux:table.row :key="$account->id">
                        <flux:table.cell>
                            <a
                                href="{{ route('accounting.accounts.view', $account) }}"
                                wire:navigate
                                class="font-medium text-teal-700 hover:underline dark:text-teal-400"
                            >
                                {{ $account->name }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$account->sub_type->badgeColor()">
                                {{ $account->sub_type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $account->primaryIdentifier() ?: '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $account->currency?->code }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $this->formatBalance($account) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$account->is_active ? 'green' : 'zinc'">
                                {{ $account->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($account) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                <flux:tooltip content="{{ __('general.view_account') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="zinc"
                                        icon="eye"
                                        icon:variant="outline"
                                        :href="route('accounting.accounts.view', $account)"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.account.edit.assign-data', { account: {{ $account->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.rebalance_account') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="teal"
                                        icon="refresh-cw"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.account.rebalance.assign-data', { account: {{ $account->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.account.delete.assign-data', { account: {{ $account->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">
                            {{ __('general.no_accounts') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.account.create :key="'account-create'" />
    <livewire:accounting.account.edit :key="'account-edit'" />
    <livewire:accounting.account.rebalance :key="'account-rebalance'" />
    <livewire:accounting.account.delete :key="'account-delete'" />
</div>
