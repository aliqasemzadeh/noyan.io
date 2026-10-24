<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketFile;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TicketAttachmentStore
{
    /**
     * @param  array<int, TemporaryUploadedFile|UploadedFile>  $files
     * @return list<TicketFile>
     */
    public static function storeMany(
        Ticket $ticket,
        ?TicketReply $reply,
        User $user,
        array $files,
    ): array {
        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof TemporaryUploadedFile && ! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('tickets/'.$ticket->id, TicketFile::DISK);

            $stored[] = TicketFile::create([
                'ticket_id' => $ticket->id,
                'ticket_reply_id' => $reply?->id,
                'user_id' => $user->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
            ]);
        }

        return $stored;
    }

    public static function delete(TicketFile $file): void
    {
        if ($file->existsOnDisk()) {
            Storage::disk(TicketFile::DISK)->delete($file->path);
        }

        $file->delete();
    }
}
