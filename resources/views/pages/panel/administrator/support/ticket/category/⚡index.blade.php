<?php

use App\Models\TicketCategory;
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

    #[On('panels.administrator.support.ticket.category.index.table')]
    public function refreshTable(): void
    {
        unset($this->categories);
    }

    #[Computed]
    public function categories(): LengthAwarePaginator
    {
        return TicketCategory::query()
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            })
            ->ordered()
            ->paginate(config('general.per_page', 15));
    }

    public function formatCreatedAt(TicketCategory $category): string
    {
        return Jalalian::fromDateTime($category->created_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.ticket_categories') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('system.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.support') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.ticket_categories') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.ticket_categories') }}
            </flux:heading>

            @can('ticket_category_create')
                <flux:modal.trigger name="ticket-category.create">
                    <flux:button variant="primary" color="teal" icon="plus">
                        {{ __('general.create_ticket_category') }}
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

        <flux:table :paginate="$this->categories">
            <flux:table.columns>
                <flux:table.column>{{ __('general.title') }}</flux:table.column>
                <flux:table.column>{{ __('general.sort_order') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_active') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->categories as $category)
                    <flux:table.row :key="$category->id">
                        <flux:table.cell>{{ $category->title }}</flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $category->sort_order }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$category->is_active ? 'green' : 'zinc'">
                                {{ $category->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($category) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @can('ticket_category_edit')
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="blue"
                                            icon="pencil"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.administrator.support.ticket.category.edit.assign-data', { category: {{ $category->id }} })"
                                        />
                                    </flux:tooltip>
                                @endcan

                                @can('ticket_category_delete')
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            icon="trash"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.administrator.support.ticket.category.delete.assign-data', { category: {{ $category->id }} })"
                                        />
                                    </flux:tooltip>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            {{ __('general.no_ticket_categories') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:support.ticket-category.create :key="'ticket-category-create'" />
    <livewire:support.ticket-category.edit :key="'ticket-category-edit'" />
    <livewire:support.ticket-category.delete :key="'ticket-category-delete'" />
</div>
