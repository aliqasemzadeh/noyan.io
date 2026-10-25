<?php

use App\Models\Business;
use App\Support\Impersonation;
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
        Impersonation::stop();

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
            (bool) preg_match('#^/sale/parties/[^/]+$#', $path) => route('sale.parties.index'),
            (bool) preg_match('#^/sale/invoices/[^/]+#', $path) => route('sale.invoices.index'),
            (bool) preg_match('#^/sale/quotes/[^/]+#', $path) => route('sale.quotes.index'),
            (bool) preg_match('#^/user/businesses/[^/]+(/edit)?$#', $path) => route('user.businesses.index'),
            $previous !== '' && ! str_contains($previous, '/livewire/') => $previous,
            default => route('dashboard'),
        };
    }
};
?>

@php
    $authUser = auth()->user();
    $currentBusiness = $authUser->currentBusiness;
    $brandName = $currentBusiness?->name ?? __('general.app_name');
    $canSwitchBusiness = $this->businesses->count() > 1;
    $profileName = $authUser->fullName()
        ?? $currentBusiness?->name
        ?? $authUser->mobile;
    $profileAvatar = $authUser->avatarUrl();
@endphp

<div @class([
    'min-w-0 flex-1 flex items-center justify-end' => $variant === 'mobile-brand',
    'w-full' => $variant === 'sidebar',
])>
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

    @if ($variant === 'mobile-brand')
        @if ($canSwitchBusiness)
            <flux:dropdown position="bottom" align="start" class="max-w-full">
                <button
                    type="button"
                    class="group flex max-w-full min-w-0 items-center gap-1 rounded-lg px-1.5 py-1 text-start hover:bg-zinc-800/5 dark:hover:bg-white/10"
                >
                    <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">
                        {{ $brandName }}
                    </span>
                    <flux:icon
                        name="chevron-down"
                        variant="micro"
                        class="size-4 shrink-0 text-zinc-400 group-hover:text-zinc-800 dark:text-white/80 dark:group-hover:text-white"
                    />
                </button>

                <flux:menu>
                    <flux:menu.radio.group>
                        @foreach ($this->businesses as $business)
                            <flux:menu.radio
                                wire:key="mobile-brand-business-{{ $business->id }}"
                                :checked="auth()->user()->current_business_id === $business->id"
                                wire:click="switchBusiness({{ $business->id }})"
                            >
                                {{ $business->name }}
                            </flux:menu.radio>
                        @endforeach
                    </flux:menu.radio.group>
                </flux:menu>
            </flux:dropdown>
        @else
            <span class="block truncate px-1.5 text-sm font-medium text-zinc-800 dark:text-zinc-100">
                {{ $brandName }}
            </span>
        @endif
    @else
        <flux:dropdown position="top" align="start" class="w-full">
            <flux:sidebar.profile
                :name="$profileName"
                :avatar="$profileAvatar"
            />

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
    @endif
</div>
