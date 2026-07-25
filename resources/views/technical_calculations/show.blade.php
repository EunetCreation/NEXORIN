@extends('layouts.app')

@section('title', 'Technical Calculation')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Technical Calculation')
@section('suppress-global-feedback', true)

@php
    $hasResults = $calculation->calculated_at !== null && ! empty($calculation->results);
    $isVerified = $hasResults && $calculation->result_status === 'verified';
    $isStale = $calculation->exists && $calculation->isStale($project);
    $lockInputs = $isVerified && ! $errors->any();

    $resultDefaults = [
        'required_pv_kw' => 0,
        'installed_pv_kw' => 0,
        'module_count' => 0,
        'area_limited_module_count' => 0,
        'inverter_count' => 0,
        'total_inverter_ac_kw' => 0,
        'dc_ac_ratio' => 0,
        'battery_nominal_kwh' => 0,
        'battery_minimum_power_kw' => 0,
        'estimated_daily_generation_kwh' => 0,
        'annual_energy_kwh' => 0,
        'minimum_modules_per_string' => 0,
        'maximum_modules_per_string' => 0,
        'modules_per_string' => 0,
        'string_count' => 0,
        'string_voc_cold_v' => 0,
        'string_vmp_hot_v' => 0,
        'dc_input_current_a' => 0,
        'dc_voltage_drop_percent' => 0,
        'ac_voltage_drop_percent' => 0,
        'protection' => [
            'dc_isolator_a' => 0,
            'dc_fuse_a' => 0,
            'ac_breaker_a' => 0,
            'dc_spd' => 'Not calculated',
            'ac_spd' => 'Not calculated',
        ],
    ];
    $results = array_replace($resultDefaults, $calculation->results ?? []);
    $results['protection'] = array_replace($resultDefaults['protection'], $results['protection'] ?? []);

    $activeOverrides = $calculation->relationLoaded('activeOverrides') ? $calculation->activeOverrides : collect();
    $overrideMap = $activeOverrides->keyBy('field');
    $warningRecords = $calculation->exists
        ? \App\Support\CalculationWarning::normalizeList($calculation->warnings ?? [], 'technical_calculation')
        : [];
    $warningCounts = collect($warningRecords)->countBy(fn ($warning) => $warning['severity'] ?? 'info');

    $assumptionSnapshot = $results['assumption_snapshot'] ?? [];
    $assumptionSnapshot = is_array($assumptionSnapshot) ? $assumptionSnapshot : [];
    $assumptionGroups = collect($assumptionSnapshot)->groupBy(
        fn ($assumption) => $assumption['source'] ?? 'system'
    );

    $formulaSnapshot = $results['formula_snapshot'] ?? [];
    $formulaSnapshot = is_array($formulaSnapshot) ? $formulaSnapshot : [];
    $formulaGroups = collect($formulaSnapshot)->groupBy(
        fn ($formula) => $formula['category'] ?? 'General'
    );

    $formatNumber = function ($value, int $decimals = 2) {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '—';
        }
        return number_format((float) $value, $decimals);
    };
    $formatTrimmed = function ($value, int $decimals = 4) {
        if ($value === null || $value === '') {
            return '—';
        }
        if (! is_numeric($value)) {
            return (string) $value;
        }
        return rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');
    };
    $formatInputValue = function (string $name) use ($calculation) {
        $value = old($name, $calculation->{$name});
        if ($value === null || $value === '') {
            return '';
        }
        if (! is_numeric($value)) {
            return (string) $value;
        }
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    };
    $formatAssumptionValue = function ($assumption) use ($formatTrimmed) {
        $value = $formatTrimmed($assumption['value'] ?? null);
        $unit = trim((string) ($assumption['unit'] ?? ''));
        return trim($value.' '.$unit);
    };
    $formatFormulaValue = function ($value, $unit = '') use ($formatTrimmed) {
        return trim($formatTrimmed($value).' '.trim((string) $unit));
    };

    $loadEnergy = data_get($sources, 'load.daily_energy_kwh');
    $solarPsh = data_get($sources, 'solar.average_psh');
    $solarMonths = (int) data_get($sources, 'solar.complete_months', 0);
    $roofArea = data_get($sources, 'roof.usable_area_m2');
    $recommendation = $sources['recommendation'] ?? null;
    $recommendationLabel = $recommendation?->recommended_system_label;

    $loadReady = is_numeric($loadEnergy) && (float) $loadEnergy > 0;
    $solarReady = is_numeric($solarPsh) && (float) $solarPsh > 0;
    $roofReady = is_numeric($roofArea) && (float) $roofArea > 0;
    $recommendationReady = filled($recommendation?->recommended_system);
    $sourceReadyCount = collect([$loadReady, $solarReady, $roofReady, $recommendationReady])->filter()->count();
    $readinessPercent = (int) round(($sourceReadyCount / 4) * 100);

    $statusTone = ! $hasResults
        ? 'pending'
        : ($isVerified ? 'verified' : ($calculation->result_status === 'detailed' ? 'detailed' : 'preliminary'));
    $statusLabel = ! $hasResults ? 'Not calculated' : $calculation->result_status_label;
    $statusDescription = ! $hasResults
        ? 'Run the engine to establish the first controlled electrical design record.'
        : ($isVerified
            ? 'The current result is locked as an engineer-verified design basis.'
            : 'Review warnings, effective values, and source freshness before downstream use.');

    $inputGroups = [
        [
            'key' => 'general',
            'number' => '01',
            'title' => 'General Design Basis',
            'description' => 'System architecture, energy offset, performance ratio, and DC/AC target.',
            'fields' => [
                ['name' => 'system_type', 'label' => 'System type', 'type' => 'select', 'options' => ['on_grid' => 'On-grid', 'hybrid' => 'Hybrid']],
                ['name' => 'energy_offset_target_percent', 'label' => 'Energy offset target', 'type' => 'number', 'step' => '0.01', 'unit' => '%'],
                ['name' => 'performance_ratio', 'label' => 'Performance ratio', 'type' => 'number', 'step' => '0.0001', 'unit' => 'ratio'],
                ['name' => 'desired_dc_ac_ratio', 'label' => 'Desired DC/AC ratio', 'type' => 'number', 'step' => '0.001', 'unit' => 'ratio'],
            ],
        ],
        [
            'key' => 'module',
            'number' => '02',
            'title' => 'PV Module Datasheet',
            'description' => 'Rated power, geometry, electrical limits, and temperature coefficients.',
            'fields' => [
                ['name' => 'module_power_wp', 'label' => 'Module power', 'type' => 'number', 'step' => '0.001', 'unit' => 'Wp'],
                ['name' => 'module_area_m2', 'label' => 'Module area', 'type' => 'number', 'step' => '0.001', 'unit' => 'm²'],
                ['name' => 'module_voc_v', 'label' => 'Open-circuit voltage', 'type' => 'number', 'step' => '0.001', 'unit' => 'V'],
                ['name' => 'module_vmp_v', 'label' => 'Maximum-power voltage', 'type' => 'number', 'step' => '0.001', 'unit' => 'V'],
                ['name' => 'module_isc_a', 'label' => 'Short-circuit current', 'type' => 'number', 'step' => '0.001', 'unit' => 'A'],
                ['name' => 'module_imp_a', 'label' => 'Maximum-power current', 'type' => 'number', 'step' => '0.001', 'unit' => 'A'],
                ['name' => 'module_voc_temp_coeff_percent_c', 'label' => 'Voc temperature coefficient', 'type' => 'number', 'step' => '0.001', 'unit' => '%/°C'],
                ['name' => 'module_vmp_temp_coeff_percent_c', 'label' => 'Vmp temperature coefficient', 'type' => 'number', 'step' => '0.001', 'unit' => '%/°C'],
                ['name' => 'minimum_site_temperature_c', 'label' => 'Minimum site temperature', 'type' => 'number', 'step' => '0.001', 'unit' => '°C'],
                ['name' => 'maximum_cell_temperature_c', 'label' => 'Maximum cell temperature', 'type' => 'number', 'step' => '0.001', 'unit' => '°C'],
            ],
        ],
        [
            'key' => 'inverter',
            'number' => '03',
            'title' => 'Inverter Datasheet',
            'description' => 'AC rating, DC voltage ceiling, MPPT window, current capacity, and tracker count.',
            'fields' => [
                ['name' => 'inverter_ac_power_kw', 'label' => 'AC power per inverter', 'type' => 'number', 'step' => '0.01', 'unit' => 'kW'],
                ['name' => 'inverter_max_dc_voltage_v', 'label' => 'Maximum DC voltage', 'type' => 'number', 'step' => '0.01', 'unit' => 'V'],
                ['name' => 'inverter_mppt_min_voltage_v', 'label' => 'Minimum MPPT voltage', 'type' => 'number', 'step' => '0.01', 'unit' => 'V'],
                ['name' => 'inverter_mppt_max_voltage_v', 'label' => 'Maximum MPPT voltage', 'type' => 'number', 'step' => '0.01', 'unit' => 'V'],
                ['name' => 'inverter_max_input_current_a', 'label' => 'Maximum input current', 'type' => 'number', 'step' => '0.01', 'unit' => 'A'],
                ['name' => 'inverter_mppt_count', 'label' => 'MPPT count', 'type' => 'number', 'step' => '1', 'unit' => 'trackers'],
            ],
        ],
        [
            'key' => 'battery-cable',
            'number' => '04',
            'title' => 'Battery, Cable & Loss Basis',
            'description' => 'Backup objective, conversion efficiency, conductor sizing, and voltage-drop threshold.',
            'fields' => [
                ['name' => 'battery_backup_hours', 'label' => 'Backup duration', 'type' => 'number', 'step' => '0.01', 'unit' => 'hours'],
                ['name' => 'battery_dod_percent', 'label' => 'Battery depth of discharge', 'type' => 'number', 'step' => '0.01', 'unit' => '%'],
                ['name' => 'battery_efficiency_percent', 'label' => 'Battery efficiency', 'type' => 'number', 'step' => '0.01', 'unit' => '%'],
                ['name' => 'inverter_efficiency_percent', 'label' => 'Inverter efficiency', 'type' => 'number', 'step' => '0.01', 'unit' => '%'],
                ['name' => 'dc_cable_length_m', 'label' => 'DC cable length', 'type' => 'number', 'step' => '0.01', 'unit' => 'm'],
                ['name' => 'dc_cable_area_mm2', 'label' => 'DC cable area', 'type' => 'number', 'step' => '0.01', 'unit' => 'mm²'],
                ['name' => 'ac_cable_length_m', 'label' => 'AC cable length', 'type' => 'number', 'step' => '0.01', 'unit' => 'm'],
                ['name' => 'ac_cable_area_mm2', 'label' => 'AC cable area', 'type' => 'number', 'step' => '0.01', 'unit' => 'mm²'],
                ['name' => 'maximum_voltage_drop_percent', 'label' => 'Maximum voltage drop', 'type' => 'number', 'step' => '0.01', 'unit' => '%'],
                ['name' => 'cable_material', 'label' => 'Cable material', 'type' => 'select', 'options' => ['copper' => 'Copper', 'aluminium' => 'Aluminium']],
                ['name' => 'engineering_notes', 'label' => 'Engineering notes', 'type' => 'textarea', 'wide' => true],
            ],
        ],
    ];

    $projectTitleParts = preg_split('/\b(Hybrid|On[- ]?grid)\b/i', $project->name, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$project->name];
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-technical-calculation.css') }}?v=67l1" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-technical-calculation.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxtc-workspace" data-nxtc-workspace>
        <section class="nxtc-workspace-hero" data-nxtc-reveal>
            <div class="nxtc-hero-copy">
                <div class="nxtc-hero-overline">
                    <a
                        href="{{ route('companies.technical-calculations.index', $company) }}"
                        class="nxtc-back-link"
                        data-nxtc-navigate="directory"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M19 12H5M10 7l-5 5 5 5"/></svg>
                        Calculation registry
                    </a>
                    <span class="nxtc-hero-kicker">Controlled electrical design</span>
                </div>

                <h2 data-nxtc-project-name>@foreach ($projectTitleParts as $titlePart)@if (preg_match('/^(Hybrid|On[- ]?grid)$/i', $titlePart))<em>{{ $titlePart }}</em>@else{{ $titlePart }}@endif @endforeach</h2>
                <p>Traceable preliminary design for PV capacity, inverter sizing, string and MPPT compatibility, battery capacity, cable voltage drop, protection, formulas, assumptions, and engineering authority.</p>

                <div class="nxtc-hero-meta">
                    <span data-nxtc-project-number>{{ $project->project_number }}</span>
                    <span>{{ $project->customer?->name ?? 'Customer unavailable' }}</span>
                    <span>{{ $project->city ?: 'Location pending' }}</span>
                    <span>{{ $calculation->system_type === 'hybrid' ? 'Hybrid basis' : 'On-grid basis' }}</span>
                </div>

                <div class="nxtc-hero-actions">
                    <a
                        href="{{ route('companies.projects.show', [$company, $project]) }}"
                        class="nxtc-button nxtc-button-secondary"
                        data-nxtc-navigate="cockpit"
                        data-nxtc-project="{{ $project->name }}"
                        data-nxtc-project-number="{{ $project->project_number }}"
                    >Project Cockpit</a>
                    <a
                        href="{{ route('companies.boms.show', [$company, $project]) }}"
                        class="nxtc-button nxtc-button-secondary"
                        data-nxtc-navigate="bom"
                        data-nxtc-project="{{ $project->name }}"
                        data-nxtc-project-number="{{ $project->project_number }}"
                    >BOM & BOQ</a>
                </div>
            </div>

            <aside class="nxtc-hero-control" data-nxtc-card>
                <div class="nxtc-control-top">
                    <span class="nxtc-control-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/><path d="M12 15v4"/></svg>
                    </span>
                    <span class="nxtc-control-status is-{{ $statusTone }}"><i></i>{{ $hasResults ? $calculation->result_status : 'awaiting run' }}</span>
                </div>

                <div class="nxtc-control-result">
                    <small>Current engineering record</small>
                    <strong>{{ $statusLabel }}</strong>
                    <p>{{ $statusDescription }}</p>
                </div>

                <div class="nxtc-control-foot">
                    <span><small>Source gate</small><b>{{ $sourceReadyCount }}/4 ready</b></span>
                    <span><small>Warnings</small><b>{{ $hasResults ? count($warningRecords) : 'Not evaluated' }}</b></span>
                    <span><small>Overrides</small><b>{{ $activeOverrides->count() }}</b></span>
                </div>
            </aside>
        </section>

        @if (session('success'))
            <div class="nxtc-alert is-success" role="status" data-nxtc-reveal>
                <span class="nxtc-alert-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg>
                </span>
                <div>
                    <strong>Engineering record updated.</strong>
                    <p>{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="nxtc-alert is-danger" role="alert" data-nxtc-reveal>
                <span class="nxtc-alert-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg>
                </span>
                <div>
                    <strong>The requested engineering action could not be completed.</strong>
                    <p>{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="nxtc-alert is-danger" role="alert" data-nxtc-reveal>
                <span class="nxtc-alert-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg>
                </span>
                <div>
                    <strong>Some engineering inputs require attention.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($isStale)
            <div class="nxtc-alert is-warning" role="alert" data-nxtc-reveal>
                <span class="nxtc-alert-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <div>
                    <strong>Source evidence changed after the latest engine run.</strong>
                    <p>Recalculate before using this result for BOM, financial analysis, quotation, or client release.</p>
                </div>
            </div>
        @endif

        <nav class="nxtc-section-nav" aria-label="Technical calculation sections" data-nxtc-section-nav data-nxtc-reveal>
            <a href="#technical-overview" class="is-active"><span>01</span> Overview</a>
            <a href="#source-gate"><span>02</span> Source gate</a>
            @if ($canRun)
                <a href="#engineering-inputs"><span>03</span> Inputs</a>
            @endif
            <a href="#calculation-results"><span>{{ $canRun ? '04' : '03' }}</span> Results</a>
            @if ($hasResults)
                <a href="#calculation-warnings"><span>{{ $canRun ? '05' : '04' }}</span> Warnings</a>
            @endif
            @if ($formulaSnapshot !== [] || $assumptionSnapshot !== [])
                <a href="#calculation-trace"><span>{{ $canRun ? '06' : '05' }}</span> Trace</a>
            @endif
            @if ($canOverride || $activeOverrides->isNotEmpty() || $isVerified)
                <a href="#engineering-control"><span>{{ $canRun ? '07' : '06' }}</span> Control</a>
            @endif
            @if ($calculation->histories->isNotEmpty())
                <a href="#calculation-audit"><span>{{ $canRun ? '08' : '07' }}</span> Audit</a>
            @endif
        </nav>

        <section id="technical-overview" class="nxtc-summary-grid" aria-label="Technical calculation summary" data-nxtc-reveal>
            <article class="nxtc-summary-card {{ $hasResults ? '' : 'is-empty' }}" data-nxtc-card>
                <span class="nxtc-summary-label">Installed PV array</span>
                <span class="nxtc-summary-value">
                    <strong>{{ $hasResults ? $formatNumber($results['installed_pv_kw'], 2).' kWp' : '—' }}</strong>
                    <small>{{ $hasResults ? number_format((int) $results['module_count']).' modules selected' : 'Awaiting calculation' }}</small>
                </span>
                <span class="nxtc-summary-line"><span style="width:{{ $hasResults ? '100' : '0' }}%"></span></span>
            </article>

            <article class="nxtc-summary-card is-blue {{ $hasResults ? '' : 'is-empty' }}" data-nxtc-card>
                <span class="nxtc-summary-label">Inverter AC power</span>
                <span class="nxtc-summary-value">
                    <strong>{{ $hasResults ? $formatNumber($results['total_inverter_ac_kw'], 2).' kW' : '—' }}</strong>
                    <small>{{ $hasResults ? number_format((int) $results['inverter_count']).' inverter units' : 'Awaiting calculation' }}</small>
                </span>
                <span class="nxtc-summary-line"><span style="width:{{ $hasResults ? min(100, ((float) $results['dc_ac_ratio'] / 1.5) * 100) : 0 }}%"></span></span>
            </article>

            <article class="nxtc-summary-card is-violet {{ $hasResults ? '' : 'is-empty' }}" data-nxtc-card>
                <span class="nxtc-summary-label">Battery capacity</span>
                <span class="nxtc-summary-value">
                    <strong>{{ $hasResults ? $formatNumber($results['battery_nominal_kwh'], 2).' kWh' : '—' }}</strong>
                    <small>{{ $hasResults ? $formatNumber($results['battery_minimum_power_kw'], 2).' kW minimum power' : 'Awaiting calculation' }}</small>
                </span>
                <span class="nxtc-summary-line"><span style="width:{{ $hasResults && (float) $results['battery_nominal_kwh'] > 0 ? '100' : '0' }}%"></span></span>
            </article>

            <article class="nxtc-summary-card is-orange {{ $hasResults ? '' : 'is-empty' }}" data-nxtc-card>
                <span class="nxtc-summary-label">Annual energy</span>
                <span class="nxtc-summary-value">
                    <strong>{{ $hasResults ? number_format((float) $results['annual_energy_kwh'], 0).' kWh' : '—' }}</strong>
                    <small>{{ $hasResults ? $formatNumber($results['estimated_daily_generation_kwh'], 2).' kWh/day estimated' : 'Awaiting calculation' }}</small>
                </span>
                <span class="nxtc-summary-line"><span style="width:{{ $hasResults ? '100' : '0' }}%"></span></span>
            </article>
        </section>

        <section id="source-gate" class="nxtc-section" data-nxtc-reveal>
            <header class="nxtc-section-head">
                <div class="nxtc-section-title">
                    <span class="nxtc-section-kicker">Evidence authority</span>
                    <h3>Engineering Source Gate</h3>
                    <p>Upstream evidence used to establish load demand, solar resource, physical roof capacity, and the approved system basis.</p>
                </div>
                <div class="nxtc-section-actions">
                    <span class="nxtc-status-pill {{ $sourceReadyCount === 4 ? 'is-verified' : 'is-preliminary' }}">{{ $sourceReadyCount }}/4 sources ready</span>
                    @if ($hasResults && $canVerify && ! $isVerified)
                        <form
                            method="POST"
                            action="{{ route('companies.technical-calculations.verify', [$company, $project]) }}"
                            data-nxtc-action-form
                            data-nxtc-action="verify"
                            data-nxtc-project="{{ $project->name }}"
                            data-nxtc-project-number="{{ $project->project_number }}"
                        >
                            @csrf
                            @method('PATCH')
                            <button class="nxtc-button nxtc-button-primary" type="submit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg>
                                Verify result
                            </button>
                        </form>
                    @endif
                </div>
            </header>

            <div class="nxtc-section-body">
                <div class="nxtc-source-overview">
                    <article class="nxtc-readiness-gauge">
                        <div class="nxtc-gauge-ring" style="--readiness:{{ $readinessPercent * 3.6 }}deg">
                            <div class="nxtc-gauge-copy">
                                <strong>{{ $readinessPercent }}%</strong>
                                <span>source readiness</span>
                            </div>
                        </div>
                        <div class="nxtc-gauge-foot">
                            <span><small>Current status</small><b>{{ $statusLabel }}</b></span>
                            <span><small>Snapshot</small><b>{{ $calculation->calculated_at?->format('d M Y H:i') ?? 'Not captured' }}</b></span>
                        </div>
                    </article>

                    <div class="nxtc-source-grid">
                        <article class="nxtc-source-card {{ $loadReady ? 'is-ready' : '' }}">
                            <div class="nxtc-source-head">
                                <span class="nxtc-source-name">
                                    <span class="nxtc-source-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8M10 19V4M16 19v-7M22 19H2"/><path d="m7 12 3-3 3 2 4-5"/></svg></span>
                                    <strong>Load Analysis</strong>
                                </span>
                                <span class="nxtc-source-state">{{ $loadReady ? 'Ready' : 'Pending' }}</span>
                            </div>
                            <span class="nxtc-source-value {{ $loadReady ? '' : 'is-empty' }}">{{ $loadReady ? $formatNumber($loadEnergy, 2).' kWh/day' : '—' }}</span>
                            <p>{{ $loadReady ? 'Daily demand and operating profile available.' : 'Complete the demand profile before relying on the design result.' }}</p>
                        </article>

                        <article class="nxtc-source-card is-blue {{ $solarReady ? 'is-ready' : '' }}">
                            <div class="nxtc-source-head">
                                <span class="nxtc-source-name">
                                    <span class="nxtc-source-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></span>
                                    <strong>Location & Solar</strong>
                                </span>
                                <span class="nxtc-source-state">{{ $solarReady ? ($solarMonths === 12 ? 'Complete' : 'Available') : 'Pending' }}</span>
                            </div>
                            <span class="nxtc-source-value {{ $solarReady ? '' : 'is-empty' }}">{{ $solarReady ? $formatNumber($solarPsh, 2).' PSH' : '—' }}</span>
                            <p>{{ $solarReady ? $solarMonths.' complete monthly resource records.' : 'Solar resource evidence is not yet available.' }}</p>
                        </article>

                        <article class="nxtc-source-card is-violet {{ $roofReady ? 'is-ready' : '' }}">
                            <div class="nxtc-source-head">
                                <span class="nxtc-source-name">
                                    <span class="nxtc-source-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 12 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/></svg></span>
                                    <strong>Roof & Site Survey</strong>
                                </span>
                                <span class="nxtc-source-state">{{ $roofReady ? ucfirst((string) ($project->roofSiteSurvey?->quality_status ?? 'ready')) : 'Pending' }}</span>
                            </div>
                            <span class="nxtc-source-value {{ $roofReady ? '' : 'is-empty' }}">{{ $roofReady ? $formatNumber($roofArea, 2).' m²' : '—' }}</span>
                            <p>{{ $roofReady ? 'Usable installation area and physical constraints available.' : 'Usable roof area has not been established.' }}</p>
                        </article>

                        <article class="nxtc-source-card is-orange {{ $recommendationReady ? 'is-ready' : '' }}">
                            <div class="nxtc-source-head">
                                <span class="nxtc-source-name">
                                    <span class="nxtc-source-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 18h6M10 22h4"/><path d="M8.5 14.5A6 6 0 1 1 15.5 14.5c-.9.6-1.5 1.4-1.5 2.5h-4c0-1.1-.6-1.9-1.5-2.5Z"/></svg></span>
                                    <strong>System Recommendation</strong>
                                </span>
                                <span class="nxtc-source-state">{{ $recommendationReady ? 'Selected' : 'Pending' }}</span>
                            </div>
                            <span class="nxtc-source-value {{ $recommendationReady ? '' : 'is-empty' }}">{{ $recommendationReady ? $recommendationLabel : '—' }}</span>
                            <p>{{ $recommendationReady ? 'Approved planning basis available for detailed sizing.' : 'Generate a controlled system recommendation first.' }}</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        @if ($canRun)
            <section id="engineering-inputs" class="nxtc-section" data-nxtc-reveal>
                <header class="nxtc-section-head">
                    <div class="nxtc-section-title">
                        <span class="nxtc-section-kicker">Engineering configuration desk</span>
                        <h3>Design Inputs</h3>
                        <p>Confirmed datasheet parameters and project-specific assumptions. Input precision is retained by the engine while the interface presents only useful engineering detail.</p>
                    </div>
                    <div class="nxtc-section-actions">
                        @if ($isVerified)
                            <span class="nxtc-status-pill is-verified">Verified basis locked</span>
                        @else
                            <span class="nxtc-status-pill is-preliminary">Revision enabled</span>
                        @endif
                    </div>
                </header>

                <div class="nxtc-section-body">
                    <form
                        method="POST"
                        action="{{ route('companies.technical-calculations.run', [$company, $project]) }}"
                        class="nxtc-input-shell"
                        data-nxtc-calculation-form
                        data-nxtc-action-form
                        data-nxtc-action="run"
                        data-nxtc-project="{{ $project->name }}"
                        data-nxtc-project-number="{{ $project->project_number }}"
                        data-nxtc-locked="{{ $lockInputs ? 'true' : 'false' }}"
                    >
                        @csrf
                        @method('PUT')

                        @if ($lockInputs)
                            <div class="nxtc-input-lock" data-nxtc-lock-banner>
                                <div>
                                    <strong>Engineer-verified calculation basis</strong>
                                    <p>Inputs are read-only to prevent accidental modification. Start a controlled revision before recalculating.</p>
                                </div>
                                <button type="button" class="nxtc-button nxtc-button-warning" data-nxtc-enable-revision>Revise engineering basis</button>
                            </div>
                        @endif

                        <div class="nxtc-input-groups">
                            @foreach ($inputGroups as $group)
                                <details class="nxtc-input-group" @if((! $hasResults && $loop->first) || $errors->any()) open @endif>
                                    <summary>
                                        <span class="nxtc-input-group-title">
                                            <span class="nxtc-input-group-number">{{ $group['number'] }}</span>
                                            <span class="nxtc-input-group-copy">
                                                <strong>{{ $group['title'] }}</strong>
                                                <span>{{ $group['description'] }}</span>
                                            </span>
                                        </span>
                                        <span class="nxtc-input-group-meta">{{ count($group['fields']) }} parameters</span>
                                    </summary>

                                    <div class="nxtc-input-group-body">
                                        <div class="nxtc-input-grid">
                                            @foreach ($group['fields'] as $field)
                                                <div class="nxtc-field {{ ! empty($field['wide']) ? 'is-wide' : '' }}">
                                                    <label for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                                                    <div class="nxtc-input-wrap {{ ! empty($field['unit']) ? 'has-unit' : '' }}">
                                                        @if ($field['type'] === 'select')
                                                            <select
                                                                id="{{ $field['name'] }}"
                                                                name="{{ $field['name'] }}"
                                                                class="@error($field['name']) nxtc-input-error @enderror"
                                                                data-nxtc-lockable
                                                                @disabled($lockInputs)
                                                            >
                                                                @foreach ($field['options'] as $value => $label)
                                                                    <option value="{{ $value }}" @selected(old($field['name'], $calculation->{$field['name']}) === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        @elseif ($field['type'] === 'textarea')
                                                            <textarea
                                                                id="{{ $field['name'] }}"
                                                                name="{{ $field['name'] }}"
                                                                class="@error($field['name']) nxtc-input-error @enderror"
                                                                placeholder="Document assumptions, exceptions, limitations, and design authority."
                                                                data-nxtc-lockable
                                                                @disabled($lockInputs)
                                                            >{{ old($field['name'], $calculation->{$field['name']}) }}</textarea>
                                                        @else
                                                            <input
                                                                id="{{ $field['name'] }}"
                                                                type="number"
                                                                step="{{ $field['step'] }}"
                                                                name="{{ $field['name'] }}"
                                                                value="{{ $formatInputValue($field['name']) }}"
                                                                class="@error($field['name']) nxtc-input-error @enderror"
                                                                data-nxtc-lockable
                                                                @disabled($lockInputs)
                                                            >
                                                        @endif
                                                        @if (! empty($field['unit']))
                                                            <span class="nxtc-input-unit">{{ $field['unit'] }}</span>
                                                        @endif
                                                    </div>
                                                    @error($field['name'])<span class="nxtc-field-error">{{ $message }}</span>@enderror
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        </div>

                        <footer class="nxtc-form-footer">
                            <p><strong>{{ $hasResults ? 'Recalculation replaces the current engine result.' : 'The first engine run creates the controlled design record.' }}</strong><br>Existing formulas, warnings, and source evidence remain traceable through the audit history.</p>
                            <div class="nxtc-form-footer-actions">
                                <button
                                    class="nxtc-button nxtc-button-primary"
                                    type="submit"
                                    data-nxtc-lockable
                                    @disabled($lockInputs)
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="m6 15 4-4 3 3 5-7"/><path d="M16 7h2v2"/></svg>
                                    {{ $hasResults ? 'Recalculate design' : 'Run technical calculation' }}
                                </button>
                            </div>
                        </footer>
                    </form>
                </div>
            </section>
        @endif

        <section id="calculation-results" class="nxtc-section" data-nxtc-reveal>
            <header class="nxtc-section-head">
                <div class="nxtc-section-title">
                    <span class="nxtc-section-kicker">Electrical design cockpit</span>
                    <h3>Calculation Results</h3>
                    <p>Effective engineering outputs generated from the current source snapshot, datasheet assumptions, formulas, warnings, and active overrides.</p>
                </div>
                <div class="nxtc-section-actions">
                    @if ($hasResults)
                        <span class="nxtc-status-pill is-{{ $statusTone }}">{{ $statusLabel }}</span>
                    @else
                        <span class="nxtc-status-pill is-pending">Awaiting calculation</span>
                    @endif
                </div>
            </header>

            <div class="nxtc-section-body">
                @if (! $hasResults)
                    <div class="nxtc-empty-cockpit">
                        <span class="nxtc-empty-cockpit-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/><path d="M12 15v4"/></svg>
                        </span>
                        <div>
                            <h4>No electrical design result yet</h4>
                            <p>Review the source gate and engineering inputs, then run the calculation engine. Warnings, formula trace, voltage-drop checks, and protection outputs will only be evaluated after a valid engine run.</p>
                            @if ($canRun)
                                <a href="#engineering-inputs" class="nxtc-button nxtc-button-primary">Review engineering inputs</a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="nxtc-result-layout">
                        <div class="nxtc-flow" aria-label="Technical design flow">
                            <article class="nxtc-flow-node">
                                <small>Daily load</small>
                                <strong>{{ $formatNumber($loadEnergy, 2) }} kWh</strong>
                                <span>Demand evidence</span>
                            </article>
                            <article class="nxtc-flow-node">
                                <small>Required PV</small>
                                <strong>{{ $formatNumber($results['required_pv_kw'], 2) }} kWp</strong>
                                <span>Energy target basis</span>
                            </article>
                            <article class="nxtc-flow-node">
                                <small>Roof limit</small>
                                <strong>{{ number_format((int) $results['area_limited_module_count']) }} modules</strong>
                                <span>Physical constraint</span>
                            </article>
                            <article class="nxtc-flow-node is-key">
                                <small>Installed array</small>
                                <strong>{{ $formatNumber($results['installed_pv_kw'], 2) }} kWp</strong>
                                <span>{{ number_format((int) $results['module_count']) }} modules selected</span>
                            </article>
                            <article class="nxtc-flow-node">
                                <small>Inverter AC</small>
                                <strong>{{ $formatNumber($results['total_inverter_ac_kw'], 2) }} kW</strong>
                                <span>DC/AC {{ $formatNumber($results['dc_ac_ratio'], 2) }}</span>
                            </article>
                            <article class="nxtc-flow-node">
                                <small>Annual energy</small>
                                <strong>{{ number_format((float) $results['annual_energy_kwh'], 0) }} kWh</strong>
                                <span>Preliminary yield</span>
                            </article>
                        </div>

                        <div class="nxtc-result-grid">
                            <article class="nxtc-result-panel">
                                <header class="nxtc-result-head">
                                    <div>
                                        <h4>PV & Inverter Sizing</h4>
                                        <p>Selected array, inverter count, roof constraint, and DC/AC relationship.</p>
                                    </div>
                                    <span class="nxtc-result-head-badge">Sizing</span>
                                </header>
                                <div class="nxtc-result-body">
                                    <div class="nxtc-metric-grid">
                                        @foreach ([
                                            ['required_pv_kw', 'Required PV', $formatNumber($results['required_pv_kw'], 2).' kWp', 'Energy target output'],
                                            ['installed_pv_kw', 'Installed PV', $formatNumber($results['installed_pv_kw'], 2).' kWp', 'Effective selected array'],
                                            ['module_count', 'Module count', number_format((int) $results['module_count']).' pcs', 'Roof-constrained selection'],
                                            [null, 'Roof limit', number_format((int) $results['area_limited_module_count']).' pcs', 'Maximum physical count'],
                                            ['inverter_count', 'Inverter count', number_format((int) $results['inverter_count']).' units', $formatNumber($results['total_inverter_ac_kw'], 2).' kW total AC'],
                                            ['dc_ac_ratio', 'DC/AC ratio', $formatNumber($results['dc_ac_ratio'], 2), 'Target '.$formatNumber($calculation->desired_dc_ac_ratio, 2)],
                                        ] as [$field, $label, $value, $caption])
                                            <div class="nxtc-metric-card">
                                                <small>{{ $label }}</small>
                                                <strong class="nxtc-metric-value">{{ $value }}</strong>
                                                <span>{{ $caption }}</span>
                                                @if ($field && $overrideMap->has($field))<em class="nxtc-override-badge">Overridden</em>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </article>

                            <article class="nxtc-result-panel">
                                <header class="nxtc-result-head">
                                    <div>
                                        <h4>Battery & Energy</h4>
                                        <p>Storage duty, minimum power, daily generation, and annual energy.</p>
                                    </div>
                                    <span class="nxtc-result-head-badge">Energy</span>
                                </header>
                                <div class="nxtc-result-body">
                                    <div class="nxtc-metric-grid">
                                        @foreach ([
                                            ['battery_nominal_kwh', 'Battery capacity', $formatNumber($results['battery_nominal_kwh'], 2).' kWh', $calculation->system_type === 'hybrid' ? 'Hybrid storage basis' : 'Not required for on-grid'],
                                            ['battery_minimum_power_kw', 'Battery power', $formatNumber($results['battery_minimum_power_kw'], 2).' kW', 'Minimum delivery power'],
                                            ['estimated_daily_generation_kwh', 'Daily generation', $formatNumber($results['estimated_daily_generation_kwh'], 2).' kWh', 'Preliminary daily yield'],
                                            ['annual_energy_kwh', 'Annual energy', number_format((float) $results['annual_energy_kwh'], 0).' kWh', 'Preliminary annual yield'],
                                        ] as [$field, $label, $value, $caption])
                                            <div class="nxtc-metric-card">
                                                <small>{{ $label }}</small>
                                                <strong class="nxtc-metric-value">{{ $value }}</strong>
                                                <span>{{ $caption }}</span>
                                                @if ($overrideMap->has($field))<em class="nxtc-override-badge">Overridden</em>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </article>

                            <article class="nxtc-result-panel is-wide">
                                <header class="nxtc-result-head">
                                    <div>
                                        <h4>String & MPPT Compatibility</h4>
                                        <p>Selected string architecture against voltage, temperature, current, and tracker constraints.</p>
                                    </div>
                                    <span class="nxtc-result-head-badge">Compatibility</span>
                                </header>
                                <div class="nxtc-result-body">
                                    @php
                                        $stringCounts = collect($results['string_module_counts'] ?? [])->map(fn ($value) => (int) $value)->values();
                                        $inverterAssignments = collect($results['inverter_string_assignments'] ?? []);
                                        $distributionLabel = (string) ($results['string_distribution_label'] ?? $stringCounts->implode(' + '));
                                    @endphp
                                    <div class="nxtc-string-visual is-professional-allocation">
                                        <div class="nxtc-string-summary">
                                            <small>Physical string architecture</small>
                                            <strong>{{ number_format((int) $results['string_count']) }} strings · {{ number_format((int) $results['module_count']) }} modules</strong>
                                            <span>{{ $distributionLabel }}. Every module is assigned once. Allowed range: {{ number_format((int) $results['minimum_modules_per_string']) }}–{{ number_format((int) $results['maximum_modules_per_string']) }} modules/string.</span>
                                        </div>
                                        <div class="nxtc-inverter-allocation" aria-label="String allocation by inverter and MPPT">
                                            @forelse ($inverterAssignments as $assignment)
                                                <article class="nxtc-inverter-allocation-card">
                                                    <header>
                                                        <div><small>PHYSICAL INVERTER</small><strong>{{ $assignment['tag'] ?? 'INV' }}</strong></div>
                                                        <span>{{ $formatNumber($assignment['rated_ac_kw'] ?? 0, 2) }} kW AC</span>
                                                    </header>
                                                    <div class="nxtc-inverter-allocation-meta">
                                                        <span><small>Assigned modules</small><b>{{ number_format((int) ($assignment['assigned_module_count'] ?? 0)) }}</b></span>
                                                        <span><small>Assigned DC</small><b>{{ $formatNumber($assignment['assigned_dc_kw'] ?? 0, 2) }} kWp</b></span>
                                                        <span><small>DC/AC ratio</small><b>{{ $formatNumber($assignment['dc_ac_ratio'] ?? 0, 3) }}</b></span>
                                                        <span><small>MPPT used</small><b>{{ number_format((int) ($assignment['used_mppt_count'] ?? 0)) }}/{{ number_format((int) ($assignment['available_mppt_count'] ?? 0)) }}</b></span>
                                                    </div>
                                                    <div class="nxtc-inverter-string-list">
                                                        @foreach (($assignment['strings'] ?? []) as $string)
                                                            <div class="nxtc-inverter-string-row">
                                                                <span><b>{{ $string['tag'] ?? 'STR' }}</b><small>{{ number_format((int) ($string['modules'] ?? 0)) }} modules</small></span>
                                                                <span class="nxtc-module-line" aria-label="{{ number_format((int) ($string['modules'] ?? 0)) }} modules">
                                                                    @for ($moduleIndex = 0; $moduleIndex < min(max((int) ($string['modules'] ?? 0), 1), 14); $moduleIndex++)<i></i>@endfor
                                                                </span>
                                                                <strong>{{ $string['mppt_label'] ?? 'MPPT' }}</strong>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </article>
                                            @empty
                                                <div class="nxtc-allocation-empty">Run the calculation again to generate physical inverter and MPPT allocation.</div>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="nxtc-engineering-matrix nxtc-matrix-spaced">
                                        <div class="nxtc-matrix-cell"><small>Allowed string range</small><strong>{{ $results['minimum_modules_per_string'] }}–{{ $results['maximum_modules_per_string'] }} modules</strong><span>Voltage-derived operating window.</span></div>
                                        <div class="nxtc-matrix-cell"><small>Cold string Voc</small><strong>{{ $formatNumber($results['string_voc_cold_v'], 2) }} V</strong><span>Checked against maximum DC voltage.</span></div>
                                        <div class="nxtc-matrix-cell"><small>Hot string Vmp</small><strong>{{ $formatNumber($results['string_vmp_hot_v'], 2) }} V</strong><span>Checked against the MPPT window.</span></div>
                                        <div class="nxtc-matrix-cell"><small>Design DC current</small><strong>{{ $formatNumber($results['dc_input_current_a'], 2) }} A</strong><span>Compared with inverter input capacity.</span></div>
                                        <div class="nxtc-matrix-cell"><small>Temperature range</small><strong>{{ $formatNumber($calculation->minimum_site_temperature_c, 1) }}–{{ $formatNumber($calculation->maximum_cell_temperature_c, 1) }}°C</strong><span>Site minimum to cell maximum.</span></div>
                                        <div class="nxtc-matrix-cell"><small>MPPT capacity</small><strong>{{ number_format((int) ($results['used_mppt_inputs'] ?? 0)) }}/{{ number_format((int) ($results['available_mppt_inputs'] ?? 0)) }} used</strong><span>{{ number_format((int) $calculation->inverter_mppt_count) }} trackers per inverter across {{ number_format((int) $results['inverter_count']) }} physical units.</span></div>
                                    </div>
                                </div>
                            </article>

                            <article class="nxtc-result-panel is-wide">
                                <header class="nxtc-result-head">
                                    <div>
                                        <h4>Cable & Preliminary Protection</h4>
                                        <p>Voltage-drop performance, conductor basis, isolation, overcurrent protection, surge protection, and grounding.</p>
                                    </div>
                                    <span class="nxtc-result-head-badge">Protection</span>
                                </header>
                                <div class="nxtc-result-body">
                                    <div class="nxtc-engineering-matrix">
                                        <div class="nxtc-matrix-cell"><small>DC voltage drop</small><strong>{{ $formatNumber($results['dc_voltage_drop_percent'], 2) }}%</strong><span>Limit {{ $formatNumber($calculation->maximum_voltage_drop_percent, 2) }}%.</span>@if($overrideMap->has('dc_voltage_drop_percent'))<em class="nxtc-override-badge">Overridden</em>@endif</div>
                                        <div class="nxtc-matrix-cell"><small>AC voltage drop</small><strong>{{ $formatNumber($results['ac_voltage_drop_percent'], 2) }}%</strong><span>Limit {{ $formatNumber($calculation->maximum_voltage_drop_percent, 2) }}%.</span>@if($overrideMap->has('ac_voltage_drop_percent'))<em class="nxtc-override-badge">Overridden</em>@endif</div>
                                        <div class="nxtc-matrix-cell"><small>DC isolator</small><strong>{{ $formatTrimmed($results['protection']['dc_isolator_a'], 1) }} A</strong><span>Preliminary isolation rating.</span></div>
                                        <div class="nxtc-matrix-cell"><small>DC fuse</small><strong>{{ $formatTrimmed($results['protection']['dc_fuse_a'], 1) }} A</strong><span>Preliminary string protection.</span></div>
                                        <div class="nxtc-matrix-cell"><small>AC breaker</small><strong>{{ $formatTrimmed($results['protection']['ac_breaker_a'], 1) }} A</strong><span>Preliminary AC overcurrent rating.</span></div>
                                        <div class="nxtc-matrix-cell"><small>SPD & grounding</small><strong>{{ $results['protection']['dc_spd'] }} / {{ $results['protection']['ac_spd'] }}</strong><span>Grounding remains required.</span></div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if ($hasResults)
            <section id="calculation-warnings" class="nxtc-section" data-nxtc-reveal>
                <header class="nxtc-section-head">
                    <div class="nxtc-section-title">
                        <span class="nxtc-section-kicker">Engineering exception register</span>
                        <h3>Warnings & Required Review</h3>
                        <p>Structured severity, source evidence, technical context, and recommended engineering action from the latest engine run.</p>
                    </div>
                    <div class="nxtc-section-actions">
                        <span class="nxtc-status-pill {{ $warningRecords === [] ? 'is-verified' : 'is-preliminary' }}">{{ count($warningRecords) }} active notices</span>
                    </div>
                </header>

                <div class="nxtc-section-body">
                    @if ($warningRecords !== [])
                        <div class="nxtc-warning-summary">
                            @foreach (['critical' => 'Critical', 'warning' => 'Warning', 'caution' => 'Caution', 'info' => 'Information'] as $severity => $label)
                                <div class="nxtc-warning-count"><small>{{ $label }}</small><strong>{{ $warningCounts->get($severity, 0) }}</strong></div>
                            @endforeach
                        </div>

                        <ul class="nxtc-warning-list">
                            @foreach ($warningRecords as $warning)
                                @php
                                    $severity = $warning['severity'] ?? 'info';
                                @endphp
                                <li class="nxtc-warning-card is-{{ $severity }}">
                                    <span class="nxtc-warning-marker">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                                    </span>
                                    <div class="nxtc-warning-main">
                                        <div class="nxtc-warning-meta">
                                            <span class="nxtc-warning-pill">{{ $severity }}</span>
                                            <span class="nxtc-warning-code">{{ $warning['code'] ?? 'NOTICE' }}</span>
                                        </div>
                                        <strong>{{ $warning['title'] ?? 'Calculation notice' }}</strong>
                                        <p>{{ $warning['message'] ?? 'Review this engineering condition.' }}</p>
                                    </div>
                                    <div class="nxtc-warning-action">
                                        <strong>Recommended action</strong>
                                        <p>{{ $warning['recommendation'] ?? 'Review the source evidence and design assumptions before downstream use.' }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="nxtc-no-warning">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><circle cx="12" cy="12" r="9"/></svg>
                            <strong>No active calculation warnings</strong>
                            <p>The latest completed engine run did not register an active technical notice.</p>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if ($formulaSnapshot !== [] || $assumptionSnapshot !== [])
            <section id="calculation-trace" class="nxtc-section" data-nxtc-reveal>
                <header class="nxtc-section-head">
                    <div class="nxtc-section-title">
                        <span class="nxtc-section-kicker">Explainable engineering trace</span>
                        <h3>Formula & Assumption Register</h3>
                        <p>Equations, source inputs, engine values, effective values, and assumptions grouped for rapid review without losing audit depth.</p>
                    </div>
                    <div class="nxtc-section-actions">
                        <span class="nxtc-status-pill is-detailed">{{ count($formulaSnapshot) }} formulas</span>
                        <span class="nxtc-status-pill is-preliminary">{{ count($assumptionSnapshot) }} assumptions</span>
                    </div>
                </header>

                <div class="nxtc-section-body">
                    <div class="nxtc-result-grid">
                        @if ($formulaSnapshot !== [])
                            <article class="nxtc-result-panel {{ $assumptionSnapshot === [] ? 'is-wide' : '' }}">
                                <header class="nxtc-result-head">
                                    <div><h4>Formula Explanation</h4><p>Grouped calculation logic with effective result and source input trace.</p></div>
                                </header>
                                <div class="nxtc-result-body">
                                    <div class="nxtc-formula-groups">
                                        @foreach ($formulaGroups as $category => $formulas)
                                            <details class="nxtc-formula-group" @if($loop->first) open @endif>
                                                <summary>
                                                    <span class="nxtc-group-title">
                                                        <span class="nxtc-group-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16M4 12h16M4 19h16"/><path d="M8 3v4M14 10v4M10 17v4"/></svg></span>
                                                        <span class="nxtc-group-copy"><strong>{{ $category }}</strong><span>Calculation logic and effective values</span></span>
                                                    </span>
                                                    <span class="nxtc-group-count">{{ $formulas->count() }}</span>
                                                </summary>
                                                <div class="nxtc-formula-items">
                                                    @foreach ($formulas as $field => $formula)
                                                        @php
                                                            $unit = $formula['unit'] ?? '';
                                                            $engineResult = $formula['result'] ?? null;
                                                            $effectiveResult = $formula['effective_result'] ?? $engineResult;
                                                            $isFormulaOverridden = (bool) ($formula['is_overridden'] ?? false);
                                                        @endphp
                                                        <details class="nxtc-formula">
                                                            <summary>
                                                                <span class="nxtc-formula-title">
                                                                    <strong>{{ $formula['label'] ?? 'Calculation formula' }}</strong>
                                                                    <span>{{ $isFormulaOverridden ? 'Effective result overridden' : 'Engine result active' }}</span>
                                                                </span>
                                                                <span class="nxtc-formula-result">{{ $formatFormulaValue($effectiveResult, $unit) }}</span>
                                                            </summary>
                                                            <div class="nxtc-formula-body">
                                                                <div class="nxtc-formula-equation">{{ $formula['display_formula'] ?? $formula['formula'] ?? '—' }}</div>
                                                                <p>{{ $formula['explanation'] ?? 'No additional formula explanation is available.' }}</p>
                                                                @if ($isFormulaOverridden)
                                                                    <span class="nxtc-formula-badge">Effective value overridden</span>
                                                                    @if (! empty($formula['override_reason']))
                                                                        <p><strong>Override reason:</strong> {{ $formula['override_reason'] }}</p>
                                                                    @endif
                                                                @endif
                                                                @if (! empty($formula['inputs']) && is_array($formula['inputs']))
                                                                    <div class="nxtc-formula-inputs">
                                                                        @foreach ($formula['inputs'] as $input)
                                                                            <div class="nxtc-formula-input">
                                                                                <span>{{ $input['label'] ?? 'Input' }}</span>
                                                                                <strong>{{ $formatFormulaValue($input['value'] ?? null, $input['unit'] ?? '') }}</strong>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </details>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </div>
                            </article>
                        @endif

                        @if ($assumptionSnapshot !== [])
                            <article class="nxtc-result-panel {{ $formulaSnapshot === [] ? 'is-wide' : '' }}">
                                <header class="nxtc-result-head">
                                    <div><h4>Engineering Assumptions</h4><p>Grouped by source to separate upstream evidence, engineer inputs, and derived values.</p></div>
                                </header>
                                <div class="nxtc-result-body">
                                    <div class="nxtc-assumption-groups">
                                        @foreach ($assumptionGroups as $source => $assumptions)
                                            <details class="nxtc-assumption-group" @if($loop->first) open @endif>
                                                <summary>
                                                    <span class="nxtc-group-title">
                                                        <span class="nxtc-group-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                                                        <span class="nxtc-group-copy"><strong>{{ ucwords(str_replace('_', ' ', $source)) }}</strong><span>Evidence and calculation basis</span></span>
                                                    </span>
                                                    <span class="nxtc-group-count">{{ $assumptions->count() }}</span>
                                                </summary>
                                                <div class="nxtc-assumption-items">
                                                    @foreach ($assumptions as $assumption)
                                                        <div class="nxtc-assumption-item">
                                                            <strong>{{ $assumption['label'] ?? 'Assumption' }}</strong>
                                                            <span class="nxtc-assumption-value">{{ $formatAssumptionValue($assumption) }}</span>
                                                            <p>{{ $assumption['description'] ?? 'No additional description is available.' }}</p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </div>
                            </article>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if ($canOverride || $activeOverrides->isNotEmpty() || $isVerified)
            <section id="engineering-control" class="nxtc-section" data-nxtc-reveal>
                <header class="nxtc-section-head">
                    <div class="nxtc-section-title">
                        <span class="nxtc-section-kicker">Engineering authority control</span>
                        <h3>Verification & Effective Values</h3>
                        <p>Document controlled deviations from the engine result while preserving original values, reasoning, downstream impact, and audit authority.</p>
                    </div>
                    <div class="nxtc-section-actions">
                        @if ($isVerified)
                            <span class="nxtc-status-pill is-verified">Verified and locked</span>
                        @elseif ($activeOverrides->isNotEmpty())
                            <span class="nxtc-status-pill is-preliminary">{{ $activeOverrides->count() }} active overrides</span>
                        @endif
                    </div>
                </header>

                <div class="nxtc-section-body">
                    <div class="nxtc-control-grid">
                        <div>
                            @if ($activeOverrides->isNotEmpty())
                                <div class="nxtc-override-note is-spaced">
                                    <strong>Engineer override active.</strong> Effective values below are used by downstream BOM, financial analysis, quotation, and reports. Original engine values remain preserved.
                                </div>
                                <div class="nxtc-override-list">
                                    @foreach ($activeOverrides as $override)
                                        <article class="nxtc-override-card">
                                            <div class="nxtc-override-field">
                                                <strong>{{ $override->label }}</strong>
                                                <small>{{ $override->creator?->name ?? 'Engineer' }} · {{ $override->created_at?->format('d M Y H:i') }}</small>
                                            </div>
                                            <span class="nxtc-override-value">{{ $override->formattedEngineValue() }}</span>
                                            <span class="nxtc-override-arrow">→</span>
                                            <span class="nxtc-override-value">{{ $override->formattedOverrideValue() }}</span>
                                            <span class="nxtc-override-reason">{{ $override->reason }}</span>
                                            @if ($canOverride)
                                                <form
                                                    method="POST"
                                                    action="{{ route('companies.technical-calculations.overrides.reset', [$company, $project, $override]) }}"
                                                    data-nxtc-action-form
                                                    data-nxtc-action="reset"
                                                    data-nxtc-project="{{ $project->name }}"
                                                    data-nxtc-project-number="{{ $project->project_number }}"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="nxtc-link-danger">Reset</button>
                                                </form>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @elseif ($isVerified)
                                <div class="nxtc-empty-cockpit is-compact">
                                    <span class="nxtc-empty-cockpit-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg>
                                    </span>
                                    <div>
                                        <h4>Verified result locked</h4>
                                        <p>The current calculation is protected from overrides. Start a controlled engineering revision and recalculate before applying a new effective value.</p>
                                    </div>
                                </div>
                            @else
                                <div class="nxtc-empty-cockpit is-compact">
                                    <span class="nxtc-empty-cockpit-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                                    </span>
                                    <div>
                                        <h4>No active overrides</h4>
                                        <p>All effective values currently match the preserved engine result.</p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($canOverride)
                            <form
                                method="POST"
                                action="{{ route('companies.technical-calculations.overrides.store', [$company, $project]) }}"
                                class="nxtc-override-form"
                                data-nxtc-action-form
                                data-nxtc-action="override"
                                data-nxtc-project="{{ $project->name }}"
                                data-nxtc-project-number="{{ $project->project_number }}"
                            >
                                @csrf
                                <div class="nxtc-input-grid">
                                    <div class="nxtc-field is-wide">
                                        <label for="field">Result field</label>
                                        <div class="nxtc-input-wrap">
                                            <select id="field" name="field" class="@error('field') nxtc-input-error @enderror">
                                                @foreach ($overrideFields as $field => $definition)
                                                    <option value="{{ $field }}" @selected(old('field') === $field)>{{ $definition['label'] }} @if($definition['unit'])({{ $definition['unit'] }})@endif</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error('field')<span class="nxtc-field-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="nxtc-field is-wide">
                                        <label for="override_value">New effective value</label>
                                        <div class="nxtc-input-wrap">
                                            <input id="override_value" type="number" step="0.0001" min="0" name="override_value" value="{{ old('override_value') }}" class="@error('override_value') nxtc-input-error @enderror">
                                        </div>
                                        @error('override_value')<span class="nxtc-field-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="nxtc-field is-wide">
                                        <label for="reason">Engineering reason</label>
                                        <div class="nxtc-input-wrap">
                                            <textarea id="reason" name="reason" class="@error('reason') nxtc-input-error @enderror" placeholder="Document verified site evidence, product selection, or engineering authority supporting the change.">{{ old('reason') }}</textarea>
                                        </div>
                                        @error('reason')<span class="nxtc-field-error">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <button class="nxtc-button nxtc-button-warning" type="submit">Apply documented override</button>
                            </form>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if ($calculation->histories->isNotEmpty())
            <section id="calculation-audit" class="nxtc-section" data-nxtc-reveal>
                <header class="nxtc-section-head">
                    <div class="nxtc-section-title">
                        <span class="nxtc-section-kicker">Immutable workflow trace</span>
                        <h3>Calculation Audit</h3>
                        <p>Compact chronology of engine runs, verification, overrides, and reset events with user authority and recorded notes.</p>
                    </div>
                    <div class="nxtc-section-actions">
                        <span class="nxtc-status-pill is-detailed">{{ $calculation->histories->count() }} events</span>
                    </div>
                </header>

                <div class="nxtc-section-body">
                    <ol class="nxtc-timeline">
                        @foreach ($calculation->histories as $history)
                            <li>
                                <span class="nxtc-timeline-marker">
                                    @if ($history->event === 'verified')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/></svg>
                                    @elseif (in_array($history->event, ['overridden', 'override_reset'], true))
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4.2-1 10-10a2 2 0 0 0-3-3l-10 10L4 20Z"/></svg>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="m6 15 4-4 3 3 5-7"/></svg>
                                    @endif
                                </span>
                                <span class="nxtc-timeline-copy">
                                    <strong>{{ ucwords(str_replace('_', ' ', $history->event)) }}</strong>
                                    <small>{{ $history->user?->name ?? 'System' }}@if($history->notes) · {{ $history->notes }}@endif</small>
                                </span>
                                <span class="nxtc-timeline-time">{{ $history->changed_at?->format('d M Y') }}<br>{{ $history->changed_at?->format('H:i') }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endif
    </div>
@endsection
