<?php

namespace App\Support;

use App\Models\Business;
use App\Models\BusinessInvoiceSetting;
use App\Settings\InvoiceSystemSettings;
use Illuminate\Support\HtmlString;

class InvoiceDocumentFooter
{
    public static function forBusiness(Business $business): ?HtmlString
    {
        $system = app(InvoiceSystemSettings::class);
        $settings = BusinessInvoiceSetting::forBusiness($business);

        $parts = array_values(array_filter([
            filled($system->invoice_global_footer) ? trim($system->invoice_global_footer) : null,
            filled($settings->invoice_footer) ? trim((string) $settings->invoice_footer) : null,
        ]));

        if ($parts === []) {
            return null;
        }

        $html = collect($parts)
            ->map(fn (string $part): string => '<p class="mt-1 text-[10px] text-black whitespace-pre-wrap">'.e($part).'</p>')
            ->implode('');

        return new HtmlString('<div class="mt-1.5 border-t border-zinc-300 pt-1.5">'.$html.'</div>');
    }
}
