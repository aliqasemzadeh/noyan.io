<?php

use App\Models\Accounting\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.project.index.table')]
    public function refreshTable(): void
    {
        unset($this->projects);
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Project::query()
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('code', 'like', $search);
                });
            })
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15);
    }
};
?>

<x-slot name="title">{{ __('general.projects') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.projects') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.projects') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.projects_page_hint') }}</flux:text>
            </div>

            <flux:modal.trigger name="project.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_project') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />

            <flux:select wire:model.live="statusFilter" searchable variant="listbox" placeholder="{{ __('general.all_statuses') }}">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('general.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('general.inactive') }}</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->projects">
            <flux:table.columns>
                <flux:table.column>{{ __('general.code') }}</flux:table.column>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_active') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->projects as $project)
                    <flux:table.row :key="$project->id">
                        <flux:table.cell>
                            <span dir="ltr">{{ $project->code ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $project->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$project->is_active ? 'green' : 'zinc'">
                                {{ $project->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.project.edit.assign-data', { project: {{ $project->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.project.delete.assign-data', { project: {{ $project->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            {{ __('general.no_projects') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.project.create :key="'project-create'" />
    <livewire:accounting.project.edit :key="'project-edit'" />
    <livewire:accounting.project.delete :key="'project-delete'" />
</div>
