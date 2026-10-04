@extends('layouts.app')

@section('title', 'Location & Solar Resource')
@section('page-eyebrow', 'Location & solar')
@section('page-heading', 'Location & Solar Resource')
@section('force-theme', 'dark')
@section('body-class', 'page-location-solar-index-v6')
@section('disable-remote-fonts', '1')

@php
    $activeStatus = $filters['status'];
    $activeSearch = $filters['search'];
    $activeFilterCount = ($activeStatus !== 'all' ? 1 : 0) + ($activeSearch !== '' ? 1 : 0);
    $projectRange = $projects->total() === 0
        ? 'No matching projects'
        : (($projects->firstItem() ?? 0).'–'.($projects->lastItem() ?? 0).' of '.number_format($projects->total()));
    $mapShown = $mapPoints->count();
    $statusTabs = [
        'all' => ['label' => 'All Projects', 'count' => $stats['projects']],
        'no_dataset' => ['label' => 'No Dataset', 'count' => $stats['no_dataset']],
        'input_required' => ['label' => 'Input Required', 'count' => $stats['input_required']],
        'complete' => ['label' => 'Dataset Complete', 'count' => $stats['complete']],
        'reviewed' => ['label' => 'Dataset Reviewed', 'count' => $stats['reviewed']],
    ];
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001019.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.019" rel="stylesheet">
    <link href="{{ asset('css/nexorin-location-solar-index-v6.css') }}?v=019a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-location-solar-index-v6.js') }}?v=019a" defer></script>
@endpush

@section('content')
<main
    class="nx19"
    data-nx19-page
    data-map-url="{{ asset('data/indonesia-country.geojson') }}"
    data-reduced-motion="respect"
>
    <script type="application/json" id="nx19-map-points">@json($mapPoints)</script>

    <header class="nx19-head" data-nx19-reveal>
        <div class="nx19-head-copy">
            <nav class="nx19-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('companies.projects.index', $company) }}">Projects</a>
                <span aria-hidden="true">/</span>
                <span>Location &amp; Solar Resource</span>
            </nav>
            <span class="nx19-kicker"><i aria-hidden="true"></i>Solar resource registry</span>
            <h2>Location &amp; Solar Resource</h2>
            <p>Portfolio view of recorded project coordinates, stored monthly solar-resource datasets, source provenance, and the current dataset review state.</p>
        </div>
        <div class="nx19-head-actions">
            <span class="nx19-scope-chip"><i aria-hidden="true"></i>{{ $company->name }}</span>
            <a href="{{ route('companies.projects.index', $company) }}" class="nx19-button is-secondary">
                <span>Project Directory</span>
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
            </a>
        </div>
    </header>

    <section class="nx19-metrics" aria-label="Solar dataset registry status" data-nx19-reveal>
        <article class="nx19-metric">
            <span>Active Projects</span>
            <strong>{{ number_format($stats['projects']) }}</strong>
            <small>Accessible non-archived project records.</small>
        </article>
        <article class="nx19-metric is-neutral">
            <span>No Dataset</span>
            <strong>{{ number_format($stats['no_dataset']) }}</strong>
            <small>No persisted solar dataset has been started.</small>
        </article>
        <article class="nx19-metric is-amber">
            <span>Input Required</span>
            <strong>{{ number_format($stats['input_required']) }}</strong>
            <small>Stored dataset is not complete for the current workflow.</small>
        </article>
        <article class="nx19-metric is-cyan">
            <span>Dataset Complete</span>
            <strong>{{ number_format($stats['complete']) }}</strong>
            <small>Required current fields and 12 monthly records are complete.</small>
        </article>
        <article class="nx19-metric is-green">
            <span>Dataset Reviewed</span>
            <strong>{{ number_format($stats['reviewed']) }}</strong>
            <small>Current recorded dataset passed the existing authorized review gate.</small>
        </article>
    </section>

    <nav class="nx19-status-tabs" aria-label="Filter solar resource registry" data-nx19-reveal>
        @foreach ($statusTabs as $statusValue => $tab)
            <a
                href="{{ route('companies.location-solar.index', ['company' => $company, 'status' => $statusValue, 'search' => $activeSearch !== '' ? $activeSearch : null]) }}"
                class="nx19-status-tab {{ $activeStatus === $statusValue ? 'is-active' : '' }}"
                @if ($activeStatus === $statusValue) aria-current="page" @endif
            >
                <span>{{ $tab['label'] }}</span>
                <strong>{{ number_format($tab['count']) }}</strong>
            </a>
        @endforeach
    </nav>

    <div class="nx19-layout">
        <div class="nx19-main-column">
            <section class="nx19-card nx19-map-card" data-nx19-surface data-nx19-reveal>
                <header class="nx19-card-head nx19-map-head">
                    <div>
                        <span class="nx19-section-kicker">Geographic overview</span>
                        <h3>Recorded Project Coordinates</h3>
                        <p>Only persisted latitude and longitude pairs are plotted. Missing locations are never geocoded or replaced with centroids.</p>
                    </div>
                    <div class="nx19-map-count">
                        <strong>{{ number_format($mappedTotal) }}</strong>
                        <span>mapped in current view</span>
                    </div>
                </header>

                <div class="nx19-map-shell" data-nx19-map-shell>
                    <div class="nx19-map" data-nx19-map role="img" aria-label="Indonesia map showing projects with recorded coordinate pairs"></div>
                    <div class="nx19-map-fallback" data-nx19-map-fallback hidden>
                        <strong>Map visualization unavailable</strong>
                        <span>The project registry below remains authoritative for recorded coordinate states.</span>
                    </div>

                    @if ($mappedTotal === 0)
                        <div class="nx19-map-empty" data-nx19-map-empty>
                            <span class="nx19-map-empty-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                            </span>
                            <strong>No recorded coordinate pairs in this view</strong>
                            <p>Projects remain in the registry without a map marker until both latitude and longitude are stored.</p>
                        </div>
                    @endif

                    <div class="nx19-map-legend" aria-label="Map marker status legend">
                        <span><i class="is-no-dataset"></i>No Dataset</span>
                        <span><i class="is-input-required"></i>Input Required</span>
                        <span><i class="is-complete"></i>Dataset Complete</span>
                        <span><i class="is-reviewed"></i>Dataset Reviewed</span>
                    </div>

                    <button type="button" class="nx19-map-reset" data-nx19-map-reset aria-label="Reset map view">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M16 7a6.5 6.5 0 1 0 .5 5M16 3v4h-4"/></svg>
                        <span>Reset</span>
                    </button>
                </div>

                @if ($mappedTotal > $mapShown)
                    <footer class="nx19-map-note">
                        Showing the {{ number_format($mapShown) }} most recently updated recorded coordinate pairs of {{ number_format($mappedTotal) }} in this filtered view.
                    </footer>
                @endif
            </section>

        </div>

        <aside class="nx19-rail" aria-label="Location and solar registry context" data-nx19-reveal>
            <section class="nx19-rail-card">
                <span class="nx19-section-kicker">Registry Basis</span>
                <h3>Stored project records</h3>
                <p>This registry reads accessible non-archived projects, recorded coordinates, stored monthly resource metadata, and the current dataset review state.</p>
            </section>

            <section class="nx19-rail-card nx19-coverage-card">
                <header>
                    <div>
                        <span class="nx19-section-kicker">Coordinate Coverage</span>
                        <h3>Recorded coordinate pairs</h3>
                    </div>
                    <strong>{{ $stats['coordinate_coverage_percent'] }}%</strong>
                </header>
                <div class="nx19-coverage-track" aria-hidden="true"><span style="--nx19-coverage: {{ $stats['coordinate_coverage_percent'] }}%"></span></div>
                <div class="nx19-rail-pair"><span>Recorded</span><strong>{{ number_format($stats['coordinates']) }}</strong></div>
                <div class="nx19-rail-pair"><span>Missing / incomplete pair</span><strong>{{ number_format($stats['missing_coordinates']) }}</strong></div>
                <small>Coverage only; this is not a coordinate-accuracy or survey-confidence score.</small>
            </section>

            <section class="nx19-rail-card">
                <span class="nx19-section-kicker">Dataset Status</span>
                <h3>Current workflow distribution</h3>
                <div class="nx19-status-list">
                    <span><i class="is-no-dataset"></i><b>No Dataset</b><strong>{{ number_format($stats['no_dataset']) }}</strong></span>
                    <span><i class="is-input-required"></i><b>Input Required</b><strong>{{ number_format($stats['input_required']) }}</strong></span>
                    <span><i class="is-complete"></i><b>Dataset Complete</b><strong>{{ number_format($stats['complete']) }}</strong></span>
                    <span><i class="is-reviewed"></i><b>Dataset Reviewed</b><strong>{{ number_format($stats['reviewed']) }}</strong></span>
                </div>
            </section>

        </aside>
    </div>

    <section class="nx19-command-band" aria-label="Solar resource registry guidance" data-nx19-surface data-nx19-reveal>
        <div class="nx19-command-segment is-gaps">
            <span class="nx19-section-kicker">Required Data Gaps</span>
            <h3>Current record coverage</h3>
            <div class="nx19-command-counts">
                <span><b>Coordinates</b><strong>{{ number_format($stats['missing_coordinates']) }}</strong></span>
                <span><b>Source name</b><strong>{{ number_format($stats['missing_source_name']) }}</strong></span>
                <span><b>Monthly set</b><strong>{{ number_format($stats['incomplete_monthly']) }}</strong></span>
            </div>
            <small>Counts use current stored records. Missing values are never substituted.</small>
        </div>

        <div class="nx19-command-segment is-next">
            <span class="nx19-section-kicker">Next Engineering Action</span>
            @if ($nextAction)
                <div class="nx19-command-next-head">
                    <span class="nx19-next-state">{{ $nextAction['state_label'] }}</span>
                    <small>{{ $nextAction['project_number'] ?: 'Project reference not recorded' }}</small>
                </div>
                <h3>{{ $nextAction['project_name'] }}</h3>
                <p>{{ $nextAction['copy'] }}</p>
                @if ($nextAction['method'] === 'post')
                    <form method="POST" action="{{ $nextAction['url'] }}" data-nx19-start>
                        @csrf
                        <button type="submit" class="nx19-command-action">
                            <span>{{ $nextAction['label'] }}</span>
                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
                        </button>
                    </form>
                @else
                    <a href="{{ $nextAction['url'] }}" class="nx19-command-action" @if (str_contains($nextAction['url'], 'location-solar-data')) data-nx19-open @endif>
                        <span>{{ $nextAction['label'] }}</span>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
                    </a>
                @endif
            @else
                <h3>No current projects</h3>
                <p>No accessible non-archived project is available for a location and solar dataset action.</p>
            @endif
        </div>

        <div class="nx19-command-segment is-basis">
            <span class="nx19-section-kicker">Current Resource Basis</span>
            <h3>Monthly recorded dataset</h3>
            <p>Current registry scope uses recorded coordinates, source metadata, monthly GHI, optional POA, Peak Sun Hours, and ambient temperature.</p>
            <small>Advanced time-series resource modeling is outside this dataset scope.</small>
        </div>
    </section>

    <section class="nx19-card nx19-registry" id="project-registry" data-nx19-surface data-nx19-reveal>
        <header class="nx19-registry-head">
            <div>
                <span class="nx19-section-kicker">Project registry</span>
                <h3>Stored Location &amp; Solar Datasets</h3>
                <p>Search accessible projects and open only the persisted dataset or explicit start workflow that currently exists.</p>
            </div>
            <span class="nx19-range">{{ $projectRange }}</span>
        </header>

        <form method="GET" action="{{ route('companies.location-solar.index', $company) }}" class="nx19-filter" data-nx19-filter>
            <input type="hidden" name="status" value="{{ $activeStatus }}">
            <label class="nx19-search" for="nx19-search">
                <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.2"/><path d="m12.5 12.5 4 4"/></svg>
                <span class="sr-only">Search location and solar registry</span>
                <input
                    id="nx19-search"
                    type="search"
                    name="search"
                    value="{{ $activeSearch }}"
                    placeholder="Search project, customer, city, province, or source"
                    autocomplete="off"
                    data-nx19-search
                >
                @if ($activeSearch !== '')
                    <a href="{{ route('companies.location-solar.index', ['company' => $company, 'status' => $activeStatus]) }}" class="nx19-search-clear" aria-label="Clear search">×</a>
                @endif
            </label>
            <button type="submit" class="nx19-button is-primary">
                <span>Apply Search</span>
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
            </button>
            @if ($activeFilterCount > 0)
                <a href="{{ route('companies.location-solar.index', $company) }}" class="nx19-filter-reset">Reset {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }}</a>
            @endif
        </form>

        @if ($projects->isEmpty())
            <div class="nx19-empty">
                <span class="nx19-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/><path d="M17.5 16.5 21 20"/><circle cx="15.5" cy="14.5" r="3.2"/></svg>
                </span>
                <div>
                    <strong>No projects match this registry view</strong>
                    <p>Change the status filter or search query. No synthetic solar dataset is created to fill an empty result.</p>
                </div>
                <a href="{{ route('companies.location-solar.index', $company) }}" class="nx19-button is-secondary">Reset Registry</a>
            </div>
        @else
            <div class="nx19-table-scroll" data-nx19-table-scroll>
                <table class="nx19-table">
                    <caption class="sr-only">Accessible current projects and their recorded location and solar-resource dataset state</caption>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Customer</th>
                            <th>Location</th>
                            <th>Coordinates</th>
                            <th>Source</th>
                            <th>Monthly Data</th>
                            <th>Dataset State</th>
                            <th>Updated</th>
                            <th><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            @php
                                $row = $registryRows->get((int) $project->getKey());
                                $customerName = $project->customer?->name ?: 'Not Recorded';
                                $customerNumber = $project->customer?->customer_number ?: 'Customer reference not recorded';
                                $locationPrimary = collect([$project->city, $project->province])->filter()->implode(', ');
                            @endphp
                            <tr data-nx19-row data-project-id="{{ $project->getKey() }}" data-state="{{ $row['state'] }}">
                                <td>
                                    <div class="nx19-project-cell">
                                        <span class="nx19-project-mark is-{{ $row['state'] }}" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M3 19h18"/></svg>
                                        </span>
                                        <div>
                                            <a href="{{ route('companies.projects.show', [$company, $project]) }}">{{ $project->name }}</a>
                                            <span>{{ $project->project_number ?: 'Project reference not recorded' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="nx19-stack-cell">
                                        <strong title="{{ $customerName }}">{{ $customerName }}</strong>
                                        <span>{{ $customerNumber }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="nx19-stack-cell">
                                        <strong>{{ $locationPrimary !== '' ? $locationPrimary : 'Not Recorded' }}</strong>
                                        <span title="{{ $project->site_address }}">{{ $project->site_address ?: 'Site address not recorded' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="nx19-coordinate-cell is-{{ $row['coordinates_state'] }}">
                                        <span class="nx19-coordinate-state"><i></i>{{ $row['coordinates_label'] }}</span>
                                        @if ($row['coordinate_text'])
                                            <code>{{ $row['coordinate_text'] }}</code>
                                        @else
                                            <small>No map marker</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="nx19-stack-cell nx19-source-cell">
                                        <strong>{{ $row['source_type_label'] }}</strong>
                                        <span title="{{ $row['source_name'] }}">{{ $row['source_name'] }}</span>
                                        @if ($row['source_reference'] || $row['source_date'])
                                            <small title="{{ $row['source_reference'] }}">
                                                {{ $row['source_date'] ?: 'Source date not recorded' }}
                                                @if ($row['source_reference']) · Reference recorded @endif
                                            </small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="nx19-monthly-cell {{ $row['complete_months'] === 12 ? 'is-complete' : '' }}">
                                        <strong>{{ $row['monthly_label'] }}</strong>
                                        @if ($row['profile_exists'])
                                            <span>Required: GHI · PSH · ambient temperature</span>
                                            <small>Optional POA recorded {{ $row['poa_months'] }}/12 months</small>
                                        @else
                                            <span>Dataset not started</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="nx19-state-cell">
                                        <span class="nx19-state is-{{ $row['state'] }}"><i></i>{{ $row['state_label'] }}</span>
                                        @if ($row['state'] === 'reviewed')
                                            <small title="{{ $row['reviewed_at'] }}">Reviewed by {{ $row['reviewed_by'] }}</small>
                                        @elseif ($row['state'] === 'complete')
                                            <small>Required registry inputs are complete.</small>
                                        @elseif ($row['state'] === 'input_required')
                                            <small>Required registry inputs remain incomplete.</small>
                                        @else
                                            <small>Solar dataset has not been started.</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="nx19-updated-cell">
                                        <strong>{{ $row['updated_label'] }}</strong>
                                        <span>{{ $row['updated_at'] ?: 'Dataset activity not recorded' }}</span>
                                    </div>
                                </td>
                                <td class="nx19-action-cell">
                                    @if ($row['profile_exists'])
                                        <a href="{{ route('companies.location-solar.show', [$company, $project]) }}" class="nx19-row-action" data-nx19-open>
                                            <span>Open dataset</span>
                                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
                                        </a>
                                    @elseif ($row['can_manage'])
                                        <form method="POST" action="{{ route('companies.location-solar.start', [$company, $project]) }}" class="nx19-start-form" data-nx19-start>
                                            @csrf
                                            <button type="submit" class="nx19-row-action is-start">
                                                <span>Start dataset</span>
                                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12"/></svg>
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="nx19-row-action is-muted">
                                            <span>View project</span>
                                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="nx19-pagination">
                <div>
                    <strong>{{ $projectRange }}</strong>
                    <span>Server-side search, status filtering, and pagination.</span>
                </div>
                @include('partials.pagination', ['paginator' => $projects])
            </footer>
        @endif
    </section>

</main>
@endsection
