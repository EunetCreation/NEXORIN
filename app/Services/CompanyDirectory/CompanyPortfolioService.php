<?php

namespace App\Services\CompanyDirectory;

use App\Models\Company;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Support\Collection;

class CompanyPortfolioService
{
    public const ACTIVE_PROJECT_STATUSES = ['draft', 'survey', 'engineering', 'quotation', 'approved'];

    public function activeProjectCounts(?array $companyIds = null): Collection
    {
        return Project::query()
            ->selectRaw('company_id, COUNT(*) as aggregate')
            ->whereNull('archived_at')
            ->whereIn('status', self::ACTIVE_PROJECT_STATUSES)
            ->when($companyIds !== null, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->groupBy('company_id')
            ->pluck('aggregate', 'company_id')
            ->map(fn ($value): int => (int) $value);
    }

    /**
     * Accepted portfolio value is intentionally narrow and auditable:
     * only accepted quotation grand_total_idr values are aggregated.
     * No FX conversion and no financial-statement values are mixed in.
     */
    public function acceptedQuotationValues(?array $companyIds = null): array
    {
        $byCompany = [];
        $total = 0.0;
        $count = 0;
        $unavailable = 0;

        $query = Quotation::query()
            ->where('status', 'accepted')
            ->when($companyIds !== null, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->select(['id', 'company_id', 'currency', 'commercial_summary']);

        foreach ($query->cursor() as $quotation) {
            if (strtoupper((string) $quotation->currency) !== 'IDR') {
                $unavailable++;
                continue;
            }

            $value = data_get($quotation->commercial_summary, 'grand_total_idr');
            if (! is_numeric($value)) {
                $unavailable++;
                continue;
            }

            $amount = (float) $value;
            $byCompany[$quotation->company_id] = ($byCompany[$quotation->company_id] ?? 0.0) + $amount;
            $total += $amount;
            $count++;
        }

        return [
            'currency' => 'IDR',
            'basis' => 'Accepted quotations · commercial grand total',
            'by_company' => $byCompany,
            'total' => $count > 0 ? $total : null,
            'accepted_quotation_count' => $count,
            'excluded_or_unavailable_count' => $unavailable,
        ];
    }
    /**
     * Recent accepted quotation values using the same IDR-only portfolio basis.
     * Invalid/non-IDR values remain excluded; no conversion is attempted.
     */
    public function recentAcceptedQuotationValues(Company|int $company, int $limit = 5): Collection
    {
        $companyId = $company instanceof Company ? $company->getKey() : $company;

        return Quotation::query()
            ->where('company_id', $companyId)
            ->where('status', 'accepted')
            ->whereRaw('UPPER(currency) = ?', ['IDR'])
            ->with('project:id,company_id,project_number,name')
            ->select([
                'id', 'company_id', 'project_id', 'quote_number', 'currency',
                'commercial_summary', 'accepted_at',
            ])
            ->latest('accepted_at')
            ->latest('id')
            ->limit(max(20, $limit * 10))
            ->get()
            ->map(function (Quotation $quotation): ?array {
                if (strtoupper((string) $quotation->currency) !== 'IDR') {
                    return null;
                }

                $value = data_get($quotation->commercial_summary, 'grand_total_idr');
                if (! is_numeric($value)) {
                    return null;
                }

                return [
                    'project_id' => $quotation->project_id,
                    'project_number' => $quotation->project?->project_number,
                    'project_name' => $quotation->project?->name ?? 'Project not recorded',
                    'quotation_number' => $quotation->quote_number,
                    'accepted_at' => $quotation->accepted_at,
                    'currency' => 'IDR',
                    'value' => (float) $value,
                ];
            })
            ->filter()
            ->take($limit)
            ->values();
    }

}
