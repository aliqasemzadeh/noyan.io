<?php

use App\Actions\Ledger\PostJournalEntryAction;
use App\Models\Accounting\JournalEntry;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?JournalEntry $journalEntry = null;

    #[On('panels.accounting.journal-entry.post.assign-data')]
    public function assignData(JournalEntry $journalEntry): void
    {
        abort_unless(
            (int) $journalEntry->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        abort_unless($journalEntry->isDraft(), 403);

        $this->journalEntry = $journalEntry;

        Flux::modal('journal-entry.post')->show();
    }

    public function post(PostJournalEntryAction $action): void
    {
        if ($this->journalEntry === null) {
            return;
        }

        $entry = $action->handle(Auth::user(), $this->journalEntry);
        $this->reset('journalEntry');

        $this->dispatch('panels.accounting.journal-entry.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.journal_entry_posted', ['number' => $entry->voucher_number]));
    }
};
?>

<flux:modal name="journal-entry.post" class="min-w-[22rem]">
    <form wire:submit="post" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.post_journal_entry') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.post_journal_entry_warning') }}
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
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="primary" color="teal">{{ __('general.post_journal_entry') }}</flux:button>
        </div>
    </form>
</flux:modal>
