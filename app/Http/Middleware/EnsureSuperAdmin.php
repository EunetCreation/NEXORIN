<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            abort(
                Response::HTTP_UNAUTHORIZED,
                'Authentication is required.'
            );
        }

        if (! $user->isSuperAdmin()) {
            abort(
                Response::HTTP_FORBIDDEN,
                'Super Admin access is required.'
            );
        }

        return $next($request);
    }
}
