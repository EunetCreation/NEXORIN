<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActiveCompanyResolver
{
    /** @var array<int, Collection<int, Company>> */
    private array $availableCompaniesCache = [];

    public function resolve(User $user, Request $request): ?Company
    {
        $sessionCompanyId = (int) $request->session()->get($this->sessionKey(), 0);

        if ($sessionCompanyId > 0) {
            $company = Company::query()->active()->find($sessionCompanyId);

            if ($company && $user->canAccessCompany($company)) {
                return $company;
            }

            $request->session()->forget($this->sessionKey());
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        $companies = $this->availableCompanies($user);

        if ($companies->count() === 1) {
            return $this->activate($user, $companies->first(), $request);
        }

        return null;
    }

    public function requiresSelection(User $user, Request $request): bool
    {
        return ! $user->isSuperAdmin()
            && $this->resolve($user, $request) === null
            && $this->availableCompanies($user)->count() > 1;
    }

    /** @return Collection<int, Company> */
    public function availableCompanies(User $user): Collection
    {
        $userId = (int) $user->getKey();

        if (isset($this->availableCompaniesCache[$userId])) {
            return $this->availableCompaniesCache[$userId];
        }

        $companies = $user->isSuperAdmin()
            ? Company::query()->active()->orderBy('name')->get()
            : $user->activeCompanies()->orderBy('companies.name')->get();

        return $this->availableCompaniesCache[$userId] = $companies;
    }

    public function activate(User $user, Company $company, Request $request): Company
    {
        abort_unless(
            $company->isActive() && $user->canAccessCompany($company),
            403,
            'You do not have access to this company.'
        );

        $request->session()->put($this->sessionKey(), $company->getKey());

        return $company;
    }

    private function sessionKey(): string
    {
        return (string) config(
            'nexorin.authentication.active_company_session_key',
            'nexorin.active_company_id'
        );
    }
}
