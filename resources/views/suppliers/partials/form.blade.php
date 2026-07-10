<div class="ui-form-section">
    <div class="ui-form-section-heading">
        <div>
            <h3>Supplier Identity</h3>
            <p>Supplier records support price books, procurement traceability, lead time, warranty source, and future quotation audit.</p>
        </div>
    </div>

    <div class="ui-form-grid">
        <div class="ui-field">
            <label class="ui-label" for="supplier_code">Supplier Code</label>
            <input id="supplier_code" class="ui-input @error('supplier_code') is-invalid @enderror" name="supplier_code" value="{{ old('supplier_code', $supplier->supplier_code) }}" required>
            @error('supplier_code') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
        <div class="ui-field">
            <label class="ui-label" for="name">Supplier Name</label>
            <input id="name" class="ui-input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $supplier->name) }}" required>
            @error('name') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
        <div class="ui-field">
            <label class="ui-label" for="contact_person">Contact Person</label>
            <input id="contact_person" class="ui-input" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="email">Email</label>
            <input id="email" class="ui-input @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email', $supplier->email) }}">
            @error('email') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
        <div class="ui-field">
            <label class="ui-label" for="phone">Phone</label>
            <input id="phone" class="ui-input" name="phone" value="{{ old('phone', $supplier->phone) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="website">Website</label>
            <input id="website" class="ui-input @error('website') is-invalid @enderror" name="website" value="{{ old('website', $supplier->website) }}" placeholder="https://example.com">
            @error('website') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
        <div class="ui-field">
            <label class="ui-label" for="status">Status</label>
            <select id="status" class="ui-select" name="status" required>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $supplier->status ?? 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="lead_time_days">Lead Time (Days)</label>
            <input id="lead_time_days" class="ui-input" type="number" min="0" max="3650" name="lead_time_days" value="{{ old('lead_time_days', $supplier->lead_time_days) }}">
        </div>
        <div class="ui-field ui-form-span-2">
            <label class="ui-label" for="address">Address</label>
            <textarea id="address" class="ui-textarea" name="address">{{ old('address', $supplier->address) }}</textarea>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="tax_number">Tax Number</label>
            <input id="tax_number" class="ui-input" name="tax_number" value="{{ old('tax_number', $supplier->tax_number) }}">
        </div>
        <div class="ui-field">
            <label class="ui-label" for="payment_terms">Payment Terms</label>
            <input id="payment_terms" class="ui-input" name="payment_terms" value="{{ old('payment_terms', $supplier->payment_terms) }}" placeholder="30% DP, 70% before delivery">
        </div>
        <div class="ui-field ui-form-span-2">
            <label class="ui-label" for="notes">Notes</label>
            <textarea id="notes" class="ui-textarea" name="notes">{{ old('notes', $supplier->notes) }}</textarea>
        </div>
    </div>
</div>
<div class="ui-form-actions">
    <a href="{{ $supplier->exists ? route('companies.suppliers.show', [$company, $supplier->id]) : route('companies.suppliers.index', $company) }}" class="ui-button ui-button-secondary">Cancel</a>
    <button type="submit" class="ui-button ui-button-primary">{{ $submitLabel }}</button>
</div>
