@props([
    'formPrefix' => 'invoiceSettingsForm',
])

@php
    $f = $formPrefix;
@endphp

<flux:field>
    <flux:label>{{ __('general.business_invoice_footer') }}</flux:label>
    <flux:textarea wire:model="{{ $f }}.invoice_footer" rows="3" />
    <flux:error name="{{ $f }}.invoice_footer" />
</flux:field>
