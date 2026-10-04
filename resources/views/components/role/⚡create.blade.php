<?php

use App\Livewire\Forms\RoleForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public RoleForm $form;

    public function save(): void
    {
        abort_unless(auth()->user()?->can('role_create'), 403);

        $role = $this->form->store();

        $this->dispatch('panels.administrator.role.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.role_created'));
    }
};
?>

<flux:modal name="role.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_role') }}</flux:heading>
    </div>

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
