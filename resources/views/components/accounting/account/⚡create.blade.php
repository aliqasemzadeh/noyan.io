<?php

use App\Livewire\Forms\AccountForm;
use App\Models\Currency;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public AccountForm $form;

    /**
     * @return Collection<int, Currency>
     */
    #[Computed]
    public function currencies(): Collection
    {
        $businessId = auth()->user()?->current_business_id;

        if ($businessId === null) {
            return Currency::query()->whereRaw('1 = 0')->get();
        }

        return Currency::cachedForBusiness($businessId);
    }

    public function save(): void
    {
        $account = $this->form->store();

        $this->dispatch('panels.accounting.account.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.account_created', ['name' => $account->name]));
    }
};
?>

<flux:modal name="account.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_account') }}</flux:heading>
    </div>

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
            <flux:label>{{ __('general.currency') }}</flux:label>
            <flux:select wire:model="form.currency_id" searchable variant="listbox" placeholder="{{ __('general.select_currency') }}">
                @foreach ($this->currencies as $currency)
                    <flux:select.option value="{{ $currency->id }}" wire:key="create-currency-{{ $currency->id }}">
                        {{ $currency->code }} — {{ $currency->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.currency_id" />
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
            <flux:label>{{ __('general.opening_balance') }}</flux:label>
            <flux:input
                wire:model="form.opening_balance"
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
