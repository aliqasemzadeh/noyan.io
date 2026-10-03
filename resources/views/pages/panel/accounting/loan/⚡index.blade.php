<?php

use App\Enums\Accounting\LoanStatus;
use App\Enums\Accounting\LoanType;
use App\Models\Accounting\Loan;
use App\Support\LocaleDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    #[On('panels.accounting.loan.index.table')]
    public function refreshTable(): void
    {
        unset($this->loans);
    }

    /**
     * @return LengthAwarePaginator<int, Loan>
     */
    #[Computed]
    public function loans(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Loan::query()
            ->with(['party', 'account'])
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhereHas('party', function ($partyQuery) use ($search): void {
                            $partyQuery->where('name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(config('general.per_page', 15));
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
};
?>

<x-slot name="title">{{ __('general.debts_and_loans') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.debts_and_loans') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.debts_and_loans') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.loans_page_hint') }}</flux:text>
            </div>

            <flux:modal.trigger name="loan.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_loan') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
                class="md:col-span-2"
            />

            <flux:select wire:model.live="typeFilter" searchable variant="listbox" placeholder="{{ __('general.all_loan_types') }}">
                <flux:select.option value="">{{ __('general.all_loan_types') }}</flux:select.option>
                @foreach (LoanType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="filter-loan-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" searchable variant="listbox" placeholder="{{ __('general.all_loan_statuses') }}">
                <flux:select.option value="">{{ __('general.all_loan_statuses') }}</flux:select.option>
                @foreach (LoanStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}" wire:key="filter-loan-status-{{ $status->value }}">
                        {{ $status->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->loans">
            <flux:table.columns>
                <flux:table.column>{{ __('general.title') }}</flux:table.column>
                <flux:table.column>{{ __('general.loan_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.party') }}</flux:table.column>
                <flux:table.column>{{ __('general.total_amount') }}</flux:table.column>
                <flux:table.column>{{ __('general.remaining_amount') }}</flux:table.column>
                <flux:table.column>{{ __('general.loan_status') }}</flux:table.column>
                <flux:table.column>{{ __('general.issue_date') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->loans as $loan)
                    <flux:table.row :key="$loan->id">
                        <flux:table.cell class="font-medium">
                            <a href="{{ route('accounting.loans.show', $loan) }}" wire:navigate class="hover:underline">
                                {{ $loan->title }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$loan->type->badgeColor()">
                                {{ $loan->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $loan->party?->displayName() ?? '—' }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $loan->total_amount) }}</flux:table.cell>
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
                        <flux:table.cell colspan="8">
                            {{ __('general.no_loans') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.loan.create :key="'loan-create-index'" />
</div>
