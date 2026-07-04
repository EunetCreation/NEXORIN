@extends('layouts.app')

@section('title', 'Financial Analysis')
@section('page-eyebrow', 'Business output')
@section('page-heading', 'Financial Analysis')

@push('styles')
<style>
    .fin-page{display:grid;gap:24px}.fin-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px;align-items:center;padding:30px 34px;border-radius:30px;color:#fff;background:linear-gradient(135deg,#021c2b 0%,#07505d 58%,#10826d 100%);box-shadow:0 24px 55px rgba(4,45,58,.18)}.fin-kicker{font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#5eead4}.fin-hero h2{margin:8px 0;font:700 clamp(2rem,4vw,3.5rem)/1.04 "Space Grotesk",sans-serif;letter-spacing:-.045em}.fin-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.74);line-height:1.62}.fin-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}.fin-actions .ui-button-secondary{border-color:rgba(255,255,255,.24);background:rgba(255,255,255,.08);color:#fff}.fin-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(320px,.85fr);gap:24px;align-items:start}.fin-card{min-width:0;padding:24px;border:1px solid var(--slate-200);border-radius:24px;background:#fff;box-shadow:var(--shadow-sm)}.fin-card h3{margin:0;color:var(--navy-900);font-size:1.1rem}.fin-sub{margin:7px 0 0;color:var(--slate-500);line-height:1.56}.fin-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.fin-metric{padding:18px;border:1px solid var(--slate-200);border-radius:20px;background:var(--slate-50)}.fin-metric span{display:block;color:var(--slate-500);font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em}.fin-metric strong{display:block;margin-top:8px;color:var(--navy-900);font:700 1.45rem/1.1 "Space Grotesk",sans-serif}.fin-form{display:grid;gap:16px}.fin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.fin-money-list{display:grid;gap:10px;margin-top:16px}.fin-money-row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--slate-100);color:var(--slate-600)}.fin-money-row strong{color:var(--navy-900)}.fin-money-row.total{margin-top:4px;padding:14px;border:1px solid #bbf7d0;border-radius:16px;background:#f0fdf4}.fin-table-wrap{overflow-x:auto}.fin-table{width:100%;border-collapse:collapse;margin-top:18px}.fin-table th{padding:12px;color:var(--slate-500);font-size:.72rem;text-align:right;letter-spacing:.08em;text-transform:uppercase;background:var(--slate-50)}.fin-table th:first-child,.fin-table td:first-child{text-align:left}.fin-table td{padding:12px;border-top:1px solid var(--slate-100);text-align:right}.fin-alert{padding:14px 16px;border-radius:18px;border:1px solid #fed7aa;background:#fff7ed;color:#9a3412}.fin-empty{padding:26px;border:1px dashed var(--slate-300);border-radius:20px;background:var(--slate-50);color:var(--slate-500)}
    @media(max-width:1100px){.fin-layout,.fin-hero{grid-template-columns:1fr}.fin-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.fin-grid{grid-template-columns:1fr}.fin-metrics{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $money = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $number = fn ($value, $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', '.');
    $summary = $analysis?->summary ?? [];
    $cashflows = $analysis?->cashflows ?? [];
@endphp

<div class="fin-page">
    <section class="fin-hero">
        <div>
            <div class="fin-kicker">{{ $project->project_number }} · {{ $project->customer?->name ?? 'No customer' }}</div>
            <h2>{{ $project->name }}</h2>
            <p>Financial analysis uses the latest technical calculation energy output and priced BOQ CAPEX. Results remain estimates until assumptions and regulation parameters are verified.</p>
        </div>
        <div class="fin-actions">
            <a class="ui-button ui-button-secondary" href="{{ route('companies.boms.show', [$company, $project]) }}">Open BOM/BOQ</a>
            <a class="ui-button ui-button-secondary" href="{{ route('companies.quotations.show', [$company, $project]) }}">Create Quotation</a>
            <a class="ui-button ui-button-secondary" href="{{ route('companies.financial-analyses.index', $company) }}">Back to list</a>
        </div>
    </section>

    @if ($analysis && ! empty($analysis->warnings))
        <div class="fin-alert">
            <strong>Warnings:</strong>
            <ul style="margin:8px 0 0 18px">
                @foreach ($analysis->warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="fin-metrics">
        <div class="fin-metric"><span>CAPEX</span><strong>{{ $analysis ? $money($analysis->project_capex_idr) : ($bom ? $money($bom->grand_price_idr) : '—') }}</strong></div>
        <div class="fin-metric"><span>First Year Saving</span><strong>{{ $analysis ? $money($analysis->first_year_saving_idr) : '—' }}</strong></div>
        <div class="fin-metric"><span>Payback</span><strong>{{ $analysis?->simple_payback_years ? $number($analysis->simple_payback_years).' yr' : '—' }}</strong></div>
        <div class="fin-metric"><span>NPV</span><strong>{{ $analysis ? $money($analysis->npv_idr) : '—' }}</strong></div>
    </section>

    <div class="fin-layout">
        <main class="fin-card">
            <h3>Financial Result</h3>
            <p class="fin-sub">Payback, ROI, NPV, IRR, LCOE, and cashflow are recalculated whenever assumptions change.</p>

            @if (! $analysis)
                <div class="fin-empty" style="margin-top:18px">
                    No financial analysis yet. Calculate it after BOQ pricing is complete.
                </div>
            @else
                <div class="fin-money-list">
                    <div class="fin-money-row"><span>Annual Energy</span><strong>{{ $number($analysis->annual_energy_kwh) }} kWh</strong></div>
                    <div class="fin-money-row"><span>Self Consumption</span><strong>{{ $number($analysis->self_consumption_percent) }}%</strong></div>
                    <div class="fin-money-row"><span>Annual O&amp;M</span><strong>{{ $money($analysis->annual_om_cost_idr) }}</strong></div>
                    <div class="fin-money-row"><span>Lifetime ROI</span><strong>{{ $number($analysis->lifetime_roi_percent) }}%</strong></div>
                    <div class="fin-money-row"><span>IRR</span><strong>{{ $analysis->irr_percent === null ? '—' : $number($analysis->irr_percent).'%' }}</strong></div>
                    <div class="fin-money-row total"><span>LCOE</span><strong>{{ $analysis->lcoe_idr_per_kwh === null ? '—' : $money($analysis->lcoe_idr_per_kwh).'/kWh' }}</strong></div>
                </div>

                <div class="fin-table-wrap">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Energy</th>
                                <th>Saving</th>
                                <th>O&amp;M</th>
                                <th>Replacement</th>
                                <th>Net</th>
                                <th>Cumulative</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($cashflows, 0, 25) as $row)
                                <tr>
                                    <td>{{ $row['year'] }}</td>
                                    <td>{{ number_format((float) $row['energy_kwh'], 0, ',', '.') }} kWh</td>
                                    <td>{{ $money($row['gross_saving_idr']) }}</td>
                                    <td>{{ $money($row['om_cost_idr']) }}</td>
                                    <td>{{ $money($row['replacement_cost_idr']) }}</td>
                                    <td>{{ $money($row['net_cashflow_idr']) }}</td>
                                    <td>{{ $money($row['cumulative_cashflow_idr']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </main>

        <aside class="fin-card">
            <h3>Assumptions</h3>
            <p class="fin-sub">Change tariff, O&amp;M, discount rate, degradation, and replacement assumptions here.</p>

            @if (! $calculation?->calculated_at)
                <div class="fin-empty" style="margin-top:18px">Run technical calculation first.</div>
            @elseif (! $bom || $bom->pricing_status !== 'priced')
                <div class="fin-empty" style="margin-top:18px">Calculate BOQ price first.</div>
            @elseif ($canRun)
                <form class="fin-form" method="POST" action="{{ route('companies.financial-analyses.calculate', [$company, $project]) }}">
                    @csrf
                    <div class="fin-grid">
                        <div class="ui-field">
                            <label class="ui-label">Electricity Tariff IDR/kWh</label>
                            <input class="ui-input" type="number" step="0.01" min="0" name="electricity_tariff_idr_per_kwh" value="{{ $parameters['electricity_tariff_idr_per_kwh'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Export Credit %</label>
                            <input class="ui-input" type="number" step="0.01" min="0" max="100" name="export_credit_percent" value="{{ $parameters['export_credit_percent'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Self Consumption %</label>
                            <input class="ui-input" type="number" step="0.01" min="0" max="100" name="self_consumption_percent" value="{{ $parameters['self_consumption_percent'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Tariff Escalation %/yr</label>
                            <input class="ui-input" type="number" step="0.01" min="0" max="100" name="tariff_escalation_percent" value="{{ $parameters['tariff_escalation_percent'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Annual O&amp;M %</label>
                            <input class="ui-input" type="number" step="0.01" min="0" max="100" name="annual_om_percent" value="{{ $parameters['annual_om_percent'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Discount Rate %</label>
                            <input class="ui-input" type="number" step="0.01" min="0" max="100" name="discount_rate_percent" value="{{ $parameters['discount_rate_percent'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Energy Degradation %/yr</label>
                            <input class="ui-input" type="number" step="0.01" min="0" max="100" name="energy_degradation_percent" value="{{ $parameters['energy_degradation_percent'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Lifetime Years</label>
                            <input class="ui-input" type="number" step="1" min="1" max="40" name="project_lifetime_years" value="{{ $parameters['project_lifetime_years'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Replacement Year</label>
                            <input class="ui-input" type="number" step="1" min="0" max="40" name="replacement_year" value="{{ $parameters['replacement_year'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Replacement Cost</label>
                            <input class="ui-input" type="number" step="1" min="0" name="replacement_cost_idr" value="{{ $parameters['replacement_cost_idr'] }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Project CAPEX Override</label>
                            <input class="ui-input" type="number" step="1" min="0" name="project_capex_idr" value="{{ $parameters['project_capex_idr'] }}">
                        </div>
                    </div>
                    <div class="ui-field">
                        <label class="ui-label">Analysis Note</label>
                        <textarea class="ui-input" name="analysis_note" rows="3" placeholder="Optional note">{{ $parameters['analysis_note'] }}</textarea>
                    </div>
                    <button class="ui-button ui-button-primary" type="submit">Calculate Financial Analysis</button>
                </form>
            @else
                <div class="fin-empty" style="margin-top:18px">You can view this analysis, but your role cannot calculate financial results.</div>
            @endif
        </aside>
    </div>
</div>
@endsection
