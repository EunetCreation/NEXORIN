@extends('layouts.app')

@section('title', 'System Recommendation')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'System Recommendation')

@push('styles')
<style>
    .rec-stack { display: grid; gap: 24px; }
    .rec-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 24px;
        align-items: center;
        padding: 32px;
        border-radius: 28px;
        color: #fff;
        background: linear-gradient(135deg, #031d2c 0%, #0b5363 100%);
        box-shadow: var(--shadow-md);
    }
    .rec-hero h2 { margin: 8px 0 12px; font: 700 clamp(2rem, 5vw, 4rem)/1.02 "Space Grotesk", sans-serif; }
    .rec-hero p { max-width: 780px; margin: 0; color: rgba(255,255,255,.72); font-size: 1.05rem; line-height: 1.65; }
    .rec-hero-kicker { color: var(--teal-400); font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
    .rec-actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: flex-end; }
    .rec-actions .ui-button { white-space: nowrap; }
    .rec-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .rec-card { min-width: 0; padding: 26px; border: 1px solid rgba(203,214,223,.75); border-radius: 24px; background: #fff; box-shadow: var(--shadow-sm); }
    .rec-stat strong { display: block; margin-top: 10px; font: 700 2rem/1 "Space Grotesk", sans-serif; }
    .rec-stat span { color: var(--slate-600); }
    .rec-layout { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(320px, .85fr); gap: 24px; align-items: start; }
    .rec-main, .rec-side { min-width: 0; display: grid; gap: 24px; }
    .rec-card h3 { margin: 0 0 8px; font: 700 1.45rem/1.2 "Space Grotesk", sans-serif; }
    .rec-intro { margin: 0 0 22px; color: var(--slate-600); line-height: 1.6; }
    .rec-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .rec-form-grid .span-2 { grid-column: 1 / -1; }
    .rec-field { min-width: 0; display: grid; gap: 8px; }
    .rec-field label { color: var(--slate-700); font-size: .86rem; font-weight: 700; }
    .rec-field input, .rec-field select, .rec-field textarea {
        width: 100%; min-width: 0; border: 1px solid var(--slate-300); border-radius: 14px; padding: 13px 14px; color: var(--slate-950); background: #fff;
    }
    .rec-field textarea { min-height: 120px; resize: vertical; }
    .rec-help { color: var(--slate-500); font-size: .78rem; line-height: 1.45; }
    .rec-source-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .rec-source { min-width: 0; padding: 18px; border: 1px solid var(--slate-200); border-radius: 18px; background: var(--slate-50); }
    .rec-source strong { display: block; margin: 8px 0 4px; font-size: 1.05rem; }
    .rec-source small { color: var(--slate-600); line-height: 1.45; }
    .rec-badge { display: inline-flex; align-items: center; gap: 7px; padding: 6px 10px; border-radius: 999px; font-size: .76rem; font-weight: 700; }
    .rec-badge.ok { color: #087a70; background: #ddf8f5; }
    .rec-badge.warn { color: #9a5b00; background: #fff4d8; }
    .rec-badge.bad { color: #b42318; background: #feeceb; }
    .rec-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .rec-list li { padding: 12px 14px; border-radius: 14px; color: var(--slate-700); background: var(--slate-50); line-height: 1.5; }
    .rec-list.blockers li { border-left: 4px solid #dc3f35; background: #fff1f0; }
    .rec-list.warnings li { border-left: 4px solid #e59a00; background: #fff9e9; }
    .rec-list.reasons li { border-left: 4px solid var(--teal-500); background: #effcfb; }
    .rec-score-head { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .rec-score-box { padding: 20px; border: 1px solid var(--slate-200); border-radius: 18px; background: var(--slate-50); }
    .rec-score-box.is-selected { border-color: rgba(22,190,179,.45); background: #effcfb; }
    .rec-score-box strong { display: block; margin-top: 8px; font: 700 2.2rem/1 "Space Grotesk", sans-serif; }
    .rec-table-wrap { width: 100%; overflow-x: auto; }
    .rec-table { width: 100%; min-width: 820px; border-collapse: collapse; }
    .rec-table th, .rec-table td { padding: 13px 12px; border-bottom: 1px solid var(--slate-200); text-align: left; vertical-align: top; }
    .rec-table th { color: var(--slate-500); font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; }
    .rec-table td { color: var(--slate-700); font-size: .9rem; line-height: 1.45; }
    .rec-result { padding: 24px; border-radius: 22px; color: #fff; background: linear-gradient(135deg, #052437, #126676); }
    .rec-result strong { display: block; margin: 8px 0 10px; font: 700 2rem/1.1 "Space Grotesk", sans-serif; }
    .rec-result p { margin: 0; color: rgba(255,255,255,.72); line-height: 1.55; }
    .rec-history { display: grid; gap: 14px; }
    .rec-history-item { padding: 16px 0; border-bottom: 1px solid var(--slate-200); }
    .rec-history-item:last-child { border-bottom: 0; }
    .rec-history-item strong { display: block; margin-bottom: 6px; }
    .rec-history-item span { color: var(--slate-500); font-size: .84rem; }
    .rec-form-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; margin-top: 22px; }
    .rec-stale { padding: 14px 16px; border: 1px solid #f3c268; border-radius: 16px; color: #7b4a00; background: #fff8e5; }
    @media (max-width: 1180px) {
        .rec-layout { grid-template-columns: 1fr; }
        .rec-source-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 900px) {
        .rec-hero { grid-template-columns: 1fr; }
        .rec-actions { justify-content: flex-start; }
        .rec-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .rec-hero, .rec-card { padding: 20px; border-radius: 20px; }
        .rec-summary, .rec-form-grid, .rec-score-head { grid-template-columns: 1fr; }
        .rec-form-grid .span-2 { grid-column: auto; }
    }
</style>
@endpush

@section('content')
    @php
        $hardConstraints = $recommendation->hard_constraints ?? $sourceAssessment['hard_constraints'];
        $warnings = $recommendation->warnings ?? $sourceAssessment['warnings'];
        $reasons = $recommendation->reasons ?? [];
        $sources = $sourceAssessment['source_snapshot'];
        $resultClass = in_array($recommendation->result_status, ['generated', 'overridden'], true) ? 'ok' : ($recommendation->result_status === 'incomplete' ? 'bad' : 'warn');
    @endphp

    <div class="rec-stack">
        <section class="rec-hero">
            <div>
                <div class="rec-hero-kicker">{{ $project->project_number }} · {{ $project->customer->name }}</div>
                <h2>{{ $project->name }}</h2>
                <p>
                    Explainable comparison of on-grid and hybrid alternatives using load, solar, roof, outage, backup, budget, and export assumptions.
                </p>
            </div>

            <div class="rec-actions">
                <a href="{{ route('companies.recommendations.index', $company) }}" class="ui-button ui-button-secondary">Recommendation Directory</a>
                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Project Details</a>
            </div>
        </section>

        @if ($isStale)
            <div class="rec-stale">
                Source data changed after the last recommendation run. Run the engine again before using this result for technical design.
            </div>
        @endif

        <section class="rec-summary" aria-label="Recommendation summary">
            <article class="rec-card rec-stat">
                <span>Current Result</span>
                <strong>{{ $recommendation->recommended_system_label }}</strong>
                <span>{{ $recommendation->result_status_label }}</span>
            </article>
            <article class="rec-card rec-stat">
                <span>On-grid Score</span>
                <strong>{{ $recommendation->on_grid_score !== null ? number_format((float) $recommendation->on_grid_score, 1) : '—' }}</strong>
                <span>Weighted suitability / 100</span>
            </article>
            <article class="rec-card rec-stat">
                <span>Hybrid Score</span>
                <strong>{{ $recommendation->hybrid_score !== null ? number_format((float) $recommendation->hybrid_score, 1) : '—' }}</strong>
                <span>Weighted suitability / 100</span>
            </article>
            <article class="rec-card rec-stat">
                <span>Decisiveness Index</span>
                <strong>{{ $recommendation->decisiveness_index !== null ? number_format((float) $recommendation->decisiveness_index, 1).'%' : '—' }}</strong>
                <span>Relative score separation</span>
            </article>
        </section>

        <section class="rec-card">
            <h3>Source Data Readiness</h3>
            <p class="rec-intro">The engine reads the latest project data. Complete source modules are required before a recommendation can be generated.</p>

            <div class="rec-source-grid">
                <article class="rec-source">
                    <span class="rec-badge {{ $sources['load']['available'] && $sources['load']['daily_energy_kwh'] > 0 ? 'ok' : 'bad' }}">
                        Load Analysis
                    </span>
                    <strong>{{ number_format((float) $sources['load']['daily_energy_kwh'], 2) }} kWh/day</strong>
                    <small>
                        {{ number_format((float) $sources['load']['day_share_percent'], 1) }}% daytime ·
                        {{ number_format((float) $sources['load']['critical_daily_energy_kwh'], 2) }} kWh critical
                    </small>
                </article>

                <article class="rec-source">
                    <span class="rec-badge {{ ($sources['solar']['complete_months'] ?? 0) === 12 ? ($sources['solar']['quality_status'] === 'verified' ? 'ok' : 'warn') : 'bad' }}">
                        Location & Solar
                    </span>
                    <strong>{{ number_format((float) $sources['solar']['average_psh'], 2) }} PSH</strong>
                    <small>
                        {{ $sources['solar']['complete_months'] }}/12 months ·
                        {{ ucfirst((string) ($sources['solar']['quality_status'] ?? 'not started')) }}
                    </small>
                </article>

                <article class="rec-source">
                    <span class="rec-badge {{ ($sources['roof']['usable_area_m2'] ?? 0) > 0 ? ($sources['roof']['quality_status'] === 'verified' ? 'ok' : 'warn') : 'bad' }}">
                        Roof & Site
                    </span>
                    <strong>{{ number_format((float) $sources['roof']['usable_area_m2'], 1) }} m² usable</strong>
                    <small>
                        {{ number_format((float) $sources['roof']['weighted_shading_percent'], 1) }}% shading ·
                        {{ ucfirst((string) ($sources['roof']['quality_status'] ?? 'not started')) }}
                    </small>
                </article>
            </div>
        </section>

        <div class="rec-layout">
            <main class="rec-main">
                <section class="rec-card">
                    <h3>Recommendation Inputs</h3>
                    <p class="rec-intro">These operational targets complement the measured project data. Every rerun replaces the previous engine result and removes an existing override.</p>

                    <form method="POST" action="{{ route('companies.recommendations.run', [$company, $project]) }}">
                        @csrf
                        @method('PUT')

                        <div class="rec-form-grid">
                            <div class="rec-field">
                                <label for="grid_connection_status">Grid connection condition</label>
                                <select id="grid_connection_status" name="grid_connection_status" @disabled(! $canRun)>
                                    <option value="stable" @selected(old('grid_connection_status', $recommendation->grid_connection_status) === 'stable')>Stable utility grid</option>
                                    <option value="occasional_outages" @selected(old('grid_connection_status', $recommendation->grid_connection_status) === 'occasional_outages')>Occasional outages</option>
                                    <option value="frequent_outages" @selected(old('grid_connection_status', $recommendation->grid_connection_status) === 'frequent_outages')>Frequent outages</option>
                                    <option value="unavailable" @selected(old('grid_connection_status', $recommendation->grid_connection_status) === 'unavailable')>Grid unavailable</option>
                                </select>
                            </div>

                            <div class="rec-field">
                                <label for="primary_objective">Primary objective</label>
                                <select id="primary_objective" name="primary_objective" @disabled(! $canRun)>
                                    <option value="savings" @selected(old('primary_objective', $recommendation->primary_objective) === 'savings')>Maximum savings</option>
                                    <option value="backup" @selected(old('primary_objective', $recommendation->primary_objective) === 'backup')>Backup reliability</option>
                                    <option value="balanced" @selected(old('primary_objective', $recommendation->primary_objective) === 'balanced')>Balanced performance</option>
                                    <option value="self_sufficiency" @selected(old('primary_objective', $recommendation->primary_objective) === 'self_sufficiency')>Energy self-sufficiency</option>
                                    <option value="maximize_roof" @selected(old('primary_objective', $recommendation->primary_objective) === 'maximize_roof')>Maximum roof utilization</option>
                                </select>
                            </div>

                            <div class="rec-field">
                                <label for="outage_frequency_per_month">Outages per month</label>
                                <input id="outage_frequency_per_month" type="number" min="0" max="100" step="1" name="outage_frequency_per_month" value="{{ old('outage_frequency_per_month', $recommendation->outage_frequency_per_month) }}" @disabled(! $canRun)>
                            </div>

                            <div class="rec-field">
                                <label for="average_outage_duration_hours">Average outage duration (hours)</label>
                                <input id="average_outage_duration_hours" type="number" min="0" max="72" step="0.25" name="average_outage_duration_hours" value="{{ old('average_outage_duration_hours', $recommendation->average_outage_duration_hours) }}" @disabled(! $canRun)>
                            </div>

                            <div class="rec-field">
                                <label for="backup_target_hours">Required backup duration (hours)</label>
                                <input id="backup_target_hours" type="number" min="0" max="72" step="0.25" name="backup_target_hours" value="{{ old('backup_target_hours', $recommendation->backup_target_hours) }}" @disabled(! $canRun)>
                                <span class="rec-help">Mark critical appliances in Load Analysis before requesting backup.</span>
                            </div>

                            <div class="rec-field">
                                <label for="target_self_sufficiency_percent">Self-sufficiency target (%)</label>
                                <input id="target_self_sufficiency_percent" type="number" min="0" max="100" step="1" name="target_self_sufficiency_percent" value="{{ old('target_self_sufficiency_percent', $recommendation->target_self_sufficiency_percent) }}" @disabled(! $canRun)>
                            </div>

                            <div class="rec-field">
                                <label for="budget_flexibility">Investment budget flexibility</label>
                                <select id="budget_flexibility" name="budget_flexibility" @disabled(! $canRun)>
                                    <option value="strict" @selected(old('budget_flexibility', $recommendation->budget_flexibility) === 'strict')>Strict</option>
                                    <option value="balanced" @selected(old('budget_flexibility', $recommendation->budget_flexibility) === 'balanced')>Balanced</option>
                                    <option value="flexible" @selected(old('budget_flexibility', $recommendation->budget_flexibility) === 'flexible')>Flexible</option>
                                </select>
                            </div>

                            <div class="rec-field">
                                <label for="export_policy">Grid export policy</label>
                                <select id="export_policy" name="export_policy" @disabled(! $canRun)>
                                    <option value="allowed" @selected(old('export_policy', $recommendation->export_policy) === 'allowed')>Export allowed</option>
                                    <option value="limited" @selected(old('export_policy', $recommendation->export_policy) === 'limited')>Export limited</option>
                                    <option value="zero_export" @selected(old('export_policy', $recommendation->export_policy) === 'zero_export')>Zero export required</option>
                                    <option value="unknown" @selected(old('export_policy', $recommendation->export_policy) === 'unknown')>Unknown</option>
                                </select>
                            </div>

                            <div class="rec-field span-2">
                                <label for="notes">Engineering notes</label>
                                <textarea id="notes" name="notes" placeholder="Assumptions, customer priorities, operational limitations, or reasons for selected targets" @disabled(! $canRun)>{{ old('notes', $recommendation->notes) }}</textarea>
                            </div>
                        </div>

                        @if ($canRun)
                            <div class="rec-form-actions">
                                <button type="submit" class="ui-button ui-button-primary">Run Recommendation Engine</button>
                            </div>
                        @endif
                    </form>
                </section>

                @if ($recommendation->hasGeneratedResult())
                    <section class="rec-card">
                        <h3>Weighted Score Breakdown</h3>
                        <p class="rec-intro">Each rating is multiplied by its documented criterion weight. The eight weights total 1.00.</p>

                        <div class="rec-score-head">
                            <article class="rec-score-box {{ $recommendation->engine_recommended_system === 'on_grid' ? 'is-selected' : '' }}">
                                <span>On-grid</span>
                                <strong>{{ number_format((float) $recommendation->on_grid_score, 2) }}</strong>
                                <small>{{ $recommendation->engine_recommended_system === 'on_grid' ? 'Engine-selected alternative' : 'Compared alternative' }}</small>
                            </article>
                            <article class="rec-score-box {{ $recommendation->engine_recommended_system === 'hybrid' ? 'is-selected' : '' }}">
                                <span>Hybrid</span>
                                <strong>{{ number_format((float) $recommendation->hybrid_score, 2) }}</strong>
                                <small>{{ $recommendation->engine_recommended_system === 'hybrid' ? 'Engine-selected alternative' : 'Compared alternative' }}</small>
                            </article>
                        </div>

                        <div class="rec-table-wrap">
                            <table class="rec-table">
                                <thead>
                                    <tr>
                                        <th>Criterion</th>
                                        <th>Weight</th>
                                        <th>On-grid Rating</th>
                                        <th>On-grid Weighted</th>
                                        <th>Hybrid Rating</th>
                                        <th>Hybrid Weighted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($scoreMatrix as $criterionRows)
                                        @php
                                            $onGridRow = $criterionRows->firstWhere('alternative', 'on_grid');
                                            $hybridRow = $criterionRows->firstWhere('alternative', 'hybrid');
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $onGridRow?->criterion_name ?? $hybridRow?->criterion_name }}</strong>
                                                <div class="rec-help">{{ $recommendation->engine_recommended_system === 'hybrid' ? $hybridRow?->rationale : $onGridRow?->rationale }}</div>
                                            </td>
                                            <td>{{ number_format((float) ($onGridRow?->weight ?? 0), 2) }}</td>
                                            <td>{{ number_format((float) ($onGridRow?->rating ?? 0), 1) }}</td>
                                            <td>{{ number_format((float) ($onGridRow?->weighted_score ?? 0), 2) }}</td>
                                            <td>{{ number_format((float) ($hybridRow?->rating ?? 0), 1) }}</td>
                                            <td>{{ number_format((float) ($hybridRow?->weighted_score ?? 0), 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                @if ($recommendation->histories->isNotEmpty())
                    <section class="rec-card">
                        <h3>Decision History</h3>
                        <p class="rec-intro">Engine runs and engineer overrides are retained for traceability.</p>
                        <div class="rec-history">
                            @foreach ($recommendation->histories as $history)
                                <article class="rec-history-item">
                                    <strong>{{ ucfirst(str_replace('_', ' ', $history->event_type)) }}</strong>
                                    <p>{{ $history->summary }}</p>
                                    <span>
                                        {{ $history->changedBy?->name ?? 'System' }} ·
                                        {{ $history->changed_at?->format('d M Y, H:i') }} ·
                                        {{ $history->previous_system ? ucfirst(str_replace('_', '-', $history->previous_system)).' → ' : '' }}
                                        {{ $history->new_system ? ucfirst(str_replace('_', '-', $history->new_system)) : 'No recommendation' }}
                                    </span>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </main>

            <aside class="rec-side">
                <section class="rec-result">
                    <span class="rec-badge {{ $resultClass }}">{{ $recommendation->result_status_label }}</span>
                    <strong>{{ $recommendation->recommended_system_label }}</strong>
                    <p>
                        @if ($recommendation->result_status === 'overridden')
                            Engine result: {{ $recommendation->engine_recommended_system_label }}. The displayed result was changed through a documented engineer override.
                        @elseif ($recommendation->hasGeneratedResult())
                            Preliminary system-type recommendation. Technical sizing and component compatibility checks remain required.
                        @else
                            Complete prerequisite modules and run the engine to obtain a recommendation.
                        @endif
                    </p>
                </section>

                @if ($hardConstraints !== [])
                    <section class="rec-card">
                        <h3>Blocking Conditions</h3>
                        <p class="rec-intro">The engine will not choose a system while these hard constraints remain.</p>
                        <ul class="rec-list blockers">
                            @foreach ($hardConstraints as $constraint)
                                <li>{{ $constraint }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($reasons !== [])
                    <section class="rec-card">
                        <h3>Recommendation Reasons</h3>
                        <ul class="rec-list reasons">
                            @foreach ($reasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($warnings !== [])
                    <section class="rec-card">
                        <h3>Warnings</h3>
                        <ul class="rec-list warnings">
                            @foreach ($warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($canOverride && $recommendation->hasGeneratedResult())
                    <section class="rec-card">
                        <h3>Engineer Override</h3>
                        <p class="rec-intro">An override does not change the engine scores. A specific technical reason is required and retained in history.</p>

                        <form method="POST" action="{{ route('companies.recommendations.override', [$company, $project]) }}">
                            @csrf
                            @method('PATCH')

                            <div class="rec-field">
                                <label for="override_system">Final selected system</label>
                                <select id="override_system" name="override_system">
                                    <option value="on_grid" @selected(old('override_system', $recommendation->recommended_system) === 'on_grid')>On-grid</option>
                                    <option value="hybrid" @selected(old('override_system', $recommendation->recommended_system) === 'hybrid')>Hybrid</option>
                                </select>
                            </div>

                            <div class="rec-field" style="margin-top: 16px;">
                                <label for="override_reason">Technical reason</label>
                                <textarea id="override_reason" name="override_reason" placeholder="Minimum 20 characters. Explain the project-specific technical or operational basis.">{{ old('override_reason', $recommendation->override_reason) }}</textarea>
                            </div>

                            <div class="rec-form-actions">
                                <button type="submit" class="ui-button ui-button-primary">Apply Override</button>
                            </div>
                        </form>

                        @if ($recommendation->override_system !== null)
                            <form method="POST" action="{{ route('companies.recommendations.override.reset', [$company, $project]) }}" style="margin-top: 12px;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-button ui-button-secondary">Restore Engine Result</button>
                            </form>
                        @endif
                    </section>
                @endif

                <section class="rec-card">
                    <h3>Method Note</h3>
                    <p class="rec-intro" style="margin-bottom: 0;">
                        This module uses hard constraints followed by weighted rule-based scoring. The weights are preliminary NEXORIN development values and must be validated through engineer review, scenario tests, and sensitivity analysis. The decisiveness index measures score separation, not statistical confidence.
                    </p>
                </section>
            </aside>
        </div>
    </div>
@endsection
