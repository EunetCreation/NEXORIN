@extends('layouts.app')

@section('title', 'Technical Calculations')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Technical Calculation')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">PV, inverter, battery, string, cable, and protection sizing</div>
                <h2 class="ui-page-title">Technical Calculation Directory</h2>
                <p class="ui-page-description">
                    Convert verified load, solar, roof, and recommendation data into a traceable preliminary electrical design.
                </p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Project Directory</a>
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="Technical calculation statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PJ</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['projects']) }}</strong>
                <span class="ui-stat-label">Available Projects</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">RN</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['calculated']) }}</strong>
                <span class="ui-stat-label">Calculations Run</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">DE</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['detailed']) }}</strong>
                <span class="ui-stat-label">Detailed Results</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">VF</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['verified']) }}</strong>
                <span class="ui-stat-label">Engineer Verified</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="Technical calculation filters">
            <form method="GET" action="{{ route('companies.technical-calculations.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="technical-calculation-search">Search projects</label>
                    <input
                        id="technical-calculation-search"
                        class="ui-input"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Project number, name, city, or customer"
                    >
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="technical-calculation-status">Calculation status</label>
                    <select id="technical-calculation-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                        <option value="not_started" @selected($filters['status'] === 'not_started')>Not calculated</option>
                        <option value="preliminary" @selected($filters['status'] === 'preliminary')>Preliminary</option>
                        <option value="detailed" @selected($filters['status'] === 'detailed')>Detailed</option>
                        <option value="verified" @selected($filters['status'] === 'verified')>Verified</option>
                    </select>
                </div>

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                    <a href="{{ route('companies.technical-calculations.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
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
                                <th>Calculation Status</th>
                                <th>Last Run</th>
                                <th>Verification</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                @php($calculation = $project->technicalCalculation)
                                <tr>
                                    <td>
                                        <div class="ui-table-primary">{{ $project->name }}</div>
                                        <div class="ui-table-secondary">{{ $project->project_number }}</div>
                                    </td>
                                    <td>
                                        <div class="ui-table-primary">{{ $project->customer?->name ?? 'Customer unavailable' }}</div>
                                        <div class="ui-table-secondary">{{ $project->customer?->customer_number ?? '—' }}</div>
                                    </td>
                                    <td>
                                        <div class="ui-table-primary">{{ $project->city ?: 'Not set' }}</div>
                                        <div class="ui-table-secondary">{{ $project->province ?: 'Location incomplete' }}</div>
                                    </td>
                                    <td>
                                        @if (! $calculation?->calculated_at)
                                            <span class="ui-status ui-status-pending">Not Calculated</span>
                                        @elseif ($calculation->result_status === 'verified')
                                            <span class="ui-status ui-status-active">Engineer Verified</span>
                                        @elseif ($calculation->result_status === 'detailed')
                                            <span class="ui-status ui-status-active">Detailed</span>
                                        @else
                                            <span class="ui-status ui-status-pending">Preliminary</span>
                                        @endif
                                    </td>
                                    <td>{{ $calculation?->calculated_at?->format('d M Y, H:i') ?? 'Not run' }}</td>
                                    <td>{{ $calculation?->verified_at?->format('d M Y, H:i') ?? 'Pending' }}</td>
                                    <td>
                                        <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Open</a>
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
