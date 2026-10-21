<?php

use App\Actions\Business\ChargeBusinessSmsWalletAction;
use App\Models\Business;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Business $business = null;

    public string $amount = '';

    public string $reason = '';

    #[On('panels.administrator.business.charge-sms-wallet.assign-data')]
    public function assignData(Business $business): void
    {
        abort_unless(Auth::user()?->can('business_sms_wallet_charge'), 403);

        $this->business = $business;
        $this->amount = '';
        $this->reason = '';
        $this->resetValidation();

        Flux::modal('business.charge-sms-wallet')->show();
    }

    public function save(ChargeBusinessSmsWalletAction $action): void
    {
        abort_unless(Auth::user()?->can('business_sms_wallet_charge'), 403);

        $validated = $this->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'amount' => __('general.sms_wallet_amount'),
            'reason' => __('general.sms_wallet_reason'),
        ]);

        $action->handle(
            $this->business,
            (string) $validated['amount'],
            Auth::user(),
            $validated['reason'] ?? null,
        );

        $this->reset('amount', 'reason', 'business');

        $this->dispatch('panels.administrator.business.index.table');

        Flux::modals()->close();
        Flux::toast(__('general.sms_wallet_charged'));
    }
};
?>

<flux:modal name="business.charge-sms-wallet" class="min-w-[22rem]">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.sms_wallet_charge') }}</flux:heading>
            <flux:text class="mt-2">{{ __('general.sms_wallet_charge_hint') }}</flux:text>
        </div>

        @if ($business)
            <flux:callout icon="building" variant="secondary" inline>
                {{ $business->name }}
                —
                <span dir="ltr">{{ number_format((float) $business->sms_wallet_balance) }}</span>
            </flux:callout>
        @endif

        <flux:field>
            <flux:label>{{ __('general.sms_wallet_amount') }}</flux:label>
            <flux:input type="number" wire:model="amount" min="1" step="1" dir="ltr" />
            <flux:error name="amount" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.sms_wallet_reason') }}</flux:label>
            <flux:input wire:model="reason" clearable />
            <flux:error name="reason" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
