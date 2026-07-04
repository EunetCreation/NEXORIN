@extends('layouts.app')

@section('title', 'Load Analysis')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Load Analysis')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Electrical demand foundation</div>
                <h2 class="ui-page-title">Project Load Analysis</h2>
                <p class="ui-page-description">
                    Build appliance schedules, validate monthly bills, and generate a 24-hour demand profile before PLTS sizing.
                </p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Project Directory</a>
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="Load analysis statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PJ</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['projects']) }}</strong>
                <span class="ui-stat-label">Available Projects</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">LA</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['started']) }}</strong>
                <span class="ui-stat-label">Analyses Started</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">EQ</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['with_appliances']) }}</strong>
                <span class="ui-stat-label">With Equipment Data</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">BL</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['with_bills']) }}</strong>
                <span class="ui-stat-label">With Bill History</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="Load analysis filters">
            <form method="GET" action="{{ route('companies.load-analyses.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="load-analysis-search">Search projects</label>
                    <input
                        id="load-analysis-search"
                        class="ui-input"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Project number, name, or customer"
                    >
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="load-analysis-status">Analysis status</label>
                    <select id="load-analysis-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                        <option value="not_started" @selected($filters['status'] === 'not_started')>Not started</option>
                        <option value="in_progress" @selected($filters['status'] === 'in_progress')>Started</option>
                    </select>
                </div>

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                    <a href="{{ route('companies.load-analyses.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
                @endif
            </form>
        </section>

        <section class="ui-table-shell">
            @if ($projects->isEmpty())
                <div class="ui-empty">
                    <div>
                        <h3>No projects available</h3>
                        <p>Create a current project or adjust the filters.</p>
                    </div>
                </div>
            @else
                <div class="ui-table-scroll">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Customer</th>
                                <th>Analysis</th>
                                <th>Appliances</th>
                                <th>Bills</th>
                                <th>Updated</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                <tr>
                                    <td>
                                        <div class="ui-table-primary">{{ $project->name }}</div>
                                        <div class="ui-table-secondary">{{ $project->project_number }}</div>
                                    </td>
                                    <td>
                                        <div class="ui-table-primary">{{ $project->customer->name }}</div>
                                        <div class="ui-table-secondary">{{ $project->customer->customer_number }}</div>
                                    </td>
                                    <td>
                                        @if ($project->loadAnalysis)
                                            <span class="ui-status ui-status-active">Started</span>
                                        @else
                                            <span class="ui-status ui-status-pending">Not Started</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($project->load_appliances_count) }}</td>
                                    <td>{{ number_format($project->electricity_bills_count) }}</td>
                                    <td>{{ $project->loadAnalysis?->updated_at?->format('d M Y, H:i') ?? 'Not started' }}</td>
                                    <td>
                                        <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="ui-table-footer">
                    @include('partials.pagination', ['paginator' => $projects])
                </div>
            @endif
        </section>
    </div>
@endsection
