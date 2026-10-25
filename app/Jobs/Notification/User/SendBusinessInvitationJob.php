<?php

namespace App\Jobs\Notification\User;

use App\Models\BusinessInvitation;
use App\Services\Sms\SetareganSmsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBusinessInvitationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public BusinessInvitation $invitation,
        public string $link,
    ) {}

    public function handle(SetareganSmsClient $sms): void
    {
        $this->invitation->loadMissing('business');

        $sms->send(
            $this->invitation->mobile,
            __('general.business_invitation_sms', [
                'business' => $this->invitation->business?->name ?? '',
                'link' => $this->link,
            ], 'fa'),
        );
    }
}
