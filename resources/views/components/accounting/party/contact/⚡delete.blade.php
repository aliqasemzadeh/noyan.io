<?php

use App\Models\Accounting\PartyContact;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?PartyContact $contact = null;

    #[On('panels.accounting.party.contact.delete.assign-data')]
    public function assignData(PartyContact $contact): void
    {
        $contact->loadMissing('party');

        abort_unless(
            (int) $contact->party?->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->contact = $contact;

        Flux::modal('party.contact.delete')->show();
    }

    public function delete(): void
    {
        if ($this->contact === null) {
            return;
        }

        $this->contact->loadMissing('party');

        abort_unless(
            (int) $this->contact->party?->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->contact->delete();
        $this->reset('contact');

        $this->dispatch('panels.accounting.party.view.contacts');
        $this->dispatch('panels.accounting.party.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.party_contact_deleted'));
    }
};
?>

<flux:modal name="party.contact.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_party_contact_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($contact)
            <flux:callout icon="user-round" variant="secondary" inline>
                {{ $contact->name }}
                @if ($contact->position)
                    — {{ $contact->position }}
                @endif
            </flux:callout>
        @endif

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="danger">{{ __('general.delete') }}</flux:button>
        </div>
    </form>
</flux:modal>
