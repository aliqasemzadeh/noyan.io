<?php

namespace App\Models;

use Database\Factories\TicketReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['ticket_id', 'user_id', 'body', 'ip'])]
class TicketReply extends Model
{
    /** @use HasFactory<TicketReplyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TicketFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(TicketFile::class);
    }
}
