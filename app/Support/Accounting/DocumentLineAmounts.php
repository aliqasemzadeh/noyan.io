<?php

namespace App\Support\Accounting;

use App\Support\Money;

final class DocumentLineAmounts
{
    /**
     * @return array{
     *     quantity: string,
     *     unit_price: string,
     *     line_total: string,
     *     discount_amount: string,
     *     after_discount: string,
     *     tax_amount: string,
     *     final_total: string
     * }
     */
    public static function forItem(object $item): array
    {
        $quantity = self::amount((string) ($item->quantity ?? '0'));
        $unitPrice = self::amount((string) ($item->unit_price ?? '0'));
        $discount = self::amount((string) ($item->discount_amount ?? '0'));
        $tax = self::amount((string) ($item->tax_amount ?? '0'));

        $lineTotal = bcmul($quantity, $unitPrice, 18);
        $afterDiscount = bcsub($lineTotal, $discount, 18);
        $finalTotal = bcadd($afterDiscount, $tax, 18);

        return [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'discount_amount' => $discount,
            'after_discount' => $afterDiscount,
            'tax_amount' => $tax,
            'final_total' => $finalTotal,
        ];
    }

    /**
     * @param  iterable<int, object>  $items
     * @return array{
     *     line_total: string,
     *     discount_amount: string,
     *     after_discount: string,
     *     tax_amount: string,
     *     final_total: string
     * }
     */
    public static function columnTotals(iterable $items): array
    {
        $totals = [
            'line_total' => '0',
            'discount_amount' => '0',
            'after_discount' => '0',
            'tax_amount' => '0',
            'final_total' => '0',
        ];

        foreach ($items as $item) {
            $line = self::forItem($item);

            foreach ($totals as $key => $value) {
                $totals[$key] = bcadd($value, $line[$key], 18);
            }
        }

        return $totals;
    }

    private static function amount(string $value): string
    {
        $normalized = Money::normalize($value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return '0';
        }

        return bcadd($normalized, '0', 18);
    }
}
