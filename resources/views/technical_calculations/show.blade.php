@extends('layouts.app')

@section('title', 'Technical Calculation')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Technical Calculation')

@push('styles')
<style>
    .tc-page{display:grid;gap:24px;min-width:0}
    .tc-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:24px;align-items:center;padding:32px 36px;border-radius:30px;background:linear-gradient(135deg,#021c2b 0%,#064255 58%,#0f6b72 100%);color:#fff;box-shadow:0 24px 55px rgba(4,45,58,.18)}
    .tc-kicker{font-size:.8rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#2dd4bf}
    .tc-hero h2{margin:10px 0 8px;font:700 clamp(2.1rem,4vw,3.8rem)/1.02 "Space Grotesk",sans-serif;letter-spacing:-.045em}
    .tc-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.72);font-size:1rem;line-height:1.65}
    .tc-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
    .tc-actions .ui-button-secondary{border-color:rgba(255,255,255,.24);background:rgba(255,255,255,.08);color:#fff}
    .tc-alert-list{margin:8px 0 0;padding-left:20px}
    .tc-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
    .tc-stat{min-width:0;padding:22px;border:1px solid var(--slate-200);border-radius:22px;background:#fff;box-shadow:var(--shadow-sm)}
    .tc-stat-code{display:inline-grid;place-items:center;min-width:52px;height:52px;padding:0 10px;border-radius:16px;background:#dcfaf6;color:#0f9f97;font-weight:800}
    .tc-stat strong{display:block;margin-top:18px;font:700 1.75rem/1 "Space Grotesk",sans-serif;color:var(--navy-900)}
    .tc-stat span{display:block;margin-top:8px;color:var(--slate-600);font-size:.84rem;line-height:1.35}
    .tc-layout{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(300px,.72fr);gap:24px;align-items:start;min-width:0}
    .tc-main,.tc-aside{display:grid;gap:24px;min-width:0}
    .tc-card{min-width:0;padding:26px;border:1px solid var(--slate-200);border-radius:24px;background:#fff;box-shadow:var(--shadow-sm)}
    .tc-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px}
    .tc-card h3{margin:0;font:700 1.45rem/1.2 "Space Grotesk",sans-serif;color:var(--navy-900)}
    .tc-card-subtitle{margin:6px 0 0;color:var(--slate-600);line-height:1.55}
    .tc-form{display:grid;gap:22px}
    .tc-form-section{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding-top:22px;border-top:1px solid var(--slate-200)}
    .tc-form-section:first-child{padding-top:0;border-top:0}
    .tc-form-section-title{grid-column:1/-1}
    .tc-form-section-title strong{display:block;color:var(--navy-900);font-size:1rem}
    .tc-form-section-title span{display:block;margin-top:4px;color:var(--slate-500);font-size:.82rem;line-height:1.45}
    .tc-field{display:grid;gap:7px;min-width:0}
    .tc-field-wide{grid-column:1/-1}
    .tc-field label{font-size:.82rem;font-weight:750;color:var(--slate-700)}
    .tc-field input,.tc-field select,.tc-field textarea{width:100%;min-width:0;padding:13px 14px;border:1px solid var(--slate-300);border-radius:14px;background:#fff;color:var(--navy-900);transition:border-color .18s ease,box-shadow .18s ease}
    .tc-field input:focus,.tc-field select:focus,.tc-field textarea:focus{outline:0;border-color:#22c7bd;box-shadow:0 0 0 4px rgba(34,199,189,.12)}
    .tc-field textarea{min-height:112px;resize:vertical}
    .tc-field-error{font-size:.78rem;color:#b42318}
    .tc-input-error{border-color:#ef4444!important;background:#fffafa!important}
    .tc-form-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;padding-top:4px}
    .tc-source-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .tc-source{padding:15px;border:1px solid var(--slate-200);border-radius:16px;background:var(--slate-50)}
    .tc-source span{display:block;color:var(--slate-500);font-size:.75rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
    .tc-source strong{display:block;margin-top:7px;color:var(--navy-900);font-size:1rem}
    .tc-status{padding:16px;border:1px solid #99f6e4;border-radius:16px;background:#ecfdf9;color:#087a70}
    .tc-status-pending{border-color:var(--slate-200);background:var(--slate-50);color:var(--slate-700)}
    .tc-status strong{display:block}
    .tc-status small{display:block;margin-top:5px;color:inherit;opacity:.78}
    .tc-warning-list,.tc-history{display:grid;gap:10px;margin:0;padding:0;list-style:none}
    .tc-warning-list li,.tc-history li{padding:13px 14px;border:1px solid var(--slate-200);border-radius:15px;background:var(--slate-50);line-height:1.45}
    .tc-warning-list li{border-color:#fed7aa;background:#fff7ed;color:#9a3412}
    .tc-warning-list .tc-no-warning{border-color:#99f6e4;background:#ecfdf9;color:#087a70}
    .tc-history strong{color:var(--navy-900)}
    .tc-history small{display:block;margin-top:4px;color:var(--slate-500)}
    .tc-result-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .tc-result-panel{min-width:0;padding:20px;border:1px solid var(--slate-200);border-radius:20px;background:var(--slate-50)}
    .tc-result-panel-wide{grid-column:1/-1}
    .tc-result-panel h4{margin:0 0 14px;color:var(--navy-900);font-size:1rem}
    .tc-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .tc-metric{padding:12px;border-radius:14px;background:#fff;border:1px solid var(--slate-200)}
    .tc-metric span{display:block;color:var(--slate-500);font-size:.72rem;font-weight:750;text-transform:uppercase;letter-spacing:.05em}
    .tc-metric strong{display:block;margin-top:6px;color:var(--navy-900);font-size:1rem}
    .tc-table-wrap{overflow-x:auto;border:1px solid var(--slate-200);border-radius:16px}
    .tc-table{width:100%;min-width:640px;border-collapse:collapse;background:#fff}
    .tc-table th,.tc-table td{padding:13px 15px;border-bottom:1px solid var(--slate-200);text-align:left;vertical-align:top}
    .tc-table tr:last-child th,.tc-table tr:last-child td{border-bottom:0}
    .tc-table th{width:24%;background:var(--slate-50);color:var(--slate-600);font-size:.76rem;text-transform:uppercase;letter-spacing:.05em}
    @media(max-width:1180px){.tc-layout{grid-template-columns:1fr}.tc-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.tc-hero{grid-template-columns:1fr;padding:26px}.tc-actions{justify-content:flex-start}.tc-stats,.tc-form-section,.tc-result-grid,.tc-source-grid{grid-template-columns:1fr}.tc-field-wide,.tc-result-panel-wide{grid-column:auto}.tc-metrics{grid-template-columns:1fr}.tc-form-actions{justify-content:stretch}.tc-form-actions .ui-button{width:100%}}
</style>
@endpush

@section('content')
    @php
        $hasResults = ! empty($calculation->results);
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
    @endphp

    <div class="tc-page">
        <section class="tc-hero">
            <div>
                <div class="tc-kicker">{{ $project->project_number }} · {{ $project->customer?->name }}</div>
                <h2>{{ $project->name }}</h2>
                <p>Run a transparent preliminary design for PV capacity, inverter sizing, string compatibility, battery capacity, cable voltage drop, and basic protection.</p>
            </div>
            <div class="tc-actions">
                <a href="{{ route('companies.technical-calculations.index', $company) }}" class="ui-button ui-button-secondary">Calculation Directory</a>
                <a href="{{ route('companies.boms.show', [$company, $project]) }}" class="ui-button ui-button-secondary">BOM/BOQ</a>
                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Project Details</a>
            </div>
        </section>

        @if (session('success'))
            <div class="ui-alert ui-alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="ui-alert ui-alert-danger">
                <strong>The calculation was not run because some inputs are invalid.</strong>
                <ul class="tc-alert-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($calculation->exists && $calculation->isStale($project))
            <div class="ui-alert ui-alert-warning">Source data changed after the last calculation. Run the engine again before using this result.</div>
        @endif

        <section class="tc-stats" aria-label="Technical result summary">
            <article class="tc-stat"><span class="tc-stat-code">PV</span><strong>{{ number_format((float) ($results['installed_pv_kw'] ?? 0), 2) }} kWp</strong><span>Installed PV array</span></article>
            <article class="tc-stat"><span class="tc-stat-code">AC</span><strong>{{ number_format((float) ($results['total_inverter_ac_kw'] ?? 0), 2) }} kW</strong><span>Total inverter AC power</span></article>
            <article class="tc-stat"><span class="tc-stat-code">BAT</span><strong>{{ number_format((float) ($results['battery_nominal_kwh'] ?? 0), 2) }} kWh</strong><span>Battery nominal capacity</span></article>
            <article class="tc-stat"><span class="tc-stat-code">YR</span><strong>{{ number_format((float) ($results['annual_energy_kwh'] ?? 0), 0) }} kWh</strong><span>Estimated annual energy</span></article>
        </section>

        <div class="tc-layout">
            <main class="tc-main">
                @if ($canRun)
                    <section class="tc-card">
                        <div class="tc-card-head">
                            <div>
                                <h3>Engineering Inputs</h3>
                                <p class="tc-card-subtitle">Use confirmed datasheet values and project-specific design assumptions.</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('companies.technical-calculations.run', [$company, $project]) }}" class="tc-form">
                            @csrf
                            @method('PUT')

                            <section class="tc-form-section">
                                <div class="tc-form-section-title"><strong>General Design</strong><span>Set the selected system and the main sizing objectives.</span></div>
                                <div class="tc-field">
                                    <label for="system_type">System type</label>
                                    <select id="system_type" name="system_type" class="@error('system_type') tc-input-error @enderror">
                                        <option value="on_grid" @selected(old('system_type', $calculation->system_type) === 'on_grid')>On-grid</option>
                                        <option value="hybrid" @selected(old('system_type', $calculation->system_type) === 'hybrid')>Hybrid</option>
                                    </select>
                                    @error('system_type')<span class="tc-field-error">{{ $message }}</span>@enderror
                                </div>
                                @foreach ([
                                    ['energy_offset_target_percent', 'Energy offset target (%)', '0.01'],
                                    ['performance_ratio', 'Performance ratio', '0.0001'],
                                    ['desired_dc_ac_ratio', 'Desired DC/AC ratio', '0.001'],
                                ] as [$name, $label, $step])
                                    <div class="tc-field">
                                        <label for="{{ $name }}">{{ $label }}</label>
                                        <input id="{{ $name }}" type="number" step="{{ $step }}" name="{{ $name }}" value="{{ old($name, $calculation->$name) }}" class="@error($name) tc-input-error @enderror">
                                        @error($name)<span class="tc-field-error">{{ $message }}</span>@enderror
                                    </div>
                                @endforeach
                            </section>

                            <section class="tc-form-section">
                                <div class="tc-form-section-title"><strong>PV Module Datasheet</strong><span>Electrical and physical parameters at the selected module rating.</span></div>
                                @foreach ([
                                    ['module_power_wp', 'Module power (Wp)'],
                                    ['module_area_m2', 'Module area (m²)'],
                                    ['module_voc_v', 'Voc (V)'],
                                    ['module_vmp_v', 'Vmp (V)'],
                                    ['module_isc_a', 'Isc (A)'],
                                    ['module_imp_a', 'Imp (A)'],
                                    ['module_voc_temp_coeff_percent_c', 'Voc coefficient (%/°C)'],
                                    ['module_vmp_temp_coeff_percent_c', 'Vmp coefficient (%/°C)'],
                                    ['minimum_site_temperature_c', 'Minimum site temperature (°C)'],
                                    ['maximum_cell_temperature_c', 'Maximum cell temperature (°C)'],
                                ] as [$name, $label])
                                    <div class="tc-field">
                                        <label for="{{ $name }}">{{ $label }}</label>
                                        <input id="{{ $name }}" type="number" step="0.001" name="{{ $name }}" value="{{ old($name, $calculation->$name) }}" class="@error($name) tc-input-error @enderror">
                                        @error($name)<span class="tc-field-error">{{ $message }}</span>@enderror
                                    </div>
                                @endforeach
                            </section>

                            <section class="tc-form-section">
                                <div class="tc-form-section-title"><strong>Inverter Datasheet</strong><span>DC limits, MPPT window, current capacity, and AC output.</span></div>
                                @foreach ([
                                    ['inverter_ac_power_kw', 'AC power per inverter (kW)'],
                                    ['inverter_max_dc_voltage_v', 'Maximum DC voltage (V)'],
                                    ['inverter_mppt_min_voltage_v', 'Minimum MPPT voltage (V)'],
                                    ['inverter_mppt_max_voltage_v', 'Maximum MPPT voltage (V)'],
                                    ['inverter_max_input_current_a', 'Maximum input current (A)'],
                                    ['inverter_mppt_count', 'MPPT count'],
                                ] as [$name, $label])
                                    <div class="tc-field">
                                        <label for="{{ $name }}">{{ $label }}</label>
                                        <input id="{{ $name }}" type="number" step="0.01" name="{{ $name }}" value="{{ old($name, $calculation->$name) }}" class="@error($name) tc-input-error @enderror">
                                        @error($name)<span class="tc-field-error">{{ $message }}</span>@enderror
                                    </div>
                                @endforeach
                            </section>

                            <section class="tc-form-section">
                                <div class="tc-form-section-title"><strong>Battery and Cable Design</strong><span>Battery parameters apply to hybrid designs. Cable values support preliminary voltage-drop checks.</span></div>
                                @foreach ([
                                    ['battery_backup_hours', 'Backup duration (hours)'],
                                    ['battery_dod_percent', 'Battery DoD (%)'],
                                    ['battery_efficiency_percent', 'Battery efficiency (%)'],
                                    ['inverter_efficiency_percent', 'Inverter efficiency (%)'],
                                    ['dc_cable_length_m', 'DC cable length (m)'],
                                    ['dc_cable_area_mm2', 'DC cable area (mm²)'],
                                    ['ac_cable_length_m', 'AC cable length (m)'],
                                    ['ac_cable_area_mm2', 'AC cable area (mm²)'],
                                    ['maximum_voltage_drop_percent', 'Maximum voltage drop (%)'],
                                ] as [$name, $label])
                                    <div class="tc-field">
                                        <label for="{{ $name }}">{{ $label }}</label>
                                        <input id="{{ $name }}" type="number" step="0.01" name="{{ $name }}" value="{{ old($name, $calculation->$name) }}" class="@error($name) tc-input-error @enderror">
                                        @error($name)<span class="tc-field-error">{{ $message }}</span>@enderror
                                    </div>
                                @endforeach
                                <div class="tc-field">
                                    <label for="cable_material">Cable material</label>
                                    <select id="cable_material" name="cable_material" class="@error('cable_material') tc-input-error @enderror">
                                        <option value="copper" @selected(old('cable_material', $calculation->cable_material) === 'copper')>Copper</option>
                                        <option value="aluminium" @selected(old('cable_material', $calculation->cable_material) === 'aluminium')>Aluminium</option>
                                    </select>
                                    @error('cable_material')<span class="tc-field-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="tc-field tc-field-wide">
                                    <label for="engineering_notes">Engineering notes</label>
                                    <textarea id="engineering_notes" name="engineering_notes" class="@error('engineering_notes') tc-input-error @enderror" placeholder="Document assumptions, exceptions, and limitations.">{{ old('engineering_notes', $calculation->engineering_notes) }}</textarea>
                                    @error('engineering_notes')<span class="tc-field-error">{{ $message }}</span>@enderror
                                </div>
                            </section>

                            <div class="tc-form-actions">
                                <button class="ui-button ui-button-primary" type="submit">Run Technical Calculation</button>
                            </div>
                        </form>
                    </section>
                @endif

                @if ($hasResults)
                    <section class="tc-card">
                        <div class="tc-card-head">
                            <div><h3>Calculation Results</h3><p class="tc-card-subtitle">Traceable outputs generated from the current source snapshot and engineering inputs.</p></div>
                        </div>

                        <div class="tc-result-grid">
                            <article class="tc-result-panel">
                                <h4>PV and Inverter Sizing</h4>
                                <div class="tc-metrics">
                                    <div class="tc-metric"><span>Required PV</span><strong>{{ number_format((float) $results['required_pv_kw'], 3) }} kWp</strong></div>
                                    <div class="tc-metric"><span>Installed PV</span><strong>{{ number_format((float) $results['installed_pv_kw'], 3) }} kWp</strong></div>
                                    <div class="tc-metric"><span>Modules</span><strong>{{ $results['module_count'] }}</strong></div>
                                    <div class="tc-metric"><span>Roof Limit</span><strong>{{ $results['area_limited_module_count'] }}</strong></div>
                                    <div class="tc-metric"><span>Inverters</span><strong>{{ $results['inverter_count'] }}</strong></div>
                                    <div class="tc-metric"><span>DC/AC Ratio</span><strong>{{ number_format((float) $results['dc_ac_ratio'], 3) }}</strong></div>
                                </div>
                            </article>

                            <article class="tc-result-panel">
                                <h4>Battery and Energy</h4>
                                <div class="tc-metrics">
                                    <div class="tc-metric"><span>Battery Capacity</span><strong>{{ number_format((float) $results['battery_nominal_kwh'], 2) }} kWh</strong></div>
                                    <div class="tc-metric"><span>Battery Power</span><strong>{{ number_format((float) $results['battery_minimum_power_kw'], 2) }} kW</strong></div>
                                    <div class="tc-metric"><span>Daily Generation</span><strong>{{ number_format((float) $results['estimated_daily_generation_kwh'], 2) }} kWh</strong></div>
                                    <div class="tc-metric"><span>Annual Energy</span><strong>{{ number_format((float) $results['annual_energy_kwh'], 0) }} kWh</strong></div>
                                </div>
                            </article>

                            <article class="tc-result-panel tc-result-panel-wide">
                                <h4>String and MPPT Compatibility</h4>
                                <div class="tc-table-wrap">
                                    <table class="tc-table">
                                        <tbody>
                                            <tr><th>Allowed string range</th><td>{{ $results['minimum_modules_per_string'] }} to {{ $results['maximum_modules_per_string'] }} modules</td><th>Selected configuration</th><td>{{ $results['string_count'] }} strings × {{ $results['modules_per_string'] }} modules</td></tr>
                                            <tr><th>Cold string Voc</th><td>{{ number_format((float) $results['string_voc_cold_v'], 2) }} V</td><th>Hot string Vmp</th><td>{{ number_format((float) $results['string_vmp_hot_v'], 2) }} V</td></tr>
                                            <tr><th>Design DC input current</th><td>{{ number_format((float) $results['dc_input_current_a'], 2) }} A</td><th>Module temperature range</th><td>{{ number_format((float) $calculation->minimum_site_temperature_c, 1) }}°C to {{ number_format((float) $calculation->maximum_cell_temperature_c, 1) }}°C</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </article>

                            <article class="tc-result-panel tc-result-panel-wide">
                                <h4>Cable and Preliminary Protection</h4>
                                <div class="tc-table-wrap">
                                    <table class="tc-table">
                                        <tbody>
                                            <tr><th>DC voltage drop</th><td>{{ number_format((float) $results['dc_voltage_drop_percent'], 3) }}%</td><th>AC voltage drop</th><td>{{ number_format((float) $results['ac_voltage_drop_percent'], 3) }}%</td></tr>
                                            <tr><th>DC isolator</th><td>{{ $results['protection']['dc_isolator_a'] }} A</td><th>DC fuse</th><td>{{ $results['protection']['dc_fuse_a'] }} A</td></tr>
                                            <tr><th>AC breaker</th><td>{{ $results['protection']['ac_breaker_a'] }} A</td><th>SPD and grounding</th><td>{{ $results['protection']['dc_spd'] }}, {{ $results['protection']['ac_spd'] }}, grounding required</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        </div>
                    </section>
                @endif
            </main>

            <aside class="tc-aside">
                <section class="tc-card">
                    <div class="tc-card-head"><div><h3>Calculation Status</h3><p class="tc-card-subtitle">Current engineering record and source readiness.</p></div></div>
                    <div class="tc-status {{ $calculation->calculated_at ? '' : 'tc-status-pending' }}">
                        <strong>{{ $calculation->calculated_at ? $calculation->result_status_label : 'Not Calculated' }}</strong>
                        <small>{{ $calculation->calculated_at?->format('d M Y, H:i') ?? 'Run the engine to create the first result.' }}</small>
                    </div>
                    <div class="tc-source-grid" style="margin-top:14px">
                        <div class="tc-source"><span>Load</span><strong>{{ number_format((float) ($sources['load']['daily_energy_kwh'] ?? 0), 2) }} kWh/day</strong></div>
                        <div class="tc-source"><span>Solar</span><strong>{{ number_format((float) ($sources['solar']['average_psh'] ?? 0), 2) }} PSH</strong></div>
                        <div class="tc-source"><span>Usable Roof</span><strong>{{ number_format((float) ($sources['roof']['usable_area_m2'] ?? 0), 2) }} m²</strong></div>
                        <div class="tc-source"><span>Recommendation</span><strong>{{ $sources['recommendation']?->recommended_system_label ?? 'Not available' }}</strong></div>
                    </div>
                    @if ($calculation->calculated_at && $canVerify)
                        <form method="POST" action="{{ route('companies.technical-calculations.verify', [$company, $project]) }}" style="margin-top:16px">
                            @csrf
                            @method('PATCH')
                            <button class="ui-button ui-button-primary" type="submit">Verify Calculation</button>
                        </form>
                    @endif
                </section>

                <section class="tc-card">
                    <div class="tc-card-head"><div><h3>Warnings</h3><p class="tc-card-subtitle">Compatibility and source-data issues detected by the engine.</p></div></div>
                    <ul class="tc-warning-list">
                        @forelse (($calculation->warnings ?? []) as $warning)
                            <li>{{ $warning }}</li>
                        @empty
                            <li class="tc-no-warning">No active calculation warnings.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="tc-card">
                    <div class="tc-card-head"><div><h3>Change History</h3><p class="tc-card-subtitle">Calculation and verification events.</p></div></div>
                    <ul class="tc-history">
                        @forelse ($calculation->histories as $history)
                            <li><strong>{{ ucfirst($history->event) }}</strong><small>{{ $history->user?->name ?? 'System' }} · {{ $history->changed_at?->format('d M Y H:i') }}</small></li>
                        @empty
                            <li>No calculation history yet.</li>
                        @endforelse
                    </ul>
                </section>
            </aside>
        </div>
    </div>
@endsection
