<section class="page executive-page">
    @include('pdf.premium.components.page-header', ['title' => 'Executive Dashboard'])

    <div class="page-content">
        @include('pdf.premium.components.section-kicker', [
            'eyebrow' => '01 · Executive View',
            'title' => 'Project cockpit for engineering and commercial decisions',
            'subtitle' => 'A compact page for decision makers. It condenses recommendation, capacity, energy, financial value, warning health, and document readiness.',
        ])

        <div class="executive-layout executive-layout-refined">
            <div class="hero-card dark-card hero-card-refined">
                <div class="hero-card-top">
                    <div>
                        <span>Recommended System</span>
                        <strong>{{ $data['headline']['system_type'] }}</strong>
                    </div>
                    <div class="status-pill status-{{ strtolower(str_replace(' ', '-', $data['headline']['status_label'])) }}">{{ $data['headline']['status_label'] }}</div>
                </div>

                <p class="hero-note">{{ $data['headline']['executive_note_compact'] ?? $data['headline']['executive_note'] }}</p>

                <div class="hero-evidence-grid">
                    <div>
                        <span>PV Capacity</span>
                        <strong>{{ $data['technical']['installed_pv_kw'] }}</strong>
                    </div>
                    <div>
                        <span>Annual Energy</span>
                        <strong>{{ $data['kpis'][2]['value'] }}</strong>
                    </div>
                    <div>
                        <span>Open Notices</span>
                        <strong>{{ $data['warnings']['total'] }}</strong>
                    </div>
                </div>
            </div>

            <div class="micro-panel">
                <span>Payback</span>
                <strong>{{ $data['summary_metrics']['payback'] }}</strong>
                <small>Simple project recovery period</small>
            </div>
            <div class="micro-panel">
                <span>ROI</span>
                <strong>{{ $data['summary_metrics']['roi'] }}</strong>
                <small>Lifetime return estimate</small>
            </div>
            <div class="micro-panel">
                <span>NPV</span>
                <strong>{{ $data['summary_metrics']['npv'] }}</strong>
                <small>Discounted project value</small>
            </div>
            <div class="micro-panel">
                <span>LCOE</span>
                <strong>{{ $data['summary_metrics']['lcoe'] }}</strong>
                <small>Levelized cost of energy</small>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="chart-card wide-card">
                <div class="card-head">
                    <div><span>Energy Forecast</span><strong>Monthly PV production estimate</strong></div>
                    <b>kWh</b>
                </div>
                <div class="chart-meta-grid">
                    <div><span>Annual Total</span><strong>{{ $data['charts']['monthly_energy_stats']['annual_total'] }}</strong></div>
                    <div><span>Peak Month</span><strong>{{ $data['charts']['monthly_energy_stats']['peak_month'] }}</strong></div>
                    <div><span>Lowest Month</span><strong>{{ $data['charts']['monthly_energy_stats']['low_month'] }}</strong></div>
                </div>
                <div class="bar-chart monthly-chart">
                    @foreach ($data['charts']['monthly_energy'] as $row)
                        <div class="bar-col">
                            <div class="bar-value">{{ $row['label'] }}</div>
                            <div class="bar-track"><span style="height: {{ $row['height'] }}%"></span></div>
                            <small>{{ $row['month'] }}</small>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="chart-card">
                <div class="card-head">
                    <div><span>Warning Health</span><strong>Open issue profile</strong></div>
                    <b>{{ $data['warnings']['total'] }}</b>
                </div>
                <div class="severity-grid">
                    @foreach ($data['warnings']['counts'] as $severity => $count)
                        <div class="severity-item severity-{{ $severity }}">
                            <span>{{ ucfirst($severity) }}</span>
                            <strong>{{ $count }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="info-strip">
            <div><span>Location</span><strong>{{ $data['project']['site_address'] }}</strong></div>
            <div><span>Coordinates</span><strong>{{ $data['project']['coordinates'] }}</strong></div>
            <div><span>Customer</span><strong>{{ $data['customer']['name'] }}</strong></div>
        </div>
    </div>
</section>
