<?php

use App\Models\Business;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new
#[Title('Businesses')]
class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.administrator.business.index.table')]
    public function refreshTable(): void
    {
        unset($this->businesses);
    }

    #[Computed]
    public function businesses(): LengthAwarePaginator
    {
        return Business::query()
            ->with(['owner'])
            ->withCount('memberships')
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('slug', 'like', $search)
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery->where('mobile', 'like', $search));
                });
            })
            ->latest()
            ->paginate(15);
    }

    public function formatCreatedAt(Business $business): string
    {
        return Jalalian::fromDateTime($business->created_at)->format('Y/m/d H:i');
    }
};
?>

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
                {{ __('general.businesses') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.businesses') }}
            </flux:heading>

            <flux:modal.trigger name="business.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_business') }}
                </flux:button>
            </flux:modal.trigger>
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
                <flux:table.column>{{ __('general.owner') }}</flux:table.column>
                <flux:table.column>{{ __('general.default_currency') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_active') }}</flux:table.column>
                <flux:table.column>{{ __('general.members_count') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->businesses as $business)
                    <flux:table.row :key="$business->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $business->name }}</div>
                            <flux:text class="text-xs" dir="ltr">{{ $business->slug }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell dir="ltr">{{ $business->owner?->mobile }}</flux:table.cell>
                        <flux:table.cell>{{ __('general.currency_'.$business->default_currency->value) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($business->is_active)
                                <flux:badge color="teal" size="sm">{{ __('general.active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('general.inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $business->memberships_count }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($business) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.business_users') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="teal"
                                        icon="users"
                                        icon:variant="outline"
                                        :href="route('system.businesses.users', $business)"
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
                                        wire:click="$dispatch('panels.administrator.business.edit.assign-data', { business: {{ $business->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.administrator.business.delete.assign-data', { business: {{ $business->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            {{ __('general.no_businesses') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:business.create :key="'business-create'" />
    <livewire:business.edit :key="'business-edit'" />
    <livewire:business.delete :key="'business-delete'" />
</div>
