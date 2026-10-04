@extends('layouts.app')

@section('title', 'BOM Library')
@section('page-eyebrow', 'Procurement intelligence')
@section('page-heading', 'BOM Library')
@section('force-theme', 'dark')
@section('body-class', 'page-bom-library-v6')
@section('disable-remote-fonts', 'true')

@php
    $moneyCompact = static function (float|int|string|null $value): string {
        $amount = (float) ($value ?? 0);
        if ($amount >= 1_000_000_000_000) return 'Rp '.number_format($amount / 1_000_000_000_000, 2).' T';
        if ($amount >= 1_000_000_000) return 'Rp '.number_format($amount / 1_000_000_000, 2).' B';
        if ($amount >= 1_000_000) return 'Rp '.number_format($amount / 1_000_000, 1).' M';
        if ($amount >= 1_000) return 'Rp '.number_format($amount / 1_000, 1).' K';
        return 'Rp '.number_format($amount, 0, ',', '.');
    };

    $activeFilters = collect([
        $filters['search'] !== '',
        $filters['status'] !== 'all',
        $filters['system'] !== 'all',
        $filters['projectStatus'] !== 'all',
    ])->filter()->count();

    $costSeriesKey = $canViewCost ? 'total_cost_idr' : 'total_price_idr';
    $costSeriesLabel = $canViewCost ? 'Cost' : 'Commercial value';
    $categoryChart = $categoryBreakdown->map(fn (array $item): array => [
        'name' => $item['label'],
        'value' => $item['line_items'],
        'cost' => $item['total_cost_idr'],
        'price' => $item['total_price_idr'],
    ])->values();

    $costChart = $categoryBreakdown
        ->filter(fn (array $item): bool => (float) $item[$costSeriesKey] > 0)
        ->map(fn (array $item): array => [
            'name' => $item['label'],
            'value' => (float) $item[$costSeriesKey],
            'lines' => $item['line_items'],
        ])->values();

    $statusChart = $statusBreakdown->map(fn (array $item): array => [
        'name' => $item['label'],
        'value' => $item['value'],
        'key' => $item['key'],
    ])->values();

    $scopeChart = $scopeImpact->map(fn (array $item): array => [
        'name' => $item['label'],
        'value' => $item['value'],
        'key' => $item['key'],
    ])->values();

    $chartPayload = [
        'category' => $categoryChart,
        'cost' => $costChart,
        'status' => $statusChart,
        'scope' => $scopeChart,
        'totals' => [
            'lineItems' => $stats['line_items'],
            'projects' => $stats['projects'],
            'cost' => $canViewCost ? $stats['cost_composition_total_idr'] : $stats['price_composition_total_idr'],
            'costLabel' => $costSeriesLabel,
        ],
        'permissions' => [
            'cost' => $canViewCost,
            'selling' => $canViewSelling,
        ],
    ];

    $healthScore = (int) $stats['portfolio_health_score'];
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-bom-library-v6.css') }}?v=002.6" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-bom-library-v6.js') }}?v=002.6" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
<div
    class="nxbomlib"
    data-nxbom-library
    data-echarts-src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0"
>
    <header class="nxbomlib-hero">
        <div class="nxbomlib-hero-copy">
            <span class="nxbomlib-eyebrow">Centralized material scope and commercial readiness</span>
            <h2>BOM Library</h2>
            <p>Centralized bill of materials packages across active solar projects, engineering scopes, commercial controls, and release states.</p>
        </div>
        <div class="nxbomlib-hero-actions">
            @if ($canViewMaterialCatalog)
                <a href="{{ route('companies.materials.index', $company) }}" class="nxbomlib-button is-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
                    Material Catalog
                </a>
            @endif
            <a href="{{ route('companies.technical-calculations.index', $company) }}" class="nxbomlib-button is-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/></svg>
                Engineering Queue
            </a>
        </div>
    </header>

    <section class="nxbomlib-kpi-grid" aria-label="BOM portfolio indicators">
        <a href="{{ route('companies.boms.index', $company) }}" class="nxbomlib-kpi is-cyan">
            <span class="nxbomlib-kpi-head"><small>Total line items</small><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg></i></span>
            <strong>{{ number_format($stats['line_items']) }}</strong>
            <span>Across {{ number_format($stats['generated']) }} generated packages</span>
            <b><em style="width:{{ min(100, max(4, $stats['projects'] > 0 ? ($stats['generated'] / $stats['projects']) * 100 : 0)) }}%"></em></b>
            <small>{{ number_format($stats['projects']) }} active projects in company scope</small>
        </a>

        <a href="{{ route('companies.boms.index', ['company' => $company, 'status' => 'not_generated']) }}" class="nxbomlib-kpi is-teal">
            <span class="nxbomlib-kpi-head"><small>Engineering ready</small><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 13 4 4L20 5"/><path d="M4 5h8M4 9h5"/></svg></i></span>
            <strong>{{ $stats['engineering_readiness_percent'] }}%</strong>
            <span>{{ number_format($stats['ready']) }} engineer-verified projects</span>
            <b><em style="width:{{ $stats['engineering_readiness_percent'] }}%"></em></b>
            <small>{{ number_format($stats['awaiting_generation']) }} ready packages still await generation</small>
        </a>

        <div class="nxbomlib-kpi is-amber">
            <span class="nxbomlib-kpi-head"><small>Unresolved controls</small><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg></i></span>
            <strong>{{ number_format($stats['unresolved_controls']) }}</strong>
            <span>Governance and pricing exceptions</span>
            <b><em style="width:{{ min(100, max(4, 100 - $stats['catalog_governance_percent'])) }}%"></em></b>
            <small>{{ number_format($controlIssues['catalog_review']) }} catalog reviews · {{ number_format($controlIssues['low_margin']) }} margin guards</small>
        </div>

        <a href="{{ route('companies.boms.index', ['company' => $company, 'status' => 'priced']) }}" class="nxbomlib-kpi is-green">
            <span class="nxbomlib-kpi-head"><small>Procurement readiness</small><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/></svg></i></span>
            <strong>{{ $stats['portfolio_readiness_percent'] }}%</strong>
            <span>{{ number_format($stats['priced']) }} commercially priced packages</span>
            <b><em style="width:{{ $stats['portfolio_readiness_percent'] }}%"></em></b>
            <small>{{ $stats['pricing_integrity_percent'] }}% pricing integrity across chargeable lines</small>
        </a>

        <div class="nxbomlib-kpi is-orange {{ $canViewCost ? '' : 'is-restricted' }}">
            <span class="nxbomlib-kpi-head"><small>Cost summary</small><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 18h16M6 15l4-4 3 2 5-7"/><path d="M16 6h2v2"/></svg></i></span>
            @if ($canViewCost)
                <strong>{{ $moneyCompact($stats['total_cost_idr']) }}</strong>
                <span>Total estimated cost</span>
                <b><em style="width:{{ min(100, max(4, $stats['pricing_coverage_percent'])) }}%"></em></b>
                <small>{{ $canViewSelling ? $moneyCompact($stats['total_price_idr']).' priced customer value' : $stats['pricing_coverage_percent'].'% pricing coverage' }}</small>
            @else
                <strong>Restricted</strong>
                <span>Cost visibility follows assigned role</span>
                <b><em style="width:{{ $stats['pricing_coverage_percent'] }}%"></em></b>
                <small>{{ $stats['pricing_coverage_percent'] }}% workflow coverage remains visible</small>
            @endif
        </div>
    </section>

    <section class="nxbomlib-filter-shell">
        <form method="GET" action="{{ route('companies.boms.index', $company) }}" class="nxbomlib-filters" data-nxbom-filter-form>
            <div class="nxbomlib-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search project, customer, city, or package number" autocomplete="off">
            </div>
            <label>
                <span>System</span>
                <select name="system" data-nxbom-auto-submit>
                    <option value="all">All systems</option>
                    @foreach (['on_grid' => 'On-grid', 'hybrid' => 'Hybrid', 'undecided' => 'Undecided'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['system'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Project state</span>
                <select name="project_status" data-nxbom-auto-submit>
                    <option value="all">All project states</option>
                    @foreach (\App\Models\Project::STATUSES as $value)
                        <option value="{{ $value }}" @selected($filters['projectStatus'] === $value)>{{ str($value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>BOM state</span>
                <select name="status" data-nxbom-auto-submit>
                    @foreach (['all' => 'All BOM states', 'not_generated' => 'Not generated', 'generated' => 'Generated', 'priced' => 'Priced', 'released' => 'Active release approval', 'draft' => 'Draft'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="nxbomlib-filter-button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Apply
            </button>
            @if ($activeFilters > 0)
                <a href="{{ route('companies.boms.index', $company) }}" class="nxbomlib-clear">Clear {{ $activeFilters }}</a>
            @endif
        </form>
    </section>

    <div class="nxbomlib-workspace-grid">
        <div class="nxbomlib-workspace-main">
        <section class="nxbomlib-table-card">
            <header class="nxbomlib-section-head">
                <div>
                    <span>BOM packages</span>
                    <h3>Project Material Portfolio</h3>
                    <p>Engineering basis, category scope, commercial exposure, readiness, and controlled release state.</p>
                </div>
                <div class="nxbomlib-result-count">{{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }} of {{ number_format($projects->total()) }}</div>
            </header>

            <div class="nxbomlib-table-wrap">
                <div class="nxbomlib-table-head" aria-hidden="true">
                    <span>Project / package</span>
                    <span>Discipline</span>
                    <span>Category breakdown</span>
                    <span>Line items</span>
                    <span>{{ $canViewCost ? 'Est. cost' : 'Est. value' }}</span>
                    <span>Readiness</span>
                    <span>Status</span>
                    <span>Updated</span>
                    <span></span>
                </div>

                <div class="nxbomlib-table-body">
                    @forelse ($projects as $project)
                        @php
                            $bom = $project->billOfMaterial;
                            $technical = $project->technicalCalculation;
                            $technicalReady = $technical?->result_status === 'verified';
                            $generated = (bool) $bom;
                            $priced = $bom?->pricing_status === 'priced';
                            $released = (bool) $project->activeReleaseApproval;
                            $scope = (array) data_get($bom?->summary, 'scope_counters', []);
                            $baseCount = (int) ($scope['base_boq_item_count'] ?? data_get($bom?->summary, 'base_boq_item_count', 0));
                            $optionalCount = (int) ($scope['optional_addon_item_count'] ?? data_get($bom?->summary, 'optional_addon_item_count', 0));
                            $internalCount = (int) ($scope['catalog_candidate_item_count'] ?? data_get($bom?->summary, 'catalog_candidate_item_count', 0));
                            $scopeTotal = max(1, $baseCount + $optionalCount + $internalCount);
                            $lineCount = (int) data_get($bom?->summary, 'item_count', 0);
                            $readiness = ($technicalReady ? 35 : 0) + ($generated ? 30 : 0) + ($priced ? 25 : 0) + ($released ? 10 : 0);
                            $stateKey = $released ? 'released' : ($priced ? 'priced' : ($generated ? 'generated' : ($technicalReady ? 'ready' : 'pending')));
                            $stateLabel = match ($stateKey) {
                                'released' => 'Approval active',
                                'priced' => 'Priced',
                                'generated' => 'In review',
                                'ready' => 'Engineer verified',
                                default => 'Engineering',
                            };
                            $updatedAt = $bom?->updated_at ?? $technical?->updated_at ?? $project->updated_at;
                            $estimatedValue = $canViewCost
                                ? ($priced ? $bom?->grand_cost_idr : null)
                                : ($canViewSelling ? ($priced ? $bom?->grand_price_idr : null) : null);
                        @endphp

                        <article
                            class="nxbomlib-row"
                            tabindex="0"
                            data-nxbom-row
                            data-open-url="{{ route('companies.boms.show', [$company, $project]) }}"
                        >
                            <div class="nxbomlib-project-cell" data-label="Project / package">
                                <span class="nxbomlib-project-mark is-{{ $project->proposed_system }}">
                                    <svg viewBox="0 0 52 52" fill="none" aria-hidden="true"><path d="M5 34 18 20l8 7 8-9 13 16"/><path d="M8 39h36M11 34h34M18 39l4-13m9 13 4-15"/><path d="M14 31h23M17 26h18"/><circle cx="40" cy="11" r="5"/></svg>
                                </span>
                                <span>
                                    <strong>{{ $project->name }}</strong>
                                    <small>{{ $bom?->bom_number ?: $project->project_number }}</small>
                                    <em>{{ $project->customer?->name ?: ($project->city ?: 'Company portfolio') }}</em>
                                </span>
                            </div>

                            <div class="nxbomlib-basis" data-label="Discipline">
                                <strong>{{ $project->proposed_system_label }}</strong>
                                <small>{{ $technicalReady ? 'Engineer verified' : ($technical?->calculated_at ? str($technical->result_status)->replace('_', ' ')->title() : 'Calculation pending') }}</small>
                            </div>

                            <div class="nxbomlib-scope" data-label="Category breakdown">
                                @if ($generated)
                                    <span class="nxbomlib-scope-track" aria-label="Base {{ $baseCount }}, optional {{ $optionalCount }}, catalog review {{ $internalCount }}">
                                        <i class="is-base" style="width:{{ ($baseCount / $scopeTotal) * 100 }}%"></i>
                                        <i class="is-optional" style="width:{{ ($optionalCount / $scopeTotal) * 100 }}%"></i>
                                        <i class="is-internal" style="width:{{ ($internalCount / $scopeTotal) * 100 }}%"></i>
                                    </span>
                                    <small>{{ round(($baseCount / $scopeTotal) * 100) }}% · {{ round(($optionalCount / $scopeTotal) * 100) }}% · {{ round(($internalCount / $scopeTotal) * 100) }}%</small>
                                @else
                                    <span class="nxbomlib-empty-meter"></span>
                                    <small>Not materialized</small>
                                @endif
                            </div>

                            <div class="nxbomlib-number" data-label="Line items">
                                <strong>{{ number_format($lineCount) }}</strong>
                                <small>{{ $generated ? 'controlled lines' : 'not generated' }}</small>
                            </div>

                            <div class="nxbomlib-number" data-label="Estimated value">
                                @if ($estimatedValue !== null)
                                    <strong>{{ $moneyCompact($estimatedValue) }}</strong>
                                    <small>{{ $canViewCost ? 'estimated cost' : 'customer total' }}</small>
                                @elseif (!$canViewCost && !$canViewSelling)
                                    <strong>Restricted</strong><small>role-controlled value</small>
                                @else
                                    <strong>—</strong><small>pricing required</small>
                                @endif
                            </div>

                            <div class="nxbomlib-readiness" data-label="Readiness">
                                <span class="nxbomlib-ring" style="--score:{{ $readiness }}"><b>{{ $readiness }}%</b></span>
                                <small>{{ $readiness >= 90 ? 'Controlled' : ($readiness >= 60 ? 'In progress' : 'Action required') }}</small>
                            </div>

                            <div class="nxbomlib-status" data-label="Status">
                                <span class="is-{{ $stateKey }}">{{ $stateLabel }}</span>
                                <small>{{ $project->status_label }}</small>
                            </div>

                            <div class="nxbomlib-updated" data-label="Updated">
                                <strong>{{ $updatedAt?->format('d M Y') }}</strong>
                                <small>{{ $updatedAt?->diffForHumans() }}</small>
                            </div>

                            <a href="{{ route('companies.boms.show', [$company, $project]) }}" class="nxbomlib-open" aria-label="Open {{ $project->name }} BOM package">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        </article>
                    @empty
                        <div class="nxbomlib-empty-state">
                            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h6l2 2h8v10H4z"/><path d="M8 13h8M8 16h5"/></svg></span>
                            <h4>No BOM packages match this scope</h4>
                            <p>Adjust the filters or complete a technical calculation before generating a material package.</p>
                            <a href="{{ route('companies.technical-calculations.index', $company) }}">Open engineering queue</a>
                        </div>
                    @endforelse
                </div>
            </div>

            <footer class="nxbomlib-pagination">
                <span>Showing {{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }} of {{ number_format($projects->total()) }} packages</span>
                @if ($projects->hasPages())
                    <nav class="nxbomlib-pages" aria-label="BOM package pagination">
                        @if ($projects->onFirstPage())
                            <span class="is-disabled" aria-hidden="true">‹</span>
                        @else
                            <a href="{{ $projects->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
                        @endif
                        @for ($pageNumber = 1; $pageNumber <= $projects->lastPage(); $pageNumber++)
                            @if ($pageNumber === $projects->currentPage())
                                <span class="is-current" aria-current="page">{{ $pageNumber }}</span>
                            @else
                                <a href="{{ $projects->url($pageNumber) }}">{{ $pageNumber }}</a>
                            @endif
                        @endfor
                        @if ($projects->hasMorePages())
                            <a href="{{ $projects->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
                        @else
                            <span class="is-disabled" aria-hidden="true">›</span>
                        @endif
                    </nav>
                @else
                    <span class="nxbomlib-pagination-single">Page 1</span>
                @endif
            </footer>
        </section>

    <section class="nxbomlib-analytics" data-nxbom-analytics>
        <div class="nxbomlib-analytics-pair">
            <article class="nxbomlib-chart-card is-large is-cost-composition">
                <header><div><span>Cost and scope composition</span><h3>Cost Composition</h3></div><b>{{ $canViewCost ? 'Estimated cost' : ($canViewSelling ? 'Commercial value' : 'Restricted') }}</b></header>
                <div class="nxbomlib-chart-layout">
                    <div class="nxbomlib-chart-shell">
                        <div class="nxbomlib-chart" data-nxbom-chart="cost" role="img" aria-label="BOM cost composition by category"></div>
                        <div class="nxbomlib-chart-fallback" aria-hidden="true"><strong>{{ $canViewCost ? $moneyCompact($stats['cost_composition_total_idr']) : ($canViewSelling ? $moneyCompact($stats['price_composition_total_idr']) : '—') }}</strong><span>Category {{ strtolower($costSeriesLabel) }}</span></div>
                    </div>
                    <ol class="nxbomlib-chart-legend is-category">
                        @forelse ($categoryBreakdown->take(6) as $item)
                            @php
                                $categoryValue = $canViewCost ? $item['total_cost_idr'] : $item['total_price_idr'];
                                $categoryShare = $canViewCost ? $item['cost_share'] : $item['price_share'];
                            @endphp
                            <li><i></i><span>{{ $item['label'] }}</span><b>{{ $canViewCost || $canViewSelling ? $moneyCompact($categoryValue) : 'Restricted' }}</b><small>{{ number_format($categoryShare, 1) }}%</small></li>
                        @empty
                            <li class="is-empty">No cost composition is available.</li>
                        @endforelse
                    </ol>
                </div>
                @if ($canViewCost)
                    <a class="nxbomlib-card-link" href="{{ route('companies.price-books.index', $company) }}">View cost controls <span>→</span></a>
                @endif
            </article>

            <article class="nxbomlib-chart-card is-large">
                <header><div><span>Material distribution</span><h3>Category Mix</h3></div><b>{{ number_format($stats['line_items']) }} total lines</b></header>
                <div class="nxbomlib-chart-layout">
                    <div class="nxbomlib-chart-shell">
                        <div class="nxbomlib-chart" data-nxbom-chart="category" role="img" aria-label="BOM line item category distribution"></div>
                        <div class="nxbomlib-chart-fallback" aria-hidden="true"><strong>{{ number_format($stats['line_items']) }}</strong><span>Total lines</span></div>
                    </div>
                    <ol class="nxbomlib-chart-legend is-category">
                        @forelse ($categoryBreakdown->take(6) as $item)
                            <li><i></i><span>{{ $item['label'] }}</span><b>{{ number_format($item['line_items']) }}</b><small>{{ number_format($item['share'], 1) }}%</small></li>
                        @empty
                            <li class="is-empty">No category lines are available.</li>
                        @endforelse
                    </ol>
                </div>
                @if ($canViewMaterialCatalog)
                    <a class="nxbomlib-card-link" href="{{ route('companies.materials.index', $company) }}">View category breakdown <span>→</span></a>
                @endif
            </article>
        </div>

        <div class="nxbomlib-readiness-grid">
            <article class="nxbomlib-readiness-card">
                <header><div><span>Portfolio readiness</span><h3>Release Readiness</h3></div><b>{{ $stats['portfolio_readiness_percent'] }}% weighted</b></header>
                <div class="nxbomlib-readiness-table">
                    <div class="nxbomlib-readiness-head"><span>Control gate</span><span>Ready</span><span>Coverage</span><span>Signal</span></div>
                    @foreach ($readinessBreakdown as $item)
                        @php
                            $tone = $item['percent'] >= 80 ? 'good' : ($item['percent'] >= 55 ? 'watch' : 'risk');
                        @endphp
                        <div class="nxbomlib-readiness-row">
                            <strong>{{ $item['label'] }}</strong>
                            <span>{{ number_format($item['value']) }} / {{ number_format($item['total']) }}</span>
                            <b>{{ $item['percent'] }}%</b>
                            <span class="nxbomlib-ready-track is-{{ $tone }}"><i style="width:{{ $item['percent'] }}%"></i></span>
                        </div>
                    @endforeach
                </div>
                <a class="nxbomlib-card-link" href="{{ route('companies.technical-calculations.index', $company) }}">Review readiness workflow <span>→</span></a>
            </article>

            <article class="nxbomlib-issues-card">
                <header><div><span>Control exceptions</span><h3>Unresolved Items</h3></div><b>{{ number_format($stats['unresolved_controls']) }}</b></header>
                <div class="nxbomlib-issue-list">
                    @forelse ($issueBreakdown as $item)
                        <div><span>{{ $item['label'] }}</span><strong>{{ number_format($item['value']) }}</strong></div>
                    @empty
                        <div class="is-clear"><span>No unresolved pricing or catalog controls</span><strong>Clear</strong></div>
                    @endforelse
                    <div class="is-total"><span>Total control exceptions</span><strong>{{ number_format($stats['unresolved_controls']) }}</strong></div>
                </div>
                <div class="nxbomlib-control-summary" aria-label="Portfolio control context">
                    <div><span>Catalog Review</span><strong>{{ number_format($controlIssues['catalog_review']) }}</strong><small>Evidence requiring catalogue review</small></div>
                    <div><span>Pricing Exceptions</span><strong>{{ number_format($controlIssues['missing_price'] + $controlIssues['expired_price'] + $controlIssues['unavailable_price'] + $controlIssues['low_margin']) }}</strong><small>Current pricing-control exceptions</small></div>
                    <div><span>Approval Gap</span><strong>{{ number_format($stats['uncontrolled']) }}</strong><small>Priced packages without active approval</small></div>
                    <div><span>Active Releases</span><strong>{{ number_format($stats['released']) }}</strong><small>Packages with active release approval</small></div>
                </div>
                @if ($canViewMaterialCatalog)
                    <a class="nxbomlib-card-link" href="{{ route('companies.materials.index', $company) }}">Resolve governance items <span>→</span></a>
                @endif
            </article>
        </div>

        <div class="nxbomlib-bottom-grid" id="scope-impact">
            <article class="nxbomlib-chart-card is-scope">
                <header><div><span>BOQ scope model</span><h3>Scope Impact</h3></div><b>Persisted summary</b></header>
                <div class="nxbomlib-chart is-bar" data-nxbom-chart="scope" role="img" aria-label="BOM scope composition">
                    <div class="nxbomlib-bar-fallback">
                        @foreach ($scopeImpact as $item)
                            <span><b>{{ number_format($item['value']) }}</b><small>{{ $item['label'] }}</small></span>
                        @endforeach
                    </div>
                </div>
                <div class="nxbomlib-scope-summary">
                    @foreach ($scopeImpact->take(3) as $item)
                        <span><b>{{ number_format($item['value']) }}</b><small>{{ $item['label'] }}</small></span>
                    @endforeach
                </div>
                <a class="nxbomlib-card-link" href="{{ route('companies.boms.index', $company) }}">Review material scope <span>→</span></a>
            </article>

            <article class="nxbomlib-chart-card is-status-card">
                <header><div><span>Persisted BOM lifecycle</span><h3>Package Status</h3></div><b>{{ number_format($stats['projects']) }} projects</b></header>
                <div class="nxbomlib-chart-layout is-compact">
                    <div class="nxbomlib-chart-shell">
                        <div class="nxbomlib-chart" data-nxbom-chart="status" role="img" aria-label="Persisted BOM package lifecycle distribution"></div>
                        <div class="nxbomlib-chart-fallback" aria-hidden="true"><strong>{{ number_format($stats['projects']) }}</strong><span>Total projects</span></div>
                    </div>
                    <ol class="nxbomlib-chart-legend is-status">
                        @forelse ($statusBreakdown as $item)
                            <li class="is-{{ $item['key'] }}"><i></i><span>{{ $item['label'] }}</span><b>{{ number_format($item['value']) }}</b></li>
                        @empty
                            <li class="is-empty">No package lifecycle records are available.</li>
                        @endforelse
                    </ol>
                </div>
                <a class="nxbomlib-card-link" href="{{ route('companies.boms.index', $company) }}">Review package lifecycle <span>→</span></a>
            </article>

            <article class="nxbomlib-health-card">
                <header><div><span>BOM health score</span><h3>Portfolio Health</h3></div><b>Live assessment</b></header>
                <div class="nxbomlib-health-gauge" style="--health:{{ $healthScore }}"><span class="nxbomlib-health-copy"><span class="nxbomlib-health-score"><strong>{{ $healthScore }}</strong><span>/100</span></span><small>{{ $healthScore >= 80 ? 'Good' : ($healthScore >= 55 ? 'Needs attention' : 'At risk') }}</small></span></div>
                <ul>
                    <li><span>Engineering completeness</span><b>{{ $stats['engineering_readiness_percent'] }}%</b></li>
                    <li><span>Pricing coverage</span><b>{{ $stats['pricing_coverage_percent'] }}%</b></li>
                    <li><span>Active approval coverage</span><b>{{ $stats['control_coverage_percent'] }}%</b></li>
                    <li><span>Catalog governance</span><b>{{ $stats['catalog_governance_percent'] }}%</b></li>
                    <li><span>Pricing integrity</span><b>{{ $stats['pricing_integrity_percent'] }}%</b></li>
                </ul>
                <a href="{{ route('companies.boms.index', ['company' => $company, 'status' => 'not_generated']) }}">Improve score <span>→</span></a>
            </article>
        </div>
    </section>
        </div>

        <aside class="nxbomlib-intelligence">
            <header><span>BOM intelligence</span><strong>Portfolio controls</strong></header>

            <section>
                <h4>Readiness gaps</h4>
                <dl>
                    <div><dt>Engineering pending</dt><dd>{{ number_format(max(0, $stats['projects'] - $stats['ready'])) }}</dd></div>
                    <div><dt>Verified / no BOM</dt><dd class="is-warning">{{ number_format($stats['awaiting_generation']) }}</dd></div>
                    <div><dt>Pricing required</dt><dd class="is-warning">{{ number_format($stats['unpriced']) }}</dd></div>
                    <div><dt>Priced / no active approval</dt><dd>{{ number_format($stats['uncontrolled']) }}</dd></div>
                </dl>
                <a href="{{ route('companies.technical-calculations.index', $company) }}">Review engineering queue <span>→</span></a>
            </section>

            <section>
                <h4>Governance review</h4>
                <dl>
                    <div><dt>Catalog review items</dt><dd class="is-warning">{{ number_format($controlIssues['catalog_review']) }}</dd></div>
                    <div><dt>Margin guard exceptions</dt><dd class="{{ $controlIssues['low_margin'] > 0 ? 'is-danger' : 'is-positive' }}">{{ number_format($controlIssues['low_margin']) }}</dd></div>
                    <div><dt>Missing / stale pricing</dt><dd class="{{ ($controlIssues['missing_price'] + $controlIssues['expired_price'] + $controlIssues['unavailable_price']) > 0 ? 'is-danger' : 'is-positive' }}">{{ number_format($controlIssues['missing_price'] + $controlIssues['expired_price'] + $controlIssues['unavailable_price']) }}</dd></div>
                </dl>
                @if ($canViewMaterialCatalog)
                    <a href="{{ route('companies.materials.index', $company) }}">Open material governance <span>→</span></a>
                @endif
            </section>

            <section>
                <h4>Scope controls</h4>
                <dl>
                    <div><dt>Base BOQ rows</dt><dd>{{ number_format((int) $scopeImpact->firstWhere('key', 'base')['value']) }}</dd></div>
                    <div><dt>Optional add-ons</dt><dd class="is-warning">{{ number_format((int) $scopeImpact->firstWhere('key', 'optional')['value']) }}</dd></div>
                    <div><dt>Catalog candidates</dt><dd>{{ number_format((int) $scopeImpact->firstWhere('key', 'catalog_review')['value']) }}</dd></div>
                    <div><dt>Utility / owner scope</dt><dd>{{ number_format((int) $scopeImpact->firstWhere('key', 'utility_owner')['value']) }}</dd></div>
                </dl>
                <a href="#scope-impact" data-nxbom-scroll-target="scope-impact">Review scope impact <span>→</span></a>
            </section>

            <section>
                <h4>Procurement actions</h4>
                <dl>
                    <div><dt>Generated, pricing required</dt><dd class="is-warning">{{ number_format($stats['unpriced']) }}</dd></div>
                    <div><dt>Priced / no active approval</dt><dd>{{ number_format($stats['uncontrolled']) }}</dd></div>
                    <div><dt>Catalog governance actions</dt><dd class="is-warning">{{ number_format($controlIssues['catalog_review']) }}</dd></div>
                    <div><dt>Price source exceptions</dt><dd class="{{ ($controlIssues['missing_price'] + $controlIssues['expired_price'] + $controlIssues['unavailable_price']) > 0 ? 'is-danger' : 'is-positive' }}">{{ number_format($controlIssues['missing_price'] + $controlIssues['expired_price'] + $controlIssues['unavailable_price']) }}</dd></div>
                </dl>
                @if ($canViewCost)
                    <a href="{{ route('companies.price-books.index', $company) }}">Open procurement controls <span>→</span></a>
                @else
                    <a href="{{ route('companies.technical-calculations.index', $company) }}">Open readiness controls <span>→</span></a>
                @endif
            </section>

            <section>
                <h4>Cost exposure</h4>
                <dl>
                    @if ($canViewCost)
                        <div><dt>Priced cost basis</dt><dd>{{ $moneyCompact($stats['total_cost_idr']) }}</dd></div>
                    @endif
                    @if ($canViewSelling)
                        <div><dt>Customer value</dt><dd>{{ $moneyCompact($stats['total_price_idr']) }}</dd></div>
                    @endif
                    @if ($canViewMargin)
                        <div><dt>Gross margin value</dt><dd class="{{ $stats['gross_margin_percent'] >= 10 ? 'is-positive' : 'is-warning' }}">{{ $moneyCompact($stats['gross_margin_idr']) }}</dd></div>
                        <div><dt>Weighted margin</dt><dd class="{{ $stats['gross_margin_percent'] >= 10 ? 'is-positive' : 'is-warning' }}">{{ number_format($stats['gross_margin_percent'], 1) }}%</dd></div>
                    @endif
                    @if (!$canViewCost && !$canViewSelling)
                        <div><dt>Commercial values</dt><dd>Restricted</dd></div>
                    @endif
                </dl>
                @if ($canViewCost)
                    <a href="{{ route('companies.price-books.index', $company) }}">Review price books <span>→</span></a>
                @endif
            </section>

            <section class="nxbomlib-activity">
                <h4>Recent package activity</h4>
                <ul>
                    @forelse ($projects->take(5) as $project)
                        @php
                            $activityBom = $project->billOfMaterial;
                            $activityState = $activityBom?->pricing_status === 'priced' ? 'priced' : ($activityBom ? 'generated' : 'pending');
                            $activityAt = $activityBom?->updated_at ?? $project->technicalCalculation?->updated_at ?? $project->updated_at;
                        @endphp
                        <li>
                            <i class="is-{{ $activityState }}"></i>
                            <span><strong>{{ $project->name }}</strong><small>{{ $activityBom?->bom_number ?: 'Engineering package' }}</small></span>
                            <time>{{ $activityAt?->diffForHumans(short: true) }}</time>
                        </li>
                    @empty
                        <li class="is-empty">No recent BOM activity is available.</li>
                    @endforelse
                </ul>
                <a href="{{ route('companies.boms.index', $company) }}">View all package activity <span>→</span></a>
            </section>
        </aside>
    </div>

    <script type="application/json" data-nxbom-chart-data>@json($chartPayload)</script>
</div>
@endsection
