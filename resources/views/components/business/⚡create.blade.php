<?php

use App\Enums\Currency;
use App\Livewire\Forms\BusinessForm;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;

new class extends Component
{
    public BusinessForm $form;

    public string $ownerSearch = '';

    public string $newOwnerMobile = '';

    public function save(): void
    {
        $business = $this->form->store();

        $this->dispatch('panels.administrator.business.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_created', ['name' => $business->name]));
    }

    public function createOwner(): void
    {
        $validated = Validator::make(
            ['mobile' => $this->newOwnerMobile],
            ['mobile' => ['required', 'string', new IranianMobile(format: 'zero'), 'unique:users,mobile']],
            attributes: ['mobile' => __('general.mobile')],
        )->validate();

        $user = User::create($validated);

        $this->form->owner_id = $user->id;
        $this->ownerSearch = '';
        $this->newOwnerMobile = '';
        unset($this->ownerOptions);

        Flux::modal('business.owner.create')->close();

        Flux::toast(__('general.user_created', ['mobile' => $user->mobile]));
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function ownerOptions(): Collection
    {
        $search = trim($this->ownerSearch);

        $results = User::query()
            ->when($search !== '', fn ($query) => $query->where('mobile', 'like', '%'.$search.'%'))
            ->latest()
            ->limit(20)
            ->get();

        if (blank($search) && $this->form->owner_id) {
            $selected = User::query()
                ->whereIn('id', [$this->form->owner_id])
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
    public function currencies(): array
    {
        return collect(Currency::cases())
            ->map(fn (Currency $currency): array => [
                'value' => $currency->value,
                'label' => __('general.currency_'.$currency->value),
            ])
            ->all();
    }
};
?>

<div>
<flux:modal name="business.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_business') }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" placeholder="{{ __('general.business_name_placeholder') }}" />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.slug') }}</flux:label>
            <flux:input wire:model="form.slug" placeholder="{{ __('general.slug_placeholder') }}" dir="ltr" />
            <flux:description>{{ __('general.slug_hint') }}</flux:description>
            <flux:error name="form.slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.owner') }}</flux:label>
            <div class="space-y-2">
                <flux:input
                    wire:model.live.debounce.300ms="ownerSearch"
                    icon="search"
                    placeholder="{{ __('general.search_owner_placeholder') }}"
                    clearable
                />
                <flux:select wire:model="form.owner_id" placeholder="{{ __('general.select_owner') }}">
                    @foreach ($this->ownerOptions as $user)
                        <flux:select.option :value="$user->id" wire:key="owner-option-{{ $user->id }}">
                            {{ $user->mobile }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:modal.trigger name="business.owner.create">
                    <flux:button type="button" variant="ghost" size="sm" icon="plus" class="w-full">
                        {{ __('general.create_owner') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>
            <flux:error name="form.owner_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.default_currency') }}</flux:label>
            <flux:select wire:model="form.default_currency">
                @foreach ($this->currencies as $currency)
                    <flux:select.option :value="$currency['value']">{{ $currency['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.default_currency" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model="form.is_active" />
            <flux:error name="form.is_active" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>

<flux:modal name="business.owner.create" class="md:w-96">
    <form wire:submit="createOwner" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.create_user') }}</flux:heading>
            <flux:text class="mt-2">{{ __('general.create_owner_hint') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('general.mobile') }}</flux:label>
            <flux:input
                wire:model="newOwnerMobile"
                placeholder="{{ __('general.mobile_placeholder') }}"
                dir="ltr"
            />
            <flux:error name="mobile" />
        </flux:field>

        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary" color="teal">
                {{ __('general.save') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
</div>
