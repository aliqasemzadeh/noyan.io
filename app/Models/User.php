<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['mobile', 'current_business_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOneTimePasswords, HasRoles, Notifiable, SoftDeletes;

    public function currentBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'current_business_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_user')
            ->withPivot(['role'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function owns(Business $business): bool
    {
        return (int) $business->owner_id === (int) $this->id;
    }

    public function belongsToBusiness(Business $business): bool
    {
        return $this->memberships()
            ->where('business_id', $business->id)
            ->exists();
    }

    /**
     * @return Collection<int, Business>
     */
    public function cachedBusinesses(): Collection
    {
        $ids = Cache::remember($this->businessesCacheKey(), now()->addHour(), function () {
            return $this->businesses()->orderBy('name')->pluck('businesses.id')->all();
        });

        if (! is_array($ids)) {
            $this->forgetBusinessesCache();

            $ids = $this->businesses()->orderBy('name')->pluck('businesses.id')->all();

            Cache::put($this->businessesCacheKey(), $ids, now()->addHour());
        }

        if ($ids === []) {
            return new Collection;
        }

        return Business::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    public function forgetBusinessesCache(): void
    {
        Cache::forget($this->businessesCacheKey());
    }

    public function businessesCacheKey(): string
    {
        return "user.{$this->id}.businesses";
    }

    public function ensureCurrentBusiness(): void
    {
        if ($this->current_business_id !== null) {
            $current = Business::query()->find($this->current_business_id);

            if ($current !== null && $this->belongsToBusiness($current)) {
                return;
            }
        }

        $firstBusiness = $this->businesses()->orderBy('name')->first();

        $this->forceFill([
            'current_business_id' => $firstBusiness?->id,
        ])->save();
    }

    /**
     * @throws AuthorizationException
     */
    public function switchBusiness(Business $business): void
    {
        if (! $this->belongsToBusiness($business)) {
            throw new AuthorizationException;
        }

        $this->forceFill([
            'current_business_id' => $business->id,
        ])->save();

        $this->forgetBusinessesCache();
    }
}
