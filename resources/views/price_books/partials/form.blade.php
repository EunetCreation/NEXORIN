<div class="ui-form-section">
    <div class="ui-form-section-heading">
        <div>
            <h3>Price Book Identity</h3>
            <p>A price book groups material costs and selling references by supplier, date, currency, and pricing basis.</p>
        </div>
    </div>

    <div class="ui-form-grid">
        <div class="ui-field">
            <label class="ui-label" for="code">Price Book Code</label>
            <input id="code" class="ui-input @error('code') is-invalid @enderror" name="code" value="{{ old('code', $priceBook->code) }}" required>
            @error('code') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
        <div class="ui-field">
            <label class="ui-label" for="name">Price Book Name</label>
            <input id="name" class="ui-input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $priceBook->name) }}" required>
            @error('name') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
        <div class="ui-field">
            <label class="ui-label" for="supplier_id">Supplier</label>
            <select id="supplier_id" class="ui-select" name="supplier_id">
                <option value="">Internal / mixed suppliers</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $priceBook->supplier_id) === (string) $supplier->id)>{{ $supplier->name }} · {{ $supplier->supplier_code }}</option>
                @endforeach
            </select>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="currency">Currency</label>
            <input id="currency" class="ui-input" name="currency" value="{{ old('currency', $priceBook->currency ?? 'IDR') }}" maxlength="3" required>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="effective_from">Effective From</label>
            <input id="effective_from" class="ui-input" type="date" name="effective_from" value="{{ old('effective_from', $priceBook->effective_from?->format('Y-m-d')) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="effective_until">Effective Until</label>
            <input id="effective_until" class="ui-input @error('effective_until') is-invalid @enderror" type="date" name="effective_until" value="{{ old('effective_until', $priceBook->effective_until?->format('Y-m-d')) }}">
            @error('effective_until') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
        <div class="ui-field">
            <label class="ui-label" for="pricing_basis">Pricing Basis</label>
            <select id="pricing_basis" class="ui-select" name="pricing_basis" required>
                @foreach ($pricingBases as $value => $label)
                    <option value="{{ $value }}" @selected(old('pricing_basis', $priceBook->pricing_basis ?? 'supplier_quote') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="status">Status</label>
            <select id="status" class="ui-select" name="status" required>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $priceBook->status ?? 'draft') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="ui-field ui-form-span-2">
            <label class="ui-label" for="notes">Notes</label>
            <textarea id="notes" class="ui-textarea" name="notes">{{ old('notes', $priceBook->notes) }}</textarea>
        </div>
    </div>
</div>
<div class="ui-form-actions">
    <a href="{{ $priceBook->exists ? route('companies.price-books.show', [$company, $priceBook->id]) : route('companies.price-books.index', $company) }}" class="ui-button ui-button-secondary">Cancel</a>
    <button type="submit" class="ui-button ui-button-primary">{{ $submitLabel }}</button>
</div>
