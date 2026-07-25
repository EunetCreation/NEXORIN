@extends('layouts.app')

@section('title', 'Load Analysis · '.$project->name)
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Load Analysis')

@php
    $hasEquipment = (int) $summary['appliance_count'] > 0;
    $billOnly = $analysis->source_mode === 'bill';
    $hasBills = (int) $summary['bill_count'] > 0;
    $hasSettings = (bool) ($analysis->connected_power_va || $analysis->tariff_group || $analysis->electricity_rate_per_kwh);
    $hasDayWindow = $analysis->daytime_start_hour !== null && $analysis->daytime_end_hour !== null;
    $validationVariance = $summary['bill_variance_percent'];
    $validationAbs = $validationVariance === null ? null : abs((float) $validationVariance);

    $validationStatus = 'Waiting for bills';
    $validationTone = 'is-warning';
    if ($billOnly && $hasBills) {
        $validationStatus = 'Bill source accepted';
        $validationTone = 'is-live';
    } elseif ($summary['average_bill_kwh'] !== null && $validationVariance === null) {
        $validationStatus = 'Waiting for equipment';
    } elseif ($validationAbs !== null && $validationAbs <= 5) {
        $validationStatus = 'Validated';
        $validationTone = 'is-live';
    } elseif ($validationAbs !== null && $validationAbs <= 10) {
        $validationStatus = 'Close match';
        $validationTone = 'is-live';
    } elseif ($validationAbs !== null) {
        $validationStatus = 'Review mismatch';
    }

    $readinessItems = $billOnly
        ? [
            ['label' => 'Billing energy source', 'complete' => $hasBills, 'meta' => number_format((int) $summary['bill_count']).' months'],
            ['label' => 'Billing coverage', 'complete' => (int) $summary['bill_count'] >= 3, 'meta' => (int) $summary['bill_count'] >= 3 ? 'Minimum baseline met' : 'Add at least 3 months'],
            ['label' => 'Analysis settings', 'complete' => $hasSettings && $hasDayWindow, 'meta' => $analysis->source_mode_label],
            ['label' => 'Equipment profile', 'complete' => true, 'meta' => $hasEquipment ? number_format((int) $summary['appliance_count']).' optional groups' : 'Optional in bills-only mode'],
        ]
        : [
            ['label' => 'Equipment profile', 'complete' => $hasEquipment, 'meta' => number_format((int) $summary['appliance_count']).' groups'],
            ['label' => 'Bill history', 'complete' => $hasBills, 'meta' => number_format((int) $summary['bill_count']).' months'],
            ['label' => 'Analysis settings', 'complete' => $hasSettings && $hasDayWindow, 'meta' => $analysis->source_mode_label],
            ['label' => 'Validation fit', 'complete' => $validationAbs !== null && $validationAbs <= 10, 'meta' => $validationStatus],
        ];
    $readinessComplete = collect($readinessItems)->filter(fn ($item) => $item['complete'])->count();
    $readinessPercent = (int) round(($readinessComplete / count($readinessItems)) * 100);

    $canViewSolar = auth()->user()->hasAnyCompanyPermission($company, [
        'location_solar.view',
        'location_solar.manage',
        'location_solar.view_own',
        'location_solar.manage_own',
    ]);

    $nextActionLabel = $canViewSolar ? 'Continue to Location & Solar' : 'Return to Project Cockpit';
    $nextActionRoute = $canViewSolar
        ? route('companies.location-solar.show', [$company, $project])
        : route('companies.projects.show', [$company, $project]);
    $nextActionTab = null;

    if (! $hasEquipment && ! $billOnly) {
        $nextActionLabel = 'Add first equipment group';
        $nextActionRoute = route('companies.load-analyses.appliances.create', [$company, $project]);
    } elseif (! $hasBills) {
        $nextActionLabel = 'Add monthly bill history';
        $nextActionRoute = '#bills';
        $nextActionTab = 'bills';
    } elseif ($validationAbs !== null && $validationAbs > 10) {
        $nextActionLabel = 'Review the validation mismatch';
        $nextActionRoute = '#overview';
        $nextActionTab = 'overview';
    }

    $profileMaximum = max($hourlyProfile ?: [0]);
    $peakHours = collect($hourlyProfile)
        ->filter(fn ($energy) => $profileMaximum > 0 && abs((float) $energy - (float) $profileMaximum) < 0.0001)
        ->keys()
        ->map(fn ($hour) => (int) $hour)
        ->values();
    $peakHour = (int) ($peakHours->first() ?? 0);
    $peakHoursAreContiguous = $peakHours->count() <= 1
        || $peakHours->values()->every(fn ($hour, $index) => $index === 0 || $hour === ((int) $peakHours[$index - 1] + 1));
    $peakWindow = $peakHours->count() > 1 && $peakHoursAreContiguous
        ? sprintf('%02d:00–%02d:00', (int) $peakHours->first(), (((int) $peakHours->last()) + 1) % 24)
        : sprintf('%02d:00–%02d:00', $peakHour, ($peakHour + 1) % 24);
    $peakDescriptor = $peakHours->count() > 1 ? 'Peak plateau' : 'Peak hour';

    $categoryMetrics = $analysis->appliances
        ->groupBy(fn ($appliance) => $appliance->category ?: 'Uncategorized')
        ->map(function ($items, $category) {
            return [
                'label' => $category,
                'count' => $items->count(),
                'daily_energy' => (float) $items->sum(fn ($item) => $item->daily_energy_kwh),
            ];
        })
        ->sortByDesc('daily_energy')
        ->values();

    $billHistory = $analysis->electricityBills->sortBy('billing_month')->values();
    $billRegistryHighest = $billHistory->sortByDesc(fn ($bill) => (float) $bill->energy_kwh)->first();
    $billRegistryLowest = $billHistory->sortBy(fn ($bill) => (float) $bill->energy_kwh)->first();
    $billEnergyValues = $billHistory->pluck('energy_kwh')->map(fn ($value) => (float) $value);
    $billMaxKwh = max($billEnergyValues->all() ?: [0]);
    $billMinKwh = $billEnergyValues->isEmpty() ? null : $billEnergyValues->min();
    $billAverageKwh = $billEnergyValues->isEmpty() ? null : $billEnergyValues->avg();
    $billVariationPercent = ($billAverageKwh && $billAverageKwh > 0 && $billMinKwh !== null)
        ? (($billMaxKwh - $billMinKwh) / $billAverageKwh) * 100
        : null;

    $billChartHistory = $billHistory->take(-12)->values();
    $billChartMaxKwh = max($billChartHistory->pluck('energy_kwh')->map(fn ($value) => (float) $value)->all() ?: [0]);
    $billHighest = $billChartHistory->sortByDesc(fn ($bill) => (float) $bill->energy_kwh)->first();
    $billLowest = $billChartHistory->sortBy(fn ($bill) => (float) $bill->energy_kwh)->first();

    $criticalHourlyProfile = $analysis->calculateHourlyProfile(
        $analysis->appliances->where('is_critical', true)->all()
    );
    $hourlyAverage = collect($hourlyProfile)->avg() ?: 0;
    $profileAxisMaximum = $profileMaximum > 0 ? $profileMaximum * 1.15 : 1;
    $averageLineTopPercent = max(12, min(86, (1 - ($hourlyAverage / $profileAxisMaximum)) * 100));
    $peakBandLeftPercent = $peakHours->isNotEmpty() ? ((int) $peakHours->first() / 24) * 100 : 0;
    $peakBandWidthPercent = $peakHours->isNotEmpty() ? ($peakHours->count() / 24) * 100 : 0;

    $historicalFitPercent = $validationAbs === null ? null : max(0, min(100, 100 - $validationAbs));
    $connectedCapacityKw = $analysis->connected_power_va ? ((float) $analysis->connected_power_va / 1000) : null;
    $connectedUtilization = $connectedCapacityKw && $connectedCapacityKw > 0
        ? min(100, ((float) $summary['connected_load_kw'] / $connectedCapacityKw) * 100)
        : min(100, (float) $summary['load_factor_percent']);
    $peakShare = (float) $summary['connected_load_kw'] > 0
        ? min(100, ((float) $summary['peak_demand_kw'] / (float) $summary['connected_load_kw']) * 100)
        : 0;

    $categoryTotalEnergy = max(0.0001, (float) $analysis->appliances->sum(fn ($item) => $item->daily_energy_kwh));
    $categoryTop = $categoryMetrics->take(4);
    $categoryOverflowCount = max(0, $categoryMetrics->count() - $categoryTop->count());

    $lastRecalculatedDate = $analysis->exists && $analysis->updated_at
        ? $analysis->updated_at->format('d M Y')
        : 'Not calculated';
    $lastRecalculatedTime = $analysis->exists && $analysis->updated_at
        ? $analysis->updated_at->format('H:i')
        : null;

    $hourlyChartRows = collect($hourlyProfile)->map(function ($energy, $hour) use ($analysis, $criticalHourlyProfile, $peakHours, $peakDescriptor) {
        $critical = (float) ($criticalHourlyProfile[$hour] ?? 0);
        $total = (float) $energy;

        return [
            'hour' => sprintf('%02d', (int) $hour),
            'window' => sprintf('%02d:00–%02d:00', (int) $hour, (((int) $hour) + 1) % 24),
            'energy' => round($total, 4),
            'critical' => round($critical, 4),
            'standard' => round(max(0, $total - $critical), 4),
            'period' => $analysis->isDaytimeHour((int) $hour) ? 'Daytime' : 'Night',
            'isDay' => $analysis->isDaytimeHour((int) $hour),
            'isPeak' => $peakHours->contains((int) $hour) && $total > 0,
            'peakLabel' => $peakDescriptor,
        ];
    })->values();

    $billChartRows = $billChartHistory->map(function ($bill) use ($billHighest, $billLowest) {
        return [
            'month' => $bill->billing_month->format('M'),
            'monthFull' => $bill->billing_month->format('F Y'),
            'energy' => round((float) $bill->energy_kwh, 3),
            'isHighest' => $billHighest && $bill->is($billHighest),
            'isLowest' => $billLowest && $bill->is($billLowest),
        ];
    })->values();

    $hourlyChartPayload = [
        'rows' => $hourlyChartRows,
        'average' => round((float) $hourlyAverage, 4),
        'maximum' => round((float) $profileAxisMaximum, 4),
        'dayStart' => (int) $analysis->daytime_start_hour,
        'dayEnd' => (int) $analysis->daytime_end_hour,
        'peakStart' => $peakHours->isNotEmpty() ? (int) $peakHours->first() : null,
        'peakEnd' => $peakHours->isNotEmpty() ? (int) $peakHours->last() : null,
        'peakLabel' => $peakDescriptor,
    ];

    $billChartPayload = [
        'rows' => $billChartRows,
        'average' => $billAverageKwh === null ? null : round((float) $billAverageKwh, 3),
        'maximum' => round((float) $billChartMaxKwh, 3),
    ];
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-load-analysis.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-load-analysis.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxla-page">
        <section class="nxla-hero nxla-hero-luxury" data-nxla-reveal>
            <div class="nxla-hero-main">
                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="nxla-back-link">
                    <span aria-hidden="true">←</span> Project Cockpit
                </a>
                <div class="nxla-hero-title-row">
                    <div>
                        <span class="nxla-hero-kicker">Load intelligence workspace</span>
                        <h2>{{ $project->name }}</h2>
                    </div>
                </div>
                <p class="nxla-hero-subtitle">
                    Turn equipment behaviour and electricity history into one explainable demand baseline before solar sizing. Every visual below uses the current project data, not decorative sample values.
                </p>
                <div class="nxla-hero-meta">
                    <span class="nxla-chip">{{ $project->project_number }}</span>
                    <span class="nxla-chip">{{ $project->customer->name }}</span>
                    <span class="nxla-chip">{{ $analysis->source_mode_label }}</span>
                    <span class="nxla-chip">Daytime {{ sprintf('%02d:00', (int) $analysis->daytime_start_hour) }}–{{ sprintf('%02d:00', (int) $analysis->daytime_end_hour) }}</span>
                </div>

                <div class="nxla-hero-signature" aria-label="24-hour demand signature">
                    <div class="nxla-signature-head">
                        <div>
                            <span>24-hour demand signature</span>
                            <strong>{{ $hasEquipment ? 'Actual modeled profile' : 'Awaiting equipment data' }}</strong>
                        </div>
                        <div class="nxla-signature-peak">
                            <small>{{ $peakDescriptor }}</small>
                            <strong>{{ number_format((float) $summary['peak_demand_kw'], 2) }} kW · {{ $peakWindow }}</strong>
                        </div>
                    </div>
                    <div class="nxla-hero-signature-chart-shell" data-nxla-echart-shell>
                        <div
                            class="nxla-echart nxla-echart--hero-signature"
                            role="img"
                            aria-label="Interactive 24-hour modeled demand signature"
                            data-nxla-echart="hero-signature"
                            data-source="nxla-hero-signature-data"
                        ></div>
                        <div class="nxla-hero-signature-loading" data-nxla-chart-loading>
                            <span aria-hidden="true"></span>
                            <small>Rendering demand signature</small>
                        </div>
                    </div>
                    <script type="application/json" id="nxla-hero-signature-data">@json($hourlyChartPayload)</script>
                    <div class="nxla-signature-legend">
                        <span><i class="main"></i>Total demand</span>
                        <span><i class="critical"></i>Critical contribution</span>
                        <span>{{ number_format((float) $summary['day_share_percent'], 1) }}% solar-day alignment</span>
                    </div>
                </div>

                <div class="nxla-hero-actions">
                    <a href="{{ route('companies.load-analyses.index', $company) }}" class="ui-button ui-button-secondary">Analysis Directory</a>
                    @if ($canManage && $hasEquipment)
                        <a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="ui-button ui-button-secondary">Add Equipment</a>
                    @endif
                </div>
            </div>

            <aside class="nxla-hero-status nxla-hero-status-luxury">
                <div class="nxla-readiness-summary">
                    <div class="nxla-ring" style="--value: {{ $readinessPercent }};">
                        <span>{{ $readinessPercent }}%</span>
                    </div>
                    <div>
                        <span class="nxla-eyebrow nxla-eyebrow-light">Engineering readiness</span>
                        <strong>{{ $readinessComplete }} of {{ count($readinessItems) }} inputs ready</strong>
                        <p>{{ $readinessPercent === 100 ? 'The module has a complete input foundation.' : 'Complete the remaining inputs before the next engineering handoff.' }}</p>
                    </div>
                </div>
                <div class="nxla-hero-status-grid">
                    <div><span>Validation fit</span><strong>{{ $historicalFitPercent === null ? 'Pending' : number_format($historicalFitPercent, 1).'%' }}</strong></div>
                    <div><span>Equipment</span><strong>{{ number_format((int) $summary['appliance_count']) }} groups</strong></div>
                    <div><span>Bill coverage</span><strong>{{ number_format((int) $summary['bill_count']) }} months</strong></div>
                    <div class="nxla-recalculated-metric">
                        <span>Recalculated</span>
                        <strong>{{ $lastRecalculatedDate }} @if($lastRecalculatedTime)<small>{{ $lastRecalculatedTime }}</small>@endif</strong>
                    </div>
                </div>
                <div class="nxla-hero-next">
                    <div>
                        <span>Next recommended move</span>
                        <strong>{{ $nextActionLabel }}</strong>
                    </div>
                    <a href="{{ $nextActionRoute }}" @if($nextActionTab) data-nxla-go-tab="{{ $nextActionTab }}" @endif>
                        Continue <span aria-hidden="true">→</span>
                    </a>
                </div>
            </aside>
        </section>

        @if (! $analysis->exists)
            <div class="ui-alert ui-alert-warning" role="status">
                <div>This analysis has not been started. An authorized project owner can add the first load input.</div>
            </div>
        @endif

        <section class="nxla-kpi-grid nxla-kpi-grid-luxury" aria-label="Load summary" data-nxla-reveal>
            <article class="nxla-kpi" style="--metric-progress: {{ number_format($connectedUtilization, 2, '.', '') }}%;">
                <div class="nxla-kpi-head">
                    <span class="nxla-kpi-icon nxla-kpi-icon--connected" aria-hidden="true">
                        <x-nexorin.icons.connected-load />
                    </span>
                    <span class="nxla-kpi-label">Connected load</span>
                </div>
                <strong class="nxla-kpi-value"><span data-nxla-value="{{ number_format((float) $summary['connected_load_kw'], 2, '.', '') }}" data-decimals="2">{{ number_format((float) $summary['connected_load_kw'], 2) }}</span> <small>kW</small></strong>
                <p>Starting demand {{ number_format((float) $summary['starting_load_kw'], 2) }} kW</p>
                <span class="nxla-kpi-progress"><span></span></span>
            </article>
            <article class="nxla-kpi" style="--metric-progress: {{ number_format($historicalFitPercent ?? 0, 2, '.', '') }}%;">
                <div class="nxla-kpi-head">
                    <span class="nxla-kpi-icon nxla-kpi-icon--daily" aria-hidden="true">
                        <x-nexorin.icons.daily-energy />
                    </span>
                    <span class="nxla-kpi-label">Daily energy</span>
                </div>
                <strong class="nxla-kpi-value"><span data-nxla-value="{{ number_format((float) $summary['daily_energy_kwh'], 2, '.', '') }}" data-decimals="2">{{ number_format((float) $summary['daily_energy_kwh'], 2) }}</span> <small>kWh/day</small></strong>
                <p>{{ number_format((float) $summary['monthly_energy_kwh'], 1) }} kWh per {{ $analysis->billing_days }} days</p>
                <span class="nxla-kpi-progress"><span></span></span>
            </article>
            <article class="nxla-kpi" style="--metric-progress: {{ number_format($peakShare, 2, '.', '') }}%;">
                <div class="nxla-kpi-head">
                    <span class="nxla-kpi-icon nxla-kpi-icon--peak" aria-hidden="true">
                        <x-nexorin.icons.coincident-peak />
                    </span>
                    <span class="nxla-kpi-label">Coincident peak</span>
                </div>
                <strong class="nxla-kpi-value"><span data-nxla-value="{{ number_format((float) $summary['peak_demand_kw'], 2, '.', '') }}" data-decimals="2">{{ number_format((float) $summary['peak_demand_kw'], 2) }}</span> <small>kW</small></strong>
                <p>Estimated peak window {{ $peakWindow }}</p>
                <span class="nxla-kpi-progress"><span></span></span>
            </article>
            <article class="nxla-kpi" style="--metric-progress: {{ number_format((float) $summary['day_share_percent'], 2, '.', '') }}%;">
                <div class="nxla-kpi-head">
                    <span class="nxla-kpi-icon nxla-kpi-icon--daylight" aria-hidden="true">
                        <x-nexorin.icons.daytime-share />
                    </span>
                    <span class="nxla-kpi-label">Daytime share</span>
                </div>
                @if ($billOnly && ! $hasEquipment)
                    <strong class="nxla-kpi-value is-not-applicable">N/A</strong>
                    <p>Hourly daytime share requires an optional equipment profile.</p>
                    <span class="nxla-kpi-progress"><span style="width:0"></span></span>
                @else
                    <strong class="nxla-kpi-value"><span data-nxla-value="{{ number_format((float) $summary['day_share_percent'], 1, '.', '') }}" data-decimals="1">{{ number_format((float) $summary['day_share_percent'], 1) }}</span> <small>%</small></strong>
                    <p>Night share {{ number_format((float) $summary['night_share_percent'], 1) }}%</p>
                    <span class="nxla-kpi-progress"><span></span></span>
                @endif
            </article>
        </section>

        <div class="nxla-tabs" role="tablist" aria-label="Load analysis sections" data-nxla-tabs>
            <button type="button" class="nxla-tab is-active" role="tab" aria-selected="true" data-nxla-tab="overview">Overview</button>
            <button type="button" class="nxla-tab" role="tab" aria-selected="false" data-nxla-tab="equipment">Equipment <span>{{ number_format((int) $summary['appliance_count']) }}</span></button>
            <button type="button" class="nxla-tab" role="tab" aria-selected="false" data-nxla-tab="bills">Electricity Bills <span>{{ number_format((int) $summary['bill_count']) }}</span></button>
            <button type="button" class="nxla-tab" role="tab" aria-selected="false" data-nxla-tab="settings">Settings</button>
        </div>

        <section class="nxla-panel is-active" data-nxla-panel="overview" id="overview">
            <div class="nxla-overview-top">
                <article class="nxla-card nxla-profile-card">
                    <header class="nxla-card-head">
                        <div>
                            <span class="nxla-eyebrow">Demand profile</span>
                            <h3>24-Hour Load Profile</h3>
                            <p>Hourly modeled energy after quantity, power, schedule, and usage factors are applied.</p>
                        </div>
                        <span class="nxla-chip is-live">{{ number_format((int) $summary['appliance_count']) }} equipment groups</span>
                    </header>
                    <div class="nxla-card-body nxla-card-body-tight">
                        @if (! $hasEquipment)
                            <div class="nxla-empty-state">
                                <div>
                                    <h4>No load profile yet</h4>
                                    <p>{{ $billOnly ? 'Bills-only mode is valid for energy sizing. Add equipment only when an hourly demand profile or daytime-share study is required.' : 'Add the first equipment group to generate a demand curve. Start with the largest or most predictable loads.' }}</p>
                                    @if ($canManage && ! $billOnly)
                                        <a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="ui-button ui-button-primary">Add first equipment</a>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="nxla-profile-meta">
                                <div class="nxla-meta-box"><span>Peak window</span><strong>{{ $peakWindow }}</strong></div>
                                <div class="nxla-meta-box"><span>Load factor</span><strong>{{ number_format((float) $summary['load_factor_percent'], 1) }}%</strong></div>
                                <div class="nxla-meta-box"><span>Critical energy</span><strong>{{ number_format((float) $summary['critical_daily_energy_kwh'], 2) }} kWh/day</strong></div>
                            </div>

                            <div class="nxla-chart-caption">
                                <div>
                                    <span>Hourly energy</span>
                                    <strong>kWh per modeled hour</strong>
                                </div>
                                <div class="nxla-chart-caption-meta">
                                    <span>Average {{ number_format($hourlyAverage, 2) }} kWh</span>
                                    <span>Daytime {{ sprintf('%02d:00', (int) $analysis->daytime_start_hour) }}–{{ sprintf('%02d:00', (int) $analysis->daytime_end_hour) }}</span>
                                </div>
                            </div>
                            <div class="nxla-echart-shell nxla-echart-shell--hourly" data-nxla-echart-shell>
                                <div
                                    id="nxla-hourly-echart"
                                    class="nxla-echart nxla-echart--hourly"
                                    role="img"
                                    aria-label="Interactive 24-hour modeled load profile"
                                    data-nxla-echart="hourly"
                                    data-source="nxla-hourly-chart-data"
                                ></div>
                                <div class="nxla-echart-loading" data-nxla-chart-loading>
                                    <span aria-hidden="true"></span>
                                    <small>Rendering engineering profile</small>
                                </div>
                            </div>
                            <script type="application/json" id="nxla-hourly-chart-data">@json($hourlyChartPayload)</script>
                            <div class="nxla-legend nxla-legend-premium">
                                <span><i></i>Standard demand</span>
                                <span><i class="critical"></i>Critical demand</span>
                                <span><i class="day"></i>Configured daytime</span>
                                <span><i class="peak"></i>Estimated peak</span>
                            </div>
                            <div class="nxla-chart-inspector" data-nxla-chart-inspector>
                                <div><span>Selected hour</span><strong data-nxla-inspector-hour>{{ $peakWindow }}</strong></div>
                                <div><span>Modeled demand</span><strong data-nxla-inspector-energy>{{ number_format((float) ($hourlyProfile[$peakHour] ?? 0), 3) }} kWh</strong></div>
                                <div><span>Critical share</span><strong data-nxla-inspector-critical>{{ number_format((float) ($criticalHourlyProfile[$peakHour] ?? 0), 3) }} kWh</strong></div>
                                <div><span>Classification</span><strong data-nxla-inspector-period>{{ $analysis->isDaytimeHour($peakHour) ? 'Daytime' : 'Night' }} · {{ $peakDescriptor }}</strong></div>
                                <button type="button" class="nxla-inspector-action" data-nxla-view-hour-equipment data-hour="{{ $peakHour }}">View active equipment <span aria-hidden="true">→</span></button>
                            </div>
                        @endif
                    </div>
                </article>

                <article class="nxla-card nxla-checklist-card">
                    <header class="nxla-card-head">
                        <div>
                            <span class="nxla-eyebrow">Readiness</span>
                            <h3>Engineering checklist</h3>
                            <p>Four compact signals determine whether the profile is ready to move forward.</p>
                        </div>
                    </header>
                    <div class="nxla-card-body nxla-card-body-tight">
                        <div class="nxla-readiness-list">
                            @foreach ($readinessItems as $item)
                                <div class="nxla-readiness-item {{ $item['complete'] ? 'is-complete' : '' }}">
                                    <span class="nxla-readiness-icon">{{ $item['complete'] ? '✓' : '—' }}</span>
                                    <div>
                                        <strong>{{ $item['label'] }}</strong>
                                        <small>{{ $item['meta'] }}</small>
                                    </div>
                                    <span>{{ $item['complete'] ? 'Ready' : 'Pending' }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="nxla-readiness-footer {{ $readinessPercent === 100 ? 'is-ready' : '' }}">
                            <div>
                                <span>{{ $readinessPercent === 100 ? 'Engineering handoff' : 'Remaining work' }}</span>
                                <strong>{{ $readinessPercent === 100 ? 'Ready for Location & Solar' : (count($readinessItems) - $readinessComplete).' inputs still pending' }}</strong>
                            </div>
                            <span class="nxla-readiness-footer-icon">{{ $readinessPercent === 100 ? '✓' : '→' }}</span>
                        </div>
                    </div>
                </article>
            </div>

            <div class="nxla-status-rail">
                <div class="nxla-status-rail-copy">
                    <span class="nxla-eyebrow">Next module</span>
                    <h4>{{ $nextActionLabel }}</h4>
                    <p>
                        @if (! $hasEquipment)
                            Add equipment first so the system can generate a believable hourly demand curve.
                        @elseif (! $hasBills)
                            Add historical monthly use to validate the modeled equipment estimate.
                        @elseif ($validationAbs !== null && $validationAbs > 10)
                            The modeled and historical values differ by {{ number_format($validationAbs, 1) }}%. Review schedules and usage factors first.
                        @else
                            The load-analysis foundation is validated and ready for the next engineering step.
                        @endif
                    </p>
                </div>
                <div class="nxla-status-rail-metrics">
                    <div><span>Monthly estimate</span><strong>{{ number_format((float) $summary['monthly_energy_kwh'], 1) }} kWh</strong></div>
                    <div><span>Historical fit</span><strong>{{ $historicalFitPercent === null ? 'Pending' : number_format($historicalFitPercent, 1).'%' }}</strong></div>
                    <div><span>Validation</span><strong>{{ $validationStatus }}</strong></div>
                </div>
                <a href="{{ $nextActionRoute }}" class="ui-button ui-button-primary" @if($nextActionTab) data-nxla-go-tab="{{ $nextActionTab }}" @endif>Continue <span aria-hidden="true">→</span></a>
            </div>
        </section>

        <section class="nxla-panel" data-nxla-panel="equipment" id="equipment" hidden>
            <article class="nxla-card">
                <header class="nxla-card-head">
                    <div>
                        <span class="nxla-eyebrow">Bottom-up model</span>
                        <h3>Equipment and appliance loads</h3>
                        <p>Search, filter, and review the equipment groups that form the 24-hour demand profile.</p>
                    </div>
                    @if ($canManage)
                        <a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="ui-button ui-button-primary">Add Equipment</a>
                    @endif
                </header>
                <div class="nxla-card-body nxla-card-body-tight">
                    @if ($categoryTop->isNotEmpty())
                        <div class="nxla-category-summary">
                            <div class="nxla-category-distribution" aria-label="Daily energy distribution by category">
                                @foreach ($categoryTop as $index => $category)
                                    @php $categoryShare = max(2, ((float) $category['daily_energy'] / $categoryTotalEnergy) * 100); @endphp
                                    <span class="tone-{{ ($index % 4) + 1 }}" style="--share: {{ number_format($categoryShare, 2, '.', '') }}%;" title="{{ $category['label'] }} · {{ number_format($categoryShare, 1) }}%"></span>
                                @endforeach
                            </div>
                            <div class="nxla-category-legend-compact">
                                @foreach ($categoryTop as $index => $category)
                                    @php $categoryShare = ((float) $category['daily_energy'] / $categoryTotalEnergy) * 100; @endphp
                                    <span><i class="tone-{{ ($index % 4) + 1 }}"></i><b>{{ $category['label'] }}</b>{{ number_format($categoryShare, 1) }}%</span>
                                @endforeach
                                @if ($categoryOverflowCount > 0)
                                    <span class="nxla-category-more">+{{ $categoryOverflowCount }} more</span>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($analysis->appliances->isEmpty())
                        <div class="nxla-empty-state">
                            <div>
                                <h4>No equipment has been added</h4>
                                <p>Record the main loads first, then refine smaller appliances after the core demand shape is visible.</p>
                            </div>
                        </div>
                    @else
                        <div class="nxla-toolbar">
                            <div class="ui-field">
                                <label class="ui-label" for="nxla-equipment-search">Search equipment</label>
                                <input id="nxla-equipment-search" type="search" class="ui-input" placeholder="Equipment name" data-nxla-equipment-search>
                            </div>
                            <div class="ui-field">
                                <label class="ui-label" for="nxla-equipment-category">Category</label>
                                <select id="nxla-equipment-category" class="ui-select" data-nxla-equipment-category>
                                    <option value="">All categories</option>
                                    @foreach ($analysis->appliances->pluck('category')->filter()->unique()->sort()->values() as $category)
                                        <option value="{{ strtolower($category) }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="nxla-toggle"><input type="checkbox" data-nxla-equipment-critical> Critical only</label>
                            <span class="nxla-time-filter" data-nxla-time-filter hidden>Active at <strong data-nxla-time-filter-label></strong><button type="button" data-nxla-clear-time-filter aria-label="Clear hour filter">×</button></span>
                        </div>

                        <div class="nxla-table-shell">
                            <div class="ui-table-scroll">
                                <table class="ui-table">
                                    <thead>
                                        <tr>
                                            <th>Equipment</th>
                                            <th>Qty × Power</th>
                                            <th>Schedule</th>
                                            <th>Daily Energy</th>
                                            <th>Critical</th>
                                            @if ($canManage)<th aria-label="Actions"></th>@endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($analysis->appliances as $appliance)
                                            <tr data-nxla-equipment-row data-name="{{ strtolower($appliance->name) }}" data-category="{{ strtolower($appliance->category ?: 'uncategorized') }}" data-critical="{{ $appliance->is_critical ? '1' : '0' }}" data-start-hour="{{ (int) $appliance->start_hour }}" data-duration="{{ number_format((float) $appliance->operating_hours_per_day, 4, '.', '') }}">
                                                <td>
                                                    <div class="nxla-table-primary">{{ $appliance->name }}</div>
                                                    <div class="nxla-table-secondary">{{ $appliance->category ?: 'Uncategorized' }} · usage {{ number_format((float) $appliance->usage_factor * 100, 0) }}% · start {{ number_format((float) $appliance->starting_multiplier, 2) }}×</div>
                                                </td>
                                                <td>{{ number_format($appliance->quantity) }} × {{ number_format((float) $appliance->rated_power_w, 1) }} W</td>
                                                <td>{{ $appliance->schedule_label }}</td>
                                                <td>{{ number_format($appliance->daily_energy_kwh, 3) }} kWh</td>
                                                <td><span class="nxla-row-badge {{ $appliance->is_critical ? 'critical' : 'standard' }}">{{ $appliance->is_critical ? 'Critical' : 'Standard' }}</span></td>
                                                @if ($canManage)
                                                    <td>
                                                        <div class="nxla-actions">
                                                            <a
                                                                href="{{ route('companies.load-analyses.appliances.edit', [$company, $project, $appliance]) }}"
                                                                class="nxla-icon-action"
                                                                title="Edit equipment"
                                                                aria-label="Edit {{ $appliance->name }}"
                                                            >
                                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.121 2.121 0 0 0-3-3L5 17v3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="m14.5 7.5 3 3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                            </a>
                                                            <form method="POST" action="{{ route('companies.load-analyses.appliances.destroy', [$company, $project, $appliance]) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button
                                                                    type="submit"
                                                                    class="nxla-icon-action is-danger"
                                                                    title="Remove equipment"
                                                                    aria-label="Remove {{ $appliance->name }}"
                                                                    data-confirm="Remove this equipment load?"
                                                                >
                                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                        <tr data-nxla-equipment-empty hidden>
                                            <td colspan="{{ $canManage ? 6 : 5 }}" style="text-align:center;color:#8190a4;">No equipment matches this filter.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </article>
        </section>

        <section class="nxla-panel" data-nxla-panel="bills" id="bills" hidden>
            <div class="nxla-bill-overview-grid">
                <article class="nxla-card nxla-bill-trend-card nxla-bill-trend-premium">
                    <header class="nxla-card-head">
                        <div>
                            <span class="nxla-eyebrow">Historical demand</span>
                            <h3>Electricity trend</h3>
                            <p>Twelve months of consumption condensed into one readable engineering signal, with average, high, low, and seasonal variation kept in view.</p>
                        </div>
                        <span class="nxla-chip {{ $validationTone }}">{{ $validationStatus }}</span>
                    </header>
                    <div class="nxla-card-body nxla-card-body-tight">
                        @if ($billHistory->isEmpty())
                            <div class="nxla-empty-state nxla-empty-state-compact">
                                <div>
                                    <h4>No bill history yet</h4>
                                    <p>Add at least one monthly record. Six to twelve months gives a stronger baseline.</p>
                                </div>
                            </div>
                        @else
                            <div class="nxla-bill-kpis nxla-bill-kpis-premium">
                                <div><span>Average</span><strong>{{ number_format((float) $billAverageKwh, 1) }} kWh</strong></div>
                                <div><span>Highest</span><strong>{{ number_format((float) $billMaxKwh, 1) }} kWh</strong></div>
                                <div><span>Lowest</span><strong>{{ number_format((float) $billMinKwh, 1) }} kWh</strong></div>
                                <div><span>Variation</span><strong>{{ $billVariationPercent === null ? 'Not available' : number_format((float) $billVariationPercent, 1).'%' }}</strong></div>
                            </div>
                            <div class="nxla-echart-shell nxla-echart-shell--bills" data-nxla-echart-shell>
                                <div
                                    id="nxla-bill-echart"
                                    class="nxla-echart nxla-echart--bills"
                                    role="img"
                                    aria-label="Interactive monthly electricity consumption trend"
                                    data-nxla-echart="bills"
                                    data-source="nxla-bill-chart-data"
                                ></div>
                                <div class="nxla-echart-loading" data-nxla-chart-loading>
                                    <span aria-hidden="true"></span>
                                    <small>Rendering consumption trend</small>
                                </div>
                            </div>
                            <script type="application/json" id="nxla-bill-chart-data">@json($billChartPayload)</script>
                            <div class="nxla-bill-chart-legend" aria-label="Electricity trend legend">
                                <span><i class="is-average"></i>Average reference</span>
                                <span><i class="is-high"></i>Highest month</span>
                                <span><i class="is-low"></i>Lowest month</span>
                            </div>
                        @endif
                    </div>
                </article>

                <article class="nxla-validation-box nxla-validation-card nxla-validation-card-premium">
                    <div class="nxla-validation-head">
                        <div>
                            <span class="nxla-eyebrow">Bill validation</span>
                            <strong>{{ $validationStatus }}</strong>
                        </div>
                        <div class="nxla-validation-score {{ $validationTone }}">
                            <span>Model fit</span>
                            <strong>{{ $historicalFitPercent === null ? '—' : number_format((float) $historicalFitPercent, 1).'%' }}</strong>
                        </div>
                    </div>
                    <p>
                        @if ($summary['average_bill_kwh'] === null)
                            Historical consumption is not available yet.
                        @elseif ($validationVariance === null)
                            Average bill consumption is {{ number_format((float) $summary['average_bill_kwh'], 1) }} kWh. Add equipment to produce the comparison.
                        @else
                            The equipment model estimates {{ number_format((float) $summary['equipment_monthly_energy_kwh'], 1) }} kWh against a historical average of {{ number_format((float) $summary['average_bill_kwh'], 1) }} kWh. Difference: {{ number_format($validationAbs, 1) }}%.
                        @endif
                    </p>
                    <div class="nxla-validation-meter" aria-hidden="true">
                        <span style="--fit: {{ number_format((float) ($historicalFitPercent ?? 0), 2, '.', '') }}%;"></span>
                    </div>
                    <div class="nxla-mini-grid">
                        <div class="nxla-mini"><span>Average bill</span><strong>{{ $summary['average_bill_amount'] === null ? 'Not available' : 'Rp '.number_format((float) $summary['average_bill_amount'], 0, ',', '.') }}</strong></div>
                        <div class="nxla-mini"><span>Historical rate</span><strong>{{ $summary['effective_rate_per_kwh'] === null ? 'Not available' : 'Rp '.number_format((float) $summary['effective_rate_per_kwh'], 0, ',', '.').' / kWh' }}</strong></div>
                        <div class="nxla-mini"><span>Annual energy</span><strong>{{ number_format((float) $summary['annual_energy_kwh'], 1) }} kWh</strong></div>
                        <div class="nxla-mini"><span>Critical energy</span><strong>{{ number_format((float) $summary['critical_daily_energy_kwh'], 2) }} kWh/day</strong></div>
                    </div>
                    <div class="nxla-validation-footnote">
                        <span class="nxla-status-dot {{ $validationTone }}"></span>
                        <p>{{ $validationAbs !== null && $validationAbs <= 5 ? 'Excellent alignment. The modeled demand is closely supported by the recorded bills.' : ($validationAbs !== null && $validationAbs <= 10 ? 'Usable alignment. Review assumptions only if the project needs a tighter commercial baseline.' : 'Review operating hours, usage factors, and the completeness of bill history before continuing.') }}</p>
                    </div>
                </article>
            </div>

            <article class="nxla-card nxla-processing nxla-add-bill-card nxla-add-bill-strip" data-processing-label="Saving bill…">
                <header class="nxla-add-bill-strip-head">
                    <div>
                        <span class="nxla-eyebrow">Add record</span>
                        <h3>New monthly bill</h3>
                        <p>Record one month without breaking the analysis flow. Energy is required; cost and peak demand add commercial and electrical context.</p>
                    </div>
                    <div class="nxla-bill-rate-preview" data-nxla-bill-rate-preview-shell data-historical-rate="{{ $summary['effective_rate_per_kwh'] ?? '' }}">
                        <span>Effective rate preview</span>
                        <strong data-nxla-bill-rate-preview>Waiting for values</strong>
                        <small data-nxla-bill-rate-context>Enter energy and bill amount to calculate.</small>
                    </div>
                </header>
                <div class="nxla-card-body nxla-card-body-tight">
                    @if ($canManage)
                        <form method="POST" action="{{ route('companies.load-analyses.bills.store', [$company, $project]) }}" class="nxla-bill-form nxla-bill-form-luxury nxla-bill-form-strip" data-nxla-processing-form data-nxla-bill-form>
                            @csrf
                            <div class="ui-field nxla-bill-field nxla-bill-field-month">
                                <label class="ui-label" for="billing_month">Month</label>
                                <input id="billing_month" class="ui-input @error('billing_month') is-invalid @enderror" type="month" name="billing_month" value="{{ old('billing_month', now()->subMonth()->format('Y-m')) }}" required>
                                @error('billing_month')<div class="ui-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="ui-field nxla-bill-field nxla-bill-field-energy">
                                <label class="ui-label" for="energy_kwh">Energy (kWh)</label>
                                <input id="energy_kwh" class="ui-input @error('energy_kwh') is-invalid @enderror" type="number" name="energy_kwh" value="{{ old('energy_kwh') }}" min="0.001" step="0.001" required>
                                @error('energy_kwh')<div class="ui-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="ui-field nxla-bill-field nxla-bill-field-amount">
                                <label class="ui-label" for="bill_amount">Bill amount (IDR)</label>
                                <input id="bill_amount" class="ui-input @error('bill_amount') is-invalid @enderror" type="number" name="bill_amount" value="{{ old('bill_amount') }}" min="0" step="1">
                                @error('bill_amount')<div class="ui-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="ui-field nxla-bill-field nxla-bill-field-peak">
                                <label class="ui-label" for="peak_demand_kw">Peak demand (kW)</label>
                                <input id="peak_demand_kw" class="ui-input @error('peak_demand_kw') is-invalid @enderror" type="number" name="peak_demand_kw" value="{{ old('peak_demand_kw') }}" min="0" step="0.001">
                                @error('peak_demand_kw')<div class="ui-error">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="ui-button ui-button-primary nxla-bill-submit" data-processing-text="Saving bill…">
                                <span>Save Bill</span>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </form>
                    @else
                        <div class="nxla-empty-state nxla-empty-state-compact"><div><h4>Read-only access</h4><p>You can review bill history but cannot add records.</p></div></div>
                    @endif
                </div>
            </article>

            @if ($analysis->electricityBills->isNotEmpty())
                <article class="nxla-card nxla-bill-registry-card nxla-bill-registry-premium">
                    <header class="nxla-card-head">
                        <div>
                            <span class="nxla-eyebrow">Monthly registry</span>
                            <h3>Recorded electricity bills</h3>
                            <p>A full-width audit trail for historical consumption, cost, effective tariff, and recorded peak demand.</p>
                        </div>
                        <span class="nxla-chip is-muted">{{ number_format((int) $summary['bill_count']) }} months</span>
                    </header>
                    <div class="nxla-card-body nxla-card-body-tight">
                        <div class="nxla-table-shell nxla-bill-table-shell">
                            <div class="ui-table-scroll">
                                <table class="ui-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Month</th>
                                            <th scope="col">Energy</th>
                                            <th scope="col">Bill Amount</th>
                                            <th scope="col">Effective Rate</th>
                                            <th scope="col">Peak Demand</th>
                                            @if($canManage)<th scope="col" aria-label="Actions"></th>@endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($analysis->electricityBills as $bill)
                                            @php
                                                $isHighestBill = $billRegistryHighest && $bill->is($billRegistryHighest);
                                                $isLowestBill = $billRegistryLowest && $bill->is($billRegistryLowest);
                                            @endphp
                                            <tr class="{{ $isHighestBill ? 'is-highest' : '' }} {{ $isLowestBill ? 'is-lowest' : '' }}">
                                                <td>
                                                    <div class="nxla-registry-month">
                                                        <strong>{{ $bill->billing_month->format('M Y') }}</strong>
                                                        @if ($isHighestBill)
                                                            <span class="nxla-registry-badge is-highest">Highest</span>
                                                        @elseif ($isLowestBill)
                                                            <span class="nxla-registry-badge is-lowest">Lowest</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="nxla-registry-number">{{ number_format((float) $bill->energy_kwh, 3) }} <small>kWh</small></td>
                                                <td class="nxla-registry-number">{{ $bill->bill_amount === null ? 'Not provided' : 'Rp '.number_format((float) $bill->bill_amount, 0, ',', '.') }}</td>
                                                <td class="nxla-registry-number">{{ $bill->effective_rate === null ? 'Not available' : 'Rp '.number_format((float) $bill->effective_rate, 0, ',', '.').' / kWh' }}</td>
                                                <td class="nxla-registry-number">{{ $bill->peak_demand_kw === null ? 'Not provided' : number_format((float) $bill->peak_demand_kw, 3).' kW' }}</td>
                                                @if ($canManage)
                                                    <td class="nxla-registry-actions">
                                                        <form method="POST" action="{{ route('companies.load-analyses.bills.destroy', [$company, $project, $bill]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="nxla-icon-action is-danger" title="Remove bill" aria-label="Remove bill for {{ $bill->billing_month->format('F Y') }}" data-confirm="Remove this bill record?">
                                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                            </button>
                                                        </form>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </article>
            @endif
        </section>

        <section class="nxla-panel" data-nxla-panel="settings" id="settings" hidden>
            <div class="nxla-settings-grid nxla-settings-grid-refined">
                <article class="nxla-card nxla-processing nxla-settings-main" data-processing-label="Recalculating…">
                    <header class="nxla-card-head">
                        <div>
                            <span class="nxla-eyebrow">Calculation assumptions</span>
                            <h3>Analysis settings</h3>
                            <p>Keep the assumptions aligned with the customer’s real electrical context.</p>
                        </div>
                        <span class="nxla-chip is-muted" data-nxla-settings-state>Saved</span>
                    </header>
                    <div class="nxla-card-body nxla-card-body-tight">
                        @if ($canManage)
                            <form method="POST" action="{{ route('companies.load-analyses.update', [$company, $project]) }}" class="nxla-settings-form" data-nxla-processing-form data-nxla-settings-form>
                                @csrf
                                @method('PUT')
                                <div class="ui-form-grid">
                                    <div class="ui-field ui-form-span-2">
                                        <label class="ui-label" for="source_mode">Primary data method</label>
                                        <select id="source_mode" class="ui-select @error('source_mode') is-invalid @enderror" name="source_mode" required>
                                            <option value="combined" @selected(old('source_mode', $analysis->source_mode) === 'combined')>Combined validation</option>
                                            <option value="equipment" @selected(old('source_mode', $analysis->source_mode) === 'equipment')>Equipment list only</option>
                                            <option value="bill" @selected(old('source_mode', $analysis->source_mode) === 'bill')>Electricity bills only</option>
                                        </select>
                                        <div class="ui-help">Combined validation is usually easiest to defend because it compares modeled demand with actual consumption.</div>
                                        @error('source_mode')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="ui-field">
                                        <label class="ui-label" for="connected_power_va">Connected power (VA)</label>
                                        <input id="connected_power_va" class="ui-input @error('connected_power_va') is-invalid @enderror" type="number" name="connected_power_va" value="{{ old('connected_power_va', $analysis->connected_power_va) }}" min="1">
                                        @error('connected_power_va')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="ui-field">
                                        <label class="ui-label" for="supply_phase">Supply phase</label>
                                        <select id="supply_phase" class="ui-select @error('supply_phase') is-invalid @enderror" name="supply_phase" required>
                                            <option value="single_phase" @selected(old('supply_phase', $analysis->supply_phase) === 'single_phase')>Single-phase</option>
                                            <option value="three_phase" @selected(old('supply_phase', $analysis->supply_phase) === 'three_phase')>Three-phase</option>
                                        </select>
                                        @error('supply_phase')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="ui-field">
                                        <label class="ui-label" for="tariff_group">Tariff group</label>
                                        <input id="tariff_group" class="ui-input @error('tariff_group') is-invalid @enderror" type="text" name="tariff_group" value="{{ old('tariff_group', $analysis->tariff_group) }}" maxlength="50" placeholder="Example: R-2/TR">
                                        @error('tariff_group')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="ui-field">
                                        <label class="ui-label" for="electricity_rate_per_kwh">Reference tariff (IDR/kWh)</label>
                                        <input id="electricity_rate_per_kwh" class="ui-input @error('electricity_rate_per_kwh') is-invalid @enderror" type="number" name="electricity_rate_per_kwh" value="{{ old('electricity_rate_per_kwh', $analysis->electricity_rate_per_kwh) }}" min="0" step="0.01">
                                        @error('electricity_rate_per_kwh')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="ui-field">
                                        <label class="ui-label" for="billing_days">Billing days</label>
                                        <input id="billing_days" class="ui-input @error('billing_days') is-invalid @enderror" type="number" name="billing_days" value="{{ old('billing_days', $analysis->billing_days) }}" min="1" max="366" required>
                                        @error('billing_days')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="ui-field">
                                        <label class="ui-label" for="daytime_start_hour">Daytime starts</label>
                                        <select id="daytime_start_hour" class="ui-select @error('daytime_start_hour') is-invalid @enderror" name="daytime_start_hour" required>
                                            @for ($hour = 0; $hour < 24; $hour++)
                                                <option value="{{ $hour }}" @selected((int) old('daytime_start_hour', $analysis->daytime_start_hour) === $hour)>{{ sprintf('%02d:00', $hour) }}</option>
                                            @endfor
                                        </select>
                                        @error('daytime_start_hour')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="ui-field">
                                        <label class="ui-label" for="daytime_end_hour">Daytime ends</label>
                                        <select id="daytime_end_hour" class="ui-select @error('daytime_end_hour') is-invalid @enderror" name="daytime_end_hour" required>
                                            @for ($hour = 0; $hour < 24; $hour++)
                                                <option value="{{ $hour }}" @selected((int) old('daytime_end_hour', $analysis->daytime_end_hour) === $hour)>{{ sprintf('%02d:00', $hour) }}</option>
                                            @endfor
                                        </select>
                                        @error('daytime_end_hour')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="ui-field ui-form-span-2">
                                        <label class="ui-label" for="notes">Analysis notes</label>
                                        <textarea id="notes" class="ui-textarea @error('notes') is-invalid @enderror" name="notes" maxlength="5000" placeholder="Data quality, assumptions, operating exceptions, or survey limitations">{{ old('notes', $analysis->notes) }}</textarea>
                                        @error('notes')<div class="ui-error">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="ui-form-actions">
                                    <button type="submit" class="ui-button ui-button-primary" data-processing-text="Recalculating…">Save and Recalculate</button>
                                </div>
                            </form>
                        @else
                            <dl class="ui-info-grid">
                                <div class="ui-info-item"><dt>Method</dt><dd>{{ $analysis->source_mode_label }}</dd></div>
                                <div class="ui-info-item"><dt>Connected Power</dt><dd>{{ $analysis->connected_power_va ? number_format($analysis->connected_power_va).' VA' : 'Not provided' }}</dd></div>
                                <div class="ui-info-item"><dt>Supply</dt><dd>{{ $analysis->supply_phase_label }}</dd></div>
                                <div class="ui-info-item"><dt>Tariff Group</dt><dd>{{ $analysis->tariff_group ?: 'Not provided' }}</dd></div>
                            </dl>
                        @endif
                    </div>
                </article>

                <div class="nxla-stack nxla-settings-side">
                    <article class="nxla-card">
                        <header class="nxla-card-head">
                            <div>
                                <span class="nxla-eyebrow">Configuration summary</span>
                                <h3>Readable assumptions</h3>
                                <p>A compact interpretation of the values currently driving the calculation.</p>
                            </div>
                        </header>
                        <div class="nxla-card-body nxla-card-body-tight">
                            <div class="nxla-insight-grid">
                                <article class="nxla-insight"><span>Method</span><strong data-nxla-summary-method>{{ $analysis->source_mode_label }}</strong><p>How daily energy is selected.</p></article>
                                <article class="nxla-insight"><span>Electrical service</span><strong data-nxla-summary-supply>{{ $analysis->supply_phase_label }}</strong><p>{{ $analysis->connected_power_va ? number_format($analysis->connected_power_va).' VA' : 'Connected power not set' }}</p></article>
                                <article class="nxla-insight"><span>Billing period</span><strong data-nxla-summary-billing>{{ $analysis->billing_days }} days</strong><p>Used for monthly and annual projections.</p></article>
                                <article class="nxla-insight"><span>Solar daytime</span><strong data-nxla-summary-daytime>{{ sprintf('%02d:00', (int) $analysis->daytime_start_hour) }}–{{ sprintf('%02d:00', (int) $analysis->daytime_end_hour) }}</strong><p>Used to calculate daytime energy share.</p></article>
                            </div>
                        </div>
                    </article>
                    <article class="nxla-decision-card nxla-recalculation-impact">
                        <span class="nxla-eyebrow">Recalculation impact</span>
                        <h4>What changes after saving</h4>
                        <ul>
                            <li>Monthly and annual energy estimates</li>
                            <li>Daytime and nighttime demand share</li>
                            <li>Load factor and hourly peak interpretation</li>
                            <li>Bill-validation variance and readiness state</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>
    </div>
@endsection
