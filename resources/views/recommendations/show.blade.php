@extends('layouts.app')

@section('title', 'System Recommendation')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'System Recommendation')

@php
    $hardConstraints = $recommendation->hard_constraints ?? $sourceAssessment['hard_constraints'];
    $warnings = $recommendation->warnings ?? $sourceAssessment['warnings'];
    $reasons = $recommendation->reasons ?? [];
    $sources = $sourceAssessment['source_snapshot'];
    $hasResult = $recommendation->hasGeneratedResult();
    $hasBlockers = $hardConstraints !== [];
    $resultTone = !$hasResult
        ? 'blocked'
        : ($recommendation->result_status === 'overridden'
            ? 'override'
            : ($recommendation->result_status === 'review_required' ? 'review' : 'ready'));

    $loadReady = $sources['load']['available'] && (float) $sources['load']['daily_energy_kwh'] > 0;
    $solarComplete = (int) ($sources['solar']['complete_months'] ?? 0) === 12 && (float) ($sources['solar']['average_psh'] ?? 0) > 0;
    $solarVerified = $solarComplete && ($sources['solar']['quality_status'] ?? null) === 'verified';
    $roofComplete = (float) ($sources['roof']['usable_area_m2'] ?? 0) > 0;
    $roofVerified = $roofComplete && ($sources['roof']['quality_status'] ?? null) === 'verified';
    $readySourceCount = collect([$loadReady, $solarComplete, $roofComplete])->filter()->count();
    $verifiedSourceCount = collect([$loadReady, $solarVerified, $roofVerified])->filter()->count();
    $readinessPercent = round(($readySourceCount / 3) * 100);

    $onGridScore = $recommendation->on_grid_score !== null ? (float) $recommendation->on_grid_score : null;
    $hybridScore = $recommendation->hybrid_score !== null ? (float) $recommendation->hybrid_score : null;
    $scoreGap = $hasResult ? abs($onGridScore - $hybridScore) : null;
    $scoreLeader = !$hasResult ? null : ($hybridScore > $onGridScore ? 'Hybrid' : 'On-grid');
    $decisiveness = $recommendation->decisiveness_index !== null ? (float) $recommendation->decisiveness_index : null;
    $decisivenessBand = $decisiveness === null
        ? 'Awaiting result'
        : ($decisiveness < 10 ? 'Close decision' : ($decisiveness < 35 ? 'Moderate separation' : 'Clear separation'));
    $runAction = $hasResult ? 'rerun' : 'run';
    $runLabel = $hasBlockers ? 'Refresh Readiness' : ($hasResult ? 'Re-run Recommendation' : 'Run Recommendation');
    $displayResult = $hasResult ? $recommendation->recommended_system_label : 'Not calculated';
    $engineResult = $hasResult ? $recommendation->engine_recommended_system_label : 'Awaiting engine run';
    $decisionStatus = $hasResult ? $recommendation->result_status_label : 'Source gate incomplete';
    $projectTitleParts = preg_split('/\b(Hybrid|On[- ]?grid)\b/i', $project->name, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$project->name];
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
    <div class="nxrec-workspace nxrec-workspace-v2" data-nxrec-workspace>
        <section class="nxrec-workspace-hero nxrec-workspace-hero-compact" data-reveal>
            <div class="nxrec-hero-copy">
                <div class="nxrec-hero-overline">
                    <a href="{{ route('companies.recommendations.index', $company) }}" class="nxrec-back-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M19 12H5M10 7l-5 5 5 5"/></svg>
                        Recommendation registry
                    </a>
                    <span class="nxrec-kicker"><span></span> Explainable system decision</span>
                </div>

                <h2>@foreach ($projectTitleParts as $titlePart)@if (preg_match('/^(Hybrid|On[- ]?grid)$/i', $titlePart))<em>{{ $titlePart }}</em>@else{{ $titlePart }}@endif @endforeach</h2>
                <p>Controlled comparison of on-grid and hybrid alternatives using the latest project evidence, constraints, and operational targets.</p>

                <div class="nxrec-hero-meta">
                    <span>{{ $project->project_number }}</span>
                    <span>{{ $project->customer->name }}</span>
                    <span>{{ $project->city ?: 'Location pending' }}</span>
                    <span>Engine {{ $recommendation->engine_version }}</span>
                </div>
            </div>

            <div class="nxrec-hero-decision is-{{ $resultTone }}" data-premium-card>
                <div class="nxrec-decision-topline">
                    <span class="nxrec-decision-icon">
                        @if ($hasResult && $recommendation->recommended_system === 'hybrid')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 20h10M9 20v-5h6v5M5 15 8 4h8l3 11M8 9h8"/><path d="M11 12h2"/></svg>
                        @elseif ($hasResult)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3v18M7 7h10M5 12h14M8 17h8"/><path d="m4 21 8-18 8 18"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19h16"/><path d="m6 15 4-4 3 3 5-7"/><path d="M16 7h2v2"/></svg>
                        @endif
                    </span>
                    <span class="nxrec-decision-status"><span></span>{{ $decisionStatus }}</span>
                </div>

                <div class="nxrec-hero-result-row">
                    <span>
                        <small>Current planning basis</small>
                        <strong>{{ $displayResult }}</strong>
                    </span>
                    @if ($hasResult)
                        <b>{{ $scoreLeader }} +{{ number_format($scoreGap, 1) }}</b>
                    @else
                        <b>Gate {{ $readySourceCount }}/3</b>
                    @endif
                </div>

                <p>
                    @if ($recommendation->result_status === 'overridden')
                        Engineer-selected result. The original engine decision remains preserved.
                    @elseif ($hasResult)
                        Preliminary system type. Detailed sizing and compatibility checks still follow.
                    @else
                        Complete the source gate, then run the recommendation engine.
                    @endif
                </p>

                <div class="nxrec-decision-foot">
                    <span><small>Decision quality</small><b>{{ $decisivenessBand }}</b></span>
                    <span><small>Verified sources</small><b>{{ $verifiedSourceCount }}/3</b></span>
                    <span><small>Model</small><b>{{ $recommendation->engine_version }}</b></span>
                </div>
            </div>
        </section>

        @if ($isStale)
            <div class="nxrec-stale-alert" role="alert" data-reveal>
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <div>
                    <strong>Source snapshot changed after the last engine run.</strong>
                    <p>Re-run the recommendation before using this decision as an engineering basis.</p>
                </div>
                <a href="#recommendation-inputs">Review inputs</a>
            </div>
        @endif

        <nav class="nxrec-section-nav nxrec-section-nav-compact" aria-label="Recommendation workspace sections" data-nxrec-section-nav data-reveal>
            <a href="#decision-overview" class="is-active"><span>01</span> Overview</a>
            <a href="#recommendation-inputs"><span>02</span> Inputs</a>
            @if ($hasResult)
                <a href="#score-trace"><span>03</span> Score trace</a>
            @endif
            <a href="#decision-control"><span>{{ $hasResult ? '04' : '03' }}</span> Decision</a>
            @if ($recommendation->histories->isNotEmpty())
                <a href="#decision-audit"><span>{{ $hasResult ? '05' : '04' }}</span> Audit</a>
            @endif
        </nav>

        <section id="decision-overview" class="nxrec-summary-grid nxrec-summary-grid-v2" aria-label="Recommendation summary" data-reveal>
            <article class="nxrec-summary-card nxrec-summary-card-primary {{ $hasResult ? '' : 'is-empty' }}" data-premium-card>
                <span class="nxrec-summary-label">Current result</span>
                <strong>{{ $displayResult }}</strong>
                <small>{{ $decisionStatus }}</small>
                @if ($hasResult)
                    <span class="nxrec-summary-line"><span style="width: 100%"></span></span>
                @else
                    <span class="nxrec-summary-placeholder">Awaiting source completion and engine run</span>
                @endif
            </article>

            <article class="nxrec-summary-card {{ $onGridScore === null ? 'is-empty' : '' }}" data-premium-card>
                <span class="nxrec-summary-label">On-grid score</span>
                <strong @if($onGridScore !== null) data-counter="{{ $onGridScore }}" @endif>{{ $onGridScore !== null ? number_format($onGridScore, 1) : '—' }}</strong>
                <small>{{ $onGridScore !== null ? 'Weighted suitability / 100' : 'Not calculated' }}</small>
                @if ($onGridScore !== null)
                    <span class="nxrec-summary-line is-blue"><span style="width: {{ $onGridScore }}%"></span></span>
                @endif
            </article>

            <article class="nxrec-summary-card {{ $hybridScore === null ? 'is-empty' : '' }}" data-premium-card>
                <span class="nxrec-summary-label">Hybrid score</span>
                <strong @if($hybridScore !== null) data-counter="{{ $hybridScore }}" @endif>{{ $hybridScore !== null ? number_format($hybridScore, 1) : '—' }}</strong>
                <small>{{ $hybridScore !== null ? 'Weighted suitability / 100' : 'Not calculated' }}</small>
                @if ($hybridScore !== null)
                    <span class="nxrec-summary-line is-violet"><span style="width: {{ $hybridScore }}%"></span></span>
                @endif
            </article>

            <article class="nxrec-summary-card {{ $decisiveness === null ? 'is-empty' : '' }}" data-premium-card>
                <span class="nxrec-summary-label">Decisiveness</span>
                <strong>{{ $decisiveness !== null ? number_format($decisiveness, 1).'%' : '—' }}</strong>
                <small>{{ $decisivenessBand }}</small>
                <button type="button" class="nxrec-info-trigger" data-nxrec-tooltip="Relative separation between both alternative scores. This is not a statistical confidence probability." aria-label="Explain decisiveness index">?</button>
            </article>
        </section>

        <section class="nxrec-readiness nxrec-readiness-v2" data-reveal data-premium-card>
            <header class="nxrec-section-heading">
                <div>
                    <span class="nxrec-section-kicker">Controlled source gate</span>
                    <h3>Source Data Readiness</h3>
                    <p>Completion unlocks scoring. Verification strengthens the engineering basis.</p>
                </div>
                <div class="nxrec-readiness-meter">
                    <span><b>{{ $readySourceCount }}/3</b> complete</span>
                    <span><b>{{ $verifiedSourceCount }}/3</b> verified</span>
                    <i><b style="width: {{ $readinessPercent }}%"></b></i>
                </div>
            </header>

            <div class="nxrec-source-grid">
                <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}" class="nxrec-source-card {{ $loadReady ? 'is-ready' : 'is-blocked' }}" data-nxrec-source>
                    <span class="nxrec-source-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8M10 19V4M16 19v-7M22 19H2"/></svg>
                    </span>
                    <span class="nxrec-source-copy">
                        <small>Load intelligence</small>
                        <strong>{{ $loadReady ? number_format((float) $sources['load']['daily_energy_kwh'], 2).' kWh/day' : 'Awaiting load profile' }}</strong>
                        <span>
                            @if ($loadReady)
                                {{ number_format((float) $sources['load']['day_share_percent'], 1) }}% daytime · {{ number_format((float) $sources['load']['critical_daily_energy_kwh'], 2) }} kWh critical
                            @else
                                Complete equipment or electricity-bill data
                            @endif
                        </span>
                    </span>
                    <span class="nxrec-source-state"><i></i>{{ $loadReady ? 'Complete' : 'Required' }}</span>
                    <svg class="nxrec-source-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </a>

                <a href="{{ route('companies.location-solar.show', [$company, $project]) }}" class="nxrec-source-card {{ !$solarComplete ? 'is-blocked' : ($solarVerified ? 'is-ready' : 'is-review') }}" data-nxrec-source>
                    <span class="nxrec-source-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </span>
                    <span class="nxrec-source-copy">
                        <small>Solar resource</small>
                        <strong>{{ $solarComplete ? number_format((float) $sources['solar']['average_psh'], 2).' PSH' : 'Awaiting 12-month profile' }}</strong>
                        <span>
                            @if ($solarComplete)
                                {{ $sources['solar']['complete_months'] }}/12 months · {{ ucfirst((string) ($sources['solar']['quality_status'] ?? 'not started')) }}
                            @else
                                {{ (int) ($sources['solar']['complete_months'] ?? 0) }}/12 months complete
                            @endif
                        </span>
                    </span>
                    <span class="nxrec-source-state"><i></i>{{ !$solarComplete ? 'Required' : ($solarVerified ? 'Verified' : 'Review') }}</span>
                    <svg class="nxrec-source-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </a>

                <a href="{{ route('companies.roof-site.show', [$company, $project]) }}" class="nxrec-source-card {{ !$roofComplete ? 'is-blocked' : ($roofVerified ? 'is-ready' : 'is-review') }}" data-nxrec-source>
                    <span class="nxrec-source-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 12 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/></svg>
                    </span>
                    <span class="nxrec-source-copy">
                        <small>Roof & site survey</small>
                        <strong>{{ $roofComplete ? number_format((float) $sources['roof']['usable_area_m2'], 1).' m² usable' : 'Awaiting usable roof area' }}</strong>
                        <span>
                            @if ($roofComplete)
                                {{ number_format((float) $sources['roof']['weighted_shading_percent'], 1) }}% shading · {{ ucfirst((string) ($sources['roof']['quality_status'] ?? 'not started')) }}
                            @else
                                Complete at least one usable roof surface
                            @endif
                        </span>
                    </span>
                    <span class="nxrec-source-state"><i></i>{{ !$roofComplete ? 'Required' : ($roofVerified ? 'Verified' : 'Review') }}</span>
                    <svg class="nxrec-source-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </a>
            </div>
        </section>

        @if ($hardConstraints !== [])
            <section class="nxrec-blocker-panel nxrec-blocker-panel-v2" role="alert" data-reveal>
                <header>
                    <span class="nxrec-blocker-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                    </span>
                    <div>
                        <span class="nxrec-section-kicker">Hard constraint gate</span>
                        <h3>{{ count($hardConstraints) }} blocking {{ \Illuminate\Support\Str::plural('condition', count($hardConstraints)) }}</h3>
                        <p>The engine may refresh readiness, but it will not select a system while these conditions remain.</p>
                    </div>
                </header>
                <ol>
                    @foreach ($hardConstraints as $constraint)
                        <li><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><p>{{ $constraint }}</p></li>
                    @endforeach
                </ol>
            </section>
        @endif

        <div class="nxrec-workspace-flow">
            <section id="recommendation-inputs" class="nxrec-panel nxrec-input-panel nxrec-input-panel-v2" data-reveal data-premium-card>
                <header class="nxrec-section-heading nxrec-input-heading">
                    <div>
                        <span class="nxrec-section-kicker">Operational decision targets</span>
                        <h3>Recommendation Inputs</h3>
                        <p>Project intent is combined with measured load, solar, and roof evidence. Every value remains visible in the trace.</p>
                    </div>
                    <div class="nxrec-live-preview" aria-label="Live target summary">
                        <span><small>Backup</small><b data-nxrec-preview-backup>{{ number_format((float) $recommendation->backup_target_hours, 1) }} h</b></span>
                        <span><small>Self-sufficiency</small><b data-nxrec-preview-self>{{ number_format((float) $recommendation->target_self_sufficiency_percent, 0) }}%</b></span>
                        <span><small>Objective</small><b data-nxrec-preview-objective>{{ $recommendation->primary_objective_label }}</b></span>
                    </div>
                </header>

                @if ($errors->any())
                    <div class="nxrec-validation-summary" role="alert">
                        <strong>Review the recommendation inputs.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('companies.recommendations.run', [$company, $project]) }}"
                    data-nxrec-action-form
                    data-nxrec-action="{{ $runAction }}"
                    data-nxrec-current-result="{{ $displayResult }}"
                    data-nxrec-engine-result="{{ $engineResult }}"
                    data-nxrec-engine-version="{{ $recommendation->engine_version }}"
                    @if($hasResult) data-nxrec-confirm="rerun" @endif
                >
                    @csrf
                    @method('PUT')

                    <div class="nxrec-input-groups nxrec-input-groups-v2">
                        <fieldset class="nxrec-input-group">
                            <legend><span>01</span><strong>Grid and continuity</strong><small>Operating environment and outage exposure</small></legend>
                            <div class="nxrec-form-grid">
                                <div class="nxrec-field">
                                    <label for="grid_connection_status">Grid connection condition</label>
                                    <div class="nxrec-control nxrec-control-select">
                                        <select id="grid_connection_status" name="grid_connection_status" required @disabled(! $canRun)>
                                            <option value="stable" @selected(old('grid_connection_status', $recommendation->grid_connection_status) === 'stable')>Stable utility grid</option>
                                            <option value="occasional_outages" @selected(old('grid_connection_status', $recommendation->grid_connection_status) === 'occasional_outages')>Occasional outages</option>
                                            <option value="frequent_outages" @selected(old('grid_connection_status', $recommendation->grid_connection_status) === 'frequent_outages')>Frequent outages</option>
                                            <option value="unavailable" @selected(old('grid_connection_status', $recommendation->grid_connection_status) === 'unavailable')>Grid unavailable</option>
                                        </select>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 10 4 4 4-4"/></svg>
                                    </div>
                                </div>

                                <div class="nxrec-field">
                                    <label for="outage_frequency_per_month">Outage frequency</label>
                                    <div class="nxrec-control nxrec-control-suffix">
                                        <input id="outage_frequency_per_month" type="number" min="0" max="100" step="1" name="outage_frequency_per_month" value="{{ old('outage_frequency_per_month', $recommendation->outage_frequency_per_month) }}" required @disabled(! $canRun)>
                                        <span>/ month</span>
                                    </div>
                                </div>

                                <div class="nxrec-field">
                                    <label for="average_outage_duration_hours">Average outage duration</label>
                                    <div class="nxrec-control nxrec-control-suffix">
                                        <input id="average_outage_duration_hours" type="number" min="0" max="72" step="0.25" name="average_outage_duration_hours" value="{{ old('average_outage_duration_hours', $recommendation->average_outage_duration_hours) }}" required @disabled(! $canRun)>
                                        <span>hours</span>
                                    </div>
                                </div>

                                <div class="nxrec-field">
                                    <label for="backup_target_hours">Required backup duration</label>
                                    <div class="nxrec-control nxrec-control-suffix">
                                        <input id="backup_target_hours" type="number" min="0" max="72" step="0.25" name="backup_target_hours" value="{{ old('backup_target_hours', $recommendation->backup_target_hours) }}" required data-nxrec-backup @disabled(! $canRun)>
                                        <span>hours</span>
                                    </div>
                                    <small>Critical appliances must be marked in Load Analysis.</small>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="nxrec-input-group">
                            <legend><span>02</span><strong>Engineering objective</strong><small>System behavior and energy ambition</small></legend>
                            <div class="nxrec-form-grid">
                                <div class="nxrec-field">
                                    <label for="primary_objective">Primary objective</label>
                                    <div class="nxrec-control nxrec-control-select">
                                        <select id="primary_objective" name="primary_objective" required data-nxrec-objective @disabled(! $canRun)>
                                            <option value="savings" @selected(old('primary_objective', $recommendation->primary_objective) === 'savings')>Maximum savings</option>
                                            <option value="backup" @selected(old('primary_objective', $recommendation->primary_objective) === 'backup')>Backup reliability</option>
                                            <option value="balanced" @selected(old('primary_objective', $recommendation->primary_objective) === 'balanced')>Balanced performance</option>
                                            <option value="self_sufficiency" @selected(old('primary_objective', $recommendation->primary_objective) === 'self_sufficiency')>Energy self-sufficiency</option>
                                            <option value="maximize_roof" @selected(old('primary_objective', $recommendation->primary_objective) === 'maximize_roof')>Maximum roof utilization</option>
                                        </select>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 10 4 4 4-4"/></svg>
                                    </div>
                                </div>

                                <div class="nxrec-field">
                                    <label for="target_self_sufficiency_percent">Self-sufficiency target</label>
                                    <div class="nxrec-control nxrec-control-suffix">
                                        <input id="target_self_sufficiency_percent" type="number" min="0" max="100" step="1" name="target_self_sufficiency_percent" value="{{ old('target_self_sufficiency_percent', $recommendation->target_self_sufficiency_percent) }}" required data-nxrec-self @disabled(! $canRun)>
                                        <span>%</span>
                                    </div>
                                </div>

                                <div class="nxrec-field">
                                    <label for="budget_flexibility">Investment budget flexibility</label>
                                    <div class="nxrec-control nxrec-control-select">
                                        <select id="budget_flexibility" name="budget_flexibility" required @disabled(! $canRun)>
                                            <option value="strict" @selected(old('budget_flexibility', $recommendation->budget_flexibility) === 'strict')>Strict</option>
                                            <option value="balanced" @selected(old('budget_flexibility', $recommendation->budget_flexibility) === 'balanced')>Balanced</option>
                                            <option value="flexible" @selected(old('budget_flexibility', $recommendation->budget_flexibility) === 'flexible')>Flexible</option>
                                        </select>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 10 4 4 4-4"/></svg>
                                    </div>
                                </div>

                                <div class="nxrec-field">
                                    <label for="export_policy">Grid export policy</label>
                                    <div class="nxrec-control nxrec-control-select">
                                        <select id="export_policy" name="export_policy" required @disabled(! $canRun)>
                                            <option value="allowed" @selected(old('export_policy', $recommendation->export_policy) === 'allowed')>Export allowed</option>
                                            <option value="limited" @selected(old('export_policy', $recommendation->export_policy) === 'limited')>Export limited</option>
                                            <option value="zero_export" @selected(old('export_policy', $recommendation->export_policy) === 'zero_export')>Zero export required</option>
                                            <option value="unknown" @selected(old('export_policy', $recommendation->export_policy) === 'unknown')>Unknown</option>
                                        </select>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 10 4 4 4-4"/></svg>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="nxrec-input-group nxrec-input-group-notes">
                            <legend><span>03</span><strong>Engineering context</strong><small>Document project-specific assumptions</small></legend>
                            <div class="nxrec-field">
                                <label for="notes">Engineering notes</label>
                                <div class="nxrec-control nxrec-control-textarea">
                                    <textarea id="notes" name="notes" maxlength="3000" placeholder="Customer priorities, operating limits, known utility constraints, or the basis for selected targets" data-nxrec-autosize data-nxrec-countable @disabled(! $canRun)>{{ old('notes', $recommendation->notes) }}</textarea>
                                    <span class="nxrec-character-count"><b data-nxrec-count>0</b>/3000</span>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <footer class="nxrec-run-dock nxrec-run-dock-v2">
                        <div>
                            <span class="nxrec-run-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="m6 15 4-4 3 3 5-7"/><path d="M16 7h2v2"/></svg>
                            </span>
                            <span>
                                <strong>{{ $hasBlockers ? 'Preflight gate will run before scoring' : ($hasResult ? 'This run replaces the active engine result' : 'Ready to calculate both alternatives') }}</strong>
                                <small>
                                    @if ($hasBlockers)
                                        Readiness will refresh, then the engine will stop while hard constraints remain.
                                    @elseif ($hasResult)
                                        Scores will be recalculated. Active overrides are removed. Audit history remains preserved.
                                    @else
                                        Hard constraints run first, followed by weighted on-grid and hybrid scoring.
                                    @endif
                                </small>
                            </span>
                        </div>

                        @if ($canRun)
                            <button type="submit" class="nxrec-button nxrec-button-primary nxrec-run-button" data-nxrec-submit>
                                <span data-nxrec-submit-label>{{ $runLabel }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                            </button>
                        @else
                            <span class="nxrec-permission-lock">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                Run access unavailable
                            </span>
                        @endif
                    </footer>
                </form>
            </section>

            @if ($hasResult)
                <section id="score-trace" class="nxrec-panel nxrec-score-panel nxrec-score-panel-v2" data-reveal data-premium-card>
                    <header class="nxrec-section-heading">
                        <div>
                            <span class="nxrec-section-kicker">Transparent weighted model</span>
                            <h3>Alternative Score Trace</h3>
                            <p>Eight weighted criteria. Open any row to inspect the engineering rationale for both alternatives.</p>
                        </div>
                        <span class="nxrec-score-lead"><small>Engine leader</small><strong>{{ $scoreLeader }}</strong><b>+{{ number_format($scoreGap, 1) }} pts</b></span>
                    </header>

                    <div class="nxrec-alternative-head nxrec-alternative-head-v2">
                        <article class="{{ $recommendation->engine_recommended_system === 'on_grid' ? 'is-selected' : '' }}">
                            <span><small>Alternative A</small><strong>On-grid</strong></span>
                            <b>{{ number_format($onGridScore, 2) }}</b>
                            <i><span style="width: {{ min(100, $onGridScore) }}%"></span></i>
                            @if ($recommendation->engine_recommended_system === 'on_grid')<em>Engine selected</em>@endif
                        </article>
                        <article class="is-hybrid {{ $recommendation->engine_recommended_system === 'hybrid' ? 'is-selected' : '' }}">
                            <span><small>Alternative B</small><strong>Hybrid</strong></span>
                            <b>{{ number_format($hybridScore, 2) }}</b>
                            <i><span style="width: {{ min(100, $hybridScore) }}%"></span></i>
                            @if ($recommendation->engine_recommended_system === 'hybrid')<em>Engine selected</em>@endif
                        </article>
                    </div>

                    <div class="nxrec-score-matrix" role="table" aria-label="Weighted score comparison">
                        <div class="nxrec-score-matrix-head" role="row">
                            <span role="columnheader">Criterion</span>
                            <span role="columnheader">Weight</span>
                            <span role="columnheader">On-grid</span>
                            <span role="columnheader">Hybrid</span>
                            <span role="columnheader">Lead</span>
                            <span role="columnheader" aria-label="Open rationale"></span>
                        </div>

                        @foreach ($scoreMatrix as $criterionRows)
                            @php
                                $onGridRow = $criterionRows->firstWhere('alternative', 'on_grid');
                                $hybridRow = $criterionRows->firstWhere('alternative', 'hybrid');
                                $criterionName = $onGridRow?->criterion_name ?? $hybridRow?->criterion_name;
                                $weight = (float) ($onGridRow?->weight ?? $hybridRow?->weight ?? 0);
                                $ogRating = (float) ($onGridRow?->rating ?? 0);
                                $hyRating = (float) ($hybridRow?->rating ?? 0);
                                $criterionLeader = abs($ogRating - $hyRating) < 0.0001 ? 'Tie' : ($hyRating > $ogRating ? 'Hybrid' : 'On-grid');
                                $criterionGap = abs($ogRating - $hyRating);
                            @endphp
                            <details class="nxrec-score-matrix-row" role="rowgroup">
                                <summary role="row">
                                    <span class="nxrec-score-name" role="cell">
                                        <i>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</i>
                                        <strong>{{ $criterionName }}</strong>
                                    </span>
                                    <span class="nxrec-score-weight" role="cell">{{ number_format($weight * 100, 0) }}%</span>
                                    <span class="nxrec-score-cell is-on-grid {{ $criterionLeader === 'On-grid' ? 'is-leading' : '' }}" role="cell">
                                        <b>{{ number_format($ogRating, 1) }}</b>
                                        <i><span style="width: {{ min(100, $ogRating) }}%"></span></i>
                                        <small>W {{ number_format((float) ($onGridRow?->weighted_score ?? 0), 2) }}</small>
                                    </span>
                                    <span class="nxrec-score-cell is-hybrid {{ $criterionLeader === 'Hybrid' ? 'is-leading' : '' }}" role="cell">
                                        <b>{{ number_format($hyRating, 1) }}</b>
                                        <i><span style="width: {{ min(100, $hyRating) }}%"></span></i>
                                        <small>W {{ number_format((float) ($hybridRow?->weighted_score ?? 0), 2) }}</small>
                                    </span>
                                    <span class="nxrec-score-winner is-{{ strtolower(str_replace('-', '', $criterionLeader)) }}" role="cell">
                                        <strong>{{ $criterionLeader }}</strong>
                                        <small>{{ $criterionLeader === 'Tie' ? 'Equal rating' : '+'.number_format($criterionGap, 1) }}</small>
                                    </span>
                                    <span class="nxrec-score-expand" role="cell">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 10 4 4 4-4"/></svg>
                                    </span>
                                </summary>
                                <div class="nxrec-score-rationale-v2">
                                    <p><strong>On-grid rationale</strong>{{ $onGridRow?->rationale }}</p>
                                    <p><strong>Hybrid rationale</strong>{{ $hybridRow?->rationale }}</p>
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif

            <section id="decision-control" class="nxrec-panel nxrec-decision-control-panel" data-reveal data-premium-card>
                <header class="nxrec-section-heading nxrec-section-heading-decision">
                    <div>
                        <span class="nxrec-section-kicker">Governed engineering decision</span>
                        <h3>Decision Control</h3>
                        <p>Separate the engine result, engineering interpretation, warnings, and final authority without losing traceability.</p>
                        <div class="nxrec-section-note">
                            <span class="nxrec-status-pill"><i></i>Current state: {{ $decisionStatus }}</span>
                        </div>
                    </div>
                </header>

                <div class="nxrec-decision-control-grid {{ $warnings !== [] ? 'has-warnings' : 'without-warnings' }}">
                    <article class="nxrec-decision-card nxrec-decision-card-v2 is-{{ $resultTone }}">
                        <div class="nxrec-decision-card-heading">
                            <span class="nxrec-decision-card-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19h16"/><path d="m6 15 4-4 3 3 5-7"/><path d="M16 7h2v2"/></svg>
                            </span>
                            <span><small>Final selected system</small><strong>{{ $displayResult }}</strong></span>
                        </div>
                        <p>
                            @if ($recommendation->result_status === 'overridden')
                                Engine result: {{ $engineResult }}. The final selection was changed under engineer authority.
                            @elseif ($hasResult)
                                The highest weighted alternative is the current planning basis.
                            @else
                                No final system exists until the source gate and engine run are complete.
                            @endif
                        </p>
                        <dl>
                            <div><dt>Engine result</dt><dd>{{ $engineResult }}</dd></div>
                            <div><dt>Generated by</dt><dd>{{ $recommendation->generator?->name ?? 'Not generated' }}</dd></div>
                            <div><dt>Generated at</dt><dd>{{ $recommendation->generated_at?->format('d M Y · H:i') ?? '—' }}</dd></div>
                            <div><dt>Model version</dt><dd>{{ $recommendation->engine_version }}</dd></div>
                        </dl>
                    </article>

                    @if ($reasons !== [])
                        <article class="nxrec-side-card nxrec-side-card-v2 nxrec-reasons-card">
                            <header class="nxrec-side-card-header">
                                <span class="nxrec-side-card-icon is-reason">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 18h6M10 22h4"/><path d="M8.5 14.5A6 6 0 1 1 15.5 14.5c-.9.6-1.5 1.4-1.5 2.5h-4c0-1.1-.6-1.9-1.5-2.5Z"/></svg>
                                </span>
                                <div><small>Explainability</small><h3>Why this result</h3></div>
                                <span class="nxrec-side-count">{{ count($reasons) }}</span>
                            </header>
                            <ol class="nxrec-reason-list nxrec-reason-list-v2">
                                @foreach ($reasons as $reason)
                                    <li><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><p>{{ $reason }}</p></li>
                                @endforeach
                            </ol>
                        </article>
                    @endif

                    @if ($warnings !== [])
                        <article class="nxrec-side-card nxrec-side-card-v2 nxrec-warning-card">
                            <header class="nxrec-side-card-header">
                                <span class="nxrec-side-card-icon is-warning">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                                </span>
                                <div><small>Engineering attention</small><h3>Open warnings</h3></div>
                                <span class="nxrec-side-count">{{ count($warnings) }}</span>
                            </header>
                            <ul class="nxrec-warning-list nxrec-warning-list-v2">
                                @foreach ($warnings as $warning)
                                    <li><span></span><p>{{ $warning }}</p></li>
                                @endforeach
                            </ul>
                        </article>
                    @endif

                    @if ($canOverride && $hasResult)
                        <article class="nxrec-side-card nxrec-side-card-v2 nxrec-override-card nxrec-override-card-v2">
                            <header class="nxrec-side-card-header">
                                <span class="nxrec-side-card-icon is-override">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4.2-1 10-10a2 2 0 0 0-3-3l-10 10L4 20Z"/><path d="m13.5 7.5 3 3"/></svg>
                                </span>
                                <div><small>Engineer authority</small><h3>Decision Override</h3></div>
                            </header>

                            @if ($recommendation->override_system !== null)
                                <div class="nxrec-override-active">
                                    <span><i></i> Active override</span>
                                    <strong>{{ $recommendation->recommended_system_label }}</strong>
                                    <small>{{ $recommendation->overrider?->name ?? 'Engineer' }} · {{ $recommendation->overridden_at?->format('d M Y · H:i') }}</small>
                                </div>
                            @endif

                            <p class="nxrec-side-intro">Scores remain unchanged. A project-specific technical basis is mandatory and retained in history.</p>

                            <form method="POST" action="{{ route('companies.recommendations.override', [$company, $project]) }}" data-nxrec-action-form data-nxrec-action="override">
                                @csrf
                                @method('PATCH')

                                <div class="nxrec-override-form-grid">
                                    <div class="nxrec-field">
                                        <label for="override_system">Final selected system</label>
                                        <div class="nxrec-control nxrec-control-select">
                                            <select id="override_system" name="override_system" required>
                                                <option value="on_grid" @selected(old('override_system', $recommendation->recommended_system) === 'on_grid')>On-grid</option>
                                                <option value="hybrid" @selected(old('override_system', $recommendation->recommended_system) === 'hybrid')>Hybrid</option>
                                            </select>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 10 4 4 4-4"/></svg>
                                        </div>
                                    </div>

                                    <div class="nxrec-field nxrec-override-reason-field">
                                        <label for="override_reason">Technical justification</label>
                                        <div class="nxrec-control nxrec-control-textarea">
                                            <textarea id="override_reason" name="override_reason" minlength="20" maxlength="3000" required placeholder="Explain the project-specific technical or operational basis. Minimum 20 characters." data-nxrec-autosize data-nxrec-countable>{{ old('override_reason', $recommendation->override_reason) }}</textarea>
                                            <span class="nxrec-character-count"><b data-nxrec-count>0</b>/3000</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="nxrec-override-actions">
                                    <button type="submit" class="nxrec-button nxrec-button-override" data-nxrec-submit>
                                        <span data-nxrec-submit-label>Apply Engineer Override</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4.2-1 10-10a2 2 0 0 0-3-3l-10 10L4 20Z"/></svg>
                                    </button>
                                </div>
                            </form>

                            @if ($recommendation->override_system !== null)
                                <form
                                    method="POST"
                                    action="{{ route('companies.recommendations.override.reset', [$company, $project]) }}"
                                    class="nxrec-restore-form"
                                    data-nxrec-action-form
                                    data-nxrec-action="restore"
                                    data-nxrec-confirm="restore"
                                    data-nxrec-current-result="{{ $displayResult }}"
                                    data-nxrec-engine-result="{{ $engineResult }}"
                                    data-nxrec-engine-version="{{ $recommendation->engine_version }}"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="nxrec-restore-button" data-nxrec-submit>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12a8 8 0 1 0 2.3-5.7L4 8.6"/><path d="M4 4v4.6h4.6"/></svg>
                                        <span data-nxrec-submit-label>Restore Engine Result</span>
                                    </button>
                                </form>
                            @endif
                        </article>
                    @endif
                </div>

                <details class="nxrec-method-note nxrec-method-note-v2">
                    <summary>
                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                            <strong>Method and interpretation note</strong>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 10 4 4 4-4"/></svg>
                    </summary>
                    <div>
                        <p>This module applies hard constraints before weighted rule-based scoring. Current weights are preliminary NEXORIN development values and require engineer review, scenario testing, and sensitivity analysis.</p>
                        <p>The decisiveness index measures relative score separation. It does not represent statistical confidence or final design certainty.</p>
                    </div>
                </details>
            </section>

            @if ($recommendation->histories->isNotEmpty())
                <section id="decision-audit" class="nxrec-panel nxrec-history-panel nxrec-history-panel-v2" data-reveal data-premium-card>
                    <header class="nxrec-section-heading">
                        <div>
                            <span class="nxrec-section-kicker">Immutable decision context</span>
                            <h3>Decision Audit Trail</h3>
                            <p>Every engine run, override, and restoration keeps its actor, transition, score snapshot, and model version.</p>
                        </div>
                        <span class="nxrec-history-count">{{ $recommendation->histories->count() }} events</span>
                    </header>

                    <div class="nxrec-history-register">
                        @foreach ($recommendation->histories as $history)
                            @php
                                $historyTone = str_contains($history->event_type, 'override')
                                    ? 'override'
                                    : (str_contains($history->event_type, 'reset') ? 'restore' : 'engine');
                                $historyLabel = match ($history->event_type) {
                                    'generated' => 'Engine recommendation generated',
                                    'regenerated' => 'Engine recommendation recalculated',
                                    'overridden' => 'Engineer override applied',
                                    'override_reset' => 'Engine result restored',
                                    default => ucfirst(str_replace('_', ' ', $history->event_type)),
                                };
                                $snapshotOnGrid = data_get($history->snapshot, 'on_grid_score');
                                $snapshotHybrid = data_get($history->snapshot, 'hybrid_score');
                                $snapshotDecisiveness = data_get($history->snapshot, 'decisiveness_index');
                                $snapshotVersion = data_get($history->snapshot, 'engine_version', '—');
                                $olderHistory = $recommendation->histories->get($loop->index + 1);
                                $olderOnGrid = data_get($olderHistory?->snapshot, 'on_grid_score');
                                $olderHybrid = data_get($olderHistory?->snapshot, 'hybrid_score');
                                $olderDecisiveness = data_get($olderHistory?->snapshot, 'decisiveness_index');
                                $onGridDelta = $snapshotOnGrid !== null && $olderOnGrid !== null ? (float) $snapshotOnGrid - (float) $olderOnGrid : null;
                                $hybridDelta = $snapshotHybrid !== null && $olderHybrid !== null ? (float) $snapshotHybrid - (float) $olderHybrid : null;
                                $decisivenessDelta = $snapshotDecisiveness !== null && $olderDecisiveness !== null ? (float) $snapshotDecisiveness - (float) $olderDecisiveness : null;
                                $scoreChanged = $onGridDelta !== null && (abs($onGridDelta) >= 0.05 || abs($hybridDelta ?? 0) >= 0.05 || abs($decisivenessDelta ?? 0) >= 0.05);
                                $systemChanged = $history->previous_system !== $history->new_system;
                                $changeState = ! $olderHistory ? 'baseline' : (($scoreChanged || $systemChanged) ? 'changed' : 'stable');
                            @endphp
                            <article class="is-{{ $historyTone }} is-{{ $changeState }}">
                                <span class="nxrec-history-marker"><i></i></span>
                                <div class="nxrec-history-content nxrec-history-content-v2">
                                    <header>
                                        <span>
                                            <small>{{ $history->changed_at?->format('d M Y · H:i') }}</small>
                                            <strong>{{ $historyLabel }}</strong>
                                        </span>
                                        <span class="nxrec-history-badges">
                                            <em class="is-{{ $changeState }}">
                                                @if ($changeState === 'baseline')
                                                    Baseline snapshot
                                                @elseif ($changeState === 'changed')
                                                    Decision changed
                                                @else
                                                    No score change
                                                @endif
                                            </em>
                                            <b>{{ $history->changedBy?->name ?? 'System' }}</b>
                                        </span>
                                    </header>

                                    <p>{{ $history->summary }}</p>

                                    <div class="nxrec-history-facts">
                                        <span><small>Transition</small><b>{{ $history->previous_system ? ucfirst(str_replace('_', '-', $history->previous_system)) : 'No result' }} → {{ $history->new_system ? ucfirst(str_replace('_', '-', $history->new_system)) : 'No result' }}</b></span>
                                        <span><small>On-grid</small><b>{{ $snapshotOnGrid !== null ? number_format((float) $snapshotOnGrid, 1) : '—' }}@if($onGridDelta !== null && abs($onGridDelta) >= 0.05)<i>{{ $onGridDelta > 0 ? '+' : '' }}{{ number_format($onGridDelta, 1) }}</i>@endif</b></span>
                                        <span><small>Hybrid</small><b>{{ $snapshotHybrid !== null ? number_format((float) $snapshotHybrid, 1) : '—' }}@if($hybridDelta !== null && abs($hybridDelta) >= 0.05)<i>{{ $hybridDelta > 0 ? '+' : '' }}{{ number_format($hybridDelta, 1) }}</i>@endif</b></span>
                                        <span><small>Decisiveness</small><b>{{ $snapshotDecisiveness !== null ? number_format((float) $snapshotDecisiveness, 1).'%' : '—' }}@if($decisivenessDelta !== null && abs($decisivenessDelta) >= 0.05)<i>{{ $decisivenessDelta > 0 ? '+' : '' }}{{ number_format($decisivenessDelta, 1) }}</i>@endif</b></span>
                                        <span><small>Engine</small><b>{{ $snapshotVersion }}</b></span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
