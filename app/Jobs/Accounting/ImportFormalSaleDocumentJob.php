<?php

namespace App\Jobs\Accounting;

use App\Actions\Invoices\SaveInvoiceAction;
use App\Actions\Quotes\SaveQuoteAction;
use App\Ai\Tools\Concerns\ImportsFormalSaleDocument;
use App\Enums\Accounting\FormalSaleDocumentKind;
use App\Enums\Accounting\InvoiceType;
use App\Models\User;
use App\Services\Imports\FormalSaleDocument;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportFormalSaleDocumentJob implements ShouldQueue
{
    use ImportsFormalSaleDocument;
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 30];

    public int $timeout = 120;

    public function __construct(
        public int $userId,
        public int $businessId,
        public string $filePath,
        public string $kind,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        if ((int) $user->current_business_id !== $this->businessId) {
            $user->forceFill(['current_business_id' => $this->businessId])->save();
        }

        $kind = FormalSaleDocumentKind::from($this->kind);
        $prepared = $this->prepareImport($this->businessId, $this->filePath, $kind);

        if (! $prepared['ok']) {
            report(new \RuntimeException($prepared['message']));

            return;
        }

        /** @var FormalSaleDocument $document */
        $document = $prepared['document'];
        $items = $prepared['items'];
        $partyId = $prepared['party_id'];
        $partyName = (string) $prepared['party_name'];

        $payloadItems = array_map(fn (array $item): array => [
            'product_id' => $item['product_id'],
            'title' => $item['title'],
            'sku' => $item['sku'],
            'unit' => $item['unit'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount_amount' => $item['discount_amount'],
            'tax_amount' => $item['tax_amount'],
        ], $items);

        $meta = [
            'imported_from' => 'excel',
            'source_serial' => $document->serial,
            'source_title' => $document->title,
            'source_file' => $this->filePath,
        ];

        $note = $document->serial !== null
            ? __('general.ai_document_import_note_serial', ['serial' => $document->serial])
            : null;

        try {
            if ($kind === FormalSaleDocumentKind::Quote) {
                $issueDate = (string) $document->issueDate;

                app(SaveQuoteAction::class)->handle($user, [
                    'party_id' => $partyId,
                    'party_name' => $partyName,
                    'issue_date' => $issueDate,
                    'valid_until' => Carbon::parse($issueDate)->addDays(7)->toDateString(),
                    'note' => $note,
                    'meta' => $meta,
                    'items' => $payloadItems,
                ]);

                return;
            }

            app(SaveInvoiceAction::class)->handle($user, [
                'type' => InvoiceType::Sale,
                'party_id' => $partyId,
                'party_name' => $partyName,
                'issue_date' => (string) $document->issueDate,
                'note' => $note,
                'meta' => $meta,
                'items' => $payloadItems,
            ]);
        } catch (ValidationException $exception) {
            report($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
