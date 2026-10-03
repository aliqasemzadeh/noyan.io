<?php

use App\Models\Business;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
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

    #[Computed]
    public function businesses(): LengthAwarePaginator
    {
        return Auth::user()
            ->businesses()
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('slug', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate(config('general.per_page', 15));
    }

    public function formatCreatedAt(Business $business): string
    {
        return Jalalian::fromDateTime($business->created_at)->format('Y/m/d H:i');
    }
};
?>

<div class="space-y-6">
    <x-slot name="title">{{ __('general.my_businesses') }} - {{ __('general.app_name') }}</x-slot>

    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.my_businesses') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-3">
            <flux:heading size="xl" level="1">
                {{ __('general.my_businesses') }}
            </flux:heading>

            <flux:button
                variant="primary"
                color="teal"
                icon="plus"
                :href="route('user.businesses.create')"
                wire:navigate
            >
                {{ __('general.create_business') }}
            </flux:button>
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

        <flux:table :paginate="$this->businesses">
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.business_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.business_category') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->businesses as $business)
                    <flux:table.row :key="$business->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $business->name }}</span>
                                @if (auth()->user()->current_business_id === $business->id)
                                    <flux:badge size="sm" color="teal">{{ __('general.active') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $business->type?->label() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $business->category?->label() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($business) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.view_business') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="zinc"
                                        icon="eye"
                                        icon:variant="outline"
                                        :href="route('user.businesses.view', $business)"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        :href="route('user.businesses.edit', $business)"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('general.no_businesses') }}</flux:text>
                                <div class="mt-4">
                                    <flux:button
                                        variant="primary"
                                        color="teal"
                                        icon="plus"
                                        :href="route('user.businesses.create')"
                                        wire:navigate
                                    >
                                        {{ __('general.create_business') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
