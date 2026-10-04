<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAnalysis extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'technical_calculation_id',
        'bill_of_material_id',
        'analysis_number',
        'status',
        'currency',
        'electricity_tariff_idr_per_kwh',
        'export_credit_percent',
        'self_consumption_percent',
        'tariff_escalation_percent',
        'annual_om_percent',
        'discount_rate_percent',
        'energy_degradation_percent',
        'project_lifetime_years',
        'replacement_year',
        'replacement_cost_idr',
        'project_capex_idr',
        'annual_energy_kwh',
        'first_year_saving_idr',
        'annual_om_cost_idr',
        'simple_payback_years',
        'lifetime_roi_percent',
        'npv_idr',
        'irr_percent',
        'lcoe_idr_per_kwh',
        'summary',
        'cashflows',
        'warnings',
        'source_snapshot',
        'notes',
        'calculated_by',
        'calculated_at',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'IDR',
    ];

    protected function casts(): array
    {
        return [
            'electricity_tariff_idr_per_kwh' => 'decimal:2',
            'export_credit_percent' => 'decimal:2',
            'self_consumption_percent' => 'decimal:2',
            'tariff_escalation_percent' => 'decimal:2',
            'annual_om_percent' => 'decimal:2',
            'discount_rate_percent' => 'decimal:2',
            'energy_degradation_percent' => 'decimal:2',
            'project_capex_idr' => 'decimal:2',
            'replacement_cost_idr' => 'decimal:2',
            'annual_energy_kwh' => 'decimal:2',
            'first_year_saving_idr' => 'decimal:2',
            'annual_om_cost_idr' => 'decimal:2',
            'simple_payback_years' => 'decimal:2',
            'lifetime_roi_percent' => 'decimal:2',
            'npv_idr' => 'decimal:2',
            'irr_percent' => 'decimal:2',
            'lcoe_idr_per_kwh' => 'decimal:2',
            'summary' => 'array',
            'cashflows' => 'array',
            'warnings' => 'array',
            'source_snapshot' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function technicalCalculation(): BelongsTo
    {
        return $this->belongsTo(TechnicalCalculation::class);
    }

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'calculated' => 'Calculated',
            'locked' => 'Locked',
            default => 'Draft',
        };
    }

    /**
     * Determine whether a stored financial result still matches the exact
     * technical calculation and priced BOQ snapshot recorded at calculation time.
     *
     * This is a legacy deterministic source check, not the future CalculationDependency graph.
     *
     * @return array{state: string, label: string, detail: string}
     */
    public function sourceFreshness(Project $project): array
    {
        if (strtoupper((string) $this->currency) !== 'IDR') {
            return [
                'state' => 'review_required',
                'label' => 'Currency Review Required',
                'detail' => 'This legacy record is not stored on the current IDR financial basis.',
            ];
        }

        $snapshot = is_array($this->source_snapshot) ? $this->source_snapshot : [];
        $requiredSnapshotKeys = [
            'project_id',
            'technical_calculation_id',
            'technical_calculation_status',
            'technical_calculated_at',
            'bill_of_material_id',
            'bom_pricing_status',
            'bom_priced_at',
            'bom_grand_price_idr',
        ];

        foreach ($requiredSnapshotKeys as $requiredKey) {
            if (! array_key_exists($requiredKey, $snapshot) || $snapshot[$requiredKey] === null || $snapshot[$requiredKey] === '') {
                return [
                    'state' => 'review_required',
                    'label' => 'Source Review Required',
                    'detail' => 'The stored analysis has an incomplete legacy source snapshot, so current-source freshness cannot be proven.',
                ];
            }
        }

        $calculation = $project->technicalCalculation;
        $bom = $project->billOfMaterial;

        if (! $calculation || ! $bom) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The technical calculation or BOQ used by this result is no longer available.',
            ];
        }

        if ((int) ($snapshot['project_id'] ?? 0) !== (int) $project->getKey()) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The stored financial result belongs to a different project source snapshot.',
            ];
        }

        if ((int) ($calculation->project_id ?? 0) !== (int) $project->getKey()
            || (int) ($calculation->company_id ?? 0) !== (int) $project->company_id
            || (int) ($bom->project_id ?? 0) !== (int) $project->getKey()
            || (int) ($bom->company_id ?? 0) !== (int) $project->company_id) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The current technical or BOQ source is outside this project financial basis.',
            ];
        }

        if ((int) ($bom->technical_calculation_id ?? 0) !== (int) $calculation->getKey()) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The current priced BOQ is linked to a different technical calculation.',
            ];
        }

        if ($calculation->isStale($project)) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'Project engineering inputs changed after the technical calculation used by this result.',
            ];
        }

        $analysisTechnicalId = (int) ($this->technical_calculation_id ?? 0);
        $analysisBomId = (int) ($this->bill_of_material_id ?? 0);
        $snapshotTechnicalId = (int) ($snapshot['technical_calculation_id'] ?? 0);
        $snapshotBomId = (int) ($snapshot['bill_of_material_id'] ?? 0);

        if (
            $analysisTechnicalId <= 0
            || $analysisBomId <= 0
            || $analysisTechnicalId !== (int) $calculation->getKey()
            || $analysisBomId !== (int) $bom->getKey()
            || $snapshotTechnicalId !== (int) $calculation->getKey()
            || $snapshotBomId !== (int) $bom->getKey()
        ) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'This financial result references an earlier technical calculation or BOQ record.',
            ];
        }

        if (($snapshot['technical_calculation_status'] ?? null) !== $calculation->result_status) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The technical calculation status changed after this financial result was stored.',
            ];
        }

        if (($snapshot['bom_pricing_status'] ?? null) !== $bom->pricing_status) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The BOQ pricing state changed after this financial result was stored.',
            ];
        }

        if (! $this->sameTimestamp($snapshot['technical_calculated_at'] ?? null, $calculation->calculated_at)) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The technical calculation was recalculated after this financial result was stored.',
            ];
        }

        if (! $this->sameTimestamp($snapshot['bom_priced_at'] ?? null, $bom->priced_at)) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The BOQ was repriced after this financial result was stored.',
            ];
        }

        $snapshotCapex = (float) ($snapshot['bom_grand_price_idr'] ?? -1);
        $currentBomPrice = (float) ($bom->grand_price_idr ?? 0);

        if ($snapshotCapex < 0 || abs($snapshotCapex - $currentBomPrice) > 0.01) {
            return [
                'state' => 'source_changed',
                'label' => 'Source Changed',
                'detail' => 'The priced BOQ value changed after this financial result was stored.',
            ];
        }

        return [
            'state' => 'current',
            'label' => 'Current Sources',
            'detail' => 'Stored technical and BOQ source references still match the current project records.',
        ];
    }

    private function sameTimestamp(mixed $snapshotValue, mixed $currentValue): bool
    {
        if (! $snapshotValue || ! $currentValue) {
            return $snapshotValue === null && $currentValue === null;
        }

        try {
            return Carbon::parse((string) $snapshotValue)->equalTo(Carbon::parse($currentValue));
        } catch (\Throwable) {
            return false;
        }
    }
}
