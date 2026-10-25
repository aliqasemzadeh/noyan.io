<?php

namespace App\Actions\Quotes;

use App\Actions\Concerns\NormalizesLineItems;
use App\Models\Accounting\Quote;
use App\Models\Accounting\QuoteItem;
use App\Models\User;
use App\Services\QuoteNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SaveQuoteAction
{
    use NormalizesLineItems;

    public function __construct(
        private QuoteNumberGenerator $quoteNumberGenerator,
    ) {}

    /**
     * @param  array{
     *     party_id?: int|null,
     *     party_name?: string|null,
     *     issue_date: string,
     *     valid_until: string,
     *     global_discount?: string|float|int,
     *     global_tax?: string|float|int,
     *     note?: string|null,
     *     meta?: array<string, mixed>|null,
     *     items: list<array{
     *         product_id?: int|null,
     *         title: string,
     *         quantity?: string|float|int,
     *         unit_price?: string|float|int,
     *         discount_amount?: string|float|int,
     *         tax_amount?: string|float|int,
     *         sort_order?: int,
     *         meta?: array<string, mixed>|null
     *     }>
     * }  $data
     */
    public function handle(User $user, array $data, ?Quote $quote = null): Quote
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to save a quote.');
        }

        return DB::transaction(function () use ($user, $data, $quote, $businessId): Quote {
            if ($quote !== null) {
                if ((int) $quote->business_id !== (int) $businessId) {
                    throw new RuntimeException('Quote does not belong to the current business.');
                }

                if (! $quote->isEditable()) {
                    throw ValidationException::withMessages([
                        'quote' => [__('general.quote_locked')],
                    ]);
                }
            }

            [$partyId, $partyName] = $this->resolveParty($businessId, $data);
            $items = $this->normalizeItems($businessId, $data['items'] ?? []);
            [$itemsTotal, $totalAmount] = $this->calculateTotals(
                $items,
                (string) ($data['global_discount'] ?? '0'),
                (string) ($data['global_tax'] ?? '0'),
            );

            $attributes = [
                'party_id' => $partyId,
                'party_name' => $partyName,
                'sale_kind_id' => $data['sale_kind_id'] ?? null,
                'settlement_type_id' => $data['settlement_type_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'],
                'items_total' => $itemsTotal,
                'global_discount' => $this->normalizeAmount((string) ($data['global_discount'] ?? '0')),
                'global_tax' => $this->normalizeAmount((string) ($data['global_tax'] ?? '0')),
                'total_amount' => $totalAmount,
                'meta' => $data['meta'] ?? null,
                'note' => $data['note'] ?? null,
            ];

            if ($quote === null) {
                $quote = Quote::query()->create([
                    ...$attributes,
                    'business_id' => $businessId,
                    'created_by' => $user->id,
                    'quote_number' => $this->quoteNumberGenerator->generate($businessId),
                    'finalized_at' => null,
                ]);
            } else {
                $quote->update($attributes);
            }

            $this->syncItems($quote, $items);

            return $quote->fresh(['items', 'party']);
        });
    }

    /**
     * @param  list<array{
     *     product_id: int|null,
     *     title: string,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     total: string,
     *     sort_order: int,
     *     meta: array<string, mixed>|null
     * }>  $items
     */
    protected function syncItems(Quote $quote, array $items): void
    {
        $quote->items()->each(function (QuoteItem $item): void {
            $item->delete();
        });

        foreach ($items as $item) {
            $quote->items()->create($item);
        }
    }
}
