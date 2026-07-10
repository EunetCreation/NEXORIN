<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceBookItemController extends Controller
{
    /**
     * Store a material price line in a company price book.
     */
    public function store(Request $request, Company $company, string $priceBook): RedirectResponse
    {
        $priceBook = $this->findPriceBook($company, $priceBook);
        $data = $this->validatedData($request, $company, $priceBook);

        $priceBook->items()->create($data);

        return redirect()
            ->route('companies.price-books.show', [$company, $priceBook->getKey()])
            ->with('success', 'Price book item added successfully.');
    }

    /**
     * Show the price item edit form.
     */
    public function edit(Company $company, string $priceBook, string $priceBookItem): View
    {
        $priceBook = $this->findPriceBook($company, $priceBook);
        $priceBookItem = $this->findPriceBookItem($priceBook, $priceBookItem);

        return view('price_book_items.edit', [
            'company' => $company,
            'priceBook' => $priceBook->load('supplier:id,name,supplier_code'),
            'priceBookItem' => $priceBookItem->load(['material.category', 'supplier:id,name,supplier_code']),
            'materials' => $this->materialsForForm($company, $priceBook, $priceBookItem),
            'suppliers' => $this->suppliersForForm($company),
            'availabilityStatuses' => $this->availabilityStatuses(),
            'quoteStatuses' => $this->quoteStatuses(),
        ]);
    }

    /**
     * Update a material price line.
     */
    public function update(Request $request, Company $company, string $priceBook, string $priceBookItem): RedirectResponse
    {
        $priceBook = $this->findPriceBook($company, $priceBook);
        $priceBookItem = $this->findPriceBookItem($priceBook, $priceBookItem);
        $data = $this->validatedData($request, $company, $priceBook, $priceBookItem);

        $priceBookItem->update($data);

        return redirect()
            ->route('companies.price-books.show', [$company, $priceBook->getKey()])
            ->with('success', 'Price book item updated successfully.');
    }

    /**
     * Remove a material price line from a price book.
     */
    public function destroy(Company $company, string $priceBook, string $priceBookItem): RedirectResponse
    {
        $priceBook = $this->findPriceBook($company, $priceBook);
        $priceBookItem = $this->findPriceBookItem($priceBook, $priceBookItem);

        $priceBookItem->delete();

        return redirect()
            ->route('companies.price-books.show', [$company, $priceBook->getKey()])
            ->with('success', 'Price book item removed successfully.');
    }

    private function findPriceBook(Company $company, string $priceBookId): PriceBook
    {
        return $company->priceBooks()
            ->whereKey($priceBookId)
            ->firstOrFail();
    }

    private function findPriceBookItem(PriceBook $priceBook, string $priceBookItemId): PriceBookItem
    {
        return $priceBook->items()
            ->whereKey($priceBookItemId)
            ->firstOrFail();
    }

    private function validatedData(
        Request $request,
        Company $company,
        PriceBook $priceBook,
        ?PriceBookItem $priceBookItem = null
    ): array {
        $materialUniqueRule = Rule::unique('price_book_items', 'material_id')
            ->where(fn ($query) => $query->where('price_book_id', $priceBook->getKey()));

        if ($priceBookItem) {
            $materialUniqueRule->ignore($priceBookItem->getKey());
        }

        $data = $request->validate([
            'material_id' => [
                'required',
                'integer',
                Rule::exists('materials', 'id')->where(function ($query) use ($company): void {
                    $query
                        ->where('is_active', true)
                        ->where(function ($visibleQuery) use ($company): void {
                            $visibleQuery
                                ->whereNull('company_id')
                                ->orWhere('company_id', $company->getKey());
                        });
                }),
                $materialUniqueRule,
            ],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(function ($query) use ($company): void {
                    $query
                        ->where('company_id', $company->getKey())
                        ->where('status', 'active');
                }),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'minimum_order_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'availability_status' => ['required', Rule::in(array_keys($this->availabilityStatuses()))],
            'quote_status' => ['nullable', Rule::in(array_keys($this->quoteStatuses()))],
            'quoted_at' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quoted_at'],
            'warranty_note' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = $this->blankStringsToNull($data);
        $data['quote_status'] = $data['quote_status'] ?? 'active';

        return $data;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Material>
     */
    private function materialsForForm(Company $company, PriceBook $priceBook, ?PriceBookItem $currentItem = null)
    {
        $linkedMaterialIds = $priceBook->items()
            ->when(
                $currentItem,
                fn ($query) => $query->where('id', '!=', $currentItem->getKey())
            )
            ->pluck('material_id')
            ->all();

        return Material::query()
            ->visibleForCompany($company)
            ->active()
            ->with('category:id,name,code')
            ->when($linkedMaterialIds !== [], fn ($query) => $query->whereNotIn('id', $linkedMaterialIds))
            ->orderBy('name')
            ->orderBy('brand')
            ->get(['id', 'company_id', 'material_category_id', 'sku', 'name', 'brand', 'model', 'uom']);
    }

    private function suppliersForForm(Company $company)
    {
        return $company->suppliers()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'supplier_code']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function blankStringsToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;
            }
        }

        return $data;
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
