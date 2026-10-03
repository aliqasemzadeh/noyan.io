<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessIsSelectedMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($request->routeIs('user.businesses.create')) {
            return $next($request);
        }

        $user->ensureCurrentBusiness();

        if ($user->current_business_id === null) {
            return redirect()->route('user.businesses.create');
        }

        return $next($request);
    }
}
