@extends('layouts.app')

@section('title', 'Financial Analysis')
@section('page-eyebrow', 'Business output')
@section('page-heading', 'Financial Analysis')

@push('styles')
    <link href="{{ asset('css/nexorin-financial-analysis.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-financial-analysis.js') }}?v=55a" defer></script>
@endpush

@section('content')
@php
    $money = function ($value, $decimals = 0) {
        if ($value === null) return '—';
        $number = (float) $value;
        $sign = $number < 0 ? '−' : '';
        return 'IDR '.$sign.number_format(abs($number), $decimals, '.', ',');
    };
    $number = fn ($value, $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, '.', ',');
    $summary = $analysis?->summary ?? [];
    $cashflows = array_values($analysis?->cashflows ?? []);
    $warnings = array_values($analysis?->warnings ?? []);
    $sourceSnapshot = $analysis?->source_snapshot ?? [];

    $technicalReady = (bool) ($calculation?->calculated_at && $calculation?->results);
    $bomReady = (bool) ($bom && $bom->pricing_status === 'priced' && (float) $bom->grand_price_idr > 0);
    $analysisReady = (bool) $analysis;

    $technicalSnapshot = $sourceSnapshot['technical_calculated_at'] ?? null;
    $bomSnapshot = $sourceSnapshot['bom_priced_at'] ?? null;
    $technicalCurrent = (bool) ($analysis && $technicalSnapshot && $technicalSnapshot === optional($calculation?->calculated_at)->toISOString());
    $bomCurrent = (bool) ($analysis && $bomSnapshot && $bomSnapshot === optional($bom?->priced_at)->toISOString());
    $sourcesCurrent = (bool) ($analysis && $technicalCurrent && $bomCurrent);

    $tariff = (float) ($analysis?->electricity_tariff_idr_per_kwh ?? $parameters['electricity_tariff_idr_per_kwh'] ?? 0);
    $discountRate = (float) ($analysis?->discount_rate_percent ?? $parameters['discount_rate_percent'] ?? 0);
    $lifetime = (int) ($analysis?->project_lifetime_years ?? $parameters['project_lifetime_years'] ?? 25);
    $npv = $analysis ? (float) $analysis->npv_idr : null;
    $irr = $analysis?->irr_percent === null ? null : (float) $analysis->irr_percent;
    $lcoe = $analysis?->lcoe_idr_per_kwh === null ? null : (float) $analysis->lcoe_idr_per_kwh;
    $payback = $analysis?->simple_payback_years === null ? null : (float) $analysis->simple_payback_years;
    $capex = $analysis ? (float) $analysis->project_capex_idr : (float) ($bom?->grand_price_idr ?? 0);

    $npvPass = $analysis && $npv >= 0;
    $irrPass = $analysis && $irr !== null && $irr >= $discountRate;
    $lcoePass = $analysis && $lcoe !== null && $tariff > 0 && $lcoe <= $tariff;
    $decisionPasses = collect([$npvPass, $irrPass, $lcoePass])->filter()->count();

    $viability = 'unassessed';
    if ($analysis) {
        $viability = ($npvPass && $irrPass && $lcoePass)
            ? 'favorable'
            : (($decisionPasses >= 2 || $npvPass) ? 'marginal' : 'unfavorable');
    }
    $viabilityLabel = match ($viability) {
        'favorable' => 'Favorable economics',
        'marginal' => 'Marginal · review required',
        'unfavorable' => 'Unfavorable economics',
        default => 'Not assessed',
    };
    $viabilityCopy = match ($viability) {
        'favorable' => 'The current NPV, hurdle-rate, and unit-energy checks support the active investment basis.',
        'marginal' => 'Some economic hurdles are met, but the result needs controlled review before customer release.',
        'unfavorable' => 'The current assumptions do not clear the main investment hurdles. Review CAPEX, tariff, yield, and financing assumptions.',
        default => 'Complete the technical and priced BOQ basis, then run the financial engine to establish an investment signal.',
    };

    $breakEvenYear = null;
    foreach ($cashflows as $row) {
        if ((float) ($row['cumulative_cashflow_idr'] ?? -1) >= 0) {
            $breakEvenYear = (int) ($row['year'] ?? 0);
            break;
        }
    }
    $replacementYear = (int) ($analysis?->replacement_year ?? $parameters['replacement_year'] ?? 0);

    $irrDelta = $analysis && $irr !== null ? $irr - $discountRate : null;
    $irrDeltaCopy = $irrDelta === null
        ? 'IRR is not resolved against the active hurdle.'
        : $number(abs($irrDelta)).' pp '.($irrDelta >= 0 ? 'above' : 'below').' the '.$number($discountRate).'% hurdle.';
    $lcoeDeltaPercent = $analysis && $lcoe !== null && $tariff > 0
        ? (($lcoe - $tariff) / $tariff) * 100
        : null;
    $lcoeDeltaCopy = $lcoeDeltaPercent === null
        ? 'Unit-energy comparison is not resolved.'
        : $number(abs($lcoeDeltaPercent), 1).'% '.($lcoeDeltaPercent <= 0 ? 'below' : 'above').' the active tariff.';

    $savingYield = $analysis && $capex > 0
        ? min(100, max(0, round(((float) $analysis->first_year_saving_idr / $capex) * 100)))
        : 0;
    $paybackPosition = $analysis && $payback !== null && $lifetime > 0
        ? min(100, max(0, round(($payback / $lifetime) * 100)))
        : 0;
    $npvSignal = ! $analysis ? 0 : ($npvPass ? 100 : 28);

    $chartPayload = [
        'cashflows' => array_map(fn ($row) => [
            'year' => (int) ($row['year'] ?? 0),
            'net' => round((float) ($row['net_cashflow_idr'] ?? 0), 2),
            'cumulative' => round((float) ($row['cumulative_cashflow_idr'] ?? 0), 2),
        ], $cashflows),
        'benchmark' => [
            'tariff' => round($tariff, 2),
            'lcoe' => round((float) ($lcoe ?? 0), 2),
        ],
        'breakEvenYear' => $breakEvenYear,
        'replacementYear' => $replacementYear,
    ];

    $nextAction = ! $technicalReady
        ? ['label' => 'Open Technical Calculation', 'route' => route('companies.technical-calculations.show', [$company, $project]), 'kind' => 'technical']
        : (! $bomReady
            ? ['label' => 'Open BOM & BOQ', 'route' => route('companies.boms.show', [$company, $project]), 'kind' => 'bom']
            : null);

    $quotationReady = (bool) ($analysis && $technicalReady && $bomReady && $sourcesCurrent);
    $capexSourceLabel = 'Awaiting priced BOQ';
    $capexSourceState = 'blocked';
    if ($analysis) {
        if ($bomReady && $bomCurrent) {
            $capexSourceLabel = $bom->bom_number ?? 'Current priced BOQ';
            $capexSourceState = 'current';
        } elseif ($bomSnapshot) {
            $capexSourceLabel = 'Historical BOQ snapshot';
            $capexSourceState = 'stale';
        } else {
            $capexSourceLabel = 'Recorded analysis snapshot';
            $capexSourceState = 'review';
        }
    } elseif ($bomReady) {
        $capexSourceLabel = $bom->bom_number ?? 'Current priced BOQ';
        $capexSourceState = 'current';
    }

    $analysisStateLabel = ! $analysis
        ? 'Not calculated'
        : ($sourcesCurrent ? ($analysis->status_label ?? 'Calculated') : 'Calculated · rerun required');
    $analysisStateTone = ! $analysis ? 'blocked' : ($sourcesCurrent ? 'current' : 'stale');
    $formatSnapshotTime = function ($value) {
        if (! $value) return 'Not recorded';
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y · H:i');
        } catch (\Throwable $exception) {
            return 'Invalid snapshot timestamp';
        }
    };
    $technicalSnapshotDisplay = $formatSnapshotTime($technicalSnapshot);
    $bomSnapshotDisplay = $formatSnapshotTime($bomSnapshot);
    $sourceBomReference = $bom?->bom_number
        ?? (($sourceSnapshot['bill_of_material_id'] ?? null) ? 'BOQ snapshot #'.$sourceSnapshot['bill_of_material_id'] : 'Unavailable');
@endphp

<div class="nxfa-workspace" data-nxfa-workspace>
    <section class="nxfa-command-hero" data-nxfa-reveal>
        <div class="nxfa-command-copy">
            <span class="nxfa-kicker">Financial decision cockpit</span>
            <h2 data-nxfa-project-name>{{ $project->name }}</h2>
            <p>
                Translate verified engineering energy output and the priced BOQ basis into transparent cashflow, investment hurdles, and a controlled customer-release decision.
            </p>
            <div class="nxfa-command-meta">
                <span data-nxfa-project-number>{{ $project->project_number }}</span>
                <span>{{ $project->customer?->name ?? 'Customer unavailable' }}</span>
                <span>{{ $analysis?->analysis_number ?? 'Analysis not created' }}</span>
            </div>
        </div>

        <aside class="nxfa-command-panel">
            <div class="nxfa-command-status is-{{ $viability }}">
                <span>Investment signal</span>
                <strong>{{ $viabilityLabel }}</strong>
            </div>
            <p>{{ $viabilityCopy }}</p>
            <div class="nxfa-command-telemetry">
                <div class="is-{{ $analysisStateTone }}">
                    <span>Analysis state</span>
                    <strong>{{ $analysisStateLabel }}</strong>
                </div>
                <div>
                    <span>Last engine run</span>
                    <strong>{{ $analysis?->calculated_at?->format('d M Y · H:i') ?? 'Not run' }}</strong>
                </div>
                <div class="is-{{ $capexSourceState }}">
                    <span>CAPEX source</span>
                    <strong>{{ $capexSourceLabel }}</strong>
                </div>
                <div>
                    <span>Decision score</span>
                    <strong>{{ $analysis ? $decisionPasses.'/3 hurdles' : 'Pending' }}</strong>
                </div>
            </div>
            <div class="nxfa-command-actions">
                <a href="{{ route('companies.boms.show', [$company, $project]) }}" class="nxfa-command-action" data-nxfa-navigate="bom">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    BOM &amp; BOQ
                </a>
                @if ($quotationReady)
                    <a href="{{ route('companies.quotations.show', [$company, $project]) }}" class="nxfa-command-action" data-nxfa-navigate="quotation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                        Quotation
                    </a>
                @else
                    <span class="nxfa-command-action is-disabled" aria-disabled="true" title="Refresh the priced BOQ and rerun Financial Analysis before quotation release.">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 10V8a5 5 0 0 1 10 0v2"/><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M12 14v2"/></svg>
                        Quotation blocked
                    </span>
                @endif
                <a href="{{ route('companies.financial-analyses.index', $company) }}" class="nxfa-command-action" data-nxfa-navigate="directory">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m15 18-6-6 6-6"/><path d="M9 12h10"/></svg>
                    Registry
                </a>
            </div>
        </aside>
    </section>

    @if ($errors->any() || $warnings !== [])
        <div class="nxfa-alert-stack" data-nxfa-reveal>
            @if ($errors->any())
                <div class="nx-feedback nx-feedback--danger" role="alert" data-alert>
                    <span class="nx-feedback-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4M12 17h.01"/></svg>
                    </span>
                    <div class="nx-feedback-body">
                        <span class="nx-feedback-kicker">Financial workflow exception</span>
                        <strong class="nx-feedback-title">Financial analysis could not be processed</strong>
                        <p class="nx-feedback-copy">Resolve the following controlled validation issue before running the financial engine again.</p>
                        <ul class="nx-feedback-list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7 7 10 10M17 7 7 17"/></svg>
                    </button>
                </div>
            @endif

            @if ($warnings !== [])
                <div class="nx-feedback nx-feedback--warning" role="status" data-alert>
                    <span class="nx-feedback-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    </span>
                    <div class="nx-feedback-body">
                        <span class="nx-feedback-kicker">Investment review signal</span>
                        <strong class="nx-feedback-title">Financial assumptions require attention</strong>
                        <p class="nx-feedback-copy">The engine completed the analysis and recorded the following review conditions.</p>
                        <ul class="nx-feedback-list">
                            @foreach ($warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7 7 10 10M17 7 7 17"/></svg>
                    </button>
                </div>
            @endif
        </div>
    @endif

    <nav class="nxfa-command-bar" data-nxfa-command-bar aria-label="Financial analysis sections">
        <a href="#financial-decision">Decision</a>
        <a href="#financial-charts">Charts</a>
        <a href="#financial-assumptions">Assumptions</a>
        @if ($analysis)<a href="#financial-cashflow">Cashflow</a>@endif
        <a href="#financial-governance">Checks &amp; audit</a>
    </nav>

    @if (! $analysis && (! $technicalReady || ! $bomReady))
        <section class="nxfa-panel" data-nxfa-reveal>
            <header class="nxfa-panel-header">
                <div>
                    <span class="nxfa-panel-kicker">Financial readiness gate</span>
                    <h3>Complete the controlled source chain</h3>
                    <p>The financial engine remains blocked until a valid technical result and priced BOM/BOQ are available.</p>
                </div>
                <span class="nxfa-panel-badge">ANALYSIS BLOCKED</span>
            </header>
            <div class="nxfa-panel-body">
                <div class="nxfa-readiness-chain" aria-label="Financial readiness chain">
                    <div class="nxfa-readiness-node {{ $technicalReady ? 'is-complete' : 'is-active' }}">
                        <span>01 · Technical calculation</span>
                        <strong>{{ $technicalReady ? 'Available' : 'Required' }}</strong>
                    </div>
                    <div class="nxfa-readiness-node {{ $bom ? 'is-complete' : ($technicalReady ? 'is-active' : '') }}">
                        <span>02 · Material schedule</span>
                        <strong>{{ $bom ? 'Generated' : 'Pending' }}</strong>
                    </div>
                    <div class="nxfa-readiness-node {{ $bomReady ? 'is-complete' : ($bom ? 'is-active' : '') }}">
                        <span>03 · Priced BOQ</span>
                        <strong>{{ $bomReady ? 'Priced' : 'Required' }}</strong>
                    </div>
                    <div class="nxfa-readiness-node {{ $technicalReady && $bomReady ? 'is-active' : '' }}">
                        <span>04 · Financial analysis</span>
                        <strong>Not calculated</strong>
                    </div>
                    <div class="nxfa-readiness-node">
                        <span>05 · Customer release</span>
                        <strong>Unavailable</strong>
                    </div>
                </div>
                <div class="nxfa-empty-state">
                    <h4>{{ ! $technicalReady ? 'Technical calculation is required' : 'Priced BOM & BOQ is required' }}</h4>
                    <p>{{ ! $technicalReady ? 'Complete the electrical design basis first. Financial analysis cannot infer energy output or project CAPEX without the upstream engineering record.' : 'Generate and price the material schedule before evaluating payback, NPV, IRR, LCOE, and lifecycle cashflow.' }}</p>
                    @if ($nextAction)
                        <div class="nxfa-empty-actions">
                            <a href="{{ $nextAction['route'] }}" class="nxfa-primary-action" data-nxfa-navigate="{{ $nextAction['kind'] }}">{{ $nextAction['label'] }}</a>
                            <a href="{{ route('companies.financial-analyses.index', $company) }}" class="nxfa-secondary-action" data-nxfa-navigate="directory">Back to Financial Registry</a>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section id="financial-decision" class="nxfa-decision-grid" data-nxfa-reveal>
        <article class="nxfa-verdict-card is-{{ $viability }}">
            <div>
                <span class="nxfa-section-kicker">Investment hurdle score</span>
                <h3>Core hurdle assessment</h3>
                <p>NPV, IRR versus the active discount rate, and LCOE versus the customer tariff are evaluated as the three controlled decision checks.</p>
            </div>
            <div class="nxfa-verdict-score">
                <strong>{{ $analysis ? $decisionPasses.'/3' : '—' }}</strong>
                <span>{{ $analysis ? 'Investment hurdles passed' : 'Awaiting financial run' }}</span>
            </div>
        </article>

        <div class="nxfa-metric-grid">
            <article class="nxfa-metric-card" data-nxfa-card>
                <span class="nxfa-metric-label">Project CAPEX</span>
                <strong class="nxfa-metric-value">{{ $capex > 0 ? $money($capex) : '—' }}</strong>
                <small>{{ $analysis ? 'Financial analysis basis' : ($bomReady ? 'Current priced BOQ basis' : 'Awaiting commercial basis') }}</small>
                <span class="nxfa-metric-track"><span style="width:{{ $capex > 0 ? 100 : 0 }}%"></span></span>
            </article>
            <article class="nxfa-metric-card is-blue" data-nxfa-card>
                <span class="nxfa-metric-label">First-year saving</span>
                <strong class="nxfa-metric-value">{{ $analysis ? $money($analysis->first_year_saving_idr) : '—' }}</strong>
                <small>{{ $analysis ? $number($savingYield, 0).'% of CAPEX recovered in year one' : 'Run the financial engine to establish savings' }}</small>
                <span class="nxfa-metric-track"><span style="width:{{ $savingYield }}%"></span></span>
            </article>
            <article class="nxfa-metric-card is-orange" data-nxfa-card>
                <span class="nxfa-metric-label">Simple payback</span>
                <strong class="nxfa-metric-value">{{ $payback !== null ? $number($payback).' years' : '—' }}</strong>
                <small>{{ $analysis ? ($breakEvenYear ? 'Cumulative break-even in year '.$breakEvenYear : 'Break-even not reached in active lifetime') : 'Awaiting analysis' }}</small>
                <span class="nxfa-metric-track"><span style="width:{{ $paybackPosition }}%"></span></span>
            </article>
            <article class="nxfa-metric-card is-violet {{ $analysis && ! $npvPass ? 'is-danger' : '' }}" data-nxfa-card>
                <span class="nxfa-metric-label">Net present value</span>
                <strong class="nxfa-metric-value">{{ $analysis ? $money($analysis->npv_idr) : '—' }}</strong>
                <small>{{ $analysis ? ($npvPass ? 'Positive at '.$number($discountRate).'% discount rate' : 'Negative at '.$number($discountRate).'% discount rate') : 'Awaiting discounted cashflow' }}</small>
                <span class="nxfa-metric-track"><span style="width:{{ $npvSignal }}%"></span></span>
            </article>
        </div>
    </section>

    <section id="financial-charts" class="nxfa-panel" data-nxfa-reveal>
        <header class="nxfa-panel-header">
            <div>
                <span class="nxfa-panel-kicker">Interactive financial intelligence</span>
                <h3>Investment trajectory and economic benchmark</h3>
                <p>Inspect annual and cumulative cashflow, the replacement event, break-even timing, and the relationship between customer electricity tariff and lifecycle energy cost.</p>
            </div>
            <span class="nxfa-panel-badge">25-YEAR FORECAST</span>
        </header>
        <div class="nxfa-panel-body">
            @if ($analysis)
                <div class="nxfa-chart-grid">
                    <article class="nxfa-chart-card">
                        <div class="nxfa-chart-head">
                            <div>
                                <strong>Lifecycle cashflow trajectory</strong>
                                <small>Annual net cashflow and cumulative investment recovery.</small>
                            </div>
                            <div class="nxfa-chart-legend">
                                <span class="is-annual"><i></i>Annual net</span>
                                <span class="is-cumulative"><i></i>Cumulative</span>
                                @if ($replacementYear > 0)
                                    <span class="is-replacement"><i></i>Replacement Y{{ $replacementYear }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="nxfa-chart" data-nxfa-cashflow-chart role="img" aria-label="Lifecycle cashflow chart"></div>
                    </article>

                    <article class="nxfa-chart-card is-benchmark">
                        <div class="nxfa-chart-head">
                            <div>
                                <strong>Tariff versus LCOE</strong>
                                <small>Current unit economics under the active assumptions.</small>
                            </div>
                        </div>
                        <div class="nxfa-chart is-compact" data-nxfa-benchmark-chart role="img" aria-label="Electricity tariff and LCOE comparison chart"></div>
                    </article>
                </div>

                <div class="nxfa-factor-grid" aria-label="Core financial hurdle comparison">
                    <div class="nxfa-factor {{ $npvPass ? 'is-pass' : 'is-fail' }}">
                        <span class="nxfa-factor-copy"><strong>NPV hurdle</strong><small>Must be zero or positive.</small></span>
                        <strong class="nxfa-factor-value">{{ $money($npv) }}</strong>
                    </div>
                    <div class="nxfa-factor {{ $irr === null ? 'is-review' : ($irrPass ? 'is-pass' : 'is-fail') }}">
                        <span class="nxfa-factor-copy"><strong>IRR versus discount rate</strong><small class="nxfa-factor-delta">{{ $irrDeltaCopy }}</small></span>
                        <strong class="nxfa-factor-value">{{ $irr === null ? 'Not resolved' : $number($irr).'%' }}</strong>
                    </div>
                    <div class="nxfa-factor {{ $lcoe === null ? 'is-review' : ($lcoePass ? 'is-pass' : 'is-fail') }}">
                        <span class="nxfa-factor-copy"><strong>LCOE versus tariff</strong><small class="nxfa-factor-delta">{{ $lcoeDeltaCopy }}</small></span>
                        <strong class="nxfa-factor-value">{{ $lcoe === null ? 'Not resolved' : $money($lcoe, 2).' / kWh' }}</strong>
                    </div>
                </div>
                <script type="application/json" id="nxfa-chart-data">{!! json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
            @else
                <div class="nxfa-chart-empty">Financial charts will appear after the current assumptions are calculated against a verified technical result and priced BOQ.</div>
            @endif
        </div>
    </section>

    <section id="financial-assumptions" class="nxfa-panel" data-nxfa-reveal>
        <header class="nxfa-panel-header">
            <div>
                <span class="nxfa-panel-kicker">Controlled financial basis</span>
                <h3>Financial assumptions workspace</h3>
                <p>Review the tariff, energy, financing, lifecycle, replacement, and CAPEX basis. Results are recalculated only when this controlled analysis is run.</p>
            </div>
            <span class="nxfa-panel-badge {{ (! $technicalReady || ! $bomReady) ? 'is-blocked' : '' }}">{{ (! $technicalReady || ! $bomReady) ? 'AWAITING PRICED BOQ' : ($canRun ? 'EDITABLE' : 'VIEW ONLY') }}</span>
        </header>
        <div class="nxfa-panel-body">
            @if (! $technicalReady || ! $bomReady)
                <div class="nxfa-empty-state">
                    <h4>Financial assumptions are waiting for an upstream basis</h4>
                    <p>{{ ! $technicalReady ? 'Run Technical Calculation before creating the financial basis.' : 'Complete BOQ pricing before creating the investment analysis.' }}</p>
                    @if ($nextAction)
                        <div class="nxfa-empty-actions">
                            <a href="{{ $nextAction['route'] }}" class="nxfa-primary-action" data-nxfa-navigate="{{ $nextAction['kind'] }}">{{ $nextAction['label'] }}</a>
                        </div>
                    @endif
                </div>
            @elseif ($canRun)
                <form method="POST" action="{{ route('companies.financial-analyses.calculate', [$company, $project]) }}" class="nxfa-assumption-form" data-nxfa-analysis-form>
                    @csrf
                    <div class="nxfa-assumption-groups">
                        <section class="nxfa-assumption-group">
                            <h4>Energy and tariff basis</h4>
                            <p>Customer tariff, export credit, self-consumption, escalation, and yield degradation.</p>
                            <div class="nxfa-input-grid">
                                <div class="nxfa-input-field is-full">
                                    <label for="electricity-tariff">Electricity tariff</label>
                                    <div class="nxfa-input-shell">
                                        <span class="nxfa-input-affix">IDR</span>
                                        <input id="electricity-tariff" type="number" step="0.01" min="0" name="electricity_tariff_idr_per_kwh" value="{{ old('electricity_tariff_idr_per_kwh', $parameters['electricity_tariff_idr_per_kwh']) }}" data-nxfa-format="tariff">
                                        <span class="nxfa-input-affix">/ kWh</span>
                                    </div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-input-field">
                                    <label for="export-credit">Export credit</label>
                                    <div class="nxfa-input-shell"><input id="export-credit" type="number" step="0.01" min="0" max="100" name="export_credit_percent" value="{{ old('export_credit_percent', $parameters['export_credit_percent']) }}" data-nxfa-format="percent"><span class="nxfa-input-affix">%</span></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-input-field">
                                    <label for="self-consumption">Self-consumption</label>
                                    <div class="nxfa-input-shell"><input id="self-consumption" type="number" step="0.01" min="0" max="100" name="self_consumption_percent" value="{{ old('self_consumption_percent', $parameters['self_consumption_percent']) }}" data-nxfa-format="percent"><span class="nxfa-input-affix">%</span></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-input-field">
                                    <label for="tariff-escalation">Tariff escalation</label>
                                    <div class="nxfa-input-shell"><input id="tariff-escalation" type="number" step="0.01" min="0" max="100" name="tariff_escalation_percent" value="{{ old('tariff_escalation_percent', $parameters['tariff_escalation_percent']) }}" data-nxfa-format="percent"><span class="nxfa-input-affix">% / yr</span></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-input-field">
                                    <label for="energy-degradation">Energy degradation</label>
                                    <div class="nxfa-input-shell"><input id="energy-degradation" type="number" step="0.01" min="0" max="100" name="energy_degradation_percent" value="{{ old('energy_degradation_percent', $parameters['energy_degradation_percent']) }}" data-nxfa-format="percent"><span class="nxfa-input-affix">% / yr</span></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                            </div>
                        </section>

                        <section class="nxfa-assumption-group">
                            <h4>Financing and lifecycle</h4>
                            <p>Required return, annual operating cost, project lifetime, and replacement timing.</p>
                            <div class="nxfa-input-grid">
                                <div class="nxfa-input-field">
                                    <label for="discount-rate">Discount rate</label>
                                    <div class="nxfa-input-shell"><input id="discount-rate" type="number" step="0.01" min="0" max="100" name="discount_rate_percent" value="{{ old('discount_rate_percent', $parameters['discount_rate_percent']) }}" data-nxfa-format="percent"><span class="nxfa-input-affix">%</span></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-input-field">
                                    <label for="annual-om">Annual O&amp;M</label>
                                    <div class="nxfa-input-shell"><input id="annual-om" type="number" step="0.01" min="0" max="100" name="annual_om_percent" value="{{ old('annual_om_percent', $parameters['annual_om_percent']) }}" data-nxfa-format="percent"><span class="nxfa-input-affix">% CAPEX</span></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-input-field">
                                    <label for="lifetime-years">Project lifetime</label>
                                    <div class="nxfa-input-shell"><input id="lifetime-years" type="number" step="1" min="1" max="40" name="project_lifetime_years" value="{{ old('project_lifetime_years', $parameters['project_lifetime_years']) }}" data-nxfa-format="years"><span class="nxfa-input-affix">years</span></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-input-field">
                                    <label for="replacement-year">Replacement event</label>
                                    <div class="nxfa-input-shell"><span class="nxfa-input-affix">Year</span><input id="replacement-year" type="number" step="1" min="0" max="40" name="replacement_year" value="{{ old('replacement_year', $parameters['replacement_year']) }}" data-nxfa-format="year"></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                            </div>
                        </section>

                        <section class="nxfa-assumption-group">
                            <h4>CAPEX and replacement basis</h4>
                            <p>Use the priced BOQ total by default or apply an authorized project CAPEX override.</p>
                            <div class="nxfa-input-grid">
                                <div class="nxfa-input-field is-full">
                                    <label for="replacement-cost">Replacement cost</label>
                                    <div class="nxfa-input-shell"><span class="nxfa-input-affix">IDR</span><input id="replacement-cost" type="number" step="1" min="0" name="replacement_cost_idr" value="{{ old('replacement_cost_idr', $parameters['replacement_cost_idr']) }}" data-nxfa-format="currency"></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-input-field is-full">
                                    <label for="capex-override">Project CAPEX override</label>
                                    <div class="nxfa-input-shell"><span class="nxfa-input-affix">IDR</span><input id="capex-override" type="number" step="1" min="0" name="project_capex_idr" value="{{ old('project_capex_idr', $parameters['project_capex_idr']) }}" data-nxfa-format="currency"></div>
                                    <small class="nxfa-input-output" data-nxfa-format-output></small>
                                </div>
                                <div class="nxfa-note-field is-full">
                                    <label for="analysis-note">Analysis note</label>
                                    <textarea id="analysis-note" name="analysis_note" rows="3" placeholder="Record the commercial, tariff, financing, or customer context behind this run.">{{ old('analysis_note', $parameters['analysis_note']) }}</textarea>
                                </div>
                            </div>
                        </section>
                    </div>

                    <footer class="nxfa-form-footer">
                        <span class="nxfa-dirty-state" data-nxfa-dirty-state>{{ $analysis ? 'Saved assumptions match the latest engine run' : 'Ready to establish the first financial result' }}</span>
                        <button class="nxfa-run-button" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg>
                            {{ $analysis ? 'Recalculate Financial Analysis' : 'Calculate Financial Analysis' }}
                        </button>
                    </footer>
                </form>
            @else
                <div class="nxfa-empty-state">
                    <h4>View-only financial record</h4>
                    <p>Your role can review the active analysis, but it cannot change assumptions or run the financial engine.</p>
                </div>
            @endif
        </div>
    </section>

    @if ($analysis)
        <section id="financial-cashflow" class="nxfa-panel" data-nxfa-reveal>
            <header class="nxfa-panel-header">
                <div>
                    <span class="nxfa-panel-kicker">Lifecycle cashflow register</span>
                    <h3>{{ $lifetime }}-year investment schedule</h3>
                    <p>Review annual energy, gross saving, O&amp;M, replacement cost, net cashflow, and cumulative recovery. Replacement and break-even years are highlighted.</p>
                </div>
                <div class="nxfa-cashflow-tools">
                    <span class="nxfa-panel-badge">{{ count($cashflows) }} YEARS</span>
                    @if (count($cashflows) > 10)
                        <button type="button" class="nxfa-cashflow-toggle" data-nxfa-cashflow-toggle data-total="{{ count($cashflows) }}" aria-expanded="false">Show years 11–{{ count($cashflows) }}</button>
                    @endif
                </div>
            </header>
            <div class="nxfa-panel-body">
                <div class="nxfa-cashflow-table-wrap">
                    <table class="nxfa-cashflow-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Energy</th>
                                <th>Gross saving</th>
                                <th>O&amp;M</th>
                                <th>Replacement</th>
                                <th>Net cashflow</th>
                                <th>Cumulative</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cashflows as $row)
                                @php
                                    $year = (int) ($row['year'] ?? 0);
                                    $isReplacement = $replacementYear > 0 && $year === $replacementYear;
                                    $isBreakEven = $breakEvenYear && $year === $breakEvenYear;
                                @endphp
                                <tr class="{{ $isReplacement ? 'is-replacement' : '' }} {{ $isBreakEven ? 'is-break-even' : '' }}" @if ($loop->iteration > 10) data-nxfa-cashflow-extra hidden @endif>
                                    <td data-label="Year">
                                        @if ($isBreakEven)
                                            <span class="nxfa-table-marker is-break-even"><i></i>{{ $year }}</span>
                                        @elseif ($isReplacement)
                                            <span class="nxfa-table-marker"><i></i>{{ $year }}</span>
                                        @else
                                            {{ $year }}
                                        @endif
                                    </td>
                                    <td data-label="Energy">{{ number_format((float) ($row['energy_kwh'] ?? 0), 0, '.', ',') }} kWh</td>
                                    <td data-label="Gross saving">{{ $money($row['gross_saving_idr'] ?? 0) }}</td>
                                    <td data-label="O&M">{{ $money($row['om_cost_idr'] ?? 0) }}</td>
                                    <td data-label="Replacement">{{ (float) ($row['replacement_cost_idr'] ?? 0) > 0 ? $money($row['replacement_cost_idr']) : '—' }}</td>
                                    <td data-label="Net cashflow" class="{{ (float) ($row['net_cashflow_idr'] ?? 0) < 0 ? 'is-negative' : 'is-positive' }}">{{ $money($row['net_cashflow_idr'] ?? 0) }}</td>
                                    <td data-label="Cumulative" class="{{ (float) ($row['cumulative_cashflow_idr'] ?? 0) < 0 ? 'is-negative' : 'is-positive' }}">{{ $money($row['cumulative_cashflow_idr'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    <section id="financial-governance" class="nxfa-panel" data-nxfa-reveal>
        <header class="nxfa-panel-header">
            <div>
                <span class="nxfa-panel-kicker">Financial governance and trace</span>
                <h3>Customer-release checks and source audit</h3>
                <p>Confirm the active technical source, priced BOQ basis, assumption run, investment hurdles, and analysis authority before downstream quotation release.</p>
            </div>
            <span class="nxfa-panel-badge">CONTROLLED REVIEW</span>
        </header>
        <div class="nxfa-panel-body">
            <div class="nxfa-governance-grid">
                <div class="nxfa-gate-list">
                    @php
                        $gateItems = [
                            ['label' => 'Technical source available', 'copy' => $technicalReady ? 'Electrical design result is available.' : 'Technical calculation is required.', 'state' => $technicalReady ? 'pass' : 'blocked'],
                            ['label' => $bomReady ? 'Priced BOQ available' : 'Priced BOQ unavailable', 'copy' => $bomReady ? 'Customer CAPEX basis is established.' : 'Complete BOM/BOQ pricing before releasing this analysis.', 'state' => $bomReady ? 'pass' : 'blocked'],
                            ['label' => ! $analysis ? 'Source snapshot unavailable' : ($sourcesCurrent ? 'Source snapshot current' : 'Source snapshot stale'), 'copy' => ! $analysis ? 'Run the financial engine to record controlled source timestamps.' : ($sourcesCurrent ? 'Analysis source timestamps match current records.' : 'An upstream source changed after the last run. Rerun the controlled analysis.'), 'state' => $sourcesCurrent ? 'pass' : 'blocked'],
                            ['label' => ! $analysis ? 'Financial analysis not calculated' : ($sourcesCurrent ? 'Financial analysis current' : 'Financial analysis stale'), 'copy' => ! $analysis ? 'Run the financial engine.' : ($sourcesCurrent ? 'Investment metrics and cashflow match the active sources.' : 'A historical result is recorded, but a rerun is required.'), 'state' => ! $analysis ? 'blocked' : ($sourcesCurrent ? 'pass' : 'review')],
                            ['label' => 'Investment hurdles reviewed', 'copy' => ! $analysis ? 'Decision hurdles are pending.' : ($viability === 'favorable' ? 'All core hurdles passed.' : 'Controlled financial review is required.'), 'state' => ! $analysis ? 'blocked' : ($viability === 'favorable' ? 'pass' : 'review')],
                            ['label' => $quotationReady ? 'Quotation release available' : 'Quotation release blocked', 'copy' => $quotationReady ? 'Current technical, BOQ, and financial sources are aligned.' : 'Refresh the priced BOQ and rerun Financial Analysis before customer release.', 'state' => $quotationReady ? ($viability === 'favorable' ? 'pass' : 'review') : 'blocked'],
                        ];
                    @endphp
                    @foreach ($gateItems as $item)
                        <div class="nxfa-gate-item is-{{ $item['state'] }}">
                            <span class="nxfa-gate-icon">
                                @if ($item['state'] === 'pass')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="m5 12 4 4L19 6"/></svg>
                                @elseif ($item['state'] === 'review')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4M12 17h.01"/></svg>
                                @endif
                            </span>
                            <span class="nxfa-gate-copy"><strong>{{ $item['label'] }}</strong><small>{{ $item['copy'] }}</small></span>
                        </div>
                    @endforeach
                </div>

                <aside>
                    <div class="nxfa-source-list">
                        <div class="nxfa-source-row"><span>Technical calculation</span><strong>{{ $calculation?->result_status_label ?? 'Unavailable' }}</strong></div>
                        <div class="nxfa-source-row"><span>Analysis technical snapshot</span><strong>{{ $technicalSnapshotDisplay }}</strong></div>
                        <div class="nxfa-source-row"><span>BOM / BOQ reference</span><strong>{{ $sourceBomReference }}</strong></div>
                        <div class="nxfa-source-row"><span>Analysis BOQ snapshot</span><strong>{{ $bomSnapshotDisplay }}</strong></div>
                        <div class="nxfa-source-row"><span>Current BOQ state</span><strong class="{{ $bomReady ? 'is-current' : 'is-stale' }}">{{ $bomReady ? 'PRICED' : ($bom ? 'NOT PRICED' : 'UNAVAILABLE') }}</strong></div>
                        <div class="nxfa-source-row"><span>Source freshness</span><strong class="{{ $sourcesCurrent ? 'is-current' : 'is-stale' }}">{{ ! $analysis ? 'NOT RECORDED' : ($sourcesCurrent ? 'CURRENT' : 'STALE · RERUN REQUIRED') }}</strong></div>
                        <div class="nxfa-source-row"><span>Calculated by</span><strong>{{ $analysis?->calculator?->name ?? 'Not recorded' }}</strong></div>
                        <div class="nxfa-source-row"><span>Financial run</span><strong>{{ $analysis?->calculated_at?->format('d M Y · H:i') ?? 'Not calculated' }}</strong></div>
                    </div>
                    <div class="nxfa-audit-note">
                        {{ $analysis?->notes ?: 'No analyst note has been recorded for the active financial basis.' }}
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
@endsection
