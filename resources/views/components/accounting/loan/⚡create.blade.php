<?php

use App\Enums\Accounting\LoanType;
use App\Livewire\Forms\LoanForm;
use App\Models\Accounting\Account;
use App\Models\Accounting\Party;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public LoanForm $form;

    public function mount(): void
    {
        $this->form->mount();
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

    /**
     * @return Collection<int, Party>
     */
    #[Computed]
    public function parties(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Party::cachedOptionsForBusiness($businessId);
    }

    public function computedTotal(): string
    {
        $principal = is_numeric($this->form->principal_amount) ? (string) $this->form->principal_amount : '0';
        $interest = is_numeric($this->form->interest_amount) ? (string) $this->form->interest_amount : '0';

        return bcadd($principal, $interest, 18);
    }

    public function save(): void
    {
        $this->form->store();

        $this->dispatch('panels.accounting.loan.index.table');
        $this->dispatch('panels.accounting.transaction.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.loan_created'));
    }
};
?>

<flux:modal name="loan.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_loan') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_loan_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.loan_type') }}</flux:label>
            <flux:radio.group wire:model="form.type" variant="cards" class="flex max-sm:flex-col">
                @foreach (LoanType::cases() as $type)
                    <flux:radio
                        :value="$type->value"
                        :label="$type->label()"
                        wire:key="loan-type-{{ $type->value }}"
                    />
                @endforeach
            </flux:radio.group>
            <flux:error name="form.type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.title') }}</flux:label>
            <flux:input wire:model="form.title" placeholder="{{ __('general.loan_title_placeholder') }}" />
            <flux:error name="form.title" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.party') }}</flux:label>
            <flux:select
                wire:model="form.party_id"
                searchable
                variant="listbox"
                placeholder="{{ __('general.select_party') }}"
            >
                @foreach ($this->parties as $party)
                    <flux:select.option value="{{ $party->id }}" wire:key="create-loan-party-{{ $party->id }}">
                        {{ $party->displayName() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.party_id" />
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
                    <flux:select.option value="{{ $account->id }}" wire:key="create-loan-account-{{ $account->id }}">
                        {{ $account->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.account_id" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.principal_amount') }}</flux:label>
                <flux:input wire:model.blur="form.principal_amount" mask:dynamic="$money($input)" placeholder="0" dir="ltr" />
                <flux:error name="form.principal_amount" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.interest_amount') }}</flux:label>
                <flux:input wire:model.blur="form.interest_amount" mask:dynamic="$money($input)" placeholder="0" dir="ltr" />
                <flux:error name="form.interest_amount" />
            </flux:field>
        </div>

        <flux:callout icon="calculator" variant="secondary" inline>
            {{ __('general.total_amount') }}:
            <span dir="ltr" class="font-medium">{{ $this->computedTotal() }}</span>
        </flux:callout>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.installments_count') }}</flux:label>
                <flux:input type="number" wire:model="form.installments_count" min="1" clearable />
                <flux:error name="form.installments_count" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.installment_amount') }}</flux:label>
                <flux:input wire:model="form.installment_amount" mask:dynamic="$money($input)" placeholder="0" dir="ltr" clearable />
                <flux:error name="form.installment_amount" />
            </flux:field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @if (\App\Support\LocaleDate::usesJalali())
                <x-date-picker wire:model="form.issue_date" name="form.issue_date" :label="__('general.issue_date')" required />
                <x-date-picker wire:model="form.first_installment_date" name="form.first_installment_date" :label="__('general.first_installment_date')" />
            @else
                <flux:field>
                    <flux:label>{{ __('general.issue_date') }}</flux:label>
                    <flux:input type="date" wire:model="form.issue_date" />
                    <flux:error name="form.issue_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.first_installment_date') }}</flux:label>
                    <flux:input type="date" wire:model="form.first_installment_date" />
                    <flux:error name="form.first_installment_date" />
                </flux:field>
            @endif
        </div>

        <flux:field>
            <flux:label>{{ __('general.description') }}</flux:label>
            <flux:textarea wire:model="form.description" rows="3" placeholder="{{ __('general.note_placeholder') }}" />
            <flux:error name="form.description" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
