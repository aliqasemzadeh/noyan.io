<?php

namespace App\Support\Accounting;

use App\Enums\Accounting\CreditDisplayStyle;
use App\Enums\Accounting\TransactionType;
use App\Models\Business;
use App\Models\Currency;
use App\Services\Accounting\BusinessLedgerSettingsService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;

class SignedAmount
{
    public static function creditStyle(?Business $business = null): CreditDisplayStyle
    {
        $business ??= Auth::user()?->currentBusiness;

        if ($business === null) {
            return CreditDisplayStyle::Parentheses;
        }

        $settings = app(BusinessLedgerSettingsService::class)->get($business);

        return $settings->credit_display_style ?? CreditDisplayStyle::Parentheses;
    }

    /**
     * @param  'none'|'in'|'out'|'transfer'|null  $direction
     * @return array{
     *     display_amount: string,
     *     formatted: string,
     *     color_class: string,
     *     icon: string|null,
     *     icon_class: string|null,
     *     decimals: int
     * }
     */
    public static function present(
        string|int|null $amount,
        string $direction = 'none',
        CreditDisplayStyle|string|null $creditStyle = null,
        int|null $decimals = null,
        Currency|string|null $currency = null,
    ): array {
        $style = $creditStyle !== null
            ? Money::resolveCreditStyle($creditStyle)
            : self::creditStyle();

        $resolvedDecimals = $decimals ?? self::decimalsFor($currency);
        $raw = (string) ($amount ?? '0');
        $displayAmount = self::displayAmountForDirection($raw, $direction);

        return [
            'display_amount' => $displayAmount,
            'formatted' => Money::formatSigned($displayAmount, $resolvedDecimals, $style),
            'color_class' => self::colorClassForDirection($displayAmount, $direction),
            'icon' => self::iconForDirection($direction),
            'icon_class' => self::iconClassForDirection($direction, $displayAmount),
            'decimals' => $resolvedDecimals,
        ];
    }

    public static function directionForTransactionType(TransactionType $type): string
    {
        return match ($type) {
            TransactionType::Income => 'in',
            TransactionType::Expense => 'out',
            TransactionType::Transfer => 'transfer',
        };
    }

    protected static function displayAmountForDirection(string $amount, string $direction): string
    {
        $absolute = Money::absolute($amount);

        return match ($direction) {
            'in' => $absolute,
            'out' => bcmul($absolute, '-1', 18),
            'transfer' => $absolute,
            default => Money::normalize($amount) !== '' && is_numeric(Money::normalize($amount))
                ? Money::normalize($amount)
                : '0',
        };
    }

    protected static function colorClassForDirection(string $displayAmount, string $direction): string
    {
        if ($direction === 'transfer') {
            return 'text-sky-600 dark:text-sky-400';
        }

        return Money::colorClass($displayAmount);
    }

    protected static function iconForDirection(string $direction): ?string
    {
        return match ($direction) {
            'in' => 'arrow-up',
            'out' => 'arrow-down',
            'transfer' => 'arrow-left-right',
            default => null,
        };
    }

    protected static function iconClassForDirection(string $direction, string $displayAmount): ?string
    {
        return match ($direction) {
            'in' => 'text-emerald-600 dark:text-emerald-400',
            'out' => 'text-rose-600 dark:text-rose-400',
            'transfer' => 'text-sky-600 dark:text-sky-400',
            default => Money::colorClass($displayAmount),
        };
    }

    protected static function decimalsFor(Currency|string|null $currency): int
    {
        if ($currency instanceof Currency) {
            return (int) $currency->decimal_places;
        }

        return 0;
    }
}
