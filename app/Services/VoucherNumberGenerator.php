<?php

namespace App\Services;

use App\Models\Accounting\JournalEntry;

class VoucherNumberGenerator
{
    public function generate(int $businessId, int $fiscalYearId): string
    {
        $latest = JournalEntry::query()
            ->where('business_id', $businessId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('voucher_number', 'not like', '%__del_%')
            ->lockForUpdate()
            ->orderByDesc('voucher_number')
            ->value('voucher_number');

        $next = 1;

        if (is_string($latest) && ctype_digit($latest)) {
            $next = ((int) $latest) + 1;
        }

        return str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
