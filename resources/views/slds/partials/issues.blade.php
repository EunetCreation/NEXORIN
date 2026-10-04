<section class="nxsld-detail-panel" data-sld-review-panel="issues" aria-hidden="true" hidden>
    <header class="nxsld-panel-heading">
        <div><span class="nxsld-eyebrow">Engineering assurance</span><h2>Issues and review notices</h2><p>Blocking findings are listed first. Each item identifies the impact and the required engineering response.</p></div>
        <span class="nxsld-panel-count">{{ $controllerConflicts->count() + count($warnings) }}</span>
    </header>

    <div class="nxsld-issue-groups">
        @if ($isLocked)
            <article class="nxsld-issue-row is-neutral">
                <span class="nxsld-issue-severity">LOCKED</span>
                <div><strong>Issued revision is read-only</strong><p>R{{ $revision->revision }} cannot be modified. Generate a new revision for drawing, metadata, or source changes.</p></div>
                <button type="button" data-sld-open-tab="revisions">Revision history</button>
            </article>
        @endif

        @foreach ($controllerConflicts as $conflict)
            <article class="nxsld-issue-row is-{{ data_get($conflict, 'severity', 'warning') }}">
                <span class="nxsld-issue-severity">{{ strtoupper((string) data_get($conflict, 'severity', 'warning')) }}</span>
                <div>
                    <strong>{{ data_get($conflict, 'code', 'APP67K') }} · {{ data_get($conflict, 'title', 'Engineering controller finding') }}</strong>
                    <p>{{ data_get($conflict, 'message', data_get($conflict, 'detail', '')) }}</p>
                    <small>{{ data_get($conflict, 'recommendation', '') }}</small>
                </div>
                @if (data_get($conflict, 'severity') === 'error' && $revision->status === 'draft' && ! $isLocked)
                    <button type="button" data-sld-sync>Synchronize</button>
                @endif
            </article>
        @endforeach

        @foreach ($warnings as $warning)
            <article class="nxsld-issue-row is-{{ $warning['severity'] ?? 'notice' }}">
                <span class="nxsld-issue-severity">{{ strtoupper($warning['severity'] ?? 'NOTICE') }}</span>
                <div><strong>{{ $warning['title'] ?? 'Review notice' }}</strong><p>{{ $warning['message'] ?? '' }}</p><small>{{ $warning['recommendation'] ?? '' }}</small></div>
            </article>
        @endforeach

        @if ($controllerConflicts->isEmpty() && $warnings === [])
            <div class="nxsld-clean-state"><span>✓</span><div><strong>No open SLD-specific issues</strong><p>The topology is internally consistent with its current calculation snapshot. Field and manufacturer verification still apply.</p></div></div>
        @endif
    </div>
</section>
