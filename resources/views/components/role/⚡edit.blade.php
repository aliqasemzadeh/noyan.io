<?php

use App\Livewire\Forms\RoleForm;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component
{
    public RoleForm $form;

    public ?Role $role = null;

    #[On('panels.administrator.role.edit.assign-data')]
    public function assignData(Role $role): void
    {
        abort_unless(auth()->user()?->can('role_edit'), 403);

        if ($role->name === 'administrator') {
            Flux::toast(__('general.administrator_role_locked'), variant: 'warning');

            return;
        }

        $this->role = $role;
        $this->form->setModel($role);
        $this->resetValidation();

        Flux::modal('role.edit')->show();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('role_edit'), 403);

        $this->form->update();

        $this->dispatch('panels.administrator.role.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.role_updated'));
    }
};
?>

<flux:modal name="role.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_role') }}</flux:heading>
    </div>

    @if ($role)
        <flux:callout icon="shield-check" variant="secondary" inline>
            {{ $role->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.role_name') }}</flux:label>
            <flux:input
                wire:model="form.name"
                placeholder="{{ __('general.role_name_placeholder') }}"
                dir="ltr"
            />
            <flux:error name="form.name" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
