<?php

use App\Enums\Accounting\TransactionType;
use App\Enums\Support\TicketStatus;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    /**
     * @return array{
     *     members_count: int,
     *     businesses_count: int,
     *     active_businesses_count: int,
     *     daily_volume: string,
     *     today_income: string,
     *     today_expense: string,
     *     today_transactions_count: int,
     *     new_members_7d: int,
     *     open_tickets_count: int,
     * }
     */
    #[Computed]
    public function stats(): array
    {
        return Cache::remember('admin.dashboard.stats', now()->addMinute(), function (): array {
            $today = now()->toDateString();
            $weekAgo = now()->subDays(7);

            $todayIncome = (string) (Transaction::query()
                ->whereDate('transaction_date', $today)
                ->where('type', TransactionType::Income)
                ->sum('base_amount') ?: '0');

            $todayExpense = (string) (Transaction::query()
                ->whereDate('transaction_date', $today)
                ->where('type', TransactionType::Expense)
                ->sum('base_amount') ?: '0');

            return [
                'members_count' => User::query()->count(),
                'businesses_count' => Business::query()->count(),
                'active_businesses_count' => Business::query()->where('is_active', true)->count(),
                'daily_volume' => bcadd($todayIncome, $todayExpense, 18),
                'today_income' => $todayIncome,
                'today_expense' => $todayExpense,
                'today_transactions_count' => Transaction::query()
                    ->whereDate('transaction_date', $today)
                    ->count(),
                'new_members_7d' => User::query()->where('created_at', '>=', $weekAgo)->count(),
                'open_tickets_count' => Ticket::query()
                    ->where('status', '!=', TicketStatus::Closed->value)
                    ->count(),
            ];
        });
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function recentUsers(): Collection
    {
        return User::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'mobile', 'created_at']);
    }

    /**
     * @return Collection<int, Business>
     */
    #[Computed]
    public function recentBusinesses(): Collection
    {
        return Business::query()
            ->with('owner:id,first_name,last_name,mobile')
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'owner_id', 'is_active', 'created_at']);
    }

    public function todayLabel(): string
    {
        return Jalalian::now()->format('Y/m/d');
    }

    public function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format(
            (float) $normalized,
            substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0
        );
    }

    public function formatCreatedAt(\DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Jalalian::fromDateTime($date)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.system_management') }} - {{ __('general.app_name') }}</x-slot>

@php($stats = $this->stats)

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <flux:heading size="xl" level="1">
                    {{ __('general.system_management') }}
                </flux:heading>

                <flux:text class="mt-2 text-base">
                    {{ __('general.admin_dashboard_hint') }}
                </flux:text>
            </div>

            <flux:badge size="sm" color="zinc" icon="calendar">
                <span dir="ltr">{{ $this->todayLabel() }}</span>
            </flux:badge>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('system.users.index') }}" wire:navigate class="block min-w-0">
            <flux:card class="h-full transition hover:border-sky-300 dark:hover:border-sky-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:text class="text-sm">{{ __('general.total_members') }}</flux:text>
                        <flux:heading size="xl" class="mt-2 break-all text-2xl tabular-nums sm:text-3xl" dir="ltr">
                            {{ number_format($stats['members_count']) }}
                        </flux:heading>
                    </div>
                    <flux:icon.users class="size-8 shrink-0 text-sky-600 dark:text-sky-400" />
                </div>
            </flux:card>
        </a>

        <a href="{{ route('system.businesses.index') }}" wire:navigate class="block min-w-0">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:text class="text-sm">{{ __('general.total_businesses') }}</flux:text>
                        <flux:heading size="xl" class="mt-2 break-all text-2xl tabular-nums sm:text-3xl" dir="ltr">
                            {{ number_format($stats['businesses_count']) }}
                        </flux:heading>
                    </div>
                    <flux:icon.building class="size-8 shrink-0 text-teal-600 dark:text-teal-400" />
                </div>
            </flux:card>
        </a>

        <flux:card class="h-full min-w-0 sm:col-span-2 xl:col-span-1">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <flux:text class="text-sm">{{ __('general.daily_transaction_volume') }}</flux:text>
                    <flux:heading size="xl" class="mt-2 break-all text-2xl tabular-nums sm:text-3xl" dir="ltr">
                        {{ $this->formatAmount($stats['daily_volume']) }}
                    </flux:heading>
                    <flux:text class="mt-1 text-xs">
                        {{ __('general.today_transactions') }}:
                        <span dir="ltr">{{ number_format($stats['today_transactions_count']) }}</span>
                    </flux:text>
                </div>
                <flux:icon.banknote class="size-8 text-emerald-600 dark:text-emerald-400" />
            </div>
        </flux:card>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card>
            <div class="flex items-center gap-3">
                <flux:icon.activity class="size-5 text-teal-600 dark:text-teal-400" />
                <div>
                    <flux:text class="text-xs">{{ __('general.active_businesses') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">
                        {{ number_format($stats['active_businesses_count']) }}
                    </flux:heading>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <flux:icon.receipt class="size-5 text-amber-600 dark:text-amber-400" />
                <div>
                    <flux:text class="text-xs">{{ __('general.today_transactions') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">
                        {{ number_format($stats['today_transactions_count']) }}
                    </flux:heading>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <flux:icon.user-plus class="size-5 text-sky-600 dark:text-sky-400" />
                <div>
                    <flux:text class="text-xs">{{ __('general.new_members_7d') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">
                        {{ number_format($stats['new_members_7d']) }}
                    </flux:heading>
                </div>
            </div>
        </flux:card>

        <a href="{{ route('system.support.tickets.index') }}" wire:navigate class="block min-w-0">
            <flux:card class="h-full transition hover:border-rose-300 dark:hover:border-rose-700">
                <div class="flex items-center gap-3">
                    <flux:icon.ticket class="size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                    <div class="min-w-0">
                        <flux:text class="text-xs">{{ __('general.open_tickets') }}</flux:text>
                        <flux:heading size="lg" class="mt-1 break-all tabular-nums" dir="ltr">
                            {{ number_format($stats['open_tickets_count']) }}
                        </flux:heading>
                    </div>
                </div>
            </flux:card>
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card>
            <div class="flex items-center gap-3">
                <flux:icon.trending-up class="size-6 text-green-600 dark:text-green-400" />
                <div>
                    <flux:text class="text-sm">{{ __('general.today_income') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">
                        {{ $this->formatAmount($stats['today_income']) }}
                    </flux:heading>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <flux:icon.trending-down class="size-6 text-rose-600 dark:text-rose-400" />
                <div>
                    <flux:text class="text-sm">{{ __('general.today_expense') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">
                        {{ $this->formatAmount($stats['today_expense']) }}
                    </flux:heading>
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.recent_businesses') }}</flux:heading>
                <a href="{{ route('system.businesses.index') }}" wire:navigate class="shrink-0 whitespace-nowrap text-sm text-teal-700 hover:underline dark:text-teal-400">
                    {{ __('general.businesses') }}
                </a>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('general.name') }}</flux:table.column>
                    <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                    <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->recentBusinesses as $business)
                        <flux:table.row :key="'recent-business-'.$business->id">
                            <flux:table.cell>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span>{{ $business->name }}</span>
                                    @if ($business->is_active)
                                        <flux:badge size="sm" color="emerald">{{ __('general.active') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span dir="ltr">{{ $business->owner?->mobile ?? '—' }}</span>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->formatCreatedAt($business->created_at) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3">{{ __('general.no_businesses') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.recent_members') }}</flux:heading>
                <a href="{{ route('system.users.index') }}" wire:navigate class="shrink-0 whitespace-nowrap text-sm text-sky-700 hover:underline dark:text-sky-400">
                    {{ __('general.users') }}
                </a>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('general.name') }}</flux:table.column>
                    <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                    <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->recentUsers as $user)
                        <flux:table.row :key="'recent-user-'.$user->id">
                            <flux:table.cell>{{ $user->fullName() ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <span dir="ltr">{{ $user->mobile }}</span>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->formatCreatedAt($user->created_at) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3">{{ __('general.no_users') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <div>
        <flux:heading size="lg" class="mb-3">{{ __('general.quick_links') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ route('system.users.index') }}" wire:navigate class="block min-w-0">
                <flux:card class="h-full transition hover:border-sky-300 dark:hover:border-sky-700">
                    <div class="flex items-start gap-3">
                        <flux:icon.users class="size-6 shrink-0 text-sky-600 dark:text-sky-400" />
                        <div class="min-w-0">
                            <flux:heading size="lg">{{ __('general.users') }}</flux:heading>
                        </div>
                    </div>
                </flux:card>
            </a>

            <a href="{{ route('system.businesses.index') }}" wire:navigate class="block min-w-0">
                <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                    <div class="flex items-start gap-3">
                        <flux:icon.building class="size-6 shrink-0 text-teal-600 dark:text-teal-400" />
                        <div class="min-w-0">
                            <flux:heading size="lg">{{ __('general.businesses') }}</flux:heading>
                        </div>
                    </div>
                </flux:card>
            </a>

            <a href="{{ route('system.support.tickets.index') }}" wire:navigate class="block min-w-0">
                <flux:card class="h-full transition hover:border-rose-300 dark:hover:border-rose-700">
                    <div class="flex items-start gap-3">
                        <flux:icon.ticket class="size-6 shrink-0 text-rose-600 dark:text-rose-400" />
                        <div class="min-w-0">
                            <flux:heading size="lg">{{ __('general.tickets') }}</flux:heading>
                        </div>
                    </div>
                </flux:card>
            </a>

            <a href="{{ route('system.settings.index') }}" wire:navigate class="block min-w-0">
                <flux:card class="h-full transition hover:border-zinc-300 dark:hover:border-zinc-600">
                    <div class="flex items-start gap-3">
                        <flux:icon.settings class="size-6 shrink-0 text-zinc-600 dark:text-zinc-300" />
                        <div class="min-w-0">
                            <flux:heading size="lg">{{ __('general.system_settings') }}</flux:heading>
                        </div>
                    </div>
                </flux:card>
            </a>
        </div>
    </div>
</div>
