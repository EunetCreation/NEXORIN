@extends('layouts.app')

@section('title', 'Financial Analysis')
@section('page-eyebrow', 'Financial engineering')
@section('page-heading', 'Financial Analysis')
@section('force-theme', 'dark')
@section('body-class', 'page-financial-show-v6')
@section('disable-remote-fonts', '1')

@php
    $money = static fn ($value): string => $value === null ? 'Not available' : 'IDR '.number_format((float) $value, 0, '.', ',');
    $moneyCompact = static function ($value): string {
        if ($value === null) return 'Not available';
        $number = (float) $value;
        $sign = $number < 0 ? '-' : '';
        $absolute = abs($number);
        if ($absolute >= 1_000_000_000_000) return $sign.'IDR '.number_format($absolute / 1_000_000_000_000, 2).'T';
        if ($absolute >= 1_000_000_000) return $sign.'IDR '.number_format($absolute / 1_000_000_000, 2).'B';
        if ($absolute >= 1_000_000) return $sign.'IDR '.number_format($absolute / 1_000_000, 2).'M';
        if ($absolute >= 1_000) return $sign.'IDR '.number_format($absolute / 1_000, 1).'K';
        return $sign.'IDR '.number_format($absolute, 0);
    };
    $number = static fn ($value, int $decimals = 2): string => $value === null ? 'Not available' : number_format((float) $value, $decimals, '.', ',');
    $dateTime = static fn ($value): string => $value ? $value->format('d M Y · H:i') : 'Not recorded';
    $dateOnly = static fn ($value): string => $value ? $value->format('d M Y') : 'Not recorded';
    $cashflows = array_values(is_array($analysis?->cashflows) ? $analysis->cashflows : []);
    $warnings = array_values(array_filter(is_array($analysis?->warnings) ? $analysis->warnings : [], fn ($item) => is_string($item) && trim($item) !== ''));
    $summary = is_array($analysis?->summary) ? $analysis->summary : [];
    $freshnessState = $analysis ? ($freshness['state'] ?? 'review_required') : 'not_calculated';
    $freshnessLabel = $analysis ? ($freshness['label'] ?? 'Source Review Required') : 'Not Calculated';
    $freshnessDetail = $analysis ? ($freshness['detail'] ?? 'Source freshness could not be determined.') : 'No saved financial result exists yet.';
    $storedQualifier = match ($freshnessState) {
        'current' => 'Current saved result',
        'source_changed' => 'Stored result · source changed',
        'review_required' => 'Stored result · source review required',
        default => 'Not calculated',
    };
    $analysisNumber = $analysis?->analysis_number ?: 'Not assigned';
    $technicalReference = $calculation ? 'Technical Calculation #'.$calculation->getKey() : 'Not available';
    $boqReference = $bom?->bom_number ?: ($bom ? 'BOQ #'.$bom->getKey() : 'Not available');
    $systemType = $calculation?->system_type ? str((string) $calculation->system_type)->replace('_', ' ')->title() : 'Not recorded';
    $technicalStatus = $calculation?->result_status ? str((string) $calculation->result_status)->replace('_', ' ')->title() : 'Not available';
    $sourceBadge = static fn (string $source): string => match ($source) {
        'saved' => 'Saved assumption',
        'priced_boq' => 'Priced BOQ basis',
        default => 'Default assumption',
    };
    $sourceClass = static fn (string $source): string => match ($source) {
        'saved' => 'is-saved',
        'priced_boq' => 'is-derived',
        default => 'is-default',
    };
    $assumptionFields = [
        [
            'name' => 'electricity_tariff_idr_per_kwh',
            'label' => 'Electricity tariff',
            'unit' => 'IDR/kWh',
            'step' => '0.01',
            'min' => '0',
            'max' => null,
            'helper' => 'Calculation assumption for avoided import value. It is not a verified utility tariff without project-specific evidence.',
        ],
        [
            'name' => 'self_consumption_percent',
            'label' => 'Self-consumption',
            'unit' => '%',
            'step' => '0.01',
            'min' => '0',
            'max' => '100',
            'helper' => 'Annual model assumption, not measured interval energy flow. Blank uses the current model default: 92% for Hybrid, 80% otherwise.',
        ],
        [
            'name' => 'export_credit_percent',
            'label' => 'Export credit',
            'unit' => '% of tariff',
            'step' => '0.01',
            'min' => '0',
            'max' => '100',
            'helper' => 'Commercial model assumption. A value above 0 requires a project-specific utility or commercial basis.',
        ],
        [
            'name' => 'tariff_escalation_percent',
            'label' => 'Tariff escalation',
            'unit' => '% / year',
            'step' => '0.01',
            'min' => '0',
            'max' => '100',
            'helper' => 'Annual escalation assumption. The current model also applies this rate to annual O&M escalation.',
        ],
        [
            'name' => 'annual_om_percent',
            'label' => 'Annual O&M',
            'unit' => '% of CAPEX',
            'step' => '0.01',
            'min' => '0',
            'max' => '100',
            'helper' => 'Annual model assumption calculated as a percentage of the analysis CAPEX basis.',
        ],
        [
            'name' => 'discount_rate_percent',
            'label' => 'Discount rate',
            'unit' => '%',
            'step' => '0.01',
            'min' => '0',
            'max' => '100',
            'helper' => 'Assumption used in discounted cashflow calculations. It is not labeled as WACC or a hurdle rate in the current model.',
        ],
        [
            'name' => 'energy_degradation_percent',
            'label' => 'Energy degradation',
            'unit' => '% / year',
            'step' => '0.01',
            'min' => '0',
            'max' => '100',
            'helper' => 'Financial-model assumption. It is not a product warranty degradation value unless independently sourced.',
        ],
        [
            'name' => 'project_lifetime_years',
            'label' => 'Analysis horizon',
            'unit' => 'years',
            'step' => '1',
            'min' => '1',
            'max' => '40',
            'helper' => 'Number of years included in this saved financial model.',
        ],
        [
            'name' => 'replacement_year',
            'label' => 'Replacement year',
            'unit' => 'year',
            'step' => '1',
            'min' => '0',
            'max' => '40',
            'helper' => 'Assumed replacement event. It is not a predicted failure, maintenance schedule, or warranty event.',
        ],
        [
            'name' => 'replacement_cost_idr',
            'label' => 'Replacement cost',
            'unit' => 'IDR',
            'step' => '1',
            'min' => '0',
            'max' => null,
            'helper' => 'Cost assumption applied in the configured replacement year.',
        ],
        [
            'name' => 'project_capex_idr',
            'label' => 'Analysis CAPEX',
            'unit' => 'IDR',
            'step' => '1',
            'min' => '0',
            'max' => null,
            'helper' => 'Uses the priced BOQ reference unless edited. A custom value changes this financial model only; it does not change the BOQ.',
        ],
    ];
    $chartPayload = [
        'currency' => 'IDR',
        'qualifier' => $storedQualifier,
        'replacementYear' => $analysis?->replacement_year,
        'cashflows' => array_map(static fn (array $row): array => [
            'year' => (int) ($row['year'] ?? 0),
            'annual' => (float) ($row['net_cashflow_idr'] ?? 0),
            'cumulative' => (float) ($row['cumulative_cashflow_idr'] ?? 0),
        ], $cashflows),
    ];
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001014.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.014" rel="stylesheet">
    <link href="{{ asset('css/nexorin-financial-analysis.css') }}?v=014b" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}" defer></script>
    <script src="{{ asset('js/nexorin-financial-analysis.js') }}?v=014b" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.014" defer></script>
@endpush

@section('content')
<main class="nx14" data-nx14-page>
    <header class="nx14-head">
        <div class="nx14-head-copy">
            <span class="nx14-kicker">Financial engineering · current saved model</span>
            <div class="nx14-title-line">
                <h2>{{ $project->name }}</h2>
                @if ($project->isArchived())
                    <span class="nx14-state-chip is-attention">Archived project</span>
                @endif
            </div>
            <p>
                Review the current technical and priced BOQ basis, explicit model assumptions, stored financial outputs, cashflows, warnings, and source freshness for this project.
            </p>
            <div class="nx14-identifiers" aria-label="Project financial references">
                <span class="nx14-mono">{{ $project->project_number ?: 'Project number not recorded' }}</span>
                <i aria-hidden="true"></i>
                <span class="nx14-mono">{{ $analysisNumber }}</span>
                @if ($project->customer)
                    <i aria-hidden="true"></i>
                    <span>{{ $project->customer->name }}</span>
                @endif
            </div>
        </div>

        <div class="nx14-head-actions">
            <div class="nx14-head-statuses">
                <span class="nx14-state-chip is-{{ $basisState['tone'] }}">{{ $basisState['label'] }}</span>
                <span class="nx14-state-chip is-{{ $freshnessState }}">{{ $freshnessLabel }}</span>
            </div>
            <div class="nx14-action-row">
                @if ($canOpenRegistry)
                    <a href="{{ route('companies.financial-analyses.index', $company) }}" class="nx14-button is-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/><path d="M9 12h11"/></svg>
                        Financial Registry
                    </a>
                @endif
                @if ($canRun)
                    <button type="submit" form="nx14-analysis-form" class="nx14-button is-primary" data-nx14-run>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v4M12 17v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M3 12h4M17 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                        {{ $analysis ? 'Recalculate Analysis' : 'Run Analysis' }}
                    </button>
                @endif
            </div>
        </div>
    </header>

    @if (session('success'))
        <div class="nx14-feedback is-success" role="status">
            <strong>Financial analysis updated</strong>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="nx14-feedback is-error" role="alert">
            <strong>Financial analysis not updated</strong>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="nx14-feedback is-error" role="alert" data-nx14-validation>
            <strong>Review the model inputs</strong>
            <span>{{ $errors->first('financial_basis') ?: 'One or more financial assumptions need attention before the model can run.' }}</span>
        </div>
    @endif

    @if ($analysis)
        <section class="nx14-output-band" aria-label="Stored financial outputs" data-nxl-surface="raised">
            <article class="nx14-output is-capex">
                <span>Analysis CAPEX</span>
                <strong title="{{ $money($analysis->project_capex_idr) }}">{{ $moneyCompact($analysis->project_capex_idr) }}</strong>
                <small>{{ $capexBasis['label'] }}</small>
            </article>
            <article class="nx14-output">
                <span>Modeled first-year benefit</span>
                <strong title="{{ $money($analysis->first_year_saving_idr) }}">{{ $moneyCompact($analysis->first_year_saving_idr) }}</strong>
                <small>Stored model output</small>
            </article>
            <article class="nx14-output {{ (float) $analysis->npv_idr < 0 ? 'is-negative' : '' }}">
                <span>NPV</span>
                <strong title="{{ $money($analysis->npv_idr) }}">{{ $moneyCompact($analysis->npv_idr) }}</strong>
                <small>At {{ $number($analysis->discount_rate_percent, 2) }}% discount rate</small>
            </article>
            <article class="nx14-output">
                <span>IRR</span>
                <strong>{{ $analysis->irr_percent === null ? 'Not available' : $number($analysis->irr_percent, 2).'%' }}</strong>
                <small>Stored model output</small>
            </article>
            <article class="nx14-output">
                <span>Simple Payback</span>
                <strong>{{ $analysis->simple_payback_years === null ? 'Not reached' : $number($analysis->simple_payback_years, 2).' yr' }}</strong>
                <small>{{ $analysis->simple_payback_years === null ? 'Within the analysis horizon' : 'Secondary descriptive metric' }}</small>
            </article>
            <article class="nx14-output">
                <span>LCOE</span>
                <strong>{{ $analysis->lcoe_idr_per_kwh === null ? 'Not available' : 'IDR '.number_format((float) $analysis->lcoe_idr_per_kwh, 0, '.', ',') }}</strong>
                <small>IDR/kWh · descriptive output</small>
            </article>
        </section>
    @else
        <section class="nx14-basis-band" data-nxl-surface="raised">
            <div>
                <span class="nx14-section-kicker">Financial model state</span>
                <strong>No saved financial analysis</strong>
                <p>{{ $basisState['ready'] ? 'The source basis is complete. Review the assumptions and run the model to create the current saved analysis.' : $basisState['detail'] }}</p>
            </div>
            <dl>
                <div><dt>Annual energy</dt><dd>{{ $basisState['annual_energy_kwh'] > 0 ? number_format($basisState['annual_energy_kwh'], 0).' kWh/year' : 'Not available' }}</dd></div>
                <div><dt>Priced BOQ reference</dt><dd>{{ $bom && (float) $bom->grand_price_idr > 0 ? $moneyCompact($bom->grand_price_idr) : 'Not available' }}</dd></div>
                <div><dt>Model currency</dt><dd>IDR</dd></div>
            </dl>
        </section>
    @endif

    <div class="nx14-workspace">
        <div class="nx14-main-column">
            <section class="nx14-model-card" data-nxl-surface="hero">
                <header class="nx14-section-head">
                    <div>
                        <span class="nx14-section-kicker">Stored project cashflow</span>
                        <h3>Annual &amp; Cumulative Cashflow</h3>
                        <p>Rendered only from cashflow rows stored with the current saved financial record. No missing years or scenarios are synthesized.</p>
                    </div>
                    <span class="nx14-qualifier is-{{ $freshnessState }}">{{ $storedQualifier }}</span>
                </header>

                @if ($analysis && count($cashflows) > 0)
                    <div class="nx14-chart-legend" aria-hidden="true">
                        <span><i class="is-annual"></i>Annual net cashflow</span>
                        <span><i class="is-cumulative"></i>Cumulative cashflow</span>
                    </div>
                    <div class="nx14-chart" id="nx14-cashflow-chart" role="img" aria-label="Stored annual and cumulative project cashflow chart"></div>
                    <script type="application/json" id="nx14-chart-data">@json($chartPayload)</script>
                    <div class="nx14-chart-foot">
                        <span>Year 0 initial CAPEX is represented by the starting cumulative position; annual bars begin at Year 1.</span>
                        @if ($analysis->replacement_year && (float) $analysis->replacement_cost_idr > 0)
                            <b>Replacement assumption: Year {{ $analysis->replacement_year }} · {{ $moneyCompact($analysis->replacement_cost_idr) }}</b>
                        @endif
                    </div>
                @elseif ($analysis)
                    <div class="nx14-empty-surface">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 19h16"/><path d="M7 16v-5M12 16V7M17 16v-3"/></svg>
                        <div><strong>Cashflow rows not recorded</strong><p>The saved analysis does not contain cashflow detail, so no chart is shown.</p></div>
                    </div>
                @else
                    <div class="nx14-empty-surface">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 19h16"/><path d="M7 16v-5M12 16V7M17 16v-3"/></svg>
                        <div><strong>Cashflow not calculated</strong><p>Run the financial model after the required technical and priced BOQ basis is complete.</p></div>
                    </div>
                @endif
            </section>

            <section class="nx14-assumptions-card" data-nxl-surface="inset">
                <header class="nx14-section-head is-form-head">
                    <div>
                        <span class="nx14-section-kicker">Financial model inputs</span>
                        <h3>Assumptions &amp; CAPEX Basis</h3>
                        <p>Defaults are explicit model assumptions. Saved values remain visible on return; no assumption is presented as independently verified.</p>
                    </div>
                    @if ($canRun)
                        <span class="nx14-edit-state" data-nx14-edit-state>Saved model inputs</span>
                    @elseif (! $canCalculate)
                        <span class="nx14-edit-state is-readonly">Read-only for this role</span>
                    @else
                        <span class="nx14-edit-state is-readonly">Inputs unlock when the financial basis is ready</span>
                    @endif
                </header>

                @if ($canRun)
                    <form id="nx14-analysis-form" method="POST" action="{{ route('companies.financial-analyses.calculate', [$company, $project]) }}" class="nx14-assumption-form" data-nx14-form>
                        @csrf
                        <div class="nx14-assumption-grid">
                            @foreach ($assumptionFields as $field)
                                @php
                                    $fieldName = $field['name'];
                                    $source = $parameterSources[$fieldName] ?? 'default';
                                    $fieldValue = old($fieldName, $parameters[$fieldName] ?? null);
                                @endphp
                                <label class="nx14-field {{ $errors->has($fieldName) ? 'has-error' : '' }}" for="nx14-{{ $fieldName }}">
                                    <span class="nx14-field-top">
                                        <b>{{ $field['label'] }}</b>
                                        <em class="nx14-source-badge {{ $sourceClass($source) }}">{{ $sourceBadge($source) }}</em>
                                    </span>
                                    <span class="nx14-input-shell">
                                        <input
                                            id="nx14-{{ $fieldName }}"
                                            name="{{ $fieldName }}"
                                            type="number"
                                            inputmode="decimal"
                                            value="{{ $fieldValue }}"
                                            step="{{ $field['step'] }}"
                                            min="{{ $field['min'] }}"
                                            @if($field['max'] !== null) max="{{ $field['max'] }}" @endif
                                            autocomplete="off"
                                            data-nx14-input
                                        >
                                        <span>{{ $field['unit'] }}</span>
                                    </span>
                                    <small>{{ $field['helper'] }}</small>
                                    @error($fieldName)<strong class="nx14-field-error">{{ $message }}</strong>@enderror
                                </label>
                            @endforeach
                        </div>

                        <label class="nx14-note-field {{ $errors->has('analysis_note') ? 'has-error' : '' }}" for="nx14-analysis-note">
                            <span class="nx14-field-top">
                                <b>Analysis note</b>
                                <em class="nx14-source-badge {{ $sourceClass($parameterSources['analysis_note'] ?? 'default') }}">{{ $sourceBadge($parameterSources['analysis_note'] ?? 'default') }}</em>
                            </span>
                            <textarea id="nx14-analysis-note" name="analysis_note" rows="4" maxlength="1000" placeholder="Record the project-specific financial model basis or review note." data-nx14-input>{{ old('analysis_note', $parameters['analysis_note'] ?? '') }}</textarea>
                            <small>Free-text project note only. It is not a verification, approval, or investment recommendation.</small>
                            @error('analysis_note')<strong class="nx14-field-error">{{ $message }}</strong>@enderror
                        </label>

                        <div class="nx14-run-bar">
                            <div>
                                <strong>{{ $analysis ? 'Recalculation updates this project’s current saved financial analysis.' : 'Running the model creates this project’s current saved financial analysis.' }}</strong>
                                <span>The current architecture stores one financial record per project; it does not keep immutable financial run history.</span>
                            </div>
                            <button type="submit" class="nx14-button is-primary is-contextual" data-nx14-run-bottom>
                                {{ $analysis ? 'Recalculate Analysis' : 'Run Analysis' }}
                                <span aria-hidden="true">→</span>
                            </button>
                        </div>
                    </form>
                @else
                    <div class="nx14-readonly-grid">
                        @foreach ($assumptionFields as $field)
                            @php
                                $fieldName = $field['name'];
                                $source = $parameterSources[$fieldName] ?? 'default';
                                $value = $parameters[$fieldName] ?? null;
                                $display = $value === null || $value === '' ? 'Model default if blank' : number_format((float) $value, in_array($fieldName, ['project_lifetime_years', 'replacement_year'], true) ? 0 : 2, '.', ',').' '.$field['unit'];
                            @endphp
                            <div class="nx14-readonly-field">
                                <span>{{ $field['label'] }}</span>
                                <strong title="{{ $display }}">{{ $display }}</strong>
                                <small class="nx14-source-badge {{ $sourceClass($source) }}">{{ $sourceBadge($source) }}</small>
                            </div>
                        @endforeach
                        <div class="nx14-readonly-note">
                            <span>Analysis note</span>
                            <p>{{ ($parameters['analysis_note'] ?? null) ?: 'Not recorded' }}</p>
                        </div>
                    </div>
                @endif
            </section>



        </div>

        <aside class="nx14-rail" aria-label="Financial analysis supporting information">
            <section class="nx14-rail-card is-basis" data-nxl-surface="raised">
                <header>
                    <span class="nx14-section-kicker">Financial basis</span>
                    <h3>Source Chain</h3>
                    <span class="nx14-state-chip is-{{ $basisState['tone'] }}">{{ $basisState['label'] }}</span>
                </header>
                <p class="nx14-rail-lead">{{ $basisState['detail'] }}</p>
                <dl class="nx14-source-list">
                    <div>
                        <dt>Technical source</dt>
                        <dd><strong>{{ $technicalReference }}</strong><span>{{ $technicalStatus }} · {{ $dateTime($calculation?->calculated_at) }}</span></dd>
                    </div>
                    <div>
                        <dt>Annual energy</dt>
                        <dd><strong>{{ $basisState['annual_energy_kwh'] > 0 ? number_format($basisState['annual_energy_kwh'], 0).' kWh/year' : 'Not available' }}</strong><span>Derived from the current technical result.</span></dd>
                    </div>
                    <div>
                        <dt>Priced BOQ source</dt>
                        <dd><strong>{{ $boqReference }}</strong><span>{{ $bom ? str((string) $bom->pricing_status)->replace('_', ' ')->title() : 'Not available' }} · {{ $dateTime($bom?->priced_at) }}</span></dd>
                    </div>
                    <div>
                        <dt>Priced BOQ reference</dt>
                        <dd><strong>{{ $bom && (float) $bom->grand_price_idr > 0 ? $moneyCompact($bom->grand_price_idr) : 'Not available' }}</strong><span>Commercial reference only; analysis CAPEX may differ.</span></dd>
                    </div>
                    <div>
                        <dt>Analysis CAPEX basis</dt>
                        <dd><strong>{{ $capexBasis['label'] }}</strong><span>{{ $capexBasis['analysis_capex_idr'] !== null ? $moneyCompact($capexBasis['analysis_capex_idr']) : ($capexBasis['priced_boq_reference_idr'] !== null ? $moneyCompact($capexBasis['priced_boq_reference_idr']) : 'Not available') }}</span></dd>
                    </div>
                </dl>
                <div class="nx14-rail-actions">
                    @if ($canOpenTechnical)
                        <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}">Open Technical Calculation <span>→</span></a>
                    @endif
                    @if ($canOpenBom)
                        <a href="{{ route('companies.boms.show', [$company, $project]) }}">Open BOM &amp; BOQ <span>→</span></a>
                    @endif
                </div>
            </section>

            <section class="nx14-rail-card is-freshness" data-nxl-surface="raised">
                <header>
                    <span class="nx14-section-kicker">Source freshness</span>
                    <h3>{{ $freshnessLabel }}</h3>
                    <span class="nx14-state-chip is-{{ $freshnessState }}">{{ $recordState['label'] }}</span>
                </header>
                <p class="nx14-rail-lead">{{ $freshnessDetail }}</p>
                @if ($analysis)
                    <dl class="nx14-mini-list">
                        <div><dt>Calculated</dt><dd>{{ $dateTime($analysis->calculated_at) }}</dd></div>
                        <div><dt>Calculated by</dt><dd>{{ $analysis->calculator?->name ?: 'Not recorded' }}</dd></div>
                        <div><dt>Currency</dt><dd>{{ strtoupper((string) $analysis->currency) }}</dd></div>
                    </dl>
                @endif
                @if ($freshnessState !== 'current' && $analysis)
                    <div class="nx14-attention-note">
                        <strong>Stored result retained for reference</strong>
                        <span>It must not be treated as current until the source chain is corrected or the model is recalculated from current sources.</span>
                    </div>
                @endif
            </section>

            <section class="nx14-rail-card is-warnings" data-nxl-surface="raised">
                <header>
                    <span class="nx14-section-kicker">Calculation warnings</span>
                    <h3>{{ count($warnings) > 0 ? count($warnings).' recorded' : 'No stored warnings' }}</h3>
                </header>
                @if (count($warnings) > 0)
                    <ul class="nx14-warning-list">
                        @foreach ($warnings as $warning)
                            <li><span aria-hidden="true">!</span><p>{{ $warning }}</p></li>
                        @endforeach
                    </ul>
                @elseif ($analysis)
                    <p class="nx14-rail-lead">The stored financial run did not record model warnings. This is not a finance approval or engineering verification.</p>
                @else
                    <p class="nx14-rail-lead">Warnings are created by the deterministic financial model when an analysis is run.</p>
                @endif
            </section>

            <section class="nx14-rail-card is-boundaries" data-nxl-surface="inset">
                <header>
                    <span class="nx14-section-kicker">Analysis boundaries</span>
                    <h3>Current Model Scope</h3>
                </header>
                <ul class="nx14-boundary-list">
                    <li><span>01</span><p>Uses annual technical energy plus an annual self-consumption assumption; it does not model time-step import/export dispatch.</p></li>
                    <li><span>02</span><p>Annual O&amp;M escalation currently follows the same escalation rate used for the electricity tariff.</p></li>
                    <li><span>03</span><p>Stores one current financial analysis per project. Recalculation updates that record rather than creating immutable run history.</p></li>
                    <li><span>04</span><p>No scenario comparison, financing schedule, tax model, P50/P90 finance, sensitivity, or bankability certification is implemented here.</p></li>
                </ul>
            </section>

            <section class="nx14-rail-card is-provenance" data-nxl-surface="inset">
                <header>
                    <span class="nx14-section-kicker">Calculation provenance</span>
                    <h3>Data Basis</h3>
                </header>
                <dl class="nx14-mini-list">
                    <div><dt>Project</dt><dd class="nx14-mono">{{ $project->project_number ?: 'Not recorded' }}</dd></div>
                    <div><dt>System type</dt><dd>{{ $systemType }}</dd></div>
                    <div><dt>Saved analysis</dt><dd class="nx14-mono">{{ $analysisNumber }}</dd></div>
                    <div><dt>Record state</dt><dd>{{ $recordState['label'] }}</dd></div>
                    <div><dt>Financial basis</dt><dd>{{ $basisState['label'] }}</dd></div>
                    <div><dt>Source state</dt><dd>{{ $freshnessLabel }}</dd></div>
                </dl>
            </section>


        </aside>
    </div>

    @if ($analysis && count($cashflows) > 0)
        <section class="nx14-table-card" data-nxl-surface="inset">
            <header class="nx14-section-head">
                <div>
                    <span class="nx14-section-kicker">Stored cashflow detail</span>
                    <h3>Cashflow Schedule</h3>
                    <p>Annual energy, modeled benefit, O&amp;M, replacement assumptions, annual net cashflow, and stored cumulative cashflow.</p>
                </div>
                <span class="nx14-table-count">{{ count($cashflows) }} years</span>
            </header>
            <div class="nx14-table-wrap">
                <table class="nx14-cashflow-table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Energy</th>
                            <th>Modeled Benefit</th>
                            <th>O&amp;M</th>
                            <th>Replacement</th>
                            <th>Annual Net</th>
                            <th>Cumulative</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cashflows as $row)
                            <tr data-nx14-cashflow-row>
                                <td class="nx14-mono">{{ number_format((int) ($row['year'] ?? 0)) }}</td>
                                <td>{{ number_format((float) ($row['energy_kwh'] ?? 0), 0) }} kWh</td>
                                <td>{{ $moneyCompact($row['gross_saving_idr'] ?? 0) }}</td>
                                <td>{{ $moneyCompact($row['om_cost_idr'] ?? 0) }}</td>
                                <td>{{ (float) ($row['replacement_cost_idr'] ?? 0) > 0 ? $moneyCompact($row['replacement_cost_idr']) : '—' }}</td>
                                <td class="{{ (float) ($row['net_cashflow_idr'] ?? 0) < 0 ? 'is-negative' : '' }}">{{ $moneyCompact($row['net_cashflow_idr'] ?? 0) }}</td>
                                <td class="{{ (float) ($row['cumulative_cashflow_idr'] ?? 0) < 0 ? 'is-negative' : '' }}">{{ $moneyCompact($row['cumulative_cashflow_idr'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="nx14-interpret-card" data-nxl-surface="inset">
            <header class="nx14-section-head is-compact">
                <div>
                    <span class="nx14-section-kicker">Output interpretation</span>
                    <h3>What These Outputs Mean</h3>
                </div>
            </header>
            <div class="nx14-interpret-grid">
                <article><strong>NPV</strong><p>Discounted value of stored modeled cashflows at the selected discount rate. Its sign is descriptive, not an approval verdict.</p></article>
                <article><strong>IRR</strong><p>Internal rate of return when a valid solution exists. It is not treated as an investment hurdle here.</p></article>
                <article><strong>Simple Payback</strong><p>Undiscounted time for cumulative modeled net cashflow to recover analysis CAPEX.</p></article>
                <article><strong>LCOE</strong><p>Discounted modeled lifecycle cost per discounted energy unit. It is not a tariff pass/fail test.</p></article>
            </div>
        </section>

</main>
@endsection
