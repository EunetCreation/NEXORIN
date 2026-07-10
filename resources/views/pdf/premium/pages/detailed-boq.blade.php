@php
    $boqChunks = array_chunk($data['commercial']['detailed_items'] ?? [], 22);
@endphp

@if (count($boqChunks) > 0)
    @foreach ($boqChunks as $chunkIndex => $boqItems)
        <section class="page detailed-boq-page">
            @include('pdf.premium.components.page-header', ['title' => 'Detailed BOM/BOQ Scope'])

            <div class="page-content">
                @include('pdf.premium.components.section-kicker', [
                    'eyebrow' => '05 · Controlled Scope'.(count($boqChunks) > 1 ? ' · '.($chunkIndex + 1).'/'.count($boqChunks) : ''),
                    'title' => $chunkIndex === 0 ? 'Detailed material, service, and responsibility matrix' : 'Detailed scope continuation',
                    'subtitle' => $chunkIndex === 0
                        ? 'This page separates chargeable project scope from optional and utility-responsibility items. It is designed to prevent missing small materials, hidden service costs, and unclear quotation boundaries.'
                        : 'Continuation page for BOM/BOQ items that do not fit on the previous page. Each row still includes scope, quantity, price status, and calculation basis.',
                ])

                @if ($chunkIndex === 0 && isset($data['commercial']['scope_stats']))
                    <div class="scope-summary-strip scope-summary-strip-wide">
                        <div><span>Total Scope Rows</span><strong>{{ $data['commercial']['scope_stats']['total_scope_rows'] ?? 0 }}</strong></div>
                        <div><span>Base BOQ</span><strong>{{ $data['commercial']['scope_stats']['base_boq_item_count'] ?? 0 }}</strong></div>
                        <div><span>Catalog Candidates</span><strong>{{ $data['commercial']['scope_stats']['catalog_candidate_item_count'] ?? 0 }}</strong></div>
                        <div><span>Optional Add-ons</span><strong>{{ $data['commercial']['scope_stats']['optional_addon_item_count'] ?? 0 }}</strong></div>
                        <div><span>Summary Cost</span><strong>{{ $data['commercial']['scope_stats']['summary_cost_item_count'] ?? 0 }}</strong></div>
                        <div><span>Utility Scope</span><strong>{{ $data['commercial']['scope_stats']['utility_responsibility_item_count'] ?? 0 }}</strong></div>
                        <div><span>Not Applicable</span><strong>{{ $data['commercial']['scope_stats']['not_applicable_item_count'] ?? 0 }}</strong></div>
                    </div>
                @endif

                <table class="premium-table detailed-boq-table">
                    <thead>
                        <tr>
                            <th>Cost Group</th>
                            <th>Item</th>
                            <th>Scope</th>
                            <th>Qty</th>
                            <th>Price / Status</th>
                            <th>Basis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($boqItems as $item)
                            <tr>
                                <td>{{ $item['group'] }}<br><span>{{ $item['category'] }}</span></td>
                                <td>{{ $item['name'] }}</td>
                                <td><span class="scope-badge">{{ $item['scope_badge'] ?? $item['scope'] }}</span></td>
                                <td>{{ $item['quantity'] }}</td>
                                <td>{{ $item['price'] }}</td>
                                <td>{{ $item['basis'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($loop->last)
                    <div class="disclaimer-box compact-boq-note">
                        <strong>Scope Note</strong>
                        <p>{{ $data['commercial']['scope_note'] }}</p>
                    </div>
                @endif
            </div>
        </section>
    @endforeach
@else
    <section class="page detailed-boq-page">
        @include('pdf.premium.components.page-header', ['title' => 'Detailed BOM/BOQ Scope'])
        <div class="page-content">
            @include('pdf.premium.components.section-kicker', [
                'eyebrow' => '05 · Controlled Scope',
                'title' => 'Detailed material, service, and responsibility matrix',
                'subtitle' => 'Detailed BOM/BOQ items are not available yet.',
            ])
            <div class="empty-panel compact-empty">Detailed BOM/BOQ items are not available yet.</div>
        </div>
    </section>
@endif
