<?php

namespace App\Actions\Quotes;

use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Quote;
use App\Models\Catalog\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ConvertQuoteToInvoiceAction
{
    public function __construct(
        private SaveInvoiceAction $saveInvoiceAction,
    ) {}

    public function handle(Quote $quote, User $user): Invoice
    {
        return DB::transaction(function () use ($quote, $user): Invoice {
            /** @var Quote $quote */
            $quote = Quote::query()
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if ((int) $quote->business_id !== (int) $user->current_business_id) {
                throw new RuntimeException('Quote does not belong to the current business.');
            }

            if (! $quote->isFinalized()) {
                throw ValidationException::withMessages([
                    'quote' => [__('general.quote_convert_requires_finalized')],
                ]);
            }

            if ($quote->isConverted()) {
                throw ValidationException::withMessages([
                    'quote' => [__('general.quote_already_converted')],
                ]);
            }

            if ($quote->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [__('general.quote_items_required')],
                ]);
            }

            $productIds = $quote->items
                ->pluck('product_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $validProductIds = $productIds === []
                ? []
                : Product::query()
                    ->where('business_id', $quote->business_id)
                    ->whereIn('id', $productIds)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

            $items = $quote->items->values()->map(function ($item, int $index) use ($validProductIds): array {
                $productId = $item->product_id !== null ? (int) $item->product_id : null;

                if ($productId !== null && ! in_array($productId, $validProductIds, true)) {
                    $productId = null;
                }

                return [
                    'product_id' => $productId,
                    'title' => (string) $item->title,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'discount_amount' => (string) $item->discount_amount,
                    'tax_amount' => (string) $item->tax_amount,
                    'sort_order' => (int) ($item->sort_order ?? $index),
                ];
            })->all();

            $invoice = $this->saveInvoiceAction->handle($user, [
                'type' => InvoiceType::Sale,
                'sale_kind_id' => $quote->sale_kind_id,
                'settlement_type_id' => $quote->settlement_type_id,
                'party_id' => $quote->party_id,
                'party_name' => $quote->party_name,
                'issue_date' => now()->toDateString(),
                'due_date' => null,
                'global_discount' => (string) $quote->global_discount,
                'global_tax' => (string) $quote->global_tax,
                'note' => $quote->note,
                'meta' => [
                    'source' => 'quote',
                    'quote_number' => $quote->quote_number,
                ],
                'quote_id' => $quote->id,
                'items' => $items,
            ]);

            $quote->forceFill([
                'invoice_id' => $invoice->id,
                'converted_at' => now(),
            ])->save();

            return $invoice->fresh(['items', 'party', 'quote']);
        });
    }
}
