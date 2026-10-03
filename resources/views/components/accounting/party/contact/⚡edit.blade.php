<?php

use App\Livewire\Forms\PartyContactForm;
use App\Models\Accounting\PartyContact;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public PartyContactForm $form;

    public ?PartyContact $contact = null;

    #[On('panels.accounting.party.contact.edit.assign-data')]
    public function assignData(PartyContact $contact): void
    {
        $contact->loadMissing('party');

        abort_unless(
            (int) $contact->party?->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->contact = $contact;
        $this->form->setModel($contact);
        $this->resetValidation();

        Flux::modal('party.contact.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();

        $this->reset('contact');

        $this->dispatch('panels.accounting.party.view.contacts');
        $this->dispatch('panels.accounting.party.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.party_contact_updated'));
    }
};
?>

<flux:modal name="party.contact.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_party_contact') }}</flux:heading>
    </div>

    @if ($contact)
        <flux:callout icon="user-round" variant="secondary" inline>
            {{ $contact->name }}
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
