<?php

namespace App\Services\Catalog;

use App\Models\Material;
use App\Support\MaterialCatalogStandards;
use Illuminate\Support\Collection;

class MaterialCatalogCompletenessEvaluator
{
    /**
     * @return array{score: int, status: string, missing_specs: array<int, string>, missing_certificates: array<int, string>, has_datasheet: bool, has_verified_certificate: bool, has_price_reference: bool, can_approve: bool, notes: array<int, string>}
     */
    public function evaluate(Material $material): array
    {
        $material->loadMissing(['category', 'specs', 'datasheets', 'certificates', 'priceBookItems']);

        $requiredSpecs = MaterialCatalogStandards::requiredSpecKeysFor($material);
        $presentSpecs = $material->specs
            ->filter(fn ($spec): bool => filled($spec->spec_value))
            ->pluck('spec_key')
            ->map(fn ($key): string => (string) $key)
            ->all();

        $missingSpecs = array_values(array_diff($requiredSpecs, $presentSpecs));
        $specScore = $requiredSpecs === []
            ? 45
            : (int) round((count($requiredSpecs) - count($missingSpecs)) / max(count($requiredSpecs), 1) * 45);

        $requiredCertificates = MaterialCatalogStandards::requiredCertificateTypesFor($material);
        $verifiedCertificates = $this->verifiedCertificateTypes($material->certificates);
        $missingCertificates = $this->missingCertificates($requiredCertificates, $verifiedCertificates);
        $certificateScore = $requiredCertificates === []
            ? 20
            : (int) round((count($requiredCertificates) - count($missingCertificates)) / max(count($requiredCertificates), 1) * 20);

        $hasDatasheet = $material->datasheets
            ->contains(fn ($datasheet): bool => (bool) $datasheet->is_active && in_array($datasheet->extraction_status, ['parsed', 'reviewed', 'applied'], true));

        $hasPriceReference = $material->priceBookItems->contains(fn ($item): bool => (float) $item->unit_cost > 0);

        $score = 0;
        $score += $material->is_active ? 10 : 0;
        $score += $specScore;
        $score += $hasDatasheet ? 15 : 0;
        $score += $certificateScore;
        $score += $hasPriceReference ? 10 : 0;

        $notes = [];

        if (! $material->is_active) {
            $notes[] = 'Material is inactive.';
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

        if (! $hasPriceReference) {
            $notes[] = 'No positive price book reference is linked yet.';
        }

        $canApprove = $material->is_active
            && $missingSpecs === []
            && $hasDatasheet
            && $missingCertificates === [];

        return [
            'score' => min(100, $score),
            'status' => match (true) {
                $canApprove && $score >= 85 => 'approval_ready',
                $score >= 70 => 'review_required',
                default => 'incomplete',
            },
            'missing_specs' => $missingSpecs,
            'missing_certificates' => $missingCertificates,
            'has_datasheet' => $hasDatasheet,
            'has_verified_certificate' => $missingCertificates === [],
            'has_price_reference' => $hasPriceReference,
            'can_approve' => $canApprove,
            'notes' => $notes,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $certificates
     * @return array<int, string>
     */
    private function verifiedCertificateTypes(Collection $certificates): array
    {
        return $certificates
            ->filter(fn ($certificate): bool => (bool) $certificate->is_active && in_array($certificate->verification_status ?? 'pending', ['verified', 'accepted'], true))
            ->pluck('certificate_type')
            ->map(fn ($type): string => strtoupper((string) $type))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $requiredCertificates
     * @param  array<int, string>  $verifiedCertificates
     * @return array<int, string>
     */
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
            ->values()
            ->all();
    }
}
