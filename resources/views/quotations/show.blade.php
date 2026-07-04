@extends('layouts.app')

@section('title', 'Quotation')
@section('page-eyebrow', 'Business output')
@section('page-heading', 'Quotation')

@push('styles')
<style>
    .quote-page{display:grid;gap:24px}.quote-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:24px;align-items:center;padding:32px 36px;border-radius:30px;background:linear-gradient(135deg,#021c2b 0%,#064255 58%,#0f766e 100%);color:#fff;box-shadow:0 24px 55px rgba(4,45,58,.18)}
    .quote-kicker{font-size:.8rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#5eead4}.quote-hero h2{margin:10px 0 8px;font:700 clamp(2.1rem,4vw,3.8rem)/1.02 "Space Grotesk",sans-serif;letter-spacing:-.045em}.quote-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.72);font-size:1rem;line-height:1.65}.quote-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.quote-actions .ui-button-secondary{border-color:rgba(255,255,255,.24);background:rgba(255,255,255,.08);color:#fff}.quote-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,.85fr);gap:24px;align-items:start}.quote-card{padding:24px;border:1px solid var(--slate-200);border-radius:24px;background:#fff;box-shadow:var(--shadow-sm);min-width:0}.quote-card h3{margin:0;color:var(--navy-900);font:700 1.18rem/1.2 "Space Grotesk",sans-serif}.quote-sub{margin:7px 0 0;color:var(--slate-500);font-size:.9rem;line-height:1.55}.quote-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.quote-metric{padding:18px;border:1px solid var(--slate-200);border-radius:20px;background:#fff;box-shadow:var(--shadow-sm)}.quote-metric span{display:block;color:var(--slate-500);font-size:.76rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.quote-metric strong{display:block;margin-top:8px;color:var(--navy-900);font:700 1.45rem/1 "Space Grotesk",sans-serif}.quote-table-wrap{overflow-x:auto;margin-top:18px}.quote-table{width:100%;border-collapse:collapse}.quote-table th{padding:12px;color:var(--slate-500);font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;text-align:left;background:var(--slate-50)}.quote-table td{padding:14px 12px;border-top:1px solid var(--slate-100);vertical-align:top}.quote-total{font-weight:900;color:var(--navy-900);background:var(--slate-50)}.quote-empty,.quote-alert{padding:18px;border-radius:18px}.quote-empty{border:1px dashed var(--slate-300);color:var(--slate-500);text-align:center;background:var(--slate-50)}.quote-alert{border:1px solid #fed7aa;background:#fff7ed;color:#9a3412}.quote-form{display:grid;gap:14px;margin-top:18px}.quote-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.quote-preview{display:grid;gap:12px;margin-top:18px}.quote-row{display:flex;justify-content:space-between;gap:14px;padding:12px 0;border-bottom:1px solid var(--slate-100)}.quote-row span{color:var(--slate-500)}.quote-row strong{text-align:right;color:var(--navy-900)}.quote-terms{display:grid;gap:10px;margin-top:18px}.quote-term{padding:14px;border:1px solid var(--slate-100);border-radius:16px;background:var(--slate-50)}.quote-term span{display:block;font-weight:800;color:var(--navy-900);margin-bottom:5px}.quote-term p{margin:0;color:var(--slate-600);line-height:1.5}.quote-issue{margin-top:16px;padding-top:16px;border-top:1px solid var(--slate-100)}
    @media(max-width:1100px){.quote-hero,.quote-layout{grid-template-columns:1fr}.quote-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.quote-actions{justify-content:flex-start}}
    @media(max-width:700px){.quote-grid,.quote-metrics{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $money = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $number = fn ($value, $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', '.');
    $commercial = $quotation?->commercial_summary ?? [];
    $technical = $quotation?->technical_summary ?? [];
    $financial = $quotation?->financial_summary ?? [];
    $terms = $quotation?->terms ?? [];
@endphp

<div class="quote-page">
    <section class="quote-hero">
        <div>
            <div class="quote-kicker">{{ $project->project_number }} · {{ $project->customer?->name ?? 'No customer' }}</div>
            <h2>{{ $quotation?->quote_number ?? 'Quotation draft' }}</h2>
            <p>Customer-facing quotation generated from the priced BOQ and financial analysis. Cost price, gross margin, and internal pricing assumptions are not shown here.</p>
        </div>
        <div class="quote-actions">
            <a class="ui-button ui-button-secondary" href="{{ route('companies.financial-analyses.show', [$company, $project]) }}">Financial Analysis</a>
            <a class="ui-button ui-button-secondary" href="{{ route('companies.quotations.index', $company) }}">Back to list</a>
        </div>
    </section>

    @if ($quotation && ! empty($quotation->warnings))
        <div class="quote-alert">
            <strong>Warnings:</strong>
            <ul style="margin:8px 0 0 18px">
                @foreach ($quotation->warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="quote-metrics">
        <div class="quote-metric"><span>Status</span><strong>{{ $quotation?->status_label ?? 'Not ready' }}</strong></div>
        <div class="quote-metric"><span>Grand Total</span><strong>{{ $quotation ? $money($commercial['grand_total_idr'] ?? 0) : ($bom ? $money($bom->grand_price_idr) : '—') }}</strong></div>
        <div class="quote-metric"><span>PV Capacity</span><strong>{{ $quotation ? $number($technical['installed_pv_kw'] ?? null, 2).' kWp' : '—' }}</strong></div>
        <div class="quote-metric"><span>Payback</span><strong>{{ $quotation && ($financial['simple_payback_years'] ?? null) !== null ? $number($financial['simple_payback_years'], 2).' yr' : '—' }}</strong></div>
    </section>

    <div class="quote-layout">
        <main class="quote-card">
            <h3>Customer Quotation Preview</h3>
            <p class="quote-sub">This section is safe to show to customer. It contains selling price, technical summary, financial highlights, and commercial terms.</p>

            @if (! $quotation)
                <div class="quote-empty" style="margin-top:18px">No quotation yet. Generate a draft after BOQ pricing and financial analysis are complete.</div>
            @else
                <div class="quote-preview">
                    <div class="quote-row"><span>Customer</span><strong>{{ $quotation->customer_snapshot['name'] ?? $project->customer?->name }}</strong></div>
                    <div class="quote-row"><span>Project</span><strong>{{ $quotation->project_snapshot['name'] ?? $project->name }}</strong></div>
                    <div class="quote-row"><span>Site</span><strong>{{ $quotation->project_snapshot['site_address'] ?? $project->site_address }}</strong></div>
                    <div class="quote-row"><span>Valid Until</span><strong>{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</strong></div>
                    <div class="quote-row"><span>Prepared By</span><strong>{{ $quotation->preparer?->name ?? 'System' }}</strong></div>
                    @if ($quotation->issued_at)
                        <div class="quote-row"><span>Issued By</span><strong>{{ $quotation->issuer?->name ?? 'System' }} · {{ $quotation->issued_at->format('d M Y, H:i') }}</strong></div>
                    @endif
                </div>

                <div class="quote-table-wrap">
                    <table class="quote-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Unit Price</th>
                                <th>Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotation->items as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->item_name }}</strong>
                                        <div class="quote-sub">{{ $item->description }}</div>
                                    </td>
                                    <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td>{{ $money($item->unit_price_idr) }}</td>
                                    <td>{{ $money($item->line_price_idr) }}</td>
                                </tr>
                            @endforeach
                            <tr class="quote-total">
                                <td colspan="4">Grand Total</td>
                                <td>{{ $money($commercial['grand_total_idr'] ?? 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="quote-terms">
                    <div class="quote-term"><span>Payment Terms</span><p>{{ $terms['payment_terms'] ?? '—' }}</p></div>
                    <div class="quote-term"><span>Delivery Terms</span><p>{{ $terms['delivery_terms'] ?? '—' }}</p></div>
                    <div class="quote-term"><span>Warranty Terms</span><p>{{ $terms['warranty_terms'] ?? '—' }}</p></div>
                    <div class="quote-term"><span>Exclusions</span><p>{{ $terms['exclusions'] ?? '—' }}</p></div>
                    @if (! empty($terms['quotation_note']))
                        <div class="quote-term"><span>Note</span><p>{{ $terms['quotation_note'] }}</p></div>
                    @endif
                </div>

                @if ($canIssue)
                    <form class="quote-issue" method="POST" action="{{ route('companies.quotations.issue', [$company, $project]) }}">
                        @csrf
                        @method('PATCH')
                        <button class="ui-button ui-button-primary" type="submit" data-confirm="Issue this quotation? It will be marked as customer-ready.">Issue Quotation</button>
                    </form>
                @endif
            @endif
        </main>

        <aside class="quote-card">
            <h3>Generate Quotation</h3>
            <p class="quote-sub">Generate or revise the customer-facing quotation using the latest BOQ and financial analysis.</p>

            @if (! $bom || $bom->pricing_status !== 'priced')
                <div class="quote-empty" style="margin-top:18px">Calculate BOQ price first.</div>
            @elseif (! $analysis || $analysis->status !== 'calculated')
                <div class="quote-empty" style="margin-top:18px">Calculate financial analysis first.</div>
            @elseif ($canGenerate)
                <form class="quote-form" method="POST" action="{{ route('companies.quotations.generate', [$company, $project]) }}">
                    @csrf
                    <div class="ui-field">
                        <label class="ui-label">Quotation Title</label>
                        <input class="ui-input" type="text" name="quotation_title" value="{{ old('quotation_title', $parameters['quotation_title']) }}">
                    </div>
                    <div class="quote-grid">
                        <div class="ui-field">
                            <label class="ui-label">Validity Days</label>
                            <input class="ui-input" type="number" min="1" max="90" name="validity_days" value="{{ old('validity_days', $parameters['validity_days']) }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Show Item Detail</label>
                            <select class="ui-select" name="include_item_detail">
                                <option value="1" @selected(old('include_item_detail', $parameters['include_item_detail']) == true)>Yes</option>
                                <option value="0" @selected(old('include_item_detail', $parameters['include_item_detail']) == false)>Package only</option>
                            </select>
                        </div>
                    </div>
                    <div class="ui-field"><label class="ui-label">Payment Terms</label><textarea class="ui-input" name="payment_terms" rows="3">{{ old('payment_terms', $parameters['payment_terms']) }}</textarea></div>
                    <div class="ui-field"><label class="ui-label">Delivery Terms</label><textarea class="ui-input" name="delivery_terms" rows="3">{{ old('delivery_terms', $parameters['delivery_terms']) }}</textarea></div>
                    <div class="ui-field"><label class="ui-label">Warranty Terms</label><textarea class="ui-input" name="warranty_terms" rows="3">{{ old('warranty_terms', $parameters['warranty_terms']) }}</textarea></div>
                    <div class="ui-field"><label class="ui-label">Exclusions</label><textarea class="ui-input" name="exclusions" rows="3">{{ old('exclusions', $parameters['exclusions']) }}</textarea></div>
                    <div class="ui-field"><label class="ui-label">Quotation Note</label><textarea class="ui-input" name="quotation_note" rows="3">{{ old('quotation_note', $parameters['quotation_note']) }}</textarea></div>
                    <button class="ui-button ui-button-primary" type="submit">Generate Quotation Draft</button>
                </form>
            @else
                <div class="quote-empty" style="margin-top:18px">You can view this quotation, but your role cannot generate or revise it.</div>
            @endif
        </aside>
    </div>
</div>
@endsection
