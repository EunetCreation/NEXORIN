<div class="ui-form-section">
    <div class="ui-form-section-heading">
        <div>
            <h3>Material and Supplier Link</h3>
            <p>Connect one catalog material to this price book and define the supplier reference used for procurement and BOQ pricing.</p>
        </div>
    </div>

    <div class="ui-form-grid">
        <div class="ui-field ui-form-span-2">
            <label class="ui-label" for="material_id">Material</label>
            <select id="material_id" class="ui-select @error('material_id') is-invalid @enderror" name="material_id" required>
                <option value="">Select material</option>
                @foreach ($materials as $material)
                    <option value="{{ $material->id }}" @selected((string) old('material_id', $priceBookItem->material_id ?? '') === (string) $material->id)>
                        {{ $material->name }} · {{ $material->sku }}{{ $material->brand ? ' · '.$material->brand : '' }}{{ $material->model ? ' '.$material->model : '' }}{{ $material->category ? ' · '.$material->category->name : '' }} · {{ ($material->catalog_status === 'approved' && $material->compliance_status === 'compliant') ? 'Catalog Approved' : 'Catalog Review Required' }}
                    </option>
                @endforeach
            </select>
            <small class="ui-help">Approved catalog materials are preferred by project pricing. Draft materials may be staged in a price book, but project-compatible BOQ resolution retains a catalog review trace.</small>
            @error('material_id') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="supplier_id">Item Supplier</label>
            <select id="supplier_id" class="ui-select @error('supplier_id') is-invalid @enderror" name="supplier_id">
                <option value="">Use price book supplier / internal source</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $priceBookItem->supplier_id ?? $priceBook->supplier_id) === (string) $supplier->id)>
                        {{ $supplier->name }} · {{ $supplier->supplier_code }}
                    </option>
                @endforeach
            </select>
            @error('supplier_id') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="supplier_sku">Supplier SKU</label>
            <input id="supplier_sku" class="ui-input @error('supplier_sku') is-invalid @enderror" name="supplier_sku" value="{{ old('supplier_sku', $priceBookItem->supplier_sku) }}" maxlength="100" placeholder="Vendor item code">
            @error('supplier_sku') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="ui-form-section">
    <div class="ui-form-section-heading">
        <div>
            <h3>Cost, Selling Reference, and Availability</h3>
            <p>Store cost price, optional selling override, margin reference, MOQ, lead time, and quote validity in a controlled form.</p>
        </div>
    </div>

    <div class="ui-form-grid">
        <div class="ui-field">
            <label class="ui-label" for="unit_cost">Unit Cost</label>
            <input id="unit_cost" class="ui-input @error('unit_cost') is-invalid @enderror" type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost', $priceBookItem->unit_cost ?? 0) }}" required>
            @error('unit_cost') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="selling_price">Selling Price Override</label>
            <input id="selling_price" class="ui-input @error('selling_price') is-invalid @enderror" type="number" step="0.01" min="0" name="selling_price" value="{{ old('selling_price', $priceBookItem->selling_price) }}" placeholder="Optional">
            @error('selling_price') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="margin_percent">Margin Percent</label>
            <input id="margin_percent" class="ui-input @error('margin_percent') is-invalid @enderror" type="number" step="0.01" min="0" max="1000" name="margin_percent" value="{{ old('margin_percent', $priceBookItem->margin_percent) }}" placeholder="Optional">
            @error('margin_percent') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="minimum_order_quantity">MOQ</label>
            <input id="minimum_order_quantity" class="ui-input @error('minimum_order_quantity') is-invalid @enderror" type="number" step="0.001" min="0" name="minimum_order_quantity" value="{{ old('minimum_order_quantity', $priceBookItem->minimum_order_quantity) }}" placeholder="Optional">
            @error('minimum_order_quantity') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="lead_time_days">Lead Time Days</label>
            <input id="lead_time_days" class="ui-input @error('lead_time_days') is-invalid @enderror" type="number" min="0" max="3650" name="lead_time_days" value="{{ old('lead_time_days', $priceBookItem->lead_time_days) }}" placeholder="Optional">
            @error('lead_time_days') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="availability_status">Availability</label>
            <select id="availability_status" class="ui-select @error('availability_status') is-invalid @enderror" name="availability_status" required>
                @foreach ($availabilityStatuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('availability_status', $priceBookItem->availability_status ?? 'available') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('availability_status') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="quote_status">Quote Status</label>
            <select id="quote_status" class="ui-select @error('quote_status') is-invalid @enderror" name="quote_status">
                @foreach ($quoteStatuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('quote_status', $priceBookItem->quote_status ?? 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('quote_status') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="quoted_at">Quoted At</label>
            <input id="quoted_at" class="ui-input @error('quoted_at') is-invalid @enderror" type="date" name="quoted_at" value="{{ old('quoted_at', $priceBookItem->quoted_at?->format('Y-m-d')) }}">
            @error('quoted_at') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="valid_until">Valid Until</label>
            <input id="valid_until" class="ui-input @error('valid_until') is-invalid @enderror" type="date" name="valid_until" value="{{ old('valid_until', $priceBookItem->valid_until?->format('Y-m-d')) }}">
            @error('valid_until') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field ui-form-span-2">
            <label class="ui-label" for="warranty_note">Warranty Note</label>
            <textarea id="warranty_note" class="ui-textarea @error('warranty_note') is-invalid @enderror" name="warranty_note" placeholder="Product or supplier warranty statement">{{ old('warranty_note', $priceBookItem->warranty_note) }}</textarea>
            @error('warranty_note') <div class="ui-error">{{ $message }}</div> @enderror
        </div>

        <div class="ui-field ui-form-span-2">
            <label class="ui-label" for="notes">Notes</label>
            <textarea id="notes" class="ui-textarea @error('notes') is-invalid @enderror" name="notes" placeholder="Procurement notes, discount condition, currency note, or quotation reference">{{ old('notes', $priceBookItem->notes) }}</textarea>
            @error('notes') <div class="ui-error">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="ui-form-actions">
    <a href="{{ route('companies.price-books.show', [$company, $priceBook->id]) }}" class="ui-button ui-button-secondary">Cancel</a>
    <button type="submit" class="ui-button ui-button-primary">{{ $submitLabel }}</button>
</div>
