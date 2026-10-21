<?php

use App\Actions\Transactions\DeleteTransactionAction;
use App\Models\Accounting\Transaction;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Transaction $transaction = null;

    #[On('panels.accounting.transaction.delete.assign-data')]
    public function assignData(Transaction $transaction): void
    {
        abort_unless(
            (int) $transaction->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->transaction = $transaction;

        Flux::modal('transaction.delete')->show();
    }

    public function delete(DeleteTransactionAction $action): void
    {
        if ($this->transaction === null) {
            return;
        }

        abort_unless(
            (int) $this->transaction->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $action->handle(Auth::user(), $this->transaction);
        $this->reset('transaction');

        $this->dispatch('panels.accounting.transaction.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.transaction_deleted'));
    }
};
?>

<flux:modal name="transaction.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_transaction_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($transaction)
            <flux:callout icon="receipt" variant="secondary" inline>
                <span dir="ltr">{{ $transaction->amount }} {{ $transaction->currency }}</span>
                — {{ $transaction->type->label() }}
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
