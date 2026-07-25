@php
    $selectedSystemTypes = old('system_types', $material->system_types ?? []);
    $selectedProjectScales = old('project_scales', $material->project_scales ?? []);
    $selectedRoofTypes = old('suitable_roof_types', $material->suitable_roof_types ?? []);
    $cancelUrl = $material->exists
        ? route('companies.materials.show', [$company, $material->id])
        : route('companies.materials.index', $company);
@endphp

@php
    $resolvedTechnicalValues = old('technical_specs', $technicalSpecValues ?? []);
    $technicalSpecErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'technical_specs.'))
        ->mapWithKeys(fn ($messages, $key) => [str_replace('technical_specs.', '', $key) => $messages]);
@endphp
<script type="application/json" data-nxmc-specification-schema-payload>@json($specificationSchemas ?? [])</script>
<script type="application/json" data-nxmc-specification-value-payload>@json($resolvedTechnicalValues)</script>
<script type="application/json" data-nxmc-specification-error-payload>@json($technicalSpecErrors)</script>

@if ($errors->any())
    <div class="nxmc-alert nxmc-reveal" role="alert" style="margin-bottom: 10px;">
        <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
        <div>
            <strong>Material record requires correction.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif

<div class="nxmc-editor-grid">
    <aside class="nxmc-editor-rail nxmc-reveal">
        <div class="nxmc-editor-progress">
            <div class="nxmc-editor-progress-head">
                <div>
                    <span class="nxmc-panel-kicker">Draft completion preview</span>
                    <h3>Form completion</h3>
                </div>
                <strong class="nxmc-preview-score" data-nxmc-progress-score>0%</strong>
            </div>
            <p>This score tracks required form inputs only. Final approval readiness is calculated separately from datasheet, certificate, document, price, and governance evidence.</p>
            <div class="nxmc-editor-track" aria-hidden="true"><span data-nxmc-progress-fill></span></div>
            <nav class="nxmc-editor-steps" aria-label="Material form sections">
                <a href="#material-identity" class="nxmc-editor-step is-active">
                    <span class="nxmc-editor-step-index">01</span><strong>Identity</strong><span class="nxmc-editor-step-state" data-nxmc-section-state="identity">In progress</span>
                </a>
                <a href="#material-applicability" class="nxmc-editor-step">
                    <span class="nxmc-editor-step-index">02</span><strong>Applicability</strong><span class="nxmc-editor-step-state" data-nxmc-section-state="applicability">In progress</span>
                </a>
                <a href="#material-technical-input" class="nxmc-editor-step">
                    <span class="nxmc-editor-step-index">03</span><strong>Technical</strong><span class="nxmc-editor-step-state" data-nxmc-section-state="technical">In progress</span>
                </a>
                <a href="#material-governance-input" class="nxmc-editor-step">
                    <span class="nxmc-editor-step-index">04</span><strong>Governance</strong><span class="nxmc-editor-step-state" data-nxmc-section-state="governance">Optional</span>
                </a>
            </nav>
        </div>

        <div class="nxmc-editor-context">
            <span>Publication control</span>
            <strong>Active is not the same as approved.</strong>
            <p>An active record can remain Draft, Needs Review, or Blocked from BOQ selection until evidence requirements are satisfied.</p>
        </div>
    </aside>

    <div class="nxmc-editor-body">
        <section id="material-identity" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                    <div>
                        <h3>Material identity</h3>
                        <p>Use stable SKU, name, brand, model, category, and unit values so downstream engineering and price-book relationships remain durable.</p>
                    </div>
                </div>
                <span class="nxmc-section-state" data-nxmc-section-state="identity">In progress</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field">
                        <label class="nxmc-label" for="sku">SKU</label>
                        <input id="sku" class="nxmc-input @error('sku') is-invalid @enderror" name="sku" value="{{ old('sku', $material->sku) }}" autocomplete="off" required>
                        <span class="nxmc-field-hint">Stable company catalog code. Avoid temporary project names.</span>
                        @error('sku') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="name">Material name</label>
                        <input id="name" class="nxmc-input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $material->name) }}" required>
                        <span class="nxmc-field-hint">Human-readable catalog identity used in engineering and commercial records.</span>
                        @error('name') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="brand">Brand</label>
                        <input id="brand" class="nxmc-input @error('brand') is-invalid @enderror" name="brand" value="{{ old('brand', $material->brand) }}">
                        @error('brand') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="model">Model</label>
                        <input id="model" class="nxmc-input @error('model') is-invalid @enderror" name="model" value="{{ old('model', $material->model) }}">
                        @error('model') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="material_category_id">Professional component subcategory</label>
                        <select id="material_category_id" class="nxmc-select @error('material_category_id') is-invalid @enderror" name="material_category_id">
                            <option value="" data-code="DEFAULT" data-profile-code="DEFAULT">Uncategorized</option>
                            @foreach ($categories as $category)
                                @php
                                    $profileCode = \App\Support\ProfessionalCatalogProfile::rootCodeFor($category);
                                    $isSelectedCategory = (string) old('material_category_id', $material->material_category_id) === (string) $category->id;
                                    $isDomainHeading = $category->parent_id === null;
                                @endphp
                                <option
                                    value="{{ $category->id }}"
                                    data-code="{{ $category->code ?: 'DEFAULT' }}"
                                    data-profile-code="{{ $profileCode ?: 'DEFAULT' }}"
                                    @selected($isSelectedCategory)
                                    @disabled($isDomainHeading && ! $isSelectedCategory)
                                >{{ $isDomainHeading ? $category->name.' · select a component below' : '— '.$category->name }}</option>
                            @endforeach
                        </select>
                        <span class="nxmc-field-hint">Choose the exact component type. NEXORIN loads its dedicated technical schema, units, validation, and evidence requirements.</span>
                        @error('material_category_id') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="uom">Unit of measure</label>
                        <input id="uom" class="nxmc-input @error('uom') is-invalid @enderror" name="uom" value="{{ old('uom', $material->uom ?? 'pcs') }}" required>
                        <span class="nxmc-field-hint">Examples: pcs, set, m, roll, lot, package, man-day.</span>
                        @error('uom') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="material_type">Material type</label>
                        <input id="material_type" class="nxmc-input @error('material_type') is-invalid @enderror" name="material_type" value="{{ old('material_type', $material->material_type) }}" placeholder="PV module, inverter, cable, protection">
                        @error('material_type') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="cost_group">Cost group</label>
                        <input id="cost_group" class="nxmc-input @error('cost_group') is-invalid @enderror" name="cost_group" value="{{ old('cost_group', $material->cost_group) }}" placeholder="Material, Electrical BOS, Structural BOS">
                        @error('cost_group') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <section id="material-applicability" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/></svg></span>
                    <div>
                        <h3>Applicability and scope</h3>
                        <p>Define where the item may appear and distinguish active record availability from approval and BOQ selection authority.</p>
                    </div>
                </div>
                <span class="nxmc-section-state" data-nxmc-section-state="applicability">In progress</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field">
                        <label class="nxmc-label" for="default_scope_mode">Default scope mode</label>
                        <select id="default_scope_mode" class="nxmc-select @error('default_scope_mode') is-invalid @enderror" name="default_scope_mode" required>
                            @foreach ($scopeModes as $value => $label)
                                <option value="{{ $value }}" @selected(old('default_scope_mode', $material->default_scope_mode ?? 'catalog_candidate') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="nxmc-field-hint">Controls the default BOM scope behavior, not approval status.</span>
                        @error('default_scope_mode') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-toggle-row">
                        <span class="nxmc-toggle-copy">
                            <strong>Active record</strong>
                            <small>Active records remain visible to catalog workflows. BOQ selection still depends on catalog approval.</small>
                        </span>
                        <label class="nxmc-toggle">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $material->is_active ?? true))>
                            <span aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label">System compatibility</label>
                        <div class="nxmc-choices">
                            @foreach ($systemTypes as $value => $label)
                                <label class="nxmc-choice">
                                    <input type="checkbox" name="system_types[]" value="{{ $value }}" @checked(in_array($value, $selectedSystemTypes, true))>
                                    <span class="nxmc-choice-surface">
                                        <span class="nxmc-choice-copy"><strong>{{ $label }}</strong><small>Allow this item in {{ strtolower($label) }} project templates.</small></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('system_types') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label">Project scale</label>
                        <div class="nxmc-choices is-two">
                            @foreach ($projectScales as $value => $label)
                                <label class="nxmc-choice">
                                    <input type="checkbox" name="project_scales[]" value="{{ $value }}" @checked(in_array($value, $selectedProjectScales, true))>
                                    <span class="nxmc-choice-surface">
                                        <span class="nxmc-choice-copy"><strong>{{ $label }}</strong><small>Suitable for {{ strtolower($label) }} planning and BOQ templates.</small></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('project_scales') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label">Roof and site compatibility</label>
                        <div class="nxmc-choices is-two">
                            @foreach ($roofTypes as $value => $label)
                                <label class="nxmc-choice">
                                    <input type="checkbox" name="suitable_roof_types[]" value="{{ $value }}" @checked(in_array($value, $selectedRoofTypes, true))>
                                    <span class="nxmc-choice-surface">
                                        <span class="nxmc-choice-copy"><strong>{{ $label }}</strong><small>Allow project matching when the roof and site survey uses this context.</small></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <span class="nxmc-field-hint">Optional. Leave every option clear when the component is not constrained by roof or site type.</span>
                        @error('suitable_roof_types') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <section id="material-technical-input" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8M10 19V4M16 19v-7M22 19H2"/><path d="m7 12 3-3 3 2 4-5"/></svg></span>
                    <div>
                        <h3>Category-specific technical intake</h3>
                        <p>The selected professional subcategory controls every technical field, unit, validation rule, readiness requirement, and future parser or project-compatibility key.</p>
                    </div>
                </div>
                <span class="nxmc-section-state" data-nxmc-section-state="technical">In progress</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-category-context nxmc-profile-context">
                    <div>
                        <span>Professional specification schema</span>
                        <strong data-nxmc-category-title>{{ $initialSpecificationSchema['title'] ?? 'General material technical profile' }}</strong>
                        <p data-nxmc-category-copy>{{ $initialSpecificationSchema['description'] ?? 'Select a professional subcategory to open its complete technical schema.' }}</p>
                    </div>
                    <div class="nxmc-profile-meter" aria-label="Category specification requirements">
                        <div><small>Required</small><strong data-nxmc-profile-spec-count>{{ number_format($initialSpecificationSchema['required_count'] ?? 0) }}</strong></div>
                        <div><small>Subtype</small><strong data-nxmc-profile-conditional-count>{{ number_format($initialSpecificationSchema['conditional_count'] ?? 0) }}</strong></div>
                        <div><small>Optional</small><strong data-nxmc-profile-optional-count>{{ number_format($initialSpecificationSchema['optional_count'] ?? 0) }}</strong></div>
                        <div><small>Complete</small><strong data-nxmc-profile-complete-count>0</strong></div>
                    </div>
                </div>

                <div class="nxmc-profile-requirements" data-nxmc-profile-requirements>
                    <div><span>Required technical evidence</span><p data-nxmc-profile-specs>Choose a subcategory to load exact engineering fields and units.</p></div>
                    <div><span>Compliance evidence</span><p data-nxmc-profile-evidence>Certificate and document requirements remain aligned with the professional domain.</p></div>
                </div>

                <div class="nxspec-commandbar">
                    <div>
                        <span>Schema-driven input</span>
                        <strong data-nxmc-schema-status>Waiting for professional subcategory</strong>
                        <p>Required fields guide approval readiness but do not block saving a Draft material record.</p>
                    </div>
                    <div class="nxspec-progress" aria-label="Required specification completion">
                        <span><i data-nxmc-spec-progress-fill></i></span>
                        <strong data-nxmc-spec-progress-copy>0 / 0 required</strong>
                    </div>
                </div>

                <div class="nxspec-groups" data-nxmc-dynamic-specifications>
                    <div class="nxspec-empty">
                        <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                        <strong>Select a professional subcategory.</strong>
                        <p>NEXORIN will load the exact component schema for electrical, mechanical, environmental, communication, safety, compliance, and lifecycle data.</p>
                    </div>
                </div>

                <noscript>
                    <div class="nxmc-alert" role="alert" style="margin-top: 10px;">
                        <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
                        <div><strong>JavaScript is required for category-specific technical intake.</strong><p>Enable JavaScript to load and validate the professional component schema.</p></div>
                    </div>
                </noscript>
            </div>
        </section>

        <section id="material-governance-input" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="M9 12h6"/></svg></span>
                    <div>
                        <h3>Description and governance notes</h3>
                        <p>Record the material function, engineering limits, selection guidance, and review notes that must remain visible beyond a single project.</p>
                    </div>
                </div>
                <span class="nxmc-section-state" data-nxmc-section-state="governance">Optional</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label" for="description">Catalog description</label>
                        <textarea id="description" class="nxmc-textarea @error('description') is-invalid @enderror" name="description" placeholder="Describe the material function and intended use in the project workflow.">{{ old('description', $material->description) }}</textarea>
                        @error('description') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label" for="technical_notes">Technical and selection notes</label>
                        <textarea id="technical_notes" class="nxmc-textarea @error('technical_notes') is-invalid @enderror" name="technical_notes" placeholder="Record engineering constraints, compatibility notes, installation limits, or vendor evidence requirements.">{{ old('technical_notes', $material->technical_notes) }}</textarea>
                        @error('technical_notes') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <footer class="nxmc-editor-actionbar nxmc-reveal">
            <div class="nxmc-action-status" data-nxmc-action-status>
                <strong><span class="nxmc-dirty-dot" aria-hidden="true"></span>Controlled material record</strong>
                <small data-nxmc-action-copy>No unsaved changes detected.</small>
            </div>
            <div class="nxmc-action-buttons">
                <a href="{{ $cancelUrl }}" class="nxmc-button nxmc-button-secondary" data-nxmc-navigate="{{ $material->exists ? 'open' : 'directory' }}" @if($material->exists) data-nxmc-material="{{ $material->name }}" data-nxmc-sku="{{ $material->sku }}" @endif>Cancel</a>
                <button type="submit" class="nxmc-button nxmc-button-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
                    {{ $submitLabel }}
                </button>
            </div>
        </footer>
    </div>
</div>
