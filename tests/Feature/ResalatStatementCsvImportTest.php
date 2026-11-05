<?php

namespace Tests\Feature;

use App\Ai\Tools\ImportBankStatement;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Services\Imports\BankStatementCsvDetector;
use App\Services\Imports\ResalatStatementCsvParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ResalatStatementCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_reads_resalat_csv_and_filename_hint(): void
    {
        $path = $this->fixturePath();

        $parsed = app(ResalatStatementCsvParser::class)->parse(
            $path,
            'Invoice_10.3900820.1_14050814.csv',
        );

        $this->assertSame('10.3900820.1', $parsed['account_number_hint']);
        $this->assertCount(5, $parsed['rows']);
        $this->assertSame('income', $parsed['rows'][0]['type']);
        $this->assertSame('961608080', $parsed['rows'][0]['amount']);
        $this->assertSame('expense', $parsed['rows'][1]['type']);
        $this->assertSame('55000000', $parsed['rows'][1]['amount']);
        $this->assertFalse($parsed['rows'][2]['is_fee']);
        $this->assertSame('50000000', $parsed['rows'][2]['amount']);
        $this->assertTrue($parsed['rows'][3]['is_fee']);
        $this->assertSame('5000', $parsed['rows'][3]['amount']);
        $this->assertSame('135000000', $parsed['rows'][4]['amount']);
    }

    public function test_detector_routes_resalat_csv(): void
    {
        $parsed = app(BankStatementCsvDetector::class)->parse(
            $this->fixturePath(),
            'Invoice_10.3900820.1_14050814.csv',
        );

        $this->assertSame('10.3900820.1', $parsed['account_number_hint']);
        $this->assertCount(5, $parsed['rows']);
    }

    public function test_import_bank_statement_tool_creates_transactions_and_links_fees(): void
    {
        // Balance before chronological first row (135M transfer):
        // after row = 25,175,342,331 → opening = 25,175,342,331 + 135,000,000
        [$user, $account] = $this->prepareAccount('25310342331');

        $stored = 'ai-imports/resalat-test.csv';
        Storage::disk('local')->put($stored, file_get_contents($this->fixturePath()));

        $this->actingAs($user);

        $preview = (string) app(ImportBankStatement::class)->handle(new Request([
            'file_path' => $stored,
            'account_number' => $account->account_number,
            'confirmed' => false,
        ]));

        $this->assertStringContainsString((string) __('general.ai_transaction_preview_ask_confirm'), $preview);
        $this->assertDatabaseCount('transactions', 0);

        $result = (string) app(ImportBankStatement::class)->handle(new Request([
            'file_path' => $stored,
            'account_number' => $account->account_number,
            'confirmed' => true,
        ]));

        $this->assertNotSame('', $result);
        $this->assertSame(5, Transaction::query()->where('account_id', $account->id)->count());

        $main = Transaction::query()
            ->where('account_id', $account->id)
            ->where('amount', '50000000')
            ->where('note', 'like', '%ثبت پایا%')
            ->first();
        $fee = Transaction::query()
            ->where('account_id', $account->id)
            ->where('amount', '5000')
            ->first();

        $this->assertNotNull($main);
        $this->assertNotNull($fee);
        $this->assertSame($main->id, (int) (($fee->meta['fee_of_transaction_id'] ?? 0)));

        // Final CSV balance (newest row): 26,031,945,411
        $this->assertSame(0, bccomp((string) $account->fresh()->current_balance, '26031945411', 18));
    }

    protected function fixturePath(): string
    {
        $path = storage_path('framework/testing/resalat-sample.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        // Newest-first Resalat export. Same-second fee listed after main in file,
        // but fee balance is higher (fee applied first chronologically).
        file_put_contents($path, <<<'CSV'
,کد شعبه,شعبه,تاریخ,ساعت,سند,شماره قبض,شماره چک,توضیحات,برداشت,واریز,مانده,یادداشت
1,10,بانکداری اجتماعی متمرکز,1405/08/13,22:37:45,14912208323,,,DD7EFE پرداخت بابت برداشت کار_سند انتقال وجه,0,961608080,26031945411,
2,1,ستاد,1405/08/13,00:30:45,14903179219,,,پرداخت قسط تسهیلات شماره 10-123-3900820-9,55000000,0,25070337331,
3,996,بانکداری مجازی,1405/08/12,06:49:52,14907172627,,,ثبت پایا با شماره پیگیری 14050812070236886241 به مقصد بانک ملي_ثبت پایا در شعبه 995,50000000,0,25125337331,
4,996,بانکداری مجازی,1405/08/12,06:49:52,14907172627,,,دریافت کارمزد ثبت پایا با شماره پیگیری 14050812070236886241_ثبت پایا در شعبه 995,5000,0,25175337331,
5,996,بانکداری مجازی,1405/08/12,06:46:51,14907170933,,,انتقال وجه به سپرده 10.8153775.1_سامانه همراه بانک,135000000,0,25175342331,
CSV);

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
            'name' => 'رسالت شخصی',
            'bank_name' => 'رسالت',
            'account_number' => '10.3900820.1',
            'current_balance' => $openingBalance,
            'opening_balance' => $openingBalance,
            'is_active' => true,
        ]);

        return [$user, $account];
    }
}
