@extends('layouts.app')

@section('title', 'Import Price Book Items')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Price book import</div>
                <h2 class="ui-page-title">Import Price Book Items</h2>
                <p class="ui-page-description">Upload supplier CSV data, preview validation results, then commit only rows that pass material, supplier, price, and quote checks.</p>
            </div>
            <div class="ui-page-actions">
                <a href="{{ route('companies.price-books.show', [$company, $priceBook->id]) }}" class="ui-button ui-button-secondary">Back to Price Book</a>
            </div>
        </header>

        <section class="ui-card">
            <div class="ui-card-body">
                <div class="ui-info-grid">
                    <div class="ui-info-item"><span>Price Book</span><strong>{{ $priceBook->name }}</strong></div>
                    <div class="ui-info-item"><span>Currency</span><strong>{{ $priceBook->currency }}</strong></div>
                    <div class="ui-info-item"><span>Header Supplier</span><strong>{{ $priceBook->supplier?->name ?? 'Internal / mixed' }}</strong></div>
                    <div class="ui-info-item"><span>Mode</span><strong>Preview before commit</strong></div>
                </div>
            </div>
        </section>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">CSV Upload</h3>
                    <p class="ui-card-description">Use the minimum headers: material_key, unit_cost. Optional headers improve supplier traceability and price health.</p>
                </div>
            </div>
            <div class="ui-card-body">
                <form method="POST" action="{{ route('companies.price-books.items.import.preview', [$company, $priceBook->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="ui-form-grid">
                        <div class="ui-field ui-form-span-2">
                            <label class="ui-label" for="import_file">CSV File</label>
                            <input id="import_file" class="ui-input @error('import_file') is-invalid @enderror" type="file" name="import_file" accept=".csv,text/csv,text/plain" required>
                            @error('import_file') <div class="ui-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="ui-form-actions">
                        <button type="submit" class="ui-button ui-button-primary">Preview Import</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Accepted CSV Headers</h3>
                    <p class="ui-card-description">The importer accepts English and simple operational aliases so supplier files need less manual cleanup.</p>
                </div>
            </div>
            <div class="ui-card-body">
                <div class="ui-table-scroll">
                    <table class="ui-table">
                        <thead><tr><th>Purpose</th><th>Accepted Headers</th><th>Required</th></tr></thead>
                        <tbody>
                            <tr><td>Material matching</td><td>material_key, material_sku, sku, item_code, material</td><td>Yes</td></tr>
                            <tr><td>Supplier matching</td><td>supplier_code, supplier, supplier_name</td><td>No</td></tr>
                            <tr><td>Cost and selling</td><td>unit_cost, cost, harga_modal, selling_price, harga_jual, margin_percent, margin</td><td>unit_cost only</td></tr>
                            <tr><td>Availability</td><td>availability_status, availability</td><td>No, defaults to available</td></tr>
                            <tr><td>Quote workflow</td><td>quote_status, quoted_at, quote_date, valid_until, expired_at, expiry_date</td><td>No, defaults to active quote</td></tr>
                            <tr><td>Procurement detail</td><td>supplier_sku, vendor_sku, moq, minimum_order_quantity, lead_time_days, lead_time</td><td>No</td></tr>
                            <tr><td>Notes</td><td>warranty_note, warranty, notes, note</td><td>No</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="ui-card-description" style="margin-top: 16px;">Supported availability values: {{ implode(', ', array_keys($availabilityStatuses)) }}. Supported quote values: {{ implode(', ', array_keys($quoteStatuses)) }}.</p>
            </div>
        </section>
    </div>
@endsection
