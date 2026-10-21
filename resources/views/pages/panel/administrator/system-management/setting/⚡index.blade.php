<?php

use App\Settings\InvoiceSystemSettings;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public string $invoice_global_footer = '';

    public string $sms_unit_cost = '1000';

    public function mount(InvoiceSystemSettings $settings): void
    {
        $this->invoice_global_footer = $settings->invoice_global_footer;
        $this->sms_unit_cost = $settings->sms_unit_cost;
    }

    public function save(InvoiceSystemSettings $settings): void
    {
        $validated = $this->validate([
            'invoice_global_footer' => ['nullable', 'string', 'max:5000'],
            'sms_unit_cost' => ['required', 'numeric', 'gte:0'],
        ], attributes: [
            'invoice_global_footer' => __('general.invoice_global_footer'),
            'sms_unit_cost' => __('general.sms_unit_cost'),
        ]);

        $settings->invoice_global_footer = (string) ($validated['invoice_global_footer'] ?? '');
        $settings->sms_unit_cost = (string) $validated['sms_unit_cost'];
        $settings->save();

        Flux::toast(__('general.system_settings_saved'));
    }
};
?>

<x-slot name="title">{{ __('general.system_settings') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('system.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_settings') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.system_settings') }}
            </flux:heading>
            <flux:text class="mt-1">
                {{ __('general.system_settings_hint') }}
            </flux:text>
        </div>
    </div>

    <div class="mx-auto max-w-2xl">
        <flux:card>
            <form wire:submit="save" class="space-y-6">
                <flux:field>
                    <flux:label>{{ __('general.invoice_global_footer') }}</flux:label>
                    <flux:textarea wire:model="invoice_global_footer" rows="4" />
                    <flux:description>{{ __('general.invoice_global_footer_hint') }}</flux:description>
                    <flux:error name="invoice_global_footer" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.sms_unit_cost') }}</flux:label>
                    <flux:input type="number" wire:model="sms_unit_cost" min="0" step="1" dir="ltr" />
                    <flux:description>{{ __('general.sms_unit_cost_hint') }}</flux:description>
                    <flux:error name="sms_unit_cost" />
                </flux:field>

                <flux:button type="submit" variant="primary" color="teal" class="w-full">
                    {{ __('general.save') }}
                </flux:button>
            </form>
        </flux:card>
    </div>
</div>
