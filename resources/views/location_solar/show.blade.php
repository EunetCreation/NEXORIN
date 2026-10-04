@extends('layouts.app')

@section('title', 'Location & Solar Resource · '.$project->name)
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Location & Solar Resource')
@section('force-theme', 'dark')
@section('body-class', 'page-location-solar-show-v6')

@push('styles')
    <link href="{{ asset('css/nexorin-location-solar-show-v6.css') }}?v=020e" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-location-solar-show-v6.js') }}?v=020e" defer></script>
@endpush

@php
    $hasDataset = $profile->exists;
    $state = $assessment['state'];
    $stateLabel = $assessment['state_label'];
    $isReviewed = $assessment['is_dataset_reviewed'];
    $isComplete = $assessment['is_dataset_complete'];
    $sourceSelection = old('data_source', $profile->data_source ?: 'manual');
    $timezoneSelection = old('timezone', $profile->timezone);
    $monthAbbreviations = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
    $historyLabels = [
        'started' => 'Dataset started',
        'updated' => 'Dataset updated',
        'verified' => 'Dataset reviewed',
        'reviewed' => 'Dataset reviewed',
    ];
    $historyTone = fn (string $event) => in_array($event, ['verified', 'reviewed'], true) ? 'reviewed' : ($event === 'started' ? 'started' : 'updated');
    $averageLabel = $assessment['complete_months'] === 12 ? 'Annual average' : 'Recorded-month average';
    $annualGhi = $assessment['derived_annual_ghi_kwh_m2'];
    $timezoneOptions = [
        'Asia/Jakarta' => 'Asia/Jakarta · WIB (UTC+7)',
        'Asia/Makassar' => 'Asia/Makassar · WITA (UTC+8)',
        'Asia/Jayapura' => 'Asia/Jayapura · WIT (UTC+9)',
    ];
@endphp

@section('content')
    @if ($hasDataset && $canReview && ! $isReviewed)
        <form id="nx20-review-form" method="POST" action="{{ route('companies.location-solar.verify', [$company, $project]) }}" hidden>
            @csrf
            @method('PATCH')
        </form>
    @endif

    <div
        class="nx20-page"
        data-nx20-page
        data-map-url="{{ asset('data/indonesia-country.geojson') }}"
        data-has-dataset="{{ $hasDataset ? 'true' : 'false' }}"
        data-can-manage="{{ $canManage ? 'true' : 'false' }}"
        data-can-review="{{ $canReview ? 'true' : 'false' }}"
        data-reviewed="{{ $isReviewed ? 'true' : 'false' }}"
        data-saved-latitude="{{ $project->latitude }}"
        data-saved-longitude="{{ $project->longitude }}"
    >
        <header class="nx20-header">
            <div class="nx20-header-copy">
                <div class="nx20-breadcrumbs">
                    <a href="{{ route('companies.location-solar.index', $company) }}">Solar Resource Registry</a>
                    <span>/</span>
                    <span>{{ $project->project_number }}</span>
                </div>
                <div class="nx20-kicker"><i></i> Project Solar Resource</div>
                <h2>{{ $project->name }}</h2>
                <p>Record the project location, monthly solar-resource basis, and source provenance used by the current feasibility workflow.</p>
                <div class="nx20-header-meta">
                    <span><small>Customer</small><strong>{{ $project->customer->name }}</strong></span>
                    <span><small>Location</small><strong data-nx20-summary-location>{{ $project->city ?: 'Not Recorded' }}{{ $project->province ? ', '.$project->province : '' }}</strong></span>
                    <span><small>Current state</small><strong class="nx20-state-text is-{{ $state }}" data-nx20-state-label>{{ $stateLabel }}</strong></span>
                </div>
            </div>

            <div class="nx20-header-actions">
                <a href="{{ route('companies.location-solar.index', $company) }}" class="nx20-btn nx20-btn-ghost">Back to Registry</a>
                @if (! $hasDataset && $canManage)
                    <form method="POST" action="{{ route('companies.location-solar.start', [$company, $project]) }}" data-nx20-start-form>
                        @csrf
                        <button type="submit" class="nx20-btn nx20-btn-primary">Start Dataset</button>
                    </form>
                @elseif ($hasDataset && $canManage)
                    <button type="submit" form="nx20-dataset-form" class="nx20-btn nx20-btn-primary" data-nx20-save>Save Dataset</button>
                @endif
                @if ($hasDataset && $canReview)
                    <button type="submit" form="nx20-review-form" class="nx20-btn nx20-btn-review" data-nx20-review @disabled(! $isComplete || $isReviewed)>
                        {{ $isReviewed ? 'Dataset Reviewed' : 'Complete Review' }}
                    </button>
                @endif
            </div>
        </header>

        <section class="nx20-metrics" aria-label="Current solar dataset summary">
            <article>
                <span>Recorded Coordinates</span>
                <strong data-nx20-metric-coordinates>{{ $assessment['coordinates_label'] }}</strong>
                <small>{{ $assessment['has_coordinates'] ? number_format((float) $project->latitude, 5).', '.number_format((float) $project->longitude, 5) : 'Project coordinate pair' }}</small>
            </article>
            <article>
                <span>Required Months</span>
                <strong data-nx20-metric-months>{{ $assessment['complete_months'] }}/12</strong>
                <small>GHI · PSH · ambient temperature</small>
            </article>
            <article>
                <span>Source Basis</span>
                <strong data-nx20-metric-source>{{ $assessment['source_type_label'] }}</strong>
                <small data-nx20-source-name>{{ filled($profile->source_name) ? $profile->source_name : 'Source name not recorded' }}</small>
            </article>
            <article>
                <span>Derived Annual GHI</span>
                <strong data-nx20-metric-annual-ghi>{{ $annualGhi !== null ? number_format($annualGhi, 0) : 'Not Available' }}</strong>
                <small>{{ $annualGhi !== null ? 'kWh/m²/year · stored monthly basis' : 'Available after 12 required months' }}</small>
            </article>
            <article>
                <span>Dataset State</span>
                <strong class="nx20-state-text is-{{ $state }}" data-nx20-metric-state>{{ $stateLabel }}</strong>
                <small data-nx20-state-copy>{{ $isReviewed ? 'Current recorded dataset review exists' : ($isComplete ? 'Eligible for current dataset review' : 'Additional recorded inputs required') }}</small>
            </article>
        </section>

        @if ($errors->any())
            <div class="nx20-alert" role="alert">
                <strong>Review the highlighted location and solar-resource inputs.</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @if (! $hasDataset)
            <section class="nx20-no-dataset">
                <div>
                    <span class="nx20-section-kicker">No Dataset</span>
                    <h3>No persisted solar-resource dataset exists for this project.</h3>
                    <p>The project location remains visible, but monthly resource values and source metadata can only be saved after the explicit Start Dataset workflow.</p>
                </div>
                @if ($canManage)
                    <form method="POST" action="{{ route('companies.location-solar.start', [$company, $project]) }}">
                        @csrf
                        <button type="submit" class="nx20-btn nx20-btn-primary">Start Solar Dataset</button>
                    </form>
                @else
                    <span class="nx20-readonly-pill">Read-only access</span>
                @endif
            </section>
        @endif

        <nav class="nx20-tabs" aria-label="Solar workspace sections">
            <a href="#nx20-location" class="is-active" data-nx20-section-link="nx20-location">Location & Source</a>
            <a href="#nx20-monthly" data-nx20-section-link="nx20-monthly">Monthly Resource</a>
            <a href="#nx20-history" data-nx20-section-link="nx20-history">Review & History</a>
            <span class="nx20-tabs-state"><i class="is-{{ $state }}"></i><b data-nx20-nav-state>{{ $stateLabel }}</b></span>
        </nav>

        <form
            id="nx20-dataset-form"
            method="POST"
            action="{{ $hasDataset ? route('companies.location-solar.update', [$company, $project]) : '#' }}"
            data-nx20-form
            novalidate
        >
            @csrf
            @if ($hasDataset) @method('PUT') @endif

            <div class="nx20-layout">
                <main class="nx20-main">
                    <section class="nx20-card nx20-location-card" id="nx20-location">
                        <header class="nx20-card-head">
                            <div><span class="nx20-section-kicker">Project Location</span><h3>Recorded Location Foundation</h3><p>Coordinates are recorded values only; survey or geocoding provenance is not inferred.</p></div>
                            <span class="nx20-chip" data-nx20-coordinate-state>{{ $assessment['coordinates_label'] }}</span>
                        </header>

                        <div class="nx20-location-grid">
                            <div class="nx20-map-panel">
                                <div class="nx20-map-toolbar">
                                    <div><strong data-nx20-map-title>{{ $assessment['has_coordinates'] ? 'Saved project coordinate' : 'Coordinate not recorded' }}</strong><small data-nx20-map-copy>{{ $assessment['has_coordinates'] ? 'Map marker reflects the currently saved latitude and longitude.' : 'Record a latitude and longitude pair to place the project marker.' }}</small></div>
                                    <button type="button" class="nx20-map-reset" data-nx20-map-reset>Reset</button>
                                </div>
                                <div class="nx20-map" data-nx20-map></div>
                                <div class="nx20-map-empty" data-nx20-map-empty @if($assessment['has_coordinates']) hidden @endif>No recorded coordinate pair</div>
                                <div class="nx20-map-fallback" data-nx20-map-fallback hidden>Map asset could not be displayed.</div>
                                <div class="nx20-map-readout" data-nx20-map-readout @if(! $assessment['has_coordinates']) hidden @endif>
                                    <span data-nx20-map-readout-label>Recorded point</span>
                                    <strong data-nx20-map-readout-value>{{ $assessment['has_coordinates'] ? number_format((float) $project->latitude, 5).', '.number_format((float) $project->longitude, 5) : 'Not Recorded' }}</strong>
                                </div>
                            </div>

                            <div class="nx20-fields nx20-location-fields">
                                <label class="nx20-field nx20-field-wide">
                                    <span>Site Address</span>
                                    <textarea name="site_address" rows="2" @disabled(! $canManage || ! $hasDataset)>{{ old('site_address', $project->site_address) }}</textarea>
                                </label>
                                <label class="nx20-field"><span>City / Regency</span><input type="text" name="city" value="{{ old('city', $project->city) }}" @disabled(! $canManage || ! $hasDataset)></label>
                                <label class="nx20-field"><span>Province</span><input type="text" name="province" value="{{ old('province', $project->province) }}" @disabled(! $canManage || ! $hasDataset)></label>
                                <label class="nx20-field"><span>Postal Code</span><input type="text" name="postal_code" value="{{ old('postal_code', $project->postal_code) }}" @disabled(! $canManage || ! $hasDataset)></label>
                                <label class="nx20-field"><span>Elevation</span><div class="nx20-input-unit"><input type="number" step="0.01" name="elevation_m" value="{{ old('elevation_m', $profile->elevation_m) }}" @disabled(! $canManage || ! $hasDataset)><em>m</em></div></label>
                                <label class="nx20-field"><span>Latitude</span><input type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $project->latitude) }}" data-nx20-latitude @disabled(! $canManage || ! $hasDataset)></label>
                                <label class="nx20-field"><span>Longitude</span><input type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $project->longitude) }}" data-nx20-longitude @disabled(! $canManage || ! $hasDataset)></label>
                                <label class="nx20-field nx20-field-wide nx20-timezone-field">
                                    <span class="nx20-field-label"><span>Recorded Timezone</span><b>Optional</b></span>
                                    <select name="timezone" @disabled(! $canManage || ! $hasDataset)>
                                        <option value="">Not Recorded</option>
                                        @if($timezoneSelection && ! array_key_exists($timezoneSelection, $timezoneOptions))
                                            <option value="{{ $timezoneSelection }}" selected>{{ $timezoneSelection }}</option>
                                        @endif
                                        @foreach($timezoneOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($timezoneSelection === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <small>
                                        @if($timezoneSelection === 'Asia/Jakarta')
                                            Confirm this value where relevant; older datasets may carry the former Asia/Jakarta default.
                                        @else
                                            Timezone is stored only when explicitly recorded.
                                        @endif
                                    </small>
                                </label>
                                @if ($canManage && $hasDataset)
                                    <div class="nx20-field-wide nx20-device-location">
                                        <button type="button" class="nx20-btn nx20-btn-ghost" data-nx20-geolocate>Use Current Device Location</button>
                                        <small>This populates the coordinate fields only; the current schema does not retain how the coordinate was obtained.</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </section>

                    <section class="nx20-card nx20-source-card">
                        <header class="nx20-card-head"><div><span class="nx20-section-kicker">Source & Provenance</span><h3>Recorded Resource Basis</h3><p>Describe where the monthly values came from without implying an ingestion workflow that is not recorded.</p></div></header>
                        <div class="nx20-source-modes">
                            @foreach([
                                'manual' => ['Manual entry', 'Values are entered from a controlled project source or worksheet.'],
                                'reference' => ['Reference source', 'Values are transcribed from a named external study or published reference.'],
                                'api' => ['API-designated source', 'The source is recorded as API-originated; this does not prove NEXORIN performed the import.'],
                            ] as $source => [$label, $copy])
                                <label class="nx20-source-option {{ $sourceSelection === $source ? 'is-selected' : '' }}">
                                    <input type="radio" name="data_source" value="{{ $source }}" @checked($sourceSelection === $source) @disabled(! $canManage || ! $hasDataset)>
                                    <span><strong>{{ $label }}</strong><small>{{ $copy }}</small></span>
                                </label>
                            @endforeach
                        </div>
                        <div class="nx20-fields nx20-source-fields">
                            <label class="nx20-field"><span>Source Name <b>required for completion</b></span><input type="text" name="source_name" value="{{ old('source_name', $profile->source_name) }}" data-nx20-source-name-input @disabled(! $canManage || ! $hasDataset)></label>
                            <label class="nx20-field"><span>Source Date</span><input type="date" name="source_date" value="{{ old('source_date', $profile->source_date?->format('Y-m-d')) }}" @disabled(! $canManage || ! $hasDataset)></label>
                            <label class="nx20-field nx20-field-wide"><span>Source Reference</span><input type="text" name="source_reference" value="{{ old('source_reference', $profile->source_reference) }}" @disabled(! $canManage || ! $hasDataset)></label>
                            <label class="nx20-field nx20-field-wide"><span>Engineering Notes</span><textarea name="notes" rows="3" @disabled(! $canManage || ! $hasDataset)>{{ old('notes', $profile->notes) }}</textarea></label>
                        </div>
                    </section>

                    <section class="nx20-card nx20-chart-card" id="nx20-monthly">
                        <header class="nx20-card-head nx20-chart-head">
                            <div><span class="nx20-section-kicker">Monthly Resource Profile</span><h3 data-nx20-chart-title>Monthly GHI</h3><p>Visualization uses the currently entered monthly values; unsaved form edits are preview only.</p></div>
                            <div class="nx20-chart-switcher" role="group" aria-label="Chart metric">
                                <button type="button" class="is-active" data-nx20-chart-metric="ghi">GHI</button>
                                <button type="button" data-nx20-chart-metric="psh">PSH</button>
                                <button type="button" data-nx20-chart-metric="temperature">Temperature</button>
                                <button type="button" data-nx20-chart-metric="poa">POA</button>
                            </div>
                        </header>
                        <div class="nx20-chart-layout">
                            <div class="nx20-chart" data-nx20-chart aria-label="Monthly solar-resource chart"></div>
                            <div class="nx20-chart-empty" data-nx20-chart-empty hidden>No recorded values for this metric.</div>
                            <aside class="nx20-chart-summary">
                                <div><span>Coverage</span><strong data-nx20-chart-coverage>{{ $assessment['complete_months'] }}/12 required months</strong></div>
                                <div><span>{{ $averageLabel }} PSH</span><strong data-nx20-summary-psh>{{ $assessment['average_psh'] !== null ? number_format($assessment['average_psh'], 2).' h/day' : 'Not Available' }}</strong></div>
                                <div><span>{{ $averageLabel }} temperature</span><strong data-nx20-summary-temperature>{{ $assessment['average_temperature_c'] !== null ? number_format($assessment['average_temperature_c'], 1).' °C' : 'Not Available' }}</strong></div>
                                <div><span>POA recorded</span><strong data-nx20-summary-poa>{{ $assessment['poa_months'] }}/12 months</strong></div>
                            </aside>
                        </div>
                    </section>

                    <section class="nx20-card nx20-monthly-card">
                        <header class="nx20-card-head"><div><span class="nx20-section-kicker">Monthly Registry</span><h3>Stored Monthly Resource Values</h3><p>GHI, Peak Sun Hours, and ambient temperature are required for the current dataset-completeness gate. POA is optional.</p></div><span class="nx20-chip" data-nx20-monthly-chip>{{ $assessment['complete_months'] }}/12 complete</span></header>
                        <div class="nx20-table-wrap">
                            <table class="nx20-monthly-table">
                                <thead><tr><th>Month</th><th>GHI Daily <small>kWh/m²/day</small></th><th>POA Daily <small>kWh/m²/day</small></th><th>Peak Sun Hours <small>h/day</small></th><th>Ambient Temp. <small>°C</small></th></tr></thead>
                                <tbody>
                                @foreach($monthlyRows as $row)
                                    @php $m = (int) $row->month; $complete = $row->isComplete(); @endphp
                                    <tr data-nx20-month-row="{{ $m }}" data-days="{{ $row->days_in_month }}">
                                        <th>
                                            <span class="nx20-month-heading">
                                                <span>{{ $monthAbbreviations[$m] }}</span>
                                                <i
                                                    class="nx20-month-state {{ $complete ? 'is-complete' : 'is-missing' }}"
                                                    data-nx20-month-state
                                                    role="img"
                                                    aria-label="Month state: {{ $complete ? 'Complete' : 'Input Required' }}"
                                                    title="{{ $complete ? 'Complete' : 'Input Required' }}"
                                                >{{ $complete ? 'Complete' : 'Input Required' }}</i>
                                            </span>
                                            <small>{{ $row->month_name }}</small>
                                        </th>
                                        <td><input type="number" step="0.001" name="monthly[{{ $m }}][ghi_daily_kwh_m2]" value="{{ old("monthly.$m.ghi_daily_kwh_m2", $row->ghi_daily_kwh_m2) }}" data-field="ghi" @disabled(! $canManage || ! $hasDataset)></td>
                                        <td><input type="number" step="0.001" name="monthly[{{ $m }}][poa_daily_kwh_m2]" value="{{ old("monthly.$m.poa_daily_kwh_m2", $row->poa_daily_kwh_m2) }}" data-field="poa" @disabled(! $canManage || ! $hasDataset)></td>
                                        <td><input type="number" step="0.01" name="monthly[{{ $m }}][peak_sun_hours]" value="{{ old("monthly.$m.peak_sun_hours", $row->peak_sun_hours) }}" data-field="psh" @disabled(! $canManage || ! $hasDataset)></td>
                                        <td><input type="number" step="0.01" name="monthly[{{ $m }}][ambient_temperature_c]" value="{{ old("monthly.$m.ambient_temperature_c", $row->ambient_temperature_c) }}" data-field="temperature" @disabled(! $canManage || ! $hasDataset)></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="nx20-card nx20-history-card" id="nx20-history">
                        <header class="nx20-card-head"><div><span class="nx20-section-kicker">Audit Trail</span><h3>Dataset History</h3><p>{{ $historyTotal > 24 ? 'Showing the latest 24 of '.number_format($historyTotal).' recorded events.' : 'Recorded dataset start, update, and review events.' }}</p></div></header>
                        @if ($historyRows->isEmpty())
                            <div class="nx20-empty-row">No dataset history has been recorded.</div>
                        @else
                            <div class="nx20-history-list">
                                @foreach($historyRows as $history)
                                    <article>
                                        <span class="nx20-history-dot is-{{ $historyTone($history->event_type) }}"></span>
                                        <div><strong>{{ $historyLabels[$history->event_type] ?? 'Dataset event' }}</strong><p>{{ $history->summary ?: 'Dataset record updated.' }}</p><small>{{ $history->changedBy?->name ?: 'User not recorded' }} · {{ $history->changed_at?->format('d M Y · H:i') }}</small></div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </main>

                <aside class="nx20-rail">
                    <section class="nx20-rail-card nx20-rail-basis">
                        <span class="nx20-section-kicker">Dataset Basis</span><h3>Monthly recorded resource basis</h3>
                        <p>The current model stores monthly daily GHI, optional POA, Peak Sun Hours, ambient temperature, project coordinates, and source metadata.</p>
                        <dl><div><dt>Source</dt><dd data-nx20-rail-source>{{ $assessment['source_type_label'] }}</dd></div><div><dt>Updated</dt><dd>{{ $profile->updated_at?->format('d M Y · H:i') ?? 'Not Recorded' }}</dd></div><div><dt>Updated by</dt><dd>{{ $profile->updater?->name ?? 'Not Recorded' }}</dd></div></dl>
                    </section>

                    <section class="nx20-rail-card nx20-rail-gaps">
                        <span class="nx20-section-kicker">Missing Inputs</span><h3>Current data gaps</h3>
                        <div class="nx20-gap-list" data-nx20-gap-list>
                            @forelse($assessment['missing_inputs'] as $gap)<span><i></i>{{ $gap }}</span>@empty<span class="is-clear"><i></i>No required current inputs are missing.</span>@endforelse
                        </div>
                    </section>

                    <section class="nx20-rail-card nx20-rail-review">
                        <span class="nx20-section-kicker">Review Scope</span><h3>{{ $isReviewed ? 'Recorded dataset reviewed' : 'Current completeness review' }}</h3>
                        <p>Dataset review confirms the current recorded coordinate pair, source name, and 12 required monthly values. It is not an independent solar-resource or bankability assessment.</p>
                        @if ($isReviewed)
                            <dl><div><dt>Reviewed by</dt><dd>{{ $profile->verifier?->name ?? 'Not Recorded' }}</dd></div><div><dt>Review date</dt><dd>{{ $profile->verified_at?->format('d M Y · H:i') ?? 'Not Recorded' }}</dd></div></dl>
                        @endif
                    </section>

                    <section class="nx20-rail-card nx20-next-action">
                        <span class="nx20-section-kicker">Next Engineering Action</span>
                        @if (! $hasDataset)
                            <h3 data-nx20-next-title>Start the solar dataset</h3><p data-nx20-next-copy>Create the persisted dataset before monthly resource values can be recorded.</p>
                        @elseif (! $isComplete)
                            <h3 data-nx20-next-title>Complete the recorded inputs</h3><p data-nx20-next-copy>{{ $assessment['missing_inputs'][0] ?? 'Complete the current dataset requirements.' }}</p>
                        @elseif (! $isReviewed)
                            <h3 data-nx20-next-title>Complete dataset review</h3><p data-nx20-next-copy>The current saved dataset is complete enough for the authorized review workflow.</p>
                        @else
                            <h3 data-nx20-next-title>Continue downstream engineering</h3><p data-nx20-next-copy>The current monthly dataset review is recorded. Downstream calculations still use their own freshness and approval gates.</p>
                        @endif
                    </section>

                    <section class="nx20-rail-card nx20-model-boundary">
                        <span class="nx20-section-kicker">Current Resource Basis</span><h3>Feasibility-scale monthly dataset</h3>
                        <p>Advanced time-series resource modeling belongs to the dedicated solar/weather and energy-simulation authority, not this monthly project record.</p>
                    </section>
                </aside>
            </div>
        </form>
    </div>
@endsection
