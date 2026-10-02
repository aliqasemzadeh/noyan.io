<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Accounting')]
class extends Component
{
    //
};
?>

<div class="space-y-6">
    <x-slot name="title">{{ __('general.accounting') }} - {{ config('app.name') }}</x-slot>

    <div>
        <flux:heading size="xl" level="1">
            {{ __('general.accounting') }}
        </flux:heading>

        <flux:text class="mt-2 text-base">
            {{ __('general.accounting_dashboard_placeholder') }}
        </flux:text>
    </div>
</div>
