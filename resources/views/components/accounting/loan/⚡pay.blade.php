<?php

use App\Livewire\Forms\LoanPaymentForm;
use App\Models\Accounting\Account;
use App\Models\Accounting\Loan;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public LoanPaymentForm $form;

    #[On('panels.accounting.loan.pay.assign-data')]
    public function assignData(int $loan): void
    {
        $businessId = Auth::user()?->current_business_id;

        $model = Loan::query()
            ->where('business_id', $businessId)
            ->whereKey($loan)
            ->firstOrFail();

        $this->form->setLoan($model);

        Flux::modal('loan.pay')->show();
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function accounts(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Account::cachedOptionsForBusiness($businessId);
    }

    public function save(): void
    {
        $loan = $this->form->store();

        $this->dispatch('panels.accounting.loan.index.table');
        $this->dispatch('panels.accounting.loan.show.refresh', loanId: $loan->id);
        $this->dispatch('panels.accounting.transaction.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.loan_payment_recorded'));
    }
};
?>

<flux:modal name="loan.pay" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.loan_payment') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.loan_payment_hint') }}</flux:text>
    </div>

    @if ($form->loan)
        <flux:callout icon="hand-coins" variant="secondary" inline>
            {{ $form->loan->title }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.amount') }}</flux:label>
            <flux:input wire:model="form.amount" placeholder="0" dir="ltr" />
            <flux:error name="form.amount" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.account') }}</flux:label>
            <flux:select
                wire:model="form.account_id"
                searchable
                variant="listbox"
                placeholder="{{ __('general.select_account') }}"
            >
                @foreach ($this->accounts as $account)
                    <flux:select.option value="{{ $account->id }}" wire:key="pay-loan-account-{{ $account->id }}">
                        {{ $account->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.account_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.transaction_date') }}</flux:label>
            <flux:input type="date" wire:model="form.transaction_date" />
            <flux:error name="form.transaction_date" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.reference_number') }}</flux:label>
            <flux:input
                wire:model="form.reference_number"
                placeholder="{{ __('general.reference_number_placeholder') }}"
                clearable
            />
            <flux:error name="form.reference_number" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.note') }}</flux:label>
            <flux:textarea wire:model="form.note" rows="3" placeholder="{{ __('general.note_placeholder') }}" />
            <flux:error name="form.note" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
