<?php

namespace Tests\Feature\Sms;

use App\Exceptions\SmsSendException;
use App\Services\Sms\SetareganSmsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SetareganSmsClientTest extends TestCase
{
    public function test_it_sends_sms_with_bearer_token_and_json_body(): void
    {
        Log::shouldReceive('channel')->with('otp')->andReturnSelf();
        Log::shouldReceive('info')->twice()->andReturnNull();

        config([
            'services.sms.endpoint' => 'https://srscrm.ir/api/sms/send',
            'services.sms.token' => 'test-token',
            'services.sms.gateway' => '1000',
            'services.sms.log_channel' => 'otp',
        ]);

        Http::preventStrayRequests();

        Http::fake([
            'srscrm.ir/api/sms/send' => Http::response([
                'ok' => true,
                'code' => 'queued',
                'message' => 'queued',
                'data' => [
                    'message_id' => 1,
                ],
            ]),
        ]);

        $payload = app(SetareganSmsClient::class)->send('09123456789', 'code: 123456');

        $this->assertTrue($payload['ok']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://srscrm.ir/api/sms/send'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['to'] === '09123456789'
                && $request['message'] === 'code: 123456'
                && $request['gateway'] === '1000';
        });
    }

    public function test_it_throws_and_logs_error_when_api_returns_error(): void
    {
        Log::shouldReceive('channel')->with('otp')->andReturnSelf();
        Log::shouldReceive('info')->once()->andReturnNull();
        Log::shouldReceive('error')->once()->andReturnNull();

        config([
            'services.sms.endpoint' => 'https://srscrm.ir/api/sms/send',
            'services.sms.token' => 'bad-token',
            'services.sms.gateway' => '1000',
            'services.sms.log_channel' => 'otp',
        ]);

        Http::preventStrayRequests();

        Http::fake([
            'srscrm.ir/api/sms/send' => Http::response([
                'ok' => false,
                'code' => 'invalid_token',
                'message' => 'Invalid token',
                'data' => null,
            ], 401),
        ]);

        $this->expectException(SmsSendException::class);
        $this->expectExceptionMessage('Invalid token');

        app(SetareganSmsClient::class)->send('09123456789', 'code: 123456');
    }

    public function test_it_logs_and_throws_on_network_connection_exception(): void
    {
        Log::shouldReceive('channel')->with('otp')->andReturnSelf();
        Log::shouldReceive('info')->once()->andReturnNull();
        Log::shouldReceive('error')->once()->andReturnNull();

        config([
            'services.sms.endpoint' => 'https://srscrm.ir/api/sms/send',
            'services.sms.token' => 'test-token',
            'services.sms.gateway' => '1000',
            'services.sms.log_channel' => 'otp',
        ]);

        Http::preventStrayRequests();

        Http::fake([
            'srscrm.ir/api/sms/send' => function () {
                throw new ConnectionException('Could not resolve host');
            },
        ]);

        $this->expectException(SmsSendException::class);
        $this->expectExceptionMessage('Could not resolve host');

        app(SetareganSmsClient::class)->send('09123456789', 'code: 123456');
    }
}
