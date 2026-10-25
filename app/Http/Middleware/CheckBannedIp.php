<?php

namespace App\Http\Middleware;

use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckBannedIp
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $bannedIps = app(SecuritySettings::class)->banned_ips;
        } catch (Throwable) {
            return $next($request);
        }

        if (in_array($request->ip(), $bannedIps, true)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
