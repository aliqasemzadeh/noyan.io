<?php

namespace App\Services\Sms;

use App\Exceptions\SmsSendException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SetareganSmsClient
{
    /**
     * @return array<string, mixed>
     */
    public function send(string $to, string $message): array
    {
        $endpoint = (string) config('services.sms.endpoint');
        $token = (string) config('services.sms.token');
        $gateway = config('services.sms.gateway');
        $logChannel = (string) config('services.sms.log_channel', 'otp');

        $requestData = [
            'to' => $to,
            'message' => $message,
            'gateway' => $gateway,
        ];

        Log::channel($logChannel)->info('Dispatching OTP SMS request', [
            'endpoint' => $endpoint,
            'to' => $to,
            'gateway' => $gateway,
            'message' => $message,
        ]);

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $requestData);

            /** @var array{ok?: bool, code?: string, message?: string, data?: mixed} $payload */
            $payload = $response->json() ?? [];

            if ($response->successful() && ($payload['ok'] ?? false) === true) {
                Log::channel($logChannel)->info('OTP SMS sent successfully', [
                    'to' => $to,
                    'status' => $response->status(),
                    'response' => $payload,
                ]);

                return $payload;
            }

            $errorCode = (string) ($payload['code'] ?? 'server_error');
            $errorMessage = (string) ($payload['message'] ?? 'SMS send failed.');

            Log::channel($logChannel)->error('OTP SMS API returned error response', [
                'to' => $to,
                'status' => $response->status(),
                'response_body' => $response->body(),
                'payload' => $payload,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            throw new SmsSendException($errorCode, $errorMessage);
        } catch (SmsSendException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::channel($logChannel)->error('OTP SMS request failed due to connection/system exception', [
                'to' => $to,
                'endpoint' => $endpoint,
                'exception_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            throw new SmsSendException('connection_error', $e->getMessage());
        }
    }
}
