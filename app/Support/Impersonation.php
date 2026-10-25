<?php

namespace App\Support;

use App\Models\User;

class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public static function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function impersonatorId(): ?int
    {
        $id = session(self::SESSION_KEY);

        return $id !== null ? (int) $id : null;
    }

    public static function impersonator(): ?User
    {
        $id = self::impersonatorId();

        if ($id === null) {
            return null;
        }

        return User::query()->find($id);
    }

    public static function start(User $impersonator): void
    {
        session()->put(self::SESSION_KEY, $impersonator->id);
    }

    public static function stop(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
