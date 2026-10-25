<?php

use App\Enums\Business\BusinessPermission;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
    }
};
?>

<x-slot name="title">{{ __('general.warehouse') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.warehouse') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('general.warehouse') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.warehouse_dashboard_hint') }}</flux:text>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        @if (auth()->user()?->canInBusiness(BusinessPermission::WarehouseView))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.warehouses') }}</flux:heading>
                <flux:button variant="primary" color="teal" class="w-full" :href="route('warehouse.warehouses.index')" wire:navigate>
                    {{ __('general.warehouses') }}
                </flux:button>
            </flux:card>
        @endif

        @if (auth()->user()?->canInBusiness(BusinessPermission::StockMovementView))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.inventory_stocks') }}</flux:heading>
                <flux:button variant="primary" color="sky" class="w-full" :href="route('warehouse.stocks.index')" wire:navigate>
                    {{ __('general.inventory_stocks') }}
                </flux:button>
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.stock_movements') }}</flux:heading>
                <flux:button variant="primary" color="zinc" class="w-full" :href="route('warehouse.movements.index')" wire:navigate>
                    {{ __('general.stock_movements') }}
                </flux:button>
            </flux:card>
        @endif

        @if (auth()->user()?->canInBusiness(BusinessPermission::StockConvert))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.stock_conversions') }}</flux:heading>
                <flux:button variant="primary" color="orange" class="w-full" :href="route('warehouse.conversions.index')" wire:navigate>
                    {{ __('general.stock_conversions') }}
                </flux:button>
            </flux:card>
        @endif
    </div>
</div>
