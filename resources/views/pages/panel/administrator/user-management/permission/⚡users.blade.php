<?php

use App\Models\User;
use App\Support\AdministratorPermissions;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;

new class extends Component
{
    use WithPagination;

    public Permission $permission;

    public string $search = '';

    public function mount(Permission $permission): void
    {
        $this->permission = $permission;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->search !== '', function ($query): void {
                $query->where('mobile', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->paginate(15);
    }

    public function toggleUser(int $userId): void
    {
        abort_unless(auth()->user()?->can('permission_access'), 403);

        $user = User::query()->findOrFail($userId);

        if ($user->hasDirectPermission($this->permission)) {
            $user->revokePermissionTo($this->permission);
            Flux::toast(__('general.permission_revoked'));
        } else {
            $user->givePermissionTo($this->permission);
            Flux::toast(__('general.permission_granted'));
        }

        unset($this->users);
    }

    public function userHasPermission(User $user): bool
    {
        return $user->hasDirectPermission($this->permission);
    }

    public function permissionLabel(): string
    {
        return AdministratorPermissions::label($this->permission->name);
    }
};
?>

<x-slot name="title">{{ __('general.access') }} - {{ $this->permissionLabel() }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('system.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('system.permissions.index')" wire:navigate>
                {{ __('general.permissions') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.access') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.access_permission_users') }}
            </flux:heading>
        </div>

        <flux:callout icon="key-round" variant="secondary" class="mt-4" inline>
            {{ $this->permissionLabel() }}
            <span dir="ltr" class="ms-2 text-sm opacity-70">{{ $permission->name }}</span>
        </flux:callout>
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
                                    :checked="$this->userHasPermission($user)"
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
