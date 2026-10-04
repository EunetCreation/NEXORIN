<?php

namespace App\Services\CompanyDirectory;

use App\Models\Company;
use Carbon\CarbonInterface;

class CompanyGovernanceReadinessService
{
    public const FRAMEWORK_VERSION = 'company-profile-governance-readiness-v1';

    public function evaluate(Company $company): array
    {
        $today = now()->startOfDay();
        $warningDate = $today->copy()->addDays(30);
        $evidence = collect([$company->credentials, $company->documents])->flatten(1);

        if ($evidence->isEmpty()) {
            return $this->result('not_assessed', 'Not assessed', [], 'No credential or governed-document evidence is recorded.');
        }

        $expired = $evidence->filter(fn ($item): bool => $this->isExpired($item->expires_at ?? null, $today));
        $expiring = $evidence->filter(fn ($item): bool => $this->isExpiring($item->expires_at ?? null, $today, $warningDate));
        $pending = $company->credentials->filter(fn ($item): bool => $item->status === 'pending');

        if ($expired->isNotEmpty()) {
            return $this->result('incomplete', 'Incomplete', [
                ['type' => 'expired_evidence', 'count' => $expired->count(), 'label' => 'Expired governance evidence'],
            ], 'At least one recorded credential or governed document has expired.');
        }

        if ($expiring->isNotEmpty()) {
            return $this->result('expiring', 'Expiring', [
                ['type' => 'expiring_evidence', 'count' => $expiring->count(), 'label' => 'Evidence expiring within 30 days'],
            ], 'Recorded evidence is approaching expiry.');
        }

        if ($pending->isNotEmpty()) {
            return $this->result('attention', 'Attention', [
                ['type' => 'pending_credentials', 'count' => $pending->count(), 'label' => 'Credentials awaiting verification'],
            ], 'Credential verification is still pending.');
        }

        $credible = $company->credentials->contains(fn ($item): bool => in_array($item->status, ['active', 'verified'], true))
            || $company->documents->contains(fn ($item): bool => $item->category === 'legal' && $item->status === 'active');

        if (! $credible) {
            return $this->result('attention', 'Attention', [], 'Evidence exists but has not reached an active or verified state.');
        }

        return $this->result('ready', 'Ready', [], 'Recorded profile-governance evidence has no current expiry or verification blocker.');
    }

    private function result(string $state, string $label, array $alerts, string $basis): array
    {
        return [
            'state' => $state,
            'label' => $label,
            'score' => null,
            'framework' => 'Company Profile Governance Readiness',
            'framework_version' => self::FRAMEWORK_VERSION,
            'basis' => $basis,
            'alerts' => $alerts,
        ];
    }

    private function isExpired(mixed $date, CarbonInterface $today): bool
    {
        return $date instanceof CarbonInterface && $date->copy()->startOfDay()->lt($today);
    }

    private function isExpiring(mixed $date, CarbonInterface $today, CarbonInterface $warningDate): bool
    {
        if (! $date instanceof CarbonInterface) {
            return false;
        }

        $candidate = $date->copy()->startOfDay();

        return $candidate->gte($today) && $candidate->lte($warningDate);
    }
}
