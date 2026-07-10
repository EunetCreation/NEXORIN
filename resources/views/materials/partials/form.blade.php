@php
    $selectedSystemTypes = old('system_types', $material->system_types ?? []);
    $selectedProjectScales = old('project_scales', $material->project_scales ?? []);
@endphp

<div class="ui-form-section">
    <div class="ui-form-section-heading">
        <div>
            <h3>Material Identity</h3>
            <p>Use stable SKU, brand, model, category, and unit values so the item can later be connected to BOQ selection and price books.</p>
        </div>
    </div>

    <div class="ui-form-grid">
        <div class="ui-field">
            <label class="ui-label" for="sku">SKU</label>
            <input id="sku" class="ui-input @error('sku') is-invalid @enderror" name="sku" value="{{ old('sku', $material->sku) }}" required>
            @error('sku') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="name">Material Name</label>
            <input id="name" class="ui-input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $material->name) }}" required>
            @error('name') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="brand">Brand</label>
            <input id="brand" class="ui-input @error('brand') is-invalid @enderror" name="brand" value="{{ old('brand', $material->brand) }}">
            @error('brand') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="model">Model</label>
            <input id="model" class="ui-input @error('model') is-invalid @enderror" name="model" value="{{ old('model', $material->model) }}">
            @error('model') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="material_category_id">Category</label>
            <select id="material_category_id" class="ui-select @error('material_category_id') is-invalid @enderror" name="material_category_id">
                <option value="">Uncategorized</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('material_category_id', $material->material_category_id) === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('material_category_id') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="uom">Unit</label>
            <input id="uom" class="ui-input @error('uom') is-invalid @enderror" name="uom" value="{{ old('uom', $material->uom ?? 'pcs') }}" required>
            @error('uom') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="material_type">Material Type</label>
            <input id="material_type" class="ui-input @error('material_type') is-invalid @enderror" name="material_type" value="{{ old('material_type', $material->material_type) }}" placeholder="PV module, inverter, cable, protection">
            @error('material_type') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="cost_group">Cost Group</label>
            <input id="cost_group" class="ui-input @error('cost_group') is-invalid @enderror" name="cost_group" value="{{ old('cost_group', $material->cost_group) }}" placeholder="Material, Electrical BOS, Structural BOS">
            @error('cost_group') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="ui-form-section">
    <div class="ui-form-section-heading">
        <div>
            <h3>Suitability and Scope</h3>
            <p>Define where this item can appear and how it should behave before it is selected into a project BOQ.</p>
        </div>
    </div>

    <div class="ui-form-grid">
        <div class="ui-field">
            <label class="ui-label" for="default_scope_mode">Default Scope Mode</label>
            <select id="default_scope_mode" class="ui-select @error('default_scope_mode') is-invalid @enderror" name="default_scope_mode" required>
                @foreach ($scopeModes as $value => $label)
                    <option value="{{ $value }}" @selected(old('default_scope_mode', $material->default_scope_mode ?? 'catalog_candidate') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('default_scope_mode') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <label class="ui-switch-card">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $material->is_active ?? true))>
            <span class="ui-switch-control"></span>
            <span class="ui-switch-copy">
                <strong>Active material</strong>
                <small>Inactive items remain visible for audit but should not be selected for new BOQ work.</small>
            </span>
        </label>

        <div class="ui-field ui-form-span-2">
            <label class="ui-label">System Compatibility</label>
            <div class="ui-form-grid">
                @foreach ($systemTypes as $value => $label)
                    <label class="ui-switch-card">
                        <input type="checkbox" name="system_types[]" value="{{ $value }}" @checked(in_array($value, $selectedSystemTypes, true))>
                        <span class="ui-switch-control"></span>
                        <span class="ui-switch-copy"><strong>{{ $label }}</strong><small>Allow this item for {{ strtolower($label) }} projects.</small></span>
                    </label>
                @endforeach
            </div>
            @error('system_types') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field ui-form-span-2">
            <label class="ui-label">Project Scale</label>
            <div class="ui-form-grid">
                @foreach ($projectScales as $value => $label)
                    <label class="ui-switch-card">
                        <input type="checkbox" name="project_scales[]" value="{{ $value }}" @checked(in_array($value, $selectedProjectScales, true))>
                        <span class="ui-switch-control"></span>
                        <span class="ui-switch-copy"><strong>{{ $label }}</strong><small>Suitable for {{ strtolower($label) }} project templates.</small></span>
                    </label>
                @endforeach
            </div>
            @error('project_scales') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="ui-form-section">
    <div class="ui-form-section-heading">
        <div>
            <h3>Technical Attributes</h3>
            <p>Core fields used later for compatibility checks, BOQ selection, and catalog-based pricing.</p>
        </div>
    </div>

    <div class="ui-form-grid">
        <div class="ui-field">
            <label class="ui-label" for="nominal_power_wp">Nominal Power (Wp)</label>
            <input id="nominal_power_wp" class="ui-input" type="number" step="0.01" min="0" name="nominal_power_wp" value="{{ old('nominal_power_wp', $material->nominal_power_wp) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="rated_power_kw">Rated Power (kW)</label>
            <input id="rated_power_kw" class="ui-input" type="number" step="0.001" min="0" name="rated_power_kw" value="{{ old('rated_power_kw', $material->rated_power_kw) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="nominal_capacity_kwh">Capacity (kWh)</label>
            <input id="nominal_capacity_kwh" class="ui-input" type="number" step="0.001" min="0" name="nominal_capacity_kwh" value="{{ old('nominal_capacity_kwh', $material->nominal_capacity_kwh) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="voltage_v">Voltage (V)</label>
            <input id="voltage_v" class="ui-input" type="number" step="0.01" min="0" name="voltage_v" value="{{ old('voltage_v', $material->voltage_v) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="current_a">Current (A)</label>
            <input id="current_a" class="ui-input" type="number" step="0.01" min="0" name="current_a" value="{{ old('current_a', $material->current_a) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="electrical_phase">Electrical Phase</label>
            <input id="electrical_phase" class="ui-input" name="electrical_phase" value="{{ old('electrical_phase', $material->electrical_phase) }}" placeholder="Single Phase / Three Phase">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="ip_rating">IP Rating</label>
            <input id="ip_rating" class="ui-input" name="ip_rating" value="{{ old('ip_rating', $material->ip_rating) }}" placeholder="IP65">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="warranty_years">Warranty (Years)</label>
            <input id="warranty_years" class="ui-input" type="number" min="0" max="80" name="warranty_years" value="{{ old('warranty_years', $material->warranty_years) }}">
        </div>
        <div class="ui-field ui-form-span-2">
            <label class="ui-label" for="description">Description</label>
            <textarea id="description" class="ui-textarea" name="description">{{ old('description', $material->description) }}</textarea>
        </div>
        <div class="ui-field ui-form-span-2">
            <label class="ui-label" for="technical_notes">Technical Notes</label>
            <textarea id="technical_notes" class="ui-textarea" name="technical_notes">{{ old('technical_notes', $material->technical_notes) }}</textarea>
        </div>
    </div>
</div>

<div class="ui-form-actions">
    <a href="{{ $material->exists ? route('companies.materials.show', [$company, $material->id]) : route('companies.materials.index', $company) }}" class="ui-button ui-button-secondary">Cancel</a>
    <button type="submit" class="ui-button ui-button-primary">{{ $submitLabel }}</button>
</div>
