<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\AccountLevel;
use App\Enums\Accounting\AccountNature;
use App\Enums\Accounting\DetailType;
use App\Enums\Accounting\StatementType;
use App\Models\Business;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\LogsBusinessActivity;
use Database\Factories\Accounting\GlAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'parent_id',
    'level',
    'code',
    'full_code',
    'name',
    'nature',
    'statement_type',
    'is_postable',
    'is_system',
    'system_key',
    'sort_order',
    'is_active',
])]
class GlAccount extends Model
{
    /** @use HasFactory<GlAccountFactory> */
    use BelongsToBusiness, HasFactory, LogsBusinessActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => AccountLevel::class,
            'nature' => AccountNature::class,
            'statement_type' => StatementType::class,
            'is_postable' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): GlAccountFactory
    {
        return GlAccountFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<GlAccount, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('full_code');
    }

    /**
     * @return HasMany<GlAccountDetailLevel, $this>
     */
    public function detailLevels(): HasMany
    {
        return $this->hasMany(GlAccountDetailLevel::class)->orderBy('level');
    }

    public function detailTypeForLevel(int $level): ?DetailType
    {
        $match = $this->detailLevels->firstWhere('level', $level);

        return $match?->detail_type;
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
    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true)->where('level', AccountLevel::Subsidiary);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function isGroup(): bool
    {
        return $this->level === AccountLevel::Group;
    }

    public function isControl(): bool
    {
        return $this->level === AccountLevel::Control;
    }

    public function isSubsidiary(): bool
    {
        return $this->level === AccountLevel::Subsidiary;
    }

    public function canHaveChildren(): bool
    {
        return $this->level !== AccountLevel::Subsidiary;
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function resolveFullCode(?GlAccount $parent = null): string
    {
        $parent ??= $this->parent;

        if ($parent === null) {
            return $this->code;
        }

        return $parent->full_code.$this->code;
    }

    public static function findBySystemKey(Business|int $business, string $systemKey): ?self
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return static::query()
            ->where('business_id', $businessId)
            ->where('system_key', $systemKey)
            ->first();
    }
}
