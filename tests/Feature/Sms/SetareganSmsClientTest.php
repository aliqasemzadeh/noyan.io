<?php

namespace Tests\Feature\Sms;

use App\Exceptions\SmsSendException;
use App\Services\Sms\SetareganSmsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetareganSmsClientTest extends TestCase
{
    public function test_it_sends_sms_with_bearer_token_and_json_body(): void
    {
        config([
            'services.sms.endpoint' => 'https://srscrm.ir/api/sms/send',
            'services.sms.token' => 'test-token',
            'services.sms.gateway' => '1000',
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

    public function test_it_throws_when_api_returns_error(): void
    {
        config([
            'services.sms.endpoint' => 'https://srscrm.ir/api/sms/send',
            'services.sms.token' => 'bad-token',
            'services.sms.gateway' => '1000',
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
}
