<?php

namespace App\Services\Sms;

use App\Exceptions\SmsSendException;
use Illuminate\Support\Facades\Http;

class SetareganSmsClient
{
    /**
     * @return array<string, mixed>
     */
    public function send(string $to, string $message): array
    {
        $response = Http::connectTimeout(3)
            ->timeout(10)
            ->withToken((string) config('services.sms.token'))
            ->acceptJson()
            ->asJson()
            ->post((string) config('services.sms.endpoint'), [
                'to' => $to,
                'message' => $message,
                'gateway' => config('services.sms.gateway'),
            ]);

        /** @var array{ok?: bool, code?: string, message?: string, data?: mixed} $payload */
        $payload = $response->json() ?? [];

        if ($response->successful() && ($payload['ok'] ?? false) === true) {
            return $payload;
        }

        throw new SmsSendException(
            (string) ($payload['code'] ?? 'server_error'),
            (string) ($payload['message'] ?? 'SMS send failed.'),
        );
    }
}
