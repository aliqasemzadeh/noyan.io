<?php

namespace App\Actions\Invoices;

use App\Actions\Concerns\NormalizesLineItems;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessPermission;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SaveInvoiceAction
{
    use NormalizesLineItems;

    public function __construct(
        private InvoiceNumberGenerator $invoiceNumberGenerator,
        private ApplyInvoiceEffectsAction $applyEffects,
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
     *     account_ids?: list<int>,
     *     meta?: array<string, mixed>|null,
     *     quote_id?: int|null,
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

            $editingFinalized = false;
            $previousTotal = null;

            if ($invoice !== null) {
                if ((int) $invoice->business_id !== (int) $businessId) {
                    throw new RuntimeException('Invoice does not belong to the current business.');
                }

                $invoice = Invoice::query()
                    ->whereKey($invoice->id)
                    ->lockForUpdate()
                    ->with(['items.product', 'party', 'business'])
                    ->firstOrFail();

                if ($invoice->isFinalized()) {
                    if (! $user->canInBusiness(BusinessPermission::InvoiceEditFinalized)) {
                        throw ValidationException::withMessages([
                            'invoice' => [__('general.invoice_already_finalized')],
                        ]);
                    }

                    if ($type !== $invoice->type) {
                        throw ValidationException::withMessages([
                            'type' => [__('general.invoice_finalized_type_locked')],
                        ]);
                    }

                    $editingFinalized = true;
                    $previousTotal = (string) $invoice->total_amount;
                    $this->applyEffects->handle($invoice, $user, reverse: true);
                }
            }

            [$partyId, $partyName] = $this->resolveParty($businessId, $data);
            $items = $this->normalizeItems($businessId, $data['items'] ?? []);
            [$itemsTotal, $totalAmount] = $this->calculateTotals(
                $items,
                (string) ($data['global_discount'] ?? '0'),
                (string) ($data['global_tax'] ?? '0'),
            );

            if ($invoice !== null && $invoice->isFinalized()) {
                $paidAmount = (string) $invoice->paid_amount;

                if (bccomp($totalAmount, $paidAmount, 18) < 0) {
                    throw ValidationException::withMessages([
                        'total_amount' => [__('general.invoice_total_below_paid_amount')],
                    ]);
                }
            }

            $attributes = [
                'party_id' => $partyId,
                'party_name' => $partyName,
                'type' => $type,
                'sale_kind_id' => $data['sale_kind_id'] ?? null,
                'settlement_type_id' => $data['settlement_type_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'items_total' => $itemsTotal,
                'global_discount' => $this->normalizeAmount((string) ($data['global_discount'] ?? '0')),
                'global_tax' => $this->normalizeAmount((string) ($data['global_tax'] ?? '0')),
                'total_amount' => $totalAmount,
                'meta' => $data['meta'] ?? null,
                'note' => $data['note'] ?? null,
            ];

            if (array_key_exists('quote_id', $data)) {
                $attributes['quote_id'] = $data['quote_id'];
            }

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
            $this->syncAccounts($invoice, $businessId, $data['account_ids'] ?? []);

            $invoice = $invoice->fresh(['items.product', 'party', 'business']);

            if ($editingFinalized) {
                $buyerSnapshots = [];

                if ($invoice->party !== null) {
                    $party = $invoice->party;
                    $buyerSnapshots = [
                        'buyer_legal_name' => $party->legal_name ?: $party->displayName(),
                        'buyer_national_id' => $party->national_id,
                        'buyer_economic_code' => $party->economic_code,
                        'buyer_postal_code' => $party->postal_code,
                        'buyer_phone' => $party->mobile ?: $party->phone,
                        'buyer_address' => $party->address,
                    ];
                }

                if ($buyerSnapshots !== []) {
                    $invoice->forceFill($buyerSnapshots)->save();
                }

                $this->applyEffects->handle($invoice, $user);

                activity()
                    ->performedOn($invoice)
                    ->causedBy($user)
                    ->event('invoice_finalized_updated')
                    ->withProperties([
                        'invoice_number' => $invoice->invoice_number,
                        'previous_total' => $previousTotal,
                        'new_total' => (string) $invoice->total_amount,
                    ])
                    ->log('invoice_finalized_updated');
            }

            return $invoice->fresh(['items', 'party', 'accounts']);
        });
    }

    /**
     * @param  list<int|string>  $accountIds
     */
    protected function syncAccounts(Invoice $invoice, int $businessId, array $accountIds): void
    {
        $ids = collect($accountIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            $invoice->accounts()->sync([]);

            return;
        }

        $validIds = Account::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $invoice->accounts()->sync($validIds);
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
}
