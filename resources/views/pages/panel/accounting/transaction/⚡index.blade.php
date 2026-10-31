<?php

use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $tagFilter = '';

    #[Url]
    public string $accountFilter = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $sortDirection = 'desc';

    /** @var list<string> */
    public array $selected = [];

    public ?string $bulkCategoryId = null;

    public ?string $bulkPartyId = null;

    /** @var list<string> */
    public array $bulkTagNames = [];

    public string $bulkTagSearch = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'desc';
        }
    }

    public function updatedSearch(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedTagFilter(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedAccountFilter(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedSortDirection(): void
    {
        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'desc';
        }

        $this->clearSelection();
        $this->resetPage();
    }

    #[On('panels.accounting.transaction.index.table')]
    public function refreshTable(): void
    {
        unset(
            $this->transactions,
            $this->filterTags,
            $this->filterAccounts,
            $this->filterCategories,
            $this->bulkCategoryOptions,
            $this->bulkParties,
            $this->suggestedBulkTags,
        );
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->transactions
            ->getCollection()
            ->pluck('id')
            ->map(fn (int|string $id): string => (string) $id)
            ->all();

        if ($pageIds === []) {
            return;
        }

        $allSelected = collect($pageIds)->every(
            fn (string $id): bool => in_array($id, $this->selected, true)
        );

        if ($allSelected) {
            $this->selected = array_values(array_diff($this->selected, $pageIds));

            return;
        }

        $this->selected = array_values(array_unique([...$this->selected, ...$pageIds]));
    }

    public function openBulkCategoryModal(): void
    {
        if ($this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $this->bulkCategoryId = null;
        $this->resetValidation();
        unset($this->bulkCategoryOptions);

        Flux::modal('transaction.bulk-category')->show();
    }

    public function openBulkPartyModal(): void
    {
        if ($this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $this->bulkPartyId = null;
        $this->resetValidation();
        unset($this->bulkParties);

        Flux::modal('transaction.bulk-party')->show();
    }

    public function openBulkTagsModal(): void
    {
        if ($this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $this->bulkTagNames = [];
        $this->bulkTagSearch = '';
        $this->resetValidation();
        unset($this->suggestedBulkTags);

        Flux::modal('transaction.bulk-tags')->show();
    }

    public function openBulkDeleteModal(): void
    {
        if ($this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        Flux::modal('transaction.bulk-delete')->show();
    }

    public function addBulkTag(?string $name = null): void
    {
        $tag = trim($name ?? $this->bulkTagSearch);

        if ($tag === '' || mb_strlen($tag) > 50) {
            return;
        }

        if (! in_array($tag, $this->bulkTagNames, true)) {
            $this->bulkTagNames[] = $tag;
        }

        $this->bulkTagSearch = '';
        unset($this->suggestedBulkTags);
    }

    public function removeBulkTag(int $index): void
    {
        if (! array_key_exists($index, $this->bulkTagNames)) {
            return;
        }

        unset($this->bulkTagNames[$index]);
        $this->bulkTagNames = array_values($this->bulkTagNames);
        unset($this->suggestedBulkTags);
    }

    public function applyBulkCategory(UpdateTransactionAction $action): void
    {
        $this->validate([
            'bulkCategoryId' => ['required', 'integer'],
        ], [], [
            'bulkCategoryId' => __('general.category'),
        ]);

        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($user === null || $businessId === null || $this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $category = Category::query()
            ->availableToBusiness($businessId)
            ->whereKey((int) $this->bulkCategoryId)
            ->first();

        if ($category === null || ! $category->isLeaf()) {
            $this->addError('bulkCategoryId', __('general.transaction_category_must_be_leaf'));

            return;
        }

        $categoryType = $category->type;
        $updated = 0;
        $skipped = 0;

        foreach ($this->selectedTransactions() as $transaction) {
            if ($transaction->type === TransactionType::Transfer) {
                $skipped++;

                continue;
            }

            if ($transaction->type->value !== $categoryType->value) {
                $skipped++;

                continue;
            }

            $action->handle($user, $transaction, $this->updatePayload($transaction, [
                'category_id' => $category->id,
            ]));
            $updated++;
        }

        $this->finishBulkAction(
            __('general.bulk_category_updated', ['count' => $updated]),
            $skipped,
        );
    }

    public function applyBulkParty(UpdateTransactionAction $action): void
    {
        if ($this->bulkPartyId === '') {
            $this->bulkPartyId = null;
        }

        $this->validate([
            'bulkPartyId' => ['nullable', 'integer'],
        ], [], [
            'bulkPartyId' => __('general.party'),
        ]);

        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($user === null || $businessId === null || $this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $partyId = $this->bulkPartyId !== null
            ? (int) $this->bulkPartyId
            : null;

        if ($partyId !== null) {
            $partyExists = Party::query()
                ->where('business_id', $businessId)
                ->whereKey($partyId)
                ->exists();

            if (! $partyExists) {
                $this->addError('bulkPartyId', __('general.transaction_party_invalid'));

                return;
            }
        }

        $updated = 0;
        $skipped = 0;

        foreach ($this->selectedTransactions() as $transaction) {
            if ($transaction->type === TransactionType::Transfer) {
                $skipped++;

                continue;
            }

            $action->handle($user, $transaction, $this->updatePayload($transaction, [
                'party_id' => $partyId,
            ]));
            $updated++;
        }

        $this->finishBulkAction(
            __('general.bulk_party_updated', ['count' => $updated]),
            $skipped,
        );
    }

    public function applyBulkTags(): void
    {
        if ($this->bulkTagNames === []) {
            $this->addError('bulkTagNames', __('general.bulk_select_at_least_one_tag'));

            return;
        }

        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($user === null || $businessId === null || $this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $tagType = Transaction::tagTypeForBusiness($businessId);
        $names = collect($this->bulkTagNames)
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $updated = 0;

        foreach ($this->selectedTransactions() as $transaction) {
            $transaction->attachTags($names, $tagType);
            $updated++;
        }

        unset($this->filterTags, $this->suggestedBulkTags);

        $this->finishBulkAction(
            __('general.bulk_tags_added', ['count' => $updated]),
            0,
        );
    }

    public function applyBulkDelete(DeleteTransactionAction $action): void
    {
        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($user === null || $businessId === null || $this->selected === []) {
            Flux::toast(__('general.bulk_nothing_selected'));

            return;
        }

        $deleted = 0;

        foreach ($this->selectedTransactions() as $transaction) {
            $action->handle($user, $transaction);
            $deleted++;
        }

        $this->finishBulkAction(
            __('general.bulk_deleted', ['count' => $deleted]),
            0,
        );
    }

    /**
     * @return Collection<int, Transaction>
     */
    protected function selectedTransactions(): Collection
    {
        $businessId = Auth::user()?->current_business_id;
        $ids = collect($this->selected)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($businessId === null || $ids === []) {
            return collect();
        }

        return Transaction::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * @param  array{category_id?: int|null, party_id?: int|null}  $overrides
     * @return array{
     *     type: TransactionType,
     *     account_id: int,
     *     destination_account_id: int|null,
     *     party_id: int|null,
     *     category_id: int|null,
     *     transaction_date: string,
     *     currency: string|null,
     *     exchange_rate: string|null,
     *     amount: string,
     *     reference_number: string|null,
     *     note: string|null
     * }
     */
    protected function updatePayload(Transaction $transaction, array $overrides = []): array
    {
        $date = $transaction->transaction_date;

        return [
            'type' => $transaction->type,
            'account_id' => (int) $transaction->account_id,
            'destination_account_id' => $transaction->destination_account_id !== null
                ? (int) $transaction->destination_account_id
                : null,
            'party_id' => array_key_exists('party_id', $overrides)
                ? $overrides['party_id']
                : ($transaction->party_id !== null ? (int) $transaction->party_id : null),
            'category_id' => array_key_exists('category_id', $overrides)
                ? $overrides['category_id']
                : ($transaction->category_id !== null ? (int) $transaction->category_id : null),
            'transaction_date' => $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d')
                : (string) $date,
            'currency' => $transaction->currency,
            'exchange_rate' => $transaction->exchange_rate !== null ? (string) $transaction->exchange_rate : null,
            'amount' => (string) $transaction->amount,
            'reference_number' => $transaction->reference_number,
            'note' => $transaction->note,
        ];
    }

    protected function finishBulkAction(string $message, int $skipped): void
    {
        $this->clearSelection();
        $this->bulkCategoryId = null;
        $this->bulkPartyId = null;
        $this->bulkTagNames = [];
        $this->bulkTagSearch = '';
        $this->refreshTable();

        Flux::modals()->close();

        if ($skipped > 0) {
            Flux::toast($message.' '.__('general.bulk_skipped_incompatible', ['count' => $skipped]));

            return;
        }

        Flux::toast($message);
    }

    #[Computed]
    public function filtersActive(): bool
    {
        return $this->typeFilter !== ''
            || $this->tagFilter !== ''
            || $this->accountFilter !== ''
            || $this->categoryFilter !== ''
            || $this->sortDirection !== 'desc';
    }

    #[Computed]
    public function pageSelected(): bool
    {
        $pageIds = $this->transactions
            ->getCollection()
            ->pluck('id')
            ->map(fn (int|string $id): string => (string) $id)
            ->all();

        if ($pageIds === []) {
            return false;
        }

        return collect($pageIds)->every(
            fn (string $id): bool => in_array($id, $this->selected, true)
        );
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function filterTags(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Transaction::tagNamesForBusiness($businessId);
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function filterAccounts(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Account::cachedOptionsForBusiness($businessId);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function filterCategories(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Category::query()
            ->where('business_id', $businessId)
            ->whereIn('type', [CategoryType::Income, CategoryType::Expense])
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    /**
     * @return Collection<int, array{id: int, name: string, path: string, depth: int, is_leaf: bool, type_label: string}>
     */
    #[Computed]
    public function bulkCategoryOptions(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        $options = collect();

        foreach ([CategoryType::Income, CategoryType::Expense] as $type) {
            $typeLabel = $type->label();

            foreach (Category::selectOptionsForBusiness($businessId, $type) as $option) {
                $options->push([
                    'id' => $option['id'],
                    'name' => $option['name'],
                    'path' => $option['path'],
                    'depth' => $option['depth'],
                    'is_leaf' => $option['is_leaf'],
                    'type_label' => $typeLabel,
                ]);
            }
        }

        return $options->values();
    }

    /**
     * @return Collection<int, Party>
     */
    #[Computed]
    public function bulkParties(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Party::cachedOptionsForBusiness($businessId);
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function suggestedBulkTags(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Transaction::tagNamesForBusiness($businessId)
            ->reject(fn (string $tag): bool => in_array($tag, $this->bulkTagNames, true))
            ->values();
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return Transaction::query()
            ->with(['account', 'destinationAccount', 'party', 'category:id,name'])
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('reference_number', 'like', $search)
                        ->orWhere('note', 'like', $search)
                        ->orWhere('currency', 'like', $search)
                        ->orWhereHas('account', fn ($accountQuery) => $accountQuery->where('name', 'like', $search))
                        ->orWhereHas('party', function ($partyQuery) use ($search): void {
                            $partyQuery->where('name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when(
                $this->tagFilter !== '' && $businessId !== null,
                fn ($query) => $query->withAnyTags([$this->tagFilter], Transaction::tagTypeForBusiness($businessId))
            )
            ->when($this->accountFilter !== '', function ($query): void {
                $accountId = (int) $this->accountFilter;

                $query->where(function ($query) use ($accountId): void {
                    $query->where('account_id', $accountId)
                        ->orWhere('destination_account_id', $accountId);
                });
            })
            ->when($this->categoryFilter === 'none', fn ($query) => $query->whereNull('category_id'))
            ->when(ctype_digit($this->categoryFilter), fn ($query) => $query->where('category_id', (int) $this->categoryFilter))
            ->orderBy('transaction_date', $direction)
            ->orderBy('created_at', $direction)
            ->paginate(config('general.per_page', 15));
    }

    public function formatDate(\DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Jalalian::fromDateTime($date)->format('Y/m/d');
    }

    public function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }
};
?>

<x-slot name="title">{{ __('general.transactions') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.transactions') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.transactions') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.transactions_page_hint') }}</flux:text>
            </div>

            <flux:modal.trigger name="transaction.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_transaction') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if (auth()->user()->current_business_id === null)
        <flux:callout icon="building" variant="secondary">
            {{ __('general.no_business_yet') }}
        </flux:callout>
    @endif

    <flux:card>
        <div class="mb-4 space-y-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />

            <flux:accordion transition>
                <flux:accordion.item heading="{{ __('general.filters') }}" :expanded="$this->filtersActive">
                    <div class="grid gap-3 pt-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <flux:select wire:model.live="typeFilter" searchable variant="listbox" placeholder="{{ __('general.all_transaction_types') }}">
                            <flux:select.option value="">{{ __('general.all_transaction_types') }}</flux:select.option>
                            @foreach (TransactionType::cases() as $type)
                                <flux:select.option value="{{ $type->value }}" wire:key="filter-tx-type-{{ $type->value }}">
                                    {{ $type->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="accountFilter" searchable variant="listbox" placeholder="{{ __('general.all_accounts') }}" clearable>
                            <flux:select.option value="">{{ __('general.all_accounts') }}</flux:select.option>
                            @foreach ($this->filterAccounts as $account)
                                <flux:select.option value="{{ $account->id }}" wire:key="filter-tx-account-{{ $account->id }}">
                                    {{ $account->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="tagFilter" searchable variant="listbox" placeholder="{{ __('general.all_tags') }}" clearable>
                            <flux:select.option value="">{{ __('general.all_tags') }}</flux:select.option>
                            @foreach ($this->filterTags as $tagName)
                                <flux:select.option value="{{ $tagName }}" wire:key="filter-tx-tag-{{ $tagName }}">
                                    {{ $tagName }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="categoryFilter" searchable variant="listbox" placeholder="{{ __('general.all_categories') }}" clearable>
                            <flux:select.option value="">{{ __('general.all_categories') }}</flux:select.option>
                            <flux:select.option value="none">{{ __('general.dashboard_uncategorized') }}</flux:select.option>
                            @foreach ($this->filterCategories as $category)
                                <flux:select.option value="{{ $category->id }}" wire:key="filter-tx-category-{{ $category->id }}">
                                    {{ $category->type->label() }} — {{ $category->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="sortDirection" variant="listbox" placeholder="{{ __('general.sort_newest_first') }}">
                            <flux:select.option value="desc">{{ __('general.sort_newest_first') }}</flux:select.option>
                            <flux:select.option value="asc">{{ __('general.sort_oldest_first') }}</flux:select.option>
                        </flux:select>
                    </div>
                </flux:accordion.item>
            </flux:accordion>
        </div>

        @if (count($selected) > 0)
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 dark:border-teal-900 dark:bg-teal-950/40">
                <flux:text class="font-medium text-teal-800 dark:text-teal-200">
                    {{ __('general.selected_count', ['count' => count($selected)]) }}
                </flux:text>

                <flux:spacer />

                <flux:dropdown>
                    <flux:button size="sm" variant="primary" color="teal" icon="chevron-down" icon:trailing>
                        {{ __('general.bulk_actions') }}
                    </flux:button>

                    <flux:menu>
                        <flux:menu.item icon="folders" wire:click="openBulkCategoryModal">
                            {{ __('general.bulk_edit_category') }}
                        </flux:menu.item>
                        <flux:menu.item icon="users" wire:click="openBulkPartyModal">
                            {{ __('general.bulk_edit_party') }}
                        </flux:menu.item>
                        <flux:menu.item icon="tags" wire:click="openBulkTagsModal">
                            {{ __('general.bulk_add_tags') }}
                        </flux:menu.item>
                        <flux:menu.separator />
                        <flux:menu.item variant="danger" icon="trash" wire:click="openBulkDeleteModal">
                            {{ __('general.bulk_delete') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <flux:button size="sm" variant="ghost" wire:click="clearSelection">
                    {{ __('general.bulk_clear_selection') }}
                </flux:button>
            </div>
        @endif

        <flux:table :paginate="$this->transactions">
            <flux:table.columns>
                <flux:table.column class="w-10">
                    <flux:checkbox
                        wire:click.prevent="toggleSelectPage"
                        :checked="$this->pageSelected"
                    />
                </flux:table.column>
                <flux:table.column>{{ __('general.transaction_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.transaction_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.account') }}</flux:table.column>
                <flux:table.column>{{ __('general.party') }}</flux:table.column>
                <flux:table.column>{{ __('general.category') }}</flux:table.column>
                <flux:table.column>{{ __('general.amount') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->transactions as $transaction)
                    <flux:table.row :key="$transaction->id">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" value="{{ $transaction->id }}" />
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatDate($transaction->transaction_date) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$transaction->type->badgeColor()">
                                {{ $transaction->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->account?->name ?? '—' }}
                            @if ($transaction->type === TransactionType::Transfer && $transaction->destinationAccount)
                                <flux:text size="sm" class="mt-0.5 block text-zinc-500">
                                    → {{ $transaction->destinationAccount->name }}
                                </flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->party?->displayName() ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->category?->name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $this->formatAmount((string) $transaction->amount) }} {{ $transaction->currency }}</span>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.transaction.edit.assign-data', { transaction: {{ $transaction->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.transaction.delete.assign-data', { transaction: {{ $transaction->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">
                            {{ __('general.no_transactions') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="transaction.bulk-category" flyout position="right" class="min-w-full sm:min-w-md">
        <form wire:submit="applyBulkCategory" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.bulk_edit_category') }}</flux:heading>
                <flux:text class="mt-2">{{ __('general.selected_count', ['count' => count($selected)]) }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('general.category') }}</flux:label>
                <flux:select
                    wire:model="bulkCategoryId"
                    searchable
                    variant="listbox"
                    placeholder="{{ __('general.select_category') }}"
                >
                    @foreach ($this->bulkCategoryOptions as $option)
                        <flux:select.option
                            value="{{ $option['id'] }}"
                            :disabled="! $option['is_leaf']"
                            wire:key="bulk-tx-category-{{ $option['id'] }}"
                        >
                            {{ $option['type_label'] }} — {{ str_repeat('— ', $option['depth']) }}{{ $option['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="bulkCategoryId" />
            </flux:field>

            <div class="flex">
                <flux:button type="submit" variant="primary" color="orange" class="w-full">
                    {{ __('general.save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="transaction.bulk-party" flyout position="right" class="min-w-full sm:min-w-md">
        <form wire:submit="applyBulkParty" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.bulk_edit_party') }}</flux:heading>
                <flux:text class="mt-2">{{ __('general.selected_count', ['count' => count($selected)]) }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('general.party') }}</flux:label>
                <flux:select
                    wire:model="bulkPartyId"
                    searchable
                    variant="listbox"
                    clearable
                    placeholder="{{ __('general.select_party') }}"
                >
                    @foreach ($this->bulkParties as $party)
                        <flux:select.option value="{{ $party->id }}" wire:key="bulk-tx-party-{{ $party->id }}">
                            {{ $party->displayName() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="bulkPartyId" />
            </flux:field>

            <div class="flex">
                <flux:button type="submit" variant="primary" color="orange" class="w-full">
                    {{ __('general.save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="transaction.bulk-tags" flyout position="right" class="min-w-full sm:min-w-md">
        <form wire:submit="applyBulkTags" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.bulk_add_tags') }}</flux:heading>
                <flux:text class="mt-2">{{ __('general.selected_count', ['count' => count($selected)]) }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('general.tags') }}</flux:label>

                @if ($bulkTagNames !== [])
                    <div class="mb-2 flex flex-wrap gap-1">
                        @foreach ($bulkTagNames as $index => $tagName)
                            <flux:badge size="sm" color="zinc" wire:key="bulk-selected-tag-{{ $index }}-{{ $tagName }}">
                                <span class="inline-flex items-center gap-1">
                                    {{ $tagName }}
                                    <button
                                        type="button"
                                        class="opacity-70 hover:opacity-100"
                                        wire:click="removeBulkTag({{ $index }})"
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
                        wire:model="bulkTagSearch"
                        wire:keydown.enter.prevent="addBulkTag"
                        placeholder="{{ __('general.select_tags') }}"
                        clearable
                        class="flex-1"
                    />
                    <flux:button type="button" variant="primary" color="zinc" icon="plus" wire:click="addBulkTag">
                        {{ __('general.create_tag') }}
                    </flux:button>
                </div>

                @if ($this->suggestedBulkTags->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($this->suggestedBulkTags as $tagName)
                            <flux:button
                                type="button"
                                size="xs"
                                variant="ghost"
                                wire:key="bulk-suggest-tag-{{ $tagName }}"
                                wire:click="addBulkTag({{ Js::from($tagName) }})"
                            >
                                {{ $tagName }}
                            </flux:button>
                        @endforeach
                    </div>
                @endif

                <flux:error name="bulkTagNames" />
            </flux:field>

            <div class="flex">
                <flux:button type="submit" variant="primary" color="orange" class="w-full">
                    {{ __('general.save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="transaction.bulk-delete" class="min-w-[22rem]">
        <form wire:submit="applyBulkDelete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('general.bulk_delete_warning') }}<br>
                    {{ __('general.action_cannot_be_reversed') }}
                </flux:text>
            </div>

            <flux:callout icon="receipt" variant="secondary" inline>
                {{ __('general.selected_count', ['count' => count($selected)]) }}
            </flux:callout>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="danger">{{ __('general.delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <livewire:accounting.transaction.create :key="'transaction-create'" />
    <livewire:accounting.transaction.edit :key="'transaction-edit'" />
    <livewire:accounting.transaction.delete :key="'transaction-delete'" />
</div>
