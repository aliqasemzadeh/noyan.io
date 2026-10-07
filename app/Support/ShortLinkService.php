<?php

namespace App\Support;

use App\Models\Business;
use App\Models\ShortLink;
use Carbon\CarbonInterface;
use RuntimeException;

class ShortLinkService
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function create(
        string $destination,
        ?Business $business = null,
        ?CarbonInterface $expiresAt = null,
    ): string {
        $code = $this->generateUniqueCode();

        ShortLink::query()->create([
            'business_id' => $business?->id,
            'code' => $code,
            'destination' => $destination,
            'expires_at' => $expiresAt,
        ]);

        return $this->urlForCode($code);
    }

    public function urlForCode(string $code): string
    {
        $base = rtrim((string) (config('short-link.short_url') ?: config('app.url')), '/');

        return $base.'/i/'.$code;
    }

    private function generateUniqueCode(): string
    {
        $length = max(4, (int) config('short-link.code_length', 5));

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = $this->randomCode($length);

            if (! ShortLink::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique short link code.');
    }

    private function randomCode(int $length): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }
}
