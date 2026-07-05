@extends('layouts.app')

@section('title', $project->name)
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Project Cockpit')

@push('styles')
<style>
    .cockpit-page{display:grid;gap:24px;min-width:0}
    .cockpit-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:24px;align-items:end;padding:34px 36px;border-radius:30px;color:#fff;background:radial-gradient(circle at 88% 8%,rgba(52,214,203,.24),transparent 30%),linear-gradient(135deg,#031522 0%,#07334d 56%,#0d6a70 100%);box-shadow:var(--shadow-lg)}
    .cockpit-hero:after{content:"";position:absolute;right:-80px;bottom:-120px;width:320px;height:320px;border-radius:50%;background:rgba(255,255,255,.08)}
    .cockpit-hero-main,.cockpit-hero-side{position:relative;z-index:1}
    .cockpit-kicker{display:inline-flex;gap:8px;align-items:center;color:#61efe6;font-size:.72rem;font-weight:850;letter-spacing:.13em;text-transform:uppercase}
    .cockpit-title{max-width:900px;margin:10px 0 9px;font:700 clamp(2.2rem,4vw,4.2rem)/.98 "Space Grotesk",sans-serif;letter-spacing:-.055em}
    .cockpit-subtitle{max-width:820px;margin:0;color:rgba(255,255,255,.72);font-size:.98rem;line-height:1.65}
    .cockpit-hero-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}
    .cockpit-hero-side{min-width:260px;display:grid;gap:12px;justify-items:end;text-align:right}
    .cockpit-health{padding:15px 16px;border:1px solid rgba(255,255,255,.16);border-radius:18px;background:rgba(255,255,255,.10);backdrop-filter:blur(10px)}
    .cockpit-health span{display:block;color:rgba(255,255,255,.64);font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}
    .cockpit-health strong{display:block;margin-top:5px;font:700 1.05rem/1.2 "Space Grotesk",sans-serif}
    .cockpit-progress-shell{width:min(360px,100%);height:10px;overflow:hidden;border-radius:999px;background:rgba(255,255,255,.16)}
    .cockpit-progress-bar{height:100%;border-radius:inherit;background:linear-gradient(90deg,#34d6cb,#b4fff7)}
    .cockpit-progress-note{color:rgba(255,255,255,.72);font-size:.8rem}
    .cockpit-actions{display:flex;flex-wrap:wrap;gap:9px;justify-content:flex-end}.cockpit-actions .ui-button-secondary{color:#fff;border-color:rgba(255,255,255,.18);background:rgba(255,255,255,.09)}
    .cockpit-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(360px,.75fr);gap:24px;align-items:start;min-width:0}
    .cockpit-main{display:grid;gap:24px;min-width:0}.cockpit-side{display:grid;gap:24px;min-width:0}
    .cockpit-card{min-width:0;border:1px solid rgba(7,40,61,.08);border-radius:26px;background:rgba(255,255,255,.94);box-shadow:var(--shadow-sm)}
    .cockpit-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:24px 24px 0}.cockpit-card-head h3{margin:0;color:var(--navy-900);font:700 1.18rem/1.2 "Space Grotesk",sans-serif;letter-spacing:-.035em}.cockpit-card-head p{margin:7px 0 0;color:var(--slate-500);font-size:.82rem;line-height:1.55}.cockpit-card-body{padding:22px 24px 24px}
    .cockpit-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;font-size:.68rem;font-weight:850;letter-spacing:.045em;text-transform:uppercase;background:var(--slate-100);color:var(--slate-600)}
    .cockpit-chip:before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}.cockpit-chip.ready{color:#087f77;background:var(--teal-100)}.cockpit-chip.progress{color:#315d82;background:#e8f3fb}.cockpit-chip.warning{color:#9a6a09;background:#fff4d1}.cockpit-chip.critical{color:#9b2d3d;background:#fff0f2}.cockpit-chip.info{color:#566c7d;background:var(--slate-100)}
    .cockpit-workflow{display:grid;grid-template-columns:repeat(9,minmax(0,1fr));gap:10px}.cockpit-step{position:relative;min-width:0;padding:14px 10px;border:1px solid var(--slate-100);border-radius:18px;background:var(--slate-50);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}.cockpit-step:hover{transform:translateY(-2px);border-color:rgba(22,190,179,.26);box-shadow:0 14px 28px rgba(3,21,34,.07)}
    .cockpit-step-index{width:30px;height:30px;display:grid;place-items:center;margin-bottom:10px;border-radius:12px;color:var(--slate-500);font-size:.75rem;font-weight:900;background:var(--slate-200)}.cockpit-step strong{display:block;overflow:hidden;color:var(--slate-800);font-size:.78rem;text-overflow:ellipsis;white-space:nowrap}.cockpit-step small{display:block;margin-top:4px;color:var(--slate-500);font-size:.66rem;line-height:1.35}.cockpit-step em{display:block;margin-top:9px;color:var(--slate-600);font-size:.68rem;font-style:normal;font-weight:750;line-height:1.3}
    .cockpit-step.complete{background:rgba(223,248,245,.72);border-color:rgba(22,190,179,.18)}.cockpit-step.complete .cockpit-step-index{color:#fff;background:var(--teal-500)}.cockpit-step.locked{background:#e2f6ea;border-color:#b7edd0}.cockpit-step.locked .cockpit-step-index{color:#fff;background:#3a9a64}.cockpit-step.current{background:#e8f3fb;border-color:#bfe1f6}.cockpit-step.current .cockpit-step-index{color:#fff;background:#4a90c5}.cockpit-step.warning{background:#fff7df;border-color:#f6d98b}.cockpit-step.warning .cockpit-step-index{color:#4d3904;background:#f5c542}.cockpit-step.missing{opacity:.78}
    .cockpit-summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.cockpit-summary-card{min-width:0;padding:18px;border:1px solid var(--slate-100);border-radius:20px;background:linear-gradient(180deg,#fff,var(--slate-50))}.cockpit-summary-card h4{margin:0 0 14px;color:var(--navy-900);font:700 1rem/1.2 "Space Grotesk",sans-serif}.cockpit-kv{display:grid;gap:9px}.cockpit-kv-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding-bottom:9px;border-bottom:1px solid var(--slate-100)}.cockpit-kv-row:last-child{padding-bottom:0;border-bottom:0}.cockpit-kv-row span{color:var(--slate-500);font-size:.72rem;font-weight:750}.cockpit-kv-row strong{color:var(--slate-800);font-size:.78rem;text-align:right;overflow-wrap:anywhere}.cockpit-kv-row.highlight strong{color:#087f77;font-size:.9rem}
    .cockpit-warning-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}.cockpit-warning-count{padding:14px;border-radius:17px;border:1px solid var(--slate-100);background:var(--slate-50)}.cockpit-warning-count span{display:block;color:var(--slate-500);font-size:.65rem;font-weight:850;text-transform:uppercase;letter-spacing:.06em}.cockpit-warning-count strong{display:block;margin-top:6px;color:var(--navy-900);font:700 1.35rem/1 "Space Grotesk",sans-serif}.cockpit-warning-count.critical{background:#fff0f2;border-color:#ffd2d9}.cockpit-warning-count.warning{background:#fff7df;border-color:#f4dc9b}.cockpit-warning-count.caution{background:#fff9ec;border-color:#f4e5b7}.cockpit-warning-count.info{background:#eef6fb;border-color:#d4eaf7}
    .cockpit-warning-list{display:grid;gap:10px;max-height:420px;overflow:auto;padding-right:4px}.cockpit-warning-item{padding:14px;border:1px solid var(--slate-100);border-left-width:4px;border-radius:16px;background:#fff}.cockpit-warning-item.critical{border-left-color:#be3345}.cockpit-warning-item.warning{border-left-color:#f5a800}.cockpit-warning-item.caution{border-left-color:#f1c75b}.cockpit-warning-item.info{border-left-color:#4a90c5}.cockpit-warning-item header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.cockpit-warning-item strong{color:var(--navy-900);font-size:.82rem}.cockpit-warning-item p{margin:7px 0 0;color:var(--slate-600);font-size:.75rem;line-height:1.55}.cockpit-warning-item small{display:block;margin-top:8px;color:var(--slate-400);font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
    .cockpit-action-list{display:grid;gap:10px}.cockpit-action{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:14px;border:1px solid var(--slate-100);border-radius:17px;text-decoration:none;background:var(--slate-50);transition:transform .18s ease,border-color .18s ease,background .18s ease}.cockpit-action:hover{transform:translateY(-1px);border-color:rgba(22,190,179,.28);background:#fff}.cockpit-action strong{display:block;color:var(--navy-900);font-size:.82rem}.cockpit-action span{display:block;margin-top:4px;color:var(--slate-500);font-size:.72rem;line-height:1.35}.cockpit-action b{color:var(--teal-600);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em}
    .cockpit-next{padding:16px;border:1px solid rgba(22,190,179,.22);border-radius:18px;background:var(--teal-100)}.cockpit-next span{display:block;color:#087f77;font-size:.66rem;font-weight:850;text-transform:uppercase;letter-spacing:.07em}.cockpit-next strong{display:block;margin-top:6px;color:var(--navy-900);font-size:.95rem}.cockpit-next p{margin:6px 0 0;color:var(--slate-700);font-size:.75rem;line-height:1.5}
    .cockpit-profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;overflow:hidden;border:1px solid var(--slate-100);border-radius:18px;background:var(--slate-100)}.cockpit-profile-item{min-height:86px;padding:15px;background:#fff}.cockpit-profile-item.wide{grid-column:span 2}.cockpit-profile-item dt{color:var(--slate-500);font-size:.64rem;font-weight:850;text-transform:uppercase;letter-spacing:.07em}.cockpit-profile-item dd{margin:8px 0 0;color:var(--slate-800);font-size:.8rem;font-weight:650;line-height:1.55;overflow-wrap:anywhere}

    .cockpit-insight-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px}.cockpit-insight{position:relative;overflow:hidden;min-height:130px;padding:16px;border:1px solid var(--slate-100);border-radius:20px;background:linear-gradient(180deg,#fff,var(--slate-50));transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}.cockpit-insight:hover{transform:translateY(-2px);border-color:rgba(22,190,179,.24);box-shadow:0 16px 30px rgba(3,21,34,.08)}.cockpit-insight:before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:var(--slate-300)}.cockpit-insight.ready:before{background:var(--teal-500)}.cockpit-insight.progress:before{background:#4a90c5}.cockpit-insight.info:before{background:#7aa3bd}.cockpit-insight.caution:before{background:#f1c75b}.cockpit-insight.warning:before{background:#f5a800}.cockpit-insight.critical:before{background:#be3345}.cockpit-insight.muted{opacity:.72}.cockpit-insight b{display:inline-flex;margin-bottom:12px;padding:6px 9px;border-radius:999px;background:var(--slate-100);color:var(--slate-700);font-size:.64rem;text-transform:uppercase;letter-spacing:.065em}.cockpit-insight h4{margin:0;color:var(--navy-900);font:700 .9rem/1.25 "Space Grotesk",sans-serif}.cockpit-insight p{margin:7px 0 0;color:var(--slate-600);font-size:.73rem;line-height:1.5}.cockpit-chart-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:16px}.cockpit-chart-panel{min-width:0;padding:18px;border:1px solid var(--slate-100);border-radius:22px;background:#fff}.cockpit-chart-panel h4{margin:0;color:var(--navy-900);font:700 .96rem/1.2 "Space Grotesk",sans-serif}.cockpit-chart-panel p{margin:6px 0 0;color:var(--slate-500);font-size:.74rem;line-height:1.5}.cockpit-cashflow{display:grid;gap:10px;margin-top:16px}.cockpit-cashflow-row{display:grid;grid-template-columns:42px minmax(0,1fr) 122px;gap:12px;align-items:center}.cockpit-cashflow-row span{color:var(--slate-500);font-size:.68rem;font-weight:800}.cockpit-cashflow-bars{display:grid;gap:5px}.cockpit-bar-track{height:9px;overflow:hidden;border-radius:999px;background:var(--slate-100)}.cockpit-bar{height:100%;min-width:4px;border-radius:inherit;background:linear-gradient(90deg,var(--teal-500),#9df4ec)}.cockpit-bar.net{background:linear-gradient(90deg,#4a90c5,#9ed4fb)}.cockpit-bar.cumulative{background:linear-gradient(90deg,#2f9f69,#b8efcd)}.cockpit-cashflow-row strong{color:var(--slate-800);font-size:.68rem;text-align:right}.cockpit-gauge-list{display:grid;gap:14px;margin-top:16px}.cockpit-gauge-head{display:flex;justify-content:space-between;gap:12px;align-items:center}.cockpit-gauge-head strong{color:var(--navy-900);font-size:.78rem}.cockpit-gauge-head span{color:var(--teal-600);font-size:.78rem;font-weight:850}.cockpit-gauge-track{height:10px;overflow:hidden;border-radius:999px;background:var(--slate-100)}.cockpit-gauge-fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--teal-500),#9df4ec)}.cockpit-gauge.ready .cockpit-gauge-fill{background:linear-gradient(90deg,var(--teal-500),#9df4ec)}.cockpit-gauge.caution .cockpit-gauge-fill,.cockpit-gauge.warning .cockpit-gauge-fill{background:linear-gradient(90deg,#f5a800,#ffe29a)}.cockpit-gauge.critical .cockpit-gauge-fill{background:linear-gradient(90deg,#be3345,#ff9fac)}.cockpit-gauge.muted .cockpit-gauge-fill{background:var(--slate-300)}.cockpit-gauge p{margin:6px 0 0;color:var(--slate-500);font-size:.68rem;line-height:1.45}.cockpit-energy-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px}.cockpit-energy-pill{padding:13px;border-radius:16px;background:var(--slate-50);border:1px solid var(--slate-100)}.cockpit-energy-pill span{display:block;color:var(--slate-500);font-size:.64rem;font-weight:850;text-transform:uppercase;letter-spacing:.06em}.cockpit-energy-pill strong{display:block;margin-top:7px;color:var(--navy-900);font-size:.82rem}.cockpit-animated{animation:cockpitFloat 520ms ease both}@keyframes cockpitFloat{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:1280px){.cockpit-layout{grid-template-columns:1fr}.cockpit-workflow{grid-template-columns:repeat(3,minmax(0,1fr))}.cockpit-summary-grid,.cockpit-insight-grid{grid-template-columns:1fr}.cockpit-chart-grid{grid-template-columns:1fr}}
    @media(max-width:760px){.cockpit-hero{grid-template-columns:1fr;padding:26px}.cockpit-hero-side{justify-items:start;text-align:left}.cockpit-actions{justify-content:flex-start}.cockpit-workflow{grid-template-columns:repeat(2,minmax(0,1fr))}.cockpit-warning-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cockpit-profile-grid{grid-template-columns:1fr}.cockpit-profile-item.wide{grid-column:auto}.cockpit-card-head{display:grid}.cockpit-cashflow-row{grid-template-columns:34px minmax(0,1fr)}.cockpit-cashflow-row strong{grid-column:2;text-align:left}.cockpit-energy-strip{grid-template-columns:1fr}}
    @media(max-width:520px){.cockpit-workflow,.cockpit-warning-grid{grid-template-columns:1fr}.cockpit-card-head,.cockpit-card-body{padding-left:18px;padding-right:18px}}
</style>
@endpush

@section('content')
    @php
        $engineering = $cockpit['engineering_summary'];
        $commercial = $cockpit['commercial_summary'];
        $financial = $cockpit['financial_summary'];
        $health = $cockpit['health'];
        $nextStep = $cockpit['next_step'];
        $warningCounts = $cockpit['warning_counts'];
        $warningRecords = $cockpit['warning_records'];
        $charts = $cockpit['charts'];
        $insights = $cockpit['insights'];
        $workflowStateLabels = [
            'complete' => 'Complete',
            'current' => 'In progress',
            'missing' => 'Missing',
            'warning' => 'Review',
            'locked' => 'Locked',
        ];
    @endphp

    <div class="cockpit-page">
        <section class="cockpit-hero">
            <div class="cockpit-hero-main">
                <div class="cockpit-kicker">{{ $project->project_number }} · {{ $project->customer->name }}</div>
                <h2 class="cockpit-title">{{ $project->name }}</h2>
                <p class="cockpit-subtitle">
                    Project cockpit for engineering, commercial, financial, warning, override, and report readiness in one professional workflow view.
                </p>
                <div class="cockpit-hero-badges">
                    <span class="ui-status ui-status-project-{{ $project->status }}">{{ $project->status_label }}</span>
                    <span class="cockpit-chip {{ $health['state'] }}">{{ $health['label'] }}</span>
                    @if ($engineering['has_overrides'])
                        <span class="cockpit-chip info">{{ $engineering['override_count'] }} active override{{ $engineering['override_count'] === 1 ? '' : 's' }}</span>
                    @endif
                    @if ($project->isArchived())
                        <span class="cockpit-chip critical">Archived</span>
                    @endif
                </div>
            </div>

            <div class="cockpit-hero-side">
                <div class="cockpit-health">
                    <span>Workflow readiness</span>
                    <strong>{{ $cockpit['completion_percent'] }}%</strong>
                    <div class="cockpit-progress-shell" aria-label="Workflow readiness">
                        <div class="cockpit-progress-bar" style="width: {{ $cockpit['completion_percent'] }}%"></div>
                    </div>
                    <div class="cockpit-progress-note">
                        {{ $cockpit['completed_steps'] }} of {{ $cockpit['total_steps'] }} modules complete
                    </div>
                </div>

                <div class="cockpit-actions">
                    <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Project Directory</a>
                    @if ($canUpdateProject && ! $project->isArchived())
                        <a href="{{ route('companies.projects.edit', [$company, $project]) }}" class="ui-button ui-button-primary">Edit Project</a>
                    @endif
                </div>
            </div>
        </section>

        <section class="cockpit-card">
            <div class="cockpit-card-head">
                <div>
                    <h3>Workflow command track</h3>
                    <p>Follow the project from customer data to quotation and report readiness.</p>
                </div>
                <span class="cockpit-chip {{ $health['state'] }}">{{ $health['description'] }}</span>
            </div>
            <div class="cockpit-card-body">
                <div class="cockpit-workflow" aria-label="Project workflow modules">
                    @foreach ($cockpit['steps'] as $index => $step)
                        <article class="cockpit-step {{ $step['state'] }}">
                            <span class="cockpit-step-index">{{ $index + 1 }}</span>
                            <strong>{{ $step['label'] }}</strong>
                            <small>{{ $step['caption'] }}</small>
                            <em>{{ $workflowStateLabels[$step['state']] ?? ucfirst($step['state']) }} · {{ $step['metric'] }}</em>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="cockpit-layout">
            <main class="cockpit-main">
                <section class="cockpit-summary-grid" aria-label="Project cockpit summaries">
                    <article class="cockpit-summary-card">
                        <h4>Engineering summary</h4>
                        <div class="cockpit-kv">
                            <div class="cockpit-kv-row highlight"><span>Installed PV</span><strong>{{ $engineering['installed_pv_kw'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Required PV</span><strong>{{ $engineering['required_pv_kw'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>System Type</span><strong>{{ $engineering['system_type'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Modules</span><strong>{{ $engineering['module_count'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Inverter</span><strong>{{ $engineering['inverter_count'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>DC/AC Ratio</span><strong>{{ $engineering['dc_ac_ratio'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Annual Energy</span><strong>{{ $engineering['annual_energy_kwh'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Status</span><strong>{{ $engineering['status'] }}</strong></div>
                        </div>
                    </article>

                    <article class="cockpit-summary-card">
                        <h4>Commercial summary</h4>
                        <div class="cockpit-kv">
                            <div class="cockpit-kv-row highlight"><span>Grand Price</span><strong>{{ $commercial['grand_price'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>BOM Status</span><strong>{{ $commercial['bom_status'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Pricing Status</span><strong>{{ $commercial['pricing_status'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Material</span><strong>{{ $commercial['material_price'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Labor</span><strong>{{ $commercial['labor_cost'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Tax</span><strong>{{ $commercial['tax'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Discount</span><strong>{{ $commercial['discount'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Quotation</span><strong>{{ $commercial['quotation_status'] }}</strong></div>
                        </div>
                    </article>

                    <article class="cockpit-summary-card">
                        <h4>Financial summary</h4>
                        <div class="cockpit-kv">
                            <div class="cockpit-kv-row highlight"><span>Payback</span><strong>{{ $financial['payback'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Status</span><strong>{{ $financial['status'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>CAPEX</span><strong>{{ $financial['capex'] }}</strong></div>
                            <div class="cockpit-kv-row {{ $financial['capex_attention'] ? 'highlight' : '' }}"><span>CAPEX Source</span><strong>{{ $financial['capex_source'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>First Year Saving</span><strong>{{ $financial['first_year_saving'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>Lifetime ROI</span><strong>{{ $financial['roi'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>NPV</span><strong>{{ $financial['npv'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>IRR</span><strong>{{ $financial['irr'] }}</strong></div>
                            <div class="cockpit-kv-row"><span>LCOE</span><strong>{{ $financial['lcoe'] }}</strong></div>
                        </div>
                    </article>
                </section>

                <section class="cockpit-card cockpit-animated">
                    <div class="cockpit-card-head">
                        <div>
                            <h3>Performance insight board</h3>
                            <p>Financial cashflow, technical gauges, energy yield, and decision signals are summarized here.</p>
                        </div>
                        <span class="cockpit-chip {{ $health['state'] }}">{{ count($insights) }} insight{{ count($insights) === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="cockpit-card-body">
                        <div class="cockpit-insight-grid">
                            @foreach ($insights as $insight)
                                <article class="cockpit-insight {{ $insight['state'] }}">
                                    <b>{{ $insight['metric'] }}</b>
                                    <h4>{{ $insight['title'] }}</h4>
                                    <p>{{ $insight['message'] }}</p>
                                </article>
                            @endforeach
                        </div>

                        <div class="cockpit-chart-grid">
                            <article class="cockpit-chart-panel">
                                <h4>Financial cashflow preview</h4>
                                <p>First available years from the financial analysis. Values remain preliminary until engineering and pricing are verified.</p>

                                @if ($charts['cashflow_available'])
                                    <div class="cockpit-cashflow">
                                        @foreach ($charts['cashflow'] as $row)
                                            <div class="cockpit-cashflow-row">
                                                <span>Y{{ $row['year'] }}</span>
                                                <div class="cockpit-cashflow-bars">
                                                    <div class="cockpit-bar-track" title="Saving {{ $row['saving_label'] }}">
                                                        <div class="cockpit-bar" style="width: {{ $row['saving_percent'] }}%"></div>
                                                    </div>
                                                    <div class="cockpit-bar-track" title="Net cashflow {{ $row['net_label'] }}">
                                                        <div class="cockpit-bar net" style="width: {{ $row['net_percent'] }}%"></div>
                                                    </div>
                                                    <div class="cockpit-bar-track" title="Cumulative {{ $row['cumulative_label'] }}">
                                                        <div class="cockpit-bar cumulative" style="width: {{ $row['cumulative_percent'] }}%"></div>
                                                    </div>
                                                </div>
                                                <strong>{{ $row['net_label'] }}</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="ui-empty" style="margin-top:16px">
                                        <div>
                                            <h3>No cashflow yet</h3>
                                            <p>Run financial analysis to activate the cashflow preview.</p>
                                        </div>
                                    </div>
                                @endif
                            </article>

                            <article class="cockpit-chart-panel">
                                <h4>Technical and energy gauges</h4>
                                <p>Key engineering indicators are shown as quick review gauges.</p>

                                <div class="cockpit-gauge-list">
                                    @foreach ($charts['technical'] as $metric)
                                        <div class="cockpit-gauge {{ $metric['state'] }}">
                                            <div class="cockpit-gauge-head">
                                                <strong>{{ $metric['label'] }}</strong>
                                                <span>{{ $metric['value'] }}</span>
                                            </div>
                                            <div class="cockpit-gauge-track">
                                                <div class="cockpit-gauge-fill" style="width: {{ $metric['percent'] }}%"></div>
                                            </div>
                                            <p>{{ $metric['note'] }}</p>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="cockpit-energy-strip">
                                    <div class="cockpit-energy-pill">
                                        <span>Annual energy</span>
                                        <strong>{{ $charts['energy']['annual_energy'] }}</strong>
                                    </div>
                                    <div class="cockpit-energy-pill">
                                        <span>Specific yield</span>
                                        <strong>{{ $charts['energy']['specific_yield'] }}</strong>
                                    </div>
                                    <div class="cockpit-energy-pill">
                                        <span>PV design fit</span>
                                        <strong>{{ $charts['energy']['pv_utilization_label'] }}</strong>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="cockpit-card">
                    <div class="cockpit-card-head">
                        <div>
                            <h3>Warning center</h3>
                            <p>All technical, roof, commercial, financial, quotation, and override warnings are grouped here.</p>
                        </div>
                        <span class="cockpit-chip {{ ($warningCounts['critical'] ?? 0) > 0 ? 'critical' : (($warningCounts['warning'] ?? 0) > 0 ? 'warning' : 'ready') }}">
                            {{ count($warningRecords) }} total warning{{ count($warningRecords) === 1 ? '' : 's' }}
                        </span>
                    </div>
                    <div class="cockpit-card-body">
                        <div class="cockpit-warning-grid">
                            <div class="cockpit-warning-count critical"><span>Critical</span><strong>{{ $warningCounts['critical'] ?? 0 }}</strong></div>
                            <div class="cockpit-warning-count warning"><span>Warning</span><strong>{{ $warningCounts['warning'] ?? 0 }}</strong></div>
                            <div class="cockpit-warning-count caution"><span>Caution</span><strong>{{ $warningCounts['caution'] ?? 0 }}</strong></div>
                            <div class="cockpit-warning-count info"><span>Info</span><strong>{{ $warningCounts['info'] ?? 0 }}</strong></div>
                        </div>

                        @if (count($warningRecords) > 0)
                            <div class="cockpit-warning-list">
                                @foreach ($warningRecords as $warning)
                                    <article class="cockpit-warning-item {{ $warning['severity'] ?? 'info' }}">
                                        <header>
                                            <strong>{{ $warning['title'] ?? 'Warning' }}</strong>
                                            <span class="cockpit-chip {{ $warning['severity'] ?? 'info' }}">{{ $warning['severity'] ?? 'info' }}</span>
                                        </header>
                                        <p>{{ $warning['message'] ?? '-' }}</p>
                                        @if (! empty($warning['recommendation']))
                                            <p><strong>Recommendation:</strong> {{ $warning['recommendation'] }}</p>
                                        @endif
                                        <small>{{ str_replace('_', ' ', $warning['source'] ?? 'system') }} · {{ $warning['code'] ?? 'WARNING' }}</small>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="ui-empty">
                                <div>
                                    <h3>No warning recorded</h3>
                                    <p>This project has no recorded warnings yet. Review assumptions before procurement or construction approval.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="cockpit-card">
                    <div class="cockpit-card-head">
                        <div>
                            <h3>Project profile</h3>
                            <p>Core site, customer, system, team, and audit metadata.</p>
                        </div>
                        @if ($project->isArchived())
                            <span class="cockpit-chip critical">Archived</span>
                        @endif
                    </div>
                    <div class="cockpit-card-body">
                        <dl class="cockpit-profile-grid">
                            <div class="cockpit-profile-item"><dt>Project Number</dt><dd>{{ $project->project_number }}</dd></div>
                            <div class="cockpit-profile-item"><dt>Customer</dt><dd>{{ $project->customer->name }}</dd></div>
                            <div class="cockpit-profile-item"><dt>Building Type</dt><dd>{{ $project->building_type_label }}</dd></div>
                            <div class="cockpit-profile-item"><dt>Proposed System</dt><dd>{{ $project->proposed_system_label }}</dd></div>
                            <div class="cockpit-profile-item"><dt>City</dt><dd>{{ $project->city ?: 'Not provided' }}</dd></div>
                            <div class="cockpit-profile-item"><dt>Province</dt><dd>{{ $project->province ?: 'Not provided' }}</dd></div>
                            <div class="cockpit-profile-item"><dt>Latitude</dt><dd>{{ $project->latitude ?: 'Not provided' }}</dd></div>
                            <div class="cockpit-profile-item"><dt>Longitude</dt><dd>{{ $project->longitude ?: 'Not provided' }}</dd></div>
                            <div class="cockpit-profile-item"><dt>Engineer</dt><dd>{{ $project->assignedEngineer?->name ?? 'Unassigned' }}</dd></div>
                            <div class="cockpit-profile-item"><dt>Sales</dt><dd>{{ $project->assignedSales?->name ?? 'Unassigned' }}</dd></div>
                            <div class="cockpit-profile-item wide"><dt>Site Address</dt><dd>{{ $project->site_address }}</dd></div>
                            <div class="cockpit-profile-item wide"><dt>Project Notes</dt><dd>{{ $project->description ?: 'No project notes.' }}</dd></div>
                        </dl>
                    </div>
                </section>

                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Status History</h3>
                            <p class="ui-card-description">Every workflow status change is retained for auditability.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        <div class="ui-timeline">
                            @foreach ($project->statusHistories as $history)
                                <article class="ui-timeline-item">
                                    <span class="ui-timeline-dot"></span>
                                    <div class="ui-timeline-copy">
                                        <strong>{{ $history->from_status ? ucfirst($history->from_status).' → ' : '' }}{{ ucfirst($history->to_status) }}</strong>
                                        <p>{{ $history->note ?: 'No status note was provided.' }}</p>
                                        <small>{{ $history->changedBy?->name ?? 'System' }} · {{ $history->changed_at?->format('d M Y, H:i') }}</small>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            </main>

            <aside class="cockpit-side">
                <section class="cockpit-card">
                    <div class="cockpit-card-head">
                        <div>
                            <h3>Next action</h3>
                            <p>The recommended next module based on workflow completeness.</p>
                        </div>
                    </div>
                    <div class="cockpit-card-body">
                        <div class="cockpit-next">
                            @if ($nextStep)
                                <span>{{ $workflowStateLabels[$nextStep['state']] ?? ucfirst($nextStep['state']) }}</span>
                                <strong>{{ $nextStep['label'] }}</strong>
                                <p>{{ $nextStep['caption'] }} · {{ $nextStep['status'] }}</p>
                            @else
                                <span>Complete</span>
                                <strong>Ready for review</strong>
                                <p>All core workflow modules are available. Review warnings, overrides, and reports before final use.</p>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="cockpit-card">
                    <div class="cockpit-card-head">
                        <div>
                            <h3>Action panel</h3>
                            <p>Open the relevant project module and continue the workflow from one place.</p>
                        </div>
                    </div>
                    <div class="cockpit-card-body">
                        <div class="cockpit-action-list">
                            @if ($canViewLoadAnalysis)
                                <a class="cockpit-action" href="{{ route('companies.load-analyses.show', [$company, $project]) }}"><span><strong>Load Analysis</strong><span>Review bills, equipment, critical loads, and daily profile.</span></span><b>Open</b></a>
                            @endif
                            @if ($canViewLocationSolar)
                                <a class="cockpit-action" href="{{ route('companies.location-solar.show', [$company, $project]) }}"><span><strong>Location & Solar</strong><span>Check coordinate, irradiation, weather, and source quality.</span></span><b>Open</b></a>
                            @endif
                            @if ($canViewRoofSite)
                                <a class="cockpit-action" href="{{ route('companies.roof-site.show', [$company, $project]) }}"><span><strong>Roof & Site Survey</strong><span>Review surface area, access, shading, and structure warnings.</span></span><b>Open</b></a>
                            @endif
                            @if ($canViewRecommendation)
                                <a class="cockpit-action" href="{{ route('companies.recommendations.show', [$company, $project]) }}"><span><strong>System Recommendation</strong><span>Compare on-grid and hybrid scoring with reasons.</span></span><b>Open</b></a>
                            @endif
                            @if ($canViewTechnicalCalculation)
                                <a class="cockpit-action" href="{{ route('companies.technical-calculations.show', [$company, $project]) }}"><span><strong>Technical Calculation</strong><span>Review PV sizing, string, cable, warning, formula, and overrides.</span></span><b>Open</b></a>
                            @endif
                            @if ($canViewBom)
                                <a class="cockpit-action" href="{{ route('companies.boms.show', [$company, $project]) }}"><span><strong>BOM/BOQ</strong><span>Generate material quantities and commercial price build-up.</span></span><b>Open</b></a>
                            @endif
                            @if ($canViewFinancialAnalysis)
                                <a class="cockpit-action" href="{{ route('companies.financial-analyses.show', [$company, $project]) }}"><span><strong>Financial Analysis</strong><span>Review saving, payback, ROI, NPV, IRR, and LCOE.</span></span><b>Open</b></a>
                            @endif
                            @if ($canViewQuotation)
                                <a class="cockpit-action" href="{{ route('companies.quotations.show', [$company, $project]) }}"><span><strong>Quotation</strong><span>Prepare, issue, and review customer-facing quotation.</span></span><b>Open</b></a>
                            @endif
                            @if ($canViewReports)
                                @if ($project->quotation)
                                    <a class="cockpit-action" href="{{ route('companies.quotations.pdf', [$company, $project]) }}"><span><strong>Quotation PDF</strong><span>Download customer quotation document.</span></span><b>PDF</b></a>
                                @endif
                                <a class="cockpit-action" href="{{ route('companies.projects.report.pdf', [$company, $project]) }}"><span><strong>Project Report PDF</strong><span>Download technical, commercial, and financial report.</span></span><b>PDF</b></a>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="cockpit-card">
                    <div class="cockpit-card-head">
                        <div>
                            <h3>Record audit</h3>
                            <p>Ownership and last known project change.</p>
                        </div>
                    </div>
                    <div class="cockpit-card-body">
                        <div class="cockpit-kv">
                            <div class="cockpit-kv-row"><span>Created By</span><strong>{{ $project->creator?->name ?? 'System' }}</strong></div>
                            <div class="cockpit-kv-row"><span>Created At</span><strong>{{ $project->created_at?->format('d M Y, H:i') }}</strong></div>
                            <div class="cockpit-kv-row"><span>Status Updated By</span><strong>{{ $project->statusUpdater?->name ?? 'System' }}</strong></div>
                            <div class="cockpit-kv-row"><span>Status Changed</span><strong>{{ $project->status_changed_at?->format('d M Y, H:i') ?: 'Not recorded' }}</strong></div>
                        </div>
                    </div>
                </section>

                @if ($canArchiveProject && ! $project->isArchived())
                    <section class="ui-card ui-danger-zone">
                        <div class="ui-card-header">
                            <div>
                                <h3 class="ui-card-title">Danger zone</h3>
                                <p class="ui-card-description">Only completed or cancelled projects can be archived.</p>
                            </div>
                        </div>
                        <div class="ui-card-body">
                            <form method="POST" action="{{ route('companies.projects.archive', [$company, $project]) }}">
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="ui-button ui-button-danger"
                                    data-confirm="Archive this project? It will be removed from the current project list."
                                    @disabled(! in_array($project->status, ['completed', 'cancelled'], true))
                                >
                                    Archive Project
                                </button>
                            </form>
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection
