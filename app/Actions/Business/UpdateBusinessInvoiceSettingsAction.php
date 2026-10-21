<?php

namespace App\Actions\Business;

use App\Enums\Accounting\InvoiceNumberFormat;
use App\Models\Business;
use App\Models\BusinessInvoiceSetting;

class UpdateBusinessInvoiceSettingsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Business $business, array $data): BusinessInvoiceSetting
    {
        $settings = BusinessInvoiceSetting::forBusiness($business);

        $payload = [
            'number_format' => $data['number_format'] instanceof InvoiceNumberFormat
                ? $data['number_format']
                : InvoiceNumberFormat::from((string) $data['number_format']),
            'number_pad' => (int) ($data['number_pad'] ?? 4),
            'sale_prefix' => filled($data['sale_prefix'] ?? null) ? $data['sale_prefix'] : null,
            'purchase_prefix' => filled($data['purchase_prefix'] ?? null) ? $data['purchase_prefix'] : null,
            'quote_prefix' => filled($data['quote_prefix'] ?? null) ? $data['quote_prefix'] : null,
            'email_enabled' => (bool) ($data['email_enabled'] ?? false),
            'sms_enabled' => (bool) ($data['sms_enabled'] ?? false),
            'smtp_host' => filled($data['smtp_host'] ?? null) ? $data['smtp_host'] : null,
            'smtp_port' => filled($data['smtp_port'] ?? null) ? (int) $data['smtp_port'] : null,
            'smtp_encryption' => filled($data['smtp_encryption'] ?? null) ? $data['smtp_encryption'] : null,
            'smtp_username' => filled($data['smtp_username'] ?? null) ? $data['smtp_username'] : null,
            'smtp_from_address' => filled($data['smtp_from_address'] ?? null) ? $data['smtp_from_address'] : null,
            'smtp_from_name' => filled($data['smtp_from_name'] ?? null) ? $data['smtp_from_name'] : null,
            'new_invoice_email_subject' => $data['new_invoice_email_subject'] ?? null,
            'new_invoice_email_body' => $data['new_invoice_email_body'] ?? null,
            'new_invoice_sms_body' => $data['new_invoice_sms_body'] ?? null,
            'payment_reminder_email_subject' => $data['payment_reminder_email_subject'] ?? null,
            'payment_reminder_email_body' => $data['payment_reminder_email_body'] ?? null,
            'payment_reminder_sms_body' => $data['payment_reminder_sms_body'] ?? null,
            'payment_receipt_email_subject' => $data['payment_receipt_email_subject'] ?? null,
            'payment_receipt_email_body' => $data['payment_receipt_email_body'] ?? null,
            'payment_receipt_sms_body' => $data['payment_receipt_sms_body'] ?? null,
            'reminder_days_before' => (int) ($data['reminder_days_before'] ?? 3),
            'reminder_on_due' => (bool) ($data['reminder_on_due'] ?? true),
            'reminder_days_after' => (int) ($data['reminder_days_after'] ?? 1),
            'reminder_email_enabled' => (bool) ($data['reminder_email_enabled'] ?? true),
            'reminder_sms_enabled' => (bool) ($data['reminder_sms_enabled'] ?? false),
            'invoice_footer' => filled($data['invoice_footer'] ?? null) ? $data['invoice_footer'] : null,
        ];

        if (array_key_exists('smtp_password', $data) && filled($data['smtp_password'])) {
            $payload['smtp_password'] = $data['smtp_password'];
        }

        $settings->update($payload);

        return $settings->fresh();
    }
}
