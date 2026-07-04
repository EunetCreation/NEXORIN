@extends('layouts.app')

@section('title', 'Roof & Site Survey')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Roof & Site Survey')

@push('styles')
<style>
    .rss-stack { display: grid; gap: 24px; }
    .rss-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 24px;
        align-items: center;
        padding: 32px;
        border-radius: 28px;
        color: #fff;
        background: linear-gradient(135deg, #031d2c 0%, #0b5363 100%);
        box-shadow: var(--shadow-md);
    }
    .rss-hero h2 { margin: 8px 0 12px; font: 700 clamp(2rem, 5vw, 4rem)/1.02 "Space Grotesk", sans-serif; }
    .rss-hero p { max-width: 760px; margin: 0; color: rgba(255,255,255,.72); font-size: 1.05rem; line-height: 1.65; }
    .rss-hero-kicker { color: var(--teal-400); font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
    .rss-actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: flex-end; }
    .rss-actions .ui-button { white-space: nowrap; }
    .rss-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .rss-card { min-width: 0; padding: 26px; border: 1px solid rgba(203,214,223,.75); border-radius: 24px; background: rgba(255,255,255,.94); box-shadow: var(--shadow-sm); }
    .rss-stat strong { display: block; margin-top: 10px; font: 700 2rem/1 "Space Grotesk", sans-serif; }
    .rss-stat span { color: var(--slate-600); }
    .rss-layout { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(320px, .85fr); gap: 24px; align-items: start; }
    .rss-main, .rss-side { min-width: 0; display: grid; gap: 24px; }
    .rss-card h3 { margin: 0 0 8px; font: 700 1.45rem/1.2 "Space Grotesk", sans-serif; }
    .rss-card-intro { margin: 0 0 22px; color: var(--slate-600); line-height: 1.6; }
    .rss-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .rss-form-grid .span-2 { grid-column: 1 / -1; }
    .rss-field { min-width: 0; display: grid; gap: 8px; }
    .rss-field label { color: var(--slate-700); font-size: .86rem; font-weight: 700; }
    .rss-field input, .rss-field select, .rss-field textarea {
        width: 100%; min-width: 0; border: 1px solid var(--slate-300); border-radius: 14px; padding: 13px 14px; color: var(--slate-950); background: #fff;
    }
    .rss-field textarea { min-height: 120px; resize: vertical; }
    .rss-section-head { display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center; margin-bottom: 18px; }
    .rss-surface-list { display: grid; gap: 14px; }
    .rss-surface { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 18px; padding: 18px; border: 1px solid var(--slate-200); border-radius: 18px; background: var(--slate-50); }
    .rss-surface-title { margin: 0 0 8px; font-weight: 700; }
    .rss-meta { display: flex; flex-wrap: wrap; gap: 8px 14px; color: var(--slate-600); font-size: .9rem; }
    .rss-inline-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start; }
    .rss-warning-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .rss-warning { padding: 12px 14px; border: 1px solid #f6c5c8; border-radius: 14px; color: #9f2f3a; background: #fff3f4; line-height: 1.5; }
    .rss-status-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1px; overflow: hidden; border: 1px solid var(--slate-200); border-radius: 18px; background: var(--slate-200); }
    .rss-status-cell { min-width: 0; padding: 18px; background: #fff; }
    .rss-status-cell small { display: block; color: var(--slate-500); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
    .rss-status-cell strong { display: block; margin-top: 8px; overflow-wrap: anywhere; }
    .rss-photo-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 14px; }
    .rss-photo { overflow: hidden; border: 1px solid var(--slate-200); border-radius: 16px; background: #fff; }
    .rss-photo img { width: 100%; aspect-ratio: 4/3; object-fit: cover; }
    .rss-photo-body { padding: 12px; }
    .rss-history { display: grid; gap: 12px; }
    .rss-history-item { padding: 16px; border-left: 3px solid var(--teal-500); border-radius: 0 14px 14px 0; background: var(--slate-50); }
    .rss-history-item strong { display: block; margin-bottom: 6px; }
    .rss-history-item p { margin: 0 0 6px; color: var(--slate-600); }
    .rss-history-item small { color: var(--slate-500); }
    .rss-empty { padding: 24px; border: 1px dashed var(--slate-300); border-radius: 18px; color: var(--slate-600); text-align: center; }
    @media (max-width: 1180px) { .rss-layout { grid-template-columns: 1fr; } .rss-side { grid-template-columns: repeat(2,minmax(0,1fr)); } .rss-side > * { min-width: 0; } }
    @media (max-width: 860px) { .rss-hero { grid-template-columns: 1fr; } .rss-actions { justify-content: flex-start; } .rss-summary { grid-template-columns: repeat(2,minmax(0,1fr)); } .rss-side { grid-template-columns: 1fr; } .rss-photo-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } }
    @media (max-width: 620px) { .rss-summary, .rss-form-grid, .rss-status-grid, .rss-photo-grid { grid-template-columns: 1fr; } .rss-form-grid .span-2 { grid-column: auto; } .rss-surface { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="rss-stack">
    <section class="rss-hero">
        <div>
            <div class="rss-hero-kicker">{{ $project->project_number }} · {{ $project->customer->name }}</div>
            <h2>{{ $project->name }}</h2>
            <p>Roof geometry, usable installation area, shading, access, safety, structural review, and survey evidence.</p>
        </div>
        <div class="rss-actions">
            <a class="ui-button ui-button-secondary" href="{{ route('companies.roof-site.index', $company) }}">Survey Directory</a>
            <a class="ui-button ui-button-secondary" href="{{ route('companies.projects.show', [$company, $project]) }}">Project Details</a>
            @if($canManage)
                <a class="ui-button ui-button-primary" href="{{ route('companies.roof-site.surfaces.create', [$company, $project]) }}">Add Roof Surface</a>
            @endif
        </div>
    </section>

    <section class="rss-summary">
        <article class="rss-card rss-stat"><span>Gross Roof Area</span><strong>{{ number_format($summary['gross_area_m2'], 2) }}</strong><span>m² across {{ $summary['surface_count'] }} surface(s)</span></article>
        <article class="rss-card rss-stat"><span>Usable Roof Area</span><strong>{{ number_format($summary['usable_area_m2'], 2) }}</strong><span>{{ number_format($summary['usable_area_percent'], 1) }}% of gross area</span></article>
        <article class="rss-card rss-stat"><span>Weighted Shading</span><strong>{{ number_format($summary['weighted_shading_percent'], 1) }}%</strong><span>Based on usable area</span></article>
        <article class="rss-card rss-stat"><span>Dataset Readiness</span><strong>{{ number_format($summary['readiness_percent'], 0) }}%</strong><span>{{ $survey->quality_status_label }}</span></article>
    </section>

    <div class="rss-layout">
        <div class="rss-main">
            <section class="rss-card">
                <div class="rss-section-head">
                    <div><h3>Survey Information</h3><p class="rss-card-intro">Record access, safety, mounting, and structural assessment details.</p></div>
                </div>

                @if($canManage)
                    <form method="POST" action="{{ route('companies.roof-site.update', [$company, $project]) }}">
                        @csrf @method('PUT')
                        <div class="rss-form-grid">
                            <div class="rss-field"><label for="survey_date">Survey date</label><input id="survey_date" type="date" name="survey_date" value="{{ old('survey_date', $survey->survey_date?->format('Y-m-d')) }}"></div>
                            @if($canAssignSurveyor)
                                <div class="rss-field"><label for="surveyed_by_user_id">Surveyed by</label><select id="surveyed_by_user_id" name="surveyed_by_user_id"><option value="">Select team member</option>@foreach($companyMembers as $member)<option value="{{ $member->id }}" @selected((string)old('surveyed_by_user_id',$survey->surveyed_by_user_id)===(string)$member->id)>{{ $member->name }}</option>@endforeach</select></div>
                            @endif
                            <div class="rss-field"><label for="access_method">Roof access method</label><select id="access_method" name="access_method"><option value="">Not recorded</option>@foreach(\App\Models\RoofSiteSurvey::ACCESS_METHODS as $value)<option value="{{ $value }}" @selected(old('access_method',$survey->access_method)===$value)>{{ ucwords(str_replace('_',' ',$value)) }}</option>@endforeach</select></div>
                            <div class="rss-field"><label for="access_condition">Access condition</label><select id="access_condition" name="access_condition">@foreach(\App\Models\RoofSiteSurvey::ACCESS_CONDITIONS as $value)<option value="{{ $value }}" @selected(old('access_condition',$survey->access_condition)===$value)>{{ ucwords(str_replace('_',' ',$value)) }}</option>@endforeach</select></div>
                            <div class="rss-field"><label for="mounting_preference">Mounting preference</label><select id="mounting_preference" name="mounting_preference">@foreach(\App\Models\RoofSiteSurvey::MOUNTING_PREFERENCES as $value)<option value="{{ $value }}" @selected(old('mounting_preference',$survey->mounting_preference)===$value)>{{ ucwords(str_replace('_',' ',$value)) }}</option>@endforeach</select></div>
                            <div class="rss-field"><label for="structural_review_status">Structural review status</label><select id="structural_review_status" name="structural_review_status">@foreach(\App\Models\RoofSiteSurvey::STRUCTURAL_REVIEW_STATUSES as $value)<option value="{{ $value }}" @selected(old('structural_review_status',$survey->structural_review_status)===$value)>{{ ucwords(str_replace('_',' ',$value)) }}</option>@endforeach</select></div>
                            <div class="rss-field"><label for="fall_protection_available">Fall protection</label><select id="fall_protection_available" name="fall_protection_available"><option value="">Not recorded</option><option value="1" @selected((string) old('fall_protection_available', is_null($survey->fall_protection_available) ? '' : ($survey->fall_protection_available ? '1' : '0')) === '1')>Available</option><option value="0" @selected((string) old('fall_protection_available', is_null($survey->fall_protection_available) ? '' : ($survey->fall_protection_available ? '1' : '0')) === '0')>Not available</option></select></div>
                            <div class="rss-field"><label for="lightning_protection_present">Lightning protection</label><select id="lightning_protection_present" name="lightning_protection_present"><option value="">Not recorded</option><option value="1" @selected((string) old('lightning_protection_present', is_null($survey->lightning_protection_present) ? '' : ($survey->lightning_protection_present ? '1' : '0')) === '1')>Present</option><option value="0" @selected((string) old('lightning_protection_present', is_null($survey->lightning_protection_present) ? '' : ($survey->lightning_protection_present ? '1' : '0')) === '0')>Not present</option></select></div>
                            <div class="rss-field"><label for="electrical_room_distance_m">Electrical room distance (m)</label><input id="electrical_room_distance_m" type="number" min="0" step="0.01" name="electrical_room_distance_m" value="{{ old('electrical_room_distance_m',$survey->electrical_room_distance_m) }}"></div>
                            <div class="rss-field"><label for="estimated_cable_route_m">Estimated cable route (m)</label><input id="estimated_cable_route_m" type="number" min="0" step="0.01" name="estimated_cable_route_m" value="{{ old('estimated_cable_route_m',$survey->estimated_cable_route_m) }}"></div>
                            <div class="rss-field span-2"><label for="weather_condition">Survey weather</label><input id="weather_condition" type="text" name="weather_condition" maxlength="80" value="{{ old('weather_condition',$survey->weather_condition) }}" placeholder="Example: Clear, dry roof surface"></div>
                            <div class="rss-field span-2"><label for="structural_notes">Structural notes</label><textarea id="structural_notes" name="structural_notes">{{ old('structural_notes',$survey->structural_notes) }}</textarea></div>
                            <div class="rss-field span-2"><label for="notes">General survey notes</label><textarea id="notes" name="notes">{{ old('notes',$survey->notes) }}</textarea></div>
                        </div>
                        <div class="rss-actions" style="margin-top:20px"><button class="ui-button ui-button-primary" type="submit">Save Survey Information</button></div>
                    </form>
                @else
                    <div class="rss-status-grid">
                        <div class="rss-status-cell"><small>Survey date</small><strong>{{ $survey->survey_date?->format('d M Y') ?? 'Not recorded' }}</strong></div>
                        <div class="rss-status-cell"><small>Surveyor</small><strong>{{ $survey->surveyor?->name ?? 'Not recorded' }}</strong></div>
                        <div class="rss-status-cell"><small>Access</small><strong>{{ $survey->access_method_label }}</strong></div>
                        <div class="rss-status-cell"><small>Structural review</small><strong>{{ $survey->structural_review_status_label }}</strong></div>
                    </div>
                @endif
            </section>

            <section class="rss-card">
                <div class="rss-section-head"><div><h3>Roof Surfaces</h3><p class="rss-card-intro">Usable area equals gross area minus setbacks, obstructions, and walkways.</p></div>@if($canManage)<a class="ui-button ui-button-primary" href="{{ route('companies.roof-site.surfaces.create', [$company,$project]) }}">Add Surface</a>@endif</div>
                @if($survey->surfaces->isEmpty())
                    <div class="rss-empty">No roof surfaces have been recorded.</div>
                @else
                    <div class="rss-surface-list">
                        @foreach($survey->surfaces as $surface)
                            <article class="rss-surface">
                                <div>
                                    <h4 class="rss-surface-title">{{ $surface->name }}</h4>
                                    <div class="rss-meta">
                                        <span>{{ $surface->roof_type_label }}</span><span>{{ $surface->roof_material_label }}</span><span>Condition: {{ $surface->roof_condition_label }}</span><span>Gross: {{ number_format($surface->gross_area_m2,2) }} m²</span><span>Usable: {{ number_format($surface->usableAreaM2(),2) }} m²</span><span>Tilt: {{ number_format($surface->tilt_deg,1) }}°</span><span>Azimuth: {{ number_format($surface->azimuth_deg,1) }}°</span><span>Shading: {{ number_format($surface->shading_percent,1) }}%</span>
                                    </div>
                                </div>
                                @if($canManage)
                                    <div class="rss-inline-actions">
                                        <a class="ui-button ui-button-secondary" href="{{ route('companies.roof-site.surfaces.edit',[$company,$project,$surface]) }}">Edit</a>
                                        <form method="POST" action="{{ route('companies.roof-site.surfaces.destroy',[$company,$project,$surface]) }}" onsubmit="return confirm('Remove this roof surface?')">@csrf @method('DELETE')<button class="ui-button ui-button-danger" type="submit">Remove</button></form>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rss-card">
                <div class="rss-section-head"><div><h3>Survey Evidence</h3><p class="rss-card-intro">Upload roof, access, obstruction, electrical, and site photographs.</p></div></div>
                @if($canManage)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('companies.roof-site.photos.store',[$company,$project]) }}" class="rss-form-grid" style="margin-bottom:22px">@csrf
                        <div class="rss-field"><label for="photo">Image</label><input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
                        <div class="rss-field"><label for="category">Category</label><select id="category" name="category">@foreach(\App\Models\RoofSiteSurveyPhoto::CATEGORIES as $value)<option value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></div>
                        <div class="rss-field span-2"><label for="caption">Caption</label><input id="caption" type="text" name="caption" maxlength="255" placeholder="Describe what the photo shows"></div>
                        <div class="span-2"><button class="ui-button ui-button-primary" type="submit">Upload Photo</button></div>
                    </form>
                @endif
                @if($survey->photos->isEmpty())
                    <div class="rss-empty">No survey photos have been uploaded.</div>
                @else
                    <div class="rss-photo-grid">
                        @foreach($survey->photos as $photo)
                            <article class="rss-photo"><img src="{{ Storage::url($photo->path) }}" alt="{{ $photo->caption ?: $photo->category_label }}"><div class="rss-photo-body"><strong>{{ $photo->category_label }}</strong><p>{{ $photo->caption ?: $photo->original_name }}</p>@if($canManage)<form method="POST" action="{{ route('companies.roof-site.photos.destroy',[$company,$project,$photo]) }}" onsubmit="return confirm('Remove this survey photo?')">@csrf @method('DELETE')<button class="ui-button ui-button-danger" type="submit">Remove</button></form>@endif</div></article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rss-card">
                <h3>Change History</h3><p class="rss-card-intro">Survey updates and verification events are retained for traceability.</p>
                @if($survey->histories->isEmpty())<div class="rss-empty">No history is available.</div>@else<div class="rss-history">@foreach($survey->histories as $history)<article class="rss-history-item"><strong>{{ ucwords(str_replace('_',' ',$history->event_type)) }}</strong><p>{{ $history->summary }}</p><small>{{ $history->changedBy?->name ?? 'System' }} · {{ $history->changed_at?->format('d M Y, H:i') }}</small></article>@endforeach</div>@endif
            </section>
        </div>

        <aside class="rss-side">
            <section class="rss-card">
                <div class="rss-section-head"><h3>Survey Status</h3><span class="ui-status {{ $survey->quality_status === 'verified' ? 'ui-status-active' : ($survey->quality_status === 'ready' ? 'ui-status-pending' : 'ui-status-inactive') }}">{{ $survey->quality_status_label }}</span></div>
                <div class="rss-status-grid">
                    <div class="rss-status-cell"><small>Surfaces</small><strong>{{ $summary['complete_surface_count'] }}/{{ $summary['surface_count'] }}</strong></div>
                    <div class="rss-status-cell"><small>Condition</small><strong>{{ $summary['overall_condition'] }}</strong></div>
                    <div class="rss-status-cell"><small>Structural review</small><strong>{{ $survey->structural_review_status_label }}</strong></div>
                    <div class="rss-status-cell"><small>Verified by</small><strong>{{ $survey->verifier?->name ?? 'Not verified' }}</strong></div>
                </div>
                @if($canVerify && $summary['is_ready_for_verification'] && $survey->quality_status !== 'verified')
                    <form method="POST" action="{{ route('companies.roof-site.verify',[$company,$project]) }}" style="margin-top:18px">@csrf @method('PATCH')<button class="ui-button ui-button-primary" type="submit">Verify Survey Dataset</button></form>
                @endif
            </section>

            <section class="rss-card">
                <h3>Engineering Warnings</h3><p class="rss-card-intro">Warnings support preliminary review and do not replace specialist assessment.</p>
                @if(empty($summary['warnings']))<div class="rss-empty">No automatic warnings were generated.</div>@else<ul class="rss-warning-list">@foreach($summary['warnings'] as $warning)<li class="rss-warning">{{ $warning }}</li>@endforeach</ul>@endif
            </section>

            <section class="rss-card">
                <h3>Verification Scope</h3>
                <p class="rss-card-intro">Verification confirms survey data completeness. It does not certify structural capacity, roof integrity, fall protection compliance, or final installation safety.</p>
                @if($survey->verified_at)<div class="rss-empty">Verified by {{ $survey->verifier?->name ?? 'authorized user' }} on {{ $survey->verified_at->format('d M Y, H:i') }}.</div>@endif
            </section>
        </aside>
    </div>
</div>
@endsection
