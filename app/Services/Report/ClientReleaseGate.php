<?php

namespace App\Services\Report;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectReleaseApproval;
use App\Models\User;
use App\Services\Quotation\QuotationEngine;
use App\Services\Quotation\QuotationLifecycleService;
use App\Support\CalculationWarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientReleaseGate
{
    /**
     * Only trusted internal roles may approve or revoke final client release.
     */
    public function userCanManageApproval(User $user, Company|int $company): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasCompanyRole($company, 'company_admin')
            || $user->hasCompanyRole($company, 'engineer');
    }

    /**
     * Evaluate whether a project may be released as a client-ready proposal.
     *
     * @param  array<int, array<string, mixed>>|null  $warnings
     * @return array<string, mixed>
     */
    public function evaluate(Project $project, ?array $warnings = null, ?bool $bomPricingReady = null): array
    {
        $this->loadRelations($project);

        $warnings ??= $this->collectWarnings($project);
        $bomPricingReady ??= $this->bomPricingReady($project);

        $blockingWarnings = collect($warnings)
            ->filter(fn (array $warning): bool => in_array(($warning['severity'] ?? null), ['critical', 'warning'], true))
            ->values();

        $criticalWarnings = $blockingWarnings
            ->filter(fn (array $warning): bool => ($warning['severity'] ?? null) === 'critical')
            ->values();

        $issues = [];
        $engineeringBlockers = [];
        $commercialBlockers = [];

        if ($criticalWarnings->isNotEmpty()) {
            $issues[] = $this->issue('critical', 'Critical report notices are open.', 'Resolve all critical notices before client release.');
        }

        if ($blockingWarnings->isNotEmpty()) {
            $warningTitles = $blockingWarnings
                ->take(4)
                ->map(fn (array $warning): string => (string) ($warning['title'] ?? $warning['code'] ?? 'Open warning'))
                ->implode(', ');

            $engineeringBlockers[] = $this->issue('warning', 'Open engineering or review notices remain.', $warningTitles ?: 'Review the open warning list.');
        }

        $technical = $project->technicalCalculation;
        if (! $technical || $technical->result_status !== 'verified') {
            $engineeringBlockers[] = $this->issue('warning', 'Technical calculation is not engineer verified.', 'Verify the technical calculation before releasing a client proposal.');
        }

        $bom = $project->billOfMaterial;
        $bomSummary = $bom?->summary ?? [];
        $bomGrand = is_numeric($bom?->grand_price_idr ?? null) ? (float) $bom->grand_price_idr : 0.0;
        $material = is_numeric($bom?->material_price_subtotal_idr ?? null) ? (float) $bom->material_price_subtotal_idr : 0.0;

        if (! $bomPricingReady || $bomGrand <= 0) {
            $commercialBlockers[] = $this->issue('warning', 'BOQ pricing is not ready.', 'Apply pricing and confirm that every required chargeable BOQ item has a valid price.');
        }

        if ($bom && $bom->pricing_status !== 'priced') {
            $commercialBlockers[] = $this->issue(
                'warning',
                'BOQ price resolver requires review.',
                'Resolve missing, expired, unavailable, margin, discount, or tax issues before client release.'
            );
        }

        if ($bom && (int) ($bomSummary['low_margin_item_count'] ?? 0) > 0) {
            $commercialBlockers[] = $this->issue(
                'critical',
                'BOQ margin is below the minimum guard.',
                'Adjust selling price or margin override before client release.'
            );
        }

        if ($bom && (int) ($bomSummary['discount_limit_exceeded_count'] ?? 0) > 0) {
            $commercialBlockers[] = $this->issue(
                'warning',
                'BOQ discount exceeds the allowed limit.',
                'Reduce discount or obtain commercial approval before client release.'
            );
        }

        if ($bom && (int) ($bomSummary['tax_review_issue_count'] ?? 0) > 0) {
            $commercialBlockers[] = $this->issue(
                'warning',
                'BOQ tax settings require review.',
                'Confirm tax mode, tax rate, and tax exempt item settings.'
            );
        }

        if ($bom && ($bomSummary['commercial_total_consistent'] ?? true) !== true) {
            $commercialBlockers[] = $this->issue(
                'critical',
                'BOQ commercial total is inconsistent.',
                'Recalculate pricing so subtotal, tax, discount, rounding, and grand total reconcile.'
            );
        }

        if ($bomPricingReady && $material > 0 && $bomGrand > 0 && $bomGrand < $material) {
            $commercialBlockers[] = $this->issue('critical', 'BOQ grand price is below material price.', 'Review pricing, discount, tax, and margin before release.');
        }

        $financial = $project->financialAnalysis;
        $financialCapex = is_numeric($financial?->project_capex_idr ?? null) ? (float) $financial->project_capex_idr : 0.0;
        if (! $financial || $financial->status !== 'calculated') {
            $commercialBlockers[] = $this->issue('warning', 'Financial analysis is not calculated.', 'Regenerate financial analysis after BOQ pricing.');
        } elseif ($bomGrand > 0 && $financialCapex > 0 && $this->isDifferent($financialCapex, $bomGrand)) {
            $commercialBlockers[] = $this->issue('warning', 'Financial analysis is not synchronized with latest BOQ.', 'Regenerate financial analysis so CAPEX matches the latest BOQ value.');
        }

        $quotation = $project->quotation;
        $quotationGrand = is_array($quotation?->commercial_summary ?? null) && is_numeric($quotation->commercial_summary['grand_total_idr'] ?? null)
            ? (float) $quotation->commercial_summary['grand_total_idr']
            : 0.0;

        if (! $quotation) {
            $commercialBlockers[] = $this->issue('warning', 'Quotation has not been generated.', 'Generate a quotation from the latest BOQ and financial analysis.');
        } else {
            if ($quotation->shouldBeConsideredExpired()) {
                app(QuotationLifecycleService::class)->expireIfNeeded($quotation);
                $quotation->refresh();
            }

            if (! $quotation->isApprovedForClient()) {
                $commercialBlockers[] = $this->issue('warning', 'Quotation is not approved.', 'Approve the quotation snapshot before client release.');
            }

            if ($quotation->valid_until && $quotation->valid_until->isPast()) {
                $commercialBlockers[] = $this->issue('warning', 'Quotation snapshot is expired.', 'Generate a new quotation revision before client release.');
            }

            $currentFingerprint = null;

            if ($bom && $financial) {
                $currentFingerprint = app(QuotationEngine::class)->sourceFingerprint($project, $bom, $financial);

                if (! $quotation->isSourceCurrent($currentFingerprint)) {
                    $commercialBlockers[] = $this->issue('warning', 'Quotation snapshot is stale.', 'Regenerate quotation because BOQ, financial analysis, or project data changed after snapshot creation.');
                }
            }

            if ($quotation->hasSnapshotControlledClientProposal() && $quotation->isApprovedForClient()) {
                if (! $quotation->client_proposal_pdf_generated_at) {
                    $commercialBlockers[] = $this->issue('warning', 'Client proposal PDF has not been generated.', 'Generate the client proposal PDF from the approved quotation snapshot before client release.');
                } elseif (! $quotation->hasCurrentClientProposalPdf($currentFingerprint)) {
                    $commercialBlockers[] = $this->issue('warning', 'Client proposal PDF is stale.', 'Regenerate the client proposal PDF because the quotation snapshot or source fingerprint changed.');
                }
            }

            if ($bomGrand > 0 && $quotationGrand > 0 && $this->isDifferent($quotationGrand, $bomGrand)) {
                $commercialBlockers[] = $this->issue('warning', 'Quotation is not synchronized with latest BOQ.', 'Regenerate and approve the quotation after BOQ changes.');
            }
        }

        $issues = array_merge($issues, $engineeringBlockers, $commercialBlockers);
        $approvalSnapshot = $this->approvalSnapshot($project, $warnings, $bomPricingReady);
        $currentHash = $this->hashSnapshot($approvalSnapshot);
        $approval = $project->relationLoaded('activeReleaseApproval')
            ? $project->getRelation('activeReleaseApproval')
            : ($project->exists ? $project->activeReleaseApproval : null);
        $latestApproval = $project->relationLoaded('releaseApprovals')
            ? $project->getRelation('releaseApprovals')->sortByDesc('created_at')->first()
            : ($project->exists ? $project->releaseApprovals()->with('approver')->latest('approved_at')->latest('id')->first() : null);
        $approvalCurrent = $approval && hash_equals((string) $approval->gate_hash, $currentHash);
        $approvalStale = $approval && ! $approvalCurrent;
        $approvalRevoked = ! $approval && $latestApproval && $latestApproval->status === 'revoked';

        $approvalAllowed = $issues === [];
        $ready = false;
        $status = 'Pending Client Release Approval';
        $tone = 'warning';
        $nextAction = 'Approve the client release after all gate checks pass.';
        $draftWatermark = 'ENGINEERING REVIEW DRAFT';

        if ($criticalWarnings->isNotEmpty() || collect($issues)->contains(fn (array $issue): bool => ($issue['severity'] ?? null) === 'critical')) {
            $status = 'Blocked by Review Gate';
            $tone = 'critical';
            $nextAction = 'Resolve critical issues before generating final client output.';
        } elseif ($engineeringBlockers !== []) {
            $status = 'Needs Engineering Review';
            $tone = 'warning';
            $nextAction = 'Resolve engineering warnings and verify technical assumptions.';
        } elseif ($commercialBlockers !== []) {
            $status = 'Needs Commercial Review';
            $tone = 'warning';
            $nextAction = 'Synchronize BOQ, financial analysis, and approved quotation snapshot.';
        } elseif ($approvalStale) {
            $status = 'Release Approval Stale';
            $tone = 'warning';
            $draftWatermark = 'STALE APPROVAL DRAFT';
            $nextAction = 'Re-approve client release because the project data changed after approval.';
        } elseif ($approvalCurrent) {
            $status = 'Ready for Client Release';
            $tone = 'ready';
            $ready = true;
            $draftWatermark = null;
            $nextAction = 'Client Proposal can be released without draft watermark.';
        } elseif ($approvalRevoked) {
            $draftWatermark = 'RELEASE REVOKED DRAFT';
            $nextAction = 'Client release approval was revoked. Approve again after review if the proposal may be released.';
        }

        return [
            'status' => $status,
            'ready' => $ready,
            'tone' => $tone,
            'approval_allowed' => $approvalAllowed,
            'approval_current' => (bool) $approvalCurrent,
            'approval_stale' => (bool) $approvalStale,
            'current_hash' => $currentHash,
            'snapshot' => $approvalSnapshot,
            'draft_watermark' => $draftWatermark,
            'final_download_allowed' => $ready,
            'approval_status' => $approvalCurrent ? 'current' : ($approvalStale ? 'stale' : ($approvalRevoked ? 'revoked' : 'missing')),
            'approval_status_label' => $approvalCurrent ? 'Current Approval' : ($approvalStale ? 'Stale Approval' : ($approvalRevoked ? 'Revoked Approval' : 'Approval Required')),
            'issues' => $issues,
            'issue_count' => count($issues),
            'blocking_warning_count' => $blockingWarnings->count(),
            'engineering_issue_count' => count($engineeringBlockers),
            'commercial_issue_count' => count($commercialBlockers),
            'approval' => $approval ? [
                'id' => $approval->getKey(),
                'status' => $approval->status,
                'status_label' => $approval->status_label,
                'approved_by' => $approval->approver?->name ?? 'System',
                'approved_at' => $approval->approved_at?->format('d M Y, H:i'),
                'notes' => $approval->notes,
                'gate_hash' => $approval->gate_hash,
            ] : null,
            'latest_approval' => $latestApproval ? [
                'id' => $latestApproval->getKey(),
                'status' => $latestApproval->status,
                'status_label' => $latestApproval->status_label,
                'approved_by' => $latestApproval->approver?->name ?? 'System',
                'approved_at' => $latestApproval->approved_at?->format('d M Y, H:i'),
                'revoked_at' => $latestApproval->revoked_at?->format('d M Y, H:i'),
                'notes' => $latestApproval->notes,
                'revoke_reason' => $latestApproval->revoke_reason,
            ] : null,
            'next_action' => $nextAction,
        ];
    }

    public function approve(Project $project, User $user, ?string $notes = null): ProjectReleaseApproval
    {
        return DB::transaction(function () use ($project, $user, $notes): ProjectReleaseApproval {
            $project->refresh();
            $this->loadRelations($project);

            $gate = $this->evaluate($project);

            if (! $gate['approval_allowed']) {
                $message = $gate['issues'][0]['title'] ?? 'Client release cannot be approved yet.';
                throw ValidationException::withMessages([
                    'release_gate' => $message,
                ]);
            }

            if ($gate['ready']) {
                return $project->activeReleaseApproval;
            }

            $project->releaseApprovals()
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_by' => $user->getKey(),
                    'revoked_at' => now(),
                    'revoke_reason' => 'Superseded by a new client release approval.',
                ]);

            return ProjectReleaseApproval::query()->create([
                'company_id' => $project->company_id,
                'project_id' => $project->getKey(),
                'status' => 'active',
                'release_status' => 'Ready for Client Release',
                'gate_hash' => $gate['current_hash'],
                'gate_snapshot' => $gate['snapshot'],
                'gate_result' => $gate,
                'notes' => $notes,
                'approved_by' => $user->getKey(),
                'approved_at' => now(),
            ]);
        });
    }

    public function revoke(Project $project, User $user, ?string $reason = null): void
    {
        $this->loadRelations($project);
        $approval = $project->activeReleaseApproval;

        if (! $approval) {
            throw ValidationException::withMessages([
                'release_gate' => 'There is no active client release approval to revoke.',
            ]);
        }

        $approval->update([
            'status' => 'revoked',
            'revoked_by' => $user->getKey(),
            'revoked_at' => now(),
            'revoke_reason' => $reason ?: 'Approval revoked by user.',
        ]);
    }

    private function loadRelations(Project $project): void
    {
        // Unit tests and report previews may pass an in-memory Project with
        // relations already attached manually. In that case, loading nested
        // relations would hit the database and break tests that intentionally
        // do not migrate every report-related table.
        if (! $project->exists) {
            return;
        }

        $project->loadMissing([
            'company',
            'customer',
            'roofSiteSurvey.surfaces',
            'systemRecommendation.scores',
            'technicalCalculation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation.items',
            'activeReleaseApproval.approver',
            'releaseApprovals.approver',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function collectWarnings(Project $project): array
    {
        $warnings = [];

        if ($project->roofSiteSurvey) {
            $summary = $project->roofSiteSurvey->calculateSummary();
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($summary['warnings'] ?? [], 'roof_site_survey'));
        }

        if ($project->systemRecommendation) {
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($project->systemRecommendation->warnings ?? [], 'system_recommendation'));
        }

        if ($project->technicalCalculation) {
            $warnings = array_merge($warnings, $project->technicalCalculation->warningRecords());
        }

        if ($project->billOfMaterial) {
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($project->billOfMaterial->warnings ?? [], 'bom_boq'));
        }

        if ($project->financialAnalysis) {
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($project->financialAnalysis->warnings ?? [], 'financial_analysis'));
        }

        if ($project->quotation) {
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($project->quotation->warnings ?? [], 'quotation'));
        }

        return CalculationWarning::unique($warnings);
    }

    private function bomPricingReady(Project $project): bool
    {
        $bom = $project->billOfMaterial;

        if ($bom === null || $bom->pricing_status !== 'priced') {
            return false;
        }

        $summary = $bom->summary ?? [];

        return is_numeric($bom->grand_price_idr)
            && (float) $bom->grand_price_idr > 0
            && (int) ($summary['missing_price_item_count'] ?? 0) === 0
            && (int) ($summary['expired_price_item_count'] ?? 0) === 0
            && (int) ($summary['unavailable_price_item_count'] ?? 0) === 0
            && (int) ($summary['low_margin_item_count'] ?? 0) === 0
            && (int) ($summary['discount_limit_exceeded_count'] ?? 0) === 0
            && (int) ($summary['tax_review_issue_count'] ?? 0) === 0
            && ($summary['commercial_total_consistent'] ?? true) === true;
    }

    private function isDifferent(float $left, float $right): bool
    {
        return abs($left - $right) / max($right, 1.0) > 0.01;
    }

    /** @return array<string, string> */
    private function issue(string $severity, string $title, string $detail): array
    {
        return [
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
        ];
    }

    /** @param array<int, array<string, mixed>> $warnings */
    private function approvalSnapshot(Project $project, array $warnings, bool $bomPricingReady): array
    {
        $bom = $project->billOfMaterial;
        $financial = $project->financialAnalysis;
        $quotation = $project->quotation;
        $calculation = $project->technicalCalculation;

        $blockingWarnings = collect($warnings)
            ->map(fn (array $warning): array => [
                'code' => (string) ($warning['code'] ?? $warning['title'] ?? 'warning'),
                'severity' => (string) ($warning['severity'] ?? 'info'),
                'source' => (string) ($warning['source'] ?? 'unknown'),
            ])
            ->sortBy(fn (array $warning): string => $warning['severity'].'-'.$warning['source'].'-'.$warning['code'])
            ->values()
            ->all();

        $quotationGrand = is_array($quotation?->commercial_summary ?? null) && is_numeric($quotation->commercial_summary['grand_total_idr'] ?? null)
            ? (float) $quotation->commercial_summary['grand_total_idr']
            : null;

        return [
            'project' => [
                'id' => $project->getKey(),
                'number' => $project->project_number,
                'name' => $project->name,
                'customer_id' => $project->customer_id,
                'site_address' => $project->site_address,
                'city' => $project->city,
                'province' => $project->province,
            ],
            'technical_calculation' => [
                'id' => $calculation?->getKey(),
                'result_status' => $calculation?->result_status,
                'calculated_at' => $calculation?->calculated_at?->toISOString(),
                'verified_at' => $calculation?->verified_at?->toISOString(),
                'updated_at' => $calculation?->updated_at?->toISOString(),
            ],
            'bom' => [
                'id' => $bom?->getKey(),
                'pricing_ready' => $bomPricingReady,
                'pricing_status' => $bom?->pricing_status,
                'company_price_book_item_count' => (int) (($bom?->summary ?? [])['company_price_book_item_count'] ?? 0),
                'manual_fallback_item_count' => (int) (($bom?->summary ?? [])['manual_fallback_item_count'] ?? 0),
                'missing_price_item_count' => (int) (($bom?->summary ?? [])['missing_price_item_count'] ?? 0),
                'expired_price_item_count' => (int) (($bom?->summary ?? [])['expired_price_item_count'] ?? 0),
                'unavailable_price_item_count' => (int) (($bom?->summary ?? [])['unavailable_price_item_count'] ?? 0),
                'low_margin_item_count' => (int) (($bom?->summary ?? [])['low_margin_item_count'] ?? 0),
                'discount_limit_exceeded_count' => (int) (($bom?->summary ?? [])['discount_limit_exceeded_count'] ?? 0),
                'tax_review_issue_count' => (int) (($bom?->summary ?? [])['tax_review_issue_count'] ?? 0),
                'commercial_total_consistent' => (bool) (($bom?->summary ?? [])['commercial_total_consistent'] ?? true),
                'tax_mode' => (string) (($bom?->summary ?? [])['tax_mode'] ?? 'exclusive'),
                'tax_percent' => (float) (($bom?->summary ?? [])['tax_percent'] ?? 0),
                'min_margin_percent' => (float) (($bom?->summary ?? [])['min_margin_percent'] ?? 0),
                'max_discount_percent' => (float) (($bom?->summary ?? [])['max_discount_percent'] ?? 0),
                'grand_price_idr' => $bom ? round((float) $bom->grand_price_idr, 2) : null,
                'material_price_subtotal_idr' => $bom ? round((float) $bom->material_price_subtotal_idr, 2) : null,
                'priced_at' => $bom?->priced_at?->toISOString(),
                'updated_at' => $bom?->updated_at?->toISOString(),
            ],
            'financial_analysis' => [
                'id' => $financial?->getKey(),
                'status' => $financial?->status,
                'project_capex_idr' => $financial ? round((float) $financial->project_capex_idr, 2) : null,
                'calculated_at' => $financial?->calculated_at?->toISOString(),
                'updated_at' => $financial?->updated_at?->toISOString(),
            ],
            'quotation' => [
                'id' => $quotation?->getKey(),
                'quote_number' => $quotation?->quote_number,
                'revision_number' => $quotation?->revision_number,
                'status' => $quotation?->status,
                'grand_total_idr' => $quotationGrand !== null ? round($quotationGrand, 2) : null,
                // Include proposal-facing commercial fields so approval becomes
                // stale when quotation notes or terms are edited after release
                // approval, even if the database timestamp has second-level
                // precision in SQLite tests.
                'title' => $quotation?->title,
                'valid_until' => $quotation?->valid_until?->toDateString(),
                'terms' => $quotation?->terms,
                'notes' => $quotation?->notes,
                'snapshot_hash' => $quotation?->snapshot_hash,
                'source_fingerprint' => $quotation?->source_fingerprint,
                'client_proposal_pdf_generated_at' => $quotation?->client_proposal_pdf_generated_at?->toISOString(),
                'client_proposal_pdf_snapshot_hash' => $quotation?->client_proposal_pdf_snapshot_hash,
                'client_proposal_pdf_source_fingerprint' => $quotation?->client_proposal_pdf_source_fingerprint,
                'client_proposal_pdf_filename' => $quotation?->client_proposal_pdf_filename,
                'approved_at' => $quotation?->approved_at?->toISOString(),
                'approval_notes' => $quotation?->approval_notes,
                'issued_at' => $quotation?->issued_at?->toISOString(),
                'updated_at' => $quotation?->updated_at?->toISOString(),
            ],
            'warnings' => $blockingWarnings,
        ];
    }

    private function hashSnapshot(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
