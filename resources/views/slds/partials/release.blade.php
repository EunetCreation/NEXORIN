<section class="nxsld-detail-panel" data-sld-review-panel="release" aria-hidden="true" hidden>
    <header class="nxsld-panel-heading">
        <div><span class="nxsld-eyebrow">Controlled workflow</span><h2>Release and drawing disposition</h2><p>Advance the revision through independent checking, approval, and formal issue without exposing governance controls on the main drawing canvas.</p></div>
        <span class="nxsld-release-pill is-{{ $releaseStatus }}">{{ strtoupper($releaseStatus) }}</span>
    </header>

    <div class="nxsld-release-readiness is-{{ $releaseStatus }}">
        <div><small>Next controlled action</small><strong>{{ data_get($releaseAssessment, 'next_target_label', 'No further transition') }}</strong><span>{{ (int) data_get($releaseSummary, 'next_blockers', 0) }} blocker · {{ (int) data_get($releaseSummary, 'next_warnings', 0) }} advisory</span></div>
        <div><span><b>{{ (int) data_get($releaseSummary, 'pass', 0) }}</b>Pass</span><span><b>{{ (int) data_get($releaseSummary, 'warning', 0) }}</b>Advisory</span><span><b>{{ (int) data_get($releaseSummary, 'blocked', 0) }}</b>Blocked</span></div>
    </div>

    <div class="nxsld-release-grid">
        <article class="nxsld-detail-card is-wide">
            <header><strong>Release gates</strong><small>{{ count($releaseGates) }} controlled check(s)</small></header>
            <div class="nxsld-gate-list">
                @forelse ($releaseGates as $gate)
                    <div class="is-{{ data_get($gate, 'status', 'warning') }}">
                        <i aria-hidden="true"></i>
                        <span><strong>{{ data_get($gate, 'label', 'Release gate') }}</strong><small>{{ data_get($gate, 'detail', '') }}</small></span>
                        <em>{{ strtoupper((string) data_get($gate, 'status', 'warning')) }}</em>
                    </div>
                @empty
                    <div class="nxsld-empty-inline"><strong>Release assessment pending.</strong><span>Run engineering checks to populate the release gates.</span></div>
                @endforelse
            </div>
        </article>

        <article class="nxsld-detail-card">
            <header><strong>Controlled export package</strong><small>Exact output identity</small></header>
            <div class="nxsld-file-list">
                @foreach (['pdf' => 'PDF drawing set', 'svg' => 'Editable SVG', 'package' => 'Audit package ZIP'] as $format => $label)
                    <div><span>{{ strtoupper($format) }}</span><p class="nxsld-mono" title="{{ data_get($exportPreview, 'filenames.'.$format) }}">{{ data_get($exportPreview, 'filenames.'.$format, '—') }}</p><small>{{ $label }}</small></div>
                @endforeach
            </div>
            @if ($latestExport !== [])
                <div class="nxsld-export-proof"><span>Latest SHA-256</span><code title="{{ data_get($latestExport, 'sha256') }}">{{ str(data_get($latestExport, 'sha256', '—'))->limit(34) }}</code><small>{{ strtoupper((string) data_get($latestExport, 'format', '—')) }} · {{ data_get($latestExport, 'exported_at') ? \Illuminate\Support\Carbon::parse(data_get($latestExport, 'exported_at'))->format('d M Y, H:i') : '—' }}</small></div>
            @endif
            <div class="nxsld-export-actions">
                @if ($controlledExportBlocked)
                    <button type="button" data-sld-export-blocked>PDF export blocked</button>
                @else
                    <a href="{{ route('companies.projects.sld.pdf', [$company, $project]) }}">Download PDF</a>
                    <a href="{{ route('companies.projects.sld.svg', [$company, $project]) }}">Download SVG</a>
                    <a class="is-primary" href="{{ route('companies.projects.sld.package', [$company, $project]) }}">Download controlled package</a>
                @endif
            </div>
        </article>

        <article class="nxsld-detail-card">
            <header><strong>Current authority</strong><small>Role separation</small></header>
            <dl class="nxsld-definition-list">
                <div><dt>Revision owner</dt><dd>{{ $revision->generator?->name ?? 'System' }}</dd></div>
                <div><dt>Checker</dt><dd>{{ data_get($documentControl, 'people.checked_by') ?: 'Not assigned' }}</dd></div>
                <div><dt>Approver</dt><dd>{{ data_get($documentControl, 'people.approved_by') ?: 'Not assigned' }}</dd></div>
                <div><dt>Issuer</dt><dd>{{ $revision->issuer?->name ?? 'Not issued' }}</dd></div>
                <div><dt>Current state</dt><dd>{{ $revision->status_label }} · {{ $issueLabel }}</dd></div>
            </dl>
        </article>

        <article class="nxsld-detail-card is-wide">
            <header><strong>Drawing disposition</strong><small>Permission-controlled state transition</small></header>
            <div class="nxsld-disposition-actions">
                @if ($canSubmit && $revision->status === 'draft')
                    <form method="POST" action="{{ route('companies.projects.sld.submit', [$company, $project]) }}"
                          data-confirm="Submit revision R{{ $revision->revision }} for engineer review?"
                          data-confirm-tone="decision" data-confirm-title="Submit SLD R{{ $revision->revision }}?"
                          data-confirm-kicker="Drawing review handoff" data-confirm-label="Submit for review"
                          data-confirm-cancel-label="Keep as draft" data-confirm-control="Document metadata and source fingerprint are locked for review"
                          data-confirm-result="The issue status becomes IFR - Internal Review">
                        @csrf @method('PATCH')
                        <label><span>Handoff note <small>optional</small></span><textarea name="review_notes" rows="3" placeholder="Design basis, open coordination item, or reviewer context"></textarea></label>
                        <label class="nxsld-check-row"><input type="checkbox" name="register_confirm" value="1" required><span>I verified the title-block preview and controlled drawing register.</span></label>
                        <button class="nxsld-action-button is-primary" type="submit">Submit for review</button>
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
                        <label><span>Checker record <small>required</small></span><textarea name="review_notes" rows="3" required minlength="10" placeholder="State the independent check basis and controlled references"></textarea></label>
                        <label class="nxsld-check-row"><input type="checkbox" name="independent_check_confirm" value="1" required><span>I am independent from the drawing preparer and accept checker responsibility.</span></label>
                        <button class="nxsld-action-button is-primary" type="submit" @disabled($isStale || ($warningCounts['critical'] ?? 0) > 0)>Record checked</button>
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
                        <label><span>Approval basis <small>required</small></span><textarea name="review_notes" rows="3" required minlength="10" placeholder="State the approval authority, basis, and controlled reference"></textarea></label>
                        <label class="nxsld-check-row"><input type="checkbox" name="independent_approval_confirm" value="1" required><span>I did not prepare or check this revision and accept approval responsibility.</span></label>
                        <button class="nxsld-action-button is-primary" type="submit" @disabled($isStale || ($warningCounts['critical'] ?? 0) > 0)>Approve and lock</button>
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
                        <label><span>Revision requirement <small>required</small></span><textarea name="review_notes" rows="3" required placeholder="State exactly what must be corrected"></textarea></label>
                        <button class="nxsld-action-button is-danger" type="submit">Return to draft</button>
                    </form>
                @endif

                @if ($isApproved)
                    <div class="nxsld-approved-state"><span>✓</span><div><strong>{{ $revision->isIssued() ? $issueLabel : 'Approved and immutable' }}</strong><small>{{ ($revision->issued_at ?? $revision->approved_at)?->format('d M Y, H:i') }} · {{ $revision->issuer?->name ?? $revision->approver?->name ?? 'Engineer' }}</small></div></div>
                    @if ($canIssue && ! $revision->isIssued())
                        <form method="POST" action="{{ route('companies.projects.sld.issue', [$company, $project]) }}"
                              data-confirm="Create a formal drawing issue from approved R{{ $revision->revision }}?"
                              data-confirm-tone="decision" data-confirm-title="Issue controlled drawing?"
                              data-confirm-kicker="Formal document issue" data-confirm-label="Issue drawing"
                              data-confirm-cancel-label="Keep approved only" data-confirm-control="The issue event, actor, note, drawing register, and export lineage are retained"
                              data-confirm-result="The approved revision receives a formal IFT, IFC, or AB issue status">
                            @csrf @method('PATCH')
                            <label><span>Issue status</span><select name="issue_status" required><option value="issued_for_tender" @disabled(! data_get($allowedTargets, 'issued_for_tender.ready', false))>Issued for Tender</option><option value="issued_for_construction" @disabled(! data_get($allowedTargets, 'issued_for_construction.ready', false))>Issued for Construction{{ data_get($allowedTargets, 'issued_for_construction.ready', false) ? '' : ' · blocked by release gates' }}</option><option value="as_built" @disabled(! data_get($allowedTargets, 'as_built.ready', false))>As-Built{{ data_get($allowedTargets, 'as_built.ready', false) ? '' : ' · blocked by release gates' }}</option></select></label>
                            <label><span>Issue authority note <small>required</small></span><textarea name="issue_note" rows="3" required minlength="10" placeholder="Utility, tender, construction, commissioning, or as-built release reference"></textarea></label>
                            <label class="nxsld-check-row"><input type="checkbox" name="issue_confirm" value="1" required><span>I verified the issue preview, release gates, disposition, and exact production filenames.</span></label>
                            <button class="nxsld-action-button is-primary" type="submit">Create formal issue</button>
                        </form>
                    @endif
                @endif

                @if (! $canSubmit && ! $canCheck && ! $canApprove && ! $isApproved)
                    <div class="nxsld-empty-inline"><strong>No disposition action is available for your role.</strong><span>Review the drawing and contact the assigned engineering authority.</span></div>
                @endif
            </div>
        </article>
    </div>
</section>
