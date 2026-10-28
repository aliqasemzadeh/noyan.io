<?php

use App\Actions\Ledger\DeleteJournalEntryAction;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?JournalEntry $journalEntry = null;

    #[On('panels.accounting.journal-entry.delete.assign-data')]
    public function assignData(JournalEntry $journalEntry): void
    {
        abort_unless(
            (int) $journalEntry->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $journalEntry->loadMissing('referenceable');

        $canDelete = $journalEntry->isOpen()
            || $journalEntry->referenceable instanceof Transaction;

        abort_unless($canDelete, 403);

        $this->journalEntry = $journalEntry;

        Flux::modal('journal-entry.delete')->show();
    }

    public function delete(DeleteJournalEntryAction $action): void
    {
        if ($this->journalEntry === null) {
            return;
        }

        $action->handle(Auth::user(), $this->journalEntry);
        $this->reset('journalEntry');

        $this->dispatch('panels.accounting.journal-entry.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.journal_entry_deleted'));
    }
};
?>

<flux:modal name="journal-entry.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                @if ($journalEntry?->referenceable instanceof Transaction)
                    {{ __('general.journal_delete_cascades_transaction') }}
                @else
                    {{ __('general.delete_journal_entry_warning') }}
                @endif
                <br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($journalEntry)
            <flux:callout icon="book-open" variant="secondary" inline>
                {{ $journalEntry->voucher_number }} — {{ $journalEntry->description }}
            </flux:callout>
        @endif

        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button type="button" variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger" class="w-full sm:w-auto">
                {{ __('general.delete') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
