<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.administrator.role.index.table')]
    public function refreshTable(): void
    {
        unset($this->roles);
    }

    #[Computed]
    public function roles(): LengthAwarePaginator
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search !== '', function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->orderBy('name')
            ->paginate(15);
    }

    public function formatCreatedAt(Role $role): string
    {
        return Jalalian::fromDateTime($role->created_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.roles') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('system.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.user_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.roles') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.roles') }}
            </flux:heading>

            @can('role_create')
                <flux:modal.trigger name="role.create">
                    <flux:button variant="primary" color="teal" icon="plus">
                        {{ __('general.create_role') }}
                    </flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    <flux:card>
        <div class="mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />
        </div>

        <flux:table :paginate="$this->roles">
            <flux:table.columns>
                <flux:table.column>{{ __('general.role_name') }}</flux:table.column>
                <flux:table.column>{{ __('general.permissions') }}</flux:table.column>
                <flux:table.column>{{ __('general.users') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->roles as $role)
                    <flux:table.row :key="$role->id">
                        <flux:table.cell>{{ $role->name }}</flux:table.cell>
                        <flux:table.cell>{{ $role->permissions_count }}</flux:table.cell>
                        <flux:table.cell>{{ $role->users_count }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($role) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @can('role_access')
                                    <flux:tooltip content="{{ __('general.access') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="teal"
                                            icon="key-round"
                                            icon:variant="outline"
                                            :href="route('system.roles.access', $role)"
                                            wire:navigate
                                        />
                                    </flux:tooltip>
                                @endcan

                                @can('role_edit')
                                    @if ($role->name !== 'administrator')
                                        <flux:tooltip content="{{ __('general.edit') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="blue"
                                                icon="pencil"
                                                icon:variant="outline"
                                                wire:click="$dispatch('panels.administrator.role.edit.assign-data', { role: {{ $role->id }} })"
                                            />
                                        </flux:tooltip>
                                    @endif
                                @endcan

                                @can('role_delete')
                                    @if ($role->name !== 'administrator')
                                        <flux:tooltip content="{{ __('general.delete') }}">
                                            <flux:button
                                                size="xs"
                                                variant="danger"
                                                icon="trash"
                                                icon:variant="outline"
                                                wire:click="$dispatch('panels.administrator.role.delete.assign-data', { role: {{ $role->id }} })"
                                            />
                                        </flux:tooltip>
                                    @endif
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            {{ __('general.no_roles') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:role.create :key="'role-create'" />
    <livewire:role.edit :key="'role-edit'" />
    <livewire:role.delete :key="'role-delete'" />
</div>
