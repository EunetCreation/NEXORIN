@extends('layouts.app')

@section('title', 'Location & Solar Data · '.$project->name)
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Location & Solar Data')

@push('styles')
<style>
    .solar-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 28px;
        border-radius: 24px;
        color: #fff;
        background:
            radial-gradient(circle at 88% 12%, rgba(245, 168, 0, .20), transparent 30%),
            radial-gradient(circle at 72% 110%, rgba(52, 214, 203, .20), transparent 38%),
            linear-gradient(135deg, #031522, #0a4661);
        box-shadow: var(--shadow-md);
    }

    .solar-hero-copy { min-width: 0; }
    .solar-hero-copy span { color: var(--teal-400); font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
    .solar-hero-copy h2 { margin: 8px 0 7px; font-family: "Space Grotesk", sans-serif; font-size: clamp(28px, 4vw, 44px); }
    .solar-hero-copy p { margin: 0; color: rgba(255,255,255,.63); }
    .solar-hero-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
    .solar-hero .ui-button-secondary { color: #fff; border-color: rgba(255,255,255,.22); background: rgba(255,255,255,.08); }

    .solar-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(300px, .85fr);
        gap: 24px;
        align-items: start;
    }

    .solar-stack { min-width: 0; display: grid; gap: 24px; }
    .solar-side { min-width: 0; display: grid; gap: 24px; }
    .solar-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .solar-form-grid .full { grid-column: 1 / -1; }
    .solar-source-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .solar-source-grid .full { grid-column: 1 / -1; }

    .solar-table { min-width: 860px; }
    .solar-table .ui-input { min-width: 118px; }
    .solar-month { font-weight: 700; color: var(--navy-900); }
    .solar-unit { color: var(--slate-500); font-size: 11px; }

    .solar-status-panel { display: grid; gap: 16px; }
    .solar-progress { height: 10px; overflow: hidden; border-radius: 999px; background: #e6eff3; }
    .solar-progress span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--teal-500), var(--teal-400)); }
    .solar-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }
    .solar-fact { min-width: 0; padding: 16px; background: #fff; }
    .solar-fact:nth-child(odd) { border-right: 1px solid var(--line); }
    .solar-fact:nth-child(n+3) { border-top: 1px solid var(--line); }
    .solar-fact span { display: block; color: var(--slate-500); font-size: 10px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
    .solar-fact strong { display: block; margin-top: 8px; color: var(--navy-900); font-size: 16px; }
    .solar-history { display: grid; gap: 12px; }
    .solar-history-item { padding: 14px; border: 1px solid var(--line); border-radius: 15px; background: #fbfdfe; }
    .solar-history-item strong { display: block; color: var(--navy-900); }
    .solar-history-item p { margin: 6px 0 0; color: var(--slate-600); font-size: 12px; line-height: 1.6; }
    .solar-history-meta { margin-top: 8px; color: var(--slate-500); font-size: 11px; }
    .solar-readonly-note { padding: 12px 14px; border: 1px solid #dbe9ee; border-radius: 14px; color: #446275; background: #f5fafb; font-size: 12px; }

    @media (max-width: 1180px) {
        .solar-layout { grid-template-columns: 1fr; }
        .solar-side { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .solar-side > *:last-child { grid-column: 1 / -1; }
    }

    @media (max-width: 760px) {
        .solar-hero { align-items: flex-start; flex-direction: column; }
        .solar-hero-actions { justify-content: flex-start; }
        .solar-form-grid, .solar-source-grid, .solar-side { grid-template-columns: 1fr; }
        .solar-form-grid .full, .solar-source-grid .full, .solar-side > *:last-child { grid-column: auto; }
    }
</style>
@endpush

@section('content')
    <div class="ui-page">
        <section class="solar-hero">
            <div class="solar-hero-copy">
                <span>{{ $project->project_number }} · {{ $project->customer->name }}</span>
                <h2>{{ $project->name }}</h2>
                <p>Project coordinates, solar resource assumptions, monthly climate values, and engineering verification.</p>
            </div>

            <div class="solar-hero-actions">
                <a href="{{ route('companies.location-solar.index', $company) }}" class="ui-button ui-button-secondary">Solar Directory</a>
                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Project Details</a>
            </div>
        </section>

        <section class="ui-stats-grid" aria-label="Solar data summary">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PSH</span></div>
                <strong class="ui-stat-value">{{ number_format($summary['average_psh'], 2) }}</strong>
                <span class="ui-stat-label">Annual Average PSH</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">GHI</span></div>
                <strong class="ui-stat-value">{{ number_format($summary['annual_irradiation_kwh_m2'], 0) }}</strong>
                <span class="ui-stat-label">Annual GHI · kWh/m²</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">°C</span></div>
                <strong class="ui-stat-value">{{ number_format($summary['average_temperature_c'], 1) }}</strong>
                <span class="ui-stat-label">Average Temperature</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">{{ $summary['complete_months'] }}/12</span></div>
                <strong class="ui-stat-value">{{ number_format($summary['readiness_percent'], 0) }}%</strong>
                <span class="ui-stat-label">Dataset Readiness</span>
            </article>
        </section>

        @error('verification')
            <div class="ui-alert ui-alert-danger">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('companies.location-solar.update', [$company, $project]) }}">
            @csrf
            @method('PUT')

            <div class="solar-layout">
                <div class="solar-stack">
                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h3 class="ui-card-title">Project Location</h3>
                                <p class="ui-card-description">Use the actual installation site. Coordinates are required before engineering verification.</p>
                            </div>
                        </div>
                        <div class="ui-card-body">
                            <div class="solar-form-grid">
                                <div class="ui-field full">
                                    <label class="ui-label" for="site_address">Site address</label>
                                    <input id="site_address" class="ui-input @error('site_address') is-invalid @enderror" name="site_address" value="{{ old('site_address', $project->site_address) }}" @disabled(! $canManage) required>
                                    @error('site_address')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="city">City</label>
                                    <input id="city" class="ui-input @error('city') is-invalid @enderror" name="city" value="{{ old('city', $project->city) }}" @disabled(! $canManage) required>
                                    @error('city')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="province">Province</label>
                                    <input id="province" class="ui-input @error('province') is-invalid @enderror" name="province" value="{{ old('province', $project->province) }}" @disabled(! $canManage) required>
                                    @error('province')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="postal_code">Postal code</label>
                                    <input id="postal_code" class="ui-input" name="postal_code" value="{{ old('postal_code', $project->postal_code) }}" @disabled(! $canManage)>
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="elevation_m">Elevation (m)</label>
                                    <input id="elevation_m" class="ui-input" type="number" step="0.01" name="elevation_m" value="{{ old('elevation_m', $profile->elevation_m) }}" @disabled(! $canManage)>
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="latitude">Latitude</label>
                                    <input id="latitude" class="ui-input @error('latitude') is-invalid @enderror" type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $project->latitude) }}" placeholder="0.507067" @disabled(! $canManage)>
                                    @error('latitude')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="ui-field">
                                    <label class="ui-label" for="longitude">Longitude</label>
                                    <input id="longitude" class="ui-input @error('longitude') is-invalid @enderror" type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $project->longitude) }}" placeholder="101.447777" @disabled(! $canManage)>
                                    @error('longitude')<div class="ui-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="ui-field full">
                                    <label class="ui-label" for="timezone">Timezone</label>
                                    <select id="timezone" class="ui-select" name="timezone" @disabled(! $canManage)>
                                        @foreach (['Asia/Jakarta' => 'WIB · Asia/Jakarta', 'Asia/Makassar' => 'WITA · Asia/Makassar', 'Asia/Jayapura' => 'WIT · Asia/Jayapura'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('timezone', $profile->timezone) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h3 class="ui-card-title">Monthly Solar Dataset</h3>
                                <p class="ui-card-description">Enter daily monthly averages. GHI, PSH, and ambient temperature are required for verification. POA is optional.</p>
                            </div>
                        </div>
                        <div class="ui-table-scroll">
                            <table class="ui-table solar-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>GHI Daily<br><span class="solar-unit">kWh/m²/day</span></th>
                                        <th>POA Daily<br><span class="solar-unit">kWh/m²/day</span></th>
                                        <th>Peak Sun Hours<br><span class="solar-unit">h/day</span></th>
                                        <th>Ambient Temp.<br><span class="solar-unit">°C</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($monthlyRows as $row)
                                        <tr>
                                            <td class="solar-month">{{ $row->month_name }}</td>
                                            <td><input class="ui-input" type="number" step="0.001" min="0" max="20" name="monthly[{{ $row->month }}][ghi_daily_kwh_m2]" value="{{ old('monthly.'.$row->month.'.ghi_daily_kwh_m2', $row->ghi_daily_kwh_m2) }}" @disabled(! $canManage)></td>
                                            <td><input class="ui-input" type="number" step="0.001" min="0" max="20" name="monthly[{{ $row->month }}][poa_daily_kwh_m2]" value="{{ old('monthly.'.$row->month.'.poa_daily_kwh_m2', $row->poa_daily_kwh_m2) }}" @disabled(! $canManage)></td>
                                            <td><input class="ui-input" type="number" step="0.01" min="0" max="24" name="monthly[{{ $row->month }}][peak_sun_hours]" value="{{ old('monthly.'.$row->month.'.peak_sun_hours', $row->peak_sun_hours) }}" @disabled(! $canManage)></td>
                                            <td><input class="ui-input" type="number" step="0.01" min="-60" max="80" name="monthly[{{ $row->month }}][ambient_temperature_c]" value="{{ old('monthly.'.$row->month.'.ambient_temperature_c', $row->ambient_temperature_c) }}" @disabled(! $canManage)></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <aside class="solar-side">
                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h3 class="ui-card-title">Source & Assumptions</h3>
                                <p class="ui-card-description">Record where the values came from and when the source was accessed.</p>
                            </div>
                        </div>
                        <div class="ui-card-body">
                            <div class="solar-source-grid">
                                <div class="ui-field full">
                                    <label class="ui-label" for="data_source">Data source type</label>
                                    <select id="data_source" class="ui-select" name="data_source" @disabled(! $canManage)>
                                        <option value="manual" @selected(old('data_source', $profile->data_source) === 'manual')>Manual Input</option>
                                        <option value="reference" @selected(old('data_source', $profile->data_source) === 'reference')>Reference Dataset</option>
                                        <option value="api" @selected(old('data_source', $profile->data_source) === 'api')>API Import</option>
                                    </select>
                                </div>
                                <div class="ui-field full">
                                    <label class="ui-label" for="source_name">Source name</label>
                                    <input id="source_name" class="ui-input" name="source_name" value="{{ old('source_name', $profile->source_name) }}" placeholder="NASA POWER, PVGIS, BMKG, or manual survey" @disabled(! $canManage)>
                                </div>
                                <div class="ui-field full">
                                    <label class="ui-label" for="source_reference">Reference or dataset URL</label>
                                    <input id="source_reference" class="ui-input" name="source_reference" value="{{ old('source_reference', $profile->source_reference) }}" placeholder="Dataset identifier or reference note" @disabled(! $canManage)>
                                </div>
                                <div class="ui-field full">
                                    <label class="ui-label" for="source_date">Source date</label>
                                    <input id="source_date" class="ui-input" type="date" name="source_date" value="{{ old('source_date', $profile->source_date?->format('Y-m-d')) }}" @disabled(! $canManage)>
                                </div>
                                <div class="ui-field full">
                                    <label class="ui-label" for="notes">Engineering notes</label>
                                    <textarea id="notes" class="ui-textarea" name="notes" rows="5" placeholder="Data quality, assumptions, corrections, or limitations" @disabled(! $canManage)>{{ old('notes', $profile->notes) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h3 class="ui-card-title">Dataset Status</h3>
                                <p class="ui-card-description">Current readiness for the roof and site survey stage.</p>
                            </div>
                            <span class="ui-status {{ $profile->quality_status === 'verified' ? 'ui-status-active' : ($profile->quality_status === 'incomplete' ? 'ui-status-inactive' : 'ui-status-pending') }}">
                                {{ $profile->quality_status_label }}
                            </span>
                        </div>
                        <div class="ui-card-body solar-status-panel">
                            <div>
                                <div class="solar-progress"><span style="width: {{ min(100, $summary['readiness_percent']) }}%"></span></div>
                                <div class="solar-unit" style="margin-top:8px">{{ number_format($summary['readiness_percent'], 1) }}% complete</div>
                            </div>

                            <div class="solar-facts">
                                <div class="solar-fact"><span>Months</span><strong>{{ $summary['complete_months'] }}/12</strong></div>
                                <div class="solar-fact"><span>Potential</span><strong>{{ $summary['potential_label'] }}</strong></div>
                                <div class="solar-fact"><span>Source</span><strong>{{ $profile->data_source_label }}</strong></div>
                                <div class="solar-fact"><span>Verified By</span><strong>{{ $profile->verifier?->name ?? 'Not verified' }}</strong></div>
                            </div>

                            @if (! $canManage)
                                <div class="solar-readonly-note">You have read-only access to this dataset.</div>
                            @endif

                            @if ($canManage)
                                <button type="submit" class="ui-button ui-button-primary">Save Location & Solar Data</button>
                            @endif
                        </div>
                    </section>
                </aside>
            </div>
        </form>

        <div class="solar-layout">
            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Change History</h3>
                        <p class="ui-card-description">Source, status, and verification changes are retained for traceability.</p>
                    </div>
                </div>
                <div class="ui-card-body solar-history">
                    @forelse ($profile->histories->take(8) as $history)
                        <article class="solar-history-item">
                            <strong>{{ ucfirst($history->event_type) }}</strong>
                            <p>{{ $history->summary }}</p>
                            <div class="solar-history-meta">
                                {{ $history->changedBy?->name ?? 'System' }} · {{ $history->changed_at?->format('d M Y, H:i') }}
                                @if ($history->previous_status !== $history->new_status)
                                    · {{ $history->previous_status ?? 'none' }} → {{ $history->new_status ?? 'none' }}
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty"><div><h3>No history yet</h3><p>The first save will create an audit entry.</p></div></div>
                    @endforelse
                </div>
            </section>

            <aside class="solar-side">
                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Engineering Verification</h3>
                            <p class="ui-card-description">Verification confirms dataset completeness, not structural suitability or final energy yield.</p>
                        </div>
                    </div>
                    <div class="ui-card-body solar-status-panel">
                        @if ($profile->quality_status === 'verified')
                            <div class="solar-readonly-note">
                                Verified by {{ $profile->verifier?->name ?? 'an authorized user' }} on {{ $profile->verified_at?->format('d M Y, H:i') }}.
                            </div>
                        @elseif ($summary['is_ready_for_verification'])
                            <div class="solar-readonly-note">The dataset is complete and ready for engineering verification.</div>
                        @else
                            <div class="solar-readonly-note">Complete coordinates, source name, and 12 monthly GHI, PSH, and temperature records.</div>
                        @endif

                        @if ($canVerify && $profile->quality_status !== 'verified')
                            <form method="POST" action="{{ route('companies.location-solar.verify', [$company, $project]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="ui-button ui-button-primary" @disabled(! $summary['is_ready_for_verification'])>Verify Dataset</button>
                            </form>
                        @endif
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
