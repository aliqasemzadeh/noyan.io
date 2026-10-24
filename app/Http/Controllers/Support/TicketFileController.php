<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\TicketFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketFileController extends Controller
{
    public function show(Request $request, TicketFile $ticketFile): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        $ticketFile->loadMissing('ticket');

        $canView = $ticketFile->ticket->ownedBy($user) || $user->can('ticket_view');

        abort_unless($canView, 403);
        abort_unless($ticketFile->existsOnDisk(), 404);

        return Storage::disk(TicketFile::DISK)->response(
            $ticketFile->path,
            $ticketFile->original_name,
            [
                'Content-Type' => $ticketFile->mime,
                'Content-Disposition' => 'inline; filename="'.$ticketFile->original_name.'"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
