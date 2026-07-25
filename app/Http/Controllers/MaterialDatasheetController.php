<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialDatasheet;
use App\Models\MaterialDatasheetApplication;
use App\Services\Catalog\DatasheetApplicationService;
use App\Services\Catalog\DatasheetParser;
use App\Services\Catalog\DatasheetTemporaryWorkspace;
use App\Services\Catalog\DatasheetUploadPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class MaterialDatasheetController extends Controller
{
    public function store(
        Request $request,
        Company $company,
        string $material,
        DatasheetParser $parser,
        DatasheetUploadPolicy $uploadPolicy
    ): RedirectResponse {
        $material = $this->findCompanyMaterial($company, $material);

        $uploadedFile = $request->file('datasheet_file');
        if ($uploadedFile instanceof UploadedFile && ! $uploadedFile->isValid()) {
            throw ValidationException::withMessages([
                'datasheet_file' => $uploadPolicy->uploadErrorMessage($uploadedFile),
            ]);
        }

        if (! $uploadPolicy->summary()['file_uploads_enabled'] && ! $request->filled('external_url')) {
            throw ValidationException::withMessages([
                'datasheet_file' => $uploadPolicy->fileUploadsDisabledMessage(),
            ]);
        }

        $maxKb = $uploadPolicy->effectiveLimitKb();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'datasheet_file' => ['required_without:external_url', 'nullable', 'file', 'max:'.$maxKb],
            'external_url' => ['required_without:datasheet_file', 'nullable', 'url', 'max:255'],
            'revision' => ['nullable', 'string', 'max:60'],
            'issued_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'variant_power_wp' => ['nullable', 'numeric', 'min:1', 'max:5000'],
        ]);

        $filePath = null;
        $originalFilename = null;
        $mimeType = null;
        $fileSize = null;
        $sha256 = null;
        $parseResult = [
            'status' => 'pending',
            'text' => '',
            'fields' => [],
            'confidence' => 0,
            'warnings' => ['No local file was uploaded. External URL evidence requires manual review.'],
            'parser_version' => DatasheetParser::VERSION,
            'page_count' => null,
            'extraction_method' => 'external_reference',
            'extraction_meta' => ['category' => ['blocked' => false, 'match' => 'uncertain']],
        ];

        if ($request->hasFile('datasheet_file')) {
            $file = $request->file('datasheet_file');
            $this->assertSupportedFile($file->getRealPath(), (string) $file->getClientOriginalExtension(), (string) $file->getMimeType());

            $sha256 = hash_file('sha256', $file->getRealPath());
            $duplicate = MaterialDatasheet::query()
                ->where('material_id', $material->getKey())
                ->where('sha256', $sha256)
                ->where('is_active', true)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['datasheet_file' => 'This exact datasheet file is already registered as active evidence for the material.']);
            }

            $originalFilename = $this->sanitizeOriginalFilename((string) $file->getClientOriginalName());
            $mimeType = (string) $file->getMimeType();
            $fileSize = $file->getSize();
            $parseResult = $parser->parseUploadedFile($file, $material, [
                'variant_power_wp' => $validated['variant_power_wp'] ?? null,
                'document_name' => $originalFilename,
            ]);
            $filePath = $file->store("material-datasheets/{$company->getKey()}/{$material->getKey()}");
        }

        try {
            $datasheet = MaterialDatasheet::query()->create([
                'material_id' => $material->getKey(),
                'title' => $validated['title'],
                'original_filename' => $originalFilename,
                'file_path' => $filePath,
                'external_url' => $validated['external_url'] ?? null,
                'mime_type' => $mimeType,
                'file_size_bytes' => $fileSize,
                'sha256' => $sha256,
                'page_count' => $parseResult['page_count'] ?? null,
                'extraction_method' => $parseResult['extraction_method'] ?? null,
                'detected_category_code' => data_get($parseResult, 'extraction_meta.category.detected_root'),
                'category_match_score' => data_get($parseResult, 'extraction_meta.category.score'),
                'revision' => $validated['revision'] ?? null,
                'issued_at' => $validated['issued_at'] ?? null,
                'is_active' => true,
                'extraction_status' => $parseResult['status'],
                'parser_version' => $parseResult['parser_version'],
                'parse_confidence' => $parseResult['confidence'],
                'parsed_text' => $parseResult['text'],
                'parsed_fields' => $parseResult['fields'],
                'parse_warnings' => $parseResult['warnings'],
                'extraction_meta' => $parseResult['extraction_meta'] ?? null,
                'parsed_at' => $request->hasFile('datasheet_file') ? now() : null,
                'uploaded_by' => $request->user()->getKey(),
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (Throwable $exception) {
            if ($filePath !== null) {
                Storage::delete($filePath);
            }
            throw $exception;
        }

        $material->markForCatalogReview($request->user()->getKey());

        $message = match ($datasheet->extraction_status) {
            'parsed' => 'Datasheet uploaded and parsed. Review each proposed field before applying it to the material.',
            'category_mismatch' => data_get($parseResult, 'extraction_meta.category.review_reason') === 'exact_component_mismatch'
                ? 'Datasheet uploaded, but its exact component subtype conflicts with this material record. Technical application is blocked.'
                : 'Datasheet uploaded, but category mismatch protection blocked technical application.',
            'variant_selection_required' => 'Datasheet uploaded and its multi-variant table was detected. Select the exact product power, then reparse before applying electrical values.',
            default => 'Datasheet uploaded. Manual review is required before it can be used as technical evidence.',
        };

        return redirect()
            ->route('companies.materials.show', [$company, $material->getKey()])
            ->with('success', $message);
    }

    public function reparse(
        Request $request,
        Company $company,
        string $material,
        MaterialDatasheet $datasheet,
        DatasheetParser $parser,
        DatasheetTemporaryWorkspace $workspace
    ): RedirectResponse {
        $material = $this->findCompanyMaterial($company, $material);
        $this->assertDatasheetOwnership($material, $datasheet);
        $validated = $request->validate([
            'variant_power_wp' => ['nullable', 'numeric', 'min:1', 'max:5000'],
        ]);

        if (! filled($datasheet->file_path) || ! Storage::exists($datasheet->file_path)) {
            return back()->with('error', 'The local datasheet source is unavailable. External references cannot be reparsed automatically.');
        }

        if ($datasheet->applications()->where('status', 'applied')->exists()) {
            return back()->with('error', 'Rollback the active parser application before reparsing this datasheet.');
        }

        try {
            $temporaryPath = $workspace->createPath();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'NEXORIN could not create its protected datasheet parser workspace. Ensure the Laravel storage/framework directory is writable, then try again.');
        }

        try {
            $contents = Storage::get($datasheet->file_path);
            if (file_put_contents($temporaryPath, $contents) === false) {
                return back()->with('error', 'The datasheet source could not be copied into the parser workspace.');
            }

            $file = new UploadedFile(
                $temporaryPath,
                $datasheet->original_filename ?: basename($datasheet->file_path),
                $datasheet->mime_type ?: null,
                null,
                true
            );
            $result = $parser->parseUploadedFile($file, $material, [
                'variant_power_wp' => $validated['variant_power_wp'] ?? null,
                'document_name' => $datasheet->original_filename ?: basename($datasheet->file_path),
            ]);
            $fingerprint = $datasheet->sha256 ?: hash_file('sha256', $temporaryPath);
        } catch (Throwable) {
            return back()->with('error', 'The stored datasheet could not be opened for reparsing.');
        } finally {
            $workspace->remove($temporaryPath);
        }

        $datasheet->update([
            'sha256' => $fingerprint,
            'page_count' => $result['page_count'] ?? null,
            'extraction_method' => $result['extraction_method'] ?? null,
            'detected_category_code' => data_get($result, 'extraction_meta.category.detected_root'),
            'category_match_score' => data_get($result, 'extraction_meta.category.score'),
            'extraction_status' => $result['status'],
            'parser_version' => $result['parser_version'],
            'parse_confidence' => $result['confidence'],
            'parsed_text' => $result['text'],
            'parsed_fields' => $result['fields'],
            'parse_warnings' => $result['warnings'],
            'extraction_meta' => $result['extraction_meta'] ?? null,
            'parsed_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'applied_at' => null,
            'applied_by' => null,
        ]);
        $material->markForCatalogReview($request->user()->getKey());

        $message = match ($result['status'] ?? null) {
            'variant_selection_required' => 'The datasheet contains multiple product variants. Select an exact power variant and reparse again.',
            'category_mismatch' => data_get($result, 'extraction_meta.category.review_reason') === 'exact_component_mismatch'
                ? 'Reparse confirmed an exact component mismatch. Use the matching material subtype or a matching datasheet before applying values.'
                : 'Reparse confirmed a document category mismatch. Technical application remains blocked.',
            default => 'Datasheet reparsed with the current schema-aware parser. Review every candidate before applying it.',
        };

        return back()->with('success', $message);
    }

    public function applyParsedSpecs(
        Request $request,
        Company $company,
        string $material,
        MaterialDatasheet $datasheet,
        DatasheetApplicationService $applicationService
    ): RedirectResponse {
        $material = $this->findCompanyMaterial($company, $material);
        $this->assertDatasheetOwnership($material, $datasheet);

        $validated = $request->validate([
            'decisions' => ['nullable', 'array'],
            'decisions.*.action' => ['required_with:decisions', 'string', 'in:accept,keep,reject'],
            'decisions.*.value' => ['nullable', 'string', 'max:2000'],
            'decisions.*.unit' => ['nullable', 'string', 'max:40'],
        ]);

        $application = $applicationService->apply(
            $material,
            $datasheet,
            (array) ($validated['decisions'] ?? []),
            $request->user()
        );

        $accepted = collect($application->field_decisions)->where('action', 'accept')->count();

        return back()->with('success', "{$accepted} reviewed datasheet field(s) applied. Catalog approval remains subject to full evidence governance.");
    }

    public function rollback(
        Request $request,
        Company $company,
        string $material,
        MaterialDatasheet $datasheet,
        MaterialDatasheetApplication $application,
        DatasheetApplicationService $applicationService
    ): RedirectResponse {
        $material = $this->findCompanyMaterial($company, $material);
        $this->assertDatasheetOwnership($material, $datasheet);

        $validated = $request->validate([
            'rollback_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $applicationService->rollback(
            $material,
            $datasheet,
            $application,
            $request->user(),
            $validated['rollback_note'] ?? null
        );

        return back()->with('success', 'Parser application rolled back. The material remains in Needs Review until governance is completed again.');
    }

    public function archive(Request $request, Company $company, string $material, MaterialDatasheet $datasheet): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);
        $this->assertDatasheetOwnership($material, $datasheet);

        if ($datasheet->applications()->where('status', 'applied')->exists()) {
            return back()->with('error', 'Rollback the active parser application before archiving this datasheet evidence.');
        }

        $datasheet->update(['is_active' => false]);
        $material->markForCatalogReview($request->user()->getKey());

        return back()->with('success', 'Datasheet archived successfully.');
    }

    private function findCompanyMaterial(Company $company, string $materialId): Material
    {
        $material = Material::query()
            ->visibleForCompany($company)
            ->whereKey($materialId)
            ->firstOrFail();

        abort_if((int) $material->company_id !== (int) $company->getKey(), 403, 'Global catalog items are read-only for this company.');

        return $material;
    }

    private function assertDatasheetOwnership(Material $material, MaterialDatasheet $datasheet): void
    {
        abort_unless((int) $datasheet->material_id === (int) $material->getKey(), 404);
    }

    private function assertSupportedFile(string $path, string $extension, string $mimeType): void
    {
        $extension = strtolower($extension);
        if (! in_array($extension, ['pdf', 'txt', 'csv'], true)) {
            throw ValidationException::withMessages(['datasheet_file' => 'Only PDF, TXT, and CSV datasheet files are supported.']);
        }

        $header = (string) file_get_contents($path, false, null, 0, 1024);
        if ($extension === 'pdf' && strpos($header, '%PDF-') === false) {
            throw ValidationException::withMessages(['datasheet_file' => 'The file extension is PDF, but the document does not contain a valid PDF signature.']);
        }

        $allowedTextMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'];
        if (in_array($extension, ['txt', 'csv'], true)) {
            if (! in_array(strtolower($mimeType), $allowedTextMimes, true)) {
                throw ValidationException::withMessages(['datasheet_file' => 'The uploaded text file has an unexpected MIME type.']);
            }

            $sample = (string) file_get_contents($path, false, null, 0, 8192);
            $nonText = preg_match_all('/[^\x09\x0A\x0D\x20-\x7E\x80-\xFF]/', $sample);
            if (str_contains($sample, "\0") || ($sample !== '' && ($nonText / strlen($sample)) > 0.15)) {
                throw ValidationException::withMessages(['datasheet_file' => 'The uploaded TXT or CSV file appears to contain binary data.']);
            }
        }
    }

    private function sanitizeOriginalFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]+/', '', $filename) ?? 'datasheet';

        return str($filename)->limit(220, '')->toString();
    }
}
