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
            if ($user->canAccessPanel(BusinessPanel::Sale)) {
                return redirect()->route('sale.dashboard');
            }

            if ($user->canAccessPanel(BusinessPanel::Accounting)) {
                return redirect()->route('accounting.dashboard');
            }

            abort(403);
        }

        return $next($request);
    }
}
