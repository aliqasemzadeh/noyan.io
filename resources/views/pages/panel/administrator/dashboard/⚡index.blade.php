<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('System')]
class extends Component
{
    //
};
?>

<div class="space-y-6">
    <x-slot name="title">{{ __('general.system_management') }} - {{ config('app.name') }}</x-slot>

    <div>
        <flux:heading size="xl" level="1">
            {{ __('general.system_management') }}
        </flux:heading>

        <flux:text class="mt-2 text-base">
            {{ __('general.system_dashboard_placeholder') }}
        </flux:text>
    </div>
</div>
