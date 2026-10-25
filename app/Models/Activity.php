<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * @property int|null $business_id
 */
class Activity extends SpatieActivity
{
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBusiness(Builder $query, Business|int $business): Builder
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return $query->where('business_id', $businessId);
    }
}
