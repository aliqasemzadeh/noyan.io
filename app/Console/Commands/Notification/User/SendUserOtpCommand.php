<?php

namespace App\Console\Commands\Notification\User;

use App\Jobs\Notification\User\SendUserOtpJob;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:otp:send {mobile : Iranian mobile number (09...)} {code? : Optional OTP code; generated when omitted}')]
#[Description('Create (when needed) and queue an OTP SMS for a user')]
class SendUserOtpCommand extends Command
{
    public function handle(): int
    {
        $mobile = (string) $this->argument('mobile');

        $user = User::query()->firstOrCreate(['mobile' => $mobile]);

        $code = $this->argument('code');

        if ($code === null) {
            $oneTimePassword = $user->createOneTimePassword(
                (int) config('otp.expires_in_minutes'),
            );
            $code = $oneTimePassword->password;
        }

        SendUserOtpJob::dispatch($user, (string) $code);

        $this->info("OTP queued for {$user->mobile}.");

        return self::SUCCESS;
    }
}
