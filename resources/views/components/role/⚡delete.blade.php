<?php

use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component
{
    public ?Role $role = null;

    #[On('panels.administrator.role.delete.assign-data')]
    public function assignData(Role $role): void
    {
        abort_unless(auth()->user()?->can('role_delete'), 403);

        if ($role->name === 'administrator') {
            Flux::toast(__('general.administrator_role_locked'), variant: 'warning');

            return;
        }

        $this->role = $role;

        Flux::modal('role.delete')->show();
    }

    public function delete(): void
    {
        abort_unless(auth()->user()?->can('role_delete'), 403);

        if ($this->role === null || $this->role->name === 'administrator') {
            abort(403);
        }

        $this->role->delete();

        $this->dispatch('panels.administrator.role.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.role_deleted'));
    }
};
?>

<flux:modal name="role.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_role_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($role)
            <flux:callout icon="shield-check" variant="secondary" inline>
                {{ $role->name }}
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
