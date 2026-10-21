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

<x-slot name="title">{{ __('general.sale') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.sale') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('general.sale') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.sale_dashboard_placeholder') }}</flux:text>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceView))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.invoices') }}</flux:heading>
                <flux:button variant="primary" color="teal" class="w-full" :href="route('sale.invoices.index')" wire:navigate>
                    {{ __('general.invoices') }}
                </flux:button>
            </flux:card>
        @endif

        @if (auth()->user()?->canInBusiness(BusinessPermission::QuoteView))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.quotes') }}</flux:heading>
                <flux:button variant="primary" color="sky" class="w-full" :href="route('sale.quotes.index')" wire:navigate>
                    {{ __('general.quotes') }}
                </flux:button>
            </flux:card>
        @endif

        @if (auth()->user()?->canInBusiness(BusinessPermission::PartyView))
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('general.parties') }}</flux:heading>
                <flux:button variant="primary" color="zinc" class="w-full" :href="route('sale.parties.index')" wire:navigate>
                    {{ __('general.parties') }}
                </flux:button>
            </flux:card>
        @endif
    </div>
</div>
