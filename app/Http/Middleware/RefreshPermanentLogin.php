<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Impersonation;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshPermanentLogin
{
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

        if ($token === '') {
            return;
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
        return max(1, (int) config('session.lifetime'));
    }
}
