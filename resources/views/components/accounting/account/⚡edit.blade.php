<?php

use App\Enums\AccountSubType;
use App\Livewire\Forms\AccountForm;
use App\Models\Accounting\Account;
use App\Models\Currency;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public AccountForm $form;

    public ?Account $account = null;

    /**
     * @return Collection<int, Currency>
     */
    #[Computed]
    public function currencies(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return Currency::query()->whereRaw('1 = 0')->get();
        }

        $currencies = Currency::cachedForBusiness($businessId);

        if ($this->account?->currency && $currencies->where('id', $this->account->currency_id)->isEmpty()) {
            $currencies = $currencies->prepend($this->account->currency);
        }

        return $currencies;
    }

    #[On('panels.accounting.account.edit.assign-data')]
    public function assignData(Account $account): void
    {
        abort_unless(
            (int) $account->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->account = $account->loadMissing('currency');
        $this->form->setModel($this->account);
        $this->resetValidation();
        unset($this->currencies);

        Flux::modal('account.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();

        $this->reset('account');
        unset($this->currencies);

        $this->dispatch('panels.accounting.account.index.table');
        $this->dispatch('panels.accounting.account.view.refresh');

        Flux::modals()->close();

        Flux::toast(__('general.account_updated'));
    }
};
?>

<flux:modal name="account.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_account') }}</flux:heading>
    </div>

    @if ($account)
        <flux:callout icon="wallet" variant="secondary" inline>
            {{ $account->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input
                wire:model="form.name"
                placeholder="{{ __('general.account_name_placeholder') }}"
            />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.account_sub_type') }}</flux:label>
            <flux:select wire:model="form.sub_type" searchable variant="listbox" placeholder="{{ __('general.select_account_sub_type') }}">
                @foreach (AccountSubType::cases() as $subType)
                    <flux:select.option value="{{ $subType->value }}" wire:key="edit-sub-type-{{ $subType->value }}">
                        {{ $subType->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.sub_type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.currency') }}</flux:label>
            <flux:select wire:model="form.currency_id" searchable variant="listbox" placeholder="{{ __('general.select_currency') }}">
                @foreach ($this->currencies as $currency)
                    <flux:select.option value="{{ $currency->id }}" wire:key="edit-currency-{{ $currency->id }}">
                        {{ $currency->code }} — {{ $currency->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.currency_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.bank_name') }}</flux:label>
            <flux:input
                wire:model="form.bank_name"
                placeholder="{{ __('general.bank_name_placeholder') }}"
                clearable
            />
            <flux:error name="form.bank_name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.account_number') }}</flux:label>
            <flux:input
                wire:model="form.account_number"
                placeholder="{{ __('general.account_number_placeholder') }}"
                dir="ltr"
                clearable
            />
            <flux:error name="form.account_number" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.card_number') }}</flux:label>
            <flux:input
                wire:model="form.card_number"
                placeholder="{{ __('general.card_number_placeholder') }}"
                dir="ltr"
                clearable
            />
            <flux:error name="form.card_number" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.iban') }}</flux:label>
            <flux:input
                wire:model="form.iban"
                placeholder="{{ __('general.iban_placeholder') }}"
                dir="ltr"
                clearable
            />
            <flux:error name="form.iban" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.opening_balance') }}</flux:label>
            <flux:input
                wire:model="form.opening_balance"
                mask:dynamic="$money($input)"
                placeholder="0"
                dir="ltr"
            />
            <flux:error name="form.opening_balance" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.note') }}</flux:label>
            <flux:textarea
                wire:model="form.note"
                placeholder="{{ __('general.account_note_placeholder') }}"
                rows="3"
            />
            <flux:error name="form.note" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model.live="form.is_active" />
            <flux:error name="form.is_active" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
