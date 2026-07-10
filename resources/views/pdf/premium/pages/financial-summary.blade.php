<section class="page financial-page">
    @include('pdf.premium.components.page-header', ['title' => 'Financial and Commercial Summary'])

    <div class="page-content">
        @include('pdf.premium.components.section-kicker', [
            'eyebrow' => '04 · Business Output',
            'title' => 'Investment, savings, and proposal readiness',
            'subtitle' => 'The financial layer translates engineering output into commercial insight. It keeps customer-facing value visible while preserving internal control of BOM, BOQ, margin, and assumptions.',
        ])

        <div class="financial-hero">
            <div class="financial-main dark-card">
                <span>Grand Price</span>
                <strong>{{ $data['commercial']['grand_price'] }}</strong>
                <p>Status: {{ $data['commercial']['bom_status'] }} · Pricing: {{ $data['commercial']['pricing_status'] }} · Source: {{ $data['commercial']['value_source'] }}</p>
            </div>
            <div class="finance-tile"><span>First-Year Saving</span><strong>{{ $data['financial']['first_year_saving'] }}</strong></div>
            <div class="finance-tile"><span>Simple Payback</span><strong>{{ $data['financial']['payback'] }}</strong></div>
            <div class="finance-tile"><span>IRR</span><strong>{{ $data['financial']['irr'] }}</strong></div>
        </div>

        <div class="dashboard-grid financial-grid">
            <div class="chart-card">
                <div class="card-head">
                    <div><span>Cost Composition</span><strong>BOQ price structure</strong></div>
                    <b>{{ $data['commercial']['gross_margin'] }}</b>
                </div>
                @if ($data['commercial']['breakdown_ready'])
                    <div class="cost-stack-list">
                        @foreach ($data['charts']['cost_stack'] as $row)
                            <div class="cost-stack-row">
                                <div class="cost-stack-label"><span>{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong></div>
                                <div class="cost-track"><span style="width: {{ max(0, min(100, $row['percent'])) }}%"></span></div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pricing-pending-card">
                        <strong>Internal BOQ pricing is not ready</strong>
                        <p>{{ $data['commercial']['breakdown_note'] }}</p>
                        <span>Displayed value source: {{ $data['commercial']['value_source'] }}</span>
                    </div>
                @endif
            </div>

            <div class="chart-card wide-card">
                <div class="card-head">
                    <div><span>Commercial Summary</span><strong>Controlled selling output</strong></div>
                    <b>IDR</b>
                </div>
                <table class="kv-table compact-kv">
                    <tbody>
                        <tr><th>Pricing Basis</th><td>{{ $data['commercial']['value_source'] }}</td></tr>
                        <tr><th>Internal Breakdown</th><td>{{ $data['commercial']['breakdown_ready'] ? 'Available' : 'Pending BOQ pricing' }}</td></tr>
                        <tr><th>Material Price</th><td>{{ $data['commercial']['material_price'] }}</td></tr>
                        <tr><th>Labor</th><td>{{ $data['commercial']['labor'] }}</td></tr>
                        <tr><th>Logistics</th><td>{{ $data['commercial']['logistics'] }}</td></tr>
                        <tr><th>Tax</th><td>{{ $data['commercial']['tax'] }}</td></tr>
                        <tr><th>Discount</th><td>{{ $data['commercial']['discount'] }}</td></tr>
                        <tr><th>Grand Price</th><td><strong>{{ $data['commercial']['grand_price'] }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if (! empty($data['commercial']['commercial_note']))
            <div class="commercial-note-box inline-commercial-note">
                <strong>Commercial Review Note</strong>
                <p>{{ $data['commercial']['commercial_note'] }}</p>
            </div>
        @endif

        <div class="two-col-section bottom-section">
            <div class="table-card">
                <div class="card-head compact"><div><span>Financial Indicators</span><strong>{{ $data['financial']['status'] }}</strong></div></div>
                <table class="kv-table">
                    <tbody>
                        <tr><th>CAPEX</th><td>{{ $data['financial']['capex'] }}</td></tr>
                        <tr><th>Annual O&M</th><td>{{ $data['financial']['annual_om'] }}</td></tr>
                        <tr><th>NPV</th><td>{{ $data['financial']['npv'] }}</td></tr>
                        <tr><th>ROI</th><td>{{ $data['financial']['roi'] }}</td></tr>
                        <tr><th>LCOE</th><td>{{ $data['financial']['lcoe'] }}</td></tr>
                        <tr><th>Electricity Tariff</th><td>{{ $data['financial']['tariff'] }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <div class="card-head compact"><div><span>BOM Highlights</span><strong>{{ $data['commercial']['base_boq_item_count'] }} base · {{ $data['commercial']['item_count'] }} scope</strong></div></div>
                <div class="scope-mini-stats">
                    <span>{{ $data['commercial']['base_boq_item_count'] }} Base BOQ</span>
                    <span>{{ $data['commercial']['catalog_candidate_item_count'] }} Catalog Candidates</span>
                    <span>{{ $data['commercial']['optional_addon_item_count'] }} Optional Add-ons</span>
                    <span>{{ $data['commercial']['summary_cost_item_count'] }} Summary Cost</span>
                    <span>{{ $data['commercial']['utility_responsibility_item_count'] }} Utility Scope</span>
                    <span>{{ $data['commercial']['not_applicable_item_count'] }} Not Applicable</span>
                </div>
                @if (count($data['commercial']['top_items']) > 0)
                    <table class="premium-table small-table">
                        <thead>
                            <tr><th>Category</th><th>Item</th><th>Qty</th><th>Price</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($data['commercial']['top_items'] as $item)
                                <tr>
                                    <td>{{ $item['category'] }}</td>
                                    <td>{{ $item['name'] }}</td>
                                    <td>{{ $item['quantity'] }}</td>
                                    <td>{{ $item['price'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty-panel compact-empty">BOM items are not available for the premium summary yet.</div>
                @endif
            </div>
        </div>

        <div class="disclaimer-box">
            <strong>Professional Disclaimer</strong>
            <p>{{ $data['disclaimer'] }}</p>
        </div>
    </div>
</section>
