@extends('layouts.app')

@section('title', 'BOM/BOQ')
@section('page-eyebrow', 'Commercial engineering')
@section('page-heading', 'BOM/BOQ')

@push('styles')
<style>
    .bom-page{display:grid;gap:24px;min-width:0}
    .bom-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:24px;align-items:center;padding:32px 36px;border-radius:30px;background:linear-gradient(135deg,#021c2b 0%,#064255 58%,#0f6b72 100%);color:#fff;box-shadow:0 24px 55px rgba(4,45,58,.18)}
    .bom-kicker{font-size:.8rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#2dd4bf}
    .bom-hero h2{margin:10px 0 8px;font:700 clamp(2.1rem,4vw,3.8rem)/1.02 "Space Grotesk",sans-serif;letter-spacing:-.045em}
    .bom-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.72);font-size:1rem;line-height:1.65}
    .bom-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
    .bom-actions .ui-button-secondary{border-color:rgba(255,255,255,.24);background:rgba(255,255,255,.08);color:#fff}
    .bom-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(300px,.75fr);gap:24px;align-items:start;min-width:0}
    .bom-card{min-width:0;padding:24px;border:1px solid var(--slate-200);border-radius:24px;background:#fff;box-shadow:var(--shadow-sm)}
    .bom-card h3{margin:0;color:var(--navy-900);font-size:1.08rem}
    .bom-card-subtitle{margin:7px 0 0;color:var(--slate-500);line-height:1.55}
    .bom-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}
    .bom-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
    .bom-stat{padding:18px;border:1px solid var(--slate-200);border-radius:18px;background:var(--slate-50)}
    .bom-stat span{display:block;color:var(--slate-500);font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
    .bom-stat strong{display:block;margin-top:8px;color:var(--navy-900);font:700 1.35rem/1.1 "Space Grotesk",sans-serif}
    .bom-table-wrap{overflow-x:auto;border:1px solid var(--slate-200);border-radius:18px}
    .bom-table{width:100%;min-width:1100px;border-collapse:collapse;background:#fff}
    .bom-table th,.bom-table td{padding:13px 15px;border-bottom:1px solid var(--slate-200);text-align:left;vertical-align:top}
    .bom-table th{background:var(--slate-50);color:var(--slate-600);font-size:.75rem;text-transform:uppercase;letter-spacing:.05em}
    .bom-table tr:last-child td{border-bottom:0}
    .bom-primary{font-weight:800;color:var(--navy-900)}
    .bom-secondary{margin-top:4px;color:var(--slate-500);font-size:.82rem;line-height:1.4}
    .bom-spec{margin:0;padding:0;list-style:none;display:grid;gap:4px;color:var(--slate-600);font-size:.8rem}
    .bom-warning-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}
    .bom-warning-list li{padding:13px 14px;border:1px solid #fed7aa;border-radius:15px;background:#fff7ed;color:#9a3412;line-height:1.45}
    .bom-warning-list .ok{border-color:#99f6e4;background:#ecfdf9;color:#087a70}
    .bom-form{margin:0}
    .bom-pricing-form{display:grid;gap:14px;margin-top:18px}
    .bom-pricing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .bom-money-list{display:grid;gap:10px;margin:18px 0 0}
    .bom-money-row{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--slate-200);color:var(--slate-600)}
    .bom-money-row strong{color:var(--navy-900)}
    .bom-money-row.total{font-size:1.05rem;border-bottom:0;color:var(--navy-900)}
    .bom-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:.7rem;font-weight:800;letter-spacing:.02em;border:1px solid var(--slate-200);background:var(--slate-50);color:var(--slate-600);white-space:nowrap}
    .bom-badge.required{border-color:#bbf7d0;background:#f0fdf4;color:#166534}
    .bom-badge.conditional{border-color:#fde68a;background:#fffbeb;color:#92400e}
    .bom-badge.optional{border-color:#dbeafe;background:#eff6ff;color:#1d4ed8}
    .bom-badge.service{border-color:#ccfbf1;background:#f0fdfa;color:#0f766e}
    .bom-badge.excluded{border-color:#e5e7eb;background:#f9fafb;color:#475569}
    .bom-badge.review{border-color:#fecaca;background:#fef2f2;color:#991b1b}
    .bom-muted-note{display:block;margin-top:4px;color:var(--slate-500);font-size:.75rem;line-height:1.35}
    @media(max-width:1180px){.bom-layout{grid-template-columns:1fr}.bom-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.bom-hero{grid-template-columns:1fr;padding:26px}.bom-actions{justify-content:flex-start}.bom-grid,.bom-pricing-grid{grid-template-columns:1fr}.bom-head{display:grid}}
</style>
@endpush

@section('content')
    @php
        $summary = $bom?->summary ?? [];
        $warnings = $bom?->warnings ?? [];
        $pricingParameters = old()
            ?: ($bom?->pricing_parameters ?? $pricingDefaults);
        $money = fn ($value) => ((float) $value > 0 ? 'IDR '.number_format((float) $value, 0, ',', '.') : 'Pending');
        $moneyZero = fn ($value) => 'IDR '.number_format((float) $value, 0, ',', '.');
        $label = \App\Support\BomBoqDictionary::class;
    @endphp

    <div class="bom-page">
        <section class="bom-hero">
            <div>
                <div class="bom-kicker">{{ $project->project_number }} · {{ $project->customer?->name }}</div>
                <h2>{{ $project->name }}</h2>
                <p>Build a complete project material, service, scope, and pricing draft from the latest engineering calculation. The BOQ separates customer cost, service scope, optional items, and utility responsibility.</p>
            </div>
            <div class="bom-actions">
                <a href="{{ route('companies.boms.index', $company) }}" class="ui-button ui-button-secondary">BOQ Directory</a>
                <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Technical Calculation</a>
                <a href="{{ route('companies.financial-analyses.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Financial Analysis</a>
                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Project Details</a>
            </div>
        </section>

        @if (session('success'))
            <div class="ui-alert ui-alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="ui-alert ui-alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="ui-alert ui-alert-danger">
                <strong>BOM/BOQ could not be processed.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="bom-grid" aria-label="BOM summary">
            <article class="bom-stat"><span>BOM Number</span><strong>{{ $bom?->bom_number ?? 'Not generated' }}</strong></article>
            <article class="bom-stat"><span>PV Capacity</span><strong>{{ number_format((float) ($summary['installed_pv_kw'] ?? 0), 2) }} kWp</strong></article>
            <article class="bom-stat"><span>Items</span><strong>{{ number_format((int) ($summary['item_count'] ?? 0)) }}</strong></article>
            <article class="bom-stat"><span>Grand Total</span><strong>{{ $money($summary['grand_price_idr'] ?? $bom?->grand_price_idr ?? 0) }}</strong></article>
        </section>

        <div class="bom-layout">
            <main class="bom-card">
                <div class="bom-head">
                    <div>
                        <h3>Professional BOM/BOQ Draft</h3>
                        <p class="bom-card-subtitle">Quantities come from engineering inputs. The draft includes main equipment, small accessories, protection, grounding, services, permits, logistics, and scope notes.</p>
                    </div>

                    @if ($canGenerate)
                        <form class="bom-form" method="POST" action="{{ route('companies.boms.generate', [$company, $project]) }}">
                            @csrf
                            <button type="submit" class="ui-button ui-button-primary">
                                {{ $bom ? 'Regenerate BOM/BOQ' : 'Generate BOM/BOQ' }}
                            </button>
                        </form>
                    @endif
                </div>

                @if (! $calculation?->calculated_at)
                    <div class="ui-empty">
                        <div>
                            <h3>Technical calculation required</h3>
                            <p>Run technical calculation before generating BOM/BOQ.</p>
                        </div>
                    </div>
                @elseif (! $bom)
                    <div class="ui-empty">
                        <div>
                            <h3>No BOM/BOQ draft yet</h3>
                            <p>Generate a draft from the latest technical calculation result.</p>
                        </div>
                    </div>
                @else
                    <div class="bom-table-wrap">
                        <table class="bom-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Cost Group</th>
                                    <th>Item</th>
                                    <th>Scope</th>
                                    <th>Specification</th>
                                    <th>Qty</th>
                                    <th>Unit</th>
                                    @if ($canViewCost)
                                        <th>Unit Cost</th>
                                        <th>Line Cost</th>
                                    @endif
                                    @if ($canViewSelling)
                                        <th>Unit Price</th>
                                        <th>Line Price</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bom->items as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        @php
                                            $metadata = $item->metadata ?? [];
                                            $scopeStatus = $metadata['scope_status'] ?? 'required';
                                            $costGroup = $metadata['cost_group'] ?? $item->category;
                                            $pricingState = $metadata['pricing_state'] ?? ($item->unit_price_idr > 0 ? 'priced' : 'pending');
                                            $priceTrace = $metadata['pricing_source_trace'] ?? [];
                                        @endphp
                                        <td>
                                            <div class="bom-primary">{{ $label::costGroupLabel($costGroup) }}</div>
                                            <div class="bom-secondary">{{ $label::categoryLabel($item->category) }}</div>
                                        </td>
                                        <td>
                                            <div class="bom-primary">{{ $item->item_name }}</div>
                                            @if ($item->description)
                                                <div class="bom-secondary">{{ $item->description }}</div>
                                            @endif
                                            @if (! empty($metadata['calculation_basis']))
                                                <span class="bom-muted-note">Basis: {{ $metadata['calculation_basis'] }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="bom-badge {{ $label::scopeTone($scopeStatus) }}">{{ $label::scopeLabel($scopeStatus) }}</span>
                                            <span class="bom-muted-note">{{ $label::pricingStateLabel($pricingState) }}</span>
                                            @if (! empty($priceTrace['price_book_code']))
                                                <span class="bom-muted-note">Price source: {{ $priceTrace['price_book_code'] }} · {{ $priceTrace['supplier_name'] ?? 'Supplier not set' }}</span>
                                            @elseif (($metadata['pricing_source'] ?? null) === 'manual_fallback')
                                                <span class="bom-muted-note">Price source: manual fallback</span>
                                            @elseif (($metadata['pricing_source'] ?? null) === 'missing_price')
                                                <span class="bom-muted-note">Price source: missing</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (! empty($item->specification))
                                                <ul class="bom-spec">
                                                    @foreach ($item->specification as $key => $value)
                                                        <li><strong>{{ $label::specLabel($key) }}:</strong> {{ $label::value($value) }}</li>
                                                    @endforeach
                                                    @if (isset($metadata['waste_factor_percent']) && (float) $metadata['waste_factor_percent'] > 0)
                                                        <li><strong>Material allowance:</strong> {{ number_format((float) $metadata['waste_factor_percent'], 1) }}%</li>
                                                    @endif
                                                </ul>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $item->quantity, 3) }}</td>
                                        <td>{{ $item->unit }}</td>
                                        @if ($canViewCost)
                                            <td>{{ $money($item->unit_cost_idr) }}</td>
                                            <td>{{ $money($item->line_cost_idr) }}</td>
                                        @endif
                                        @if ($canViewSelling)
                                            <td>{{ $money($item->unit_price_idr) }}</td>
                                            <td>{{ $money($item->line_price_idr) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </main>

            <aside class="bom-card">
                <h3>BOQ Pricing</h3>
                <p class="bom-card-subtitle">Apply company price books first. The engine keeps source trace, supplier validity, margin guard, discount guard, tax mode, and customer-safe selling totals for audit.</p>

                @if ($bom)
                    <div class="bom-money-list">
                        <div class="bom-money-row"><span>Pricing Status</span><strong>{{ $label::pricingStateLabel($summary['pricing_status'] ?? $bom->pricing_status) }}</strong></div>
                        <div class="bom-money-row"><span>Commercial Guard</span><strong>Min margin {{ number_format((float) ($summary['min_margin_percent'] ?? $pricingParameters['min_margin_percent'] ?? 10), 2) }}% · Max discount {{ number_format((float) ($summary['max_discount_percent'] ?? $pricingParameters['max_discount_percent'] ?? 10), 2) }}%</strong></div>
                        @if ($canViewCost)
                            <div class="bom-money-row"><span>Material Cost</span><strong>{{ $money($bom->material_cost_subtotal_idr) }}</strong></div>
                            <div class="bom-money-row"><span>Company Price Book Items</span><strong>{{ number_format((int) ($summary['company_price_book_item_count'] ?? 0)) }}</strong></div>
                            <div class="bom-money-row"><span>Manual Fallback Items</span><strong>{{ number_format((int) ($summary['manual_fallback_item_count'] ?? 0)) }}</strong></div>
                            <div class="bom-money-row"><span>Missing / Review Items</span><strong>{{ number_format((int) ($summary['missing_price_item_count'] ?? 0) + (int) ($summary['expired_price_item_count'] ?? 0) + (int) ($summary['unavailable_price_item_count'] ?? 0) + (int) ($summary['low_margin_item_count'] ?? 0) + (int) ($summary['discount_limit_exceeded_count'] ?? 0)) }}</strong></div>
                        @endif
                        @if ($canViewSelling)
                            <div class="bom-money-row"><span>Material Price</span><strong>{{ $money($bom->material_price_subtotal_idr) }}</strong></div>
                            <div class="bom-money-row"><span>Commercial Subtotal</span><strong>{{ $money($summary['commercial_subtotal_idr'] ?? 0) }}</strong></div>
                            <div class="bom-money-row"><span>Item Discount</span><strong>{{ $moneyZero($summary['item_discount_idr'] ?? 0) }}</strong></div>
                            <div class="bom-money-row"><span>Labor</span><strong>{{ $money($bom->labor_cost_idr) }}</strong></div>
                            <div class="bom-money-row"><span>Logistics</span><strong>{{ $money($bom->logistics_cost_idr) }}</strong></div>
                            <div class="bom-money-row"><span>Other Cost</span><strong>{{ $money($bom->other_cost_idr) }}</strong></div>
                            <div class="bom-money-row"><span>Contingency</span><strong>{{ $money($bom->contingency_cost_idr) }}</strong></div>
                            <div class="bom-money-row"><span>Tax</span><strong>{{ $money($bom->tax_idr) }} · {{ ucfirst($summary['tax_mode'] ?? $pricingParameters['tax_mode'] ?? 'exclusive') }}</strong></div>
                            <div class="bom-money-row"><span>Global Discount</span><strong>{{ $moneyZero($summary['global_discount_idr'] ?? 0) }}</strong></div>
                            <div class="bom-money-row"><span>Total Discount</span><strong>{{ $moneyZero($bom->discount_idr) }}</strong></div>
                            <div class="bom-money-row"><span>Rounding</span><strong>{{ $moneyZero($summary['rounding_adjustment_idr'] ?? 0) }}</strong></div>
                            <div class="bom-money-row total"><span>Grand Total</span><strong>{{ $money($bom->grand_price_idr) }}</strong></div>
                        @endif
                        @if ($canViewCost && $canViewSelling)
                            <div class="bom-money-row"><span>Gross Margin</span><strong>{{ $money($bom->gross_margin_idr) }} · {{ number_format((float) $bom->gross_margin_percent, 2) }}%</strong></div>
                        @endif
                    </div>
                @endif

                @if ($canPrice)
                    <form class="bom-pricing-form" method="POST" action="{{ route('companies.boms.price', [$company, $project]) }}">
                        @csrf
                        <div class="bom-pricing-grid">
                            <div class="ui-field">
                                <label class="ui-label">Material Markup %</label>
                                <input class="ui-input" type="number" step="0.01" min="0" max="200" name="material_markup_percent" value="{{ $pricingParameters['material_markup_percent'] ?? 15 }}">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Minimum Margin %</label>
                                <input class="ui-input" type="number" step="0.01" min="0" max="100" name="min_margin_percent" value="{{ $pricingParameters['min_margin_percent'] ?? 10 }}">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Max Discount %</label>
                                <input class="ui-input" type="number" step="0.01" min="0" max="100" name="max_discount_percent" value="{{ $pricingParameters['max_discount_percent'] ?? 10 }}">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Labor Rate per kWp</label>
                                <input class="ui-input" type="number" step="1" min="0" name="labor_rate_per_kwp_idr" value="{{ $pricingParameters['labor_rate_per_kwp_idr'] ?? 850000 }}">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Logistics Cost</label>
                                <input class="ui-input" type="number" step="1" min="0" name="logistics_cost_idr" value="{{ $pricingParameters['logistics_cost_idr'] ?? 1500000 }}">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Other Cost</label>
                                <input class="ui-input" type="number" step="1" min="0" name="other_cost_idr" value="{{ $pricingParameters['other_cost_idr'] ?? 0 }}">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Contingency %</label>
                                <input class="ui-input" type="number" step="0.01" min="0" max="100" name="contingency_percent" value="{{ $pricingParameters['contingency_percent'] ?? 3 }}">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Tax %</label>
                                <input class="ui-input" type="number" step="0.01" min="0" max="100" name="tax_percent" value="{{ $pricingParameters['tax_percent'] ?? 11 }}">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Tax Mode</label>
                                <select class="ui-select" name="tax_mode">
                                    <option value="exclusive" @selected(($pricingParameters['tax_mode'] ?? 'exclusive') === 'exclusive')>Tax exclusive</option>
                                    <option value="inclusive" @selected(($pricingParameters['tax_mode'] ?? 'exclusive') === 'inclusive')>Tax inclusive</option>
                                </select>
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Rounding Unit</label>
                                <select class="ui-select" name="rounding_unit_idr">
                                    @foreach ([1, 100, 1000, 10000] as $roundingUnit)
                                        <option value="{{ $roundingUnit }}" @selected((float) ($pricingParameters['rounding_unit_idr'] ?? 1) === (float) $roundingUnit)>IDR {{ number_format($roundingUnit, 0, ',', '.') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ui-field">
                                <label class="ui-label">Discount %</label>
                                <input class="ui-input" type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ $pricingParameters['discount_percent'] ?? 0 }}">
                            </div>
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Tax Exempt Item Codes</label>
                            <input class="ui-input" type="text" name="tax_exempt_item_codes" value="{{ is_array($pricingParameters['tax_exempt_item_codes'] ?? null) ? implode(', ', $pricingParameters['tax_exempt_item_codes']) : ($pricingParameters['tax_exempt_item_codes'] ?? '') }}" placeholder="Optional, comma separated item codes">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label">Pricing Note</label>
                            <textarea class="ui-input" name="pricing_note" rows="3" placeholder="Optional commercial note for internal review or quotation context">{{ $pricingParameters['pricing_note'] ?? '' }}</textarea>
                        </div>
                        <button class="ui-button ui-button-primary" type="submit">Calculate BOQ Price</button>
                    </form>
                @elseif ($bom)
                    <p class="bom-card-subtitle" style="margin-top:16px">You can view this draft, but your role cannot update BOQ pricing.</p>
                @endif
            </aside>
        </div>

        <aside class="bom-card">
            <h3>Project Checks</h3>
            <p class="bom-card-subtitle">
                Generated from engineering status:
                <strong>{{ $calculation?->result_status_label ?? 'Not calculated' }}</strong>.
            </p>

            <ul class="bom-warning-list" style="margin-top:18px">
                @forelse ($warnings as $warning)
                    <li>{{ is_array($warning) ? ($warning['message'] ?? $warning['title'] ?? json_encode($warning)) : $warning }}</li>
                @empty
                    <li class="ok">No BOM/BOQ issue recorded.</li>
                @endforelse
            </ul>

            <div style="margin-top:20px;color:var(--slate-600);font-size:.9rem;line-height:1.6">
                <div><strong>Generated by:</strong> {{ $bom?->generator?->name ?? '—' }}</div>
                <div><strong>Generated at:</strong> {{ $bom?->generated_at?->format('d M Y, H:i') ?? '—' }}</div>
                <div><strong>Priced by:</strong> {{ $bom?->pricer?->name ?? '—' }}</div>
                <div><strong>Priced at:</strong> {{ $bom?->priced_at?->format('d M Y, H:i') ?? 'Not priced' }}</div>
                <div><strong>Engineering calculation:</strong> {{ $calculation?->calculated_at?->format('d M Y, H:i') ?? '—' }}</div>
                <div><strong>Engineering verified:</strong> {{ $calculation?->verified_at?->format('d M Y, H:i') ?? 'Pending' }}</div>
            </div>
        </aside>
    </div>
@endsection
