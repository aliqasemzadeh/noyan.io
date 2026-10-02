<?php

use App\Enums\Currency;
use App\Livewire\Forms\BusinessForm;
use App\Models\Business;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public BusinessForm $form;

    public ?Business $business = null;

    public string $ownerSearch = '';

    #[On('panels.administrator.business.edit.assign-data')]
    public function assignData(Business $business): void
    {
        $this->business = $business;
        $this->form->setModel($business);
        $this->ownerSearch = $business->owner?->mobile ?? '';
        $this->resetValidation();
        unset($this->owners);

        Flux::modal('business.edit')->show();
    }

    #[Computed]
    public function owners(): Collection
    {
        $ownerId = $this->form->owner_id;
        $search = $this->ownerSearch;

        return User::query()
            ->when($search !== '' || $ownerId, function ($query) use ($ownerId, $search): void {
                $query->where(function ($query) use ($ownerId, $search): void {
                    if ($search !== '') {
                        $query->where('mobile', 'like', '%'.$search.'%');
                    }

                    if ($ownerId) {
                        $query->orWhere('id', $ownerId);
                    }
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    public function save(): void
    {
        $this->form->update();

        $this->reset('ownerSearch', 'business');
        unset($this->owners);

        $this->dispatch('panels.administrator.business.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_updated'));
    }
};
?>

<flux:modal name="business.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_business') }}</flux:heading>
    </div>

    @if ($business)
        <flux:callout icon="box" variant="secondary" inline>
            {{ $business->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input
                wire:model="form.name"
                placeholder="{{ __('general.business_name_placeholder') }}"
            />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.slug') }}</flux:label>
            <flux:input
                wire:model="form.slug"
                placeholder="{{ __('general.slug_placeholder') }}"
                dir="ltr"
            />
            <flux:description>{{ __('general.slug_hint') }}</flux:description>
            <flux:error name="form.slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.owner') }}</flux:label>
            <flux:select
                wire:model="form.owner_id"
                variant="combobox"
                clearable
                :filter="false"
                placeholder="{{ __('general.select_owner') }}"
            >
                <x-slot name="input">
                    <flux:select.input
                        wire:model.live="ownerSearch"
                        placeholder="{{ __('general.search_owner_placeholder') }}"
                    />
                </x-slot>

                @foreach ($this->owners as $user)
                    <flux:select.option value="{{ $user->id }}" wire:key="edit-owner-{{ $user->id }}">
                        {{ $user->mobile }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.owner_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.default_currency') }}</flux:label>
            <flux:select wire:model="form.default_currency" searchable variant="listbox">
                @foreach (Currency::cases() as $currency)
                    <flux:select.option value="{{ $currency->value }}">
                        {{ __('general.currency_'.$currency->value) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.default_currency" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model.live="form.is_active" />
            <flux:error name="form.is_active" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
