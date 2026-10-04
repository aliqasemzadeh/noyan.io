<?php

namespace Tests\Feature;

use App\Actions\Ledger\CreateJournalEntryAction;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\CategoryType;
use App\Models\Accounting\FiscalYear;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JournalEntryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_journal_entries_index(): void
    {
        [$user] = $this->actingBusinessUser();

        $this->actingAs($user)
            ->get(route('accounting.journal-entries.index'))
            ->assertOk();
    }

    public function test_user_can_save_draft_via_form(): void
    {
        [$user, $business, $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.journal-entry.form')
            ->set('form.fiscal_year_id', $fiscalYear->id)
            ->set('form.entry_date', now()->toDateString())
            ->set('form.description', 'Manual voucher')
            ->set('form.lines.0.category_id', $debitCategory->id)
            ->set('form.lines.0.debit', '250000')
            ->set('form.lines.0.credit', '0')
            ->set('form.lines.1.category_id', $creditCategory->id)
            ->set('form.lines.1.debit', '0')
            ->set('form.lines.1.credit', '250000')
            ->call('saveAsDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('journal_entries', [
            'business_id' => $business->id,
            'description' => 'Manual voucher',
            'status' => JournalEntryStatus::Draft->value,
        ]);
    }

    public function test_user_can_post_draft_from_modal(): void
    {
        [$user, , $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        $entry = app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'To post',
        ], [
            ['category_id' => $debitCategory->id, 'debit' => '100', 'credit' => '0'],
            ['category_id' => $creditCategory->id, 'debit' => '0', 'credit' => '100'],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.journal-entry.post')
            ->call('assignData', $entry)
            ->call('post')
            ->assertHasNoErrors();

        $this->assertTrue($entry->fresh()->isPosted());
    }

    public function test_user_can_delete_draft_from_modal(): void
    {
        [$user, , $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        $entry = app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'To delete',
        ], [
            ['category_id' => $debitCategory->id, 'debit' => '100', 'credit' => '0'],
            ['category_id' => $creditCategory->id, 'debit' => '0', 'credit' => '100'],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.journal-entry.delete')
            ->call('assignData', $entry)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('journal_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_posted_entry_cannot_be_edited(): void
    {
        [$user, , $fiscalYear, $debitCategory, $creditCategory] = $this->seedContext();

        $entry = app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Posted',
            'status' => JournalEntryStatus::Posted,
        ], [
            ['category_id' => $debitCategory->id, 'debit' => '100', 'credit' => '0'],
            ['category_id' => $creditCategory->id, 'debit' => '0', 'credit' => '100'],
        ]);

        $this->actingAs($user)
            ->get(route('accounting.journal-entries.edit', $entry))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingBusinessUser(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        return [$user, $business];
    }

    /**
     * @return array{0: User, 1: Business, 2: FiscalYear, 3: Category, 4: Category}
     */
    protected function seedContext(): array
    {
        [$user, $business] = $this->actingBusinessUser();

        $year = (int) now()->format('Y');
        $fiscalYear = FiscalYear::factory()->create([
            'business_id' => $business->id,
            'name' => (string) $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
        ]);

        $debitCategory = Category::factory()->create([
            'business_id' => $business->id,
            'type' => CategoryType::Asset,
            'name' => 'Cash',
            'slug' => 'cash-'.$business->id,
        ]);

        $creditCategory = Category::factory()->income()->create([
            'business_id' => $business->id,
            'name' => 'Income',
            'slug' => 'income-'.$business->id,
        ]);

        return [$user, $business, $fiscalYear, $debitCategory, $creditCategory];
    }
}
