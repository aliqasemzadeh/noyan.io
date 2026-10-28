<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Impersonation;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RefreshPermanentLogin
{
    public const PERMANENT_LIFETIME_MINUTES = 576000;

    /**
     * Renew the remember-me cookie on each visit so login does not expire.
     * Browsers drop a cookie after 400 days without a visit; this resets that window.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = auth('web');

        if ($guard instanceof SessionGuard) {
            $guard->setRememberDuration($this->minutes());
            $guard->user();
        }

        $response = $next($request);

        if ($guard instanceof SessionGuard) {
            $this->refreshRememberCookie($guard);
        }

        return $response;
    }

    protected function refreshRememberCookie(SessionGuard $guard): void
    {
        $user = $guard->user();

        if (! $user instanceof User || Impersonation::isImpersonating()) {
            return;
        }

        $token = $user->getRememberToken();

        if (! is_string($token) || $token === '') {
            $token = Str::random(60);
            $guard->getProvider()->updateRememberToken($user, $token);
        }

        $jar = $guard->getCookieJar();

        $jar->queue($jar->make(
            $guard->getRecallerName(),
            $user->getAuthIdentifier().'|'.$token.'|'.$guard->hashPasswordForCookie($user->getAuthPassword()),
            $this->minutes(),
        ));
    }

    protected function minutes(): int
    {
        return max(self::PERMANENT_LIFETIME_MINUTES, (int) config('session.lifetime'));
    }
}
