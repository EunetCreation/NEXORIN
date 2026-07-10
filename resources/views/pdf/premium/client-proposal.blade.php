<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['project']['number'] }} Client Proposal</title>
    @if (! empty($inlineCss))
        <style>{!! $inlineCss !!}</style>
    @else
        <link rel="stylesheet" href="{{ $assets['css'] }}">
    @endif
</head>
<body class="nx-report nx-client-proposal {{ $assetMode === 'web' ? 'nx-preview' : 'nx-print' }}">
    @php
        $proposal = $data['client_proposal'];
        $scopeChunks = array_chunk($proposal['scope_rows'] ?? [], 19);
        $draftWatermark = $proposal['watermark'] ?? null;
    @endphp

    <section class="page cover-page client-cover-page">
        <div class="cover-hero" style="background-image: linear-gradient(90deg, rgba(3, 13, 18, .96) 0%, rgba(3, 13, 18, .82) 44%, rgba(3, 13, 18, .25) 100%), url('{{ $assets['hero'] }}');"></div>
        <div class="cover-grid"></div>
        <div class="cover-orb cover-orb-one"></div>
        <div class="cover-orb cover-orb-two"></div>
        @if ($draftWatermark)
            <div class="draft-watermark dark-watermark">{{ $draftWatermark }}</div>
        @endif

        <div class="cover-content">
            <div class="cover-logo-panel">
                <img src="{{ $assets['logo'] }}" alt="NEXORIN Logo">
            </div>

            <div class="cover-label">Client Proposal</div>
            <h1>{{ $proposal['title'] }}</h1>
            <p class="cover-lead">{{ $proposal['proposal_note'] }}</p>

            <div class="cover-info-grid">
                <div><span>Proposal No.</span><strong>{{ $proposal['quote_number'] }}</strong></div>
                <div><span>Prepared For</span><strong>{{ $proposal['prepared_for'] }}</strong></div>
                <div><span>Location</span><strong>{{ $data['project']['city'] ?: $data['project']['province'] ?: 'Location not recorded' }}</strong></div>
                <div><span>Release Status</span><strong>{{ $proposal['release_status'] }}</strong></div>
            </div>

            <div class="cover-kpi-row">
                @foreach ($proposal['system_summary'] as $card)
                    @include('pdf.premium.components.kpi-card', [
                        'label' => $card['label'],
                        'value' => $card['value'],
                        'caption' => $card['caption'],
                        'tone' => $loop->index === 0 ? 'teal' : ($loop->index === 1 ? 'navy' : ($loop->index === 2 ? 'gold' : 'slate')),
                    ])
                @endforeach
            </div>

            <div class="cover-footer">
                <div>
                    <span>Prepared By</span>
                    <strong>{{ $proposal['prepared_by'] }}</strong>
                </div>
                <div>
                    <span>Valid Until</span>
                    <strong>{{ $proposal['valid_until'] }}</strong>
                </div>
                <div>
                    <span>Document Revision</span>
                    <strong>{{ $proposal['revision'] }}</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="page client-summary-page">
        @include('pdf.premium.components.page-header', ['title' => 'Client Proposal Summary'])
        @if ($draftWatermark)
            <div class="draft-watermark light-watermark">{{ $draftWatermark }}</div>
        @endif

        <div class="page-content">
            @include('pdf.premium.components.section-kicker', [
                'eyebrow' => '01 · Proposal Summary',
                'title' => 'Selected system and commercial value',
                'subtitle' => 'This customer-facing summary presents the selected system, key benefits, estimated production, commercial value, and release readiness.',
            ])

            <div class="client-summary-grid">
                <div class="client-main-card dark-card">
                    <span>Proposal Value</span>
                    <strong>{{ $data['commercial']['grand_price'] }}</strong>
                    <p>{{ $proposal['release_note'] }}</p>
                </div>
                <div class="finance-tile"><span>Recommended System</span><strong>{{ $data['headline']['system_type'] }}</strong></div>
                <div class="finance-tile"><span>PV Capacity</span><strong>{{ $data['technical']['installed_pv_kw'] }}</strong></div>
                <div class="finance-tile"><span>Annual Energy</span><strong>{{ $data['charts']['monthly_energy_stats']['annual_total'] }}</strong></div>
                <div class="finance-tile"><span>Simple Payback</span><strong>{{ $data['financial']['payback'] }}</strong></div>
                <div class="finance-tile"><span>First-Year Saving</span><strong>{{ $data['financial']['first_year_saving'] }}</strong></div>
            </div>

            <div class="two-col-section client-two-col">
                <div class="table-card">
                    <div class="card-head compact"><div><span>Project Information</span><strong>Customer and site</strong></div></div>
                    <table class="kv-table">
                        <tbody>
                            <tr><th>Customer</th><td>{{ $data['customer']['name'] }}</td></tr>
                            <tr><th>Project</th><td>{{ $data['project']['name'] }}</td></tr>
                            <tr><th>Site Address</th><td>{{ $data['project']['site_address'] }}</td></tr>
                            <tr><th>Coordinates</th><td>{{ $data['project']['coordinates'] }}</td></tr>
                            <tr><th>Prepared By</th><td>{{ $data['company']['name'] }}</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="table-card">
                    <div class="card-head compact"><div><span>Customer Value</span><strong>Customer-facing metrics</strong></div></div>
                    <table class="kv-table">
                        <tbody>
                            <tr><th>Proposal Value</th><td>{{ $data['commercial']['grand_price'] }}</td></tr>
                            <tr><th>First-Year Saving</th><td>{{ $data['financial']['first_year_saving'] }}</td></tr>
                            <tr><th>Simple Payback</th><td>{{ $data['financial']['payback'] }}</td></tr>
                            <tr><th>Annual Energy</th><td>{{ $data['charts']['monthly_energy_stats']['annual_total'] }}</td></tr>
                            <tr><th>Release Status</th><td>{{ $proposal['release_status'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="why-hybrid-box">
                <strong>{{ $proposal['why_hybrid']['title'] }}</strong>
                <ul>
                    @foreach ($proposal['why_hybrid']['points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    @forelse ($scopeChunks as $chunkIndex => $scopeRows)
        <section class="page client-scope-page">
            @include('pdf.premium.components.page-header', ['title' => 'Client Proposal Scope'])
            @if ($draftWatermark)
                <div class="draft-watermark light-watermark">{{ $draftWatermark }}</div>
            @endif

            <div class="page-content">
                @include('pdf.premium.components.section-kicker', [
                    'eyebrow' => '02 · Included Scope · '.($chunkIndex + 1).'/'.count($scopeChunks),
                    'title' => $chunkIndex === 0 ? 'Included base BOQ scope' : 'Included scope continuation',
                    'subtitle' => 'This page lists the customer-facing scope included in the current proposal value. Optional add-ons and catalog candidates are excluded unless selected in writing.',
                ])

                <table class="premium-table client-scope-table">
                    <thead>
                        <tr>
                            <th>Cost Group</th>
                            <th>Item</th>
                            <th>Scope</th>
                            <th>Qty</th>
                            <th>Price / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($scopeRows as $item)
                            <tr>
                                <td>{{ $item['group'] }}</td>
                                <td><strong>{{ $item['name'] }}</strong><div>{{ $item['category'] }}</div></td>
                                <td><span class="scope-badge">{{ $item['scope'] }}</span></td>
                                <td>{{ $item['quantity'] }}</td>
                                <td>{{ $item['price'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
        </section>
    @empty
        <section class="page client-scope-page">
            @include('pdf.premium.components.page-header', ['title' => 'Client Proposal Scope'])
            <div class="page-content">
                @include('pdf.premium.components.section-kicker', [
                    'eyebrow' => '02 · Included Scope',
                    'title' => 'Scope is not available yet',
                    'subtitle' => 'Generate BOM/BOQ and apply pricing before creating the client proposal PDF.',
                ])
                <div class="empty-panel">No client scope items are available.</div>
            </div>
        </section>
    @endforelse

    <section class="page client-terms-page">
        @include('pdf.premium.components.page-header', ['title' => 'Commercial Terms'])
        @if ($draftWatermark)
            <div class="draft-watermark light-watermark">{{ $draftWatermark }}</div>
        @endif

        <div class="page-content">
            @include('pdf.premium.components.section-kicker', [
                'eyebrow' => '03 · Terms and Release Readiness',
                'title' => 'Commercial terms and exclusions',
                'subtitle' => 'The final release should be issued only after engineering review, pricing review, and quotation approval are complete.',
            ])

            <div class="terms-grid">
                @foreach ($proposal['terms'] as $label => $value)
                    <div class="term-card">
                        <span>{{ $label }}</span>
                        <p>{{ $value }}</p>
                    </div>
                @endforeach
                @if (! empty($proposal['quotation_note']))
                    <div class="term-card full-term">
                        <span>Quotation Note</span>
                        <p>{{ $proposal['quotation_note'] }}</p>
                    </div>
                @endif
            </div>

            <div class="scope-note-box client-terms-scope-note">
                <strong>Scope Note</strong>
                <p>Only the listed base scope is included in the current proposal value. Optional add-ons, utility responsibilities, owner-side works, and catalog candidates remain excluded unless selected or re-scoped by the company.</p>
            </div>

            <div class="release-gate-card {{ $proposal['release_ready'] ? 'ready' : 'draft' }}">
                <span>Release Gate</span>
                <strong>{{ $proposal['release_status'] }}</strong>
                <p>{{ $proposal['release_note'] }}</p>
            </div>

            <div class="signature-grid">
                <div>
                    <span>Prepared By</span>
                    <strong>{{ $proposal['prepared_by'] }}</strong>
                </div>
                <div>
                    <span>Accepted By</span>
                    <strong>{{ $proposal['prepared_for'] }}</strong>
                </div>
            </div>

            <div class="disclaimer-box client-disclaimer">
                <strong>Proposal Disclaimer</strong>
                <p>{{ $data['disclaimer'] }}</p>
            </div>
        </div>
    </section>
</body>
</html>
