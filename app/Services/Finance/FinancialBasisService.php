<?php

namespace App\Services\Finance;

use App\Models\Project;

class FinancialBasisService
{
    /**
     * Assess the current project-level prerequisites required by the present
     * FinancialAnalysisEngine. This is the shared per-project authority used by
     * both the Page 013 registry and Page 014 workspace.
     *
     * @return array{key: string, label: string, detail: string, tone: string, ready: bool, annual_energy_kwh: float}
     */
    public function assess(Project $project): array
    {
        if ($project->isArchived()) {
            return $this->state(
                'project_archived',
                'Project Archived',
                'The stored project may be reviewed, but a new financial calculation cannot be run while it is archived.',
                'blocked'
            );
        }

        $calculation = $project->technicalCalculation;
        $bom = $project->billOfMaterial;

        if (! $calculation || ! $calculation->calculated_at || ! is_array($calculation->results)) {
            return $this->state(
                'technical_required',
                'Technical Calculation Required',
                'No stored technical result is available for financial analysis.',
                'blocked'
            );
        }

        if (
            (int) $calculation->project_id !== (int) $project->getKey()
            || (int) $calculation->company_id !== (int) $project->company_id
        ) {
            return $this->state(
                'technical_source_review',
                'Technical Source Review Required',
                'The stored technical result does not match the active project and company scope.',
                'attention'
            );
        }

        if ($calculation->isStale($project)) {
            return $this->state(
                'technical_stale',
                'Technical Basis Stale',
                'Engineering source data changed after the stored technical calculation.',
                'attention'
            );
        }

        $annualEnergyKwh = max(0, (float) ($calculation->results['annual_energy_kwh'] ?? 0));

        if ($annualEnergyKwh <= 0) {
            return $this->state(
                'energy_required',
                'Annual Energy Required',
                'A positive stored annual-energy result from the current technical calculation is required.',
                'attention'
            );
        }

        if (! $bom) {
            return $this->state(
                'boq_required',
                'BOQ Required',
                'Generate the project BOM/BOQ before creating a financial result.',
                'blocked',
                $annualEnergyKwh
            );
        }

        if (
            (int) $bom->project_id !== (int) $project->getKey()
            || (int) $bom->company_id !== (int) $project->company_id
        ) {
            return $this->state(
                'boq_scope_review',
                'BOQ Source Review Required',
                'The stored BOQ does not match the active project and company scope.',
                'attention',
                $annualEnergyKwh
            );
        }

        if (! $bom->technical_calculation_id || (int) $bom->technical_calculation_id !== (int) $calculation->getKey()) {
            return $this->state(
                'boq_source_review',
                'BOQ Source Review Required',
                'The current BOQ is not linked to the current technical calculation record.',
                'attention',
                $annualEnergyKwh
            );
        }

        if ($bom->pricing_status !== 'priced' || (float) $bom->grand_price_idr <= 0) {
            return $this->state(
                'boq_pricing_required',
                'BOQ Pricing Required',
                'A positive priced BOQ value is required before financial analysis.',
                'attention',
                $annualEnergyKwh
            );
        }

        if (! $bom->priced_at) {
            return $this->state(
                'boq_pricing_review',
                'BOQ Pricing Review Required',
                'The BOQ is marked priced but has no recorded pricing timestamp.',
                'attention',
                $annualEnergyKwh
            );
        }

        return $this->state(
            'ready',
            'Financial Basis Ready',
            'Current technical result · positive annual energy · linked priced IDR BOQ.',
            'ready',
            $annualEnergyKwh
        );
    }

    /** @return array{key: string, label: string, detail: string, tone: string, ready: bool, annual_energy_kwh: float} */
    private function state(
        string $key,
        string $label,
        string $detail,
        string $tone,
        float $annualEnergyKwh = 0.0
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'detail' => $detail,
            'tone' => $tone,
            'ready' => $key === 'ready',
            'annual_energy_kwh' => $annualEnergyKwh,
        ];
    }
}
