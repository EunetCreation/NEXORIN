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
    .bom-table{width:100%;min-width:920px;border-collapse:collapse;background:#fff}
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
    @media(max-width:1180px){.bom-layout{grid-template-columns:1fr}.bom-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.bom-hero{grid-template-columns:1fr;padding:26px}.bom-actions{justify-content:flex-start}.bom-grid{grid-template-columns:1fr}.bom-head{display:grid}}
</style>
@endpush

@section('content')
    @php
        $summary = $bom?->summary ?? [];
        $warnings = $bom?->warnings ?? [];
    @endphp

    <div class="bom-page">
        <section class="bom-hero">
            <div>
                <div class="bom-kicker">{{ $project->project_number }} · {{ $project->customer?->name }}</div>
                <h2>{{ $project->name }}</h2>
                <p>Generate a preliminary bill of materials and zero-priced BOQ draft from the latest technical calculation. Pricing will be added after supplier and cost modules are connected.</p>
            </div>
            <div class="bom-actions">
                <a href="{{ route('companies.boms.index', $company) }}" class="ui-button ui-button-secondary">BOM Directory</a>
                <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Technical Calculation</a>
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
                <strong>BOM/BOQ could not be generated.</strong>
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
            <article class="bom-stat"><span>Pricing</span><strong>{{ strtoupper(str_replace('_', ' ', $summary['pricing_status'] ?? 'not_priced')) }}</strong></article>
        </section>

        <div class="bom-layout">
            <main class="bom-card">
                <div class="bom-head">
                    <div>
                        <h3>Material and BOQ Draft</h3>
                        <p class="bom-card-subtitle">Quantities are generated from technical calculation outputs. Cost and selling prices are kept at zero until supplier pricing is connected.</p>
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
                                    <th>Category</th>
                                    <th>Item</th>
                                    <th>Specification</th>
                                    <th>Qty</th>
                                    <th>Unit</th>
                                    <th>Unit Cost</th>
                                    <th>Line Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bom->items as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ strtoupper(str_replace('_', ' ', $item->category)) }}</td>
                                        <td>
                                            <div class="bom-primary">{{ $item->item_name }}</div>
                                            <div class="bom-secondary">{{ $item->item_code ?? '—' }}</div>
                                            @if ($item->description)
                                                <div class="bom-secondary">{{ $item->description }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if (! empty($item->specification))
                                                <ul class="bom-spec">
                                                    @foreach ($item->specification as $key => $value)
                                                        <li><strong>{{ str_replace('_', ' ', $key) }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $item->quantity, 3) }}</td>
                                        <td>{{ $item->unit }}</td>
                                        <td>IDR {{ number_format((float) $item->unit_cost_idr, 0, ',', '.') }}</td>
                                        <td>IDR {{ number_format((float) $item->line_cost_idr, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </main>

            <aside class="bom-card">
                <h3>Source and Warnings</h3>
                <p class="bom-card-subtitle">
                    Generated from technical calculation status:
                    <strong>{{ $calculation?->result_status_label ?? 'Not calculated' }}</strong>.
                </p>

                <ul class="bom-warning-list" style="margin-top:18px">
                    @forelse ($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @empty
                        <li class="ok">No BOM warning recorded.</li>
                    @endforelse
                </ul>

                <div style="margin-top:20px;color:var(--slate-600);font-size:.9rem;line-height:1.6">
                    <div><strong>Generated by:</strong> {{ $bom?->generator?->name ?? '—' }}</div>
                    <div><strong>Generated at:</strong> {{ $bom?->generated_at?->format('d M Y, H:i') ?? '—' }}</div>
                    <div><strong>Calculation run:</strong> {{ $calculation?->calculated_at?->format('d M Y, H:i') ?? '—' }}</div>
                    <div><strong>Calculation verified:</strong> {{ $calculation?->verified_at?->format('d M Y, H:i') ?? 'Pending' }}</div>
                </div>
            </aside>
        </div>
    </div>
@endsection
