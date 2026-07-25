@push('styles')
    <link href="{{ asset('css/nexorin-roof-site.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-roof-site.js') }}?v=55a" defer></script>
@endpush

<div class="rss-surface-editor" data-rss-surface-editor>
    <section class="rss-surface-editor-hero">
        <div>
            <div class="rss-section-kicker">{{ $project->project_number }} · Roof surface editor</div>
            <h2>{{ $heading }}</h2>
            <p>{{ $description }}</p>
        </div>
        <a href="{{ route('companies.roof-site.show', [$company, $project]) }}" class="rss-button rss-button-primary">Back to survey</a>
    </section>

    <div class="rss-surface-editor-layout">
        <form method="POST" action="{{ $action }}" class="rss-surface-editor-main" data-rss-surface-form data-rss-action-form data-rss-action="save" data-rss-action-title="Saving Roof Surface" data-rss-action-copy="Validating geometry, exclusions, orientation, shading, and readiness impact.">
            @csrf
            @if ($method !== 'POST') @method($method) @endif

            <header>
                <div class="rss-section-kicker">Engineering inputs</div>
                <h3>Surface geometry and installation constraints</h3>
                <p>Keep the roof plane precise. The live panel updates usable area and orientation as you type.</p>
            </header>

            <div class="rss-surface-editor-body">
                <div class="rss-form-group">
                    <div class="rss-form-group-head"><strong>Surface identity</strong><span>Name the roof plane and document its physical construction.</span></div>
                    <div class="rss-field-grid">
                        <div class="rss-field span-2"><label for="name">Surface name</label><input id="name" name="name" type="text" maxlength="120" required value="{{ old('name', $surface->name) }}" placeholder="Example: Main north roof" data-rss-surface-field></div>
                        <div class="rss-field"><label for="roof_type">Roof type</label><select id="roof_type" name="roof_type" required data-rss-surface-field>@foreach (\App\Models\RoofSurface::ROOF_TYPES as $value)<option value="{{ $value }}" @selected(old('roof_type', $surface->roof_type) === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select></div>
                        <div class="rss-field"><label for="roof_material">Roof material</label><select id="roof_material" name="roof_material" required data-rss-surface-field>@foreach (\App\Models\RoofSurface::ROOF_MATERIALS as $value)<option value="{{ $value }}" @selected(old('roof_material', $surface->roof_material) === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select></div>
                        <div class="rss-field"><label for="roof_condition">Roof condition</label><select id="roof_condition" name="roof_condition" required data-rss-surface-field>@foreach (\App\Models\RoofSurface::ROOF_CONDITIONS as $value)<option value="{{ $value }}" @selected(old('roof_condition', $surface->roof_condition) === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
                        <div class="rss-field"><label for="sort_order">Display order</label><input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $surface->sort_order ?? 0) }}" data-rss-surface-field></div>
                    </div>
                </div>

                <div class="rss-form-group">
                    <div class="rss-form-group-head"><strong>Area and exclusions</strong><span>Usable area equals gross area minus setbacks, obstructions, and walkways.</span></div>
                    <div class="rss-field-grid">
                        <div class="rss-field"><label for="gross_area_m2">Gross area (m²)</label><input id="gross_area_m2" name="gross_area_m2" type="number" min="0.01" step="0.01" required value="{{ old('gross_area_m2', $surface->gross_area_m2) }}" data-rss-surface-field data-rss-metric="gross"></div>
                        <div class="rss-field"><label for="setback_area_m2">Setback area (m²)</label><input id="setback_area_m2" name="setback_area_m2" type="number" min="0" step="0.01" value="{{ old('setback_area_m2', $surface->setback_area_m2 ?? 0) }}" data-rss-surface-field data-rss-metric="setback"></div>
                        <div class="rss-field"><label for="obstruction_area_m2">Obstruction area (m²)</label><input id="obstruction_area_m2" name="obstruction_area_m2" type="number" min="0" step="0.01" value="{{ old('obstruction_area_m2', $surface->obstruction_area_m2 ?? 0) }}" data-rss-surface-field data-rss-metric="obstruction"></div>
                        <div class="rss-field"><label for="walkway_area_m2">Walkway area (m²)</label><input id="walkway_area_m2" name="walkway_area_m2" type="number" min="0" step="0.01" value="{{ old('walkway_area_m2', $surface->walkway_area_m2 ?? 0) }}" data-rss-surface-field data-rss-metric="walkway"></div>
                    </div>
                </div>

                <div class="rss-form-group">
                    <div class="rss-form-group-head"><strong>Orientation, shading, and structure</strong><span>Record the geometry and preliminary capacity assumptions used in downstream design.</span></div>
                    <div class="rss-field-grid">
                        <div class="rss-field"><label for="tilt_deg">Tilt (degrees)</label><input id="tilt_deg" name="tilt_deg" type="number" min="0" max="90" step="0.01" required value="{{ old('tilt_deg', $surface->tilt_deg) }}" data-rss-surface-field data-rss-metric="tilt"></div>
                        <div class="rss-field"><label for="azimuth_deg">Azimuth (degrees)</label><input id="azimuth_deg" name="azimuth_deg" type="number" min="0" max="360" step="0.01" required value="{{ old('azimuth_deg', $surface->azimuth_deg) }}" data-rss-surface-field data-rss-metric="azimuth"><small>0° north, 90° east, 180° south, 270° west.</small></div>
                        <div class="rss-field"><label for="shading_percent">Shading (%)</label><input id="shading_percent" name="shading_percent" type="number" min="0" max="100" step="0.01" required value="{{ old('shading_percent', $surface->shading_percent ?? 0) }}" data-rss-surface-field data-rss-metric="shading"></div>
                        <div class="rss-field"><label for="max_additional_load_kg_m2">Recorded load allowance (kg/m²)</label><input id="max_additional_load_kg_m2" name="max_additional_load_kg_m2" type="number" min="0" step="0.01" value="{{ old('max_additional_load_kg_m2', $surface->max_additional_load_kg_m2) }}" data-rss-surface-field><small>Leave blank when no structural data is available.</small></div>
                        <div class="rss-field span-2"><label for="notes">Surface notes</label><textarea id="notes" name="notes" rows="4" data-rss-surface-field data-rss-autosize>{{ old('notes', $surface->notes) }}</textarea></div>
                    </div>
                </div>
            </div>

            <footer class="rss-surface-editor-actions">
                <button class="rss-button rss-button-primary" type="submit">{{ $submitLabel }}</button>
                <a class="rss-button rss-button-ghost" href="{{ route('companies.roof-site.show', [$company, $project]) }}">Cancel</a>
            </footer>
        </form>

        <aside class="rss-surface-live">
            <section class="rss-surface-live-card">
                <div class="rss-section-kicker">Live geometry</div>
                <h3 data-rss-live-name>{{ old('name', $surface->name) ?: 'New roof surface' }}</h3>
                <div class="rss-surface-live-grid">
                    <article><small>Gross area</small><strong data-rss-live-gross>0.00 m²</strong></article>
                    <article><small>Usable area</small><strong data-rss-live-usable>0.00 m²</strong></article>
                    <article><small>Utilization</small><strong data-rss-live-utilization>0.0%</strong></article>
                    <article><small>Shading</small><strong data-rss-live-shading>0.0%</strong></article>
                    <article><small>Tilt</small><strong data-rss-live-tilt>0.0°</strong></article>
                    <article><small>Azimuth</small><strong data-rss-live-azimuth>0.0°</strong></article>
                </div>
                <div class="rss-utilization-track" style="margin-top: 14px;"><i data-rss-live-track style="width: 0%"></i></div>
                <p class="rss-surface-live-note" data-rss-live-note>Enter gross area and exclusions to calculate the usable installation area.</p>
            </section>

            <section class="rss-rail-scope">
                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l7 3v5c0 4.6-2.8 8-7 10-4.2-2-7-5.4-7-10V6l7-3Z"/><path d="M9 12h6"/></svg></span>
                <div><strong>Preliminary engineering record</strong><p>Surface data supports layout and readiness. It does not replace structural certification or final installation approval.</p></div>
            </section>
        </aside>
    </div>
</div>
