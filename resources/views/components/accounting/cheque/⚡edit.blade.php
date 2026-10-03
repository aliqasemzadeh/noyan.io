<?php

use App\Livewire\Forms\ChequeForm;
use App\Models\Accounting\Account;
use App\Models\Accounting\Cheque;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ChequeForm $form;

    public ?Cheque $cheque = null;

    #[On('panels.accounting.cheque.edit.assign-data')]
    public function assignData(int $chequeId): void
    {
        $businessId = Auth::user()?->current_business_id;

        $cheque = Cheque::query()
            ->with(['party', 'account'])
            ->where('business_id', $businessId)
            ->whereKey($chequeId)
            ->firstOrFail();

        $this->cheque = $cheque;
        $this->form->setModel($cheque);
        $this->resetValidation();

        Flux::modal('cheque.edit')->show();
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
        $this->form->update();

        $this->reset('cheque');

        $this->dispatch('panels.accounting.cheque.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.cheque_updated'));
    }
};
?>

<flux:modal name="cheque.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_cheque') }}</flux:heading>
    </div>

    @if ($cheque)
        <flux:callout icon="banknote" variant="secondary" inline>
            {{ $cheque->type->label() }} — {{ $cheque->party?->displayName() }}
        </flux:callout>

        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('general.amount') }}</flux:label>
                <flux:input :value="$cheque->amount" readonly dir="ltr" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.issue_date') }}</flux:label>
                <flux:input type="date" :value="$cheque->issue_date->toDateString()" readonly />
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
                <flux:label>{{ __('general.due_date') }}</flux:label>
                <flux:input type="date" wire:model="form.due_date" />
                <flux:error name="form.due_date" />
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
                        <flux:select.option value="{{ $account->id }}" wire:key="edit-cheque-account-{{ $account->id }}">
                            {{ $account->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.account_id" />
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
    @endif
</flux:modal>
