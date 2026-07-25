@extends('layouts.app')

@section('title', 'Roof & Site Survey · '.$project->name)
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Roof & Site Survey')

@push('styles')
    <link href="{{ asset('css/nexorin-roof-site.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-roof-site.js') }}?v=55a" defer></script>
@endpush

@php
    $quality = $survey->quality_status ?? 'incomplete';
    $isVerified = $quality === 'verified';
    $statusLabel = $survey->quality_status_label;
    $surfaceCount = (int) $summary['surface_count'];
    $completeSurfaceCount = (int) $summary['complete_surface_count'];
    $photoCount = $survey->photos->count();
    $historyCount = $survey->histories->count();
    $hasSurfaceData = $surfaceCount > 0;
    $foundationReady = $survey->survey_date !== null && filled($survey->access_method) && $survey->structural_review_status !== 'not_assessed';
    $siteLabel = trim(collect([$project->city, $project->province])->filter()->join(', '));
    $verifyAvailable = $canVerify && $summary['is_ready_for_verification'] && ! $isVerified;
    $conditionClass = match (strtolower((string) $summary['overall_condition'])) {
        'good' => 'is-good',
        'fair' => 'is-fair',
        'poor' => 'is-poor',
        default => 'is-neutral',
    };
@endphp

@section('content')
    @if ($canVerify && ! $isVerified)
        <form id="rss-verify-form" method="POST" action="{{ route('companies.roof-site.verify', [$company, $project]) }}" hidden data-rss-action-form data-rss-action="verify">
            @csrf
            @method('PATCH')
        </form>
    @endif

    <div
        class="rss-workspace"
        data-rss-workspace
        data-initial-status="{{ $quality }}"
        data-can-manage="{{ $canManage ? 'true' : 'false' }}"
        data-server-errors="{{ $errors->any() ? 'true' : 'false' }}"
        data-surface-count="{{ $surfaceCount }}"
        data-complete-surface-count="{{ $completeSurfaceCount }}"
    >
        <section class="rss-workspace-hero" aria-labelledby="rss-workspace-title">
            <div class="rss-hero-grid" aria-hidden="true"></div>
            <span class="rss-hero-orb rss-hero-orb-one" aria-hidden="true"></span>
            <span class="rss-hero-orb rss-hero-orb-two" aria-hidden="true"></span>

            <div class="rss-workspace-hero-copy">
                <a href="{{ route('companies.roof-site.index', $company) }}" class="rss-back-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
                    Roof survey directory
                </a>
                <div class="rss-kicker"><span aria-hidden="true"></span>{{ $project->project_number }} · {{ $project->customer->name }}</div>
                <h2 id="rss-workspace-title">{{ $project->name }}</h2>
                <p>Build a traceable roof and site foundation for layout planning, structural review, installation safety, electrical routing, and downstream engineering decisions.</p>

                <div class="rss-workspace-hero-meta">
                    <span><small>Installation site</small><strong>{{ $siteLabel !== '' ? $siteLabel : 'Location not set' }}</strong></span>
                    <span><small>Roof surfaces</small><strong data-rss-summary-surfaces>{{ number_format($surfaceCount) }} captured</strong></span>
                    <span><small>Current state</small><strong data-rss-summary-status>{{ $statusLabel }}</strong></span>
                </div>
            </div>

            <aside class="rss-workspace-command">
                <div class="rss-workspace-command-head">
                    <span class="rss-workspace-command-icon" aria-hidden="true">
                        <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                            <rect class="i-main" x="5.2" y="4.4" width="13.6" height="15.2" rx="3"/>
                            <path class="i-soft" d="M8.3 8h7.4M8.3 11h4.8"/>
                            <path class="i-accent" d="m8.6 15 1.8 1.8 4.6-4.8"/>
                            <circle class="i-fill" cx="17.6" cy="6.5" r="1.25"/>
                        </svg>
                    </span>
                    <span class="rss-workspace-state is-{{ $quality }}">{{ $statusLabel }}</span>
                </div>

                <div class="rss-workspace-ring" style="--rss-readiness: {{ min(100, (float) $summary['readiness_percent']) }}">
                    <span><strong data-rss-readiness-value>{{ number_format($summary['readiness_percent'], 0) }}%</strong><small>ready</small></span>
                </div>

                <div class="rss-workspace-command-copy">
                    <strong data-rss-command-title>{{ $summary['is_ready_for_verification'] ? 'Engineering verification available' : 'Complete the survey foundation' }}</strong>
                    <p data-rss-command-copy>{{ $summary['is_ready_for_verification'] ? 'The saved survey satisfies the current completeness gate.' : 'Readiness responds to survey details, structural review, and complete roof surfaces.' }}</p>
                </div>

                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="rss-button rss-button-ghost rss-button-full">
                    View project cockpit
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </aside>
        </section>

        <section class="rss-workspace-kpis" aria-label="Live roof survey summary">
            <article>
                <span class="rss-kpi-icon is-teal" aria-hidden="true"><svg class="rss-icon-art" viewBox="0 0 24 24" fill="none"><path class="i-main" d="m4.6 17.7 3.5-9h7.8l3.5 9Z"/><path class="i-soft" d="M7.4 13.4h9.2M9.2 8.7l-1.8 9M14.8 8.7l1.8 9"/><path class="i-accent" d="M5.2 5.3v2.2M18.8 5.3v2.2M5.2 6.4h13.6"/><circle class="i-fill" cx="12" cy="6.4" r="1.05"/></svg></span>
                <div><small>Gross roof area</small><strong>{{ number_format($summary['gross_area_m2'], 2) }}</strong><span>m² across {{ number_format($surfaceCount) }} surface{{ $surfaceCount === 1 ? '' : 's' }}</span></div>
            </article>
            <article>
                <span class="rss-kpi-icon is-blue" aria-hidden="true"><svg class="rss-icon-art" viewBox="0 0 24 24" fill="none"><path class="i-soft" d="m4.6 17.7 3.5-9h7.8l3.5 9Z"/><path class="i-main" d="m7.1 15.6 2.3-5.5h5.2l2.3 5.5Z"/><path class="i-accent" d="M9.3 13h5.4"/><path class="i-soft" d="M6.4 19.4h11.2"/><circle class="i-fill" cx="17.7" cy="7.2" r="1.05"/></svg></span>
                <div><small>Usable roof area</small><strong>{{ number_format($summary['usable_area_m2'], 2) }}</strong><span>{{ number_format($summary['usable_area_percent'], 1) }}% of gross area</span></div>
            </article>
            <article>
                <span class="rss-kpi-icon is-amber" aria-hidden="true"><svg class="rss-icon-art" viewBox="0 0 24 24" fill="none"><circle class="i-accent" cx="7.8" cy="7.3" r="2.25"/><path class="i-soft" d="M7.8 3.5v1.2M4.4 4.9l.85.85M3.8 8.2H5"/><path class="i-main" d="m4.9 17.7 3.5-8.7h7.2l3.5 8.7Z"/><path class="i-soft" d="M9.3 9.1 15 17.7M5 17.7h14"/><path class="i-accent" d="m13 11.9 2.9 4.4"/><circle class="i-fill" cx="16.7" cy="8.2" r="1.05"/></svg></span>
                <div><small>Weighted shading</small><strong>{{ number_format($summary['weighted_shading_percent'], 1) }}%</strong><span>Area-weighted exposure</span></div>
            </article>
            <article>
                <span class="rss-kpi-icon is-violet" aria-hidden="true"><svg class="rss-icon-art" viewBox="0 0 24 24" fill="none"><circle class="i-main" cx="12" cy="12" r="7.4"/><path class="i-soft" d="M12 4.6v2M19.4 12h-2M12 19.4v-2M4.6 12h2"/><path class="i-accent" d="m8.7 12.2 2.1 2.1 4.5-4.8"/><circle class="i-fill" cx="18.1" cy="6.1" r="1.2"/></svg></span>
                <div><small>Dataset readiness</small><strong data-rss-kpi-readiness>{{ number_format($summary['readiness_percent'], 0) }}%</strong><span>{{ $statusLabel }}</span></div>
            </article>
        </section>

        @if ($errors->any())
            <div class="rss-validation-alert" role="alert">
                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg></span>
                <div><strong>Review the highlighted roof-survey inputs</strong><p>{{ $errors->first() }}</p></div>
            </div>
        @endif

        <nav class="rss-workspace-nav" aria-label="Roof survey sections">
            <a href="#rss-foundation" class="is-active" data-rss-section-link="rss-foundation"><span>01</span>Foundation</a>
            <a href="#rss-surfaces" data-rss-section-link="rss-surfaces"><span>02</span>Roof surfaces</a>
            <a href="#rss-evidence" data-rss-section-link="rss-evidence"><span>03</span>Evidence</a>
            <a href="#rss-audit" data-rss-section-link="rss-audit"><span>04</span>Audit trail</a>
            <div class="rss-workspace-nav-state"><i aria-hidden="true"></i><span data-rss-nav-state>{{ $isVerified ? 'Verified' : ($summary['is_ready_for_verification'] ? 'Ready to verify' : 'In progress') }}</span></div>
        </nav>

        @if (! $hasSurfaceData && $canManage)
            <section class="rss-onboarding">
                <div class="rss-onboarding-copy">
                    <span class="rss-onboarding-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 5.2h8.4v13.6H5.5z"/><path d="M8 8.5h3.5M8 12h2.5" opacity=".5"/><path d="M14 8.3h3.5v3.3M17.5 11.6l-2.2 2.2M15.3 13.8h3.5v3.5"/></svg></span>
                    <div><div class="rss-section-kicker">Guided setup</div><h3>Start with the survey foundation, then capture one roof surface</h3><p>That single workflow activates usable-area calculations, warning logic, and the verification readiness path.</p></div>
                </div>
                <div class="rss-onboarding-actions">
                    <a href="#rss-foundation"><strong>Complete survey details</strong><span>Record date, access, mounting, safety, and structural review.</span><small>Review foundation →</small></a>
                    <a href="{{ route('companies.roof-site.surfaces.create', [$company, $project]) }}"><strong>Add first roof surface</strong><span>Capture geometry, exclusions, orientation, shading, and roof condition.</span><small>Open surface editor →</small></a>
                </div>
            </section>
        @endif

        <div class="rss-workspace-layout">
            <div class="rss-workspace-main">
                <form id="rss-survey-form" method="POST" action="{{ route('companies.roof-site.update', [$company, $project]) }}" data-rss-form data-rss-action-form data-rss-action="save">
                    @csrf
                    @method('PUT')

                    <section class="rss-section" id="rss-foundation" data-rss-section>
                        <header class="rss-section-head">
                            <span class="rss-section-number">01</span>
                            <span class="rss-section-icon is-teal" aria-hidden="true"><svg class="rss-icon-art" viewBox="0 0 24 24" fill="none"><rect class="i-main" x="6" y="4.6" width="12" height="14.8" rx="2.7"/><path class="i-soft" d="M9.2 4.6V3.2h5.6v1.4M8.7 9h6.6M8.7 12h4"/><path class="i-accent" d="M14.3 15.4c0 1.5-2.3 3.1-2.3 3.1s-2.3-1.6-2.3-3.1a2.3 2.3 0 1 1 4.6 0Z"/><circle class="i-fill" cx="12" cy="15.2" r=".8"/></svg></span>
                            <div><div class="rss-section-kicker">Survey foundation</div><h3>Record the site, access, safety, and engineering context</h3><p>Keep the baseline compact and traceable. These values drive readiness and warning logic.</p></div>
                            <span class="rss-section-state {{ $foundationReady ? '' : 'is-warning' }}" data-rss-foundation-state>{{ $foundationReady ? 'Ready' : 'Needs input' }}</span>
                        </header>

                        <div class="rss-section-body">
                            @if ($canManage)
                                <div class="rss-form-group">
                                    <div class="rss-form-group-head"><strong>Survey identity</strong><span>When and by whom the site was assessed.</span></div>
                                    <div class="rss-field-grid">
                                        <div class="rss-field"><label for="survey_date">Survey date</label><input id="survey_date" type="date" name="survey_date" value="{{ old('survey_date', $survey->survey_date?->format('Y-m-d')) }}" data-rss-field></div>
                                        @if ($canAssignSurveyor)
                                            <div class="rss-field"><label for="surveyed_by_user_id">Surveyed by</label><select id="surveyed_by_user_id" name="surveyed_by_user_id" data-rss-field><option value="">Select team member</option>@foreach ($companyMembers as $member)<option value="{{ $member->id }}" @selected((string) old('surveyed_by_user_id', $survey->surveyed_by_user_id) === (string) $member->id)>{{ $member->name }} · {{ $member->email }}</option>@endforeach</select></div>
                                        @endif
                                        <div class="rss-field {{ $canAssignSurveyor ? 'span-2' : '' }}"><label for="weather_condition">Survey weather</label><input id="weather_condition" type="text" name="weather_condition" maxlength="80" value="{{ old('weather_condition', $survey->weather_condition) }}" placeholder="Example: Clear, dry roof surface" data-rss-field></div>
                                    </div>
                                </div>

                                <div class="rss-form-group">
                                    <div class="rss-form-group-head"><strong>Access and safety</strong><span>Document how the team reached the roof and the safety controls observed onsite.</span></div>
                                    <div class="rss-field-grid">
                                        <div class="rss-field"><label for="access_method">Roof access method</label><select id="access_method" name="access_method" data-rss-field><option value="">Not recorded</option>@foreach (\App\Models\RoofSiteSurvey::ACCESS_METHODS as $value)<option value="{{ $value }}" @selected(old('access_method', $survey->access_method) === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select></div>
                                        <div class="rss-field"><label for="access_condition">Access condition</label><select id="access_condition" name="access_condition" data-rss-field>@foreach (\App\Models\RoofSiteSurvey::ACCESS_CONDITIONS as $value)<option value="{{ $value }}" @selected(old('access_condition', $survey->access_condition) === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select></div>
                                        <div class="rss-field"><label for="fall_protection_available">Fall protection</label><select id="fall_protection_available" name="fall_protection_available" data-rss-field><option value="">Not recorded</option><option value="1" @selected((string) old('fall_protection_available', is_null($survey->fall_protection_available) ? '' : ($survey->fall_protection_available ? '1' : '0')) === '1')>Available</option><option value="0" @selected((string) old('fall_protection_available', is_null($survey->fall_protection_available) ? '' : ($survey->fall_protection_available ? '1' : '0')) === '0')>Not available</option></select></div>
                                        <div class="rss-field"><label for="lightning_protection_present">Lightning protection</label><select id="lightning_protection_present" name="lightning_protection_present" data-rss-field><option value="">Not recorded</option><option value="1" @selected((string) old('lightning_protection_present', is_null($survey->lightning_protection_present) ? '' : ($survey->lightning_protection_present ? '1' : '0')) === '1')>Present</option><option value="0" @selected((string) old('lightning_protection_present', is_null($survey->lightning_protection_present) ? '' : ($survey->lightning_protection_present ? '1' : '0')) === '0')>Not present</option></select></div>
                                    </div>
                                </div>

                                <div class="rss-form-group">
                                    <div class="rss-form-group-head"><strong>Mounting, structure, and routing</strong><span>Summarize the expected mounting approach and electrical path without adding visual clutter.</span></div>
                                    <div class="rss-field-grid">
                                        <div class="rss-field"><label for="mounting_preference">Mounting preference</label><select id="mounting_preference" name="mounting_preference" data-rss-field>@foreach (\App\Models\RoofSiteSurvey::MOUNTING_PREFERENCES as $value)<option value="{{ $value }}" @selected(old('mounting_preference', $survey->mounting_preference) === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select></div>
                                        <div class="rss-field"><label for="structural_review_status">Structural review status</label><select id="structural_review_status" name="structural_review_status" data-rss-field>@foreach (\App\Models\RoofSiteSurvey::STRUCTURAL_REVIEW_STATUSES as $value)<option value="{{ $value }}" @selected(old('structural_review_status', $survey->structural_review_status) === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select></div>
                                        <div class="rss-field"><label for="electrical_room_distance_m">Electrical room distance (m)</label><input id="electrical_room_distance_m" type="number" min="0" step="0.01" name="electrical_room_distance_m" value="{{ old('electrical_room_distance_m', $survey->electrical_room_distance_m) }}" data-rss-field></div>
                                        <div class="rss-field"><label for="estimated_cable_route_m">Estimated cable route (m)</label><input id="estimated_cable_route_m" type="number" min="0" step="0.01" name="estimated_cable_route_m" value="{{ old('estimated_cable_route_m', $survey->estimated_cable_route_m) }}" data-rss-field></div>
                                        <div class="rss-field span-2"><label for="structural_notes">Structural notes</label><textarea id="structural_notes" name="structural_notes" rows="3" data-rss-field data-rss-autosize>{{ old('structural_notes', $survey->structural_notes) }}</textarea></div>
                                        <div class="rss-field span-2"><label for="notes">General survey notes</label><textarea id="notes" name="notes" rows="3" data-rss-field data-rss-autosize>{{ old('notes', $survey->notes) }}</textarea></div>
                                    </div>
                                </div>
                            @else
                                <div class="rss-readonly-grid">
                                    <article><small>Survey date</small><strong>{{ $survey->survey_date?->format('d M Y') ?? 'Not recorded' }}</strong></article>
                                    <article><small>Surveyor</small><strong>{{ $survey->surveyor?->name ?? 'Not recorded' }}</strong></article>
                                    <article><small>Access method</small><strong>{{ $survey->access_method_label }}</strong></article>
                                    <article><small>Access condition</small><strong>{{ ucwords(str_replace('_', ' ', $survey->access_condition ?: 'unknown')) }}</strong></article>
                                    <article><small>Mounting preference</small><strong>{{ ucwords(str_replace('_', ' ', $survey->mounting_preference ?: 'undecided')) }}</strong></article>
                                    <article><small>Structural review</small><strong>{{ $survey->structural_review_status_label }}</strong></article>
                                </div>
                            @endif
                        </div>
                    </section>
                </form>

                <section class="rss-section" id="rss-surfaces" data-rss-section>
                        <header class="rss-section-head">
                            <span class="rss-section-number">02</span>
                            <span class="rss-section-icon is-blue" aria-hidden="true"><svg class="rss-icon-art" viewBox="0 0 24 24" fill="none"><path class="i-main" d="m4.6 17.7 3.5-9h7.8l3.5 9Z"/><path class="i-soft" d="M7.4 13.3h9.2M9.2 8.7l-1.8 9M14.8 8.7l1.8 9"/><path class="i-accent" d="M6 5.7h4M14 5.7h4"/><path class="i-accent" d="M7.5 4.7v2M16.5 4.7v2"/><circle class="i-fill" cx="12" cy="10.9" r="1"/></svg></span>
                            <div><div class="rss-section-kicker">Roof surface analysis</div><h3>Translate each roof plane into usable engineering area</h3><p>Review geometry, exclusions, orientation, shading, and surface condition in one compact registry.</p></div>
                            @if ($canManage)<a href="{{ route('companies.roof-site.surfaces.create', [$company, $project]) }}" class="rss-button rss-button-primary rss-section-action">Add surface</a>@else<span class="rss-section-state">{{ $surfaceCount }} surfaces</span>@endif
                        </header>

                        <div class="rss-section-body">
                            @if ($survey->surfaces->isEmpty())
                                <div class="rss-section-empty">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 18h16M6 14h12M8 10h8"/><path d="m5 18 4-12h6l4 12"/></svg></span>
                                    <div><h4>No roof surfaces recorded</h4><p>Add one surface to activate gross area, usable area, shading, and verification readiness.</p></div>
                                    @if ($canManage)<a href="{{ route('companies.roof-site.surfaces.create', [$company, $project]) }}" class="rss-button rss-button-primary">Add first surface</a>@endif
                                </div>
                            @else
                                <div class="rss-surface-list">
                                    @foreach ($survey->surfaces as $surface)
                                        @php
                                            $usableArea = (float) $surface->usableAreaM2();
                                            $utilization = (float) $surface->gross_area_m2 > 0 ? max(0, min(100, ($usableArea / (float) $surface->gross_area_m2) * 100)) : 0;
                                            $conditionTone = match ($surface->roof_condition) {
                                                'good' => 'is-good',
                                                'fair' => 'is-fair',
                                                'poor' => 'is-poor',
                                                default => 'is-neutral',
                                            };
                                        @endphp
                                        <article class="rss-surface-card">
                                            <header>
                                                <div>
                                                    <div class="rss-surface-title-row"><h4>{{ $surface->name }}</h4><span class="rss-condition-badge {{ $conditionTone }}">{{ $surface->roof_condition_label }}</span></div>
                                                    <p>{{ $surface->roof_type_label }} · {{ $surface->roof_material_label }}</p>
                                                </div>
                                                @if ($canManage)
                                                    <div class="rss-surface-actions">
                                                        <a href="{{ route('companies.roof-site.surfaces.edit', [$company, $project, $surface]) }}" class="rss-icon-button" aria-label="Edit {{ $surface->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4.2-1 10-10a2 2 0 0 0-3-3l-10 10L4 20Z"/></svg></a>
                                                        <form method="POST" action="{{ route('companies.roof-site.surfaces.destroy', [$company, $project, $surface]) }}" data-confirm="Remove this roof surface?">@csrf @method('DELETE')<button type="submit" class="rss-icon-button is-danger" aria-label="Remove {{ $surface->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13"/></svg></button></form>
                                                    </div>
                                                @endif
                                            </header>
                                            <div class="rss-surface-metrics">
                                                <article><small>Gross area</small><strong>{{ number_format($surface->gross_area_m2, 2) }} m²</strong></article>
                                                <article><small>Usable area</small><strong>{{ number_format($usableArea, 2) }} m²</strong></article>
                                                <article><small>Tilt / azimuth</small><strong>{{ number_format($surface->tilt_deg, 1) }}° / {{ number_format($surface->azimuth_deg, 1) }}°</strong></article>
                                                <article><small>Shading</small><strong>{{ number_format($surface->shading_percent, 1) }}%</strong></article>
                                            </div>
                                            <div class="rss-utilization-row">
                                                <span><b>{{ number_format($utilization, 1) }}%</b> usable area</span>
                                                <div class="rss-utilization-track"><i style="width: {{ number_format($utilization, 2, '.', '') }}%"></i></div>
                                                <small>Setback {{ number_format((float) $surface->setback_area_m2, 2) }} · Obstruction {{ number_format((float) $surface->obstruction_area_m2, 2) }} · Walkway {{ number_format((float) $surface->walkway_area_m2, 2) }} m²</small>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="rss-section" id="rss-evidence" data-rss-section>
                        <header class="rss-section-head">
                            <span class="rss-section-number">03</span>
                            <span class="rss-section-icon is-violet" aria-hidden="true"><svg class="rss-icon-art" viewBox="0 0 24 24" fill="none"><rect class="i-main" x="4.4" y="5.2" width="15.2" height="13.6" rx="3"/><circle class="i-accent" cx="9" cy="9.1" r="1.35"/><path class="i-main" d="m6.9 16 3.6-3.6 2.7 2.5 2.3-2.1 3.2 3.2"/><path class="i-soft" d="M15.3 7.7h2.2"/><circle class="i-fill" cx="17.7" cy="7.7" r=".9"/></svg></span>
                            <div><div class="rss-section-kicker">Survey evidence</div><h3>Build a visual record that reviewers can trust</h3><p>Upload roof, access, obstruction, electrical, and site photographs with clear categories and captions.</p></div>
                            <span class="rss-section-state {{ $photoCount > 0 ? '' : 'is-warning' }}">{{ $photoCount }} photos</span>
                        </header>

                        <div class="rss-section-body">
                            @if ($canManage)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('companies.roof-site.photos.store', [$company, $project]) }}" class="rss-evidence-uploader" data-rss-action-form data-rss-action="save" data-rss-action-title="Uploading Survey Evidence" data-rss-action-copy="Securing the image, category, caption, and audit metadata.">
                                    @csrf
                                    <label class="rss-dropzone" for="rss-photo-input" data-rss-dropzone>
                                        <input id="rss-photo-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required data-rss-photo-input>
                                        <span class="rss-dropzone-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 14v5h14v-5"/></svg></span>
                                        <span><strong data-rss-file-name>Drop survey evidence here or browse files</strong><small>JPEG, PNG, or WebP. Add a clear category and caption before upload.</small></span>
                                        <img alt="Selected evidence preview" data-rss-photo-preview hidden>
                                    </label>
                                    <div class="rss-evidence-fields">
                                        <div class="rss-field"><label for="category">Category</label><select id="category" name="category">@foreach (\App\Models\RoofSiteSurveyPhoto::CATEGORIES as $value)<option value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></div>
                                        <div class="rss-field"><label for="caption">Caption</label><input id="caption" type="text" name="caption" maxlength="255" placeholder="Describe what the photo shows"></div>
                                        <button class="rss-button rss-button-primary" type="submit">Upload evidence</button>
                                    </div>
                                </form>
                            @endif

                            @if ($survey->photos->isEmpty())
                                <div class="rss-section-empty is-compact">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="m7 16 4-4 3 3 2-2 3 3"/></svg></span>
                                    <div><h4>No survey evidence uploaded</h4><p>A roof overview and access photograph are a strong minimum starting point.</p></div>
                                </div>
                            @else
                                <div class="rss-photo-grid">
                                    @foreach ($survey->photos as $photo)
                                        <article class="rss-photo-card">
                                            <img src="{{ Storage::url($photo->path) }}" alt="{{ $photo->caption ?: $photo->category_label }}">
                                            <div>
                                                <span class="rss-photo-category">{{ $photo->category_label }}</span>
                                                <strong>{{ $photo->caption ?: $photo->original_name }}</strong>
                                                <small>{{ $photo->uploader?->name ?? 'Team member' }} · {{ $photo->created_at?->format('d M Y, H:i') }}</small>
                                                @if ($canManage)
                                                    <form method="POST" action="{{ route('companies.roof-site.photos.destroy', [$company, $project, $photo]) }}" data-confirm="Remove this survey photo?">@csrf @method('DELETE')<button type="submit" class="rss-photo-remove">Remove</button></form>
                                                @endif
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="rss-section" id="rss-audit" data-rss-section>
                        <header class="rss-section-head">
                            <span class="rss-section-number">04</span>
                            <span class="rss-section-icon is-neutral" aria-hidden="true"><svg class="rss-icon-art" viewBox="0 0 24 24" fill="none"><path class="i-main" d="M6 4.4h10.8v15.2H6z"/><path class="i-soft" d="M8.8 8h5.1M8.8 11.5h4M8.8 15h3"/><path class="i-accent" d="M18.1 7.2v8.6"/><circle class="i-fill" cx="18.1" cy="7.2" r="1.15"/><circle class="i-fill" cx="18.1" cy="11.5" r="1.15"/><circle class="i-fill" cx="18.1" cy="15.8" r="1.15"/></svg></span>
                            <div><div class="rss-section-kicker">Audit trail</div><h3>Keep every survey update and verification traceable</h3><p>Review a compact timeline of changes, responsible users, and engineering milestones.</p></div>
                            <span class="rss-section-state">{{ $historyCount }} events</span>
                        </header>

                        <div class="rss-section-body">
                            @if ($survey->histories->isEmpty())
                                <div class="rss-section-empty is-compact"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6"/></svg></span><div><h4>No audit event has been recorded yet</h4><p>The first saved update creates a traceable roof-survey history entry.</p></div></div>
                            @else
                                <div class="rss-history-timeline">
                                    @foreach ($survey->histories as $history)
                                        <article>
                                            <span class="rss-history-marker {{ $history->event_type === 'verified' ? 'is-verified' : '' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">@if ($history->event_type === 'verified')<path d="M20 7 9 18l-5-5"/>@else<path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/>@endif</svg></span>
                                            <div><header><strong>{{ ucwords(str_replace('_', ' ', $history->event_type)) }}</strong><time>{{ $history->changed_at?->format('d M Y · H:i') }}</time></header><p>{{ $history->summary }}</p><footer><span>{{ $history->changedBy?->name ?? 'System' }}</span></footer></div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                </div>

                <aside class="rss-workspace-rail">
                    <section class="rss-rail-card rss-rail-control">
                        <header>
                            <div><div class="rss-section-kicker">Live survey state</div><h3>Readiness control</h3></div>
                            <div class="rss-rail-ring" style="--rss-readiness: {{ min(100, (float) $summary['readiness_percent']) }}"><span data-rss-rail-readiness>{{ number_format($summary['readiness_percent'], 0) }}%</span></div>
                        </header>

                        <div class="rss-rail-change-state">
                            <span><i aria-hidden="true"></i><b data-rss-change-label>{{ $errors->any() ? 'Review required' : 'Saved state' }}</b></span>
                            <strong data-rss-change-count>0 fields</strong>
                        </div>

                        <dl class="rss-rail-summary">
                            <div><dt>Roof surfaces</dt><dd>{{ $completeSurfaceCount }}/{{ $surfaceCount }} complete</dd></div>
                            <div><dt>Overall condition</dt><dd><span class="rss-condition-inline {{ $conditionClass }}">{{ $summary['overall_condition'] }}</span></dd></div>
                            <div><dt>Structural review</dt><dd data-rss-rail-structural>{{ $survey->structural_review_status_label }}</dd></div>
                            <div><dt>Evidence</dt><dd>{{ $photoCount }} photo{{ $photoCount === 1 ? '' : 's' }}</dd></div>
                            <div><dt>Warnings</dt><dd>{{ number_format($summary['warning_count']) }}</dd></div>
                        </dl>

                        <div class="rss-rail-gate">
                            <span>Verification path</span>
                            <div class="rss-readiness-path">
                                <span class="{{ $surfaceCount > 0 ? 'is-complete' : '' }}"><i></i><b>Dataset</b></span>
                                <span class="{{ $summary['is_ready_for_verification'] ? 'is-complete' : '' }}"><i></i><b>Ready</b></span>
                                <span class="{{ $isVerified ? 'is-complete' : '' }}"><i></i><b>Verified</b></span>
                            </div>
                        </div>

                        @if ($canManage)
                            <button type="submit" form="rss-survey-form" class="rss-save-button" data-rss-submit disabled><span data-rss-submit-label>Saved</span></button>
                            <span class="rss-save-hint"><kbd>Ctrl</kbd><b>+</b><kbd>S</kbd> quick save</span>
                        @endif

                        @if ($canVerify && ! $isVerified)
                            <button type="submit" form="rss-verify-form" class="rss-verify-button" data-rss-verify @disabled(! $summary['is_ready_for_verification'])>Verify survey dataset</button>
                        @endif
                    </section>

                    <section class="rss-rail-card rss-rail-warnings">
                        <header><div><div class="rss-section-kicker">Engineering warnings</div><h3>{{ $summary['warning_count'] > 0 ? $summary['warning_count'].' review signal'.($summary['warning_count'] === 1 ? '' : 's') : 'No automatic warning' }}</h3></div></header>
                        @if (empty($summary['warnings']))
                            <div class="rss-warning-empty"><span>✓</span><p>The current saved dataset does not trigger automatic roof-survey warnings.</p></div>
                        @else
                            <ul>@foreach ($summary['warnings'] as $warning)<li><span>!</span><p>{{ $warning }}</p></li>@endforeach</ul>
                        @endif
                    </section>

                    <section class="rss-rail-scope">
                        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l7 3v5c0 4.6-2.8 8-7 10-4.2-2-7-5.4-7-10V6l7-3Z"/><path d="M9 12h6"/></svg></span>
                        <div><strong>Controlled verification scope</strong><p>Verification confirms dataset completeness. It does not certify structural capacity, roof integrity, fall protection compliance, or final installation safety.</p></div>
                    </section>
            </aside>
        </div>

        @if ($canManage)
            <div class="rss-mobile-save" data-rss-mobile-save>
                <div><span data-rss-mobile-state>Saved state</span><strong data-rss-mobile-count>0 changes</strong></div>
                <button type="submit" form="rss-survey-form" data-rss-submit disabled><span data-rss-submit-label>Saved</span></button>
            </div>
        @endif
    </div>
@endsection
