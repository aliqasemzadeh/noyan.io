<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockDocumentPriority;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Models\Catalog\StockDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgressStockDocumentAction
{
    public function markPacked(StockDocument $document): StockDocument
    {
        return $this->move($document, StockDocumentStatus::PendingPick, [
            'status' => StockDocumentStatus::Packed,
        ]);
    }

    public function markUrgent(StockDocument $document): StockDocument
    {
        return DB::transaction(function () use ($document): StockDocument {
            /** @var StockDocument $document */
            $document = StockDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($document->type !== StockDocumentType::Outbound || ! in_array($document->status, [StockDocumentStatus::PendingPick, StockDocumentStatus::Packed], true)) {
                throw ValidationException::withMessages([
                    'document' => [__('general.stock_document_not_open')],
                ]);
            }

            $document->forceFill([
                'priority' => StockDocumentPriority::Urgent,
            ])->save();

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function move(StockDocument $document, StockDocumentStatus $from, array $attributes): StockDocument
    {
        return DB::transaction(function () use ($document, $from, $attributes): StockDocument {
            /** @var StockDocument $document */
            $document = StockDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($document->type !== StockDocumentType::Outbound || $document->status !== $from) {
                throw ValidationException::withMessages([
                    'document' => [__('general.stock_document_not_open')],
                ]);
            }

            $document->forceFill($attributes)->save();

            return $document;
        });
    }
}
