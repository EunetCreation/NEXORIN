@extends('layouts.app')

@section('title', 'Edit Material · '.$material->name)
@section('page-eyebrow', 'Material Catalog')
@section('page-heading', 'Edit Material')
@section('force-theme', 'dark')
@section('body-class', 'page-material-edit-v6')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=63a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-material-catalog-refinement.css') }}?v=1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-material-edit.css') }}?v=22a" rel="stylesheet">
@endpush

@php
    $selectedSystemTypes = array_values((array) old('system_types', $material->system_types ?? []));
    $selectedProjectScales = array_values((array) old('project_scales', $material->project_scales ?? []));
    $selectedRoofTypes = array_values((array) old('suitable_roof_types', $material->suitable_roof_types ?? []));
    $resolvedTechnicalValues = old('technical_specs', $technicalSpecValues ?? []);
    $technicalSpecErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'technical_specs.'))
        ->mapWithKeys(fn ($messages, $key) => [str_replace('technical_specs.', '', $key) => $messages]);

    $canonicalEngineeringClassCount = \App\Support\EngineeringProductMasterDictionary::count();
    $initialSchema = $initialSpecificationSchema ?? \App\Support\ProfessionalSpecificationSchema::for($material);
    $readiness = $catalogReadiness ?? app(\App\Services\Catalog\MaterialCatalogCompletenessEvaluator::class)->evaluate($material);
    $readinessScore = (int) ($readiness['score'] ?? 0);
    $readinessStatus = (string) ($readiness['status'] ?? 'incomplete');
    $readinessLabel = match ($readinessStatus) {
        'approval_ready' => 'Approval Ready',
        'review_required' => 'Review Required',
        default => 'Incomplete Basis',
    };
    $readinessTone = match ($readinessStatus) {
        'approval_ready' => 'is-green',
        'review_required' => 'is-amber',
        default => 'is-red',
    };

    $catalogStatusRaw = (string) ($material->catalog_status ?? 'draft');
    $catalogStatusLabel = match ($catalogStatusRaw) {
        'approved' => 'Approved',
        'needs_review' => 'Needs Review',
        'rejected' => 'Rejected',
        default => 'Draft',
    };
    $catalogStatusTone = match ($catalogStatusRaw) {
        'approved' => 'is-green',
        'needs_review' => 'is-amber',
        'rejected' => 'is-red',
        default => 'is-cyan',
    };

    $complianceStatusRaw = (string) ($material->compliance_status ?? 'needs_review');
    $complianceStatusLabel = match ($complianceStatusRaw) {
        'compliant' => 'Compliant',
        default => 'Needs Review',
    };
    $complianceStatusTone = $complianceStatusRaw === 'compliant' ? 'is-green' : 'is-amber';

    $isActiveRecord = (bool) ($material->is_active ?? true);
    $isApproved = $catalogStatusRaw === 'approved';
    $boqEligible = $isActiveRecord && $isApproved;
    $humanizeUiToken = static fn (?string $val, string $fallback = 'Solar Engineering'): string => filled($val)
        ? (string) str((string) $val)->replace(['_', '.'], ' ')->squish()
        : $fallback;

    $activeDatasheets = $material->datasheets ? $material->datasheets->where('is_active', true)->values() : collect();
    $parsedDatasheets = $activeDatasheets->filter(fn ($d) => in_array($d->extraction_status, ['parsed', 'reviewed', 'applied'], true))->values();
    $activeApplications = $activeDatasheets->flatMap(fn ($d) => $d->applications ?? collect())->where('status', 'applied')->values();
    $appliedSpecKeys = $activeApplications->flatMap(fn ($app) => array_keys((array) ($app->after_specs ?? [])))->unique()->values()->all();

    $activeCertificates = $material->certificates ? $material->certificates->where('is_active', true)->values() : collect();
    $verifiedCertificates = $activeCertificates->filter(fn ($c) => in_array($c->verification_status, ['verified', 'accepted'], true) && ! ($c->expires_at?->isPast() ?? false))->values();

    $activeDocuments = $material->documents ? $material->documents->where('is_active', true)->values() : collect();
    $verifiedDocuments = $activeDocuments->filter(fn ($d) => in_array($d->verification_status, ['verified', 'accepted'], true) && ! ($d->expires_at?->isPast() ?? false))->values();

    $priceItems = $material->priceBookItems ?? collect();
    $positivePriceItems = $priceItems->filter(fn ($p) => (float) $p->unit_cost > 0)->values();

    $requiredCertificates = array_values($initialSchema['required_certificates'] ?? []);
    $requiredDocuments = array_values(array_filter(
        $initialSchema['required_documents'] ?? [],
        fn (string $type): bool => ! in_array($type, ['datasheet', 'certificate'], true)
    ));
    $documentTypeLabels = \App\Support\ProfessionalCatalogProfile::supportingDocumentLabels();

    $schemaFieldLabelsByKey = collect($initialSchema['groups'] ?? [])
        ->flatMap(fn (array $group): array => $group['fields'] ?? [])
        ->mapWithKeys(fn (array $field): array => [
            (string) ($field['key'] ?? '') => (string) ($field['label'] ?? str((string) ($field['key'] ?? ''))->replace('_', ' ')->title()),
        ])
        ->all();

    $schemaFieldKeys = array_keys($schemaFieldLabelsByKey);

    $humanizedMissingSpecLabels = array_map(
        fn (string $key): string => $schemaFieldLabelsByKey[$key] ?? (string) str($key)->replace('_', ' ')->title(),
        (array) ($readiness['missing_specs'] ?? [])
    );

    $humanizedReadinessNotes = array_map(function (string $note) use ($humanizedMissingSpecLabels): string {
        if (str_starts_with($note, 'Missing required technical specs:')) {
            $count = count($humanizedMissingSpecLabels);
            $preview = array_slice($humanizedMissingSpecLabels, 0, 4);
            $suffix = $count > 4 ? ' (+'.($count - 4).' more)' : '';

            return 'Missing required technical parameters ('.$count.'): '.implode(', ', $preview).$suffix.'.';
        }

        return str_replace('_', ' ', $note);
    }, (array) ($readiness['notes'] ?? []));
    $customSpecs = ($material->specs ?? collect())
        ->reject(fn ($spec): bool => in_array($spec->spec_key, $schemaFieldKeys, true))
        ->values();

    $persistedSpecMap = array_replace(
        \App\Support\ProfessionalSpecificationSchema::legacySpecValues($material),
        ($material->specs ?? collect())->pluck('spec_value', 'spec_key')->all()
    );

    $baselinePayload = [
        'id' => $material->id,
        'sku' => (string) ($material->sku ?? ''),
        'name' => (string) ($material->name ?? ''),
        'brand' => (string) ($material->brand ?? ''),
        'model' => (string) ($material->model ?? ''),
        'material_category_id' => $material->material_category_id ? (string) $material->material_category_id : '',
        'category_code' => (string) ($material->category?->code ?: 'DEFAULT'),
        'category_name' => (string) ($material->category?->name ?: 'Unclassified component'),
        'uom' => (string) ($material->uom ?? 'pcs'),
        'material_type' => (string) ($material->material_type ?? ''),
        'cost_group' => (string) ($material->cost_group ?? ''),
        'default_scope_mode' => (string) ($material->default_scope_mode ?? 'catalog_candidate'),
        'is_active' => (bool) ($material->is_active ?? true),
        'system_types' => array_values((array) ($material->system_types ?? [])),
        'project_scales' => array_values((array) ($material->project_scales ?? [])),
        'suitable_roof_types' => array_values((array) ($material->suitable_roof_types ?? [])),
        'description' => (string) ($material->description ?? ''),
        'technical_notes' => (string) ($material->technical_notes ?? ''),
        'catalog_status' => $catalogStatusRaw,
        'catalog_status_label' => $catalogStatusLabel,
        'compliance_status' => $complianceStatusRaw,
        'compliance_status_label' => $complianceStatusLabel,
        'specs' => $persistedSpecMap,
        'applied_spec_keys' => $appliedSpecKeys,
        'has_evidence' => $activeDatasheets->isNotEmpty() || $activeCertificates->isNotEmpty() || $activeDocuments->isNotEmpty(),
        'datasheets_count' => $activeDatasheets->count(),
        'parsed_datasheets_count' => $parsedDatasheets->count(),
        'certificates_count' => $activeCertificates->count(),
        'verified_certificates_count' => $verifiedCertificates->count(),
        'documents_count' => $activeDocuments->count(),
        'verified_documents_count' => $verifiedDocuments->count(),
        'price_items_count' => $positivePriceItems->count(),
    ];
@endphp

@section('content')
    <div class="nxmc-editor nxmce-workspace" data-nxmce-workspace>
        <script type="application/json" data-nxmc-specification-schema-payload>@json($specificationSchemas ?? [])</script>
        <script type="application/json" data-nxmc-specification-value-payload>@json($resolvedTechnicalValues)</script>
        <script type="application/json" data-nxmc-specification-error-payload>@json($technicalSpecErrors)</script>
        <script type="application/json" data-nxmce-baseline-payload>@json($baselinePayload)</script>

        {{-- Top Command Header --}}
        <header class="nxmce-header nxmc-reveal">
            <div class="nxmce-header-top">
                <div class="nxmce-breadcrumbs">
                    <a href="{{ route('companies.materials.index', $company) }}" class="nxmce-back-link" data-nxmc-navigate="directory">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12.5 15-5-5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>Back to Material Catalog</span>
                    </a>
                    <span class="nxmce-crumb-sep" aria-hidden="true">/</span>
                    <a href="{{ route('companies.materials.show', [$company, $material->id]) }}" class="nxmce-record-link" data-nxmc-navigate="open" data-nxmc-material="{{ $material->name }}" data-nxmc-sku="{{ $material->sku }}">
                        <span>Material Record</span>
                        <code>{{ $material->sku }}</code>
                    </a>
                </div>

                <div class="nxmce-header-actions">
                    <a href="{{ route('companies.materials.show', [$company, $material->id]) }}" class="nxmc-button nxmc-button-secondary nxmce-btn-cancel" data-nxmc-navigate="open" data-nxmc-material="{{ $material->name }}" data-nxmc-sku="{{ $material->sku }}">
                        Cancel
                    </a>
                    <button type="button" class="nxmc-button nxmc-button-secondary nxmce-btn-reset" data-nxmce-reset-form disabled>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4.5 8A6 6 0 1 1 6 14.2M4.5 4v4h4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Reset Changes
                    </button>
                    <button type="submit" form="nxmceEditForm" class="nxmc-button nxmc-button-primary nxmce-btn-save" data-nxmce-top-save>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4.5 3.5h9l2 2v11h-11z"/><path d="M7 3.5v4.5h6V3.5M7 16.5v-5h6v5"/></svg>
                        <span>Save Changes</span>
                    </button>
                </div>
            </div>

            <div class="nxmce-header-main">
                <div class="nxmce-title-cluster">
                    <div class="nxmce-kicker-row">
                        <span class="nxmc-kicker">Persisted material mutation workspace</span>
                        <span class="nxmce-scope-pill">Company Catalog · {{ $company->name }}</span>
                    </div>
                    <div class="nxmce-title-row">
                        <h2>Edit Material</h2>
                        <span class="nxmce-title-divider" aria-hidden="true">·</span>
                        <strong class="nxmce-record-headline" data-nxmce-headline-name>{{ $material->name }}</strong>
                    </div>
                    <div class="nxmce-record-submeta">
                        <span data-nxmce-headline-brand>{{ $material->brand ?: 'Manufacturer not recorded' }}</span>
                        <span aria-hidden="true">•</span>
                        <span data-nxmce-headline-class>{{ $material->category?->name ?? 'Unclassified component' }}</span>
                        <span aria-hidden="true">•</span>
                        <span class="nxmc-mono" data-nxmce-headline-sku>SKU: {{ $material->sku }}</span>
                        @if ($material->model)
                            <span aria-hidden="true">•</span>
                            <span class="nxmc-mono" data-nxmce-headline-model>Model: {{ $material->model }}</span>
                        @endif
                        <span aria-hidden="true">•</span>
                        <span>
                            Created {{ $material->created_at?->format('M j, Y') ?? 'Recorded' }}{{ $material->creator ? ' by '.$material->creator->name : '' }}
                            @if ($material->updated_at)
                                · Updated {{ $material->updated_at->format('M j, Y') }}{{ $material->updater ? ' by '.$material->updater->name : '' }}
                            @endif
                        </span>
                    </div>
                </div>

                <div class="nxmce-status-cluster" aria-label="Current governance and mutation status">
                    <span class="nxmce-status-badge {{ $catalogStatusTone }}" title="Current persisted catalog governance state">
                        <i></i>
                        <small>Governance</small>
                        <strong>{{ $catalogStatusLabel }}</strong>
                    </span>
                    <span class="nxmce-status-badge {{ $complianceStatusTone }}" title="Current compliance review status">
                        <i></i>
                        <small>Compliance</small>
                        <strong>{{ $complianceStatusLabel }}</strong>
                    </span>
                    <span class="nxmce-status-badge {{ $isActiveRecord ? 'is-teal' : 'is-muted' }}" data-nxmce-header-active-badge title="Active visibility is separate from BOQ approval">
                        <i></i>
                        <small>Availability</small>
                        <strong data-nxmce-header-active-label>{{ $isActiveRecord ? 'Active Record' : 'Inactive' }}</strong>
                    </span>
                    <span class="nxmce-status-badge is-neutral" data-nxmce-live-signal role="status" aria-live="polite">
                        <i></i>
                        <small>Session</small>
                        <strong data-nxmce-live-signal-text>No unsaved changes</strong>
                    </span>
                </div>
            </div>

            <nav class="nxmce-section-tabs" aria-label="Material edit workspace sections">
                <a href="#material-identity" class="nxmce-section-tab is-active" data-nxmce-tab="identity">
                    <span class="nxmce-tab-index">01</span>
                    <span class="nxmce-tab-label">Identity &amp; Class</span>
                    <em class="nxmce-tab-badge" data-nxmc-section-state="identity">Complete</em>
                </a>
                <a href="#material-applicability" class="nxmce-section-tab" data-nxmce-tab="applicability">
                    <span class="nxmce-tab-index">02</span>
                    <span class="nxmce-tab-label">Scope &amp; Fit</span>
                    <em class="nxmce-tab-badge" data-nxmc-section-state="applicability">Complete</em>
                </a>
                <a href="#material-technical-input" class="nxmce-section-tab" data-nxmce-tab="technical">
                    <span class="nxmce-tab-index">03</span>
                    <span class="nxmce-tab-label">Technical Specs</span>
                    <em class="nxmce-tab-badge" data-nxmc-section-state="technical">In progress</em>
                </a>
                <a href="#material-evidence-impact" class="nxmce-section-tab" data-nxmce-tab="evidence">
                    <span class="nxmce-tab-index">04</span>
                    <span class="nxmce-tab-label">Linked Evidence</span>
                    <em class="nxmce-tab-badge">{{ $activeDatasheets->count() + $activeCertificates->count() + $activeDocuments->count() }} linked</em>
                </a>
                <a href="#material-governance-input" class="nxmce-section-tab" data-nxmce-tab="governance">
                    <span class="nxmce-tab-index">05</span>
                    <span class="nxmce-tab-label">Governance Notes</span>
                    <em class="nxmce-tab-badge" data-nxmc-section-state="governance">Optional</em>
                </a>
            </nav>
        </header>

        @if ($errors->any())
            <div class="nxmc-alert nxmce-validation-banner nxmc-reveal" role="alert">
                <span class="nxmc-alert-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <div>
                    <strong>Material update could not be saved — review the highlighted fields below.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form id="nxmceEditForm" method="POST" action="{{ route('companies.materials.update', [$company, $material->id]) }}" data-nxmc-editor-form data-nxmc-editor-mode="edit" novalidate>
            @csrf
            @method('PUT')

            <div class="nxmce-grid">
                {{-- Primary Edit Workspace (Left Column) --}}
                <div class="nxmc-editor-body nxmce-main">
                    {{-- 01. Overview & Material Identity --}}
                    <section id="material-identity" class="nxmc-form-section nxmce-card nxmce-overview-card nxmc-reveal">
                        <header class="nxmc-form-section-head nxmce-card-head">
                            <div class="nxmc-form-section-title">
                                <span class="nxmc-form-section-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                                </span>
                                <div>
                                    <span class="nxmce-section-kicker">Section 01 · Persisted Record Identity &amp; Classification</span>
                                    <h3>Material identity and component class</h3>
                                    <p>Maintain the authoritative SKU, manufacturer, model, and engineering component class. Changing identity or component class invalidates existing catalog approval.</p>
                                </div>
                            </div>
                            <div class="nxmce-section-head-meta">
                                <span class="nxmc-section-state" data-nxmc-section-state="identity">Complete</span>
                            </div>
                        </header>

                        <div class="nxmc-form-section-body nxmce-overview-body">
                            <div class="nxmce-identity-stage">
                                {{-- Left: Component Class Visual Stage (Truthful Catalog Media Boundary) --}}
                                <aside class="nxmce-media-stage" aria-label="Component class illustration">
                                    <div class="nxmce-visual-frame" data-nxmce-class-visual data-nxmce-image-base="{{ asset('images/material-classes') }}" data-category-code="{{ $material->category?->code ?: 'DEFAULT' }}" data-profile-code="{{ \App\Support\ProfessionalCatalogProfile::rootCodeFor($material) ?: 'DEFAULT' }}">
                                        <div class="nxmce-visual-glow" aria-hidden="true"></div>
                                        <div class="nxmce-visual-grid" aria-hidden="true"></div>
                                        <div class="nxmce-visual-badges">
                                            <span class="nxmce-visual-tag" data-nxmce-visual-tag>Component class preview</span>
                                            <span class="nxmce-visual-code" data-nxmce-visual-code>{{ $material->category?->parent?->name ?: 'Engineering Class' }}</span>
                                        </div>
                                        <img class="nxmce-class-image" data-nxmce-class-image alt="{{ $material->category?->name ?? 'Engineering component class illustration' }}" loading="eager" decoding="async">
                                    </div>

                                    <div class="nxmce-media-caption">
                                        <div class="nxmce-media-caption-head">
                                            <span>Catalog media boundary</span>
                                            <strong>Class illustration only</strong>
                                        </div>
                                        <p>No manufacturer product photo upload is stored on this record. This illustration represents the selected engineering component class and is never treated as compliance evidence.</p>
                                    </div>
                                </aside>

                                {{-- Right: 8 Identity Form Fields in 4 Laser-Aligned Rows --}}
                                <div class="nxmce-identity-fields">
                                    <div class="nxmc-form-grid nxmce-identity-grid">
                                        <div class="nxmc-field">
                                            <div class="nxmci-category-label-row">
                                                <label class="nxmc-label" for="brand">Manufacturer / Brand</label>
                                            </div>
                                            <input id="brand" data-nxmci-identity-field="brand" class="nxmc-input @error('brand') is-invalid @enderror" name="brand" value="{{ old('brand', $material->brand) }}" placeholder="e.g. Trina Solar Co., Ltd.">
                                            @error('brand') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="nxmc-field nxmci-category-selector">
                                            <div class="nxmci-category-label-row">
                                                <label class="nxmc-label" for="material_category_id">Component Class <span class="nxmce-req">*</span></label>
                                                <span class="nxmci-category-coverage">{{ $canonicalEngineeringClassCount }} classes</span>
                                            </div>
                                            <div class="nxmci-category-picker" data-nxmci-category-picker>
                                                <button type="button" class="nxmci-category-trigger" data-nxmci-category-trigger aria-haspopup="listbox" aria-expanded="false" aria-controls="nxmci-category-options">
                                                    <span class="nxmci-category-trigger-copy">
                                                        <strong data-nxmci-category-trigger-label>{{ $material->category?->name ?? 'Select component class' }}</strong>
                                                        <small data-nxmci-category-trigger-meta>{{ $material->category?->parent?->name ?? 'Choose from the engineering catalog' }}</small>
                                                    </span>
                                                    <span class="nxmci-category-trigger-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 20 20" fill="none"><path d="m5.5 7.5 4.5 4.5 4.5-4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </span>
                                                </button>
                                                <div class="nxmci-category-menu" data-nxmci-category-menu hidden>
                                                    <div class="nxmci-category-menu-head">
                                                        <span>Exact engineering classes</span>
                                                        <small data-nxmci-category-menu-count>{{ $canonicalEngineeringClassCount }} available</small>
                                                    </div>
                                                    <label class="nxmci-category-search">
                                                        <span class="nxmci-category-search-icon" aria-hidden="true">
                                                            <svg viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="4.75" stroke="currentColor" stroke-width="1.45"/><path d="m12 12 3.7 3.7" stroke="currentColor" stroke-width="1.45" stroke-linecap="round"/></svg>
                                                        </span>
                                                        <input type="search" class="nxmci-category-filter" data-nxmci-category-filter placeholder="Search module, inverter, breaker, transformer..." autocomplete="off" aria-label="Search exact engineering component classes" aria-controls="nxmci-category-options">
                                                        <kbd>/</kbd>
                                                    </label>
                                                    <div id="nxmci-category-options" class="nxmci-category-options" data-nxmci-category-options role="listbox" aria-label="Engineering component classes"></div>
                                                </div>
                                            </div>
                                            <select id="material_category_id" class="nxmc-select nxmci-category-native @error('material_category_id') is-invalid @enderror" name="material_category_id" required>
                                                <option value="" data-code="DEFAULT" data-profile-code="DEFAULT">Select component class</option>
                                                @foreach ($categories as $category)
                                                    @php
                                                        $profileCode = \App\Support\ProfessionalCatalogProfile::rootCodeFor($category);
                                                        $isSelectedCategory = (string) old('material_category_id', $material->material_category_id) === (string) $category->id;
                                                        $isDomainHeading = $category->parent_id === null;
                                                        $isCanonicalClass = \App\Support\EngineeringProductMasterDictionary::has((string) $category->code);
                                                    @endphp
                                                    @if ($isDomainHeading || $isCanonicalClass || $isSelectedCategory)
                                                        <option
                                                            value="{{ $category->id }}"
                                                            data-code="{{ $category->code ?: 'DEFAULT' }}"
                                                            data-profile-code="{{ $profileCode ?: 'DEFAULT' }}"
                                                            data-parent-id="{{ $category->parent_id ?? '' }}"
                                                            data-canonical-class="{{ $isCanonicalClass ? '1' : '0' }}"
                                                            @selected($isSelectedCategory)
                                                            @disabled($isDomainHeading && ! $isSelectedCategory)
                                                        >{{ $isDomainHeading ? $category->name.' · select a component below' : '— '.($isCanonicalClass ? $category->name : $category->name.' (Current Legacy Profile)') }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            @error('material_category_id') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="nxmc-field">
                                            <div class="nxmci-category-label-row">
                                                <label class="nxmc-label" for="name">Material / Product Name <span class="nxmce-req">*</span></label>
                                            </div>
                                            <input id="name" data-nxmci-identity-field="name" class="nxmc-input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $material->name) }}" required>
                                            @error('name') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="nxmc-field">
                                            <div class="nxmci-category-label-row">
                                                <label class="nxmc-label" for="model">Model / Part Number</label>
                                                <span class="nxmce-inline-status {{ filled($material->model) ? 'is-recorded' : 'is-missing' }}" data-nxmce-model-badge>
                                                    {{ filled($material->model) ? 'Recorded' : 'Not recorded' }}
                                                </span>
                                            </div>
                                            <input id="model" data-nxmci-identity-field="model" class="nxmc-input nxmc-mono @error('model') is-invalid @enderror" name="model" value="{{ old('model', $material->model) }}" placeholder="e.g. TSM-NEG21C.20">
                                            @error('model') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="nxmc-field">
                                            <div class="nxmci-category-label-row">
                                                <label class="nxmc-label" for="sku">Catalog SKU <span class="nxmce-req">*</span></label>
                                            </div>
                                            <input id="sku" data-nxmci-identity-field="sku" class="nxmc-input nxmc-mono @error('sku') is-invalid @enderror" name="sku" value="{{ old('sku', $material->sku) }}" autocomplete="off" required>
                                            @error('sku') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="nxmc-field">
                                            <div class="nxmci-category-label-row">
                                                <label class="nxmc-label" for="uom">Unit of Measure <span class="nxmce-req">*</span></label>
                                            </div>
                                            <input id="uom" class="nxmc-input nxmc-mono @error('uom') is-invalid @enderror" name="uom" value="{{ old('uom', $material->uom ?? 'pcs') }}" required>
                                            @error('uom') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="nxmc-field">
                                            <div class="nxmci-category-label-row">
                                                <label class="nxmc-label" for="material_type">Material Type (Admin)</label>
                                            </div>
                                            <input id="material_type" class="nxmc-input @error('material_type') is-invalid @enderror" name="material_type" value="{{ old('material_type', $material->material_type) }}" placeholder="PV module, inverter, cable, protection">
                                            @error('material_type') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="nxmc-field">
                                            <div class="nxmci-category-label-row">
                                                <label class="nxmc-label" for="cost_group">Cost Group (Admin)</label>
                                            </div>
                                            <input id="cost_group" class="nxmc-input @error('cost_group') is-invalid @enderror" name="cost_group" value="{{ old('cost_group', $material->cost_group) }}" placeholder="Material, Electrical BOS, Structural BOS">
                                            @error('cost_group') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Full-Width Catalog Description with live character count --}}
                            <div class="nxmc-field nxmce-description-field">
                                <div class="nxmce-label-split">
                                    <label class="nxmc-label" for="description">Catalog description (administrative)</label>
                                    <span class="nxmce-char-counter nxmc-mono" data-nxmce-desc-counter>{{ mb_strlen((string) old('description', $material->description ?? '')) }} / 2000 characters</span>
                                </div>
                                <textarea id="description" class="nxmc-textarea nxmce-compact-textarea @error('description') is-invalid @enderror" name="description" maxlength="2000" placeholder="Describe the material function, construction, and intended commercial or engineering context.">{{ old('description', $material->description) }}</textarea>
                                @error('description') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                            </div>

                            </div>
                    </section>

                    {{-- 02. Applicability, Scope & Availability --}}
                    <section id="material-applicability" class="nxmc-form-section nxmce-card nxmc-reveal">
                        <header class="nxmc-form-section-head nxmce-card-head">
                            <div class="nxmc-form-section-title">
                                <span class="nxmc-form-section-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/></svg>
                                </span>
                                <div>
                                    <span class="nxmce-section-kicker">Section 02 · Catalog Scope, Availability &amp; System Fit</span>
                                    <h3>Applicability and scope</h3>
                                    <p>Define default BOM/BOQ scope, active availability, and system suitability. Active status never substitutes for catalog approval.</p>
                                </div>
                            </div>
                            <span class="nxmc-section-state" data-nxmc-section-state="applicability">Complete</span>
                        </header>

                        <div class="nxmc-form-section-body">
                            <div class="nxmc-form-grid nxmce-applicability-top-grid">
                                <div class="nxmc-field">
                                    <label class="nxmc-label" for="default_scope_mode">Default BOM / BOQ scope mode <span class="nxmce-req">*</span></label>
                                    <select id="default_scope_mode" class="nxmc-select @error('default_scope_mode') is-invalid @enderror" name="default_scope_mode" required data-nxmci-premium-select data-nxmci-select-label="Default BOM / BOQ scope mode" data-nxmci-select-meta="Choose the default BOM/BOQ role" data-nxmci-select-selected-meta="Default BOM/BOQ role">
                                        @foreach ($scopeModes as $value => $label)
                                            <option value="{{ $value }}" @selected(old('default_scope_mode', $material->default_scope_mode ?? 'catalog_candidate') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="nxmc-field-hint">Sets the default BOM/BOQ line classification; does not bypass catalog approval.</span>
                                    @error('default_scope_mode') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                </div>

                                <div class="nxmc-field">
                                    <label class="nxmc-label" for="is_active">Catalog availability &amp; visibility</label>
                                    <div class="nxmc-toggle-row nxmce-active-toggle-card">
                                        <span class="nxmc-toggle-copy">
                                            <strong>Active catalog record</strong>
                                            <small>Visible in catalog selection workflows.</small>
                                        </span>
                                        <label class="nxmc-toggle">
                                            <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $material->is_active ?? true))>
                                            <span aria-hidden="true"></span>
                                        </label>
                                    </div>
                                    <span class="nxmc-field-hint">Changing active availability or scope on an approved material resets it to Needs Review.</span>
                                </div>

                                <div class="nxmc-field nxmc-form-span-2">
                                    <label class="nxmc-label">System applicability</label>
                                    <div class="nxmc-choices nxmce-choices-3col">
                                        @foreach ($systemTypes as $value => $label)
                                            <label class="nxmc-choice">
                                                <input type="checkbox" name="system_types[]" value="{{ $value }}" @checked(in_array($value, $selectedSystemTypes, true))>
                                                <span class="nxmc-choice-surface">
                                                    <span class="nxmc-choice-copy">
                                                        <strong>{{ $label }}</strong>
                                                        <small>Allow in {{ strtolower($label) }} solar architecture workflows.</small>
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('system_types') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                </div>

                                <div class="nxmc-form-span-2 nxmce-suitability-split">
                                    <div class="nxmc-field nxmce-suitability-col">
                                        <label class="nxmc-label">Project scale suitability</label>
                                        <div class="nxmc-choices nxmce-choices-stack-2">
                                            @foreach ($projectScales as $value => $label)
                                                <label class="nxmc-choice">
                                                    <input type="checkbox" name="project_scales[]" value="{{ $value }}" @checked(in_array($value, $selectedProjectScales, true))>
                                                    <span class="nxmc-choice-surface">
                                                        <span class="nxmc-choice-copy">
                                                            <strong>{{ $label }}</strong>
                                                            <small>Suitable for {{ strtolower($label) }} deployment scale.</small>
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <span class="nxmc-field-hint">Select all project scales supported by this component.</span>
                                        @error('project_scales') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="nxmc-field nxmce-suitability-col">
                                        <label class="nxmc-label">Roof and site suitability</label>
                                        <div class="nxmc-choices is-two nxmce-choices-grid-2x2">
                                            @foreach ($roofTypes as $value => $label)
                                                <label class="nxmc-choice">
                                                    <input type="checkbox" name="suitable_roof_types[]" value="{{ $value }}" @checked(in_array($value, $selectedRoofTypes, true))>
                                                    <span class="nxmc-choice-surface">
                                                        <span class="nxmc-choice-copy">
                                                            <strong>{{ $label }}</strong>
                                                            <small>Compatible with {{ strtolower($label) }}.</small>
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <span class="nxmc-field-hint">Leave unchecked when not constrained by roof or mounting context.</span>
                                        @error('suitable_roof_types') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- 03. Component Engineering Data (Schema-Driven) --}}
                    <section id="material-technical-input" class="nxmc-form-section nxmce-card nxmc-reveal">
                        <header class="nxmc-form-section-head nxmce-card-head">
                            <div class="nxmc-form-section-title">
                                <span class="nxmc-form-section-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8M10 19V4M16 19v-7M22 19H2"/><path d="m7 12 3-3 3 2 4-5"/></svg>
                                </span>
                                <div>
                                    <span class="nxmce-section-kicker">Section 03 · Class-Driven Technical Specification Authority</span>
                                    <h3>Component engineering data</h3>
                                    <p>The selected component class governs all visible technical parameters and units. Missing values remain explicitly unrecorded—no typical engineering values are ever assumed.</p>
                                </div>
                            </div>
                            <span class="nxmc-section-state" data-nxmc-section-state="technical">In progress</span>
                        </header>

                        <div class="nxmc-form-section-body">
                            {{-- Category Switch / Stale Specification Warning Banner (Dynamic) --}}
                            <div class="nxmce-category-switch-banner" data-nxmce-category-switch-banner hidden role="status" aria-live="polite">
                                <div class="nxmce-switch-banner-head">
                                    <div class="nxmce-switch-title">
                                        <span class="nxmce-switch-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                                        </span>
                                        <div>
                                            <strong>Component class changed — technical schema impact detected</strong>
                                            <p data-nxmce-switch-summary>Comparing persisted specification keys against the newly selected component class schema.</p>
                                        </div>
                                    </div>
                                    <button type="button" class="nxmc-button nxmc-button-secondary nxmce-btn-restore-class" data-nxmce-restore-class>
                                        Restore Original Class
                                    </button>
                                </div>
                                <div class="nxmce-switch-columns">
                                    <div class="nxmce-switch-box is-preserved">
                                        <span>Preserved shared keys (<b data-nxmce-switch-preserved-count>0</b>)</span>
                                        <p data-nxmce-switch-preserved-list>None</p>
                                    </div>
                                    <div class="nxmce-switch-box is-removed">
                                        <span>Stale keys removed on save (<b data-nxmce-switch-stale-count>0</b>)</span>
                                        <p data-nxmce-switch-stale-list>None</p>
                                    </div>
                                </div>
                            </div>



                            <div class="nxspec-commandbar nxmce-spec-toolbar">
                                <div class="nxmce-spec-toolbar-copy">
                                    <span>Engineering data checklist</span>
                                    <strong data-nxmc-schema-status>{{ $initialSchema['title'] ?? 'Select a component class' }}</strong>
                                    <p>Missing values remain visible as unrecorded. Saving technical parameter changes resets an Approved material to Needs Review.</p>
                                </div>
                                <div class="nxmce-spec-toolbar-controls">
                                    <div class="nxmce-spec-filters" role="group" aria-label="Filter technical specification fields">
                                        <button type="button" class="nxmce-spec-filter is-active" data-nxmce-spec-filter="all">All</button>
                                        <button type="button" class="nxmce-spec-filter" data-nxmce-spec-filter="required">Required</button>
                                        <button type="button" class="nxmce-spec-filter" data-nxmce-spec-filter="missing">Missing</button>
                                        <button type="button" class="nxmce-spec-filter" data-nxmce-spec-filter="modified">Modified</button>
                                    </div>
                                    <div class="nxspec-progress" aria-label="Required specification completion">
                                        <span><i data-nxmc-spec-progress-fill></i></span>
                                        <strong data-nxmc-spec-progress-copy>0 of 0 recorded</strong>
                                    </div>
                                </div>
                            </div>

                            <nav class="nxmci-spec-group-nav" data-nxmci-spec-group-nav aria-label="Technical specification groups" hidden></nav>

                            <div class="nxspec-groups" data-nxmc-dynamic-specifications>
                                <div class="nxspec-empty">
                                    <span aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                                    </span>
                                    <strong>Select an engineering component class.</strong>
                                    <p>The applicable engineering fields and units will load for the selected component class.</p>
                                </div>
                            </div>

                            @if ($customSpecs->isNotEmpty())
                                <div class="nxmce-custom-specs-panel">
                                    <div class="nxmce-custom-specs-head">
                                        <div>
                                            <span>Additional custom specifications ({{ $customSpecs->count() }})</span>
                                            <strong>Preserved outside category schema</strong>
                                        </div>
                                        <a href="{{ route('companies.materials.show', [$company, $material->id]) }}#material-technical" class="nxmce-inline-link">Manage on Material Record →</a>
                                    </div>
                                    <div class="nxmce-custom-specs-grid">
                                        @foreach ($customSpecs as $customSpec)
                                            <div class="nxmce-custom-spec-item">
                                                <small>{{ $customSpec->spec_label }}</small>
                                                <strong class="nxmc-mono">{{ $customSpec->formatted_value ?: 'Not recorded' }}</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>

                    {{-- 04. Evidence Basis & Mutation Consequences --}}
                    <section id="material-evidence-impact" class="nxmc-form-section nxmce-card nxmc-reveal">
                        <header class="nxmc-form-section-head nxmce-card-head">
                            <div class="nxmc-form-section-title">
                                <span class="nxmc-form-section-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6M9 16h6"/></svg>
                                </span>
                                <div>
                                    <span class="nxmce-section-kicker">Section 04 · Linked Evidence &amp; Governance Consequences</span>
                                    <h3>Evidence basis and mutation impact</h3>
                                    <p>Editing a Material never deletes attached datasheets, certificates, or supporting documents, but changing engineering values invalidates existing approval until evidence is re-reviewed.</p>
                                </div>
                            </div>
                            <a href="{{ route('companies.materials.show', [$company, $material->id]) }}#material-datasheets" class="nxmc-button nxmc-button-secondary nxmce-card-action">
                                Open Evidence Center
                            </a>
                        </header>

                        <div class="nxmc-form-section-body">
                            <div class="nxmce-evidence-grid">
                                {{-- Card A: Datasheets & Parser Applications --}}
                                <article class="nxmce-evidence-card">
                                    <header class="nxmce-evidence-card-head">
                                        <div>
                                            <span>Datasheets &amp; parser trace</span>
                                            <h4>Technical datasheets ({{ $activeDatasheets->count() }})</h4>
                                        </div>
                                        <span class="nxmce-mini-badge {{ $parsedDatasheets->isNotEmpty() ? 'is-green' : ($activeDatasheets->isNotEmpty() ? 'is-amber' : 'is-muted') }}">
                                            {{ $parsedDatasheets->isNotEmpty() ? 'Parsed source linked' : ($activeDatasheets->isNotEmpty() ? 'Review pending' : 'No active datasheet') }}
                                        </span>
                                    </header>
                                    @if ($activeDatasheets->isNotEmpty())
                                        <ul class="nxmce-evidence-list">
                                            @foreach ($activeDatasheets->take(3) as $ds)
                                                <li>
                                                    <div>
                                                        <strong>{{ $ds->title }}</strong>
                                                        <small>
                                                            {{ $ds->original_filename ?: 'External reference' }}
                                                            @if ($ds->revision) · {{ $ds->revision }} @endif
                                                            @if ($ds->parser_version) · Parser {{ $ds->parser_version }} @endif
                                                        </small>
                                                    </div>
                                                    <span class="nxmce-mini-badge {{ in_array($ds->extraction_status, ['parsed', 'reviewed', 'applied'], true) ? 'is-teal' : 'is-amber' }}">
                                                        {{ match ($ds->extraction_status) {
                                                            'applied' => 'Applied',
                                                            'reviewed' => 'Reviewed',
                                                            'parsed' => 'Parsed',
                                                            'category_mismatch' => 'Class Mismatch',
                                                            'variant_selection_required' => 'Variant Required',
                                                            default => 'Pending Review',
                                                        } }}
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="nxmce-evidence-empty">No active datasheet is registered for this material yet. Upload and parse a vendor datasheet on the Material Record page.</p>
                                    @endif
                                    <footer class="nxmce-evidence-foot">
                                        <span data-nxmce-datasheet-impact-note>
                                            @if ($activeApplications->isNotEmpty())
                                                {{ count($appliedSpecKeys) }} specification field(s) were applied from a parsed datasheet. Manual edits to those fields block automatic parser rollback.
                                            @else
                                                Datasheets remain preserved when this material is edited.
                                            @endif
                                        </span>
                                    </footer>
                                </article>

                                {{-- Card B: Certifications --}}
                                <article class="nxmce-evidence-card">
                                    <header class="nxmce-evidence-card-head">
                                        <div>
                                            <span>Compliance evidence</span>
                                            <h4>Certifications ({{ $verifiedCertificates->count() }} verified)</h4>
                                        </div>
                                        <span class="nxmce-mini-badge {{ ($readiness['has_verified_certificate'] ?? false) ? 'is-green' : 'is-amber' }}">
                                            {{ ($readiness['has_verified_certificate'] ?? false) ? 'Requirements met' : 'Verification required' }}
                                        </span>
                                    </header>
                                    @if ($activeCertificates->isNotEmpty())
                                        <ul class="nxmce-evidence-list">
                                            @foreach ($activeCertificates->take(4) as $cert)
                                                @php
                                                    $certExpired = $cert->expires_at?->isPast() ?? false;
                                                    $certVerified = in_array($cert->verification_status, ['verified', 'accepted'], true) && ! $certExpired;
                                                @endphp
                                                <li>
                                                    <div>
                                                        <strong>{{ $cert->certificate_type }} · {{ $cert->title }}</strong>
                                                        <small>{{ $cert->issuer ?: 'Issuer not recorded' }}{{ $cert->certificate_number ? ' · '.$cert->certificate_number : '' }}</small>
                                                    </div>
                                                    <span class="nxmce-mini-badge {{ $certVerified ? 'is-green' : ($certExpired ? 'is-red' : 'is-amber') }}">
                                                        {{ $certExpired ? 'Expired' : ($certVerified ? 'Verified' : 'Pending') }}
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="nxmce-evidence-empty">
                                            No active certificates registered.
                                            @if ($requiredCertificates !== [])
                                                Expected for this class: {{ implode(', ', $requiredCertificates) }}.
                                            @endif
                                        </p>
                                    @endif
                                    <footer class="nxmce-evidence-foot">
                                        <span>Certificates stay attached on save, but changed engineering values require re-review before approval.</span>
                                    </footer>
                                </article>

                                {{-- Card C: Supporting Documents --}}
                                <article class="nxmce-evidence-card">
                                    <header class="nxmce-evidence-card-head">
                                        <div>
                                            <span>Supporting documentation</span>
                                            <h4>Documents ({{ $verifiedDocuments->count() }} verified)</h4>
                                        </div>
                                        <span class="nxmce-mini-badge {{ ($readiness['has_verified_document_pack'] ?? false) ? 'is-green' : 'is-amber' }}">
                                            {{ ($readiness['has_verified_document_pack'] ?? false) ? 'Pack complete' : 'Pack incomplete' }}
                                        </span>
                                    </header>
                                    @if ($activeDocuments->isNotEmpty())
                                        <ul class="nxmce-evidence-list">
                                            @foreach ($activeDocuments->take(4) as $doc)
                                                @php
                                                    $docExpired = $doc->expires_at?->isPast() ?? false;
                                                    $docVerified = in_array($doc->verification_status, ['verified', 'accepted'], true) && ! $docExpired;
                                                @endphp
                                                <li>
                                                    <div>
                                                        <strong>{{ $doc->title }}</strong>
                                                        <small>{{ $documentTypeLabels[$doc->document_type] ?? 'Supporting Document' }}{{ $doc->revision ? ' · '.$doc->revision : '' }}</small>
                                                    </div>
                                                    <span class="nxmce-mini-badge {{ $docVerified ? 'is-green' : ($docExpired ? 'is-red' : 'is-amber') }}">
                                                        {{ $docExpired ? 'Expired' : ($docVerified ? 'Verified' : 'Pending') }}
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="nxmce-evidence-empty">
                                            No supporting documents registered.
                                            @if ($requiredDocuments !== [])
                                                Expected: {{ implode(', ', array_map(fn ($t) => $documentTypeLabels[$t] ?? $t, $requiredDocuments)) }}.
                                            @endif
                                        </p>
                                    @endif
                                    <footer class="nxmce-evidence-foot">
                                        <span>Supporting manuals, warranties, and drawings remain preserved across material edits.</span>
                                    </footer>
                                </article>

                                {{-- Card D: Commercial Links & Architectural Boundary --}}
                                <article class="nxmce-evidence-card">
                                    <header class="nxmce-evidence-card-head">
                                        <div>
                                            <span>Commercial &amp; project boundary</span>
                                            <h4>Price books ({{ $positivePriceItems->count() }} active)</h4>
                                        </div>
                                        <span class="nxmce-mini-badge {{ $positivePriceItems->isNotEmpty() ? 'is-blue' : 'is-muted' }}">
                                            {{ $positivePriceItems->isNotEmpty() ? 'Priced reference' : 'Unpriced' }}
                                        </span>
                                    </header>
                                    @if ($priceItems->isNotEmpty())
                                        <ul class="nxmce-evidence-list">
                                            @foreach ($priceItems->take(3) as $pItem)
                                                <li>
                                                    <div>
                                                        <strong>{{ $pItem->priceBook?->name ?? 'Company Price Book' }}</strong>
                                                        <small>{{ $pItem->supplier?->name ?? ($pItem->priceBook?->supplier?->name ?? 'Supplier reference') }}{{ $pItem->supplier_sku ? ' · '.$pItem->supplier_sku : '' }}</small>
                                                    </div>
                                                    <span class="nxmce-mini-badge is-blue nxmc-mono">
                                                        {{ ($pItem->priceBook?->currency ?? 'IDR').' '.number_format((float) $pItem->unit_cost, 0, '.', ',') }}
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="nxmce-evidence-empty">No price-book items are currently linked to this material record.</p>
                                    @endif
                                    <footer class="nxmce-evidence-foot">
                                        <span>Catalog updates apply to future BOM and price-book selections; existing project calculations remain unchanged until manually reviewed.</span>
                                    </footer>
                                </article>
                            </div>
                        </div>
                    </section>

                    {{-- 05. Technical Notes & Governance Audit --}}
                    <section id="material-governance-input" class="nxmc-form-section nxmce-card nxmc-reveal">
                        <header class="nxmc-form-section-head nxmce-card-head">
                            <div class="nxmc-form-section-title">
                                <span class="nxmc-form-section-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="M9 12h6"/></svg>
                                </span>
                                <div>
                                    <span class="nxmce-section-kicker">Section 05 · Engineering Constraints &amp; Governance Lineage</span>
                                    <h3>Technical selection notes and audit lineage</h3>
                                    <p>Record engineering constraints, installation limits, or vendor evidence notes. Modifying technical notes invalidates an existing approval so reviewers verify the updated guidance.</p>
                                </div>
                            </div>
                            <span class="nxmc-section-state" data-nxmc-section-state="governance">Optional</span>
                        </header>

                        <div class="nxmc-form-section-body">
                            <div class="nxmc-field">
                                <div class="nxmce-label-split">
                                    <label class="nxmc-label" for="technical_notes">Technical and selection notes (engineering-governed)</label>
                                    <span class="nxmce-char-counter nxmc-mono" data-nxmce-notes-counter>{{ mb_strlen((string) old('technical_notes', $material->technical_notes ?? '')) }} / 2000 characters</span>
                                </div>
                                <textarea id="technical_notes" class="nxmc-textarea @error('technical_notes') is-invalid @enderror" name="technical_notes" maxlength="2000" placeholder="Record engineering constraints, derating conditions, mounting or termination limits, and vendor evidence requirements.">{{ old('technical_notes', $material->technical_notes) }}</textarea>
                                @error('technical_notes') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="nxmce-lineage-strip" aria-label="Record audit lineage">
                                <div>
                                    <small>Created</small>
                                    <strong>{{ $material->creator?->name ?? 'System' }}</strong>
                                    <span>{{ $material->created_at?->format('M j, Y H:i') ?? 'Recorded' }}</span>
                                </div>
                                <div>
                                    <small>Last Updated</small>
                                    <strong>{{ $material->updater?->name ?? ($material->creator?->name ?? 'System') }}</strong>
                                    <span>{{ $material->updated_at?->format('M j, Y H:i') ?? 'Recorded' }}</span>
                                </div>
                                <div>
                                    <small>Last Reviewed</small>
                                    <strong>{{ $material->reviewer?->name ?? 'Not yet reviewed' }}</strong>
                                    <span>{{ $material->last_reviewed_at?->format('M j, Y H:i') ?? 'Pending review cycle' }}</span>
                                </div>
                                <div>
                                    <small>Approval Authority</small>
                                    <strong>{{ $material->approver?->name ?? 'Not currently approved' }}</strong>
                                    <span>{{ $material->approved_at?->format('M j, Y H:i') ?? 'Approval requires full evidence' }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Controlled Save Action Bar --}}
                    <footer class="nxmc-editor-actionbar nxmce-actionbar nxmc-reveal">
                        <div class="nxmc-action-status nxmce-action-status" data-nxmc-action-status>
                            <div class="nxmce-action-headline">
                                <span class="nxmc-dirty-dot" aria-hidden="true"></span>
                                <strong data-nxmce-footer-title>Controlled material record · No unsaved changes</strong>
                                <span class="nxmce-consequence-pill is-neutral" data-nxmce-footer-consequence>Governance state unchanged</span>
                            </div>
                            <small data-nxmc-action-copy>No unsaved changes detected. Edit any field above to preview its governance and evidence impact before saving.</small>
                        </div>
                        <div class="nxmc-action-buttons">
                            <a href="{{ route('companies.materials.show', [$company, $material->id]) }}" class="nxmc-button nxmc-button-secondary" data-nxmc-navigate="open" data-nxmc-material="{{ $material->name }}" data-nxmc-sku="{{ $material->sku }}">Cancel</a>
                            <button type="button" class="nxmc-button nxmc-button-secondary" data-nxmce-reset-form disabled>Reset to Persisted</button>
                            <button type="submit" class="nxmc-button nxmc-button-primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
                                {{ $submitLabel ?? 'Save Changes' }}
                            </button>
                        </div>
                    </footer>
                </div>

                {{-- Mutation-Aware Intelligence & Governance Right Rail --}}
                <aside class="nxmc-editor-rail nxmce-rail nxmc-reveal" aria-label="Material mutation and governance intelligence rail">
                    {{-- 1. Live Change Impact Card --}}
                    <section class="nxmce-rail-card nxmce-impact-card" data-nxmce-impact-panel aria-labelledby="nxmce-impact-title">
                        <header class="nxmce-rail-head">
                            <div>
                                <span class="nxmc-panel-kicker">Mutation intelligence</span>
                                <h3 id="nxmce-impact-title">Change impact</h3>
                            </div>
                            <span class="nxmce-impact-badge is-clean" data-nxmce-impact-badge>No persisted changes</span>
                        </header>

                        <p class="nxmce-rail-copy" data-nxmce-impact-summary>
                            Form matches the persisted catalog record. Edit any field to preview governance and evidence consequences.
                        </p>

                        <dl class="nxmce-impact-list">
                            <div>
                                <dt>Session status</dt>
                                <dd class="nxmc-mono" data-nxmce-impact-count>0 change groups modified</dd>
                            </div>
                            <div>
                                <dt>Engineering basis</dt>
                                <dd data-nxmce-impact-basis>Unchanged</dd>
                            </div>
                            <div>
                                <dt>Approval impact</dt>
                                <dd data-nxmce-impact-approval>
                                    {{ $catalogStatusRaw === 'approved' ? 'Approved state preserved while clean' : 'Record is currently '.$catalogStatusLabel }}
                                </dd>
                            </div>
                            <div>
                                <dt>Schema / class impact</dt>
                                <dd data-nxmce-impact-schema>Same component class ({{ $material->category?->name ?? 'Unclassified' }})</dd>
                            </div>
                            <div>
                                <dt>Evidence consequence</dt>
                                <dd data-nxmce-impact-evidence>
                                    {{ ($activeDatasheets->isNotEmpty() || $activeCertificates->isNotEmpty() || $activeDocuments->isNotEmpty())
                                        ? 'Existing evidence preserved · No review triggered'
                                        : 'No linked evidence recorded' }}
                                </dd>
                            </div>
                            <div>
                                <dt>Project calculations</dt>
                                <dd>Manual review required per project</dd>
                            </div>
                        </dl>

                        <div class="nxmce-changed-fields-box" data-nxmce-changed-fields-box hidden>
                            <span>Modified in this session</span>
                            <div class="nxmce-changed-chips" data-nxmce-changed-fields-list></div>
                        </div>
                    </section>

                    {{-- 2. Governance & Readiness State --}}
                    <section class="nxmce-rail-card" aria-labelledby="nxmce-governance-rail-title">
                        <header class="nxmce-rail-head">
                            <div>
                                <span class="nxmc-panel-kicker">Catalog governance</span>
                                <h3 id="nxmce-governance-rail-title">Governance state</h3>
                            </div>
                            <strong class="nxmce-readiness-pill {{ $readinessTone }} nxmc-mono">{{ $readinessScore }}%</strong>
                        </header>

                        <div class="nxmce-governance-matrix">
                            <div>
                                <small>Catalog status</small>
                                <strong class="{{ $catalogStatusTone }}">{{ $catalogStatusLabel }}</strong>
                            </div>
                            <div>
                                <small>Compliance</small>
                                <strong class="{{ $complianceStatusTone }}">{{ $complianceStatusLabel }}</strong>
                            </div>
                            <div>
                                <small>Availability</small>
                                <strong class="{{ $isActiveRecord ? 'is-green' : 'is-amber' }}" data-nxmce-rail-active>{{ $isActiveRecord ? 'Active' : 'Inactive' }}</strong>
                            </div>
                            <div>
                                <small>BOQ release</small>
                                <strong class="{{ $boqEligible ? 'is-green' : 'is-amber' }}" data-nxmce-rail-boq>{{ $boqEligible ? 'Eligible' : 'Blocked' }}</strong>
                            </div>
                        </div>

                        <div class="nxmce-governance-rule">
                            <strong>Active does not mean approved.</strong>
                            <p>Engineering, identity, or applicability edits reset approval to Needs Review. Administrative edits preserve current approval.</p>
                        </div>

                        @if (! empty($humanizedReadinessNotes))
                            <ul class="nxmce-blocker-list" aria-label="Current approval readiness notes">
                                @foreach (array_slice($humanizedReadinessNotes, 0, 3) as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- 3. Technical Data Status (4-Column Balanced Grid) --}}
                    <section class="nxmce-rail-card" aria-labelledby="nxmce-tech-rail-title">
                        <header class="nxmce-rail-head">
                            <div>
                                <span class="nxmc-panel-kicker">Schema completeness</span>
                                <h3 id="nxmce-tech-rail-title">Technical data status</h3>
                            </div>
                            <span class="nxmce-rail-tag" data-nxmci-schema-rail-state>Class selected</span>
                        </header>

                        <div class="nxmce-tech-metrics">
                            <div><small>Required</small><strong class="nxmc-mono" data-nxmci-rail-required>{{ $initialSchema['required_count'] ?? 0 }}</strong></div>
                            <div><small>Recorded</small><strong class="nxmc-mono" data-nxmci-rail-recorded>0</strong></div>
                            <div><small>Missing</small><strong class="nxmc-mono" data-nxmci-rail-missing>0</strong></div>
                            <div data-nxmci-rail-metric="conditional"><small>Conditional</small><strong class="nxmc-mono" data-nxmci-rail-conditional>{{ $initialSchema['conditional_count'] ?? 0 }}</strong></div>
                        </div>

                        <div class="nxmc-editor-track" aria-hidden="true"><span data-nxmc-progress-fill></span></div>
                        <div class="nxmce-tech-rail-foot">
                            <span data-nxmci-schema-rail-copy>Required engineering parameters for the active component class.</span>
                            <strong class="nxmc-preview-score" data-nxmc-progress-score>0%</strong>
                        </div>
                        <a href="#material-technical-input" class="nxmci-rail-link nxmce-rail-jump">Jump to technical specifications <span aria-hidden="true">→</span></a>
                    </section>

                    {{-- 4. Evidence Basis, Baseline & Quick Actions --}}
                    <section class="nxmce-rail-card" aria-labelledby="nxmce-evidence-rail-title">
                        <header class="nxmce-rail-head">
                            <div>
                                <span class="nxmc-panel-kicker">Supporting records</span>
                                <h3 id="nxmce-evidence-rail-title">Evidence &amp; baseline</h3>
                            </div>
                            <code class="nxmce-rail-code">{{ $material->sku }}</code>
                        </header>

                        <ul class="nxmce-evidence-summary-rows">
                            <li>
                                <span>Datasheets</span>
                                <strong class="{{ $parsedDatasheets->isNotEmpty() ? 'is-green' : ($activeDatasheets->isNotEmpty() ? 'is-amber' : 'is-muted') }}">
                                    {{ $parsedDatasheets->isNotEmpty() ? $parsedDatasheets->count().' parsed / '.$activeDatasheets->count().' active' : ($activeDatasheets->isNotEmpty() ? $activeDatasheets->count().' pending review' : 'None recorded') }}
                                </strong>
                            </li>
                            <li>
                                <span>Certificates</span>
                                <strong class="{{ ($readiness['has_verified_certificate'] ?? false) ? 'is-green' : 'is-amber' }}">
                                    {{ $verifiedCertificates->count() }} verified / {{ count($requiredCertificates) }} required
                                </strong>
                            </li>
                            <li>
                                <span>Supporting documents</span>
                                <strong class="{{ ($readiness['has_verified_document_pack'] ?? false) ? 'is-green' : 'is-amber' }}">
                                    {{ $verifiedDocuments->count() }} verified / {{ count($requiredDocuments) }} required
                                </strong>
                            </li>
                            <li>
                                <span>Price-book references</span>
                                <strong class="{{ $positivePriceItems->isNotEmpty() ? 'is-blue' : 'is-muted' }}">
                                    {{ $positivePriceItems->count() }} positive cost link(s)
                                </strong>
                            </li>
                            <li>
                                <span>Catalog media</span>
                                <strong class="is-muted">Class visual only</strong>
                            </li>
                            <li>
                                <span>Update mode</span>
                                <strong class="is-muted">Transactional save</strong>
                            </li>
                        </ul>

                        <div class="nxmce-rail-actions">
                            <a href="{{ route('companies.materials.show', [$company, $material->id]) }}" class="nxmc-button nxmc-button-secondary" data-nxmc-navigate="open" data-nxmc-material="{{ $material->name }}" data-nxmc-sku="{{ $material->sku }}">
                                Open Material Record
                            </a>
                            <a href="{{ route('companies.materials.index', $company) }}" class="nxmc-button nxmc-button-secondary" data-nxmc-navigate="directory">
                                Material Catalog
                            </a>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=76" defer></script>
    <script src="{{ asset('js/nexorin-material-edit.js') }}?v=22a" defer></script>
@endpush
