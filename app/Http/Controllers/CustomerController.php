<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Display the company customer directory.
     */
    public function index(
        Request $request,
        Company $company
    ): View {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'type' => [
                'nullable',
                Rule::in(['residential', 'commercial']),
            ],
            'sort' => [
                'nullable',
                Rule::in(['name', 'customer_number', 'type', 'status', 'created_at']),
            ],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? null;
        $type = $validated['type'] ?? null;
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        $customers = $company->customers()
            ->with('portalUser:id,name,email')
            ->withCount([
                'projects',
                'projects as active_projects_count' => fn (Builder $query) => $query
                    ->whereNull('archived_at')
                    ->whereNotIn('status', ['completed', 'cancelled']),
            ])
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $customerQuery) use ($search): void {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $status,
                fn (Builder $query) => $query->where('status', $status)
            )
            ->when(
                $type,
                fn (Builder $query) => $query->where('type', $type)
            )
            ->orderBy($sort, $direction)
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => $company->customers()->count(),
            'active' => $company->customers()->active()->count(),
            'residential' => $company->customers()
                ->where('type', 'residential')
                ->where('status', 'active')
                ->count(),
            'commercial' => $company->customers()
                ->where('type', 'commercial')
                ->where('status', 'active')
                ->count(),
        ];

        return view('customers.index', [
            'company' => $company,
            'customers' => $customers,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Show the customer creation form.
     */
    public function create(Company $company): View
    {
        return view('customers.create', [
            'company' => $company,
            'portalUsers' => $this->portalUsers($company),
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(
        StoreCustomerRequest $request,
        Company $company
    ): RedirectResponse {
        $customer = DB::transaction(function () use ($request, $company): Customer {
            $data = $request->validated();
            $data['company_id'] = $company->getKey();
            $data['status'] = 'active';
            $data['created_by'] = $request->user()->getKey();
            $data['updated_by'] = $request->user()->getKey();

            $customer = Customer::query()->create($data);

            $customer->update([
                'customer_number' => sprintf(
                    '%s-CUS-%05d',
                    strtoupper($company->code),
                    $customer->getKey()
                ),
            ]);

            return $customer;
        });

        return redirect()
            ->route('companies.customers.show', [$company, $customer])
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Display a customer profile.
     */
    public function show(
        Company $company,
        Customer $customer
    ): View {
        $customer->load([
            'portalUser:id,name,email,status',
            'creator:id,name,email',
            'updater:id,name,email',
            'projects' => fn ($query) => $query
                ->with([
                    'assignedEngineer:id,name',
                    'assignedSales:id,name',
                ])
                ->latest('created_at')
                ->limit(8),
        ]);

        $customer->loadCount([
            'projects',
            'projects as current_projects_count' => fn (Builder $query) => $query
                ->whereNull('archived_at'),
        ]);

        return view('customers.show', [
            'company' => $company,
            'customer' => $customer,
        ]);
    }

    /**
     * Show the customer edit form.
     */
    public function edit(
        Company $company,
        Customer $customer
    ): View {
        if (! $customer->isActive()) {
            abort(404);
        }

        return view('customers.edit', [
            'company' => $company,
            'customer' => $customer,
            'portalUsers' => $this->portalUsers($company),
        ]);
    }

    /**
     * Update a customer.
     */
    public function update(
        UpdateCustomerRequest $request,
        Company $company,
        Customer $customer
    ): RedirectResponse {
        if (! $customer->isActive()) {
            return back()->with(
                'error',
                'Archived customers cannot be updated.'
            );
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()->getKey();

        $customer->update($data);

        return redirect()
            ->route('companies.customers.show', [$company, $customer])
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Archive a customer record.
     */
    public function archive(
        Request $request,
        Company $company,
        Customer $customer
    ): RedirectResponse {
        if (! $customer->isActive()) {
            return back()->with('error', 'Customer is already archived.');
        }

        $hasOpenProjects = $customer->projects()
            ->whereNull('archived_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        if ($hasOpenProjects) {
            return back()->with(
                'error',
                'Complete, cancel, or archive the customer’s open projects before archiving this customer.'
            );
        }

        $customer->update([
            'status' => 'archived',
            'archived_at' => now(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('companies.customers.index', $company)
            ->with('success', 'Customer archived successfully.');
    }

    /**
     * Get active customer-role portal users for the company.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function portalUsers(Company $company)
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.email'])
            ->where('users.status', 'active')
            ->whereHas(
                'companyMemberships',
                fn (Builder $query) => $query
                    ->where('company_id', $company->getKey())
                    ->where('status', 'active')
                    ->whereHas(
                        'roles',
                        fn (Builder $roleQuery) => $roleQuery
                            ->where('roles.code', 'customer')
                            ->where('roles.scope', 'company')
                            ->where('roles.is_active', true)
                    )
            )
            ->orderBy('name')
            ->get();
    }
}
