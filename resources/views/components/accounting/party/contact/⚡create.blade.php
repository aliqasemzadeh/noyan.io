<?php

use App\Livewire\Forms\PartyContactForm;
use App\Models\Accounting\Party;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public PartyContactForm $form;

    public ?Party $party = null;

    #[On('panels.accounting.party.contact.create.assign-data')]
    public function assignData(Party $party): void
    {
        abort_unless(
            (int) $party->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->party = $party;
        $this->form->reset();
        $this->form->setParty($party);
        $this->resetValidation();

        Flux::modal('party.contact.create')->show();
    }

    public function save(): void
    {
        $contact = $this->form->store();

        $this->reset('party');

        $this->dispatch('panels.accounting.party.view.contacts');
        $this->dispatch('panels.accounting.party.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.party_contact_created', ['name' => $contact->name]));
    }
};
?>

<flux:modal name="party.contact.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_party_contact') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_party_contact_hint') }}</flux:text>
    </div>

    @if ($party)
        <flux:callout icon="users" variant="secondary" inline>
            {{ $party->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input
                wire:model="form.name"
                placeholder="{{ __('general.contact_name_placeholder') }}"
            />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.contact_position') }}</flux:label>
            <flux:input
                wire:model="form.position"
                placeholder="{{ __('general.contact_position_placeholder') }}"
                clearable
            />
            <flux:error name="form.position" />
        </flux:field>

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
            <flux:label>{{ __('general.note') }}</flux:label>
            <flux:textarea
                wire:model="form.note"
                placeholder="{{ __('general.contact_note_placeholder') }}"
                rows="3"
            />
            <flux:error name="form.note" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_primary_contact') }}</flux:label>
            <flux:switch wire:model.live="form.is_primary" />
            <flux:error name="form.is_primary" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
