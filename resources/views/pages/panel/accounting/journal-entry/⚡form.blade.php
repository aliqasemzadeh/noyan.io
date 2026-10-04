<?php

use App\Actions\Ledger\CreateJournalEntryAction;
use App\Actions\Ledger\PostJournalEntryAction;
use App\Actions\Ledger\UpdateJournalEntryAction;
use App\Enums\CategoryType;
use App\Livewire\Forms\Accounting\JournalEntryForm;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Party;
use App\Models\Accounting\Project;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public JournalEntryForm $form;

    public function mount(?JournalEntry $journalEntry = null): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if ($journalEntry !== null) {
            abort_unless((int) $journalEntry->business_id === (int) Auth::user()?->current_business_id, 404);
            abort_unless($journalEntry->isDraft(), 403);

            $journalEntry->load('lines');
            $this->form->setJournalEntry($journalEntry);

            return;
        }

        $businessId = Auth::user()?->current_business_id;
        $defaultFiscalYearId = null;

        if ($businessId !== null) {
            $defaultFiscalYearId = FiscalYear::query()
                ->where('business_id', $businessId)
                ->orderByRaw('is_closed asc')
                ->orderByDesc('start_date')
                ->value('id');
        }

        $this->form->initializeDefaults($defaultFiscalYearId !== null ? (int) $defaultFiscalYearId : null);
    }

    public function addLine(): void
    {
        $this->form->addLine();
    }

    public function removeLine(int $index): void
    {
        $this->form->removeLine($index);
    }

    public function saveAsDraft(CreateJournalEntryAction $createAction, UpdateJournalEntryAction $updateAction): void
    {
        $entry = $this->form->saveAsDraft($createAction, $updateAction);

        Flux::toast(__('general.journal_entry_draft_saved', ['number' => $entry->voucher_number]));

        $this->redirect(route('accounting.journal-entries.edit', $entry), navigate: true);
    }

    public function saveAndPost(
        CreateJournalEntryAction $createAction,
        UpdateJournalEntryAction $updateAction,
        PostJournalEntryAction $postAction,
    ): void {
        $entry = $this->form->saveAndPost($createAction, $updateAction, $postAction);

        Flux::toast(__('general.journal_entry_posted', ['number' => $entry->voucher_number]));

        $this->redirect(route('accounting.journal-entries.index'), navigate: true);
    }

    /**
     * @return Collection<int, FiscalYear>
     */
    #[Computed]
    public function fiscalYears(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return FiscalYear::query()
            ->where('business_id', $businessId)
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * @return Collection<int, array{id: int, name: string, path: string, depth: int, is_leaf: bool, is_system: bool}>
     */
    #[Computed]
    public function categoryOptions(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return collect(CategoryType::accountingCases())
            ->flatMap(fn (CategoryType $type) => Category::selectOptionsForBusiness($businessId, $type))
            ->filter(fn (array $option): bool => $option['is_leaf'])
            ->values();
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

        return Party::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name']);
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
     * @return Collection<int, CostCenter>
     */
    #[Computed]
    public function costCenters(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return CostCenter::cachedOptionsForBusiness($businessId);
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Project::cachedOptionsForBusiness($businessId);
    }

    public function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }

    public function heading(): string
    {
        if ($this->form->journalEntry !== null) {
            return __('general.edit_journal_entry', ['number' => $this->form->journalEntry->voucher_number]);
        }

        return __('general.create_journal_entry');
    }
};
?>

<x-slot name="title">{{ $this->heading() }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.journal-entries.index')" wire:navigate>
                {{ __('general.journal_entries') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $this->heading() }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $this->heading() }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.journal_entry_form_hint') }}</flux:text>
            </div>

            <flux:button variant="ghost" :href="route('accounting.journal-entries.index')" wire:navigate icon="arrow-left">
                {{ __('general.back') }}
            </flux:button>
        </div>
    </div>

    @if ($this->fiscalYears->isEmpty())
        <flux:callout icon="calendar" variant="warning">
            {{ __('general.journal_needs_fiscal_year') }}
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" color="teal" :href="route('accounting.fiscal-years.index')" wire:navigate>
                    {{ __('general.fiscal_years') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <form wire:submit="saveAsDraft" class="space-y-6">
        <flux:card class="space-y-4">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('general.fiscal_year') }}</flux:label>
                    <flux:select wire:model="form.fiscal_year_id" searchable variant="listbox" placeholder="{{ __('general.select_fiscal_year') }}">
                        @foreach ($this->fiscalYears as $fiscalYear)
                            <flux:select.option value="{{ $fiscalYear->id }}" wire:key="form-fy-{{ $fiscalYear->id }}">
                                {{ $fiscalYear->name }}
                                @if ($fiscalYear->is_closed)
                                    ({{ __('general.fiscal_year_closed_badge') }})
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.fiscal_year_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.journal_entry_date') }}</flux:label>
                    <flux:input type="date" wire:model="form.entry_date" />
                    <flux:error name="form.entry_date" />
                </flux:field>

                <flux:field class="md:col-span-3">
                    <flux:label>{{ __('general.description') }}</flux:label>
                    <flux:textarea wire:model="form.description" rows="2" />
                    <flux:error name="form.description" />
                </flux:field>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('general.journal_lines') }}</flux:heading>
                <flux:button type="button" size="sm" variant="primary" color="zinc" icon="plus" wire:click="addLine">
                    {{ __('general.add_journal_line') }}
                </flux:button>
            </div>

            <flux:error name="form.lines" />
            <flux:error name="lines" />

            <div class="space-y-4">
                @foreach ($form->lines as $index => $line)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="journal-line-{{ $line['id'] }}">
                        <div class="mb-3 flex items-center justify-between">
                            <flux:text class="font-medium">{{ __('general.journal_line') }} #{{ $index + 1 }}</flux:text>
                            @if (count($form->lines) > 2)
                                <flux:button
                                    type="button"
                                    size="xs"
                                    variant="danger"
                                    icon="trash"
                                    icon:variant="outline"
                                    wire:click="removeLine({{ $index }})"
                                />
                            @endif
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <flux:field class="md:col-span-2 xl:col-span-3">
                                <flux:label>{{ __('general.category') }}</flux:label>
                                <flux:select
                                    wire:model="form.lines.{{ $index }}.category_id"
                                    searchable
                                    variant="listbox"
                                    placeholder="{{ __('general.select_category') }}"
                                >
                                    @foreach ($this->categoryOptions as $option)
                                        <flux:select.option value="{{ $option['id'] }}" wire:key="line-{{ $index }}-cat-{{ $option['id'] }}">
                                            {{ $option['path'] }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="form.lines.{{ $index }}.category_id" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('general.party') }}</flux:label>
                                <flux:select
                                    wire:model="form.lines.{{ $index }}.party_id"
                                    searchable
                                    variant="listbox"
                                    placeholder="{{ __('general.optional') }}"
                                    clearable
                                >
                                    @foreach ($this->parties as $party)
                                        <flux:select.option value="{{ $party->id }}" wire:key="line-{{ $index }}-party-{{ $party->id }}">
                                            {{ $party->displayName() }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('general.cash_and_bank_accounts') }}</flux:label>
                                <flux:select
                                    wire:model="form.lines.{{ $index }}.account_id"
                                    searchable
                                    variant="listbox"
                                    placeholder="{{ __('general.optional') }}"
                                    clearable
                                >
                                    @foreach ($this->accounts as $account)
                                        <flux:select.option value="{{ $account->id }}" wire:key="line-{{ $index }}-account-{{ $account->id }}">
                                            {{ $account->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('general.cost_center') }}</flux:label>
                                <flux:select
                                    wire:model="form.lines.{{ $index }}.cost_center_id"
                                    searchable
                                    variant="listbox"
                                    placeholder="{{ __('general.optional') }}"
                                    clearable
                                >
                                    @foreach ($this->costCenters as $costCenter)
                                        <flux:select.option value="{{ $costCenter->id }}" wire:key="line-{{ $index }}-cc-{{ $costCenter->id }}">
                                            {{ $costCenter->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('general.project') }}</flux:label>
                                <flux:select
                                    wire:model="form.lines.{{ $index }}.project_id"
                                    searchable
                                    variant="listbox"
                                    placeholder="{{ __('general.optional') }}"
                                    clearable
                                >
                                    @foreach ($this->projects as $project)
                                        <flux:select.option value="{{ $project->id }}" wire:key="line-{{ $index }}-project-{{ $project->id }}">
                                            {{ $project->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('general.debit') }}</flux:label>
                                <flux:input wire:model.live.debounce.200ms="form.lines.{{ $index }}.debit" dir="ltr" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('general.credit') }}</flux:label>
                                <flux:input wire:model.live.debounce.200ms="form.lines.{{ $index }}.credit" dir="ltr" />
                            </flux:field>

                            <flux:field class="md:col-span-2 xl:col-span-3">
                                <flux:label>{{ __('general.line_description') }}</flux:label>
                                <flux:input wire:model="form.lines.{{ $index }}.description" clearable />
                            </flux:field>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50 md:grid-cols-3">
                <div>
                    <flux:text class="text-sm">{{ __('general.total_debit') }}</flux:text>
                    <div class="mt-1 text-lg font-semibold" dir="ltr">{{ $this->formatAmount($form->totalDebit()) }}</div>
                </div>
                <div>
                    <flux:text class="text-sm">{{ __('general.total_credit') }}</flux:text>
                    <div class="mt-1 text-lg font-semibold" dir="ltr">{{ $this->formatAmount($form->totalCredit()) }}</div>
                </div>
                <div>
                    <flux:text class="text-sm">{{ __('general.difference') }}</flux:text>
                    <div
                        class="mt-1 text-lg font-semibold {{ $form->isBalanced() ? 'text-green-600' : 'text-rose-600' }}"
                        dir="ltr"
                    >
                        {{ $this->formatAmount($form->difference()) }}
                    </div>
                </div>
            </div>
        </flux:card>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:button
                type="submit"
                variant="primary"
                color="zinc"
                class="w-full sm:w-auto"
                :disabled="! $form->isBalanced() || $this->fiscalYears->isEmpty()"
            >
                {{ __('general.save_as_draft') }}
            </flux:button>

            <flux:button
                type="button"
                variant="primary"
                color="teal"
                class="w-full sm:w-auto"
                wire:click="saveAndPost"
                :disabled="! $form->isBalanced() || $this->fiscalYears->isEmpty()"
            >
                {{ __('general.save_and_post') }}
            </flux:button>
        </div>
    </form>
</div>
