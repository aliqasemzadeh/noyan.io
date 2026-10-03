<?php

use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Livewire\Forms\ChequeStatusForm;
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
    public ChequeStatusForm $form;

    public ?Cheque $cheque = null;

    #[On('panels.accounting.cheque.change-status.assign-data')]
    public function assignData(int $chequeId): void
    {
        $businessId = Auth::user()?->current_business_id;

        $cheque = Cheque::query()
            ->with(['party', 'account'])
            ->where('business_id', $businessId)
            ->whereKey($chequeId)
            ->firstOrFail();

        $this->cheque = $cheque;
        $this->form->setCheque($cheque);
        $this->resetValidation();

        Flux::modal('cheque.change-status')->show();
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
     * @return list<ChequeStatus>
     */
    public function allowedStatuses(): array
    {
        return $this->cheque?->allowedTransitions() ?? [];
    }

    public function effectHint(): ?string
    {
        $status = ChequeStatus::tryFrom($this->form->status);

        if ($status === null || $this->cheque === null) {
            return null;
        }

        return match ($status) {
            ChequeStatus::Deposited => __('general.cheque_effect_deposit'),
            ChequeStatus::Cleared => $this->cheque->type === ChequeType::Issued
                ? __('general.cheque_effect_clear_issued')
                : __('general.cheque_effect_clear_received'),
            ChequeStatus::Bounced, ChequeStatus::Returned => $this->cheque->type === ChequeType::Issued
                ? __('general.cheque_effect_reverse_issued')
                : __('general.cheque_effect_reverse_received'),
            ChequeStatus::Registered => null,
        };
    }

    public function save(): void
    {
        $this->form->submit();

        $this->reset('cheque');

        $this->dispatch('panels.accounting.cheque.index.table');
        $this->dispatch('panels.accounting.transaction.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.cheque_status_changed'));
    }
};
?>

<flux:modal name="cheque.change-status" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.change_cheque_status') }}</flux:heading>
    </div>

    @if ($cheque)
        <flux:callout icon="banknote" variant="secondary" inline>
            {{ $cheque->party?->displayName() }} — {{ $cheque->cheque_number }}
        </flux:callout>

        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('general.new_status') }}</flux:label>
                <flux:radio.group wire:model.live="form.status" variant="cards" class="flex max-sm:flex-col">
                    @foreach ($this->allowedStatuses() as $status)
                        <flux:radio
                            :value="$status->value"
                            :label="$status->label()"
                            wire:key="change-cheque-status-{{ $status->value }}"
                        />
                    @endforeach
                </flux:radio.group>
                <flux:error name="form.status" />
            </flux:field>

            @if ($this->effectHint())
                <flux:callout icon="banknote" variant="secondary" inline>
                    {{ $this->effectHint() }}
                </flux:callout>
            @endif

            @if ($form->status === ChequeStatus::Cleared->value)
                <flux:field>
                    <flux:label>{{ __('general.transaction_date') }}</flux:label>
                    <flux:input type="date" wire:model="form.transaction_date" />
                    <flux:error name="form.transaction_date" />
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
                            <flux:select.option value="{{ $account->id }}" wire:key="change-cheque-account-{{ $account->id }}">
                                {{ $account->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.account_id" />
                </flux:field>
            @endif

            @if (in_array($form->status, [ChequeStatus::Bounced->value, ChequeStatus::Returned->value], true))
                <flux:field>
                    <flux:label>{{ __('general.note') }}</flux:label>
                    <flux:textarea wire:model="form.note" rows="3" placeholder="{{ __('general.note_placeholder') }}" />
                    <flux:error name="form.note" />
                </flux:field>
            @endif

            <flux:button type="submit" variant="primary" color="teal" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        </form>
    @endif
</flux:modal>
