<?php

namespace Tests\Feature;

use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Business;
use App\Models\User;
use App\Services\Accounting\BusinessLedgerSettingsService;
use App\Services\VoucherNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoucherNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_yearly_numbering_increments_within_fiscal_year(): void
    {
        [$business, $fiscalYear, $user] = $this->seedBusinessWithFiscalYear();
        $this->configureNumbering($business, VoucherNumberingMode::Yearly, prefix: null, pad: 4);

        $generator = app(VoucherNumberGenerator::class);

        $first = DB::transaction(fn () => $generator->generate($business->id, $fiscalYear->id, '2026-03-01'));
        $this->assertSame('0001', $first['voucher_number']);
        $this->assertSame(1, $first['sequence_number']);
        $this->assertSame('fy:'.$fiscalYear->id, $first['sequence_scope']);

        $this->createEntry($business, $fiscalYear, $user, $first, '2026-03-01');

        $second = DB::transaction(fn () => $generator->generate($business->id, $fiscalYear->id, '2026-06-15'));
        $this->assertSame('0002', $second['voucher_number']);
        $this->assertSame(2, $second['sequence_number']);
    }

    public function test_monthly_numbering_resets_per_month_and_includes_period(): void
    {
        [$business, $fiscalYear, $user] = $this->seedBusinessWithFiscalYear();
        $this->configureNumbering($business, VoucherNumberingMode::Monthly, prefix: 'JE', pad: 4);

        $generator = app(VoucherNumberGenerator::class);

        $march = DB::transaction(fn () => $generator->generate($business->id, $fiscalYear->id, '2026-03-10'));
        $this->createEntry($business, $fiscalYear, $user, $march, '2026-03-10');

        $april = DB::transaction(fn () => $generator->generate($business->id, $fiscalYear->id, '2026-04-02'));

        $this->assertNotSame($march['sequence_scope'], $april['sequence_scope']);
        $this->assertSame(1, $april['sequence_number']);
        $this->assertStringStartsWith('JE-', $april['voucher_number']);
        $this->assertStringEndsWith('-0001', $april['voucher_number']);
    }

    public function test_manual_numbering_requires_value(): void
    {
        [$business, $fiscalYear] = $this->seedBusinessWithFiscalYear();
        $this->configureNumbering($business, VoucherNumberingMode::Manual, prefix: 'JE', pad: 4);

        $generator = app(VoucherNumberGenerator::class);

        $this->expectException(ValidationException::class);

        DB::transaction(fn () => $generator->generate($business->id, $fiscalYear->id, '2026-03-01'));
    }

    public function test_manual_numbering_applies_prefix_and_pad(): void
    {
        [$business, $fiscalYear] = $this->seedBusinessWithFiscalYear();
        $this->configureNumbering($business, VoucherNumberingMode::Manual, prefix: 'JE', pad: 4);

        $generator = app(VoucherNumberGenerator::class);

        $result = DB::transaction(
            fn () => $generator->generate($business->id, $fiscalYear->id, '2026-03-01', '12'),
        );

        $this->assertSame('JE-0012', $result['voucher_number']);
        $this->assertSame(12, $result['sequence_number']);
    }

    /**
     * @return array{0: Business, 1: FiscalYear, 2: User}
     */
    protected function seedBusinessWithFiscalYear(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $fiscalYear = FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ]);

        return [$business, $fiscalYear, $user];
    }

    protected function configureNumbering(
        Business $business,
        VoucherNumberingMode $mode,
        ?string $prefix,
        int $pad,
    ): void {
        app(BusinessLedgerSettingsService::class)->update($business, [
            'voucher_numbering_mode' => $mode,
            'voucher_prefix' => $prefix,
            'voucher_number_pad' => $pad,
            'default_voucher_status' => JournalEntryStatus::Draft,
            'auto_post_mode' => AutoPostMode::Off,
        ]);
    }

    /**
     * @param  array{voucher_number: string, sequence_number: int, sequence_scope: string}  $numbering
     */
    protected function createEntry(
        Business $business,
        FiscalYear $fiscalYear,
        User $user,
        array $numbering,
        string $entryDate,
    ): JournalEntry {
        return JournalEntry::query()->create([
            'business_id' => $business->id,
            'fiscal_year_id' => $fiscalYear->id,
            'voucher_number' => $numbering['voucher_number'],
            'sequence_number' => $numbering['sequence_number'],
            'sequence_scope' => $numbering['sequence_scope'],
            'entry_date' => $entryDate,
            'description' => 'Test voucher',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
    }
}
