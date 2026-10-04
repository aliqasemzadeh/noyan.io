<?php

namespace Tests\Feature;

use App\Actions\Ledger\CreateJournalEntryAction;
use App\Actions\Ledger\DeleteJournalEntryAction;
use App\Actions\Ledger\PostJournalEntryAction;
use App\Actions\Ledger\UpdateJournalEntryAction;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\CategoryType;
use App\Models\Accounting\FiscalYear;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateJournalEntryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_balanced_draft_journal_entry(): void
    {
        [$user, $business, $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        $entry = app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Sale voucher',
        ], [
            [
                'category_id' => $debitCategory->id,
                'debit' => '1000000',
                'credit' => '0',
                'description' => 'Receivable',
            ],
            [
                'category_id' => $creditCategory->id,
                'debit' => '0',
                'credit' => '1000000',
                'description' => 'Revenue',
            ],
        ]);

        $this->assertTrue($entry->isDraft());
        $this->assertSame('0001', $entry->voucher_number);
        $this->assertSame(2, $entry->lines()->count());
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'business_id' => $business->id,
            'status' => JournalEntryStatus::Draft->value,
        ]);
    }

    public function test_rejects_unbalanced_journal_entry(): void
    {
        [$user, , $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        $this->expectException(ValidationException::class);

        app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Broken voucher',
        ], [
            [
                'category_id' => $debitCategory->id,
                'debit' => '1000000',
                'credit' => '0',
            ],
            [
                'category_id' => $creditCategory->id,
                'debit' => '0',
                'credit' => '900000',
            ],
        ]);
    }

    public function test_rejects_closed_fiscal_year(): void
    {
        [$user, $business, , $debitCategory, $creditCategory] = $this->seedContext();

        $closedYear = FiscalYear::factory()->closed()->create([
            'business_id' => $business->id,
            'name' => '1403',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $closedYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Closed year',
        ], [
            ['category_id' => $debitCategory->id, 'debit' => '100', 'credit' => '0'],
            ['category_id' => $creditCategory->id, 'debit' => '0', 'credit' => '100'],
        ]);
    }

    public function test_rejects_entry_date_outside_fiscal_year(): void
    {
        [$user, , $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        $this->expectException(ValidationException::class);

        app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->subYear()->toDateString(),
            'description' => 'Out of range',
        ], [
            ['category_id' => $debitCategory->id, 'debit' => '100', 'credit' => '0'],
            ['category_id' => $creditCategory->id, 'debit' => '0', 'credit' => '100'],
        ]);
    }

    public function test_update_and_post_and_delete_draft_lifecycle(): void
    {
        [$user, , $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        $entry = app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Draft',
        ], [
            ['category_id' => $debitCategory->id, 'debit' => '500', 'credit' => '0'],
            ['category_id' => $creditCategory->id, 'debit' => '0', 'credit' => '500'],
        ]);

        $updated = app(UpdateJournalEntryAction::class)->handle($user, $entry, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Updated draft',
        ], [
            ['category_id' => $debitCategory->id, 'debit' => '750', 'credit' => '0'],
            ['category_id' => $creditCategory->id, 'debit' => '0', 'credit' => '750'],
        ]);

        $this->assertSame('Updated draft', $updated->description);
        $this->assertSame(0, bccomp((string) $updated->lines()->sum('debit'), '750', 4));

        $posted = app(PostJournalEntryAction::class)->handle($user, $updated);
        $this->assertTrue($posted->isPosted());

        $this->expectException(ValidationException::class);
        app(DeleteJournalEntryAction::class)->handle($user, $posted);
    }

    public function test_can_delete_draft_journal_entry(): void
    {
        [$user, , $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        $entry = app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'To delete',
        ], [
            ['category_id' => $debitCategory->id, 'debit' => '200', 'credit' => '0'],
            ['category_id' => $creditCategory->id, 'debit' => '0', 'credit' => '200'],
        ]);

        app(DeleteJournalEntryAction::class)->handle($user, $entry);

        $this->assertSoftDeleted('journal_entries', [
            'id' => $entry->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Business, 2: FiscalYear, 3: Category, 4: Category}
     */
    protected function seedContext(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $year = (int) now()->format('Y');
        $fiscalYear = FiscalYear::factory()->create([
            'business_id' => $business->id,
            'name' => (string) $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_closed' => false,
        ]);

        $debitCategory = Category::factory()->create([
            'business_id' => $business->id,
            'type' => CategoryType::Asset,
            'name' => 'Accounts Receivable',
            'slug' => 'ar-'.$business->id,
        ]);

        $creditCategory = Category::factory()->income()->create([
            'business_id' => $business->id,
            'name' => 'Sales Revenue',
            'slug' => 'sales-'.$business->id,
        ]);

        return [$user, $business, $fiscalYear, $debitCategory, $creditCategory];
    }
}
