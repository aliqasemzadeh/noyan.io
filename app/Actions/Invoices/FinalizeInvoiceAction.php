<?php

namespace App\Actions\Invoices;

use App\Enums\Accounting\InvoiceNotificationType;
use App\Jobs\Notifications\SendInvoiceNotificationJob;
use App\Models\Accounting\Invoice;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FinalizeInvoiceAction
{
    public function __construct(
        protected ApplyInvoiceEffectsAction $applyEffects,
        protected AutoPostingEngine $autoPostingEngine,
    ) {}

    public function handle(Invoice $invoice, User $user): Invoice
    {
        return DB::transaction(function () use ($invoice, $user): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->with(['items.product', 'party', 'business'])
                ->firstOrFail();

            if ((int) $invoice->business_id !== (int) $user->current_business_id) {
                throw new RuntimeException('Invoice does not belong to the current business.');
            }

            if ($invoice->isFinalized()) {
                throw ValidationException::withMessages([
                    'invoice' => [__('general.invoice_already_finalized')],
                ]);
            }

            if ($invoice->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [__('general.invoice_items_required')],
                ]);
            }

            $this->applyEffects->handle($invoice, $user);

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

            $invoice->forceFill([
                ...$buyerSnapshots,
                'finalized_at' => now(),
            ])->save();

            try {
                $this->autoPostingEngine->postInvoice($invoice->fresh(['items', 'party']) ?? $invoice, $user);
            } catch (Throwable $exception) {
                Log::error('Invoice auto-post failed.', [
                    'invoice_id' => $invoice->id,
                    'message' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            SendInvoiceNotificationJob::dispatch(
                $invoice->id,
                InvoiceNotificationType::NewInvoice->value,
            );

            return $invoice->fresh(['items', 'party']);
        });
    }
}
