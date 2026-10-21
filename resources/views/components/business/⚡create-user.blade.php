<?php

use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Livewire\Forms\BusinessUserForm;
use App\Models\Business;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public BusinessUserForm $form;

    public ?Business $business = null;

    public string $userSearch = '';

    public ?string $editingRole = null;

    #[On('panels.administrator.business.create-user.assign-data')]
    public function assignData(Business $business): void
    {
        $this->business = $business;
        $this->form->setBusiness($business);
        $this->userSearch = '';
        $this->editingRole = null;
        $this->resetValidation();
        unset($this->users);

        Flux::modal('business.create-user')->show();
    }

    public function updatedFormRoles(): void
    {
        $this->form->syncPermissionsFromRoles();

        if ($this->editingRole !== null && ! in_array($this->editingRole, $this->form->roles, true)) {
            $this->editingRole = null;
            Flux::modal('business.create-user.role-permissions')->close();
        }
    }

    public function openRolePermissions(string $role): void
    {
        if (! in_array($role, $this->form->roles, true)) {
            return;
        }

        $roleEnum = BusinessRole::tryFrom($role);

        if ($roleEnum === null || $roleEnum === BusinessRole::Owner) {
            return;
        }

        if (! isset($this->form->permissions[$role])) {
            $this->form->permissions[$role] = $roleEnum->defaultPermissions();
        }

        $this->editingRole = $role;

        Flux::modal('business.create-user.role-permissions')->show();
    }

    public function toggleRolePermission(string $permission): void
    {
        if ($this->editingRole === null) {
            return;
        }

        $role = BusinessRole::tryFrom($this->editingRole);

        if ($role === null || ! in_array($permission, $role->applicablePermissionValues(), true)) {
            return;
        }

        $current = $this->form->permissions[$this->editingRole] ?? [];

        if (in_array($permission, $current, true)) {
            $this->form->permissions[$this->editingRole] = array_values(array_filter(
                $current,
                fn (string $value): bool => $value !== $permission,
            ));

            return;
        }

        $this->form->permissions[$this->editingRole] = array_values(array_unique([
            ...$current,
            $permission,
        ]));
    }

    public function roleHasPermission(string $permission): bool
    {
        if ($this->editingRole === null) {
            return false;
        }

        return in_array($permission, $this->form->permissions[$this->editingRole] ?? [], true);
    }

    #[Computed]
    public function users(): Collection
    {
        $userId = $this->form->user_id;
        $search = $this->userSearch;
        $businessId = $this->business?->id;

        return User::query()
            ->when($search !== '' || $userId, function ($query) use ($userId, $search): void {
                $query->where(function ($query) use ($userId, $search): void {
                    if ($search !== '') {
                        $query->where('mobile', 'like', '%'.$search.'%');
                    }

                    if ($userId) {
                        $query->orWhere('id', $userId);
                    }
                });
            })
            ->when($businessId, function ($query) use ($userId, $businessId): void {
                $query->where(function ($query) use ($userId, $businessId): void {
                    $query->whereDoesntHave('memberships', function ($query) use ($businessId): void {
                        $query->where('business_id', $businessId);
                    });

                    if ($userId) {
                        $query->orWhere('id', $userId);
                    }
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * @return array<int, BusinessRole>
     */
    public function availableRoles(): array
    {
        return BusinessRole::assignable();
    }

    public function editingRoleEnum(): ?BusinessRole
    {
        return $this->editingRole !== null
            ? BusinessRole::tryFrom($this->editingRole)
            : null;
    }

    /**
     * @return array<string, list<BusinessPermission>>
     */
    public function editingPermissionGroups(): array
    {
        return $this->editingRoleEnum()?->applicablePermissionGroups() ?? [];
    }

    public function save(): void
    {
        $this->form->syncPermissionsFromRoles();
        $this->form->store();

        $this->reset('userSearch', 'business', 'editingRole');
        unset($this->users);

        $this->dispatch('panels.administrator.business.users.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_user_added'));
    }
};
?>

<div>
<flux:modal name="business.create-user" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_business_user') }}</flux:heading>
    </div>

    @if ($business)
        <flux:callout icon="box" variant="secondary" inline>
            {{ $business->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.user') }}</flux:label>
            <flux:select
                wire:model="form.user_id"
                variant="combobox"
                :filter="false"
                placeholder="{{ __('general.select_user') }}"
            >
                <x-slot name="input">
                    <flux:select.input
                        wire:model.live="userSearch"
                        placeholder="{{ __('general.search_user_placeholder') }}"
                    />
                </x-slot>

                @foreach ($this->users as $user)
                    <flux:select.option value="{{ $user->id }}" wire:key="business-user-{{ $user->id }}">
                        {{ $user->mobile }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.user_id" />
        </flux:field>

        <div class="space-y-3">
            <flux:checkbox.group
                wire:model.live="form.roles"
                label="{{ __('general.roles') }}"
                description="{{ __('general.business_roles_hint') }}"
            >
                @foreach ($this->availableRoles() as $role)
                    <flux:checkbox
                        wire:key="create-role-{{ $role->value }}"
                        value="{{ $role->value }}"
                        label="{{ $role->label() }}"
                        description="{{ $role->description() }}"
                    />
                @endforeach
            </flux:checkbox.group>
            <flux:error name="form.roles" />
        </div>

        @if ($form->roles !== [])
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('general.business_role_permissions') }}</flux:heading>
                <flux:text size="sm">{{ __('general.business_role_permissions_hint') }}</flux:text>

                <div class="space-y-2">
                    @foreach ($form->roles as $roleValue)
                        @php($role = App\Enums\Business\BusinessRole::tryFrom($roleValue))
                        @continue($role === null)

                        <div
                            class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                            wire:key="create-role-perm-row-{{ $roleValue }}"
                        >
                            <div class="min-w-0">
                                <flux:text class="font-medium">{{ $role->label() }}</flux:text>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ __('general.business_role_permission_count', ['count' => count($form->permissions[$roleValue] ?? [])]) }}
                                </flux:text>
                            </div>

                            <flux:button
                                type="button"
                                size="sm"
                                variant="filled"
                                color="blue"
                                icon="shield-check"
                                wire:click="openRolePermissions('{{ $roleValue }}')"
                            >
                                {{ __('general.access') }}
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>

<flux:modal name="business.create-user.role-permissions" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.business_permissions') }}</flux:heading>
        @if ($this->editingRoleEnum())
            <flux:text class="mt-1">{{ $this->editingRoleEnum()->label() }}</flux:text>
        @endif
    </div>

    <flux:text size="sm">{{ __('general.business_permissions_switch_hint') }}</flux:text>

    <div class="space-y-6">
        @forelse ($this->editingPermissionGroups() as $group => $permissions)
            <div class="space-y-3" wire:key="create-perm-group-{{ $group }}">
                <flux:heading size="sm">{{ __('general.business_permission_group_'.$group) }}</flux:heading>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($permissions as $permission)
                        <div class="flex items-center justify-between gap-4 py-3" wire:key="create-perm-{{ $permission->value }}">
                            <flux:text class="font-medium">{{ $permission->label() }}</flux:text>
                            <flux:switch
                                :checked="$this->roleHasPermission($permission->value)"
                                wire:click="toggleRolePermission('{{ $permission->value }}')"
                            />
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:text>{{ __('general.no_permissions') }}</flux:text>
        @endforelse
    </div>
</flux:modal>
</div>
