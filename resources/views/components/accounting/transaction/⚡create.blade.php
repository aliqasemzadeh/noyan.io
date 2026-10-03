<?php

use App\Enums\Accounting\TransactionType;
use App\Livewire\Forms\TransactionForm;
use App\Models\Accounting\Account;
use App\Models\Accounting\Party;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public TransactionForm $form;

    public function mount(): void
    {
        $this->form->mount();
    }

    public function updatedFormType(): void
    {
        if ($this->form->type === TransactionType::Transfer->value) {
            $this->form->party_id = null;
        } else {
            $this->form->destination_account_id = null;
        }
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

    public function save(): void
    {
        $this->form->store();

        $this->dispatch('panels.accounting.transaction.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.transaction_created'));
    }
};
?>

<flux:modal name="transaction.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_transaction') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_transaction_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.transaction_type') }}</flux:label>
            <flux:radio.group wire:model.live="form.type" variant="cards" class="flex max-sm:flex-col">
                @foreach (TransactionType::cases() as $type)
                    <flux:radio
                        :value="$type->value"
                        :label="$type->label()"
                        wire:key="transaction-type-{{ $type->value }}"
                    />
                @endforeach
            </flux:radio.group>
            <flux:error name="form.type" />
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
                    <flux:select.option value="{{ $account->id }}" wire:key="create-tx-account-{{ $account->id }}">
                        {{ $account->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.account_id" />
        </flux:field>

        @if ($form->type === TransactionType::Transfer->value)
            <flux:field>
                <flux:label>{{ __('general.destination_account') }}</flux:label>
                <flux:select
                    wire:model="form.destination_account_id"
                    searchable
                    variant="listbox"
                    placeholder="{{ __('general.select_destination_account') }}"
                >
                    @foreach ($this->accounts as $account)
                        <flux:select.option value="{{ $account->id }}" wire:key="create-tx-destination-{{ $account->id }}">
                            {{ $account->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.destination_account_id" />
            </flux:field>
        @endif

        @if ($form->type !== TransactionType::Transfer->value)
            <flux:field>
                <flux:label>{{ __('general.party') }}</flux:label>
                <flux:select
                    wire:model="form.party_id"
                    searchable
                    variant="listbox"
                    placeholder="{{ __('general.select_party') }}"
                    clearable
                >
                    @foreach ($this->parties as $party)
                        <flux:select.option value="{{ $party->id }}" wire:key="create-tx-party-{{ $party->id }}">
                            {{ $party->displayName() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.party_id" />
            </flux:field>
        @endif

        <flux:field>
            <flux:label>{{ __('general.amount') }}</flux:label>
            <flux:input
                wire:model="form.amount"
                placeholder="0"
                dir="ltr"
            />
            <flux:error name="form.amount" />
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
            <flux:textarea
                wire:model="form.note"
                rows="3"
                placeholder="{{ __('general.note_placeholder') }}"
            />
            <flux:error name="form.note" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
