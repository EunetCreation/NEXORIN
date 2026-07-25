@extends('layouts.app')

@section('title', 'Solar Resource · '.$project->name)
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Solar Resource')

@push('styles')
    <link href="{{ asset('css/nexorin-location-solar.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-location-solar.js') }}?v=55a" defer></script>
@endpush

@php
    $isVerified = $profile->quality_status === 'verified';
    $hasMonthlyData = $summary['complete_months'] > 0;
    $statusLabel = match ($profile->quality_status) {
        'verified' => 'Verified dataset',
        'estimated' => 'Ready to verify',
        'imported' => 'Imported · ready',
        default => $summary['complete_months'] > 0 ? 'Dataset in progress' : 'Dataset setup required',
    };
    $sourceSelection = old('data_source', $profile->data_source ?: 'manual');
    $monthAbbreviations = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
    $sourceLabels = [
        'manual' => ['title' => 'Manual engineering input', 'copy' => 'Enter monthly resource values from a controlled worksheet or survey source.'],
        'reference' => ['title' => 'Reference dataset', 'copy' => 'Document a trusted published dataset and normalize its monthly values.'],
        'api' => ['title' => 'API-derived dataset', 'copy' => 'Identify values imported from a supported external solar-resource service.'],
    ];
@endphp

@section('content')
    @if ($canVerify && ! $isVerified)
        <form id="solar-verify-form" method="POST" action="{{ route('companies.location-solar.verify', [$company, $project]) }}" hidden>
            @csrf
            @method('PATCH')
        </form>
    @endif

    <div
        class="solar-resource-workspace"
        data-solar-workspace
        data-initial-status="{{ $profile->quality_status }}"
        data-can-manage="{{ $canManage ? 'true' : 'false' }}"
        data-server-errors="{{ $errors->any() ? 'true' : 'false' }}"
    >
        <section class="solar-workspace-hero" aria-labelledby="solar-workspace-title">
            <div class="solar-workspace-hero-grid" aria-hidden="true"></div>
            <span class="solar-workspace-orb solar-workspace-orb-one" aria-hidden="true"></span>
            <span class="solar-workspace-orb solar-workspace-orb-two" aria-hidden="true"></span>

            <div class="solar-workspace-hero-copy">
                <a href="{{ route('companies.location-solar.index', $company) }}" class="solar-workspace-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
                    Solar resource directory
                </a>
                <div class="solar-workspace-kicker"><span aria-hidden="true"></span>{{ $project->project_number }} · {{ $project->customer->name }}</div>
                <h2 id="solar-workspace-title">{{ $project->name }}</h2>
                <p>Build a traceable solar-resource foundation for site assessment, recommendation, yield analysis, and downstream engineering decisions.</p>

                <div class="solar-workspace-hero-meta">
                    <span>
                        <small>Installation site</small>
                        <strong data-solar-summary="site">{{ $project->city ?: 'Location not set' }}{{ $project->province ? ', '.$project->province : '' }}</strong>
                    </span>
                    <span>
                        <small>Resource source</small>
                        <strong data-solar-summary="source">{{ $profile->data_source_label }}</strong>
                    </span>
                    <span>
                        <small>Current state</small>
                        <strong data-solar-summary="status">{{ $statusLabel }}</strong>
                    </span>
                </div>
            </div>

            <aside class="solar-workspace-hero-command">
                <div class="solar-workspace-hero-command-head">
                    <span class="solar-workspace-command-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <circle cx="12" cy="12" r="3.3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M18.7 5.3l-2.1 2.1M7.4 16.6l-2.1 2.1"/>
                        </svg>
                    </span>
                    <span class="solar-workspace-state is-{{ $profile->quality_status }}" data-solar-hero-state>{{ $statusLabel }}</span>
                </div>

                <div class="solar-workspace-readiness-ring" data-solar-readiness-ring style="--solar-readiness: {{ min(100, $summary['readiness_percent']) }}">
                    <span><strong data-solar-readiness-value>{{ number_format($summary['readiness_percent'], 0) }}%</strong><small>ready</small></span>
                </div>

                <div class="solar-workspace-command-copy">
                    <strong data-solar-command-title>{{ $summary['is_ready_for_verification'] ? 'Engineering verification available' : 'Complete the resource foundation' }}</strong>
                    <p data-solar-command-copy>{{ $summary['is_ready_for_verification'] ? 'Coordinates, source identity, and all required monthly values are available.' : 'Readiness updates automatically as location, source, and monthly values are completed.' }}</p>
                </div>

                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="solar-button solar-button-ghost solar-button-full">
                    View project cockpit
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </aside>
        </section>

        <section class="solar-workspace-kpis" aria-label="Live solar resource summary">
            <article>
                <span class="solar-kpi-icon is-teal" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 16.5h14"/>
                        <path d="M7.2 16.4a4.8 4.8 0 0 1 9.6 0"/>
                        <path d="M12 6.2v2.1M8.4 7.8l1.2 1.2M15.6 7.8l-1.2 1.2" opacity=".46"/>
                        <path d="M6.3 18.8h.01M12 18.8h.01M17.7 18.8h.01"/>
                    </svg>
                </span>
                <div><small>Annual average PSH</small><strong data-solar-kpi="psh">{{ number_format($summary['average_psh'], 2) }}</strong><span>hours per day</span></div>
            </article>
            <article>
                <span class="solar-kpi-icon is-blue" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4.5 17.5h15"/>
                        <circle cx="12" cy="7.4" r="2.3"/>
                        <path d="M12 3v1.4M8.7 4.2l.9.9M15.3 4.2l-.9.9" opacity=".46"/>
                        <path d="M8.3 10.6 7 13.4M12 10.2v3.7M15.7 10.6 17 13.4"/>
                    </svg>
                </span>
                <div><small>Annual irradiation</small><strong data-solar-kpi="ghi">{{ number_format($summary['annual_irradiation_kwh_m2'], 0) }}</strong><span>kWh/m² per year</span></div>
            </article>
            <article>
                <span class="solar-kpi-icon is-amber" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 5.2a2 2 0 1 1 4 0V13a3.5 3.5 0 1 1-4 0V5.2Z"/>
                        <path d="M12 9.2v6.6"/>
                        <path d="M17.5 7.2v.01M18.6 10.1v.01M16.3 9.4v.01" opacity=".46"/>
                    </svg>
                </span>
                <div><small>Average temperature</small><strong data-solar-kpi="temperature">{{ number_format($summary['average_temperature_c'], 1) }}</strong><span>ambient annual mean</span></div>
            </article>
            <article>
                <span class="solar-kpi-icon is-violet" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="5.5" width="14" height="13.5" rx="3"/>
                        <path d="M8.5 4v3M15.5 4v3M5 9.5h14" opacity=".46"/>
                        <path d="M8.4 12.5h.01M12 12.5h.01M15.6 12.5h.01M8.4 15.5h.01M12 15.5h.01M15.6 15.5h.01"/>
                    </svg>
                    <span class="solar-kpi-icon-badge" data-solar-kpi-months>{{ $summary['complete_months'] }}/12</span>
                </span>
                <div><small>Monthly coverage</small><strong data-solar-kpi="coverage">{{ number_format($summary['readiness_percent'], 0) }}%</strong><span data-solar-potential>{{ $summary['potential_label'] }} solar potential</span></div>
            </article>
        </section>

        @if ($errors->any())
            <div class="solar-validation-alert" role="alert">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg>
                </span>
                <div>
                    <strong>Review the highlighted solar-resource inputs</strong>
                    <p>{{ $errors->first() }}</p>
                </div>
            </div>
        @endif

        <nav class="solar-workspace-nav" aria-label="Solar resource sections">
            <a href="#solar-foundation" class="is-active" data-solar-section-link="solar-foundation"><span>01</span>Foundation</a>
            <a href="#solar-profile" data-solar-section-link="solar-profile"><span>02</span>Resource profile</a>
            <a href="#solar-monthly" data-solar-section-link="solar-monthly"><span>03</span>Monthly registry</a>
            <a href="#solar-audit" data-solar-section-link="solar-audit"><span>04</span>Audit trail</a>
            <div class="solar-workspace-nav-state"><i aria-hidden="true"></i><span data-solar-nav-state>{{ $isVerified ? 'Verified' : ($summary['is_ready_for_verification'] ? 'Ready to verify' : 'In progress') }}</span></div>
        </nav>

        @if (! $hasMonthlyData && $canManage)
            <section class="solar-onboarding" data-solar-onboarding>
                <div class="solar-onboarding-copy">
                    <span class="solar-onboarding-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3v4M12 17v4M4.2 7.5l3.4 2M16.4 14.5l3.4 2M4.2 16.5l3.4-2M16.4 9.5l3.4-2"/><circle cx="12" cy="12" r="4"/></svg>
                    </span>
                    <div>
                        <div class="solar-section-kicker">Guided setup</div>
                        <h3>Choose how this project’s solar dataset will be documented</h3>
                        <p>The selection sets source traceability. Monthly engineering values remain fully reviewable before verification.</p>
                    </div>
                </div>
                <div class="solar-onboarding-actions">
                    @foreach ($sourceLabels as $value => $source)
                        <button type="button" data-solar-source-launch="{{ $value }}">
                            <strong>{{ $source['title'] }}</strong>
                            <span>{{ $source['copy'] }}</span>
                            <small>Start setup <b>→</b></small>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        <form method="POST" action="{{ route('companies.location-solar.update', [$company, $project]) }}" data-solar-form>
            @csrf
            @method('PUT')

            <div class="solar-workspace-layout">
                <div class="solar-workspace-main">
                    <section class="solar-section" id="solar-foundation" data-solar-section>
                        <header class="solar-section-head">
                            <span class="solar-section-number">01</span>
                            <span class="solar-section-icon is-teal">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                            </span>
                            <div>
                                <div class="solar-section-kicker">Site and source foundation</div>
                                <h3>Anchor the dataset to a traceable installation context</h3>
                                <p>Coordinate accuracy and source identity determine whether the monthly resource profile can enter engineering verification.</p>
                            </div>
                            <span class="solar-section-state" data-solar-section-state="foundation">{{ $project->latitude !== null && $project->longitude !== null && filled($profile->source_name) ? 'Ready' : 'Needs input' }}</span>
                        </header>

                        <div class="solar-section-body solar-foundation-grid">
                            <div class="solar-foundation-panel">
                                <div class="solar-panel-title">
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 20V9l8-5 8 5v11"/><path d="M8 20v-6h8v6"/></svg>
                                    </span>
                                    <div><strong>Installation site</strong><small>Address and geographic coordinates</small></div>
                                </div>

                                <div class="solar-field-grid">
                                    <div class="solar-field full">
                                        <label for="site_address">Project site address <b>*</b></label>
                                        <textarea id="site_address" name="site_address" rows="2" maxlength="255" data-solar-field data-solar-autosize @disabled(! $canManage) required>{{ old('site_address', $project->site_address) }}</textarea>
                                        @error('site_address')<span class="solar-field-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="solar-field">
                                        <label for="city">City or regency <b>*</b></label>
                                        <input id="city" name="city" value="{{ old('city', $project->city) }}" data-solar-field @disabled(! $canManage) required>
                                        @error('city')<span class="solar-field-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="solar-field">
                                        <label for="province">Province <b>*</b></label>
                                        <input id="province" name="province" value="{{ old('province', $project->province) }}" data-solar-field @disabled(! $canManage) required>
                                        @error('province')<span class="solar-field-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="solar-field">
                                        <label for="postal_code">Postal code</label>
                                        <input id="postal_code" name="postal_code" value="{{ old('postal_code', $project->postal_code) }}" data-solar-field @disabled(! $canManage)>
                                    </div>
                                    <div class="solar-location-status {{ $project->latitude !== null && $project->longitude !== null ? '' : 'is-missing' }}" data-solar-location-status>
                                        <span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg>
                                        </span>
                                        <div><strong data-solar-location-label>{{ $project->latitude !== null && $project->longitude !== null ? 'Coordinates ready' : 'Coordinates required' }}</strong><small data-solar-location-copy>{{ $project->latitude !== null && $project->longitude !== null ? 'Available for solar and map workflows.' : 'Add coordinates before verification.' }}</small></div>
                                    </div>
                                </div>

                                <div class="solar-coordinate-panel">
                                    <div class="solar-coordinate-head">
                                        <div><strong>Geographic coordinates</strong><small>Optional during setup, required for verification</small></div>
                                        @if ($canManage)
                                            <button type="button" data-solar-use-location>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>
                                                <span data-solar-location-button-label>Use current location</span>
                                            </button>
                                        @endif
                                    </div>
                                    <div class="solar-field-grid">
                                        <div class="solar-field">
                                            <label for="latitude">Latitude</label>
                                            <input id="latitude" type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $project->latitude) }}" placeholder="0.5070670" data-solar-field @disabled(! $canManage)>
                                            @error('latitude')<span class="solar-field-error">{{ $message }}</span>@enderror
                                        </div>
                                        <div class="solar-field">
                                            <label for="longitude">Longitude</label>
                                            <input id="longitude" type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $project->longitude) }}" placeholder="101.4477770" data-solar-field @disabled(! $canManage)>
                                            @error('longitude')<span class="solar-field-error">{{ $message }}</span>@enderror
                                        </div>
                                        <div class="solar-field">
                                            <label for="elevation_m">Elevation</label>
                                            <div class="solar-input-suffix"><input id="elevation_m" type="number" step="0.01" name="elevation_m" value="{{ old('elevation_m', $profile->elevation_m) }}" data-solar-field @disabled(! $canManage)><span>m</span></div>
                                        </div>
                                        <div class="solar-field">
                                            <label for="timezone">Timezone <b>*</b></label>
                                            <select id="timezone" name="timezone" data-solar-field @disabled(! $canManage) required>
                                                @foreach (['Asia/Jakarta' => 'WIB · Asia/Jakarta', 'Asia/Makassar' => 'WITA · Asia/Makassar', 'Asia/Jayapura' => 'WIT · Asia/Jayapura'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('timezone', $profile->timezone) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="solar-foundation-panel solar-source-panel" data-solar-source-panel>
                                <div class="solar-panel-title">
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                                    </span>
                                    <div><strong>Source traceability</strong><small>Dataset origin and engineering assumptions</small></div>
                                </div>

                                <fieldset class="solar-source-options">
                                    <legend>Data source strategy <b>*</b></legend>
                                    @foreach ($sourceLabels as $value => $source)
                                        <label class="{{ $sourceSelection === $value ? 'is-selected' : '' }}">
                                            <input type="radio" name="data_source" value="{{ $value }}" @checked($sourceSelection === $value) data-solar-source-option data-solar-field @disabled(! $canManage) required>
                                            <span class="solar-source-option-icon is-{{ $value }}">
                                                @if ($value === 'manual')
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></svg>
                                                @elseif ($value === 'reference')
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/></svg>
                                                @else
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 12h8M12 8v8"/><rect x="3" y="5" width="18" height="14" rx="4"/></svg>
                                                @endif
                                            </span>
                                            <span><strong>{{ $source['title'] }}</strong><small>{{ $source['copy'] }}</small></span>
                                            <i aria-hidden="true"></i>
                                        </label>
                                    @endforeach
                                </fieldset>

                                <div class="solar-field-grid one-column">
                                    <div class="solar-field">
                                        <label for="source_name">Source name <b>*</b></label>
                                        <input id="source_name" name="source_name" value="{{ old('source_name', $profile->source_name) }}" placeholder="NASA POWER, PVGIS, BMKG, or controlled worksheet" data-solar-field @disabled(! $canManage)>
                                    </div>
                                    <div class="solar-field">
                                        <label for="source_reference">Reference or dataset identifier</label>
                                        <input id="source_reference" name="source_reference" value="{{ old('source_reference', $profile->source_reference) }}" placeholder="Dataset URL, version, station, or internal reference" data-solar-field @disabled(! $canManage)>
                                    </div>
                                    <div class="solar-field">
                                        <label for="source_date">Source date</label>
                                        <input id="source_date" type="date" name="source_date" value="{{ old('source_date', $profile->source_date?->format('Y-m-d')) }}" data-solar-field @disabled(! $canManage)>
                                    </div>
                                    <div class="solar-field">
                                        <label for="notes">Engineering assumptions</label>
                                        <textarea id="notes" name="notes" rows="4" maxlength="3000" placeholder="Record quality limitations, corrections, normalization, or assumptions." data-solar-field data-solar-autosize @disabled(! $canManage)>{{ old('notes', $profile->notes) }}</textarea>
                                        <small class="solar-character-count"><span data-solar-character-count="notes">{{ strlen((string) old('notes', $profile->notes)) }}</span>/3000</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="solar-section" id="solar-profile" data-solar-section>
                        <header class="solar-section-head">
                            <span class="solar-section-number">02</span>
                            <span class="solar-section-icon is-blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 18h16M6 15l3-4 3 2 5-7 2 2"/></svg>
                            </span>
                            <div>
                                <div class="solar-section-kicker">Solar profile intelligence</div>
                                <h3>Read the seasonal resource pattern before reviewing raw values</h3>
                                <p>The visual profile updates instantly as monthly GHI, PSH, and temperature inputs change.</p>
                            </div>
                            <span class="solar-section-state {{ $summary['complete_months'] === 12 ? '' : 'is-review' }}" data-solar-section-state="profile">{{ $summary['complete_months'] === 12 ? '12 months ready' : $summary['complete_months'].' of 12 months' }}</span>
                        </header>

                        <div class="solar-section-body">
                            <div class="solar-profile-toolbar">
                                <div class="solar-chart-tabs" role="group" aria-label="Select solar chart metric">
                                    <button type="button" class="is-active" data-solar-chart-metric="ghi">GHI</button>
                                    <button type="button" data-solar-chart-metric="psh">Peak sun hours</button>
                                    <button type="button" data-solar-chart-metric="temperature">Temperature</button>
                                </div>
                                <div class="solar-chart-live"><i aria-hidden="true"></i>Live from monthly registry</div>
                            </div>

                            <div class="solar-profile-chart" data-solar-chart data-chart-metric="ghi">
                                <div class="solar-chart-head">
                                    <div>
                                        <span data-solar-chart-eyebrow>Global horizontal irradiation</span>
                                        <strong data-solar-chart-title>Monthly GHI profile</strong>
                                    </div>
                                    <div class="solar-chart-unit" data-solar-chart-unit>kWh/m²/day</div>
                                </div>
                                <div class="solar-chart-stage">
                                    <div
                                        class="solar-chart-canvas"
                                        data-solar-chart-canvas
                                        role="img"
                                        aria-label="Monthly global horizontal irradiation profile. The chart updates from the twelve-month solar resource registry."
                                    ></div>
                                    <div class="solar-chart-fallback" data-solar-chart-fallback hidden>
                                        Apache ECharts is unavailable. Monthly values remain available in the controlled registry below.
                                    </div>
                                </div>
                                <div class="solar-chart-summary">
                                    <div><span>Annual average</span><strong data-solar-chart-average>{{ number_format($summary['average_psh'], 2) }}</strong><small data-solar-chart-average-unit>PSH/day</small></div>
                                    <div><span>Strongest month</span><strong data-solar-chart-peak>Not available</strong><small data-solar-chart-peak-value>Complete monthly values</small></div>
                                    <div><span>Lowest month</span><strong data-solar-chart-low>Not available</strong><small data-solar-chart-low-value>Complete monthly values</small></div>
                                    <div><span>Seasonal spread</span><strong data-solar-chart-spread>0%</strong><small>highest versus lowest</small></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="solar-section" id="solar-monthly" data-solar-section>
                        <header class="solar-section-head">
                            <span class="solar-section-number">03</span>
                            <span class="solar-section-icon is-violet">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>
                            </span>
                            <div>
                                <div class="solar-section-kicker">Monthly resource registry</div>
                                <h3>Maintain twelve traceable monthly engineering records</h3>
                                <p>GHI, PSH, and ambient temperature are required for verification. Plane-of-array irradiation remains optional.</p>
                            </div>
                            <span class="solar-section-state {{ $summary['complete_months'] === 12 ? '' : 'is-review' }}" data-solar-monthly-state>{{ $summary['complete_months'] }}/12 complete</span>
                        </header>

                        <div class="solar-monthly-guide">
                            <span><i class="is-complete"></i>Required month complete</span>
                            <span><i class="is-partial"></i>Partial values</span>
                            <span><i></i>No required values</span>
                            <small>POA values can be added when array-plane data is available.</small>
                        </div>

                        <div class="solar-monthly-table-wrap">
                            <table class="solar-monthly-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>GHI daily <span>kWh/m²/day</span></th>
                                        <th>POA daily <span>kWh/m²/day</span></th>
                                        <th>Peak sun hours <span>h/day</span></th>
                                        <th>Ambient temp. <span>°C</span></th>
                                        <th>Record state</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($monthlyRows as $row)
                                        @php
                                            $ghi = old('monthly.'.$row->month.'.ghi_daily_kwh_m2', $row->ghi_daily_kwh_m2);
                                            $poa = old('monthly.'.$row->month.'.poa_daily_kwh_m2', $row->poa_daily_kwh_m2);
                                            $psh = old('monthly.'.$row->month.'.peak_sun_hours', $row->peak_sun_hours);
                                            $temperature = old('monthly.'.$row->month.'.ambient_temperature_c', $row->ambient_temperature_c);
                                            $requiredCount = collect([$ghi, $psh, $temperature])->filter(fn ($value) => $value !== null && $value !== '')->count();
                                            $rowState = $requiredCount === 3 ? 'complete' : ($requiredCount > 0 ? 'partial' : 'empty');
                                        @endphp
                                        <tr data-solar-month-row="{{ $row->month }}" class="is-{{ $rowState }}">
                                            <td>
                                                <span class="solar-month-index">{{ str_pad((string) $row->month, 2, '0', STR_PAD_LEFT) }}</span>
                                                <div><strong>{{ $row->month_name }}</strong><small>{{ $row->days_in_month }} modeled days</small></div>
                                            </td>
                                            <td><input aria-label="{{ $row->month_name }} daily GHI" type="number" step="0.001" min="0" max="20" name="monthly[{{ $row->month }}][ghi_daily_kwh_m2]" value="{{ $ghi }}" data-solar-month-input="ghi" data-solar-field @disabled(! $canManage)></td>
                                            <td><input aria-label="{{ $row->month_name }} daily POA" type="number" step="0.001" min="0" max="20" name="monthly[{{ $row->month }}][poa_daily_kwh_m2]" value="{{ $poa }}" data-solar-month-input="poa" data-solar-field @disabled(! $canManage)></td>
                                            <td><input aria-label="{{ $row->month_name }} peak sun hours" type="number" step="0.01" min="0" max="24" name="monthly[{{ $row->month }}][peak_sun_hours]" value="{{ $psh }}" data-solar-month-input="psh" data-solar-field @disabled(! $canManage)></td>
                                            <td><input aria-label="{{ $row->month_name }} ambient temperature" type="number" step="0.01" min="-60" max="80" name="monthly[{{ $row->month }}][ambient_temperature_c]" value="{{ $temperature }}" data-solar-month-input="temperature" data-solar-field @disabled(! $canManage)></td>
                                            <td><span class="solar-row-state is-{{ $rowState }}" data-solar-row-state><i></i><b>{{ ucfirst($rowState) }}</b></span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="solar-section" id="solar-audit" data-solar-section>
                        <header class="solar-section-head">
                            <span class="solar-section-number">04</span>
                            <span class="solar-section-icon is-neutral">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                            </span>
                            <div>
                                <div class="solar-section-kicker">Traceability and audit</div>
                                <h3>Review the dataset’s controlled change history</h3>
                                <p>Source, status, update, and verification events remain visible for engineering review.</p>
                            </div>
                            <span class="solar-section-state is-optional">{{ $profile->histories->count() }} event{{ $profile->histories->count() === 1 ? '' : 's' }}</span>
                        </header>

                        <div class="solar-section-body">
                            @if ($profile->histories->isEmpty())
                                <div class="solar-history-empty">
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.3-5.7L4 8.5"/><path d="M4 4v4.5h4.5M12 7v5l3 2"/></svg>
                                    </span>
                                    <div><strong>No audit event has been recorded yet</strong><p>The first saved update will create a traceable location and solar-resource history entry.</p></div>
                                </div>
                            @else
                                <div class="solar-history-timeline">
                                    @foreach ($profile->histories->take(8) as $history)
                                        <article>
                                            <span class="solar-history-marker is-{{ $history->event_type }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    @if ($history->event_type === 'verified')
                                                        <path d="m7 12 3 3 7-7"/><circle cx="12" cy="12" r="9"/>
                                                    @else
                                                        <path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/>
                                                    @endif
                                                </svg>
                                            </span>
                                            <div>
                                                <header><strong>{{ ucfirst($history->event_type) }}</strong><time>{{ $history->changed_at?->format('d M Y · H:i') }}</time></header>
                                                <p>{{ $history->summary }}</p>
                                                <footer>
                                                    <span>{{ $history->changedBy?->name ?? 'System' }}</span>
                                                    @if ($history->previous_status !== $history->new_status)
                                                        <span>{{ $history->previous_status ?? 'none' }} → {{ $history->new_status ?? 'none' }}</span>
                                                    @endif
                                                </footer>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                </div>

                <aside class="solar-workspace-rail">
                    <section class="solar-rail-card solar-rail-control">
                        <header>
                            <div>
                                <div class="solar-section-kicker">Engineering gate</div>
                                <h3>Live dataset state</h3>
                            </div>
                            <div class="solar-rail-ring" data-solar-readiness-ring style="--solar-readiness: {{ min(100, $summary['readiness_percent']) }}"><span data-solar-readiness-value>{{ number_format($summary['readiness_percent'], 0) }}%</span></div>
                        </header>

                        <div class="solar-rail-change-state">
                            <span><i aria-hidden="true"></i><b data-solar-change-label>{{ $errors->any() ? 'Review required' : 'Saved state' }}</b></span>
                            <strong data-solar-change-count>0 changes</strong>
                        </div>

                        <div class="solar-rail-progress"><span data-solar-progress-bar style="width: {{ min(100, $summary['readiness_percent']) }}%"></span></div>

                        <div class="solar-verification-checklist">
                            <div data-solar-check="coordinates" class="{{ $project->latitude !== null && $project->longitude !== null ? 'is-complete' : '' }}"><i></i><span><strong>Geographic coordinates</strong><small>Latitude and longitude</small></span></div>
                            <div data-solar-check="source" class="{{ filled($profile->source_name) ? 'is-complete' : '' }}"><i></i><span><strong>Source identity</strong><small>Traceable source name</small></span></div>
                            <div data-solar-check="months" class="{{ $summary['complete_months'] === 12 ? 'is-complete' : '' }}"><i></i><span><strong>Monthly coverage</strong><small data-solar-month-check-copy>{{ $summary['complete_months'] }}/12 required records</small></span></div>
                        </div>

                        <div class="solar-rail-facts">
                            <div><span>Potential</span><strong data-solar-rail-potential>{{ $summary['potential_label'] }}</strong></div>
                            <div><span>Source</span><strong data-solar-rail-source>{{ $profile->data_source_label }}</strong></div>
                            <div><span>Verification</span><strong data-solar-rail-verification>{{ $isVerified ? 'Verified' : 'Pending' }}</strong></div>
                            <div><span>Verified by</span><strong>{{ $profile->verifier?->name ?? 'Not verified' }}</strong></div>
                        </div>

                        @if (! $canManage)
                            <div class="solar-rail-note">You have read-only access to this dataset.</div>
                        @else
                            <button type="submit" class="solar-save-button" data-solar-submit disabled>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
                                <span data-solar-submit-label>Saved</span>
                            </button>
                            <span class="solar-save-hint"><kbd>Ctrl</kbd><b>+</b><kbd>S</kbd> quick save</span>
                        @endif
                    </section>

                    <section class="solar-rail-card solar-rail-verification">
                        <span class="solar-rail-verification-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3l7 3v5c0 4.6-2.8 8-7 10-4.2-2-7-5.4-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                        </span>
                        <div>
                            <div class="solar-section-kicker">Verification control</div>
                            <h3 data-solar-verification-title>{{ $isVerified ? 'Engineering dataset verified' : ($summary['is_ready_for_verification'] ? 'Ready for engineering review' : 'Verification requirements pending') }}</h3>
                            <p data-solar-verification-copy>
                                @if ($isVerified)
                                    Verified by {{ $profile->verifier?->name ?? 'an authorized user' }} on {{ $profile->verified_at?->format('d M Y, H:i') }}.
                                @elseif ($summary['is_ready_for_verification'])
                                    The dataset is complete. Save any pending changes before applying verification.
                                @else
                                    Complete coordinates, source name, and all 12 required monthly records.
                                @endif
                            </p>
                        </div>

                        @if ($canVerify && ! $isVerified)
                            <button type="submit" form="solar-verify-form" class="solar-verify-button" data-solar-verify @disabled(! $summary['is_ready_for_verification'])>
                                Verify dataset
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        @endif
                    </section>

                    <section class="solar-rail-card solar-rail-boundary">
                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg>
                        </span>
                        <div><strong>Controlled engineering boundary</strong><p>Verification confirms dataset completeness and traceability. It does not replace structural review or final energy-yield validation.</p></div>
                    </section>
                </aside>
            </div>

            @if ($canManage)
                <div class="solar-mobile-save" data-solar-mobile-save>
                    <div><span data-solar-mobile-state>Saved state</span><strong data-solar-mobile-count>0 changes</strong></div>
                    <button type="submit" data-solar-submit disabled><span data-solar-submit-label>Saved</span></button>
                </div>
            @endif
        </form>
    </div>
@endsection
