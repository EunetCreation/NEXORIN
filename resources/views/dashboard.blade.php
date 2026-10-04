@extends('layouts.app')

@section('title', 'Command Center')
@section('page-eyebrow', 'Command center')
@section('page-heading', 'Command Center')
@section('force-theme', 'dark')
@section('body-class', 'page-dashboard-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-dashboard-v6.css') }}?v=012f" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001012.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}" defer></script>
    <script src="{{ asset('js/nexorin-dashboard-v6.js') }}?v=012b" defer></script>
    <script src="{{ asset('js/nexorin-project-flow.js') }}?v=55a" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@php
    $statusLabels = [
        'draft' => 'Draft',
        'survey' => 'Survey',
        'engineering' => 'Engineering',
        'quotation' => 'Quotation',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
    $statusTones = [
        'draft' => 'neutral',
        'survey' => 'info',
        'engineering' => 'warning',
        'quotation' => 'purple',
        'approved' => 'success',
        'completed' => 'success',
        'cancelled' => 'danger',
    ];
    $systemLabels = [
        'on_grid' => 'On-grid',
        'hybrid' => 'Hybrid',
        'off_grid' => 'Off-grid',
        'undecided' => 'Undecided',
    ];
    $quotationTones = [
        'draft' => 'neutral',
        'internal_review' => 'warning',
        'approved' => 'success',
        'sent' => 'info',
        'issued' => 'purple',
        'accepted' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'danger',
        'expired' => 'danger',
        'locked' => 'neutral',
    ];
    $engineeringLabels = [
        'stale' => 'Stale',
        'preliminary' => 'Preliminary',
        'detailed' => 'Detailed',
        'verified' => 'Engineer Verified',
    ];

    $compactNumber = static function (float|int $value, int $decimals = 1): string {
        $absolute = abs((float) $value);
        if ($absolute >= 1_000_000_000_000) return number_format($value / 1_000_000_000_000, $decimals).'T';
        if ($absolute >= 1_000_000_000) return number_format($value / 1_000_000_000, $decimals).'B';
        if ($absolute >= 1_000_000) return number_format($value / 1_000_000, $decimals).'M';
        if ($absolute >= 1_000) return number_format($value / 1_000, $decimals).'K';
        return number_format($value, $decimals === 0 ? 0 : $decimals);
    };
    $compactIdr = static fn (float|int $value): string => 'IDR '.$compactNumber($value, abs((float) $value) >= 1_000_000 ? 1 : 0);
    $firstName = str($currentUser->name)->before(' ')->toString();
    $visibleMetricCount = collect([
        $permissions['projects'],
        $permissions['engineering'],
        $permissions['catalog'],
        $permissions['bom'],
        $permissions['quotations'],
    ])->filter()->count();

    $hasSummaryData = $portfolioProjectCount > 0
        || $engineeringRecordCount > 0
        || $catalogTotal > 0
        || $pricedBoqCount > 0
        || collect($quotationStatusCounts)->sum() > 0;
    $hasWorkQueue = $permissions['projects'] && $workQueue->isNotEmpty();
    $hasQuotationActivity = $permissions['quotations']
        && ($recentQuotations->isNotEmpty() || collect($quotationStatusCounts)->sum() > 0);
    $hasAuthoritativeActions = $permissions['projects']
        || $permissions['engineering']
        || $permissions['catalog']
        || $permissions['quotations'];

    $projectChartRows = collect($projectStatusCounts)
        ->map(fn ($count, $status) => [
            'key' => $status,
            'label' => $statusLabels[$status] ?? str($status)->headline()->toString(),
            'value' => (int) $count,
        ])
        ->values();
    $engineeringChartRows = collect($engineeringStatusCounts)
        ->map(fn ($count, $status) => [
            'key' => $status,
            'label' => $engineeringLabels[$status] ?? str($status)->headline()->toString(),
            'value' => (int) $count,
        ])
        ->values();
    $quotationChartRows = collect($quotationStatusCounts)
        ->map(fn ($count, $status) => [
            'key' => $status,
            'label' => str($status)->replace('_', ' ')->title()->toString(),
            'value' => (int) $count,
        ])
        ->values();
    $systemChartRows = collect($projectSystemCounts)
        ->map(fn ($count, $system) => [
            'key' => $system,
            'label' => $systemLabels[$system] ?? str($system)->replace('_', ' ')->title()->toString(),
            'value' => (int) $count,
        ])
        ->values();

    $projectSystemStates = $systemChartRows->filter(fn (array $row) => $row['value'] > 0)->sortByDesc('value')->values();
    $engineeringStates = $engineeringChartRows->filter(fn (array $row) => $row['value'] > 0)->sortByDesc('value')->values();
    $hasProjectSystemDistribution = $projectSystemStates->count() > 1;
    $hasEngineeringDistribution = $engineeringStates->count() > 1;
    $dominantProjectSystem = $projectSystemStates->first();
    $dominantEngineeringState = $engineeringStates->first();
    $catalogNeedsReview = $catalogTotal > 0 && $catalogApproved < $catalogTotal;

    $chartPayload = [
        'systems' => $systemChartRows,
        'engineering' => $engineeringChartRows,
        'quotations' => $quotationChartRows,
        'mapPoints' => $projectMapPoints,
    ];
@endphp

@section('content')
<div
    class="dashboard-page nx12"
    data-nx12-root
    data-map-url="{{ asset('data/indonesia-country.geojson') }}"
    data-reduced-motion="respect"
>
    <script type="application/json" data-nx12-chart-data>@json($chartPayload)</script>

    <header class="nx12-head" data-premium-reveal>
        <div class="nx12-head-copy">
            <span class="nx12-kicker"><i aria-hidden="true"></i>Solar Engineering Operating System</span>
            <div class="nx12-title-line">
                <h1>Command Center</h1>
                @if ($currentCompany)
                    <span class="nx12-scope-pill">{{ $currentCompany->name }}</span>
                @endif
            </div>
            <p>Operational view across project workflow, stored engineering records, catalog governance, priced BOM/BOQ, and quotation activity for the active company.</p>
        </div>
        <div class="nx12-head-actions">
            <span class="nx12-role-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3l7 3v5c0 4.8-2.9 8.2-7 10-4.1-1.8-7-5.2-7-10V6l7-3z"/><path d="M9.5 12l1.7 1.7 3.5-4"/></svg>
                {{ $roleLabel }}
            </span>
            @if ($currentCompany && $permissions['projects'])
                <a class="nx12-btn nx12-btn--secondary nx12-btn--portfolio" href="{{ route('companies.projects.index', $currentCompany) }}">
                    Project Portfolio
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>
    </header>

    @if (! $currentCompany)
        <section class="nx12-empty nx12-empty--workspace" data-premium-reveal>
            <span class="nx12-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 21V6l8-3 8 3v15"/><path d="M8 9h2M14 9h2M8 13h2M14 13h2M10 21v-4h4v4"/></svg>
            </span>
            <div><span>Company context required</span><h2>Select a company workspace</h2><p>The command center does not aggregate companies into a synthetic fleet view. Open an authorized company to inspect its records.</p></div>
            @if ($currentUser->isSuperAdmin())
                <a class="nx12-btn nx12-btn--primary" href="{{ route('companies.index') }}">Open Companies</a>
            @endif
        </section>
    @else
        @if ($visibleMetricCount > 0 && $hasSummaryData)
            <section class="nx12-metrics" aria-label="Authoritative company summary" data-premium-reveal>
                @if ($permissions['projects'])
                    <article class="nx12-metric nx12-metric--hero" data-premium-card title="Visible non-archived project records, excluding completed and cancelled lifecycle states.">
                        <div class="nx12-metric-top"><span class="nx12-metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M3 19h18"/></svg></span><span>Current records</span></div>
                        <small>Current Projects</small>
                        <strong>{{ number_format($currentProjectsCount) }}</strong>
                        <p>{{ number_format($portfolioProjectCount) }} visible project {{ \Illuminate\Support\Str::plural('record', $portfolioProjectCount) }}</p>
                    </article>
                @endif

                @if ($permissions['engineering'])
                    <article class="nx12-metric" data-premium-card title="Stored Technical Calculation records for visible projects. Coverage is not a portfolio verification score.">
                        <div class="nx12-metric-top"><span class="nx12-metric-icon nx12-tone-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="3" width="14" height="18" rx="3"/><path d="M8 7h8M8 11h3M13 11h3M8 15h3M13 15h3"/></svg></span><span>Stored basis</span></div>
                        <small>Engineering Records</small>
                        <strong>{{ number_format($engineeringRecordCount) }}</strong>
                        <p>{{ $portfolioProjectCount > 0 ? $engineeringRecordCoveragePercent.'% project coverage' : 'No visible project records' }}</p>
                    </article>
                @endif

                @if ($permissions['catalog'])
                    <article class="nx12-metric {{ $catalogNeedsReview ? 'nx12-metric--attention' : ($catalogTotal > 0 && $catalogApprovalPercent === 100 ? 'nx12-metric--positive' : '') }}" data-premium-card title="Approval coverage of active catalog materials visible to this company. This is not total engineering readiness.">
                        <div class="nx12-metric-top"><span class="nx12-metric-icon {{ $catalogNeedsReview ? 'nx12-tone-amber' : 'nx12-tone-green' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4h12a2 2 0 0 1 2 2v12l-4-2-4 2-4-2-4 2V6a2 2 0 0 1 2-2z"/><path d="M8 8h8M8 12h8"/></svg></span><span>Governance</span></div>
                        <small>Catalog Approval</small>
                        <strong>{{ $catalogTotal > 0 ? $catalogApprovalPercent.'%' : 'N/A' }}</strong>
                        <p>
                            @if ($catalogTotal > 0)
                                {{ number_format($catalogApproved) }} of {{ number_format($catalogTotal) }} approved
                            @else
                                No active catalog records
                            @endif
                        </p>
                    </article>
                @endif

                @if ($permissions['bom'])
                    <article class="nx12-metric nx12-metric--commercial" data-premium-card title="Sum of priced BOM/BOQ grand selling totals for visible projects. Kept separate from quotation lifecycle value.">
                        <div class="nx12-metric-top"><span class="nx12-metric-icon nx12-tone-purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h10"/><path d="M18 15v6M15 18h6"/></svg></span><span>Priced BOM/BOQ</span></div>
                        <small>Recorded Selling Value</small>
                        <strong class="nx12-money">{{ $pricedBoqCount > 0 ? $compactIdr($pricedBoqValueIdr) : 'Not Available' }}</strong>
                        <p>{{ $pricedBoqCount > 0 ? $pricedBoqCount.' priced BOM/BOQ '.\Illuminate\Support\Str::plural('record', $pricedBoqCount) : 'No priced BOM/BOQ records' }}</p>
                    </article>
                @endif

                @if ($permissions['quotations'])
                    <article class="nx12-metric" data-premium-card title="Quotation records excluding accepted, rejected, cancelled, and expired lifecycle states.">
                        <div class="nx12-metric-top"><span class="nx12-metric-icon nx12-tone-purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/></svg></span><span>Commercial queue</span></div>
                        <small>Open Quotations</small>
                        <strong>{{ number_format($quotationOpenCount) }}</strong>
                        <p>{{ number_format(collect($quotationStatusCounts)->sum()) }} accessible quotation {{ \Illuminate\Support\Str::plural('record', collect($quotationStatusCounts)->sum()) }}</p>
                    </article>
                @endif
            </section>
        @endif

        @if ($permissions['projects'] && $portfolioProjectCount > 0)
            <section class="nx12-primary-grid" data-premium-reveal>
                <article class="nx12-card nx12-map-card" data-premium-card>
                    <header class="nx12-card-head">
                        <div><span>Geographic context</span><h2>Exact Project Coordinates</h2><p>Stored project latitude and longitude only.</p></div>
                        <div class="nx12-head-stat"><strong>{{ number_format($exactCoordinateCount) }}</strong><span>Exact {{ \Illuminate\Support\Str::plural('coordinate', $exactCoordinateCount) }}</span></div>
                    </header>
                    @if ($exactCoordinateCount > 0)
                        <div class="nx12-map-shell">
                            <div class="nx12-map" data-nx12-map role="img" aria-label="Map of exact project coordinates in Indonesia"></div>
                            <div class="nx12-map-fallback" data-nx12-map-fallback hidden>Map visualization is unavailable. Exact coordinate records remain authoritative in their project records.</div>
                            @if ($exactCoordinateCount > $projectMapPoints->count())
                                <small class="nx12-map-limit">Showing the {{ number_format($projectMapPoints->count()) }} most recently updated exact coordinates of {{ number_format($exactCoordinateCount) }}.</small>
                            @endif
                        </div>
                    @else
                        <div class="nx12-compact-empty"><strong>No exact project coordinates recorded</strong><span>Location labels remain text context until project coordinates are recorded.</span></div>
                    @endif
                </article>

                <article class="nx12-card nx12-pipeline-card">
                    <header class="nx12-card-head nx12-card-head--compact">
                        <div><span>Workflow distribution</span><h2>Project Pipeline</h2><p>Lifecycle distribution of visible project records.</p></div>
                    </header>
                    <div class="nx12-pipeline-list">
                        @foreach ($projectStatusCounts as $status => $count)
                            @continue($count === 0 && $status === 'cancelled')
                            @php $share = $portfolioProjectCount > 0 ? (int) round(($count / $portfolioProjectCount) * 100) : 0; @endphp
                            <div class="nx12-pipeline-row nx12-state-{{ $status }}">
                                <div><span>{{ $statusLabels[$status] ?? str($status)->headline() }}</span><strong>{{ number_format($count) }}</strong></div>
                                <div class="nx12-pipeline-track"><span style="--nx12-share: {{ $share }}%"></span></div>
                                <small>{{ $share }}%</small>
                            </div>
                        @endforeach
                    </div>
                    <div class="nx12-pipeline-total"><span>Visible project records</span><strong>{{ number_format($portfolioProjectCount) }}</strong></div>
                </article>
            </section>

            <section class="nx12-analytics-grid" data-premium-reveal>
                <article class="nx12-card nx12-insight-card {{ $hasProjectSystemDistribution ? 'nx12-insight-card--chart' : 'nx12-insight-card--compact' }}">
                    <header class="nx12-card-head nx12-card-head--compact"><div><span>Portfolio configuration</span><h2>System Mix</h2><p>Recorded project system configuration, not equipment compatibility.</p></div></header>
                    @if ($projectSystemStates->isEmpty())
                        <div class="nx12-compact-empty"><strong>No system configuration recorded</strong><span>System mix appears after project configuration is stored.</span></div>
                    @elseif ($hasProjectSystemDistribution)
                        <div class="nx12-chart nx12-chart--system" data-nx12-system-chart role="img" aria-label="Project count by recorded system configuration"></div>
                    @else
                        @php $systemShare = $portfolioProjectCount > 0 ? (int) round(($dominantProjectSystem['value'] / $portfolioProjectCount) * 100) : 0; @endphp
                        <div class="nx12-single-state nx12-single-state--cyan">
                            <span class="nx12-single-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M3 19h18"/></svg></span>
                            <span><strong>{{ number_format($dominantProjectSystem['value']) }} {{ $dominantProjectSystem['label'] }} {{ \Illuminate\Support\Str::plural('project', $dominantProjectSystem['value']) }}</strong><small>All visible projects share this recorded configuration.</small></span>
                            <em>{{ $systemShare }}%</em>
                        </div>
                    @endif
                </article>

                @if ($permissions['engineering'])
                    <article class="nx12-card nx12-insight-card nx12-insight-card--engineering {{ $hasEngineeringDistribution ? 'nx12-insight-card--chart' : 'nx12-insight-card--compact' }}" data-premium-card>
                        <header class="nx12-card-head nx12-card-head--compact"><div><span>Engineering basis</span><h2>Calculation Record Status</h2><p>Stored calculation status with current legacy stale detection.</p></div></header>
                        @if ($engineeringStates->isEmpty())
                            <div class="nx12-compact-empty"><strong>No calculated technical records</strong><span>Engineering status appears after a calculation is stored.</span></div>
                        @elseif ($hasEngineeringDistribution)
                            <div class="nx12-chart nx12-chart--donut" data-nx12-engineering-chart role="img" aria-label="Technical calculation records by result status"></div>
                        @else
                            @php $engineeringShare = $engineeringRecordCount > 0 ? (int) round(($dominantEngineeringState['value'] / $engineeringRecordCount) * 100) : 0; @endphp
                            <div class="nx12-single-state nx12-single-state--{{ $dominantEngineeringState['key'] }}">
                                <span class="nx12-single-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="3" width="14" height="18" rx="3"/><path d="M8 7h8M8 11h3M13 11h3M8 15h3M13 15h3"/></svg></span>
                                <span><strong>{{ number_format($dominantEngineeringState['value']) }} {{ $dominantEngineeringState['label'] }} {{ \Illuminate\Support\Str::plural('record', $dominantEngineeringState['value']) }}</strong><small>All stored calculations currently share this record status.</small></span>
                                <em>{{ $engineeringShare }}%</em>
                            </div>
                        @endif
                    </article>
                @endif
            </section>
        @elseif ($permissions['projects'])
            <section class="nx12-empty nx12-empty--projects" data-premium-reveal>
                <span class="nx12-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M3 19h18"/></svg></span>
                <div><span>Project workspace is empty</span><h2>Start with the first controlled project record</h2><p>The command center stays compact until real project, engineering, catalog, and commercial records exist.</p></div>
                @if ($permissions['create_project'])<a class="nx12-btn nx12-btn--primary" href="{{ route('companies.projects.create', $currentCompany) }}">Create Project</a>@endif
            </section>
        @endif

        @if ($hasWorkQueue || $hasQuotationActivity || $hasAuthoritativeActions)
        <section class="nx12-lower-grid {{ $hasWorkQueue ? '' : 'nx12-lower-grid--rail-only' }} {{ $hasWorkQueue && !$hasQuotationActivity && $hasAuthoritativeActions ? 'nx12-lower-grid--paired' : '' }}" data-premium-reveal>
            @if ($hasWorkQueue)
                <article class="nx12-card nx12-queue-card">
                    <header class="nx12-card-head">
                        <div><span>Continue work</span><h2>Project Work Queue</h2><p>Stored module coverage and the next unresolved workflow step.</p></div>
                        <span class="nx12-count-chip">{{ $workQueue->count() }} recent</span>
                    </header>
                    <div class="nx12-table-wrap">
                        <table class="nx12-table">
                            <thead><tr><th>Project</th><th>Context</th><th>Module Coverage</th><th>Next Workflow Action</th><th><span class="sr-only">Open</span></th></tr></thead>
                            <tbody>
                            @foreach ($workQueue as $item)
                                @php $project = $item['project']; $next = $item['next_step']; @endphp
                                <tr data-premium-row data-row-href="{{ route('companies.projects.show', [$currentCompany, $project]) }}" tabindex="0" data-project-number="{{ $project->project_number }}" data-project-name="{{ $project->name }}">
                                    <td><div class="nx12-project-cell"><span class="nx12-project-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 18h18"/></svg></span><span><strong>{{ $project->name }}</strong><small>{{ $project->customer?->name ?? 'Customer not recorded' }} · {{ $project->updated_at?->diffForHumans() ?? 'Update time not recorded' }}</small></span></div></td>
                                    <td><div class="nx12-context-cell"><span class="nx12-status nx12-status--{{ $statusTones[$project->status] ?? 'neutral' }}">{{ $statusLabels[$project->status] ?? str($project->status)->headline() }}</span><small>{{ $systemLabels[$project->proposed_system ?? 'undecided'] ?? str($project->proposed_system)->headline() }}</small></div></td>
                                    <td><div class="nx12-coverage"><div><span style="--nx12-coverage: {{ $item['module_coverage_percent'] }}%"></span></div><strong>{{ $item['module_coverage_percent'] }}%</strong></div></td>
                                    <td><strong class="nx12-next">{{ $next['label'] ?? 'Review project record' }}</strong><small class="{{ $item['warning_count'] > 0 ? 'nx12-warning-copy' : '' }}">{{ $item['warning_count'] > 0 ? $item['warning_count'].' recorded warning'.($item['warning_count'] === 1 ? '' : 's') : ($next['caption'] ?? 'No unresolved workflow module recorded') }}</small></td>
                                    <td><a class="nx12-row-action" href="{{ route('companies.projects.show', [$currentCompany, $project]) }}" aria-label="Open {{ $project->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @endif

            <aside class="nx12-rail">
                @if ($hasQuotationActivity)
                    <article class="nx12-card nx12-quote-card" data-premium-card>
                        <header class="nx12-card-head nx12-card-head--compact"><div><span>Commercial activity</span><h2>Recent Quotations</h2><p>Latest accessible quotation records and lifecycle state.</p></div>@if ($currentCompany)<a class="nx12-icon-link" href="{{ route('companies.quotations.index', $currentCompany) }}" aria-label="Open quotations">↗</a>@endif</header>
                        <div class="nx12-quote-list">
                            @forelse ($recentQuotations as $quotation)
                                <a href="{{ route('companies.quotations.show', [$currentCompany, $quotation->project]) }}" class="nx12-quote-item">
                                    <span class="nx12-quote-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/></svg></span>
                                    <span><strong>{{ $quotation->quote_number ?: 'Draft quotation' }}</strong><small>{{ $quotation->project?->name ?? 'Project not available' }} · {{ $quotation->updated_at?->diffForHumans() ?? 'Update time not recorded' }}</small></span>
                                    <em class="nx12-status nx12-status--{{ $quotationTones[$quotation->status] ?? 'neutral' }}">{{ $quotation->status_label }}</em>
                                </a>
                            @empty
                                <div class="nx12-compact-empty nx12-compact-empty--rail"><strong>No quotation activity</strong><span>Accessible quotation records will appear here when created.</span></div>
                            @endforelse
                        </div>
                        @if ($quotationStatusCounts->isNotEmpty())
                            <div class="nx12-mini-chart" data-nx12-quotation-chart role="img" aria-label="Quotation count by lifecycle status"></div>
                        @endif
                    </article>
                @endif

                @if ($hasAuthoritativeActions)
                <article class="nx12-card nx12-actions-card">
                    <header class="nx12-card-head nx12-card-head--compact"><div><span>Next workspaces</span><h2>Continue Work</h2><p>Open permitted workspaces for the active company.</p></div></header>
                    <div class="nx12-action-list">
                        @if ($permissions['projects'])<a href="{{ route('companies.projects.index', $currentCompany) }}"><span>Project Portfolio</span><small>Continue visible project records</small><b>→</b></a>@endif
                        @if ($permissions['engineering'])<a href="{{ route('companies.technical-calculations.index', $currentCompany) }}"><span>Technical Calculations</span><small>Review stored engineering basis</small><b>→</b></a>@endif
                        @if ($permissions['catalog'])<a href="{{ route('companies.materials.index', $currentCompany) }}"><span>Material Catalog</span><small>Review approval and evidence</small><b>→</b></a>@endif
                        @if ($permissions['quotations'])<a href="{{ route('companies.quotations.index', $currentCompany) }}"><span>Quotations</span><small>Continue commercial workflow</small><b>→</b></a>@endif
                    </div>
                </article>
                @endif
            </aside>
        </section>
        @endif

        @if ($visibleMetricCount > 0 && $hasSummaryData)
            <details class="nx12-basis" data-premium-reveal>
                <summary>
                    <span><i aria-hidden="true"></i><b>Data basis</b><small>Company-scoped · permission-scoped · stored records</small></span>
                    <em>View methodology</em>
                </summary>
                <div class="nx12-basis-grid">
                    @if ($permissions['projects'])<div><i class="is-cyan"></i><span><strong>Projects</strong><small>Visible company project records; archived records excluded.</small></span></div>@endif
                    @if ($permissions['engineering'])<div><i class="is-blue"></i><span><strong>Engineering</strong><small>Stored Technical Calculation states with legacy stale detection.</small></span></div>@endif
                    @if ($permissions['catalog'])<div><i class="is-green"></i><span><strong>Catalog</strong><small>Approval coverage of visible active catalog records.</small></span></div>@endif
                    @if ($permissions['bom'] || $permissions['quotations'])<div><i class="is-purple"></i><span><strong>Commercial</strong><small>Priced BOM/BOQ and quotation lifecycle remain separate domains.</small></span></div>@endif
                </div>
            </details>
        @endif

        @if ($visibleMetricCount === 0)
            <section class="nx12-empty nx12-empty--access" data-premium-reveal>
                <span class="nx12-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                <div><span>Access scope</span><h2>No command-center modules are available for this role</h2><p>Nothing is inferred or disclosed from domains that the active role cannot access. Ask a company administrator if additional access is required.</p></div>
            </section>
        @endif
    @endif
</div>
@endsection
