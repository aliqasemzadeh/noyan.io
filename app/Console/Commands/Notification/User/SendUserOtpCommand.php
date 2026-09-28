<?php

namespace App\Console\Commands\Notification\User;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-user-otp-command')]
#[Description('Command description')]
class SendUserOtpCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
