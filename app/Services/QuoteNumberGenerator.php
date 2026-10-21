<?php

namespace App\Services;

use App\Enums\Accounting\InvoiceNumberFormat;
use App\Models\Accounting\Quote;
use App\Models\Business;
use App\Models\BusinessInvoiceSetting;
use Morilog\Jalali\Jalalian;

class QuoteNumberGenerator
{
    public function generate(int $businessId, ?Jalalian $at = null): string
    {
        $jalali = $at ?? Jalalian::now();
        $settings = $this->settingsFor($businessId);
        $format = $settings->number_format ?? InvoiceNumberFormat::YearMonth;
        $period = $format->periodPattern($jalali);
        $prefix = $settings->quotePrefix();
        $pad = max(1, (int) ($settings->number_pad ?? 4));
        $pattern = $prefix.'-'.$period.'-';

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

        return $pattern.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }

    protected function settingsFor(int $businessId): BusinessInvoiceSetting
    {
        $business = Business::query()->find($businessId);

        if ($business === null) {
            return new BusinessInvoiceSetting(BusinessInvoiceSetting::defaultAttributes());
        }

        return BusinessInvoiceSetting::forBusiness($business);
    }
}
