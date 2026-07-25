@extends('layouts.sld-editor')

@section('title', 'SLD Drawing Editor')

@push('styles')
    <link href="{{ asset('css/nexorin-sld-editor.css') }}?v=67m3-67m4" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-sld-editor.js') }}?v=67m3-67m4" defer></script>
@endpush

@php
    $meta = $graph['meta'] ?? [];
    $summary = $meta['summary'] ?? [];
    $editor = $graph['editor'] ?? [];
    $engineeringController = $graph['engineering_controller'] ?? [];
    $controllerStatus = $engineeringController['status'] ?? 'pending';
    $controllerSummary = $engineeringController['summary'] ?? [];
    $controllerSources = $engineeringController['sources'] ?? [];
    $controllerImpacts = $engineeringController['impacts'] ?? [];
    $controllerConflicts = $engineeringController['conflicts'] ?? [];
    $controllerCables = $engineeringController['cable_schedule'] ?? [];
    $controllerProtection = $engineeringController['protection_schedule'] ?? [];
    $status = $revision?->status_label ?? 'No drawing revision';
    $statusTone = $revision?->status_tone ?? 'muted';
    $isLocked = ! $canEdit;
    $symbolRegistryService = app(\App\Services\Sld\SldSymbolRegistry::class);
    $symbolGroups = $symbolRegistryService->paletteGroups();
    $layerDefinitions = [
        ['E-EQUIPMENT', 'Equipment', 'Primary electrical equipment'],
        ['E-DC-POWER', 'DC power', 'DC positive, negative, and battery circuits'],
        ['E-AC-POWER', 'AC power', 'Phase conductors and AC circuits'],
        ['E-NEUTRAL', 'Neutral', 'Neutral conductor network'],
        ['E-PE', 'Protective earth', 'Earthing and bonding network'],
        ['E-COMM', 'Communication', 'RS-485, Ethernet, CAN, and SCADA links'],
        ['E-CONTROL', 'Control', 'Control and interlock circuits'],
        ['E-PROTECTION', 'Protection', 'Breakers, fuses, SPD, GFPD, and AFCI'],
        ['E-METERING', 'Metering', 'Meters, CT, VT, relay, PCC, and APP PLN'],
        ['E-ANNOTATION', 'Annotation', 'Text, leaders, and drawing notes'],
        ['E-BOUNDARY', 'Boundary', 'Functional and ownership boundaries'],
        ['E-EXISTING', 'Existing', 'Existing equipment and circuits'],
        ['E-FUTURE', 'Future', 'Future or reserved equipment'],
    ];@endphp

@section('content')
<main
    class="nxsld-workspace is-display-clean"
    data-sld-editor
    data-editor-save-url="{{ $revision ? route('companies.projects.sld.editor.save', [$company, $project]) : '' }}"
    data-editor-controller-url="{{ $revision ? route('companies.projects.sld.controller.sync', [$company, $project]) : '' }}"
    data-editor-close-url="{{ route('companies.projects.sld.show', [$company, $project]) }}"
    data-editor-read-only="{{ $isLocked ? 'true' : 'false' }}"
>
    <script type="application/json" data-sld-editor-payload>{!! json_encode([
        'graph' => $graph,
        'editor' => $editor,
        'project' => [
            'name' => $project->name,
            'number' => $project->project_number,
        ],
        'links' => [
            'technical_calculation' => route('companies.technical-calculations.show', [$company, $project]),
            'bom' => route('companies.boms.show', [$company, $project]),
            'financial_analysis' => route('companies.financial-analyses.show', [$company, $project]),
            'quotation' => route('companies.quotations.show', [$company, $project]),
            'sld_review' => route('companies.projects.sld.show', [$company, $project]),
        ],
        'revision' => $revision ? [
            'id' => $revision->id,
            'revision' => $revision->revision,
            'status' => $revision->status,
            'status_label' => $revision->status_label,
        ] : null,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    <header class="nxsld-document-bar">
        <div class="nxsld-document-left">
            <button class="nxsld-icon-button" type="button" data-editor-close aria-label="Back to controlled SLD review" title="Back to SLD review">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
            </button>
            <div class="nxsld-quick-access" aria-label="Quick access commands">
                <button type="button" data-editor-save title="Save drawing (Ctrl+S)" @disabled($isLocked)><svg viewBox="0 0 24 24"><path d="M5 4h11l3 3v13H5zM8 4v6h8V4M8 16h8"/></svg></button>
                <button type="button" data-editor-action="undo" title="Undo (Ctrl+Z)"><svg viewBox="0 0 24 24"><path d="M9 8 4 12l5 4M5 12h8a6 6 0 0 1 6 6"/></svg></button>
                <button type="button" data-editor-action="redo" title="Redo (Ctrl+Y)"><svg viewBox="0 0 24 24"><path d="m15 8 5 4-5 4m4-4h-8a6 6 0 0 0-6 6"/></svg></button>
            </div>
            <span class="nxsld-brand-mark" aria-hidden="true"><i></i></span>
            <div class="nxsld-document-identity">
                <div class="nxsld-document-kicker"><span>NEXORIN</span><b>SLD ENGINE</b><em>MAXGRAPH</em></div>
                <div class="nxsld-document-title">
                    <h1>{{ $meta['system_label'] ?? 'Single-line diagram' }}</h1>
                    <strong class="nxsld-mono">{{ $project->project_number }}</strong>
                    <span>{{ $project->name }}</span>
                    <span class="nxsld-revision-chip">R{{ $revision?->revision ?? '—' }}</span>
                </div>
            </div>
        </div>

        <div class="nxsld-document-center" aria-label="Drawing status">
            <span class="nxsld-status-indicator is-{{ $statusTone }}"><i></i>{{ $status }}</span>
            <span class="nxsld-save-state" data-editor-save-state>{{ $isLocked ? 'Read-only drawing' : 'All changes saved' }}</span>
            <button type="button" class="nxsld-check-status is-pending" data-editor-check-status title="Open electrical-rule checks"><i></i><span>CHECK</span><b data-editor-check-status-label>PENDING</b><em data-editor-check-status-count>—</em></button>
            <button type="button" class="nxsld-controller-status is-{{ $controllerStatus }}" data-editor-controller-status title="Open APP67K engineering controller"><i></i><span>SOURCE</span><b data-editor-controller-status-label>{{ strtoupper($controllerStatus) }}</b><em data-editor-controller-status-count>{{ (int) ($controllerSummary['total'] ?? 0) }}</em></button>
        </div>

        <div class="nxsld-document-actions">
            <button type="button" class="nxsld-icon-button" data-editor-action="fullscreen" aria-label="Enter fullscreen drafting mode" title="Fullscreen (F)">
                <svg data-fullscreen-enter viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M8 21H3v-5m13 5h5v-5"/></svg>
                <svg data-fullscreen-exit viewBox="0 0 24 24" aria-hidden="true" hidden><path d="M9 3v6H3m12-6v6h6M9 21v-6H3m12 6v-6h6"/></svg>
            </button>
            <button class="nxsld-secondary-button" type="button" data-editor-close>Close editor</button>
            <button class="nxsld-primary-button" type="button" data-editor-save @disabled($isLocked)>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h11l3 3v13H5zM8 4v6h8V4M8 16h8"/></svg>
                Save drawing
            </button>
        </div>
    </header>

    @if (! $revision)
        <section class="nxsld-no-revision">
            <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5h16v14zM8 9h8M8 13h5"/></svg></span>
            <div>
                <small>CONTROLLED DRAWING UNAVAILABLE</small>
                <h2>Generate an SLD revision before opening model space.</h2>
                <p>The drafting engine edits a revision linked to a technical-calculation snapshot.</p>
            </div>
            <button class="nxsld-primary-button" type="button" data-editor-close>Open SLD review</button>
        </section>
    @else
        <nav class="nxsld-ribbon" aria-label="SLD precision drafting commands">
            <div class="nxsld-ribbon-tabs" role="tablist" aria-label="Drafting ribbon">
                <button type="button" class="is-active" data-editor-ribbon-tab="home">HOME</button>
                <button type="button" data-editor-ribbon-tab="insert">INSERT</button>
                <button type="button" data-editor-ribbon-tab="annotate">ANNOTATE</button>
                <button type="button" data-editor-ribbon-tab="view">VIEW</button>
                <button type="button" data-editor-ribbon-tab="manage">MANAGE</button>
                <button type="button" class="nxsld-ribbon-collapse" data-editor-action="toggle-ribbon" aria-pressed="false" title="Collapse ribbon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 14 5-5 5 5"/></svg><span>Collapse</span></button>
            </div>

            <div class="nxsld-ribbon-panel is-active" data-editor-ribbon-panel="home">
                <div class="nxsld-ribbon-cluster"><small>CONTROL</small><div class="nxsld-command-group">
                    <button type="button" class="nxsld-command is-active" data-editor-tool="select" title="Select (V)"><svg viewBox="0 0 24 24"><path d="m5 3 14 9-7 2-3 7z"/></svg><span>Select</span><kbd>V</kbd></button>
                    <button type="button" class="nxsld-command" data-editor-tool="pan" title="Pan (H), Space + drag, or middle mouse"><svg viewBox="0 0 24 24"><path d="M8 11V6a1.5 1.5 0 0 1 3 0v4-6a1.5 1.5 0 0 1 3 0v6-5a1.5 1.5 0 0 1 3 0v6-3a1.5 1.5 0 0 1 3 0v5c0 4-2.5 7-7 7h-1c-2.4 0-4.2-1.1-5.5-2.9L3.7 13a1.6 1.6 0 0 1 2.4-2.1L8 12.5z"/></svg><span>Pan</span><kbd>H</kbd></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster"><small>DRAW</small><div class="nxsld-command-group">
                    <button type="button" class="nxsld-command" data-editor-tool="line" title="Line (L)"><svg viewBox="0 0 24 24"><path d="M5 19 19 5M5 19h.01M19 5h.01"/></svg><span>Line</span><kbd>L</kbd></button>
                    {{-- Polyline remains hidden until the multi-vertex object model is completed. --}}
                    <button type="button" class="nxsld-command" data-editor-tool="rectangle" title="Rectangle (REC)"><svg viewBox="0 0 24 24"><rect x="4" y="6" width="16" height="12"/></svg><span>Rectangle</span><kbd>REC</kbd></button>
                    <div class="nxsld-command-menu">
                        <button type="button" class="nxsld-command" data-editor-tool="circle" title="Circle (C)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><span>Circle</span><kbd>C</kbd></button>
                        <button type="button" class="nxsld-command-menu-toggle" data-editor-geometry-menu-toggle aria-expanded="false" aria-label="Circle construction modes"><svg viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></button>
                        <div class="nxsld-command-flyout" data-editor-geometry-menu hidden>
                            <header><small>CIRCLE METHOD</small><strong data-editor-circle-mode-label>Center, Radius</strong></header>
                            <button type="button" class="is-active" data-editor-circle-mode="center-radius"><i><svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="10"/><path d="M16 16 24 10"/></svg></i><span><b>Center, Radius</b><small>Center point, then radius</small></span></button>
                            <button type="button" data-editor-circle-mode="center-diameter"><i><svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="10"/><path d="M7 16h18"/></svg></i><span><b>Center, Diameter</b><small>Center point, then diameter</small></span></button>
                            <button type="button" data-editor-circle-mode="two-point"><i><svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="10"/><path d="M6 16h20"/><circle cx="6" cy="16" r="1.5"/><circle cx="26" cy="16" r="1.5"/></svg></i><span><b>2-Point</b><small>Two diameter endpoints</small></span></button>
                            <button type="button" data-editor-circle-mode="three-point"><i><svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="10"/><circle cx="16" cy="6" r="1.5"/><circle cx="7" cy="21" r="1.5"/><circle cx="25" cy="21" r="1.5"/></svg></i><span><b>3-Point</b><small>Three points on circumference</small></span></button>
                        </div>
                    </div>
                    <div class="nxsld-command-menu">
                        <button type="button" class="nxsld-command" data-editor-tool="arc" title="Arc (A)"><svg viewBox="0 0 24 24"><path d="M5 17A10 10 0 0 1 19 7"/></svg><span>Arc</span><kbd>A</kbd></button>
                        <button type="button" class="nxsld-command-menu-toggle" data-editor-geometry-menu-toggle aria-expanded="false" aria-label="Arc construction modes"><svg viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></button>
                        <div class="nxsld-command-flyout" data-editor-geometry-menu hidden>
                            <header><small>ARC METHOD</small><strong data-editor-arc-mode-label>3-Point Arc</strong></header>
                            <button type="button" class="is-active" data-editor-arc-mode="three-point"><i><svg viewBox="0 0 32 32"><path d="M5 24A13 13 0 0 1 27 12"/><circle cx="5" cy="24" r="1.5"/><circle cx="17" cy="7" r="1.5"/><circle cx="27" cy="12" r="1.5"/></svg></i><span><b>3-Point</b><small>Start, point on arc, endpoint</small></span></button>
                            <button type="button" data-editor-arc-mode="center-start-end"><i><svg viewBox="0 0 32 32"><path d="M7 24A12 12 0 0 1 25 10"/><path d="M16 17 7 24M16 17 25 10"/><circle cx="16" cy="17" r="1.5"/></svg></i><span><b>Center, Start, End</b><small>Center first, then arc limits</small></span></button>
                            <button type="button" data-editor-arc-mode="start-center-end"><i><svg viewBox="0 0 32 32"><path d="M7 24A12 12 0 0 1 25 10"/><path d="M7 24 16 17 25 10"/><circle cx="16" cy="17" r="1.5"/></svg></i><span><b>Start, Center, End</b><small>Start first, then center</small></span></button>
                            <button type="button" data-editor-arc-mode="start-end-radius"><i><svg viewBox="0 0 32 32"><path d="M5 24A13 13 0 0 1 27 12"/><path d="M16 18 24 11"/><circle cx="5" cy="24" r="1.5"/><circle cx="27" cy="12" r="1.5"/></svg></i><span><b>Start, End, Radius</b><small>Endpoints, then radius and side</small></span></button>
                        </div>
                    </div>
                    <button type="button" class="nxsld-command is-engineering" data-editor-tool="wire" title="Engineering wire (W)"><svg viewBox="0 0 24 24"><path d="M4 18h6V6h10M4 18h.01M20 6h.01"/></svg><span>Wire</span><kbd>W</kbd></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster is-premium-modify"><small>MODIFY</small><div class="nxsld-command-group is-compact">
                    <button type="button" class="nxsld-command" data-editor-action="cut" data-editor-requires-selection title="Cut removable objects (Ctrl+X)"><svg viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.5 7.5 10 9M8.5 16.5l10-9"/></svg><span>Cut</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="copy" data-editor-requires-selection title="Copy (Ctrl+C)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg><span>Copy</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="paste" title="Paste (Ctrl+V)"><svg viewBox="0 0 24 24"><path d="M9 5h6M9 3h6v4H9z"/><rect x="5" y="5" width="14" height="16" rx="2"/></svg><span>Paste</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="duplicate" data-editor-requires-selection title="Duplicate (Ctrl+D)"><svg viewBox="0 0 24 24"><rect x="7" y="7" width="12" height="12" rx="2"/><path d="M16 7V4H4v12h3"/></svg><span>Duplicate</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="delete" data-editor-requires-selection title="Erase selected object (Delete)"><svg viewBox="0 0 24 24"><path d="M5 7h14m-9 0V4h4v3m-7 0 1 13h8l1-13"/></svg><span>Erase</span></button>
                </div><div class="nxsld-selection-chip" title="Current selection"><span>SELECTED</span><b data-editor-selection-count>0</b></div></div>
                <div class="nxsld-ribbon-spacer"></div>
                <div class="nxsld-coordinate-readout" aria-label="Cursor coordinate"><span>X</span><b data-editor-coordinate-x>0</b><span>Y</span><b data-editor-coordinate-y>0</b></div>
            </div>

            <div class="nxsld-ribbon-panel" data-editor-ribbon-panel="insert">
                <div class="nxsld-ribbon-cluster"><small>ELECTRICAL EQUIPMENT</small><div class="nxsld-command-group">
                    <button type="button" class="nxsld-command is-engineering" data-editor-action="open-library" title="Open the controlled PLTS engineering symbol palette"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg><span>Symbol library</span><kbd>[</kbd></button>
                </div></div>
                <span class="nxsld-ribbon-message"><b>INSERT</b> places electrical equipment only. Use <kbd>W</kbd> Wire and geometry commands from HOME. This removes duplicate commands and keeps each ribbon tab task-specific.</span>
            </div>

            <div class="nxsld-ribbon-panel" data-editor-ribbon-panel="annotate">
                <div class="nxsld-ribbon-cluster"><small>ANNOTATION</small><div class="nxsld-command-group">
                    <button type="button" class="nxsld-command" data-editor-tool="text" title="Drawing note (T)"><svg viewBox="0 0 24 24"><path d="M5 5h14M12 5v14M8 19h8"/></svg><span>Note</span><kbd>T</kbd></button>
                    <button type="button" class="nxsld-command" data-editor-tool="leader" title="Leader (ML)"><svg viewBox="0 0 24 24"><path d="M4 18 14 8h6M4 18h.01"/></svg><span>Leader</span><kbd>ML</kbd></button>
                </div></div>
                <span class="nxsld-ribbon-message">Annotation geometry uses the same precision snap and dynamic input engine as drafting linework.</span>
            </div>

            <div class="nxsld-ribbon-panel" data-editor-ribbon-panel="view">
                <div class="nxsld-ribbon-cluster"><small>NAVIGATION</small><div class="nxsld-command-group">
                    <button type="button" class="nxsld-command" data-editor-action="fit" title="Fit all visible drawing objects to the viewport (0)"><svg viewBox="0 0 24 24"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/></svg><span>Fit drawing</span><kbd>0</kbd></button>
                    <button type="button" class="nxsld-command" data-editor-action="zoom-in"><svg viewBox="0 0 24 24"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5M10 7v6M7 10h6"/></svg><span>Zoom in</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="zoom-out"><svg viewBox="0 0 24 24"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5M7 10h6"/></svg><span>Zoom out</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="fullscreen"><svg viewBox="0 0 24 24"><path d="M8 3H3v5m13-5h5v5M8 21H3v-5m13 5h5v-5"/></svg><span>Fullscreen</span><kbd>F</kbd></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-display-switch" aria-label="Canvas information density"><span>DISPLAY</span><button type="button" class="is-active" data-editor-display="clean">Clean</button><button type="button" data-editor-display="engineering">Engineering</button><button type="button" data-editor-display="inspection">Inspection</button></div>
                <div class="nxsld-ribbon-divider"></div>
                <button type="button" class="nxsld-panels-button" data-editor-action="toggle-panels"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM9 5v14m6-14v14"/></svg><span>Panels</span><kbd>[ / P</kbd></button>
            </div>

            <div class="nxsld-ribbon-panel nxsld-manage-ribbon" data-editor-ribbon-panel="manage">
                <div class="nxsld-ribbon-cluster nxsld-routing-ribbon"><small>SMART ROUTING</small><div class="nxsld-command-group is-compact">
                    <button type="button" class="nxsld-command nxsld-command-route" data-editor-action="rebuild-all-routes" title="Rebuild every automatic wire using deterministic network-priority orthogonal channel routing"><svg viewBox="0 0 24 24"><path d="M4 6h5v5h6V6h5M4 18h5v-5h6v5h5"/><circle cx="4" cy="6" r="1.5"/><circle cx="20" cy="18" r="1.5"/></svg><span>Route all</span><b data-editor-routing-ribbon-count>—</b></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster nxsld-check-ribbon"><small>DESIGN CHECK</small><div class="nxsld-command-group is-compact">
                    <button type="button" class="nxsld-command nxsld-command-check" data-editor-action="run-electrical-check" title="Run deterministic electrical-rule check"><svg viewBox="0 0 24 24"><path d="M12 3 3 7v5c0 5 3.6 8.3 9 9 5.4-.7 9-4 9-9V7zM8 12l2.5 2.5L16 9"/></svg><span>Run check</span><b data-editor-check-ribbon-count>—</b></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster"><small>ALIGNMENT</small><div class="nxsld-mini-command-grid is-six">
                    <button type="button" data-editor-action="align-left" data-editor-requires-two title="Align left"><svg viewBox="0 0 24 24"><path d="M4 3v18M7 6h10v4H7zM7 14h14v4H7z"/></svg><span>Left</span></button>
                    <button type="button" data-editor-action="align-center" data-editor-requires-two title="Align horizontal centers"><svg viewBox="0 0 24 24"><path d="M12 3v18M5 6h14v4H5zM7 14h10v4H7z"/></svg><span>Center</span></button>
                    <button type="button" data-editor-action="align-right" data-editor-requires-two title="Align right"><svg viewBox="0 0 24 24"><path d="M20 3v18M7 6h10v4H7zM3 14h14v4H3z"/></svg><span>Right</span></button>
                    <button type="button" data-editor-action="align-top" data-editor-requires-two title="Align top"><svg viewBox="0 0 24 24"><path d="M3 4h18M6 7v10h4V7zM14 7v14h4V7z"/></svg><span>Top</span></button>
                    <button type="button" data-editor-action="align-middle" data-editor-requires-two title="Align vertical centers"><svg viewBox="0 0 24 24"><path d="M3 12h18M6 5h4v14H6zM14 7h4v10h-4z"/></svg><span>Middle</span></button>
                    <button type="button" data-editor-action="align-bottom" data-editor-requires-two title="Align bottom"><svg viewBox="0 0 24 24"><path d="M3 20h18M6 7h4v10H6zM14 3h4v14h-4z"/></svg><span>Bottom</span></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster"><small>DISTRIBUTE</small><div class="nxsld-command-group is-compact">
                    <button type="button" class="nxsld-command" data-editor-action="distribute-horizontal" data-editor-requires-three title="Distribute with equal horizontal gaps"><svg viewBox="0 0 24 24"><path d="M3 4v16M21 4v16M7 8h3v8H7zM14 8h3v8h-3zM5 12h2m3 0h4m3 0h2"/></svg><span>Horizontal</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="distribute-vertical" data-editor-requires-three title="Distribute with equal vertical gaps"><svg viewBox="0 0 24 24"><path d="M4 3h16M4 21h16M8 7h8v3H8zM8 14h8v3H8zM12 5v2m0 3v4m0 3v2"/></svg><span>Vertical</span></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster is-transform-cluster"><small>TRANSFORM</small><div class="nxsld-mini-command-grid is-five">
                    <button type="button" data-editor-action="rotate-left" data-editor-requires-transform title="Rotate symbol 90° counter-clockwise"><svg viewBox="0 0 24 24"><path d="M5 7V3m0 0h4M5 3l4 4M6.8 16.2A7 7 0 1 0 7 7"/></svg><span>Rotate L</span></button>
                    <button type="button" data-editor-action="rotate-right" data-editor-requires-transform title="Rotate symbol 90° clockwise"><svg viewBox="0 0 24 24"><path d="M19 7V3m0 0h-4m4 0-4 4M17.2 16.2A7 7 0 1 1 17 7"/></svg><span>Rotate R</span></button>
                    <button type="button" data-editor-action="mirror-horizontal" data-editor-requires-transform title="Mirror symbol horizontally"><svg viewBox="0 0 24 24"><path d="M12 3v18M3 6l7 3v6l-7 3zM21 6l-7 3v6l7 3z"/></svg><span>Mirror H</span></button>
                    <button type="button" data-editor-action="mirror-vertical" data-editor-requires-transform title="Mirror symbol vertically"><svg viewBox="0 0 24 24"><path d="M3 12h18M6 3l3 7h6l3-7zM6 21l3-7h6l3 7z"/></svg><span>Mirror V</span></button>
                    <button type="button" data-editor-action="reset-transform" data-editor-requires-transform title="Reset rotation and mirror"><svg viewBox="0 0 24 24"><path d="M4 4v6h6M5.5 15.5A7 7 0 1 0 7 7"/></svg><span>Reset</span></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster is-structure-cluster"><small>STRUCTURE</small><div class="nxsld-command-group is-compact">
                    <button type="button" class="nxsld-command" data-editor-action="group" data-editor-requires-two title="Group selected objects (Ctrl+G)"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="7" height="7"/><rect x="14" y="4" width="7" height="7"/><rect x="3" y="15" width="7" height="6"/><rect x="14" y="15" width="7" height="6"/><path d="M10 7.5h4M7 11v4m10-4v4M10 18h4"/></svg><span>Group</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="ungroup" data-editor-requires-group title="Ungroup selected objects (Ctrl+Shift+G)"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="7" height="7"/><rect x="14" y="4" width="7" height="7"/><rect x="3" y="15" width="7" height="6"/><rect x="14" y="15" width="7" height="6"/><path d="M9 8h6M9 18h6" stroke-dasharray="2 2"/></svg><span>Ungroup</span></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster"><small>OBJECT CONTROL</small><div class="nxsld-command-group is-compact">
                    <button type="button" class="nxsld-command" data-editor-action="lock" data-editor-requires-selection title="Lock selected objects (Alt+L)"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>Lock</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="unlock" data-editor-requires-selection title="Unlock selected objects (Alt+Shift+L)"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M16 10V7a4 4 0 0 0-7.7-1.5"/></svg><span>Unlock</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="reset-size" data-editor-requires-selection title="Restore standard engineering symbol size"><svg viewBox="0 0 24 24"><path d="M5 9V5h4M19 9V5h-4M5 15v4h4M19 15v4h-4M8 8h8v8H8z"/></svg><span>Std size</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="bring-front" data-editor-requires-selection title="Bring to front"><svg viewBox="0 0 24 24"><rect x="8" y="4" width="12" height="12" rx="2"/><rect x="4" y="8" width="12" height="12" rx="2"/></svg><span>Front</span></button>
                    <button type="button" class="nxsld-command" data-editor-action="send-back" data-editor-requires-selection title="Send to back"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="12" height="12" rx="2"/><rect x="8" y="8" width="12" height="12" rx="2"/></svg><span>Back</span></button>
                </div></div>
                <div class="nxsld-ribbon-divider"></div>
                <div class="nxsld-ribbon-cluster"><small>SOURCE CONTROL</small><div class="nxsld-command-group is-compact">
                    <button type="button" class="nxsld-command" data-editor-action="reset-layout"><svg viewBox="0 0 24 24"><path d="M4 4v6h6M20 20v-6h-6M5 15a7 7 0 0 0 12 3M19 9A7 7 0 0 0 7 6"/></svg><span>Reset layout</span></button>
                </div></div>
                <span class="nxsld-ribbon-message">Object geometry is editable. Calculation-controlled ratings remain protected and traceable.</span>
            </div>
        </nav>

        <section class="nxsld-editor-shell">
            <button type="button" class="nxsld-panel-rail is-left" data-editor-open-panel="palette" aria-label="Open electrical library" aria-controls="nxsld-electrical-library" aria-expanded="true">
                <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg><span>LIBRARY</span><b>›</b>
            </button>

            <aside id="nxsld-electrical-library" class="nxsld-palette" data-editor-panel="palette" aria-label="Electrical symbol library" aria-hidden="false">
                <header class="nxsld-panel-header">
                    <div><small>SYMBOL PALETTE</small><h2>Electrical Library</h2></div>
                    <button type="button" data-editor-toggle-panel="palette" aria-label="Collapse electrical library" aria-controls="nxsld-electrical-library" aria-expanded="true" title="Collapse library ([)"><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
                </header>
                <label class="nxsld-symbol-search">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
                    <input type="search" data-editor-symbol-search placeholder="Search equipment..." autocomplete="off">
                    <kbd>/</kbd>
                </label>
                <div class="nxsld-symbol-filters" data-editor-symbol-filters aria-label="Symbol category filters">
                    <button type="button" class="is-active" data-editor-symbol-filter="all">All</button>
                    <button type="button" data-editor-symbol-filter="pv">PV/DC</button>
                    <button type="button" data-editor-symbol-filter="conversion">Convert</button>
                    <button type="button" data-editor-symbol-filter="protection">Protection</button>
                    <button type="button" data-editor-symbol-filter="ac-grid">AC/Grid</button>
                    <button type="button" data-editor-symbol-filter="monitoring">Monitoring</button>
                </div>
                <div class="nxsld-symbol-tooltip" data-editor-symbol-tooltip hidden role="tooltip"></div>
                <div class="nxsld-palette-scroll">
                    @foreach ($symbolGroups as $groupId => $group)
                        <section class="nxsld-symbol-group" data-editor-symbol-group data-editor-symbol-group-id="{{ $groupId }}">
                            <button type="button" class="nxsld-symbol-group-title" data-editor-group-toggle aria-expanded="true"><span>{{ $group['short_label'] ?? $group['label'] ?? $groupId }}</span><b>{{ count($group['symbols'] ?? []) }}</b><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></button>
                            <div class="nxsld-symbol-list">
                                @foreach (($group['symbols'] ?? []) as $symbol)
                                    @php
                                        $kind = (string) ($symbol['id'] ?? 'panel');
                                        $label = (string) ($symbol['title'] ?? $kind);
                                        $description = (string) ($symbol['subtitle'] ?? 'Electrical equipment');
                                        $standard = is_array($symbol['standard'] ?? null) ? $symbol['standard'] : [];
                                        $standardFamily = (string) ($standard['family'] ?? 'NEXORIN engineering reference form');
                                        $standardStatus = (string) ($standard['status_label'] ?? 'Reference drawing form');
                                        $standardPages = (string) ($standard['source_pages'] ?? '');
                                        $tagPrefix = (string) ($symbol['tag_prefix'] ?? strtoupper(substr(str_replace('_', '', $kind), 0, 5)));
                                        $ports = is_array($symbol['ports'] ?? null) ? $symbol['ports'] : [];
                                        $aliases = collect(is_array($symbol['aliases'] ?? null) ? $symbol['aliases'] : [])->map(fn ($alias) => strtolower((string) $alias))->implode(' ');
                                        $isAssembly = ($standard['status'] ?? '') === 'nexorin_assembly';
                                        $scopeLabel = $isAssembly ? 'ASSEMBLY' : 'INDIVIDUAL';
                                        $size = is_array($symbol['nominal_size'] ?? null) ? $symbol['nominal_size'] : ['width' => 144, 'height' => 120];
                                    @endphp
                                    <button type="button" class="nxsld-symbol-card" data-editor-add-kind="{{ $kind }}" data-editor-symbol-name="{{ strtolower($label.' '.$description.' '.$kind.' '.$aliases.' '.$standardFamily.' '.$standardStatus.' '.$tagPrefix) }}" data-editor-symbol-category="{{ $group['category'] ?? 'all' }}" data-editor-symbol-standard="{{ $standardFamily }}" data-editor-symbol-status="{{ $standardStatus }}" data-editor-symbol-scope="{{ $scopeLabel }}" data-editor-symbol-pages="{{ $standardPages }}" data-editor-symbol-prefix="{{ $tagPrefix }}" data-editor-symbol-description="{{ $description }}" data-editor-symbol-port-count="{{ count($ports) }}" data-editor-symbol-size="{{ (int) ($size['width'] ?? 144) }} × {{ (int) ($size['height'] ?? 120) }}" draggable="{{ $canEdit ? 'true' : 'false' }}" @disabled($isLocked)>
                                        <span class="nxsld-symbol-glyph is-{{ $kind }}" data-editor-symbol-glyph="{{ $kind }}" aria-hidden="true"></span>
                                        <span class="nxsld-symbol-card-copy"><strong>{{ $label }}</strong><small>{{ $description }}</small><em class="{{ $isAssembly ? 'is-assembly' : 'is-individual' }}">{{ $scopeLabel }}</em></span>
                                        <span class="nxsld-symbol-add" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </aside>

            <section class="nxsld-model-space" aria-label="SLD model space">
                <div class="nxsld-model-tabs">
                    <div>
                        <button type="button" class="is-active" data-editor-space="model">Model Space</button>
                        <button type="button" data-editor-space="sheet">A3 Paper Space</button>
                    </div>
                    <span data-editor-space-description>Unlimited drafting canvas</span>
                    <b><i></i>LIVE DRAFT</b>
                </div>
                <div class="nxsld-canvas-frame">
                    <div class="nxsld-sheet-paper" data-editor-sheet-paper>
                        <div class="nxsld-paper-control" data-editor-paper-control>
                            <div><span>PAPER SPACE</span><strong data-editor-paper-status>{{ strtoupper($paperSummary['mode'] ?? 'AUTO') }} · {{ $paperSummary['sheet_count'] ?? count($a3Svgs) }} SHEET(S)</strong><small data-editor-paper-message>{{ $paperSummary['message'] ?? 'Paper-space composition follows the saved Model Space drawing.' }}</small></div>
                            <label>Composition<select data-editor-paper-mode @disabled($isLocked)>
                                @foreach (['as_drawn' => 'As drawn · WYSIWYG A3', 'single' => 'Fit complete drawing', 'multi' => 'Coordinated overview + details', 'catalog' => 'Symbol register'] as $paperModeValue => $paperModeLabel)
                                    <option value="{{ $paperModeValue }}" @selected((data_get($graph, 'editor.paper.mode', 'as_drawn') === $paperModeValue) || ($paperModeValue === 'as_drawn' && data_get($graph, 'editor.paper.mode') === 'auto'))>{{ $paperModeLabel }}</option>
                                @endforeach
                            </select></label>
                        </div>
                        <div class="nxsld-paper-preview" data-editor-paper-preview aria-label="A3 output composition preview">
                            <div class="nxsld-paper-pages" data-editor-paper-pages>
                                @forelse ($a3Svgs as $pageIndex => $pageSvg)
                                    <article class="nxsld-paper-page{{ $pageIndex === 0 ? ' is-active' : '' }}" data-editor-paper-page="{{ $pageIndex }}">{!! $pageSvg !!}</article>
                                @empty
                                    <div class="nxsld-paper-preview-empty">Generate a drawing revision to preview the A3 sheet.</div>
                                @endforelse
                            </div>
                            <nav class="nxsld-paper-navigation" data-editor-paper-navigation aria-label="Paper sheet navigation">
                                @foreach ($paperSheets ?? [] as $pageIndex => $sheet)
                                    <button type="button" class="{{ $pageIndex === 0 ? 'is-active' : '' }}" data-editor-paper-tab="{{ $pageIndex }}"><b>{{ str_pad((string) $sheet['number'], 2, '0', STR_PAD_LEFT) }}</b><span>{{ $sheet['title'] }}</span><small>{{ strtoupper($sheet['type']) }} · {{ $sheet['node_count'] }} OBJECTS</small></button>
                                @endforeach
                            </nav>
                            <span class="nxsld-paper-preview-note" data-editor-paper-preview-note>Saved intelligent paper-space composition</span>
                        </div>
                        <div class="nxsld-graph-container" data-editor-graph tabindex="0" aria-label="Editable SLD diagram. Use V for select, H for pan, L for line, PL for polyline, REC for rectangle, C for circle, A for arc, W for wire, mouse wheel to zoom, hold Space and drag to pan temporarily, or drag with the middle mouse button."></div>
                    </div>
                    <svg class="nxsld-drafting-overlay" data-editor-drafting-overlay aria-hidden="true">
                        <rect class="nxsld-window-selection" data-editor-selection-window hidden/>
                        <g class="nxsld-precision-resize-overlay" data-editor-resize-overlay></g>
                        <g class="nxsld-drag-preview" data-editor-drag-preview></g>
                        <g data-editor-tracking-guides></g>
                        <path data-editor-geometry-preview d=""/>
                        <g class="nxsld-selected-snap-points" data-editor-selected-snap-points></g>
                        <g data-editor-snap-marker></g>
                    </svg>
                    <div class="nxsld-dynamic-input" data-editor-dynamic-input hidden>
                        <span data-editor-dynamic-prompt>Specify point</span>
                        <b data-editor-dynamic-primary>0.000</b>
                        <b data-editor-dynamic-secondary>0.00°</b>
                    </div>
                    <div class="nxsld-snap-tooltip" data-editor-snap-tooltip hidden>Endpoint</div>
                    <div class="nxsld-wire-toast" data-editor-wire-toast hidden aria-live="polite"></div>
                    <div class="nxsld-canvas-crosshair" data-editor-crosshair aria-hidden="true"><i></i><b></b></div>
                    <div class="nxsld-origin-marker" aria-hidden="true"><span>X</span><span>Y</span></div>
                    <div class="nxsld-engine-badge" data-editor-geometry-badge><i></i><span>maxGraph 0.24</span><span class="nxsld-geometry-contract" data-editor-geometry-version>GEOM 67M.1</span><span class="nxsld-routing-contract" data-editor-routing-version>ROUTE 67M.2</span><span class="nxsld-label-contract" data-editor-label-placement-badge><span data-editor-label-placement-version>LABEL 67M.3</span> · <b data-editor-label-placement-count>CLEAR</b></span><span class="nxsld-visual-contract" data-editor-visual-regression-badge><span data-editor-visual-regression-version>VISUAL 67M.4</span> · <b data-editor-visual-regression-count>LOCKED</b></span><b data-editor-node-count>0</b> devices<b data-editor-wire-count>0</b> wires<b data-editor-line-count>0</b> geometry<b data-editor-geometry-issue-count>0</b> issues</div>
                    <div class="nxsld-empty-selection-hint" data-editor-canvas-hint><kbd>W</kbd> choose Wire, then connect two symbols</div>
                    <div class="nxsld-viewport-controls" aria-label="Viewport navigation controls">
                        <button type="button" data-editor-tool="pan" title="Pan (H)">PAN</button>
                        <button type="button" data-editor-action="fit" title="Fit drawing (0)">FIT</button>
                        <button type="button" data-editor-action="zoom-out" title="Zoom out">−</button>
                        <output data-editor-floating-zoom>100%</output>
                        <button type="button" data-editor-action="zoom-in" title="Zoom in">+</button>
                    </div>
                </div>
            </section>

            <aside id="nxsld-properties-inspector" class="nxsld-inspector" data-editor-panel="inspector" aria-label="Properties inspector" aria-hidden="false">
                <header class="nxsld-panel-header">
                    <div><small>INSPECTOR</small><h2 data-editor-inspector-heading>No selection</h2></div>
                    <button type="button" data-editor-toggle-panel="inspector" aria-label="Collapse properties inspector" aria-controls="nxsld-properties-inspector" aria-expanded="true" title="Collapse inspector (])"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>
                </header>
                <div class="nxsld-inspector-tabs" role="tablist">
                    <button type="button" class="is-active" data-editor-inspector-tab="properties">Properties</button>
                    <button type="button" data-editor-inspector-tab="data">Engineering</button>
                    <button type="button" data-editor-inspector-tab="controller">Controller <b data-editor-controller-tab-count>{{ (int) ($controllerSummary['total'] ?? 0) }}</b></button>
                    <button type="button" data-editor-inspector-tab="layers">Layers</button>
                    <button type="button" data-editor-inspector-tab="checks">Checks <b data-editor-check-tab-count>—</b></button>
                </div>

                <div class="nxsld-inspector-body is-active" data-editor-inspector-panel="properties">
                    <div class="nxsld-inspector-empty" data-editor-inspector-empty>
                        <span><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg></span>
                        <strong>Select an object</strong>
                        <p>Inspect drafting geometry, engineering authority, and display metadata.</p>
                    </div>
                    <form class="nxsld-property-form" data-editor-inspector-form hidden autocomplete="off">
                        <div class="nxsld-object-profile" data-editor-object-profile>
                            <span class="nxsld-object-profile-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg></span>
                            <div><small data-editor-profile-eyebrow>DRAWING OBJECT</small><strong data-editor-profile-title>Selected object</strong><p data-editor-profile-meta>Editable drafting geometry</p></div>
                            <em data-editor-profile-state>READY</em>
                        </div>
                        <div class="nxsld-authority-help" data-editor-authority-help hidden></div>
                        <section data-editor-non-wire-section>
                            <h3>IDENTITY</h3>
                            <label><span data-editor-field-label="title">Equipment name</span><input type="text" data-editor-field="title" maxlength="120"></label>
                            <label><span data-editor-field-label="tag">Equipment tag <em>optional</em></span><input type="text" data-editor-field="tag" maxlength="40"></label>
                            <label><span data-editor-field-label="subtitle">Description</span><textarea data-editor-field="subtitle" maxlength="180" rows="2"></textarea></label>
                            <label><span data-editor-field-label="rating">Main rating</span><textarea data-editor-field="rating" maxlength="180" rows="2"></textarea></label>
                            <div class="nxsld-unit-grid" data-editor-engineering-fields>
                                <label><span>Rated power</span><i><input type="number" data-editor-field="rated_power" step="0.01"><b>kW</b></i></label>
                                <label><span>Rated voltage</span><i><input type="number" data-editor-field="rated_voltage" step="1"><b>V</b></i></label>
                                <label><span>Rated current</span><i><input type="number" data-editor-field="rated_current" step="0.1"><b>A</b></i></label>
                                <label><span>Manufacturer</span><i><input type="text" data-editor-field="manufacturer" maxlength="80"></i></label>
                            </div>
                        </section>
                        <section class="nxsld-wire-engineering-panel" data-editor-wire-panel hidden>
                            <div class="nxsld-wire-panel-head">
                                <div><small>CONNECTED CIRCUIT</small><h3>SMART WIRE</h3><p>Port-bound electrical connection with persistent routing and cable metadata.</p></div>
                                <span data-editor-wire-network-badge>WIRE</span>
                            </div>
                            <div class="nxsld-wire-route-card">
                                <div><small>SOURCE</small><strong data-editor-wire-source>—</strong></div>
                                <span><i></i><b data-editor-wire-route-state>OBSTACLE-AWARE</b><i></i></span>
                                <div><small>DESTINATION</small><strong data-editor-wire-target>—</strong></div>
                            </div>
                            <div class="nxsld-wire-route-metrics" aria-label="Smart route diagnostics">
                                <span><small>ENGINE</small><b data-editor-wire-route-strategy>A* VISIBILITY GRID</b></span>
                                <span><small>CLEARANCE</small><b data-editor-wire-route-clearance>STANDARD</b></span>
                                <span><small>BENDS</small><b data-editor-wire-route-bends>0</b></span>
                                <span><small>CROSSINGS</small><b data-editor-wire-route-crossings>0</b></span>
                                <span><small>LENGTH</small><b><i data-editor-wire-route-length>0</i> u</b></span>
                            </div>
                            <div class="nxsld-wire-field-grid">
                                <label><span>Wire ID</span><input type="text" data-editor-wire-field="wire_id" maxlength="40" placeholder="W-DC-001"></label>
                                <label class="is-authority-locked"><span>Network type</span><select data-editor-wire-field="network" aria-describedby="wire-network-authority">
                                    <option value="dc_positive">DC positive</option>
                                    <option value="dc_negative">DC negative</option>
                                    <option value="dc_power">DC power</option>
                                    <option value="battery_positive">Battery positive</option>
                                    <option value="battery_negative">Battery negative</option>
                                    <option value="battery_dc">Battery DC</option>
                                    <option value="ac_power">AC power</option>
                                    <option value="neutral">Neutral</option>
                                    <option value="protective_earth">Protective earth</option>
                                    <option value="communication">Communication</option>
                                    <option value="control">Control</option>
                                    <option value="measurement">Measurement</option>
                                    <option value="unclassified">Unclassified</option>
                                </select><small id="wire-network-authority" class="nxsld-field-authority-note">Derived from connected engineering ports</small></label>
                                <label><span>Cable tag</span><input type="text" data-editor-wire-field="cable_tag" maxlength="40" placeholder="CBL-DC-01"></label>
                                <label><span>Cable type</span><input type="text" data-editor-wire-field="cable_type" maxlength="60" placeholder="PV1-F"></label>
                                <label><span>Conductor size</span><i><input type="text" data-editor-wire-field="cable_size" maxlength="30" placeholder="6"><b>mm²</b></i></label>
                                <label><span>Number of cores</span><input type="number" data-editor-wire-field="cores" min="1" max="99" step="1" placeholder="1"></label>
                                <label><span>Voltage class</span><input type="text" data-editor-wire-field="voltage_class" maxlength="40" placeholder="1.5 kV DC"></label>
                                <label><span>Installation method</span><select data-editor-wire-field="installation_method">
                                    <option value="">Not specified</option>
                                    <option value="Cable tray">Cable tray</option>
                                    <option value="Conduit">Conduit</option>
                                    <option value="Underground">Underground</option>
                                    <option value="Free air">Free air</option>
                                    <option value="Panel internal">Panel internal</option>
                                </select></label>
                                <label><span>Routing mode</span><select data-editor-wire-field="routing_mode">
                                    <option value="auto">Obstacle-aware auto-route</option>
                                    <option value="manual">Preserve manual route</option>
                                </select></label>
                                <label><span>Routing clearance</span><select data-editor-wire-field="routing_clearance">
                                    <option value="compact">Compact · 12 u</option>
                                    <option value="standard">Standard · 18 u</option>
                                    <option value="generous">Generous · 26 u</option>
                                </select></label>
                                <label class="is-toggle"><span>Canvas label</span><button type="button" data-editor-wire-field="show_label" aria-pressed="true"><span>Show wire ID</span><i></i></button></label>
                                <label class="is-full"><span>Engineering notes</span><textarea data-editor-wire-field="notes" maxlength="240" rows="2" placeholder="Cable schedule or installation note"></textarea></label>
                            </div>
                            <div class="nxsld-wire-actions">
                                <button type="button" data-editor-action="wire-reset-route"><svg viewBox="0 0 24 24"><path d="M4 12h5l3-5 3 10 3-5h2"/><path d="M5 5v4h4M19 19v-4h-4"/></svg><span>Rebuild smart route</span></button>
                                <button type="button" data-editor-action="wire-select-source"><svg viewBox="0 0 24 24"><circle cx="6" cy="12" r="3"/><path d="M9 12h9M15 8l4 4-4 4"/></svg><span>Select source</span></button>
                                <button type="button" data-editor-action="wire-select-target"><svg viewBox="0 0 24 24"><circle cx="18" cy="12" r="3"/><path d="M6 12h9M9 8l-4 4 4 4"/></svg><span>Select destination</span></button>
                            </div>
                        </section>
                        <section class="nxsld-transform-section" data-editor-non-wire-section>
                            <div class="nxsld-section-heading"><div><h3>DRAFTING GEOMETRY</h3><p>Precision position, size, and orientation.</p></div><span data-editor-transform-status>0° · NORMAL</span></div>
                            <div class="nxsld-property-grid">
                                <label><span data-editor-field-label="x">Position X</span><input type="number" data-editor-field="x" min="-10000" max="10000" step="10"></label>
                                <label><span data-editor-field-label="y">Position Y</span><input type="number" data-editor-field="y" min="-10000" max="10000" step="10"></label>
                                <label><span data-editor-field-label="w">Width</span><input type="number" data-editor-field="w" min="0" max="5000" step="10"></label>
                                <label><span data-editor-field-label="h">Height</span><input type="number" data-editor-field="h" min="0" max="5000" step="10"></label>
                                <label class="is-full"><span data-editor-field-label="rotation">Rotation</span><select data-editor-field="rotation"><option value="0">0° · Standard</option><option value="90">90° · Clockwise</option><option value="180">180° · Reversed</option><option value="270">270° · Counter-clockwise</option></select></label>
                            </div>
                            <div class="nxsld-transform-pad" aria-label="Transform selected object">
                                <button type="button" data-editor-action="rotate-left" data-editor-requires-transform title="Rotate left"><svg viewBox="0 0 24 24"><path d="M5 7V3m0 0h4M5 3l4 4M6.8 16.2A7 7 0 1 0 7 7"/></svg><span>Rotate left</span></button>
                                <button type="button" data-editor-action="rotate-right" data-editor-requires-transform title="Rotate right"><svg viewBox="0 0 24 24"><path d="M19 7V3m0 0h-4m4 0-4 4M17.2 16.2A7 7 0 1 1 17 7"/></svg><span>Rotate right</span></button>
                                <button type="button" data-editor-action="mirror-horizontal" data-editor-requires-transform title="Mirror horizontal"><svg viewBox="0 0 24 24"><path d="M12 3v18M3 6l7 3v6l-7 3zM21 6l-7 3v6l7 3z"/></svg><span>Mirror H</span></button>
                                <button type="button" data-editor-action="mirror-vertical" data-editor-requires-transform title="Mirror vertical"><svg viewBox="0 0 24 24"><path d="M3 12h18M6 3l3 7h6l3-7zM6 21l3-7h6l3 7z"/></svg><span>Mirror V</span></button>
                            </div>
                        </section>
                        <section class="nxsld-display-control-section" data-editor-display-controls data-editor-non-wire-section>
                            <div class="nxsld-section-heading"><div><h3>SYMBOL VISIBILITY</h3><p>Keep drawing density appropriate for the issue stage.</p></div></div>
                            <div class="nxsld-visibility-grid">
                                <button type="button" data-editor-visibility="tag" aria-pressed="true"><span><svg viewBox="0 0 24 24"><path d="M4 7h10l6 5-6 5H4zM8 12h.01"/></svg><b>Equipment tag</b><small>Show designator</small></span><i></i></button>
                                <button type="button" data-editor-visibility="rating" aria-pressed="true"><span><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg><b>Main rating</b><small>Show electrical value</small></span><i></i></button>
                                <button type="button" data-editor-visibility="subtitle" aria-pressed="true"><span><svg viewBox="0 0 24 24"><path d="M5 6h14M5 11h14M5 16h9"/></svg><b>Description</b><small>Show equipment note</small></span><i></i></button>
                                <button type="button" data-editor-visibility="ports" aria-pressed="true"><span><svg viewBox="0 0 24 24"><path d="M3 12h5m8 0h5M8 8v8h8V8z"/></svg><b>Connection ports</b><small>Show engineering anchors</small></span><i></i></button>
                            </div>
                        </section>
                        <section>
                            <h3>CONTROL</h3>
                            <div class="nxsld-inspector-actions">
                                <button type="button" data-editor-action="duplicate" data-editor-requires-selection><svg viewBox="0 0 24 24"><rect x="7" y="7" width="12" height="12" rx="2"/><path d="M16 7V4H4v12h3"/></svg><span>Duplicate</span></button>
                                <button type="button" data-editor-action="reset-size" data-editor-requires-selection><svg viewBox="0 0 24 24"><path d="M5 9V5h4M19 9V5h-4M5 15v4h4M19 15v4h-4M8 8h8v8H8z"/></svg><span>Standard size</span></button>
                                <button type="button" data-editor-action="lock" data-editor-requires-selection><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>Lock</span></button>
                                <button type="button" data-editor-action="unlock" data-editor-requires-selection><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M16 10V7a4 4 0 0 0-7.7-1.5"/></svg><span>Unlock</span></button>
                                <button type="button" data-editor-action="group" data-editor-requires-two><svg viewBox="0 0 24 24"><rect x="3" y="4" width="7" height="7"/><rect x="14" y="4" width="7" height="7"/><rect x="3" y="15" width="7" height="6"/><rect x="14" y="15" width="7" height="6"/></svg><span>Group</span></button>
                                <button type="button" data-editor-action="ungroup" data-editor-requires-group><svg viewBox="0 0 24 24"><rect x="3" y="4" width="7" height="7"/><rect x="14" y="4" width="7" height="7"/><rect x="3" y="15" width="7" height="6"/><rect x="14" y="15" width="7" height="6"/></svg><span>Ungroup</span></button>
                                <button type="button" data-editor-action="reset-transform" data-editor-requires-transform><svg viewBox="0 0 24 24"><path d="M4 4v6h6M5.5 15.5A7 7 0 1 0 7 7"/></svg><span>Reset transform</span></button>
                            </div>
                            <dl class="nxsld-property-readout">
                                <div><dt>Object type</dt><dd data-editor-inspector-kind>—</dd></div>
                                <div><dt>Authority</dt><dd data-editor-inspector-authority>—</dd></div>
                                <div><dt>Layer</dt><dd data-editor-inspector-layer>—</dd></div>
                                <div><dt>Structure</dt><dd data-editor-inspector-group>Independent object</dd></div>
                                <div><dt>IEC status</dt><dd data-editor-inspector-standard>—</dd></div>
                                <div><dt>Engineering ports</dt><dd data-editor-inspector-ports>—</dd></div>
                            </dl>
                            <details class="nxsld-system-reference"><summary>System reference</summary><code data-editor-inspector-id>—</code></details>
                        </section>
                        <button type="button" class="nxsld-danger-button" data-editor-delete @disabled($isLocked)><svg viewBox="0 0 24 24"><path d="M5 7h14m-9 0V4h4v3m-7 0 1 13h8l1-13"/></svg><span data-editor-delete-label>Remove selected object</span></button>
                    </form>
                </div>

                <div class="nxsld-inspector-body" data-editor-inspector-panel="data">
                    <section class="nxsld-engineering-card">
                        <span>CALCULATION AUTHORITY</span>
                        <strong>{{ ($meta['calculation_verified'] ?? false) ? 'Verified technical source' : 'Engineering draft source' }}</strong>
                        <p>Calculated ratings remain read-only. Layout, routing, visibility, notes, and manual drafting objects remain editable while this revision is a draft.</p>
                    </section>
                    <dl class="nxsld-engineering-summary">
                        <div><dt>PV capacity</dt><dd>{{ number_format((float) ($summary['installed_pv_kw'] ?? 0), 2) }} kWp</dd></div>
                        <div><dt>Array</dt><dd>{{ $summary['string_layout'] ?? 'Pending' }}</dd></div>
                        <div><dt>Inverter AC</dt><dd>{{ number_format((float) ($summary['inverter_ac_kw'] ?? 0), 2) }} kW</dd></div>
                        <div><dt>Supply</dt><dd>{{ strtoupper(str_replace('_', ' ', (string) ($summary['supply_phase'] ?? 'Utility'))) }}</dd></div>
                    </dl>
                </div>


                <div class="nxsld-inspector-body nxsld-controller-panel" data-editor-inspector-panel="controller">
                    <section class="nxsld-controller-hero is-{{ $controllerStatus }}" data-editor-controller-hero>
                        <div><span>APP67K · PLTS ENGINEERING CONTROLLER</span><strong data-editor-controller-heading>{{ str($controllerStatus)->headline() }}</strong><p data-editor-controller-message>{{ $engineeringController['message'] ?? 'Engineering controller status is unavailable.' }}</p></div>
                        <button type="button" data-editor-controller-sync @disabled($isLocked)><svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 1-2.3-5.7M20 4v7h-7"/></svg>Synchronize source</button>
                    </section>

                    <div class="nxsld-controller-counts">
                        <div><span>Errors</span><b data-editor-controller-count="error">{{ (int) ($controllerSummary['error'] ?? 0) }}</b></div>
                        <div><span>Warnings</span><b data-editor-controller-count="warning">{{ (int) ($controllerSummary['warning'] ?? 0) }}</b></div>
                        <div><span>Notices</span><b data-editor-controller-count="notice">{{ (int) ($controllerSummary['notice'] ?? 0) }}</b></div>
                    </div>

                    <section class="nxsld-controller-section">
                        <header><span>CONTROLLED SOURCE CHAIN</span><small>Authority flows left to right</small></header>
                        <div class="nxsld-source-chain" data-editor-controller-sources>
                            @foreach ($controllerSources as $sourceItem)
                                <article class="is-{{ $sourceItem['status'] ?? 'pending' }}"><i></i><div><strong>{{ $sourceItem['label'] ?? 'Source' }}</strong><small>{{ $sourceItem['detail'] ?? '—' }}</small></div><b>{{ strtoupper($sourceItem['status'] ?? 'pending') }}</b></article>
                            @endforeach
                        </div>
                    </section>

                    <section class="nxsld-controller-section">
                        <header><span>CHANGE IMPACT</span><small>What must remain synchronized</small></header>
                        <div class="nxsld-impact-grid" data-editor-controller-impacts>
                            @foreach ($controllerImpacts as $impact)
                                <article class="is-{{ $impact['status'] ?? 'ready' }}"><span>{{ $impact['label'] ?? 'Impact' }}</span><b>{{ (int) ($impact['count'] ?? 0) }}</b></article>
                            @endforeach
                        </div>
                    </section>

                    <section class="nxsld-controller-section">
                        <header><span>CONTROLLER FINDINGS</span><small>Click a finding to locate its SLD object</small></header>
                        <div class="nxsld-controller-findings" data-editor-controller-conflicts>
                            @forelse ($controllerConflicts as $conflict)
                                <button type="button" class="is-{{ $conflict['severity'] ?? 'notice' }}" data-editor-controller-conflict='@json($conflict)'><i></i><span><b>{{ $conflict['code'] ?? 'K---' }} · {{ $conflict['title'] ?? 'Engineering finding' }}</b><small>{{ $conflict['message'] ?? '' }}</small><em>{{ $conflict['action'] ?? '' }}</em></span></button>
                            @empty
                                <p class="nxsld-controller-empty">No controller conflicts. Source chain is synchronized.</p>
                            @endforelse
                        </div>
                    </section>

                    <details class="nxsld-controller-schedule">
                        <summary><span>Cable schedule</span><b>{{ count($controllerCables) }}</b></summary>
                        <div data-editor-controller-cables>
                            @foreach ($controllerCables as $cable)
                                <article><strong>{{ $cable['wire_id'] ?? 'CABLE' }}</strong><span>{{ $cable['label'] ?? '' }}</span><small>{{ $cable['detail'] ?? '' }}</small></article>
                            @endforeach
                        </div>
                    </details>
                    <details class="nxsld-controller-schedule">
                        <summary><span>Protection schedule</span><b>{{ count($controllerProtection) }}</b></summary>
                        <div data-editor-controller-protection>
                            @foreach ($controllerProtection as $protectionItem)
                                <article><strong>{{ $protectionItem['id'] ?? 'PROTECTION' }}</strong><span>{{ $protectionItem['label'] ?? '' }}</span><small>{{ number_format((float) ($protectionItem['rating'] ?? 0), 0) }} {{ $protectionItem['unit'] ?? '' }} · {{ $protectionItem['designation'] ?? '' }}</small></article>
                            @endforeach
                        </div>
                    </details>

                    <section class="nxsld-controller-actions">
                        <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}">Technical Calculation</a>
                        <a href="{{ route('companies.boms.show', [$company, $project]) }}">BOM / BOQ</a>
                        <a href="{{ route('companies.financial-analyses.show', [$company, $project]) }}">Financial Analysis</a>
                        <a href="{{ route('companies.quotations.show', [$company, $project]) }}">Quotation</a>
                    </section>
                    <label class="nxsld-controller-ack"><span>Review note</span><textarea data-editor-controller-reason rows="2" placeholder="Reason for acknowledging non-blocking review items"></textarea></label>
                    <button type="button" class="nxsld-controller-ack-button" data-editor-controller-ack @disabled($isLocked)>Acknowledge review items</button>
                    <footer class="nxsld-controller-disclaimer">Technical Calculation controls ratings and topology. SLD controls approved drafting layout. BOM/BOQ, financial analysis, and quotation remain downstream snapshots and must be regenerated when their source becomes stale.</footer>
                </div>

                <div class="nxsld-inspector-body nxsld-checks-panel" data-editor-inspector-panel="checks">
                    <section class="nxsld-check-summary is-pending" data-editor-check-summary>
                        <div><span>APP67I ELECTRICAL RULE CHECK</span><strong data-editor-check-heading>Check pending</strong><p data-editor-check-message>Run the deterministic rule engine to inspect topology, terminal compatibility, grounding, tags, and cable metadata.</p></div>
                        <button type="button" data-editor-run-check><svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 1-2.3-5.7M20 4v7h-7"/></svg>Run check</button>
                    </section>
                    <div class="nxsld-check-counts" aria-label="Electrical issue counts">
                        <button type="button" class="is-active" data-editor-check-filter="all"><span>All</span><b data-editor-check-count="total">0</b></button>
                        <button type="button" data-editor-check-filter="error"><span>Errors</span><b data-editor-check-count="error">0</b></button>
                        <button type="button" data-editor-check-filter="warning"><span>Warnings</span><b data-editor-check-count="warning">0</b></button>
                        <button type="button" data-editor-check-filter="notice"><span>Notices</span><b data-editor-check-count="notice">0</b></button>
                    </div>
                    <div class="nxsld-check-list" data-editor-check-list><p>No check result is available.</p></div>
                    <footer class="nxsld-check-disclaimer">Rule checks support engineering review. They do not replace protection coordination, conductor calculations, manufacturer instructions, or local authority approval.</footer>
                </div>

                <div class="nxsld-inspector-body" data-editor-inspector-panel="layers">
                    <div class="nxsld-layer-toolbar"><span>LAYER CONTROL</span><button type="button" data-editor-layer-show-all>Show all</button><button type="button" data-editor-layer-print-all>Print all</button></div>
                    <div class="nxsld-layer-list" data-editor-layer-list>
                        @foreach ($layerDefinitions as [$layerName, $layerLabel, $layerDescription])
                            <div class="nxsld-layer-row" data-editor-layer="{{ $layerName }}"><span><i></i><b>{{ $layerName }}</b><small>{{ $layerDescription }}</small></span><div><button type="button" class="is-active" data-editor-layer-action="visible" title="Toggle layer visibility"><svg viewBox="0 0 24 24"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2"/></svg></button><button type="button" data-editor-layer-action="locked" title="Lock layer"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></button><button type="button" class="is-active" data-editor-layer-action="print" title="Include layer in export"><svg viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 17H4V9h16v8h-2M7 14h10v7H7z"/></svg></button></div></div>
                        @endforeach
                    </div>
                    <section class="nxsld-excluded-section">
                        <header><span>EXCLUDED FROM DRAWING</span><b data-editor-excluded-count>0</b></header>
                        <div data-editor-excluded-list><p>No calculated objects are excluded.</p></div>
                    </section>
                </div>
            </aside>

            <button type="button" class="nxsld-panel-rail is-right" data-editor-open-panel="inspector" aria-label="Open properties inspector" aria-controls="nxsld-properties-inspector" aria-expanded="true">
                <b>‹</b><span>INSPECTOR</span><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>
            </button>
        </section>

        <div class="nxsld-command-line" data-editor-command-line>
            <button type="button" data-editor-command-history aria-label="Command history">☰</button>
            <span>Command:</span><strong data-editor-command-name>SELECT</strong><p data-editor-command-prompt>Ready. Select objects or choose a drafting command.</p><kbd>ESC</kbd>
        </div>

        <footer class="nxsld-statusbar">
            <div class="nxsld-status-left">
                <span class="is-ready"><i></i>ENGINE READY</span>
                <span class="nxsld-mono">SCHEMA {{ $graph['schema_version'] ?? 'nexorin-sld' }}</span>
                <span>{{ $isLocked ? 'READ ONLY' : 'DRAFT EDITABLE' }}</span>
            </div>
            <div class="nxsld-status-center"><span data-editor-selection-status>No object selected</span></div>
            <div class="nxsld-status-right">
                <button type="button" class="is-active" data-editor-status-toggle="grid" title="Grid display (F7)">GRID <b data-editor-grid-size>20</b></button>
                <button type="button" class="is-active" data-editor-status-toggle="snap" title="Grid snap (F9)">SNAP</button>
                <button type="button" class="{{ data_get($graph, 'editor.viewport.ortho_enabled', false) ? 'is-active' : '' }}" data-editor-status-toggle="ortho" title="Orthogonal mode (F8)">ORTHO</button>
                <button type="button" class="is-active" data-editor-status-toggle="polar" title="Polar tracking (F10)">POLAR</button>
                <span class="nxsld-status-menu" data-editor-osnap-menu-shell>
                    <button type="button" class="is-active" data-editor-status-toggle="osnap" title="Object snap (F3)">OSNAP</button>
                    <button type="button" class="nxsld-status-menu-toggle" data-editor-osnap-menu-toggle aria-expanded="false" aria-label="Object snap settings" title="Object snap settings"><svg viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></button>
                    <div class="nxsld-osnap-menu" data-editor-osnap-menu hidden>
                        <header><span>OBJECT SNAP SETTINGS</span><strong data-editor-osnap-summary>8 of 8 modes enabled</strong></header>
                        <div class="nxsld-osnap-grid">
                            <label><input type="checkbox" data-editor-osnap-mode="endpoint" checked><i class="is-endpoint"></i><span><b>Endpoint</b><small>Line and geometry ends</small></span></label>
                            <label><input type="checkbox" data-editor-osnap-mode="midpoint" checked><i class="is-midpoint"></i><span><b>Midpoint</b><small>Exact segment center</small></span></label>
                            <label><input type="checkbox" data-editor-osnap-mode="center" checked><i class="is-center"></i><span><b>Center</b><small>Circle and arc center</small></span></label>
                            <label><input type="checkbox" data-editor-osnap-mode="geometric_center" checked><i class="is-geometric-center"></i><span><b>Geometric center</b><small>Rectangle and equipment</small></span></label>
                            <label><input type="checkbox" data-editor-osnap-mode="quadrant" checked><i class="is-quadrant"></i><span><b>Quadrant</b><small>Circle cardinal points</small></span></label>
                            <label><input type="checkbox" data-editor-osnap-mode="intersection" checked><i class="is-intersection"></i><span><b>Intersection</b><small>Crossing linework</small></span></label>
                            <label><input type="checkbox" data-editor-osnap-mode="nearest" checked><i class="is-nearest"></i><span><b>Nearest</b><small>Closest point on geometry</small></span></label>
                            <label data-editor-osnap-wire-only><input type="checkbox" data-editor-osnap-mode="port" checked><i class="is-port"></i><span><b>Engineering port <em>WIRE ONLY</em></b><small>Canonical electrical terminal</small></span></label>
                        </div>
                        <footer><button type="button" data-editor-osnap-all>Enable all</button><button type="button" data-editor-osnap-none>Clear all</button><kbd>F3</kbd></footer>
                    </div>
                </span>
                <button type="button" class="is-active" data-editor-status-toggle="otrack" title="Object snap tracking (F11)">OTRACK</button>
                <button type="button" class="is-active" data-editor-status-toggle="dynamic" title="Dynamic input (F12)">DYN</button>
                <span class="nxsld-status-zoom"><button type="button" data-editor-action="zoom-out">−</button><output data-editor-zoom-label>100%</output><input type="range" min="25" max="200" step="5" value="100" data-editor-zoom-range aria-label="Drawing zoom"><button type="button" data-editor-action="zoom-in">+</button></span>
            </div>
        </footer>
    @endif

    <section class="nxsld-close-dialog" data-editor-close-dialog hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="nxsldCloseTitle">
        <div class="nxsld-close-card">
            <span class="nxsld-close-icon"><svg viewBox="0 0 24 24"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
            <div><small>UNSAVED DRAFT</small><h2 id="nxsldCloseTitle">Save your drawing before leaving?</h2><p>Your layout, routing, exclusions, and manual objects have not been saved.</p></div>
            <footer>
                <button type="button" data-editor-close-cancel>Continue editing</button>
                <button type="button" data-editor-close-discard>Close without saving</button>
                <button type="button" class="is-primary" data-editor-close-save>Save and close</button>
            </footer>
        </div>
    </section>
</main>
@endsection
