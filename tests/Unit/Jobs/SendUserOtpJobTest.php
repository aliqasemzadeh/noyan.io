<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Notification\User\SendUserOtpJob;
use App\Models\User;
use App\Services\Sms\SetareganSmsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendUserOtpJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_otp_via_sms_client(): void
    {
        $user = User::factory()->create([
            'mobile' => '09123456789',
        ]);

        $sms = Mockery::mock(SetareganSmsClient::class);
        $sms->shouldReceive('send')
            ->once()
            ->with('09123456789', __('general.otp_sms_message', ['code' => '123456'], 'fa'))
            ->andReturn(['ok' => true, 'code' => 'queued']);

        (new SendUserOtpJob($user, '123456'))->handle($sms);
    }
}
