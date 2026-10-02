<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div>
    <flux:sidebar.nav>
        @if (request()->routeIs('system.*'))
            <flux:sidebar.item
                icon="home"
                :href="route('system.dashboard')"
                wire:navigate
                :current="request()->routeIs('system.dashboard')"
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="users"
                :href="route('system.users.index')"
                wire:navigate
                :current="request()->routeIs('system.users.*')"
            >
                {{ __('general.users') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="building"
                :href="route('system.businesses.index')"
                wire:navigate
                :current="request()->routeIs('system.businesses.*')"
            >
                {{ __('general.businesses') }}
            </flux:sidebar.item>
        @elseif (request()->routeIs('user.*'))
            <flux:sidebar.item
                icon="home"
                :href="route('user.dashboard')"
                wire:navigate
                :current="request()->routeIs('user.dashboard')"
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>
        @elseif (request()->routeIs('accounting.*'))
            <flux:sidebar.item
                icon="home"
                :href="route('accounting.dashboard')"
                wire:navigate
                :current="request()->routeIs('accounting.dashboard')"
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>
        @endif
    </flux:sidebar.nav>
</div>
