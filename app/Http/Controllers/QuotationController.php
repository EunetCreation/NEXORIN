<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\GenerateQuotationRequest;
use App\Models\Company;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\Quotation\QuotationEngine;
use App\Services\Quotation\QuotationPdfRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(
        private readonly QuotationEngine $quotationEngine,
    ) {
    }

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = ['all', 'not_generated', 'draft', 'issued', 'accepted', 'rejected', 'expired', 'locked'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number,email,phone',
                'billOfMaterial:id,project_id,pricing_status,grand_price_idr,priced_at',
                'financialAnalysis:id,project_id,status,project_capex_idr,first_year_saving_idr,simple_payback_years,calculated_at',
                'quotation:id,project_id,quote_number,revision_number,status,commercial_summary,valid_until,prepared_by,issued_at,created_at,updated_at',
            ]);

        if (! $user->hasAnyCompanyPermission($company, ['project.view', 'quotation.view'])) {
            $query->ownedBy($user);
        }

        $projects = $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('project_number', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'not_generated', fn (Builder $builder) => $builder->whereDoesntHave('quotation'))
            ->when(
                $status !== 'all' && $status !== 'not_generated',
                fn (Builder $builder) => $builder->whereHas(
                    'quotation',
                    fn (Builder $quotation) => $quotation->where('status', $status)
                )
            )
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statsQuery = $company->projects()->current();

        if (! $user->hasAnyCompanyPermission($company, ['project.view', 'quotation.view'])) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'ready' => (clone $statsQuery)
                ->whereHas('billOfMaterial', fn (Builder $bom) => $bom
                    ->where('pricing_status', 'priced')
                    ->where('grand_price_idr', '>', 0))
                ->whereHas('financialAnalysis', fn (Builder $analysis) => $analysis
                    ->where('status', 'calculated'))
                ->count(),
            'draft' => (clone $statsQuery)->whereHas('quotation', fn (Builder $quotation) => $quotation->where('status', 'draft'))->count(),
            'issued' => (clone $statsQuery)->whereHas('quotation', fn (Builder $quotation) => $quotation->where('status', 'issued'))->count(),
        ];

        return view('quotations.index', [
            'company' => $company,
            'projects' => $projects,
            'stats' => $stats,
            'filters' => compact('search', 'status'),
        ]);
    }

    public function show(Request $request, Company $company, Project $project): View
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.view', 'quotation.create', 'quotation.update', 'quotation.send'],
            ['quotation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number,email,phone,address,city,province',
            'technicalCalculation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation.items',
            'quotation.preparer:id,name',
            'quotation.issuer:id,name',
        ]);

        $quotation = $project->quotation;
        $bom = $project->billOfMaterial;
        $analysis = $project->financialAnalysis;
        $defaults = $this->quotationEngine->defaultParameters();
        $terms = $quotation?->terms ?? [];
        $summary = $quotation?->summary ?? [];

        $parameters = array_merge($defaults, [
            'quotation_title' => $quotation?->title ?: ($summary['title'] ?? $defaults['quotation_title']),
            'validity_days' => $summary['validity_days'] ?? $defaults['validity_days'],
            'include_item_detail' => true,
            'payment_terms' => $terms['payment_terms'] ?? $defaults['payment_terms'],
            'delivery_terms' => $terms['delivery_terms'] ?? $defaults['delivery_terms'],
            'warranty_terms' => $terms['warranty_terms'] ?? $defaults['warranty_terms'],
            'exclusions' => $terms['exclusions'] ?? $defaults['exclusions'],
            'quotation_note' => $terms['quotation_note'] ?? $quotation?->notes,
        ]);

        $canGenerate = $request->user()->hasAnyCompanyPermission($company, ['quotation.create', 'quotation.update'])
            && ! $project->isArchived();
        $canIssue = $request->user()->hasCompanyPermission($company, 'quotation.send')
            && $quotation
            && $quotation->status === 'draft'
            && ! $project->isArchived();

        return view('quotations.show', compact(
            'company',
            'project',
            'quotation',
            'bom',
            'analysis',
            'parameters',
            'canGenerate',
            'canIssue'
        ));
    }

    public function generate(GenerateQuotationRequest $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.create', 'quotation.update'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot generate quotations.');
        }

        $project->load([
            'customer',
            'technicalCalculation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation',
        ]);

        $bom = $project->billOfMaterial;
        $analysis = $project->financialAnalysis;

        if (! $bom || $bom->pricing_status !== 'priced' || (float) $bom->grand_price_idr <= 0) {
            throw ValidationException::withMessages([
                'bom' => 'Calculate BOQ pricing before generating a quotation.',
            ]);
        }

        if (! $analysis || $analysis->status !== 'calculated') {
            throw ValidationException::withMessages([
                'financial_analysis' => 'Calculate financial analysis before generating a quotation.',
            ]);
        }

        $payload = $this->quotationEngine->generate(
            $project,
            $bom,
            $analysis,
            $request->validated()
        );

        DB::transaction(function () use ($request, $company, $project, $bom, $analysis, $payload): void {
            $quotation = $project->quotation()->firstOrNew();
            $revision = $quotation->exists ? ((int) $quotation->revision_number + 1) : 0;
            $summary = $payload['summary'];

            $quotation->fill([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'financial_analysis_id' => $analysis->getKey(),
                'bill_of_material_id' => $bom->getKey(),
                'quote_number' => $quotation->quote_number ?: $this->nextQuoteNumber($company),
                'revision_number' => $revision,
                'status' => 'draft',
                'currency' => 'IDR',
                'title' => $summary['title'],
                'valid_until' => $summary['valid_until'],
                'customer_snapshot' => $payload['customer_snapshot'],
                'project_snapshot' => $payload['project_snapshot'],
                'technical_summary' => $payload['technical_summary'],
                'financial_summary' => $payload['financial_summary'],
                'commercial_summary' => $payload['commercial_summary'],
                'terms' => $payload['terms'],
                'warnings' => $payload['warnings'],
                'source_snapshot' => $payload['source_snapshot'],
                'notes' => $payload['terms']['quotation_note'],
                'prepared_by' => $request->user()->getKey(),
                'issued_by' => null,
                'issued_at' => null,
                'created_by' => $quotation->exists ? $quotation->created_by : $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]);

            $quotation->save();
            $quotation->items()->delete();

            foreach ($payload['items'] as $index => $item) {
                $quotation->items()->create([
                    'category' => $item['category'],
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price_idr' => $item['unit_price_idr'],
                    'line_price_idr' => $item['line_price_idr'],
                    'source_key' => $item['source_key'],
                    'sort_order' => $index + 1,
                    'metadata' => $item['metadata'],
                ]);
            }
        });

        return redirect()
            ->route('companies.quotations.show', [$company, $project])
            ->with('success', 'Quotation draft has been generated.');
    }


    public function pdf(Request $request, Company $company, Project $project, QuotationPdfRenderer $pdfRenderer): Response
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.view', 'quotation.create', 'quotation.update', 'quotation.send'],
            ['quotation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number,email,phone,address,city,province',
            'quotation.items',
            'quotation.project.customer',
            'quotation.preparer:id,name',
            'quotation.issuer:id,name',
        ]);

        $quotation = $project->quotation;

        if (! $quotation) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate quotation before downloading the PDF.',
            ]);
        }

        $pdf = $pdfRenderer->render($quotation);
        $filename = $pdfRenderer->filename($quotation);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function issue(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.send'],
            []
        );

        $quotation = $project->quotation;

        if (! $quotation) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate quotation before issuing it.',
            ]);
        }

        if ($quotation->status !== 'draft') {
            return back()->with('error', 'Only draft quotations can be issued.');
        }

        $quotation->forceFill([
            'status' => 'issued',
            'issued_by' => $request->user()->getKey(),
            'issued_at' => now(),
            'updated_by' => $request->user()->getKey(),
        ])->save();

        if ($project->canTransitionTo('quotation')) {
            $project->forceFill([
                'status' => 'quotation',
                'status_updated_by' => $request->user()->getKey(),
                'status_changed_at' => now(),
            ])->save();
        }

        return back()->with('success', 'Quotation has been issued.');
    }

    private function nextQuoteNumber(Company $company): string
    {
        $prefix = sprintf('QTN-%s-%s', strtoupper((string) $company->code), now()->format('Ymd'));
        $count = Quotation::query()
            ->where('company_id', $company->getKey())
            ->where('quote_number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $count);
    }
}
