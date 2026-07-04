<?php

use App\Http\Middleware\EnsureCompanyAccess;
use App\Http\Middleware\EnsureCompanyPermission;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'company.access' => EnsureCompanyAccess::class,
            'company.permission' => EnsureCompanyPermission::class,
            'system.super_admin' => EnsureSuperAdmin::class,
            'user.active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*'),
        );
    })
    ->create();