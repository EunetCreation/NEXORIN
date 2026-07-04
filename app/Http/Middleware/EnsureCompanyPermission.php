<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$permissionCodes
    ): Response {
        $user = $request->user();

        if (! $user) {
            abort(
                Response::HTTP_UNAUTHORIZED,
                'Authentication is required.'
            );
        }

        $company = $request->attributes->get('current_company');

        if (! $company instanceof Company) {
            abort(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Company context is unavailable.'
            );
        }

        $permissionCodes = array_values(array_unique(array_filter(
            array_map(
                fn (string $code): string => trim($code),
                $permissionCodes
            ),
            fn (string $code): bool => $code !== ''
        )));

        if ($permissionCodes === []) {
            abort(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Permission configuration is invalid.'
            );
        }

        $availablePermissionCount = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->where('is_active', true)
            ->count();

        if ($availablePermissionCount !== count($permissionCodes)) {
            abort(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Permission configuration is unavailable.'
            );
        }

        if (! $user->hasAnyCompanyPermission($company, $permissionCodes)) {
            return $this->denyAccess($request);
        }

        return $next($request);
    }

    /**
     * Return an appropriate denial response for browser or JSON requests.
     */
    private function denyAccess(Request $request): Response
    {
        $message = 'You do not have permission to access this feature.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', $message);
    }
}
