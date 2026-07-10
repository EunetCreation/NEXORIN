@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-eyebrow', 'Command center')
@section('page-heading', 'Dashboard')

@php
    $roleLabel = $currentUser->isSuperAdmin()
        ? 'Super Admin'
        : ($currentUser->companyMemberships()
            ->where('company_id', $currentCompany?->getKey())
            ->where('status', 'active')
            ->with('roles')
            ->first()?->roles?->first()?->name ?? 'Company Member');

    $canCreateProject = $currentCompany
        && $currentUser->hasAnyCompanyPermission($currentCompany, ['project.create', 'project.create_own']);

    $canViewProjects = $currentCompany
        && $currentUser->hasAnyCompanyPermission($currentCompany, ['project.view', 'project.view_own']);

    $canViewCatalog = $currentCompany
        && $currentUser->hasAnyCompanyPermission($currentCompany, ['equipment.view', 'supplier.view', 'price.cost.view']);

    $canViewQuotations = $currentCompany
        && $currentUser->hasAnyCompanyPermission($currentCompany, ['quotation.view', 'quotation.view_own']);

    $canViewEngineering = $currentCompany
        && $currentUser->hasAnyCompanyPermission($currentCompany, ['calculation.view', 'calculation.view_own']);

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

    $formatCompactCurrency = function (float|int $value): string {
        $value = (float) $value;

        return match (true) {
            $value >= 1_000_000_000 => 'IDR '.number_format($value / 1_000_000_000, 1).'B',
            $value >= 1_000_000 => 'IDR '.number_format($value / 1_000_000, 1).'M',
            $value >= 1_000 => 'IDR '.number_format($value / 1_000, 0).'K',
            default => 'IDR '.number_format($value, 0, ',', '.'),
        };
    };

    $pipelineTotal = max(1, $projectStatusCounts->sum());
    $firstName = str($currentUser->name)->before(' ')->toString();
    $greeting = now('Asia/Jakarta')->hour < 12
        ? 'morning'
        : (now('Asia/Jakarta')->hour < 18 ? 'afternoon' : 'evening');
@endphp

@section('content')
<div class="dashboard-page">
    <section class="dashboard-intro premium-intro" data-reveal>
        <div class="dashboard-intro-copy">
            <span class="dashboard-intro-kicker">
                <span class="live-dot"></span>
                {{ $currentCompany?->name ?? 'NEXORIN Workspace' }}
            </span>
            <h2>Good {{ $greeting }}, <span>{{ $firstName }}</span>.</h2>
            <p>Track projects, engineering reviews, catalog readiness, and quotation activity from one focused workspace.</p>
        </div>

        <div class="dashboard-context" aria-label="Workspace context">
            <span class="context-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path d="M12 3l7 3v5c0 4.8-2.9 8.2-7 10-4.1-1.8-7-5.2-7-10V6l7-3z"/>
                    <path d="M9.5 12l1.7 1.7 3.5-4"/>
                </svg>
                {{ $roleLabel }}
            </span>
            <span class="context-chip context-chip-live">
                <span class="context-status-dot"></span>
                {{ $activeProjectsCount }} active project{{ $activeProjectsCount === 1 ? '' : 's' }}
            </span>
        </div>
    </section>

    <section class="dashboard-metrics" aria-label="Workspace summary">
        <article class="metric-card metric-card-accent" data-reveal data-premium-card>
            <div class="metric-card-topline">
                <span class="metric-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg>
                </span>
                <span class="metric-trend">Live</span>
            </div>
            <span class="metric-label">Active Projects</span>
            <strong class="metric-number" data-counter="{{ $activeProjectsCount }}">{{ $activeProjectsCount }}</strong>
            <span class="metric-caption">Projects moving through survey, engineering, or commercial review.</span>
        </article>

        <article class="metric-card" data-reveal data-premium-card>
            <div class="metric-card-topline">
                <span class="metric-icon tone-warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.6L2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0z"/></svg>
                </span>
                <span class="metric-trend tone-warning">Review</span>
            </div>
            <span class="metric-label">Needs Attention</span>
            <strong class="metric-number" data-counter="{{ $needsReviewCount }}">{{ $needsReviewCount }}</strong>
            <span class="metric-caption">Projects that still need engineering or workflow review.</span>
        </article>

        <article class="metric-card" data-reveal data-premium-card>
            <div class="metric-card-topline">
                <span class="metric-icon tone-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M13 2L5 14h6l-1 8 8-12h-6z"/></svg>
                </span>
                <span class="metric-trend tone-blue">Capacity</span>
            </div>
            <span class="metric-label">Designed Capacity</span>
            <strong class="metric-number"><span data-counter="{{ round($designedCapacityKw, 1) }}">{{ number_format($designedCapacityKw, 1) }}</span><small> kW</small></strong>
            <span class="metric-caption">Stored inverter capacity across technical calculations.</span>
        </article>

        <article class="metric-card metric-card-dark" data-reveal data-premium-card>
            <div class="metric-card-topline">
                <span class="metric-icon tone-light">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M12 3v18"/><path d="M17 7.5c0-1.9-2.2-3.5-5-3.5S7 5.6 7 7.5 9.2 11 12 11s5 1.6 5 3.5-2.2 3.5-5 3.5-5-1.6-5-3.5"/></svg>
                </span>
                <span class="metric-trend tone-light">Pipeline</span>
            </div>
            <span class="metric-label">Commercial Value</span>
            <strong class="metric-number metric-currency">{{ $formatCompactCurrency($pipelineValue) }}</strong>
            <span class="metric-caption">Current BOQ selling value recorded in the workspace.</span>
        </article>
    </section>

    <section class="dashboard-grid">
        <div class="dashboard-main-column">
            <article class="dashboard-card pipeline-card" data-reveal data-premium-card>
                <header class="dashboard-card-header">
                    <div>
                        <span class="card-kicker">Portfolio overview</span>
                        <h3>Project Pipeline</h3>
                        <p>Distribution of projects across the existing NEXORIN workflow.</p>
                    </div>
                    @if ($canViewProjects)
                        <a href="{{ route('companies.projects.index', $currentCompany) }}" class="card-text-link">All projects <span>↗</span></a>
                    @endif
                </header>

                <div class="pipeline-visual" aria-label="Project status distribution">
                    @foreach (['draft', 'survey', 'engineering', 'quotation', 'approved', 'completed'] as $status)
                        @php $count = (int) $projectStatusCounts->get($status, 0); @endphp
                        <span
                            class="pipeline-segment pipeline-{{ $status }}"
                            style="--pipeline-weight: {{ max(0.5, ($count / $pipelineTotal) * 100) }}"
                            title="{{ $statusLabels[$status] }}: {{ $count }}"
                        ></span>
                    @endforeach
                </div>

                <div class="pipeline-legend">
                    @foreach (['draft', 'survey', 'engineering', 'quotation', 'approved', 'completed'] as $status)
                        <div class="pipeline-legend-item">
                            <span class="pipeline-dot pipeline-{{ $status }}"></span>
                            <span>{{ $statusLabels[$status] }}</span>
                            <strong>{{ $projectStatusCounts->get($status, 0) }}</strong>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="dashboard-card work-queue-card" data-reveal>
                <header class="dashboard-card-header">
                    <div>
                        <span class="card-kicker">Continue working</span>
                        <h3>Project Work Queue</h3>
                        <p>Module readiness reflects stored project data. Warnings remain visible and still require review.</p>
                    </div>
                    <span class="card-count">{{ $projectQueue->count() }} recent</span>
                </header>

                @if ($projectQueue->isNotEmpty())
                    <div class="work-queue-table-wrap">
                        <table class="work-queue-table">
                            <colgroup>
                                <col class="queue-col-project">
                                <col class="queue-col-stage">
                                <col class="queue-col-system">
                                <col class="queue-col-progress">
                                <col class="queue-col-next">
                                <col class="queue-col-action">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Stage</th>
                                    <th>System</th>
                                    <th>Data Readiness</th>
                                    <th>Next Step</th>
                                    <th><span class="sr-only">Open</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($projectQueue as $queueItem)
                                    @php
                                        $project = $queueItem['project'];
                                        $status = $project->status ?? 'draft';
                                        $nextStep = $queueItem['next_step'];
                                    @endphp
                                    <tr data-premium-row>
                                        <td>
                                            <div class="project-cell">
                                                <span class="project-symbol" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <path d="M4 15h16l-2-8H6l-2 8z"/>
                                                        <path d="M8 7l-1 8M12 7v8M16 7l1 8M3 18h18M12 15v6"/>
                                                    </svg>
                                                </span>
                                                <span class="project-cell-copy">
                                                    <strong>{{ $project->name }}</strong>
                                                    <small>{{ $project->customer?->name ?? 'No customer' }} · {{ $project->updated_at->diffForHumans() }}</small>
                                                </span>
                                            </div>
                                        </td>
                                        <td><span class="status-badge tone-{{ $statusTones[$status] ?? 'neutral' }}">{{ $statusLabels[$status] ?? str($status)->headline() }}</span></td>
                                        <td><span class="system-label">{{ $systemLabels[$project->proposed_system ?? 'undecided'] ?? str($project->proposed_system)->headline() }}</span></td>
                                        <td>
                                            <div class="table-progress">
                                                <div class="table-progress-track"><span data-progress="{{ $queueItem['completion_percent'] }}"></span></div>
                                                <strong>
                                                    {{ $queueItem['completion_percent'] >= 100
                                                        ? 'Data ready'
                                                        : $queueItem['completion_percent'].'% ready' }}
                                                </strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="next-step-label">{{ $nextStep['label'] ?? 'Review' }}</span>
                                            @if ($queueItem['warning_count'] > 0)
                                                <small class="warning-note">{{ $queueItem['warning_count'] }} warning{{ $queueItem['warning_count'] > 1 ? 's' : '' }}</small>
                                            @else
                                                <small>{{ $nextStep['caption'] ?? 'Workflow complete' }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('companies.projects.show', [$currentCompany, $project]) }}" class="row-action" aria-label="Open {{ $project->name }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="dashboard-empty-state">
                        <span class="empty-state-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg>
                        </span>
                        <h4>Your project queue is ready.</h4>
                        <p>Create the first project without changing the current NEXORIN workflow.</p>
                        @if ($canCreateProject)
                            <a href="{{ route('companies.projects.create', $currentCompany) }}" class="dashboard-primary-button">Create Project</a>
                        @endif
                    </div>
                @endif
            </article>
        </div>

        <aside class="dashboard-side-column" aria-label="Workspace health and commercial activity">
            <article class="readiness-card" data-reveal data-premium-card>
                <div class="readiness-glow"></div>
                <header>
                    <span class="card-kicker light">Workspace health</span>
                    <h3>Catalog Readiness</h3>
                    <p>Approved materials and active commercial references available for BOQ generation.</p>
                </header>

                <div class="readiness-score-row">
                    <div class="readiness-ring" style="--readiness: {{ $catalogReadinessPercent }}">
                        <div>
                            <strong data-counter="{{ $catalogReadinessPercent }}">{{ $catalogReadinessPercent }}</strong><span>%</span>
                            <small>ready</small>
                        </div>
                    </div>

                    <div class="readiness-summary">
                        <strong>{{ $catalogApproved }} / {{ $catalogTotal }}</strong>
                        <span>approved materials</span>
                    </div>
                </div>

                <div class="readiness-stats">
                    <div><span>Price references</span><strong>{{ $priceReferenceCount }}</strong></div>
                    <div><span>Open quotations</span><strong>{{ $quotationOpenCount }}</strong></div>
                    <div><span>Review queue</span><strong>{{ $needsReviewCount }}</strong></div>
                </div>

                @if ($canViewCatalog)
                    <a href="{{ route('companies.materials.index', $currentCompany) }}" class="readiness-action">
                        Review catalog
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                    </a>
                @endif
            </article>

            <article class="dashboard-card quotation-card" data-reveal data-premium-card>
                <header class="dashboard-card-header compact">
                    <div>
                        <span class="card-kicker">Commercial queue</span>
                        <h3>Recent Quotations</h3>
                        <p>Latest quotation revisions and current release status.</p>
                    </div>
                    @if ($canViewQuotations)
                        <a href="{{ route('companies.quotations.index', $currentCompany) }}" class="card-icon-link" aria-label="View quotations">↗</a>
                    @endif
                </header>

                <div class="quotation-list">
                    @forelse ($recentQuotations as $quotation)
                        <a href="{{ route('companies.quotations.show', [$currentCompany, $quotation->project]) }}" class="quotation-list-item">
                            <span class="quotation-symbol">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/></svg>
                            </span>
                            <span class="quotation-copy">
                                <strong>{{ $quotation->quote_number ?: 'Draft quotation' }}</strong>
                                <small>{{ $quotation->project?->name ?? 'Unlinked project' }}</small>
                            </span>
                            <span class="status-badge tone-{{ $quotationTones[$quotation->status] ?? 'neutral' }}">{{ $quotation->status_label }}</span>
                        </a>
                    @empty
                        <div class="mini-empty-state">
                            <strong>No quotation activity</strong>
                            <span>Generated quotation revisions will appear here.</span>
                        </div>
                    @endforelse
                </div>
            </article>
        </aside>

        @if ($currentCompany)
            <article class="dashboard-card command-dock-card" data-reveal>
                <header class="dashboard-card-header compact command-dock-header">
                    <div>
                        <span class="card-kicker">Workspace shortcuts</span>
                        <h3>Open the next module</h3>
                    </div>
                    <span class="command-dock-note">Fast access. Existing routes and workflow stay unchanged.</span>
                </header>

                <div class="command-dock-grid">
                    @if ($canViewProjects)
                        <a href="{{ route('companies.projects.index', $currentCompany) }}" class="command-dock-link">
                            <span class="command-dock-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/>
                                </svg>
                            </span>
                            <span class="command-dock-copy"><strong>Projects</strong><small>Manage the project pipeline</small></span>
                            <span class="command-dock-arrow">→</span>
                        </a>
                    @endif
                    @if ($canViewCatalog)
                        <a href="{{ route('companies.materials.index', $currentCompany) }}" class="command-dock-link">
                            <span class="command-dock-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M6 4h12a2 2 0 0 1 2 2v12l-4-2-4 2-4-2-4 2V6a2 2 0 0 1 2-2z"/><path d="M8 8h8M8 12h8"/>
                                </svg>
                            </span>
                            <span class="command-dock-copy"><strong>Catalog</strong><small>Review material readiness</small></span>
                            <span class="command-dock-arrow">→</span>
                        </a>
                    @endif
                    @if ($canViewQuotations)
                        <a href="{{ route('companies.quotations.index', $currentCompany) }}" class="command-dock-link">
                            <span class="command-dock-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/>
                                </svg>
                            </span>
                            <span class="command-dock-copy"><strong>Quotations</strong><small>Review and release offers</small></span>
                            <span class="command-dock-arrow">→</span>
                        </a>
                    @endif
                    @if ($canViewEngineering)
                        <a href="{{ route('companies.technical-calculations.index', $currentCompany) }}" class="command-dock-link">
                            <span class="command-dock-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <rect x="5" y="3" width="14" height="18" rx="3"/><path d="M8 7h8M8 11h3M13 11h3M8 15h3M13 15h3"/>
                                </svg>
                            </span>
                            <span class="command-dock-copy"><strong>Engineering</strong><small>Inspect technical results</small></span>
                            <span class="command-dock-arrow">→</span>
                        </a>
                    @endif
                </div>
            </article>
        @endif
    </section>
</div>
@endsection
