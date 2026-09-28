<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Dashboard')]
class extends Component
{
    public function logout(): void
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        Flux::toast(__('general.logout'));

        $this->redirect(route('login'), navigate: true);
    }
};
?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <flux:main>
        <div class="mx-auto max-w-3xl space-y-6 py-10">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="xl">{{ __('general.dashboard') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('general.welcome_user') }} — {{ auth()->user()->mobile }}
                    </flux:text>
                </div>

                <flux:button variant="danger" icon="arrow-right-start-on-rectangle" wire:click="logout">
                    {{ __('general.logout') }}
                </flux:button>
            </div>
        </div>
    </flux:main>
</div>
