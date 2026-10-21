<?php

namespace App\Jobs\Notifications;

use App\Actions\Notifications\SendInvoiceNotificationAction;
use App\Enums\Accounting\InvoiceNotificationType;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Models\Accounting\Invoice;
use App\Models\BusinessInvoiceSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SendInvoicePaymentRemindersJob implements ShouldQueue
{
    use Queueable;

    public function handle(SendInvoiceNotificationAction $action): void
    {
        $today = Carbon::today();

        Invoice::query()
            ->with(['party', 'business.invoiceSettings'])
            ->whereNotNull('finalized_at')
            ->whereNotNull('due_date')
            ->whereIn('payment_status', [
                InvoicePaymentStatus::Unpaid->value,
                InvoicePaymentStatus::PartiallyPaid->value,
            ])
            ->whereHas('business.invoiceSettings', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('email_enabled', true)->where('reminder_email_enabled', true);
                })->orWhere(function ($query): void {
                    $query->where('sms_enabled', true)->where('reminder_sms_enabled', true);
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($action, $today): void {
                foreach ($invoices as $invoice) {
                    $settings = $invoice->business?->invoiceSettings
                        ?? BusinessInvoiceSetting::forBusiness($invoice->business);

                    $dueDate = Carbon::parse($invoice->due_date)->startOfDay();
                    $type = $this->resolveType($settings, $dueDate, $today);

                    if ($type === null) {
                        continue;
                    }

                    $action->handle($invoice, $type);
                }
            });
    }

    protected function resolveType(
        BusinessInvoiceSetting $settings,
        Carbon $dueDate,
        Carbon $today,
    ): ?InvoiceNotificationType {
        if ($settings->reminder_on_due && $dueDate->equalTo($today)) {
            return InvoiceNotificationType::ReminderDue;
        }

        if ($settings->reminder_days_before > 0) {
            $before = $dueDate->copy()->subDays($settings->reminder_days_before);
            if ($before->equalTo($today)) {
                return InvoiceNotificationType::ReminderBefore;
            }
        }

        if ($settings->reminder_days_after > 0) {
            $after = $dueDate->copy()->addDays($settings->reminder_days_after);
            if ($after->equalTo($today)) {
                return InvoiceNotificationType::ReminderAfter;
            }
        }

        return null;
    }
}
