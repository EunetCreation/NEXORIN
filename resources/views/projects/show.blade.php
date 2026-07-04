@extends('layouts.app')

@section('title', $project->name)
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Project Details')

@section('content')
    <div class="ui-page">
        <section class="ui-detail-hero">
            <div class="ui-detail-avatar ui-project-detail-avatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M4 19V9l8-5 8 5v10"/>
                    <path d="M8 19v-6h8v6"/>
                </svg>
            </div>

            <div class="ui-detail-copy">
                <h2>{{ $project->name }}</h2>
                <p>{{ $project->project_number }} · {{ $project->customer->name }} · {{ $project->proposed_system_label }}</p>
            </div>

            <div class="ui-detail-actions">
                <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Project Directory</a>
                @if ($canViewLoadAnalysis)
                    <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Load Analysis</a>
                @endif
                @if ($canViewLocationSolar)
                    <a href="{{ route('companies.location-solar.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Location & Solar</a>
                @endif
                @if ($canViewRoofSite)
                    <a href="{{ route('companies.roof-site.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Roof & Site Survey</a>
                @endif
                @if ($canViewRecommendation)
                    <a href="{{ route('companies.recommendations.show', [$company, $project]) }}" class="ui-button ui-button-secondary">System Recommendation</a>
                @endif
                @if ($canUpdateProject && ! $project->isArchived())
                    <a href="{{ route('companies.projects.edit', [$company, $project]) }}" class="ui-button ui-button-primary">Edit Project</a>
                @endif
            </div>
        </section>

        <section class="ui-workflow-card">
            @php
                $workflow = ['draft', 'survey', 'engineering', 'quotation', 'approved', 'completed'];
                $currentIndex = array_search($project->status, $workflow, true);
            @endphp

            <div class="ui-workflow-header">
                <div>
                    <span class="ui-page-kicker">Current workflow</span>
                    <h3>{{ $project->status_label }}</h3>
                </div>
                <span class="ui-status ui-status-project-{{ $project->status }}">{{ $project->status_label }}</span>
            </div>

            <div class="ui-workflow-track" aria-label="Project workflow">
                @foreach ($workflow as $index => $workflowStatus)
                    @php
                        $completed = is_int($currentIndex) && $index < $currentIndex;
                        $active = $project->status === $workflowStatus;
                    @endphp
                    <div class="ui-workflow-step {{ $completed ? 'is-complete' : '' }} {{ $active ? 'is-active' : '' }}">
                        <span>{{ $index + 1 }}</span>
                        <strong>{{ ucfirst($workflowStatus) }}</strong>
                    </div>
                @endforeach
            </div>

            @if ($project->status === 'cancelled')
                <div class="ui-workflow-cancelled">This project is currently cancelled and may be reopened to Draft by an authorized user.</div>
            @endif
        </section>

        <div class="ui-detail-grid">
            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Project Profile</h3>
                        <p class="ui-card-description">Core project, site, and system information.</p>
                    </div>
                    @if ($project->isArchived())
                        <span class="ui-status ui-status-inactive">Archived</span>
                    @endif
                </div>
                <div class="ui-card-body">
                    <dl class="ui-info-grid">
                        <div class="ui-info-item">
                            <dt>Project Number</dt>
                            <dd>{{ $project->project_number }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Customer</dt>
                            <dd>{{ $project->customer->name }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Building Type</dt>
                            <dd>{{ $project->building_type_label }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Proposed System</dt>
                            <dd>{{ $project->proposed_system_label }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>City</dt>
                            <dd>{{ $project->city ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Province</dt>
                            <dd>{{ $project->province ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Latitude</dt>
                            <dd>{{ $project->latitude ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Longitude</dt>
                            <dd>{{ $project->longitude ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item ui-info-span-2">
                            <dt>Site Address</dt>
                            <dd>{{ $project->site_address }}</dd>
                        </div>
                        <div class="ui-info-item ui-info-span-2">
                            <dt>Project Notes</dt>
                            <dd>{{ $project->description ?: 'No project notes.' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <div class="ui-stack">
                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Team Assignment</h3>
                            <p class="ui-card-description">Current engineering and commercial owners.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        <div class="ui-member-list">
                            <div class="ui-member-item">
                                <span class="ui-member-avatar">EN</span>
                                <span class="ui-member-copy">
                                    <strong>{{ $project->assignedEngineer?->name ?? 'Engineer unassigned' }}</strong>
                                    <span>{{ $project->assignedEngineer?->email ?? 'Assign an engineer from the edit page.' }}</span>
                                </span>
                                <span class="ui-role-tag">Engineer</span>
                            </div>
                            <div class="ui-member-item">
                                <span class="ui-member-avatar">SA</span>
                                <span class="ui-member-copy">
                                    <strong>{{ $project->assignedSales?->name ?? 'Sales unassigned' }}</strong>
                                    <span>{{ $project->assignedSales?->email ?? 'Assign a sales user from the edit page.' }}</span>
                                </span>
                                <span class="ui-role-tag">Sales</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Record Audit</h3>
                            <p class="ui-card-description">Who created and last changed this project.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        <dl class="ui-info-grid">
                            <div class="ui-info-item">
                                <dt>Created By</dt>
                                <dd>{{ $project->creator?->name ?? 'System' }}</dd>
                            </div>
                            <div class="ui-info-item">
                                <dt>Created At</dt>
                                <dd>{{ $project->created_at?->format('d M Y, H:i') }}</dd>
                            </div>
                            <div class="ui-info-item">
                                <dt>Status Updated By</dt>
                                <dd>{{ $project->statusUpdater?->name ?? 'System' }}</dd>
                            </div>
                            <div class="ui-info-item">
                                <dt>Status Changed</dt>
                                <dd>{{ $project->status_changed_at?->format('d M Y, H:i') ?: 'Not recorded' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </div>
        </div>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Status History</h3>
                    <p class="ui-card-description">Every workflow status change is retained for auditability.</p>
                </div>
            </div>
            <div class="ui-card-body">
                <div class="ui-timeline">
                    @foreach ($project->statusHistories as $history)
                        <article class="ui-timeline-item">
                            <span class="ui-timeline-dot"></span>
                            <div class="ui-timeline-copy">
                                <strong>
                                    {{ $history->from_status ? ucfirst($history->from_status).' → ' : '' }}{{ ucfirst($history->to_status) }}
                                </strong>
                                <p>{{ $history->note ?: 'No status note was provided.' }}</p>
                                <small>
                                    {{ $history->changedBy?->name ?? 'System' }} · {{ $history->changed_at?->format('d M Y, H:i') }}
                                </small>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($canArchiveProject && ! $project->isArchived())
            <section class="ui-card ui-danger-zone">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Archive Project</h3>
                        <p class="ui-card-description">Only completed or cancelled projects can be archived.</p>
                    </div>
                    <form method="POST" action="{{ route('companies.projects.archive', [$company, $project]) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="ui-button ui-button-danger"
                            data-confirm="Archive this project? It will be removed from the current project list."
                            @disabled(! in_array($project->status, ['completed', 'cancelled'], true))
                        >
                            Archive Project
                        </button>
                    </form>
                </div>
            </section>
        @endif
    </div>
@endsection
