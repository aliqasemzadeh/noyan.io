<?php

use App\Enums\PartyType;
use App\Livewire\Forms\PartyForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public PartyForm $form;

    public function save(): void
    {
        $party = $this->form->store();

        $this->dispatch('panels.accounting.party.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.party_created', ['name' => $party->name]));
    }
};
?>

<flux:modal name="party.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_party') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_party_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.party_type') }}</flux:label>
            <flux:select wire:model.live="form.type" searchable variant="listbox" placeholder="{{ __('general.select_party_type') }}">
                @foreach (PartyType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="create-party-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input
                wire:model="form.name"
                placeholder="{{ __('general.party_name_placeholder') }}"
            />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.legal_name') }}</flux:label>
            <flux:input
                wire:model="form.legal_name"
                placeholder="{{ __('general.legal_name_placeholder') }}"
                clearable
            />
            <flux:error name="form.legal_name" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.national_id') }}</flux:label>
                <flux:input
                    wire:model="form.national_id"
                    placeholder="{{ $form->type === 'company' ? __('general.company_national_id_placeholder') : __('general.national_id_placeholder') }}"
                    dir="ltr"
                    clearable
                />
                <flux:error name="form.national_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.economic_code') }}</flux:label>
                <flux:input
                    wire:model="form.economic_code"
                    placeholder="{{ __('general.economic_code_placeholder') }}"
                    dir="ltr"
                    clearable
                />
                <flux:error name="form.economic_code" />
            </flux:field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.mobile') }}</flux:label>
                <flux:input
                    wire:model="form.mobile"
                    placeholder="{{ __('general.mobile_placeholder') }}"
                    dir="ltr"
                    clearable
                />
                <flux:error name="form.mobile" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.phone') }}</flux:label>
                <flux:input
                    wire:model="form.phone"
                    placeholder="{{ __('general.phone_placeholder') }}"
                    dir="ltr"
                    clearable
                />
                <flux:error name="form.phone" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('general.email') }}</flux:label>
            <flux:input
                type="email"
                wire:model="form.email"
                placeholder="{{ __('general.email_placeholder') }}"
                dir="ltr"
                clearable
            />
            <flux:error name="form.email" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.address') }}</flux:label>
            <flux:textarea
                wire:model="form.address"
                placeholder="{{ __('general.address_placeholder') }}"
                rows="3"
            />
            <flux:error name="form.address" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.postal_code') }}</flux:label>
                <flux:input
                    wire:model="form.postal_code"
                    placeholder="{{ __('general.postal_code_placeholder') }}"
                    dir="ltr"
                    clearable
                />
                <flux:error name="form.postal_code" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.credit_limit') }}</flux:label>
                <flux:input
                    wire:model="form.credit_limit"
                    placeholder="0"
                    dir="ltr"
                />
                <flux:error name="form.credit_limit" />
            </flux:field>
        </div>

        <div class="space-y-3">
            <flux:field variant="inline">
                <flux:label>{{ __('general.is_customer') }}</flux:label>
                <flux:switch wire:model.live="form.is_customer" />
                <flux:error name="form.is_customer" />
            </flux:field>

            <flux:field variant="inline">
                <flux:label>{{ __('general.is_supplier') }}</flux:label>
                <flux:switch wire:model.live="form.is_supplier" />
                <flux:error name="form.is_supplier" />
            </flux:field>

            <flux:field variant="inline">
                <flux:label>{{ __('general.is_active') }}</flux:label>
                <flux:switch wire:model.live="form.is_active" />
                <flux:error name="form.is_active" />
            </flux:field>
        </div>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
