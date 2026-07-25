<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialDocument;
use App\Support\ProfessionalCatalogProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialDocumentController extends Controller
{
    public function store(Request $request, Company $company, string $material): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(ProfessionalCatalogProfile::supportingDocumentLabels()))],
            'title' => ['required', 'string', 'max:180'],
            'document_file' => ['required_without:external_url', 'nullable', 'file', 'mimes:pdf,txt,csv,xls,xlsx,doc,docx,jpg,jpeg,png,webp', 'max:20480'],
            'external_url' => ['required_without:document_file', 'nullable', 'url', 'max:255'],
            'revision' => ['nullable', 'string', 'max:60'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $filePath = null;
        $originalFilename = null;
        $mimeType = null;
        $fileSize = null;

        if ($request->hasFile('document_file')) {
            $file = $request->file('document_file');
            $filePath = $file->store("material-documents/{$company->getKey()}/{$material->getKey()}");
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $fileSize = $file->getSize();
        }

        MaterialDocument::query()->create([
            'material_id' => $material->getKey(),
            'document_type' => $validated['document_type'],
            'title' => $validated['title'],
            'original_filename' => $originalFilename,
            'file_path' => $filePath,
            'external_url' => $validated['external_url'] ?? null,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
            'revision' => $validated['revision'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
            'verification_status' => 'pending',
            'uploaded_by' => $request->user()->getKey(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->touchGovernance($request, $material);

        return back()->with('success', 'Supporting document registered. Verify it before relying on it as approval evidence.');
    }

    public function verify(Request $request, Company $company, string $material, MaterialDocument $document): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);
        abort_unless((int) $document->material_id === (int) $material->getKey(), 404);

        $validated = $request->validate([
            'verification_status' => ['required', Rule::in(['verified', 'rejected'])],
            'verification_notes' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('verification_status') === 'rejected')],
        ]);

        $document->update([
            'verification_status' => $validated['verification_status'],
            'verified_at' => now(),
            'verified_by' => $request->user()->getKey(),
            'verification_notes' => $validated['verification_notes'] ?? null,
        ]);

        $this->touchGovernance($request, $material);

        return back()->with('success', $validated['verification_status'] === 'verified'
            ? 'Document verified and added to the professional evidence pack.'
            : 'Document rejected. The record remains available for audit.');
    }

    public function archive(Request $request, Company $company, string $material, MaterialDocument $document): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);
        abort_unless((int) $document->material_id === (int) $material->getKey(), 404);

        $document->update(['is_active' => false]);
        $this->touchGovernance($request, $material);

        return back()->with('success', 'Supporting document archived while its audit history remains preserved.');
    }

    private function touchGovernance(Request $request, Material $material): void
    {
        $material->markForCatalogReview($request->user()->getKey());
    }

    private function findCompanyMaterial(Company $company, string $materialId): Material
    {
        $material = Material::query()->visibleForCompany($company)->whereKey($materialId)->firstOrFail();
        abort_if((int) $material->company_id !== (int) $company->getKey(), 403, 'Global catalog items are read-only for this company.');

        return $material;
    }
}
