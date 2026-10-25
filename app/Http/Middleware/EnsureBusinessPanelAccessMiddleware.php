<?php

namespace App\Http\Middleware;

use App\Enums\Business\BusinessPanel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessPanelAccessMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $panel): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $businessPanel = BusinessPanel::from($panel);

        if (! $user->canAccessPanel($businessPanel)) {
            foreach ([
                BusinessPanel::Accounting,
                BusinessPanel::Warehouse,
                BusinessPanel::Sale,
            ] as $fallback) {
                if ($user->canAccessPanel($fallback)) {
                    return redirect()->route($fallback->dashboardRoute());
                }
            }

            abort(403);
        }

        return $next($request);
    }
}
