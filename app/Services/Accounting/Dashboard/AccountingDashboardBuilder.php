<?php

namespace App\Services\Accounting\Dashboard;

use App\Enums\AccountSubType;
use App\Enums\Accounting\DashboardPeriod;
use App\Enums\Accounting\InvoiceAging;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\Catalog\Product;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class AccountingDashboardBuilder
{
    private const SCALE = 4;

    /**
     * @return array<string, mixed>
     */
    public function build(
        int $businessId,
        DashboardPeriod $period,
        bool $compareLastYear,
        string $currencyCode,
        string $bucketKey,
    ): array {
        $cacheKey = sprintf(
            'accounting.dashboard.%d.%s.%d.%s.%s.%s.%s',
            $businessId,
            $period->value,
            $compareLastYear ? 1 : 0,
            $currencyCode === '' ? 'base' : $currencyCode,
            $bucketKey === '' ? '-' : $bucketKey,
            now()->toDateString(),
            app()->getLocale(),
        );

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($businessId, $period, $compareLastYear, $currencyCode, $bucketKey): array {
            return $this->compose($businessId, $period, $compareLastYear, $currencyCode, $bucketKey);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function empty(): array
    {
        return $this->blank('IRR', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function compose(
        int $businessId,
        DashboardPeriod $period,
        bool $compareLastYear,
        string $currencyCode,
        string $bucketKey,
    ): array {
        $context = $this->currencyContext($businessId, $currencyCode);
        [$current, $previousRange, $grain] = $this->ranges($businessId, $period, $compareLastYear);
        $buckets = $this->buckets($current['start'], $current['end'], $grain);

        $focus = null;

        if ($bucketKey !== '') {
            foreach ($buckets as $index => $bucket) {
                if ($bucket['key'] === $bucketKey) {
                    $focus = $bucket;
                    $focus['index'] = $index;
                    break;
                }
            }
        }

        $metric = $focus ?? $current;

        if ($focus !== null && ! $compareLastYear) {
            $previousRange = $focus['index'] > 0 ? $buckets[$focus['index'] - 1] : null;
        } elseif ($focus !== null && $compareLastYear) {
            $previousRange = $this->shiftYear($focus['start'], $focus['end']);
        }

        $invoiceDays = $this->invoiceDays($businessId, $current['start'], $current['end'], $context);
        $transactionDays = $this->transactionDays($businessId, $current['start'], $current['end'], $context);

        $previousInvoices = [];
        $previousTransactions = [];

        if ($previousRange !== null) {
            $previousInvoices = $this->invoiceDays($businessId, $previousRange['start'], $previousRange['end'], $context);
            $previousTransactions = $this->transactionDays($businessId, $previousRange['start'], $previousRange['end'], $context);
        }

        $revenue = $this->netRevenue($invoiceDays, $metric['start'], $metric['end']);
        $expenses = $this->expenseTotal($transactionDays, $metric['start'], $metric['end']);
        $previousRevenue = $previousRange === null ? null : $this->netRevenue($previousInvoices, $previousRange['start'], $previousRange['end']);
        $previousExpenses = $previousRange === null ? null : $this->expenseTotal($previousTransactions, $previousRange['start'], $previousRange['end']);
        $movement = $this->cashMovement($transactionDays, $metric['start'], $metric['end']);
        $previousMovement = $previousRange === null ? null : $this->cashMovement($previousTransactions, $previousRange['start'], $previousRange['end']);
        $profit = $this->sub($revenue, $expenses);
        $previousProfit = ($previousRevenue === null || $previousExpenses === null)
            ? null
            : $this->sub($previousRevenue, $previousExpenses);

        $cash = $this->cashPosition($businessId, $context);
        $aging = $this->aging($businessId, $context);

        $board = $this->blank($context['code'], $context['decimals']);
        $board['comparison'] = $previousRange === null ? null : ($compareLastYear ? 'year' : 'previous');
        $board['bucket'] = $focus['key'] ?? '';
        $board['kpis'] = [
            'cash_balance' => $cash['total'],
            'cash_by_currency' => $cash['by_currency'],
            'cash_movement' => $movement,
            'cash_movement_change' => $previousMovement === null ? null : $this->percentChange($movement, $previousMovement),
            'revenue' => $revenue,
            'revenue_change' => $previousRevenue === null ? null : $this->percentChange($revenue, $previousRevenue),
            'expenses' => $expenses,
            'expenses_change' => $previousExpenses === null ? null : $this->percentChange($expenses, $previousExpenses),
            'profit' => $profit,
            'profit_change' => $previousProfit === null ? null : $this->percentChange($profit, $previousProfit),
            'margin' => $this->margin($profit, $revenue),
            'receivables' => $aging['receivables'],
            'payables' => $aging['payables'],
        ];
        $board['cash_flow'] = $this->cashFlowSeries($buckets, $transactionDays);
        $board['profit_trend'] = $this->profitSeries($buckets, $invoiceDays, $transactionDays);
        $board['expenses_chart'] = $this->expenseSlices($businessId, $transactionDays, $metric['start'], $metric['end']);
        $board['aging'] = $aging['buckets'];
        $board['cheques'] = $this->cheques($businessId);
        $board['overdue_invoices'] = $this->overdueInvoices($businessId, $context);
        $board['accounts'] = $cash['accounts'];
        $board['drafts'] = $this->drafts($businessId);
        $board['low_stock'] = $this->lowStock($businessId);
        $board['top_products'] = $this->topProducts($businessId, $metric['start'], $metric['end'], $context);
        $board['moodian'] = $this->moodian($businessId, $context);

        return $board;
    }

    /**
     * @return array<string, mixed>
     */
    private function blank(string $code, int $decimals): array
    {
        return [
            'currency_code' => $code,
            'decimal_places' => $decimals,
            'comparison' => null,
            'bucket' => '',
            'kpis' => [
                'cash_balance' => '0',
                'cash_by_currency' => [],
                'cash_movement' => '0',
                'cash_movement_change' => null,
                'revenue' => '0',
                'revenue_change' => null,
                'expenses' => '0',
                'expenses_change' => null,
                'profit' => '0',
                'profit_change' => null,
                'margin' => null,
                'receivables' => '0',
                'payables' => '0',
            ],
            'cash_flow' => [],
            'profit_trend' => [],
            'expenses_chart' => [],
            'aging' => [],
            'cheques' => [
                'overdue_count' => 0,
                'upcoming_count' => 0,
                'items' => [],
            ],
            'overdue_invoices' => [],
            'accounts' => [],
            'drafts' => [
                'draft' => 0,
                'pending_review' => 0,
            ],
            'low_stock' => [],
            'top_products' => [],
            'moodian' => [],
        ];
    }

    /**
     * @return array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}
     */
    private function currencyContext(int $businessId, string $currencyCode): array
    {
        $links = BusinessCurrency::query()
            ->with('currency:id,code,decimal_places')
            ->where('business_id', $businessId)
            ->get();

        $base = $links->firstWhere('is_base', true);
        $baseCode = $base?->currency?->code ?? 'IRR';
        $rates = [];

        foreach ($links as $link) {
            $code = $link->currency?->code;

            if ($code === null) {
                continue;
            }

            $rates[$code] = $this->num((string) $link->exchange_rate_to_base);
        }

        $rates[$baseCode] = '1';
        $selected = $currencyCode === '' ? null : $links->first(fn (BusinessCurrency $link): bool => $link->currency?->code === $currencyCode);

        if ($currencyCode !== '' && $selected === null) {
            return [
                'convert' => true,
                'code' => $baseCode,
                'base_code' => $baseCode,
                'currency_id' => $base?->currency_id,
                'decimals' => (int) ($base?->currency?->decimal_places ?? 0),
                'rates' => $rates,
            ];
        }

        $display = $selected?->currency ?? $base?->currency;

        return [
            'convert' => $selected === null,
            'code' => $display?->code ?? $baseCode,
            'base_code' => $baseCode,
            'currency_id' => $selected?->currency_id ?? $base?->currency_id,
            'decimals' => (int) ($display?->decimal_places ?? 0),
            'rates' => $rates,
        ];
    }

    /**
     * @return array{0: array{start: string, end: string}, 1: array{start: string, end: string}, 2: string}
     */
    private function ranges(int $businessId, DashboardPeriod $period, bool $compareLastYear): array
    {
        $today = Jalalian::now();

        [$start, $end, $grain] = match ($period) {
            DashboardPeriod::Today => [$today->toCarbon()->startOfDay(), $today->toCarbon()->startOfDay(), 'day'],
            DashboardPeriod::Month => $this->monthBounds($today),
            DashboardPeriod::Quarter => $this->quarterBounds($today),
            DashboardPeriod::FiscalYear => $this->fiscalBounds($businessId, $today),
        };

        $current = [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];

        $previous = $compareLastYear
            ? $this->shiftYear($current['start'], $current['end'])
            : $this->previousPeriod($businessId, $period, $start, $end);

        return [$current, $previous, $grain];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function monthBounds(Jalalian $today): array
    {
        $start = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/01', $today->getYear(), $today->getMonth()));

        return [
            $start->toCarbon()->startOfDay(),
            $this->monthEnd($start),
            'week',
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function quarterBounds(Jalalian $today): array
    {
        $startMonth = (intdiv($today->getMonth() - 1, 3) * 3) + 1;
        $start = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/01', $today->getYear(), $startMonth));
        $endMonth = $start->addMonths(2);

        return [
            $start->toCarbon()->startOfDay(),
            $this->monthEnd($endMonth),
            'month',
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function fiscalBounds(int $businessId, Jalalian $today): array
    {
        $date = $today->toCarbon()->toDateString();
        $fiscalYear = FiscalYear::query()
            ->where('business_id', $businessId)
            ->where('is_closed', false)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($fiscalYear !== null) {
            return [
                Carbon::parse($fiscalYear->start_date)->startOfDay(),
                Carbon::parse($fiscalYear->end_date)->startOfDay(),
                'month',
            ];
        }

        $start = Jalalian::fromFormat('Y/m/d', sprintf('%04d/01/01', $today->getYear()));

        return [
            $start->toCarbon()->startOfDay(),
            $this->monthEnd(Jalalian::fromFormat('Y/m/d', sprintf('%04d/12/01', $today->getYear()))),
            'month',
        ];
    }

    /**
     * @return array{start: string, end: string}
     */
    private function previousPeriod(int $businessId, DashboardPeriod $period, Carbon $start, Carbon $end): array
    {
        if ($period === DashboardPeriod::Today) {
            $yesterday = Jalalian::fromCarbon($start)->subDays(1)->toCarbon()->toDateString();

            return ['start' => $yesterday, 'end' => $yesterday];
        }

        if ($period === DashboardPeriod::Month) {
            $previous = Jalalian::fromCarbon($start)->subMonths(1);
            $monthStart = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/01', $previous->getYear(), $previous->getMonth()));

            return [
                'start' => $monthStart->toCarbon()->toDateString(),
                'end' => $this->monthEnd($monthStart)->toDateString(),
            ];
        }

        if ($period === DashboardPeriod::Quarter) {
            $previous = Jalalian::fromCarbon($start)->subMonths(3);
            $monthStart = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/01', $previous->getYear(), $previous->getMonth()));

            return [
                'start' => $monthStart->toCarbon()->toDateString(),
                'end' => $this->monthEnd($monthStart->addMonths(2))->toDateString(),
            ];
        }

        $earlier = FiscalYear::query()
            ->where('business_id', $businessId)
            ->whereDate('end_date', '<', $start->toDateString())
            ->orderByDesc('end_date')
            ->first();

        if ($earlier !== null) {
            return [
                'start' => Carbon::parse($earlier->start_date)->toDateString(),
                'end' => Carbon::parse($earlier->end_date)->toDateString(),
            ];
        }

        return $this->shiftYear($start->toDateString(), $end->toDateString());
    }

    /**
     * @return array{start: string, end: string}
     */
    private function shiftYear(string $start, string $end): array
    {
        return [
            'start' => Jalalian::fromCarbon(Carbon::parse($start))->subYears(1)->toCarbon()->toDateString(),
            'end' => Jalalian::fromCarbon(Carbon::parse($end))->subYears(1)->toCarbon()->toDateString(),
        ];
    }

    private function monthEnd(Jalalian $monthStart): Carbon
    {
        return Jalalian::fromFormat(
            'Y/m/d',
            sprintf('%04d/%02d/%02d', $monthStart->getYear(), $monthStart->getMonth(), $monthStart->getMonthDays()),
        )->toCarbon()->startOfDay();
    }

    /**
     * @return list<array{key: string, label: string, start: string, end: string}>
     */
    private function buckets(string $start, string $end, string $grain): array
    {
        $rangeStart = Carbon::parse($start)->startOfDay();
        $rangeEnd = Carbon::parse($end)->startOfDay();

        if ($grain === 'day') {
            $buckets = [];
            $cursor = $rangeStart->copy();

            while ($cursor->lte($rangeEnd) && count($buckets) < 31) {
                $buckets[] = [
                    'key' => $cursor->toDateString(),
                    'label' => Jalalian::fromCarbon($cursor)->format('m/d'),
                    'start' => $cursor->toDateString(),
                    'end' => $cursor->toDateString(),
                ];
                $cursor->addDay();
            }

            return $buckets;
        }

        if ($grain === 'week') {
            $jalali = Jalalian::fromCarbon($rangeStart);
            $days = $jalali->getMonthDays();
            $slices = [[1, 7], [8, 14], [15, 21], [22, $days]];
            $buckets = [];

            foreach ($slices as $index => [$from, $to]) {
                $sliceStart = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $jalali->getYear(), $jalali->getMonth(), $from))
                    ->toCarbon()
                    ->startOfDay();
                $sliceEnd = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $jalali->getYear(), $jalali->getMonth(), min($to, $days)))
                    ->toCarbon()
                    ->startOfDay();

                if ($sliceEnd->lt($rangeStart) || $sliceStart->gt($rangeEnd)) {
                    continue;
                }

                $buckets[] = [
                    'key' => sprintf('%04d-%02d-w%d', $jalali->getYear(), $jalali->getMonth(), $index + 1),
                    'label' => __('general.dashboard_week', ['week' => $index + 1]),
                    'start' => ($sliceStart->greaterThan($rangeStart) ? $sliceStart : $rangeStart)->toDateString(),
                    'end' => ($sliceEnd->lessThan($rangeEnd) ? $sliceEnd : $rangeEnd)->toDateString(),
                ];
            }

            return $buckets;
        }

        $cursor = Jalalian::fromCarbon($rangeStart);
        $cursor = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/01', $cursor->getYear(), $cursor->getMonth()));
        $endJalali = Jalalian::fromCarbon($rangeEnd);
        $spansYears = $cursor->getYear() !== $endJalali->getYear();
        $buckets = [];
        $guard = 0;

        while ($guard < 36) {
            $guard++;
            $monthStart = $cursor->toCarbon()->startOfDay();
            $monthEnd = $this->monthEnd($cursor);

            if ($monthStart->gt($rangeEnd)) {
                break;
            }

            if ($monthEnd->gte($rangeStart)) {
                $label = __('general.dashboard_month_'.$cursor->getMonth());

                if ($spansYears) {
                    $label .= ' '.$cursor->getYear();
                }

                $buckets[] = [
                    'key' => sprintf('%04d-%02d', $cursor->getYear(), $cursor->getMonth()),
                    'label' => $label,
                    'start' => ($monthStart->greaterThan($rangeStart) ? $monthStart : $rangeStart)->toDateString(),
                    'end' => ($monthEnd->lessThan($rangeEnd) ? $monthEnd : $rangeEnd)->toDateString(),
                ];
            }

            if ($cursor->getYear() === $endJalali->getYear() && $cursor->getMonth() === $endJalali->getMonth()) {
                break;
            }

            $cursor = $cursor->addMonths(1);
        }

        return $buckets;
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     * @return list<array{day: string, type: string, amount: string}>
     */
    private function invoiceDays(int $businessId, string $start, string $end, array $context): array
    {
        $query = Invoice::query()
            ->where('business_id', $businessId)
            ->whereNotNull('finalized_at')
            ->whereIn('type', [InvoiceType::Sale->value, InvoiceType::Return->value])
            ->whereBetween('issue_date', [$start, $end]);

        $this->constrainCurrency($query, 'currency', $context);

        $rows = $query
            ->toBase()
            ->selectRaw('DATE(issue_date) as day, type, currency, SUM(total_amount) as total_amount, SUM(CASE WHEN exchange_rate IS NULL OR exchange_rate = 0 THEN 0 ELSE total_amount * exchange_rate END) as rated, SUM(CASE WHEN exchange_rate IS NULL OR exchange_rate = 0 THEN total_amount ELSE 0 END) as unrated')
            ->groupByRaw('DATE(issue_date), type, currency')
            ->get();

        return $this->collapseDays($rows, $context);
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     * @return list<array{day: string, type: string, category_id: int|null, amount: string}>
     */
    private function transactionDays(int $businessId, string $start, string $end, array $context): array
    {
        $query = Transaction::query()
            ->where('business_id', $businessId)
            ->whereIn('type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('transaction_date', [$start, $end]);

        $this->constrainCurrency($query, 'currency', $context);

        $rows = $query
            ->toBase()
            ->selectRaw('DATE(transaction_date) as day, type, category_id, currency, SUM(amount) as total_amount, SUM(COALESCE(base_amount, amount * COALESCE(NULLIF(exchange_rate, 0), 1))) as rated')
            ->groupByRaw('DATE(transaction_date), type, category_id, currency')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $amount = $this->transactionAmount($row, $context);
            $categoryId = $row->category_id === null ? '' : (string) $row->category_id;
            $key = $row->day.'|'.$row->type.'|'.$categoryId;
            $grouped[$key] = $this->add($grouped[$key] ?? '0', $amount);
        }

        $days = [];

        foreach ($grouped as $key => $amount) {
            [$day, $type, $categoryId] = explode('|', $key);
            $days[] = [
                'day' => $day,
                'type' => $type,
                'category_id' => $categoryId === '' ? null : (int) $categoryId,
                'amount' => $amount,
            ];
        }

        return $days;
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     */
    private function constrainCurrency(mixed $query, string $column, array $context): void
    {
        if ($context['convert']) {
            return;
        }

        $code = $context['code'];

        if ($code === $context['base_code']) {
            $query->where(function ($query) use ($column, $code): void {
                $query->where($column, $code)
                    ->orWhereNull($column)
                    ->orWhere($column, '');
            });

            return;
        }

        $query->where($column, $code);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     * @return list<array{day: string, type: string, amount: string}>
     */
    private function collapseDays(Collection $rows, array $context): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $amount = $this->documentAmount($row, $context);
            $key = $row->day.'|'.$row->type;
            $grouped[$key] = $this->add($grouped[$key] ?? '0', $amount);
        }

        $days = [];

        foreach ($grouped as $key => $amount) {
            [$day, $type] = explode('|', $key, 2);
            $days[] = [
                'day' => $day,
                'type' => $type,
                'amount' => $amount,
            ];
        }

        return $days;
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     */
    private function documentAmount(object $row, array $context): string
    {
        if (! $context['convert'] || $this->normalizeCurrency($row->currency ?? null, $context['base_code']) === $context['base_code']) {
            return $this->num((string) $row->total_amount);
        }

        $currency = $this->normalizeCurrency($row->currency ?? null, $context['base_code']);
        $rate = $context['rates'][$currency] ?? '1';

        return $this->add($this->num((string) $row->rated), $this->mul($this->num((string) $row->unrated), $rate));
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     */
    private function transactionAmount(object $row, array $context): string
    {
        if (! $context['convert']) {
            return $this->num((string) $row->total_amount);
        }

        return $this->num((string) ($row->rated ?? $row->total_amount));
    }

    /**
     * @param  list<array{day: string, type: string, amount: string}>  $days
     */
    private function netRevenue(array $days, string $start, string $end): string
    {
        $total = '0';

        foreach ($days as $day) {
            if ($day['day'] < $start || $day['day'] > $end) {
                continue;
            }

            if ($day['type'] === InvoiceType::Sale->value) {
                $total = $this->add($total, $day['amount']);
            } elseif ($day['type'] === InvoiceType::Return->value) {
                $total = $this->sub($total, $day['amount']);
            }
        }

        return $total;
    }

    /**
     * @param  list<array{day: string, type: string, category_id?: int|null, amount: string}>  $days
     */
    private function expenseTotal(array $days, string $start, string $end): string
    {
        $total = '0';

        foreach ($days as $day) {
            if ($day['day'] < $start || $day['day'] > $end || $day['type'] !== TransactionType::Expense->value) {
                continue;
            }

            $total = $this->add($total, $day['amount']);
        }

        return $total;
    }

    /**
     * @param  list<array{day: string, type: string, amount: string}>  $days
     */
    private function cashMovement(array $days, string $start, string $end): string
    {
        $total = '0';

        foreach ($days as $day) {
            if ($day['day'] < $start || $day['day'] > $end) {
                continue;
            }

            if ($day['type'] === TransactionType::Income->value) {
                $total = $this->add($total, $day['amount']);
            } elseif ($day['type'] === TransactionType::Expense->value) {
                $total = $this->sub($total, $day['amount']);
            }
        }

        return $total;
    }

    /**
     * @param  list<array{key: string, label: string, start: string, end: string}>  $buckets
     * @param  list<array{day: string, type: string, amount: string}>  $days
     * @return list<array{key: string, label: string, inflow: float, outflow: float}>
     */
    private function cashFlowSeries(array $buckets, array $days): array
    {
        $series = [];

        foreach ($buckets as $bucket) {
            $inflow = '0';
            $outflow = '0';

            foreach ($days as $day) {
                if ($day['day'] < $bucket['start'] || $day['day'] > $bucket['end']) {
                    continue;
                }

                if ($day['type'] === TransactionType::Income->value) {
                    $inflow = $this->add($inflow, $day['amount']);
                } elseif ($day['type'] === TransactionType::Expense->value) {
                    $outflow = $this->add($outflow, $day['amount']);
                }
            }

            $series[] = [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'inflow' => (float) $inflow,
                'outflow' => (float) $outflow,
            ];
        }

        return $series;
    }

    /**
     * @param  list<array{key: string, label: string, start: string, end: string}>  $buckets
     * @param  list<array{day: string, type: string, amount: string}>  $invoices
     * @param  list<array{day: string, type: string, amount: string}>  $transactions
     * @return list<array{key: string, label: string, revenue: float, expense: float, profit: float}>
     */
    private function profitSeries(array $buckets, array $invoices, array $transactions): array
    {
        $series = [];

        foreach ($buckets as $bucket) {
            $revenue = $this->netRevenue($invoices, $bucket['start'], $bucket['end']);
            $expense = $this->expenseTotal($transactions, $bucket['start'], $bucket['end']);
            $series[] = [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'revenue' => (float) $revenue,
                'expense' => (float) $expense,
                'profit' => (float) $this->sub($revenue, $expense),
            ];
        }

        return $series;
    }

    /**
     * @param  list<array{day: string, type: string, category_id?: int|null, amount: string}>  $days
     * @return list<array{category_id: string, label: string, value: float}>
     */
    private function expenseSlices(int $businessId, array $days, string $start, string $end): array
    {
        $totals = [];

        foreach ($days as $day) {
            if ($day['day'] < $start || $day['day'] > $end || $day['type'] !== TransactionType::Expense->value) {
                continue;
            }

            $key = isset($day['category_id']) && $day['category_id'] !== null ? (string) $day['category_id'] : 'none';
            $totals[$key] = $this->add($totals[$key] ?? '0', $day['amount']);
        }

        $ids = array_values(array_filter(array_keys($totals), fn (string $key): bool => $key !== 'none'));
        $names = $ids === []
            ? collect()
            : Category::query()->where('business_id', $businessId)->whereIn('id', $ids)->pluck('name', 'id');

        $slices = [];

        foreach ($totals as $key => $amount) {
            if (bccomp($this->num($amount), '0', self::SCALE) === 0) {
                continue;
            }

            $slices[] = [
                'category_id' => $key,
                'label' => $key === 'none' ? __('general.dashboard_uncategorized') : (string) ($names[$key] ?? __('general.dashboard_uncategorized')),
                'value' => (float) $amount,
            ];
        }

        usort($slices, fn (array $left, array $right): int => $right['value'] <=> $left['value']);

        return $slices;
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     * @return array{total: string, by_currency: list<array{code: string, amount: string}>, accounts: list<array{id: int, name: string, sub_type: string, balance: string, code: string}>, receivables?: string}
     */
    private function cashPosition(int $businessId, array $context): array
    {
        $accounts = Account::query()
            ->with('currency:id,code')
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->whereIn('sub_type', AccountSubType::values())
            ->when(! $context['convert'] && $context['currency_id'] !== null, fn ($query) => $query->where('currency_id', $context['currency_id']))
            ->orderBy('name')
            ->get();

        $total = '0';
        $byCurrency = [];
        $rows = [];

        foreach ($accounts as $account) {
            $code = $account->currency?->code ?? $context['base_code'];
            $native = $this->num((string) $account->current_balance);
            $byCurrency[$code] = $this->add($byCurrency[$code] ?? '0', $native);
            $display = $context['convert']
                ? $this->mul($native, $context['rates'][$code] ?? '1')
                : $native;
            $total = $this->add($total, $display);
            $rows[] = [
                'id' => $account->id,
                'name' => $account->name,
                'sub_type' => $account->sub_type->value,
                'balance' => $display,
                'code' => $code,
            ];
        }

        $breakdown = [];

        foreach ($byCurrency as $code => $amount) {
            $breakdown[] = ['code' => $code, 'amount' => $amount];
        }

        return [
            'total' => $total,
            'by_currency' => $breakdown,
            'accounts' => $rows,
        ];
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     * @return array{receivables: string, payables: string, buckets: list<array{key: string, label: string, receivables: float, payables: float}>}
     */
    private function aging(int $businessId, array $context): array
    {
        $today = now()->toDateString();
        $days30 = now()->subDays(30)->toDateString();
        $days60 = now()->subDays(60)->toDateString();

        $query = Invoice::query()
            ->where('business_id', $businessId)
            ->whereNotNull('finalized_at')
            ->whereIn('type', [InvoiceType::Sale->value, InvoiceType::Purchase->value])
            ->where('payment_status', '!=', InvoicePaymentStatus::Paid->value)
            ->whereColumn('total_amount', '>', 'paid_amount');

        $this->constrainCurrency($query, 'currency', $context);

        $rows = $query
            ->toBase()
            ->selectRaw(
                'type, currency, CASE WHEN due_date IS NULL OR due_date >= ? THEN ? WHEN due_date >= ? THEN ? WHEN due_date >= ? THEN ? ELSE ? END as bucket, SUM(total_amount - paid_amount) as total_amount, SUM(CASE WHEN exchange_rate IS NULL OR exchange_rate = 0 THEN 0 ELSE (total_amount - paid_amount) * exchange_rate END) as rated, SUM(CASE WHEN exchange_rate IS NULL OR exchange_rate = 0 THEN (total_amount - paid_amount) ELSE 0 END) as unrated',
                [$today, InvoiceAging::Current->value, $days30, InvoiceAging::Days1To30->value, $days60, InvoiceAging::Days31To60->value, InvoiceAging::Over60->value],
            )
            ->groupByRaw('type, currency, bucket')
            ->get();

        $amounts = [];

        foreach (InvoiceAging::cases() as $bucket) {
            $amounts[$bucket->value] = [
                InvoiceType::Sale->value => '0',
                InvoiceType::Purchase->value => '0',
            ];
        }

        foreach ($rows as $row) {
            $bucket = (string) $row->bucket;
            $type = (string) $row->type;

            if (! isset($amounts[$bucket][$type])) {
                continue;
            }

            $amounts[$bucket][$type] = $this->add($amounts[$bucket][$type], $this->documentAmount($row, $context));
        }

        $receivables = '0';
        $payables = '0';
        $buckets = [];

        foreach (InvoiceAging::cases() as $bucket) {
            $receivable = $amounts[$bucket->value][InvoiceType::Sale->value];
            $payable = $amounts[$bucket->value][InvoiceType::Purchase->value];
            $receivables = $this->add($receivables, $receivable);
            $payables = $this->add($payables, $payable);
            $buckets[] = [
                'key' => $bucket->value,
                'label' => $bucket->label(),
                'receivables' => (float) $receivable,
                'payables' => (float) $payable,
            ];
        }

        return [
            'receivables' => $receivables,
            'payables' => $payables,
            'buckets' => $buckets,
        ];
    }

    /**
     * @return array{overdue_count: int, upcoming_count: int, items: list<array{id: int, number: string, party: string, amount: string, due_date: string, type: string, overdue: bool}>}
     */
    private function cheques(int $businessId): array
    {
        $overdue = Cheque::query()
            ->with(['party:id,name,legal_name'])
            ->where('business_id', $businessId)
            ->overdue()
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $upcoming = Cheque::query()
            ->with(['party:id,name,legal_name'])
            ->where('business_id', $businessId)
            ->dueWithin(7)
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $items = $overdue->concat($upcoming)->map(fn (Cheque $cheque): array => [
            'id' => $cheque->id,
            'number' => $cheque->cheque_number,
            'party' => $cheque->party?->displayName() ?? '',
            'amount' => (string) $cheque->amount,
            'due_date' => $cheque->due_date?->toDateString() ?? '',
            'type' => $cheque->type->value,
            'overdue' => $cheque->isOverdue(),
        ])->values()->all();

        return [
            'overdue_count' => Cheque::query()->where('business_id', $businessId)->overdue()->count(),
            'upcoming_count' => Cheque::query()->where('business_id', $businessId)->dueWithin(7)->count(),
            'items' => $items,
        ];
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     * @return list<array{id: int, number: string, party: string, amount: string, due_date: string, currency: string}>
     */
    private function overdueInvoices(int $businessId, array $context): array
    {
        $query = Invoice::query()
            ->with(['party:id,name,legal_name'])
            ->where('business_id', $businessId)
            ->where('type', InvoiceType::Sale)
            ->whereNotNull('finalized_at')
            ->where('payment_status', '!=', InvoicePaymentStatus::Paid)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereColumn('total_amount', '>', 'paid_amount');

        $this->constrainCurrency($query, 'currency', $context);

        return $query
            ->orderByRaw('(total_amount - paid_amount) desc')
            ->orderBy('due_date')
            ->limit(8)
            ->get()
            ->map(function (Invoice $invoice) use ($context): array {
                $remaining = $this->sub((string) $invoice->total_amount, (string) $invoice->paid_amount);
                $row = (object) [
                    'currency' => $invoice->currency,
                    'total_amount' => $remaining,
                    'rated' => ($invoice->exchange_rate === null || (float) $invoice->exchange_rate === 0.0) ? '0' : $this->mul($remaining, (string) $invoice->exchange_rate),
                    'unrated' => ($invoice->exchange_rate === null || (float) $invoice->exchange_rate === 0.0) ? $remaining : '0',
                ];

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'party' => $invoice->displayPartyName(),
                    'amount' => $this->documentAmount($row, $context),
                    'due_date' => $invoice->due_date?->toDateString() ?? '',
                    'currency' => $invoice->currency ?: $context['base_code'],
                ];
            })
            ->all();
    }

    /**
     * @return array{draft: int, pending_review: int}
     */
    private function drafts(int $businessId): array
    {
        $counts = JournalEntry::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [JournalEntryStatus::Draft->value, JournalEntryStatus::PendingReview->value])
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'draft' => (int) ($counts[JournalEntryStatus::Draft->value] ?? 0),
            'pending_review' => (int) ($counts[JournalEntryStatus::PendingReview->value] ?? 0),
        ];
    }

    /**
     * @return list<array{id: int, name: string, stock: string, minimum: string}>
     */
    private function lowStock(int $businessId): array
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->where('track_inventory', true)
            ->where('min_stock', '>', 0)
            ->whereColumn('stock_quantity', '<', 'min_stock')
            ->orderBy('stock_quantity')
            ->limit(8)
            ->get(['id', 'name', 'stock_quantity', 'min_stock'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => (string) $product->stock_quantity,
                'minimum' => (string) $product->min_stock,
            ])
            ->all();
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     * @return list<array{id: int, name: string, quantity: string, amount: string}>
     */
    private function topProducts(int $businessId, string $start, string $end, array $context): array
    {
        $query = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.business_id', $businessId)
            ->whereNull('invoices.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNotNull('invoices.finalized_at')
            ->where('invoices.type', InvoiceType::Sale->value)
            ->whereBetween('invoices.issue_date', [$start, $end])
            ->whereNotNull('invoice_items.product_id');

        $this->constrainCurrency($query, 'invoices.currency', $context);

        $rows = $query
            ->toBase()
            ->selectRaw('products.id as product_id, products.name as product_name, invoices.currency as currency, SUM(invoice_items.quantity) as quantity, SUM(invoice_items.total) as total_amount, SUM(CASE WHEN invoices.exchange_rate IS NULL OR invoices.exchange_rate = 0 THEN 0 ELSE invoice_items.total * invoices.exchange_rate END) as rated, SUM(CASE WHEN invoices.exchange_rate IS NULL OR invoices.exchange_rate = 0 THEN invoice_items.total ELSE 0 END) as unrated')
            ->groupBy('products.id', 'products.name', 'invoices.currency')
            ->get();

        $merged = [];

        foreach ($rows as $row) {
            $id = (int) $row->product_id;
            $merged[$id] ??= [
                'id' => $id,
                'name' => (string) $row->product_name,
                'quantity' => '0',
                'amount' => '0',
            ];
            $merged[$id]['quantity'] = $this->add($merged[$id]['quantity'], (string) $row->quantity);
            $merged[$id]['amount'] = $this->add($merged[$id]['amount'], $this->documentAmount($row, $context));
        }

        $products = array_values($merged);
        usort($products, fn (array $left, array $right): int => bccomp($right['amount'], $left['amount'], self::SCALE));

        return array_slice($products, 0, 5);
    }

    /**
     * @param  array{convert: bool, code: string, base_code: string, currency_id: int|null, decimals: int, rates: array<string, string>}  $context
     * @return list<array{status: string, count: int}>
     */
    private function moodian(int $businessId, array $context): array
    {
        $query = Invoice::query()
            ->where('business_id', $businessId)
            ->where('type', InvoiceType::Sale)
            ->whereNotNull('finalized_at');

        $this->constrainCurrency($query, 'currency', $context);

        $rows = DB::query()
            ->fromSub(
                $query->selectRaw("COALESCE(NULLIF(moodian_status, ''), 'unsent') as status_key"),
                'moodian_rows',
            )
            ->selectRaw('status_key, COUNT(*) as aggregate')
            ->groupBy('status_key')
            ->get();

        return $rows->map(fn (object $row): array => [
            'status' => (string) $row->status_key,
            'count' => (int) $row->aggregate,
        ])->all();
    }

    private function margin(string $profit, string $revenue): ?string
    {
        if (bccomp($this->num($revenue), '0', self::SCALE) !== 1) {
            return null;
        }

        return bcdiv($this->mul($profit, '100'), $this->num($revenue), 1);
    }

    private function percentChange(string $current, string $previous): ?string
    {
        $base = ltrim($this->num($previous), '-');

        if (bccomp($base, '0', self::SCALE) === 0) {
            return null;
        }

        $delta = $this->sub($current, $previous);

        return bcdiv($this->mul($delta, '100'), $base, 1);
    }

    private function normalizeCurrency(?string $currency, string $baseCode): string
    {
        $currency = trim((string) $currency);

        return $currency === '' ? $baseCode : $currency;
    }

    private function num(?string $amount): string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return '0';
        }

        return $amount;
    }

    private function add(string $left, string $right): string
    {
        return bcadd($this->num($left), $this->num($right), self::SCALE);
    }

    private function sub(string $left, string $right): string
    {
        return bcsub($this->num($left), $this->num($right), self::SCALE);
    }

    private function mul(string $left, string $right): string
    {
        return bcmul($this->num($left), $this->num($right), self::SCALE);
    }
}
