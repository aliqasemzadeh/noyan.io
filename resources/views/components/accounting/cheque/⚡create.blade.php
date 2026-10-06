<?php

use App\Enums\Accounting\ChequeType;
use App\Livewire\Forms\ChequeForm;
use App\Models\Accounting\Account;
use App\Models\Accounting\Party;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ChequeForm $form;

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

    public function effectHint(): string
    {
        return $this->form->type === ChequeType::Issued->value
            ? __('general.cheque_effect_register_issued')
            : __('general.cheque_effect_register_received');
    }

    public function save(): void
    {
        $this->form->store();

        $this->dispatch('panels.accounting.cheque.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.cheque_created'));
    }
};
?>

<flux:modal name="cheque.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_cheque') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_cheque_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.cheque_type') }}</flux:label>
            <flux:radio.group wire:model="form.type" variant="cards" class="flex max-sm:flex-col">
                @foreach (ChequeType::cases() as $type)
                    <flux:radio
                        :value="$type->value"
                        :label="$type->label()"
                        wire:key="create-cheque-type-{{ $type->value }}"
                    />
                @endforeach
            </flux:radio.group>
            <flux:error name="form.type" />
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
                    <flux:select.option value="{{ $party->id }}" wire:key="create-cheque-party-{{ $party->id }}">
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
                clearable
            >
                @foreach ($this->accounts as $account)
                    <flux:select.option value="{{ $account->id }}" wire:key="create-cheque-account-{{ $account->id }}">
                        {{ $account->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.account_id" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.cheque_number') }}</flux:label>
                <flux:input wire:model="form.cheque_number" dir="ltr" />
                <flux:error name="form.cheque_number" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.sayad_number') }}</flux:label>
                <flux:input wire:model="form.sayad_number" maxlength="16" dir="ltr" clearable />
                <flux:error name="form.sayad_number" />
            </flux:field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.bank_name') }}</flux:label>
                <flux:input wire:model="form.bank_name" />
                <flux:error name="form.bank_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.bank_branch') }}</flux:label>
                <flux:input wire:model="form.bank_branch" clearable />
                <flux:error name="form.bank_branch" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('general.amount') }}</flux:label>
            <flux:input wire:model.blur="form.amount" mask:dynamic="$money($input)" placeholder="0" dir="ltr" />
            <flux:error name="form.amount" />
        </flux:field>

        <flux:callout icon="banknote" variant="secondary" inline>
            {{ $this->effectHint() }}
        </flux:callout>

        <div class="grid gap-4 sm:grid-cols-2">
            @if (\App\Support\LocaleDate::usesJalali())
                <x-date-picker wire:model="form.issue_date" name="form.issue_date" :label="__('general.issue_date')" required />
                <x-date-picker wire:model="form.due_date" name="form.due_date" :label="__('general.due_date')" required />
            @else
                <flux:field>
                    <flux:label>{{ __('general.issue_date') }}</flux:label>
                    <flux:input type="date" wire:model="form.issue_date" />
                    <flux:error name="form.issue_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.due_date') }}</flux:label>
                    <flux:input type="date" wire:model="form.due_date" />
                    <flux:error name="form.due_date" />
                </flux:field>
            @endif
        </div>

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
