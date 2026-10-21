@props([
    'formPrefix' => 'invoiceSettingsForm',
])

@php
    $f = $formPrefix;
@endphp

<div class="space-y-8 border-t border-zinc-200 pt-6 dark:border-zinc-700">
    <div>
        <flux:heading size="lg">{{ __('general.invoice_settings') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.invoice_settings_hint') }}</flux:text>
    </div>

    <div class="space-y-4">
        <flux:heading size="md">{{ __('general.invoice_number_format') }}</flux:heading>

        <flux:field>
            <flux:label>{{ __('general.invoice_number_format') }}</flux:label>
            <flux:radio.group wire:model="{{ $f }}.number_format" variant="cards" class="flex max-sm:flex-col">
                @foreach (\App\Enums\Accounting\InvoiceNumberFormat::cases() as $format)
                    <flux:radio :value="$format->value" :label="$format->label()" />
                @endforeach
            </flux:radio.group>
            <flux:error name="{{ $f }}.number_format" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.invoice_number_pad') }}</flux:label>
                <flux:input type="number" wire:model="{{ $f }}.number_pad" min="1" max="10" />
                <flux:error name="{{ $f }}.number_pad" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('general.invoice_sale_prefix') }}</flux:label>
                <flux:input wire:model="{{ $f }}.sale_prefix" placeholder="SL" clearable dir="ltr" />
                <flux:error name="{{ $f }}.sale_prefix" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('general.invoice_purchase_prefix') }}</flux:label>
                <flux:input wire:model="{{ $f }}.purchase_prefix" placeholder="PR" clearable dir="ltr" />
                <flux:error name="{{ $f }}.purchase_prefix" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('general.invoice_quote_prefix') }}</flux:label>
                <flux:input wire:model="{{ $f }}.quote_prefix" placeholder="QT" clearable dir="ltr" />
                <flux:error name="{{ $f }}.quote_prefix" />
            </flux:field>
        </div>
    </div>

    <div class="space-y-4">
        <flux:field variant="inline">
            <flux:label>{{ __('general.invoice_email_enabled') }}</flux:label>
            <flux:switch wire:model.live="{{ $f }}.email_enabled" />
            <flux:error name="{{ $f }}.email_enabled" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.invoice_sms_enabled') }}</flux:label>
            <flux:switch wire:model.live="{{ $f }}.sms_enabled" />
            <flux:error name="{{ $f }}.sms_enabled" />
        </flux:field>
    </div>

    @if ($this->{$f}->email_enabled)
        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="md">{{ __('general.smtp_settings') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('general.smtp_host') }}</flux:label>
                    <flux:input wire:model="{{ $f }}.smtp_host" dir="ltr" clearable />
                    <flux:error name="{{ $f }}.smtp_host" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('general.smtp_port') }}</flux:label>
                    <flux:input type="number" wire:model="{{ $f }}.smtp_port" dir="ltr" />
                    <flux:error name="{{ $f }}.smtp_port" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('general.smtp_encryption') }}</flux:label>
                    <flux:select wire:model="{{ $f }}.smtp_encryption" variant="listbox">
                        <flux:select.option value="">{{ __('general.smtp_encryption_none') }}</flux:select.option>
                        <flux:select.option value="tls">TLS</flux:select.option>
                        <flux:select.option value="ssl">SSL</flux:select.option>
                    </flux:select>
                    <flux:error name="{{ $f }}.smtp_encryption" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('general.smtp_username') }}</flux:label>
                    <flux:input wire:model="{{ $f }}.smtp_username" dir="ltr" clearable />
                    <flux:error name="{{ $f }}.smtp_username" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('general.smtp_password') }}</flux:label>
                    <flux:input type="password" wire:model="{{ $f }}.smtp_password" viewable dir="ltr" />
                    <flux:description>{{ __('general.smtp_password_keep_hint') }}</flux:description>
                    <flux:error name="{{ $f }}.smtp_password" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('general.smtp_from_address') }}</flux:label>
                    <flux:input type="email" wire:model="{{ $f }}.smtp_from_address" dir="ltr" clearable />
                    <flux:error name="{{ $f }}.smtp_from_address" />
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('general.smtp_from_name') }}</flux:label>
                    <flux:input wire:model="{{ $f }}.smtp_from_name" clearable />
                    <flux:error name="{{ $f }}.smtp_from_name" />
                </flux:field>
            </div>
        </div>
    @endif

    <div class="space-y-4">
        <div>
            <flux:heading size="md">{{ __('general.notification_templates') }}</flux:heading>
            <flux:text class="mt-1 text-sm">
                {{ __('general.available_placeholders') }}:
                <span dir="ltr">{client_name}, {company_name}, {invoice_number}, {amount}, {balance}, {due_date}, {link}</span>
            </flux:text>
        </div>

        @foreach ([
            'new_invoice' => __('general.new_invoice_email'),
            'payment_reminder' => __('general.payment_reminder_email'),
            'payment_receipt' => __('general.payment_receipt_email'),
        ] as $key => $label)
            <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">{{ $label }}</flux:heading>
                <flux:field>
                    <flux:label>{{ __('general.email_subject') }}</flux:label>
                    <flux:input wire:model="{{ $f }}.{{ $key }}_email_subject" clearable />
                    <flux:error name="{{ $f }}.{{ $key }}_email_subject" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('general.email_body') }}</flux:label>
                    <flux:textarea wire:model="{{ $f }}.{{ $key }}_email_body" rows="4" />
                    <flux:error name="{{ $f }}.{{ $key }}_email_body" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('general.sms_body') }}</flux:label>
                    <flux:textarea wire:model="{{ $f }}.{{ $key }}_sms_body" rows="3" />
                    <flux:error name="{{ $f }}.{{ $key }}_sms_body" />
                </flux:field>
            </div>
        @endforeach
    </div>

    <div class="space-y-4">
        <flux:heading size="md">{{ __('general.reminder_settings') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.reminder_days_before') }}</flux:label>
                <flux:input type="number" wire:model="{{ $f }}.reminder_days_before" min="0" max="90" />
                <flux:error name="{{ $f }}.reminder_days_before" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('general.reminder_days_after') }}</flux:label>
                <flux:input type="number" wire:model="{{ $f }}.reminder_days_after" min="0" max="90" />
                <flux:error name="{{ $f }}.reminder_days_after" />
            </flux:field>
        </div>

        <flux:field variant="inline">
            <flux:label>{{ __('general.reminder_on_due') }}</flux:label>
            <flux:switch wire:model="{{ $f }}.reminder_on_due" />
            <flux:error name="{{ $f }}.reminder_on_due" />
        </flux:field>
        <flux:field variant="inline">
            <flux:label>{{ __('general.reminder_email_enabled') }}</flux:label>
            <flux:switch wire:model="{{ $f }}.reminder_email_enabled" />
            <flux:error name="{{ $f }}.reminder_email_enabled" />
        </flux:field>
        <flux:field variant="inline">
            <flux:label>{{ __('general.reminder_sms_enabled') }}</flux:label>
            <flux:switch wire:model="{{ $f }}.reminder_sms_enabled" />
            <flux:error name="{{ $f }}.reminder_sms_enabled" />
        </flux:field>
    </div>

    <flux:field>
        <flux:label>{{ __('general.business_invoice_footer') }}</flux:label>
        <flux:textarea wire:model="{{ $f }}.invoice_footer" rows="3" />
        <flux:error name="{{ $f }}.invoice_footer" />
    </flux:field>
</div>
