<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Users')]
class extends Component
{
    //
};
?>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.users') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1" class="mt-4">
            {{ __('general.users') }}
        </flux:heading>

        <flux:text class="mt-2">
            {{ __('general.users_page_placeholder') }}
        </flux:text>
    </div>

    <flux:separator variant="subtle" />

    <flux:card>
        <flux:callout icon="users" variant="secondary" inline>
            {{ __('general.users_page_placeholder') }}
        </flux:callout>
    </flux:card>
</div>
