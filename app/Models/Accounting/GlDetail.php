<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\DetailType;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\LogsBusinessActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'type',
    'code',
    'name',
    'detailable_type',
    'detailable_id',
    'is_active',
])]
class GlDetail extends Model
{
    use BelongsToBusiness, LogsBusinessActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DetailType::class,
            'is_active' => 'boolean',
        ];
    }

    public function detailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfType(Builder $query, DetailType|string $type): Builder
    {
        $value = $type instanceof DetailType ? $type->value : $type;

        return $query->where('type', $value);
    }
}
