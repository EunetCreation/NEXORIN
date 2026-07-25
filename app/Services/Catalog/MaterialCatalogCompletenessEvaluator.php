<?php

namespace App\Services\Catalog;

use App\Models\Material;
use App\Support\MaterialCatalogStandards;
use App\Support\ProfessionalCatalogProfile;
use App\Support\ProfessionalSpecificationSchema;
use Illuminate\Support\Collection;

class MaterialCatalogCompletenessEvaluator
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(Material $material): array
    {
        $material->loadMissing(['category.parent', 'specs', 'datasheets', 'certificates', 'documents', 'priceBookItems']);

        $requiredSpecs = MaterialCatalogStandards::requiredSpecKeysFor($material);
        $presentSpecs = $material->specs
            ->filter(fn ($spec): bool => filled($spec->spec_value))
            ->pluck('spec_key')
            ->map(fn ($key): string => (string) $key)
            ->all();
        $missingSpecs = array_values(array_diff($requiredSpecs, $presentSpecs));

        $requiredCertificates = MaterialCatalogStandards::requiredCertificateTypesFor($material);
        $verifiedCertificates = $this->verifiedCertificateTypes($material->certificates);
        $missingCertificates = $this->missingCertificates($requiredCertificates, $verifiedCertificates);
        $expiredCertificates = $material->certificates
            ->filter(fn ($certificate): bool => (bool) $certificate->is_active && $certificate->expires_at?->isPast())
            ->pluck('title')->values()->all();

        $requiredDocuments = array_values(array_filter(
            MaterialCatalogStandards::requiredDocumentTypesFor($material),
            fn (string $type): bool => ! in_array($type, ['datasheet', 'certificate'], true)
        ));
        $verifiedDocuments = $material->documents
            ->filter(fn ($document): bool => (bool) $document->is_active
                && in_array($document->verification_status, ['verified', 'accepted'], true)
                && ! ($document->expires_at?->isPast() ?? false))
            ->pluck('document_type')
            ->map(fn ($type): string => strtolower((string) $type))
            ->unique()->values()->all();
        $missingDocuments = array_values(array_diff($requiredDocuments, $verifiedDocuments));
        $expiredDocuments = $material->documents
            ->filter(fn ($document): bool => (bool) $document->is_active && $document->expires_at?->isPast())
            ->pluck('title')->values()->all();

        $hasDatasheet = $material->datasheets->contains(
            fn ($datasheet): bool => (bool) $datasheet->is_active
                && in_array($datasheet->extraction_status, ['parsed', 'reviewed', 'applied'], true)
        );
        $hasPriceReference = $material->priceBookItems->contains(fn ($item): bool => (float) $item->unit_cost > 0);
        $categoryCode = strtoupper((string) $material->category?->code);
        $usesProfessionalRootOnly = $material->category
            && $material->category->parent_id === null
            && array_key_exists($categoryCode, ProfessionalCatalogProfile::profiles());
        $exactComponentCategory = $material->material_category_id !== null && ! $usesProfessionalRootOnly;
        $identityComplete = filled($material->sku)
            && filled($material->name)
            && filled($material->brand)
            && filled($material->model)
            && $exactComponentCategory;

        $specScore = $this->ratioScore($requiredSpecs, $missingSpecs, 35);
        $certificateScore = $this->ratioScore($requiredCertificates, $missingCertificates, 15);
        $documentScore = $this->ratioScore($requiredDocuments, $missingDocuments, 15);

        $score = ($material->is_active ? 5 : 0)
            + ($identityComplete ? 5 : 0)
            + $specScore
            + ($hasDatasheet ? 15 : 0)
            + $certificateScore
            + $documentScore
            + ($hasPriceReference ? 10 : 0);

        $notes = [];
        if (! $material->is_active) {
            $notes[] = 'Material is inactive.';
        }
        if (! $identityComplete) {
            $notes[] = $usesProfessionalRootOnly
                ? 'Select an exact professional component subcategory before approval.'
                : 'Brand, model, exact component category, SKU, and item name must be complete.';
        }
        if ($missingSpecs !== []) {
            $notes[] = 'Missing required technical specs: '.implode(', ', $missingSpecs).'.';
        }
        if (! $hasDatasheet) {
            $notes[] = 'No parsed active datasheet is available.';
        }
        if ($missingCertificates !== []) {
            $notes[] = 'Missing verified certificates: '.implode(', ', $missingCertificates).'.';
        }
        if ($missingDocuments !== []) {
            $labels = ProfessionalCatalogProfile::documentLabels();
            $notes[] = 'Missing verified supporting documents: '.implode(', ', array_map(fn (string $type): string => $labels[$type] ?? $type, $missingDocuments)).'.';
        }
        if ($expiredCertificates !== [] || $expiredDocuments !== []) {
            $notes[] = 'Expired evidence must be renewed before approval.';
        }
        if (! $hasPriceReference) {
            $notes[] = 'No positive price-book reference is linked yet. Approval may continue, but commercial readiness remains incomplete.';
        }

        $canApprove = $material->is_active
            && $identityComplete
            && $missingSpecs === []
            && $hasDatasheet
            && $missingCertificates === []
            && $missingDocuments === []
            && $expiredCertificates === []
            && $expiredDocuments === [];

        return [
            'score' => min(100, max(0, $score)),
            'status' => match (true) {
                $canApprove && $score >= 85 => 'approval_ready',
                $score >= 65 => 'review_required',
                default => 'incomplete',
            },
            'profile' => ProfessionalSpecificationSchema::for($material),
            'identity_complete' => $identityComplete,
            'exact_component_category' => $exactComponentCategory,
            'missing_specs' => $missingSpecs,
            'missing_certificates' => $missingCertificates,
            'missing_documents' => $missingDocuments,
            'expired_certificates' => $expiredCertificates,
            'expired_documents' => $expiredDocuments,
            'has_datasheet' => $hasDatasheet,
            'has_verified_certificate' => $missingCertificates === [] && $expiredCertificates === [],
            'has_verified_document_pack' => $missingDocuments === [] && $expiredDocuments === [],
            'has_price_reference' => $hasPriceReference,
            'can_approve' => $canApprove,
            'notes' => $notes,
        ];
    }

    private function ratioScore(array $required, array $missing, int $weight): int
    {
        if ($required === []) {
            return $weight;
        }

        return (int) round((count($required) - count($missing)) / max(count($required), 1) * $weight);
    }

    /** @param Collection<int, mixed> $certificates */
    private function verifiedCertificateTypes(Collection $certificates): array
    {
        return $certificates
            ->filter(fn ($certificate): bool => (bool) $certificate->is_active
                && in_array($certificate->verification_status ?? 'pending', ['verified', 'accepted'], true)
                && ! ($certificate->expires_at?->isPast() ?? false))
            ->pluck('certificate_type')
            ->map(fn ($type): string => strtoupper((string) $type))
            ->values()->all();
    }

    private function missingCertificates(array $requiredCertificates, array $verifiedCertificates): array
    {
        return collect($requiredCertificates)
            ->filter(function (string $required) use ($verifiedCertificates): bool {
                $required = strtoupper($required);
                foreach ($verifiedCertificates as $verified) {
                    if (str_contains($verified, $required) || str_contains($required, $verified)) {
                        return false;
                    }
                }
                return true;
            })
            ->values()->all();
    }
}
