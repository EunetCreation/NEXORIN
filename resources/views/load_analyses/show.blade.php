@extends('layouts.app')

@section('title', 'Load Analysis · '.$project->name)
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Load Analysis')

@push('styles')
<style>
    .load-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 28px;
        border-radius: 24px;
        color: #fff;
        background:
            radial-gradient(circle at 88% 12%, rgba(52, 214, 203, .24), transparent 30%),
            linear-gradient(135deg, #031522, #0a4661);
        box-shadow: var(--shadow-md);
    }

    .load-hero-copy { min-width: 0; }
    .load-hero-copy span { color: var(--teal-400); font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
    .load-hero-copy h2 { margin: 8px 0 7px; font-family: "Space Grotesk", sans-serif; font-size: clamp(28px, 4vw, 44px); }
    .load-hero-copy p { margin: 0; color: rgba(255,255,255,.63); }
    .load-hero-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
    .load-hero .ui-button-secondary { color: #fff; border-color: rgba(255,255,255,.18); background: rgba(255,255,255,.08); }

    .load-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(320px, .75fr);
        align-items: start;
        gap: 22px;
    }

    /* Grid children must be allowed to shrink inside their assigned column. */
    .load-grid > *,
    .load-stack,
    .load-stack > *,
    .load-grid .ui-card,
    .load-grid .ui-card-header,
    .load-grid .ui-card-body {
        min-width: 0;
    }

    .load-stack {
        display: grid;
        align-content: start;
        gap: 22px;
    }

    .load-grid .ui-card {
        overflow: hidden;
        background: #fff;
    }
    .load-metric-note { margin-top: 8px; color: var(--slate-500); font-size: 12px; }

    .profile-chart {
        min-height: 270px;
        display: grid;
        grid-template-columns: repeat(24, minmax(18px, 1fr));
        align-items: end;
        gap: 6px;
        padding: 24px 16px 10px;
        overflow-x: auto;
        border: 1px solid var(--slate-200);
        border-radius: 18px;
        background:
            linear-gradient(to top, rgba(6,29,46,.04) 1px, transparent 1px) 0 0 / 100% 25%,
            var(--slate-50);
    }

    .profile-column { min-width: 18px; display: grid; grid-template-rows: 210px auto; gap: 8px; align-items: end; }
    .profile-bar-wrap { height: 210px; display: flex; align-items: end; justify-content: center; }
    .profile-bar {
        width: min(24px, 78%);
        min-height: 2px;
        border-radius: 7px 7px 3px 3px;
        background: linear-gradient(180deg, var(--teal-400), var(--teal-600));
        box-shadow: 0 8px 18px rgba(13,169,159,.20);
    }
    .profile-hour { color: var(--slate-500); font-size: 9px; text-align: center; }
    .profile-value { display: block; margin-bottom: 4px; color: var(--slate-700); font-size: 9px; text-align: center; white-space: nowrap; }

    .load-validation {
        padding: 18px;
        border: 1px solid rgba(22,190,179,.24);
        border-radius: 18px;
        background: rgba(223,248,245,.52);
    }
    .load-validation strong { display: block; font-family: "Space Grotesk", sans-serif; font-size: 18px; }
    .load-validation p { margin: 7px 0 0; color: var(--slate-600); font-size: 13px; line-height: 1.65; }

    .load-table-actions {
        display: flex;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 8px;
    }

    .load-inline-form {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        align-items: end;
    }

    .load-inline-form > * {
        min-width: 0;
    }

    .load-inline-form > .ui-button {
        grid-column: 1 / -1;
        justify-self: end;
    }
    .load-critical { color: #a24820; font-weight: 700; }
    .load-empty { padding: 24px; color: var(--slate-500); text-align: center; }

    @media (max-width: 1320px) {
        .load-inline-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 1180px) {
        .load-grid { grid-template-columns: minmax(0, 1fr); }
    }

    @media (max-width: 720px) {
        .load-hero { align-items: flex-start; flex-direction: column; }
        .load-hero-actions { width: 100%; justify-content: flex-start; }
        .load-hero-actions .ui-button { flex: 1 1 180px; }
        .load-inline-form { grid-template-columns: minmax(0, 1fr); }
        .load-inline-form > .ui-button { width: 100%; justify-self: stretch; }
    }
</style>
@endpush

@section('content')
    <div class="ui-page">
        <section class="load-hero">
            <div class="load-hero-copy">
                <span>{{ $project->project_number }} · {{ $project->customer->name }}</span>
                <h2>{{ $project->name }}</h2>
                <p>Appliance demand, monthly bill validation, daytime share, and the preliminary 24-hour load profile.</p>
            </div>

            <div class="load-hero-actions">
                <a href="{{ route('companies.load-analyses.index', $company) }}" class="ui-button ui-button-secondary">Analysis Directory</a>
                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Project Details</a>
                @if ($canManage)
                    <a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="ui-button ui-button-primary">Add Equipment</a>
                @endif
            </div>
        </section>

        @if (! $analysis->exists)
            <div class="ui-alert ui-alert-warning" role="status">
                <div>This analysis has not been started. An authorized project owner can add the first load input.</div>
            </div>
        @endif

        <section class="ui-stats-grid" aria-label="Load summary">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">kW</span></div>
                <strong class="ui-stat-value">{{ number_format($summary['connected_load_kw'], 2) }}</strong>
                <span class="ui-stat-label">Connected Load</span>
                <div class="load-metric-note">Starting load {{ number_format($summary['starting_load_kw'], 2) }} kW</div>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">24h</span></div>
                <strong class="ui-stat-value">{{ number_format($summary['daily_energy_kwh'], 2) }}</strong>
                <span class="ui-stat-label">Daily Energy · kWh</span>
                <div class="load-metric-note">{{ number_format($summary['monthly_energy_kwh'], 1) }} kWh per {{ $analysis->billing_days }} days</div>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PK</span></div>
                <strong class="ui-stat-value">{{ number_format($summary['peak_demand_kw'], 2) }}</strong>
                <span class="ui-stat-label">Estimated Peak · kW</span>
                <div class="load-metric-note">Load factor {{ number_format($summary['load_factor_percent'], 1) }}%</div>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">DAY</span></div>
                <strong class="ui-stat-value">{{ number_format($summary['day_share_percent'], 1) }}%</strong>
                <span class="ui-stat-label">Daytime Energy Share</span>
                <div class="load-metric-note">Night share {{ number_format($summary['night_share_percent'], 1) }}%</div>
            </article>
        </section>

        <div class="load-grid">
            <div class="load-stack">
                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">24-Hour Load Profile</h3>
                            <p class="ui-card-description">Hourly energy is generated from each equipment start hour, duration, quantity, power, and usage factor.</p>
                        </div>
                        <span class="ui-status ui-status-active">{{ number_format($summary['appliance_count']) }} equipment groups</span>
                    </div>
                    <div class="ui-card-body">
                        <div class="profile-chart" aria-label="Hourly load profile chart">
                            @foreach ($hourlyProfile as $hour => $energy)
                                @php
                                    $height = $profileMaximum > 0 ? ($energy / $profileMaximum) * 100 : 0;
                                @endphp
                                <div class="profile-column" title="{{ sprintf('%02d:00', $hour) }} · {{ number_format($energy, 3) }} kWh">
                                    <div class="profile-bar-wrap">
                                        <div>
                                            @if ($energy > 0)
                                                <span class="profile-value">{{ number_format($energy, 1) }}</span>
                                            @endif
                                            <div class="profile-bar" style="height: {{ $energy > 0 ? max(3, ($height / 100) * 200) : 2 }}px"></div>
                                        </div>
                                    </div>
                                    <span class="profile-hour">{{ sprintf('%02d', $hour) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Equipment and Appliance Loads</h3>
                            <p class="ui-card-description">The equipment list is the primary bottom-up energy estimate.</p>
                        </div>
                        @if ($canManage)
                            <a href="{{ route('companies.load-analyses.appliances.create', [$company, $project]) }}" class="ui-button ui-button-primary">Add Equipment</a>
                        @endif
                    </div>

                    @if ($analysis->appliances->isEmpty())
                        <div class="load-empty">No equipment has been added. Start with lighting, cooling, pumps, production equipment, and critical loads.</div>
                    @else
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
                                        <tr>
                                            <td>
                                                <div class="ui-table-primary">{{ $appliance->name }}</div>
                                                <div class="ui-table-secondary">{{ $appliance->category ?: 'Uncategorized' }} · usage {{ number_format((float) $appliance->usage_factor * 100, 0) }}%</div>
                                            </td>
                                            <td>{{ number_format($appliance->quantity) }} × {{ number_format((float) $appliance->rated_power_w, 1) }} W</td>
                                            <td>{{ $appliance->schedule_label }}</td>
                                            <td>{{ number_format($appliance->daily_energy_kwh, 3) }} kWh</td>
                                            <td>
                                                @if ($appliance->is_critical)
                                                    <span class="load-critical">Critical</span>
                                                @else
                                                    Standard
                                                @endif
                                            </td>
                                            @if ($canManage)
                                                <td>
                                                    <div class="load-table-actions">
                                                        <a href="{{ route('companies.load-analyses.appliances.edit', [$company, $project, $appliance]) }}" class="ui-button ui-button-secondary">Edit</a>
                                                        <form method="POST" action="{{ route('companies.load-analyses.appliances.destroy', [$company, $project, $appliance]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="ui-button ui-button-danger" data-confirm="Remove this equipment load?">Remove</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Monthly Electricity Bills</h3>
                            <p class="ui-card-description">Enter historical kWh and bill values to validate the bottom-up appliance estimate.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        @if ($canManage)
                            <form method="POST" action="{{ route('companies.load-analyses.bills.store', [$company, $project]) }}" class="load-inline-form">
                                @csrf
                                <div class="ui-field">
                                    <label class="ui-label" for="billing_month">Month</label>
                                    <input id="billing_month" class="ui-input @error('billing_month') is-invalid @enderror" type="month" name="billing_month" value="{{ old('billing_month', now()->subMonth()->format('Y-m')) }}" required>
                                    @error('billing_month')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="energy_kwh">Energy (kWh)</label>
                                    <input id="energy_kwh" class="ui-input @error('energy_kwh') is-invalid @enderror" type="number" name="energy_kwh" value="{{ old('energy_kwh') }}" min="0.001" step="0.001" required>
                                    @error('energy_kwh')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="bill_amount">Bill amount (IDR)</label>
                                    <input id="bill_amount" class="ui-input @error('bill_amount') is-invalid @enderror" type="number" name="bill_amount" value="{{ old('bill_amount') }}" min="0" step="1">
                                    @error('bill_amount')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="peak_demand_kw">Peak demand (kW)</label>
                                    <input id="peak_demand_kw" class="ui-input @error('peak_demand_kw') is-invalid @enderror" type="number" name="peak_demand_kw" value="{{ old('peak_demand_kw') }}" min="0" step="0.001">
                                    @error('peak_demand_kw')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="ui-button ui-button-primary">Save Bill</button>
                            </form>
                        @endif
                    </div>

                    @if ($analysis->electricityBills->isEmpty())
                        <div class="load-empty">No bill history has been recorded.</div>
                    @else
                        <div class="ui-table-scroll">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Energy</th>
                                        <th>Bill Amount</th>
                                        <th>Effective Rate</th>
                                        <th>Peak Demand</th>
                                        @if ($canManage)<th aria-label="Actions"></th>@endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($analysis->electricityBills as $bill)
                                        <tr>
                                            <td>{{ $bill->billing_month->format('M Y') }}</td>
                                            <td>{{ number_format((float) $bill->energy_kwh, 3) }} kWh</td>
                                            <td>{{ $bill->bill_amount === null ? 'Not provided' : 'Rp '.number_format((float) $bill->bill_amount, 0, ',', '.') }}</td>
                                            <td>{{ $bill->effective_rate === null ? 'Not available' : 'Rp '.number_format($bill->effective_rate, 0, ',', '.').' / kWh' }}</td>
                                            <td>{{ $bill->peak_demand_kw === null ? 'Not provided' : number_format((float) $bill->peak_demand_kw, 3).' kW' }}</td>
                                            @if ($canManage)
                                                <td>
                                                    <form method="POST" action="{{ route('companies.load-analyses.bills.destroy', [$company, $project, $bill]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="ui-button ui-button-danger" data-confirm="Remove this bill record?">Remove</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <div class="load-stack">
                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Analysis Settings</h3>
                            <p class="ui-card-description">Grid connection and calculation assumptions.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        @if ($canManage)
                            <form method="POST" action="{{ route('companies.load-analyses.update', [$company, $project]) }}">
                                @csrf
                                @method('PUT')
                                <div class="ui-form-grid">
                                    <div class="ui-field ui-form-span-2">
                                        <label class="ui-label" for="source_mode">Primary data method</label>
                                        <select id="source_mode" class="ui-select @error('source_mode') is-invalid @enderror" name="source_mode" required>
                                            <option value="combined" @selected(old('source_mode', $analysis->source_mode) === 'combined')>Combined validation</option>
                                            <option value="equipment" @selected(old('source_mode', $analysis->source_mode) === 'equipment')>Equipment list</option>
                                            <option value="bill" @selected(old('source_mode', $analysis->source_mode) === 'bill')>Electricity bills</option>
                                        </select>
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
                                    <button type="submit" class="ui-button ui-button-primary">Save Settings</button>
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
                </section>

                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Bill Validation</h3>
                            <p class="ui-card-description">Comparison between equipment-derived monthly energy and historical bills.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        @if ($summary['average_bill_kwh'] === null)
                            <div class="load-validation">
                                <strong>Bill history required</strong>
                                <p>Add at least one monthly kWh record to compare the appliance estimate with actual consumption.</p>
                            </div>
                        @elseif ($summary['bill_variance_percent'] === null)
                            <div class="load-validation">
                                <strong>{{ number_format($summary['average_bill_kwh'], 1) }} kWh average bill</strong>
                                <p>Add equipment loads before comparing the bottom-up estimate with historical consumption.</p>
                            </div>
                        @else
                            <div class="load-validation">
                                <strong>{{ number_format($summary['average_bill_kwh'], 1) }} kWh average bill</strong>
                                <p>
                                    Equipment estimate: {{ number_format($summary['equipment_monthly_energy_kwh'], 1) }} kWh.
                                    Difference: {{ number_format(abs($summary['bill_variance_percent']), 1) }}%
                                    {{ $summary['bill_variance_percent'] > 0 ? 'above' : 'below' }} the historical average.
                                </p>
                            </div>
                        @endif

                        <dl class="ui-info-grid" style="margin-top: 16px">
                            <div class="ui-info-item"><dt>Average Bill Amount</dt><dd>{{ $summary['average_bill_amount'] === null ? 'Not available' : 'Rp '.number_format($summary['average_bill_amount'], 0, ',', '.') }}</dd></div>
                            <div class="ui-info-item"><dt>Effective Historical Rate</dt><dd>{{ $summary['effective_rate_per_kwh'] === null ? 'Not available' : 'Rp '.number_format($summary['effective_rate_per_kwh'], 0, ',', '.').' / kWh' }}</dd></div>
                            <div class="ui-info-item"><dt>Critical Daily Energy</dt><dd>{{ number_format($summary['critical_daily_energy_kwh'], 3) }} kWh</dd></div>
                            <div class="ui-info-item"><dt>Annualized Energy</dt><dd>{{ number_format($summary['annual_energy_kwh'], 1) }} kWh</dd></div>
                        </dl>
                    </div>
                </section>

                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Calculation Status</h3>
                            <p class="ui-card-description">Current data completeness for the next engineering stage.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        <dl class="ui-info-grid">
                            <div class="ui-info-item"><dt>Equipment Groups</dt><dd>{{ number_format($summary['appliance_count']) }}</dd></div>
                            <div class="ui-info-item"><dt>Bill Months</dt><dd>{{ number_format($summary['bill_count']) }}</dd></div>
                            <div class="ui-info-item"><dt>Last Updated</dt><dd>{{ $analysis->exists ? $analysis->updated_at?->format('d M Y, H:i') : 'Not started' }}</dd></div>
                            <div class="ui-info-item"><dt>Result Status</dt><dd>Preliminary Engineering Estimate</dd></div>
                        </dl>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
