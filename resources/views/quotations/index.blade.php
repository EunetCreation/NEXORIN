@extends('layouts.app')

@section('title', 'Quotations')
@section('page-eyebrow', 'Business output')
@section('page-heading', 'Quotations')

@push('styles')
<style>
    .quote-page{display:grid;gap:24px}.quote-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px;align-items:center;padding:30px 34px;border-radius:30px;color:#fff;background:linear-gradient(135deg,#021c2b 0%,#064255 58%,#0f766e 100%);box-shadow:0 24px 55px rgba(4,45,58,.18)}
    .quote-kicker{font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#5eead4}.quote-hero h2{margin:8px 0;font:700 clamp(2rem,4vw,3.5rem)/1.04 "Space Grotesk",sans-serif;letter-spacing:-.045em}.quote-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.74);line-height:1.62}.quote-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.quote-stat{padding:20px;border:1px solid var(--slate-200);border-radius:22px;background:#fff;box-shadow:var(--shadow-sm)}.quote-stat span{color:var(--slate-500);font-size:.82rem;font-weight:700}.quote-stat strong{display:block;margin-top:7px;color:var(--navy-900);font:700 2rem/1 "Space Grotesk",sans-serif}.quote-card{padding:24px;border:1px solid var(--slate-200);border-radius:24px;background:#fff;box-shadow:var(--shadow-sm)}.quote-filters{display:grid;grid-template-columns:minmax(220px,1fr) 180px auto;gap:12px;margin-bottom:18px}.quote-table-wrap{overflow-x:auto}.quote-table{width:100%;border-collapse:collapse}.quote-table th{padding:12px;color:var(--slate-500);font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;text-align:left;background:var(--slate-50)}.quote-table td{padding:16px 12px;border-top:1px solid var(--slate-100);vertical-align:top}.quote-primary{color:var(--navy-900);font-weight:800}.quote-secondary{margin-top:4px;color:var(--slate-500);font-size:.84rem}.quote-pill{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:.72rem;font-weight:800;background:#eefbf8;color:#0f766e}.quote-pill.warn{background:#fff7ed;color:#c2410c}.quote-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}.quote-empty{padding:28px;border:1px dashed var(--slate-300);border-radius:20px;color:var(--slate-500);text-align:center;background:var(--slate-50)}
    @media(max-width:900px){.quote-hero,.quote-filters{grid-template-columns:1fr}.quote-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
@endpush

@section('content')
@php
    $money = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp

<div class="quote-page">
    <section class="quote-hero">
        <div>
            <div class="quote-kicker">Customer-facing proposal</div>
            <h2>Create quotations from approved engineering and pricing.</h2>
            <p>Quotation uses BOQ selling price and financial analysis, then hides internal cost data from customer-facing output.</p>
        </div>
        <a class="ui-button ui-button-secondary" href="{{ route('companies.financial-analyses.index', $company) }}">Review Financial Analysis</a>
    </section>

    <section class="quote-stats">
        <article class="quote-stat"><span>Projects</span><strong>{{ number_format($stats['projects']) }}</strong></article>
        <article class="quote-stat"><span>Ready</span><strong>{{ number_format($stats['ready']) }}</strong></article>
        <article class="quote-stat"><span>Draft</span><strong>{{ number_format($stats['draft']) }}</strong></article>
        <article class="quote-stat"><span>Sent</span><strong>{{ number_format($stats['issued']) }}</strong></article>
    </section>

    <section class="quote-card">
        <form class="quote-filters" method="GET" action="{{ route('companies.quotations.index', $company) }}">
            <div class="ui-field">
                <label class="ui-label">Search projects</label>
                <input class="ui-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Project, customer, city">
            </div>
            <div class="ui-field">
                <label class="ui-label">Status</label>
                <select class="ui-select" name="status">
                    <option value="all" @selected($filters['status'] === 'all')>All</option>
                    <option value="not_generated" @selected($filters['status'] === 'not_generated')>Not generated</option>
                    <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                    <option value="internal_review" @selected($filters['status'] === 'internal_review')>Internal Review</option>
                    <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                    <option value="sent" @selected($filters['status'] === 'sent')>Sent</option>
                    <option value="issued" @selected($filters['status'] === 'issued')>Legacy Issued</option>
                    <option value="accepted" @selected($filters['status'] === 'accepted')>Accepted</option>
                    <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                    <option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option>
                    <option value="expired" @selected($filters['status'] === 'expired')>Expired</option>
                </select>
            </div>
            <button class="ui-button ui-button-secondary" type="submit">Apply</button>
        </form>

        @if ($projects->isEmpty())
            <div class="quote-empty">No projects match the selected filter.</div>
        @else
            <div class="quote-table-wrap">
                <table class="quote-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Customer</th>
                            <th>Readiness</th>
                            <th>Quotation</th>
                            <th>Total</th>
                            <th>Valid Until</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            @php($quotation = $project->quotation)
                            @php($ready = $project->billOfMaterial?->pricing_status === 'priced' && $project->financialAnalysis?->status === 'calculated')
                            <tr>
                                <td>
                                    <div class="quote-primary">{{ $project->name }}</div>
                                    <div class="quote-secondary">{{ $project->project_number }}</div>
                                </td>
                                <td>
                                    <div class="quote-primary">{{ $project->customer?->name ?? 'Customer unavailable' }}</div>
                                    <div class="quote-secondary">{{ $project->customer?->customer_number ?? '—' }}</div>
                                </td>
                                <td>
                                    @if ($ready)
                                        <span class="quote-pill">Ready</span>
                                    @else
                                        <span class="quote-pill warn">Need BOQ/Financial</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($quotation)
                                        <div class="quote-primary">{{ $quotation->quote_number }}</div>
                                        <div class="quote-secondary">{{ $quotation->status_label }} · Rev {{ $quotation->revision_number }}</div>
                                    @else
                                        <span class="quote-pill warn">Not generated</span>
                                    @endif
                                </td>
                                <td>{{ $quotation ? $money($quotation->commercial_summary['grand_total_idr'] ?? 0) : ($project->billOfMaterial ? $money($project->billOfMaterial->grand_price_idr) : '—') }}</td>
                                <td>{{ $quotation?->valid_until?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <div class="quote-actions">
                                        <a class="ui-button ui-button-secondary" href="{{ route('companies.quotations.show', [$company, $project]) }}">Open</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="ui-table-footer">
                @include('partials.pagination', ['paginator' => $projects])
            </div>
        @endif
    </section>
</div>
@endsection
