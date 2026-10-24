<?php

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Support\TicketAttachmentStore;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    public string $body = '';

    /** @var array<int, mixed> */
    public array $images = [];

    public function removeImage(int $index): void
    {
        if (! isset($this->images[$index])) {
            return;
        }

        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function mount(Ticket $ticket): void
    {
        abort_unless($ticket->ownedBy(Auth::user()), 403);

        $this->ticket = $ticket->load([
            'category:id,title',
            'business:id,name',
            'replies.user:id,mobile',
            'replies.files',
        ]);
    }

    #[Computed]
    public function replies()
    {
        return $this->ticket->replies()
            ->with(['user:id,mobile', 'files'])
            ->oldest()
            ->get();
    }

    public function reply(): void
    {
        abort_unless($this->ticket->ownedBy(Auth::user()), 403);

        $this->ticket->refresh();
        $this->ticket->assertOpenForReply();

        $validated = $this->validate([
            'body' => ['required', 'string', 'max:10000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $user = Auth::user();

        $reply = TicketReply::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
            'ip' => request()->ip(),
        ]);

        TicketAttachmentStore::storeMany(
            ticket: $this->ticket,
            reply: $reply,
            user: $user,
            files: $this->images,
        );

        $this->ticket->markWaitingStaff();

        $this->reset('body', 'images');
        unset($this->replies);

        Flux::toast(__('general.ticket_replied'));
    }

    public function closeTicket(): void
    {
        abort_unless($this->ticket->ownedBy(Auth::user()), 403);

        $this->ticket->refresh();

        if ($this->ticket->isClosed()) {
            return;
        }

        $this->ticket->close();
        $this->ticket->refresh();

        Flux::toast(__('general.ticket_closed'));
    }

    public function formatDateTime($datetime): string
    {
        return Jalalian::fromDateTime($datetime)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ $ticket->title }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.support.tickets.index')" wire:navigate>
                {{ __('general.support') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $ticket->title }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-2">
                <flux:heading size="xl" level="1">{{ $ticket->title }}</flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$ticket->status->badgeColor()">
                        {{ $ticket->status->label() }}
                    </flux:badge>
                    <flux:text class="text-sm">{{ $ticket->category?->title }}</flux:text>
                    @if ($ticket->business)
                        <flux:text class="text-sm">• {{ $ticket->business->name }}</flux:text>
                    @endif
                </div>
            </div>

            @if ($ticket->isOpen())
                <flux:button variant="danger" icon="lock-keyhole" wire:click="closeTicket" wire:confirm="{{ __('general.close_ticket') }}?">
                    {{ __('general.close_ticket') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        @foreach ($this->replies as $reply)
            <flux:card wire:key="user-reply-{{ $reply->id }}">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <flux:heading size="sm" dir="ltr">{{ $reply->user?->mobile }}</flux:heading>
                    <flux:text class="text-xs">{{ $this->formatDateTime($reply->created_at) }}</flux:text>
                </div>
                <flux:text class="whitespace-pre-wrap">{{ $reply->body }}</flux:text>
                <x-support.ticket-image-gallery :files="$reply->files" />
            </flux:card>
        @endforeach
    </div>

    @if ($ticket->isOpen())
        <flux:card>
            <form wire:submit="reply" class="space-y-4">
                <flux:heading size="lg">{{ __('general.send_reply') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('general.message') }}</flux:label>
                    <flux:textarea wire:model="body" rows="5" />
                    <flux:error name="body" />
                </flux:field>

                <x-support.ticket-image-upload
                    model="images"
                    :files="$images"
                    error-prefix="images"
                    remove-action="removeImage"
                />

                <flux:button type="submit" variant="primary" color="teal" class="w-full">
                    {{ __('general.send_reply') }}
                </flux:button>
            </form>
        </flux:card>
    @else
        <flux:callout icon="lock-keyhole" variant="secondary">
            {{ __('general.ticket_closed_cannot_reply') }}
        </flux:callout>
    @endif
</div>
