<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Models\Catalog\StockDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInboundDiscrepancyAction
{
    public function handle(StockDocument $document): StockDocument
    {
        return DB::transaction(function () use ($document): StockDocument {
            /** @var StockDocument $document */
            $document = StockDocument::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($document->type !== StockDocumentType::Inbound || $document->status !== StockDocumentStatus::Discrepancy) {
                throw ValidationException::withMessages([
                    'document' => [__('general.stock_document_not_open')],
                ]);
            }

            $document->forceFill([
                'status' => StockDocumentStatus::Received,
            ])->save();

            return $document;
        });
    }
}
