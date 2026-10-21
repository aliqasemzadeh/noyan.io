<?php

namespace App\Models;

use App\Enums\Accounting\InvoiceNumberFormat;
use App\Enums\Accounting\InvoiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id',
    'number_format',
    'number_pad',
    'sale_prefix',
    'purchase_prefix',
    'quote_prefix',
    'email_enabled',
    'sms_enabled',
    'smtp_host',
    'smtp_port',
    'smtp_encryption',
    'smtp_username',
    'smtp_password',
    'smtp_from_address',
    'smtp_from_name',
    'new_invoice_email_subject',
    'new_invoice_email_body',
    'new_invoice_sms_body',
    'payment_reminder_email_subject',
    'payment_reminder_email_body',
    'payment_reminder_sms_body',
    'payment_receipt_email_subject',
    'payment_receipt_email_body',
    'payment_receipt_sms_body',
    'reminder_days_before',
    'reminder_on_due',
    'reminder_days_after',
    'reminder_email_enabled',
    'reminder_sms_enabled',
    'invoice_footer',
])]
class BusinessInvoiceSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number_format' => InvoiceNumberFormat::class,
            'number_pad' => 'integer',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
            'reminder_days_before' => 'integer',
            'reminder_on_due' => 'boolean',
            'reminder_days_after' => 'integer',
            'reminder_email_enabled' => 'boolean',
            'reminder_sms_enabled' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'number_format' => InvoiceNumberFormat::YearMonth->value,
            'number_pad' => 4,
            'sale_prefix' => null,
            'purchase_prefix' => null,
            'quote_prefix' => null,
            'email_enabled' => false,
            'sms_enabled' => false,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_encryption' => null,
            'smtp_username' => null,
            'smtp_password' => null,
            'smtp_from_address' => null,
            'smtp_from_name' => null,
            'new_invoice_email_subject' => __('general.default_new_invoice_email_subject'),
            'new_invoice_email_body' => __('general.default_new_invoice_email_body'),
            'new_invoice_sms_body' => __('general.default_new_invoice_sms_body'),
            'payment_reminder_email_subject' => __('general.default_payment_reminder_email_subject'),
            'payment_reminder_email_body' => __('general.default_payment_reminder_email_body'),
            'payment_reminder_sms_body' => __('general.default_payment_reminder_sms_body'),
            'payment_receipt_email_subject' => __('general.default_payment_receipt_email_subject'),
            'payment_receipt_email_body' => __('general.default_payment_receipt_email_body'),
            'payment_receipt_sms_body' => __('general.default_payment_receipt_sms_body'),
            'reminder_days_before' => 3,
            'reminder_on_due' => true,
            'reminder_days_after' => 1,
            'reminder_email_enabled' => true,
            'reminder_sms_enabled' => false,
            'invoice_footer' => null,
        ];
    }

    public static function forBusiness(Business $business): self
    {
        return $business->invoiceSettings()->firstOrCreate(
            ['business_id' => $business->id],
            self::defaultAttributes(),
        );
    }

    public function prefixForInvoiceType(InvoiceType $type): string
    {
        $custom = match ($type) {
            InvoiceType::Sale => $this->sale_prefix,
            InvoiceType::Purchase => $this->purchase_prefix,
            InvoiceType::Return => null,
        };

        return filled($custom) ? strtoupper((string) $custom) : $type->prefix();
    }

    public function quotePrefix(): string
    {
        return filled($this->quote_prefix) ? strtoupper((string) $this->quote_prefix) : 'QT';
    }

    public function hasCompleteSmtp(): bool
    {
        return filled($this->smtp_host)
            && filled($this->smtp_port)
            && filled($this->smtp_from_address)
            && filled($this->smtp_username);
    }

    public function emailSubjectFor(string $templateKey): string
    {
        return (string) ($this->{$templateKey.'_email_subject'} ?? '');
    }

    public function emailBodyFor(string $templateKey): string
    {
        return (string) ($this->{$templateKey.'_email_body'} ?? '');
    }

    public function smsBodyFor(string $templateKey): string
    {
        return (string) ($this->{$templateKey.'_sms_body'} ?? '');
    }
}
