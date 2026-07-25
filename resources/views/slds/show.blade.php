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
    $nodes = collect($graph['nodes'] ?? []);
    $firstNode = $nodes->first();
    $isApproved = $revision?->isApproved() ?? false;
    $isLocked = $revision?->isLocked() ?? false;
    $isStale = $revision && (($source['fingerprint'] ?? null) !== app(\App\Services\Sld\SldGraphBuilder::class)->sourceFingerprint($project));
    $paperSummary = is_array($paperSet['summary'] ?? null) ? $paperSet['summary'] : [];
    $paperSheets = is_array($paperSet['sheets'] ?? null) ? $paperSet['sheets'] : [];
    $paperModeLabel = match($paperSummary['mode'] ?? null) {
        'multi' => 'Multi-sheet set',
        'catalog' => 'Symbol register',
        'single' => 'Single A3 sheet',
        default => 'Paper space pending',
    };
    $documentControl = is_array($revision?->document_metadata) ? $revision->document_metadata : [];
    $drawingRegister = is_array(data_get($documentControl, 'drawing.register')) ? data_get($documentControl, 'drawing.register') : [];
    $issueStatus = (string) ($revision?->issue_status ?: 'draft');
    $statusStep = match(true) {
        in_array($issueStatus, ['issued_for_tender', 'issued_for_construction', 'as_built'], true) => 5,
        $revision?->status === 'approved' => 4,
        $revision?->status === 'checked' => 3,
        $revision?->status === 'needs_review' => 2,
        default => 1,
    };
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
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-sld.css') }}?v=5" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-sld.js') }}?v=5" defer></script>
@endpush

@section('content')
    <main class="nxsld-workspace" data-sld-workspace>
        <header class="nxsld-hero">
            <div class="nxsld-hero-grid" aria-hidden="true"></div>
            <div class="nxsld-hero-copy">
                <div class="nxsld-kicker-row">
                    <a class="nxsld-back" href="{{ route('companies.projects.show', [$company, $project]) }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M19 12H5M10 7l-5 5 5 5"/></svg>
                        Project cockpit
                    </a>
                    <span class="nxsld-kicker">Controlled electrical drawing</span>
                </div>
                <h1>{{ $project->name }}</h1>
                <p>One auditable drawing surface for system topology, protection boundaries, source ratings, engineering notices, and revision approval.</p>
                <div class="nxsld-meta">
                    <span class="nxsld-mono">{{ $project->project_number }}</span>
                    <span>{{ $meta['system_label'] ?? 'Topology not generated' }}</span>
                    <span>{{ $revision ? 'Revision R'.$revision->revision.' · '.$issueLabel : 'No revision' }}</span>
                </div>
            </div>

            <div class="nxsld-hero-actions">
                @if ($revision)
                    <a class="nxsld-button nxsld-button--ghost" href="{{ route('companies.projects.sld.edit', [$company, $project]) }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 16 10-10 4 4L8 20H4zM13 7l4 4"/><path d="M4 20h16"/></svg>
                        {{ $revision->status === 'draft' ? 'Edit drawing' : 'Open editor' }}
                    </a>
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
                        <button class="nxsld-button nxsld-button--primary" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 4v11m-4-4 4 4 4-4M5 20h14"/></svg>
                            {{ $revision ? 'New revision' : 'Generate SLD' }}
                        </button>
                    </form>
                @endif
                <a class="nxsld-button nxsld-button--ghost" href="{{ route('companies.technical-calculations.show', [$company, $project]) }}">
                    Calculation source
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"/></svg>
                </a>
            </div>
        </header>

        @if (! $revision)
            <section class="nxsld-empty" data-sld-reveal>
                <div class="nxsld-empty-symbol" aria-hidden="true">
                    <svg viewBox="0 0 96 96" fill="none"><path d="M14 48h19m30 0h19M33 48l18-19M51 29v38M51 67l12-19" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><circle cx="48" cy="48" r="39" stroke="currentColor" stroke-width="2" opacity=".25"/></svg>
                </div>
                <div class="nxsld-empty-copy">
                    <span class="nxsld-section-kicker">Drawing workspace ready</span>
                    <h2>Create your first single-line diagram.</h2>
                    <p>Generate a controlled topology from Technical Calculation, then refine it in the precision drafting editor. The calculation remains the engineering source of truth.</p>
                    <div class="nxsld-empty-actions">
                        @if ($canGenerate)
                            <form method="POST" action="{{ route('companies.projects.sld.generate', [$company, $project]) }}"
                                  data-confirm="Generate the first controlled SLD from the current technical calculation?"
                                  data-confirm-tone="decision" data-confirm-title="Create the first SLD revision?"
                                  data-confirm-kicker="First drawing setup" data-confirm-label="Create SLD"
                                  data-confirm-cancel-label="Not yet" data-confirm-control="Current calculation and topology are fingerprinted"
                                  data-confirm-result="Revision R1 opens as an editable engineering draft">
                                @csrf
                                <input type="hidden" name="notes" value="Generated from the current technical calculation workspace.">
                                <button class="nxsld-button nxsld-button--primary nxsld-button--large" type="submit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                    Create New SLD
                                </button>
                            </form>
                        @endif
                        <a class="nxsld-button nxsld-button--ghost nxsld-button--large" href="{{ route('companies.technical-calculations.show', [$company, $project]) }}">Review calculation source</a>
                    </div>
                    <div class="nxsld-empty-gates">
                        <span><b>01</b> Current calculation</span>
                        <i></i>
                        <span><b>02</b> Generated draft</span>
                        <i></i>
                        <span><b>03</b> Engineer approval</span>
                    </div>
                </div>
            </section>
        @else
            <section class="nxsld-command" data-sld-reveal>
                <div class="nxsld-command-status">
                    <span class="nxsld-status-orb nxsld-status-orb--{{ $revision->status_tone }}"></span>
                    <div>
                        <span class="nxsld-section-kicker">Active drawing</span>
                        <strong>{{ $revision->status_label }} · R{{ $revision->revision }}</strong>
                        <small>{{ $revision->generated_at?->format('d M Y, H:i') }}{{ $revision->generator ? ' · '.$revision->generator->name : '' }}</small>
                    </div>
                </div>

                <div class="nxsld-lifecycle" aria-label="Drawing approval and issue progress">
                    @foreach ([1 => 'Draft', 2 => 'Review', 3 => 'Checked', 4 => 'Approved', 5 => 'Issued'] as $step => $label)
                        <span class="{{ $statusStep >= $step ? 'is-complete' : '' }} {{ $statusStep === $step ? 'is-current' : '' }}"><b>0{{ $step }}</b>{{ $label }}</span>
                        @if ($step < 5)<i class="{{ $statusStep > $step ? 'is-complete' : '' }}"></i>@endif
                    @endforeach
                </div>

                <div class="nxsld-command-actions">
                    @if ($controlledExportBlocked)
                        <span class="nxsld-icon-action is-disabled" title="{{ $controlledExportBlockTitle }}" aria-disabled="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m-4-4 4 4 4-4M5 21h14"/></svg>
                            <span><strong>SVG</strong><small>Blocked by {{ $controlledExportErrorCount }} error(s)</small></span>
                        </span>
                        <span class="nxsld-icon-action is-disabled" title="{{ $controlledExportBlockTitle }}" aria-disabled="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h4"/></svg>
                            <span><strong>PDF</strong><small>Controlled export blocked</small></span>
                        </span>
                    @else
                        <a class="nxsld-icon-action" href="{{ route('companies.projects.sld.svg', [$company, $project]) }}" title="Download editable vector drawing">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m-4-4 4 4 4-4M5 21h14"/></svg>
                            <span><strong>SVG</strong><small>Editable vector</small></span>
                        </a>
                        <a class="nxsld-icon-action" href="{{ route('companies.projects.sld.pdf', [$company, $project]) }}" title="Download controlled A3 PDF">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h4"/></svg>
                            <span><strong>PDF</strong><small>A3 drawing</small></span>
                        </a>
                        <a class="nxsld-icon-action" href="{{ route('companies.projects.sld.manifest', [$company, $project]) }}" title="Download production drawing register and export manifest">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>
                            <span><strong>Manifest</strong><small>Register + SHA-256</small></span>
                        </a>
                        <a class="nxsld-icon-action is-package" href="{{ route('companies.projects.sld.package', [$company, $project]) }}" title="Download controlled PDF, SVG, register, manifest, and checksums as one ZIP package">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v13H4zM7 4h10v3M9 11h6M9 15h6"/></svg>
                            <span><strong>Package</strong><small>PDF + SVG + audit files</small></span>
                        </a>
                    @endif
                </div>
            </section>

            @if ($errors->has('sld'))
                <div class="nxsld-alert nxsld-alert--danger" role="alert" data-sld-reveal>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg>
                    <div><strong>The requested controlled download was not generated.</strong><span>{{ $errors->first('sld') }}</span></div>
                </div>
            @endif

            @if ($controlledExportBlocked)
                <div class="nxsld-alert nxsld-alert--danger" role="alert" data-sld-reveal>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg>
                    <div><strong>Controlled SVG and PDF export are blocked.</strong><span>{{ $controlledExportBlockDetail }} Open the drawing editor, synchronize the source when requested, run Checks, and save the draft before downloading.</span></div>
                </div>
            @endif

            @if ($isStale)
                <div class="nxsld-alert nxsld-alert--danger" role="alert" data-sld-reveal>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg>
                    <div><strong>This drawing is no longer current.</strong><span>Project or calculation data changed after R{{ $revision->revision }} was generated. Create a new revision before approval.</span></div>
                </div>
            @endif

            @if ($isLocked)
                <div class="nxsld-alert nxsld-alert--lock" role="status" data-sld-reveal>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <div><strong>Issued revision locked.</strong><span>R{{ $revision->revision }} is read-only{{ $revision->locked_at ? ' since '.$revision->locked_at->format('d M Y, H:i') : '' }}. Generate a new revision for any drawing, metadata, or engineering-source change.</span></div>
                </div>
            @endif

            <section class="nxsld-metrics" data-sld-reveal aria-label="Electrical design summary">
                <article><span>PV array</span><strong>{{ $formatMetric(data_get($summary, 'installed_pv_kw')) }} <small>kWp</small></strong><p>{{ data_get($summary, 'module_count', '—') }} modules</p></article>
                <article><span>String layout</span><strong class="is-text">{{ data_get($summary, 'string_layout', 'Pending') }}</strong><p>Balanced without phantom modules</p></article>
                <article><span>Inverter AC</span><strong>{{ $formatMetric(data_get($summary, 'inverter_ac_kw')) }} <small>kW</small></strong><p>DC/AC {{ $formatMetric(data_get($summary, 'dc_ac_ratio'), 3) }}</p></article>
                <article><span>Voltage drop</span><strong>{{ $formatMetric(data_get($summary, 'dc_voltage_drop_percent'), 2) }}<small>% DC</small></strong><p>{{ $formatMetric(data_get($summary, 'ac_voltage_drop_percent'), 2) }}% AC</p></article>
                <article class="is-review is-check-{{ $checkStatus }}"><span>Electrical check</span><strong class="is-text">{{ strtoupper($checkStatus) }}</strong><p>{{ data_get($checkSummary, 'error', 0) }} error · {{ data_get($checkSummary, 'warning', 0) }} warning · {{ data_get($checkSummary, 'notice', 0) }} notice</p></article>
            </section>

            <section class="nxsld-production" data-sld-reveal data-sld-production-workflow>
                <header class="nxsld-production-head">
                    <div>
                        <span class="nxsld-section-kicker">APP67L.4-FINAL workflow hardening</span>
                        <h2>Production drawing control</h2>
                        <p>Review the title-block source, release gates, independent authorities, material traceability, revision delta, and exact export filenames before the drawing advances.</p>
                    </div>
                    <div class="nxsld-production-status is-{{ $releaseStatus }}">
                        <span>{{ data_get($documentControl, 'drawing.issue_code', 'DRAFT') }}</span>
                        <strong>{{ $issueLabel }}</strong>
                        <small>R{{ $revision->revision }} · {{ data_get($documentControl, 'version', '67L.4-FINAL') }}</small>
                        <em>{{ data_get($exportPreview, 'disposition', 'NOT FOR CONSTRUCTION') }}</em>
                    </div>
                </header>

                <div class="nxsld-readiness is-{{ $releaseStatus }}" role="status" aria-label="Release readiness">
                    <div>
                        <span>Next controlled action</span>
                        <strong>{{ data_get($releaseAssessment, 'next_target_label', 'No further transition') }}</strong>
                        <small>{{ (int) data_get($releaseSummary, 'next_blockers', 0) }} blocker · {{ (int) data_get($releaseSummary, 'next_warnings', 0) }} advisory</small>
                    </div>
                    <div class="nxsld-readiness-metrics">
                        <span><b>{{ (int) data_get($releaseSummary, 'pass', 0) }}</b>Pass</span>
                        <span><b>{{ (int) data_get($releaseSummary, 'warning', 0) }}</b>Advisory</span>
                        <span><b>{{ (int) data_get($releaseSummary, 'blocked', 0) }}</b>Blocked</span>
                    </div>
                </div>

                <div class="nxsld-production-grid nxsld-production-grid--r2">
                    <article class="nxsld-production-card is-metadata">
                        <div class="nxsld-production-card-head"><strong>Controlled metadata</strong><small>Title-block source</small></div>
                        @if ($revision->status === 'draft' && ! $isLocked && $canSubmit)
                            <form class="nxsld-document-form" method="POST" action="{{ route('companies.projects.sld.document-control.update', [$company, $project]) }}">
                                @csrf @method('PATCH')
                                <label><span>Drawing number prefix</span><input name="drawing_number_prefix" required maxlength="80" value="{{ old('drawing_number_prefix', data_get($documentControl, 'drawing.number_prefix', $revision->drawing_number_prefix)) }}"></label>
                                <label><span>Package title</span><input name="drawing_title" required maxlength="180" value="{{ old('drawing_title', data_get($documentControl, 'drawing.title', $revision->drawing_title)) }}"></label>
                                <label><span>Client</span><input name="client_name" required maxlength="180" value="{{ old('client_name', data_get($documentControl, 'project.client', $revision->client_name)) }}"></label>
                                <label><span>Site location</span><input name="site_location" required maxlength="240" value="{{ old('site_location', data_get($documentControl, 'project.site_location', $revision->site_location)) }}"></label>
                                <button class="nxsld-button nxsld-button--primary" type="submit">Update controlled metadata</button>
                            </form>
                        @else
                            <dl class="nxsld-production-meta">
                                <div><dt>Prefix</dt><dd class="nxsld-mono">{{ data_get($documentControl, 'drawing.number_prefix', '—') }}</dd></div>
                                <div><dt>Client</dt><dd>{{ data_get($documentControl, 'project.client', '—') }}</dd></div>
                                <div><dt>Site</dt><dd>{{ data_get($documentControl, 'project.site_location', '—') }}</dd></div>
                                <div><dt>Organization</dt><dd>{{ data_get($documentControl, 'organization.name', '—') }}</dd></div>
                            </dl>
                        @endif
                    </article>

                    <article class="nxsld-production-card is-preview">
                        <div class="nxsld-production-card-head"><strong>Issue preview</strong><small>Before controlled export</small></div>
                        <div class="nxsld-titleblock-preview">
                            <div><span>Project</span><strong>{{ data_get($documentControl, 'project.name', $project->name) }}</strong><small>{{ data_get($documentControl, 'project.number', $project->project_number) }}</small></div>
                            <div><span>Drawing package</span><strong>{{ data_get($documentControl, 'drawing.title', '—') }}</strong><small>{{ data_get($documentControl, 'drawing.number_prefix', '—') }} · R{{ $revision->revision }}</small></div>
                            <div><span>Issue / disposition</span><strong>{{ data_get($exportPreview, 'issue_code', 'DRAFT') }} · {{ data_get($exportPreview, 'issue_label', 'Draft') }}</strong><small>{{ data_get($exportPreview, 'disposition', 'NOT FOR CONSTRUCTION') }}</small></div>
                            <div><span>Authorities</span><strong>{{ data_get($documentControl, 'people.checked_by', 'Checker pending') ?: 'Checker pending' }}</strong><small>{{ data_get($documentControl, 'people.approved_by', 'Approver pending') ?: 'Approver pending' }}</small></div>
                        </div>
                    </article>

                    <article class="nxsld-production-card is-export">
                        <div class="nxsld-production-card-head"><strong>Controlled export package</strong><small>Exact output identity</small></div>
                        <div class="nxsld-file-preview">
                            @foreach (['pdf' => 'PDF drawing set', 'svg' => 'Editable SVG', 'package' => 'Audit package ZIP'] as $format => $label)
                                <div><span>{{ strtoupper($format) }}</span><p class="nxsld-mono" title="{{ data_get($exportPreview, 'filenames.'.$format) }}">{{ data_get($exportPreview, 'filenames.'.$format, '—') }}</p><small>{{ $label }}</small></div>
                            @endforeach
                        </div>
                        @if ($latestExport !== [])
                            <div class="nxsld-export-proof">
                                <span>Latest SHA-256</span>
                                <code title="{{ data_get($latestExport, 'sha256') }}">{{ str(data_get($latestExport, 'sha256', '—'))->limit(34) }}</code>
                                <small>{{ strtoupper((string) data_get($latestExport, 'format', '—')) }} · {{ data_get($latestExport, 'exported_at') ? \Illuminate\Support\Carbon::parse(data_get($latestExport, 'exported_at'))->format('d M Y, H:i') : '—' }}</small>
                            </div>
                        @endif
                        <a class="nxsld-button nxsld-button--primary nxsld-package-button" href="{{ route('companies.projects.sld.package', [$company, $project]) }}">Download controlled package</a>
                    </article>

                    <article class="nxsld-production-card is-gates">
                        <div class="nxsld-production-card-head"><strong>Release gates</strong><small>{{ count($releaseGates) }} controlled checks</small></div>
                        <div class="nxsld-gate-list">
                            @foreach ($releaseGates as $gate)
                                <div class="is-{{ data_get($gate, 'status', 'warning') }}">
                                    <i aria-hidden="true"></i>
                                    <span><strong>{{ data_get($gate, 'label', 'Release gate') }}</strong><small>{{ data_get($gate, 'detail', '') }}</small></span>
                                    <em>{{ strtoupper((string) data_get($gate, 'status', 'warning')) }}</em>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="nxsld-production-card is-register">
                        <div class="nxsld-production-card-head"><strong>Drawing register</strong><small>{{ count($drawingRegister) }} controlled sheets</small></div>
                        <div class="nxsld-register-table">
                            @forelse ($drawingRegister as $row)
                                <div><span>{{ str_pad((string) data_get($row, 'sheet', 0), 2, '0', STR_PAD_LEFT) }}</span><b class="nxsld-mono">{{ data_get($row, 'drawing_number', '—') }}</b><small>{{ data_get($row, 'title', 'Drawing sheet') }}</small><em>{{ data_get($row, 'revision', 'R'.$revision->revision) }}</em></div>
                            @empty
                                <p>Drawing register will be created when document control is initialized.</p>
                            @endforelse
                        </div>
                    </article>

                    <article class="nxsld-production-card is-materials">
                        <div class="nxsld-production-card-head"><strong>Material traceability</strong><small>Catalog and BOM linkage</small></div>
                        <div class="nxsld-material-list">
                            @foreach ($equipmentRegister as $group)
                                @php($items = is_array(data_get($group, 'items')) ? data_get($group, 'items') : [])
                                <div class="{{ data_get($group, 'status') === 'BOM linked' ? 'is-linked' : 'is-tbc' }}">
                                    <span><strong>{{ data_get($group, 'label', str(data_get($group, 'group', 'Equipment'))->headline()) }}</strong><small>{{ data_get($group, 'status', 'TBC') }} · {{ count($items) }} item(s)</small></span>
                                    <em>{{ collect($items)->filter(fn ($item) => filled(data_get($item, 'datasheet_id')))->count() }} DS</em>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="nxsld-production-card is-comparison">
                        <div class="nxsld-production-card-head"><strong>Revision comparison</strong><small>R{{ data_get($revisionComparison, 'previous_revision', '—') }} → R{{ $revision->revision }}</small></div>
                        @if (data_get($revisionComparison, 'available', false))
                            <div class="nxsld-delta-summary">
                                <span><b>{{ count(data_get($revisionComparison, 'graph.nodes_added', [])) }}</b>Nodes added</span>
                                <span><b>{{ count(data_get($revisionComparison, 'graph.nodes_removed', [])) }}</b>Nodes removed</span>
                                <span><b>{{ count(data_get($revisionComparison, 'graph.connections_added', [])) + count(data_get($revisionComparison, 'graph.connections_removed', [])) }}</b>Wire changes</span>
                                <span class="{{ data_get($revisionComparison, 'source_changed') ? 'is-changed' : '' }}"><b>{{ data_get($revisionComparison, 'source_changed') ? 'YES' : 'NO' }}</b>Source changed</span>
                            </div>
                            <div class="nxsld-change-list">
                                @forelse (data_get($revisionComparison, 'changes', []) as $change)
                                    <div><strong>{{ data_get($change, 'label') }}</strong><span>{{ data_get($change, 'before', '—') }}</span><i>→</i><span>{{ data_get($change, 'after', '—') }}</span></div>
                                @empty
                                    <p>No controlled metadata field changed between these revisions.</p>
                                @endforelse
                            </div>
                        @else
                            <div class="nxsld-production-empty"><strong>First controlled revision.</strong><p>{{ data_get($revisionComparison, 'message', 'No previous revision is available.') }}</p></div>
                        @endif
                    </article>


                    <article class="nxsld-production-card is-revision-history">
                        <div class="nxsld-production-card-head"><strong>Revision register</strong><small>{{ $revisionHistory->count() }} immutable record(s)</small></div>
                        <div class="nxsld-revision-history">
                            @foreach ($revisionHistory as $record)
                                <div class="{{ $record->id === $revision->id ? 'is-current' : '' }} {{ $record->status === 'superseded' ? 'is-superseded' : '' }}">
                                    <span><b>R{{ $record->revision }}</b><small>{{ $record->issue_status_label }} · {{ str($record->status)->replace('_', ' ')->title() }}</small></span>
                                    <em>{{ $record->isLocked() ? 'LOCKED' : ($record->status === 'superseded' ? 'SUPERSEDED' : 'ACTIVE') }}</em>
                                    <p>{{ $record->supersededBy ? 'Replaced by R'.$record->supersededBy->revision : ($record->locked_at ? 'Locked '.$record->locked_at->format('d M Y, H:i') : 'Editable only while draft') }}</p>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="nxsld-production-card is-package-history">
                        <div class="nxsld-production-card-head"><strong>Package archive</strong><small>{{ $packageHistory->count() }} stored export(s)</small></div>
                        <div class="nxsld-package-history">
                            @forelse ($packageHistory as $packageEvent)
                                @php($verificationStatus = (string) data_get($packageEvent, 'verification.status', 'not_verified'))
                                <div class="is-{{ $verificationStatus }}">
                                    <span><b>R{{ data_get($packageEvent, 'revision') }} · {{ data_get($packageEvent, 'issue_code', 'DRAFT') }}</b><small>{{ data_get($packageEvent, 'exported_at') ? \Illuminate\Support\Carbon::parse(data_get($packageEvent, 'exported_at'))->format('d M Y, H:i') : '—' }}</small></span>
                                    <code title="{{ data_get($packageEvent, 'sha256') }}">{{ str(data_get($packageEvent, 'sha256', '—'))->limit(24) }}</code>
                                    <em>{{ strtoupper(str_replace('_', ' ', $verificationStatus)) }}</em>
                                    <div class="nxsld-package-actions">
                                        @if (filled(data_get($packageEvent, 'archive.path')))
                                            <a href="{{ route('companies.projects.sld.packages.download', [$company, $project, data_get($packageEvent, 'revision'), data_get($packageEvent, 'event_id')]) }}">Re-download</a>
                                            @if ($canApprove)
                                                <form method="POST" action="{{ route('companies.projects.sld.packages.verify', [$company, $project, data_get($packageEvent, 'revision'), data_get($packageEvent, 'event_id')]) }}">
                                                    @csrf
                                                    <button type="submit">Verify SHA-256</button>
                                                </form>
                                            @endif
                                        @else
                                            <span>Legacy export, archive unavailable</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="nxsld-production-empty"><strong>No archived package yet.</strong><p>Download a controlled package to create a private, verifiable export record.</p></div>
                            @endforelse
                        </div>
                    </article>

                    <article class="nxsld-production-card is-audit-trail">
                        <div class="nxsld-production-card-head"><strong>Audit trail</strong><small>Latest {{ $auditTrail->count() }} event(s)</small></div>
                        <div class="nxsld-audit-trail">
                            @forelse ($auditTrail as $event)
                                <div><i></i><span><b>{{ str(data_get($event, 'event', 'event'))->replace('_', ' ')->title() }}</b><small>{{ data_get($event, 'user_name', 'System') }} · {{ data_get($event, 'at') ? \Illuminate\Support\Carbon::parse(data_get($event, 'at'))->format('d M Y, H:i') : '—' }}</small></span><em>{{ count((array) data_get($event, 'changes', [])) }} Δ</em></div>
                            @empty
                                <div class="nxsld-production-empty"><strong>Audit history pending.</strong><p>R3 records metadata, workflow, issue, package, verification, and supersession events.</p></div>
                            @endforelse
                        </div>
                    </article>
                </div>
            </section>

            <section class="nxsld-drawing-shell" data-sld-reveal data-sld-drawing-shell>
                <header class="nxsld-drawing-head">
                    <div>
                        <span class="nxsld-section-kicker">Interactive engineering canvas</span>
                        <h2>{{ $meta['system_label'] ?? 'PV system' }} · Revision R{{ $revision->revision }}</h2>
                        <p>Select a symbol to inspect its sourced rating. Drag the canvas when zoomed, or use the controls to fit the complete A3 drawing.</p>
                    </div>
                    <div class="nxsld-tools" role="toolbar" aria-label="Drawing controls">
                        <button type="button" data-sld-zoom="out" aria-label="Zoom out"><svg viewBox="0 0 24 24"><path d="M6 12h12"/></svg></button>
                        <output data-sld-zoom-label>Fit</output>
                        <button type="button" data-sld-zoom="in" aria-label="Zoom in"><svg viewBox="0 0 24 24"><path d="M12 6v12M6 12h12"/></svg></button>
                        <button type="button" class="is-wide" data-sld-zoom="fit">Fit drawing</button>
                        <button type="button" data-sld-fullscreen aria-label="Toggle drawing focus mode"><svg viewBox="0 0 24 24"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5m13 5h5v-5"/></svg></button>
                    </div>
                </header>

                <div class="nxsld-paper-intelligence" aria-label="Paper-space composition status">
                    <div>
                        <span class="nxsld-paper-mode">{{ $paperModeLabel }}</span>
                        <strong>{{ data_get($paperSummary, 'sheet_count', count($svgs)) }} sheet{{ (int) data_get($paperSummary, 'sheet_count', count($svgs)) === 1 ? '' : 's' }}</strong>
                        <p>{{ data_get($paperSummary, 'message', 'Paper space follows the saved Model Space composition.') }}</p>
                    </div>
                    <div class="nxsld-paper-readability is-{{ data_get($paperSummary, 'readability', 'pass') }}">
                        <span>Readability</span>
                        <b>{{ strtoupper((string) data_get($paperSummary, 'readability', 'PASS')) }}</b>
                        <small>Minimum scale {{ number_format((float) data_get($paperSummary, 'minimum_scale', 1), 2) }}</small>
                    </div>
                </div>

                @if (count($svgs) > 1)
                    <nav class="nxsld-sheet-tabs" data-sld-sheet-nav aria-label="Drawing sheets">
                        @foreach ($paperSheets as $index => $sheet)
                            <button type="button" class="{{ $index === 0 ? 'is-active' : '' }}" data-sld-sheet-tab="{{ $index }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                                <span>{{ str_pad((string) ($sheet['number'] ?? $index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <b>{{ $sheet['title'] ?? 'Drawing sheet' }}</b>
                                <small>{{ str($sheet['type'] ?? 'detail')->headline() }} · {{ $sheet['node_count'] ?? 0 }} objects</small>
                            </button>
                        @endforeach
                    </nav>
                @endif

                <div class="nxsld-canvas-layout">
                    <div class="nxsld-stage" data-sld-stage tabindex="0" aria-label="Interactive single-line diagram. Use plus, minus, zero, and arrow keys.">
                        <div class="nxsld-stage-inner" data-sld-stage-inner>
                            @foreach ($svgs as $index => $pageSvg)
                                <article class="nxsld-sheet-page {{ $index === 0 ? 'is-active' : '' }}" data-sld-sheet-page="{{ $index }}" aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                                    {!! $pageSvg !!}
                                </article>
                            @endforeach
                        </div>
                        <div class="nxsld-stage-help"><span>Drag to pan</span><span><kbd>+</kbd><kbd>−</kbd> zoom</span><span><kbd>0</kbd> fit</span>@if(count($svgs) > 1)<span><kbd>PgUp</kbd><kbd>PgDn</kbd> sheets</span>@endif</div>
                    </div>

                    <aside class="nxsld-inspector" data-sld-inspector aria-live="polite">
                        <div class="nxsld-inspector-head">
                            <span class="nxsld-inspector-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h5m6 0h5M9 7v10m6-10v10M9 12h6"/></svg></span>
                            <span class="nxsld-inspector-state" data-sld-inspector-state>{{ strtoupper($firstNode['state'] ?? 'review') }}</span>
                        </div>
                        <span class="nxsld-section-kicker">Selected component</span>
                        <h3 data-sld-inspector-title>{{ $firstNode['title'] ?? 'Select a symbol' }}</h3>
                        <p data-sld-inspector-subtitle>{{ $firstNode['subtitle'] ?? 'Choose an item on the drawing to inspect its source.' }}</p>
                        <dl>
                            <div><dt>Sourced rating</dt><dd data-sld-inspector-rating>{{ $firstNode['rating'] ?? '—' }}</dd></div>
                            <div><dt>Drawing role</dt><dd data-sld-inspector-role>{{ isset($firstNode['kind']) ? ucfirst($firstNode['kind']) : '—' }}</dd></div>
                        </dl>
                        <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}">Trace to calculation <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></a>
                    </aside>
                </div>
            </section>

            <div class="nxsld-detail-grid">
                <section class="nxsld-panel" data-sld-reveal>
                    <header class="nxsld-panel-head">
                        <div><span class="nxsld-section-kicker">Engineering assurance</span><h2>Review notices</h2></div>
                        <span class="nxsld-count">{{ count($warnings) }}</span>
                    </header>
                    @if ($warnings === [])
                        <div class="nxsld-clean">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                            <div><strong>No open SLD-specific notices</strong><p>The topology is internally consistent with its current calculation snapshot. Field and manufacturer verification still apply.</p></div>
                        </div>
                    @else
                        <div class="nxsld-warning-list">
                            @foreach ($warnings as $warning)
                                <article class="nxsld-warning nxsld-warning--{{ $warning['severity'] ?? 'info' }}">
                                    <span>{{ strtoupper($warning['severity'] ?? 'info') }}</span>
                                    <div><strong>{{ $warning['title'] ?? 'Review notice' }}</strong><p>{{ $warning['message'] ?? '' }}</p><small>{{ $warning['recommendation'] ?? '' }}</small></div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="nxsld-panel" data-sld-reveal>
                    <header class="nxsld-panel-head"><div><span class="nxsld-section-kicker">Audit provenance</span><h2>Source trace</h2></div><span class="nxsld-trace-mark">SHA</span></header>
                    <dl class="nxsld-source-list">
                        <div><dt>Calculation state</dt><dd>{{ str(data_get($source, 'technical_calculation.result_status', 'Not available'))->headline() }}</dd></div>
                        <div><dt>Calculated</dt><dd>{{ data_get($source, 'technical_calculation.calculated_at') ? \Illuminate\Support\Carbon::parse(data_get($source, 'technical_calculation.calculated_at'))->format('d M Y, H:i') : '—' }}</dd></div>
                        <div><dt>Recommendation</dt><dd>{{ str(data_get($source, 'recommendation.recommended_system', 'Not available'))->headline() }}</dd></div>
                        <div><dt>BOQ linkage</dt><dd>{{ data_get($source, 'bom.id') ? 'Snapshot #'.data_get($source, 'bom.id') : 'Not linked' }}</dd></div>
                        <div><dt>Fingerprint</dt><dd class="nxsld-mono" title="{{ data_get($source, 'fingerprint') }}">{{ str(data_get($source, 'fingerprint', '—'))->limit(22) }}</dd></div>
                    </dl>
                    <p class="nxsld-panel-note">Any change to the calculation, topology, recommendation, project, or BOQ invalidates this revision fingerprint.</p>
                </section>
            </div>

            <section class="nxsld-review" data-sld-reveal>
                <header>
                    <div><span class="nxsld-section-kicker">Controlled workflow</span><h2>Drawing disposition</h2><p>Revision state changes are explicit, permission-controlled, and retained in history.</p></div>
                    <div class="nxsld-review-badge"><span>Current authority</span><strong>{{ match(true) { $revision->isIssued() => 'Formal issue record', $isApproved => 'Locked approval', $revision->status === 'checked' => 'Approval authority', $revision->status === 'needs_review' => 'Checker authority', default => 'Draft owner' } }}</strong></div>
                </header>
                <div class="nxsld-review-actions">
                    @if ($canSubmit && $revision->status === 'draft')
                        <form method="POST" action="{{ route('companies.projects.sld.submit', [$company, $project]) }}"
                              data-confirm="Submit revision R{{ $revision->revision }} for engineer review?"
                              data-confirm-tone="decision" data-confirm-title="Submit SLD R{{ $revision->revision }}?"
                              data-confirm-kicker="Drawing review handoff" data-confirm-label="Submit for review"
                              data-confirm-cancel-label="Keep as draft" data-confirm-control="Document metadata and source fingerprint are locked for review"
                              data-confirm-result="The issue status becomes IFR - Internal Review">
                            @csrf @method('PATCH')
                            <label><span>Handoff note <small>optional</small></span><textarea name="review_notes" rows="2" placeholder="Design basis, open coordination item, or reviewer context"></textarea></label>
                            <label class="nxsld-integrity-check"><input type="checkbox" name="register_confirm" value="1" required><span>I verified the title-block preview and six-sheet drawing register.</span></label>
                            <button class="nxsld-button nxsld-button--primary" type="submit">Submit for review</button>
                        </form>
                    @endif

                    @if ($canCheck && $revision->status === 'needs_review')
                        <form method="POST" action="{{ route('companies.projects.sld.check', [$company, $project]) }}"
                              data-confirm="Record the engineering check for SLD R{{ $revision->revision }}?"
                              data-confirm-tone="decision" data-confirm-title="Check SLD R{{ $revision->revision }}?"
                              data-confirm-kicker="Independent drawing check" data-confirm-label="Record checked"
                              data-confirm-cancel-label="Continue checking" data-confirm-control="Source freshness, APP67K, APP67I, and critical notices are verified"
                              data-confirm-result="The issue status becomes CHK and waits for approval">
                            @csrf @method('PATCH')
                            <label><span>Checker record <small>required</small></span><textarea name="review_notes" rows="2" required minlength="10" placeholder="State the independent check basis and controlled references"></textarea></label>
                            <label class="nxsld-integrity-check"><input type="checkbox" name="independent_check_confirm" value="1" required><span>I am independent from the drawing preparer and accept checker responsibility.</span></label>
                            <button class="nxsld-button nxsld-button--primary" type="submit" @disabled($isStale || ($warningCounts['critical'] ?? 0) > 0)>Record checked</button>
                        </form>
                    @endif

                    @if ($canApprove && $revision->status === 'checked')
                        <form method="POST" action="{{ route('companies.projects.sld.approve', [$company, $project]) }}"
                              data-confirm="Approve and lock SLD revision R{{ $revision->revision }}?"
                              data-confirm-tone="decision" data-confirm-title="Approve SLD R{{ $revision->revision }}?"
                              data-confirm-kicker="Engineering approval authority" data-confirm-label="Approve and lock"
                              data-confirm-cancel-label="Continue review" data-confirm-control="A recorded checker and verified technical calculation are required"
                              data-confirm-result="R{{ $revision->revision }} becomes the approved controlled drawing">
                            @csrf @method('PATCH')
                            <label><span>Approval basis <small>required</small></span><textarea name="review_notes" rows="2" required minlength="10" placeholder="State the approval authority, basis, and controlled reference"></textarea></label>
                            <label class="nxsld-integrity-check"><input type="checkbox" name="independent_approval_confirm" value="1" required><span>I did not prepare or check this revision and accept approval responsibility.</span></label>
                            <button class="nxsld-button nxsld-button--primary" type="submit" @disabled($isStale || ($warningCounts['critical'] ?? 0) > 0)>Approve and lock</button>
                        </form>
                    @endif

                    @if ($canApprove && in_array($revision->status, ['needs_review', 'checked'], true))
                        <form method="POST" action="{{ route('companies.projects.sld.reject', [$company, $project]) }}"
                              data-confirm="Return SLD revision R{{ $revision->revision }} to draft?"
                              data-confirm-tone="danger" data-confirm-title="Return R{{ $revision->revision }} for revision?"
                              data-confirm-kicker="Engineering review finding" data-confirm-label="Return to draft"
                              data-confirm-cancel-label="Continue review" data-confirm-control="A revision reason is required and retained in issue history"
                              data-confirm-result="The active drawing returns to editable DRAFT state">
                            @csrf @method('PATCH')
                            <label><span>Revision requirement <small>required</small></span><textarea name="review_notes" rows="2" required placeholder="State exactly what must be corrected"></textarea></label>
                            <button class="nxsld-button nxsld-button--danger" type="submit">Return to draft</button>
                        </form>
                    @endif

                    @if ($isApproved)
                        <div class="nxsld-approved">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.8 2.8 8.2 7 10 4.2-1.8 7-5.2 7-10V6l-7-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>
                            <div><strong>{{ $revision->isIssued() ? $issueLabel : 'Approved and immutable' }}</strong><span>{{ ($revision->issued_at ?? $revision->approved_at)?->format('d M Y, H:i') }} · {{ $revision->issuer?->name ?? $revision->approver?->name ?? 'Engineer' }}</span></div>
                        </div>
                        @if ($canIssue && ! $revision->isIssued())
                            <form method="POST" action="{{ route('companies.projects.sld.issue', [$company, $project]) }}"
                                  data-confirm="Create a formal drawing issue from approved R{{ $revision->revision }}?"
                                  data-confirm-tone="decision" data-confirm-title="Issue controlled drawing?"
                                  data-confirm-kicker="Formal document issue" data-confirm-label="Issue drawing"
                                  data-confirm-cancel-label="Keep approved only" data-confirm-control="The issue event, actor, note, drawing register, and export lineage are retained"
                                  data-confirm-result="The approved revision receives a formal IFT, IFC, or AB issue status">
                                @csrf @method('PATCH')
                                <label><span>Issue status</span><select name="issue_status" required><option value="issued_for_tender" @disabled(! data_get($allowedTargets, 'issued_for_tender.ready', false))>Issued for Tender</option><option value="issued_for_construction" @disabled(! data_get($allowedTargets, 'issued_for_construction.ready', false))>Issued for Construction{{ data_get($allowedTargets, 'issued_for_construction.ready', false) ? '' : ' · blocked by release gates' }}</option><option value="as_built" @disabled(! data_get($allowedTargets, 'as_built.ready', false))>As-Built{{ data_get($allowedTargets, 'as_built.ready', false) ? '' : ' · blocked by release gates' }}</option></select><small>The server revalidates source currency, engineering checks, independent authorities, and release gates before creating the issue.</small></label>
                                <label><span>Issue authority note <small>required</small></span><textarea name="issue_note" rows="2" required minlength="10" placeholder="Utility, tender, construction, commissioning, or as-built release reference"></textarea></label>
                                <label class="nxsld-integrity-check"><input type="checkbox" name="issue_confirm" value="1" required><span>I verified the issue preview, release gates, disposition, and exact production filenames.</span></label>
                                <button class="nxsld-button nxsld-button--primary" type="submit">Create formal issue</button>
                            </form>
                        @endif
                    @endif
                </div>

            </section>

            @if ($project->sldRevisions->count() > 1)
                <details class="nxsld-history" data-sld-reveal>
                    <summary><span><b>Revision history</b><small>Immutable drawing lineage</small></span><strong>{{ $project->sldRevisions->count() }} records</strong></summary>
                    <div class="nxsld-history-table">
                        @foreach ($project->sldRevisions as $history)
                            <div><span class="nxsld-mono">R{{ $history->revision }}</span><strong>{{ $history->status_label }} · {{ $history->issue_status_label }}</strong><span>{{ $history->generated_at?->format('d M Y, H:i') }}</span><small>{{ $history->generator?->name ?? 'System' }}</small></div>
                        @endforeach
                    </div>
                </details>
            @endif
        @endif
    </main>
@endsection

