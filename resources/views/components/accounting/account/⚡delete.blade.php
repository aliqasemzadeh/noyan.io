<?php

use App\Models\Accounting\Account;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Account $account = null;

    #[On('panels.accounting.account.delete.assign-data')]
    public function assignData(Account $account): void
    {
        abort_unless(
            (int) $account->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->account = $account;

        Flux::modal('account.delete')->show();
    }

    public function delete(): void
    {
        if ($this->account === null) {
            return;
        }

        abort_unless(
            (int) $this->account->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->account->delete();
        $this->reset('account');

        $this->dispatch('panels.accounting.account.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.account_deleted'));
    }
};
?>

<flux:modal name="account.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_account_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($account)
            <flux:callout icon="wallet" variant="secondary" inline>
                {{ $account->name }}
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
