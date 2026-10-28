<?php

use App\Actions\Ledger\CreateJournalEntryAction;
use App\Actions\Ledger\PostJournalEntryAction;
use App\Actions\Ledger\UpdateJournalEntryAction;
use App\Enums\CategoryType;
use App\Livewire\Forms\Accounting\JournalEntryForm;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\GlDetail;
use App\Models\Accounting\JournalEntry;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public JournalEntryForm $form;

    public ?int $activeLineIndex = null;

    public function mount(?JournalEntry $journalEntry = null): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if ($journalEntry !== null) {
            abort_unless((int) $journalEntry->business_id === (int) Auth::user()?->current_business_id, 404);
            abort_unless($journalEntry->isOpen(), 403);
            abort_if($journalEntry->isSystemGenerated(), 403, __('general.journal_system_generated_locked'));

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

    public function updatedFormLines(mixed $value, string $key): void
    {
        if (preg_match('/^(\d+)\.(debit|credit)$/', $key, $matches) === 1) {
            $this->form->clearOppositeSide((int) $matches[1], $matches[2]);
        }
    }

    public function addLine(): void
    {
        $this->form->addLine();
    }

    public function removeLine(int $index): void
    {
        if ($this->activeLineIndex === $index) {
            $this->activeLineIndex = null;
        }

        $this->form->removeLine($index);
    }

    public function duplicateLine(int $index): void
    {
        $this->form->duplicateLine($index);
    }

    public function swapLineAmounts(int $index): void
    {
        $this->form->swapLineAmounts($index);
    }

    public function balanceLine(int $index): void
    {
        $this->form->balanceLine($index);
    }

    public function openLineSettings(int $index): void
    {
        if (! isset($this->form->lines[$index])) {
            return;
        }

        $this->activeLineIndex = $index;
        Flux::modal('journal-line-settings')->show();
    }

    public function closeLineSettings(): void
    {
        Flux::modal('journal-line-settings')->close();
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
     * @return Collection<int, GlAccount>
     */
    #[Computed]
    public function glAccounts(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return GlAccount::query()
            ->with('detailLevels')
            ->where('business_id', $businessId)
            ->postable()
            ->active()
            ->orderBy('full_code')
            ->get(['id', 'full_code', 'name']);
    }

    /**
     * @return Collection<int, GlDetail>
     */
    #[Computed]
    public function glDetails(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return GlDetail::query()
            ->where('business_id', $businessId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'type', 'name']);
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

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <flux:heading size="xl">{{ $this->heading() }}</flux:heading>

                    @if ($form->journalEntry !== null)
                        <flux:badge size="sm" :color="$form->journalEntry->status->badgeColor()">
                            {{ $form->journalEntry->status->label() }}
                        </flux:badge>
                    @endif

                    <flux:badge size="sm" :color="$form->isBalanced() ? 'green' : 'rose'" :icon="$form->isBalanced() ? 'circle-check' : 'scale'">
                        {{ $form->isBalanced() ? __('general.journal_balanced_badge') : __('general.journal_unbalanced_badge') }}
                    </flux:badge>

                    <flux:badge size="sm" color="zinc" icon="layers">
                        {{ __('general.journal_lines_count', ['count' => count($form->lines)]) }}
                    </flux:badge>
                </div>
                <flux:text class="mt-1">{{ __('general.journal_entry_form_hint') }}</flux:text>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                <flux:button variant="ghost" :href="route('accounting.journal-entries.index')" wire:navigate icon="arrow-left">
                    {{ __('general.back') }}
                </flux:button>
            </div>
        </div>
    </div>

    @if ($this->fiscalYears->isEmpty())
        <flux:callout icon="calendar" variant="warning">
            {{ __('general.journal_needs_fiscal_year') }}
            <div class="mt-3">
                <flux:button size="sm" variant="primary" color="teal" :href="route('accounting.fiscal-years.index')" wire:navigate>
                    {{ __('general.fiscal_years') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <form wire:submit="saveAsDraft" class="space-y-6">
        {{-- Header Card: 12-Column Responsive Layout without Overflow --}}
        <flux:card>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-12 lg:items-start">
                <div class="min-w-0 lg:col-span-3">
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
                </div>

                <div class="min-w-0 lg:col-span-3">
                    @if (\App\Support\LocaleDate::usesJalali())
                        <x-date-picker wire:model="form.entry_date" name="form.entry_date" :label="__('general.journal_entry_date')" required />
                    @else
                        <flux:field>
                            <flux:label>{{ __('general.journal_entry_date') }}</flux:label>
                            <flux:input type="date" wire:model="form.entry_date" />
                            <flux:error name="form.entry_date" />
                        </flux:field>
                    @endif
                </div>

                <div class="min-w-0 md:col-span-2 lg:col-span-6">
                    <flux:field>
                        <flux:label>{{ __('general.description') }}</flux:label>
                        <flux:input
                            wire:model="form.description"
                            placeholder="{{ __('general.journal_description_placeholder') }}"
                            clearable
                        />
                        <flux:error name="form.description" />
                    </flux:field>
                </div>
            </div>
        </flux:card>

        {{-- Journal Lines Card: Multi-column Grid without Horizontal Scroll --}}
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('general.journal_lines') }}</flux:heading>
                    <flux:badge size="sm" color="zinc">{{ count($form->lines) }}</flux:badge>
                </div>

                <flux:button type="button" size="sm" variant="primary" color="indigo" icon="plus" wire:click="addLine">
                    {{ __('general.add_journal_line') }}
                </flux:button>
            </div>

            <flux:error name="form.lines" />
            <flux:error name="lines" />

            {{-- Desktop Column Headers --}}
            <div class="hidden rounded-lg bg-zinc-100/80 px-3 py-2.5 text-xs font-semibold text-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-300 xl:grid xl:grid-cols-12 xl:items-center xl:gap-3">
                <div class="col-span-4 flex items-center gap-2 min-w-0">
                    <span class="w-6 text-center">#</span>
                    <span>{{ __('general.gl_account') }}</span>
                </div>
                <div class="col-span-3 min-w-0">{{ __('general.line_description') }}</div>
                <div class="col-span-2 min-w-0">{{ __('general.debit') }}</div>
                <div class="col-span-2 min-w-0">{{ __('general.credit') }}</div>
                <div class="col-span-1 text-end">{{ __('general.actions') }}</div>
            </div>

            <div class="divide-y divide-zinc-200/80 rounded-xl border border-zinc-200 dark:divide-zinc-700/80 dark:border-zinc-700">
                @foreach ($form->lines as $index => $line)
                    @php
                        $lineAccountId = (int) ($line['gl_account_id'] ?? 0);
                        $lineAccount = $lineAccountId > 0 ? $this->glAccounts->firstWhere('id', $lineAccountId) : null;
                        $detailLevels = $lineAccount?->detailLevels ?? collect();
                        $selectedCategoryId = (int) ($line['category_id'] ?? 0);
                        $selectedCategory = $selectedCategoryId > 0 ? $this->categoryOptions->firstWhere('id', $selectedCategoryId) : null;
                    @endphp

                    <div
                        class="p-3 transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 sm:p-4"
                        wire:key="journal-line-{{ $line['id'] }}"
                    >
                        {{-- Mobile Header --}}
                        <div class="mb-3 flex items-center justify-between xl:hidden">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex size-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $index + 1 }}
                                </span>
                                <flux:text class="text-sm font-medium">{{ __('general.journal_line') }} #{{ $index + 1 }}</flux:text>
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip content="{{ __('general.journal_line_settings') }}">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        color="violet"
                                        icon="sliders-horizontal"
                                        icon:variant="outline"
                                        wire:click="openLineSettings({{ $index }})"
                                    />
                                </flux:tooltip>

                                <flux:tooltip content="{{ __('general.journal_balance_line') }}">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        color="amber"
                                        icon="scale"
                                        icon:variant="outline"
                                        wire:click="balanceLine({{ $index }})"
                                    />
                                </flux:tooltip>

                                <flux:tooltip content="{{ __('general.journal_swap_amounts') }}">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        color="sky"
                                        icon="arrow-left-right"
                                        icon:variant="outline"
                                        wire:click="swapLineAmounts({{ $index }})"
                                    />
                                </flux:tooltip>

                                <flux:tooltip content="{{ __('general.journal_duplicate_line') }}">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        color="zinc"
                                        icon="copy"
                                        icon:variant="outline"
                                        wire:click="duplicateLine({{ $index }})"
                                    />
                                </flux:tooltip>

                                @if (count($form->lines) > 2)
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button
                                            type="button"
                                            size="xs"
                                            variant="primary"
                                            color="red"
                                            icon="trash"
                                            icon:variant="outline"
                                            wire:click="removeLine({{ $index }})"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </div>

                        {{-- Primary Row Columns --}}
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-12 xl:items-start">
                            {{-- Account Column --}}
                            <div class="min-w-0 md:col-span-2 xl:col-span-4">
                                <div class="flex items-start gap-2">
                                    <span class="mt-2 hidden size-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 xl:inline-flex">
                                        {{ $index + 1 }}
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <flux:field>
                                            <flux:label class="xl:sr-only">{{ __('general.gl_account') }}</flux:label>
                                            <flux:select
                                                wire:model.live="form.lines.{{ $index }}.gl_account_id"
                                                searchable
                                                variant="listbox"
                                                placeholder="{{ __('general.select_gl_account') }}"
                                                clearable
                                            >
                                                @foreach ($this->glAccounts as $glAccount)
                                                    <flux:select.option value="{{ $glAccount->id }}" wire:key="line-{{ $index }}-gl-{{ $glAccount->id }}">
                                                        <span dir="ltr">{{ $glAccount->full_code }}</span> — {{ $glAccount->name }}
                                                    </flux:select.option>
                                                @endforeach
                                            </flux:select>
                                            <flux:error name="form.lines.{{ $index }}.gl_account_id" />
                                        </flux:field>
                                    </div>
                                </div>
                            </div>

                            {{-- Description Column --}}
                            <div class="min-w-0 md:col-span-2 xl:col-span-3">
                                <flux:field>
                                    <flux:label class="xl:sr-only">{{ __('general.line_description') }}</flux:label>
                                    <flux:input
                                        wire:model="form.lines.{{ $index }}.description"
                                        placeholder="{{ __('general.journal_line_description_placeholder') }}"
                                        clearable
                                    />
                                    <flux:error name="form.lines.{{ $index }}.description" />
                                </flux:field>
                            </div>

                            {{-- Debit Column --}}
                            <div class="min-w-0 xl:col-span-2">
                                <flux:field>
                                    <flux:label class="xl:sr-only">{{ __('general.debit') }}</flux:label>
                                    <flux:input
                                        wire:model.live.debounce.250ms="form.lines.{{ $index }}.debit"
                                        mask:dynamic="$money($input)"
                                        dir="ltr"
                                        placeholder="0"
                                    />
                                    <flux:error name="form.lines.{{ $index }}.debit" />
                                </flux:field>
                            </div>

                            {{-- Credit Column --}}
                            <div class="min-w-0 xl:col-span-2">
                                <flux:field>
                                    <flux:label class="xl:sr-only">{{ __('general.credit') }}</flux:label>
                                    <flux:input
                                        wire:model.live.debounce.250ms="form.lines.{{ $index }}.credit"
                                        mask:dynamic="$money($input)"
                                        dir="ltr"
                                        placeholder="0"
                                    />
                                    <flux:error name="form.lines.{{ $index }}.credit" />
                                </flux:field>
                            </div>

                            {{-- Desktop Row Actions --}}
                            <div class="hidden items-center justify-end gap-1 pt-1 xl:col-span-1 xl:flex">
                                <flux:tooltip content="{{ __('general.journal_line_settings') }}">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        color="violet"
                                        icon="sliders-horizontal"
                                        icon:variant="outline"
                                        wire:click="openLineSettings({{ $index }})"
                                    />
                                </flux:tooltip>

                                <flux:tooltip content="{{ __('general.journal_balance_line') }}">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        color="amber"
                                        icon="scale"
                                        icon:variant="outline"
                                        wire:click="balanceLine({{ $index }})"
                                    />
                                </flux:tooltip>

                                <flux:tooltip content="{{ __('general.journal_swap_amounts') }}">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        color="sky"
                                        icon="arrow-left-right"
                                        icon:variant="outline"
                                        wire:click="swapLineAmounts({{ $index }})"
                                    />
                                </flux:tooltip>

                                <flux:tooltip content="{{ __('general.journal_duplicate_line') }}">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        color="zinc"
                                        icon="copy"
                                        icon:variant="outline"
                                        wire:click="duplicateLine({{ $index }})"
                                    />
                                </flux:tooltip>

                                @if (count($form->lines) > 2)
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button
                                            type="button"
                                            size="xs"
                                            variant="primary"
                                            color="red"
                                            icon="trash"
                                            icon:variant="outline"
                                            wire:click="removeLine({{ $index }})"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </div>

                        {{-- Multi-column Sub-row for Required Floating Details & Personal Category Summary (No Horizontal Overflow) --}}
                        @if ($detailLevels->isNotEmpty() || $selectedCategory !== null || $errors->has("lines.{$index}*") || $errors->has("form.lines.{$index}*"))
                            <div class="mt-3 rounded-lg bg-zinc-50/90 p-3 dark:bg-zinc-800/50 xl:ms-8">
                                @if ($detailLevels->isNotEmpty())
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                        @foreach ($detailLevels as $requirement)
                                            <div class="min-w-0" wire:key="line-{{ $index }}-level-{{ $requirement->level }}">
                                                <flux:field>
                                                    <flux:label>
                                                        {{ __('general.detail_level_label', ['level' => $requirement->level]) }}
                                                        —
                                                        {{ $requirement->detail_type->label() }}
                                                    </flux:label>
                                                    <flux:select
                                                        wire:model="form.lines.{{ $index }}.details.{{ $requirement->level }}"
                                                        searchable
                                                        variant="listbox"
                                                        placeholder="{{ __('general.select') }}..."
                                                        clearable
                                                    >
                                                        @foreach ($this->glDetails->where('type', $requirement->detail_type) as $detail)
                                                            <flux:select.option value="{{ $detail->id }}" wire:key="line-{{ $index }}-detail-{{ $requirement->level }}-{{ $detail->id }}">
                                                                {{ $detail->name }}
                                                            </flux:select.option>
                                                        @endforeach
                                                    </flux:select>
                                                    <flux:error name="form.lines.{{ $index }}.details.{{ $requirement->level }}" />
                                                    <flux:error name="lines.{{ $index }}.details.{{ $requirement->level }}" />
                                                </flux:field>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($selectedCategory !== null)
                                    <div class="{{ $detailLevels->isNotEmpty() ? 'mt-2.5 pt-2.5 border-t border-zinc-200/70 dark:border-zinc-700/70' : '' }} flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2 min-w-0">
                                            <flux:badge size="sm" color="violet" icon="tag">
                                                {{ __('general.traditional_category') }}: {{ $selectedCategory['path'] }}
                                            </flux:badge>
                                        </div>
                                        <flux:button
                                            type="button"
                                            size="xs"
                                            variant="ghost"
                                            icon="pencil"
                                            wire:click="openLineSettings({{ $index }})"
                                        >
                                            {{ __('general.edit') }}
                                        </flux:button>
                                    </div>
                                @endif

                                <flux:error name="lines.{{ $index }}" />
                                <flux:error name="form.lines.{{ $index }}" />
                            </div>
                        @else
                            <div class="mt-2 flex flex-wrap items-center gap-2 xl:ms-8">
                                <flux:button
                                    type="button"
                                    size="xs"
                                    variant="ghost"
                                    icon="tag"
                                    wire:click="openLineSettings({{ $index }})"
                                >
                                    {{ __('general.show_traditional_category') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Summary Totals Cards --}}
            <div class="grid grid-cols-1 gap-3 pt-2 md:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm font-medium">{{ __('general.total_debit') }}</flux:text>
                        <flux:badge size="sm" color="sky">{{ __('general.debit') }}</flux:badge>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100" dir="ltr">
                        {{ $this->formatAmount($form->totalDebit()) }}
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm font-medium">{{ __('general.total_credit') }}</flux:text>
                        <flux:badge size="sm" color="indigo">{{ __('general.credit') }}</flux:badge>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100" dir="ltr">
                        {{ $this->formatAmount($form->totalCredit()) }}
                    </div>
                </div>

                <div class="rounded-xl border p-4 {{ $form->isBalanced() ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-800/60 dark:bg-emerald-950/30' : 'border-rose-200 bg-rose-50/60 dark:border-rose-800/60 dark:bg-rose-950/30' }}">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm font-medium">{{ __('general.difference') }}</flux:text>
                        <flux:badge size="sm" :color="$form->isBalanced() ? 'green' : 'rose'" :icon="$form->isBalanced() ? 'circle-check' : 'circle-alert'">
                            {{ $form->isBalanced() ? __('general.journal_balanced_badge') : __('general.journal_unbalanced_badge') }}
                        </flux:badge>
                    </div>
                    <div
                        class="mt-2 text-xl font-bold tracking-tight {{ $form->isBalanced() ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}"
                        dir="ltr"
                    >
                        {{ $this->formatAmount($form->difference()) }}
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- Sticky Action Bar --}}
        <div class="sticky bottom-0 z-10 -mx-1 border-t border-zinc-200 bg-white/95 px-2 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95 sm:static sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none dark:sm:bg-transparent">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <flux:button
                        type="submit"
                        variant="primary"
                        color="orange"
                        icon="save"
                        class="w-full sm:w-auto"
                        :disabled="! $form->isBalanced() || $this->fiscalYears->isEmpty()"
                    >
                        {{ __('general.save_as_draft') }}
                    </flux:button>

                    <flux:button
                        type="button"
                        variant="primary"
                        color="teal"
                        icon="circle-check"
                        class="w-full sm:w-auto"
                        wire:click="saveAndPost"
                        :disabled="! $form->isBalanced() || $this->fiscalYears->isEmpty()"
                    >
                        {{ __('general.save_and_post') }}
                    </flux:button>
                </div>

                <flux:button
                    type="button"
                    variant="primary"
                    color="indigo"
                    icon="plus"
                    class="w-full sm:w-auto"
                    wire:click="addLine"
                >
                    {{ __('general.add_journal_line') }}
                </flux:button>
            </div>
        </div>
    </form>

    {{-- Flyout Modal for Line Settings (Personal Category & All Floating Detail Levels) --}}
    <flux:modal name="journal-line-settings" flyout position="right" class="md:w-96">
        @if ($activeLineIndex !== null && isset($form->lines[$activeLineIndex]))
            @php
                $modalLine = $form->lines[$activeLineIndex];
                $modalAccountId = (int) ($modalLine['gl_account_id'] ?? 0);
                $modalAccount = $modalAccountId > 0 ? $this->glAccounts->firstWhere('id', $modalAccountId) : null;
                $modalDetailLevels = $modalAccount?->detailLevels ?? collect();
            @endphp

            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('general.journal_line_settings') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('general.journal_line_settings_hint', ['number' => $activeLineIndex + 1]) }}
                    </flux:text>
                </div>

                <flux:field>
                    <flux:label>{{ __('general.gl_account') }}</flux:label>
                    <flux:select
                        wire:model.live="form.lines.{{ $activeLineIndex }}.gl_account_id"
                        searchable
                        variant="listbox"
                        placeholder="{{ __('general.select_gl_account') }}"
                        clearable
                    >
                        @foreach ($this->glAccounts as $glAccount)
                            <flux:select.option value="{{ $glAccount->id }}" wire:key="flyout-gl-{{ $glAccount->id }}">
                                <span dir="ltr">{{ $glAccount->full_code }}</span> — {{ $glAccount->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.lines.{{ $activeLineIndex }}.gl_account_id" />
                </flux:field>

                @if ($modalDetailLevels->isNotEmpty())
                    <div class="space-y-4">
                        @foreach ($modalDetailLevels as $requirement)
                            <flux:field wire:key="flyout-line-{{ $activeLineIndex }}-level-{{ $requirement->level }}">
                                <flux:label>
                                    {{ __('general.detail_level_label', ['level' => $requirement->level]) }}
                                    —
                                    {{ $requirement->detail_type->label() }}
                                </flux:label>
                                <flux:select
                                    wire:model="form.lines.{{ $activeLineIndex }}.details.{{ $requirement->level }}"
                                    searchable
                                    variant="listbox"
                                    placeholder="{{ __('general.select') }}..."
                                    clearable
                                >
                                    @foreach ($this->glDetails->where('type', $requirement->detail_type) as $detail)
                                        <flux:select.option value="{{ $detail->id }}" wire:key="flyout-detail-{{ $requirement->level }}-{{ $detail->id }}">
                                            {{ $detail->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="form.lines.{{ $activeLineIndex }}.details.{{ $requirement->level }}" />
                            </flux:field>
                        @endforeach
                    </div>
                @else
                    <flux:callout icon="layers" variant="secondary">
                        {{ __('general.journal_no_detail_required') }}
                    </flux:callout>
                @endif

                <flux:field>
                    <flux:label>{{ __('general.traditional_category') }}</flux:label>
                    <flux:select
                        wire:model="form.lines.{{ $activeLineIndex }}.category_id"
                        searchable
                        variant="listbox"
                        placeholder="{{ __('general.select_category') }}"
                        clearable
                    >
                        @foreach ($this->categoryOptions as $option)
                            <flux:select.option value="{{ $option['id'] }}" wire:key="flyout-cat-{{ $option['id'] }}">
                                {{ $option['path'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.lines.{{ $activeLineIndex }}.category_id" />
                </flux:field>

                <flux:button
                    type="button"
                    variant="primary"
                    color="orange"
                    class="w-full"
                    wire:click="closeLineSettings"
                >
                    {{ __('general.save') }}
                </flux:button>
            </div>
        @endif
    </flux:modal>
</div>
