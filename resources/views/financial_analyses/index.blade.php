@extends('layouts.app')

@section('title', 'Financial Analysis')
@section('page-eyebrow', 'Business output')
@section('page-heading', 'Financial Analysis')

@php
    $readyRate = $stats['projects'] > 0 ? min(100, round(($stats['ready'] / $stats['projects']) * 100)) : 0;
    $calculatedRate = $stats['projects'] > 0 ? min(100, round(($stats['calculated'] / $stats['projects']) * 100)) : 0;
    $lockedRate = $stats['calculated'] > 0 ? min(100, round(($stats['locked'] / $stats['calculated']) * 100)) : 0;
    $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] !== 'all' ? 1 : 0);
    $money = fn ($value) => $value === null ? '—' : 'IDR '.number_format((float) $value, 0, '.', ',');
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-financial-analysis.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-financial-analysis.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxfa-directory" data-nxfa-directory>
        <header class="nxfa-directory-hero" data-nxfa-reveal>
            <div class="nxfa-directory-copy">
                <span class="nxfa-kicker">Controlled investment decision registry</span>
                <h2>Financial Analysis</h2>
                <p>
                    Convert priced BOQ records and verified engineering output into traceable project economics. Review financial readiness, viability signals, investment metrics, and the latest analysis milestone from one controlled register.
                </p>
            </div>

            <div class="nxfa-directory-context" aria-label="Financial analysis registry context">
                <span class="nxfa-context-chip"><i></i> Financial engine online</span>
                <a
                    href="{{ route('companies.boms.index', $company) }}"
                    class="nxfa-context-link"
                    data-nxfa-navigate="bom"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    BOM &amp; BOQ registry
                </a>
            </div>
        </header>

        <section class="nxfa-stat-grid" aria-label="Financial analysis statistics">
            <a
                href="{{ route('companies.financial-analyses.index', $company) }}"
                class="nxfa-stat-card {{ $filters['status'] === 'all' ? 'is-selected' : '' }}"
                data-nxfa-card
                data-nxfa-reveal
                data-nxfa-navigate="directory"
            >
                <span class="nxfa-stat-topline">
                    <span class="nxfa-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8l8-5 8 5v11"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg>
                    </span>
                    <span class="nxfa-stat-state">Portfolio</span>
                </span>
                <span class="nxfa-stat-body">
                    <strong class="nxfa-stat-value">{{ number_format($stats['projects']) }}</strong>
                    <span>Available projects</span>
                    <small>Current projects visible to your role and company access.</small>
                </span>
                <span class="nxfa-stat-progress"><span style="width:100%"></span></span>
            </a>

            <article
                class="nxfa-stat-card is-orange"
                data-nxfa-card
                data-nxfa-reveal
            >
                <span class="nxfa-stat-topline">
                    <span class="nxfa-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/><path d="m15 16 2 2 4-5"/></svg>
                    </span>
                    <span class="nxfa-stat-state">Commercial basis</span>
                </span>
                <span class="nxfa-stat-body">
                    <strong class="nxfa-stat-value">{{ number_format($stats['ready']) }}</strong>
                    <span>BOQ ready</span>
                    <small>{{ $readyRate }}% of visible projects have verified engineering and a priced BOQ.</small>
                </span>
                <span class="nxfa-stat-progress"><span style="width:{{ $readyRate }}%"></span></span>
            </article>

            <a
                href="{{ route('companies.financial-analyses.index', ['company' => $company, 'status' => 'calculated']) }}"
                class="nxfa-stat-card is-blue {{ $filters['status'] === 'calculated' ? 'is-selected' : '' }}"
                data-nxfa-card
                data-nxfa-reveal
                data-nxfa-navigate="directory"
            >
                <span class="nxfa-stat-topline">
                    <span class="nxfa-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg>
                    </span>
                    <span class="nxfa-stat-state">Calculated</span>
                </span>
                <span class="nxfa-stat-body">
                    <strong class="nxfa-stat-value">{{ number_format($stats['calculated']) }}</strong>
                    <span>Financial analyses</span>
                    <small>{{ $calculatedRate }}% of visible projects have a current investment result.</small>
                </span>
                <span class="nxfa-stat-progress"><span style="width:{{ $calculatedRate }}%"></span></span>
            </a>

            <a
                href="{{ route('companies.financial-analyses.index', ['company' => $company, 'status' => 'locked']) }}"
                class="nxfa-stat-card is-violet {{ $filters['status'] === 'locked' ? 'is-selected' : '' }}"
                data-nxfa-card
                data-nxfa-reveal
                data-nxfa-navigate="directory"
            >
                <span class="nxfa-stat-topline">
                    <span class="nxfa-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    </span>
                    <span class="nxfa-stat-state">Governance</span>
                </span>
                <span class="nxfa-stat-body">
                    <strong class="nxfa-stat-value">{{ number_format($stats['locked']) }}</strong>
                    <span>Locked analyses</span>
                    <small>{{ $lockedRate }}% of calculated records are under controlled lock.</small>
                </span>
                <span class="nxfa-stat-progress"><span style="width:{{ $lockedRate }}%"></span></span>
            </a>
        </section>

        <section class="nxfa-registry" data-nxfa-reveal data-nxfa-card>
            <header class="nxfa-registry-header">
                <div>
                    <span class="nxfa-registry-kicker">Financial engineering register</span>
                    <h3>Project Investment Portfolio</h3>
                    <p>Commercial readiness, financial state, investment verdict, key economics, and the latest controlled analysis milestone.</p>
                </div>
                <div class="nxfa-registry-summary">
                    @if ($activeFilters > 0)
                        <span class="nxfa-filter-count">{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                    @endif
                    <span class="nxfa-result-range">{{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }}</span>
                </div>
            </header>

            <form
                method="GET"
                action="{{ route('companies.financial-analyses.index', $company) }}"
                class="nxfa-filter-toolbar"
                data-nxfa-filter-form
                aria-label="Financial analysis filters"
            >
                <div class="nxfa-filter-field">
                    <label for="financial-search">Search registry</label>
                    <div class="nxfa-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="financial-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Project, customer, city, or number" autocomplete="off">
                        @if ($filters['search'] !== '')
                            <a
                                href="{{ route('companies.financial-analyses.index', ['company' => $company, 'status' => $filters['status'] !== 'all' ? $filters['status'] : null]) }}"
                                class="nxfa-filter-clear"
                                aria-label="Clear search"
                                data-nxfa-navigate="directory"
                            >×</a>
                        @endif
                    </div>
                </div>

                <div class="nxfa-filter-field">
                    <label for="financial-status">Analysis state</label>
                    <div class="nxfa-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/></svg>
                        <select id="financial-status" name="status" data-nxfa-auto-submit>
                            <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                            <option value="not_calculated" @selected($filters['status'] === 'not_calculated')>Not calculated</option>
                            <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                            <option value="calculated" @selected($filters['status'] === 'calculated')>Calculated</option>
                            <option value="locked" @selected($filters['status'] === 'locked')>Locked</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="nxfa-filter-submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                    Apply
                </button>

                @if ($activeFilters > 0)
                    <a href="{{ route('companies.financial-analyses.index', $company) }}" class="nxfa-reset-link" data-nxfa-navigate="directory">Reset</a>
                @endif
            </form>

            @if ($projects->isEmpty())
                <div class="nxfa-registry-empty">
                    <span class="nxfa-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/></svg>
                    </span>
                    <h4>No matching financial records</h4>
                    <p>Adjust the search or analysis-state filter to restore the visible investment portfolio.</p>
                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.financial-analyses.index', $company) }}" class="nxfa-empty-action" data-nxfa-navigate="directory">Clear active filters</a>
                    @endif
                </div>
            @else
                <div class="nxfa-registry-labels" aria-hidden="true">
                    <span>Project</span>
                    <span>Source readiness</span>
                    <span>Financial state</span>
                    <span>CAPEX</span>
                    <span>Investment result</span>
                    <span></span>
                </div>

                <div class="nxfa-registry-list">
                    @foreach ($projects as $project)
                        @php
                            $analysis = $project->financialAnalysis;
                            $bom = $project->billOfMaterial;
                            $calculation = $project->technicalCalculation;
                            $ready = $calculation?->calculated_at && $bom?->pricing_status === 'priced' && (float) $bom?->grand_price_idr > 0;
                            $npv = $analysis ? (float) $analysis->npv_idr : null;
                            $irr = $analysis?->irr_percent === null ? null : (float) $analysis->irr_percent;
                            $viability = ! $analysis ? 'unassessed' : ($npv < 0 ? 'unfavorable' : ($irr === null ? 'marginal' : 'favorable'));
                            $viabilityLabel = match ($viability) {
                                'favorable' => 'Favorable',
                                'marginal' => 'Review required',
                                'unfavorable' => 'Unfavorable',
                                default => 'Not assessed',
                            };
                        @endphp
                        <article
                            class="nxfa-registry-row"
                            tabindex="0"
                            data-nxfa-row
                            data-nxfa-project="{{ $project->name }}"
                            data-nxfa-project-number="{{ $project->project_number }}"
                        >
                            <div class="nxfa-project-cell">
                                <span class="nxfa-project-glyph {{ $analysis ? '' : 'is-pending' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg>
                                </span>
                                <span class="nxfa-project-copy">
                                    <strong>{{ $project->name }}</strong>
                                    <small>
                                        <b>{{ $project->project_number }}</b><i></i>
                                        <em>{{ $project->customer?->name ?? 'Customer unavailable' }}{{ $project->city ? ' · '.$project->city : '' }}</em>
                                    </small>
                                </span>
                            </div>

                            <div class="nxfa-state-cell">
                                <strong>{{ $ready ? 'Commercial basis ready' : (! $calculation?->calculated_at ? 'Awaiting calculation' : 'BOQ pricing required') }}</strong>
                                <small>{{ $ready ? 'Technical result and priced BOQ available' : 'Financial engine prerequisite incomplete' }}</small>
                            </div>

                            <div class="nxfa-state-cell">
                                <span class="nxfa-status-pill is-{{ $analysis ? 'calculated' : 'pending' }}">{{ $analysis?->status_label ?? 'Not calculated' }}</span>
                                <small>{{ $analysis?->analysis_number ?? 'No financial record' }}</small>
                            </div>

                            <div class="nxfa-state-cell">
                                <strong class="nxfa-registry-value">{{ $analysis ? $money($analysis->project_capex_idr) : ($bom && (float) $bom->grand_price_idr > 0 ? $money($bom->grand_price_idr) : '—') }}</strong>
                                <small>{{ $analysis ? 'Analysis CAPEX basis' : ($ready ? 'Priced BOQ basis' : 'Commercial basis pending') }}</small>
                            </div>

                            <div class="nxfa-state-cell">
                                <span class="nxfa-status-pill is-{{ $viability }}">{{ $viabilityLabel }}</span>
                                <small>
                                    @if ($analysis)
                                        Payback {{ $analysis->simple_payback_years ? number_format((float) $analysis->simple_payback_years, 2, '.', ',').' yr' : 'not reached' }} · NPV {{ $money($analysis->npv_idr) }}
                                    @else
                                        Run analysis to assess project economics
                                    @endif
                                </small>
                            </div>

                            <div>
                                <a
                                    class="nxfa-open-action"
                                    href="{{ route('companies.financial-analyses.show', [$company, $project]) }}"
                                    data-nxfa-navigate="open"
                                    data-nxfa-project="{{ $project->name }}"
                                    data-nxfa-project-number="{{ $project->project_number }}"
                                >
                                    Open
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 12h14"/><path d="m14 7 5 5-5 5"/></svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <footer class="nxfa-registry-footer">
                    {{ $projects->links() }}
                </footer>
            @endif
        </section>
    </div>
@endsection
