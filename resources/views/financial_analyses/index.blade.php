@extends('layouts.app')

@section('title', 'Financial Analysis')
@section('page-eyebrow', 'Business output')
@section('page-heading', 'Financial Analysis')

@push('styles')
<style>
    .fin-page{display:grid;gap:24px}.fin-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px;align-items:center;padding:30px 34px;border-radius:30px;color:#fff;background:linear-gradient(135deg,#021c2b 0%,#07505d 58%,#10826d 100%);box-shadow:0 24px 55px rgba(4,45,58,.18)}
    .fin-kicker{font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#5eead4}.fin-hero h2{margin:8px 0;font:700 clamp(2rem,4vw,3.5rem)/1.04 "Space Grotesk",sans-serif;letter-spacing:-.045em}.fin-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.74);line-height:1.62}.fin-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.fin-stat{padding:20px;border:1px solid var(--slate-200);border-radius:22px;background:#fff;box-shadow:var(--shadow-sm)}.fin-stat span{color:var(--slate-500);font-size:.82rem;font-weight:700}.fin-stat strong{display:block;margin-top:7px;color:var(--navy-900);font:700 2rem/1 "Space Grotesk",sans-serif}.fin-card{padding:24px;border:1px solid var(--slate-200);border-radius:24px;background:#fff;box-shadow:var(--shadow-sm)}.fin-filters{display:grid;grid-template-columns:minmax(220px,1fr) 180px auto;gap:12px;margin-bottom:18px}.fin-table-wrap{overflow-x:auto}.fin-table{width:100%;border-collapse:collapse}.fin-table th{padding:12px;color:var(--slate-500);font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;text-align:left;background:var(--slate-50)}.fin-table td{padding:16px 12px;border-top:1px solid var(--slate-100);vertical-align:top}.fin-primary{color:var(--navy-900);font-weight:800}.fin-secondary{margin-top:4px;color:var(--slate-500);font-size:.84rem}.fin-pill{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:.72rem;font-weight:800;background:#eefbf8;color:#0f766e}.fin-pill.warn{background:#fff7ed;color:#c2410c}.fin-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}.fin-empty{padding:28px;border:1px dashed var(--slate-300);border-radius:20px;color:var(--slate-500);text-align:center;background:var(--slate-50)}
    @media(max-width:900px){.fin-hero,.fin-filters{grid-template-columns:1fr}.fin-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
@endpush

@section('content')
@php
    $money = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp

<div class="fin-page">
    <section class="fin-hero">
        <div>
            <div class="fin-kicker">Financial viability</div>
            <h2>Convert BOQ price into investment metrics.</h2>
            <p>Use technical energy output and BOQ pricing to estimate savings, payback, ROI, NPV, IRR, LCOE, and annual cashflow.</p>
        </div>
        <a class="ui-button ui-button-secondary" href="{{ route('companies.boms.index', $company) }}">Review BOM/BOQ</a>
    </section>

    <section class="fin-stats">
        <div class="fin-stat"><span>Projects</span><strong>{{ number_format($stats['projects']) }}</strong></div>
        <div class="fin-stat"><span>Ready</span><strong>{{ number_format($stats['ready']) }}</strong></div>
        <div class="fin-stat"><span>Calculated</span><strong>{{ number_format($stats['calculated']) }}</strong></div>
        <div class="fin-stat"><span>Locked</span><strong>{{ number_format($stats['locked']) }}</strong></div>
    </section>

    <section class="fin-card">
        <form class="fin-filters" method="GET" action="{{ route('companies.financial-analyses.index', $company) }}">
            <input class="ui-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search project, number, customer, or city">
            <select class="ui-input" name="status">
                @foreach (['all' => 'All', 'not_calculated' => 'Not calculated', 'draft' => 'Draft', 'calculated' => 'Calculated', 'locked' => 'Locked'] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="ui-button ui-button-primary" type="submit">Filter</button>
        </form>

        @if ($projects->isEmpty())
            <div class="fin-empty">No project matched the selected filter.</div>
        @else
            <div class="fin-table-wrap">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Readiness</th>
                            <th>CAPEX</th>
                            <th>Saving</th>
                            <th>Payback</th>
                            <th>NPV</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            @php
                                $analysis = $project->financialAnalysis;
                                $bom = $project->billOfMaterial;
                                $ready = $project->technicalCalculation?->calculated_at && $bom?->pricing_status === 'priced' && (float) $bom?->grand_price_idr > 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fin-primary">{{ $project->name }}</div>
                                    <div class="fin-secondary">{{ $project->project_number }} · {{ $project->customer?->name ?? 'No customer' }}</div>
                                </td>
                                <td>
                                    @if ($ready)
                                        <span class="fin-pill">Ready</span>
                                    @else
                                        <span class="fin-pill warn">Need BOQ price</span>
                                    @endif
                                    <div class="fin-secondary">{{ $analysis?->status_label ?? 'Not calculated' }}</div>
                                </td>
                                <td>{{ $analysis ? $money($analysis->project_capex_idr) : ($bom ? $money($bom->grand_price_idr) : '—') }}</td>
                                <td>{{ $analysis ? $money($analysis->first_year_saving_idr) : '—' }}</td>
                                <td>{{ $analysis?->simple_payback_years ? number_format((float) $analysis->simple_payback_years, 2).' years' : '—' }}</td>
                                <td>{{ $analysis ? $money($analysis->npv_idr) : '—' }}</td>
                                <td>
                                    <div class="fin-actions">
                                        <a class="ui-button ui-button-secondary" href="{{ route('companies.financial-analyses.show', [$company, $project]) }}">Open</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $projects->links() }}
        @endif
    </section>
</div>
@endsection
