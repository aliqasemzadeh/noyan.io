<?php

use App\Enums\Support\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $categoryId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    #[On('panels.administrator.support.ticket.index.table')]
    public function refreshTable(): void
    {
        unset($this->tickets);
    }

    #[Computed]
    public function categories(): Collection
    {
        return TicketCategory::query()->ordered()->get(['id', 'title']);
    }

    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        return Ticket::query()
            ->with(['user:id,mobile', 'category:id,title', 'business:id,name'])
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhereHas('user', fn ($q) => $q->where('mobile', 'like', $search));
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->categoryId !== '', fn ($query) => $query->where('ticket_category_id', $this->categoryId))
            ->latest()
            ->paginate(config('general.per_page', 15));
    }

    public function formatCreatedAt(Ticket $ticket): string
    {
        return Jalalian::fromDateTime($ticket->created_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.tickets') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.tickets') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.tickets') }}
            </flux:heading>
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

            <flux:select wire:model.live="status" searchable clearable placeholder="{{ __('general.status') }}">
                @foreach (TicketStatus::cases() as $ticketStatus)
                    <flux:select.option value="{{ $ticketStatus->value }}" wire:key="status-{{ $ticketStatus->value }}">
                        {{ $ticketStatus->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="categoryId" searchable clearable placeholder="{{ __('general.ticket_category') }}">
                @foreach ($this->categories as $category)
                    <flux:select.option value="{{ $category->id }}" wire:key="category-{{ $category->id }}">
                        {{ $category->title }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->tickets">
            <flux:table.columns>
                <flux:table.column>{{ __('general.title') }}</flux:table.column>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column>{{ __('general.ticket_category') }}</flux:table.column>
                <flux:table.column>{{ __('general.business') }}</flux:table.column>
                <flux:table.column>{{ __('general.status') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->tickets as $ticket)
                    <flux:table.row :key="$ticket->id">
                        <flux:table.cell>{{ $ticket->title }}</flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $ticket->user?->mobile }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->category?->title }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->business?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$ticket->status->badgeColor()">
                                {{ $ticket->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($ticket) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:tooltip content="{{ __('general.view') }}">
                                <flux:button
                                    size="xs"
                                    variant="primary"
                                    color="teal"
                                    icon="eye"
                                    icon:variant="outline"
                                    :href="route('system.support.tickets.view', $ticket)"
                                    wire:navigate
                                />
                            </flux:tooltip>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            {{ __('general.no_tickets') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
