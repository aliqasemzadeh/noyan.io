<?php

namespace App\Livewire\Forms;

use App\Enums\Support\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\TicketAttachmentStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class TicketForm extends Form
{
    public string $title = '';

    public ?int $ticket_category_id = null;

    public ?int $business_id = null;

    public string $body = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $images = [];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = auth()->user();

        return [
            'title' => ['required', 'string', 'max:255'],
            'ticket_category_id' => [
                'required',
                'integer',
                Rule::exists('ticket_categories', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'business_id' => [
                'nullable',
                'integer',
                Rule::exists('business_user', 'business_id')->where(fn ($query) => $query->where('user_id', $user?->id)->whereNull('deleted_at')),
            ],
            'body' => ['required', 'string', 'max:10000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'title' => __('general.title'),
            'ticket_category_id' => __('general.ticket_category'),
            'business_id' => __('general.business'),
            'body' => __('general.message'),
            'images' => __('general.ticket_images'),
            'images.*' => __('general.ticket_image'),
        ];
    }

    public function store(User $user, ?string $ip = null): Ticket
    {
        if ($this->business_id === null || $this->business_id === 0) {
            $this->business_id = null;
        }

        if ($this->ticket_category_id === 0) {
            $this->ticket_category_id = null;
        }

        $validated = $this->validate();

        return DB::transaction(function () use ($validated, $user, $ip): Ticket {
            $ticket = Ticket::create([
                'user_id' => $user->id,
                'business_id' => $validated['business_id'] ?? null,
                'ticket_category_id' => $validated['ticket_category_id'],
                'title' => $validated['title'],
                'status' => TicketStatus::New,
                'ip' => $ip,
            ]);

            $reply = TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => $validated['body'],
                'ip' => $ip,
            ]);

            TicketAttachmentStore::storeMany(
                ticket: $ticket,
                reply: $reply,
                user: $user,
                files: $this->images,
            );

            $this->reset();

            return $ticket;
        });
    }
}
