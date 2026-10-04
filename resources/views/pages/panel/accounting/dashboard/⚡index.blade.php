<?php

use App\Models\Accounting\Cheque;
use App\Support\LocaleDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * @return array{overdue: Collection<int, Cheque>, upcoming: Collection<int, Cheque>, overdue_count: int, upcoming_count: int}
     */
    #[Computed]
    public function chequeAlerts(): array
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return [
                'overdue' => collect(),
                'upcoming' => collect(),
                'overdue_count' => 0,
                'upcoming_count' => 0,
            ];
        }

        $overdue = Cheque::query()
            ->with(['party:id,name,legal_name'])
            ->where('business_id', $businessId)
            ->overdue()
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        $upcoming = Cheque::query()
            ->with(['party:id,name,legal_name'])
            ->where('business_id', $businessId)
            ->dueWithin(7)
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        return [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'overdue_count' => Cheque::query()->where('business_id', $businessId)->overdue()->count(),
            'upcoming_count' => Cheque::query()->where('business_id', $businessId)->dueWithin(7)->count(),
        ];
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

    public function dueLabel(Cheque $cheque): string
    {
        $days = $cheque->daysUntilDue();

        if ($days < 0) {
            return __('general.cheque_overdue_by_days', ['days' => abs($days)]);
        }

        if ($days === 0) {
            return __('general.cheque_due_today');
        }

        return __('general.cheque_due_in_days', ['days' => $days]);
    }
};
?>

<x-slot name="title">{{ __('general.accounting') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.accounting') }}
            </flux:heading>

            <flux:text class="mt-2 text-base">
                {{ __('general.accounting_dashboard_placeholder') }}
            </flux:text>
        </div>
    </div>

    @php($alerts = $this->chequeAlerts)

    @if ($alerts['overdue_count'] > 0 || $alerts['upcoming_count'] > 0)
        <div class="space-y-4">
            @if ($alerts['overdue_count'] > 0)
                <flux:callout icon="calendar-clock" variant="danger" inline>
                    {{ __('general.cheques_overdue') }} ({{ $alerts['overdue_count'] }}) —
                    <a href="{{ route('accounting.cheques.index') }}" wire:navigate class="underline">
                        {{ __('general.view_all_cheques') }}
                    </a>
                </flux:callout>
            @endif

            <flux:card>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('general.cheque_alerts') }}</flux:heading>
                    <a href="{{ route('accounting.cheques.index') }}" wire:navigate class="text-sm text-teal-700 hover:underline dark:text-teal-400">
                        {{ __('general.view_all_cheques') }}
                    </a>
                </div>

                <div class="space-y-3">
                    @foreach ($alerts['overdue']->concat($alerts['upcoming']) as $cheque)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700" wire:key="dashboard-cheque-{{ $cheque->id }}">
                            <div>
                                <div class="font-medium">{{ $cheque->party?->displayName() }} — <span dir="ltr">{{ $cheque->cheque_number }}</span></div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    <flux:badge size="sm" :color="$cheque->type->badgeColor()">{{ $cheque->type->label() }}</flux:badge>
                                    <span dir="ltr">{{ $this->formatAmount((string) $cheque->amount) }}</span>
                                    <span>{{ $this->formatDate($cheque->due_date) }}</span>
                                </div>
                            </div>
                            <flux:badge size="sm" :color="$cheque->isOverdue() ? 'rose' : 'amber'">
                                {{ $this->dueLabel($cheque) }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('accounting.invoices.create', ['type' => 'sale']) }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start gap-3">
                    <flux:icon.trending-up class="size-6 text-teal-600 dark:text-teal-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.new_sale_invoice') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.quick_sale_invoice_hint') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>

        <a href="{{ route('accounting.invoices.create', ['type' => 'purchase']) }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-amber-300 dark:hover:border-amber-700">
                <div class="flex items-start gap-3">
                    <flux:icon.trending-down class="size-6 text-amber-600 dark:text-amber-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.new_purchase_invoice') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.quick_purchase_invoice_hint') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('accounting.invoices.index') }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start gap-3">
                    <flux:icon.file-text class="size-6 text-sky-600 dark:text-sky-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.invoices') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.invoices_page_hint') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>

        <a href="{{ route('accounting.cheques.index') }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start gap-3">
                    <flux:icon.banknote class="size-6 text-emerald-600 dark:text-emerald-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.cheques') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.cheques_page_hint') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>

        <a href="{{ route('accounting.journal-entries.index') }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start gap-3">
                    <flux:icon.book-open class="size-6 text-indigo-600 dark:text-indigo-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.journal_entries') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.journal_entries_page_hint') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>

        <a href="{{ route('accounting.accounts.index') }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start gap-3">
                    <flux:icon.wallet class="size-6 text-teal-600 dark:text-teal-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.cash_and_bank_accounts') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.treasury') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>

        <a href="{{ route('accounting.currencies.index') }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start gap-3">
                    <flux:icon.coins class="size-6 text-amber-600 dark:text-amber-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.business_currencies') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.currencies') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>
    </div>
</div>
