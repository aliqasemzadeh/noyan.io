<?php

namespace App\Models;

use Database\Factories\TicketFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'ticket_id',
    'ticket_reply_id',
    'user_id',
    'path',
    'original_name',
    'mime',
])]
class TicketFile extends Model
{
    /** @use HasFactory<TicketFileFactory> */
    use HasFactory, SoftDeletes;

    public const DISK = 'local';

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<TicketReply, $this>
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'ticket_reply_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->path);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk(self::DISK)->exists($this->path);
    }
}
