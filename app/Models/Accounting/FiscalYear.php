<?php

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\Accounting\FiscalYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'name',
    'start_date',
    'end_date',
    'is_closed',
])]
class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use BelongsToBusiness, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_closed' => 'boolean',
        ];
    }

    protected static function newFactory(): FiscalYearFactory
    {
        return FiscalYearFactory::new();
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_closed', false);
    }

    public function containsDate(string $date): bool
    {
        return $date >= $this->start_date->toDateString()
            && $date <= $this->end_date->toDateString();
    }
}
