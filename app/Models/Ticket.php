<?php

namespace App\Models;

use App\Enums\Support\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'user_id',
    'business_id',
    'ticket_category_id',
    'title',
    'status',
    'assigned_user_id',
    'ip',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<TicketCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return HasMany<TicketReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * @return HasMany<TicketFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(TicketFile::class);
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function ownedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }

    public function assertOpenForReply(): void
    {
        if ($this->isClosed()) {
            throw ValidationException::withMessages([
                'body' => __('general.ticket_closed_cannot_reply'),
            ]);
        }
    }

    public function markWaitingStaff(): void
    {
        if ($this->isClosed()) {
            return;
        }

        $this->update(['status' => TicketStatus::WaitingStaff]);
    }

    public function markWaitingUser(): void
    {
        if ($this->isClosed()) {
            return;
        }

        $this->update(['status' => TicketStatus::WaitingUser]);
    }

    public function close(): void
    {
        $this->update(['status' => TicketStatus::Closed]);
    }

    public function reopen(): void
    {
        $this->update(['status' => TicketStatus::WaitingStaff]);
    }
}
