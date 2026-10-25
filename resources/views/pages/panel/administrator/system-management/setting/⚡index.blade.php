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

    <flux:tab.group>
        <flux:tabs>
            <flux:tab name="general">{{ __('general.general_settings') }}</flux:tab>
            <flux:tab name="contact">{{ __('general.contact_settings') }}</flux:tab>
            <flux:tab name="maintenance">{{ __('general.maintenance_settings') }}</flux:tab>
            <flux:tab name="security">{{ __('general.security_settings') }}</flux:tab>
            <flux:tab name="social">{{ __('general.social_settings') }}</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="general">
            <livewire:system-management.setting.general :key="'setting-general'" />
        </flux:tab.panel>

        <flux:tab.panel name="contact">
            <livewire:system-management.setting.contact :key="'setting-contact'" />
        </flux:tab.panel>

        <flux:tab.panel name="maintenance">
            <livewire:system-management.setting.maintenance :key="'setting-maintenance'" />
        </flux:tab.panel>

        <flux:tab.panel name="security">
            <livewire:system-management.setting.security :key="'setting-security'" />
        </flux:tab.panel>

        <flux:tab.panel name="social">
            <livewire:system-management.setting.social :key="'setting-social'" />
        </flux:tab.panel>
    </flux:tab.group>
</div>
