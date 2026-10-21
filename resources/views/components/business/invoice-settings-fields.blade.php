@props([
    'formPrefix' => 'invoiceSettingsForm',
])

<div class="space-y-8 border-t border-zinc-200 pt-6 dark:border-zinc-700">
    <div>
        <flux:heading size="lg">{{ __('general.invoice_settings') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.invoice_settings_hint') }}</flux:text>
    </div>

    <x-business.invoice-numbering-fields :form-prefix="$formPrefix" />

    <x-business.invoice-notification-fields :form-prefix="$formPrefix" />

    <x-business.invoice-footer-field :form-prefix="$formPrefix" />
</div>
