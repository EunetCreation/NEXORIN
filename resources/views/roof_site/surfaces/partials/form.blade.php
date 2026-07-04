@push('styles')
<style>
    .surface-form-shell { width: min(980px, 100%); margin: 0 auto; }
    .surface-form-card { padding: 28px; border: 1px solid var(--slate-200); border-radius: 24px; background: rgba(255,255,255,.96); box-shadow: var(--shadow-sm); }
    .surface-form-card h2 { margin: 0 0 8px; font: 700 1.8rem/1.2 "Space Grotesk", sans-serif; }
    .surface-form-card > p { margin: 0 0 24px; color: var(--slate-600); line-height: 1.6; }
    .surface-form-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 18px; }
    .surface-field { min-width: 0; display: grid; gap: 8px; }
    .surface-field.span-2 { grid-column: 1/-1; }
    .surface-field label { color: var(--slate-700); font-size: .86rem; font-weight: 700; }
    .surface-field input, .surface-field select, .surface-field textarea { width: 100%; min-width: 0; padding: 13px 14px; border: 1px solid var(--slate-300); border-radius: 14px; background: #fff; }
    .surface-field textarea { min-height: 130px; resize: vertical; }
    .surface-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
    .surface-help { color: var(--slate-500); font-size: .82rem; line-height: 1.5; }
    @media(max-width:680px){.surface-form-grid{grid-template-columns:1fr}.surface-field.span-2{grid-column:auto}}
</style>
@endpush

<div class="surface-form-shell">
    <section class="surface-form-card">
        <h2>{{ $heading }}</h2>
        <p>{{ $description }}</p>

        <form method="POST" action="{{ $action }}">
            @csrf
            @if($method !== 'POST') @method($method) @endif

            <div class="surface-form-grid">
                <div class="surface-field span-2">
                    <label for="name">Surface name</label>
                    <input id="name" name="name" type="text" maxlength="120" required value="{{ old('name',$surface->name) }}" placeholder="Example: Main north roof">
                </div>
                <div class="surface-field">
                    <label for="roof_type">Roof type</label>
                    <select id="roof_type" name="roof_type" required>
                        @foreach(\App\Models\RoofSurface::ROOF_TYPES as $value)
                            <option value="{{ $value }}" @selected(old('roof_type',$surface->roof_type)===$value)>{{ ucwords(str_replace('_',' ',$value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="surface-field">
                    <label for="roof_material">Roof material</label>
                    <select id="roof_material" name="roof_material" required>
                        @foreach(\App\Models\RoofSurface::ROOF_MATERIALS as $value)
                            <option value="{{ $value }}" @selected(old('roof_material',$surface->roof_material)===$value)>{{ ucwords(str_replace('_',' ',$value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="surface-field">
                    <label for="roof_condition">Roof condition</label>
                    <select id="roof_condition" name="roof_condition" required>
                        @foreach(\App\Models\RoofSurface::ROOF_CONDITIONS as $value)
                            <option value="{{ $value }}" @selected(old('roof_condition',$surface->roof_condition)===$value)>{{ ucfirst($value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="surface-field">
                    <label for="gross_area_m2">Gross area (m²)</label>
                    <input id="gross_area_m2" name="gross_area_m2" type="number" min="0.01" step="0.01" required value="{{ old('gross_area_m2',$surface->gross_area_m2) }}">
                </div>
                <div class="surface-field">
                    <label for="setback_area_m2">Setback area (m²)</label>
                    <input id="setback_area_m2" name="setback_area_m2" type="number" min="0" step="0.01" value="{{ old('setback_area_m2',$surface->setback_area_m2 ?? 0) }}">
                </div>
                <div class="surface-field">
                    <label for="obstruction_area_m2">Obstruction area (m²)</label>
                    <input id="obstruction_area_m2" name="obstruction_area_m2" type="number" min="0" step="0.01" value="{{ old('obstruction_area_m2',$surface->obstruction_area_m2 ?? 0) }}">
                </div>
                <div class="surface-field">
                    <label for="walkway_area_m2">Walkway area (m²)</label>
                    <input id="walkway_area_m2" name="walkway_area_m2" type="number" min="0" step="0.01" value="{{ old('walkway_area_m2',$surface->walkway_area_m2 ?? 0) }}">
                </div>
                <div class="surface-field">
                    <label for="tilt_deg">Tilt (degrees)</label>
                    <input id="tilt_deg" name="tilt_deg" type="number" min="0" max="90" step="0.01" required value="{{ old('tilt_deg',$surface->tilt_deg) }}">
                </div>
                <div class="surface-field">
                    <label for="azimuth_deg">Azimuth (degrees)</label>
                    <input id="azimuth_deg" name="azimuth_deg" type="number" min="0" max="360" step="0.01" required value="{{ old('azimuth_deg',$surface->azimuth_deg) }}">
                    <div class="surface-help">Convention: 0° north, 90° east, 180° south, and 270° west.</div>
                </div>
                <div class="surface-field">
                    <label for="shading_percent">Shading (%)</label>
                    <input id="shading_percent" name="shading_percent" type="number" min="0" max="100" step="0.01" required value="{{ old('shading_percent',$surface->shading_percent ?? 0) }}">
                </div>
                <div class="surface-field">
                    <label for="max_additional_load_kg_m2">Recorded load allowance (kg/m²)</label>
                    <input id="max_additional_load_kg_m2" name="max_additional_load_kg_m2" type="number" min="0" step="0.01" value="{{ old('max_additional_load_kg_m2',$surface->max_additional_load_kg_m2) }}">
                    <div class="surface-help">Leave blank when no structural data is available.</div>
                </div>
                <div class="surface-field">
                    <label for="sort_order">Display order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order',$surface->sort_order ?? 0) }}">
                </div>
                <div class="surface-field span-2">
                    <label for="notes">Surface notes</label>
                    <textarea id="notes" name="notes">{{ old('notes',$surface->notes) }}</textarea>
                </div>
            </div>

            <div class="surface-actions">
                <button class="ui-button ui-button-primary" type="submit">{{ $submitLabel }}</button>
                <a class="ui-button ui-button-secondary" href="{{ route('companies.roof-site.show',[$company,$project]) }}">Cancel</a>
            </div>
        </form>
    </section>
</div>
