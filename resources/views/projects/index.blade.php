@extends('layouts.app')

@section('title', 'Projects')
@section('page-eyebrow', 'Project command center')
@section('page-heading', 'Projects')

@push('styles')
    <link href="{{ asset('css/nexorin-project-flow.css') }}?v=67b" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-project-flow.js') }}?v=55a" defer></script>
@endpush

@php
    $totalProjects = max(1, (int) $stats['total']);
    $activeFilters = collect([
        $filters['search'],
        $filters['status'],
        $filters['system'],
        $filters['archive'] !== 'current' ? $filters['archive'] : null,
    ])->filter(fn ($value) => $value !== null && $value !== '')->count();

    $baseQuery = [
        'search' => $filters['search'],
        'status' => $filters['status'],
        'system' => $filters['system'],
        'archive' => $filters['archive'],
        'sort' => $filters['sort'],
        'direction' => $filters['direction'],
    ];

    $projectListUrl = function (array $overrides = []) use ($company, $baseQuery): string {
        $query = array_merge($baseQuery, $overrides);
        $query = array_filter(
            $query,
            fn ($value) => $value !== null && $value !== ''
        );

        return route(
            'companies.projects.index',
            array_merge(['company' => $company], $query)
        );
    };

    $recentSldRevision = $recentProject?->sldRevisions?->first();
    $recentSldState = $recentSldRevision?->status
        ? str($recentSldRevision->status)->replace('_', ' ')->title()
        : 'Not generated';

    $statusContext = [
        'draft' => 'Setup and project data',
        'survey' => 'Site survey in progress',
        'engineering' => 'Technical review underway',
        'quotation' => 'Commercial proposal stage',
        'approved' => 'Approved for delivery',
        'completed' => 'Project workflow complete',
    ];
@endphp

@section('content')
    <div class="project-directory-page">
        <header class="project-directory-hero" data-reveal>
            <div class="project-directory-heading">
                <span class="project-directory-kicker">
                    <span></span>
                    PLTS portfolio workspace
                </span>
                <h2>Project portfolio</h2>
                <p>
                    Control every project from first customer brief through survey, engineering,
                    commercial release, and completion inside {{ $company->name }}.
                </p>
            </div>

            <div class="project-directory-context">
                <span class="project-context-chip project-context-chip-live">
                    <span></span>
                    {{ number_format($stats['active']) }} in progress
                </span>

                <span class="project-context-chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 19V9l8-5 8 5v10"/>
                        <path d="M8 19v-6h8v6M3 19h18"/>
                    </svg>
                    {{ number_format($stats['total']) }} current projects
                </span>

                @if (auth()->user()->hasCompanyPermission($company, 'customer.view'))
                    <a href="{{ route('companies.customers.index', $company) }}" class="project-context-link">
                        View customers
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M5 12h14"/><path d="M13 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endif
            </div>
        </header>

        @if ($recentProject)
            <section class="project-continue-card" data-reveal data-premium-card aria-labelledby="continue-project-title">
                <div class="project-continue-visual" aria-hidden="true">
                    <span class="project-continue-grid"></span>
                    <span class="project-continue-flow">
                        <i></i><i></i><i></i><i></i>
                    </span>
                    <span class="project-continue-orbit"></span>
                </div>

                <div class="project-continue-copy">
                    <span class="project-continue-kicker"><span></span> Continue working</span>
                    <h3 id="continue-project-title">{{ $recentProject->name }}</h3>
                    <p>
                        Resume the most recently updated engineering workspace without searching the full portfolio.
                    </p>
                    <div class="project-continue-meta">
                        <span><small>Project</small><strong>{{ $recentProject->project_number }}</strong></span>
                        <span><small>Customer</small><strong>{{ $recentProject->customer?->name ?? 'Unassigned' }}</strong></span>
                        <span><small>Workflow</small><strong>{{ $recentProject->status_label }}</strong></span>
                        <span><small>SLD</small><strong>{{ $recentSldState }}</strong></span>
                        <span><small>Updated</small><strong>{{ $recentProject->updated_at?->diffForHumans() }}</strong></span>
                    </div>
                </div>

                <div class="project-continue-actions">
                    <a href="{{ route('companies.projects.show', [$company, $recentProject]) }}" class="project-continue-primary">
                        Continue project
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M5 12h14"/><path d="M13 5l7 7-7 7"/>
                        </svg>
                    </a>
                    @if ($canViewSld)
                        <a href="{{ route('companies.projects.sld.show', [$company, $recentProject]) }}" class="project-continue-secondary">
                            Open SLD workspace
                        </a>
                    @endif
                </div>
            </section>
        @elseif ($canCreateProject)
            <section class="project-first-launch" data-reveal aria-labelledby="first-sld-title">
                <span class="project-first-launch-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M8 34h12l4-20h16"/><path d="M11 26h10M14 18h8"/>
                        <circle cx="31" cy="27" r="8"/><path d="M31 23v8M27 27h8"/>
                    </svg>
                </span>
                <div>
                    <span class="project-first-launch-kicker">First engineering workspace</span>
                    <h3 id="first-sld-title">Create your first project and SLD</h3>
                    <p>
                        Start with project data, complete the technical calculation, then generate or draft the controlled single-line diagram.
                    </p>
                </div>
                <a href="{{ route('companies.projects.create', $company) }}" class="project-first-launch-button">
                    <span>+</span> Create project + SLD
                </a>
            </section>
        @endif

        <div class="project-all-projects-heading" data-reveal>
            <div>
                <span>All projects</span>
                <h3>Portfolio overview</h3>
            </div>
            <p>Review portfolio health, then search or filter the complete project registry.</p>
        </div>

        <section class="project-stat-grid" aria-label="Project portfolio statistics">
            <a
                href="{{ $projectListUrl(['status' => null]) }}"
                class="project-stat-card project-stat-card-dark {{ $filters['status'] === null ? 'is-selected' : '' }}"
                style="--stat-progress: 100%;"
                data-reveal
                data-premium-card
            >
                <span class="project-stat-topline">
                    <span class="project-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 19V9l8-5 8 5v10"/>
                            <path d="M8 19v-6h8v6M3 19h18"/>
                        </svg>
                    </span>
                    <span class="project-stat-state">Portfolio</span>
                </span>
                <span class="project-stat-body">
                    <strong data-counter="{{ $stats['total'] }}">{{ number_format($stats['total']) }}</strong>
                    <span>Current projects</span>
                    <small>All visible records in the active workspace</small>
                </span>
                <span class="project-stat-progress"><span></span></span>
            </a>

            <a
                href="{{ $projectListUrl(['status' => 'draft']) }}"
                class="project-stat-card project-stat-card-amber {{ $filters['status'] === 'draft' ? 'is-selected' : '' }}"
                style="--stat-progress: {{ ($stats['draft'] / $totalProjects) * 100 }}%;"
                data-reveal
                data-premium-card
            >
                <span class="project-stat-topline">
                    <span class="project-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>
                        </svg>
                    </span>
                    <span class="project-stat-state">Setup</span>
                </span>
                <span class="project-stat-body">
                    <strong data-counter="{{ $stats['draft'] }}">{{ number_format($stats['draft']) }}</strong>
                    <span>Draft projects</span>
                    <small>Records still completing initial project data</small>
                </span>
                <span class="project-stat-progress"><span></span></span>
            </a>

            <article
                class="project-stat-card project-stat-card-teal"
                style="--stat-progress: {{ ($stats['active'] / $totalProjects) * 100 }}%;"
                data-reveal
                data-premium-card
            >
                <span class="project-stat-topline">
                    <span class="project-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 12h4l2-5 4 10 2-5h4"/>
                        </svg>
                    </span>
                    <span class="project-stat-state">Active</span>
                </span>
                <span class="project-stat-body">
                    <strong data-counter="{{ $stats['active'] }}">{{ number_format($stats['active']) }}</strong>
                    <span>In progress</span>
                    <small>Survey, engineering, quotation, and approval</small>
                </span>
                <span class="project-stat-progress"><span></span></span>
            </article>

            <a
                href="{{ $projectListUrl(['status' => 'completed']) }}"
                class="project-stat-card project-stat-card-green {{ $filters['status'] === 'completed' ? 'is-selected' : '' }}"
                style="--stat-progress: {{ ($stats['completed'] / $totalProjects) * 100 }}%;"
                data-reveal
                data-premium-card
            >
                <span class="project-stat-topline">
                    <span class="project-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="8"/><path d="M8 12l3 3 5-6"/>
                        </svg>
                    </span>
                    <span class="project-stat-state">Delivered</span>
                </span>
                <span class="project-stat-body">
                    <strong data-counter="{{ $stats['completed'] }}">{{ number_format($stats['completed']) }}</strong>
                    <span>Completed</span>
                    <small>Projects that have finished the workflow</small>
                </span>
                <span class="project-stat-progress"><span></span></span>
            </a>
        </section>

        <section class="project-registry-card" data-reveal data-premium-card>
            <header class="project-registry-header">
                <div>
                    <span class="project-registry-kicker">Portfolio registry</span>
                    <h3>Project directory</h3>
                    <p>
                        {{ number_format($projects->total()) }} matching {{ \Illuminate\Support\Str::plural('record', $projects->total()) }}.
                        Open a row to continue its workflow.
                    </p>
                </div>

                <div class="project-registry-summary">
                    @if ($activeFilters > 0)
                        <span class="project-filter-count">{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                    @endif
                    <span class="project-result-range">
                        @if ($projects->total() === 0)
                            No projects
                        @elseif ($projects->hasPages())
                            Showing {{ number_format($projects->firstItem()) }}–{{ number_format($projects->lastItem()) }} of {{ number_format($projects->total()) }}
                        @else
                            Showing {{ number_format($projects->total()) }} {{ \Illuminate\Support\Str::plural('project', $projects->total()) }}
                        @endif
                    </span>
                </div>
            </header>

            <form
                id="projectDirectoryFilters"
                method="GET"
                action="{{ route('companies.projects.index', $company) }}"
                class="project-filter-toolbar"
                aria-label="Project directory filters"
            >
                <div class="project-filter-search">
                    <label for="project-search">Search projects</label>
                    <div class="project-filter-control project-filter-search-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>
                        </svg>
                        <input
                            id="project-search"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Name, number, customer, or address"
                            autocomplete="off"
                        >
                        @if ($filters['search'] !== '')
                            <button
                                type="button"
                                class="project-filter-clear-search"
                                aria-label="Clear project search"
                                data-project-clear-search
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M7 7l10 10M17 7L7 17"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="project-filter-field">
                    <label for="project-status">Stage</label>
                    <div class="project-filter-control project-filter-select-control">
                        <select id="project-status" name="status" data-project-auto-submit>
                            <option value="">All stages</option>
                            @foreach (\App\Models\Project::STATUSES as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M7 10l5 5 5-5"/>
                        </svg>
                    </div>
                </div>

                <div class="project-filter-field">
                    <label for="project-system">System</label>
                    <div class="project-filter-control project-filter-select-control">
                        <select id="project-system" name="system" data-project-auto-submit>
                            <option value="">All systems</option>
                            <option value="undecided" @selected($filters['system'] === 'undecided')>Undecided</option>
                            <option value="on_grid" @selected($filters['system'] === 'on_grid')>On-grid</option>
                            <option value="hybrid" @selected($filters['system'] === 'hybrid')>Hybrid</option>
                        </select>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M7 10l5 5 5-5"/>
                        </svg>
                    </div>
                </div>

                <div class="project-filter-field">
                    <label for="project-archive">Records</label>
                    <div class="project-filter-control project-filter-select-control">
                        <select id="project-archive" name="archive" data-project-auto-submit>
                            <option value="current" @selected($filters['archive'] === 'current')>Current</option>
                            <option value="archived" @selected($filters['archive'] === 'archived')>Archived</option>
                            <option value="all" @selected($filters['archive'] === 'all')>All records</option>
                        </select>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M7 10l5 5 5-5"/>
                        </svg>
                    </div>
                </div>

                <div class="project-filter-field project-sort-field">
                    <label for="project-sort">Sort</label>
                    <div class="project-filter-control project-filter-select-control">
                        <select id="project-sort" name="sort" data-project-auto-submit>
                            <option value="created_at" @selected($filters['sort'] === 'created_at')>Created</option>
                            <option value="status_changed_at" @selected($filters['sort'] === 'status_changed_at')>Stage updated</option>
                            <option value="name" @selected($filters['sort'] === 'name')>Project name</option>
                            <option value="project_number" @selected($filters['sort'] === 'project_number')>Project number</option>
                            <option value="status" @selected($filters['sort'] === 'status')>Workflow stage</option>
                        </select>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M7 10l5 5 5-5"/>
                        </svg>
                    </div>
                </div>

                <input id="project-sort-direction" type="hidden" name="direction" value="{{ $filters['direction'] }}">

                <div class="project-filter-actions">
                    <button
                        type="button"
                        class="project-sort-direction"
                        aria-label="Change sort direction"
                        title="Sort {{ $filters['direction'] === 'asc' ? 'ascending' : 'descending' }}"
                        data-project-sort-direction
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            @if ($filters['direction'] === 'asc')
                                <path d="M8 17V5M4 9l4-4 4 4"/><path d="M16 7h4M16 12h3M16 17h2"/>
                            @else
                                <path d="M8 5v12M4 13l4 4 4-4"/><path d="M16 7h2M16 12h3M16 17h4"/>
                            @endif
                        </svg>
                    </button>

                    <button type="submit" class="project-filter-apply">
                        Apply filters
                    </button>

                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.projects.index', $company) }}" class="project-filter-reset" title="Reset filters">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 7h16M7 12h10M10 17h4"/><path d="M18 4l2 3-2 3"/>
                            </svg>
                            Reset
                        </a>
                    @endif
                </div>
            </form>

            <nav id="project-stage-filters" class="project-stage-filters" aria-label="Filter projects by workflow stage">
                <a href="{{ $projectListUrl(['status' => null]) }}" class="{{ $filters['status'] === null ? 'is-active' : '' }}">
                    All
                </a>
                @foreach (\App\Models\Project::STATUSES as $status)
                    <a
                        href="{{ $projectListUrl(['status' => $status]) }}"
                        class="project-stage-filter-{{ $status }} {{ $filters['status'] === $status ? 'is-active' : '' }}"
                    >
                        <span></span>
                        {{ ucfirst($status) }}
                    </a>
                @endforeach
            </nav>

            @if ($projects->isEmpty())
                <div class="project-directory-empty">
                    <span class="project-directory-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 18h18"/>
                        </svg>
                    </span>
                    <span class="project-directory-empty-kicker">No matching portfolio record</span>
                    <h4>No projects found</h4>
                    <p>Adjust the filters or start a new project to populate this workspace.</p>
                    <div>
                        <a href="{{ route('companies.projects.index', $company) }}" class="project-empty-secondary">Reset filters</a>
                        @if ($canCreateProject)
                            <a href="{{ route('companies.projects.create', $company) }}" class="project-empty-primary">Create project</a>
                        @endif
                    </div>
                </div>
            @else
                <div class="project-registry-table" role="table" aria-label="Project directory">
                    <div class="project-registry-table-head" role="row">
                        <span role="columnheader">Project</span>
                        <span role="columnheader">Customer</span>
                        <span role="columnheader">System</span>
                        <span role="columnheader">Workflow</span>
                        <span role="columnheader">Team</span>
                        <span role="columnheader">Updated</span>
                        <span role="columnheader" aria-label="Actions"></span>
                    </div>

                    <div class="project-registry-table-body" role="rowgroup">
                        @foreach ($projects as $project)
                            @php
                                $projectStatusContext = $project->isArchived()
                                    ? 'Archived '.$project->archived_at?->format('d M Y')
                                    : ($statusContext[$project->status] ?? 'Workflow active');
                            @endphp

                            <div
                                class="project-registry-row"
                                role="row"
                                tabindex="0"
                                data-row-href="{{ route('companies.projects.show', [$company, $project]) }}"
                                data-project-number="{{ $project->project_number }}"
                                data-project-name="{{ $project->name }}"
                            >
                                <div class="project-registry-project" role="cell" data-label="Project">
                                    <span class="project-registry-symbol project-system-{{ $project->proposed_system }}">
                                        @if ($project->proposed_system === 'hybrid')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M3 14h12l-1.5-6h-9L3 14z"/><path d="M6 8l-.7 6M9.5 8v6M13 8l.7 6M2 17h14"/>
                                                <rect x="17" y="7" width="4" height="9" rx="1"/><path d="M18.5 5h1M18.5 18h1"/>
                                            </svg>
                                        @elseif ($project->proposed_system === 'on_grid')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M3 14h12l-1.5-6h-9L3 14z"/><path d="M6 8l-.7 6M9.5 8v6M13 8l.7 6M2 17h14"/>
                                                <path d="M18 4v16M15 7h6M16 11h4M17 15h2"/>
                                            </svg>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <circle cx="12" cy="12" r="8"/><path d="M14.8 9.2l-1.6 4-4 1.6 1.6-4 4-1.6z"/>
                                            </svg>
                                        @endif
                                    </span>
                                    <span class="project-registry-primary-copy">
                                        <strong>{{ $project->name }}</strong>
                                        <small>{{ $project->project_number }}</small>
                                    </span>
                                </div>

                                <div class="project-registry-customer" role="cell" data-label="Customer">
                                    <strong>{{ $project->customer->name }}</strong>
                                    <small>{{ $project->customer->customer_number }}</small>
                                </div>

                                <div class="project-registry-system" role="cell" data-label="System">
                                    <strong>{{ $project->proposed_system_label }}</strong>
                                    <small>{{ $project->building_type_label }}</small>
                                </div>

                                <div class="project-registry-workflow" role="cell" data-label="Workflow">
                                    <span class="project-stage-badge project-stage-{{ $project->status }}">
                                        <span></span>
                                        {{ $project->status_label }}
                                    </span>
                                    <small>{{ $projectStatusContext }}</small>
                                </div>

                                <div class="project-registry-team" role="cell" data-label="Team">
                                    <span class="project-assignment-line">
                                        <span class="project-assignment-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <circle cx="12" cy="8" r="3"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/>
                                            </svg>
                                        </span>
                                        <span>
                                            <small>Engineer</small>
                                            <strong>{{ $project->assignedEngineer?->name ?? 'Unassigned' }}</strong>
                                        </span>
                                    </span>
                                    <span class="project-assignment-line">
                                        <span class="project-assignment-icon project-assignment-icon-sales">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M4 5h16v11H4z"/><path d="M8 20h8M12 16v4"/><path d="M8 10h8"/>
                                            </svg>
                                        </span>
                                        <span>
                                            <small>Sales</small>
                                            <strong>{{ $project->assignedSales?->name ?? 'Unassigned' }}</strong>
                                        </span>
                                    </span>
                                </div>

                                <div class="project-registry-updated" role="cell" data-label="Updated">
                                    <strong>{{ $project->updated_at?->diffForHumans() }}</strong>
                                    <small>{{ $project->updated_at?->format('d M Y · H:i') }}</small>
                                </div>

                                <div class="project-registry-actions" role="cell" data-label="Actions">
                                    @if (auth()->user()->hasCompanyPermission($company, 'project.update') && ! $project->isArchived())
                                        <a
                                            href="{{ route('companies.projects.edit', [$company, $project]) }}"
                                            class="project-row-edit"
                                            aria-label="Edit {{ $project->name }}"
                                            data-tooltip="Edit project"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M4 20h4l11-11-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/>
                                            </svg>
                                        </a>
                                    @endif

                                    <a
                                        href="{{ route('companies.projects.show', [$company, $project]) }}"
                                        class="project-row-open"
                                        aria-label="Open {{ $project->name }}"
                                        data-tooltip="Open project"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                            <path d="M5 12h14"/><path d="M13 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="project-registry-pagination">
                    @include('partials.pagination', ['paginator' => $projects])
                </div>
            @endif
        </section>
    </div>
@endsection
