<?php

use App\Actions\Transactions\UpdateTransactionAction;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Livewire\Forms\TransactionForm;
use App\Models\Accounting\Account;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public TransactionForm $form;

    public ?Transaction $transaction = null;

    public string $tagSearch = '';

    #[On('panels.accounting.transaction.edit.assign-data')]
    public function assignData(Transaction $transaction): void
    {
        abort_unless(
            (int) $transaction->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->transaction = $transaction;
        $this->form->setTransaction($transaction);
        $this->tagSearch = '';
        $this->resetValidation();
        unset($this->transactionCategories, $this->suggestedTags);

        Flux::modal('transaction.edit')->show();
    }

    public function updatedFormType(): void
    {
        $this->form->category_id = null;
        unset($this->transactionCategories);

        if ($this->form->type === TransactionType::Transfer->value) {
            $this->form->party_id = null;
        } else {
            $this->form->destination_account_id = null;
        }
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function suggestedTags(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Transaction::tagNamesForBusiness($businessId)
            ->reject(fn (string $tag): bool => in_array($tag, $this->form->tag_names, true))
            ->values();
    }

    public function addTag(?string $name = null): void
    {
        $tag = trim($name ?? $this->tagSearch);

        if ($tag === '' || mb_strlen($tag) > 50) {
            return;
        }

        if (! in_array($tag, $this->form->tag_names, true)) {
            $this->form->tag_names[] = $tag;
        }

        $this->tagSearch = '';
        unset($this->suggestedTags);
    }

    public function removeTag(int $index): void
    {
        if (! array_key_exists($index, $this->form->tag_names)) {
            return;
        }

        unset($this->form->tag_names[$index]);
        $this->form->tag_names = array_values($this->form->tag_names);
        unset($this->suggestedTags);
    }

    /**
     * @return Collection<int, array{id: int, name: string, path: string, depth: int, is_leaf: bool, is_system: bool}>
     */
    #[Computed]
    public function transactionCategories(): Collection
    {
        $businessId = Auth::user()?->current_business_id;
        $type = CategoryType::tryFrom($this->form->type);

        if ($businessId === null || $type === null) {
            return collect();
        }

        return Category::selectOptionsForBusiness($businessId, $type);
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

    public function linkedLabel(): ?string
    {
        if ($this->transaction === null) {
            return null;
        }

        if ($this->transaction->invoice_id !== null) {
            return __('general.transaction_linked_to_invoice');
        }

        if ($this->transaction->cheque_id !== null) {
            return __('general.transaction_linked_to_cheque');
        }

        if ($this->transaction->loan_id !== null) {
            return __('general.transaction_linked_to_loan');
        }

        return null;
    }

    public function save(UpdateTransactionAction $action): void
    {
        $this->form->update($action);

        $this->reset('transaction');

        $this->dispatch('panels.accounting.transaction.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.transaction_updated'));
    }
};
?>

<flux:modal name="transaction.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_transaction') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.edit_transaction_hint') }}</flux:text>
    </div>

    @if ($this->linkedLabel())
        <flux:callout icon="link" variant="secondary" inline>
            {{ $this->linkedLabel() }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.transaction_type') }}</flux:label>
            <flux:radio.group wire:model.live="form.type" variant="cards" class="flex max-sm:flex-col">
                @foreach (TransactionType::cases() as $type)
                    <flux:radio
                        :value="$type->value"
                        :label="$type->label()"
                        wire:key="edit-transaction-type-{{ $type->value }}"
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
                    <flux:select.option value="{{ $account->id }}" wire:key="edit-tx-account-{{ $account->id }}">
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
                        <flux:select.option value="{{ $account->id }}" wire:key="edit-tx-destination-{{ $account->id }}">
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
                        <flux:select.option value="{{ $party->id }}" wire:key="edit-tx-party-{{ $party->id }}">
                            {{ $party->displayName() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.party_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.category') }}</flux:label>
                <flux:select
                    wire:model="form.category_id"
                    searchable
                    variant="listbox"
                    clearable
                    placeholder="{{ __('general.select_category') }}"
                >
                    @foreach ($this->transactionCategories as $option)
                        <flux:select.option
                            value="{{ $option['id'] }}"
                            :disabled="! $option['is_leaf']"
                            wire:key="edit-tx-category-{{ $option['id'] }}"
                        >
                            {{ str_repeat('— ', $option['depth']) }}{{ $option['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.category_id" />
            </flux:field>
        @endif

        <flux:field>
            <flux:label>{{ __('general.amount') }}</flux:label>
            <flux:input
                wire:model="form.amount"
                mask:dynamic="$money($input)"
                placeholder="0"
                dir="ltr"
            />
            <flux:error name="form.amount" />
        </flux:field>

        @if (\App\Support\LocaleDate::usesJalali())
            <x-date-picker
                wire:model="form.transaction_date"
                name="form.transaction_date"
                :label="__('general.transaction_date')"
                required
            />
        @else
            <flux:field>
                <flux:label>{{ __('general.transaction_date') }}</flux:label>
                <flux:input type="date" wire:model="form.transaction_date" />
                <flux:error name="form.transaction_date" />
            </flux:field>
        @endif

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

        <flux:field>
            <flux:label>{{ __('general.tags') }}</flux:label>

            @if ($form->tag_names !== [])
                <div class="mb-2 flex flex-wrap gap-1">
                    @foreach ($form->tag_names as $index => $tagName)
                        <flux:badge size="sm" color="zinc" wire:key="edit-selected-tag-{{ $index }}-{{ $tagName }}">
                            <span class="inline-flex items-center gap-1">
                                {{ $tagName }}
                                <button
                                    type="button"
                                    class="opacity-70 hover:opacity-100"
                                    wire:click="removeTag({{ $index }})"
                                    aria-label="{{ __('general.delete') }}"
                                >
                                    ×
                                </button>
                            </span>
                        </flux:badge>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2">
                <flux:input
                    wire:model="tagSearch"
                    wire:keydown.enter.prevent="addTag"
                    placeholder="{{ __('general.select_tags') }}"
                    clearable
                    class="flex-1"
                />
                <flux:button type="button" variant="primary" color="zinc" icon="plus" wire:click="addTag">
                    {{ __('general.create_tag') }}
                </flux:button>
            </div>

            @if ($this->suggestedTags->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($this->suggestedTags as $tagName)
                        <flux:button
                            type="button"
                            size="xs"
                            variant="ghost"
                            wire:key="edit-suggest-tag-{{ $tagName }}"
                            wire:click="addTag({{ Js::from($tagName) }})"
                        >
                            {{ $tagName }}
                        </flux:button>
                    @endforeach
                </div>
            @endif

            <flux:error name="form.tag_names" />
            <flux:error name="form.tag_names.*" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
