<section class="nxsld-detail-panel is-active" data-sld-review-panel="overview" aria-hidden="false">
    <div class="nxsld-overview-grid">
        <article class="nxsld-info-card">
            <header><span>System Overview</span><b>PV</b></header>
            <dl>
                <div><dt>PV Capacity</dt><dd>{{ $formatMetric(data_get($summary, 'installed_pv_kw')) }} kWp</dd></div>
                <div><dt>AC Capacity</dt><dd>{{ $formatMetric(data_get($summary, 'inverter_ac_kw')) }} kW</dd></div>
                <div><dt>DC/AC Ratio</dt><dd>{{ $formatMetric(data_get($summary, 'dc_ac_ratio'), 3) }}</dd></div>
                <div><dt>Grid Voltage</dt><dd>{{ $formatMetric(data_get($summary, 'grid_voltage_v'), 0) }} V</dd></div>
                <div><dt>Frequency</dt><dd>{{ $formatMetric(data_get($summary, 'grid_frequency_hz', 50), 0) }} Hz</dd></div>
            </dl>
        </article>

        <article class="nxsld-info-card">
            <header><span>Equipment</span><b>EQ</b></header>
            <dl>
                <div><dt>PV Modules</dt><dd>{{ $moduleCount ?: '—' }}</dd></div>
                <div><dt>PV Strings</dt><dd>{{ $stringCount ?: '—' }}</dd></div>
                <div><dt>Inverters</dt><dd>{{ $inverterCount ?: '—' }}</dd></div>
                <div><dt>AC Combiner</dt><dd>{{ collect($graph['nodes'] ?? [])->where('kind', 'ac_combiner')->count() ?: '—' }}</dd></div>
                <div><dt>Transformers</dt><dd>{{ collect($graph['nodes'] ?? [])->filter(fn ($node) => str_contains((string) data_get($node, 'kind'), 'transformer'))->count() ?: '—' }}</dd></div>
            </dl>
        </article>

        <article class="nxsld-info-card">
            <header><span>Drawing Info</span><b>DR</b></header>
            <dl>
                <div><dt>Sheets</dt><dd>{{ count($svgs) }}</dd></div>
                <div><dt>Composition</dt><dd>{{ $paperModeLabel }}</dd></div>
                <div><dt>Paper Size</dt><dd>A3 Landscape</dd></div>
                <div><dt>Readability</dt><dd>{{ strtoupper((string) data_get($paperSummary, 'readability', 'PASS')) }}</dd></div>
                <div><dt>Generated</dt><dd>{{ $revision->generated_at?->format('d M Y') ?? '—' }}</dd></div>
            </dl>
        </article>

        <article class="nxsld-info-card">
            <header><span>Status</span><b>ST</b></header>
            <dl>
                <div><dt>Drawing Status</dt><dd>{{ $revision->status_label }}</dd></div>
                <div><dt>Issue Purpose</dt><dd>{{ data_get($exportPreview, 'disposition', 'Not for Construction') }}</dd></div>
                <div><dt>Source</dt><dd>{{ $isStale ? 'Synchronization required' : 'Current' }}</dd></div>
                <div><dt>Checker</dt><dd>{{ data_get($documentControl, 'people.checked_by') ?: 'Not assigned' }}</dd></div>
                <div><dt>Approver</dt><dd>{{ data_get($documentControl, 'people.approved_by') ?: 'Not assigned' }}</dd></div>
            </dl>
        </article>

        <article class="nxsld-info-card is-actions">
            <header><span>Quick Actions</span><b>⚡</b></header>
            <div class="nxsld-quick-actions">
                <a class="is-primary" href="{{ route('companies.projects.sld.edit', [$company, $project]) }}">Edit Drawing</a>
                <button type="button" data-sld-open-tab="issues">Run Checks</button>
                @if ($controlledExportBlocked)
                    <button type="button" data-sld-export-blocked>Export Current Sheet (PDF)</button>
                @else
                    <a href="{{ route('companies.projects.sld.pdf', [$company, $project]) }}">Export Current Sheet (PDF)</a>
                @endif
                <button type="button" data-sld-open-tab="release">More controlled actions</button>
            </div>
        </article>
    </div>
</section>
