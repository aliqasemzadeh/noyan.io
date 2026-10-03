<?php

namespace App\Actions\Invoices;

use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Party;
use App\Models\Catalog\Product;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SaveInvoiceAction
{
    public function __construct(
        private InvoiceNumberGenerator $invoiceNumberGenerator,
    ) {}

    /**
     * @param  array{
     *     type: string|InvoiceType,
     *     party_id?: int|null,
     *     party_name?: string|null,
     *     issue_date: string,
     *     due_date?: string|null,
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
    public function handle(User $user, array $data, ?Invoice $invoice = null): Invoice
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to save an invoice.');
        }

        return DB::transaction(function () use ($user, $data, $invoice, $businessId): Invoice {
            $type = $data['type'] instanceof InvoiceType
                ? $data['type']
                : InvoiceType::from((string) $data['type']);

            if ($invoice !== null) {
                if ((int) $invoice->business_id !== (int) $businessId) {
                    throw new RuntimeException('Invoice does not belong to the current business.');
                }

                if ($invoice->isFinalized()) {
                    throw ValidationException::withMessages([
                        'invoice' => [__('general.invoice_already_finalized')],
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
                'type' => $type,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'items_total' => $itemsTotal,
                'global_discount' => $this->normalizeAmount((string) ($data['global_discount'] ?? '0')),
                'global_tax' => $this->normalizeAmount((string) ($data['global_tax'] ?? '0')),
                'total_amount' => $totalAmount,
                'meta' => $data['meta'] ?? null,
                'note' => $data['note'] ?? null,
            ];

            if ($invoice === null) {
                $invoice = Invoice::query()->create([
                    ...$attributes,
                    'business_id' => $businessId,
                    'created_by' => $user->id,
                    'invoice_number' => $this->invoiceNumberGenerator->generate($businessId, $type),
                    'paid_amount' => '0',
                    'payment_status' => InvoicePaymentStatus::Unpaid,
                    'finalized_at' => null,
                ]);
            } else {
                $invoice->update($attributes);
            }

            $this->syncItems($invoice, $items);

            return $invoice->fresh(['items', 'party']);
        });
    }

    /**
     * @param  array{party_id?: int|null, party_name?: string|null}  $data
     * @return array{0: int|null, 1: string|null}
     */
    protected function resolveParty(int $businessId, array $data): array
    {
        $partyId = isset($data['party_id']) ? (int) $data['party_id'] : null;
        $partyName = filled($data['party_name'] ?? null) ? trim((string) $data['party_name']) : null;

        if ($partyId) {
            $party = Party::query()
                ->where('business_id', $businessId)
                ->whereKey($partyId)
                ->first();

            if ($party === null) {
                throw ValidationException::withMessages([
                    'party_id' => [__('general.invoice_party_invalid')],
                ]);
            }

            return [$party->id, $partyName ?: $party->displayName()];
        }

        if ($partyName === null || $partyName === '') {
            throw ValidationException::withMessages([
                'party_name' => [__('general.invoice_party_required')],
            ]);
        }

        return [null, $partyName];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{
     *     product_id: int|null,
     *     title: string,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     total: string,
     *     sort_order: int,
     *     meta: array<string, mixed>|null
     * }>
     */
    protected function normalizeItems(int $businessId, array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $index => $item) {
            $title = trim((string) ($item['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $productId = isset($item['product_id']) && filled($item['product_id'])
                ? (int) $item['product_id']
                : null;

            if ($productId !== null) {
                $exists = Product::query()
                    ->where('business_id', $businessId)
                    ->whereKey($productId)
                    ->exists();

                if (! $exists) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => [__('general.invoice_product_invalid')],
                    ]);
                }
            }

            $quantity = $this->normalizeAmount((string) ($item['quantity'] ?? '1'));
            $unitPrice = $this->normalizeAmount((string) ($item['unit_price'] ?? '0'));
            $discount = $this->normalizeAmount((string) ($item['discount_amount'] ?? '0'));
            $tax = $this->normalizeAmount((string) ($item['tax_amount'] ?? '0'));
            $line = bcmul($quantity, $unitPrice, 18);
            $total = bcadd(bcsub($line, $discount, 18), $tax, 18);

            $normalized[] = [
                'product_id' => $productId,
                'title' => $title,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total' => $total,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'meta' => $item['meta'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{total: string, sort_order: int}>  $items
     * @return array{0: string, 1: string}
     */
    protected function calculateTotals(array $items, string $globalDiscount, string $globalTax): array
    {
        $itemsTotal = '0';

        foreach ($items as $item) {
            $itemsTotal = bcadd($itemsTotal, $item['total'], 18);
        }

        $totalAmount = bcadd(
            bcsub($itemsTotal, $this->normalizeAmount($globalDiscount), 18),
            $this->normalizeAmount($globalTax),
            18,
        );

        return [$itemsTotal, $totalAmount];
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
    protected function syncItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->each(function (InvoiceItem $item): void {
            $item->delete();
        });

        foreach ($items as $item) {
            $invoice->items()->create($item);
        }
    }

    protected function normalizeAmount(string $value): string
    {
        $value = trim(str_replace(',', '', $value));

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 18);
    }
}
