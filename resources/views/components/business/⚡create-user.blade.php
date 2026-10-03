<?php

use App\Enums\Business\BusinessRole;
use App\Livewire\Forms\BusinessUserForm;
use App\Models\Business;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public BusinessUserForm $form;

    public ?Business $business = null;

    public string $userSearch = '';

    #[On('panels.administrator.business.create-user.assign-data')]
    public function assignData(Business $business): void
    {
        $this->business = $business;
        $this->form->setBusiness($business);
        $this->userSearch = '';
        $this->resetValidation();
        unset($this->users);

        Flux::modal('business.create-user')->show();
    }

    #[Computed]
    public function users(): Collection
    {
        $userId = $this->form->user_id;
        $search = $this->userSearch;
        $businessId = $this->business?->id;

        return User::query()
            ->when($search !== '' || $userId, function ($query) use ($userId, $search): void {
                $query->where(function ($query) use ($userId, $search): void {
                    if ($search !== '') {
                        $query->where('mobile', 'like', '%'.$search.'%');
                    }

                    if ($userId) {
                        $query->orWhere('id', $userId);
                    }
                });
            })
            ->when($businessId, function ($query) use ($userId, $businessId): void {
                $query->where(function ($query) use ($userId, $businessId): void {
                    $query->whereDoesntHave('memberships', function ($query) use ($businessId): void {
                        $query->where('business_id', $businessId);
                    });

                    if ($userId) {
                        $query->orWhere('id', $userId);
                    }
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * @return array<int, BusinessRole>
     */
    public function availableRoles(): array
    {
        return array_values(array_filter(
            BusinessRole::cases(),
            fn (BusinessRole $role): bool => $role !== BusinessRole::Owner,
        ));
    }

    public function save(): void
    {
        $this->form->store();

        $this->reset('userSearch', 'business');
        unset($this->users);

        $this->dispatch('panels.administrator.business.users.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_user_added'));
    }
};
?>

<flux:modal name="business.create-user" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_business_user') }}</flux:heading>
    </div>

    @if ($business)
        <flux:callout icon="box" variant="secondary" inline>
            {{ $business->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.user') }}</flux:label>
            <flux:select
                wire:model="form.user_id"
                variant="combobox"
                :filter="false"
                placeholder="{{ __('general.select_user') }}"
            >
                <x-slot name="input">
                    <flux:select.input
                        wire:model.live="userSearch"
                        placeholder="{{ __('general.search_user_placeholder') }}"
                    />
                </x-slot>

                @foreach ($this->users as $user)
                    <flux:select.option value="{{ $user->id }}" wire:key="business-user-{{ $user->id }}">
                        {{ $user->mobile }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.user_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.role') }}</flux:label>
            <flux:select wire:model="form.role" searchable variant="listbox">
                @foreach ($this->availableRoles() as $role)
                    <flux:select.option value="{{ $role->value }}">
                        {{ __('general.business_role_'.$role->value) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.role" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
