<?php

use App\Enums\Currency;
use App\Livewire\Forms\BusinessForm;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;

new class extends Component
{
    public BusinessForm $form;

    public string $ownerSearch = '';

    public string $newOwnerMobile = '';

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

    public function createOwner(): void
    {
        $validated = $this->validate([
            'newOwnerMobile' => [
                'required',
                'string',
                new IranianMobile(format: 'zero'),
                Rule::unique('users', 'mobile'),
            ],
        ], attributes: [
            'newOwnerMobile' => __('general.mobile'),
        ]);

        $user = User::create([
            'mobile' => $validated['newOwnerMobile'],
        ]);

        $this->form->owner_id = $user->id;
        $this->ownerSearch = $user->mobile;
        $this->reset('newOwnerMobile');
        $this->resetValidation('newOwnerMobile');
        unset($this->owners);

        Flux::modal('business.create.owner')->close();

        Flux::toast(__('general.user_created', ['mobile' => $user->mobile]));
    }

    public function save(): void
    {
        $business = $this->form->store();

        $this->reset('ownerSearch', 'newOwnerMobile');
        unset($this->owners);

        $this->dispatch('panels.administrator.business.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_created', ['name' => $business->name]));
    }
};
?>

<flux:modal name="business.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_business') }}</flux:heading>
    </div>

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
                    <flux:select.option value="{{ $user->id }}" wire:key="owner-{{ $user->id }}">
                        {{ $user->mobile }}
                    </flux:select.option>
                @endforeach

                <flux:select.option.create modal="business.create.owner">
                    {{ __('general.create_owner') }}
                </flux:select.option.create>
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

<flux:modal name="business.create.owner" class="md:w-96">
    <form wire:submit="createOwner" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.create_owner') }}</flux:heading>
            <flux:text class="mt-2">{{ __('general.create_owner_hint') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('general.mobile') }}</flux:label>
            <flux:input
                wire:model="newOwnerMobile"
                placeholder="{{ __('general.mobile_placeholder') }}"
                dir="ltr"
            />
            <flux:error name="newOwnerMobile" />
        </flux:field>

        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary" color="teal">
                {{ __('general.save') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
