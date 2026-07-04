<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\CompanyMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    /**
     * Display the company directory.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => [
                'nullable',
                Rule::in(['name', 'code', 'status', 'created_at']),
            ],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? null;
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        $companies = Company::query()
            ->with('creator:id,name,email')
            ->withCount('activeMemberships')
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $companyQuery) use ($search): void {
                        $companyQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('registration_number', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $status,
                fn (Builder $query) => $query->where('status', $status)
            )
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => Company::query()->count(),
            'active' => Company::query()->active()->count(),
            'inactive' => Company::query()->where('status', 'inactive')->count(),
            'memberships' => CompanyMembership::query()
                ->where('status', 'active')
                ->count(),
        ];

        return view('companies.index', [
            'companies' => $companies,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Show the form for creating a company.
     */
    public function create(): View
    {
        return view('companies.create');
    }

    /**
     * Store a newly created company.
     */
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $company = DB::transaction(function () use ($request): Company {
            $data = $request->validated();
            $data['slug'] = $this->makeUniqueSlug(
                $data['name'],
                $data['code']
            );
            $data['created_by'] = $request->user()->getKey();

            return Company::query()->create($data);
        });

        return redirect()
            ->route('companies.show', $company)
            ->with('success', 'Company created successfully.');
    }

    /**
     * Display a company profile.
     */
    public function show(Company $company): View
    {
        $company->load([
            'creator:id,name,email',
            'memberships' => fn ($query) => $query
                ->with([
                    'user:id,name,email',
                    'roles:id,code,name',
                ])
                ->latest('joined_at')
                ->latest('id'),
        ]);

        $company->loadCount([
            'memberships',
            'activeMemberships',
            'customers',
            'projects',
        ]);

        return view('companies.show', [
            'company' => $company,
        ]);
    }

    /**
     * Show the form for editing a company.
     */
    public function edit(Company $company): View
    {
        return view('companies.edit', [
            'company' => $company,
        ]);
    }

    /**
     * Update a company.
     */
    public function update(
        UpdateCompanyRequest $request,
        Company $company
    ): RedirectResponse {
        DB::transaction(function () use ($request, $company): void {
            $data = $request->validated();

            if (
                $company->name !== $data['name']
                || $company->code !== $data['code']
            ) {
                $data['slug'] = $this->makeUniqueSlug(
                    $data['name'],
                    $data['code'],
                    $company->getKey()
                );
            }

            $company->update($data);
        });

        return redirect()
            ->route('companies.show', $company)
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Update a company status from the company directory.
     */
    public function updateStatus(
        Request $request,
        Company $company
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $company->update([
            'status' => $validated['status'],
        ]);

        return back()->with(
            'success',
            $validated['status'] === 'active'
                ? 'Company activated successfully.'
                : 'Company deactivated successfully.'
        );
    }

    /**
     * Soft delete a company.
     */
    public function destroy(Company $company): RedirectResponse
    {
        if ($company->isActive()) {
            return back()->with(
                'error',
                'Deactivate the company before archiving it.'
            );
        }

        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company archived successfully.');
    }

    /**
     * Generate a unique company slug.
     */
    private function makeUniqueSlug(
        string $name,
        string $code,
        ?int $ignoreCompanyId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = Str::lower($code);
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (
            Company::withTrashed()
                ->where('slug', $slug)
                ->when(
                    $ignoreCompanyId,
                    fn (Builder $query) => $query->whereKeyNot($ignoreCompanyId)
                )
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
