<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-slot name="title">{{ __('general.dashboard') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
<div>
        <flux:heading size="xl" level="1">
            {{ __('general.welcome_user') }}
        </flux:heading>

        <flux:text class="mt-2 text-base">
            {{ auth()->user()->mobile }}
            @if (auth()->user()->currentBusiness)
                — {{ auth()->user()->currentBusiness->name }}
            @endif
        </flux:text>
    </div>

    <flux:separator variant="subtle" />

    @if (! auth()->user()->currentBusiness)
        <flux:callout icon="building-office-2" variant="secondary" inline>
            {{ __('general.no_business_yet') }}
        </flux:callout>
    @endif
</div>
