<?php

namespace App\Models;

use App\Enums\Business\BusinessPanel;
use App\Enums\Business\BusinessPermission;
use App\Enums\Gender;
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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'mobile',
    'current_business_id',
    'first_name',
    'last_name',
    'email',
    'gender',
    'birth_date',
])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOneTimePasswords, HasRoles, InteractsWithMedia, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
        ];
    }

    /**
     * Remember-me cookies are only restored when the password is a string.
     * OTP accounts have no password, so keep an empty string and let the
     * remember token itself identify the user.
     */
    public function getAuthPassword(): string
    {
        $password = $this->password;

        return is_string($password) ? $password : '';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function avatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?: null;
    }

    public function fullName(): ?string
    {
        $name = trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));

        return $name !== '' ? $name : null;
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('administrator');
    }

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
            ->withPivot(['roles', 'permissions'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function owns(Business $business): bool
    {
        return (int) $business->owner_id === (int) $this->id;
    }

    public function canManageBusinessUsers(Business $business): bool
    {
        return $this->can('business_user_manage') || $this->owns($business);
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

    public function currentMembership(): ?BusinessUser
    {
        if ($this->current_business_id === null) {
            return null;
        }

        return $this->memberships()
            ->where('business_id', $this->current_business_id)
            ->first();
    }

    public function canInBusiness(BusinessPermission|string $permission): bool
    {
        $membership = $this->currentMembership();

        if ($membership === null) {
            return false;
        }

        return $membership->hasPermission($permission);
    }

    public function canAccessPanel(BusinessPanel $panel): bool
    {
        $membership = $this->currentMembership();

        if ($membership === null) {
            return false;
        }

        return $membership->canAccessPanel($panel);
    }

    public function defaultPanel(): BusinessPanel
    {
        if ($this->canAccessPanel(BusinessPanel::Accounting)) {
            return BusinessPanel::Accounting;
        }

        if ($this->canAccessPanel(BusinessPanel::Warehouse)) {
            return BusinessPanel::Warehouse;
        }

        if ($this->canAccessPanel(BusinessPanel::Sale)) {
            return BusinessPanel::Sale;
        }

        return BusinessPanel::Accounting;
    }
}
