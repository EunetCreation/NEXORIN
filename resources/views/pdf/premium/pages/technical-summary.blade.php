<section class="page technical-page">
    @include('pdf.premium.components.page-header', ['title' => 'Technical System Summary'])

    <div class="page-content">
        @include('pdf.premium.components.section-kicker', [
            'eyebrow' => '03 · Engineering Layer',
            'title' => 'Technical configuration and site readiness',
            'subtitle' => 'This page consolidates PV sizing, inverter, battery, string planning, site input, solar data, and roof readiness. It is designed for engineer review before commercial release.',
        ])

        <div class="technical-grid">
            <div class="technical-primary dark-card">
                <span>Calculation Status</span>
                <strong>{{ $data['technical']['status'] }}</strong>
                <p>Calculated at {{ $data['technical']['calculated_at'] }}. Verified at: {{ $data['technical']['verified_at'] }}.</p>
            </div>
            <div class="tech-metric"><span>Array PV</span><strong>{{ $data['technical']['installed_pv_kw'] }}</strong><small>{{ $data['technical']['module_count'] }}</small></div>
            <div class="tech-metric"><span>Inverter</span><strong>{{ $data['technical']['inverter_power'] }}</strong><small>{{ $data['technical']['dc_ac_ratio'] }} DC/AC</small></div>
            <div class="tech-metric"><span>Battery</span><strong>{{ $data['technical']['battery_capacity'] }}</strong><small>{{ $data['technical']['battery_power'] }}</small></div>
        </div>

        <div class="status-matrix">
            @foreach ($data['status_matrix'] as $status)
                <div class="status-cell status-cell-{{ $status['tone'] }}">
                    <span>{{ $status['label'] }}</span>
                    <strong>{{ $status['status'] }}</strong>
                    <small>{{ $status['note'] }}</small>
                </div>
            @endforeach
        </div>

        <div class="two-col-section technical-columns">
            <div class="table-card">
                <div class="card-head compact"><div><span>Design Parameters</span><strong>Core electrical summary</strong></div></div>
                <table class="kv-table">
                    <tbody>
                        <tr><th>Module Power</th><td>{{ $data['technical']['module_power'] }}</td></tr>
                        <tr><th>String Design</th><td>{{ $data['technical']['string_design'] }}</td></tr>
                        <tr><th>DC Voltage Drop</th><td>{{ $data['technical']['voltage_drop_dc'] }}</td></tr>
                        <tr><th>AC Voltage Drop</th><td>{{ $data['technical']['voltage_drop_ac'] }}</td></tr>
                        <tr><th>Load Source</th><td>{{ $data['load']['source'] }}</td></tr>
                        <tr><th>Supply Phase</th><td>{{ $data['load']['phase'] }}</td></tr>
                        <tr><th>Peak Demand</th><td>{{ $data['load']['peak_demand'] }}</td></tr>
                        <tr><th>Critical Energy</th><td>{{ $data['load']['critical_energy'] }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <div class="card-head compact"><div><span>Site and Solar</span><strong>Input quality and roof condition</strong></div></div>
                <table class="kv-table">
                    <tbody>
                        <tr><th>Solar Source</th><td>{{ $data['site']['solar_source'] }}</td></tr>
                        <tr><th>Solar Quality</th><td>{{ $data['site']['solar_quality'] }}</td></tr>
                        <tr><th>Average PSH</th><td>{{ $data['site']['average_psh'] }}</td></tr>
                        <tr><th>Annual Irradiation</th><td>{{ $data['site']['annual_irradiation'] }}</td></tr>
                        <tr><th>Usable Roof Area</th><td>{{ $data['site']['usable_area'] }}</td></tr>
                        <tr><th>Weighted Shading</th><td>{{ $data['site']['weighted_shading'] }}</td></tr>
                        <tr><th>Roof Readiness</th><td>{{ $data['site']['readiness'] }}</td></tr>
                        <tr><th>Structural Review</th><td>{{ $data['site']['structural_review'] }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="warning-panel">
            <div class="card-head compact"><div><span>Open Review Matrix</span><strong>Actionable notices</strong></div><b>{{ $data['warnings']['total'] }} total</b></div>
            @if (count($data['warnings']['items']) > 0)
                <div class="warning-list">
                    @foreach ($data['warnings']['items'] as $warning)
                        <div class="warning-item warning-{{ $warning['severity'] ?? 'info' }}">
                            <span>{{ strtoupper($warning['severity'] ?? 'info') }}</span>
                            <strong>{{ $warning['title'] ?? 'Review notice' }}</strong>
                            <em>{{ $warning['source_label'] ?? 'Report' }}</em>
                            <p>{{ $warning['message'] ?? 'No message recorded.' }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-panel compact-empty">No warning is open in the current engineering dataset.</div>
            @endif
        </div>
    </div>
</section>
