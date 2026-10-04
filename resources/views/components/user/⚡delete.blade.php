<?php

use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?User $user = null;

    #[On('panels.administrator.user.delete.assign-data')]
    public function assignData(User $user): void
    {
        abort_unless(auth()->user()?->can('user_delete'), 403);

        $this->user = $user;

        Flux::modal('user.delete')->show();
    }

    public function delete(): void
    {
        abort_unless(auth()->user()?->can('user_delete'), 403);

        if ($this->user) {
            $this->user->delete();
        }

        $this->dispatch('panels.administrator.user.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.user_deleted'));
    }
};
?>

<flux:modal name="user.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_warning_message') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($user)
            <flux:callout icon="box" variant="secondary" inline>
                {{ $user->mobile }}
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