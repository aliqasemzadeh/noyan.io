@props([
    'formPrefix' => 'invoiceSettingsForm',
])

@php
    $f = $formPrefix;
@endphp

<div class="space-y-4">
    <div>
        <flux:heading size="md">{{ __('general.invoice_number_format') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.invoice_settings_hint') }}</flux:text>
    </div>

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
