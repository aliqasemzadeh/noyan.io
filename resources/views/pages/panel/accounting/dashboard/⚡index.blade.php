<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="space-y-6">
    <x-slot name="title">{{ __('general.accounting') }} - {{ __('general.app_name') }}</x-slot>

    <div>
        <flux:heading size="xl" level="1">
            {{ __('general.accounting') }}
        </flux:heading>

        <flux:text class="mt-2 text-base">
            {{ __('general.accounting_dashboard_placeholder') }}
        </flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('accounting.accounts.index') }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start gap-3">
                    <flux:icon.wallet class="size-6 text-teal-600 dark:text-teal-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.cash_and_bank_accounts') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.treasury') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>

        <a href="{{ route('accounting.currencies.index') }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start gap-3">
                    <flux:icon.coins class="size-6 text-amber-600 dark:text-amber-400" />
                    <div>
                        <flux:heading size="lg">{{ __('general.business_currencies') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.currencies') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </a>
    </div>
</div>
