<?php

namespace App\Models\Accounting;

use App\Models\Business;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'business_id',
    'settlement_type_id',
    'is_default',
])]
class BusinessSettlementType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function settlementType(): BelongsTo
    {
        return $this->belongsTo(SettlementType::class);
    }

    public function setAsDefault(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('business_id', $this->business_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }
}
