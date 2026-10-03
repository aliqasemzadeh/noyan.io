<?php

use App\Actions\Cheques\DeleteChequeAction;
use App\Models\Accounting\Cheque;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Cheque $cheque = null;

    #[On('panels.accounting.cheque.delete.assign-data')]
    public function assignData(int $chequeId): void
    {
        $businessId = Auth::user()?->current_business_id;

        $this->cheque = Cheque::query()
            ->with('party')
            ->where('business_id', $businessId)
            ->whereKey($chequeId)
            ->firstOrFail();

        Flux::modal('cheque.delete')->show();
    }

    public function delete(): void
    {
        if ($this->cheque === null) {
            return;
        }

        $user = Auth::user();

        abort_unless($user !== null, 403);

        app(DeleteChequeAction::class)->handle($user, $this->cheque);

        $this->reset('cheque');

        $this->dispatch('panels.accounting.cheque.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.cheque_deleted'));
    }
};
?>

<flux:modal name="cheque.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_cheque_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($cheque)
            <flux:callout icon="banknote" variant="secondary" inline>
                {{ $cheque->party?->displayName() }} — {{ $cheque->cheque_number }}
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
