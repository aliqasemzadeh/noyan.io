<?php

namespace App\Livewire\Forms;

use App\Actions\Business\UpdateBusinessInvoiceSettingsAction;
use App\Enums\Accounting\InvoiceNumberFormat;
use App\Models\Business;
use App\Models\BusinessInvoiceSetting;
use Illuminate\Validation\Rule;
use Livewire\Form;

class BusinessInvoiceSettingsForm extends Form
{
    public ?Business $business = null;

    public string $number_format = 'year_month';

    public int $number_pad = 4;

    public string $sale_prefix = '';

    public string $purchase_prefix = '';

    public string $quote_prefix = '';

    public bool $email_enabled = false;

    public bool $sms_enabled = false;

    public string $smtp_host = '';

    public string $smtp_port = '';

    public string $smtp_encryption = '';

    public string $smtp_username = '';

    public string $smtp_password = '';

    public string $smtp_from_address = '';

    public string $smtp_from_name = '';

    public string $new_invoice_email_subject = '';

    public string $new_invoice_email_body = '';

    public string $new_invoice_sms_body = '';

    public string $payment_reminder_email_subject = '';

    public string $payment_reminder_email_body = '';

    public string $payment_reminder_sms_body = '';

    public string $payment_receipt_email_subject = '';

    public string $payment_receipt_email_body = '';

    public string $payment_receipt_sms_body = '';

    public int $reminder_days_before = 3;

    public bool $reminder_on_due = true;

    public int $reminder_days_after = 1;

    public bool $reminder_email_enabled = true;

    public bool $reminder_sms_enabled = false;

    public string $invoice_footer = '';

    public function setModel(Business $business): void
    {
        $this->business = $business;
        $settings = BusinessInvoiceSetting::forBusiness($business);

        $this->number_format = $settings->number_format?->value ?? InvoiceNumberFormat::YearMonth->value;
        $this->number_pad = (int) $settings->number_pad;
        $this->sale_prefix = (string) ($settings->sale_prefix ?? '');
        $this->purchase_prefix = (string) ($settings->purchase_prefix ?? '');
        $this->quote_prefix = (string) ($settings->quote_prefix ?? '');
        $this->email_enabled = (bool) $settings->email_enabled;
        $this->sms_enabled = (bool) $settings->sms_enabled;
        $this->smtp_host = (string) ($settings->smtp_host ?? '');
        $this->smtp_port = $settings->smtp_port !== null ? (string) $settings->smtp_port : '';
        $this->smtp_encryption = (string) ($settings->smtp_encryption ?? '');
        $this->smtp_username = (string) ($settings->smtp_username ?? '');
        $this->smtp_password = '';
        $this->smtp_from_address = (string) ($settings->smtp_from_address ?? '');
        $this->smtp_from_name = (string) ($settings->smtp_from_name ?? '');
        $this->new_invoice_email_subject = (string) ($settings->new_invoice_email_subject ?? '');
        $this->new_invoice_email_body = (string) ($settings->new_invoice_email_body ?? '');
        $this->new_invoice_sms_body = (string) ($settings->new_invoice_sms_body ?? '');
        $this->payment_reminder_email_subject = (string) ($settings->payment_reminder_email_subject ?? '');
        $this->payment_reminder_email_body = (string) ($settings->payment_reminder_email_body ?? '');
        $this->payment_reminder_sms_body = (string) ($settings->payment_reminder_sms_body ?? '');
        $this->payment_receipt_email_subject = (string) ($settings->payment_receipt_email_subject ?? '');
        $this->payment_receipt_email_body = (string) ($settings->payment_receipt_email_body ?? '');
        $this->payment_receipt_sms_body = (string) ($settings->payment_receipt_sms_body ?? '');
        $this->reminder_days_before = (int) $settings->reminder_days_before;
        $this->reminder_on_due = (bool) $settings->reminder_on_due;
        $this->reminder_days_after = (int) $settings->reminder_days_after;
        $this->reminder_email_enabled = (bool) $settings->reminder_email_enabled;
        $this->reminder_sms_enabled = (bool) $settings->reminder_sms_enabled;
        $this->invoice_footer = (string) ($settings->invoice_footer ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number_format' => ['required', Rule::enum(InvoiceNumberFormat::class)],
            'number_pad' => ['required', 'integer', 'min:1', 'max:10'],
            'sale_prefix' => ['nullable', 'string', 'max:20'],
            'purchase_prefix' => ['nullable', 'string', 'max:20'],
            'quote_prefix' => ['nullable', 'string', 'max:20'],
            'email_enabled' => ['boolean'],
            'sms_enabled' => ['boolean'],
            'smtp_host' => ['nullable', 'required_if:email_enabled,true', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'required_if:email_enabled,true', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['', 'tls', 'ssl'])],
            'smtp_username' => ['nullable', 'required_if:email_enabled,true', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'required_if:email_enabled,true', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'new_invoice_email_subject' => ['nullable', 'string', 'max:255'],
            'new_invoice_email_body' => ['nullable', 'string', 'max:10000'],
            'new_invoice_sms_body' => ['nullable', 'string', 'max:1000'],
            'payment_reminder_email_subject' => ['nullable', 'string', 'max:255'],
            'payment_reminder_email_body' => ['nullable', 'string', 'max:10000'],
            'payment_reminder_sms_body' => ['nullable', 'string', 'max:1000'],
            'payment_receipt_email_subject' => ['nullable', 'string', 'max:255'],
            'payment_receipt_email_body' => ['nullable', 'string', 'max:10000'],
            'payment_receipt_sms_body' => ['nullable', 'string', 'max:1000'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:90'],
            'reminder_on_due' => ['boolean'],
            'reminder_days_after' => ['required', 'integer', 'min:0', 'max:90'],
            'reminder_email_enabled' => ['boolean'],
            'reminder_sms_enabled' => ['boolean'],
            'invoice_footer' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'number_format' => __('general.invoice_number_format'),
            'number_pad' => __('general.invoice_number_pad'),
            'sale_prefix' => __('general.invoice_sale_prefix'),
            'purchase_prefix' => __('general.invoice_purchase_prefix'),
            'quote_prefix' => __('general.invoice_quote_prefix'),
            'email_enabled' => __('general.invoice_email_enabled'),
            'sms_enabled' => __('general.invoice_sms_enabled'),
            'smtp_host' => __('general.smtp_host'),
            'smtp_port' => __('general.smtp_port'),
            'smtp_encryption' => __('general.smtp_encryption'),
            'smtp_username' => __('general.smtp_username'),
            'smtp_password' => __('general.smtp_password'),
            'smtp_from_address' => __('general.smtp_from_address'),
            'smtp_from_name' => __('general.smtp_from_name'),
            'reminder_days_before' => __('general.reminder_days_before'),
            'reminder_days_after' => __('general.reminder_days_after'),
            'invoice_footer' => __('general.business_invoice_footer'),
        ];
    }

    public function update(UpdateBusinessInvoiceSettingsAction $action): BusinessInvoiceSetting
    {
        $validated = $this->validate();

        $settings = $action->handle($this->business, $validated);

        $this->setModel($this->business->fresh());

        return $settings;
    }
}
