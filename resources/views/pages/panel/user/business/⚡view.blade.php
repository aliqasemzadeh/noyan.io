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

<x-slot name="title">{{ $business->name }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
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
    </div>

    <flux:card>
        <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:items-start sm:text-start">
            <div class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-zinc-50 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">
                @if ($business->logoUrl())
                    <img
                        src="{{ $business->logoUrl() }}"
                        alt="{{ $business->name }}"
                        class="size-full object-contain p-2"
                    />
                @else
                    <flux:icon.building-storefront class="size-10 text-zinc-400" />
                @endif
            </div>

            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-col items-center gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <flux:heading size="xl" level="1" class="truncate">
                        {{ $business->name }}
                    </flux:heading>
                    <flux:badge :color="$business->is_active ? 'teal' : 'zinc'">
                        {{ $business->is_active ? __('general.active') : __('general.inactive') }}
                    </flux:badge>
                </div>
                <flux:text class="text-sm text-zinc-500" dir="ltr">
                    {{ $business->slug }}
                </flux:text>
            </div>

            <div class="shrink-0">
                @if (auth()->user()->owns($business))
                    <flux:dropdown>
                        <flux:button icon:trailing="chevron-down">
                            {{ __('general.options') }}
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item
                                icon="users"
                                :href="route('user.businesses.users', $business)"
                                wire:navigate
                            >
                                {{ __('general.business_users') }}
                            </flux:menu.item>
                            <flux:menu.item
                                icon="history"
                                :href="route('user.businesses.activity', $business)"
                                wire:navigate
                            >
                                {{ __('general.business_activity_log') }}
                            </flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item
                                icon="pencil"
                                :href="route('user.businesses.edit', $business)"
                                wire:navigate
                            >
                                {{ __('general.edit_business') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @else
                    <flux:button
                        variant="primary"
                        color="blue"
                        icon="pencil"
                        :href="route('user.businesses.edit', $business)"
                        wire:navigate
                    >
                        {{ __('general.edit_business') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('general.business_tab_general') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.business_type') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $business->type?->label() ?? '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.business_category') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $business->category?->label() ?? '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.created_at') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $this->formatCreatedAt() }}</flux:heading>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('general.contact_info') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.legal_name') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $business->legal_name ?: '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.phone') }}</flux:text>
                <flux:heading size="sm" class="mt-1" dir="ltr">{{ $business->phone ?: '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.national_id') }}</flux:text>
                <flux:heading size="sm" class="mt-1" dir="ltr">{{ $business->national_id ?: '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.economic_code') }}</flux:text>
                <flux:heading size="sm" class="mt-1" dir="ltr">{{ $business->economic_code ?: '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.postal_code') }}</flux:text>
                <flux:heading size="sm" class="mt-1" dir="ltr">{{ $business->postal_code ?: '—' }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('general.tax_memory_id') }}</flux:text>
                <flux:heading size="sm" class="mt-1" dir="ltr">{{ $business->tax_memory_id ?: '—' }}</flux:heading>
            </div>
            <div class="sm:col-span-2">
                <flux:text class="text-sm text-zinc-500">{{ __('general.address') }}</flux:text>
                <flux:heading size="sm" class="mt-1">{{ $business->address ?: '—' }}</flux:heading>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('general.invoice_colors') }}</flux:heading>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <span
                    class="size-10 rounded-full ring-1 ring-zinc-200 dark:ring-zinc-700"
                    style="background-color: {{ $business->invoicePrimaryColor() }}"
                    title="{{ $business->invoicePrimaryColor() }}"
                ></span>
                <div>
                    <flux:text class="text-sm text-zinc-500">{{ __('general.invoice_primary_color') }}</flux:text>
                    <flux:heading size="sm" class="mt-0.5" dir="ltr">{{ $business->invoicePrimaryColor() }}</flux:heading>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="size-10 rounded-full ring-1 ring-zinc-200 dark:ring-zinc-700"
                    style="background-color: {{ $business->invoiceSecondaryColor() }}"
                    title="{{ $business->invoiceSecondaryColor() }}"
                ></span>
                <div>
                    <flux:text class="text-sm text-zinc-500">{{ __('general.invoice_secondary_color') }}</flux:text>
                    <flux:heading size="sm" class="mt-0.5" dir="ltr">{{ $business->invoiceSecondaryColor() }}</flux:heading>
                </div>
            </div>
        </div>
    </flux:card>
</div>
