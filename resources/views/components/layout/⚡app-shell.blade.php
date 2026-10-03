<?php

use App\Models\Business;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $variant = 'sidebar';

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Business>
     */
    #[Computed]
    public function businesses()
    {
        return Auth::user()->cachedBusinesses();
    }

    public function switchBusiness(int $businessId): void
    {
        $business = Business::query()->findOrFail($businessId);

        try {
            Auth::user()->switchBusiness($business);
        } catch (AuthorizationException) {
            Flux::toast(__('general.business_switch_denied'), variant: 'danger');

            return;
        }

        unset($this->businesses);

        Flux::toast(__('general.business_switched'));

        $this->redirect($this->urlAfterBusinessSwitch());
    }

    public function logout(): void
    {
        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        Flux::toast(__('general.logout'));

        $this->redirect(route('login'), navigate: true);
    }

    private function urlAfterBusinessSwitch(): string
    {
        $previous = url()->previous();
        $path = rtrim((string) parse_url($previous, PHP_URL_PATH), '/') ?: '/';

        return match (true) {
            (bool) preg_match('#^/accounting/accounts/[^/]+$#', $path) => route('accounting.accounts.index'),
            (bool) preg_match('#^/accounting/parties/[^/]+$#', $path) => route('accounting.parties.index'),
            (bool) preg_match('#^/user/businesses/[^/]+(/edit)?$#', $path) => route('user.businesses.index'),
            $previous !== '' && ! str_contains($previous, '/livewire/') => $previous,
            default => route('dashboard'),
        };
    }
};
?>

@php
    $profileName = auth()->user()->currentBusiness?->name ?? auth()->user()->mobile;
@endphp

<div>
    <div
        wire:loading.flex
        wire:target="switchBusiness"
        class="fixed inset-0 z-[100] items-center justify-center bg-zinc-50/80 dark:bg-zinc-900/80 backdrop-blur-sm"
    >
        <div class="flex flex-col items-center gap-3">
            <flux:icon name="loader-circle" class="size-8 animate-spin text-zinc-700 dark:text-zinc-200" />
            <flux:text>{{ __('general.switching_business') }}</flux:text>
        </div>
    </div>

    @if ($variant === 'sidebar')
        <div class="w-full max-lg:hidden">
            <flux:dropdown position="top" align="start" class="w-full">
                <flux:sidebar.profile :name="$profileName" />

                <flux:menu>
                    @if ($this->businesses->isNotEmpty())
                        <flux:menu.radio.group>
                            @foreach ($this->businesses as $business)
                                <flux:menu.radio
                                    wire:key="sidebar-business-{{ $business->id }}"
                                    :checked="auth()->user()->current_business_id === $business->id"
                                    wire:click="switchBusiness({{ $business->id }})"
                                >
                                    {{ $business->name }}
                                </flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>

                        <flux:menu.separator />
                    @endif

                    <flux:menu.item icon="plus" :href="route('user.businesses.create')" wire:navigate>
                        {{ __('general.add_business') }}
                    </flux:menu.item>

                    <flux:menu.separator />

                    <flux:menu.item icon="arrow-right-start-on-rectangle" wire:click="logout">
                        {{ __('general.logout') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    @else
        <flux:dropdown position="top" align="start">
            <flux:profile avatar:name="{{ $profileName }}" />

            <flux:menu>
                @if ($this->businesses->isNotEmpty())
                    <flux:menu.radio.group>
                        @foreach ($this->businesses as $business)
                            <flux:menu.radio
                                wire:key="header-business-{{ $business->id }}"
                                :checked="auth()->user()->current_business_id === $business->id"
                                wire:click="switchBusiness({{ $business->id }})"
                            >
                                {{ $business->name }}
                            </flux:menu.radio>
                        @endforeach
                    </flux:menu.radio.group>

                    <flux:menu.separator />
                @endif

                <flux:menu.item icon="plus" :href="route('user.businesses.create')" wire:navigate>
                    {{ __('general.add_business') }}
                </flux:menu.item>

                <flux:menu.separator />

                <flux:menu.item icon="arrow-right-start-on-rectangle" wire:click="logout">
                    {{ __('general.logout') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
