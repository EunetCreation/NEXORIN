@extends('layouts.app')

@section('title', 'BOM/BOQ')
@section('page-eyebrow', 'Commercial engineering')
@section('page-heading', 'BOM/BOQ')

@php
    $awaitingGeneration = max(0, $stats['ready'] - $stats['generated']);
    $readyRate = $stats['projects'] > 0 ? min(100, round(($awaitingGeneration / $stats['projects']) * 100)) : 0;
    $generatedRate = $stats['projects'] > 0 ? min(100, round(($stats['generated'] / $stats['projects']) * 100)) : 0;
    $pricedRate = $stats['generated'] > 0 ? min(100, round(($stats['priced'] / $stats['generated']) * 100)) : 0;
    $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] !== 'all' ? 1 : 0);
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-bom-boq.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-bom-boq.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxbom-directory" data-nxbom-directory>
        <header class="nxbom-directory-hero" data-nxbom-reveal>
            <div class="nxbom-directory-copy">
                <span class="nxbom-kicker">Material intelligence and commercial control</span>
                <h2>BOM &amp; BOQ Registry</h2>
                <p>
                    Govern the handoff from verified engineering quantities to material scope, price coverage, margin control, and customer-ready commercial totals.
                </p>
            </div>

            <div class="nxbom-directory-context" aria-label="BOM and BOQ registry context">
                <span class="nxbom-context-chip"><i></i> Commercial engine online</span>
                <a
                    href="{{ route('companies.technical-calculations.index', $company) }}"
                    class="nxbom-context-link"
                    data-nxbom-navigate="technical-directory"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/>
                    </svg>
                    Technical calculations
                </a>
            </div>
        </header>

        <section class="nxbom-stat-grid" aria-label="BOM and BOQ statistics">
            <a
                href="{{ route('companies.boms.index', $company) }}"
                class="nxbom-stat-card is-teal {{ $filters['status'] === 'all' ? 'is-selected' : '' }}"
                data-nxbom-card
                data-nxbom-reveal
                data-nxbom-navigate="directory"
            >
                <span class="nxbom-stat-topline">
                    <span class="nxbom-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h6l2 2h8v10H4z"/><path d="M4 7V5h6l2 2"/></svg>
                    </span>
                    <span class="nxbom-stat-state">Portfolio</span>
                </span>
                <span class="nxbom-stat-body">
                    <strong class="nxbom-stat-value">{{ number_format($stats['projects']) }}</strong>
                    <span>Available projects</span>
                    <small>Current projects visible to your role.</small>
                </span>
                <span class="nxbom-stat-progress"><span style="width:100%"></span></span>
            </a>

            <a
                href="{{ route('companies.boms.index', ['company' => $company, 'status' => 'not_generated']) }}"
                class="nxbom-stat-card is-orange {{ $filters['status'] === 'not_generated' ? 'is-selected' : '' }}"
                data-nxbom-card
                data-nxbom-reveal
                data-nxbom-navigate="directory"
            >
                <span class="nxbom-stat-topline">
                    <span class="nxbom-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/><path d="m3 6 2 2 3-4"/></svg>
                    </span>
                    <span class="nxbom-stat-state">Engineering ready</span>
                </span>
                <span class="nxbom-stat-body">
                    <strong class="nxbom-stat-value">{{ number_format($awaitingGeneration) }}</strong>
                    <span>Awaiting generation</span>
                    <small>{{ $readyRate }}% of visible projects are calculated but not materialized.</small>
                </span>
                <span class="nxbom-stat-progress"><span style="width:{{ $readyRate }}%"></span></span>
            </a>

            <a
                href="{{ route('companies.boms.index', ['company' => $company, 'status' => 'generated']) }}"
                class="nxbom-stat-card is-blue {{ $filters['status'] === 'generated' ? 'is-selected' : '' }}"
                data-nxbom-card
                data-nxbom-reveal
                data-nxbom-navigate="directory"
            >
                <span class="nxbom-stat-topline">
                    <span class="nxbom-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/><path d="M17 16h1"/></svg>
                    </span>
                    <span class="nxbom-stat-state">Materialized</span>
                </span>
                <span class="nxbom-stat-body">
                    <strong class="nxbom-stat-value">{{ number_format($stats['generated']) }}</strong>
                    <span>Generated schedules</span>
                    <small>{{ $generatedRate }}% of visible projects have a BOM draft.</small>
                </span>
                <span class="nxbom-stat-progress"><span style="width:{{ $generatedRate }}%"></span></span>
            </a>

            <a
                href="{{ route('companies.boms.index', ['company' => $company, 'status' => 'priced']) }}"
                class="nxbom-stat-card is-violet {{ $filters['status'] === 'priced' ? 'is-selected' : '' }}"
                data-nxbom-card
                data-nxbom-reveal
                data-nxbom-navigate="directory"
            >
                <span class="nxbom-stat-topline">
                    <span class="nxbom-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg>
                    </span>
                    <span class="nxbom-stat-state">Commercial</span>
                </span>
                <span class="nxbom-stat-body">
                    <strong class="nxbom-stat-value">{{ number_format($stats['priced']) }}</strong>
                    <span>Priced BOQ records</span>
                    <small>{{ $pricedRate }}% of generated schedules are commercially priced.</small>
                </span>
                <span class="nxbom-stat-progress"><span style="width:{{ $pricedRate }}%"></span></span>
            </a>
        </section>

        <section class="nxbom-registry" data-nxbom-reveal data-nxbom-card>
            <header class="nxbom-registry-header">
                <div>
                    <span class="nxbom-registry-kicker">Commercial engineering register</span>
                    <h3>Project Material Portfolio</h3>
                    <p>Engineering readiness, BOM generation, price state, customer total, and the latest controlled milestone.</p>
                </div>
                <div class="nxbom-registry-summary">
                    @if ($activeFilters > 0)
                        <span class="nxbom-filter-count">{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                    @endif
                    <span class="nxbom-result-range">{{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }}</span>
                </div>
            </header>

            <form
                method="GET"
                action="{{ route('companies.boms.index', $company) }}"
                class="nxbom-filter-toolbar"
                data-nxbom-filter-form
                aria-label="BOM and BOQ filters"
            >
                <div class="nxbom-filter-field is-grow">
                    <label for="bom-search">Search registry</label>
                    <div class="nxbom-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input
                            id="bom-search"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Project, customer, city, or number"
                            autocomplete="off"
                        >
                        @if ($filters['search'] !== '')
                            <a
                                href="{{ route('companies.boms.index', ['company' => $company, 'status' => $filters['status'] !== 'all' ? $filters['status'] : null]) }}"
                                class="nxbom-filter-clear"
                                aria-label="Clear search"
                                data-nxbom-navigate="directory"
                            >×</a>
                        @endif
                    </div>
                </div>

                <div class="nxbom-filter-field">
                    <label for="bom-status">Commercial state</label>
                    <div class="nxbom-filter-control is-select">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                        <select id="bom-status" name="status" data-nxbom-auto-submit>
                            <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                            <option value="not_generated" @selected($filters['status'] === 'not_generated')>Not generated</option>
                            <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                            <option value="generated" @selected($filters['status'] === 'generated')>Generated</option>
                            <option value="priced" @selected($filters['status'] === 'priced')>Priced</option>
                            <option value="locked" @selected($filters['status'] === 'locked')>Locked</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="nxbom-filter-submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                    Apply
                </button>

                @if ($activeFilters > 0)
                    <a href="{{ route('companies.boms.index', $company) }}" class="nxbom-reset-link" data-nxbom-navigate="directory">Reset</a>
                @endif
            </form>

            @if ($projects->isEmpty())
                <div class="nxbom-registry-empty">
                    <span class="nxbom-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    </span>
                    <h4>No matching project records</h4>
                    <p>Adjust the search or commercial state filter to restore the visible BOM and BOQ portfolio.</p>
                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.boms.index', $company) }}" class="nxbom-empty-action" data-nxbom-navigate="directory">Clear active filters</a>
                    @endif
                </div>
            @else
                <div class="nxbom-registry-labels" aria-hidden="true">
                    <span>Project</span>
                    <span>Engineering basis</span>
                    <span>BOM / BOQ state</span>
                    <span>Commercial total</span>
                    <span>Latest milestone</span>
                    <span></span>
                </div>

                <div class="nxbom-registry-list">
                    @foreach ($projects as $project)
                        @php
                            $calculation = $project->technicalCalculation;
                            $bom = $project->billOfMaterial;
                            $engineeringReady = (bool) $calculation?->calculated_at;
                            $engineeringVerified = $calculation?->result_status === 'verified';
                            $priced = $bom?->pricing_status === 'priced';
                        @endphp
                        <article
                            class="nxbom-registry-row"
                            tabindex="0"
                            data-nxbom-row
                            data-nxbom-project="{{ $project->name }}"
                            data-nxbom-project-number="{{ $project->project_number }}"
                        >
                            <div class="nxbom-project-cell">
                                <span class="nxbom-project-glyph {{ $priced ? 'is-priced' : ($bom ? 'is-generated' : '') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/><path d="m3 6 2 2 3-4"/></svg>
                                </span>
                                <span class="nxbom-project-copy">
                                    <strong>{{ $project->name }}</strong>
                                    <small><b>{{ $project->project_number }}</b><i></i>{{ $project->customer?->name ?? 'Customer unavailable' }}</small>
                                </span>
                            </div>

                            <div class="nxbom-state-cell">
                                <span class="nxbom-cell-eyebrow">Technical calculation</span>
                                <strong>{{ $engineeringVerified ? 'Engineer verified' : ($engineeringReady ? ucfirst((string) $calculation->result_status) : 'Awaiting calculation') }}</strong>
                                <small>{{ $calculation?->calculated_at?->format('d M Y · H:i') ?? 'No calculation milestone' }}</small>
                            </div>

                            <div class="nxbom-state-cell">
                                <span class="nxbom-cell-eyebrow">Material schedule</span>
                                <strong>{{ ! $bom ? 'Not generated' : ($priced ? 'Priced BOQ' : ucfirst((string) $bom->status).' draft') }}</strong>
                                <small>{{ $bom?->bom_number ?? 'Quantity schedule pending' }}</small>
                            </div>

                            <div class="nxbom-total-cell">
                                <span class="nxbom-cell-eyebrow">Customer total</span>
                                <strong>{{ $priced ? 'IDR '.number_format((float) $bom->grand_price_idr, 0, ',', '.') : '—' }}</strong>
                                <small>{{ $priced ? 'Commercial price established' : ($bom ? 'Pricing not completed' : 'BOM required first') }}</small>
                            </div>

                            <div class="nxbom-state-cell">
                                <span class="nxbom-cell-eyebrow">Latest milestone</span>
                                <strong>{{ $bom?->priced_at ? 'Priced' : ($bom?->generated_at ? 'Generated' : ($calculation?->calculated_at ? 'Calculated' : 'Not started')) }}</strong>
                                <small>{{ ($bom?->priced_at ?? $bom?->generated_at ?? $calculation?->calculated_at)?->diffForHumans() ?? 'No commercial milestone' }}</small>
                            </div>

                            <div class="nxbom-row-action">
                                <a
                                    href="{{ route('companies.boms.show', [$company, $project]) }}"
                                    data-nxbom-navigate="open"
                                    data-nxbom-project="{{ $project->name }}"
                                    data-nxbom-project-number="{{ $project->project_number }}"
                                    aria-label="Open BOM and BOQ for {{ $project->name }}"
                                >
                                    <span>Open</span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <footer class="nxbom-registry-footer">
                    @include('partials.pagination', ['paginator' => $projects])
                </footer>
            @endif
        </section>
    </div>
@endsection
