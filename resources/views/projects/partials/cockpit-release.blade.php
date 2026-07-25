@php
    $releaseReady = (bool) ($releaseGate['ready'] ?? false);
    $releaseIssues = $releaseGate['issues'] ?? [];
    $releaseBlockerCount = (int) ($releaseGate['issue_count'] ?? 0);
    $releaseApprovalPending = ! $releaseReady && $releaseBlockerCount === 0;
    $releaseIsEarly = $cockpit['completed_steps'] < 5
        && ! $project->billOfMaterial
        && ! $project->quotation;
@endphp

<section class="nx-cockpit-section-card nx-release-card" data-cockpit-glow>
    <header class="nx-section-heading">
        <div class="nx-section-heading-icon nx-tone-release">
            @include('projects.partials.cockpit-icon', ['name' => 'release'])
        </div>
        <div>
            <span class="nx-section-kicker">Client release control</span>
            <h3>Client Release Gate</h3>
            <p>Engineering, pricing, quotation, and explicit approval remain separate from module completion.</p>
        </div>
        <span class="nx-state-pill {{ $releaseReady ? 'is-ready' : (($releaseGate['tone'] ?? '') === 'critical' ? 'is-critical' : 'is-warning') }}">
            {{ $releaseGate['status'] ?? 'Review required' }}
        </span>
    </header>

    <div class="nx-release-overview {{ $releaseReady ? 'is-ready' : 'is-blocked' }}">
        <div class="nx-release-overview-copy">
            <span>{{ $releaseReady ? 'Release cleared' : 'Current release position' }}</span>
            <strong>{{ $releaseReady ? 'Ready for client release' : ($releaseIsEarly ? 'Waiting for core workflow' : 'Not ready for final client release') }}</strong>
            <p>
                @if ($releaseIsEarly)
                    Release checks become meaningful after the core engineering and commercial modules are underway.
                @else
                    {{ $releaseGate['next_action'] ?? 'Review the release gate before sending the client proposal.' }}
                @endif
            </p>
        </div>
        <div class="nx-release-score {{ $releaseReady ? 'is-ready' : ($releaseApprovalPending ? 'is-approval' : 'is-blocked') }}">
            @if ($releaseReady)
                <strong>Clear</strong>
                <span>release gate</span>
            @elseif ($releaseApprovalPending)
                <strong>Approval</strong>
                <span>remaining step</span>
            @else
                <strong>{{ $releaseBlockerCount }}</strong>
                <span>release blocker{{ $releaseBlockerCount === 1 ? '' : 's' }}</span>
            @endif
        </div>
    </div>

    <div class="nx-release-matrix" aria-label="Release gate dimensions">
        <div>
            <span>Release status</span>
            <strong>{{ $releaseGate['status'] ?? 'Review Required' }}</strong>
        </div>
        <div>
            <span>Approval Status</span>
            <strong>{{ $releaseGate['approval_status_label'] ?? 'Approval Required' }}</strong>
        </div>
        <div>
            <span>Engineering</span>
            <strong>{{ $releaseGate['engineering_issue_count'] ?? 0 }} issue{{ ($releaseGate['engineering_issue_count'] ?? 0) === 1 ? '' : 's' }}</strong>
        </div>
        <div>
            <span>Commercial</span>
            <strong>{{ $releaseGate['commercial_issue_count'] ?? 0 }} issue{{ ($releaseGate['commercial_issue_count'] ?? 0) === 1 ? '' : 's' }}</strong>
        </div>
    </div>

    @if (! empty($releaseGate['approval']))
        <div class="nx-release-approval-record {{ ! empty($releaseGate['approval_stale']) ? 'is-stale' : '' }}">
            <strong>{{ $releaseGate['approval']['status_label'] ?? 'Approval recorded' }}</strong>
            <span>Approved by {{ $releaseGate['approval']['approved_by'] ?? 'System' }} at {{ $releaseGate['approval']['approved_at'] ?? 'unknown time' }}.</span>
            @if (! empty($releaseGate['approval_stale']))
                <span>Approval is stale. Re-approve after current project data is reviewed.</span>
            @endif
        </div>
    @endif

    @if ($releaseIsEarly)
        <details class="nx-disclosure nx-release-preliminary">
            <summary>
                <span>View preliminary release checks</span>
                <small>{{ count($releaseIssues) }} recorded</small>
            </summary>
            <div class="nx-release-issue-list">
                @forelse (array_slice($releaseIssues, 0, 5) as $issue)
                    <article class="nx-release-issue is-{{ $issue['severity'] ?? 'warning' }}">
                        <strong>{{ $issue['title'] }}</strong>
                        <span>{{ $issue['detail'] }}</span>
                    </article>
                @empty
                    <article class="nx-release-issue">
                        <strong>No preliminary issue recorded.</strong>
                        <span>Release checks will activate as the project progresses.</span>
                    </article>
                @endforelse
            </div>
        </details>
    @else
        <div class="nx-release-issue-list">
            @forelse (array_slice($releaseIssues, 0, 6) as $issue)
                <article class="nx-release-issue is-{{ $issue['severity'] ?? 'warning' }}">
                    <strong>{{ $issue['title'] }}</strong>
                    <span>{{ $issue['detail'] }}</span>
                </article>
            @empty
                <article class="nx-release-issue is-ready">
                    <strong>All gate checks passed.</strong>
                    <span>{{ $releaseReady ? 'Final Client Proposal PDF is available.' : 'Explicit client release approval is the remaining step.' }}</span>
                </article>
            @endforelse
        </div>
    @endif

    @if ($canApproveClientRelease)
        <div class="nx-release-controls">
            @if (! $releaseReady && ($releaseGate['approval_allowed'] ?? false))
                <form method="POST" action="{{ route('companies.projects.report.client-release.approve', [$company, $project]) }}" class="nx-release-form">
                    @csrf
                    <label class="ui-label" for="release-approval-notes">Approval notes</label>
                    <textarea id="release-approval-notes" class="ui-textarea" name="notes" placeholder="Optional release note for the audit trail"></textarea>
                    <button class="ui-button ui-button-primary" type="submit" data-confirm="Approve this project for client release?">Approve client release</button>
                </form>
            @elseif (! $releaseReady && ! ($releaseGate['approval_allowed'] ?? false))
                <div class="nx-release-lock-note">Approval remains locked until all engineering and commercial gate issues are resolved.</div>
            @endif

            @if (! empty($releaseGate['approval']))
                <form method="POST" action="{{ route('companies.projects.report.client-release.revoke', [$company, $project]) }}" class="nx-release-form nx-release-form-secondary">
                    @csrf
                    @method('DELETE')
                    <label class="ui-label" for="release-revoke-reason">Revoke reason</label>
                    <textarea id="release-revoke-reason" class="ui-textarea" name="revoke_reason" required placeholder="Required reason, for example: pricing changed after client discussion."></textarea>
                    <button class="ui-button ui-button-secondary" type="submit" data-confirm="Revoke client release approval?">Revoke approval</button>
                </form>
            @endif
        </div>
    @endif

    @if ($project->releaseApprovals->isNotEmpty())
        <details class="nx-disclosure">
            <summary>
                <span>Release approval history</span>
                <small>{{ $project->releaseApprovals->count() }} record{{ $project->releaseApprovals->count() === 1 ? '' : 's' }}</small>
            </summary>
            <div class="nx-release-history">
                @foreach ($project->releaseApprovals->take(5) as $approvalRecord)
                    <article class="nx-release-history-item is-{{ $approvalRecord->status }}">
                        <strong>{{ $approvalRecord->status_label }} · {{ $approvalRecord->approved_at?->format('d M Y, H:i') ?? 'No approval time' }}</strong>
                        <span>
                            Approved by {{ $approvalRecord->approver?->name ?? 'System' }}
                            @if ($approvalRecord->revoked_at)
                                · revoked {{ $approvalRecord->revoked_at->format('d M Y, H:i') }}
                            @endif
                        </span>
                        @if ($approvalRecord->notes || $approvalRecord->revoke_reason)
                            <span>{{ $approvalRecord->notes ?: $approvalRecord->revoke_reason }}</span>
                        @endif
                    </article>
                @endforeach
            </div>
        </details>
    @endif
</section>
