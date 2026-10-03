<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div>
    <flux:sidebar.nav>
        <flux:sidebar.item
            icon="settings"
            :href="route('system.dashboard')"
            wire:navigate
            :current="request()->routeIs('system.*')"
        >
            {{ __('general.system_management') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="circle-user"
            :href="route('user.dashboard')"
            wire:navigate
            :current="request()->routeIs('user.*')"
        >
            {{ __('general.user_settings') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="calculator"
            :href="route('accounting.dashboard')"
            wire:navigate
            :current="request()->routeIs('accounting.*')"
        >
            {{ __('general.accounting') }}
        </flux:sidebar.item>
    </flux:sidebar.nav>
</div>
