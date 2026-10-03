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

            <flux:sidebar.item
                icon="coins"
                :href="route('system.currencies.index')"
                wire:navigate
                :current="request()->routeIs('system.currencies.*')"
            >
                {{ __('general.currencies') }}
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

            <flux:sidebar.item
                icon="building"
                :href="route('user.businesses.index')"
                wire:navigate
                :current="request()->routeIs('user.businesses.*')"
            >
                {{ __('general.my_businesses') }}
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

            <flux:sidebar.group expandable icon="users" heading="{{ __('general.parties') }}" class="grid">
                <flux:sidebar.item
                    :href="route('accounting.parties.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.parties.*')"
                >
                    {{ __('general.parties') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group expandable icon="package" heading="{{ __('general.catalog') }}" class="grid">
                <flux:sidebar.item
                    :href="route('accounting.catalog.products.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.catalog.products.*')"
                >
                    {{ __('general.products') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="layers"
                    :href="route('accounting.catalog.categories.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.catalog.categories.*')"
                >
                    {{ __('general.product_categories') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="tags"
                    :href="route('accounting.catalog.brands.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.catalog.brands.*')"
                >
                    {{ __('general.brands') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group expandable icon="wallet" heading="{{ __('general.treasury') }}" class="grid">
                <flux:sidebar.item
                    :href="route('accounting.accounts.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.accounts.*')"
                >
                    {{ __('general.cash_and_bank_accounts') }}
                </flux:sidebar.item>

                <flux:sidebar.item
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
