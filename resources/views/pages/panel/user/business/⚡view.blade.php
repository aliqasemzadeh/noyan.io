<?php

use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    public Business $business;

    public function mount(Business $business): void
    {
        abort_unless(Auth::user()?->belongsToBusiness($business), 403);

        $this->business = $business;
    }

    public function formatCreatedAt(): string
    {
        return Jalalian::fromDateTime($this->business->created_at)->format('Y/m/d H:i');
    }
};
?>

<div class="space-y-6">
    <x-slot name="title">{{ $business->name }} - {{ __('general.app_name') }}</x-slot>

    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.index')" wire:navigate>
                {{ __('general.my_businesses') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $business->name }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-3">
            <flux:heading size="xl" level="1">
                {{ $business->name }}
            </flux:heading>

            <flux:button
                variant="primary"
                color="blue"
                icon="pencil"
                :href="route('user.businesses.edit', $business)"
                wire:navigate
            >
                {{ __('general.edit_business') }}
            </flux:button>
        </div>
    </div>

    <flux:card>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.slug') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $business->slug }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.created_at') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $this->formatCreatedAt() }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.business_type') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $business->type?->label() ?? '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.business_category') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $business->category?->label() ?? '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.is_active') }}</flux:text>
                <div class="mt-1">
                    <flux:badge :color="$business->is_active ? 'teal' : 'zinc'">
                        {{ $business->is_active ? __('general.active') : __('general.inactive') }}
                    </flux:badge>
                </div>
            </div>
        </div>
    </flux:card>
</div>
