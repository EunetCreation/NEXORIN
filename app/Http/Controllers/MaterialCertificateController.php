<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialCertificateController extends Controller
{
    public function store(Request $request, Company $company, string $material): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);

        $validated = $request->validate([
            'certificate_type' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:180'],
            'certificate_number' => ['nullable', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:160'],
            'certificate_file' => ['required_without:external_url', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'external_url' => ['required_without:certificate_file', 'nullable', 'url', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $filePath = null;
        if ($request->hasFile('certificate_file')) {
            $filePath = $request->file('certificate_file')
                ->store("material-certificates/{$company->getKey()}/{$material->getKey()}");
        }

        MaterialCertificate::query()->create([
            'material_id' => $material->getKey(),
            'certificate_type' => strtoupper(trim($validated['certificate_type'])),
            'title' => $validated['title'],
            'certificate_number' => $validated['certificate_number'] ?? null,
            'issuer' => $validated['issuer'] ?? null,
            'file_path' => $filePath,
            'external_url' => $validated['external_url'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
            'verification_status' => 'pending',
            'uploaded_by' => $request->user()->getKey(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $material->markForCatalogReview($request->user()->getKey());

        return back()->with('success', 'Certificate evidence registered. Verification is required before it contributes to approval readiness.');
    }

    public function verify(Request $request, Company $company, string $material, MaterialCertificate $certificate): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);
        abort_unless((int) $certificate->material_id === (int) $material->getKey(), 404);

        $validated = $request->validate([
            'verification_status' => ['required', Rule::in(['verified', 'rejected'])],
            'verification_notes' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('verification_status') === 'rejected')],
        ]);

        $certificate->update([
            'verification_status' => $validated['verification_status'],
            'verified_at' => now(),
            'verified_by' => $request->user()->getKey(),
            'verification_notes' => $validated['verification_notes'] ?? null,
        ]);

        $material->markForCatalogReview($request->user()->getKey());

        return back()->with('success', $validated['verification_status'] === 'verified'
            ? 'Certificate verified and added to catalog compliance coverage.'
            : 'Certificate rejected. The evidence remains in the audit trail.');
    }

    public function archive(Request $request, Company $company, string $material, MaterialCertificate $certificate): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);
        abort_unless((int) $certificate->material_id === (int) $material->getKey(), 404);

        $certificate->update(['is_active' => false]);
        $material->markForCatalogReview($request->user()->getKey());

        return back()->with('success', 'Certificate archived while its verification history remains preserved.');
    }

    private function findCompanyMaterial(Company $company, string $materialId): Material
    {
        $material = Material::query()->visibleForCompany($company)->whereKey($materialId)->firstOrFail();
        abort_if((int) $material->company_id !== (int) $company->getKey(), 403, 'Global catalog items are read-only for this company.');

        return $material;
    }
}
