<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Ledger\ConvertManualSettlementJournalsAction;
use App\Actions\Ledger\DeleteJournalEntryAction;
use App\Actions\Ledger\PostJournalEntryAction;
use App\Actions\Ledger\SyncTreasuryFromPostedJournalAction;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\TransactionType;
use App\Enums\AccountSubType;
use App\Enums\AccountType;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTreasuryFromPostedJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_transfer_journal_creates_treasury_transfer_and_updates_balances(): void
    {
        [$user, $business, $fiscalYear, $cashGl, $source, $destination] = $this->seedTransferContext();

        $entry = JournalEntry::factory()->create([
            'business_id' => $business->id,
            'fiscal_year_id' => $fiscalYear->id,
            'created_by' => $user->id,
            'status' => JournalEntryStatus::Draft,
            'description' => 'Internal transfer',
            'referenceable_type' => null,
            'referenceable_id' => null,
        ]);

        JournalLine::factory()->debit('1000000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'category_id' => null,
            'account_id' => $destination->id,
            'description' => 'In',
        ]);
        JournalLine::factory()->credit('1000000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'category_id' => null,
            'account_id' => $source->id,
            'description' => 'Out',
        ]);

        $posted = app(PostJournalEntryAction::class)->handle($user, $entry);

        $this->assertTrue($posted->isPosted());
        $this->assertNotNull($posted->referenceable_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $posted->id,
            'deleted_at' => null,
        ]);

        $transaction = Transaction::query()
            ->where('business_id', $business->id)
            ->where('type', TransactionType::Transfer)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame($source->id, (int) $transaction->account_id);
        $this->assertSame($destination->id, (int) $transaction->destination_account_id);
        $this->assertSame(0, bccomp((string) $transaction->amount, '1000000', 18));
        $this->assertSame(0, bccomp((string) $source->fresh()->current_balance, '4000000', 18));
        $this->assertSame(0, bccomp((string) $destination->fresh()->current_balance, '2000000', 18));
    }

    public function test_convert_transfer_with_fee_creates_transfer_and_expense(): void
    {
        [$user, $business, $fiscalYear, $cashGl, $source, $destination] = $this->seedTransferContext(
            sourceOpening: '5000000700',
            destinationOpening: '0',
        );

        $feeGl = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('system_key', 'bank_fees')
            ->first()
            ?? GlAccount::query()
                ->where('business_id', $business->id)
                ->where('name', 'like', '%کارمزد%')
                ->first()
            ?? GlAccount::query()->where('business_id', $business->id)->whereKeyNot($cashGl->id)->firstOrFail();

        $entry = JournalEntry::factory()->posted()->create([
            'business_id' => $business->id,
            'fiscal_year_id' => $fiscalYear->id,
            'created_by' => $user->id,
            'description' => 'Transfer with fee',
            'voucher_number' => 'JE-0012',
            'referenceable_type' => null,
            'referenceable_id' => null,
        ]);

        JournalLine::factory()->debit('4200000700')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'category_id' => null,
            'account_id' => $destination->id,
            'description' => 'In',
        ]);
        JournalLine::factory()->credit('4200000000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'category_id' => null,
            'account_id' => $source->id,
            'description' => 'Out',
        ]);
        JournalLine::factory()->credit('700')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $feeGl->id,
            'category_id' => null,
            'account_id' => null,
            'description' => 'Fee',
        ]);

        $stats = app(ConvertManualSettlementJournalsAction::class)->handle($business, $user);

        $this->assertSame(1, $stats['converted']);
        $this->assertNotNull($entry->fresh()->referenceable_id);

        $transfer = Transaction::query()
            ->where('business_id', $business->id)
            ->where('type', TransactionType::Transfer)
            ->first();
        $fee = Transaction::query()
            ->where('business_id', $business->id)
            ->where('type', TransactionType::Expense)
            ->first();

        $this->assertNotNull($transfer);
        $this->assertNotNull($fee);
        $this->assertSame(0, bccomp((string) $transfer->amount, '4200000000', 18));
        $this->assertSame(0, bccomp((string) $fee->amount, '700', 18));
        $this->assertSame($transfer->id, (int) ($fee->meta['fee_of_transaction_id'] ?? 0));
        $this->assertSame(0, bccomp((string) $source->fresh()->current_balance, '800000000', 18));
        $this->assertSame(0, bccomp((string) $destination->fresh()->current_balance, '4200000000', 18));
    }

    public function test_sync_is_idempotent_for_already_linked_journal(): void
    {
        [$user, $business, $fiscalYear, $cashGl, $source, $destination] = $this->seedTransferContext();

        $entry = JournalEntry::factory()->posted()->create([
            'business_id' => $business->id,
            'fiscal_year_id' => $fiscalYear->id,
            'created_by' => $user->id,
            'referenceable_type' => null,
            'referenceable_id' => null,
        ]);

        JournalLine::factory()->debit('1000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'category_id' => null,
            'account_id' => $destination->id,
        ]);
        JournalLine::factory()->credit('1000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'category_id' => null,
            'account_id' => $source->id,
        ]);

        $first = app(SyncTreasuryFromPostedJournalAction::class)->handle($user, $entry->fresh(['lines.glAccount']));
        $second = app(SyncTreasuryFromPostedJournalAction::class)->handle($user, $entry->fresh(['lines.glAccount']));

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, Transaction::query()->where('business_id', $business->id)->count());
        $this->assertSame(0, bccomp((string) $source->fresh()->current_balance, '4999000', 18));
    }

    public function test_deleting_synced_transfer_journal_soft_deletes_transactions_and_reverses_balances(): void
    {
        [$user, $business, $fiscalYear, $cashGl, $source, $destination] = $this->seedTransferContext();

        $entry = JournalEntry::factory()->posted()->create([
            'business_id' => $business->id,
            'fiscal_year_id' => $fiscalYear->id,
            'created_by' => $user->id,
            'referenceable_type' => null,
            'referenceable_id' => null,
        ]);

        JournalLine::factory()->debit('250000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'category_id' => null,
            'account_id' => $destination->id,
        ]);
        JournalLine::factory()->credit('250000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'category_id' => null,
            'account_id' => $source->id,
        ]);

        $transaction = app(SyncTreasuryFromPostedJournalAction::class)->handle($user, $entry->fresh(['lines.glAccount']));
        $this->assertNotNull($transaction);

        app(DeleteJournalEntryAction::class)->handle($user, $entry->fresh(['referenceable']));

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
        $this->assertSoftDeleted('journal_entries', ['id' => $entry->id]);
        $this->assertSame(0, bccomp((string) $source->fresh()->current_balance, '5000000', 18));
        $this->assertSame(0, bccomp((string) $destination->fresh()->current_balance, '1000000', 18));
    }

    /**
     * @return array{0: User, 1: Business, 2: FiscalYear, 3: GlAccount, 4: Account, 5: Account}
     */
    protected function seedTransferContext(
        string $sourceOpening = '5000000',
        string $destinationOpening = '1000000',
    ): array {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        $settings = app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => AutoPostMode::Realtime,
        ]);

        $fiscalYear = FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
            'is_system' => true,
            'business_id' => null,
        ]);
        $business->activateCurrency($currency, '1', true);

        $cashGl = GlAccount::query()->findOrFail($settings->default_cash_and_bank_id);

        $source = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Source bank',
            'type' => AccountType::Asset,
            'sub_type' => AccountSubType::Bank,
            'opening_balance' => $sourceOpening,
            'current_balance' => $sourceOpening,
        ]);
        $source->syncFloatingDetail();

        $destination = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Destination bank',
            'type' => AccountType::Asset,
            'sub_type' => AccountSubType::Bank,
            'opening_balance' => $destinationOpening,
            'current_balance' => $destinationOpening,
        ]);
        $destination->syncFloatingDetail();

        return [$user, $business, $fiscalYear, $cashGl, $source, $destination];
    }
}
