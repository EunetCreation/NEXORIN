@extends('layouts.app')

@section('title', 'System Recommendations')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'System Recommendation')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Explainable decision support</div>
                <h2 class="ui-page-title">Project System Recommendations</h2>
                <p class="ui-page-description">
                    Compare on-grid and hybrid suitability using verified project data, transparent criterion weights, constraints, and documented engineering decisions.
                </p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Project Directory</a>
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="System recommendation statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PJ</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['projects']) }}</strong>
                <span class="ui-stat-label">Available Projects</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">RN</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['generated']) }}</strong>
                <span class="ui-stat-label">Recommendations Run</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">OG</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['on_grid']) }}</strong>
                <span class="ui-stat-label">On-grid Selected</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">HY</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['hybrid']) }}</strong>
                <span class="ui-stat-label">Hybrid Selected</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="System recommendation filters">
            <form method="GET" action="{{ route('companies.recommendations.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="recommendation-search">Search projects</label>
                    <input
                        id="recommendation-search"
                        class="ui-input"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Project number, name, city, or customer"
                    >
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="recommendation-status">Result status</label>
                    <select id="recommendation-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                        <option value="not_started" @selected($filters['status'] === 'not_started')>Not started</option>
                        <option value="incomplete" @selected($filters['status'] === 'incomplete')>Source data incomplete</option>
                        <option value="generated" @selected($filters['status'] === 'generated')>Generated</option>
                        <option value="review_required" @selected($filters['status'] === 'review_required')>Review required</option>
                        <option value="overridden" @selected($filters['status'] === 'overridden')>Engineer override</option>
                    </select>
                </div>

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                    <a href="{{ route('companies.recommendations.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
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
                                <th>Current Result</th>
                                <th>On-grid</th>
                                <th>Hybrid</th>
                                <th>Status</th>
                                <th>Generated</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                @php($result = $project->systemRecommendation)
                                <tr>
                                    <td>
                                        <div class="ui-table-primary">{{ $project->name }}</div>
                                        <div class="ui-table-secondary">{{ $project->project_number }}</div>
                                    </td>
                                    <td>
                                        <div class="ui-table-primary">{{ $project->customer->name }}</div>
                                        <div class="ui-table-secondary">{{ $project->customer->customer_number }}</div>
                                    </td>
                                    <td>{{ $result?->recommended_system_label ?? 'Not generated' }}</td>
                                    <td>{{ $result?->on_grid_score !== null ? number_format((float) $result->on_grid_score, 1) : '—' }}</td>
                                    <td>{{ $result?->hybrid_score !== null ? number_format((float) $result->hybrid_score, 1) : '—' }}</td>
                                    <td>
                                        @if ($result)
                                            <span class="ui-status {{ in_array($result->result_status, ['generated', 'overridden'], true) ? 'ui-status-active' : ($result->result_status === 'incomplete' ? 'ui-status-inactive' : 'ui-status-pending') }}">
                                                {{ $result->result_status_label }}
                                            </span>
                                        @else
                                            <span class="ui-status ui-status-pending">Not Started</span>
                                        @endif
                                    </td>
                                    <td>{{ $result?->generated_at?->format('d M Y, H:i') ?? 'Not run' }}</td>
                                    <td>
                                        <a href="{{ route('companies.recommendations.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Open</a>
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
