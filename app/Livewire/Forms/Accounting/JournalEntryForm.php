<?php

namespace App\Livewire\Forms\Accounting;

use App\Actions\Ledger\CreateJournalEntryAction;
use App\Actions\Ledger\PostJournalEntryAction;
use App\Actions\Ledger\UpdateJournalEntryAction;
use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\JournalEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class JournalEntryForm extends Form
{
    public ?JournalEntry $journalEntry = null;

    public ?int $fiscal_year_id = null;

    public string $entry_date = '';

    public string $description = '';

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public function setJournalEntry(JournalEntry $entry): void
    {
        $this->journalEntry = $entry;
        $this->fiscal_year_id = $entry->fiscal_year_id;
        $this->entry_date = $entry->entry_date?->format('Y-m-d') ?? '';
        $this->description = (string) $entry->description;
        $this->lines = $entry->lines
            ->map(fn ($line): array => [
                'id' => (string) $line->id,
                'category_id' => $line->category_id,
                'party_id' => $line->party_id,
                'account_id' => $line->account_id,
                'cost_center_id' => $line->cost_center_id,
                'project_id' => $line->project_id,
                'debit' => $this->formatAmount((string) $line->debit),
                'credit' => $this->formatAmount((string) $line->credit),
                'description' => (string) ($line->description ?? ''),
            ])
            ->values()
            ->all();

        if (count($this->lines) < 2) {
            while (count($this->lines) < 2) {
                $this->addLine();
            }
        }
    }

    public function initializeDefaults(?int $fiscalYearId = null): void
    {
        $this->fiscal_year_id = $fiscalYearId;
        $this->entry_date = now()->toDateString();
        $this->description = '';
        $this->lines = [];
        $this->addLine();
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'id' => (string) Str::uuid(),
            'category_id' => null,
            'party_id' => null,
            'account_id' => null,
            'cost_center_id' => null,
            'project_id' => null,
            'debit' => '0',
            'credit' => '0',
            'description' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 2) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function totalDebit(): string
    {
        $total = '0';

        foreach ($this->lines as $line) {
            $total = bcadd($total, $this->normalizeAmount((string) ($line['debit'] ?? '0')), 4);
        }

        return $total;
    }

    public function totalCredit(): string
    {
        $total = '0';

        foreach ($this->lines as $line) {
            $total = bcadd($total, $this->normalizeAmount((string) ($line['credit'] ?? '0')), 4);
        }

        return $total;
    }

    public function difference(): string
    {
        return bcsub($this->totalDebit(), $this->totalCredit(), 4);
    }

    public function isBalanced(): bool
    {
        return bccomp($this->difference(), '0', 4) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'fiscal_year_id' => [
                'required',
                'integer',
                Rule::exists('fiscal_years', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at')),
            ],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.category_id' => ['nullable', 'integer'],
            'lines.*.party_id' => ['nullable', 'integer'],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.cost_center_id' => ['nullable', 'integer'],
            'lines.*.project_id' => ['nullable', 'integer'],
            'lines.*.debit' => ['nullable', 'string'],
            'lines.*.credit' => ['nullable', 'string'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'fiscal_year_id' => __('general.fiscal_year'),
            'entry_date' => __('general.journal_entry_date'),
            'description' => __('general.description'),
            'lines' => __('general.journal_lines'),
        ];
    }

    public function saveAsDraft(CreateJournalEntryAction $createAction, UpdateJournalEntryAction $updateAction): JournalEntry
    {
        $this->validate();

        if (! $this->isBalanced()) {
            throw ValidationException::withMessages([
                'lines' => [__('general.journal_entry_out_of_balance')],
            ]);
        }

        $user = Auth::user();

        if ($user === null) {
            throw ValidationException::withMessages([
                'description' => [__('general.business_required')],
            ]);
        }

        $payload = $this->entryPayload();
        $lines = $this->linesPayload();

        if ($this->journalEntry !== null) {
            return $updateAction->handle($user, $this->journalEntry, $payload, $lines);
        }

        return $createAction->handle($user, [
            ...$payload,
            'status' => JournalEntryStatus::Draft,
        ], $lines);
    }

    public function saveAndPost(
        CreateJournalEntryAction $createAction,
        UpdateJournalEntryAction $updateAction,
        PostJournalEntryAction $postAction,
    ): JournalEntry {
        $entry = $this->saveAsDraft($createAction, $updateAction);

        return $postAction->handle(Auth::user(), $entry);
    }

    /**
     * @return array{fiscal_year_id: int, entry_date: string, description: string}
     */
    protected function entryPayload(): array
    {
        return [
            'fiscal_year_id' => (int) $this->fiscal_year_id,
            'entry_date' => $this->entry_date,
            'description' => trim($this->description),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function linesPayload(): array
    {
        return array_map(fn (array $line): array => [
            'category_id' => $line['category_id'] ?? null,
            'party_id' => $line['party_id'] ?? null,
            'account_id' => $line['account_id'] ?? null,
            'cost_center_id' => $line['cost_center_id'] ?? null,
            'project_id' => $line['project_id'] ?? null,
            'debit' => $line['debit'] ?? '0',
            'credit' => $line['credit'] ?? '0',
            'description' => $line['description'] ?? null,
        ], $this->lines);
    }

    protected function formatAmount(string $value): string
    {
        $normalized = rtrim(rtrim($value, '0'), '.') ?: '0';

        return $normalized;
    }

    protected function normalizeAmount(string $value): string
    {
        $value = trim(str_replace(',', '', $value));

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 4);
    }
}
