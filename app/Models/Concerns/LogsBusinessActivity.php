<?php

namespace App\Models\Concerns;

use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\JournalLine;
use App\Models\Accounting\PartyContact;
use App\Models\Accounting\QuoteItem;
use App\Models\Business;
use App\Models\Catalog\StockConversionItem;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait LogsBusinessActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function beforeActivityLogged(ActivityContract $activity, string $eventName): void
    {
        $activity->business_id = $this->resolveBusinessIdForActivity();
    }

    protected function resolveBusinessIdForActivity(): ?int
    {
        if ($this instanceof Business) {
            return (int) $this->getKey();
        }

        if (array_key_exists('business_id', $this->getAttributes())) {
            $businessId = $this->getAttribute('business_id');

            return $businessId !== null ? (int) $businessId : null;
        }

        return match (true) {
            $this instanceof InvoiceItem => $this->invoice()->value('business_id'),
            $this instanceof QuoteItem => $this->quote()->value('business_id'),
            $this instanceof JournalLine => $this->journalEntry()->value('business_id'),
            $this instanceof PartyContact => $this->party()->value('business_id'),
            $this instanceof StockConversionItem => $this->conversion()->value('business_id'),
            default => null,
        };
    }
}
