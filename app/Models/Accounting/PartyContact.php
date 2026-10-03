<?php

namespace App\Models\Accounting;

use Database\Factories\PartyContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'party_id',
    'name',
    'position',
    'phone',
    'mobile',
    'email',
    'note',
    'is_primary',
])]
class PartyContact extends Model
{
    /** @use HasFactory<PartyContactFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function newFactory(): PartyContactFactory
    {
        return PartyContactFactory::new();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
