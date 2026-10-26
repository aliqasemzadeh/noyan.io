<?php

use App\Actions\Notifications\SendInvoiceNotificationAction;
use App\Enums\AccountSubType;
use App\Enums\Accounting\ChequeType;
use App\Enums\Accounting\DashboardPeriod;
use App\Enums\Accounting\InvoiceAging;
use App\Enums\Accounting\InvoiceNotificationType;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Accounting\BusinessCurrency;
use App\Models\Accounting\Invoice;
use App\Services\Accounting\Dashboard\AccountingDashboardBuilder;
use App\Support\LocaleDate;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $period = DashboardPeriod::Month->value;

    #[Url]
    public string $currency = '';

    #[Url]
    public bool $compareLastYear = false;

    #[Url]
    public string $bucket = '';

    /** @var array<string, mixed> */
    public array $board = [];

    /** @var list<array<string, mixed>> */
    public array $cashFlow = [];

    /** @var list<array<string, mixed>> */
    public array $profitTrend = [];

    /** @var list<array<string, mixed>> */
    public array $expenseChart = [];

    /** @var list<array<string, mixed>> */
    public array $agingChart = [];

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if (DashboardPeriod::tryFrom($this->period) === null) {
            $this->period = DashboardPeriod::Month->value;
        }

        $this->load();
    }

    public function updatedPeriod(): void
    {
        $this->bucket = '';
        $this->load();
    }

    public function updatedCurrency(): void
    {
        $this->bucket = '';
        $this->load();
    }

    public function updatedCompareLastYear(): void
    {
        $this->bucket = '';
        $this->load();
    }

    public function focusBucket(string $key): void
    {
        $this->bucket = $this->bucket === $key ? '' : $key;
        $this->load();
    }

    public function remind(int $invoiceId, SendInvoiceNotificationAction $action): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::InvoiceView), 403);

        $businessId = Auth::user()?->current_business_id;
        $invoice = Invoice::query()
            ->where('business_id', $businessId)
            ->find($invoiceId);

        if ($invoice === null || ! $invoice->isFinalized()) {
            Flux::toast(text: __('general.dashboard_reminder_failed'), variant: 'danger');

            return;
        }

        $result = $action->handle($invoice, InvoiceNotificationType::PaymentReminder, force: true);

        if ($result['email'] || $result['sms']) {
            Flux::toast(__('general.dashboard_reminder_sent'));

            return;
        }

        Flux::toast(text: $result['errors'][0] ?? __('general.dashboard_reminder_failed'), variant: 'danger');
    }

    public function load(bool $retry = true): void
    {
        $dashboard = app(AccountingDashboardBuilder::class);
        $businessId = Auth::user()?->current_business_id;
        $period = DashboardPeriod::tryFrom($this->period) ?? DashboardPeriod::Month;

        $board = $businessId === null
            ? $dashboard->empty()
            : $dashboard->build($businessId, $period, $this->compareLastYear, $this->currency, $this->bucket);

        if ($retry && $this->bucket !== '' && ($board['bucket'] ?? '') === '') {
            $this->bucket = '';
            $this->load(false);

            return;
        }

        $this->board = $board;
        $this->cashFlow = $board['cash_flow'];
        $this->profitTrend = $board['profit_trend'];
        $this->expenseChart = $board['expenses_chart'];
        $this->agingChart = $board['aging'];
    }

    /**
     * @return Collection<int, BusinessCurrency>
     */
    #[Computed]
    public function currencyOptions(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return BusinessCurrency::query()
            ->with('currency:id,code,name')
            ->where('business_id', $businessId)
            ->get();
    }

    public function allows(BusinessPermission $permission): bool
    {
        return (bool) Auth::user()?->canInBusiness($permission);
    }

    public function operationsFirst(): bool
    {
        $roles = Auth::user()?->currentMembership()?->roleEnums() ?? [];

        if ($roles === []) {
            return false;
        }

        foreach ($roles as $role) {
            if ($role === BusinessRole::Owner || $role === BusinessRole::Admin) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function sectionOrder(): array
    {
        $sections = $this->operationsFirst()
            ? ['operations', 'charts', 'commerce']
            : ['charts', 'operations', 'commerce'];

        return array_values(array_filter($sections, fn (string $section): bool => match ($section) {
            'charts' => $this->showCharts(),
            'operations' => $this->showOperations(),
            'commerce' => $this->showCommerce(),
            default => false,
        }));
    }

    public function showCharts(): bool
    {
        return $this->allows(BusinessPermission::TransactionView)
            || $this->allows(BusinessPermission::InvoiceView);
    }

    public function showOperations(): bool
    {
        return $this->allows(BusinessPermission::ChequeView)
            || $this->allows(BusinessPermission::InvoiceView)
            || $this->allows(BusinessPermission::AccountView)
            || $this->allows(BusinessPermission::JournalEntryView);
    }

    public function showCommerce(): bool
    {
        return $this->allows(BusinessPermission::ProductView)
            || $this->allows(BusinessPermission::InvoiceView);
    }

    public function formatAmount(string|float|int|null $amount): string
    {
        return Money::format($amount === null ? '0' : (string) $amount, (int) ($this->board['decimal_places'] ?? 0));
    }

    public function formatPercent(?string $percent): string
    {
        if ($percent === null || ! is_numeric($percent)) {
            return '—';
        }

        $formatted = Money::format(ltrim($percent, '-'), 1);

        if (bccomp($percent, '0', 1) === 1) {
            return '+'.$formatted.'%';
        }

        if (bccomp($percent, '0', 1) === -1) {
            return '-'.$formatted.'%';
        }

        return $formatted.'%';
    }

    public function deltaColor(string $metric, ?string $percent): string
    {
        if ($percent === null || ! is_numeric($percent) || bccomp($percent, '0', 1) === 0) {
            return 'zinc';
        }

        $up = bccomp($percent, '0', 1) === 1;

        if ($metric === 'expenses') {
            return $up ? 'rose' : 'emerald';
        }

        return $up ? 'emerald' : 'rose';
    }

    public function formatDate(?string $date): string
    {
        return $date === null || $date === '' ? '—' : LocaleDate::formatDate($date);
    }

    public function dueLabel(string $date): string
    {
        if ($date === '') {
            return '';
        }

        $days = (int) now()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay(), false);

        if ($days < 0) {
            return __('general.cheque_overdue_by_days', ['days' => abs($days)]);
        }

        if ($days === 0) {
            return __('general.cheque_due_today');
        }

        return __('general.cheque_due_in_days', ['days' => $days]);
    }

    public function subTypeLabel(string $value): string
    {
        return AccountSubType::tryFrom($value)?->label() ?? $value;
    }

    public function chequeTypeLabel(string $value): string
    {
        return ChequeType::tryFrom($value)?->label() ?? $value;
    }

    public function moodianLabel(string $status): string
    {
        if ($status === 'unsent') {
            return __('general.dashboard_moodian_unsent');
        }

        return $status;
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

        <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <flux:heading size="xl" level="1">{{ __('general.accounting') }}</flux:heading>
                <flux:text class="mt-2 text-base">{{ __('general.accounting_dashboard_placeholder') }}</flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($this->allows(BusinessPermission::JournalEntryCreate))
                    <flux:button variant="primary" color="indigo" icon="file-pen-line" :href="route('accounting.journal-entries.create')" wire:navigate>
                        {{ __('general.create_journal_entry') }}
                    </flux:button>
                @endif

                @if ($this->allows(BusinessPermission::InvoiceCreate))
                    <flux:button variant="primary" color="teal" icon="trending-up" :href="route('accounting.invoices.create', ['type' => 'sale'])" wire:navigate>
                        {{ __('general.new_sale_invoice') }}
                    </flux:button>
                @endif

                @if ($this->allows(BusinessPermission::TransactionCreate))
                    <flux:button variant="primary" color="sky" icon="banknote" wire:click="$dispatch('panels.accounting.transaction.create.open', { type: 'income' })">
                        {{ __('general.dashboard_record_receipt') }}
                    </flux:button>
                    <flux:button variant="primary" color="rose" icon="receipt" wire:click="$dispatch('panels.accounting.transaction.create.open', { type: 'expense' })">
                        {{ __('general.dashboard_record_expense') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <flux:card>
        <div class="grid gap-3 md:grid-cols-3">
            <flux:select wire:model.live="period" variant="listbox" searchable :label="__('general.dashboard_period')">
                @foreach (DashboardPeriod::cases() as $option)
                    <flux:select.option value="{{ $option->value }}" wire:key="dashboard-period-{{ $option->value }}">
                        {{ $option->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="currency" variant="listbox" searchable :label="__('general.currency')">
                <flux:select.option value="">{{ __('general.dashboard_base_currency') }}</flux:select.option>
                @foreach ($this->currencyOptions as $option)
                    @if ($option->currency)
                        <flux:select.option value="{{ $option->currency->code }}" wire:key="dashboard-currency-{{ $option->id }}">
                            {{ $option->currency->code }} — {{ $option->currency->name }}
                        </flux:select.option>
                    @endif
                @endforeach
            </flux:select>

            <div class="flex items-end">
                <flux:field variant="inline" class="w-full">
                    <flux:switch wire:model.live="compareLastYear" />
                    <flux:label>{{ __('general.dashboard_compare_last_year') }}</flux:label>
                </flux:field>
            </div>
        </div>
    </flux:card>

    <livewire:accounting.ai-intro :key="'accounting-ai-intro'" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @if ($this->allows(BusinessPermission::AccountView))
            <a href="{{ route('accounting.accounts.index') }}" wire:navigate class="block">
                <flux:card class="h-full transition hover:border-sky-300 dark:hover:border-sky-700">
                    <div class="flex items-start justify-between gap-3">
                        <flux:text>{{ __('general.dashboard_cash_balance') }}</flux:text>
                        <flux:badge size="sm" color="sky" icon="wallet" />
                    </div>
                    <flux:heading size="xl" class="mt-2 tabular-nums" dir="ltr">
                        {{ $this->formatAmount($board['kpis']['cash_balance'] ?? '0') }}
                        <span class="text-sm font-medium text-zinc-500">{{ $board['currency_code'] ?? '' }}</span>
                    </flux:heading>
                    <flux:text class="mt-2">
                        {{ __('general.dashboard_cash_movement') }}:
                        <span dir="ltr">{{ $this->formatAmount($board['kpis']['cash_movement'] ?? '0') }}</span>
                    </flux:text>
                    <div class="mt-2">
                        <flux:badge size="sm" :color="$this->deltaColor('cash', $board['kpis']['cash_movement_change'] ?? null)">
                            {{ $this->formatPercent($board['kpis']['cash_movement_change'] ?? null) }}
                        </flux:badge>
                    </div>
                </flux:card>
            </a>
        @endif

        @if ($this->allows(BusinessPermission::InvoiceView))
            <a href="{{ route('accounting.invoices.index', ['typeFilter' => InvoiceType::Sale->value]) }}" wire:navigate class="block">
                <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                    <div class="flex items-start justify-between gap-3">
                        <flux:text>{{ __('general.dashboard_total_revenue') }}</flux:text>
                        <flux:badge size="sm" color="teal" icon="trending-up" />
                    </div>
                    <flux:heading size="xl" class="mt-2 tabular-nums" dir="ltr">
                        {{ $this->formatAmount($board['kpis']['revenue'] ?? '0') }}
                    </flux:heading>
                    <div class="mt-2">
                        <flux:badge size="sm" :color="$this->deltaColor('revenue', $board['kpis']['revenue_change'] ?? null)">
                            {{ $this->formatPercent($board['kpis']['revenue_change'] ?? null) }}
                        </flux:badge>
                    </div>
                </flux:card>
            </a>
        @endif

        @if ($this->allows(BusinessPermission::TransactionView))
            <a href="{{ route('accounting.transactions.index', ['typeFilter' => 'expense']) }}" wire:navigate class="block">
                <flux:card class="h-full transition hover:border-rose-300 dark:hover:border-rose-700">
                    <div class="flex items-start justify-between gap-3">
                        <flux:text>{{ __('general.dashboard_total_expenses') }}</flux:text>
                        <flux:badge size="sm" color="rose" icon="trending-down" />
                    </div>
                    <flux:heading size="xl" class="mt-2 tabular-nums" dir="ltr">
                        {{ $this->formatAmount($board['kpis']['expenses'] ?? '0') }}
                    </flux:heading>
                    <div class="mt-2">
                        <flux:badge size="sm" :color="$this->deltaColor('expenses', $board['kpis']['expenses_change'] ?? null)">
                            {{ $this->formatPercent($board['kpis']['expenses_change'] ?? null) }}
                        </flux:badge>
                    </div>
                </flux:card>
            </a>
        @endif

        @if ($this->allows(BusinessPermission::InvoiceView) && $this->allows(BusinessPermission::TransactionView))
            <flux:card class="h-full">
                <div class="flex items-start justify-between gap-3">
                    <flux:text>{{ __('general.dashboard_net_profit') }}</flux:text>
                    <flux:badge size="sm" color="emerald" icon="banknote" />
                </div>
                <flux:heading size="xl" class="mt-2 tabular-nums" dir="ltr">
                    {{ $this->formatAmount($board['kpis']['profit'] ?? '0') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ __('general.dashboard_profit_margin') }}:
                    {{ $this->formatPercent($board['kpis']['margin'] ?? null) }}
                </flux:text>
                <div class="mt-2">
                    <flux:badge size="sm" :color="$this->deltaColor('profit', $board['kpis']['profit_change'] ?? null)">
                        {{ $this->formatPercent($board['kpis']['profit_change'] ?? null) }}
                    </flux:badge>
                </div>
            </flux:card>
        @endif

        @if ($this->allows(BusinessPermission::InvoiceView))
            <a href="{{ route('accounting.invoices.index', ['typeFilter' => InvoiceType::Sale->value]) }}" wire:navigate class="block">
                <flux:card class="h-full transition hover:border-amber-300 dark:hover:border-amber-700">
                    <div class="flex items-start justify-between gap-3">
                        <flux:text>{{ __('general.dashboard_receivables') }}</flux:text>
                        <flux:badge size="sm" color="amber" icon="hand-coins" />
                    </div>
                    <flux:heading size="xl" class="mt-2 tabular-nums" dir="ltr">
                        {{ $this->formatAmount($board['kpis']['receivables'] ?? '0') }}
                    </flux:heading>
                    <flux:text class="mt-2">{{ __('general.dashboard_as_of_today') }}</flux:text>
                </flux:card>
            </a>

            <a href="{{ route('accounting.invoices.index', ['typeFilter' => InvoiceType::Purchase->value]) }}" wire:navigate class="block">
                <flux:card class="h-full transition hover:border-orange-300 dark:hover:border-orange-700">
                    <div class="flex items-start justify-between gap-3">
                        <flux:text>{{ __('general.dashboard_payables') }}</flux:text>
                        <flux:badge size="sm" color="orange" icon="receipt" />
                    </div>
                    <flux:heading size="xl" class="mt-2 tabular-nums" dir="ltr">
                        {{ $this->formatAmount($board['kpis']['payables'] ?? '0') }}
                    </flux:heading>
                    <flux:text class="mt-2">{{ __('general.dashboard_as_of_today') }}</flux:text>
                </flux:card>
            </a>
        @endif
    </div>

    @foreach ($this->sectionOrder() as $section)
        @if ($section === 'charts')
            <div class="space-y-4" wire:key="dashboard-section-charts">
                <flux:heading size="lg">{{ __('general.dashboard_trends') }}</flux:heading>

                <div class="grid gap-4 xl:grid-cols-2">
                    @if ($this->allows(BusinessPermission::TransactionView))
                        <flux:card>
                            <flux:heading size="lg">{{ __('general.dashboard_cash_flow') }}</flux:heading>
                            <div
                                class="mt-4 cursor-pointer"
                                x-data
                                @click="
                                    const keyEl = $el.querySelector('[data-chart-key]');
                                    if (! keyEl) return;
                                    const key = (keyEl.textContent || '').trim();
                                    if (key !== '') $wire.focusBucket(key);
                                "
                            >
                                <flux:chart wire:model="cashFlow" wire:key="cash-flow-{{ $period }}-{{ $currency }}-{{ $bucket }}">
                                    <flux:chart.viewport class="aspect-[3/1]">
                                        <flux:chart.svg>
                                            <flux:chart.group>
                                                <flux:chart.bar field="inflow" class="text-teal-500" radius="4 0" width="80%" />
                                                <flux:chart.bar field="outflow" class="text-rose-500" radius="4 0" width="80%" />
                                            </flux:chart.group>
                                            <flux:chart.axis axis="x" field="label">
                                                <flux:chart.axis.tick />
                                                <flux:chart.axis.line />
                                            </flux:chart.axis>
                                            <flux:chart.axis axis="y" :format="['useGrouping' => true]">
                                                <flux:chart.axis.grid />
                                                <flux:chart.axis.tick />
                                            </flux:chart.axis>
                                            <flux:chart.cursor type="area" />
                                        </flux:chart.svg>
                                    </flux:chart.viewport>
                                    <div class="flex justify-center gap-4 pt-4">
                                        <flux:chart.legend :label="__('general.dashboard_inflows')">
                                            <flux:chart.legend.indicator class="bg-teal-500" />
                                        </flux:chart.legend>
                                        <flux:chart.legend :label="__('general.dashboard_outflows')">
                                            <flux:chart.legend.indicator class="bg-rose-500" />
                                        </flux:chart.legend>
                                    </div>
                                    <flux:chart.tooltip>
                                        <flux:chart.tooltip.heading field="label" />
                                        <flux:chart.tooltip.value field="inflow" :label="__('general.dashboard_inflows')" :format="['useGrouping' => true]" />
                                        <flux:chart.tooltip.value field="outflow" :label="__('general.dashboard_outflows')" :format="['useGrouping' => true]" />
                                        <span data-chart-key class="hidden"><slot field="key"></slot></span>
                                    </flux:chart.tooltip>
                                </flux:chart>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($cashFlow as $point)
                                    <flux:button
                                        wire:key="cash-bucket-{{ $point['key'] }}"
                                        size="xs"
                                        variant="{{ $bucket === $point['key'] ? 'primary' : 'ghost' }}"
                                        color="{{ $bucket === $point['key'] ? 'teal' : 'zinc' }}"
                                        wire:click="focusBucket('{{ $point['key'] }}')"
                                    >
                                        {{ $point['label'] }}
                                    </flux:button>
                                @endforeach
                            </div>
                        </flux:card>
                    @endif

                    @if ($this->allows(BusinessPermission::InvoiceView) && $this->allows(BusinessPermission::TransactionView))
                        <flux:card>
                            <flux:heading size="lg">{{ __('general.dashboard_profit_trend') }}</flux:heading>
                            <div
                                class="mt-4 cursor-pointer"
                                x-data
                                @click="
                                    const keyEl = $el.querySelector('[data-chart-key]');
                                    if (! keyEl) return;
                                    const key = (keyEl.textContent || '').trim();
                                    if (key !== '') $wire.focusBucket(key);
                                "
                            >
                                <flux:chart wire:model="profitTrend" wire:key="profit-trend-{{ $period }}-{{ $currency }}-{{ $bucket }}">
                                    <flux:chart.viewport class="aspect-[3/1]">
                                        <flux:chart.svg>
                                            <flux:chart.group>
                                                <flux:chart.bar field="revenue" class="text-teal-500" radius="4 0" width="70%" />
                                                <flux:chart.bar field="expense" class="text-amber-500" radius="4 0" width="70%" />
                                            </flux:chart.group>
                                            <flux:chart.line field="profit" class="text-sky-500" />
                                            <flux:chart.axis axis="x" field="label">
                                                <flux:chart.axis.tick />
                                                <flux:chart.axis.line />
                                            </flux:chart.axis>
                                            <flux:chart.axis axis="y" :format="['useGrouping' => true]">
                                                <flux:chart.axis.grid />
                                                <flux:chart.axis.tick />
                                            </flux:chart.axis>
                                            <flux:chart.cursor type="area" />
                                        </flux:chart.svg>
                                    </flux:chart.viewport>
                                    <div class="flex flex-wrap justify-center gap-4 pt-4">
                                        <flux:chart.legend :label="__('general.dashboard_total_revenue')">
                                            <flux:chart.legend.indicator class="bg-teal-500" />
                                        </flux:chart.legend>
                                        <flux:chart.legend :label="__('general.dashboard_total_expenses')">
                                            <flux:chart.legend.indicator class="bg-amber-500" />
                                        </flux:chart.legend>
                                        <flux:chart.legend :label="__('general.dashboard_net_profit')">
                                            <flux:chart.legend.indicator class="bg-sky-500" />
                                        </flux:chart.legend>
                                    </div>
                                    <flux:chart.tooltip>
                                        <flux:chart.tooltip.heading field="label" />
                                        <flux:chart.tooltip.value field="revenue" :label="__('general.dashboard_total_revenue')" :format="['useGrouping' => true]" />
                                        <flux:chart.tooltip.value field="expense" :label="__('general.dashboard_total_expenses')" :format="['useGrouping' => true]" />
                                        <flux:chart.tooltip.value field="profit" :label="__('general.dashboard_net_profit')" :format="['useGrouping' => true]" />
                                        <span data-chart-key class="hidden"><slot field="key"></slot></span>
                                    </flux:chart.tooltip>
                                </flux:chart>
                            </div>
                        </flux:card>
                    @endif

                    @if ($this->allows(BusinessPermission::TransactionView))
                        <flux:card>
                            <flux:heading size="lg">{{ __('general.dashboard_expense_breakdown') }}</flux:heading>
                            @if ($expenseChart === [])
                                <flux:text class="mt-4">{{ __('general.dashboard_no_data') }}</flux:text>
                            @else
                                <div class="mt-4">
                                    <flux:chart wire:model="expenseChart" wire:key="expense-chart-{{ $period }}-{{ $currency }}-{{ $bucket }}">
                                        <flux:chart.viewport class="mx-auto aspect-square w-40">
                                            <flux:chart.svg>
                                                <flux:chart.pie field="value" label-field="label" inner-radius="60%" radius="4" />
                                            </flux:chart.svg>
                                        </flux:chart.viewport>
                                        <flux:chart.tooltip>
                                            <flux:chart.tooltip.value label-field="label" field="value" :format="['useGrouping' => true]" />
                                        </flux:chart.tooltip>
                                    </flux:chart>
                                </div>
                                <div class="mt-4 space-y-2">
                                    @foreach ($expenseChart as $slice)
                                        <a
                                            href="{{ route('accounting.transactions.index', ['typeFilter' => 'expense', 'categoryFilter' => $slice['category_id']]) }}"
                                            wire:navigate
                                            class="flex items-center justify-between gap-3 text-sm hover:text-teal-700 dark:hover:text-teal-300"
                                            wire:key="expense-slice-{{ $slice['category_id'] }}"
                                        >
                                            <span>{{ $slice['label'] }}</span>
                                            <span dir="ltr" class="tabular-nums">{{ $this->formatAmount((string) $slice['value']) }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </flux:card>
                    @endif

                    @if ($this->allows(BusinessPermission::InvoiceView))
                        <flux:card>
                            <flux:heading size="lg">{{ __('general.dashboard_aging') }}</flux:heading>
                            <div class="mt-4">
                                <flux:chart wire:model="agingChart" horizontal wire:key="aging-chart-{{ $currency }}">
                                    <flux:chart.viewport class="aspect-[2/1]">
                                        <flux:chart.svg>
                                            <flux:chart.group>
                                                <flux:chart.bar field="receivables" class="text-amber-500" radius="4 0" width="70%" />
                                                <flux:chart.bar field="payables" class="text-orange-500" radius="4 0" width="70%" />
                                            </flux:chart.group>
                                            <flux:chart.axis axis="y" field="label">
                                                <flux:chart.axis.tick />
                                                <flux:chart.axis.line />
                                            </flux:chart.axis>
                                            <flux:chart.axis axis="x" :format="['useGrouping' => true]">
                                                <flux:chart.axis.grid />
                                                <flux:chart.axis.tick />
                                            </flux:chart.axis>
                                            <flux:chart.cursor type="area" />
                                        </flux:chart.svg>
                                    </flux:chart.viewport>
                                    <div class="flex justify-center gap-4 pt-4">
                                        <flux:chart.legend :label="__('general.dashboard_receivables')">
                                            <flux:chart.legend.indicator class="bg-amber-500" />
                                        </flux:chart.legend>
                                        <flux:chart.legend :label="__('general.dashboard_payables')">
                                            <flux:chart.legend.indicator class="bg-orange-500" />
                                        </flux:chart.legend>
                                    </div>
                                    <flux:chart.tooltip>
                                        <flux:chart.tooltip.heading field="label" />
                                        <flux:chart.tooltip.value field="receivables" :label="__('general.dashboard_receivables')" :format="['useGrouping' => true]" />
                                        <flux:chart.tooltip.value field="payables" :label="__('general.dashboard_payables')" :format="['useGrouping' => true]" />
                                    </flux:chart.tooltip>
                                </flux:chart>
                            </div>
                            <div class="mt-4 space-y-2">
                                @foreach ($agingChart as $bucketRow)
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm" wire:key="aging-row-{{ $bucketRow['key'] }}">
                                        <span>{{ $bucketRow['label'] }}</span>
                                        <span class="flex gap-3">
                                            <a href="{{ route('accounting.invoices.index', ['typeFilter' => InvoiceType::Sale->value, 'aging' => $bucketRow['key']]) }}" wire:navigate class="text-amber-700 hover:underline dark:text-amber-300" dir="ltr">
                                                {{ $this->formatAmount((string) $bucketRow['receivables']) }}
                                            </a>
                                            <a href="{{ route('accounting.invoices.index', ['typeFilter' => InvoiceType::Purchase->value, 'aging' => $bucketRow['key']]) }}" wire:navigate class="text-orange-700 hover:underline dark:text-orange-300" dir="ltr">
                                                {{ $this->formatAmount((string) $bucketRow['payables']) }}
                                            </a>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </flux:card>
                    @endif
                </div>
            </div>
        @endif

        @if ($section === 'operations')
            <div class="space-y-4" wire:key="dashboard-section-operations">
                <flux:heading size="lg">{{ __('general.dashboard_follow_up') }}</flux:heading>

                @if ($this->allows(BusinessPermission::ChequeView) && (($board['cheques']['overdue_count'] ?? 0) > 0 || ($board['cheques']['upcoming_count'] ?? 0) > 0))
                    <flux:card>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <flux:heading size="lg">{{ __('general.cheque_alerts') }}</flux:heading>
                            <a href="{{ route('accounting.cheques.index') }}" wire:navigate class="text-sm text-teal-700 hover:underline dark:text-teal-400">
                                {{ __('general.view_all_cheques') }}
                            </a>
                        </div>
                        <div class="space-y-3">
                            @foreach ($board['cheques']['items'] as $cheque)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700" wire:key="dashboard-cheque-{{ $cheque['id'] }}">
                                    <div>
                                        <div class="font-medium">{{ $cheque['party'] }} — <span dir="ltr">{{ $cheque['number'] }}</span></div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                            <flux:badge size="sm" :color="$cheque['type'] === 'received' ? 'teal' : 'amber'">{{ $this->chequeTypeLabel($cheque['type']) }}</flux:badge>
                                            <span dir="ltr">{{ $this->formatAmount($cheque['amount']) }}</span>
                                            <span>{{ $this->formatDate($cheque['due_date']) }}</span>
                                        </div>
                                    </div>
                                    <flux:badge size="sm" :color="$cheque['overdue'] ? 'rose' : 'amber'">
                                        {{ $this->dueLabel($cheque['due_date']) }}
                                    </flux:badge>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @endif

                @if ($this->allows(BusinessPermission::InvoiceView))
                    <flux:card>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <flux:heading size="lg">{{ __('general.dashboard_overdue_invoices') }}</flux:heading>
                            <a href="{{ route('accounting.invoices.index', ['typeFilter' => InvoiceType::Sale->value, 'aging' => InvoiceAging::Days1To30->value]) }}" wire:navigate class="text-sm text-teal-700 hover:underline dark:text-teal-400">
                                {{ __('general.invoices') }}
                            </a>
                        </div>
                        @if (($board['overdue_invoices'] ?? []) === [])
                            <flux:text>{{ __('general.dashboard_no_data') }}</flux:text>
                        @else
                            <div class="space-y-3">
                                @foreach ($board['overdue_invoices'] as $invoice)
                                    <div class="flex flex-wrap items-center justify-between gap-3" wire:key="overdue-invoice-{{ $invoice['id'] }}">
                                        <div>
                                            <div class="font-medium">{{ $invoice['party'] }} — <span dir="ltr">{{ $invoice['number'] }}</span></div>
                                            <div class="mt-1 text-sm text-zinc-500">{{ $this->formatDate($invoice['due_date']) }}</div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span dir="ltr" class="tabular-nums">{{ $this->formatAmount($invoice['amount']) }}</span>
                                            <flux:tooltip content="{{ __('general.dashboard_send_reminder') }}">
                                                <flux:button size="xs" variant="primary" color="amber" icon="bell" icon:variant="outline" wire:click="remind({{ $invoice['id'] }})" />
                                            </flux:tooltip>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                @endif

                @if ($this->allows(BusinessPermission::AccountView))
                    <flux:card>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <flux:heading size="lg">{{ __('general.dashboard_bank_balances') }}</flux:heading>
                            <a href="{{ route('accounting.accounts.index') }}" wire:navigate class="text-sm text-teal-700 hover:underline dark:text-teal-400">
                                {{ __('general.accounts') }}
                            </a>
                        </div>
                        @if (($board['accounts'] ?? []) === [])
                            <flux:text>{{ __('general.dashboard_no_data') }}</flux:text>
                        @else
                            <div class="space-y-3">
                                @foreach ($board['accounts'] as $account)
                                    <a href="{{ route('accounting.accounts.view', $account['id']) }}" wire:navigate class="flex items-center justify-between gap-3 hover:text-teal-700 dark:hover:text-teal-300" wire:key="dashboard-account-{{ $account['id'] }}">
                                        <span>{{ $account['name'] }} <span class="text-sm text-zinc-500">{{ $this->subTypeLabel($account['sub_type']) }}</span></span>
                                        <span dir="ltr" class="tabular-nums">{{ $this->formatAmount($account['balance']) }} {{ $account['code'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                @endif

                @if ($this->allows(BusinessPermission::JournalEntryView))
                    <flux:card>
                        <flux:heading size="lg">{{ __('general.dashboard_draft_vouchers') }}</flux:heading>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a href="{{ route('accounting.journal-entries.index', ['statusFilter' => JournalEntryStatus::Draft->value]) }}" wire:navigate>
                                <flux:badge size="sm" color="amber">{{ __('general.dashboard_draft_count', ['count' => $board['drafts']['draft'] ?? 0]) }}</flux:badge>
                            </a>
                            <a href="{{ route('accounting.journal-entries.index', ['statusFilter' => JournalEntryStatus::PendingReview->value]) }}" wire:navigate>
                                <flux:badge size="sm" color="sky">{{ __('general.dashboard_pending_review_count', ['count' => $board['drafts']['pending_review'] ?? 0]) }}</flux:badge>
                            </a>
                        </div>
                    </flux:card>
                @endif
            </div>
        @endif

        @if ($section === 'commerce')
            <div class="space-y-4" wire:key="dashboard-section-commerce">
                <flux:heading size="lg">{{ __('general.dashboard_sales_stock') }}</flux:heading>
                <div class="grid gap-4 lg:grid-cols-3">
                    @if ($this->allows(BusinessPermission::ProductView))
                        <flux:card>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <flux:heading size="lg">{{ __('general.dashboard_low_stock') }}</flux:heading>
                                <a href="{{ route('accounting.catalog.products.index') }}" wire:navigate class="text-sm text-teal-700 hover:underline dark:text-teal-400">
                                    {{ __('general.products') }}
                                </a>
                            </div>
                            @if (($board['low_stock'] ?? []) === [])
                                <flux:text>{{ __('general.dashboard_no_data') }}</flux:text>
                            @else
                                <div class="space-y-2">
                                    @foreach ($board['low_stock'] as $product)
                                        <div class="flex items-center justify-between gap-3 text-sm" wire:key="low-stock-{{ $product['id'] }}">
                                            <span>{{ $product['name'] }}</span>
                                            <span dir="ltr" class="tabular-nums">{{ $this->formatAmount($product['stock']) }} / {{ $this->formatAmount($product['minimum']) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </flux:card>

                        <flux:card>
                            <flux:heading size="lg">{{ __('general.dashboard_top_products') }}</flux:heading>
                            @if (($board['top_products'] ?? []) === [])
                                <flux:text class="mt-3">{{ __('general.dashboard_no_data') }}</flux:text>
                            @else
                                <div class="mt-3 space-y-2">
                                    @foreach ($board['top_products'] as $product)
                                        <div class="flex items-center justify-between gap-3 text-sm" wire:key="top-product-{{ $product['id'] }}">
                                            <span>{{ $product['name'] }}</span>
                                            <span dir="ltr" class="tabular-nums">{{ $this->formatAmount($product['amount']) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </flux:card>
                    @endif

                    @if ($this->allows(BusinessPermission::InvoiceView))
                        <flux:card>
                            <flux:heading size="lg">{{ __('general.dashboard_moodian') }}</flux:heading>
                            @if (($board['moodian'] ?? []) === [])
                                <flux:text class="mt-3">{{ __('general.dashboard_no_data') }}</flux:text>
                            @else
                                <div class="mt-3 space-y-2">
                                    @foreach ($board['moodian'] as $row)
                                        <div class="flex items-center justify-between gap-3 text-sm" wire:key="moodian-{{ $row['status'] }}">
                                            <span>{{ $this->moodianLabel($row['status']) }}</span>
                                            <span dir="ltr" class="tabular-nums">{{ $row['count'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </flux:card>
                    @endif
                </div>
            </div>
        @endif
    @endforeach

    @if ($this->allows(BusinessPermission::TransactionCreate))
        <livewire:accounting.transaction.create :key="'dashboard-transaction-create'" />
    @endif
</div>
