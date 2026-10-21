<?php

namespace App\Services;

use App\Enums\Accounting\InvoiceNumberFormat;
use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Business;
use App\Models\BusinessInvoiceSetting;
use Morilog\Jalali\Jalalian;

class InvoiceNumberGenerator
{
    public function generate(int $businessId, InvoiceType $type, ?Jalalian $at = null): string
    {
        $jalali = $at ?? Jalalian::now();
        $settings = $this->settingsFor($businessId);
        $format = $settings->number_format ?? InvoiceNumberFormat::YearMonth;
        $period = $format->periodPattern($jalali);
        $prefix = $settings->prefixForInvoiceType($type);
        $pad = max(1, (int) ($settings->number_pad ?? 4));
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
