<?php

namespace Tests\Feature;

use App\Ai\Tools\ImportBankStatement;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Services\Imports\MellatStatementCsvParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class MellatStatementCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_reads_mellat_csv_rows(): void
    {
        $path = $this->fixturePath();

        $parsed = app(MellatStatementCsvParser::class)->parse($path, '1266272158-sample.csv');

        $this->assertSame('1266272158', $parsed['account_number_hint']);
        $this->assertCount(4, $parsed['rows']);
        $this->assertSame('expense', $parsed['rows'][0]['type']);
        $this->assertSame('120000', $parsed['rows'][0]['amount']);
        $this->assertTrue($parsed['rows'][0]['is_fee']);
        $this->assertSame('income', $parsed['rows'][3]['type']);
        $this->assertSame('4200000000', $parsed['rows'][3]['amount']);
    }

    public function test_import_bank_statement_tool_creates_transactions_and_links_fees(): void
    {
        [$user, $account] = $this->prepareAccount('19990301000');

        $stored = 'ai-imports/mellat-test.csv';
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
        $this->assertSame(4, Transaction::query()->where('account_id', $account->id)->count());

        $main = Transaction::query()
            ->where('account_id', $account->id)
            ->where('amount', '2000000000')
            ->first();
        $fee = Transaction::query()
            ->where('account_id', $account->id)
            ->where('amount', '120000')
            ->first();

        $this->assertNotNull($main);
        $this->assertNotNull($fee);
        $this->assertSame($main->id, (int) (($fee->meta['fee_of_transaction_id'] ?? 0)));

        // 19,990,301,000 - 120,000 - 2,000,000,000 - 100,000 + 4,200,000,000 = 22,190,081,000
        $this->assertSame(0, bccomp((string) $account->fresh()->current_balance, '22190081000', 18));
    }

    protected function fixturePath(): string
    {
        $path = storage_path('framework/testing/mellat-sample.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, <<<'CSV'
مانده,مبلغ گردش بستانکار,مبلغ گردش بدهکار,شرح,واریز کننده/ ذیتفع,شماره سریال,شناسه واریز,کد حسابگری,شعبه,زمان,تاریخ,رديف
"19,990,181,000",0,"120,000",کارمزد پایا ۱۴۰۵۰۸۰۲۰۱۲۲۲۰۰۳۰۷۶۳,کارمزد پایا,320030763,0,39255,دانشکده پزشکی شیراز,08:30:06,1405/08/02,1
"17,990,181,000",0,"2,000,000,000",پایا ۱۴۰۵۰۸۰۲۰۱۲۲۲۰۰۳۰۷۶۳ از همراه بانک,IR670560082481003952271001,1620030763,0,39255,دانشکده پزشکی شیراز,08:30:06,1405/08/02,2
"17,990,081,000",0,"100,000",کارمزد پایا ۱۴۰۵۰۸۰۲۰۱۲۲۲۰۰۳۱۳۹۶,کارمزد پایا,320031396,0,39255,دانشکده پزشکی شیراز,08:31:11,1405/08/02,3
"22,190,081,000","4,200,000,000",0,حواله همراه‌ بانک به حساب ۷۶۳۸۷۰۳۵۰۳,علی - قاسم زاده,1676997258,0,2402,اداره حسابداری متمرکز,18:41:00,1405/08/07,4
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
            'name' => 'ملت - پزشکی',
            'bank_name' => 'ملت',
            'account_number' => '1266272158',
            'current_balance' => $openingBalance,
            'opening_balance' => $openingBalance,
            'is_active' => true,
        ]);

        return [$user, $account];
    }
}
