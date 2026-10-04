<section class="nxsld-detail-panel" data-sld-review-panel="traceability" aria-hidden="true" hidden>
    <header class="nxsld-panel-heading">
        <div><span class="nxsld-eyebrow">Source lineage</span><h2>Engineering traceability</h2><p>Review the controlled metadata, calculation source, catalog linkage, datasheets, and drawing register behind this revision.</p></div>
        <span class="nxsld-panel-tag">SHA-256</span>
    </header>

    <div class="nxsld-trace-grid">
        <article class="nxsld-detail-card is-wide">
            <header><strong>Controlled metadata</strong><small>Title-block source</small></header>
            @if ($revision->status === 'draft' && ! $isLocked && $canSubmit)
                <form class="nxsld-metadata-form" method="POST" action="{{ route('companies.projects.sld.document-control.update', [$company, $project]) }}">
                    @csrf @method('PATCH')
                    <label><span>Drawing number prefix</span><input name="drawing_number_prefix" required maxlength="80" value="{{ old('drawing_number_prefix', data_get($documentControl, 'drawing.number_prefix', $revision->drawing_number_prefix)) }}"></label>
                    <label><span>Package title</span><input name="drawing_title" required maxlength="180" value="{{ old('drawing_title', data_get($documentControl, 'drawing.title', $revision->drawing_title)) }}"></label>
                    <label><span>Client</span><input name="client_name" required maxlength="180" value="{{ old('client_name', data_get($documentControl, 'project.client', $revision->client_name)) }}"></label>
                    <label><span>Site location</span><input name="site_location" required maxlength="240" value="{{ old('site_location', data_get($documentControl, 'project.site_location', $revision->site_location)) }}"></label>
                    <button class="nxsld-action-button is-primary" type="submit">Update metadata</button>
                </form>
            @else
                <dl class="nxsld-definition-list">
                    <div><dt>Drawing prefix</dt><dd class="nxsld-mono">{{ data_get($documentControl, 'drawing.number_prefix', '—') }}</dd></div>
                    <div><dt>Drawing title</dt><dd>{{ data_get($documentControl, 'drawing.title', '—') }}</dd></div>
                    <div><dt>Client</dt><dd>{{ data_get($documentControl, 'project.client', '—') }}</dd></div>
                    <div><dt>Site</dt><dd>{{ data_get($documentControl, 'project.site_location', '—') }}</dd></div>
                    <div><dt>Organization</dt><dd>{{ data_get($documentControl, 'organization.name', '—') }}</dd></div>
                </dl>
            @endif
        </article>

        <article class="nxsld-detail-card">
            <header><strong>Source snapshot</strong><small>Engineering authority</small></header>
            <dl class="nxsld-definition-list">
                <div><dt>Calculation</dt><dd>{{ str(data_get($source, 'technical_calculation.result_status', 'Not available'))->headline() }}</dd></div>
                <div><dt>Calculated</dt><dd>{{ data_get($source, 'technical_calculation.calculated_at') ? \Illuminate\Support\Carbon::parse(data_get($source, 'technical_calculation.calculated_at'))->format('d M Y, H:i') : '—' }}</dd></div>
                <div><dt>Recommendation</dt><dd>{{ str(data_get($source, 'recommendation.recommended_system', 'Not available'))->headline() }}</dd></div>
                <div><dt>BOM linkage</dt><dd>{{ data_get($source, 'bom.id') ? 'Snapshot #'.data_get($source, 'bom.id') : 'Not linked' }}</dd></div>
                <div><dt>Fingerprint</dt><dd class="nxsld-mono" title="{{ data_get($source, 'fingerprint') }}">{{ str(data_get($source, 'fingerprint', '—'))->limit(30) }}</dd></div>
            </dl>
        </article>

        <article class="nxsld-detail-card">
            <header><strong>Issue preview</strong><small>Current title block</small></header>
            <dl class="nxsld-definition-list">
                <div><dt>Project</dt><dd>{{ data_get($documentControl, 'project.name', $project->name) }}</dd></div>
                <div><dt>Package</dt><dd>{{ data_get($documentControl, 'drawing.title', '—') }}</dd></div>
                <div><dt>Issue</dt><dd>{{ data_get($exportPreview, 'issue_code', 'DRAFT') }} · {{ data_get($exportPreview, 'issue_label', 'Draft') }}</dd></div>
                <div><dt>Disposition</dt><dd>{{ data_get($exportPreview, 'disposition', 'NOT FOR CONSTRUCTION') }}</dd></div>
                <div><dt>Authorities</dt><dd>{{ data_get($documentControl, 'people.checked_by') ?: 'Checker pending' }} / {{ data_get($documentControl, 'people.approved_by') ?: 'Approver pending' }}</dd></div>
            </dl>
        </article>

        <article class="nxsld-detail-card is-wide">
            <header><strong>Material traceability</strong><small>Catalog, BOM, and datasheet linkage</small></header>
            <div class="nxsld-material-grid">
                @forelse ($equipmentRegister as $group)
                    @php($items = is_array(data_get($group, 'items')) ? data_get($group, 'items') : [])
                    <div class="{{ data_get($group, 'status') === 'BOM linked' ? 'is-linked' : 'is-pending' }}">
                        <span><strong>{{ data_get($group, 'label', str(data_get($group, 'group', 'Equipment'))->headline()) }}</strong><small>{{ data_get($group, 'status', 'TBC') }} · {{ count($items) }} item(s)</small></span>
                        <em>{{ collect($items)->filter(fn ($item) => filled(data_get($item, 'datasheet_id')))->count() }} datasheet(s)</em>
                    </div>
                @empty
                    <div class="nxsld-empty-inline"><strong>No equipment register is available.</strong><span>Generate or synchronize BOM/BOQ before controlled issue.</span></div>
                @endforelse
            </div>
        </article>

        <article class="nxsld-detail-card is-wide">
            <header><strong>Drawing register</strong><small>{{ count($drawingRegister) }} controlled sheet(s)</small></header>
            <div class="nxsld-drawing-register">
                @forelse ($drawingRegister as $row)
                    <div><span>{{ str_pad((string) data_get($row, 'sheet', 0), 2, '0', STR_PAD_LEFT) }}</span><b class="nxsld-mono">{{ data_get($row, 'drawing_number', '—') }}</b><small>{{ data_get($row, 'title', 'Drawing sheet') }}</small><em>{{ data_get($row, 'revision', 'R'.$revision->revision) }}</em></div>
                @empty
                    <div class="nxsld-empty-inline"><strong>Drawing register pending.</strong><span>It will be created when document control is initialized.</span></div>
                @endforelse
            </div>
        </article>
    </div>
</section>
