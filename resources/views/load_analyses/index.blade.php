@extends('layouts.app')

@section('title', 'Load Analyses')
@section('page-eyebrow', 'Load engineering')
@section('page-heading', 'Load Analyses')
@section('force-theme', 'dark')
@section('body-class', 'page-load-analysis-index-v6')
@section('disable-remote-fonts', '1')

@php
    $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] !== 'all' ? 1 : 0);
    $projectRange = $projects->total() === 0
        ? 'No current projects'
        : (($projects->firstItem() ?? 0).'–'.($projects->lastItem() ?? 0).' of '.number_format($projects->total()));

    $formatEnergy = static function ($value): string {
        if ($value === null) return '—';
        $value = (float) $value;
        if (abs($value) >= 1_000_000) return number_format($value / 1_000_000, 2).' GWh';
        if (abs($value) >= 1_000) return number_format($value / 1_000, 1).' MWh';
        return number_format($value, 1).' kWh';
    };

    $formatPower = static function ($value): string {
        if ($value === null) return '—';
        $value = (float) $value;
        if (abs($value) >= 1_000) return number_format($value / 1_000, 2).' MW';
        return number_format($value, $value >= 100 ? 0 : 1).' kW';
    };
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001017.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.017" rel="stylesheet">
    <link href="{{ asset('css/nexorin-load-analysis-index-v6.css') }}?v=017a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-load-analysis-index-v6.js') }}?v=017a" defer></script>
@endpush

@section('content')
<main class="nx17" data-nx17-page>
    <header class="nx17-head" data-nx17-reveal>
        <div class="nx17-head-copy">
            <span class="nx17-kicker">Load engineering · project registry</span>
            <h2>Load Engineering Registry</h2>
            <p>
                Review current project load models, recorded equipment and bill inputs, deterministic summary values, and existing downstream timestamp flags. Current appliance modelling remains preliminary unless stronger source evidence exists.
            </p>
        </div>

        <div class="nx17-head-actions">
            <span class="nx17-scope-chip"><i></i>{{ $company->name }}</span>
            <a href="{{ route('companies.projects.index', $company) }}" class="nx17-link-button">
                <span>Project Directory</span>
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
            </a>
        </div>
    </header>

    <section class="nx17-command-band" aria-label="Load registry context" data-nx17-surface data-nx17-reveal>
        <article class="nx17-command-metric">
            <span>Current projects</span>
            <strong>{{ number_format($stats['projects']) }}</strong>
            <small>Non-archived projects visible to this user.</small>
        </article>
        <a href="{{ route('companies.load-analyses.index', ['company' => $company, 'status' => 'available']) }}" class="nx17-command-metric is-cyan">
            <span>Load analyses</span>
            <strong>{{ number_format($stats['analyses']) }}</strong>
            <small>Projects with a persisted current Load Analysis.</small>
        </a>
        <a href="{{ route('companies.load-analyses.index', ['company' => $company, 'status' => 'equipment_model']) }}" class="nx17-command-metric is-green">
            <span>Equipment models</span>
            <strong>{{ number_format($stats['with_appliances']) }}</strong>
            <small>Projects with one or more equipment load groups.</small>
        </a>
        <a href="{{ route('companies.load-analyses.index', ['company' => $company, 'status' => 'no_analysis']) }}" class="nx17-command-metric is-amber">
            <span>Need load analysis</span>
            <strong>{{ number_format($stats['needs_analysis']) }}</strong>
            <small>Current projects without a persisted Load Analysis.</small>
        </a>
    </section>

    <div class="nx17-workspace">
        <section class="nx17-registry" data-nx17-surface data-nx17-reveal>
            <header class="nx17-registry-head">
                <div>
                    <span class="nx17-section-kicker">Project load models</span>
                    <h3>Current Load Analyses</h3>
                    <p>Annualized energy and peak demand are shown only where the current stored load model can derive them.</p>
                </div>
                <div class="nx17-result-meta">
                    @if ($activeFilters > 0)
                        <span class="nx17-filter-count">{{ $activeFilters }} active {{
                            \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                    @endif
                    <span>{{ $projectRange }}</span>
                </div>
            </header>

            <form method="GET" action="{{ route('companies.load-analyses.index', $company) }}" class="nx17-filters" data-nx17-filter-form>
                <div class="nx17-field nx17-field--search">
                    <label for="load-analysis-search">Search</label>
                    <div class="nx17-control-wrap">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="4.7"/><path d="m12.1 12.1 4 4"/></svg>
                        <input id="load-analysis-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Project number, project name, or customer">
                    </div>
                </div>

                <div class="nx17-field">
                    <label for="load-analysis-status">Registry state</label>
                    <select id="load-analysis-status" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All current projects</option>
                        <option value="no_analysis" @selected($filters['status'] === 'no_analysis')>No Load Analysis</option>
                        <option value="available" @selected($filters['status'] === 'available')>Load Analysis available</option>
                        <option value="input_required" @selected($filters['status'] === 'input_required')>Input required</option>
                        <option value="equipment_model" @selected($filters['status'] === 'equipment_model')>Equipment model recorded</option>
                        <option value="bill_reference" @selected($filters['status'] === 'bill_reference')>Bill reference recorded</option>
                    </select>
                </div>

                <div class="nx17-filter-actions">
                    <button type="submit" class="nx17-button nx17-button--primary">Apply</button>
                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.load-analyses.index', $company) }}" class="nx17-button nx17-button--ghost">Reset</a>
                    @endif
                </div>
            </form>

            @if ($projects->isEmpty())
                <div class="nx17-empty">
                    <span class="nx17-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M5 5h14v14H5z"/><path d="M8 9h8M8 13h5"/></svg>
                    </span>
                    @if ((int) $stats['projects'] === 0)
                        <h4>No current projects available</h4>
                        <p>This company has no non-archived project available to the current Load Analysis registry.</p>
                        <a href="{{ route('companies.projects.index', $company) }}" class="nx17-inline-link">Open Project Directory</a>
                    @else
                        <h4>No projects match these filters</h4>
                        <p>The registry is server-filtered. Clear the current search or state filter to review the remaining authorized projects.</p>
                        <a href="{{ route('companies.load-analyses.index', $company) }}" class="nx17-inline-link">Clear filters</a>
                    @endif
                </div>
            @else
                <div class="nx17-table-shell">
                    <div class="nx17-table-head nx17-grid" aria-hidden="true">
                        <span>Project / customer</span>
                        <span>Load state</span>
                        <span>Annualized energy</span>
                        <span>Peak demand</span>
                        <span>Inputs</span>
                        <span>Freshness</span>
                        <span>Action</span>
                    </div>

                    <div class="nx17-table-body">
                        @foreach ($projects as $project)
                            @php
                                $row = $registryRows->get($project->getKey());
                                $customerName = $project->customer?->name ?: 'Customer not recorded';
                                $customerNumber = $project->customer?->customer_number ?: 'Customer reference not recorded';
                            @endphp
                            <article class="nx17-row nx17-grid" data-nx17-row>
                                <div class="nx17-project-cell">
                                    <span class="nx17-project-glyph" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M4 18.5h16"/><path d="M6 18.5V9.6l5.9-4.1 6.1 4.1v8.9"/><path d="M9 13h6M12 10v6"/></svg>
                                    </span>
                                    <div class="nx17-project-copy">
                                        <strong title="{{ $project->name }}">{{ $project->name }}</strong>
                                        <span class="nx17-mono">{{ $project->project_number }}</span>
                                        <small title="{{ $customerName }}">{{ $customerName }} · {{ $customerNumber }}</small>
                                    </div>
                                </div>

                                <div class="nx17-state-cell">
                                    <span class="nx17-state is-{{ $row['state'] }}"><i></i>{{ $row['state_label'] }}</span>
                                    <strong>{{ $row['basis_label'] }}</strong>
                                    <small>{{ $project->proposed_system_label }} · {{ $project->building_type_label }}</small>
                                </div>

                                <div class="nx17-value-cell">
                                    <strong>{{ $formatEnergy($row['annual_energy_kwh']) }}</strong>
                                    <small>{{ $row['annual_energy_kwh'] === null ? 'Insufficient load input' : 'Current annualized summary' }}</small>
                                </div>

                                <div class="nx17-value-cell">
                                    <strong>{{ $formatPower($row['peak_demand_kw']) }}</strong>
                                    <small>{{ $row['peak_basis'] }}</small>
                                </div>

                                <div class="nx17-input-cell">
                                    <strong>{{ $row['input_label'] }}</strong>
                                    <small>
                                        @if ($row['critical_count'] > 0)
                                            {{ number_format($row['critical_count']) }} marked critical
                                        @else
                                            No critical equipment recorded
                                        @endif
                                    </small>
                                </div>

                                <div class="nx17-freshness-cell">
                                    @if ($row['updated_at'])
                                        <strong>{{ $row['updated_at']->diffForHumans() }}</strong>
                                        <small>{{ $row['updated_at']->format('d M Y, H:i') }}</small>
                                    @else
                                        <strong>Not recorded</strong>
                                        <small>No Load Analysis timestamp</small>
                                    @endif
                                    <span class="nx17-downstream is-{{ $row['downstream_state'] }}" title="{{ $row['downstream_detail'] }}">
                                        <i></i>{{ $row['downstream_label'] }}
                                    </span>
                                </div>

                                <div class="nx17-action-cell">
                                    @if (($row['action_method'] ?? 'get') === 'post')
                                        <form method="POST" action="{{ $row['action_url'] }}" class="nx17-row-action-form">
                                            @csrf
                                            <button type="submit" class="nx17-row-action" aria-label="{{ $row['action_label'] }} for {{ $project->name }}">
                                                <span>{{ $row['action_label'] }}</span>
                                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ $row['action_url'] }}" class="nx17-row-action" aria-label="{{ $row['action_label'] }} for {{ $project->name }}">
                                            <span>{{ $row['action_label'] }}</span>
                                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <footer class="nx17-pagination">
                    @include('partials.pagination', ['paginator' => $projects])
                </footer>
            @endif
        </section>

        <aside class="nx17-rail" aria-label="Load registry interpretation">
            <section class="nx17-rail-card" data-nx17-surface data-nx17-reveal>
                <span class="nx17-section-kicker">Registry basis</span>
                <h3>Current portfolio coverage</h3>
                <div class="nx17-rail-stats">
                    <div><span>Analysis available</span><strong>{{ number_format($stats['analyses']) }}</strong></div>
                    <div><span>Input required</span><strong>{{ number_format($stats['input_required']) }}</strong></div>
                    <div><span>Bill reference</span><strong>{{ number_format($stats['with_bills']) }}</strong></div>
                    <div><span>Equipment model</span><strong>{{ number_format($stats['with_appliances']) }}</strong></div>
                </div>
            </section>

            <section class="nx17-rail-card" data-nx17-surface data-nx17-reveal>
                <span class="nx17-section-kicker">Model boundaries</span>
                <h3>How to read this index</h3>
                <ul class="nx17-basis-list">
                    <li><i class="is-cyan"></i><span><strong>Equipment model</strong> uses the current repeated daily operating window and Usage factor.</span></li>
                    <li><i class="is-blue"></i><span><strong>Annualized energy</strong> is derived from the current stored load model, not a measured interval dataset.</span></li>
                    <li><i class="is-amber"></i><span><strong>Peak demand</strong> is modeled from the existing 24-hour profile or uses recorded bill peak averages when that is the available basis.</span></li>
                    <li><i class="is-purple"></i><span><strong>Critical load</strong> is a user classification and does not imply guaranteed backup or life-safety service.</span></li>
                </ul>
            </section>

            <section class="nx17-rail-card nx17-rail-card--attention" data-nx17-surface data-nx17-reveal>
                <span class="nx17-section-kicker">Downstream freshness</span>
                <h3>Timestamp-based review only</h3>
                <p>
                    “Downstream review required” reuses the current Recommendation and Technical Calculation freshness checks. It is a conservative timestamp signal, not a full dependency graph.
                </p>
                <a href="{{ route('companies.load-analyses.index', ['company' => $company, 'status' => 'input_required']) }}" class="nx17-rail-link">
                    Review projects needing inputs
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
                </a>
            </section>
        </aside>
    </div>
</main>
@endsection
