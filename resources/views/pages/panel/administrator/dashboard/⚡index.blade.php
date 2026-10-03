<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-slot name="title">{{ __('general.system_management') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
<div>
        <flux:heading size="xl" level="1">
            {{ __('general.system_management') }}
        </flux:heading>

        <flux:text class="mt-2 text-base">
            {{ __('general.system_dashboard_placeholder') }}
        </flux:text>
    </div>
</div>
