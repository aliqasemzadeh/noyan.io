<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="space-y-6">
    <x-slot name="title">{{ __('general.user_settings') }} - {{ __('general.app_name') }}</x-slot>

    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">
                {{ __('general.user_settings') }}
            </flux:heading>

            <flux:text class="mt-2 text-base">
                {{ __('general.user_dashboard_placeholder') }}
            </flux:text>
        </div>

        <flux:dropdown>
            <flux:button icon:trailing="chevron-down">{{ __('general.options') }}</flux:button>
            <flux:menu>
                <flux:menu.item icon="building" :href="route('user.businesses.index')" wire:navigate>
                    {{ __('general.my_businesses') }}
                </flux:menu.item>
                <flux:menu.item icon="plus" :href="route('user.businesses.create')" wire:navigate>
                    {{ __('general.add_business') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    @if (! auth()->user()->currentBusiness)
        <flux:callout icon="building" variant="secondary" inline>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="sm">{{ __('general.no_business_yet') }}</flux:heading>
                    <flux:text>{{ __('general.create_first_business_hint') }}</flux:text>
                </div>
                <flux:button
                    variant="primary"
                    color="teal"
                    icon="plus"
                    :href="route('user.businesses.create')"
                    wire:navigate
                >
                    {{ __('general.create_business') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif
</div>
