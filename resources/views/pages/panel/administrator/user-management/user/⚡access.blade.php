<?php

use App\Models\User;
use App\Support\AdministratorPermissions;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component
{
    public User $user;

    public string $roleSearch = '';

    public string $permissionSearch = '';

    public function mount(User $user): void
    {
        $this->user = $user->load(['roles', 'permissions']);
    }

    #[Computed]
    public function roles(): \Illuminate\Support\Collection
    {
        $search = mb_strtolower(trim($this->roleSearch));

        return Role::query()
            ->orderBy('name')
            ->get()
            ->when($search !== '', fn ($roles) => $roles->filter(
                fn (Role $role) => str_contains(mb_strtolower($role->name), $search)
            )->values());
    }

    /**
     * @return array<string, list<Permission>>
     */
    #[Computed]
    public function permissionGroups(): array
    {
        $search = mb_strtolower(trim($this->permissionSearch));

        $permissions = Permission::query()
            ->whereIn('name', AdministratorPermissions::names())
            ->orderBy('name')
            ->get()
            ->when($search !== '', fn ($items) => $items->filter(function (Permission $permission) use ($search): bool {
                return str_contains(mb_strtolower($permission->name), $search)
                    || str_contains(mb_strtolower(AdministratorPermissions::label($permission->name)), $search);
            })->values());

        $groups = [];

        foreach ($permissions as $permission) {
            $prefix = strstr($permission->name, '_', true) ?: $permission->name;
            $groups[$prefix][] = $permission;
        }

        return $groups;
    }

    public function toggleRole(int $roleId): void
    {
        abort_unless(auth()->user()?->can('user_access'), 403);

        $role = Role::query()->findOrFail($roleId);

        if ($this->user->hasRole($role)) {
            $this->user->removeRole($role);
            Flux::toast(__('general.role_removed'));
        } else {
            $this->user->assignRole($role);
            Flux::toast(__('general.role_assigned'));
        }

        $this->user->refresh()->load(['roles', 'permissions']);
        unset($this->roles);
    }

    public function togglePermission(int $permissionId): void
    {
        abort_unless(auth()->user()?->can('user_access'), 403);

        $permission = Permission::query()->findOrFail($permissionId);

        if ($this->user->hasDirectPermission($permission)) {
            $this->user->revokePermissionTo($permission);
            Flux::toast(__('general.permission_revoked'));
        } else {
            $this->user->givePermissionTo($permission);
            Flux::toast(__('general.permission_granted'));
        }

        $this->user->refresh()->load(['roles', 'permissions']);
        unset($this->permissionGroups);
    }

    public function permissionLabel(Permission $permission): string
    {
        return AdministratorPermissions::label($permission->name);
    }

    public function groupLabel(string $group): string
    {
        return AdministratorPermissions::groupLabel($group);
    }
};
?>

<x-slot name="title">{{ __('general.access') }} - {{ $user->mobile }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('system.users.index')" wire:navigate>
                {{ __('general.users') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.access') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.access') }}
            </flux:heading>
        </div>

        <flux:callout icon="user" variant="secondary" class="mt-4" inline>
            {{ $user->mobile }}
        </flux:callout>
    </div>

    <flux:card>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="lg">{{ __('general.access_roles') }}</flux:heading>
            <flux:input
                wire:model.live.debounce.300ms="roleSearch"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
                class="sm:max-w-xs"
            />
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($this->roles as $role)
                <div class="flex items-center justify-between gap-4 py-3" wire:key="user-role-{{ $role->id }}">
                    <div>
                        <flux:text class="font-medium">{{ $role->name }}</flux:text>
                    </div>
                    <flux:field variant="inline">
                        <flux:switch
                            :checked="$user->hasRole($role)"
                            wire:click="toggleRole({{ $role->id }})"
                        />
                    </flux:field>
                </div>
            @empty
                <flux:text>{{ __('general.no_roles') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    <flux:card>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="lg">{{ __('general.access_permissions') }}</flux:heading>
            <flux:input
                wire:model.live.debounce.300ms="permissionSearch"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
                class="sm:max-w-xs"
            />
        </div>

        <div class="space-y-6">
            @forelse ($this->permissionGroups as $group => $permissions)
                <div wire:key="user-permission-group-{{ $group }}">
                    <flux:heading size="sm" class="mb-2">{{ $this->groupLabel($group) }}</flux:heading>
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($permissions as $permission)
                            <div class="flex items-center justify-between gap-4 py-3" wire:key="user-permission-{{ $permission->id }}">
                                <div class="min-w-0">
                                    <flux:text class="font-medium">{{ $this->permissionLabel($permission) }}</flux:text>
                                    <flux:text class="text-sm text-zinc-500" dir="ltr">{{ $permission->name }}</flux:text>
                                    @if ($user->hasPermissionTo($permission) && ! $user->hasDirectPermission($permission))
                                        <flux:badge size="sm" color="zinc" class="mt-1">{{ __('general.inherited_from_role') }}</flux:badge>
                                    @elseif ($user->hasDirectPermission($permission))
                                        <flux:badge size="sm" color="teal" class="mt-1">{{ __('general.direct_permission') }}</flux:badge>
                                    @endif
                                </div>
                                <flux:field variant="inline">
                                    <flux:switch
                                        :checked="$user->hasDirectPermission($permission)"
                                        wire:click="togglePermission({{ $permission->id }})"
                                    />
                                </flux:field>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <flux:text>{{ __('general.no_permissions') }}</flux:text>
            @endforelse
        </div>
    </flux:card>
</div>
