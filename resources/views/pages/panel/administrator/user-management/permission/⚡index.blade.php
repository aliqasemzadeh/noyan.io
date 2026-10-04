<?php

use App\Support\AdministratorPermissions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function permissions(): LengthAwarePaginator
    {
        return Permission::query()
            ->withCount('users')
            ->when($this->search !== '', function ($query): void {
                $search = mb_strtolower($this->search);
                $matchingNames = collect(AdministratorPermissions::names())
                    ->filter(function (string $name) use ($search): bool {
                        return str_contains(mb_strtolower($name), $search)
                            || str_contains(mb_strtolower(AdministratorPermissions::label($name)), $search);
                    })
                    ->values()
                    ->all();

                $query->where(function ($query) use ($matchingNames): void {
                    $query->where('name', 'like', '%'.$this->search.'%');

                    if ($matchingNames !== []) {
                        $query->orWhereIn('name', $matchingNames);
                    }
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }

    public function permissionLabel(Permission $permission): string
    {
        return AdministratorPermissions::label($permission->name);
    }
};
?>

<x-slot name="title">{{ __('general.permissions') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.user_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.permissions') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.permissions') }}
            </flux:heading>
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

        <flux:table :paginate="$this->permissions">
            <flux:table.columns>
                <flux:table.column>{{ __('general.permission_label') }}</flux:table.column>
                <flux:table.column>{{ __('general.permission_name') }}</flux:table.column>
                <flux:table.column>{{ __('general.users') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->permissions as $permission)
                    <flux:table.row :key="$permission->id">
                        <flux:table.cell>{{ $this->permissionLabel($permission) }}</flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $permission->name }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $permission->users_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('permission_access')
                                <div class="flex justify-end gap-2">
                                    <flux:tooltip content="{{ __('general.access') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="teal"
                                            icon="key-round"
                                            icon:variant="outline"
                                            :href="route('system.permissions.users', $permission)"
                                            wire:navigate
                                        />
                                    </flux:tooltip>
                                </div>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            {{ __('general.no_permissions') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
