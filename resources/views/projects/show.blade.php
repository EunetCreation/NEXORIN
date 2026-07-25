@extends('layouts.app')

@section('title', $project->name)
@section('page-eyebrow', 'Project command center')
@section('page-heading', 'Project Cockpit')

@push('styles')
    <link href="{{ asset('css/nexorin-cockpit.css') }}?v=55a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-project-flow.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-cockpit.js') }}?v=55a" defer></script>
    <script src="{{ asset('js/nexorin-project-flow.js') }}?v=55a" defer></script>
@endpush

@section('content')
    @php
        $engineering = $cockpit['engineering_summary'];
        $commercial = $cockpit['commercial_summary'];
        $financial = $cockpit['financial_summary'];
        $health = $cockpit['health'];
        $nextStep = $cockpit['next_step'];
        $warningCounts = $cockpit['warning_counts'];
        $warningRecords = $cockpit['warning_records'];
        $charts = $cockpit['charts'];
        $insights = $cockpit['insights'];
        $warningTotal = count($warningRecords);
        $criticalCount = (int) ($warningCounts['critical'] ?? 0);
        $warningCount = (int) ($warningCounts['warning'] ?? 0);
        $cautionCount = (int) ($warningCounts['caution'] ?? 0);
        $infoCount = (int) ($warningCounts['info'] ?? 0);
        $reviewItemCount = $criticalCount + $warningCount + $cautionCount;
        $reviewBreakdown = collect([
            $criticalCount > 0 ? $criticalCount.' critical' : null,
            $warningCount > 0 ? $warningCount.' warning'.($warningCount === 1 ? '' : 's') : null,
            $cautionCount > 0 ? $cautionCount.' caution'.($cautionCount === 1 ? '' : 's') : null,
        ])->filter()->implode(' · ');

        $engineeringWarningSources = ['system_recommendation', 'technical_calculation', 'roof_site_survey', 'technical_override'];
        $engineeringWarnings = collect($warningRecords)
            ->filter(fn (array $warning): bool => in_array($warning['source'] ?? '', $engineeringWarningSources, true));
        $engineeringCriticalCount = $engineeringWarnings->where('severity', 'critical')->count();
        $engineeringWarningCount = $engineeringWarnings->where('severity', 'warning')->count();
        $engineeringCautionCount = $engineeringWarnings->where('severity', 'caution')->count();
        $engineeringReviewCount = $engineeringCriticalCount + $engineeringWarningCount + $engineeringCautionCount;
        $engineeringReviewBreakdown = collect([
            $engineeringCriticalCount > 0 ? $engineeringCriticalCount.' critical' : null,
            $engineeringWarningCount > 0 ? $engineeringWarningCount.' warning'.($engineeringWarningCount === 1 ? '' : 's') : null,
            $engineeringCautionCount > 0 ? $engineeringCautionCount.' caution'.($engineeringCautionCount === 1 ? '' : 's') : null,
        ])->filter()->implode(' · ');

        $releaseReady = (bool) ($releaseGate['ready'] ?? false);
        $releaseIssueCount = (int) ($releaseGate['issue_count'] ?? 0);
        $hasEngineering = (bool) $project->technicalCalculation?->calculated_at;
        $hasCommercial = (bool) $project->billOfMaterial;
        $hasFinancial = (bool) $project->financialAnalysis;
        $hasQuotation = (bool) $project->quotation;
        $hasAnyOutput = $hasEngineering || $hasCommercial || $hasFinancial || $hasQuotation;
        $sldRevision = $project->sldRevisions->first(fn ($revision) => $revision->status !== 'superseded');

        $workflowStateLabels = [
            'complete' => 'Complete',
            'current' => 'In progress',
            'missing' => 'Not started',
            'warning' => 'Review needed',
            'locked' => 'Finalized',
        ];

        $moduleRoutes = [
            'project' => $canUpdateProject && ! $project->isArchived()
                ? route('companies.projects.edit', [$company, $project])
                : route('companies.projects.show', [$company, $project]),
            'load' => $canViewLoadAnalysis ? route('companies.load-analyses.show', [$company, $project]) : null,
            'solar' => $canViewLocationSolar ? route('companies.location-solar.show', [$company, $project]) : null,
            'roof' => $canViewRoofSite ? route('companies.roof-site.show', [$company, $project]) : null,
            'recommendation' => $canViewRecommendation ? route('companies.recommendations.show', [$company, $project]) : null,
            'technical' => $canViewTechnicalCalculation ? route('companies.technical-calculations.show', [$company, $project]) : null,
            'sld' => $canViewTechnicalCalculation ? route('companies.projects.sld.show', [$company, $project]) : null,
            'bom' => $canViewBom ? route('companies.boms.show', [$company, $project]) : null,
            'financial' => $canViewFinancialAnalysis ? route('companies.financial-analyses.show', [$company, $project]) : null,
            'quotation' => $canViewQuotation ? route('companies.quotations.show', [$company, $project]) : null,
        ];

        $warningActionMap = [
            'system_recommendation' => ['url' => $moduleRoutes['recommendation'], 'label' => 'Open recommendation'],
            'technical_calculation' => ['url' => $moduleRoutes['technical'], 'label' => 'Open technical calculation'],
            'roof_site_survey' => ['url' => $moduleRoutes['roof'], 'label' => 'Open roof survey'],
            'technical_override' => ['url' => $moduleRoutes['technical'], 'label' => 'Review overrides'],
            'bom_boq' => ['url' => $moduleRoutes['bom'], 'label' => 'Open BOM/BOQ'],
            'financial_analysis' => ['url' => $moduleRoutes['financial'], 'label' => 'Open financial analysis'],
            'quotation' => ['url' => $moduleRoutes['quotation'], 'label' => 'Open quotation'],
        ];

        $nextStepUrl = $nextStep ? ($moduleRoutes[$nextStep['key']] ?? null) : null;
        $nextActionTitle = $nextStep['label'] ?? ($reviewItemCount > 0 ? 'Review project warnings' : ($releaseReady ? 'Client release is ready' : 'Review client release gate'));
        $nextActionCopy = $nextStep
            ? $nextStep['caption'].' · '.$nextStep['status']
            : ($reviewItemCount > 0
                ? ($reviewBreakdown ?: $reviewItemCount.' review items').' remain before this project can move cleanly toward release.'
                : ($releaseReady
                    ? 'All release checks are clear and the final proposal is available.'
                    : 'Module completion is separate from client release approval. Review the remaining gate checks.'));
        $nextActionTab = $nextStep ? 'workflow' : ($reviewItemCount > 0 ? 'warnings' : 'release');

        $engineeringState = ! $hasEngineering
            ? 'pending'
            : ($engineeringCriticalCount > 0
                ? 'critical'
                : ($engineeringReviewCount > 0 ? 'warning' : 'ready'));
        $engineeringLabel = ! $hasEngineering
            ? 'Not calculated'
            : ($engineeringReviewCount > 0
                ? $engineeringReviewCount.' review item'.($engineeringReviewCount === 1 ? '' : 's')
                : 'Review clear');
        $engineeringCaption = ! $hasEngineering
            ? 'Technical results unavailable'
            : ($engineeringReviewCount > 0
                ? $engineeringReviewBreakdown
                : 'No active engineering review signal');
        $technicalStatusLabel = str_contains(strtolower((string) $engineering['status']), 'verified')
            ? 'Technical calculation verified'
            : $engineering['status'];
        $technicalStatusTone = str_contains(strtolower((string) $engineering['status']), 'verified')
            ? 'ready'
            : (str_contains(strtolower((string) $engineering['status']), 'preliminary') ? 'info' : 'progress');
        $releaseLabel = $releaseReady
            ? 'Ready'
            : ($releaseIssueCount > 0
                ? $releaseIssueCount.' release blocker'.($releaseIssueCount === 1 ? '' : 's')
                : 'Approval pending');
        $projectLocation = collect([$project->city, $project->province])->filter()->implode(', ') ?: 'Location not specified';
    @endphp

    <div class="nx-cockpit" data-cockpit-root data-project-number="{{ $project->project_number }}" data-project-name="{{ $project->name }}">
        <section class="nx-cockpit-hero" data-cockpit-glow>
            <div class="nx-cockpit-hero-ambient" aria-hidden="true"></div>

            <div class="nx-cockpit-hero-copy">
                <a class="nx-cockpit-back" href="{{ route('companies.projects.index', $company) }}">
                    @include('projects.partials.cockpit-icon', ['name' => 'back', 'size' => 16])
                    Project portfolio
                </a>

                <span class="nx-cockpit-eyebrow">{{ $project->project_number }} · {{ $project->customer->name }}</span>
                <h2>{{ $project->name }}</h2>
                <p>{{ $projectLocation }} · {{ $project->building_type_label }} · {{ $project->proposed_system_label }}</p>

                <div class="nx-cockpit-hero-badges">
                    <span class="nx-hero-badge">Updated {{ $project->updated_at?->diffForHumans() ?? 'recently' }}</span>
                    @if ($hasQuotation)
                        <span class="nx-hero-badge is-info">Quotation {{ $commercial['quotation_status'] }}</span>
                    @endif
                    @if ($engineering['has_overrides'])
                        <span class="nx-hero-badge is-info">{{ $engineering['override_count'] }} active override{{ $engineering['override_count'] === 1 ? '' : 's' }}</span>
                    @endif
                    @if ($project->isArchived())
                        <span class="nx-hero-badge is-critical">Archived</span>
                    @endif
                </div>

                <div class="nx-cockpit-hero-actions">
                    @if ($canUpdateProject && ! $project->isArchived())
                        <a class="nx-cockpit-button is-primary" href="{{ route('companies.projects.edit', [$company, $project]) }}">
                            @include('projects.partials.cockpit-icon', ['name' => 'edit', 'size' => 17])
                            Edit project
                        </a>
                    @endif
                    <button class="nx-cockpit-button is-ghost" type="button" data-cockpit-open-tab="workflow">
                        View workflow
                        @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 16])
                    </button>
                </div>
            </div>

            <div class="nx-cockpit-status-matrix" aria-label="Project status dimensions">
                <article class="nx-status-dimension is-lifecycle">
                    <span>Project lifecycle</span>
                    <strong>{{ $project->status_label }}</strong>
                    <small>Manual business status</small>
                </article>
                <article class="nx-status-dimension is-modules">
                    <span>Module completion</span>
                    <strong>{{ $cockpit['completed_steps'] }} / {{ $cockpit['total_steps'] }}</strong>
                    <small>{{ $cockpit['completion_percent'] }}% complete</small>
                    <i><b data-cockpit-progress="{{ $cockpit['completion_percent'] }}"></b></i>
                </article>
                <article class="nx-status-dimension is-engineering is-{{ $engineeringState }}">
                    <span>Engineering review</span>
                    <strong>{{ $engineeringLabel }}</strong>
                    <small>{{ $engineeringCaption }}</small>
                </article>
                <article class="nx-status-dimension is-release {{ $releaseReady ? 'is-ready' : 'is-blocked' }}">
                    <span>Client release</span>
                    <strong>{{ $releaseLabel }}</strong>
                    <small>{{ $releaseGate['status'] ?? 'Review required' }}</small>
                </article>
            </div>
        </section>

        <section class="nx-next-command" data-cockpit-glow>
            <div class="nx-next-command-icon">
                @include('projects.partials.cockpit-icon', ['name' => $nextStep['key'] ?? ($engineeringReviewCount > 0 ? 'warning' : 'release'), 'size' => 24])
            </div>
            <div class="nx-next-command-copy">
                <span>Recommended next action</span>
                <strong>{{ $nextActionTitle }}</strong>
                <p>{{ $nextActionCopy }}</p>
            </div>
            <div class="nx-next-command-meta">
                @if ($nextStep)
                    <span class="nx-state-pill is-{{ $nextStep['state'] === 'warning' ? 'warning' : 'progress' }}">{{ $workflowStateLabels[$nextStep['state']] ?? ucfirst($nextStep['state']) }}</span>
                @elseif ($reviewItemCount > 0)
                    <span class="nx-state-pill is-warning">{{ $reviewItemCount }} review item{{ $reviewItemCount === 1 ? '' : 's' }}</span>
                @elseif ($releaseReady)
                    <span class="nx-state-pill is-ready">Release ready</span>
                @else
                    <span class="nx-state-pill is-warning">Gate review</span>
                @endif
            </div>
            <div class="nx-next-command-actions">
                @if ($nextStepUrl)
                    <a class="nx-cockpit-button is-primary" href="{{ $nextStepUrl }}"
                        data-project-module="{{ $nextStep['key'] }}"
                        data-project-module-state="{{ $workflowStateLabels[$nextStep['state']] ?? ucfirst($nextStep['state']) }}"
                        data-project-module-metric="{{ $nextStep['metric'] }}">
                        Continue to {{ $nextStep['label'] }}
                        @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 16])
                    </a>
                @else
                    <button class="nx-cockpit-button is-primary" type="button" data-cockpit-open-tab="{{ $nextActionTab }}">
                        Open {{ ucfirst($nextActionTab) }}
                        @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 16])
                    </button>
                @endif
            </div>
        </section>

        <nav class="nx-cockpit-tabs" role="tablist" aria-label="Project cockpit sections">
            <button type="button" role="tab" aria-selected="true" data-cockpit-tab="overview">
                <span>Overview</span>
            </button>
            <button type="button" role="tab" aria-selected="false" data-cockpit-tab="workflow">
                <span>Workflow</span><b>{{ $cockpit['completed_steps'] }}/{{ $cockpit['total_steps'] }}</b>
            </button>
            <button type="button" role="tab" aria-selected="false" data-cockpit-tab="engineering">
                <span>Engineering</span>
            </button>
            <button type="button" role="tab" aria-selected="false" data-cockpit-tab="commercial">
                <span>Commercial</span>
            </button>
            <button type="button" role="tab" aria-selected="false" data-cockpit-tab="warnings">
                <span>Warnings</span><b>{{ $reviewItemCount }}</b>
            </button>
            <button type="button" role="tab" aria-selected="false" data-cockpit-tab="release">
                <span>Release</span><b class="{{ $releaseReady ? 'is-ready' : 'is-warning' }}">{{ $releaseReady ? 'Ready' : ($releaseIssueCount > 0 ? $releaseIssueCount : 'Approval') }}</b>
            </button>
            <button type="button" role="tab" aria-selected="false" data-cockpit-tab="reports">
                <span>Reports & Audit</span>
            </button>
        </nav>

        <div class="nx-cockpit-panels">
            <section class="nx-cockpit-panel is-active" role="tabpanel" data-cockpit-panel="overview">
                <div class="nx-overview-grid">
                    <section class="nx-cockpit-section-card nx-decision-snapshot" data-cockpit-glow>
                        <header class="nx-section-heading">
                            <div>
                                <span class="nx-section-kicker">Decision snapshot</span>
                                <h3>Project outputs at a glance</h3>
                                <p>Core engineering, commercial, and financial values appear as their source modules become available.</p>
                            </div>
                        </header>

                        @if ($hasAnyOutput)
                            <div class="nx-kpi-grid">
                                <article class="nx-kpi-card is-engineering">
                                    <span>Installed PV</span>
                                    <strong>{{ $engineering['installed_pv_kw'] }}</strong>
                                    <small>{{ $engineering['system_type'] }} system</small>
                                </article>
                                <article class="nx-kpi-card is-energy">
                                    <span>Annual energy</span>
                                    <strong>{{ $engineering['annual_energy_kwh'] }}</strong>
                                    <small>{{ $charts['energy']['specific_yield'] }} specific yield</small>
                                </article>
                                <article class="nx-kpi-card is-commercial">
                                    <span>Project value</span>
                                    <strong>{{ $commercial['grand_price'] }}</strong>
                                    <small>{{ $commercial['pricing_status'] }}</small>
                                </article>
                                <article class="nx-kpi-card is-financial">
                                    <span>Simple payback</span>
                                    <strong>{{ $financial['payback'] }}</strong>
                                    <small>{{ $financial['status'] }}</small>
                                </article>
                                <article class="nx-kpi-card is-financial">
                                    <span>IRR</span>
                                    <strong>{{ $financial['irr'] }}</strong>
                                    <small>NPV {{ $financial['npv'] }}</small>
                                </article>
                                <article class="nx-kpi-card is-quotation">
                                    <span>Quotation</span>
                                    <strong>{{ $commercial['quotation_status'] }}</strong>
                                    <small>{{ $commercial['quote_number'] }}</small>
                                </article>
                            </div>
                        @else
                            <div class="nx-cockpit-empty">
                                <span class="nx-cockpit-empty-icon">@include('projects.partials.cockpit-icon', ['name' => 'technical', 'size' => 25])</span>
                                <div>
                                    <strong>Decision outputs will appear as the workflow progresses</strong>
                                    <p>Complete load, solar, roof, recommendation, technical, pricing, and financial modules to activate this command snapshot.</p>
                                    @if ($nextStepUrl)
                                        <a href="{{ $nextStepUrl }}"
                                            data-project-module="{{ $nextStep['key'] }}"
                                            data-project-module-state="{{ $workflowStateLabels[$nextStep['state']] ?? ucfirst($nextStep['state']) }}"
                                            data-project-module-metric="{{ $nextStep['metric'] }}">Continue to {{ $nextStep['label'] }} @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 15])</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </section>

                    <section class="nx-cockpit-section-card nx-attention-board" data-cockpit-glow>
                        <header class="nx-section-heading is-compact">
                            <div>
                                <span class="nx-section-kicker">Attention board</span>
                                <h3>Signals that need a decision</h3>
                            </div>
                            <button type="button" class="nx-text-button" data-cockpit-open-tab="warnings">Open warnings</button>
                        </header>

                        <div class="nx-insight-list">
                            @foreach (array_slice($insights, 0, 3) as $insight)
                                <article class="nx-insight-card is-{{ $insight['state'] }}">
                                    <span>{{ $insight['metric'] }}</span>
                                    <strong>{{ $insight['title'] }}</strong>
                                    <p>{{ $insight['message'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </div>

                <div class="nx-overview-lower-grid">
                    <section class="nx-cockpit-section-card" data-cockpit-glow>
                        <header class="nx-section-heading is-compact">
                            <div>
                                <span class="nx-section-kicker">Workflow pulse</span>
                                <h3>Module position</h3>
                            </div>
                            <button type="button" class="nx-text-button" data-cockpit-open-tab="workflow">View all modules</button>
                        </header>
                        <div class="nx-module-pulse">
                            @foreach ($cockpit['steps'] as $index => $step)
                                <span class="is-{{ $step['state'] }}" title="{{ $step['label'] }} · {{ $workflowStateLabels[$step['state']] ?? ucfirst($step['state']) }}">
                                    <b>{{ $index + 1 }}</b><small>{{ $step['label'] }}</small>
                                </span>
                            @endforeach
                        </div>
                    </section>

                    <section class="nx-cockpit-section-card" data-cockpit-glow>
                        <header class="nx-section-heading is-compact">
                            <div>
                                <span class="nx-section-kicker">Recent activity</span>
                                <h3>Latest project events</h3>
                            </div>
                        </header>
                        <div class="nx-activity-list">
                            @forelse ($project->statusHistories->take(3) as $history)
                                <article>
                                    <span></span>
                                    <div>
                                        <strong>{{ $history->from_status ? ucfirst($history->from_status).' → ' : '' }}{{ ucfirst($history->to_status) }}</strong>
                                        <p>{{ $history->note ?: 'No status note was provided.' }}</p>
                                        <small>{{ $history->changedBy?->name ?? 'System' }} · {{ $history->changed_at?->diffForHumans() }}</small>
                                    </div>
                                </article>
                            @empty
                                <div class="nx-cockpit-empty is-compact"><div><strong>No activity yet</strong><p>Project activity will appear here.</p></div></div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </section>

            <section class="nx-cockpit-panel" role="tabpanel" hidden data-cockpit-panel="workflow">
                <section class="nx-cockpit-section-card" data-cockpit-glow>
                    <header class="nx-section-heading">
                        <div>
                            <span class="nx-section-kicker">Nine-module workflow</span>
                            <h3>Engineering and commercial command track</h3>
                            <p>Each module keeps its original route, permission, and business logic. The cockpit only clarifies where the project stands.</p>
                        </div>
                        <span class="nx-state-pill is-{{ $health['state'] === 'ready' ? 'ready' : ($health['state'] === 'warning' ? 'warning' : 'progress') }}">{{ $health['description'] }}</span>
                    </header>

                    <div class="nx-workflow-grid">
                        @foreach ($cockpit['steps'] as $index => $step)
                            @php $stepUrl = $moduleRoutes[$step['key']] ?? null; @endphp
                            @if ($stepUrl)
                                <a class="nx-workflow-card is-{{ $step['state'] }}" href="{{ $stepUrl }}" data-cockpit-glow
                                    data-project-module="{{ $step['key'] }}"
                                    data-project-module-index="{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}"
                                    data-project-module-state="{{ $workflowStateLabels[$step['state']] ?? ucfirst($step['state']) }}"
                                    data-project-module-metric="{{ $step['metric'] }}">
                            @else
                                <article class="nx-workflow-card is-{{ $step['state'] }} is-disabled" aria-disabled="true">
                            @endif
                                    <span class="nx-workflow-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="nx-workflow-icon">@include('projects.partials.cockpit-icon', ['name' => $step['key'], 'size' => 21])</span>
                                    <div class="nx-workflow-copy">
                                        <strong>{{ $step['label'] }}</strong>
                                        <p>{{ $step['caption'] }}</p>
                                    </div>
                                    <span class="nx-workflow-state">{{ $workflowStateLabels[$step['state']] ?? ucfirst($step['state']) }}</span>
                                    <small>{{ $step['metric'] }}</small>
                                    @if ($stepUrl)
                                        <span class="nx-workflow-arrow">@include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 16])</span>
                                    @endif
                            @if ($stepUrl)
                                </a>
                            @else
                                </article>
                            @endif
                        @endforeach
                    </div>
                </section>
            </section>

            <section class="nx-cockpit-panel" role="tabpanel" hidden data-cockpit-panel="engineering">
                @if ($hasEngineering)
                    <section class="nx-cockpit-section-card" data-cockpit-glow>
                        <header class="nx-section-heading">
                            <div class="nx-section-heading-icon nx-tone-engineering">@include('projects.partials.cockpit-icon', ['name' => 'technical'])</div>
                            <div>
                                <span class="nx-section-kicker">Engineering intelligence</span>
                                <h3>Technical result command board</h3>
                                <p>Design capacity, energy yield, ratio position, cable performance, and active review signals.</p>
                            </div>
                            <span class="nx-state-pill is-{{ $technicalStatusTone }}">{{ $technicalStatusLabel }}</span>
                            @if ($moduleRoutes['sld'])
                                <a class="nx-section-action" href="{{ $moduleRoutes['sld'] }}">Open SLD @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 14])</a>
                            @endif
                        </header>

                        @if ($engineeringReviewCount > 0)
                            <div class="nx-review-ribbon is-{{ $engineeringCriticalCount > 0 ? 'critical' : 'warning' }}">
                                <span class="nx-review-ribbon-icon">@include('projects.partials.cockpit-icon', ['name' => 'warning', 'size' => 17])</span>
                                <div>
                                    <strong>{{ $engineeringReviewCount }} engineering review item{{ $engineeringReviewCount === 1 ? '' : 's' }}</strong>
                                    <small>{{ $engineeringReviewBreakdown }}</small>
                                </div>
                                <button type="button" data-cockpit-open-tab="warnings">Review warnings @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 14])</button>
                            </div>
                        @endif

                        <div class="nx-kpi-grid nx-kpi-grid-engineering">
                            <article class="nx-kpi-card is-engineering"><span>Installed PV</span><strong>{{ $engineering['installed_pv_kw'] }}</strong><small>Required {{ $engineering['required_pv_kw'] }}</small></article>
                            <article class="nx-kpi-card is-energy"><span>Annual energy</span><strong>{{ $engineering['annual_energy_kwh'] }}</strong><small>{{ $charts['energy']['specific_yield'] }}</small></article>
                            <article class="nx-kpi-card is-engineering"><span>DC/AC ratio</span><strong>{{ $engineering['dc_ac_ratio'] }}</strong><small>Recommended 0.95–1.35</small></article>
                            <article class="nx-kpi-card is-engineering"><span>PV modules</span><strong>{{ $engineering['module_count'] }}</strong><small>{{ $engineering['inverter_count'] }} inverter</small></article>
                        </div>
                    </section>

                    <div class="nx-engineering-grid">
                        <section class="nx-cockpit-section-card" data-cockpit-glow>
                            <header class="nx-section-heading is-compact"><div><span class="nx-section-kicker">Technical gauges</span><h3>Design benchmarks</h3></div></header>
                            <div class="nx-gauge-list">
                                @foreach ($charts['technical'] as $metric)
                                    <article class="nx-gauge is-{{ $metric['state'] }}">
                                        <div><strong>{{ $metric['label'] }}</strong><span>{{ $metric['value'] }}</span></div>
                                        <i><b style="--gauge-width: {{ $metric['percent'] }}%"></b></i>
                                        <p>{{ $metric['note'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </section>

                        <section class="nx-cockpit-section-card" data-cockpit-glow>
                            <header class="nx-section-heading is-compact"><div><span class="nx-section-kicker">Engineering details</span><h3>Source values</h3></div></header>
                            <dl class="nx-detail-list">
                                <div><dt>System type</dt><dd>{{ $engineering['system_type'] }}</dd></div>
                                <div><dt>Daily load</dt><dd>{{ $engineering['daily_load_kwh'] }}</dd></div>
                                <div><dt>Usable roof area</dt><dd>{{ $engineering['usable_roof_area_m2'] }}</dd></div>
                                <div><dt>Calculation status</dt><dd>{{ $engineering['status'] }}</dd></div>
                                <div><dt>SLD status</dt><dd>{{ $sldRevision?->status_label ?? 'Not generated' }}</dd></div>
                                <div><dt>Active overrides</dt><dd>{{ $engineering['has_overrides'] ? $engineering['override_count'] : 'None' }}</dd></div>
                                <div><dt>PV design fit</dt><dd>{{ $charts['energy']['pv_utilization_label'] }}</dd></div>
                            </dl>
                        </section>
                    </div>
                @else
                    <section class="nx-cockpit-section-card" data-cockpit-glow>
                        <div class="nx-cockpit-empty is-large">
                            <span class="nx-cockpit-empty-icon">@include('projects.partials.cockpit-icon', ['name' => 'technical', 'size' => 28])</span>
                            <div>
                                <span class="nx-section-kicker">Engineering workspace</span>
                                <strong>Technical results are not available yet</strong>
                                <p>Complete the upstream project modules, then run Technical Calculation to generate PV sizing, inverter, string, cable, battery, energy, and warning outputs.</p>
                                @if ($moduleRoutes['technical'])
                                    <a href="{{ $moduleRoutes['technical'] }}">Open technical calculation @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 15])</a>
                                @endif
                            </div>
                        </div>
                    </section>
                @endif
            </section>

            <section class="nx-cockpit-panel" role="tabpanel" hidden data-cockpit-panel="commercial">
                @if ($hasCommercial || $hasFinancial || $hasQuotation)
                    <section class="nx-cockpit-section-card" data-cockpit-glow>
                        <header class="nx-section-heading">
                            <div class="nx-section-heading-icon nx-tone-commercial">@include('projects.partials.cockpit-icon', ['name' => 'financial'])</div>
                            <div>
                                <span class="nx-section-kicker">Commercial intelligence</span>
                                <h3>Pricing, quotation, and financial position</h3>
                                <p>Commercial totals and feasibility indicators remain traceable to their source modules.</p>
                            </div>
                        </header>

                        <div class="nx-kpi-grid">
                            <article class="nx-kpi-card is-commercial"><span>Grand price</span><strong>{{ $commercial['grand_price'] }}</strong><small>{{ $commercial['pricing_status'] }}</small></article>
                            <article class="nx-kpi-card is-commercial"><span>Quotation</span><strong>{{ $commercial['quotation_status'] }}</strong><small>{{ $commercial['quote_number'] }}</small></article>
                            <article class="nx-kpi-card is-financial"><span>Payback</span><strong>{{ $financial['payback'] }}</strong><small>{{ $financial['status'] }}</small></article>
                            <article class="nx-kpi-card is-financial"><span>IRR</span><strong>{{ $financial['irr'] }}</strong><small>ROI {{ $financial['roi'] }}</small></article>
                            <article class="nx-kpi-card is-financial {{ $hasFinancial && str_contains($financial['npv'], '-') ? 'is-negative' : '' }}"><span>NPV</span><strong>{{ $financial['npv'] }}</strong><small>{{ $financial['capex_source'] }}</small></article>
                            <article class="nx-kpi-card is-financial"><span>LCOE</span><strong>{{ $financial['lcoe'] }}</strong><small>First-year saving {{ $financial['first_year_saving'] }}</small></article>
                        </div>
                    </section>

                    <div class="nx-commercial-grid">
                        <section class="nx-cockpit-section-card" data-cockpit-glow>
                            <header class="nx-section-heading is-compact">
                                <div><span class="nx-section-kicker">Financial performance</span><h3>Cashflow preview</h3><p>Select any year to inspect saving, net cashflow, and cumulative position.</p></div>
                            </header>
                            @if ($charts['cashflow_available'])
                                <div class="nx-cashflow-legend">
                                    <span class="is-saving">Annual saving</span>
                                    <span class="is-net">Net cashflow</span>
                                    <span class="is-cumulative">Cumulative cashflow</span>
                                </div>
                                <div class="nx-cashflow-chart">
                                    @foreach ($charts['cashflow'] as $row)
                                        <article class="nx-cashflow-row">
                                            <span>Y{{ $row['year'] }}</span>
                                            <div class="nx-cashflow-bars">
                                                <i title="Annual saving {{ $row['saving_label'] }}"><b class="is-saving" style="--bar-width: {{ $row['saving_percent'] }}%"></b></i>
                                                <i title="Net cashflow {{ $row['net_label'] }}"><b class="is-net {{ $row['net_state'] === 'critical' ? 'is-negative' : '' }}" style="--bar-width: {{ $row['net_percent'] }}%"></b></i>
                                                <i title="Cumulative cashflow {{ $row['cumulative_label'] }}"><b class="is-cumulative" style="--bar-width: {{ $row['cumulative_percent'] }}%"></b></i>
                                            </div>
                                            <button
                                                type="button"
                                                class="nx-cashflow-inspector"
                                                data-cashflow-inspector
                                                aria-expanded="false"
                                                aria-label="Inspect year {{ $row['year'] }} cashflow values"
                                            >
                                                <span class="nx-cashflow-net">{{ $row['net_label'] }}</span>
                                                <span class="nx-cashflow-tooltip" role="tooltip">
                                                    <span><small>Annual saving</small><b>{{ $row['saving_label'] }}</b></span>
                                                    <span><small>Net cashflow</small><b>{{ $row['net_label'] }}</b></span>
                                                    <span><small>Cumulative</small><b>{{ $row['cumulative_label'] }}</b></span>
                                                </span>
                                            </button>
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <div class="nx-cockpit-empty is-compact"><div><strong>No cashflow yet</strong><p>Run financial analysis to activate the cashflow preview.</p></div></div>
                            @endif
                        </section>

                        <section class="nx-cockpit-section-card" data-cockpit-glow>
                            <header class="nx-section-heading is-compact"><div><span class="nx-section-kicker">Commercial details</span><h3>Pricing build-up</h3></div></header>
                            <dl class="nx-detail-list">
                                <div><dt>BOM status</dt><dd>{{ $commercial['bom_status'] }}</dd></div>
                                <div><dt>Material</dt><dd>{{ $commercial['material_price'] }}</dd></div>
                                <div><dt>Labor</dt><dd>{{ $commercial['labor_cost'] }}</dd></div>
                                <div><dt>Tax</dt><dd>{{ $commercial['tax'] }}</dd></div>
                                <div><dt>Discount</dt><dd>{{ $commercial['discount'] }}</dd></div>
                                <div><dt>CAPEX</dt><dd>{{ $financial['capex'] }}</dd></div>
                            </dl>
                        </section>
                    </div>
                @else
                    <section class="nx-cockpit-section-card" data-cockpit-glow>
                        <div class="nx-cockpit-empty is-large">
                            <span class="nx-cockpit-empty-icon">@include('projects.partials.cockpit-icon', ['name' => 'financial', 'size' => 28])</span>
                            <div>
                                <span class="nx-section-kicker">Commercial workspace</span>
                                <strong>Commercial outputs are not available yet</strong>
                                <p>Generate the BOM/BOQ, apply pricing, run financial analysis, and prepare a quotation to activate this commercial command board.</p>
                                @if ($moduleRoutes['bom'])
                                    <a href="{{ $moduleRoutes['bom'] }}">Open BOM/BOQ @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 15])</a>
                                @endif
                            </div>
                        </div>
                    </section>
                @endif
            </section>

            <section class="nx-cockpit-panel" role="tabpanel" hidden data-cockpit-panel="warnings">
                <section class="nx-cockpit-section-card" data-cockpit-glow>
                    <header class="nx-section-heading">
                        <div class="nx-section-heading-icon nx-tone-warning">@include('projects.partials.cockpit-icon', ['name' => 'warning'])</div>
                        <div>
                            <span class="nx-section-kicker">Engineering and commercial review</span>
                            <h3>Warning center</h3>
                            <p>Technical, roof, commercial, financial, quotation, and override warnings stay grouped and traceable.</p>
                        </div>
                        <span class="nx-state-pill {{ ($warningCounts['critical'] ?? 0) > 0 ? 'is-critical' : (($warningCounts['warning'] ?? 0) > 0 ? 'is-warning' : 'is-ready') }}">{{ $warningTotal }} total</span>
                    </header>

                    <div class="nx-warning-filter" role="group" aria-label="Filter warnings by severity">
                        <button type="button" class="is-active" data-warning-filter="all">All <b>{{ $warningTotal }}</b></button>
                        <button type="button" data-warning-filter="critical">Critical <b>{{ $warningCounts['critical'] ?? 0 }}</b></button>
                        <button type="button" data-warning-filter="warning">Warning <b>{{ $warningCounts['warning'] ?? 0 }}</b></button>
                        <button type="button" data-warning-filter="caution">Caution <b>{{ $warningCounts['caution'] ?? 0 }}</b></button>
                        <button type="button" data-warning-filter="info">Info <b>{{ $warningCounts['info'] ?? 0 }}</b></button>
                    </div>

                    @if ($warningTotal > 0)
                        <div class="nx-warning-list">
                            @foreach ($warningRecords as $warning)
                                @php
                                    $warningSource = $warning['source'] ?? 'system';
                                    $warningAction = $warningActionMap[$warningSource] ?? null;
                                    $warningAnchor = 'warning-'.\Illuminate\Support\Str::slug(($warning['code'] ?? 'warning').'-'.$loop->iteration);
                                @endphp
                                <article id="{{ $warningAnchor }}" class="nx-warning-card is-{{ $warning['severity'] ?? 'info' }}" data-warning-severity="{{ $warning['severity'] ?? 'info' }}">
                                    <div class="nx-warning-card-head">
                                        <span class="nx-warning-icon">@include('projects.partials.cockpit-icon', ['name' => 'warning', 'size' => 17])</span>
                                        <div><strong>{{ $warning['title'] ?? 'Warning' }}</strong><small>{{ str_replace('_', ' ', $warning['source'] ?? 'system') }} · {{ $warning['code'] ?? 'WARNING' }}</small></div>
                                        <span class="nx-state-pill is-{{ ($warning['severity'] ?? 'info') === 'critical' ? 'critical' : (($warning['severity'] ?? 'info') === 'warning' ? 'warning' : 'info') }}">{{ $warning['severity'] ?? 'info' }}</span>
                                    </div>
                                    <p>{{ $warning['message'] ?? '-' }}</p>
                                    <div class="nx-warning-card-footer">
                                        @if (! empty($warning['recommendation']))
                                            <div class="nx-warning-recommendation"><span>Recommended action</span><strong>{{ $warning['recommendation'] }}</strong></div>
                                        @endif
                                        @if (! empty($warningAction['url']))
                                            <a class="nx-warning-action" href="{{ $warningAction['url'] }}">
                                                {{ $warningAction['label'] }}
                                                @include('projects.partials.cockpit-icon', ['name' => 'arrow', 'size' => 14])
                                            </a>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="nx-cockpit-empty is-large">
                            <span class="nx-cockpit-empty-icon">@include('projects.partials.cockpit-icon', ['name' => 'release', 'size' => 28])</span>
                            <div><strong>No recorded warning</strong><p>No critical, warning, caution, or informational record is currently attached to this project.</p></div>
                        </div>
                    @endif
                </section>
            </section>

            <section class="nx-cockpit-panel" role="tabpanel" hidden data-cockpit-panel="release">
                @include('projects.partials.cockpit-release')
            </section>

            <section class="nx-cockpit-panel" role="tabpanel" hidden data-cockpit-panel="reports">
                @include('projects.partials.cockpit-reports-audit')
            </section>
        </div>
    </div>
@endsection
