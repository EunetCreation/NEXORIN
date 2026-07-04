<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAccess
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

        $company = $request->route('company');

        if (! $company instanceof Company) {
            abort(
                Response::HTTP_NOT_FOUND,
                'Company not found.'
            );
        }

        /*
         * Inactive companies cannot be used for operational routes.
         * Super Admins can still manage them through the protected
         * company administration routes.
         */
        if (! $company->isActive()) {
            return $this->denyAccess(
                $request,
                'This company is inactive. Please contact a NEXORIN administrator.'
            );
        }

        /*
         * Super Admins can access every active company without
         * requiring a company membership.
         */
        if ($user->isSuperAdmin()) {
            $request->attributes->set(
                'current_company',
                $company
            );

            return $next($request);
        }

        $membership = $user->companyMemberships()
            ->where('company_id', $company->getKey())
            ->first();

        if (! $membership) {
            return $this->denyAccess(
                $request,
                'You do not have access to this company.'
            );
        }

        if (! $membership->isActive()) {
            return $this->denyAccess(
                $request,
                'Your access to this company is inactive. Please contact your company administrator.'
            );
        }

        /*
         * Store the authorized company in the request so controllers
         * and permission middleware use the same verified context.
         */
        $request->attributes->set(
            'current_company',
            $company
        );

        return $next($request);
    }

    /**
     * Return an appropriate denial response for browser or JSON requests.
     */
    private function denyAccess(
        Request $request,
        string $message
    ): Response {
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
