<?php

namespace App\Ai\Tools;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Transaction;
use App\Support\LocaleDate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetDailyExpenses implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'گزارش هزینه‌های یک روز (پیش‌فرض امروز). وقتی کاربر هزینه امروز، مجموع برداشت‌ها، یا لیست هزینه‌های روز را خواست این ابزار را صدا بزن.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($user === null || $businessId === null) {
            return __('general.business_required');
        }

        $dateInput = trim((string) $request->string('date'));
        $storageDate = $dateInput !== ''
            ? LocaleDate::toStorageDate($dateInput)
            : now()->toDateString();

        if ($storageDate === null) {
            return __('general.ai_daily_expenses_invalid_date');
        }

        $transactions = Transaction::query()
            ->with(['account:id,name', 'category:id,name'])
            ->where('business_id', $businessId)
            ->where('type', TransactionType::Expense)
            ->whereDate('transaction_date', $storageDate)
            ->orderByDesc('id')
            ->get(['id', 'account_id', 'category_id', 'amount', 'base_amount', 'currency', 'note']);

        $displayDate = LocaleDate::formatDate($storageDate);

        if ($transactions->isEmpty()) {
            return __('general.ai_daily_expenses_empty', [
                'date' => $displayDate,
            ]);
        }

        $totalBase = $transactions->reduce(
            fn (string $carry, Transaction $transaction): string => bcadd($carry, (string) $transaction->base_amount, 18),
            '0',
        );

        $lines = $transactions->map(function (Transaction $transaction, int $index): string {
            $amount = $this->formatAmount((string) $transaction->amount);
            $currency = $transaction->currency ?: '';
            $amountStr = $currency !== '' ? "{$amount} {$currency}" : $amount;
            $account = $transaction->account?->name ?: '—';
            $category = $transaction->category?->name;
            $note = trim((string) ($transaction->note ?? ''));

            $detail = $category ?: ($note !== '' ? $note : null);

            return __('general.ai_daily_expenses_item', [
                'n' => $index + 1,
                'amount' => $amountStr,
                'account' => $account,
                'detail' => $detail !== null ? " — {$detail}" : '',
            ]);
        })->implode("\n");

        return __('general.ai_daily_expenses_summary', [
            'date' => $displayDate,
            'total' => $this->formatAmount($totalBase),
            'count' => $transactions->count(),
            'items' => $lines,
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()
                ->description('تاریخ جلالی مثل 1403/07/01 یا میلادی Y-m-d؛ خالی = امروز'),
        ];
    }

    protected function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format(
            (float) $normalized,
            substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0,
        );
    }
}
