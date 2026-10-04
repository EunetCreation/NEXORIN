@extends('layouts.app')

@section('title', 'Financial Analyses')
@section('page-eyebrow', 'Financial engineering')
@section('page-heading', 'Financial Analyses')
@section('force-theme', 'dark')
@section('body-class', 'page-financial-index-v6')
@section('disable-remote-fonts', '1')

@php
    $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] !== 'all' ? 1 : 0);
    $projectCount = max(0, (int) ($stats['projects'] ?? 0));
    $rate = static fn (int $value): int => $projectCount > 0 ? min(100, (int) round(($value / $projectCount) * 100)) : 0;
    $money = static fn ($value): string => $value === null ? '—' : 'IDR '.number_format((float) $value, 0, '.', ',');
    $compactMoney = static function ($value): string {
        if ($value === null) return '—';
        $number = (float) $value;
        $absolute = abs($number);
        if ($absolute >= 1_000_000_000_000) return 'IDR '.number_format($number / 1_000_000_000_000, 1).'T';
        if ($absolute >= 1_000_000_000) return 'IDR '.number_format($number / 1_000_000_000, 1).'B';
        if ($absolute >= 1_000_000) return 'IDR '.number_format($number / 1_000_000, 1).'M';
        if ($absolute >= 1_000) return 'IDR '.number_format($number / 1_000, 1).'K';
        return 'IDR '.number_format($number, 0);
    };
    $projectRange = ($projects->firstItem() ?? 0).'–'.($projects->lastItem() ?? 0).' of '.number_format($projects->total());
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001013.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
    <link href="{{ asset('css/nexorin-financial-index-v6.css') }}?v=013b" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-financial-index-v6.js') }}?v=013a" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
    <main class="nx13" data-nx13-page>
        <header class="nx13-head">
            <div class="nx13-head-copy">
                <span class="nx13-kicker">Financial engineering · portfolio registry</span>
                <h2>Financial Analyses</h2>
                <p>
                    Track which current projects have a usable technical and priced BOQ basis, which already hold a financial result, and which source records need review before the economics are used again.
                </p>
            </div>

            <div class="nx13-head-actions">
                <span class="nx13-scope-chip" title="Project visibility is enforced server-side by the current company and project permission scope.">
                    <span class="nx13-scope-dot"></span>
                    {{ $visibilityScope === 'company' ? 'Company project scope' : 'Owned project scope' }}
                </span>
                <a href="{{ route('companies.boms.index', $company) }}" class="nx13-link-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    BOM &amp; BOQ registry
                </a>
            </div>
        </header>

        <section class="nx13-command-band" aria-label="Financial portfolio command metrics" data-nxl-surface="raised">
            <a href="{{ route('companies.financial-analyses.index', $company) }}" class="nx13-command-metric {{ $filters['status'] === 'all' ? 'is-active' : '' }}">
                <span class="nx13-command-label">Projects in scope</span>
                <strong>{{ number_format($projectCount) }}</strong>
                <small>Current projects visible to this role.</small>
            </a>
            <div class="nx13-command-metric is-cyan">
                <span class="nx13-command-label">Financial basis ready</span>
                <strong>{{ number_format($stats['basis_ready']) }}</strong>
                <small>{{ $rate($stats['basis_ready']) }}% have current technical energy and a linked priced BOQ.</small>
            </div>
            <div class="nx13-command-metric is-blue">
                <span class="nx13-command-label">Stored analyses</span>
                <strong>{{ number_format($stats['stored_analyses']) }}</strong>
                <small>{{ $rate($stats['stored_analyses']) }}% have a saved financial analysis.</small>
            </div>
            <a href="{{ route('companies.financial-analyses.index', ['company' => $company, 'status' => 'not_calculated']) }}" class="nx13-command-metric is-amber {{ $filters['status'] === 'not_calculated' ? 'is-active' : '' }}">
                <span class="nx13-command-label">Ready without analysis</span>
                <strong>{{ number_format($stats['ready_without_analysis']) }}</strong>
                <small>Basis complete; no financial analysis has been saved.</small>
            </a>
        </section>

        <div class="nx13-workspace {{ $projectCount <= 2 ? 'is-sparse-company' : '' }}">
            <section class="nx13-registry" data-nxl-surface="raised">
                <header class="nx13-registry-head">
                    <div>
                        <span class="nx13-section-kicker">Financial analysis registry</span>
                        <h3>Project Financial Basis</h3>
                        <p>Source readiness, stored analysis state, CAPEX basis, current calculated outputs, and the next project action.</p>
                    </div>
                    <div class="nx13-result-meta">
                        @if ($activeFilters > 0)
                            <span>{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                        @endif
                        <b>{{ $projectRange }}</b>
                    </div>
                </header>

                <form method="GET" action="{{ route('companies.financial-analyses.index', $company) }}" class="nx13-filters" data-nx13-filter-form aria-label="Financial analysis filters">
                    <label class="nx13-search-field" for="financial-search">
                        <span>Search registry</span>
                        <span class="nx13-control-shell">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                            <input id="financial-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Project, customer, city, or number" autocomplete="off">
                            @if ($filters['search'] !== '')
                                <a href="{{ route('companies.financial-analyses.index', ['company' => $company, 'status' => $filters['status'] !== 'all' ? $filters['status'] : null]) }}" class="nx13-clear" aria-label="Clear search">×</a>
                            @endif
                        </span>
                    </label>

                    <label class="nx13-status-field" for="financial-status">
                        <span>Stored analysis state</span>
                        <span class="nx13-control-shell">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/></svg>
                            <select id="financial-status" name="status" data-nx13-auto-submit>
                                <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                                <option value="not_calculated" @selected($filters['status'] === 'not_calculated')>No stored analysis</option>
                                <option value="draft" @selected($filters['status'] === 'draft')>Stored draft</option>
                                <option value="calculated" @selected($filters['status'] === 'calculated')>Calculated</option>
                                <option value="locked" @selected($filters['status'] === 'locked')>Legacy locked records</option>
                            </select>
                        </span>
                    </label>

                    <button type="submit" class="nx13-apply">
                        Apply filters
                    </button>
                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.financial-analyses.index', $company) }}" class="nx13-reset">Reset</a>
                    @endif
                </form>

                @if ($projects->isEmpty())
                    <div class="nx13-empty">
                        <span class="nx13-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/></svg>
                        </span>
                        @if ($projectCount === 0)
                            <h4>No current projects in this scope</h4>
                            <p>Financial analysis becomes available after a current project has the required technical and commercial basis.</p>
                        @else
                            <h4>No projects match these filters</h4>
                            <p>Change the search or stored-analysis filter. No financial result has been inferred or fabricated for this empty state.</p>
                            <a href="{{ route('companies.financial-analyses.index', $company) }}">Clear active filters</a>
                        @endif
                    </div>
                @else
                    <div class="nx13-columns" aria-hidden="true">
                        <span>Project</span>
                        <span>Financial basis</span>
                        <span>Stored record</span>
                        <span>CAPEX basis</span>
                        <span>Calculated outputs</span>
                        <span></span>
                    </div>

                    <div class="nx13-rows">
                        @foreach ($projects as $project)
                            @php
                                $analysis = $project->financialAnalysis;
                                $bom = $project->billOfMaterial;
                                $state = $registryStates[$project->getKey()];
                                $basis = $state['basis'];
                                $record = $state['analysis'];
                                $freshness = $state['freshness'];
                                $isCurrentAnalysis = $analysis && ($freshness['state'] ?? null) === 'current' && $analysis->status === 'calculated';
                                $analysisCurrencyIsIdr = $analysis && strtoupper((string) $analysis->currency) === 'IDR';
                                $capexValue = $analysisCurrencyIsIdr
                                    ? $analysis->project_capex_idr
                                    : (($bom?->pricing_status === 'priced' && (float) $bom?->grand_price_idr > 0) ? $bom->grand_price_idr : null);
                                $capexLabel = $analysisCurrencyIsIdr
                                    ? (($freshness['state'] ?? null) === 'current' ? 'Analysis CAPEX basis' : 'Stored analysis CAPEX')
                                    : (($bom?->pricing_status === 'priced' && (float) $bom?->grand_price_idr > 0) ? 'Priced BOQ basis' : 'CAPEX basis not available');
                                $npv = $analysisCurrencyIsIdr ? (float) $analysis->npv_idr : null;
                                $npvTone = $npv === null ? 'neutral' : ($npv < 0 ? 'negative' : 'positive');
                            @endphp
                            <article class="nx13-row" data-nxl-row data-nx13-row data-project-number="{{ $project->project_number }}" tabindex="0">
                                <div class="nx13-project">
                                    <span class="nx13-project-mark {{ $analysis ? 'has-analysis' : '' }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg>
                                    </span>
                                    <span class="nx13-project-copy">
                                        <strong title="{{ $project->name }}">{{ $project->name }}</strong>
                                        <small><b>{{ $project->project_number }}</b><i></i>{{ $project->customer?->name ?? 'Customer not recorded' }}</small>
                                        @if ($project->city)
                                            <em>{{ $project->city }}</em>
                                        @endif
                                    </span>
                                </div>

                                <div class="nx13-state-cell">
                                    <span class="nx13-state-badge is-{{ $basis['tone'] }}">{{ $basis['label'] }}</span>
                                    <small>{{ $basis['detail'] }}</small>
                                </div>

                                <div class="nx13-state-cell">
                                    <span class="nx13-state-badge is-{{ $record['tone'] }}">{{ $record['label'] }}</span>
                                    @if ($analysis)
                                        <b class="nx13-analysis-number">{{ $analysis->analysis_number ?? 'Analysis reference not recorded' }}</b>
                                        <span class="nx13-source-state is-{{ $freshness['state'] ?? 'review_required' }}">{{ $freshness['label'] ?? 'Source Review Required' }}</span>
                                    @else
                                        <small>{{ $basis['ready'] ? 'Basis is ready for the financial workspace.' : 'Complete the required upstream basis first.' }}</small>
                                    @endif
                                </div>

                                <div class="nx13-capex-cell">
                                    <strong>{{ $compactMoney($capexValue) }}</strong>
                                    <small>{{ $capexLabel }}</small>
                                    @if ($analysis && ! $analysisCurrencyIsIdr)
                                        <span class="nx13-inline-warning">Currency review required</span>
                                    @endif
                                </div>

                                <div class="nx13-output-cell">
                                    @if ($isCurrentAnalysis)
                                        <strong class="is-{{ $npvTone }}">NPV {{ $compactMoney($analysis->npv_idr) }}</strong>
                                        <small>
                                            IRR {{ $analysis->irr_percent !== null ? number_format((float) $analysis->irr_percent, 2).'%' : 'not available' }}
                                            <i></i>
                                            Payback {{ $analysis->simple_payback_years !== null ? number_format((float) $analysis->simple_payback_years, 2).' yr' : 'not reached within analysis horizon' }}
                                        </small>
                                        <em>LCOE {{ $analysis->lcoe_idr_per_kwh !== null ? 'IDR '.number_format((float) $analysis->lcoe_idr_per_kwh, 0).'/kWh' : 'not available' }}</em>
                                    @elseif ($analysis && $analysisCurrencyIsIdr)
                                        <strong class="is-stored">Stored NPV {{ $compactMoney($analysis->npv_idr) }}</strong>
                                        <small>{{ $freshness['detail'] ?? 'Review the stored source basis before using these outputs.' }}</small>
                                        <em>Current LCOE is withheld until source freshness is established.</em>
                                    @else
                                        <strong>No current financial outputs</strong>
                                        <small>{{ $basis['ready'] ? 'Open the workspace to run financial analysis.' : 'Financial analysis is blocked by the basis state shown here.' }}</small>
                                    @endif
                                </div>

                                <div class="nx13-row-action">
                                    <a href="{{ route('companies.financial-analyses.show', [$company, $project]) }}" class="nx13-open" aria-label="Open financial workspace for {{ $project->name }}" title="Open financial workspace">
                                        <span>Open</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 12h14"/><path d="m14 7 5 5-5 5"/></svg>
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <footer class="nx13-registry-footer">
                        {{ $projects->links() }}
                    </footer>
                @endif
            </section>

            <aside class="nx13-rail" aria-label="Financial portfolio context">
                <section class="nx13-rail-card is-primary" data-nxl-surface="raised">
                    <header>
                        <span class="nx13-section-kicker">Portfolio coverage</span>
                        <h3>Financial Basis Chain</h3>
                    </header>
                    <div class="nx13-coverage-list">
                        @foreach ([
                            ['label' => 'Current technical basis', 'value' => $stats['technical_current'], 'tone' => 'cyan'],
                            ['label' => 'Linked priced BOQ', 'value' => $stats['priced_boq'], 'tone' => 'blue'],
                            ['label' => 'Financial basis ready', 'value' => $stats['basis_ready'], 'tone' => 'green'],
                            ['label' => 'Stored analyses', 'value' => $stats['stored_analyses'], 'tone' => 'purple'],
                        ] as $coverage)
                            <div class="nx13-coverage-item is-{{ $coverage['tone'] }}">
                                <div><span>{{ $coverage['label'] }}</span><b>{{ number_format($coverage['value']) }} / {{ number_format($projectCount) }}</b></div>
                                <span class="nx13-progress"><i style="--nx13-progress: {{ $rate($coverage['value']) }}%"></i></span>
                            </div>
                        @endforeach
                    </div>
                    <p class="nx13-rail-note">Coverage is based on current stored project records only. It is not an approval, bankability score, or scenario recommendation.</p>
                    <div class="nx13-rail-context">
                        <h4>Model basis</h4>
                        <div class="nx13-rail-context-list">
                            <p><span>IDR</span><b>Currency basis</b><small>Financial values in this registry use the current IDR basis.</small></p>
                            <p><span>S</span><b>Source freshness</b><small>Saved results are checked against the current technical calculation and priced BOQ before outputs are treated as current.</small></p>
                        </div>
                    </div>
                </section>

                <section class="nx13-rail-card is-attention" data-nxl-surface>
                    <header>
                        <span class="nx13-section-kicker">Attention queue</span>
                        <h3>Basis Exceptions</h3>
                    </header>
                    <div class="nx13-attention-list">
                        @if ($stats['technical_basis_required'] > 0)
                            <div><span class="is-red"></span><p><strong>{{ number_format($stats['technical_basis_required']) }}</strong> Technical / energy basis required<small>No current positive annual-energy technical basis is available for these projects.</small></p></div>
                        @endif
                        @if ($stats['technical_stale'] > 0)
                            <div><span class="is-amber"></span><p><strong>{{ number_format($stats['technical_stale']) }}</strong> Technical basis stale<small>Upstream load, solar, roof/site, or recommendation data changed after calculation.</small></p></div>
                        @endif
                        @if ($stats['pricing_required'] > 0)
                            <div><span class="is-blue"></span><p><strong>{{ number_format($stats['pricing_required']) }}</strong> BOQ pricing required<small>Technical basis is current but no linked priced BOQ is ready.</small></p></div>
                        @endif
                        @if ($stats['ready_without_analysis'] > 0)
                            <div><span class="is-cyan"></span><p><strong>{{ number_format($stats['ready_without_analysis']) }}</strong> Ready without analysis<small>Financial prerequisites are present, but no financial analysis has been saved.</small></p></div>
                        @endif
                        @if ($stats['legacy_locked'] > 0)
                            <div><span class="is-purple"></span><p><strong>{{ number_format($stats['legacy_locked']) }}</strong> Legacy locked records<small>This legacy locked state is preserved for the record; no current action changes this state.</small></p></div>
                        @endif
                        @if (($stats['technical_basis_required'] + $stats['technical_stale'] + $stats['pricing_required'] + $stats['ready_without_analysis'] + $stats['legacy_locked']) === 0)
                            <div><span class="is-cyan"></span><p><strong>0</strong> Tracked basis exceptions<small>No exception is present in the states summarized by this registry.</small></p></div>
                        @endif
                    </div>

                    <div class="nx13-rail-context is-analysis">
                        <h4>Analysis boundaries</h4>
                        <div class="nx13-rail-context-list">
                            <p><span>1×</span><b>Analysis structure</b><small>Each project currently has one saved financial analysis. Scenario comparison is not yet available here.</small></p>
                            <p><span>A</span><b>Explicit assumptions</b><small>Tariff, self-consumption, O&amp;M, discount rate, degradation, project life, and replacement settings remain model assumptions.</small></p>
                        </div>
                    </div>

                    <div class="nx13-output-context">
                        <h4>Output interpretation</h4>
                        <div class="nx13-output-guide">
                            <p><strong>NPV</strong><span>Sign is descriptive only; no investment verdict is inferred.</span></p>
                            <p><strong>IRR</strong><span>Shown only when the saved calculation produced a value.</span></p>
                            <p><strong>Payback</strong><span>Secondary metric; unreached results reference the analysis horizon.</span></p>
                            <p><strong>LCOE</strong><span>Shown only while the saved source basis remains current.</span></p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <footer class="nx13-basis-footer">
            <span>Data basis</span>
            <p>Current visible projects · current technical results · linked priced BOQ · saved financial source basis · archived projects excluded.</p>
        </footer>
    </main>
@endsection
