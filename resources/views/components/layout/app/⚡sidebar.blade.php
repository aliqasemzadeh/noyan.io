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
            <flux:sidebar.group heading="{{ __('general.system_management') }}" class="grid">
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

                <flux:sidebar.item
                    icon="coins"
                    :href="route('system.currencies.index')"
                    wire:navigate
                    :current="request()->routeIs('system.currencies.*')"
                >
                    {{ __('general.currencies') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        @elseif (request()->routeIs('user.*'))
            <flux:sidebar.group heading="{{ __('general.user_settings') }}" class="grid">
                <flux:sidebar.item
                    icon="home"
                    :href="route('user.dashboard')"
                    wire:navigate
                    :current="request()->routeIs('user.dashboard')"
                >
                    {{ __('general.dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        @elseif (request()->routeIs('accounting.*'))
            <flux:sidebar.group heading="{{ __('general.accounting') }}" class="grid">
                <flux:sidebar.item
                    icon="home"
                    :href="route('accounting.dashboard')"
                    wire:navigate
                    :current="request()->routeIs('accounting.dashboard')"
                >
                    {{ __('general.dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group heading="{{ __('general.treasury') }}" class="grid">
                <flux:sidebar.item
                    icon="wallet"
                    :href="route('accounting.accounts.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.accounts.*')"
                >
                    {{ __('general.cash_and_bank_accounts') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="coins"
                    :href="route('accounting.currencies.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.currencies.*')"
                >
                    {{ __('general.business_currencies') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        @endif
    </flux:sidebar.nav>
</div>
