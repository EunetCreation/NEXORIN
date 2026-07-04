<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait AuthorizesProjectResource
{
    /**
     * Authorize access to a project-scoped resource.
     *
     * @param  array<int, string>  $companyPermissions
     * @param  array<int, string>  $ownPermissions
     */
    protected function authorizeProjectResource(
        Request $request,
        Company $company,
        Project $project,
        array $companyPermissions,
        array $ownPermissions
    ): void {
        $user = $request->user();

        if ((int) $project->company_id !== (int) $company->getKey()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($user->hasAnyCompanyPermission($company, $companyPermissions)) {
            return;
        }

        if (
            $user->hasAnyCompanyPermission($company, $ownPermissions)
            && $project->isOwnedBy($user)
        ) {
            return;
        }

        if ($user->hasAnyCompanyPermission($company, $ownPermissions)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        abort(Response::HTTP_FORBIDDEN);
    }

    /**
     * Determine whether a user can manage a project-scoped resource.
     *
     * @param  array<int, string>  $companyPermissions
     * @param  array<int, string>  $ownPermissions
     */
    protected function canManageProjectResource(
        Request $request,
        Company $company,
        Project $project,
        array $companyPermissions,
        array $ownPermissions
    ): bool {
        $user = $request->user();

        if ($user->hasAnyCompanyPermission($company, $companyPermissions)) {
            return true;
        }

        return $user->hasAnyCompanyPermission($company, $ownPermissions)
            && $project->isOwnedBy($user);
    }
}
