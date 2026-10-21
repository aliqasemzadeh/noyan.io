<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\InvoiceNotificationChannel;
use App\Enums\Accounting\InvoiceNotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'channel',
    'type',
    'sent_at',
    'meta',
])]
class InvoiceNotification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => InvoiceNotificationChannel::class,
            'type' => InvoiceNotificationType::class,
            'sent_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
