<?php

use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\LoanStatus;
use App\Enums\Accounting\LoanType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Accounting\PartyContact;
use App\Models\Accounting\Transaction;
use App\Support\LocaleDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Party $party;

    public string $ledgerTab = 'transactions';

    public string $transactionSearch = '';

    public string $transactionTypeFilter = '';

    public string $transactionDateFrom = '';

    public string $transactionDateTo = '';

    public string $invoiceSearch = '';

    public string $invoiceTypeFilter = '';

    public string $invoiceStatusFilter = '';

    public string $invoiceDateFrom = '';

    public string $invoiceDateTo = '';

    public string $loanSearch = '';

    public string $loanTypeFilter = '';

    public string $loanStatusFilter = '';

    public function mount(Party $party): void
    {
        Auth::user()?->ensureCurrentBusiness();

        abort_unless(
            (int) $party->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->party = $party;
    }

    public function updatedLedgerTab(): void
    {
        $this->resetPage('txPage');
        $this->resetPage('invoicePage');
        $this->resetPage('loanPage');
    }

    public function updatedTransactionSearch(): void
    {
        $this->resetPage('txPage');
    }

    public function updatedTransactionTypeFilter(): void
    {
        $this->resetPage('txPage');
    }

    public function updatedTransactionDateFrom(): void
    {
        $this->resetPage('txPage');
    }

    public function updatedTransactionDateTo(): void
    {
        $this->resetPage('txPage');
    }

    public function updatedInvoiceSearch(): void
    {
        $this->resetPage('invoicePage');
    }

    public function updatedInvoiceTypeFilter(): void
    {
        $this->resetPage('invoicePage');
    }

    public function updatedInvoiceStatusFilter(): void
    {
        $this->resetPage('invoicePage');
    }

    public function updatedInvoiceDateFrom(): void
    {
        $this->resetPage('invoicePage');
    }

    public function updatedInvoiceDateTo(): void
    {
        $this->resetPage('invoicePage');
    }

    public function updatedLoanSearch(): void
    {
        $this->resetPage('loanPage');
    }

    public function updatedLoanTypeFilter(): void
    {
        $this->resetPage('loanPage');
    }

    public function updatedLoanStatusFilter(): void
    {
        $this->resetPage('loanPage');
    }

    #[On('panels.accounting.party.view.refresh')]
    public function refreshParty(): void
    {
        if (! Party::query()->whereKey($this->party->id)->exists()) {
            $this->redirect(route('accounting.parties.index'), navigate: true);

            return;
        }

        $this->party->refresh();
        unset(
            $this->contacts,
            $this->formattedBalance,
            $this->formattedCreditLimit,
            $this->formattedCreatedAt,
            $this->ledgerTransactions,
            $this->ledgerInvoices,
            $this->ledgerLoans,
        );
    }

    #[On('panels.accounting.party.view.contacts')]
    public function refreshContacts(): void
    {
        unset($this->contacts);
        $this->party->refresh();
    }

    #[On('panels.accounting.party.index.table')]
    public function handlePartyListChanged(): void
    {
        if (! Party::query()->whereKey($this->party->id)->exists()) {
            $this->redirect(route('accounting.parties.index'), navigate: true);
        }
    }

    #[On('panels.accounting.transaction.index.table')]
    #[On('panels.accounting.invoice.index.table')]
    #[On('panels.accounting.loan.index.table')]
    public function refreshLedgers(): void
    {
        $this->party->refresh();
        unset($this->ledgerTransactions, $this->ledgerInvoices, $this->ledgerLoans, $this->formattedBalance);
    }

    /**
     * @return Collection<int, PartyContact>
     */
    #[Computed]
    public function contacts(): Collection
    {
        return $this->party->contacts()
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    #[Computed]
    public function ledgerTransactions(): LengthAwarePaginator
    {
        $dateFrom = LocaleDate::parseFilterDate($this->transactionDateFrom);
        $dateTo = LocaleDate::parseFilterDate($this->transactionDateTo);

        return $this->party->transactions()
            ->with(['account', 'invoice:id,invoice_number', 'loan:id,title', 'category:id,name'])
            ->when($this->transactionSearch !== '', function ($query): void {
                $search = '%'.$this->transactionSearch.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('reference_number', 'like', $search)
                        ->orWhere('note', 'like', $search)
                        ->orWhereHas('account', fn ($accountQuery) => $accountQuery->where('name', 'like', $search));
                });
            })
            ->when($this->transactionTypeFilter !== '', fn ($query) => $query->where('type', $this->transactionTypeFilter))
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('transaction_date', '<=', $dateTo))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(config('general.per_page', 15), pageName: 'txPage');
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    #[Computed]
    public function ledgerInvoices(): LengthAwarePaginator
    {
        $dateFrom = LocaleDate::parseFilterDate($this->invoiceDateFrom);
        $dateTo = LocaleDate::parseFilterDate($this->invoiceDateTo);

        return $this->party->invoices()
            ->when($this->invoiceSearch !== '', function ($query): void {
                $search = '%'.$this->invoiceSearch.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'like', $search)
                        ->orWhere('note', 'like', $search)
                        ->orWhere('party_name', 'like', $search);
                });
            })
            ->when($this->invoiceTypeFilter !== '', fn ($query) => $query->where('type', $this->invoiceTypeFilter))
            ->when($this->invoiceStatusFilter !== '', fn ($query) => $query->where('payment_status', $this->invoiceStatusFilter))
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('issue_date', '<=', $dateTo))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(config('general.per_page', 15), pageName: 'invoicePage');
    }

    /**
     * @return LengthAwarePaginator<int, Loan>
     */
    #[Computed]
    public function ledgerLoans(): LengthAwarePaginator
    {
        return $this->party->loans()
            ->with('account')
            ->when($this->loanSearch !== '', function ($query): void {
                $search = '%'.$this->loanSearch.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            })
            ->when($this->loanTypeFilter !== '', fn ($query) => $query->where('type', $this->loanTypeFilter))
            ->when($this->loanStatusFilter !== '', fn ($query) => $query->where('status', $this->loanStatusFilter))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(config('general.per_page', 15), pageName: 'loanPage');
    }

    #[Computed]
    public function formattedBalance(): string
    {
        return $this->formatAmount((string) $this->party->balance);
    }

    #[Computed]
    public function formattedCreditLimit(): string
    {
        return $this->formatAmount((string) $this->party->credit_limit);
    }

    #[Computed]
    public function formattedCreatedAt(): string
    {
        return LocaleDate::formatDateTime($this->party->created_at);
    }

    public function formatDate(\DateTimeInterface|string|null $date): string
    {
        return LocaleDate::formatDate($date);
    }

    public function usesJalaliDates(): bool
    {
        return LocaleDate::usesJalali();
    }

    public function dateFilterPlaceholder(): string
    {
        return LocaleDate::filterPlaceholder();
    }

    public function dateFilterInputType(): string
    {
        return LocaleDate::filterInputType();
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

<x-slot name="title">{{ $party->name }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.parties.index')" wire:navigate>
                {{ __('general.parties') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $party->name }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div class="space-y-2">
                <flux:heading size="xl" level="1">
                    {{ $party->name }}
                </flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$party->type->badgeColor()">
                        {{ $party->type->label() }}
                    </flux:badge>
                    @if ($party->is_customer)
                        <flux:badge size="sm" color="teal">{{ __('general.customer') }}</flux:badge>
                    @endif
                    @if ($party->is_supplier)
                        <flux:badge size="sm" color="amber">{{ __('general.supplier') }}</flux:badge>
                    @endif
                    <flux:badge size="sm" :color="$party->is_active ? 'green' : 'zinc'">
                        {{ $party->is_active ? __('general.active') : __('general.inactive') }}
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
                        wire:click="$dispatch('panels.accounting.party.edit.assign-data', { party: {{ $party->id }} })"
                    >
                        {{ __('general.edit') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item
                        variant="danger"
                        icon="trash"
                        wire:click="$dispatch('panels.accounting.party.delete.assign-data', { party: {{ $party->id }} })"
                    >
                        {{ __('general.delete') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.party_details') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.legal_name') }}</flux:text>
                    <flux:text class="font-medium">{{ $party->legal_name ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.national_id') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->national_id ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.economic_code') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->economic_code ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.balance') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formattedBalance }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.credit_limit') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formattedCreditLimit }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.created_at') }}</flux:text>
                    <flux:text class="font-medium">{{ $this->formattedCreatedAt }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.contact_info') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.mobile') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->mobile ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.phone') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->phone ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.email') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->email ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.postal_code') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->postal_code ?: '—' }}</flux:text>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.address') }}</flux:text>
                    <flux:text class="max-w-xs text-end font-medium">{{ $party->address ?: '—' }}</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('general.party_contacts') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.party_contacts_hint') }}</flux:text>
            </div>

            <flux:button
                variant="primary"
                color="teal"
                icon="plus"
                wire:click="$dispatch('panels.accounting.party.contact.create.assign-data', { party: {{ $party->id }} })"
            >
                {{ __('general.create_party_contact') }}
            </flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.contact_position') }}</flux:table.column>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column>{{ __('general.email') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_primary_contact') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->contacts as $contact)
                    <flux:table.row :key="$contact->id">
                        <flux:table.cell class="font-medium">{{ $contact->name }}</flux:table.cell>
                        <flux:table.cell>{{ $contact->position ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $contact->mobile ?: '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $contact->email ?: '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($contact->is_primary)
                                <flux:badge size="sm" color="teal">{{ __('general.primary') }}</flux:badge>
                            @else
                                <flux:text>—</flux:text>
                            @endif
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
                                        wire:click="$dispatch('panels.accounting.party.contact.edit.assign-data', { contact: {{ $contact->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.party.contact.delete.assign-data', { contact: {{ $contact->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            {{ __('general.no_party_contacts') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('general.party_ledger') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.party_ledger_hint') }}</flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button
                size="sm"
                :variant="$ledgerTab === 'transactions' ? 'primary' : 'ghost'"
                :color="$ledgerTab === 'transactions' ? 'teal' : 'zinc'"
                wire:click="$set('ledgerTab', 'transactions')"
            >
                {{ __('general.ledger_tab_transactions') }}
            </flux:button>
            <flux:button
                size="sm"
                :variant="$ledgerTab === 'invoices' ? 'primary' : 'ghost'"
                :color="$ledgerTab === 'invoices' ? 'teal' : 'zinc'"
                wire:click="$set('ledgerTab', 'invoices')"
            >
                {{ __('general.ledger_tab_invoices') }}
            </flux:button>
            <flux:button
                size="sm"
                :variant="$ledgerTab === 'loans' ? 'primary' : 'ghost'"
                :color="$ledgerTab === 'loans' ? 'teal' : 'zinc'"
                wire:click="$set('ledgerTab', 'loans')"
            >
                {{ __('general.ledger_tab_loans') }}
            </flux:button>
        </div>

        @if ($ledgerTab === 'transactions')
            <div class="grid gap-3 md:grid-cols-4">
                <flux:input
                    wire:model.live.debounce.300ms="transactionSearch"
                    icon="search"
                    placeholder="{{ __('general.search') }}..."
                    clearable
                    class="md:col-span-2"
                />
                <flux:select wire:model.live="transactionTypeFilter" searchable variant="listbox" placeholder="{{ __('general.all_transaction_types') }}">
                    <flux:select.option value="">{{ __('general.all_transaction_types') }}</flux:select.option>
                    @foreach (TransactionType::cases() as $type)
                        @continue($type === TransactionType::Transfer)
                        <flux:select.option value="{{ $type->value }}" wire:key="party-tx-type-{{ $type->value }}">
                            {{ $type->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <div class="md:col-span-4 lg:col-span-1">
                    @if (\App\Support\LocaleDate::usesJalali())
                        <x-jalali-date-range
                            wire:model.start="transactionDateFrom"
                            wire:model.end="transactionDateTo"
                            :label="false"
                            :placeholder="__('general.date_range_placeholder')"
                        />
                    @else
                        <div class="grid grid-cols-2 gap-2">
                            <flux:input
                                type="date"
                                wire:model.live="transactionDateFrom"
                                placeholder="{{ __('general.date_from') }}"
                                clearable
                            />
                            <flux:input
                                type="date"
                                wire:model.live="transactionDateTo"
                                placeholder="{{ __('general.date_to') }}"
                                clearable
                            />
                        </div>
                    @endif
                </div>
            </div>

            <flux:table :paginate="$this->ledgerTransactions">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.transaction_date') }}</flux:table.column>
                    <flux:table.column>{{ __('general.transaction_type') }}</flux:table.column>
                    <flux:table.column>{{ __('general.account') }}</flux:table.column>
                    <flux:table.column>{{ __('general.amount') }}</flux:table.column>
                    <flux:table.column>{{ __('general.related_invoice') }}</flux:table.column>
                    <flux:table.column>{{ __('general.related_loan') }}</flux:table.column>
                    <flux:table.column>{{ __('general.note') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->ledgerTransactions as $transaction)
                        <flux:table.row :key="'party-tx-'.$transaction->id">
                            <flux:table.cell>{{ $this->formatDate($transaction->transaction_date) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$transaction->type->badgeColor()">
                                    {{ $transaction->type->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $transaction->account?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $transaction->amount) }}</flux:table.cell>
                            <flux:table.cell>{{ $transaction->invoice?->invoice_number ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($transaction->loan)
                                    <a href="{{ route('accounting.loans.show', $transaction->loan) }}" wire:navigate class="hover:underline">
                                        {{ $transaction->loan->title }}
                                    </a>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $transaction->note ?: '—' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ __('general.no_transactions') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @elseif ($ledgerTab === 'invoices')
            <div class="grid gap-3 md:grid-cols-4">
                <flux:input
                    wire:model.live.debounce.300ms="invoiceSearch"
                    icon="search"
                    placeholder="{{ __('general.search') }}..."
                    clearable
                    class="md:col-span-2"
                />
                <flux:select wire:model.live="invoiceTypeFilter" searchable variant="listbox" placeholder="{{ __('general.all_invoice_types') }}">
                    <flux:select.option value="">{{ __('general.all_invoice_types') }}</flux:select.option>
                    @foreach (InvoiceType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}" wire:key="party-inv-type-{{ $type->value }}">
                            {{ $type->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="invoiceStatusFilter" searchable variant="listbox" placeholder="{{ __('general.payment_status') }}">
                    <flux:select.option value="">{{ __('general.payment_status') }}</flux:select.option>
                    @foreach (InvoicePaymentStatus::cases() as $status)
                        <flux:select.option value="{{ $status->value }}" wire:key="party-inv-status-{{ $status->value }}">
                            {{ $status->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                @if (\App\Support\LocaleDate::usesJalali())
                    <x-jalali-date-range
                        wire:model.start="invoiceDateFrom"
                        wire:model.end="invoiceDateTo"
                        :label="false"
                        :placeholder="__('general.date_range_placeholder')"
                        class="md:col-span-2"
                    />
                @else
                    <flux:input
                        type="date"
                        wire:model.live="invoiceDateFrom"
                        placeholder="{{ __('general.date_from') }}"
                        clearable
                    />
                    <flux:input
                        type="date"
                        wire:model.live="invoiceDateTo"
                        placeholder="{{ __('general.date_to') }}"
                        clearable
                    />
                @endif
            </div>

            <flux:table :paginate="$this->ledgerInvoices">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.invoice_number') }}</flux:table.column>
                    <flux:table.column>{{ __('general.invoice_type') }}</flux:table.column>
                    <flux:table.column>{{ __('general.payment_status') }}</flux:table.column>
                    <flux:table.column>{{ __('general.issue_date') }}</flux:table.column>
                    <flux:table.column>{{ __('general.total_amount') }}</flux:table.column>
                    <flux:table.column>{{ __('general.paid_amount') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->ledgerInvoices as $invoice)
                        <flux:table.row :key="'party-inv-'.$invoice->id">
                            <flux:table.cell class="font-medium">{{ $invoice->invoice_number }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$invoice->type->badgeColor()">
                                    {{ $invoice->type->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$invoice->payment_status->badgeColor()">
                                    {{ $invoice->payment_status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->formatDate($invoice->issue_date) }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $invoice->total_amount) }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $invoice->paid_amount) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:tooltip content="{{ __('general.view') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="zinc"
                                        icon="eye"
                                        icon:variant="outline"
                                        :href="route('accounting.invoices.edit', $invoice)"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ __('general.no_invoices') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            <div class="grid gap-3 md:grid-cols-3">
                <flux:input
                    wire:model.live.debounce.300ms="loanSearch"
                    icon="search"
                    placeholder="{{ __('general.search') }}..."
                    clearable
                />
                <flux:select wire:model.live="loanTypeFilter" searchable variant="listbox" placeholder="{{ __('general.all_loan_types') }}">
                    <flux:select.option value="">{{ __('general.all_loan_types') }}</flux:select.option>
                    @foreach (LoanType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}" wire:key="party-loan-type-{{ $type->value }}">
                            {{ $type->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="loanStatusFilter" searchable variant="listbox" placeholder="{{ __('general.all_loan_statuses') }}">
                    <flux:select.option value="">{{ __('general.all_loan_statuses') }}</flux:select.option>
                    @foreach (LoanStatus::cases() as $status)
                        <flux:select.option value="{{ $status->value }}" wire:key="party-loan-status-{{ $status->value }}">
                            {{ $status->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:table :paginate="$this->ledgerLoans">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.title') }}</flux:table.column>
                    <flux:table.column>{{ __('general.loan_type') }}</flux:table.column>
                    <flux:table.column>{{ __('general.principal_amount') }}</flux:table.column>
                    <flux:table.column>{{ __('general.remaining_amount') }}</flux:table.column>
                    <flux:table.column>{{ __('general.loan_status') }}</flux:table.column>
                    <flux:table.column>{{ __('general.issue_date') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->ledgerLoans as $loan)
                        <flux:table.row :key="'party-loan-'.$loan->id">
                            <flux:table.cell class="font-medium">{{ $loan->title }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$loan->type->badgeColor()">
                                    {{ $loan->type->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $loan->principal_amount) }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ $this->formatAmount($loan->remainingAmount()) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$loan->status->badgeColor()">
                                    {{ $loan->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->formatDate($loan->issue_date) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:tooltip content="{{ __('general.view') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="zinc"
                                        icon="eye"
                                        icon:variant="outline"
                                        :href="route('accounting.loans.show', $loan)"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ __('general.no_loans') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <livewire:accounting.party.edit :key="'party-edit-view-'.$party->id" />
    <livewire:accounting.party.delete :key="'party-delete-view-'.$party->id" />
    <livewire:accounting.party.contact.create :key="'party-contact-create-'.$party->id" />
    <livewire:accounting.party.contact.edit :key="'party-contact-edit-'.$party->id" />
    <livewire:accounting.party.contact.delete :key="'party-contact-delete-'.$party->id" />
</div>
