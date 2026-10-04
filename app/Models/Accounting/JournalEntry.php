<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\User;
use Database\Factories\Accounting\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'fiscal_year_id',
    'voucher_number',
    'entry_date',
    'description',
    'referenceable_type',
    'referenceable_id',
    'status',
    'created_by',
])]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use BelongsToBusiness, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => JournalEntryStatus::class,
        ];
    }

    protected static function newFactory(): JournalEntryFactory
    {
        return JournalEntryFactory::new();
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::Posted;
    }
}
