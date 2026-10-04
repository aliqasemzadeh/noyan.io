<?php

use Livewire\Component;

new class extends Component
{
    //
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

    <flux:card>
        <flux:callout icon="settings" variant="secondary" inline>
            {{ __('general.system_settings_placeholder') }}
        </flux:callout>
    </flux:card>
</div>
