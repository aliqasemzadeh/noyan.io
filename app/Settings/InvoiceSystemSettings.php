<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class InvoiceSystemSettings extends Settings
{

    public static function group(): string
    {
        return 'invoice';
    }
}