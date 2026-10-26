<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\AutoPostQueueStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'business_id',
    'source_type',
    'source_id',
    'entry_date',
    'status',
    'requested_by',
    'journal_entry_id',
])]
class AutoPostQueue extends Model
{
    use BelongsToBusiness;

    protected $table = 'auto_post_queue';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => AutoPostQueueStatus::class,
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
