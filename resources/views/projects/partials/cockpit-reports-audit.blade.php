<nav class="nx-report-subnav" aria-label="Reports and audit sections">
    <button type="button" class="is-active" data-report-target="cockpit-report-files">Reports</button>
    <button type="button" data-report-target="cockpit-project-profile">Project details</button>
    <button type="button" data-report-target="cockpit-audit-trail">Audit trail</button>
    @if ($canArchiveProject && ! $project->isArchived())
        <button type="button" data-report-target="cockpit-danger-zone">Danger zone</button>
    @endif
</nav>

<section id="cockpit-report-files" class="nx-cockpit-section-card nx-report-anchor" data-cockpit-glow>
    <header class="nx-section-heading">
        <div class="nx-section-heading-icon nx-tone-report">
            @include('projects.partials.cockpit-icon', ['name' => 'report'])
        </div>
        <div>
            <span class="nx-section-kicker">Controlled outputs</span>
            <h3>Reports and proposal files</h3>
            <p>Open previews, download internal engineering documents, and release customer-facing files from one controlled workspace.</p>
        </div>
    </header>

    @if ($canViewReports)
        <div class="nx-report-grid">
            @if ($project->quotation)
                <a class="nx-report-card" href="{{ route('companies.quotations.pdf', [$company, $project]) }}">
                    <span class="nx-report-card-icon">@include('projects.partials.cockpit-icon', ['name' => 'quotation'])</span>
                    <span><strong>Quotation PDF</strong><small>Download the current customer quotation.</small></span>
                    <b>PDF</b>
                </a>
            @endif

            <a class="nx-report-card" href="{{ route('companies.projects.report.premium.preview', [$company, $project]) }}" target="_blank" rel="noopener">
                <span class="nx-report-card-icon">@include('projects.partials.cockpit-icon', ['name' => 'report'])</span>
                <span><strong>Internal report preview</strong><small>Review the browser-rendered engineering report.</small></span>
                <b>Preview</b>
            </a>

            <a class="nx-report-card" href="{{ route('companies.projects.report.premium.pdf', [$company, $project]) }}">
                <span class="nx-report-card-icon">@include('projects.partials.cockpit-icon', ['name' => 'report'])</span>
                <span><strong>Internal engineering report</strong><small>Download the complete technical and audit report.</small></span>
                <b>PDF+</b>
            </a>

            <a class="nx-report-card" href="{{ route('companies.projects.report.client-proposal.preview', [$company, $project]) }}" target="_blank" rel="noopener">
                <span class="nx-report-card-icon">@include('projects.partials.cockpit-icon', ['name' => 'project'])</span>
                <span><strong>Client proposal preview</strong><small>Inspect the customer-facing proposal before export.</small></span>
                <b>Preview</b>
            </a>

            <a class="nx-report-card" href="{{ route('companies.projects.report.client-proposal.pdf', [$company, $project]) }}">
                <span class="nx-report-card-icon">@include('projects.partials.cockpit-icon', ['name' => 'project'])</span>
                <span><strong>Draft proposal PDF</strong><small>Download the proposal with the release watermark.</small></span>
                <b>Draft</b>
            </a>

            @if ($releaseGate['ready'] ?? false)
                <a class="nx-report-card is-final" href="{{ route('companies.projects.report.client-proposal.final-pdf', [$company, $project]) }}">
                    <span class="nx-report-card-icon">@include('projects.partials.cockpit-icon', ['name' => 'release'])</span>
                    <span><strong>Final Client Proposal PDF</strong><small>Download the approved proposal without a draft watermark.</small></span>
                    <b>Final</b>
                </a>
            @else
                <span class="nx-report-card is-disabled" aria-disabled="true">
                    <span class="nx-report-card-icon">@include('projects.partials.cockpit-icon', ['name' => 'release'])</span>
                    <span><strong>Final Client Proposal PDF</strong><small>Locked until the Client Release Gate is ready.</small></span>
                    <b>Locked</b>
                </span>
            @endif
        </div>
    @else
        <div class="nx-cockpit-empty is-compact">
            <span class="nx-cockpit-empty-icon">@include('projects.partials.cockpit-icon', ['name' => 'report', 'size' => 24])</span>
            <div><strong>Report access is unavailable</strong><p>Your current company role does not provide access to project report outputs.</p></div>
        </div>
    @endif
</section>

<div class="nx-audit-grid">
    <section id="cockpit-project-profile" class="nx-cockpit-section-card nx-report-anchor" data-cockpit-glow>
        <header class="nx-section-heading is-compact">
            <div class="nx-section-heading-icon nx-tone-audit">
                @include('projects.partials.cockpit-icon', ['name' => 'project'])
            </div>
            <div>
                <span class="nx-section-kicker">Project context</span>
                <h3>Profile and ownership</h3>
            </div>
        </header>

        <dl class="nx-profile-grid">
            <div><dt>Project number</dt><dd>{{ $project->project_number }}</dd></div>
            <div><dt>Customer</dt><dd>{{ $project->customer->name }}</dd></div>
            <div><dt>Building type</dt><dd>{{ $project->building_type_label }}</dd></div>
            <div><dt>Proposed system</dt><dd>{{ $project->proposed_system_label }}</dd></div>
            <div><dt>City</dt><dd>{{ $project->city ?: 'Not provided' }}</dd></div>
            <div><dt>Province</dt><dd>{{ $project->province ?: 'Not provided' }}</dd></div>
            <div><dt>Latitude</dt><dd>{{ $project->latitude ?: 'Not provided' }}</dd></div>
            <div><dt>Longitude</dt><dd>{{ $project->longitude ?: 'Not provided' }}</dd></div>
            <div><dt>Engineer</dt><dd>{{ $project->assignedEngineer?->name ?? 'Unassigned' }}</dd></div>
            <div><dt>Sales</dt><dd>{{ $project->assignedSales?->name ?? 'Unassigned' }}</dd></div>
            <div class="is-wide"><dt>Site address</dt><dd>{{ $project->site_address }}</dd></div>
            <div class="is-wide"><dt>Project notes</dt><dd>{{ $project->description ?: 'No project notes.' }}</dd></div>
        </dl>
    </section>

    <section id="cockpit-audit-trail" class="nx-cockpit-section-card nx-report-anchor" data-cockpit-glow>
        <header class="nx-section-heading is-compact">
            <div class="nx-section-heading-icon nx-tone-audit">
                @include('projects.partials.cockpit-icon', ['name' => 'audit'])
            </div>
            <div>
                <span class="nx-section-kicker">Traceability</span>
                <h3>Status and record audit</h3>
            </div>
        </header>

        <div class="nx-audit-facts">
            <div><span>Created by</span><strong>{{ $project->creator?->name ?? 'System' }}</strong><small>{{ $project->created_at?->format('d M Y, H:i') }}</small></div>
            <div><span>Status updated by</span><strong>{{ $project->statusUpdater?->name ?? 'System' }}</strong><small>{{ $project->status_changed_at?->format('d M Y, H:i') ?: 'Not recorded' }}</small></div>
        </div>

        <div class="nx-timeline">
            @forelse ($project->statusHistories as $history)
                <article class="nx-timeline-item">
                    <span class="nx-timeline-dot"></span>
                    <div>
                        <strong>{{ $history->from_status ? ucfirst($history->from_status).' → ' : '' }}{{ ucfirst($history->to_status) }}</strong>
                        <p>{{ $history->note ?: 'No status note was provided.' }}</p>
                        <small>{{ $history->changedBy?->name ?? 'System' }} · {{ $history->changed_at?->format('d M Y, H:i') }}</small>
                    </div>
                </article>
            @empty
                <div class="nx-cockpit-empty is-compact"><div><strong>No status history</strong><p>Status changes will appear here.</p></div></div>
            @endforelse
        </div>
    </section>
</div>

@if ($canArchiveProject && ! $project->isArchived())
    <section id="cockpit-danger-zone" class="nx-danger-zone nx-report-anchor">
        <div>
            <span>Danger zone</span>
            <strong>Archive this project</strong>
            <p>Only completed or cancelled projects can be archived. Archiving removes the project from the current directory.</p>
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
                Archive project
            </button>
        </form>
    </section>
@endif
