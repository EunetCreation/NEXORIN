@extends('layouts.app')

@section('title', 'BOM/BOQ')
@section('page-eyebrow', 'Commercial engineering')
@section('page-heading', 'BOM/BOQ')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Technical calculation to material quantity</div>
                <h2 class="ui-page-title">BOM/BOQ Directory</h2>
                <p class="ui-page-description">
                    Generate preliminary material quantities from the latest technical calculation before pricing and quotation.
                </p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('companies.technical-calculations.index', $company) }}" class="ui-button ui-button-secondary">Technical Calculations</a>
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="BOM statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PJ</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['projects']) }}</strong>
                <span class="ui-stat-label">Available Projects</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">TC</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['ready']) }}</strong>
                <span class="ui-stat-label">Ready From Calculation</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">BM</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['generated']) }}</strong>
                <span class="ui-stat-label">BOM/BOQ Drafts</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PR</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['priced'] ?? 0) }}</strong>
                <span class="ui-stat-label">Priced BOQ</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="BOM filters">
            <form method="GET" action="{{ route('companies.boms.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="bom-search">Search projects</label>
                    <input
                        id="bom-search"
                        class="ui-input"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Project number, name, city, or customer"
                    >
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="bom-status">BOM status</label>
                    <select id="bom-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                        <option value="not_generated" @selected($filters['status'] === 'not_generated')>Not generated</option>
                        <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                        <option value="generated" @selected($filters['status'] === 'generated')>Generated</option>
                        <option value="priced" @selected($filters['status'] === 'priced')>Priced</option>
                        <option value="locked" @selected($filters['status'] === 'locked')>Locked</option>
                    </select>
                </div>

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                    <a href="{{ route('companies.boms.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
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
                                <th>Technical Calculation</th>
                                <th>BOM/BOQ Status</th>
                                <th>Pricing</th>
                                <th>Generated At</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                @php($calculation = $project->technicalCalculation)
                                @php($bom = $project->billOfMaterial)
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
                                        @if (! $calculation?->calculated_at)
                                            <span class="ui-status ui-status-pending">Not Ready</span>
                                        @elseif ($calculation->result_status === 'verified')
                                            <span class="ui-status ui-status-active">Verified</span>
                                        @else
                                            <span class="ui-status ui-status-pending">{{ ucfirst($calculation->result_status) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (! $bom)
                                            <span class="ui-status ui-status-pending">Not Generated</span>
                                        @elseif ($bom->status === 'locked')
                                            <span class="ui-status ui-status-active">Locked</span>
                                        @else
                                            <span class="ui-status ui-status-active">Generated</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (! $bom)
                                            —
                                        @elseif ($bom->pricing_status === 'priced')
                                            <span class="ui-status ui-status-active">Priced</span>
                                            <div class="ui-table-secondary">IDR {{ number_format((float) $bom->grand_price_idr, 0, ',', '.') }}</div>
                                        @else
                                            <span class="ui-status ui-status-pending">Not Priced</span>
                                        @endif
                                    </td>
                                    <td>{{ $bom?->generated_at?->format('d M Y, H:i') ?? '—' }}</td>
                                    <td>
                                        <a href="{{ route('companies.boms.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Open</a>
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
