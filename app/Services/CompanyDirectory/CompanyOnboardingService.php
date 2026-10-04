<?php

namespace App\Services\CompanyDirectory;

use App\Models\Company;
use App\Models\CompanyOnboardingRequirement;
use Illuminate\Support\Collection;

class CompanyOnboardingService
{
    public function requirements(): Collection
    {
        return CompanyOnboardingRequirement::query()->active()->get();
    }

    public function evaluate(Company $company, ?Collection $requirements = null): array
    {
        $requirements ??= $this->requirements();
        $companyType = $company->profile?->company_type;

        $applicable = $requirements->filter(function (CompanyOnboardingRequirement $requirement) use ($companyType): bool {
            $types = $requirement->applicable_company_types;
            if (is_array($types) && $types !== []) {
                return $companyType !== null && in_array($companyType, $types, true);
            }

            return $requirement->required_by_default;
        })->values();

        if ($applicable->isEmpty()) {
            return [
                'state' => 'not_configured',
                'label' => 'Not configured',
                'progress' => null,
                'complete' => 0,
                'total' => 0,
                'items' => [],
                'blockers' => [],
                'definition_versions' => [],
            ];
        }

        $items = $applicable->map(function (CompanyOnboardingRequirement $requirement) use ($company): array {
            $complete = $this->passes($company, $requirement->evaluation_strategy);

            return [
                'code' => $requirement->code,
                'label' => $requirement->label,
                'complete' => $complete,
                'version' => $requirement->definition_version,
            ];
        });

        $complete = $items->where('complete', true)->count();
        $total = $items->count();
        $progress = (int) round(($complete / max(1, $total)) * 100);
        $state = $progress === 100 ? 'complete' : ($progress === 0 ? 'not_started' : 'in_progress');

        return [
            'state' => $state,
            'label' => match ($state) {
                'complete' => 'Complete',
                'not_started' => 'Not started',
                default => 'In progress',
            },
            'progress' => $progress,
            'complete' => $complete,
            'total' => $total,
            'items' => $items->all(),
            'blockers' => $items->where('complete', false)->values()->all(),
            'definition_versions' => $items->pluck('version')->unique()->values()->all(),
        ];
    }

    private function passes(Company $company, string $strategy): bool
    {
        return match ($strategy) {
            'company_identity' => filled($company->name) && filled($company->code),
            'legal_registration' => filled($company->registration_number)
                || $company->credentials->contains(fn ($credential): bool => $credential->kind === 'registration'),
            'primary_contact' => $company->contacts->contains(fn ($contact): bool => (bool) $contact->is_primary),
            'service_region' => $company->regions->isNotEmpty(),
            'company_review' => $company->profile?->last_reviewed_at !== null,
            'certifications' => $company->credentials->contains(fn ($credential): bool => $credential->kind === 'certification'),
            'insurance' => $company->credentials->contains(fn ($credential): bool => $credential->kind === 'insurance'),
            'financial_documentation' => $company->financialSnapshots->isNotEmpty()
                || $company->documents->contains(fn ($document): bool => $document->category === 'financial'),
            default => false,
        };
    }
}
