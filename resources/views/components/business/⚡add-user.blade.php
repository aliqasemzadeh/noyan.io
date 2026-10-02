<?php

use App\Enums\BusinessRole;
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

    #[On('panels.administrator.business.user.add.assign-data')]
    public function assignData(Business $business): void
    {
        $this->business = $business;
        $this->form->setBusiness($business);
        $this->userSearch = '';
        $this->resetValidation();
        unset($this->userOptions);

        Flux::modal('business.user.add')->show();
    }

    public function save(): void
    {
        $this->form->store();

        $this->dispatch('panels.administrator.business.users.table');
        $this->dispatch('panels.administrator.business.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_user_added'));
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function userOptions(): Collection
    {
        $search = trim($this->userSearch);
        $memberIds = $this->business
            ? $this->business->memberships()->pluck('user_id')
            : collect();

        $results = User::query()
            ->when($memberIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $memberIds))
            ->when($search !== '', fn ($query) => $query->where('mobile', 'like', '%'.$search.'%'))
            ->latest()
            ->limit(20)
            ->get();

        if ($this->form->user_id) {
            $selected = User::query()
                ->whereIn('id', [$this->form->user_id])
                ->whereNotIn('id', $results->pluck('id'))
                ->get();

            $results = $selected->merge($results);
        }

        return $results;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function roles(): array
    {
        return collect(BusinessRole::cases())
            ->reject(fn (BusinessRole $role): bool => $role === BusinessRole::Owner)
            ->map(fn (BusinessRole $role): array => [
                'value' => $role->value,
                'label' => __('general.business_role_'.$role->value),
            ])
            ->values()
            ->all();
    }
};
?>

<flux:modal name="business.user.add" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.add_business_user') }}</flux:heading>
    </div>

    @if ($business)
        <flux:callout icon="building" variant="secondary" inline>
            {{ $business->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.user') }}</flux:label>
            <div class="space-y-2">
                <flux:input
                    wire:model.live.debounce.300ms="userSearch"
                    icon="search"
                    placeholder="{{ __('general.search_user_placeholder') }}"
                    clearable
                />
                <flux:select wire:model="form.user_id" placeholder="{{ __('general.select_user') }}">
                    @foreach ($this->userOptions as $user)
                        <flux:select.option :value="$user->id" wire:key="add-user-option-{{ $user->id }}">
                            {{ $user->mobile }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:error name="form.user_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.role') }}</flux:label>
            <flux:select wire:model="form.role">
                @foreach ($this->roles as $role)
                    <flux:select.option :value="$role['value']">{{ $role['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.role" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
