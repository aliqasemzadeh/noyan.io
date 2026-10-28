<?php

namespace App\Livewire\Forms\Accounting;

use App\Actions\Ledger\CreateJournalEntryAction;
use App\Actions\Ledger\PostJournalEntryAction;
use App\Actions\Ledger\UpdateJournalEntryAction;
use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\JournalEntry;
use App\Support\Concerns\NormalizesLocaleFormValues;
use App\Support\LocaleDate;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class JournalEntryForm extends Form
{
    use NormalizesLocaleFormValues;

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
        $this->entry_date = LocaleDate::formatInput($entry->entry_date);
        $this->description = (string) $entry->description;
        $entry->loadMissing('lines.details');

        $this->lines = $entry->lines
            ->map(fn ($line): array => [
                'id' => (string) $line->id,
                'category_id' => $line->category_id,
                'gl_account_id' => $line->gl_account_id,
                'details' => $line->details
                    ->mapWithKeys(fn ($detail): array => [$detail->level => $detail->gl_detail_id])
                    ->all(),
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
        $this->entry_date = $this->todayInput();
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
            'gl_account_id' => null,
            'details' => [],
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

    public function duplicateLine(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $source = $this->lines[$index];
        $copy = [
            'id' => (string) Str::uuid(),
            'category_id' => $source['category_id'] ?? null,
            'gl_account_id' => $source['gl_account_id'] ?? null,
            'details' => is_array($source['details'] ?? null) ? $source['details'] : [],
            'debit' => (string) ($source['debit'] ?? '0'),
            'credit' => (string) ($source['credit'] ?? '0'),
            'description' => (string) ($source['description'] ?? ''),
        ];

        array_splice($this->lines, $index + 1, 0, [$copy]);
        $this->lines = array_values($this->lines);
    }

    public function swapLineAmounts(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $debit = (string) ($this->lines[$index]['debit'] ?? '0');
        $credit = (string) ($this->lines[$index]['credit'] ?? '0');

        $this->lines[$index]['debit'] = $credit;
        $this->lines[$index]['credit'] = $debit;
    }

    public function balanceLine(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $otherDebit = '0';
        $otherCredit = '0';

        foreach ($this->lines as $i => $line) {
            if ($i === $index) {
                continue;
            }

            $otherDebit = bcadd($otherDebit, $this->normalizeAmount((string) ($line['debit'] ?? '0')), 4);
            $otherCredit = bcadd($otherCredit, $this->normalizeAmount((string) ($line['credit'] ?? '0')), 4);
        }

        $diff = bcsub($otherDebit, $otherCredit, 4);
        $cmp = bccomp($diff, '0', 4);

        if ($cmp === 1) {
            $this->lines[$index]['debit'] = '0';
            $this->lines[$index]['credit'] = $this->formatAmount($diff);
        } elseif ($cmp === -1) {
            $absDiff = ltrim($diff, '-');
            $this->lines[$index]['debit'] = $this->formatAmount($absDiff);
            $this->lines[$index]['credit'] = '0';
        } else {
            $this->lines[$index]['debit'] = '0';
            $this->lines[$index]['credit'] = '0';
        }
    }

    public function clearOppositeSide(int $index, string $changedSide): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $value = $this->normalizeAmount((string) ($this->lines[$index][$changedSide] ?? '0'));

        if (bccomp($value, '0', 4) === 1) {
            $opposite = $changedSide === 'debit' ? 'credit' : 'debit';
            $this->lines[$index][$opposite] = '0';
        }
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
            'entry_date' => $this->localeDateRules(),
            'description' => ['required', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.category_id' => ['nullable', 'integer'],
            'lines.*.gl_account_id' => ['nullable', 'integer'],
            'lines.*.details' => ['nullable', 'array'],
            'lines.*.details.*' => ['nullable', 'integer'],
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
        foreach ($this->lines as $index => $line) {
            $this->lines[$index]['debit'] = Money::normalize((string) ($line['debit'] ?? '0'));
            $this->lines[$index]['credit'] = Money::normalize((string) ($line['credit'] ?? '0'));
        }

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
            'entry_date' => LocaleDate::toStorageDate($this->entry_date) ?? $this->entry_date,
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
            'gl_account_id' => $line['gl_account_id'] ?? null,
            'details' => $line['details'] ?? [],
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
        $value = Money::normalize($value);

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 4);
    }
}
