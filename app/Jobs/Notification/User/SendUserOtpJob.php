<?php

namespace App\Jobs\Notification\User;

use App\Models\User;
use App\Services\Sms\SetareganSmsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendUserOtpJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public User $user,
        public string $code,
    ) {}

    public function handle(SetareganSmsClient $sms): void
    {
        $sms->send(
            $this->user->mobile,
            __('general.otp_sms_message', ['code' => $this->code], 'fa'),
        );
    }
}
