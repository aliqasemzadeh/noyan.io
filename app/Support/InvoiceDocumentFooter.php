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
            ->map(fn (string $part): string => '<p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-wrap">'.e($part).'</p>')
            ->implode('');

        return new HtmlString('<div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">'.$html.'</div>');
    }
}
