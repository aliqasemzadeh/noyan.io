<?php

use App\Models\User;
use App\Support\AdministratorPermissions;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component
{
    use WithPagination;

    public Role $role;

    public string $permissionSearch = '';

    public string $userSearch = '';

    public function mount(Role $role): void
    {
        $this->role = $role->load('permissions');
    }

    public function updatedUserSearch(): void
    {
        $this->resetPage();
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

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->userSearch !== '', function ($query): void {
                $query->where('mobile', 'like', '%'.$this->userSearch.'%');
            })
            ->latest()
            ->paginate(15);
    }

    public function togglePermission(int $permissionId): void
    {
        abort_unless(auth()->user()?->can('role_access'), 403);

        $permission = Permission::query()->findOrFail($permissionId);

        if ($this->role->hasPermissionTo($permission)) {
            $this->role->revokePermissionTo($permission);
            Flux::toast(__('general.role_permission_revoked'));
        } else {
            $this->role->givePermissionTo($permission);
            Flux::toast(__('general.role_permission_granted'));
        }

        $this->role->refresh()->load('permissions');
        unset($this->permissionGroups);
    }

    public function toggleUser(int $userId): void
    {
        abort_unless(auth()->user()?->can('role_access'), 403);

        $user = User::query()->findOrFail($userId);

        if ($user->hasRole($this->role)) {
            $user->removeRole($this->role);
            Flux::toast(__('general.role_removed'));
        } else {
            $user->assignRole($this->role);
            Flux::toast(__('general.role_assigned'));
        }

        unset($this->users);
    }

    public function permissionLabel(Permission $permission): string
    {
        return AdministratorPermissions::label($permission->name);
    }

    public function groupLabel(string $group): string
    {
        return AdministratorPermissions::groupLabel($group);
    }

    public function userHasRole(User $user): bool
    {
        return $user->hasRole($this->role);
    }
};
?>

<x-slot name="title">{{ __('general.access') }} - {{ $role->name }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('system.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('system.roles.index')" wire:navigate>
                {{ __('general.roles') }}
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

        <flux:callout icon="shield-check" variant="secondary" class="mt-4" inline>
            {{ $role->name }}
        </flux:callout>
    </div>

    <flux:card>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="lg">{{ __('general.access_role_permissions') }}</flux:heading>
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
                <div wire:key="role-permission-group-{{ $group }}">
                    <flux:heading size="sm" class="mb-2">{{ $this->groupLabel($group) }}</flux:heading>
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($permissions as $permission)
                            <div class="flex items-center justify-between gap-4 py-3" wire:key="role-permission-{{ $permission->id }}">
                                <div class="min-w-0">
                                    <flux:text class="font-medium">{{ $this->permissionLabel($permission) }}</flux:text>
                                    <flux:text class="text-sm text-zinc-500" dir="ltr">{{ $permission->name }}</flux:text>
                                </div>
                                <flux:field variant="inline">
                                    <flux:switch
                                        :checked="$role->hasPermissionTo($permission)"
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

    <flux:card>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="lg">{{ __('general.access_role_users') }}</flux:heading>
            <flux:input
                wire:model.live.debounce.300ms="userSearch"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
                class="sm:max-w-xs"
            />
        </div>

        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.access') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>{{ $user->mobile }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:field variant="inline">
                                <flux:switch
                                    :checked="$this->userHasRole($user)"
                                    wire:click="toggleUser({{ $user->id }})"
                                />
                            </flux:field>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2">
                            {{ __('general.no_users') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
