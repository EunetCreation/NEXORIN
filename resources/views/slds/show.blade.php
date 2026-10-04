@extends('layouts.app')

@section('title', 'Single-Line Diagram')
@section('page-eyebrow', 'Engineering drawing control')
@section('page-heading', 'Single-Line Diagram')

@php
    $warnings = $revision?->warnings ?? [];
    $warningCounts = collect($warnings)->countBy(fn ($warning) => $warning['severity'] ?? 'info');
    $electricalValidation = is_array($graph['electrical_validation'] ?? null) ? $graph['electrical_validation'] : [];
    $checkSummary = is_array($electricalValidation['summary'] ?? null) ? $electricalValidation['summary'] : [];
    $checkStatus = (string) ($electricalValidation['status'] ?? $checkSummary['status'] ?? 'pending');
    $hasBlockingElectricalErrors = (bool) ($electricalValidation['blocking'] ?? $checkSummary['blocking'] ?? false);
    $engineeringController = is_array($graph['engineering_controller'] ?? null) ? $graph['engineering_controller'] : [];
    $controllerSummary = is_array($engineeringController['summary'] ?? null) ? $engineeringController['summary'] : [];
    $controllerConflicts = collect(is_array($engineeringController['conflicts'] ?? null) ? $engineeringController['conflicts'] : []);
    $hasBlockingEngineeringErrors = (bool) ($engineeringController['blocking'] ?? false);
    $controlledExportBlocked = $hasBlockingElectricalErrors || $hasBlockingEngineeringErrors;
    $controlledExportErrorCount = (int) data_get($checkSummary, 'error', 0) + (int) data_get($controllerSummary, 'error', 0);
    $controlledExportBlockTitle = $hasBlockingEngineeringErrors
        ? 'Synchronize the engineering source before controlled export'
        : 'Resolve electrical-rule errors before controlled export';
    $controlledExportBlockDetail = $hasBlockingEngineeringErrors
        ? ((string) ($engineeringController['message'] ?? 'The controlled drawing is not synchronized with its current engineering source.'))
        : sprintf('APP67I found %d electrical-rule error(s).', (int) data_get($checkSummary, 'error', 0));
    $meta = $graph['meta'] ?? [];
    $summary = $meta['summary'] ?? [];
    $source = $revision?->source_snapshot ?? [];
    $isApproved = $revision?->isApproved() ?? false;
    $isLocked = $revision?->isLocked() ?? false;
    $isStale = $revision && (($source['fingerprint'] ?? null) !== app(\App\Services\Sld\SldGraphBuilder::class)->sourceFingerprint($project));
    $paperSummary = is_array($paperSet['summary'] ?? null) ? $paperSet['summary'] : [];
    $paperSheets = is_array($paperSet['sheets'] ?? null) ? $paperSet['sheets'] : [];
    $paperModeLabel = match($paperSummary['mode'] ?? null) {
        'multi' => 'Controlled multi-sheet set',
        'catalog' => 'Symbol register',
        'single' => 'Single A3 sheet',
        default => 'Paper space pending',
    };
    $documentControl = is_array($revision?->document_metadata) ? $revision->document_metadata : [];
    $drawingRegister = is_array(data_get($documentControl, 'drawing.register')) ? data_get($documentControl, 'drawing.register') : [];
    $issueLabel = $revision?->issue_status_label ?? 'Draft';
    $exportManifest = is_array($revision?->export_manifest) ? $revision->export_manifest : [];
    $latestExport = is_array($exportManifest['latest'] ?? null) ? $exportManifest['latest'] : [];
    $releaseGates = is_array($releaseAssessment['gates'] ?? null) ? $releaseAssessment['gates'] : [];
    $releaseSummary = is_array($releaseAssessment['summary'] ?? null) ? $releaseAssessment['summary'] : [];
    $releaseStatus = (string) ($releaseAssessment['status'] ?? 'blocked');
    $equipmentRegister = is_array(data_get($documentControl, 'equipment_register')) ? data_get($documentControl, 'equipment_register') : [];
    $allowedTargets = is_array($releaseAssessment['allowed_targets'] ?? null) ? $releaseAssessment['allowed_targets'] : [];
    $formatMetric = static function ($value, int $decimals = 2): string {
        if (! is_numeric($value)) return '—';
        $formatted = number_format((float) $value, $decimals, '.', ',');
        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    };
    $materialIncompleteCount = collect($equipmentRegister)->sum(function ($group): int {
        $items = collect(is_array(data_get($group, 'items')) ? data_get($group, 'items') : []);
        if ((string) data_get($group, 'status') !== 'BOM linked') return max(1, $items->count());
        return $items->filter(fn ($item) => blank(data_get($item, 'material_id')) || blank(data_get($item, 'datasheet_id')))->count();
    });
    $checkerMissing = blank(data_get($documentControl, 'people.checked_by'));
    $approverMissing = blank(data_get($documentControl, 'people.approved_by'));
    $actionItems = collect();
    if ($errors->has('sld')) {
        $actionItems->push(['tone' => 'danger', 'title' => 'Controlled download failed', 'detail' => $errors->first('sld'), 'action' => 'issues']);
    }
    if ($isStale || $hasBlockingEngineeringErrors) {
        $actionItems->push(['tone' => 'danger', 'title' => 'Engineering source requires synchronization', 'detail' => (string) ($engineeringController['message'] ?? 'Technical Calculation or downstream engineering data changed after this revision was generated.'), 'action' => 'sync']);
    }
    if ($hasBlockingElectricalErrors) {
        $actionItems->push(['tone' => 'danger', 'title' => 'Electrical checks contain blocking errors', 'detail' => sprintf('%d error(s) must be resolved before controlled export.', (int) data_get($checkSummary, 'error', 0)), 'action' => 'issues']);
    }
    if ($materialIncompleteCount > 0) {
        $actionItems->push(['tone' => 'warning', 'title' => 'Material traceability is incomplete', 'detail' => $materialIncompleteCount.' equipment or datasheet link(s) require review.', 'action' => 'traceability']);
    }
    if ($checkerMissing && ! $revision?->isIssued()) {
        $actionItems->push(['tone' => 'warning', 'title' => 'Checker has not been assigned', 'detail' => 'Independent checking authority is required before controlled issue.', 'action' => 'release']);
    }
    $actionItems = $actionItems->take(4)->values();
    $moduleCount = (int) data_get($summary, 'module_count', 0);
    $stringCount = (int) data_get($summary, 'physical_string_count', data_get($summary, 'string_count', 0));
    $inverterCount = (int) data_get($summary, 'physical_inverter_count', data_get($summary, 'inverter_count', 0));
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-sld.css') }}?v=landing-001" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-sld.js') }}?v=landing-001" defer></script>
@endpush

@section('content')
    <main
        class="nxsld-review-workspace"
        data-sld-workspace
        data-sld-review-workspace
        data-sld-sync-url="{{ $revision ? route('companies.projects.sld.controller.sync', [$company, $project]) : '' }}"
    >
        <header class="nxsld-page-header">
            <div class="nxsld-page-heading">
                <nav class="nxsld-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('companies.projects.show', [$company, $project]) }}">Projects</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('companies.projects.show', [$company, $project]) }}">{{ $project->name }}</a>
                    <span aria-hidden="true">/</span>
                    <strong>SLD Review</strong>
                </nav>
                <div class="nxsld-title-row">
                    <div>
                        <span class="nxsld-eyebrow">Controlled electrical drawing</span>
                        <h1>Single-Line Diagram</h1>
                        <p>{{ $project->name }}</p>
                    </div>
                    @if ($revision)
                        <div class="nxsld-status-badges" aria-label="Drawing status">
                            <span class="is-neutral">Revision <b>R{{ str_pad((string) $revision->revision, 2, '0', STR_PAD_LEFT) }}</b></span>
                            <span class="is-{{ $revision->status_tone }}">{{ strtoupper($revision->status_label) }}</span>
                            <span class="is-danger">{{ data_get($exportPreview, 'disposition', 'NOT FOR CONSTRUCTION') }}</span>
                        </div>
                    @endif
                </div>
                <div class="nxsld-source-meta">
                    <span>{{ $revision?->generated_at?->format('d M Y, H:i') ?? 'Drawing not generated' }}</span>
                    <i aria-hidden="true"></i>
                    <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}">Technical Calculation {{ data_get($source, 'technical_calculation.id') ? '#'.data_get($source, 'technical_calculation.id') : '' }}</a>
                    @if ($revision?->generator)
                        <i aria-hidden="true"></i><span>Generated by {{ $revision->generator->name }}</span>
                    @endif
                </div>
            </div>

            <div class="nxsld-header-actions" aria-label="SLD actions">
                @if ($revision)
                    <a class="nxsld-action-button is-primary" href="{{ route('companies.projects.sld.edit', [$company, $project]) }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 16 10-10 4 4L8 20H4zM13 7l4 4"/><path d="M4 20h16"/></svg>
                        {{ $revision->status === 'draft' ? 'Edit Drawing' : 'Open Drawing' }}
                    </a>
                    @if ($revision->status === 'draft' && ! $isLocked)
                        <button class="nxsld-action-button" type="button" data-sld-sync>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7h-6V1M4 17h6v6"/><path d="M5.1 9A8 8 0 0 1 18 4l2 3M18.9 15A8 8 0 0 1 6 20l-2-3"/></svg>
                            Synchronize
                        </button>
                    @endif
                    <button class="nxsld-action-button" type="button" data-sld-open-tab="issues">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 6v5c0 4.6 2.7 7.9 8 10 5.3-2.1 8-5.4 8-10V6z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>
                        Run Checks
                        @if ($controlledExportErrorCount > 0 || count($warnings) > 0)<span class="nxsld-action-count">{{ $controlledExportErrorCount + count($warnings) }}</span>@endif
                    </button>
                @endif
                @if ($canGenerate)
                    <form method="POST" action="{{ route('companies.projects.sld.generate', [$company, $project]) }}"
                          data-confirm="Generate a controlled SLD revision from the current technical calculation?"
                          data-confirm-tone="decision"
                          data-confirm-title="{{ $revision ? 'Generate a new SLD revision?' : 'Generate the first SLD revision?' }}"
                          data-confirm-kicker="Drawing revision control"
                          data-confirm-label="{{ $revision ? 'Generate new revision' : 'Generate SLD' }}"
                          data-confirm-cancel-label="Keep current revision"
                          data-confirm-control="Current calculation and topology snapshot will be fingerprinted"
                          data-confirm-result="{{ $revision ? 'R'.($revision->revision + 1).' becomes the active draft; R'.$revision->revision.' remains in history' : 'Revision R1 is created as an engineering draft' }}">
                        @csrf
                        <input type="hidden" name="notes" value="Generated from the current technical calculation workspace.">
                        <button class="nxsld-action-button" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>
                            {{ $revision ? 'New Revision' : 'Generate SLD' }}
                        </button>
                    </form>
                @endif
                @if ($revision)
                    @if ($controlledExportBlocked)
                        <button class="nxsld-action-button is-export" type="button" data-sld-export-blocked>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h4"/></svg>
                            Export PDF
                            <svg class="nxsld-chevron" viewBox="0 0 24 24"><path d="m8 10 4 4 4-4"/></svg>
                        </button>
                    @else
                        <a class="nxsld-action-button is-export" href="{{ route('companies.projects.sld.pdf', [$company, $project]) }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h4"/></svg>
                            Export PDF
                        </a>
                    @endif
                @endif
            </div>
        </header>

        @if (! $revision)
            <section class="nxsld-empty-state">
                <span class="nxsld-empty-icon" aria-hidden="true"><svg viewBox="0 0 96 96" fill="none"><path d="M14 48h19m30 0h19M33 48l18-19M51 29v38M51 67l12-19" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><circle cx="48" cy="48" r="39" stroke="currentColor" stroke-width="2" opacity=".25"/></svg></span>
                <div><span class="nxsld-eyebrow">Drawing workspace ready</span><h2>Create the first controlled SLD revision.</h2><p>Generate a topology from Technical Calculation, then refine its layout in the precision drafting editor. The calculation remains the engineering source of truth.</p></div>
                @if ($canGenerate)
                    <form method="POST" action="{{ route('companies.projects.sld.generate', [$company, $project]) }}">
                        @csrf
                        <input type="hidden" name="notes" value="Initial controlled SLD generated from Technical Calculation.">
                        <button class="nxsld-action-button is-primary" type="submit">Generate SLD</button>
                    </form>
                @endif
            </section>
        @else
            <section class="nxsld-status-strip" aria-label="Engineering status summary">
                <button type="button" data-sld-open-tab="traceability" class="is-{{ data_get($source, 'technical_calculation.result_status') === 'verified' ? 'success' : 'warning' }}">
                    <span class="nxsld-status-icon">{{ data_get($source, 'technical_calculation.result_status') === 'verified' ? '✓' : '!' }}</span>
                    <span><small>Calculation</small><strong>{{ str(data_get($source, 'technical_calculation.result_status', 'Pending'))->headline() }}</strong></span>
                </button>
                <button type="button" data-sld-open-tab="issues" class="is-{{ $isStale || $hasBlockingEngineeringErrors ? 'warning' : 'success' }}">
                    <span class="nxsld-status-icon">{{ $isStale || $hasBlockingEngineeringErrors ? '!' : '✓' }}</span>
                    <span><small>Source Sync</small><strong>{{ $isStale || $hasBlockingEngineeringErrors ? data_get($controllerSummary, 'total', 1).' change(s)' : 'Current' }}</strong></span>
                </button>
                <button type="button" data-sld-open-tab="issues" class="is-{{ $hasBlockingElectricalErrors ? 'danger' : ($checkStatus === 'pending' ? 'warning' : 'success') }}">
                    <span class="nxsld-status-icon">{{ $hasBlockingElectricalErrors ? '!' : '✓' }}</span>
                    <span><small>Electrical Check</small><strong>{{ str($checkStatus)->headline() }}</strong></span>
                </button>
                <button type="button" data-sld-open-tab="traceability" class="is-{{ $materialIncompleteCount > 0 ? 'warning' : 'success' }}">
                    <span class="nxsld-status-icon">{{ $materialIncompleteCount > 0 ? '!' : '✓' }}</span>
                    <span><small>Materials Link</small><strong>{{ $materialIncompleteCount > 0 ? $materialIncompleteCount.' incomplete' : 'Complete' }}</strong></span>
                </button>
                <button type="button" data-sld-open-tab="release" class="is-{{ $releaseStatus === 'ready' ? 'success' : 'danger' }}">
                    <span class="nxsld-status-icon">{{ $releaseStatus === 'ready' ? '✓' : '!' }}</span>
                    <span><small>Release Status</small><strong>{{ data_get($exportPreview, 'disposition', 'Not for Construction') }}</strong></span>
                </button>
            </section>

            <section class="nxsld-main-workspace">
                <div class="nxsld-drawing-card" data-sld-drawing-shell>
                    <nav class="nxsld-sheet-navigation" aria-label="Drawing sheets">
                        @foreach ($paperSheets as $index => $sheet)
                            <button type="button" class="{{ $index === 0 ? 'is-active' : '' }}" data-sld-sheet-tab="{{ $index }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                                <span>{{ str_pad((string) ($sheet['number'] ?? $index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <strong>{{ $sheet['title'] ?? 'Drawing sheet' }}</strong>
                            </button>
                        @endforeach
                        @if ($paperSheets === [])
                            <button type="button" class="is-active" aria-pressed="true"><span>01</span><strong>Main SLD</strong></button>
                        @endif
                        <span class="nxsld-sheet-more" aria-hidden="true">•••</span>
                    </nav>

                    <div class="nxsld-cad-stage" data-sld-stage tabindex="0" aria-label="SLD paper-space preview. Drag to pan and use keyboard controls to zoom.">
                        <div class="nxsld-stage-inner" data-sld-stage-inner>
                            @foreach ($svgs as $index => $pageSvg)
                                <article class="nxsld-sheet-page {{ $index === 0 ? 'is-active' : '' }}" data-sld-sheet-page="{{ $index }}" aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                                    {!! $pageSvg !!}
                                </article>
                            @endforeach
                        </div>
                        <div class="nxsld-cad-minimap" aria-hidden="true"><span></span></div>
                        <div class="nxsld-cad-controls" role="toolbar" aria-label="Drawing preview controls">
                            <button type="button" data-sld-zoom="out" aria-label="Zoom out">−</button>
                            <output data-sld-zoom-label>Fit</output>
                            <button type="button" data-sld-zoom="in" aria-label="Zoom in">+</button>
                            <button type="button" data-sld-zoom="fit">Fit</button>
                            <button type="button" data-sld-fit-width>Fit Width</button>
                            <button type="button" data-sld-fullscreen aria-label="Toggle fullscreen"><svg viewBox="0 0 24 24"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5m13 5h5v-5"/></svg></button>
                            <button type="button" data-sld-monochrome><svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 0 18V3Z"/></svg>Monochrome</button>
                            @if (! $controlledExportBlocked)
                                <a href="{{ route('companies.projects.sld.pdf', [$company, $project]) }}" aria-label="Download current PDF"><svg viewBox="0 0 24 24"><path d="M12 3v12m-4-4 4 4 4-4M5 21h14"/></svg></a>
                            @else
                                <button type="button" data-sld-export-blocked aria-label="PDF export blocked"><svg viewBox="0 0 24 24"><path d="M12 3v12m-4-4 4 4 4-4M5 21h14"/></svg></button>
                            @endif
                        </div>
                    </div>
                </div>

                <aside class="nxsld-action-rail">
                    <section class="nxsld-action-required">
                        <header><span>Action Required</span><b>{{ $actionItems->count() }}</b></header>
                        @forelse ($actionItems as $item)
                            <article class="is-{{ $item['tone'] }}">
                                <span class="nxsld-issue-icon">{{ $item['tone'] === 'danger' ? '!' : '△' }}</span>
                                <div><strong>{{ $item['title'] }}</strong><p>{{ $item['detail'] }}</p></div>
                                @if ($item['action'] === 'sync')
                                    <button type="button" data-sld-sync>Synchronize</button>
                                @else
                                    <button type="button" data-sld-open-tab="{{ $item['action'] }}">Review</button>
                                @endif
                            </article>
                        @empty
                            <div class="nxsld-action-clear"><span>✓</span><div><strong>Ready for engineering review</strong><p>No blocking drawing issues are open.</p></div></div>
                        @endforelse
                        <button class="nxsld-view-all" type="button" data-sld-open-tab="issues">View all issues <span>→</span></button>
                    </section>

                    <section class="nxsld-drawing-summary">
                        <header>Drawing Summary</header>
                        <div>
                            <span><b>{{ $formatMetric(data_get($summary, 'installed_pv_kw')) }} kWp</b><small>System Capacity</small></span>
                            <span><b>{{ $moduleCount ?: '—' }}</b><small>PV Modules</small></span>
                            <span><b>{{ $stringCount ?: '—' }}</b><small>PV Strings</small></span>
                            <span><b>{{ $inverterCount ?: '—' }}</b><small>Inverters</small></span>
                            <span><b>{{ count($svgs) }}</b><small>Controlled Sheets</small></span>
                            <span><b>R{{ str_pad((string) $revision->revision, 2, '0', STR_PAD_LEFT) }}</b><small>Drawing Revision</small></span>
                        </div>
                    </section>
                </aside>
            </section>

            <nav class="nxsld-detail-tabs" aria-label="SLD review details">
                @foreach (['overview' => 'Overview', 'issues' => 'Issues', 'traceability' => 'Traceability', 'revisions' => 'Revisions', 'release' => 'Release'] as $key => $label)
                    <button type="button" class="{{ $key === 'overview' ? 'is-active' : '' }}" data-sld-review-tab="{{ $key }}" aria-pressed="{{ $key === 'overview' ? 'true' : 'false' }}">
                        {{ $label }}
                        @if ($key === 'issues' && ($controlledExportErrorCount + count($warnings)) > 0)<span>{{ $controlledExportErrorCount + count($warnings) }}</span>@endif
                    </button>
                @endforeach
            </nav>

            <div class="nxsld-detail-panels">
                @include('slds.partials.overview')
                @include('slds.partials.issues')
                @include('slds.partials.traceability')
                @include('slds.partials.revisions')
                @include('slds.partials.release')
            </div>

            <footer class="nxsld-controlled-footer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                This is a controlled document. Changes are tracked and require authorization.
            </footer>

            <div class="nxsld-export-dialog" data-sld-export-dialog hidden aria-hidden="true">
                <section role="dialog" aria-modal="true" aria-labelledby="nxsldExportTitle">
                    <button class="nxsld-dialog-close" type="button" data-sld-export-close aria-label="Close">×</button>
                    <span class="nxsld-dialog-icon">!</span>
                    <h2 id="nxsldExportTitle">PDF export is currently blocked</h2>
                    <p>{{ $controlledExportBlockDetail }}</p>
                    <div><strong>{{ $controlledExportErrorCount }} blocking error(s)</strong><span>Review engineering issues, synchronize the draft, then rerun checks.</span></div>
                    <footer><button type="button" data-sld-export-close>Close</button><button type="button" class="is-primary" data-sld-open-tab="issues" data-sld-export-close>Review blockers</button></footer>
                </section>
            </div>
        @endif
    </main>
@endsection
