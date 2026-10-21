<?php

use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Livewire\Forms\BusinessUserForm;
use App\Models\BusinessUser;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public BusinessUserForm $form;

    public ?BusinessUser $membership = null;

    #[On('panels.administrator.business.edit-user.assign-data')]
    public function assignData(BusinessUser $membership): void
    {
        abort_if($membership->role === BusinessRole::Owner, 403);

        $this->membership = $membership;
        $this->form->setMembership($membership);
        $this->resetValidation();

        Flux::modal('business.edit-user')->show();
    }

    public function updatedFormRole(): void
    {
        $this->form->syncPermissionsFromRole();
    }

    /**
     * @return array<int, BusinessRole>
     */
    public function availableRoles(): array
    {
        return array_values(array_filter(
            BusinessRole::cases(),
            fn (BusinessRole $role): bool => $role !== BusinessRole::Owner,
        ));
    }

    /**
     * @return array<string, list<BusinessPermission>>
     */
    public function permissionGroups(): array
    {
        return BusinessPermission::grouped();
    }

    public function save(): void
    {
        $this->form->update();

        $this->reset('membership');

        $this->dispatch('panels.administrator.business.users.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_user_updated'));
    }
};
?>

<flux:modal name="business.edit-user" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_business_user') }}</flux:heading>
    </div>

    @if ($membership)
        <flux:callout icon="box" variant="secondary" inline>
            <span dir="ltr">{{ $membership->user?->mobile }}</span>
            — {{ $membership->business?->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.role') }}</flux:label>
            <flux:select wire:model.live="form.role" searchable variant="listbox">
                @foreach ($this->availableRoles() as $role)
                    <flux:select.option value="{{ $role->value }}">
                        {{ __('general.business_role_'.$role->value) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.role" />
        </flux:field>

        <div class="space-y-4">
            <div>
                <flux:heading size="sm">{{ __('general.business_permissions') }}</flux:heading>
                <flux:text size="sm" class="mt-1">{{ __('general.business_permissions_hint') }}</flux:text>
            </div>

            @foreach ($this->permissionGroups() as $group => $permissions)
                <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('general.business_permission_group_'.$group) }}</flux:heading>
                    <div class="grid gap-2">
                        @foreach ($permissions as $permission)
                            <flux:field variant="inline" wire:key="edit-perm-{{ $permission->value }}">
                                <flux:checkbox
                                    wire:model="form.permissions"
                                    value="{{ $permission->value }}"
                                />
                                <flux:label>{{ $permission->label() }}</flux:label>
                            </flux:field>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <flux:error name="form.permissions" />
        </div>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
