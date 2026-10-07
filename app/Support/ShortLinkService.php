<?php

namespace App\Support;

use App\Models\ShortLink;
use Carbon\CarbonInterface;
use RuntimeException;

class ShortLinkService
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function create(string $destination, ?CarbonInterface $expiresAt = null): string
    {
        $code = $this->generateUniqueCode();

        ShortLink::query()->create([
            'code' => $code,
            'destination' => $destination,
            'expires_at' => $expiresAt,
        ]);

        return rtrim((string) config('shortlink.base_url'), '/').'/i/'.$code;
    }

    private function generateUniqueCode(): string
    {
        $length = max(4, (int) config('shortlink.code_length', 5));

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
