<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\PriceBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceBookController extends Controller
{
    public function index(Request $request, Company $company): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'draft', 'active', 'archived'])],
            'supplier' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['name', 'code', 'status', 'effective_from', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? 'all';
        $supplierId = $validated['supplier'] ?? null;
        $sort = $validated['sort'] ?? 'effective_from';
        $direction = $validated['direction'] ?? 'desc';

        $priceBooks = $company->priceBooks()
            ->with('supplier:id,name,supplier_code')
            ->withCount('items')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $priceBookQuery) use ($search): void {
                    $priceBookQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('currency', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => $company->priceBooks()->count(),
            'active' => $company->priceBooks()->where('status', 'active')->count(),
            'draft' => $company->priceBooks()->where('status', 'draft')->count(),
            'archived' => $company->priceBooks()->where('status', 'archived')->count(),
        ];

        return view('price_books.index', [
            'company' => $company,
            'priceBooks' => $priceBooks,
            'suppliers' => $company->suppliers()->orderBy('name')->get(['id', 'name', 'supplier_code']),
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'supplier' => $supplierId,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(Company $company): View
    {
        return view('price_books.create', [
            'company' => $company,
            'priceBook' => new PriceBook([
                'currency' => 'IDR',
                'pricing_basis' => 'supplier_quote',
                'status' => 'draft',
            ]),
            'suppliers' => $company->suppliers()->active()->orderBy('name')->get(['id', 'name', 'supplier_code']),
            'statuses' => $this->statuses(),
            'pricingBases' => $this->pricingBases(),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validatedData($request, $company);
        $data['company_id'] = $company->getKey();
        $data['created_by'] = $request->user()->getKey();
        $data['updated_by'] = $request->user()->getKey();

        $priceBook = PriceBook::query()->create($data);

        return redirect()
            ->route('companies.price-books.show', [$company, $priceBook->getKey()])
            ->with('success', 'Price book created successfully.');
    }

    public function show(Company $company, string $priceBook): View
    {
        $priceBook = $this->findPriceBook($company, $priceBook);

        $priceBook->load([
            'supplier:id,name,supplier_code',
            'items.material.category',
            'items.supplier:id,name,supplier_code',
            'creator:id,name',
            'updater:id,name',
        ]);

        $items = $priceBook->items;
        $linkedMaterialIds = $items->pluck('material_id')->all();

        $itemStats = [
            'total' => $items->count(),
            'available' => $items->where('availability_status', 'available')->count(),
            'expired' => $items->filter(fn ($item): bool => $item->derived_quote_status === 'expired')->count(),
            'obsolete' => $items->filter(fn ($item): bool => $item->derived_quote_status === 'obsolete')->count(),
            'unavailable' => $items->whereIn('availability_status', ['unavailable', 'discontinued'])->count(),
            'missing_cost' => $items->filter(fn ($item): bool => $item->price_health_status === 'missing_cost')->count(),
            'missing_selling_reference' => $items->filter(fn ($item): bool => $item->price_health_status === 'missing_selling_reference')->count(),
            'low_margin' => $items->filter(fn ($item): bool => $item->price_health_status === 'low_margin')->count(),
            'healthy' => $items->filter(fn ($item): bool => $item->price_health_status === 'healthy')->count(),
        ];

        $materials = Material::query()
            ->visibleForCompany($company)
            ->active()
            ->with('category:id,name,code')
            ->when($linkedMaterialIds !== [], fn (Builder $query) => $query->whereNotIn('id', $linkedMaterialIds))
            ->orderBy('name')
            ->orderBy('brand')
            ->get(['id', 'company_id', 'material_category_id', 'sku', 'name', 'brand', 'model', 'uom']);

        return view('price_books.show', [
            'company' => $company,
            'priceBook' => $priceBook,
            'materials' => $materials,
            'suppliers' => $company->suppliers()->active()->orderBy('name')->get(['id', 'name', 'supplier_code']),
            'availabilityStatuses' => $this->availabilityStatuses(),
            'quoteStatuses' => $this->quoteStatuses(),
            'itemStats' => $itemStats,
        ]);
    }

    public function edit(Company $company, string $priceBook): View
    {
        return view('price_books.edit', [
            'company' => $company,
            'priceBook' => $this->findPriceBook($company, $priceBook),
            'suppliers' => $company->suppliers()->orderBy('name')->get(['id', 'name', 'supplier_code']),
            'statuses' => $this->statuses(),
            'pricingBases' => $this->pricingBases(),
        ]);
    }

    public function update(Request $request, Company $company, string $priceBook): RedirectResponse
    {
        $priceBook = $this->findPriceBook($company, $priceBook);
        $data = $this->validatedData($request, $company, $priceBook);
        $data['updated_by'] = $request->user()->getKey();

        $priceBook->update($data);

        return redirect()
            ->route('companies.price-books.show', [$company, $priceBook->getKey()])
            ->with('success', 'Price book updated successfully.');
    }

    public function archive(Company $company, string $priceBook): RedirectResponse
    {
        $priceBook = $this->findPriceBook($company, $priceBook);
        $priceBook->update(['status' => 'archived']);

        return back()->with('success', 'Price book archived successfully.');
    }

    public function activate(Company $company, string $priceBook): RedirectResponse
    {
        $priceBook = $this->findPriceBook($company, $priceBook);
        $priceBook->update(['status' => 'active']);

        return back()->with('success', 'Price book activated successfully.');
    }

    private function findPriceBook(Company $company, string $priceBookId): PriceBook
    {
        return $company->priceBooks()
            ->whereKey($priceBookId)
            ->firstOrFail();
    }

    private function validatedData(Request $request, Company $company, ?PriceBook $priceBook = null): array
    {
        $priceBookCodeRule = Rule::unique('price_books', 'code')
            ->where(fn ($query) => $query->where('company_id', $company->getKey()));

        if ($priceBook) {
            $priceBookCodeRule->ignore($priceBook->getKey());
        }

        return $request->validate([
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $company->getKey())),
            ],
            'code' => [
                'required',
                'string',
                'max:80',
                $priceBookCodeRule,
            ],
            'name' => ['required', 'string', 'max:180'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'pricing_basis' => ['required', Rule::in(array_keys($this->pricingBases()))],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function statuses(): array
    {
        return [
            'draft' => 'Draft',
            'active' => 'Active',
            'archived' => 'Archived',
        ];
    }

    private function pricingBases(): array
    {
        return [
            'supplier_quote' => 'Supplier Quote',
            'internal_estimate' => 'Internal Estimate',
            'market_reference' => 'Market Reference',
            'contract_price' => 'Contract Price',
        ];
    }

    private function availabilityStatuses(): array
    {
        return [
            'available' => 'Available',
            'limited' => 'Limited Stock',
            'backorder' => 'Backorder',
            'unavailable' => 'Unavailable',
            'discontinued' => 'Discontinued',
        ];
    }

    private function quoteStatuses(): array
    {
        return [
            'active' => 'Active Quote',
            'obsolete' => 'Obsolete Quote',
        ];
    }
}
