<?php

use App\Models\Accounting\Party;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Party $party = null;

    #[On('panels.accounting.party.delete.assign-data')]
    public function assignData(Party $party): void
    {
        abort_unless(
            (int) $party->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->party = $party;

        Flux::modal('party.delete')->show();
    }

    public function delete(): void
    {
        if ($this->party === null) {
            return;
        }

        abort_unless(
            (int) $this->party->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->party->delete();
        $this->reset('party');

        $this->dispatch('panels.accounting.party.index.table');
        $this->dispatch('panels.accounting.party.view.refresh');

        Flux::modals()->close();

        Flux::toast(__('general.party_deleted'));
    }
};
?>

<flux:modal name="party.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_party_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($party)
            <flux:callout icon="users" variant="secondary" inline>
                {{ $party->name }}
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
