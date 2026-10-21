<?php

namespace App\Enums\Accounting;

enum InvoiceNotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
}
