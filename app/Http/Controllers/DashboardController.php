<?php

namespace App\Http\Controllers;

use App\Models\BillOfMaterial;
use App\Models\Material;
use App\Models\PriceBookItem;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\TechnicalCalculation;
use App\Support\ProjectCockpit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the current user's workspace overview.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $company = $user->activeCompanies()->first();

        $overview = $this->emptyOverview();

        if ($company) {
            $overview = $this->buildOverview($company->getKey());
        }

        return view('dashboard', [
            'currentUser' => $user,
            'currentCompany' => $company,
            ...$overview,
        ]);
    }

    /**
     * Build dashboard data without changing the existing project workflow.
     *
     * @return array<string, mixed>
     */
    private function buildOverview(int $companyId): array
    {
        $projectQuery = Project::query()
            ->where('company_id', $companyId)
            ->whereNull('archived_at');

        $projectStatusCounts = collect(Project::STATUSES)
            ->mapWithKeys(fn (string $status): array => [
                $status => (clone $projectQuery)->where('status', $status)->count(),
            ]);

        $activeProjectsCount = $projectStatusCounts
            ->except(['completed', 'cancelled'])
            ->sum();

        $needsReviewCount = $projectStatusCounts
            ->only(['survey', 'engineering'])
            ->sum();

        $recentProjects = (clone $projectQuery)
            ->with([
                'customer',
                'loadAnalysis.electricityBills',
                'loadAnalysis.appliances',
                'solarProfile',
                'roofSiteSurvey.surfaces',
                'systemRecommendation',
                'technicalCalculation',
                'billOfMaterial',
                'financialAnalysis',
                'quotation',
            ])
            ->latest('updated_at')
            ->take(6)
            ->get();

        $projectQueue = $recentProjects->map(function (Project $project): array {
            $cockpit = ProjectCockpit::build($project);

            return [
                'project' => $project,
                'completion_percent' => $cockpit['completion_percent'],
                'next_step' => $cockpit['next_step'],
                'health' => $cockpit['health'],
                'warning_count' => collect($cockpit['warning_counts'])->sum(),
            ];
        });

        $catalogQuery = Material::query()
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->where('is_active', true);

        $catalogTotal = (clone $catalogQuery)->count();
        $catalogApproved = (clone $catalogQuery)
            ->where('catalog_status', 'approved')
            ->count();

        $quotationStatusCounts = Quotation::query()
            ->where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentQuotations = Quotation::query()
            ->where('company_id', $companyId)
            ->with('project.customer')
            ->latest('updated_at')
            ->take(4)
            ->get();

        return [
            'activeProjectsCount' => $activeProjectsCount,
            'needsReviewCount' => $needsReviewCount,
            'designedCapacityKw' => (float) TechnicalCalculation::query()
                ->where('company_id', $companyId)
                ->sum('inverter_ac_power_kw'),
            'pipelineValue' => (float) BillOfMaterial::query()
                ->where('company_id', $companyId)
                ->sum('grand_price_idr'),
            'projectStatusCounts' => $projectStatusCounts,
            'projectQueue' => $projectQueue,
            'catalogTotal' => $catalogTotal,
            'catalogApproved' => $catalogApproved,
            'catalogReadinessPercent' => $catalogTotal > 0
                ? (int) round(($catalogApproved / $catalogTotal) * 100)
                : 0,
            'priceReferenceCount' => PriceBookItem::query()
                ->whereHas('priceBook', fn ($query) => $query->where('company_id', $companyId))
                ->count(),
            'quotationStatusCounts' => $quotationStatusCounts,
            'quotationOpenCount' => $quotationStatusCounts
                ->except(['accepted', 'rejected', 'cancelled', 'expired'])
                ->sum(),
            'recentQuotations' => $recentQuotations,
        ];
    }

    /**
     * Keep the view predictable for users without an active company.
     *
     * @return array<string, mixed>
     */
    private function emptyOverview(): array
    {
        return [
            'activeProjectsCount' => 0,
            'needsReviewCount' => 0,
            'designedCapacityKw' => 0.0,
            'pipelineValue' => 0.0,
            'projectStatusCounts' => collect(Project::STATUSES)
                ->mapWithKeys(fn (string $status): array => [$status => 0]),
            'projectQueue' => collect(),
            'catalogTotal' => 0,
            'catalogApproved' => 0,
            'catalogReadinessPercent' => 0,
            'priceReferenceCount' => 0,
            'quotationStatusCounts' => collect(),
            'quotationOpenCount' => 0,
            'recentQuotations' => collect(),
        ];
    }
}
