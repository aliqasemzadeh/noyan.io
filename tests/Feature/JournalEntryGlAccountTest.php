<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Ledger\CreateJournalEntryAction;
use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalEntryGlAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_journal_entry_with_gl_accounts_and_required_party_detail(): void
    {
        [$user, $business, $fiscalYear] = $this->seedContext();

        $receivable = GlAccount::findBySystemKey($business, 'accounts_receivable');
        $sales = GlAccount::findBySystemKey($business, 'sales');
        $this->assertNotNull($receivable);
        $this->assertNotNull($sales);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'Customer A',
        ]);

        $entry = app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'GL sale voucher',
        ], [
            [
                'gl_account_id' => $receivable->id,
                'party_id' => $party->id,
                'debit' => '500',
                'credit' => '0',
            ],
            [
                'gl_account_id' => $sales->id,
                'debit' => '0',
                'credit' => '500',
            ],
        ]);

        $this->assertTrue($entry->isDraft());
        $debitLine = $entry->lines->firstWhere('gl_account_id', $receivable->id);
        $this->assertNotNull($debitLine);
        $this->assertSame($party->id, $debitLine->party_id);
        $this->assertNotNull($debitLine->detail_level_4_id);
        $this->assertSame(JournalEntryStatus::Draft, $entry->status);
    }

    public function test_rejects_receivable_line_without_party_detail(): void
    {
        [$user, $business, $fiscalYear] = $this->seedContext();

        $receivable = GlAccount::findBySystemKey($business, 'accounts_receivable');
        $sales = GlAccount::findBySystemKey($business, 'sales');

        $this->expectException(ValidationException::class);

        app(CreateJournalEntryAction::class)->handle($user, [
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Missing party',
        ], [
            [
                'gl_account_id' => $receivable->id,
                'debit' => '500',
                'credit' => '0',
            ],
            [
                'gl_account_id' => $sales->id,
                'debit' => '0',
                'credit' => '500',
            ],
        ]);
    }

    /**
     * @return array{0: User, 1: Business, 2: FiscalYear}
     */
    protected function seedContext(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        $fiscalYear = FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        return [$user, $business, $fiscalYear];
    }
}
