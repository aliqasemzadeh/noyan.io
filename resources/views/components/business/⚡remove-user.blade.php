<?php

use App\Enums\BusinessRole;
use App\Models\BusinessUser;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?BusinessUser $membership = null;

    #[On('panels.administrator.business.user.remove.assign-data')]
    public function assignData(BusinessUser $membership): void
    {
        $this->membership = $membership->load(['user', 'business']);

        Flux::modal('business.user.remove')->show();
    }

    public function remove(): void
    {
        if ($this->membership === null) {
            return;
        }

        if ($this->membership->role === BusinessRole::Owner) {
            Flux::toast(__('general.cannot_remove_business_owner'), variant: 'danger');

            return;
        }

        $this->membership->delete();

        $this->dispatch('panels.administrator.business.users.table');
        $this->dispatch('panels.administrator.business.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_user_removed'));
    }
};
?>

<flux:modal name="business.user.remove" class="min-w-[22rem]">
    <form wire:submit="remove" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.remove_business_user_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($membership?->user)
            <flux:callout icon="user" variant="secondary" inline>
                {{ $membership->user->mobile }}
            </flux:callout>
        @endif

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="danger">{{ __('general.delete') }}</flux:button>
        </div>
    </form>
</flux:modal>
