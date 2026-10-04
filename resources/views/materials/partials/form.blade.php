@php
    $selectedSystemTypes = old('system_types', $material->system_types ?? []);
    $selectedProjectScales = old('project_scales', $material->project_scales ?? []);
    $selectedRoofTypes = old('suitable_roof_types', $material->suitable_roof_types ?? []);
    $isCreate = ! $material->exists;
    $cancelUrl = $material->exists
        ? route('companies.materials.show', [$company, $material->id])
        : route('companies.materials.index', $company);
    $canonicalEngineeringClassCount = \App\Support\EngineeringProductMasterDictionary::count();
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

@if ($isCreate)
    <nav class="nxmci-intake-map nxmc-reveal" aria-label="Material intake map">
        <a href="#material-identity"><span>01</span><strong>Identity</strong><small>Catalog record</small></a>
        <a href="#material-applicability"><span>02</span><strong>Applicability</strong><small>Broad usage scope</small></a>
        <a href="#material-technical-input"><span>03</span><strong>Technical data</strong><small>Component-specific</small></a>
        <a href="#material-evidence-boundary"><span>04</span><strong>Evidence</strong><small>After creation</small></a>
        <a href="#material-governance-input"><span>05</span><strong>Governance</strong><small>Draft context</small></a>
    </nav>
@endif

<div class="nxmc-editor-grid">
    <aside class="nxmc-editor-rail nxmc-reveal">
        @if ($isCreate)
            <section class="nxmci-preview" aria-labelledby="nxmci-preview-title">
                <div class="nxmci-preview-visual" data-nxmci-preview-visual data-profile="DEFAULT" data-nxmci-image-base="{{ asset('images/material-classes') }}" aria-hidden="true">
                    <div class="nxmci-preview-stage-glow"></div>
                    <div class="nxmci-preview-orbit"></div>
                    <img class="nxmci-preview-class-image" data-nxmci-preview-image alt="" loading="eager" decoding="async">
                    <div class="nxmci-preview-artwork" data-nxmci-preview-artwork></div>
                </div>
                <div class="nxmci-preview-copy" aria-live="polite">
                    <div class="nxmci-preview-heading-row">
                        <span class="nxmc-panel-kicker">Live catalog identity</span>
                        <span class="nxmci-preview-profile" data-nxmci-preview-profile>Unclassified</span>
                    </div>
                    <h3 id="nxmci-preview-title" data-nxmci-preview-title>Unclassified material</h3>
                    <p data-nxmci-preview-copy>Select an engineering component class to load its technical requirements and class visual.</p>
                    <dl class="nxmci-preview-identity">
                        <div><dt>Manufacturer / Brand</dt><dd data-nxmci-preview-brand>Not recorded</dd></div>
                        <div><dt>Model</dt><dd data-nxmci-preview-model>Not recorded</dd></div>
                        <div><dt>SKU</dt><dd data-nxmci-preview-sku>Not recorded</dd></div>
                    </dl>
                    <div class="nxmci-preview-meta">
                        <span><small>Ownership</small><strong>Company catalog</strong></span>
                        <span><small>Initial state</small><strong>Draft · Needs Review</strong></span>
                    </div>
                </div>
            </section>

            <section class="nxmc-editor-progress nxmci-create-progress">
                <div class="nxmc-editor-progress-head">
                    <div>
                        <span class="nxmc-panel-kicker">Draft form completion</span>
                        <h3>Required to create</h3>
                    </div>
                    <strong class="nxmc-preview-score" data-nxmc-progress-score>0 / 5</strong>
                </div>
                <p>These five inputs are required to create the draft. Technical data and evidence are completed separately.</p>
                <div class="nxmc-editor-track" aria-hidden="true"><span data-nxmc-progress-fill></span></div>
                <div class="nxmci-required-list" data-nxmci-required-list>
                    <span data-nxmci-required-field="sku"><i></i>SKU</span>
                    <span data-nxmci-required-field="name"><i></i>Material name</span>
                    <span data-nxmci-required-field="material_category_id"><i></i>Component class</span>
                    <span data-nxmci-required-field="uom"><i></i>Unit of measure</span>
                    <span data-nxmci-required-field="default_scope_mode"><i></i>Catalog scope</span>
                </div>
                <p class="nxmci-missing-copy" data-nxmci-missing-copy>5 required inputs remain.</p>
            </section>

            <section class="nxmci-technical-inspector" aria-labelledby="nxmci-technical-inspector-title">
                <div class="nxmci-inspector-head">
                    <div>
                        <span class="nxmc-panel-kicker">Technical data</span>
                        <h3 id="nxmci-technical-inspector-title">Engineering data status</h3>
                    </div>
                    <span class="nxmci-inspector-state" data-nxmci-schema-rail-state>Select component</span>
                </div>
                <div class="nxmci-inspector-metrics">
                    <div><small>Required</small><strong data-nxmci-rail-required>0</strong></div>
                    <div><small>Recorded</small><strong data-nxmci-rail-recorded>0</strong></div>
                    <div><small>Missing</small><strong data-nxmci-rail-missing>0</strong></div>
                    <div data-nxmci-rail-metric="conditional"><small>Conditional</small><strong data-nxmci-rail-conditional>0</strong></div>
                </div>
                <p data-nxmci-schema-rail-copy>Select a component class to see the required engineering data and what is still missing.</p>
                <a href="#material-technical-input" class="nxmci-rail-link">Open technical intake <span aria-hidden="true">→</span></a>
            </section>

            <section class="nxmc-editor-context nxmci-rail-card nxmci-governance-rail">
                <span>Governance basis</span>
                <strong>Active does not mean approved.</strong>
                <p>New materials always start as Draft and Needs Review. Approval requires the applicable technical data and supporting evidence.</p>
                <div class="nxmci-governance-tags"><span>Draft</span><span>Needs Review</span><span>Evidence required</span></div>
            </section>

        @else
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
        @endif
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
                        <input id="sku" data-nxmci-identity-field="sku" class="nxmc-input @error('sku') is-invalid @enderror" name="sku" value="{{ old('sku', $material->sku) }}" autocomplete="off" required>
                        <span class="nxmc-field-hint">Stable company catalog code. Avoid temporary project names.</span>
                        @error('sku') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="name">Material name</label>
                        <input id="name" data-nxmci-identity-field="name" class="nxmc-input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $material->name) }}" required>
                        <span class="nxmc-field-hint">Human-readable catalog identity used in engineering and commercial records.</span>
                        @error('name') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="brand">Manufacturer / Brand</label>
                        <input id="brand" data-nxmci-identity-field="brand" class="nxmc-input @error('brand') is-invalid @enderror" name="brand" value="{{ old('brand', $material->brand) }}">
                        @error('brand') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="model">Model</label>
                        <input id="model" data-nxmci-identity-field="model" class="nxmc-input @error('model') is-invalid @enderror" name="model" value="{{ old('model', $material->model) }}">
                        @error('model') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field nxmc-form-span-2 nxmci-category-selector">
                        <div class="nxmci-category-label-row">
                            <label class="nxmc-label" for="material_category_id">Engineering component class</label>
                            <span class="nxmci-category-coverage">{{ $canonicalEngineeringClassCount }} exact classes</span>
                        </div>
                        <div class="nxmci-category-picker" data-nxmci-category-picker>
                            <button type="button" class="nxmci-category-trigger" data-nxmci-category-trigger aria-haspopup="listbox" aria-expanded="false" aria-controls="nxmci-category-options">
                                <span class="nxmci-category-trigger-copy">
                                    <strong data-nxmci-category-trigger-label>Select component class</strong>
                                    <small data-nxmci-category-trigger-meta>Choose from the engineering catalog</small>
                                </span>
                                <span class="nxmci-category-trigger-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="m5.5 7.5 4.5 4.5 4.5-4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            </button>
                            <div class="nxmci-category-menu" data-nxmci-category-menu hidden>
                                <div class="nxmci-category-menu-head">
                                    <span>Exact engineering classes</span>
                                    <small data-nxmci-category-menu-count>{{ $canonicalEngineeringClassCount }} available</small>
                                </div>
                                <label class="nxmci-category-search">
                                    <span class="nxmci-category-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="4.75" stroke="currentColor" stroke-width="1.45"/><path d="m12 12 3.7 3.7" stroke="currentColor" stroke-width="1.45" stroke-linecap="round"/></svg></span>
                                    <input type="search" class="nxmci-category-filter" data-nxmci-category-filter placeholder="Search module, inverter, breaker, transformer..." autocomplete="off" aria-label="Search exact engineering component classes" aria-controls="nxmci-category-options">
                                    <kbd>/</kbd>
                                </label>
                                <div id="nxmci-category-options" class="nxmci-category-options" data-nxmci-category-options role="listbox" aria-label="Engineering component classes"></div>
                            </div>
                        </div>
                        <select id="material_category_id" class="nxmc-select nxmci-category-native @error('material_category_id') is-invalid @enderror" name="material_category_id">
                            <option value="" data-code="DEFAULT" data-profile-code="DEFAULT">Select component class</option>
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
                                    data-parent-id="{{ $category->parent_id ?? '' }}"
                                    data-canonical-class="{{ \App\Support\EngineeringProductMasterDictionary::has((string) $category->code) ? '1' : '0' }}"
                                    @selected($isSelectedCategory)
                                    @disabled($isDomainHeading && ! $isSelectedCategory)
                                >{{ $isDomainHeading ? $category->name.' · select a component below' : '— '.$category->name }}</option>
                            @endforeach
                        </select>
                        <span class="nxmc-field-hint" data-nxmci-category-filter-status>Choose the exact component class. It determines the engineering fields shown below.</span>
                        @error('material_category_id') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="uom">Unit of measure</label>
                        <input id="uom" class="nxmc-input @error('uom') is-invalid @enderror" name="uom" value="{{ old('uom', $material->uom ?? 'pcs') }}" required>
                        <span class="nxmc-field-hint">Examples: pcs, set, m, roll, lot, package, or man-day. Use the procurement unit carried into BOM/BOQ.</span>
                        @error('uom') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="material_type">Material type</label>
                        <input id="material_type" class="nxmc-input @error('material_type') is-invalid @enderror" name="material_type" value="{{ old('material_type', $material->material_type) }}" placeholder="PV module, inverter, cable, protection">
                        <span class="nxmc-field-hint">Optional business grouping. The engineering component class above remains the technical authority.</span>
                        @error('material_type') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="cost_group">Cost group</label>
                        <input id="cost_group" class="nxmc-input @error('cost_group') is-invalid @enderror" name="cost_group" value="{{ old('cost_group', $material->cost_group) }}" placeholder="Material, Electrical BOS, Structural BOS">
                        <span class="nxmc-field-hint">Optional cost classification for BOM/BOQ and price-book organization.</span>
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
                        <p>Define where this material may be used. An active catalog record remains separate from approval for controlled BOQ selection.</p>
                    </div>
                </div>
                <span class="nxmc-section-state" data-nxmc-section-state="applicability">In progress</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field">
                        <label class="nxmc-label" for="default_scope_mode">Default scope mode</label>
                        <select id="default_scope_mode" class="nxmc-select @error('default_scope_mode') is-invalid @enderror" name="default_scope_mode" required @if($isCreate) data-nxmci-premium-select data-nxmci-select-label="Catalog scope" data-nxmci-select-meta="Choose the default BOM/BOQ role" data-nxmci-select-selected-meta="Default BOM/BOQ role" @endif>
                            @foreach ($scopeModes as $value => $label)
                                <option value="{{ $value }}" @selected(old('default_scope_mode', $material->default_scope_mode ?? 'catalog_candidate') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="nxmc-field-hint">Sets the default BOM/BOQ scope for this material; it does not approve the material.</span>
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
                        <label class="nxmc-label">{{ $isCreate ? 'System applicability' : 'System compatibility' }}</label>
                        <div class="nxmc-choices">
                            @foreach ($systemTypes as $value => $label)
                                <label class="nxmc-choice">
                                    <input type="checkbox" name="system_types[]" value="{{ $value }}" @checked(in_array($value, $selectedSystemTypes, true))>
                                    <span class="nxmc-choice-surface">
                                        <span class="nxmc-choice-copy"><strong>{{ $label }}</strong><small>Mark this material as suitable for {{ strtolower($label) }} system planning.</small></span>
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
                                        <span class="nxmc-choice-copy"><strong>{{ $label }}</strong><small>Mark this material as suitable for {{ strtolower($label) }} project planning.</small></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('project_scales') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label">{{ $isCreate ? 'Roof and site suitability' : 'Roof and site compatibility' }}</label>
                        <div class="nxmc-choices is-two">
                            @foreach ($roofTypes as $value => $label)
                                <label class="nxmc-choice">
                                    <input type="checkbox" name="suitable_roof_types[]" value="{{ $value }}" @checked(in_array($value, $selectedRoofTypes, true))>
                                    <span class="nxmc-choice-surface">
                                        <span class="nxmc-choice-copy"><strong>{{ $label }}</strong><small>Mark this material as suitable for this roof or site context.</small></span>
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
                        <h3>Component engineering data</h3>
                        <p>{{ $isCreate ? 'The selected component class controls the engineering fields and units below. Missing values stay visible and are never filled automatically.' : 'The selected component class controls the technical fields, units, validation rules, and review requirements for this material.' }}</p>
                    </div>
                </div>
                <span class="nxmc-section-state" data-nxmc-section-state="technical">In progress</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-category-context nxmc-profile-context">
                    <div>
                        <div class="nxmci-schema-authority-row">
                            <span>Component specification</span>
                            <em data-nxmci-schema-authority>{{ ! empty($initialSpecificationSchema['master_reference']) ? 'Engineering component class' : 'Catalog requirements' }}</em>
                        </div>
                        <strong data-nxmc-category-title>{{ $initialSpecificationSchema['title'] ?? 'General material technical profile' }}</strong>
                        <p data-nxmc-category-copy>{{ $initialSpecificationSchema['description'] ?? 'Select an engineering component class to load its technical requirements.' }}</p>
                    </div>
                    <div class="nxmc-profile-meter" aria-label="Category specification requirements">
                        <div><small>Required</small><strong data-nxmc-profile-spec-count>{{ number_format($initialSpecificationSchema['required_count'] ?? 0) }}</strong></div>
                        <div data-nxmci-profile-metric="conditional"><small>Conditional</small><strong data-nxmc-profile-conditional-count>{{ number_format($initialSpecificationSchema['conditional_count'] ?? 0) }}</strong></div>
                        <div data-nxmci-profile-metric="optional"><small>Optional</small><strong data-nxmc-profile-optional-count>{{ number_format($initialSpecificationSchema['optional_count'] ?? 0) }}</strong></div>
                        <div><small>{{ $isCreate ? 'Recorded' : 'Complete' }}</small><strong data-nxmc-profile-complete-count>0</strong></div>
                    </div>
                </div>

                <div class="nxmc-profile-requirements" data-nxmc-profile-requirements>
                    <div><span>{{ $isCreate ? 'Engineering fields' : 'Required engineering data' }}</span><p data-nxmc-profile-specs>Choose a component class to load the applicable engineering fields and units.</p></div>
                    <div><span>Evidence basis</span><p data-nxmc-profile-evidence>Certificates and documents remain separate evidence for catalog review.</p></div>
                </div>

                <div class="nxspec-commandbar">
                    <div>
                        <span>Engineering data checklist</span>
                        <strong data-nxmc-schema-status>Select a component class</strong>
                        <p>{{ $isCreate ? 'Record manufacturer-backed values as they become available. Draft creation remains separate from approval.' : 'Required engineering data supports review but does not block saving a Draft material record.' }}</p>
                    </div>
                    <div class="nxspec-progress" aria-label="Required specification completion">
                        <span><i data-nxmc-spec-progress-fill></i></span>
                        <strong data-nxmc-spec-progress-copy>0 of 0 recorded</strong>
                    </div>
                </div>
                <nav class="nxmci-spec-group-nav" data-nxmci-spec-group-nav aria-label="Technical specification groups" hidden></nav>

                <div class="nxspec-groups" data-nxmc-dynamic-specifications>
                    <div class="nxspec-empty">
                        <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                        <strong>Select an engineering component class.</strong>
                        <p>The applicable engineering fields and units will load for the selected component class.</p>
                    </div>
                </div>

                <noscript>
                    <div class="nxmc-alert" role="alert" style="margin-top: 10px;">
                        <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
                        <div><strong>Interactive technical fields could not be loaded.</strong><p>Refresh the page. If the problem continues, contact your administrator.</p></div>
                    </div>
                </noscript>
            </div>
        </section>

        @if ($isCreate)
            <section id="material-evidence-boundary" class="nxmc-form-section nxmci-evidence-boundary nxmc-reveal">
                <header class="nxmc-form-section-head">
                    <div class="nxmc-form-section-title">
                        <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6M9 16h6"/></svg></span>
                        <div>
                            <h3>Evidence starts after creation</h3>
                            <p>After the draft is created, attach datasheets, certificates, and supporting documents. The class visual is assigned automatically from the selected engineering component class.</p>
                        </div>
                    </div>
                    <span class="nxmc-section-state is-optional">Next step</span>
                </header>
                <div class="nxmc-form-section-body">
                    <div class="nxmci-evidence-grid">
                        <div><span>Datasheet</span><strong>Attach after creation</strong><p>Values taken from a datasheet remain subject to review before approval.</p></div>
                        <div><span>Certificates</span><strong>Attach applicable evidence</strong><p>Certificate requirements follow the selected engineering component class.</p></div>
                        <div><span>Supporting documents</span><strong>Keep the record traceable</strong><p>Installation manuals, drawings, and supporting references remain attached to the material record.</p></div>
                        <div><span>Class visual</span><strong>Engineering component image</strong><p>The preview follows the selected engineering component class.</p></div>
                    </div>
                </div>
            </section>
        @endif

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
                        <textarea id="technical_notes" class="nxmc-textarea @error('technical_notes') is-invalid @enderror" name="technical_notes" placeholder="{{ $isCreate ? 'Record engineering constraints, applicability notes, installation limits, or vendor evidence requirements.' : 'Record engineering constraints, compatibility notes, installation limits, or vendor evidence requirements.' }}">{{ old('technical_notes', $material->technical_notes) }}</textarea>
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
