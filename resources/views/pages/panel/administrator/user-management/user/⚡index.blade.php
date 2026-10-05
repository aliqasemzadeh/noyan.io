<?php

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.administrator.user.index.table')]
    public function refreshTable(): void
    {
        unset($this->users);
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

    public function formatCreatedAt(User $user): string
    {
        return Jalalian::fromDateTime($user->created_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.users') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.users') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.users') }}
            </flux:heading>

            @can('user_create')
                <flux:modal.trigger name="user.create">
                    <flux:button variant="primary" color="teal" icon="plus">
                        {{ __('general.create_user') }}
                    </flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        <flux:text class="mt-2">
            {{ __('general.users_ai_hint') }}
        </flux:text>
    </div>

    <flux:separator variant="subtle" />

    <flux:card>
        <livewire:ai.composer context="users" :key="'ai-composer-users'" />
    </flux:card>

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
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>{{ $user->mobile }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($user) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @can('user_access')
                                    <flux:tooltip content="{{ __('general.access') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="teal"
                                            icon="key-round"
                                            icon:variant="outline"
                                            :href="route('system.users.access', $user)"
                                            wire:navigate
                                        />
                                    </flux:tooltip>
                                @endcan

                                @can('user_edit')
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button size="xs" variant="primary" color="blue" icon="pencil" icon:variant="outline" wire:click="$dispatch('panels.administrator.user.edit.assign-data', { user: {{ $user->id }} })" />
                                    </flux:tooltip>
                                @endcan

                                @can('user_delete')
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button size="xs" variant="danger" icon="trash" icon:variant="outline" wire:click="$dispatch('panels.administrator.user.delete.assign-data', { user: {{ $user->id }} })" />
                                    </flux:tooltip>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            {{ __('general.no_users') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:user.create :key="'user-create'" />
    <livewire:user.edit :key="'user-edit'" />
    <livewire:user.delete :key="'user-delete'" />
</div>
