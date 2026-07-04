@extends('layouts.app')

@section('title', 'Projects')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Projects')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">PLTS project workflow</div>
                <h2 class="ui-page-title">Project Directory</h2>
                <p class="ui-page-description">
                    Track PLTS projects from initial draft through survey, engineering,
                    quotation, approval, and completion inside {{ $company->name }}.
                </p>
            </div>

            <div class="ui-page-actions">
                @if (auth()->user()->hasCompanyPermission($company, 'customer.view'))
                    <a href="{{ route('companies.customers.index', $company) }}" class="ui-button ui-button-secondary">View Customers</a>
                @endif

                @if ($canCreateProject)
                    <a href="{{ route('companies.projects.create', $company) }}" class="ui-button ui-button-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        New Project
                    </a>
                @endif
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="Project statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 19V9l8-5 8 5v10"/>
                            <path d="M8 19v-6h8v6M3 19h18"/>
                        </svg>
                    </span>
                </div>
                <strong class="ui-stat-value">{{ number_format($stats['total']) }}</strong>
                <span class="ui-stat-label">Current Projects</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M5 4h14v16H5z"/>
                            <path d="M8 8h8M8 12h8"/>
                        </svg>
                    </span>
                </div>
                <strong class="ui-stat-value">{{ number_format($stats['draft']) }}</strong>
                <span class="ui-stat-label">Draft Projects</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 12h4l2-5 4 10 2-5h4"/>
                        </svg>
                    </span>
                </div>
                <strong class="ui-stat-value">{{ number_format($stats['active']) }}</strong>
                <span class="ui-stat-label">In Progress</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="8"/>
                            <path d="M8 12l3 3 5-6"/>
                        </svg>
                    </span>
                </div>
                <strong class="ui-stat-value">{{ number_format($stats['completed']) }}</strong>
                <span class="ui-stat-label">Completed</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="Project filters">
            <form method="GET" action="{{ route('companies.projects.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="project-search">Search projects</label>
                    <div class="ui-input-wrap">
                        <svg class="ui-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-4-4"/>
                        </svg>
                        <input
                            id="project-search"
                            class="ui-input ui-input-with-icon"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Project number, name, customer, or address"
                        >
                    </div>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="project-status">Status</label>
                    <select id="project-status" class="ui-select" name="status">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\Project::STATUSES as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="project-system">System</label>
                    <select id="project-system" class="ui-select" name="system">
                        <option value="">All systems</option>
                        <option value="undecided" @selected($filters['system'] === 'undecided')>Undecided</option>
                        <option value="on_grid" @selected($filters['system'] === 'on_grid')>On-grid</option>
                        <option value="hybrid" @selected($filters['system'] === 'hybrid')>Hybrid</option>
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="project-archive">Records</label>
                    <select id="project-archive" class="ui-select" name="archive">
                        <option value="current" @selected($filters['archive'] === 'current')>Current</option>
                        <option value="archived" @selected($filters['archive'] === 'archived')>Archived</option>
                        <option value="all" @selected($filters['archive'] === 'all')>All records</option>
                    </select>
                </div>

                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['status'] || $filters['system'] || $filters['archive'] !== 'current')
                    <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
                @endif
            </form>
        </section>

        <section class="ui-table-shell">
            @if ($projects->isEmpty())
                <div class="ui-empty">
                    <div>
                        <div class="ui-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 19V9l8-5 8 5v10"/>
                                <path d="M8 19v-6h8v6M3 19h18"/>
                            </svg>
                        </div>
                        <h3>No projects found</h3>
                        <p>Create a project or adjust the filters to display matching records.</p>
                        @if ($canCreateProject)
                            <a href="{{ route('companies.projects.create', $company) }}" class="ui-button ui-button-primary">New Project</a>
                        @endif
                    </div>
                </div>
            @else
                <div class="ui-table-scroll">
                    <table class="ui-table ui-project-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Customer</th>
                                <th>System</th>
                                <th>Workflow</th>
                                <th>Assignments</th>
                                <th>Updated</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                <tr>
                                    <td>
                                        <div class="ui-company-cell">
                                            <span class="ui-project-avatar">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M4 19V9l8-5 8 5v10"/>
                                                    <path d="M8 19v-6h8v6"/>
                                                </svg>
                                            </span>
                                            <span class="ui-company-copy">
                                                <strong>{{ $project->name }}</strong>
                                                <span>{{ $project->project_number }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">{{ $project->customer->name }}</span>
                                        <span class="ui-table-secondary">{{ $project->customer->customer_number }}</span>
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">{{ $project->proposed_system_label }}</span>
                                        <span class="ui-table-secondary">{{ $project->building_type_label }}</span>
                                    </td>
                                    <td>
                                        <span class="ui-status ui-status-project-{{ $project->status }}">{{ $project->status_label }}</span>
                                        @if ($project->isArchived())
                                            <span class="ui-table-secondary">Archived {{ $project->archived_at?->format('d M Y') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">{{ $project->assignedEngineer?->name ?? 'Engineer unassigned' }}</span>
                                        <span class="ui-table-secondary">{{ $project->assignedSales?->name ?? 'Sales unassigned' }}</span>
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">{{ $project->updated_at?->format('d M Y') }}</span>
                                        <span class="ui-table-secondary">{{ $project->updated_at?->format('H:i') }}</span>
                                    </td>
                                    <td>
                                        <div class="ui-actions">
                                            <a
                                                href="{{ route('companies.projects.show', [$company, $project]) }}"
                                                class="ui-icon-button"
                                                aria-label="View {{ $project->name }}"
                                                title="View project"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/>
                                                    <circle cx="12" cy="12" r="2.5"/>
                                                </svg>
                                            </a>
                                            @if (auth()->user()->hasCompanyPermission($company, 'project.update') && ! $project->isArchived())
                                                <a
                                                    href="{{ route('companies.projects.edit', [$company, $project]) }}"
                                                    class="ui-icon-button"
                                                    aria-label="Edit {{ $project->name }}"
                                                    title="Edit project"
                                                >
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                        <path d="M4 20h4l11-11-4-4L4 16v4z"/>
                                                        <path d="M13.5 6.5l4 4"/>
                                                    </svg>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.pagination', ['paginator' => $projects])
            @endif
        </section>
    </div>
@endsection
