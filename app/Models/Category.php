<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Models\Accounting\Transaction;
use App\Models\Catalog\Product;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'business_id',
    'parent_id',
    'type',
    'code',
    'name',
    'slug',
    'description',
    'is_system',
    'sort_order',
    'is_active',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'is_system' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Category $category): void {
            $category->forgetRelevantCaches();
        });

        static::deleted(function (Category $category): void {
            $category->forgetRelevantCaches();
        });

        static::restored(function (Category $category): void {
            $category->forgetRelevantCaches();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
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
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true)->whereNull('business_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfType(Builder $query, CategoryType|string $type): Builder
    {
        $value = $type instanceof CategoryType ? $type->value : $type;

        return $query->where('type', $value);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAvailableToBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where(function (Builder $query) use ($businessId): void {
            $query->where(function (Builder $query): void {
                $query->system()->active();
            })->orWhere(function (Builder $query) use ($businessId): void {
                $query->where('business_id', $businessId)->where('is_active', true);
            });
        });
    }

    public static function systemCacheKey(CategoryType $type): string
    {
        return "categories.system.{$type->value}";
    }

    public static function businessCacheKey(int $businessId, CategoryType $type): string
    {
        return "categories.business.{$businessId}.{$type->value}";
    }

    public static function forgetSystemCache(CategoryType $type): void
    {
        Cache::forget(self::systemCacheKey($type));
    }

    public static function forgetBusinessCache(int $businessId, CategoryType $type): void
    {
        Cache::forget(self::businessCacheKey($businessId, $type));
    }

    /**
     * @return Collection<int, Category>
     */
    public static function cachedSystemTree(CategoryType $type): Collection
    {
        $cacheKey = self::systemCacheKey($type);

        $ids = Cache::remember($cacheKey, now()->addHour(), function () use ($type) {
            return static::query()
                ->system()
                ->active()
                ->ofType($type)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('id')
                ->all();
        });

        if (! is_array($ids)) {
            self::forgetSystemCache($type);

            $ids = static::query()
                ->system()
                ->active()
                ->ofType($type)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('id')
                ->all();

            Cache::put($cacheKey, $ids, now()->addHour());
        }

        return self::hydrateOrdered($ids);
    }

    /**
     * @return Collection<int, Category>
     */
    public static function cachedBusinessTree(int $businessId, CategoryType $type): Collection
    {
        $cacheKey = self::businessCacheKey($businessId, $type);

        $ids = Cache::remember($cacheKey, now()->addHour(), function () use ($businessId, $type) {
            return static::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->ofType($type)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('id')
                ->all();
        });

        if (! is_array($ids)) {
            self::forgetBusinessCache($businessId, $type);

            $ids = static::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->ofType($type)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('id')
                ->all();

            Cache::put($cacheKey, $ids, now()->addHour());
        }

        return self::hydrateOrdered($ids);
    }

    /**
     * Merged flat collection of system + business categories for a type.
     *
     * @return SupportCollection<int, Category>
     */
    public static function treeForBusiness(int $businessId, CategoryType $type): SupportCollection
    {
        return self::cachedSystemTree($type)
            ->concat(self::cachedBusinessTree($businessId, $type))
            ->unique('id')
            ->values();
    }

    /**
     * Build select options with per-business leaf detection.
     *
     * @return SupportCollection<int, array{id: int, name: string, path: string, depth: int, is_leaf: bool, is_system: bool}>
     */
    public static function selectOptionsForBusiness(int $businessId, CategoryType $type): SupportCollection
    {
        $nodes = self::treeForBusiness($businessId, $type)->keyBy('id');
        $childrenByParent = [];

        foreach ($nodes as $node) {
            $parentKey = $node->parent_id ?? 0;
            $childrenByParent[$parentKey][] = $node->id;
        }

        foreach ($childrenByParent as &$childIds) {
            usort($childIds, function (int $left, int $right) use ($nodes): int {
                $leftNode = $nodes->get($left);
                $rightNode = $nodes->get($right);

                return [$leftNode->sort_order, $leftNode->name] <=> [$rightNode->sort_order, $rightNode->name];
            });
        }
        unset($childIds);

        $options = new SupportCollection;
        $visited = [];

        $walk = function (int $parentId, int $depth, array $ancestors) use (&$walk, &$options, &$visited, $nodes, $childrenByParent): void {
            foreach ($childrenByParent[$parentId] ?? [] as $id) {
                if (isset($visited[$id])) {
                    continue;
                }

                $visited[$id] = true;
                $node = $nodes->get($id);

                if ($node === null) {
                    continue;
                }

                $pathParts = [...$ancestors, $node->name];
                $childIds = $childrenByParent[$id] ?? [];

                $options->push([
                    'id' => $node->id,
                    'name' => $node->name,
                    'path' => implode(' › ', $pathParts),
                    'depth' => $depth,
                    'is_leaf' => $childIds === [],
                    'is_system' => (bool) $node->is_system,
                ]);

                $walk($id, $depth + 1, $pathParts);
            }
        };

        $walk(0, 0, []);

        return $options;
    }

    /**
     * Global leaf check (any non-deleted child). Used by ProcessTransactionAction.
     */
    public function isLeaf(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isEmpty();
        }

        if (array_key_exists('children_count', $this->attributes)) {
            return (int) $this->attributes['children_count'] === 0;
        }

        return ! $this->children()->exists();
    }

    public function isOwnedByBusiness(?int $businessId): bool
    {
        return $businessId !== null && (int) $this->business_id === $businessId;
    }

    public function isEditableByBusiness(?int $businessId): bool
    {
        return ! $this->is_system && $this->isOwnedByBusiness($businessId);
    }

    public function pathLabel(string $separator = ' › '): string
    {
        $parts = [$this->name];
        $current = $this;
        $guard = 0;

        while ($current->parent_id !== null && $guard < 50) {
            $parent = $current->relationLoaded('parent')
                ? $current->parent
                : $current->parent()->first();

            if ($parent === null) {
                break;
            }

            array_unshift($parts, $parent->name);
            $current = $parent;
            $guard++;
        }

        return implode($separator, $parts);
    }

    public function isInUse(): bool
    {
        return $this->products()->exists() || $this->transactions()->exists();
    }

    /**
     * @return list<int>
     */
    public function ancestorIds(): array
    {
        $ids = [];
        $current = $this;
        $guard = 0;

        while ($current->parent_id !== null && $guard < 50) {
            $ids[] = (int) $current->parent_id;
            $parent = $current->relationLoaded('parent')
                ? $current->parent
                : $current->parent()->first();

            if ($parent === null) {
                break;
            }

            $current = $parent;
            $guard++;
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $queue = [$this->id];
        $guard = 0;

        while ($queue !== [] && $guard < 500) {
            $parentId = array_shift($queue);
            $childIds = static::query()
                ->where('parent_id', $parentId)
                ->pluck('id')
                ->all();

            foreach ($childIds as $childId) {
                $ids[] = (int) $childId;
                $queue[] = (int) $childId;
            }

            $guard++;
        }

        return $ids;
    }

    protected function forgetRelevantCaches(): void
    {
        if ($this->type instanceof CategoryType) {
            $type = $this->type;
        } else {
            return;
        }

        if ($this->business_id === null || $this->is_system) {
            self::forgetSystemCache($type);
        }

        if ($this->business_id !== null) {
            self::forgetBusinessCache((int) $this->business_id, $type);
        }
    }

    /**
     * @param  list<int|string>  $ids
     * @return Collection<int, Category>
     */
    protected static function hydrateOrdered(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $models = static::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = new Collection;

        foreach ($ids as $id) {
            $model = $models->get($id);

            if ($model !== null) {
                $ordered->push($model);
            }
        }

        return $ordered;
    }
}
