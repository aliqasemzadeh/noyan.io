<?php

use App\Actions\Business\UpdateBusinessAction;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Livewire\Forms\UserBusinessForm;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public Business $business;

    public UserBusinessForm $form;

    public function mount(Business $business): void
    {
        abort_unless(Auth::user()?->belongsToBusiness($business), 403);

        $this->business = $business;
        $this->form->setModel($business);
    }

    public function save(UpdateBusinessAction $action): void
    {
        $this->form->update($action);

        Flux::toast(__('general.business_updated'));

        $this->redirect(route('user.businesses.view', $this->business), navigate: true);
    }
};
?>

<x-slot name="title">{{ __('general.edit_business') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.index')" wire:navigate>
                {{ __('general.my_businesses') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.view', $business)" wire:navigate>
                {{ $business->name }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.edit_business') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.edit_business') }}
            </flux:heading>
        </div>
    </div>

    <div class="mx-auto max-w-2xl">
        <flux:card>
            <form wire:submit="save" class="space-y-6">
                <flux:field>
                    <flux:label>{{ __('general.name') }}</flux:label>
                    <flux:input
                        wire:model="form.name"
                        placeholder="{{ __('general.business_name_placeholder') }}"
                        clearable
                    />
                    <flux:error name="form.name" />
                </flux:field>

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
                    <flux:select
                        wire:model="form.category"
                        variant="listbox"
                        searchable
                        placeholder="{{ __('general.select_business_category') }}"
                    >
                        @foreach (BusinessCategory::cases() as $category)
                            <flux:select.option :value="$category->value">
                                {{ $category->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.category" />
                </flux:field>

                <flux:button type="submit" variant="primary" color="teal" class="w-full">
                    {{ __('general.save') }}
                </flux:button>
            </form>
        </flux:card>
    </div>
</div>
