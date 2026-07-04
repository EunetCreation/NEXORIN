@extends('layouts.app')

@section('title', 'Location & Solar Data')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Location & Solar Data')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Solar resource foundation</div>
                <h2 class="ui-page-title">Project Location & Solar Data</h2>
                <p class="ui-page-description">
                    Record project coordinates, source metadata, and monthly solar parameters before roof assessment and system recommendation.
                </p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Project Directory</a>
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="Location and solar statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PJ</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['projects']) }}</strong>
                <span class="ui-stat-label">Available Projects</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">SD</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['started']) }}</strong>
                <span class="ui-stat-label">Datasets Started</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">RD</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['ready']) }}</strong>
                <span class="ui-stat-label">Ready Datasets</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">OK</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['verified']) }}</strong>
                <span class="ui-stat-label">Verified Datasets</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="Location and solar filters">
            <form method="GET" action="{{ route('companies.location-solar.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="solar-search">Search projects</label>
                    <input
                        id="solar-search"
                        class="ui-input"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Project number, name, city, or customer"
                    >
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="solar-status">Dataset status</label>
                    <select id="solar-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                        <option value="not_started" @selected($filters['status'] === 'not_started')>Not started</option>
                        <option value="incomplete" @selected($filters['status'] === 'incomplete')>Incomplete</option>
                        <option value="ready" @selected($filters['status'] === 'ready')>Ready for verification</option>
                        <option value="verified" @selected($filters['status'] === 'verified')>Verified</option>
                    </select>
                </div>

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                    <a href="{{ route('companies.location-solar.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
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
                                <th>Source</th>
                                <th>Quality</th>
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
                                        <div class="ui-table-primary">{{ $project->city ?: 'Not set' }}</div>
                                        <div class="ui-table-secondary">
                                            {{ $project->latitude !== null && $project->longitude !== null ? $project->latitude.', '.$project->longitude : 'Coordinates missing' }}
                                        </div>
                                    </td>
                                    <td>{{ $project->solarProfile?->data_source_label ?? 'Not started' }}</td>
                                    <td>
                                        @php($quality = $project->solarProfile?->quality_status ?? 'not_started')
                                        <span class="ui-status {{ $quality === 'verified' ? 'ui-status-active' : ($quality === 'incomplete' ? 'ui-status-inactive' : 'ui-status-pending') }}">
                                            {{ ucfirst(str_replace('_', ' ', $quality)) }}
                                        </span>
                                    </td>
                                    <td>{{ $project->solarProfile?->updated_at?->format('d M Y, H:i') ?? 'Not started' }}</td>
                                    <td>
                                        <a href="{{ route('companies.location-solar.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Open</a>
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
