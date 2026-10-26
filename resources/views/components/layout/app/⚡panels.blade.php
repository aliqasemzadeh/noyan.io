<?php

use App\Enums\Business\BusinessPanel;
use Livewire\Component;

new class extends Component
{
    //
};
?>

<div>
    <flux:sidebar.nav>
        @if (auth()->user()?->canAccessPanel(BusinessPanel::Accounting))
            <flux:sidebar.item
                icon="calculator"
                :href="route('accounting.dashboard')"
                wire:navigate
                :current="request()->routeIs('accounting.*')"
            >
                {{ __('general.accounting') }}
            </flux:sidebar.item>
        @endif

        @if (auth()->user()?->canAccessPanel(BusinessPanel::Warehouse))
            <flux:sidebar.item
                icon="warehouse"
                :href="route('warehouse.dashboard')"
                wire:navigate
                :current="request()->routeIs('warehouse.*')"
            >
                {{ __('general.warehouse') }}
            </flux:sidebar.item>
        @endif

        @if (auth()->user()?->canAccessPanel(BusinessPanel::Sale))
            <flux:sidebar.item
                icon="shopping-bag"
                :href="route('sale.dashboard')"
                wire:navigate
                :current="request()->routeIs('sale.*')"
            >
                {{ __('general.sale') }}
            </flux:sidebar.item>
        @endif

        <flux:sidebar.item
            icon="circle-user"
            :href="route('user.dashboard')"
            wire:navigate
            :current="request()->routeIs('user.*')"
        >
            {{ __('general.user_panel') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="settings"
            :href="route('system.dashboard')"
            wire:navigate
            :current="request()->routeIs('system.*')"
        >
            {{ __('general.system_management') }}
        </flux:sidebar.item>
    </flux:sidebar.nav>
</div>
