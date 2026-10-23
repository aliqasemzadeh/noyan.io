<?php

use App\Actions\Accounts\RebalanceAccountAction;
use App\Models\Accounting\Account;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Account $account = null;

    #[On('panels.accounting.account.rebalance.assign-data')]
    public function assignData(Account $account): void
    {
        abort_unless(
            (int) $account->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->account = $account;

        Flux::modal('account.rebalance')->show();
    }

    public function rebalance(RebalanceAccountAction $action): void
    {
        if ($this->account === null) {
            return;
        }

        abort_unless(
            (int) $this->account->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $action->handle($this->account);
        $this->reset('account');

        $this->dispatch('panels.accounting.account.index.table');
        $this->dispatch('panels.accounting.account.view.refresh');

        Flux::modals()->close();

        Flux::toast(__('general.account_rebalanced'));
    }
};
?>

<flux:modal name="account.rebalance" class="min-w-[22rem]">
    <form wire:submit="rebalance" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.rebalance_account') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.rebalance_account_warning') }}
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

            <flux:button type="submit" variant="primary" color="teal" icon="refresh-cw">
                {{ __('general.rebalance_account') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
