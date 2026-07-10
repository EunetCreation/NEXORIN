<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceBookBulkItemController extends Controller
{
    public function bulkUpdate(Request $request, Company $company, string $priceBook): RedirectResponse
    {
        $priceBook = $this->findPriceBook($company, $priceBook);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.margin_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'items.*.minimum_order_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'items.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'items.*.availability_status' => ['required', Rule::in(array_keys($this->availabilityStatuses()))],
            'items.*.quote_status' => ['required', Rule::in(array_keys($this->quoteStatuses()))],
            'items.*.quoted_at' => ['nullable', 'date'],
            'items.*.valid_until' => ['nullable', 'date'],
        ]);

        $itemIds = array_map('intval', array_keys($data['items']));
        $items = $priceBook->items()
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        if ($items->count() !== count($itemIds)) {
            return back()
                ->withErrors(['items' => 'One or more selected price items do not belong to this price book.'])
                ->withInput();
        }

        $warnings = [];

        foreach ($data['items'] as $itemId => $payload) {
            $payload = $this->blankStringsToNull($payload);
            $unitCost = (float) $payload['unit_cost'];
            $sellingPrice = $payload['selling_price'] !== null ? (float) $payload['selling_price'] : null;

            if (
                ! empty($payload['quoted_at'])
                && ! empty($payload['valid_until'])
                && $payload['valid_until'] < $payload['quoted_at']
            ) {
                return back()
                    ->withErrors(['items' => 'Valid until date must be after or equal to quoted at date for item #'.$itemId.'.'])
                    ->withInput();
            }

            if ($sellingPrice !== null && $sellingPrice < $unitCost) {
                $warnings[] = 'Selling price is lower than unit cost for item #'.$itemId.'.';
            }

            $items->get((int) $itemId)->update($payload);
        }

        $message = count($itemIds).' price book item(s) updated successfully.';

        $redirect = redirect()
            ->route('companies.price-books.show', [$company, $priceBook->getKey()])
            ->with('success', $message);

        if ($warnings !== []) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    public function importForm(Company $company, string $priceBook): View
    {
        $priceBook = $this->findPriceBook($company, $priceBook)->load('supplier:id,name,supplier_code');

        return view('price_book_items.import', [
            'company' => $company,
            'priceBook' => $priceBook,
            'availabilityStatuses' => $this->availabilityStatuses(),
            'quoteStatuses' => $this->quoteStatuses(),
        ]);
    }

    public function previewImport(Request $request, Company $company, string $priceBook): View
    {
        $priceBook = $this->findPriceBook($company, $priceBook)->load('supplier:id,name,supplier_code');

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $preview = $this->buildImportPreview($request, $company, $priceBook);
        $sessionKey = $this->importSessionKey($priceBook);

        session()->put($sessionKey, [
            'batch_code' => $preview['batch_code'],
            'valid_rows' => $preview['valid_rows'],
        ]);

        return view('price_book_items.import_preview', [
            'company' => $company,
            'priceBook' => $priceBook,
            'previewRows' => $preview['rows'],
            'summary' => $preview['summary'],
            'batchCode' => $preview['batch_code'],
        ]);
    }

    public function commitImport(Company $company, string $priceBook): RedirectResponse
    {
        $priceBook = $this->findPriceBook($company, $priceBook);
        $sessionKey = $this->importSessionKey($priceBook);
        $payload = session()->get($sessionKey);

        if (! is_array($payload) || empty($payload['valid_rows'])) {
            return redirect()
                ->route('companies.price-books.items.import.form', [$company, $priceBook->getKey()])
                ->with('error', 'No valid import preview is available. Upload and preview a CSV file first.');
        }

        $created = 0;
        $updated = 0;

        foreach ($payload['valid_rows'] as $row) {
            $item = PriceBookItem::query()->firstOrNew([
                'price_book_id' => $priceBook->getKey(),
                'material_id' => $row['material_id'],
            ]);

            if ($item->exists) {
                $updated++;
            } else {
                $created++;
            }

            $item->fill(Arr::except($row, ['material_id']));
            $item->price_book_id = $priceBook->getKey();
            $item->material_id = $row['material_id'];
            $item->import_batch_code = $payload['batch_code'];
            $item->save();
        }

        session()->forget($sessionKey);

        return redirect()
            ->route('companies.price-books.show', [$company, $priceBook->getKey()])
            ->with('success', "Price import committed. {$created} created, {$updated} updated.");
    }

    /**
     * @return array{batch_code:string, rows:array<int, array<string, mixed>>, valid_rows:array<int, array<string, mixed>>, summary:array<string, int>}
     */
    private function buildImportPreview(Request $request, Company $company, PriceBook $priceBook): array
    {
        $file = $request->file('import_file');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [
                'batch_code' => $this->newBatchCode(),
                'rows' => [[
                    'row_number' => 0,
                    'material_label' => 'File error',
                    'supplier_label' => null,
                    'action' => 'error',
                    'status' => 'error',
                    'messages' => ['Unable to read uploaded CSV file.'],
                    'values' => [],
                ]],
                'valid_rows' => [],
                'summary' => ['total' => 1, 'valid' => 0, 'warning' => 0, 'error' => 1],
            ];
        }

        $header = null;
        $rows = [];
        $validRows = [];
        $seenMaterialIds = [];
        $rowNumber = 0;
        $batchCode = $this->newBatchCode();

        while (($columns = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isBlankCsvRow($columns)) {
                continue;
            }

            if ($header === null) {
                $header = $this->normalizeHeader($columns);
                continue;
            }

            $rawRow = $this->combineRow($header, $columns);
            $result = $this->mapImportRow($rawRow, $rowNumber, $company, $priceBook, $seenMaterialIds);
            $rows[] = $result['preview'];

            if ($result['valid_row'] !== null) {
                $validRows[] = $result['valid_row'];
                $seenMaterialIds[] = $result['valid_row']['material_id'];
            }
        }

        fclose($handle);

        if ($header === null) {
            $rows[] = [
                'row_number' => 0,
                'material_label' => 'Empty file',
                'supplier_label' => null,
                'action' => 'error',
                'status' => 'error',
                'messages' => ['CSV file has no header row.'],
                'values' => [],
            ];
        }

        $summary = [
            'total' => count($rows),
            'valid' => collect($rows)->where('status', 'valid')->count(),
            'warning' => collect($rows)->where('status', 'warning')->count(),
            'error' => collect($rows)->where('status', 'error')->count(),
        ];

        return [
            'batch_code' => $batchCode,
            'rows' => $rows,
            'valid_rows' => $validRows,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string, string|null> $row
     * @param array<int, int> $seenMaterialIds
     * @return array{preview:array<string, mixed>, valid_row:?array<string, mixed>}
     */
    private function mapImportRow(array $row, int $rowNumber, Company $company, PriceBook $priceBook, array $seenMaterialIds): array
    {
        $messages = [];
        $warnings = [];
        $materialKey = $this->firstValue($row, ['material_key', 'material_sku', 'sku', 'item_code', 'material']);
        $material = $this->resolveMaterial($company, $materialKey);
        $supplierKey = $this->firstValue($row, ['supplier_code', 'supplier', 'supplier_name']);
        $supplier = $supplierKey !== null ? $this->resolveSupplier($company, $supplierKey) : null;

        if ($material === null) {
            $messages[] = 'Material not found or not active for this company.';
        } elseif (in_array($material->getKey(), $seenMaterialIds, true)) {
            $messages[] = 'Duplicate material appears more than once in this import file.';
        }

        if ($supplierKey !== null && $supplier === null) {
            $messages[] = 'Supplier not found or inactive for this company.';
        }

        $unitCost = $this->decimalValue($this->firstValue($row, ['unit_cost', 'cost', 'harga_modal']));
        $sellingPrice = $this->nullableDecimalValue($this->firstValue($row, ['selling_price', 'harga_jual']));
        $marginPercent = $this->nullableDecimalValue($this->firstValue($row, ['margin_percent', 'margin']));
        $moq = $this->nullableDecimalValue($this->firstValue($row, ['minimum_order_quantity', 'moq']));
        $leadTime = $this->nullableIntegerValue($this->firstValue($row, ['lead_time_days', 'lead_time']));
        $availability = $this->firstValue($row, ['availability_status', 'availability']) ?? 'available';
        $quoteStatus = $this->firstValue($row, ['quote_status', 'status_quote']) ?? 'active';
        $quotedAt = $this->nullableDateValue($this->firstValue($row, ['quoted_at', 'quote_date']));
        $validUntil = $this->nullableDateValue($this->firstValue($row, ['valid_until', 'expired_at', 'expiry_date']));

        if ($unitCost === null || $unitCost < 0) {
            $messages[] = 'Unit cost must be a non-negative number.';
        }

        if ($sellingPrice !== null && $sellingPrice < 0) {
            $messages[] = 'Selling price must be a non-negative number.';
        }

        if ($marginPercent !== null && ($marginPercent < 0 || $marginPercent > 1000)) {
            $messages[] = 'Margin percent must be between 0 and 1000.';
        }

        if ($moq !== null && $moq < 0) {
            $messages[] = 'MOQ must be a non-negative number.';
        }

        if ($leadTime !== null && ($leadTime < 0 || $leadTime > 3650)) {
            $messages[] = 'Lead time must be between 0 and 3650 days.';
        }

        if (! array_key_exists($availability, $this->availabilityStatuses())) {
            $messages[] = 'Availability status is not supported.';
        }

        if (! array_key_exists($quoteStatus, $this->quoteStatuses())) {
            $messages[] = 'Quote status is not supported.';
        }

        if ($quotedAt === false) {
            $messages[] = 'Quoted at date is invalid.';
            $quotedAt = null;
        }

        if ($validUntil === false) {
            $messages[] = 'Valid until date is invalid.';
            $validUntil = null;
        }

        if ($quotedAt !== null && $validUntil !== null && $validUntil < $quotedAt) {
            $messages[] = 'Valid until date must be after or equal to quoted at date.';
        }

        if ($unitCost !== null && $sellingPrice !== null && $sellingPrice < $unitCost) {
            $warnings[] = 'Selling price is lower than unit cost.';
        }

        if ($validUntil !== null && $validUntil->isBefore(today())) {
            $warnings[] = 'Quote is already expired.';
        }

        if ($supplier === null && $priceBook->supplier_id === null) {
            $warnings[] = 'No supplier is linked to this row or the price book header.';
        }

        $existingItem = $material
            ? $priceBook->items()->where('material_id', $material->getKey())->first()
            : null;

        $status = $messages !== [] ? 'error' : ($warnings !== [] ? 'warning' : 'valid');
        $action = $messages !== [] ? 'error' : ($existingItem ? 'update' : 'create');
        $previewMessages = array_merge($messages, $warnings);

        $validRow = null;

        if ($messages === [] && $material !== null && $unitCost !== null) {
            $validRow = [
                'material_id' => $material->getKey(),
                'supplier_id' => $supplier?->getKey() ?? $priceBook->supplier_id,
                'supplier_sku' => $this->firstValue($row, ['supplier_sku', 'vendor_sku']),
                'unit_cost' => $unitCost,
                'selling_price' => $sellingPrice,
                'margin_percent' => $marginPercent,
                'minimum_order_quantity' => $moq,
                'lead_time_days' => $leadTime,
                'availability_status' => $availability,
                'quote_status' => $quoteStatus,
                'quoted_at' => $quotedAt?->toDateString(),
                'valid_until' => $validUntil?->toDateString(),
                'warranty_note' => $this->firstValue($row, ['warranty_note', 'warranty']),
                'notes' => $this->firstValue($row, ['notes', 'note']),
            ];
        }

        return [
            'preview' => [
                'row_number' => $rowNumber,
                'material_label' => $material?->display_name ?? $materialKey ?? 'No material key',
                'supplier_label' => $supplier?->name ?? $priceBook->supplier?->name,
                'action' => $action,
                'status' => $status,
                'messages' => $previewMessages !== [] ? $previewMessages : ['Ready to import.'],
                'values' => [
                    'unit_cost' => $unitCost,
                    'selling_price' => $sellingPrice,
                    'margin_percent' => $marginPercent,
                    'availability_status' => $availability,
                    'quote_status' => $quoteStatus,
                    'valid_until' => $validUntil instanceof Carbon ? $validUntil->toDateString() : null,
                ],
            ],
            'valid_row' => $validRow,
        ];
    }

    private function resolveMaterial(Company $company, ?string $key): ?Material
    {
        if ($key === null || trim($key) === '') {
            return null;
        }

        $key = trim($key);

        return Material::query()
            ->visibleForCompany($company)
            ->active()
            ->where(function ($query) use ($key): void {
                $query
                    ->where('sku', $key)
                    ->orWhere('name', $key)
                    ->orWhere('model', $key)
                    ->orWhereRaw('LOWER(sku) = ?', [Str::lower($key)])
                    ->orWhereRaw('LOWER(name) = ?', [Str::lower($key)]);
            })
            ->first();
    }

    private function resolveSupplier(Company $company, string $key): ?Supplier
    {
        $key = trim($key);

        return $company->suppliers()
            ->active()
            ->where(function ($query) use ($key): void {
                $query
                    ->where('supplier_code', $key)
                    ->orWhere('name', $key)
                    ->orWhereRaw('LOWER(supplier_code) = ?', [Str::lower($key)])
                    ->orWhereRaw('LOWER(name) = ?', [Str::lower($key)]);
            })
            ->first();
    }

    private function findPriceBook(Company $company, string $priceBookId): PriceBook
    {
        return $company->priceBooks()
            ->whereKey($priceBookId)
            ->firstOrFail();
    }

    private function normalizeHeader(array $columns): array
    {
        return array_map(function (?string $header): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

            return Str::of($header)
                ->trim()
                ->lower()
                ->replace([' ', '-', '.'], '_')
                ->toString();
        }, $columns);
    }

    private function combineRow(array $header, array $columns): array
    {
        $row = [];

        foreach ($header as $index => $field) {
            $row[$field] = isset($columns[$index]) ? trim((string) $columns[$index]) : null;
        }

        return $row;
    }

    private function isBlankCsvRow(array $columns): bool
    {
        return collect($columns)
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->isEmpty();
    }

    private function firstValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function decimalValue(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function nullableDecimalValue(?string $value): ?float
    {
        return $value === null ? null : $this->decimalValue($value);
    }

    private function nullableIntegerValue(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : -1;
    }

    private function nullableDateValue(?string $value): Carbon|false|null
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return false;
        }
    }

    private function importSessionKey(PriceBook $priceBook): string
    {
        return 'price_book_import_preview_'.$priceBook->getKey();
    }

    private function newBatchCode(): string
    {
        return 'PBIMP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
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
