<?php

namespace App\Services;

use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use Morilog\Jalali\Jalalian;

class InvoiceNumberGenerator
{
    public function generate(int $businessId, InvoiceType $type, ?Jalalian $at = null): string
    {
        $jalali = $at ?? Jalalian::now();
        $period = $jalali->format('Y-m');
        $prefix = $type->prefix();
        $pattern = $prefix.'-'.$period.'-';

        $latest = Invoice::query()
            ->where('business_id', $businessId)
            ->where('type', $type->value)
            ->where('invoice_number', 'like', $pattern.'%')
            ->where('invoice_number', 'not like', '%__del_%')
            ->lockForUpdate()
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $next = 1;

        if (is_string($latest)) {
            $sequence = substr($latest, strlen($pattern));

            if (ctype_digit($sequence)) {
                $next = ((int) $sequence) + 1;
            }
        }

        return $pattern.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
