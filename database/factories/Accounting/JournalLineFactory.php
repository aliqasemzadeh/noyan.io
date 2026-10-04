<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'category_id' => Category::factory(),
            'party_id' => null,
            'account_id' => null,
            'cost_center_id' => null,
            'project_id' => null,
            'debit' => '0',
            'credit' => '0',
            'description' => null,
        ];
    }

    public function debit(string $amount): static
    {
        return $this->state(fn (): array => [
            'debit' => $amount,
            'credit' => '0',
        ]);
    }

    public function credit(string $amount): static
    {
        return $this->state(fn (): array => [
            'debit' => '0',
            'credit' => $amount,
        ]);
    }
}
