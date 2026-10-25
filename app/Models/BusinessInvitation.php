<?php

namespace App\Models;

use App\Enums\Business\BusinessRole;
use Database\Factories\BusinessInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'business_id',
    'mobile',
    'roles',
    'permissions',
    'token',
    'invited_by',
    'expires_at',
    'accepted_at',
    'accepted_user_id',
])]
class BusinessInvitation extends Model
{
    /** @use HasFactory<BusinessInvitationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            if (blank($invitation->token)) {
                $invitation->token = Str::random(64);
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && ! $this->trashed();
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * @return list<BusinessRole>
     */
    public function roleEnums(): array
    {
        $roles = [];

        foreach ($this->roleValues() as $value) {
            $role = BusinessRole::tryFrom($value);

            if ($role !== null) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    public function roleValues(): array
    {
        $roles = $this->roles;

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $roles)));
    }

    public function acceptUrl(): string
    {
        return route('business-invitations.accept', ['token' => $this->token]);
    }
}
