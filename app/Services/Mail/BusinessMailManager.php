<?php

namespace App\Services\Mail;

use App\Models\BusinessInvoiceSetting;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class BusinessMailManager
{
    public function mailerFor(BusinessInvoiceSetting $settings): Mailer
    {
        if (! $settings->email_enabled || ! $settings->hasCompleteSmtp()) {
            throw new RuntimeException('Business SMTP is not configured.');
        }

        $name = 'business_smtp_'.$settings->business_id;

        Config::set("mail.mailers.{$name}", [
            'transport' => 'smtp',
            'scheme' => null,
            'url' => null,
            'host' => $settings->smtp_host,
            'port' => $settings->smtp_port,
            'encryption' => filled($settings->smtp_encryption) ? $settings->smtp_encryption : null,
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'timeout' => null,
            'local_domain' => null,
        ]);

        Config::set("mail.from.address_{$name}", $settings->smtp_from_address);
        Config::set("mail.from.name_{$name}", $settings->smtp_from_name ?: config('app.name'));

        return Mail::mailer($name);
    }
}
