<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request, Company $company): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive', 'blacklisted'])],
            'sort' => ['nullable', Rule::in(['name', 'supplier_code', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? 'all';
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        $suppliers = $company->suppliers()
            ->withCount(['priceBooks', 'priceBookItems'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $supplierQuery) use ($search): void {
                    $supplierQuery
                        ->where('supplier_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => $company->suppliers()->count(),
            'active' => $company->suppliers()->where('status', 'active')->count(),
            'inactive' => $company->suppliers()->where('status', 'inactive')->count(),
            'blacklisted' => $company->suppliers()->where('status', 'blacklisted')->count(),
        ];

        return view('suppliers.index', [
            'company' => $company,
            'suppliers' => $suppliers,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(Company $company): View
    {
        return view('suppliers.create', [
            'company' => $company,
            'supplier' => new Supplier(['status' => 'active']),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validatedData($request, $company);
        $data['company_id'] = $company->getKey();
        $data['created_by'] = $request->user()->getKey();
        $data['updated_by'] = $request->user()->getKey();

        $supplier = Supplier::query()->create($data);

        return redirect()
            ->route('companies.suppliers.show', [$company, $supplier->getKey()])
            ->with('success', 'Supplier created successfully.');
    }

    public function show(Company $company, string $supplier): View
    {
        $supplier = $this->findSupplier($company, $supplier);

        $supplier->load([
            'priceBooks' => fn ($query) => $query->withCount('items')->limit(8),
            'priceBookItems.material.category',
            'creator:id,name',
            'updater:id,name',
        ]);

        return view('suppliers.show', [
            'company' => $company,
            'supplier' => $supplier,
        ]);
    }

    public function edit(Company $company, string $supplier): View
    {
        return view('suppliers.edit', [
            'company' => $company,
            'supplier' => $this->findSupplier($company, $supplier),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, Company $company, string $supplier): RedirectResponse
    {
        $supplier = $this->findSupplier($company, $supplier);
        $data = $this->validatedData($request, $company, $supplier);
        $data['updated_by'] = $request->user()->getKey();

        $supplier->update($data);

        return redirect()
            ->route('companies.suppliers.show', [$company, $supplier->getKey()])
            ->with('success', 'Supplier updated successfully.');
    }

    public function archive(Company $company, string $supplier): RedirectResponse
    {
        $supplier = $this->findSupplier($company, $supplier);
        $supplier->update(['status' => 'inactive']);

        return back()->with('success', 'Supplier archived successfully.');
    }

    public function activate(Company $company, string $supplier): RedirectResponse
    {
        $supplier = $this->findSupplier($company, $supplier);
        $supplier->update(['status' => 'active']);

        return back()->with('success', 'Supplier activated successfully.');
    }

    private function findSupplier(Company $company, string $supplierId): Supplier
    {
        return $company->suppliers()
            ->whereKey($supplierId)
            ->firstOrFail();
    }

    private function validatedData(Request $request, Company $company, ?Supplier $supplier = null): array
    {
        $supplierCodeRule = Rule::unique('suppliers', 'supplier_code')
            ->where(fn ($query) => $query->where('company_id', $company->getKey()));

        if ($supplier) {
            $supplierCodeRule->ignore($supplier->getKey());
        }

        return $request->validate([
            'supplier_code' => [
                'required',
                'string',
                'max:80',
                $supplierCodeRule,
            ],
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:180'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'payment_terms' => ['nullable', 'string', 'max:180'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function statuses(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'blacklisted' => 'Blacklisted',
        ];
    }
}
