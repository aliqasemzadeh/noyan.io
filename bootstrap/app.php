<?php

use App\Http\Middleware\CheckBannedIp;
use App\Http\Middleware\EnsureBusinessIsSelectedMiddleware;
use App\Http\Middleware\EnsureBusinessPanelAccessMiddleware;
use App\Http\Middleware\EnsureBusinessPermissionMiddleware;
use App\Http\Middleware\RefreshPermanentLogin;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Added by EnvKit so shared (public) URLs keep the https scheme and
        // public host. Safe locally; remove to opt out.
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->web(append: [
            SetLocale::class,
            CheckBannedIp::class,
            RefreshPermanentLogin::class,
        ]);
        $middleware->alias([
            'business.selected' => EnsureBusinessIsSelectedMiddleware::class,
            'business.panel' => EnsureBusinessPanelAccessMiddleware::class,
            'business.permission' => EnsureBusinessPermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
