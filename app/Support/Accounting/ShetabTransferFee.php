<?php

namespace App\Support\Accounting;

/**
 * کارمزد انتقال کارت به کارت / کارت به شبا (سحاب) — تعرفه ۱۴۰۵.
 *
 * تا یک میلیون تومان: ۱٬۱۰۰ تومان؛ به ازای هر یک میلیون تومان مازاد: +۳۵۰ تومان.
 */
final class ShetabTransferFee
{
    private const BASE_TOMAN = '1100';

    private const PER_EXTRA_MILLION_TOMAN = '350';

    private const MILLION_TOMAN = '1000000';

    /**
     * محاسبه کارمزد به واحد ارز حساب. برای ارزهای غیر IRT/IRR مقدار null برمی‌گردد.
     */
    public static function forAmount(string $amount, string $currencyCode): ?string
    {
        $currencyCode = strtoupper(trim($currencyCode));

        if (! in_array($currencyCode, ['IRT', 'IRR'], true)) {
            return null;
        }

        if ($amount === '' || ! is_numeric($amount) || bccomp($amount, '0', 18) !== 1) {
            return null;
        }

        $amountToman = $currencyCode === 'IRR'
            ? bcdiv($amount, '10', 0)
            : bcadd($amount, '0', 0);

        if (bccomp($amountToman, '0', 0) !== 1) {
            return null;
        }

        $feeToman = self::feeInToman($amountToman);

        return $currencyCode === 'IRR'
            ? bcmul($feeToman, '10', 0)
            : $feeToman;
    }

    protected static function feeInToman(string $amountToman): string
    {
        $blocks = (int) bcdiv($amountToman, self::MILLION_TOMAN, 0);

        if (bccomp(bcmod($amountToman, self::MILLION_TOMAN), '0', 0) === 1) {
            $blocks++;
        }

        if ($blocks < 1) {
            $blocks = 1;
        }

        $extra = max(0, $blocks - 1);

        return bcadd(
            self::BASE_TOMAN,
            bcmul((string) $extra, self::PER_EXTRA_MILLION_TOMAN, 0),
            0,
        );
    }
}
