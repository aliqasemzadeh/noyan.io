<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class InvoiceSystemSettings extends Settings
{
    public string $invoice_global_footer = '';

    public string $sms_unit_cost = '1000';

    public static function group(): string
    {
        return 'invoice';
    }
}
