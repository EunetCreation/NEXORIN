@extends('layouts.app')

@section('title', 'Technical Calculations')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Technical Calculation')

@php
    $preliminary = max(0, $stats['calculated'] - $stats['detailed'] - $stats['verified']);
    $preliminaryRate = $stats['calculated'] > 0
        ? min(100, round(($preliminary / $stats['calculated']) * 100))
        : 0;
    $detailRate = $stats['calculated'] > 0
        ? min(100, round(($stats['detailed'] / $stats['calculated']) * 100))
        : 0;
    $verificationRate = $stats['calculated'] > 0
        ? min(100, round(($stats['verified'] / $stats['calculated']) * 100))
        : 0;
    $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] !== 'all' ? 1 : 0);
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-technical-calculation.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-technical-calculation.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxtc-directory" data-nxtc-directory>
        <header class="nxtc-directory-hero" data-nxtc-reveal>
            <div class="nxtc-directory-copy">
                <span class="nxtc-kicker">Controlled electrical design registry</span>
                <h2>Technical Calculation</h2>
                <p>
                    Convert project evidence into a traceable PV, inverter, battery, string, cable, and protection design. Review calculation maturity, verification authority, and the latest engineering milestone from one controlled register.
                </p>
            </div>

            <div class="nxtc-directory-context" aria-label="Technical calculation registry context">
                <span class="nxtc-context-chip"><i></i> Engineering engine online</span>
                <a
                    href="{{ route('companies.projects.index', $company) }}"
                    class="nxtc-context-link"
                    data-nxtc-navigate="projects"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 19V8l8-5 8 5v11"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/>
                    </svg>
                    Project directory
                </a>
            </div>
        </header>

        <section class="nxtc-stat-grid" aria-label="Technical calculation statistics">
            <a
                href="{{ route('companies.technical-calculations.index', $company) }}"
                class="nxtc-stat-card is-teal {{ $filters['status'] === 'all' ? 'is-selected' : '' }}"
                data-nxtc-card
                data-nxtc-reveal
                data-nxtc-navigate="directory"
            >
                <span class="nxtc-stat-topline">
                    <span class="nxtc-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    </span>
                    <span class="nxtc-stat-state">Portfolio</span>
                </span>
                <span class="nxtc-stat-body">
                    <strong class="nxtc-stat-value">{{ number_format($stats['projects']) }}</strong>
                    <span>Available projects</span>
                    <small>Current projects visible to your role.</small>
                </span>
                <span class="nxtc-stat-progress"><span style="width:100%"></span></span>
            </a>

            <a
                href="{{ route('companies.technical-calculations.index', ['company' => $company, 'status' => 'preliminary']) }}"
                class="nxtc-stat-card is-orange {{ $filters['status'] === 'preliminary' ? 'is-selected' : '' }}"
                data-nxtc-card
                data-nxtc-reveal
                data-nxtc-navigate="directory"
            >
                <span class="nxtc-stat-topline">
                    <span class="nxtc-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/></svg>
                    </span>
                    <span class="nxtc-stat-state">Preliminary</span>
                </span>
                <span class="nxtc-stat-body">
                    <strong class="nxtc-stat-value">{{ number_format($preliminary) }}</strong>
                    <span>Preliminary results</span>
                    <small>{{ $preliminaryRate }}% of completed calculation records.</small>
                </span>
                <span class="nxtc-stat-progress"><span style="width:{{ $preliminaryRate }}%"></span></span>
            </a>

            <a
                href="{{ route('companies.technical-calculations.index', ['company' => $company, 'status' => 'detailed']) }}"
                class="nxtc-stat-card is-blue {{ $filters['status'] === 'detailed' ? 'is-selected' : '' }}"
                data-nxtc-card
                data-nxtc-reveal
                data-nxtc-navigate="directory"
            >
                <span class="nxtc-stat-topline">
                    <span class="nxtc-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 18h16"/><path d="M6 15V9M10 15V5M14 15v-7M18 15v-3"/><path d="m5 8 5-4 4 3 5-5"/></svg>
                    </span>
                    <span class="nxtc-stat-state">Detailed</span>
                </span>
                <span class="nxtc-stat-body">
                    <strong class="nxtc-stat-value">{{ number_format($stats['detailed']) }}</strong>
                    <span>Detailed results</span>
                    <small>{{ $detailRate }}% of completed calculation records.</small>
                </span>
                <span class="nxtc-stat-progress"><span style="width:{{ $detailRate }}%"></span></span>
            </a>

            <a
                href="{{ route('companies.technical-calculations.index', ['company' => $company, 'status' => 'verified']) }}"
                class="nxtc-stat-card is-violet {{ $filters['status'] === 'verified' ? 'is-selected' : '' }}"
                data-nxtc-card
                data-nxtc-reveal
                data-nxtc-navigate="directory"
            >
                <span class="nxtc-stat-topline">
                    <span class="nxtc-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg>
                    </span>
                    <span class="nxtc-stat-state">Authority</span>
                </span>
                <span class="nxtc-stat-body">
                    <strong class="nxtc-stat-value">{{ number_format($stats['verified']) }}</strong>
                    <span>Engineer verified</span>
                    <small>{{ $verificationRate }}% of completed calculation records.</small>
                </span>
                <span class="nxtc-stat-progress"><span style="width:{{ $verificationRate }}%"></span></span>
            </a>
        </section>

        <section class="nxtc-registry" data-nxtc-reveal data-nxtc-card>
            <header class="nxtc-registry-header">
                <div>
                    <span class="nxtc-registry-kicker">Electrical design register</span>
                    <h3>Calculation Portfolio</h3>
                    <p>Project-level calculation maturity, latest engine execution, and engineer verification authority.</p>
                </div>
                <div class="nxtc-registry-summary">
                    @if ($activeFilters > 0)
                        <span class="nxtc-filter-count">{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                    @endif
                    <span class="nxtc-result-range">{{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }}</span>
                </div>
            </header>

            <form
                method="GET"
                action="{{ route('companies.technical-calculations.index', $company) }}"
                class="nxtc-filter-toolbar"
                data-nxtc-filter-form
                aria-label="Technical calculation filters"
            >
                <div class="nxtc-filter-field">
                    <label for="technical-calculation-search">Search registry</label>
                    <div class="nxtc-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input
                            id="technical-calculation-search"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Project, customer, city, or number"
                            autocomplete="off"
                        >
                        @if ($filters['search'] !== '')
                            <a
                                href="{{ route('companies.technical-calculations.index', ['company' => $company, 'status' => $filters['status'] !== 'all' ? $filters['status'] : null]) }}"
                                class="nxtc-filter-clear"
                                aria-label="Clear search"
                                data-nxtc-navigate="directory"
                            >×</a>
                        @endif
                    </div>
                </div>

                <div class="nxtc-filter-field">
                    <label for="technical-calculation-status">Calculation state</label>
                    <div class="nxtc-filter-control">
                        <select id="technical-calculation-status" name="status" data-nxtc-auto-submit>
                            <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                            <option value="not_started" @selected($filters['status'] === 'not_started')>Not calculated</option>
                            <option value="preliminary" @selected($filters['status'] === 'preliminary')>Preliminary</option>
                            <option value="detailed" @selected($filters['status'] === 'detailed')>Detailed</option>
                            <option value="verified" @selected($filters['status'] === 'verified')>Engineer verified</option>
                        </select>
                    </div>
                </div>

                <div class="nxtc-filter-actions">
                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.technical-calculations.index', $company) }}" class="nxtc-button nxtc-button-secondary" data-nxtc-navigate="directory">Clear</a>
                    @endif
                    <button type="submit" class="nxtc-button nxtc-button-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                        Apply filters
                    </button>
                </div>
            </form>

            @if ($projects->isEmpty())
                <div class="nxtc-empty-registry">
                    <div>
                        <span class="nxtc-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/></svg>
                        </span>
                        <h3>No calculation records found</h3>
                        <p>Adjust the active filters or return to the project directory to open a current engineering project.</p>
                    </div>
                </div>
            @else
                <div class="nxtc-registry-head" aria-hidden="true">
                    <span>Project</span>
                    <span>Calculation state</span>
                    <span>Last engine run</span>
                    <span>Verification</span>
                    <span></span>
                </div>

                <div class="nxtc-registry-list">
                    @foreach ($projects as $project)
                        @php
                            $calculation = $project->technicalCalculation;
                            $statusTone = ! $calculation?->calculated_at
                                ? 'pending'
                                : ($calculation->result_status === 'verified'
                                    ? 'verified'
                                    : ($calculation->result_status === 'detailed' ? 'detailed' : 'preliminary'));
                            $statusLabel = ! $calculation?->calculated_at
                                ? 'Not calculated'
                                : ($calculation->result_status === 'verified'
                                    ? 'Engineer verified'
                                    : ($calculation->result_status === 'detailed' ? 'Detailed engineering' : 'Preliminary'));
                        @endphp
                        <article
                            class="nxtc-registry-row"
                            data-nxtc-row
                            data-nxtc-reveal
                            tabindex="0"
                            role="link"
                            aria-label="Open technical calculation for {{ $project->name }}"
                        >
                            <div class="nxtc-project-cell">
                                <span class="nxtc-project-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/><path d="M12 15v4"/></svg>
                                </span>
                                <span class="nxtc-project-copy">
                                    <strong>{{ $project->name }}</strong>
                                    <span class="nxtc-project-meta">
                                        <span class="nxtc-project-number">{{ $project->project_number }}</span>
                                        <i></i>
                                        <span>{{ $project->customer?->name ?? 'Customer unavailable' }}</span>
                                        <i></i>
                                        <span>{{ $project->city ?: 'Location pending' }}</span>
                                    </span>
                                </span>
                            </div>

                            <div class="nxtc-registry-status">
                                <span class="nxtc-status-pill is-{{ $statusTone }}">{{ $statusLabel }}</span>
                                <small>{{ $calculation?->calculated_at ? 'Electrical design record available' : 'Awaiting first engine run' }}</small>
                            </div>

                            <div class="nxtc-registry-time">
                                <span>{{ $calculation?->calculated_at?->format('d M Y') ?? 'Not run' }}</span>
                                <small>{{ $calculation?->calculated_at?->format('H:i') ?? 'No engine milestone' }}</small>
                            </div>

                            <div class="nxtc-registry-time">
                                <span>{{ $calculation?->verified_at?->format('d M Y') ?? 'Pending' }}</span>
                                <small>{{ $calculation?->verified_at?->format('H:i') ?? 'Engineer authority required' }}</small>
                            </div>

                            <div class="nxtc-registry-action">
                                <a
                                    href="{{ route('companies.technical-calculations.show', [$company, $project]) }}"
                                    class="nxtc-button nxtc-button-secondary nxtc-open-button"
                                    data-nxtc-navigate="open"
                                    data-nxtc-project="{{ $project->name }}"
                                    data-nxtc-project-number="{{ $project->project_number }}"
                                >
                                    Open
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <footer class="nxtc-registry-footer">
                    @include('partials.pagination', ['paginator' => $projects])
                </footer>
            @endif
        </section>
    </div>
@endsection
