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
        return Auth::user()->businesses()->orderBy('name')->get();
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
    }

    public function logout(): void
    {
        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        Flux::toast(__('general.logout'));

        $this->redirect(route('login'), navigate: true);
    }
};
?>

<div>
    @if ($variant === 'sidebar')
        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile :name="auth()->user()->mobile" />

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

                <flux:menu.item icon="arrow-right-start-on-rectangle" wire:click="logout">
                    {{ __('general.logout') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @else
        <flux:dropdown position="top" align="end">
            <flux:profile :name="auth()->user()->mobile" />

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

                <flux:menu.item icon="arrow-right-start-on-rectangle" wire:click="logout">
                    {{ __('general.logout') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
