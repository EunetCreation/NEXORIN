@extends('layouts.app')

@section('title', 'Roof & Site Survey')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Roof & Site Survey')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Site readiness foundation</div>
                <h2 class="ui-page-title">Project Roof & Site Surveys</h2>
                <p class="ui-page-description">
                    Record roof geometry, usable area, shading, access, safety, and structural review requirements before system recommendation.
                </p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Project Directory</a>
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="Roof and site survey statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PJ</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['projects']) }}</strong>
                <span class="ui-stat-label">Available Projects</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">SV</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['started']) }}</strong>
                <span class="ui-stat-label">Surveys Started</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">RD</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['ready']) }}</strong>
                <span class="ui-stat-label">Ready Surveys</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">OK</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['verified']) }}</strong>
                <span class="ui-stat-label">Verified Surveys</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="Roof and site survey filters">
            <form method="GET" action="{{ route('companies.roof-site.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="roof-site-search">Search projects</label>
                    <input
                        id="roof-site-search"
                        class="ui-input"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Project number, name, city, or customer"
                    >
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="roof-site-status">Survey status</label>
                    <select id="roof-site-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                        <option value="not_started" @selected($filters['status'] === 'not_started')>Not started</option>
                        <option value="incomplete" @selected($filters['status'] === 'incomplete')>Incomplete</option>
                        <option value="ready" @selected($filters['status'] === 'ready')>Ready for verification</option>
                        <option value="verified" @selected($filters['status'] === 'verified')>Verified</option>
                    </select>
                </div>

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                    <a href="{{ route('companies.roof-site.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
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
                                <th>Location</th>
                                <th>Survey Date</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                @php($quality = $project->roofSiteSurvey?->quality_status ?? 'not_started')
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
                                        <div class="ui-table-primary">{{ $project->city ?: 'Not set' }}</div>
                                        <div class="ui-table-secondary">{{ $project->province ?: 'Location incomplete' }}</div>
                                    </td>
                                    <td>{{ $project->roofSiteSurvey?->survey_date?->format('d M Y') ?? 'Not recorded' }}</td>
                                    <td>
                                        <span class="ui-status {{ $quality === 'verified' ? 'ui-status-active' : ($quality === 'incomplete' ? 'ui-status-inactive' : 'ui-status-pending') }}">
                                            {{ ucfirst(str_replace('_', ' ', $quality)) }}
                                        </span>
                                    </td>
                                    <td>{{ $project->roofSiteSurvey?->updated_at?->format('d M Y, H:i') ?? 'Not started' }}</td>
                                    <td>
                                        <a href="{{ route('companies.roof-site.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Open</a>
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
