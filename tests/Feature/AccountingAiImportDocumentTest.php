<?php

namespace Tests\Feature;

use App\Ai\Agents\AccountingAssistant;
use App\Ai\Tools\ImportInvoice;
use App\Ai\Tools\ImportQuote;
use App\Enums\Accounting\FormalSaleDocumentKind;
use App\Enums\Accounting\InvoiceType;
use App\Jobs\Accounting\ImportFormalSaleDocumentJob;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Accounting\Quote;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Currency;
use App\Models\User;
use App\Services\Imports\FormalSaleExcelParser;
use App\Support\LocaleDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AccountingAiImportDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_detects_quote_and_invoice_from_examples(): void
    {
        $parser = app(FormalSaleExcelParser::class);

        $quotePath = $this->makeFormalSaleExcel(
            title: 'پیش فروش کالا و خدمات',
            partyName: 'مدرسه حضرت زینب (س)',
            serial: '2107-2',
            issueDate: '1405/07/27',
            items: [
                ['title' => 'اس اس دی اپیسر', 'quantity' => '18', 'unit_price' => '63500000'],
            ],
        );

        $quote = $parser->parse($quotePath);
        $this->assertSame(FormalSaleDocumentKind::Quote, $quote->kind);
        $this->assertSame('مدرسه حضرت زینب (س)', $quote->partyName);
        $this->assertSame('2107-2', $quote->serial);
        $this->assertCount(1, $quote->items);
        $this->assertSame('اس اس دی اپیسر', $quote->items[0]['title']);
        $this->assertSame('18', $quote->items[0]['quantity']);
        $this->assertSame('63500000', $quote->items[0]['unit_price']);

        $invoicePath = $this->makeFormalSaleExcel(
            title: 'فروش کالا و خدمات',
            partyName: 'آموزش و پرورش ناحیه 2',
            serial: '1405-0002',
            issueDate: '1405/07/27',
            items: [
                ['title' => 'مادربرد H510mk fater', 'quantity' => '2', 'unit_price' => '1000000'],
                ['title' => 'رم ۸ گیگ', 'quantity' => '4', 'unit_price' => '500000'],
                ['title' => 'هارد', 'quantity' => '1', 'unit_price' => '2000000'],
                ['title' => 'پاور', 'quantity' => '1', 'unit_price' => '1500000'],
                ['title' => 'کیس', 'quantity' => '1', 'unit_price' => '3000000'],
                ['title' => 'مانیتور', 'quantity' => '1', 'unit_price' => '4000000'],
            ],
            skipTitleWithoutQty: 'کیس کامپیوتر',
        );

        $invoice = $parser->parse($invoicePath);
        $this->assertSame(FormalSaleDocumentKind::Invoice, $invoice->kind);
        $this->assertSame('آموزش و پرورش ناحیه 2', $invoice->partyName);
        $this->assertSame('1405-0002', $invoice->serial);
        $this->assertCount(6, $invoice->items);
        $this->assertSame('مادربرد H510mk fater', $invoice->items[0]['title']);
        $this->assertFalse(collect($invoice->items)->contains(fn (array $item): bool => str_contains($item['title'], 'کیس کامپیوتر')));
    }

    public function test_import_quote_preview_does_not_persist(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $path = $this->storeQuoteFixture();

        $result = (string) (new ImportQuote)->handle(new Request([
            'confirmed' => false,
            'file_path' => $path,
        ]));

        $this->assertStringContainsString(__('general.quote'), $result);
        $this->assertStringContainsString('مدرسه حضرت زینب (س)', $result);
        $this->assertStringContainsString(__('general.ai_transaction_preview_ask_confirm'), $result);
        $this->assertDatabaseCount('quotes', 0);
        $this->assertSame($user->id, auth()->id());
        $this->assertSame($business->id, auth()->user()->current_business_id);
    }

    public function test_import_quote_confirmed_queues_job_without_persisting(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $path = $this->storeQuoteFixture();

        Queue::fake();

        $result = (string) (new ImportQuote)->handle(new Request([
            'confirmed' => true,
            'file_path' => $path,
        ]));

        $this->assertSame(__('general.ai_document_import_queued'), $result);
        $this->assertDatabaseCount('quotes', 0);

        Queue::assertPushed(ImportFormalSaleDocumentJob::class, function (ImportFormalSaleDocumentJob $job) use ($user, $business, $path): bool {
            return $job->userId === $user->id
                && $job->businessId === $business->id
                && $job->filePath === $path
                && $job->kind === FormalSaleDocumentKind::Quote->value;
        });
    }

    public function test_import_quote_job_creates_draft_and_links_matching_product(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $path = $this->storeQuoteFixture();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'اس اس دی اپیسر',
            'sku' => 'SSD-APACER',
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'مدرسه حضرت زینب (س)',
            'is_customer' => true,
        ]);

        (new ImportFormalSaleDocumentJob(
            $user->id,
            $business->id,
            $path,
            FormalSaleDocumentKind::Quote->value,
        ))->handle();

        $quote = Quote::query()->with('items')->first();

        $this->assertNotNull($quote);
        $this->assertNull($quote->finalized_at);
        $this->assertSame($party->id, $quote->party_id);
        $this->assertCount(1, $quote->items);
        $this->assertSame($product->id, $quote->items->first()->product_id);
        $this->assertSame('18.000000000000000000', (string) $quote->items->first()->quantity);
    }

    public function test_import_quote_job_leaves_product_unlinked_when_not_found(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $path = $this->storeQuoteFixture(
            partyName: 'دبیرستان نام آوران',
            productTitle: 'کالای ناشناس',
        );

        (new ImportFormalSaleDocumentJob(
            $user->id,
            $business->id,
            $path,
            FormalSaleDocumentKind::Quote->value,
        ))->handle();

        $quote = Quote::query()->with('items')->first();

        $this->assertNotNull($quote);
        $this->assertNull($quote->items->first()->product_id);
        $this->assertNull($quote->party_id);
        $this->assertSame('دبیرستان نام آوران', $quote->party_name);
    }

    public function test_import_invoice_confirmed_queues_job_without_persisting(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $path = $this->storeInvoiceFixture();

        Queue::fake();

        $result = (string) (new ImportInvoice)->handle(new Request([
            'confirmed' => true,
            'file_path' => $path,
        ]));

        $this->assertSame(__('general.ai_document_import_queued'), $result);
        $this->assertDatabaseCount('invoices', 0);

        Queue::assertPushed(ImportFormalSaleDocumentJob::class, function (ImportFormalSaleDocumentJob $job) use ($user, $business, $path): bool {
            return $job->userId === $user->id
                && $job->businessId === $business->id
                && $job->filePath === $path
                && $job->kind === FormalSaleDocumentKind::Invoice->value;
        });
    }

    public function test_import_invoice_job_creates_sale_draft_and_skips_header_row(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $path = $this->storeInvoiceFixture();

        (new ImportFormalSaleDocumentJob(
            $user->id,
            $business->id,
            $path,
            FormalSaleDocumentKind::Invoice->value,
        ))->handle();

        $invoice = Invoice::query()->with('items')->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceType::Sale, $invoice->type);
        $this->assertNull($invoice->finalized_at);
        $this->assertCount(6, $invoice->items);
        $this->assertFalse($invoice->items->contains(fn ($item): bool => str_contains((string) $item->title, 'کیس کامپیوتر')));
        $this->assertSame(
            LocaleDate::toStorageDate('1405/07/27'),
            $invoice->issue_date->toDateString(),
        );
    }

    public function test_import_quote_rejects_invoice_file(): void
    {
        $this->actingBusinessOwner();
        $path = $this->storeInvoiceFixture();

        Queue::fake();

        $result = (string) (new ImportQuote)->handle(new Request([
            'confirmed' => true,
            'file_path' => $path,
        ]));

        $this->assertStringContainsString(__('general.invoice'), $result);
        $this->assertDatabaseCount('quotes', 0);
        Queue::assertNothingPushed();
    }

    public function test_accounting_assistant_instructions_mention_import_tools(): void
    {
        $this->actingBusinessOwner();

        $instructions = (string) (new AccountingAssistant)->instructions();

        $this->assertStringContainsString('import_quote', $instructions);
        $this->assertStringContainsString('import_invoice', $instructions);
        $this->assertStringContainsString('file_path', $instructions);
        $this->assertStringContainsString('صف', $instructions);
    }

    public function test_composer_shows_file_badge_without_file_path(): void
    {
        AccountingAssistant::fake([__('general.ai_document_import_queued')]);

        $this->actingBusinessOwner();

        $absolute = $this->makeFormalSaleExcel(
            title: 'پیش فروش کالا و خدمات',
            partyName: 'شهید مطهری',
            serial: '1860',
            issueDate: '1405/07/27',
            items: [
                ['title' => 'کالا', 'quantity' => '1', 'unit_price' => '1000'],
            ],
        );

        $upload = UploadedFile::fake()->createWithContent(
            'شهید مطهری 1860.xlsx',
            (string) file_get_contents($absolute),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        Livewire::actingAs(auth()->user())
            ->test('ai.composer', ['context' => 'accounting'])
            ->set('document', $upload)
            ->set('prompt', '')
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertSet('messages.0.attachment.name', 'شهید مطهری 1860.xlsx')
            ->assertDontSeeHtml('file_path=')
            ->assertSee('شهید مطهری 1860.xlsx');
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingBusinessOwner(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'name' => 'فروشگاه تست',
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
        ]);
        $business->activateCurrency($currency, '1', true);

        $this->actingAs($user);

        return [$user, $business];
    }

    protected function storeQuoteFixture(
        string $partyName = 'مدرسه حضرت زینب (س)',
        string $productTitle = 'اس اس دی اپیسر',
    ): string {
        $absolute = $this->makeFormalSaleExcel(
            title: 'پیش فروش کالا و خدمات',
            partyName: $partyName,
            serial: '2107-2',
            issueDate: '1405/07/27',
            items: [
                ['title' => $productTitle, 'quantity' => '18', 'unit_price' => '63500000'],
            ],
        );

        return $this->storeAbsoluteExcel($absolute);
    }

    protected function storeInvoiceFixture(): string
    {
        $absolute = $this->makeFormalSaleExcel(
            title: 'فروش کالا و خدمات',
            partyName: 'آموزش و پرورش ناحیه 2',
            serial: '1405-0002',
            issueDate: '1405/07/27',
            items: [
                ['title' => 'مادربرد H510mk fater', 'quantity' => '2', 'unit_price' => '1000000'],
                ['title' => 'رم ۸ گیگ', 'quantity' => '4', 'unit_price' => '500000'],
                ['title' => 'هارد', 'quantity' => '1', 'unit_price' => '2000000'],
                ['title' => 'پاور', 'quantity' => '1', 'unit_price' => '1500000'],
                ['title' => 'کیس', 'quantity' => '1', 'unit_price' => '3000000'],
                ['title' => 'مانیتور', 'quantity' => '1', 'unit_price' => '4000000'],
            ],
            skipTitleWithoutQty: 'کیس کامپیوتر',
        );

        return $this->storeAbsoluteExcel($absolute);
    }

    protected function storeAbsoluteExcel(string $absolute): string
    {
        $relative = 'ai-imports/'.basename($absolute);
        $destination = storage_path('app/private/'.$relative);
        @mkdir(dirname($destination), 0777, true);
        copy($absolute, $destination);

        return $relative;
    }

    /**
     * @param  list<array{title: string, quantity: string, unit_price: string}>  $items
     */
    protected function makeFormalSaleExcel(
        string $title,
        string $partyName,
        string $serial,
        string $issueDate,
        array $items,
        ?string $skipTitleWithoutQty = null,
    ): string {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('B1', $title);
        $sheet->setCellValue('BC1', $serial);
        $sheet->setCellValue('BC3', $issueDate);
        $sheet->setCellValue('K14', $partyName);

        $row = 21;

        if ($skipTitleWithoutQty !== null) {
            $sheet->setCellValue('G'.$row, $skipTitleWithoutQty);
            $row++;
        }

        foreach ($items as $item) {
            $sheet->setCellValue('G'.$row, $item['title']);
            $sheet->setCellValue('T'.$row, $item['quantity']);
            $sheet->setCellValue('Z'.$row, $item['unit_price']);
            $row++;
        }

        $sheet->setCellValue('B'.$row, 'جمع کل');

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ai-import-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
