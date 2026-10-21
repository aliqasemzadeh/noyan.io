<?php

namespace App\Services;

use App\Models\Accounting\Quote;
use Morilog\Jalali\Jalalian;

class QuoteNumberGenerator
{
    public function generate(int $businessId, ?Jalalian $at = null): string
    {
        $jalali = $at ?? Jalalian::now();
        $period = $jalali->format('Y-m');
        $pattern = 'QT-'.$period.'-';

        $latest = Quote::query()
            ->where('business_id', $businessId)
            ->where('quote_number', 'like', $pattern.'%')
            ->where('quote_number', 'not like', '%__del_%')
            ->lockForUpdate()
            ->orderByDesc('quote_number')
            ->value('quote_number');

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
