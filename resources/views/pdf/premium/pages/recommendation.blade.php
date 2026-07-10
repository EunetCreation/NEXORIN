<section class="page recommendation-page">
    @include('pdf.premium.components.page-header', ['title' => 'System Recommendation'])

    <div class="page-content">
        @include('pdf.premium.components.section-kicker', [
            'eyebrow' => '02 · Recommendation Engine',
            'title' => 'Explainable system selection',
            'subtitle' => 'The recommendation page shows the preferred configuration, scoring comparison, decision inputs, and reasons so the output can be reviewed by engineer, sales, and customer-facing teams.',
        ])

        <div class="recommendation-hero">
            <div class="recommendation-score">
                <span>Selected System</span>
                <strong>{{ $data['recommendation']['system'] }}</strong>
                <small>{{ $data['recommendation']['status'] }}</small>
            </div>
            <div class="score-bars">
                @foreach ($data['charts']['score_bars'] as $bar)
                    @php $scoreWidth = max(0, min(100, $bar['value'])); @endphp
                    <div class="score-row">
                        <div class="score-label"><span>{{ $bar['label'] }}</span><strong>{{ number_format($bar['value'], 2, ',', '.') }}</strong></div>
                        <div class="score-track"><span style="width: {{ $scoreWidth }}%"></span></div>
                    </div>
                @endforeach
                <div class="decisiveness">Decisiveness index: <strong>{{ $data['recommendation']['decisiveness'] }}</strong></div>
            </div>
        </div>

        <div class="decision-grid">
            <div><span>Grid Status</span><strong>{{ $data['recommendation']['grid_status'] }}</strong></div>
            <div><span>Primary Objective</span><strong>{{ $data['recommendation']['objective'] }}</strong></div>
            <div><span>Budget Profile</span><strong>{{ $data['recommendation']['budget'] }}</strong></div>
            <div><span>Export Policy</span><strong>{{ $data['recommendation']['export_policy'] }}</strong></div>
        </div>

        <div class="two-col-section">
            <div class="insight-card">
                <div class="card-head compact"><div><span>Decision Reasons</span><strong>Why this system is proposed</strong></div></div>
                <ol class="reason-list">
                    @foreach ($data['recommendation']['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ol>
            </div>
            <div class="insight-card muted-card">
                <div class="card-head compact"><div><span>Commercial Meaning</span><strong>How to present it</strong></div></div>
                <p>The recommendation should be positioned as a controlled planning basis. Sales can use the score and reasons to explain the trade-off between savings, backup reliability, roof limitation, budget, and export policy.</p>
                <p>Engineering should review hard constraints, roof survey readiness, string compatibility, voltage limits, and active warnings before procurement or final installation decision.</p>
            </div>
        </div>

        @if (count($data['recommendation']['score_rows']) > 0)
            <div class="table-card trace-card">
                <div class="card-head compact"><div><span>Scoring Trace</span><strong>Top criteria records</strong></div></div>
                <p class="trace-note">{{ $data['recommendation']['score_rows_note'] }}</p>
                <table class="premium-table trace-table">
                    <thead>
                        <tr>
                            <th>Alternative</th>
                            <th>Criterion</th>
                            <th>Weight</th>
                            <th>Rating</th>
                            <th>Score</th>
                            <th>Rationale</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['recommendation']['score_rows'] as $row)
                            <tr>
                                <td>{{ $row['alternative'] }}</td>
                                <td>{{ $row['criterion'] }}</td>
                                <td>{{ $row['weight'] }}</td>
                                <td>{{ $row['rating'] }}</td>
                                <td>{{ $row['weighted_score'] }}</td>
                                <td>{{ $row['rationale'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-panel">No detailed scoring rows are stored yet. Generate or refresh the recommendation engine to populate traceable criteria.</div>
        @endif
    </div>
</section>
