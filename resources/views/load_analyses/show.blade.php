@extends('layouts.app')

@section('title', 'Load Analysis · '.$project->name)
@section('page-eyebrow', 'Load engineering')
@section('page-heading', 'Load Analysis')
@section('force-theme', 'dark')
@section('body-class', 'page-load-analysis-show-v6')
@section('disable-remote-fonts', '1')

@php
    $formatPower = static function ($value, int $decimals = 1): string {
        if ($value === null) return 'Not Available';
        $value = (float) $value;
        if (abs($value) >= 1000) return number_format($value / 1000, 2).' MW';
        return number_format($value, $decimals).' kW';
    };

    $formatEnergy = static function ($value, int $decimals = 1): string {
        if ($value === null) return 'Not Available';
        $value = (float) $value;
        if (abs($value) >= 1_000_000) return number_format($value / 1_000_000, 2).' GWh';
        if (abs($value) >= 1000) return number_format($value / 1000, 2).' MWh';
        return number_format($value, $decimals).' kWh';
    };

    $formatMoney = static function ($value): string {
        if ($value === null) return 'Not Recorded';
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    };

    $equipmentDaily = $workspace['has_equipment'] ? (float) $summary['equipment_daily_energy_kwh'] : null;
    $billAverage = $workspace['has_bills'] ? $summary['average_bill_kwh'] : null;
    $variance = $workspace['reconciliation_variance_percent'];
    $varianceAbs = $variance === null ? null : abs((float) $variance);
    $varianceLabel = $variance === null
        ? 'Insufficient Data'
        : ($varianceAbs > 10 ? 'Review mismatch' : 'Historical reference');
    $varianceTone = $variance === null ? 'neutral' : ($varianceAbs > 10 ? 'attention' : 'info');
    $customerName = $project->customer?->name ?: 'Customer not recorded';
    $customerNumber = $project->customer?->customer_number ?: 'Customer reference not recorded';
    $updatedLabel = $analysisExists && $analysis->updated_at
        ? $analysis->updated_at->format('d M Y, H:i')
        : 'Not Recorded';
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001018.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.018" rel="stylesheet">
    <link href="{{ asset('css/nexorin-load-analysis-show-v6.css') }}?v=018b" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-load-analysis-show-v6.js') }}?v=018b" defer></script>
@endpush

@section('content')
<main class="nx18" data-nx18-page data-analysis-exists="{{ $analysisExists ? 'true' : 'false' }}">
    <script type="application/json" id="nx18-profile-data">@json($workspace['profile_chart'])</script>
    <script type="application/json" id="nx18-bill-data">@json($workspace['bill_chart'])</script>

    <header class="nx18-command" data-nx18-reveal>
        <div class="nx18-command-copy">
            <nav class="nx18-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('companies.load-analyses.index', $company) }}">Load Analyses</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('companies.projects.show', [$company, $project]) }}">{{ $project->project_number }}</a>
            </nav>
            <div class="nx18-title-row">
                <div>
                    <span class="nx18-kicker">Load engineering workspace</span>
                    <h2>Load Analysis</h2>
                    <p>{{ $project->name }}</p>
                </div>
                <span class="nx18-state is-{{ $workspace['state']['tone'] }}"><i></i>{{ $workspace['state']['label'] }}</span>
            </div>
            <div class="nx18-project-meta">
                <span>{{ $customerName }}</span>
                <span>{{ $customerNumber }}</span>
                <span>{{ $workspace['basis_label'] }}</span>
                <span>Updated {{ $updatedLabel }}</span>
            </div>
        </div>

        <div class="nx18-command-actions">
            <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="nx18-button is-ghost">Project Cockpit</a>
            @if ($analysisExists && $canManage)
                <a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="nx18-button is-primary">
                    <span>Add equipment</span>
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12"/></svg>
                </a>
            @endif
        </div>
    </header>

    @if (! $analysisExists)
        <section class="nx18-empty-start" data-nx18-surface data-nx18-reveal>
            <div class="nx18-empty-start-icon" aria-hidden="true">
                <svg viewBox="0 0 28 28"><path d="M5 22V7.5h18V22H5Z"/><path d="M9 16l3-3 3 2 4-5"/></svg>
            </div>
            <div class="nx18-empty-start-copy">
                <span class="nx18-section-kicker">No persisted analysis</span>
                <h3>Load Analysis has not been started</h3>
                <p>
                    Viewing this page does not create engineering state. Start the Load Analysis explicitly to persist the current project workspace, then add equipment, bill history, or confirmed settings.
                </p>
                <div class="nx18-boundary-inline">
                    <span>Default source: Equipment + bill reference</span>
                    <span>Default billing period: 30 days</span>
                    <span>Default daytime window: 06:00–18:00</span>
                </div>
                <small>These initialization values are application defaults, not measured or verified evidence.</small>
            </div>
            <div class="nx18-empty-start-action">
                @if ($canManage)
                    <form method="POST" action="{{ route('companies.load-analyses.start', [$company, $project]) }}">
                        @csrf
                        <button type="submit" class="nx18-button is-primary is-large">Start Load Analysis</button>
                    </form>
                @else
                    <span class="nx18-readonly-badge">Read-only access</span>
                    <p>A user with Load Analysis management permission must start this workspace.</p>
                @endif
            </div>
        </section>
    @else
        <nav class="nx18-tabs" role="tablist" aria-label="Load Analysis sections" data-nx18-tabs data-nx18-reveal>
            <button type="button" role="tab" aria-selected="true" aria-controls="nx18-panel-overview" id="nx18-tab-overview" data-nx18-tab="overview" class="is-active">Overview</button>
            <button type="button" role="tab" aria-selected="false" aria-controls="nx18-panel-equipment" id="nx18-tab-equipment" data-nx18-tab="equipment">Equipment <span>{{ number_format((int) $summary['appliance_count']) }}</span></button>
            <button type="button" role="tab" aria-selected="false" aria-controls="nx18-panel-bills" id="nx18-tab-bills" data-nx18-tab="bills">Bill History <span>{{ number_format((int) $summary['bill_count']) }}</span></button>
            <button type="button" role="tab" aria-selected="false" aria-controls="nx18-panel-settings" id="nx18-tab-settings" data-nx18-tab="settings">Analysis Settings</button>
        </nav>

        <div class="nx18-workspace">
            <div class="nx18-main">
                <section id="nx18-panel-overview" role="tabpanel" aria-labelledby="nx18-tab-overview" data-nx18-panel="overview" class="nx18-panel is-active">
                    <section class="nx18-metrics" aria-label="Current deterministic load summary" data-nx18-reveal>
                        <article class="nx18-metric is-cyan">
                            <span>Connected load</span>
                            <strong>{{ $workspace['has_equipment'] ? $formatPower($summary['connected_load_kw'], 2) : 'Not Available' }}</strong>
                            <small>{{ $workspace['has_equipment'] ? 'Recorded equipment nameplate basis' : 'No equipment recorded' }}</small>
                        </article>
                        <article class="nx18-metric is-blue">
                            <span>Modeled daily energy</span>
                            <strong>{{ $workspace['has_equipment'] ? $formatEnergy($summary['equipment_daily_energy_kwh'], 2) : 'Not Available' }}</strong>
                            <small>Repeated 24-hour equipment schedule</small>
                        </article>
                        <article class="nx18-metric is-purple">
                            <span>Critical share</span>
                            <strong>{{ $workspace['critical_share_percent'] === null ? 'Not Available' : number_format((float) $workspace['critical_share_percent'], 1).'%' }}</strong>
                            <small>User classification; not life-safety assurance</small>
                        </article>
                        <article class="nx18-metric is-amber">
                            <span>Modeled profile peak</span>
                            <strong>{{ $workspace['modeled_peak_kw'] === null ? 'Not Available' : $formatPower($workspace['modeled_peak_kw'], 2) }}</strong>
                            <small>Maximum modeled 1-hour average load</small>
                        </article>
                        <article class="nx18-metric is-blue is-bill-reference">
                            <span>Average bill energy</span>
                            <strong>{{ $billAverage === null ? 'Not Recorded' : $formatEnergy($billAverage, 1) }}</strong>
                            <small>{{ number_format((int) $summary['bill_count']) }} recorded month{{ (int) $summary['bill_count'] === 1 ? '' : 's' }}</small>
                        </article>
                    </section>

                    <div class="nx18-overview-grid">
                        <section class="nx18-card nx18-profile-card" data-nx18-surface data-nx18-reveal>
                            <header class="nx18-card-head">
                                <div>
                                    <span class="nx18-section-kicker">Repeated daily model</span>
                                    <h3>24-Hour Modeled Load Profile</h3>
                                    <p>Modeled 1-hour average load derived from stored equipment schedules. This is not measured interval demand.</p>
                                </div>
                                @if ($workspace['has_equipment'])
                                    <span class="nx18-data-chip">1-hour avg · kW</span>
                                @endif
                            </header>
                            @if ($workspace['has_equipment'])
                                <div class="nx18-chart" data-nx18-chart="profile" role="img" aria-label="24-hour modeled one-hour average load profile with critical-tagged equipment overlay"></div>
                                <footer class="nx18-chart-note">
                                    <span><i class="is-total"></i>Total modeled load</span>
                                    <span><i class="is-critical"></i>Critical-tagged portion</span>
                                    <span>Daytime {{ sprintf('%02d:00', (int) $analysis->daytime_start_hour) }}–{{ sprintf('%02d:00', (int) $analysis->daytime_end_hour) }}</span>
                                </footer>
                            @else
                                <div class="nx18-chart-empty">
                                    <strong>No equipment profile available</strong>
                                    <p>Add equipment schedules to create the repeated 24-hour modeled profile.</p>
                                    @if ($canManage)
                                        <a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="nx18-inline-link">Add equipment</a>
                                    @endif
                                </div>
                            @endif
                        </section>

                        <section class="nx18-card nx18-bill-card" data-nx18-surface data-nx18-reveal>
                            <header class="nx18-card-head">
                                <div>
                                    <span class="nx18-section-kicker">Recorded history</span>
                                    <h3>Electricity Bill Reference</h3>
                                    <p>Monthly energy from stored bill records. No future values are projected.</p>
                                </div>
                            </header>
                            @if ($workspace['has_bills'])
                                <div class="nx18-chart nx18-chart--bill" data-nx18-chart="bills" role="img" aria-label="Recorded electricity bill energy trend"></div>
                                <div class="nx18-bill-mini-grid">
                                    <div><span>Effective recorded rate</span><strong>{{ $workspace['effective_bill_rate'] === null ? 'Not Recorded' : $formatMoney($workspace['effective_bill_rate']).'/kWh' }}</strong></div>
                                    <div><span>Average recorded peak</span><strong>{{ $workspace['recorded_bill_peak_kw'] === null ? 'Not Recorded' : $formatPower($workspace['recorded_bill_peak_kw'], 2) }}</strong></div>
                                </div>
                            @else
                                <div class="nx18-chart-empty is-compact">
                                    <strong>No bill history recorded</strong>
                                    <p>Monthly utility history can be added as a separate historical reference.</p>
                                    @if ($canManage)
                                        <button type="button" class="nx18-inline-link is-button" data-nx18-open-tab="bills">Add bill history</button>
                                    @endif
                                </div>
                            @endif
                        </section>
                    </div>

                    <section class="nx18-reconciliation is-{{ $varianceTone }}" data-nx18-surface data-nx18-reveal>
                        <div>
                            <span class="nx18-section-kicker">Bill comparison</span>
                            <h3>{{ $varianceLabel }}</h3>
                            @if ($variance === null)
                                <p>Both an equipment model and bill history are required before model-to-bill variance can be shown.</p>
                            @else
                                <p>
                                    Modeled monthly equipment energy differs from average recorded bill energy by
                                    <strong>{{ number_format((float) $variance, 1) }}%</strong>.
                                    This is a reconciliation indicator only; it is not an engineering validation score.
                                </p>
                            @endif
                        </div>
                        <div class="nx18-reconciliation-values">
                            <div><span>Modeled month</span><strong>{{ $workspace['has_equipment'] ? $formatEnergy($summary['equipment_monthly_energy_kwh'], 1) : 'Not Available' }}</strong></div>
                            <div><span>Average bill month</span><strong>{{ $billAverage === null ? 'Not Recorded' : $formatEnergy($billAverage, 1) }}</strong></div>
                            <div><span>Model-to-bill variance</span><strong>{{ $variance === null ? 'Not Available' : number_format((float) $variance, 1).'%' }}</strong></div>
                        </div>
                    </section>

                    <section class="nx18-card nx18-equipment-preview" data-nx18-surface data-nx18-reveal>
                        <header class="nx18-card-head">
                            <div>
                                <span class="nx18-section-kicker">Equipment basis</span>
                                <h3>Recorded Equipment Groups</h3>
                                <p>Current appliance records that feed the authoritative repeated daily model.</p>
                            </div>
                            <button type="button" class="nx18-inline-link is-button" data-nx18-open-tab="equipment">View equipment registry</button>
                        </header>
                        @if ($workspace['has_equipment'])
                            <div class="nx18-preview-list">
                                @foreach ($analysis->appliances->take(6) as $appliance)
                                    <article>
                                        <div class="nx18-preview-name">
                                            <i class="{{ $appliance->is_critical ? 'is-critical' : '' }}"></i>
                                            <div><strong>{{ $appliance->name }}</strong><span>{{ $appliance->category ?: 'Uncategorized' }}</span></div>
                                        </div>
                                        <div><span>Connected</span><strong>{{ $formatPower($appliance->connected_load_kw, 2) }}</strong></div>
                                        <div><span>Daily energy</span><strong>{{ $formatEnergy($appliance->daily_energy_kwh, 2) }}</strong></div>
                                        <div><span>Schedule</span><strong>{{ $appliance->schedule_label }}</strong></div>
                                    </article>
                                @endforeach
                            </div>
                            @if ($analysis->appliances->count() > 6)
                                <p class="nx18-more-note">{{ number_format($analysis->appliances->count() - 6) }} additional equipment group{{ $analysis->appliances->count() - 6 === 1 ? '' : 's' }} available in the Equipment tab.</p>
                            @endif
                        @else
                            <div class="nx18-empty-inline">No equipment groups recorded.</div>
                        @endif
                    </section>
                </section>

                <section id="nx18-panel-equipment" role="tabpanel" aria-labelledby="nx18-tab-equipment" data-nx18-panel="equipment" class="nx18-panel" hidden>
                    <section class="nx18-card" data-nx18-surface>
                        <header class="nx18-card-head nx18-card-head--action">
                            <div>
                                <span class="nx18-section-kicker">Equipment registry</span>
                                <h3>Load Equipment</h3>
                                <p>One stored operating window per equipment group. Usage factor and starting multiplier remain simplified assumptions.</p>
                            </div>
                            @if ($canManage)
                                <a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="nx18-button is-primary">Add equipment</a>
                            @endif
                        </header>

                        @if ($workspace['has_equipment'])
                            <div class="nx18-table-shell">
                                <table class="nx18-table nx18-table--equipment">
                                    <thead>
                                        <tr>
                                            <th>Equipment</th>
                                            <th>Qty / rated power</th>
                                            <th>Connected</th>
                                            <th>Operating assumption</th>
                                            <th>Daily energy</th>
                                            <th>Starting assumption</th>
                                            <th>Critical tag</th>
                                            @if ($canManage)<th class="is-actions" aria-label="Actions"></th>@endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($analysis->appliances as $appliance)
                                            <tr>
                                                <td><strong>{{ $appliance->name }}</strong><span>{{ $appliance->category ?: 'Uncategorized' }}</span></td>
                                                <td><strong>{{ number_format((int) $appliance->quantity) }} × {{ number_format((float) $appliance->rated_power_w, 0) }} W</strong><span>Stored group</span></td>
                                                <td><strong>{{ $formatPower($appliance->connected_load_kw, 2) }}</strong><span>Nameplate total</span></td>
                                                <td><strong>{{ $appliance->schedule_label }}</strong><span>Usage {{ number_format((float) $appliance->usage_factor * 100, 0) }}%</span></td>
                                                <td><strong>{{ $formatEnergy($appliance->daily_energy_kwh, 2) }}</strong><span>Daily model</span></td>
                                                <td><strong>{{ number_format((float) $appliance->starting_multiplier, 2) }}× · {{ $formatPower($appliance->starting_load_kw, 2) }}</strong><span>Preliminary</span></td>
                                                <td>
                                                    <span class="nx18-tag {{ $appliance->is_critical ? 'is-critical' : 'is-standard' }}">{{ $appliance->is_critical ? 'Critical-tagged' : 'Standard' }}</span>
                                                </td>
                                                @if ($canManage)
                                                    <td class="is-actions">
                                                        <div class="nx18-row-actions">
                                                            <a href="{{ route('companies.load-analyses.appliances.edit', [$company, $project, $appliance]) }}" aria-label="Edit {{ $appliance->name }}">Edit</a>
                                                            <form method="POST" action="{{ route('companies.load-analyses.appliances.destroy', [$company, $project, $appliance]) }}" data-nx18-confirm="Remove this equipment group?">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit">Remove</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="nx18-empty-panel">
                                <strong>No equipment recorded</strong>
                                <p>A 24-hour modeled profile is not available until at least one equipment schedule exists.</p>
                                @if ($canManage)<a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="nx18-button is-primary">Add first equipment</a>@endif
                            </div>
                        @endif
                    </section>

                    <div class="nx18-equipment-support">
                        <section class="nx18-card nx18-category-card" data-nx18-surface>
                            <header class="nx18-card-head"><div><span class="nx18-section-kicker">Recorded categories</span><h3>Equipment Distribution</h3></div></header>
                            @if ($workspace['categories'])
                                <div class="nx18-category-table" role="table" aria-label="Equipment category distribution">
                                    <div class="nx18-category-row is-head" role="row">
                                        <span role="columnheader">Category</span><span role="columnheader">Groups</span><span role="columnheader">Connected</span><span role="columnheader">Daily energy</span>
                                    </div>
                                    @foreach ($workspace['categories'] as $category)
                                        <div class="nx18-category-row" role="row">
                                            <strong role="cell">{{ $category['category'] }}</strong>
                                            <span role="cell">{{ number_format($category['count']) }}</span>
                                            <strong role="cell">{{ $formatPower($category['connected_kw'], 2) }}</strong>
                                            <strong role="cell">{{ $formatEnergy($category['daily_kwh'], 2) }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="nx18-empty-inline">No categories available.</div>
                            @endif
                        </section>
                        <section class="nx18-card nx18-boundary-card" data-nx18-surface>
                            <header class="nx18-card-head"><div><span class="nx18-section-kicker">Starting demand boundary</span><h3>Preliminary Equipment Context</h3></div></header>
                            <p>Starting multipliers remain equipment-level fallbacks; no measured LRA/inrush, starting sequence, or simultaneous-start study is stored.</p>
                            <ul>
                                <li>Do not combine every multiplier into an authoritative system start peak.</li>
                                <li>Measured inrush/LRA or manufacturer starting data outranks the current multiplier.</li>
                                <li>Critical-tagged means user priority only; it does not guarantee backup or life-safety service.</li>
                            </ul>
                        </section>
                    </div>
                </section>

                <section id="nx18-panel-bills" role="tabpanel" aria-labelledby="nx18-tab-bills" data-nx18-panel="bills" class="nx18-panel" hidden>
                    <div class="nx18-bills-top">
                        <section class="nx18-card" data-nx18-surface>
                            <header class="nx18-card-head"><div><span class="nx18-section-kicker">Historical reference</span><h3>Recorded Electricity Bill Trend</h3><p>Monthly energy comes directly from stored bill records.</p></div></header>
                            @if ($workspace['has_bills'])
                                <div class="nx18-chart nx18-chart--bill-large" data-nx18-chart="bills-large" role="img" aria-label="Recorded monthly electricity bill energy"></div>
                            @else
                                <div class="nx18-chart-empty"><strong>No bill history recorded</strong><p>Add a monthly bill record to establish historical energy reference.</p></div>
                            @endif
                        </section>

                        <section class="nx18-card nx18-bill-summary" data-nx18-surface>
                            <header class="nx18-card-head"><div><span class="nx18-section-kicker">Bill summary</span><h3>Recorded Reference</h3></div></header>
                            <div class="nx18-info-grid">
                                <div><span>Months recorded</span><strong>{{ number_format((int) $summary['bill_count']) }}</strong></div>
                                <div><span>Average energy</span><strong>{{ $billAverage === null ? 'Not Recorded' : $formatEnergy($billAverage, 1) }}</strong></div>
                                <div><span>Effective rate</span><strong>{{ $workspace['effective_bill_rate'] === null ? 'Not Recorded' : $formatMoney($workspace['effective_bill_rate']).'/kWh' }}</strong></div>
                                <div><span>Average recorded peak</span><strong>{{ $workspace['recorded_bill_peak_kw'] === null ? 'Not Recorded' : $formatPower($workspace['recorded_bill_peak_kw'], 2) }}</strong></div>
                            </div>
                            <p class="nx18-small-note">A recorded bill peak remains separate from the synthetic equipment-profile peak. Monthly bill data does not create a 24-hour interval profile.</p>
                        </section>
                    </div>

                    @if ($canManage)
                        <section class="nx18-card nx18-bill-entry" data-nx18-surface>
                            <header class="nx18-card-head"><div><span class="nx18-section-kicker">Historical input</span><h3>Add or Replace Bill Month</h3><p>Saving the same month replaces that month's stored values.</p></div></header>
                            <form method="POST" action="{{ route('companies.load-analyses.bills.store', [$company, $project]) }}" class="nx18-form nx18-form--bill" data-nx18-bill-form>
                                @csrf
                                <label><span>Billing month</span><input type="month" name="billing_month" value="{{ old('billing_month') }}" required></label>
                                <label><span>Energy <small>kWh</small></span><input type="number" name="energy_kwh" data-nx18-bill-energy value="{{ old('energy_kwh') }}" min="0.001" step="0.001" required></label>
                                <label><span>Bill amount <small>IDR</small></span><input type="number" name="bill_amount" data-nx18-bill-amount value="{{ old('bill_amount') }}" min="0" step="0.01"></label>
                                <label><span>Recorded peak demand <small>kW</small></span><input type="number" name="peak_demand_kw" value="{{ old('peak_demand_kw') }}" min="0" step="0.001"></label>
                                <div class="nx18-rate-preview" data-nx18-rate-preview><span>Unsaved effective rate</span><strong>Waiting for energy + amount</strong><small>Browser preview only; not authoritative until saved.</small></div>
                                <button type="submit" class="nx18-button is-primary">Save bill month</button>
                            </form>
                        </section>
                    @endif

                    <section class="nx18-card" data-nx18-surface>
                        <header class="nx18-card-head"><div><span class="nx18-section-kicker">Monthly registry</span><h3>Bill History</h3></div></header>
                        @if ($workspace['has_bills'])
                            <div class="nx18-table-shell">
                                <table class="nx18-table nx18-table--bills">
                                    <thead><tr><th>Month</th><th>Energy</th><th>Bill amount</th><th>Effective rate</th><th>Recorded peak demand</th>@if ($canManage)<th class="is-actions" aria-label="Actions"></th>@endif</tr></thead>
                                    <tbody>
                                        @foreach ($analysis->electricityBills as $bill)
                                            <tr>
                                                <td><strong>{{ $bill->billing_month->format('F Y') }}</strong></td>
                                                <td><strong>{{ $formatEnergy($bill->energy_kwh, 1) }}</strong></td>
                                                <td><strong>{{ $formatMoney($bill->bill_amount) }}</strong></td>
                                                <td><strong>{{ $bill->effective_rate === null ? 'Not Recorded' : $formatMoney($bill->effective_rate).'/kWh' }}</strong></td>
                                                <td><strong>{{ $bill->peak_demand_kw === null ? 'Not Recorded' : $formatPower($bill->peak_demand_kw, 2) }}</strong></td>
                                                @if ($canManage)
                                                    <td><form method="POST" action="{{ route('companies.load-analyses.bills.destroy', [$company, $project, $bill]) }}" data-nx18-confirm="Remove this bill record?">@csrf @method('DELETE')<button type="submit" class="nx18-delete-link">Remove</button></form></td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="nx18-empty-inline">No electricity bill records available.</div>
                        @endif
                    </section>
                </section>

                <section id="nx18-panel-settings" role="tabpanel" aria-labelledby="nx18-tab-settings" data-nx18-panel="settings" class="nx18-panel nx18-panel--settings" hidden>
                    <section class="nx18-card nx18-settings-surface" data-nx18-surface>
                        <header class="nx18-card-head"><div><span class="nx18-section-kicker">Stored analysis assumptions</span><h3>Analysis Settings</h3><p>These settings shape the simplified current load model. They are not evidence of measured interval behavior.</p></div></header>

                        @if ($canManage)
                            <form method="POST" action="{{ route('companies.load-analyses.update', [$company, $project]) }}" class="nx18-form nx18-form--settings">
                                @csrf
                                @method('PUT')
                                <label class="nx18-field-span-2"><span>Data basis</span>
                                    <select name="source_mode" required>
                                        <option value="combined" @selected(old('source_mode', $analysis->source_mode) === 'combined')>Equipment + bill reference</option>
                                        <option value="equipment" @selected(old('source_mode', $analysis->source_mode) === 'equipment')>Equipment schedule only</option>
                                        <option value="bill" @selected(old('source_mode', $analysis->source_mode) === 'bill')>Electricity bills only</option>
                                    </select>
                                    <small>Combined keeps the equipment model and bill history separate, with variance shown only as reconciliation.</small>
                                </label>
                                <label><span>Supply phase</span><select name="supply_phase" required><option value="single_phase" @selected(old('supply_phase', $analysis->supply_phase) === 'single_phase')>Single-phase</option><option value="three_phase" @selected(old('supply_phase', $analysis->supply_phase) === 'three_phase')>Three-phase</option></select></label>
                                <label><span>Connected service power <small>VA</small></span><input type="number" name="connected_power_va" value="{{ old('connected_power_va', $analysis->connected_power_va) }}" min="1" step="1"></label>
                                <label><span>Tariff group</span><input type="text" name="tariff_group" value="{{ old('tariff_group', $analysis->tariff_group) }}" maxlength="50" placeholder="Not recorded"></label>
                                <label><span>Reference electricity rate <small>IDR/kWh</small></span><input type="number" name="electricity_rate_per_kwh" value="{{ old('electricity_rate_per_kwh', $analysis->electricity_rate_per_kwh) }}" min="0" step="0.01"></label>
                                <label><span>Billing days</span><input type="number" name="billing_days" value="{{ old('billing_days', $analysis->billing_days) }}" min="1" max="366" required></label>
                                <label><span>Daytime start</span><select name="daytime_start_hour" required>@for ($hour = 0; $hour < 24; $hour++)<option value="{{ $hour }}" @selected((int) old('daytime_start_hour', $analysis->daytime_start_hour) === $hour)>{{ sprintf('%02d:00', $hour) }}</option>@endfor</select></label>
                                <label><span>Daytime end</span><select name="daytime_end_hour" required>@for ($hour = 0; $hour < 24; $hour++)<option value="{{ $hour }}" @selected((int) old('daytime_end_hour', $analysis->daytime_end_hour) === $hour)>{{ sprintf('%02d:00', $hour) }}</option>@endfor</select></label>
                                <label class="nx18-field-span-2"><span>Engineering notes</span><textarea name="notes" rows="5" maxlength="5000" placeholder="Record the basis, source context, and unresolved limitations here.">{{ old('notes', $analysis->notes) }}</textarea></label>
                                <div class="nx18-form-actions nx18-field-span-2"><span>Saving changes updates this Load Analysis and may require downstream review.</span><button type="submit" class="nx18-button is-primary">Save analysis settings</button></div>
                            </form>
                        @else
                            <div class="nx18-settings-readonly">
                                <div><span>Data basis</span><strong>{{ $workspace['source_mode']['label'] }}</strong></div>
                                <div><span>Supply phase</span><strong>{{ $analysis->supply_phase_label }}</strong></div>
                                <div><span>Connected service power</span><strong>{{ $analysis->connected_power_va ? number_format((int) $analysis->connected_power_va).' VA' : 'Not Recorded' }}</strong></div>
                                <div><span>Tariff group</span><strong>{{ $analysis->tariff_group ?: 'Not Recorded' }}</strong></div>
                                <div><span>Reference rate</span><strong>{{ $analysis->electricity_rate_per_kwh ? $formatMoney($analysis->electricity_rate_per_kwh).'/kWh' : 'Not Recorded' }}</strong></div>
                                <div><span>Billing days</span><strong>{{ number_format((int) $analysis->billing_days) }}</strong></div>
                                <div><span>Daytime window</span><strong>{{ sprintf('%02d:00', (int) $analysis->daytime_start_hour) }}–{{ sprintf('%02d:00', (int) $analysis->daytime_end_hour) }}</strong></div>
                                <div><span>Notes</span><strong>{{ $analysis->notes ?: 'Not Recorded' }}</strong></div>
                            </div>
                        @endif
                    </section>
                </section>
            </div>

            <aside class="nx18-rail" aria-label="Load Analysis interpretation">
                <section class="nx18-rail-card" data-nx18-surface data-nx18-reveal>
                    <span class="nx18-section-kicker">Data basis</span>
                    <h3>{{ $workspace['source_mode']['label'] }}</h3>
                    <p>{{ $workspace['source_mode']['detail'] }}</p>
                    <div class="nx18-rail-stats">
                        <div><span>Equipment groups</span><strong>{{ number_format((int) $summary['appliance_count']) }}</strong></div>
                        <div><span>Bill months</span><strong>{{ number_format((int) $summary['bill_count']) }}</strong></div>
                        <div><span>Current state</span><strong>{{ $workspace['state']['label'] }}</strong></div>
                    </div>
                </section>

                <section class="nx18-rail-card nx18-rail-card--boundary" data-nx18-surface data-nx18-reveal>
                    <span class="nx18-section-kicker">Current model boundary</span>
                    <h3>Simplified load model</h3>
                    <ul class="nx18-rail-list">
                        <li>One repeated 24-hour equipment schedule.</li>
                        <li>No measured interval dataset.</li>
                        <li>No seasonal, shift, or diversity study.</li>
                        <li>Starting values remain preliminary.</li>
                        <li>Bill history is reference data, not validation.</li>
                    </ul>
                </section>

                <section class="nx18-rail-card nx18-rail-card--action" data-nx18-surface data-nx18-reveal>
                    <span class="nx18-section-kicker">Next engineering action</span>
                    <h3>
                        @if ($workspace['next_action']['type'] === 'equipment') Complete the equipment basis
                        @elseif ($workspace['next_action']['type'] === 'bill') Add historical reference
                        @elseif ($workspace['next_action']['type'] === 'solar') Location &amp; solar resource
                        @else Continue project engineering
                        @endif
                    </h3>
                    <p>
                        @if ($workspace['next_action']['type'] === 'equipment') Add the next recorded equipment group required by the selected source basis.
                        @elseif ($workspace['next_action']['type'] === 'bill') Record or review monthly utility history as a separate reference basis.
                        @elseif ($workspace['next_action']['type'] === 'solar') Continue with project location and solar-resource inputs after reviewing this load basis.
                        @else Return to the project workspace for the next available engineering step.
                        @endif
                    </p>
                    @if ($workspace['next_action']['type'] === 'equipment')
                        @if ($canManage)<a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="nx18-button is-primary is-full">Add equipment</a>@endif
                    @elseif ($workspace['next_action']['type'] === 'bill')
                        <button type="button" class="nx18-button is-primary is-full" data-nx18-open-tab="bills">Open Bill History</button>
                    @elseif (isset($workspace['next_action']['url']))
                        <a href="{{ $workspace['next_action']['url'] }}" class="nx18-button is-primary is-full">{{ $workspace['next_action']['label'] }}</a>
                    @endif
                </section>

                <section class="nx18-rail-card is-{{ $workspace['downstream']['key'] }}" data-nx18-surface data-nx18-reveal>
                    <span class="nx18-section-kicker">Downstream freshness</span>
                    <h3>{{ $workspace['downstream']['label'] }}</h3>
                    <p>
                        @if ($workspace['downstream']['key'] === 'review-required') Newer load inputs require the existing downstream result to be reviewed.
                        @elseif ($workspace['downstream']['key'] === 'no-stale-flag') No recorded upstream change currently requires downstream review.
                        @else Recommendation and Technical Calculation freshness are not yet available.
                        @endif
                    </p>
                    <small>Freshness is limited to the currently implemented project checks.</small>
                </section>

                <section class="nx18-rail-card" data-nx18-surface data-nx18-reveal>
                    <span class="nx18-section-kicker">Missing inputs</span>
                    <h3>{{ count($workspace['missing_inputs']) ? 'Review required' : 'No immediate basis gap' }}</h3>
                    @if ($workspace['missing_inputs'])
                        <ul class="nx18-rail-list is-attention">@foreach ($workspace['missing_inputs'] as $missing)<li>{{ $missing }}</li>@endforeach</ul>
                    @else
                        <p>The selected current source basis has its primary input type recorded. This does not mean the load model is verified or complete.</p>
                    @endif
                </section>
            </aside>
        </div>
    @endif
</main>
@endsection
