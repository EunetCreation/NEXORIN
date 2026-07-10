@extends('layouts.app')

@section('title', 'Price Import Preview')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Price book import</div>
                <h2 class="ui-page-title">Import Preview</h2>
                <p class="ui-page-description">Review validation results before committing supplier prices into {{ $priceBook->name }}.</p>
            </div>
            <div class="ui-page-actions">
                <a href="{{ route('companies.price-books.items.import.form', [$company, $priceBook->id]) }}" class="ui-button ui-button-secondary">Upload Another CSV</a>
                <a href="{{ route('companies.price-books.show', [$company, $priceBook->id]) }}" class="ui-button ui-button-secondary">Back to Price Book</a>
            </div>
        </header>

        <section class="ui-stats-grid">
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">RW</span></div><strong class="ui-stat-value">{{ number_format($summary['total']) }}</strong><span class="ui-stat-label">Rows</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">OK</span></div><strong class="ui-stat-value">{{ number_format($summary['valid']) }}</strong><span class="ui-stat-label">Valid</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">WR</span></div><strong class="ui-stat-value">{{ number_format($summary['warning']) }}</strong><span class="ui-stat-label">Warnings</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">ER</span></div><strong class="ui-stat-value">{{ number_format($summary['error']) }}</strong><span class="ui-stat-label">Errors</span></article>
        </section>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Preview Result</h3>
                    <p class="ui-card-description">Batch {{ $batchCode }}. Rows with errors will not be committed. Warning rows can still be committed and should be reviewed by procurement.</p>
                </div>
                @if (($summary['valid'] + $summary['warning']) > 0)
                    <form method="POST" action="{{ route('companies.price-books.items.import.commit', [$company, $priceBook->id]) }}" data-confirm="Commit valid and warning rows into this price book?">
                        @csrf
                        <button type="submit" class="ui-button ui-button-primary">Commit Import</button>
                    </form>
                @endif
            </div>
            <div class="ui-card-body">
                @if ($previewRows === [])
                    <div class="ui-empty ui-empty-compact"><div><h3>No rows detected</h3><p>The CSV file did not contain importable data rows.</p></div></div>
                @else
                    <div class="ui-table-scroll">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Material</th>
                                    <th>Supplier</th>
                                    <th>Action</th>
                                    <th>Cost</th>
                                    <th>Selling / Margin</th>
                                    <th>Availability</th>
                                    <th>Quote</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($previewRows as $row)
                                    <tr>
                                        <td>{{ $row['row_number'] }}</td>
                                        <td><div class="ui-table-primary">{{ $row['material_label'] }}</div></td>
                                        <td>{{ $row['supplier_label'] ?? 'No supplier' }}</td>
                                        <td>{{ ucfirst($row['action']) }}</td>
                                        <td>{{ $row['values']['unit_cost'] !== null ? number_format($row['values']['unit_cost'], 0, ',', '.') : '—' }}</td>
                                        <td>
                                            <div class="ui-table-primary">{{ $row['values']['selling_price'] !== null ? number_format($row['values']['selling_price'], 0, ',', '.') : 'No override' }}</div>
                                            <div class="ui-table-secondary">{{ $row['values']['margin_percent'] !== null ? number_format($row['values']['margin_percent'], 2).'%' : 'No margin' }}</div>
                                        </td>
                                        <td>{{ $row['values']['availability_status'] ?? '—' }}</td>
                                        <td>
                                            <div class="ui-table-primary">{{ $row['values']['quote_status'] ?? 'active' }}</div>
                                            <div class="ui-table-secondary">{{ $row['values']['valid_until'] ?? 'open validity' }}</div>
                                        </td>
                                        <td>
                                            @if ($row['status'] === 'valid')
                                                <span class="ui-status ui-status-active">Valid</span>
                                            @elseif ($row['status'] === 'warning')
                                                <span class="ui-status ui-status-pending">Warning</span>
                                            @else
                                                <span class="ui-status ui-status-inactive">Error</span>
                                            @endif
                                            <div class="ui-table-secondary" style="margin-top: 6px;">
                                                {{ implode(' ', $row['messages']) }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
