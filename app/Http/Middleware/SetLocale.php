<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if (! is_string($locale) || $locale === '') {
            try {
                $locale = app(GeneralSettings::class)->locale;
            } catch (Throwable) {
                $locale = config('app.locale');
            }
        }

        if (in_array($locale, ['en', 'fa'], true)) {
            App::setLocale($locale);
        }

        try {
            $timezone = app(GeneralSettings::class)->timezone;

            if (is_string($timezone) && $timezone !== '') {
                date_default_timezone_set($timezone);
                config(['app.timezone' => $timezone]);
            }
        } catch (Throwable) {
            // Settings table may not be ready during migrate/boot.
        }

        return $next($request);
    }
}
