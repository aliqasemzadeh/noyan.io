<?php

use App\Enums\Accounting\LoanStatus;
use App\Models\Accounting\Loan;
use App\Support\LocaleDate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Loan $loan;

    public function mount(Loan $loan): void
    {
        Auth::user()?->ensureCurrentBusiness();

        abort_unless(
            (int) $loan->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->loan = $loan->loadMissing(['party', 'account']);
    }

    #[On('panels.accounting.loan.show.refresh')]
    public function refreshLoan(?int $loanId = null): void
    {
        if ($loanId !== null && $loanId !== (int) $this->loan->id) {
            return;
        }

        if (! Loan::query()->whereKey($this->loan->id)->exists()) {
            $this->redirect(route('accounting.loans.index'), navigate: true);

            return;
        }

        $this->loan->refresh()->loadMissing(['party', 'account']);
        unset($this->transactions);
    }

    #[On('panels.accounting.loan.index.table')]
    public function handleLoanListChanged(): void
    {
        $this->refreshLoan();
    }

    /**
     * @return Collection<int, \App\Models\Accounting\Transaction>
     */
    #[Computed]
    public function transactions(): Collection
    {
        return $this->loan->transactions()
            ->with(['account', 'party'])
            ->latest('transaction_date')
            ->latest('id')
            ->get();
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

<x-slot name="title">{{ $loan->title }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.loans.index')" wire:navigate>
                {{ __('general.debts_and_loans') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $loan->title }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div class="space-y-2">
                <flux:heading size="xl" level="1">{{ $loan->title }}</flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$loan->type->badgeColor()">
                        {{ $loan->type->label() }}
                    </flux:badge>
                    <flux:badge size="sm" :color="$loan->status->badgeColor()">
                        {{ $loan->status->label() }}
                    </flux:badge>
                </div>
            </div>

            @if ($loan->status === LoanStatus::Active)
                <flux:button
                    variant="primary"
                    color="teal"
                    icon="banknote"
                    wire:click="$dispatch('panels.accounting.loan.pay.assign-data', { loan: {{ $loan->id }} })"
                >
                    {{ __('general.loan_payment') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.loan_details') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.party') }}</flux:text>
                    <flux:text class="font-medium">{{ $loan->party?->displayName() ?? '—' }}</flux:text>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.account') }}</flux:text>
                    <flux:text class="font-medium">{{ $loan->account?->name ?? '—' }}</flux:text>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.issue_date') }}</flux:text>
                    <flux:text class="font-medium">{{ $this->formatDate($loan->issue_date) }}</flux:text>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.first_installment_date') }}</flux:text>
                    <flux:text class="font-medium">{{ $this->formatDate($loan->first_installment_date) }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.amount') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.principal_amount') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formatAmount((string) $loan->principal_amount) }}</flux:text>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.interest_amount') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formatAmount((string) $loan->interest_amount) }}</flux:text>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.total_amount') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formatAmount((string) $loan->total_amount) }}</flux:text>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.paid_amount') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formatAmount((string) $loan->paid_amount) }}</flux:text>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.remaining_amount') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formatAmount($loan->remainingAmount()) }}</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    @if ($loan->description)
        <flux:card class="space-y-2">
            <flux:heading size="lg">{{ __('general.description') }}</flux:heading>
            <flux:text>{{ $loan->description }}</flux:text>
        </flux:card>
    @endif

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('general.loan_transactions') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('general.transaction_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.transaction_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.account') }}</flux:table.column>
                <flux:table.column>{{ __('general.amount') }}</flux:table.column>
                <flux:table.column>{{ __('general.note') }}</flux:table.column>
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
                        <flux:table.cell>{{ $transaction->account?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell dir="ltr">{{ $this->formatAmount((string) $transaction->amount) }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->note ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            {{ __('general.no_transactions') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.loan.pay :key="'loan-pay-'.$loan->id" />
</div>
