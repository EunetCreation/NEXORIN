@extends('layouts.app')

@section('title', 'System Recommendations')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'System Recommendation')

@php
    $completionRate = $stats['projects'] > 0
        ? min(100, round(($stats['generated'] / $stats['projects']) * 100))
        : 0;
    $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] !== 'all' ? 1 : 0);
@endphp

@push('styles')
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,650;9..144,700&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/nexorin-system-recommendation.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-system-recommendation.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxrec-directory" data-nxrec-directory>
        <header class="nxrec-directory-hero" data-reveal>
            <div class="nxrec-directory-copy">
                <span class="nxrec-kicker"><span></span> Explainable decision registry</span>
                <h2>System Recommendation</h2>
                <p>
                    Review every project decision from one controlled engineering register. Compare alternatives, identify incomplete source data, and open the complete scoring trace without losing audit context.
                </p>
            </div>

            <div class="nxrec-directory-context" aria-label="Recommendation registry context">
                <span class="nxrec-context-chip nxrec-context-chip-live">
                    <span></span>
                    Engine v1.0.0
                </span>
                <a href="{{ route('companies.projects.index', $company) }}" class="nxrec-context-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 19V8l8-5 8 5v11"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/>
                    </svg>
                    Project directory
                </a>
            </div>
        </header>

        <section class="nxrec-stat-grid" aria-label="System recommendation statistics">
            <a href="{{ route('companies.recommendations.index', $company) }}" class="nxrec-stat-card nxrec-stat-card-dark {{ $filters['status'] === 'all' ? 'is-selected' : '' }}" data-reveal data-premium-card>
                <span class="nxrec-stat-topline">
                    <span class="nxrec-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    </span>
                    <span class="nxrec-stat-state">Portfolio</span>
                </span>
                <span class="nxrec-stat-body">
                    <strong data-counter="{{ $stats['projects'] }}">{{ number_format($stats['projects']) }}</strong>
                    <span>Available projects</span>
                    <small>Current projects visible to your role.</small>
                </span>
                <span class="nxrec-stat-progress"><span style="width: 100%"></span></span>
            </a>

            <a href="{{ route('companies.recommendations.index', ['company' => $company, 'status' => 'generated']) }}" class="nxrec-stat-card nxrec-stat-card-teal {{ $filters['status'] === 'generated' ? 'is-selected' : '' }}" data-reveal data-premium-card>
                <span class="nxrec-stat-topline">
                    <span class="nxrec-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 13 4 4L20 5"/><path d="M20 12a8 8 0 1 1-4-7.1"/></svg>
                    </span>
                    <span class="nxrec-stat-state">Generated</span>
                </span>
                <span class="nxrec-stat-body">
                    <strong data-counter="{{ $stats['generated'] }}">{{ number_format($stats['generated']) }}</strong>
                    <span>Recommendations run</span>
                    <small>{{ $completionRate }}% of the visible portfolio has a decision.</small>
                </span>
                <span class="nxrec-stat-progress"><span style="width: {{ $completionRate }}%"></span></span>
            </a>

            <a href="{{ route('companies.recommendations.index', ['company' => $company, 'search' => $filters['search'] ?: null]) }}" class="nxrec-stat-card nxrec-stat-card-blue" data-reveal data-premium-card>
                <span class="nxrec-stat-topline">
                    <span class="nxrec-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 17h16"/><path d="m6 14 4-4 3 3 5-7"/><path d="M16 6h2v2"/></svg>
                    </span>
                    <span class="nxrec-stat-state">On-grid</span>
                </span>
                <span class="nxrec-stat-body">
                    <strong data-counter="{{ $stats['on_grid'] }}">{{ number_format($stats['on_grid']) }}</strong>
                    <span>On-grid selected</span>
                    <small>Current final result, including engineer overrides.</small>
                </span>
                <span class="nxrec-stat-progress"><span style="width: {{ $stats['generated'] > 0 ? min(100, ($stats['on_grid'] / $stats['generated']) * 100) : 0 }}%"></span></span>
            </a>

            <a href="{{ route('companies.recommendations.index', ['company' => $company, 'search' => $filters['search'] ?: null]) }}" class="nxrec-stat-card nxrec-stat-card-violet" data-reveal data-premium-card>
                <span class="nxrec-stat-topline">
                    <span class="nxrec-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 20h10"/><path d="M9 20v-5h6v5"/><path d="M5 15 8 4h8l3 11"/><path d="M8 9h8"/></svg>
                    </span>
                    <span class="nxrec-stat-state">Hybrid</span>
                </span>
                <span class="nxrec-stat-body">
                    <strong data-counter="{{ $stats['hybrid'] }}">{{ number_format($stats['hybrid']) }}</strong>
                    <span>Hybrid selected</span>
                    <small>Current final result, including engineer overrides.</small>
                </span>
                <span class="nxrec-stat-progress"><span style="width: {{ $stats['generated'] > 0 ? min(100, ($stats['hybrid'] / $stats['generated']) * 100) : 0 }}%"></span></span>
            </a>
        </section>

        <section class="nxrec-registry" data-reveal data-premium-card>
            <header class="nxrec-registry-header">
                <div>
                    <span class="nxrec-registry-kicker">Decision register</span>
                    <h3>Recommendation Portfolio</h3>
                    <p>Project-level results, score separation, readiness status, and the latest engine milestone.</p>
                </div>
                <div class="nxrec-registry-summary">
                    @if ($activeFilters > 0)
                        <span class="nxrec-filter-count">{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                    @endif
                    <span class="nxrec-result-range">{{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }}</span>
                </div>
            </header>

            <form method="GET" action="{{ route('companies.recommendations.index', $company) }}" class="nxrec-filter-toolbar" data-nxrec-action-form data-nxrec-action="filter" aria-label="System recommendation filters">
                <div class="nxrec-filter-search">
                    <label for="recommendation-search">Search registry</label>
                    <div class="nxrec-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="recommendation-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Project, customer, city, or number" autocomplete="off" data-nxrec-directory-search>
                        @if ($filters['search'] !== '')
                            <a href="{{ route('companies.recommendations.index', ['company' => $company, 'status' => $filters['status'] !== 'all' ? $filters['status'] : null]) }}" aria-label="Clear search">×</a>
                        @endif
                    </div>
                </div>

                <div class="nxrec-filter-field">
                    <label for="recommendation-status">Decision state</label>
                    <div class="nxrec-filter-control nxrec-filter-select">
                        <select id="recommendation-status" name="status" data-nxrec-auto-submit>
                            <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                            <option value="not_started" @selected($filters['status'] === 'not_started')>Not started</option>
                            <option value="incomplete" @selected($filters['status'] === 'incomplete')>Source data incomplete</option>
                            <option value="generated" @selected($filters['status'] === 'generated')>Generated</option>
                            <option value="review_required" @selected($filters['status'] === 'review_required')>Review required</option>
                            <option value="overridden" @selected($filters['status'] === 'overridden')>Engineer override</option>
                        </select>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg>
                    </div>
                </div>

                <div class="nxrec-filter-actions">
                    <button type="submit" class="nxrec-filter-apply">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                        Apply
                    </button>
                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.recommendations.index', $company) }}" class="nxrec-filter-reset" aria-label="Reset filters">Reset</a>
                    @endif
                </div>
            </form>

            @if ($projects->isEmpty())
                <div class="nxrec-empty">
                    <span class="nxrec-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19h16"/><path d="m6 15 4-4 3 3 5-7"/><path d="M16 7h2v2"/></svg>
                    </span>
                    <span class="nxrec-empty-kicker">No matching decision record</span>
                    <h3>The current filters returned no projects.</h3>
                    <p>Reset the registry or return to the project directory to continue the engineering workflow.</p>
                    <div>
                        <a href="{{ route('companies.recommendations.index', $company) }}" class="nxrec-button nxrec-button-primary">Reset registry</a>
                        <a href="{{ route('companies.projects.index', $company) }}" class="nxrec-button nxrec-button-ghost">Project directory</a>
                    </div>
                </div>
            @else
                <div class="nxrec-table" role="table" aria-label="System recommendation registry">
                    <div class="nxrec-table-head" role="row">
                        <span role="columnheader">Project</span>
                        <span role="columnheader">Selected system</span>
                        <span role="columnheader">System score comparison</span>
                        <span role="columnheader">Status</span>
                        <span role="columnheader">Last engine run</span>
                        <span role="columnheader" aria-label="Actions"></span>
                    </div>

                    <div class="nxrec-table-body" role="rowgroup">
                        @foreach ($projects as $project)
                            @php
                                $result = $project->systemRecommendation;
                                $statusTone = !$result
                                    ? 'pending'
                                    : (in_array($result->result_status, ['generated', 'overridden'], true)
                                        ? 'ready'
                                        : ($result->result_status === 'incomplete' ? 'blocked' : 'review'));
                                $resultSystem = $result?->recommended_system ?? 'undecided';
                            @endphp
                            <article class="nxrec-table-row" role="row" tabindex="0" data-row-href="{{ route('companies.recommendations.show', [$company, $project]) }}" data-premium-card
                                data-project-number="{{ $project->project_number }}"
                                data-project-name="{{ $project->name }}">
                                <div class="nxrec-project-cell" role="cell" data-label="Project">
                                    <span class="nxrec-project-symbol is-{{ $resultSystem }}">
                                        @if ($resultSystem === 'hybrid')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 20h10M9 20v-5h6v5M5 15 8 4h8l3 11M8 9h8"/><path d="M11 12h2"/></svg>
                                        @elseif ($resultSystem === 'on_grid')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3v18M7 7h10M5 12h14M8 17h8"/><path d="m4 21 8-18 8 18"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8"/><path d="M12 8v5M12 16h.01"/></svg>
                                        @endif
                                    </span>
                                    <span class="nxrec-project-copy">
                                        <strong title="{{ $project->name }}">{{ $project->name }}</strong>
                                        <span class="nxrec-project-meta">
                                            <small class="nxrec-project-id">{{ $project->project_number }}</small>
                                            <i aria-hidden="true"></i>
                                            <small class="nxrec-project-customer" title="{{ $project->customer->name }}">{{ $project->customer->name }}</small>
                                        </span>
                                    </span>
                                </div>

                                <div class="nxrec-decision-cell" role="cell" data-label="Selected system">
                                    <span class="nxrec-cell-eyebrow">Planning basis</span>
                                    <strong>{{ $result?->recommended_system_label ?? 'Not generated' }}</strong>
                                    <small>
                                        @if (!$result)
                                            Awaiting recommendation
                                        @elseif ($result->engine_recommended_system && $result->recommended_system !== $result->engine_recommended_system)
                                            Engineer override · Suggested {{ $result->engine_recommended_system_label }}
                                        @else
                                            Engine-selected result
                                        @endif
                                    </small>
                                </div>

                                <div class="nxrec-comparison-cell" role="cell" data-label="System score comparison">
                                    @if ($result?->on_grid_score !== null && $result?->hybrid_score !== null)
                                        <div class="nxrec-mini-score" aria-label="On-grid and hybrid recommendation scores">
                                            <span>
                                                <small>On-grid</small>
                                                <i aria-hidden="true"><b style="width: {{ min(100, (float) $result->on_grid_score) }}%"></b></i>
                                                <strong>{{ number_format((float) $result->on_grid_score, 1) }}</strong>
                                            </span>
                                            <span>
                                                <small>Hybrid</small>
                                                <i aria-hidden="true"><b style="width: {{ min(100, (float) $result->hybrid_score) }}%"></b></i>
                                                <strong>{{ number_format((float) $result->hybrid_score, 1) }}</strong>
                                            </span>
                                        </div>
                                    @else
                                        <span class="nxrec-no-score">Scores are not available yet</span>
                                    @endif
                                </div>

                                <div class="nxrec-status-cell" role="cell" data-label="Status">
                                    <span class="nxrec-status is-{{ $statusTone }}"><span></span>{{ $result?->result_status_label ?? 'Not started' }}</span>
                                </div>

                                <div class="nxrec-milestone-cell" role="cell" data-label="Last engine run">
                                    <strong>{{ $result?->generated_at?->diffForHumans() ?? 'Not run' }}</strong>
                                    <small>{{ $result?->generated_at?->format('d M Y · H:i') ?? 'No engine milestone' }}</small>
                                </div>

                                <div class="nxrec-row-action" role="cell" data-label="Action">
                                    <a href="{{ route('companies.recommendations.show', [$company, $project]) }}" data-nxrec-open aria-label="Open recommendation for {{ $project->name }}">
                                        <span>Open</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <footer class="nxrec-registry-footer">
                    @include('partials.pagination', ['paginator' => $projects])
                </footer>
            @endif
        </section>
    </div>
@endsection
