<section class="page cover-page">
    <div class="cover-hero" style="background-image: linear-gradient(90deg, rgba(3, 13, 18, .96) 0%, rgba(3, 13, 18, .82) 44%, rgba(3, 13, 18, .25) 100%), url('{{ $assets['hero'] }}');"></div>
    <div class="cover-grid"></div>
    <div class="cover-orb cover-orb-one"></div>
    <div class="cover-orb cover-orb-two"></div>

    <div class="cover-content">
        <div class="cover-logo-panel">
            <img src="{{ $assets['logo'] }}" alt="NEXORIN Logo">
        </div>

        <div class="cover-label">{{ $data['meta']['document_title'] }}</div>
        <h1>{{ $data['project']['name'] }}</h1>
        <p class="cover-lead">{{ $data['headline']['executive_note'] }}</p>

        <div class="cover-info-grid">
            <div><span>Project Code</span><strong>{{ $data['project']['number'] }}</strong></div>
            <div><span>Customer</span><strong>{{ $data['customer']['name'] }}</strong></div>
            <div><span>Location</span><strong>{{ $data['project']['city'] ?: $data['project']['province'] ?: 'Location not recorded' }}</strong></div>
            <div><span>Release Status</span><strong>{{ $data['meta']['release_status'] ?? $data['meta']['classification'] }}</strong></div>
        </div>

        <div class="cover-kpi-row">
            @foreach ($data['kpis'] as $kpi)
                @include('pdf.premium.components.kpi-card', [
                    'label' => $kpi['label'],
                    'value' => $kpi['value'],
                    'caption' => $kpi['caption'],
                    'tone' => $kpi['tone'],
                ])
            @endforeach
        </div>

        <div class="cover-footer">
            <div>
                <span>Prepared By</span>
                <strong>{{ $data['company']['name'] }}</strong>
            </div>
            <div>
                <span>Generated</span>
                <strong>{{ $data['meta']['generated_at'] }}</strong>
            </div>
            <div>
                <span>Document Revision</span>
                <strong>{{ $data['meta']['revision'] }}</strong>
            </div>
        </div>
    </div>
</section>
