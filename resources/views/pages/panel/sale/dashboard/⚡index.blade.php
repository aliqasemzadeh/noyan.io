<?php

use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessPermission;
use App\Models\Accounting\Invoice;
use App\Support\LocaleDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    public int $selectedYear;

    public int $selectedMonth;

    /** @var list<array{date: string, month_number: int, sales: float, purchases: float, sales_count: int, purchases_count: int}> */
    public array $data = [];

    /**
     * @var list<string>
     */
    protected array $monthNames = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        $now = Jalalian::now();
        $this->selectedYear = $now->getYear();
        $this->selectedMonth = $now->getMonth();
        $this->refreshChartData();
    }

    public function previousYear(): void
    {
        $this->selectedYear--;
        $this->refreshChartData();
    }

    public function nextYear(): void
    {
        $this->selectedYear++;
        $this->refreshChartData();
    }

    public function selectMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            return;
        }

        $this->selectedMonth = $month;
        unset($this->selectedMonthSummary);
    }

    public function refreshChartData(): void
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            $this->data = $this->emptyChartData();

            return;
        }

        $cacheKey = sprintf('sale.dashboard.monthly.%d.%d', $businessId, $this->selectedYear);

        $this->data = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($businessId): array {
            [$yearStart, $yearEnd] = $this->jalaliYearBounds($this->selectedYear);

            $rows = Invoice::query()
                ->where('business_id', $businessId)
                ->whereIn('type', [InvoiceType::Sale->value, InvoiceType::Purchase->value])
                ->whereBetween('issue_date', [$yearStart->toDateString(), $yearEnd->toDateString()])
                ->get(['type', 'total_amount', 'issue_date']);

            $buckets = [];

            for ($month = 1; $month <= 12; $month++) {
                $buckets[$month] = [
                    'date' => $this->monthNames[$month],
                    'month_number' => $month,
                    'sales' => 0.0,
                    'purchases' => 0.0,
                    'sales_count' => 0,
                    'purchases_count' => 0,
                ];
            }

            foreach ($rows as $invoice) {
                $month = Jalalian::fromDateTime($invoice->issue_date)->getMonth();

                if ($invoice->type === InvoiceType::Sale) {
                    $buckets[$month]['sales'] += (float) $invoice->total_amount;
                    $buckets[$month]['sales_count']++;
                } elseif ($invoice->type === InvoiceType::Purchase) {
                    $buckets[$month]['purchases'] += (float) $invoice->total_amount;
                    $buckets[$month]['purchases_count']++;
                }
            }

            return array_values($buckets);
        });

        unset($this->selectedMonthSummary);
    }

    /**
     * @return array{
     *     label: string,
     *     sales: string,
     *     purchases: string,
     *     sales_count: int,
     *     purchases_count: int,
     *     invoices: Collection<int, Invoice>
     * }
     */
    #[Computed]
    public function selectedMonthSummary(): array
    {
        $monthRow = collect($this->data)->firstWhere('month_number', $this->selectedMonth);

        $businessId = Auth::user()?->current_business_id;
        $invoices = collect();

        if ($businessId !== null) {
            [$start, $end] = $this->jalaliMonthBounds($this->selectedYear, $this->selectedMonth);

            $invoices = Invoice::query()
                ->with(['party:id,name,legal_name'])
                ->where('business_id', $businessId)
                ->whereIn('type', [InvoiceType::Sale->value, InvoiceType::Purchase->value])
                ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->latest('issue_date')
                ->latest('id')
                ->limit(10)
                ->get();
        }

        return [
            'label' => $this->monthNames[$this->selectedMonth].' '.$this->selectedYear,
            'sales' => (string) ($monthRow['sales'] ?? 0),
            'purchases' => (string) ($monthRow['purchases'] ?? 0),
            'sales_count' => (int) ($monthRow['sales_count'] ?? 0),
            'purchases_count' => (int) ($monthRow['purchases_count'] ?? 0),
            'invoices' => $invoices,
        ];
    }

    public function formatAmount(string|float|int $amount): string
    {
        $amount = (string) $amount;

        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }

    public function formatDate(\DateTimeInterface|string|null $date): string
    {
        return LocaleDate::formatDate($date);
    }

    /**
     * @return list<array{date: string, month_number: int, sales: float, purchases: float, sales_count: int, purchases_count: int}>
     */
    protected function emptyChartData(): array
    {
        $rows = [];

        for ($month = 1; $month <= 12; $month++) {
            $rows[] = [
                'date' => $this->monthNames[$month],
                'month_number' => $month,
                'sales' => 0.0,
                'purchases' => 0.0,
                'sales_count' => 0,
                'purchases_count' => 0,
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function jalaliYearBounds(int $year): array
    {
        $start = Jalalian::fromFormat('Y/m/d', sprintf('%04d/01/01', $year))->toCarbon()->startOfDay();
        $esfand = Jalalian::fromFormat('Y/m/d', sprintf('%04d/12/01', $year));
        $end = Jalalian::fromFormat('Y/m/d', sprintf('%04d/12/%02d', $year, $esfand->getMonthDays()))
            ->toCarbon()
            ->endOfDay();

        return [$start, $end];
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function jalaliMonthBounds(int $year, int $month): array
    {
        $startJalali = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/01', $year, $month));
        $start = $startJalali->toCarbon()->startOfDay();
        $end = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $year, $month, $startJalali->getMonthDays()))
            ->toCarbon()
            ->endOfDay();

        return [$start, $end];
    }
};
?>

<x-slot name="title">{{ __('general.sale') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.sale') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <flux:heading size="xl">{{ __('general.sale') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.sale_dashboard_hint') }}</flux:text>
            </div>

            @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceCreate))
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <flux:button
                        variant="primary"
                        color="teal"
                        icon="trending-up"
                        class="w-full sm:w-auto"
                        :href="route('sale.invoices.create', ['type' => 'sale'])"
                        wire:navigate
                    >
                        {{ __('general.new_sale_invoice') }}
                    </flux:button>
                    <flux:button
                        variant="primary"
                        color="amber"
                        icon="trending-down"
                        class="w-full sm:w-auto"
                        :href="route('sale.invoices.create', ['type' => 'purchase'])"
                        wire:navigate
                    >
                        {{ __('general.new_purchase_invoice') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </div>

    @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceView))
        <flux:card class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="lg">{{ __('general.monthly_sales_purchases_report') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('general.monthly_report_click_hint') }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-2 sm:justify-start">
                    <flux:tooltip content="{{ __('general.previous_year') }}">
                        <flux:button
                            size="sm"
                            variant="primary"
                            color="zinc"
                            icon="chevron-right"
                            icon:variant="outline"
                            wire:click="previousYear"
                        />
                    </flux:tooltip>

                    <flux:badge size="sm" color="zinc" icon="calendar">
                        {{ __('general.jalali_year') }}
                        <span dir="ltr" class="ms-1">{{ $selectedYear }}</span>
                    </flux:badge>

                    <flux:tooltip content="{{ __('general.next_year') }}">
                        <flux:button
                            size="sm"
                            variant="primary"
                            color="zinc"
                            icon="chevron-left"
                            icon:variant="outline"
                            wire:click="nextYear"
                        />
                    </flux:tooltip>
                </div>
            </div>

            <div
                x-data
                @click="
                    const monthEl = $el.querySelector('[data-chart-month]');
                    if (! monthEl) return;
                    const month = Number((monthEl.textContent || '').trim());
                    if (month >= 1 && month <= 12) $wire.selectMonth(month);
                "
                class="cursor-pointer"
            >
                <flux:chart wire:model="data" class="aspect-[4/3] sm:aspect-[2/1] xl:aspect-[3/1]">
                    <flux:chart.viewport class="aspect-[4/3] sm:aspect-[2/1] xl:aspect-[3/1]">
                        <flux:chart.svg>
                            <flux:chart.group>
                                <flux:chart.bar field="sales" class="text-teal-500" radius="4 0" width="80%" />
                                <flux:chart.bar field="purchases" class="text-amber-500" radius="4 0" width="80%" />
                            </flux:chart.group>

                            <flux:chart.axis axis="x" field="date">
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
                        <flux:chart.legend :label="__('general.chart_sales')">
                            <flux:chart.legend.indicator class="bg-teal-500" />
                        </flux:chart.legend>
                        <flux:chart.legend :label="__('general.chart_purchases')">
                            <flux:chart.legend.indicator class="bg-amber-500" />
                        </flux:chart.legend>
                    </div>

                    <flux:chart.tooltip>
                        <flux:chart.tooltip.heading field="date" />
                        <flux:chart.tooltip.value field="sales" :label="__('general.chart_sales')" :format="['useGrouping' => true]" />
                        <flux:chart.tooltip.value field="purchases" :label="__('general.chart_purchases')" :format="['useGrouping' => true]" />
                        <span data-chart-month class="hidden"><slot field="month_number"></slot></span>
                    </flux:chart.tooltip>
                </flux:chart>
            </div>

            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-12">
                @foreach ($data as $row)
                    <flux:button
                        wire:key="sale-month-pill-{{ $row['month_number'] }}"
                        size="sm"
                        variant="{{ $selectedMonth === $row['month_number'] ? 'primary' : 'ghost' }}"
                        color="{{ $selectedMonth === $row['month_number'] ? 'teal' : 'zinc' }}"
                        class="w-full"
                        wire:click="selectMonth({{ $row['month_number'] }})"
                    >
                        {{ $row['date'] }}
                    </flux:button>
                @endforeach
            </div>
        </flux:card>

        @php($summary = $this->selectedMonthSummary)

        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.selected_month_summary') }}</flux:heading>
                <flux:badge size="sm" color="teal">{{ $summary['label'] }}</flux:badge>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-teal-200 bg-teal-50/60 p-4 dark:border-teal-900 dark:bg-teal-950/40">
                    <flux:text class="text-sm">{{ __('general.sales_amount') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">{{ $this->formatAmount($summary['sales']) }}</flux:heading>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                    <flux:text class="text-sm">{{ __('general.purchases_amount') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">{{ $this->formatAmount($summary['purchases']) }}</flux:heading>
                </div>
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ __('general.sales_count') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">{{ number_format($summary['sales_count']) }}</flux:heading>
                </div>
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ __('general.purchases_count') }}</flux:text>
                    <flux:heading size="lg" class="mt-1" dir="ltr">{{ number_format($summary['purchases_count']) }}</flux:heading>
                </div>
            </div>

            <div class="space-y-3">
                @forelse ($summary['invoices'] as $invoice)
                    <div
                        wire:key="sale-dashboard-invoice-{{ $invoice->id }}"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
                    >
                        <div class="min-w-0">
                            <div class="font-medium">{{ $invoice->invoice_number }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                <flux:badge size="sm" :color="$invoice->type->badgeColor()">{{ $invoice->type->label() }}</flux:badge>
                                <span>{{ $invoice->displayPartyName() }}</span>
                                <span>{{ $this->formatDate($invoice->issue_date) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium" dir="ltr">{{ $this->formatAmount((string) $invoice->total_amount) }}</span>
                            @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceEdit) || auth()->user()?->canInBusiness(BusinessPermission::InvoiceEditFinalized))
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        :href="route('sale.invoices.edit', $invoice)"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                            @endif
                        </div>
                    </div>
                @empty
                    <flux:text class="py-4 text-center">{{ __('general.no_invoices_this_month') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceView))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.invoices') }}</flux:heading>
                <flux:button variant="primary" color="teal" class="w-full" :href="route('sale.invoices.index')" wire:navigate>
                    {{ __('general.invoices') }}
                </flux:button>
            </flux:card>
        @endif

        @if (auth()->user()?->canInBusiness(BusinessPermission::QuoteView))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.quotes') }}</flux:heading>
                <flux:button variant="primary" color="sky" class="w-full" :href="route('sale.quotes.index')" wire:navigate>
                    {{ __('general.quotes') }}
                </flux:button>
            </flux:card>
        @endif

        @if (auth()->user()?->canInBusiness(BusinessPermission::PartyView))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.parties') }}</flux:heading>
                <flux:button variant="primary" color="zinc" class="w-full" :href="route('sale.parties.index')" wire:navigate>
                    {{ __('general.parties') }}
                </flux:button>
            </flux:card>
        @endif
    </div>
</div>
