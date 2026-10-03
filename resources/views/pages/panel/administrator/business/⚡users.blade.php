<?php

use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new
#[Title('Business Users')]
class extends Component
{
    use WithPagination;

    public Business $business;

    public string $search = '';

    public function mount(Business $business): void
    {
        $this->business = $business;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.administrator.business.users.table')]
    public function refreshTable(): void
    {
        unset($this->memberships);
    }

    #[Computed]
    public function memberships(): LengthAwarePaginator
    {
        return BusinessUser::query()
            ->with('user')
            ->where('business_id', $this->business->id)
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->whereHas('user', function ($query) use ($search): void {
                    $query->where('mobile', 'like', $search);
                });
            })
            ->latest()
            ->paginate(15);
    }

    public function formatCreatedAt(BusinessUser $membership): string
    {
        return Jalalian::fromDateTime($membership->created_at)->format('Y/m/d H:i');
    }

    public function roleLabel(BusinessRole $role): string
    {
        return __('general.business_role_'.$role->value);
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
            <flux:breadcrumbs.item :href="route('system.businesses.index')" wire:navigate>
                {{ __('general.businesses') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.business_users') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">
                    {{ __('general.business_users') }}
                </flux:heading>
                <flux:text class="mt-2">{{ $business->name }}</flux:text>
            </div>

            <flux:button
                variant="primary"
                color="teal"
                icon="plus"
                wire:click="$dispatch('panels.administrator.business.create-user.assign-data', { business: {{ $business->id }} })"
            >
                {{ __('general.create_business_user') }}
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

        <flux:table :paginate="$this->memberships">
            <flux:table.columns>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column>{{ __('general.role') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->memberships as $membership)
                    <flux:table.row :key="$membership->id">
                        <flux:table.cell>
                            <span dir="ltr">{{ $membership->user?->mobile }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->roleLabel($membership->role) }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($membership) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($membership->role !== BusinessRole::Owner)
                                <div class="flex justify-end gap-2">
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            icon="trash"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.administrator.business.remove-user.assign-data', { membership: {{ $membership->id }} })"
                                        />
                                    </flux:tooltip>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            {{ __('general.no_business_users') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:business.create-user :key="'business-create-user-'.$business->id" />
    <livewire:business.remove-user :key="'business-remove-user-'.$business->id" />
</div>
