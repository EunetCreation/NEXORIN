@extends('layouts.app')

@section('title', 'Load Analysis')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Load Analyses')

@php
    $totalProjects = max((int) $stats['projects'], 1);
    $startedProgress = min(100, ((int) $stats['started'] / $totalProjects) * 100);
    $equipmentProgress = min(100, ((int) $stats['with_appliances'] / $totalProjects) * 100);
    $billProgress = min(100, ((int) $stats['with_bills'] / $totalProjects) * 100);
    $activeFilterCount = collect([
        $filters['search'] !== '' ? $filters['search'] : null,
        $filters['status'] !== 'all' ? $filters['status'] : null,
    ])->filter()->count();
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-load-analysis.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-load-analysis.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxla-directory">
        <header class="nxla-directory-hero" data-nxla-reveal>
            <div class="nxla-directory-heading">
                <span class="nxla-eyebrow">Electrical demand foundation</span>
                <h2>Load analysis portfolio</h2>
                <p>
                    Review appliance schedules, historical bills, and modeled demand profiles before each project moves into PLTS sizing.
                    The directory keeps progress readable without turning the workspace into a dense engineering table.
                </p>
            </div>

            <div class="nxla-directory-context">
                <span class="nxla-chip is-live">{{ number_format($stats['started']) }} analyses started</span>
                <span class="nxla-chip">{{ number_format($stats['with_appliances']) }} with equipment data</span>
                <a href="{{ route('companies.projects.index', $company) }}" class="nxla-context-link">
                    Project Directory
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M5 12h14"/><path d="M13 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </header>

        <section class="nxla-stat-grid" aria-label="Load analysis statistics">
            <article class="nxla-stat-card is-dark" style="--progress: 100%;" data-nxla-reveal>
                <div class="nxla-stat-top">
                    <span class="nxla-stat-icon nxla-stat-icon--portfolio">
                        <svg class="nxla-icon-art" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect class="i-surface" x="4.6" y="5.3" width="14.8" height="13.6" rx="3"/>
                            <path class="i-main" d="M8.1 9.7h7.8M8.1 13.1h4.1"/>
                            <path class="i-accent" d="M7.8 16.2h2.1l1.1-2.8 1.5 4 1.2-2.2h2.4"/>
                            <path class="i-soft" d="M7.6 5.3V4.2h4.6l1.2 1.1"/>
                        </svg>
                    </span>
                    <span class="nxla-chip">Portfolio</span>
                </div>
                <strong data-nxla-counter="{{ $stats['projects'] }}">{{ number_format($stats['projects']) }}</strong>
                <b>Available projects</b>
                <small>Current projects visible in this workspace</small>
                <span class="nxla-stat-progress"><span></span></span>
            </article>

            <a href="{{ route('companies.load-analyses.index', ['company' => $company, 'status' => 'in_progress']) }}" class="nxla-stat-card" style="--progress: {{ $startedProgress }}%;" data-nxla-reveal>
                <div class="nxla-stat-top">
                    <span class="nxla-stat-icon nxla-stat-icon--started">
                        <svg class="nxla-icon-art" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect class="i-surface" x="4.8" y="5.2" width="14.4" height="13.6" rx="3"/>
                            <path class="i-main" d="M7 15.9h10"/>
                            <path class="i-accent" d="M7.2 14.6 9.6 12l2.4 1.9 4.1-4.8 1.7 1.6"/>
                            <path class="i-soft" d="M16.9 9.2h2v2"/>
                            <circle class="i-fill" cx="7.2" cy="14.6" r="1.05"/>
                        </svg>
                    </span>
                    <span class="nxla-chip is-live">Started</span>
                </div>
                <strong data-nxla-counter="{{ $stats['started'] }}">{{ number_format($stats['started']) }}</strong>
                <b>Analyses started</b>
                <small>Projects with an active load-analysis record</small>
                <span class="nxla-stat-progress"><span></span></span>
            </a>

            <article class="nxla-stat-card" style="--progress: {{ $equipmentProgress }}%;" data-nxla-reveal>
                <div class="nxla-stat-top">
                    <span class="nxla-stat-icon nxla-stat-icon--equipment">
                        <svg class="nxla-icon-art" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect class="i-surface" x="7.2" y="7.2" width="9.6" height="9.6" rx="2.6"/>
                            <path class="i-main" d="M9.6 10.1h4.8v4H9.6z"/>
                            <path class="i-soft" d="M7.2 12H5.2M18.8 12h-2M12 7.2V5.2M12 18.8v-2"/>
                            <path class="i-accent" d="M10.3 14.5h1.2l.8-2 1.1 2.8.8-1.4h1"/>
                        </svg>
                    </span>
                    <span class="nxla-chip">Equipment</span>
                </div>
                <strong data-nxla-counter="{{ $stats['with_appliances'] }}">{{ number_format($stats['with_appliances']) }}</strong>
                <b>With equipment data</b>
                <small>Projects already producing a modeled demand profile</small>
                <span class="nxla-stat-progress"><span></span></span>
            </article>

            <article class="nxla-stat-card" style="--progress: {{ $billProgress }}%;" data-nxla-reveal>
                <div class="nxla-stat-top">
                    <span class="nxla-stat-icon nxla-stat-icon--bills">
                        <svg class="nxla-icon-art" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path class="i-surface" d="M7 4.6h8.6l2.2 2.2v12.2l-2-1.2-1.8 1.2-1.8-1.2-1.8 1.2-1.8-1.2-1.8 1.2V4.6Z"/>
                            <path class="i-main" d="M15.6 4.6v3h3M9 10.2h5.2M9 13.1h3.8"/>
                            <path class="i-accent" d="m12.2 15.2 1.4 1.4 2.5-2.8"/>
                        </svg>
                    </span>
                    <span class="nxla-chip">Bills</span>
                </div>
                <strong data-nxla-counter="{{ $stats['with_bills'] }}">{{ number_format($stats['with_bills']) }}</strong>
                <b>With bill history</b>
                <small>Projects that can validate modeled energy against actual use</small>
                <span class="nxla-stat-progress"><span></span></span>
            </article>
        </section>

        <section class="nxla-card nxla-registry" data-nxla-reveal>
            <header class="nxla-registry-head">
                <div>
                    <span class="nxla-eyebrow">Load registry</span>
                    <h3>Project load analyses</h3>
                    <p>Open a project to inspect equipment, bills, settings, and validation quality.</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    @if ($activeFilterCount > 0)
                        <span class="nxla-chip is-warning">{{ $activeFilterCount }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilterCount) }}</span>
                    @endif
                    <span class="nxla-result-count">
                        @if ($projects->total() === 0)
                            No projects
                        @elseif ($projects->hasPages())
                            {{ number_format($projects->firstItem()) }}–{{ number_format($projects->lastItem()) }} of {{ number_format($projects->total()) }}
                        @else
                            Showing {{ number_format($projects->total()) }} {{ \Illuminate\Support\Str::plural('project', $projects->total()) }}
                        @endif
                    </span>
                </div>
            </header>

            <form method="GET" action="{{ route('companies.load-analyses.index', $company) }}" class="nxla-filter-bar" aria-label="Load analysis filters">
                <div class="ui-field">
                    <label class="ui-label" for="load-analysis-search">Search projects</label>
                    <input id="load-analysis-search" class="ui-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Project number, name, or customer">
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="load-analysis-status">Analysis status</label>
                    <select id="load-analysis-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                        <option value="not_started" @selected($filters['status'] === 'not_started')>Not started</option>
                        <option value="in_progress" @selected($filters['status'] === 'in_progress')>Started</option>
                    </select>
                </div>
                <div class="nxla-filter-actions">
                    <button type="submit" class="ui-button ui-button-primary">Apply filters</button>
                    @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                        <a href="{{ route('companies.load-analyses.index', $company) }}" class="ui-button ui-button-secondary">Reset</a>
                    @endif
                </div>
            </form>

            @if ($projects->isEmpty())
                <div class="nxla-empty">
                    <div>
                        <h3>No projects match this view</h3>
                        <p>Create a current project or clear the active filters.</p>
                    </div>
                </div>
            @else
                <div class="nxla-registry-grid nxla-registry-header-row" aria-hidden="true">
                    <span>Project</span>
                    <span>Customer</span>
                    <span>Analysis</span>
                    <span>Equipment</span>
                    <span>Bills</span>
                    <span>Updated</span>
                    <span></span>
                </div>

                @foreach ($projects as $project)
                    @php
                        $buildingType = strtolower((string) ($project->building_type ?? 'residential'));
                        $systemType = strtolower(str_replace('-', '_', (string) ($project->proposed_system ?? 'undecided')));
                        $hasAnalysis = (bool) $project->loadAnalysis;
                        $hasModeledLoad = (int) $project->load_appliances_count > 0;

                        $buildingGlyph = match (true) {
                            in_array($buildingType, ['commercial', 'office', 'retail'], true) => 'commercial',
                            in_array($buildingType, ['industrial', 'factory', 'warehouse'], true) => 'industrial',
                            in_array($buildingType, ['residential', 'house'], true) => 'residential',
                            default => 'general',
                        };

                        $systemGlyph = match ($systemType) {
                            'hybrid' => 'hybrid',
                            'on_grid', 'ongrid', 'grid_tied' => 'grid',
                            default => 'undecided',
                        };

                        $analysisGlyphState = $hasModeledLoad
                            ? 'is-modeled'
                            : ($hasAnalysis ? 'is-started' : 'is-idle');
                    @endphp
                    <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}" class="nxla-registry-grid nxla-registry-row">
                        <div class="nxla-row-project">
                            <span class="nxla-row-icon nxla-row-icon--{{ $buildingGlyph }} {{ $analysisGlyphState }}" aria-hidden="true">
                                <span class="nxla-row-icon-main">
                                    @switch($buildingGlyph)
                                        @case('commercial')
                                            <svg class="nxla-icon-art" viewBox="0 0 24 24" fill="none">
                                                <rect class="i-surface" x="6.1" y="4.9" width="10.2" height="14.2" rx="2.6"/>
                                                <path class="i-main" d="M8.6 8.2h5.2M8.6 11.5h5.2M8.6 14.8h5.2"/>
                                                <path class="i-accent" d="M16.3 10.2h2.2v6.8M16.3 13.6h2.2"/>
                                                <path class="i-soft" d="M11.2 19.1v-2.2"/>
                                            </svg>
                                            @break

                                        @case('industrial')
                                            <svg class="nxla-icon-art" viewBox="0 0 24 24" fill="none">
                                                <path class="i-main" d="M4 19.1h16M5.2 19.1v-7.3l4 2.5v-3.2l3.8 2.4V8.9h5.8v10.2"/>
                                                <path class="i-accent" d="M7.7 15.7h3.2m2.1 0h2.6m2.1 0h1.3"/>
                                                <path class="i-soft" d="M15.4 5.1h2.4v2.7"/>
                                            </svg>
                                            @break

                                        @case('general')
                                            <svg class="nxla-icon-art" viewBox="0 0 24 24" fill="none">
                                                <path class="i-main" d="M4.8 7h6l1.7 2H19v8.6a1.7 1.7 0 0 1-1.7 1.7H6.5a1.7 1.7 0 0 1-1.7-1.7V7Z"/>
                                                <path class="i-accent" d="M7.8 14.1h7.8"/>
                                                <path class="i-soft" d="M9.5 11.3h4.5M10.5 16.7h3"/>
                                            </svg>
                                            @break

                                        @default
                                            <svg class="nxla-icon-art" viewBox="0 0 24 24" fill="none">
                                                <path class="i-main" d="m4.5 10.7 7.5-5.9 7.5 5.9M6.5 10.1v8h11v-8"/>
                                                <path class="i-accent" d="M9.1 15.6h5.8"/>
                                                <path class="i-soft" d="M9.3 18.1v-3.7h5.4v3.7"/>
                                            </svg>
                                    @endswitch
                                </span>
                            </span>
                            <div>
                                <div class="nxla-row-primary">{{ $project->name }}</div>
                                <div class="nxla-row-secondary">{{ $project->project_number }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="nxla-row-primary">{{ $project->customer->name }}</div>
                            <div class="nxla-row-secondary">{{ $project->customer->customer_number }}</div>
                        </div>
                        <div>
                            @if ($project->loadAnalysis)
                                <span class="nxla-chip is-live">Started</span>
                            @else
                                <span class="nxla-chip is-muted">Not started</span>
                            @endif
                        </div>
                        <div class="nxla-row-metric">{{ number_format($project->load_appliances_count) }}<small>groups</small></div>
                        <div class="nxla-row-metric">{{ number_format($project->electricity_bills_count) }}<small>months</small></div>
                        <div class="nxla-row-metric">
                            {{ $project->loadAnalysis?->updated_at?->diffForHumans() ?? 'Not started' }}
                            <small>{{ $project->loadAnalysis?->updated_at?->format('d M Y, H:i') ?? 'No update yet' }}</small>
                        </div>
                        <span class="nxla-row-action" aria-label="Open load analysis">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M5 12h14"/><path d="M13 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>
                @endforeach

                <div class="ui-table-footer">
                    @include('partials.pagination', ['paginator' => $projects])
                </div>
            @endif
        </section>
    </div>
@endsection
