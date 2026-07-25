@extends('layouts.app')

@section('title', 'Edit Project')
@section('page-eyebrow', 'Projects')
@section('page-heading', 'Edit '.$project->project_number)

@push('styles')
    <link href="{{ asset('css/nexorin-project-editor.css') }}?v=55a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-project-flow.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-project-editor.js') }}?v=55a" defer></script>
    <script src="{{ asset('js/nexorin-project-flow.js') }}?v=55a" defer></script>
@endpush

@php
    $statusLabel = str($project->status)->replace('_', ' ')->title();
    $systemLabel = str($project->proposed_system)->replace('_', '-')->title();
    $buildingLabel = str($project->building_type)->replace('_', ' ')->title();
    $hasServerErrors = $errors->any();
@endphp

@section('content')
    <div
        class="project-editor"
        data-project-editor
        data-project-number="{{ $project->project_number }}"
        data-project-name="{{ $project->name }}"
        data-original-status="{{ $project->status }}"
        data-server-errors="{{ $hasServerErrors ? 'true' : 'false' }}"
    >
        <header class="project-editor-hero" data-editor-reveal>
            <div class="project-editor-hero-grid" aria-hidden="true"></div>
            <span class="project-editor-hero-orb project-editor-hero-orb-one" aria-hidden="true"></span>
            <span class="project-editor-hero-orb project-editor-hero-orb-two" aria-hidden="true"></span>

            <div class="project-editor-hero-copy">
                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="project-editor-back-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6"/>
                    </svg>
                    Project cockpit
                </a>

                <span class="project-editor-kicker">
                    <span aria-hidden="true"></span>
                    Controlled project revision
                </span>

                <h2>{{ $project->name }}</h2>
                <p>Update the project brief, installation site, accountable owners, and workflow state with a traceable audit history.</p>

                <div class="project-editor-meta" aria-label="Project metadata">
                    <span>
                        <small>Project</small>
                        <strong>{{ $project->project_number }}</strong>
                    </span>
                    <span>
                        <small>Current status</small>
                        <strong>{{ $statusLabel }}</strong>
                    </span>
                    <span>
                        <small>Last saved</small>
                        <strong>{{ $project->updated_at?->diffForHumans() ?? 'Not available' }}</strong>
                    </span>
                </div>
            </div>

            <div class="project-editor-hero-command">
                <div class="project-editor-hero-state">
                    <span class="project-editor-command-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 5h16v14H4z"/>
                            <path d="M8 9h8M8 13h5M8 17h3"/>
                            <path d="M17 14v5M14.5 16.5h5"/>
                        </svg>
                    </span>
                    <div>
                        <small>Revision state</small>
                        <strong data-editor-state-title>{{ $hasServerErrors ? 'Review highlighted fields' : 'Project data synchronized' }}</strong>
                        <p data-editor-state-copy>
                            {{ $hasServerErrors ? 'Correct the validation issues before saving.' : 'No pending changes in this session.' }}
                        </p>
                    </div>
                </div>

                <span class="project-editor-sync-badge {{ $hasServerErrors ? 'is-attention' : '' }}" data-editor-sync-badge>
                    <span aria-hidden="true"></span>
                    <span data-editor-sync-label>{{ $hasServerErrors ? 'Needs review' : 'Saved state' }}</span>
                </span>

                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="project-editor-view-button">
                    View project
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path d="M5 12h14M13 6l6 6-6 6"/>
                    </svg>
                </a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ route('companies.projects.update', [$company, $project]) }}"
            id="projectEditorForm"
            class="project-editor-layout"
            data-project-editor-form
        >
            @csrf
            @method('PUT')

            <main class="project-editor-main">
                @include('projects.partials.form', ['project' => $project])
            </main>

            <aside class="project-editor-rail" aria-label="Project revision summary">
                <div class="project-editor-rail-sticky">
                    <section class="project-editor-control-card" data-editor-reveal>
                        <div class="project-editor-control-head">
                            <div>
                                <span>Revision control</span>
                                <h3>Live project state</h3>
                            </div>
                            <span class="project-editor-rail-state" data-editor-rail-state>Saved</span>
                        </div>

                        <div class="project-editor-change-meter">
                            <div>
                                <span>Session changes</span>
                                <strong data-editor-change-count>0 fields</strong>
                            </div>
                        </div>

                        <div class="project-editor-summary-list">
                            <div>
                                <span>Configuration</span>
                                <strong data-editor-summary="configuration">{{ $buildingLabel }} · {{ $systemLabel }}</strong>
                            </div>
                            <div>
                                <span>Site</span>
                                <strong data-editor-summary="location">{{ collect([$project->city, $project->province])->filter()->join(', ') ?: 'Location not specified' }}</strong>
                            </div>
                            <div>
                                <span>Engineering owner</span>
                                <strong data-editor-summary="engineer">{{ $project->assignedEngineer?->name ?? 'Unassigned' }}</strong>
                            </div>
                            <div>
                                <span>Sales owner</span>
                                <strong data-editor-summary="sales">{{ $project->assignedSales?->name ?? 'Unassigned' }}</strong>
                            </div>
                        </div>

                        <div class="project-editor-workflow-preview">
                            <span>Workflow transition</span>
                            <div>
                                <strong>{{ $statusLabel }}</strong>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                <strong data-editor-summary="status">{{ str(old('status', $project->status))->replace('_', ' ')->title() }}</strong>
                            </div>
                            <small data-editor-workflow-copy>No workflow transition selected.</small>
                        </div>

                        <div class="project-editor-rail-actions">
                            <button type="submit" class="project-editor-save-button" data-editor-submit disabled>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path d="M5 4h12l2 2v14H5z"/>
                                    <path d="M8 4v6h8V4M8 20v-6h8v6"/>
                                </svg>
                                <span data-editor-submit-label>Saved</span>
                            </button>
                            <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="project-editor-cancel-button">Discard and return</a>
                        </div>

                        <p class="project-editor-shortcut">
                            <kbd>Ctrl</kbd><span>+</span><kbd>S</kbd>
                            <span>Save changes</span>
                        </p>

                        <div class="project-editor-controlled-note">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M12 3l7 3v5c0 4.6-2.8 8-7 10-4.2-2-7-5.4-7-10V6l7-3z"/>
                                <path d="M8.5 12l2.2 2.2 4.8-5"/>
                            </svg>
                            <span>Project number and audit history remain protected.</span>
                        </div>
                    </section>
                </div>
            </aside>

            <div class="project-editor-mobile-dock" data-editor-mobile-dock>
                <div>
                    <span data-editor-mobile-state>Saved state</span>
                    <strong data-editor-mobile-count>No pending changes</strong>
                </div>
                <button type="submit" data-editor-submit disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
                    <span data-editor-submit-label>Saved</span>
                </button>
            </div>
        </form>
    </div>
@endsection
