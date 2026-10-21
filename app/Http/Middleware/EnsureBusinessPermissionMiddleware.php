<?php

namespace App\Http\Middleware;

use App\Enums\Business\BusinessPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessPermissionMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->canInBusiness(BusinessPermission::from($permission))) {
            abort(403);
        }

        return $next($request);
    }
}
