<?php

namespace App\Jobs\Notifications;

use App\Actions\Notifications\SendInvoiceNotificationAction;
use App\Enums\Accounting\InvoiceNotificationChannel;
use App\Enums\Accounting\InvoiceNotificationType;
use App\Models\Accounting\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInvoiceNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>|null  $channels
     */
    public function __construct(
        public int $invoiceId,
        public string $type,
        public ?array $channels = null,
        public bool $force = false,
    ) {
        $this->afterCommit();
    }

    public function handle(SendInvoiceNotificationAction $action): void
    {
        $invoice = Invoice::query()->with(['party', 'business'])->find($this->invoiceId);

        if ($invoice === null) {
            return;
        }

        $type = InvoiceNotificationType::from($this->type);
        $channels = $this->channels === null
            ? null
            : array_map(
                fn (string $channel): InvoiceNotificationChannel => InvoiceNotificationChannel::from($channel),
                $this->channels,
            );

        $action->handle($invoice, $type, $channels, $this->force);
    }
}
