<section class="nxsld-detail-panel" data-sld-review-panel="revisions" aria-hidden="true" hidden>
    <header class="nxsld-panel-heading">
        <div><span class="nxsld-eyebrow">Immutable lineage</span><h2>Revisions and controlled packages</h2><p>Compare engineering changes, inspect the revision register, and verify archived drawing packages.</p></div>
        <span class="nxsld-panel-count">{{ $revisionHistory->count() }}</span>
    </header>

    <div class="nxsld-revision-grid">
        <article class="nxsld-detail-card is-wide">
            <header><strong>Revision comparison</strong><small>R{{ data_get($revisionComparison, 'previous_revision', '—') }} → R{{ $revision->revision }}</small></header>
            @if (data_get($revisionComparison, 'available', false))
                <div class="nxsld-delta-grid">
                    <span><b>{{ count(data_get($revisionComparison, 'graph.nodes_added', [])) }}</b><small>Nodes added</small></span>
                    <span><b>{{ count(data_get($revisionComparison, 'graph.nodes_removed', [])) }}</b><small>Nodes removed</small></span>
                    <span><b>{{ count(data_get($revisionComparison, 'graph.connections_added', [])) + count(data_get($revisionComparison, 'graph.connections_removed', [])) }}</b><small>Wire changes</small></span>
                    <span class="{{ data_get($revisionComparison, 'source_changed') ? 'is-changed' : '' }}"><b>{{ data_get($revisionComparison, 'source_changed') ? 'YES' : 'NO' }}</b><small>Source changed</small></span>
                </div>
                <div class="nxsld-change-table">
                    @forelse (data_get($revisionComparison, 'changes', []) as $change)
                        <div><strong>{{ data_get($change, 'label') }}</strong><span>{{ data_get($change, 'before', '—') }}</span><i>→</i><span>{{ data_get($change, 'after', '—') }}</span></div>
                    @empty
                        <p>No controlled metadata field changed between these revisions.</p>
                    @endforelse
                </div>
            @else
                <div class="nxsld-empty-inline"><strong>First controlled revision.</strong><span>{{ data_get($revisionComparison, 'message', 'No previous revision is available.') }}</span></div>
            @endif
        </article>

        <article class="nxsld-detail-card">
            <header><strong>Revision register</strong><small>{{ $revisionHistory->count() }} record(s)</small></header>
            <div class="nxsld-revision-list">
                @foreach ($revisionHistory as $record)
                    <div class="{{ $record->id === $revision->id ? 'is-current' : '' }} {{ $record->status === 'superseded' ? 'is-superseded' : '' }}">
                        <span><b>R{{ $record->revision }}</b><small>{{ $record->issue_status_label }} · {{ str($record->status)->replace('_', ' ')->title() }}</small></span>
                        <em>{{ $record->isLocked() ? 'LOCKED' : ($record->status === 'superseded' ? 'SUPERSEDED' : 'ACTIVE') }}</em>
                        <p>{{ $record->supersededBy ? 'Replaced by R'.$record->supersededBy->revision : ($record->locked_at ? 'Locked '.$record->locked_at->format('d M Y, H:i') : 'Editable only while draft') }}</p>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="nxsld-detail-card">
            <header><strong>Package archive</strong><small>{{ $packageHistory->count() }} export(s)</small></header>
            <div class="nxsld-package-list">
                @forelse ($packageHistory as $packageEvent)
                    @php($verificationStatus = (string) data_get($packageEvent, 'verification.status', 'not_verified'))
                    <div class="is-{{ $verificationStatus }}">
                        <span><b>R{{ data_get($packageEvent, 'revision') }} · {{ data_get($packageEvent, 'issue_code', 'DRAFT') }}</b><small>{{ data_get($packageEvent, 'exported_at') ? \Illuminate\Support\Carbon::parse(data_get($packageEvent, 'exported_at'))->format('d M Y, H:i') : '—' }}</small></span>
                        <code title="{{ data_get($packageEvent, 'sha256') }}">{{ str(data_get($packageEvent, 'sha256', '—'))->limit(24) }}</code>
                        <em>{{ strtoupper(str_replace('_', ' ', $verificationStatus)) }}</em>
                        <div>
                            @if (filled(data_get($packageEvent, 'archive.path')))
                                <a href="{{ route('companies.projects.sld.packages.download', [$company, $project, data_get($packageEvent, 'revision'), data_get($packageEvent, 'event_id')]) }}">Re-download</a>
                                @if ($canApprove)
                                    <form method="POST" action="{{ route('companies.projects.sld.packages.verify', [$company, $project, data_get($packageEvent, 'revision'), data_get($packageEvent, 'event_id')]) }}">@csrf<button type="submit">Verify SHA-256</button></form>
                                @endif
                            @else
                                <span>Legacy export, archive unavailable</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="nxsld-empty-inline"><strong>No archived package yet.</strong><span>Download a controlled package to create a private, verifiable export record.</span></div>
                @endforelse
            </div>
        </article>

        <article class="nxsld-detail-card is-wide">
            <header><strong>Audit trail</strong><small>Latest {{ $auditTrail->count() }} event(s)</small></header>
            <div class="nxsld-audit-list">
                @forelse ($auditTrail as $event)
                    <div><i></i><span><b>{{ str(data_get($event, 'event', 'event'))->replace('_', ' ')->title() }}</b><small>{{ data_get($event, 'user_name', 'System') }} · {{ data_get($event, 'at') ? \Illuminate\Support\Carbon::parse(data_get($event, 'at'))->format('d M Y, H:i') : '—' }}</small></span><em>{{ count((array) data_get($event, 'changes', [])) }} Δ</em></div>
                @empty
                    <div class="nxsld-empty-inline"><strong>Audit history pending.</strong><span>Metadata, workflow, issue, package, verification, and supersession events appear here.</span></div>
                @endforelse
            </div>
        </article>
    </div>
</section>
