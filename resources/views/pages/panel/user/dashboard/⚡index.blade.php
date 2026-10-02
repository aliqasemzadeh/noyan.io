<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('User settings')]
class extends Component
{
    //
};
?>

<div class="space-y-6">
    <x-slot name="title">{{ __('general.user_settings') }} - {{ config('app.name') }}</x-slot>

    <div>
        <flux:heading size="xl" level="1">
            {{ __('general.user_settings') }}
        </flux:heading>

        <flux:text class="mt-2 text-base">
            {{ __('general.user_dashboard_placeholder') }}
        </flux:text>
    </div>
</div>
