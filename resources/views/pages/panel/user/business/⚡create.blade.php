<?php

use App\Actions\Business\CreateBusinessAction;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Livewire\Forms\UserBusinessForm;
use App\Models\Currency;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public UserBusinessForm $form;

    public int $step = 1;

    public function mount(): void
    {
        $this->form->currency_id = $this->form->defaultCurrencyId();
        $this->form->type = BusinessType::Store->value;
        $this->form->category = BusinessCategory::Other->value;
    }

    /**
     * @return Collection<int, Currency>
     */
    #[Computed]
    public function currencies(): Collection
    {
        return Currency::cachedActive();
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->form->validateOnly('name');
            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            $this->form->validateOnly('currency_id');
            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function save(CreateBusinessAction $action): void
    {
        $business = $this->form->store(Auth::user(), $action);

        Flux::toast(__('general.business_created', ['name' => $business->name]));

        $this->redirect(route('user.dashboard'), navigate: true);
    }
};
?>

<x-slot name="title">{{ __('general.create_business') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            @if (auth()->user()->businesses()->exists())
                <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                    {{ __('general.dashboard') }}
                </flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('user.businesses.index')" wire:navigate>
                    {{ __('general.my_businesses') }}
                </flux:breadcrumbs.item>
            @endif
            <flux:breadcrumbs.item>
                {{ __('general.create_business') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.create_business') }}
            </flux:heading>
        </div>
    </div>

    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center gap-2">
            @foreach ([1 => 'business_wizard_step_basics', 2 => 'business_wizard_step_currency', 3 => 'business_wizard_step_type'] as $number => $label)
                <div @class([
                    'flex flex-1 flex-col gap-1 rounded-xl border px-3 py-2 text-sm',
                    'border-teal-500 bg-teal-50 text-teal-800 dark:border-teal-400 dark:bg-teal-950/40 dark:text-teal-100' => $step === $number,
                    'border-zinc-200 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400' => $step !== $number,
                ])>
                    <span class="text-xs font-medium">{{ $number }}</span>
                    <span>{{ __('general.'.$label) }}</span>
                </div>
            @endforeach
        </div>

        <flux:card>
            <form wire:submit="save" class="space-y-6">
                @if ($step === 1)
                    <div class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('general.business_wizard_step_basics') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('general.business_wizard_basics_hint') }}</flux:text>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('general.name') }}</flux:label>
                            <flux:input
                                wire:model="form.name"
                                placeholder="{{ __('general.business_name_placeholder') }}"
                                clearable
                            />
                            <flux:error name="form.name" />
                        </flux:field>
                    </div>
                @endif

                @if ($step === 2)
                    <div class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('general.business_wizard_step_currency') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('general.business_wizard_currency_hint') }}</flux:text>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('general.base_currency') }}</flux:label>
                            <flux:select wire:model="form.currency_id" searchable placeholder="{{ __('general.select_base_currency') }}">
                                @foreach ($this->currencies as $currency)
                                    <flux:select.option :value="$currency->id">
                                        {{ $currency->code }} — {{ $currency->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.currency_id" />
                        </flux:field>
                    </div>
                @endif

                @if ($step === 3)
                    <div class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('general.business_wizard_step_type') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('general.business_wizard_type_hint') }}</flux:text>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('general.business_type') }}</flux:label>
                            <flux:radio.group wire:model="form.type" variant="cards" class="flex max-sm:flex-col">
                                @foreach (BusinessType::cases() as $type)
                                    <flux:radio :value="$type->value" :label="$type->label()" />
                                @endforeach
                            </flux:radio.group>
                            <flux:error name="form.type" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('general.business_category') }}</flux:label>
                            <flux:select wire:model="form.category" searchable placeholder="{{ __('general.select_business_category') }}">
                                @foreach (BusinessCategory::cases() as $category)
                                    <flux:select.option :value="$category->value">
                                        {{ $category->label() }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.category" />
                        </flux:field>
                    </div>
                @endif

                <div class="flex gap-2">
                    @if ($step > 1)
                        <flux:button type="button" variant="ghost" wire:click="previousStep" class="w-full">
                            {{ __('general.previous') }}
                        </flux:button>
                    @endif

                    @if ($step < 3)
                        <flux:button type="button" variant="primary" color="teal" wire:click="nextStep" class="w-full">
                            {{ __('general.next') }}
                        </flux:button>
                    @else
                        <flux:button type="submit" variant="primary" color="teal" class="w-full">
                            {{ __('general.finish_create_business') }}
                        </flux:button>
                    @endif
                </div>
            </form>
        </flux:card>
    </div>
</div>
