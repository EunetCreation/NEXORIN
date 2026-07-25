@extends('layouts.app')

@section('title', $material->name)
@section('page-eyebrow', 'Material intelligence')
@section('page-heading', 'Material Record')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=64c-r4" rel="stylesheet">
    <link href="{{ asset('css/nexorin-material-catalog-refinement.css') }}?v=1" rel="stylesheet">
@endpush

@php
    $readinessScore = (int) ($catalogReadiness['score'] ?? 0);
    $readinessStatus = $catalogReadiness['status'] ?? 'incomplete';
    $readinessLabel = match ($readinessStatus) {
        'approval_ready' => 'Approval ready',
        'review_required' => 'Review required',
        default => 'Incomplete record',
    };
    $readinessTone = match ($readinessStatus) {
        'approval_ready' => 'is-green',
        'review_required' => 'is-amber',
        default => 'is-red',
    };
    $catalogStatus = str($material->catalog_status ?? 'draft')->replace('_', ' ')->title();
    $complianceStatus = str($material->compliance_status ?? 'needs_review')->replace('_', ' ')->title();
    $datasheetReady = (bool) ($catalogReadiness['has_datasheet'] ?? false);
    $certificateReady = (bool) ($catalogReadiness['has_verified_certificate'] ?? false);
    $documentPackReady = (bool) ($catalogReadiness['has_verified_document_pack'] ?? false);
    $priceReady = (bool) ($catalogReadiness['has_price_reference'] ?? false);
    $boqReady = $material->is_active && ($material->catalog_status ?? 'draft') === 'approved';
    $systems = collect($material->system_types ?? [])->map(fn ($value) => match ($value) {
        'on_grid' => 'On-grid',
        'hybrid' => 'Hybrid',
        'off_grid' => 'Off-grid',
        default => str($value)->replace('_', ' ')->title()->toString(),
    });
    $scales = collect($material->project_scales ?? [])->map(fn ($value) => str($value)->replace('_', ' ')->title()->toString());
    $roofTypes = collect($material->suitable_roof_types ?? [])->map(fn ($value) => match ($value) {
        'pitched' => 'Pitched Roof',
        'flat' => 'Flat Roof',
        'canopy' => 'Canopy / Carport',
        default => str($value)->replace('_', ' ')->title()->toString(),
    });
    $electricalPhase = match ($material->electrical_phase) {
        'single_phase' => 'Single Phase',
        'three_phase' => 'Three Phase',
        'dc' => 'DC',
        default => 'Not constrained',
    };
    $missingSpecs = collect($catalogReadiness['missing_specs'] ?? []);
    $missingCertificates = collect($catalogReadiness['missing_certificates'] ?? []);
    $missingDocuments = collect($catalogReadiness['missing_documents'] ?? []);
    $activeDatasheets = $material->datasheets->where('is_active', true);
    $activeCertificates = $material->certificates->where('is_active', true);
    $activeDocuments = $material->documents->where('is_active', true);
    $expiredEvidenceCount = collect($catalogReadiness['expired_certificates'] ?? [])->count() + collect($catalogReadiness['expired_documents'] ?? [])->count();
    $normalizedSpecMap = $material->specs->keyBy('spec_key');
    $schemaGroups = collect($specificationSchema['groups'] ?? []);
    $schemaKeys = $schemaGroups->flatMap(fn ($group) => collect($group['fields'] ?? [])->pluck('key'))->values();
    $requiredSchemaKeys = collect($specificationSchema['required_specs'] ?? []);
    $completedRequiredSpecs = $requiredSchemaKeys->filter(fn ($key) => filled($normalizedSpecMap->get($key)?->spec_value))->count();
    $customSpecs = $material->specs->reject(fn ($spec) => $schemaKeys->contains($spec->spec_key));
    $hasActiveDatasheet = $activeDatasheets->isNotEmpty();
    $parsedDatasheetCount = $activeDatasheets->filter(fn ($datasheet) => in_array($datasheet->extraction_status, ['parsed', 'reviewed', 'applied'], true))->count();
    $appliedDatasheetCount = $activeDatasheets->filter(fn ($datasheet) => $datasheet->applications->contains('status', 'applied'))->count();
    $materialIdentity = collect([
        $material->brand,
        $material->model,
        filled($material->nominal_power_wp) ? number_format((float) $material->nominal_power_wp, 2, '.', ',').' Wp' : null,
    ])->filter()->implode(' · ') ?: $material->name;
    $datasheetEvidenceLabel = match (true) {
        $datasheetReady => 'Parsed evidence ready',
        $hasActiveDatasheet => 'Review pending',
        default => 'Evidence missing',
    };
    $datasheetEvidenceCopy = match (true) {
        $datasheetReady => $parsedDatasheetCount.' active parsed reference'.($parsedDatasheetCount === 1 ? '' : 's'),
        $hasActiveDatasheet => $activeDatasheets->count().' source'.($activeDatasheets->count() === 1 ? '' : 's').' registered, not yet review-ready',
        default => 'No active vendor source registered',
    };
    [$nextActionHref, $nextActionLabel] = match (true) {
        ! $hasActiveDatasheet => ['#material-datasheets', 'Upload datasheet'],
        ! $datasheetReady => ['#material-datasheets', 'Review datasheet'],
        $missingSpecs->isNotEmpty() => ['#material-technical', 'Complete technical'],
        ! $certificateReady || ! $documentPackReady => ['#material-evidence', 'Complete evidence'],
        ! $priceReady => ['#material-pricing', 'Link pricing'],
        default => ['#material-governance', $boqReady ? 'Open governance' : 'Review approval'],
    };
@endphp

@section('content')
    <div class="nxmc-record" data-nxmc-tab-workspace>
        <section class="nxmc-record-hero nxmc-reveal">
            <div class="nxmc-record-copy">
                <span class="nxmc-kicker nxmc-record-kicker">Material intelligence record</span>
                <h2 data-nxmc-material-name>{{ $materialIdentity }}</h2>
                <p>
                    @if ($materialIdentity !== $material->name)
                        Catalog label: <strong>{{ $material->name }}</strong> ·
                    @endif
                    Technical identity, vendor evidence, commercial references, and catalog governance are maintained as one traceable material record.
                </p>
                <div class="nxmc-record-meta">
                    <span data-nxmc-material-sku>{{ $material->sku }}</span>
                    <span>{{ $material->category?->name ?? 'Uncategorized' }}</span>
                    <span>{{ $material->scope_label }}</span>
                    <span>{{ collect([$material->brand, $material->model])->filter()->implode(' · ') ?: 'Generic identity' }}</span>
                </div>
            </div>

            <aside class="nxmc-record-command" aria-label="Material readiness command panel">
                <div class="nxmc-readiness-ring" style="--nxmc-progress: {{ $readinessScore }};">
                    <strong class="nxmc-readiness-value">{{ $readinessScore }}<small>%</small></strong>
                </div>
                <div class="nxmc-command-copy">
                    <span>Catalog readiness</span>
                    <h3>{{ $readinessLabel }}</h3>
                    <p>{{ $boqReady ? 'Approved and active for controlled BOM and quotation use.' : 'Complete the remaining evidence and governance controls before BOQ release.' }}</p>
                    <div class="nxmc-command-actions">
                        <a href="{{ $nextActionHref }}" class="nxmc-button nxmc-button-primary">{{ $nextActionLabel }}</a>
                        <a href="{{ route('companies.materials.index', $company) }}" class="nxmc-button" data-nxmc-navigate="directory">Back to catalog</a>
                        @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                            <a href="{{ route('companies.materials.edit', [$company, $material->id]) }}" class="nxmc-button" data-nxmc-navigate="edit" data-nxmc-material="{{ $materialIdentity }}" data-nxmc-sku="{{ $material->sku }}">Edit material</a>
                        @endif
                    </div>
                </div>
            </aside>
        </section>

        @if ($errors->any())
            <div class="nxmc-alert nxmc-reveal" role="alert">
                <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
                <div><strong>Evidence submission requires correction.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            </div>
        @endif

        <nav class="nxmc-record-nav nxmc-reveal" aria-label="Material record sections" data-nxmc-tab-nav role="tablist">
            <a href="#material-overview" class="is-active" aria-current="location" aria-selected="true" aria-controls="material-overview" data-nxmc-section-tab role="tab"><i></i>Overview</a>
            <a href="#material-technical" aria-selected="false" aria-controls="material-technical" data-nxmc-section-tab role="tab"><i></i>Technical</a>
            <a href="#material-datasheets" aria-selected="false" aria-controls="material-datasheets" data-nxmc-section-tab role="tab"><i></i>Datasheets</a>
            <a href="#material-evidence" aria-selected="false" aria-controls="material-evidence" data-nxmc-section-tab role="tab"><i></i>Documents & certificates</a>
            <a href="#material-pricing" aria-selected="false" aria-controls="material-pricing" data-nxmc-section-tab role="tab"><i></i>Pricing</a>
            <a href="#material-governance" aria-selected="false" aria-controls="material-governance" data-nxmc-section-tab role="tab"><i></i>Compliance & audit</a>
            <button type="button" class="nxmc-record-nav-all" data-nxmc-show-all aria-pressed="false">Full record</button>
        </nav>

        <section class="nxmc-record-stat-grid nxmc-reveal" aria-label="Material governance summary">
            <article class="nxmc-record-stat">
                <span class="nxmc-record-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span>
                <div class="nxmc-record-stat-copy">
                    <span>Record state</span>
                    <strong>{{ $material->is_active ? 'Active' : 'Archived' }}</strong>
                    <small>{{ $material->is_active ? 'Available to active catalog workflows' : 'Retained for historical audit only' }}</small>
                </div>
            </article>
            <article class="nxmc-record-stat is-orange">
                <span class="nxmc-record-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                <div class="nxmc-record-stat-copy">
                    <span>BOQ availability</span>
                    <strong>{{ $boqReady ? 'Selectable' : 'Blocked' }}</strong>
                    <small>{{ $catalogStatus }} catalog governance state</small>
                </div>
            </article>
            <article class="nxmc-record-stat is-blue">
                <span class="nxmc-record-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/></svg></span>
                <div class="nxmc-record-stat-copy">
                    <span>Datasheet evidence</span>
                    <strong>{{ $datasheetEvidenceLabel }}</strong>
                    <small>{{ $datasheetEvidenceCopy }}</small>
                </div>
            </article>
            <article class="nxmc-record-stat is-violet">
                <span class="nxmc-record-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                <div class="nxmc-record-stat-copy">
                    <span>Price coverage</span>
                    <strong>{{ $priceReady ? 'Commercial link ready' : 'Price reference missing' }}</strong>
                    <small>{{ number_format($material->priceBookItems->count()) }} linked price-book record{{ $material->priceBookItems->count() === 1 ? '' : 's' }}</small>
                </div>
            </article>
        </section>

        <section id="material-overview" class="nxmc-panel nxmc-reveal" data-nxmc-tab-panel>
            <header class="nxmc-panel-head">
                <div>
                    <span class="nxmc-section-kicker">Identity and readiness</span>
                    <h3>Material governance overview</h3>
                    <p>Separate identity, applicability, record activity, approval readiness, compliance, and commercial evidence so no status is interpreted ambiguously.</p>
                </div>
                <span class="nxmc-panel-badge {{ $readinessTone }}">{{ $readinessLabel }}</span>
            </header>
            <div class="nxmc-panel-body">
                <div class="nxmc-overview-grid">
                    <div>
                        <div class="nxmc-information-grid">
                            <div class="nxmc-info-card"><span>Brand</span><strong>{{ $material->brand ?: 'Not recorded' }}</strong></div>
                            <div class="nxmc-info-card"><span>Model</span><strong>{{ $material->model ?: 'Not recorded' }}</strong></div>
                            <div class="nxmc-info-card"><span>Material type</span><strong>{{ $material->material_type ?: 'Not classified' }}</strong></div>
                            <div class="nxmc-info-card"><span>Cost group</span><strong>{{ $material->cost_group ?: 'Not assigned' }}</strong></div>
                            <div class="nxmc-info-card"><span>Unit of measure</span><strong class="nxmc-mono">{{ $material->uom }}</strong></div>
                            <div class="nxmc-info-card"><span>Default scope</span><strong>{{ str($material->default_scope_mode)->replace('_', ' ')->title() }}</strong></div>
                            <div class="nxmc-info-card"><span>System fit</span><strong>{{ $systems->isNotEmpty() ? $systems->implode(', ') : 'General' }}</strong></div>
                            <div class="nxmc-info-card"><span>Project scale</span><strong>{{ $scales->isNotEmpty() ? $scales->implode(', ') : 'Not constrained' }}</strong></div>
                            <div class="nxmc-info-card"><span>Roof / site fit</span><strong>{{ $roofTypes->isNotEmpty() ? $roofTypes->implode(', ') : 'Not constrained' }}</strong></div>
                            <div class="nxmc-info-card"><span>Electrical phase</span><strong>{{ $electricalPhase }}</strong></div>
                            <div class="nxmc-info-card"><span>Catalog ownership</span><strong>{{ $material->scope_label }}</strong></div>
                        </div>

                        @if ($material->description)
                            <div class="nxmc-copy-block">
                                <h4>Description</h4>
                                <p>{{ $material->description }}</p>
                            </div>
                        @endif
                        @if ($material->technical_notes)
                            <div class="nxmc-copy-block">
                                <h4>Technical notes</h4>
                                <p>{{ $material->technical_notes }}</p>
                            </div>
                        @endif
                    </div>

                    <aside class="nxmc-readiness-summary">
                        <div class="nxmc-readiness-summary-head">
                            <strong>Readiness evidence</strong>
                            <span>{{ $readinessScore }}%</span>
                        </div>
                        <div class="nxmc-readiness-track" aria-hidden="true"><span style="width: {{ $readinessScore }}%;"></span></div>
                        <div class="nxmc-check-list">
                            <div class="nxmc-check-item {{ $material->is_active ? 'is-complete' : 'is-blocked' }}">
                                <span class="nxmc-check-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg></span>
                                <span class="nxmc-check-copy"><strong>Record availability</strong><small>{{ $material->is_active ? 'Material is active.' : 'Archived materials cannot pass approval.' }}</small></span>
                                <span class="nxmc-check-state">{{ $material->is_active ? 'Ready' : 'Blocked' }}</span>
                            </div>
                            <div class="nxmc-check-item {{ $missingSpecs->isEmpty() ? 'is-complete' : 'is-blocked' }}">
                                <span class="nxmc-check-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8M10 19V4M16 19v-7M22 19H2"/></svg></span>
                                <span class="nxmc-check-copy"><strong>Technical specification set</strong><small>{{ $missingSpecs->isEmpty() ? 'Required category specifications are present.' : $missingSpecs->count().' required specification'.($missingSpecs->count() === 1 ? '' : 's').' missing.' }}</small></span>
                                <span class="nxmc-check-state">{{ $missingSpecs->isEmpty() ? 'Ready' : 'Review' }}</span>
                            </div>
                            <div class="nxmc-check-item {{ $datasheetReady ? 'is-complete' : 'is-blocked' }}">
                                <span class="nxmc-check-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4"/></svg></span>
                                <span class="nxmc-check-copy"><strong>Parsed datasheet evidence</strong><small>{{ $datasheetReady ? 'Active parsed evidence is available.' : ($hasActiveDatasheet ? 'A source is registered but still requires category, variant, or field review.' : 'Upload and review an active vendor datasheet.') }}</small></span>
                                <span class="nxmc-check-state">{{ $datasheetReady ? 'Ready' : ($hasActiveDatasheet ? 'Review' : 'Missing') }}</span>
                            </div>
                            <div class="nxmc-check-item {{ $certificateReady ? 'is-complete' : 'is-blocked' }}">
                                <span class="nxmc-check-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span>
                                <span class="nxmc-check-copy"><strong>Certificate coverage</strong><small>{{ $certificateReady ? 'Required certificate evidence is verified.' : $missingCertificates->count().' certificate requirement'.($missingCertificates->count() === 1 ? '' : 's').' unresolved.' }}</small></span>
                                <span class="nxmc-check-state">{{ $certificateReady ? 'Ready' : 'Review' }}</span>
                            </div>
                            <div class="nxmc-check-item {{ $priceReady ? 'is-complete' : 'is-blocked' }}">
                                <span class="nxmc-check-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/></svg></span>
                                <span class="nxmc-check-copy"><strong>Positive price reference</strong><small>{{ $priceReady ? 'At least one positive cost reference is linked.' : 'Commercial coverage is not yet established.' }}</small></span>
                                <span class="nxmc-check-state">{{ $priceReady ? 'Linked' : 'Missing' }}</span>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section id="material-technical" class="nxmc-panel nxmc-reveal" data-nxmc-tab-panel>
            <header class="nxmc-panel-head">
                <div>
                    <span class="nxmc-section-kicker">Component-specific engineering profile</span>
                    <h3>{{ $specificationSchema['title'] ?? 'Technical specifications' }}</h3>
                    <p>{{ $specificationSchema['description'] ?? 'Normalized component data used for compatibility, project selection, BOM pricing, and future SLD generation.' }}</p>
                </div>
                <span class="nxmc-panel-badge {{ $missingSpecs->isEmpty() ? 'is-green' : 'is-amber' }}">{{ $completedRequiredSpecs }} / {{ $requiredSchemaKeys->count() }} required</span>
            </header>
            <div class="nxmc-panel-body">
                <div class="nxspec-record-command">
                    <div>
                        <span class="nxmc-panel-kicker">Exact subcategory schema</span>
                        <strong>{{ $material->category?->name ?? 'Uncategorized component' }}</strong>
                        <p>The fields below are generated from the selected professional component type. They are the same keys used by readiness, parser review, project compatibility, and governed pricing trace.</p>
                    </div>
                    <div class="nxspec-record-kpis" aria-label="Technical specification completeness">
                        <div><span>Required</span><strong>{{ number_format($requiredSchemaKeys->count()) }}</strong></div>
                        <div><span>Complete</span><strong>{{ number_format($completedRequiredSpecs) }}</strong></div>
                        <div><span>Missing</span><strong>{{ number_format($missingSpecs->count()) }}</strong></div>
                        <div><span>Additional</span><strong>{{ number_format($customSpecs->count()) }}</strong></div>
                    </div>
                    @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                        <a href="{{ route('companies.materials.edit', [$company, $material->id]) }}#material-technical-input" class="nxmc-button nxmc-button-primary">
                            Edit technical profile
                        </a>
                    @endif
                </div>

                <div class="nxspec-record-groups">
                    @forelse ($schemaGroups as $groupIndex => $group)
                        @php
                            $groupFields = collect($group['fields'] ?? []);
                            $groupComplete = $groupFields->filter(fn ($field) => filled($normalizedSpecMap->get($field['key'])?->spec_value))->count();
                        @endphp
                        <details class="nxspec-record-group" @if ($groupIndex === 0) open @endif>
                            <summary class="nxspec-record-group-head">
                                <div>
                                    <span class="nxspec-group-index">{{ str_pad((string) ($groupIndex + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <div>
                                        <h4>{{ $group['label'] ?? 'Technical group' }}</h4>
                                        <p>{{ $group['description'] ?? 'Category-specific technical attributes.' }}</p>
                                    </div>
                                </div>
                                <span>{{ $groupComplete }} / {{ $groupFields->count() }} recorded</span>
                            </summary>
                            <div class="nxspec-record-grid">
                                @foreach ($groupFields as $field)
                                    @php
                                        $spec = $normalizedSpecMap->get($field['key']);
                                        $rawValue = $spec?->spec_value;
                                        $isPresent = filled($rawValue);
                                        $displayValue = $isPresent && ($field['input_type'] ?? null) === 'select'
                                            ? (($field['options'][(string) $rawValue] ?? $rawValue))
                                            : $rawValue;
                                        $unit = $spec?->spec_unit ?: ($field['unit'] ?? null);
                                    @endphp
                                    <article class="nxspec-record-field {{ $isPresent ? 'is-complete' : 'is-missing' }}">
                                        <div class="nxspec-record-field-head">
                                            <span>{{ $field['label'] ?? str($field['key'])->replace('_', ' ')->title() }}</span>
                                            <small>{{ ($field['required'] ?? false) ? 'Required' : 'Optional' }}</small>
                                        </div>
                                        <strong>{{ $isPresent ? $displayValue : 'Not recorded' }}{{ $isPresent && $unit ? ' '.$unit : '' }}</strong>
                                        <p>{{ $field['description'] ?? 'Governed component-specific technical value.' }}</p>
                                        <footer>
                                            <code>{{ $field['key'] }}</code>
                                            @if ($spec?->is_filterable)<span>Smart filter</span>@endif
                                        </footer>
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <div class="nxmc-empty" style="min-height:180px;">
                            <div>
                                <span class="nxmc-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                                <h3>No specific component schema selected</h3>
                                <p>Edit the material and choose an exact professional subcategory to load the correct technical intake.</p>
                            </div>
                        </div>
                    @endforelse
                </div>

                @if ($customSpecs->isNotEmpty())
                    <div class="nxspec-custom-zone">
                        <header>
                            <div>
                                <span class="nxmc-panel-kicker">Additional engineering attributes</span>
                                <h4>Custom and parser-only specifications</h4>
                            </div>
                            <span>{{ number_format($customSpecs->count()) }} records</span>
                        </header>
                        <div class="nxmc-spec-grid">
                            @foreach ($customSpecs as $spec)
                                <div class="nxmc-spec-card is-governed">
                                    <span>{{ $spec->spec_label }}</span>
                                    <strong>{{ $spec->spec_value ?? '—' }}{{ $spec->spec_unit ? ' '.$spec->spec_unit : '' }}</strong>
                                    <small>{{ $spec->spec_key }}{{ $spec->is_filterable ? ' · filterable' : '' }}</small>
                                    @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                                        <form method="POST" action="{{ route('companies.materials.specs.destroy', [$company, $material->id, $spec->id]) }}" data-confirm="Remove this additional normalized specification?" data-nxmc-action="specificationDelete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="nxmc-spec-remove" aria-label="Remove {{ $spec->spec_label }}">×</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                    <details class="nxmc-advanced-disclosure" @if (old('spec_key') || old('spec_value') || old('spec_label')) open @endif>
                        <summary>
                            <span><strong>Advanced normalization</strong><small>Add a vendor attribute outside the governed component schema.</small></span>
                            <span class="nxmc-advanced-disclosure-state">Optional</span>
                        </summary>
                    <form method="POST" action="{{ route('companies.materials.specs.store', [$company, $material->id]) }}" class="nxmc-spec-intake nxspec-advanced-intake" data-nxmc-action="specification">
                        @csrf
                        <div class="nxmc-spec-intake-copy">
                            <span class="nxmc-panel-kicker">Advanced normalization</span>
                            <strong>Add a non-schema engineering attribute</strong>
                            <p>Use this only for an additional vendor attribute that is not already available in the component-specific edit form. Existing schema fields should be maintained through Edit technical profile.</p>
                        </div>
                        <div class="nxmc-spec-intake-fields">
                            <div class="nxmc-field">
                                <label class="nxmc-label" for="spec_key">Specification key</label>
                                <input id="spec_key" class="nxmc-input" name="spec_key" placeholder="vendor_specific_attribute" required>
                            </div>
                            <div class="nxmc-field"><label class="nxmc-label" for="spec_value">Value</label><input id="spec_value" class="nxmc-input" name="spec_value" required></div>
                            <div class="nxmc-field"><label class="nxmc-label" for="spec_unit">Unit</label><input id="spec_unit" class="nxmc-input" name="spec_unit" placeholder="V, A, kW, mm"></div>
                            <div class="nxmc-field"><label class="nxmc-label" for="spec_label">Display label</label><input id="spec_label" class="nxmc-input" name="spec_label" placeholder="Auto from key"></div>
                            <label class="nxmc-spec-filterable"><input type="checkbox" name="is_filterable" value="1"><span>Available for smart filtering</span></label>
                            <button class="nxmc-button nxmc-button-primary" type="submit">Save additional spec</button>
                        </div>
                    </form>
                    </details>
                @endif

                @if ($missingSpecs->isNotEmpty())
                    <div class="nxmc-alert" style="margin-top: 10px;">
                        <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
                        <div><strong>Component-specific technical profile is incomplete.</strong><p>{{ $missingSpecs->count() }} required field{{ $missingSpecs->count() === 1 ? '' : 's' }} still need a reviewed value before catalog approval.</p></div>
                    </div>
                @endif
            </div>
        </section>

        <section id="material-datasheets" class="nxmc-panel nxmc-reveal" data-nxmc-tab-panel>
            <header class="nxmc-panel-head">
                <div>
                    <span class="nxmc-section-kicker">Vendor evidence and parser review</span>
                    <h3>Datasheets</h3>
                    <p>Store the source file, inspect extraction confidence and warnings, review normalized values, then apply approved fields to the technical catalog record.</p>
                </div>
                <span class="nxmc-panel-badge {{ $datasheetReady ? 'is-green' : 'is-amber' }}">{{ $datasheetReady ? 'Parsed evidence ready' : ($hasActiveDatasheet ? 'Review pending' : 'Evidence required') }}</span>
            </header>
            <div class="nxmc-panel-body">
                <div class="nxmc-parser-flow" aria-label="Datasheet governance workflow">
                    <div class="{{ $hasActiveDatasheet ? 'is-complete' : 'is-current' }}"><b>01</b><span><strong>Register source</strong><small>Store the controlled vendor file.</small></span></div>
                    <div class="{{ $parsedDatasheetCount > 0 ? 'is-complete' : ($hasActiveDatasheet ? 'is-current' : '') }}"><b>02</b><span><strong>Parse & qualify</strong><small>Confirm category, subtype, and variant.</small></span></div>
                    <div class="{{ $appliedDatasheetCount > 0 ? 'is-complete' : ($parsedDatasheetCount > 0 ? 'is-current' : '') }}"><b>03</b><span><strong>Review & apply</strong><small>Accept, keep, or reject each field.</small></span></div>
                    <div class="{{ $boqReady ? 'is-complete' : ($appliedDatasheetCount > 0 ? 'is-current' : '') }}"><b>04</b><span><strong>Govern & release</strong><small>Complete evidence, pricing, and approval.</small></span></div>
                </div>

                <div class="nxmc-upload-grid">
                    @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                        <details class="nxmc-upload-disclosure" @if (! $hasActiveDatasheet || $errors->any()) open @endif>
                            <summary>
                                <span><strong>{{ $hasActiveDatasheet ? 'Register another vendor source' : 'Register vendor evidence' }}</strong><small>PDF, TXT, CSV, or a controlled external reference</small></span>
                            </summary>
                            <form method="POST" action="{{ route('companies.materials.datasheets.store', [$company, $material->id]) }}" enctype="multipart/form-data" class="nxmc-upload-panel nxmc-upload-panel-form" data-nxmc-action="datasheet" data-nxmc-upload-max-bytes="{{ $datasheetUploadPolicy['effective_limit_bytes'] }}" data-nxmc-upload-max-label="{{ $datasheetUploadPolicy['effective_limit_label'] }}">
                                @csrf
                                <h4>Register vendor evidence</h4>
                                <p>Upload a local datasheet or provide a controlled external reference. Parsed values always require user review before they are applied.</p>
                            @if (! empty($datasheetUploadPolicy['warnings']))
                                <div class="nxmc-alert" style="margin:10px 0 2px;">
                                    <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
                                    <div>
                                        <strong>Server upload runtime requires attention.</strong>
                                        <ul>@foreach ($datasheetUploadPolicy['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                                    </div>
                                </div>
                            @endif
                            <div class="nxmc-form-grid">
                                <div class="nxmc-field">
                                    <label class="nxmc-label" for="datasheet_title">Evidence title</label>
                                    <input id="datasheet_title" class="nxmc-input" name="title" value="{{ old('title', $material->name.' Datasheet') }}" required>
                                </div>
                                <div class="nxmc-field nxmc-file-shell">
                                    <label class="nxmc-label" for="datasheet_file">Datasheet file</label>
                                    <input id="datasheet_file" class="nxmc-input" type="file" name="datasheet_file" accept=".pdf,.txt,.csv" data-nxmc-datasheet-file>
                                    <span class="nxmc-file-note">Effective server limit: {{ $datasheetUploadPolicy['effective_limit_label'] }}. Supported formats: PDF, TXT, and CSV. Scanned PDFs are flagged for manual review.</span>
                                    <span class="nxmc-field-error" data-nxmc-upload-client-error hidden></span>
                                </div>
                                @if (($specificationSchema['root_code'] ?? null) === 'PV_MODULE')
                                    <div class="nxmc-field">
                                        <label class="nxmc-label" for="datasheet_variant_power_wp">Target product variant</label>
                                        <div class="nxmc-input-with-unit">
                                            <input id="datasheet_variant_power_wp" class="nxmc-input" type="number" min="1" max="5000" step="0.01" name="variant_power_wp" value="{{ old('variant_power_wp', $material->nominal_power_wp) }}" placeholder="Example: 470">
                                            <span>Wp</span>
                                        </div>
                                        <span class="nxmc-file-note">Optional. Use this when one vendor PDF contains several power columns. The parser also tries to match the material model and nominal power automatically.</span>
                                    </div>
                                @endif
                                <div class="nxmc-field">
                                    <label class="nxmc-label" for="datasheet_external_url">External URL</label>
                                    <input id="datasheet_external_url" class="nxmc-input" name="external_url" placeholder="https://vendor.example/datasheet.pdf">
                                </div>
                                <div class="nxmc-field">
                                    <label class="nxmc-label" for="datasheet_revision">Revision</label>
                                    <input id="datasheet_revision" class="nxmc-input" name="revision" placeholder="Rev. A">
                                </div>
                                <div class="nxmc-field">
                                    <label class="nxmc-label" for="datasheet_issued_at">Issued date</label>
                                    <input id="datasheet_issued_at" class="nxmc-input" type="date" name="issued_at">
                                </div>
                                <div class="nxmc-field">
                                    <label class="nxmc-label" for="datasheet_notes">Review note</label>
                                    <input id="datasheet_notes" class="nxmc-input" name="notes" placeholder="Vendor, document context, or review note">
                                </div>
                            </div>
                            <div class="nxmc-form-actions" style="justify-content:flex-start;">
                                <button class="nxmc-button nxmc-button-primary" type="submit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v11"/><path d="m7 8 5-5 5 5"/><path d="M5 14v6h14v-6"/></svg>
                                    Upload and parse
                                </button>
                            </div>
                            </form>
                        </details>
                    @else
                        <div class="nxmc-upload-panel">
                            <h4>Evidence workspace is read-only</h4>
                            <p>This global reference or permission context does not allow datasheet changes. Existing parser evidence remains visible for traceability.</p>
                        </div>
                    @endif

                    <div class="nxmc-upload-panel nxmc-parser-governance-panel">
                        <span class="nxmc-panel-kicker">After parsing</span>
                        <h4>Review before the catalog changes</h4>
                        <p>Parser output is a proposed technical interpretation. A human must validate category, variant, values, units, and source trace before application.</p>
                        <div class="nxmc-check-list">
                            <div class="nxmc-check-item {{ $activeDatasheets->isNotEmpty() ? 'is-complete' : 'is-blocked' }}">
                                <span class="nxmc-check-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/></svg></span>
                                <span class="nxmc-check-copy"><strong>Source evidence</strong><small>{{ $activeDatasheets->count() }} active datasheet{{ $activeDatasheets->count() === 1 ? '' : 's' }} registered.</small></span>
                                <span class="nxmc-check-state">{{ $activeDatasheets->isNotEmpty() ? 'Present' : 'Missing' }}</span>
                            </div>
                            <div class="nxmc-check-item {{ $datasheetReady ? 'is-complete' : 'is-blocked' }}">
                                <span class="nxmc-check-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8M10 19V4M16 19v-7M22 19H2"/></svg></span>
                                <span class="nxmc-check-copy"><strong>Normalized extraction</strong><small>{{ $datasheetReady ? 'Parsed, reviewed, or applied evidence is available.' : 'No active parsed evidence has passed review state.' }}</small></span>
                                <span class="nxmc-check-state">{{ $datasheetReady ? 'Ready' : 'Pending' }}</span>
                            </div>
                            <div class="nxmc-check-item is-complete">
                                <span class="nxmc-check-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/></svg></span>
                                <span class="nxmc-check-copy"><strong>Human approval control</strong><small>Parsed values are applied only through an explicit controlled action.</small></span>
                                <span class="nxmc-check-state">Enforced</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nxmc-datasheet-list">
                    @forelse ($material->datasheets as $datasheet)
                        @include('materials.partials.datasheet-parser-card', ['datasheet' => $datasheet])
                    @empty
                        <div class="nxmc-empty" style="min-height:150px;">
                            <div><span class="nxmc-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4"/></svg></span><h3>No vendor evidence registered</h3><p>Approval requires active parsed datasheet evidence so engineering values remain traceable to a source document.</p></div>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        @include('materials.partials.evidence-center')

        <section id="material-pricing" class="nxmc-panel nxmc-reveal" data-nxmc-tab-panel>
            <header class="nxmc-panel-head">
                <div>
                    <span class="nxmc-section-kicker">Commercial coverage</span>
                    <h3>Price-book references</h3>
                    <p>Review supplier ownership, current unit cost, controlled selling price, availability, and validity for every commercial material link.</p>
                </div>
                <span class="nxmc-panel-badge {{ $priceReady ? 'is-green' : 'is-amber' }}">{{ $priceReady ? 'Positive cost linked' : 'Pricing required' }}</span>
            </header>
            <div class="nxmc-panel-body">
                @if (! auth()->user()->hasCompanyPermission($company, 'price.cost.view'))
                    <div class="nxmc-alert"><span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span><div><strong>Commercial references are restricted.</strong><p>Viewing cost and price-book values requires cost-price permission.</p></div></div>
                @elseif ($material->priceBookItems->isEmpty())
                    <div class="nxmc-empty-actions nxmc-empty-actions-top">
                        <a class="nxmc-button nxmc-button-primary nxmc-button-compact" href="{{ route('companies.price-books.index', $company) }}">Open price-book workspace</a>
                        @if (auth()->user()->hasCompanyPermission($company, 'price.cost.update'))
                            <a class="nxmc-button nxmc-button-secondary nxmc-button-compact" href="{{ route('companies.price-books.create', $company) }}">Create price book</a>
                        @endif
                    </div>
                    <div class="nxmc-empty" style="min-height:160px;"><div><span class="nxmc-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg></span><h3>No price-book references</h3><p>Link this material to a supplier price book to establish cost, selling price, availability, and validity evidence.</p></div></div>
                @else
                    <div class="nxmc-price-list">
                        @foreach ($material->priceBookItems as $item)
                            <article class="nxmc-price-card">
                                <div><span>Price book</span><strong>{{ $item->priceBook?->name ?? '—' }}</strong></div>
                                <div><span>Supplier</span><strong>{{ $item->supplier?->name ?? $item->priceBook?->supplier?->name ?? '—' }}</strong></div>
                                <div><span>Unit cost</span><strong class="nxmc-price-value">{{ $item->priceBook?->currency ?? 'IDR' }} {{ number_format((float) $item->unit_cost, 0, '.', ',') }}</strong></div>
                                <div><span>Selling price</span><strong class="nxmc-price-value">{{ $item->selling_price ? ($item->priceBook?->currency ?? 'IDR').' '.number_format((float) $item->selling_price, 0, '.', ',') : 'Auto / not set' }}</strong></div>
                                <div><span>Availability</span><strong>{{ str($item->availability_status)->replace('_', ' ')->title() }}</strong></div>
                                <div><span>Valid until</span><strong>{{ $item->valid_until?->format('d M Y') ?? 'Not limited' }}</strong></div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section id="material-governance" class="nxmc-panel nxmc-reveal" data-nxmc-tab-panel>
            <header class="nxmc-panel-head">
                <div>
                    <span class="nxmc-section-kicker">Compliance and record authority</span>
                    <h3>Governance & audit</h3>
                    <p>Certificate evidence, approval state, record ownership, and change metadata remain visible independently from active availability.</p>
                </div>
                <span class="nxmc-panel-badge {{ $complianceStatus === 'Compliant' ? 'is-green' : 'is-amber' }}">{{ $complianceStatus }}</span>
            </header>
            <div class="nxmc-panel-body">
                <div class="nxmc-governance-grid">
                    <div>
                        <span class="nxmc-panel-kicker">Professional governance profile</span>
                        <div class="nxmc-governance-profile" style="margin-top:8px;">
                            <div><span>Catalog domain</span><strong>{{ $catalogProfile['name'] ?? 'General Material' }}</strong></div>
                            <div><span>Required specifications</span><strong>{{ number_format(count($catalogProfile['specs'] ?? [])) }}</strong></div>
                            <div><span>Required certificates</span><strong>{{ number_format(count($catalogProfile['certificates'] ?? [])) }}</strong></div>
                            <div><span>Required documents</span><strong>{{ number_format(count($catalogProfile['documents'] ?? [])) }}</strong></div>
                        </div>
                        <div class="nxmc-alert {{ $documentPackReady && $certificateReady ? 'is-success' : '' }}" style="margin-top:10px;">
                            <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
                            <div><strong>{{ $documentPackReady && $certificateReady ? 'Professional evidence pack verified.' : 'Evidence pack still requires review.' }}</strong><p>Use the Documents & compliance center to register, verify, renew, or archive all supporting evidence.</p><div class="nxmc-inline-actions"><a class="nxmc-button nxmc-button-secondary nxmc-button-compact" href="#material-evidence">Open evidence center</a></div></div>
                        </div>

                        @if (! empty($catalogReadiness['notes']))
                            <div class="nxmc-alert" style="margin-top:10px;">
                                <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
                                <div><strong>Catalog readiness blockers</strong><ul>@foreach ($catalogReadiness['notes'] as $note)<li>{{ $note }}</li>@endforeach</ul><div class="nxmc-inline-actions"><a class="nxmc-button nxmc-button-primary nxmc-button-compact" href="{{ $nextActionHref }}">{{ $nextActionLabel }}</a></div></div>
                            </div>
                        @endif
                    </div>

                    <aside>
                        <span class="nxmc-panel-kicker">Record audit</span>
                        <div class="nxmc-audit-list" style="margin-top:8px;">
                            <div class="nxmc-audit-row"><span class="nxmc-audit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/></svg></span><span class="nxmc-audit-copy"><strong>Catalog governance</strong><small>{{ $catalogStatus }} · {{ $complianceStatus }}</small></span><span class="nxmc-audit-value">{{ $material->approved_at?->format('d M Y') ?? 'Not approved' }}</span></div>
                            <div class="nxmc-audit-row"><span class="nxmc-audit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/></svg></span><span class="nxmc-audit-copy"><strong>Record ownership</strong><small>{{ $material->scope_label }}</small></span><span class="nxmc-audit-value">{{ $material->company?->name ?? 'Global' }}</span></div>
                            <div class="nxmc-audit-row"><span class="nxmc-audit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg></span><span class="nxmc-audit-copy"><strong>Last material update</strong><small>{{ $material->updater?->name ?? 'System user' }}</small></span><span class="nxmc-audit-value">{{ $material->updated_at?->format('d M Y · H:i') ?? '—' }}</span></div>
                            <div class="nxmc-audit-row"><span class="nxmc-audit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/></svg></span><span class="nxmc-audit-copy"><strong>Last standards review</strong><small>Compliance evidence review</small></span><span class="nxmc-audit-value">{{ $material->last_reviewed_at?->format('d M Y · H:i') ?? 'Not reviewed' }}</span></div>
                        </div>

                        @if ($canModify)
                            <div class="nxmc-governance-actions">
                                @if (($catalogReadiness['can_approve'] ?? false) && ($material->catalog_status ?? 'draft') !== 'approved')
                                    <form method="POST" action="{{ route('companies.materials.approve', [$company, $material->id]) }}"
                                        data-confirm="Approve this material for controlled BOQ and quotation use? The record currently has {{ $readinessScore }}% readiness."
                                        data-confirm-tone="decision" data-confirm-title="Approve material for BOQ use?" data-confirm-kicker="Catalog release authority"
                                        data-confirm-label="Approve for BOQ" data-confirm-cancel-label="Cancel"
                                        data-confirm-control="Technical, evidence, pricing, and governance gates have been evaluated"
                                        data-confirm-result="Material becomes selectable in controlled BOM and quotation workflows"
                                        data-nxmc-action="approve">
                                        @csrf
                                        @method('PATCH')
                                        <button class="nxmc-button nxmc-button-primary" type="submit">Approve material</button>
                                    </form>
                                @endif
                                @if ($material->is_active)
                                    <form method="POST" action="{{ route('companies.materials.archive', [$company, $material->id]) }}" data-confirm="Archive this material record while preserving its audit history?" data-nxmc-action="archive">
                                        @csrf
                                        @method('DELETE')
                                        <button class="nxmc-button nxmc-button-danger" type="submit">Archive record</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('companies.materials.activate', [$company, $material->id]) }}" data-nxmc-action="activate">
                                        @csrf
                                        @method('PATCH')
                                        <button class="nxmc-button nxmc-button-primary" type="submit">Activate record</button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <div class="nxmc-alert is-success" style="margin-top:10px;"><span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg></span><div><strong>Global reference record</strong><p>Create a company-specific material to manage custom specifications, price references, and availability rules.</p></div></div>
                        @endif
                    </aside>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=64b-r4" defer></script>
@endpush

