<?php

namespace Tests\Feature;

use App\Ai\Tools\ImportBankStatement;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Services\Imports\BankStatementCsvDetector;
use App\Services\Imports\BlubankStatementExcelParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BlubankStatementExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_reads_blubank_excel_and_iban_hint(): void
    {
        $path = $this->fixturePath();

        $parsed = app(BlubankStatementExcelParser::class)->parse($path, 'SDOC-sample.xlsx');

        $this->assertSame('IR370560611828005185652801', $parsed['account_number_hint']);
        $this->assertCount(5, $parsed['rows']);
        $this->assertSame('expense', $parsed['rows'][0]['type']);
        $this->assertSame('14500', $parsed['rows'][0]['amount']);
        $this->assertTrue($parsed['rows'][0]['is_fee']);
        $this->assertSame('14056801193482', $parsed['rows'][0]['reference_number']);
        $this->assertSame('expense', $parsed['rows'][1]['type']);
        $this->assertSame('20000000', $parsed['rows'][1]['amount']);
        $this->assertFalse($parsed['rows'][1]['is_fee']);
        $this->assertSame('income', $parsed['rows'][4]['type']);
        $this->assertSame('150000000', $parsed['rows'][4]['amount']);
        $this->assertSame('1109840500', $parsed['rows'][0]['expected_balance']);
    }

    public function test_detector_routes_blubank_excel(): void
    {
        $path = $this->fixturePath();

        $this->assertTrue(app(BankStatementCsvDetector::class)->looksLikeBankStatement($path, 'blubank.xlsx'));

        $parsed = app(BankStatementCsvDetector::class)->parse($path, 'blubank.xlsx');

        $this->assertSame('IR370560611828005185652801', $parsed['account_number_hint']);
        $this->assertCount(5, $parsed['rows']);
    }

    public function test_import_bank_statement_tool_creates_transactions_and_links_fees(): void
    {
        // Chronological first (oldest in file): 150M income → after = 1,150,000,000 → opening before = 1,000,000,000
        [$user, $account] = $this->prepareAccount('1000000000');

        $stored = 'ai-imports/blubank-test.xlsx';
        Storage::disk('local')->put($stored, file_get_contents($this->fixturePath()));

        $this->actingAs($user);

        $preview = (string) app(ImportBankStatement::class)->handle(new Request([
            'file_path' => $stored,
            'account_number' => $account->iban,
            'confirmed' => false,
        ]));

        $this->assertStringContainsString((string) __('general.ai_transaction_preview_ask_confirm'), $preview);
        $this->assertDatabaseCount('transactions', 0);

        $result = (string) app(ImportBankStatement::class)->handle(new Request([
            'file_path' => $stored,
            'account_number' => $account->iban,
            'confirmed' => true,
        ]));

        $this->assertNotSame('', $result, $result);
        $this->assertSame(5, Transaction::query()->where('account_id', $account->id)->count(), $result);

        $main = Transaction::query()
            ->where('account_id', $account->id)
            ->where('amount', '20000000')
            ->where('note', 'like', '%انتقال به کارت%')
            ->first();
        $fee = Transaction::query()
            ->where('account_id', $account->id)
            ->where('amount', '14500')
            ->first();

        $this->assertNotNull($main);
        $this->assertNotNull($fee);
        $this->assertSame($main->id, (int) (($fee->meta['fee_of_transaction_id'] ?? 0)));

        // Newest row balance after fee: 1,109,840,500
        $this->assertSame(0, bccomp((string) $account->fresh()->current_balance, '1109840500', 18));
    }

    public function test_import_uses_iban_hint_when_account_number_omitted(): void
    {
        [$user, $account] = $this->prepareAccount('1000000000');

        $stored = 'ai-imports/blubank-iban-hint.xlsx';
        Storage::disk('local')->put($stored, file_get_contents($this->fixturePath()));

        $this->actingAs($user);

        $result = (string) app(ImportBankStatement::class)->handle(new Request([
            'file_path' => $stored,
            'confirmed' => true,
        ]));

        $this->assertNotSame('', $result);
        $this->assertSame(5, Transaction::query()->where('account_id', $account->id)->count());
    }

    protected function fixturePath(): string
    {
        $path = storage_path('framework/testing/blubank-sample.xlsx');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('transaction');

        $sheet->setCellValue('B2', 'مانده از قبل: 1,217,726,362 (ریال)');
        $sheet->setCellValue('O7', 'شماره شبا: IR370560611828005185652801');
        $sheet->setCellValue('F8', 'صورتحساب از تاریخ 01/08/1405 00:00 تا تاریخ 14/08/1405 12:45');

        $headers = [
            2 => 'مانده (ریال)',
            3 => 'برداشت (ریال)',
            4 => 'واریز (ریال)',
            7 => 'نوع تراکنش',
            11 => 'شرح سند',
            16 => 'شماره سند',
            18 => 'تاریخ',
            19 => 'ردیف',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue([$col, 11], $label);
        }

        // Newest-first Blu export with consistent balances (main then fee at same second).
        // Chronology: +150M → -20M → -14,500 → -20M → -14,500
        $rows = [
            [1109840500, 14500, 0, 'کارمزد', 'برداشت از سپرده بابت کارمزد انتقال کارت به کارت-تراکنش اصلی ش م 379935782374 ش پ 522505', '14056801193482', '1405/08/13 17:05:58', 1],
            [1109855000, 20000000, 0, 'انتقال به کارت', 'انتقال به کارت شماره: 5047061676003698 بنام: علی قاسم زاده از درگاه اینترنتی 10000005 بانک سامان - ش م: 379935782374 ش پ: 522505', '14056801193482', '1405/08/13 17:05:58', 2],
            [1129855000, 14500, 0, 'کارمزد', 'برداشت از سپرده بابت کارمزد انتقال کارت به کارت-تراکنش اصلی ش م 359179490777 ش پ 994929', '14056711502948', '1405/08/11 07:26:35', 3],
            [1130000000, 20000000, 0, 'انتقال به کارت', 'انتقال به کارت شماره: 5047061676003698 بنام: علی قاسم زاده از درگاه اینترنتی 10000005 بانک سامان - ش م: 359179490777 ش پ: 994929', '14056711502948', '1405/08/11 07:26:35', 4],
            [1150000000, 0, 150000000, 'دریافت از کارت', 'انتقال از کارت شماره: 6037697490625503 بنام: محبوبه قاسم زاده از خودپرداز 00011307 بانک سینا - ش م: 630017011755 ش پ: 011755', '14056517569873', '1405/08/05 17:21:44', 5],
        ];

        foreach ($rows as $index => $row) {
            $excelRow = 12 + $index;
            $sheet->setCellValue([2, $excelRow], $row[0]);
            $sheet->setCellValue([3, $excelRow], $row[1]);
            $sheet->setCellValue([4, $excelRow], $row[2]);
            $sheet->setCellValue([7, $excelRow], $row[3]);
            $sheet->setCellValue([11, $excelRow], $row[4]);
            $sheet->setCellValue([16, $excelRow], $row[5]);
            $sheet->setCellValue([18, $excelRow], $row[6]);
            $sheet->setCellValue([19, $excelRow], $row[7]);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @return array{0: User, 1: Account}
     */
    protected function prepareAccount(string $openingBalance): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $currency = Currency::factory()->create([
            'code' => 'IRT',
            'decimal_places' => 0,
            'is_system' => true,
            'business_id' => null,
        ]);
        $business->activateCurrency($currency, '1', true);

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'بلو بانک',
            'bank_name' => 'بلو',
            'account_number' => '611828005185652801',
            'iban' => 'IR370560611828005185652801',
            'current_balance' => $openingBalance,
            'opening_balance' => $openingBalance,
            'is_active' => true,
        ]);

        return [$user, $account];
    }
}
