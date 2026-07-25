@php
    $cancelUrl = $priceBook->exists
        ? route('companies.price-books.show', [$company, $priceBook->id])
        : route('companies.price-books.index', $company);

    $selectedSupplier = collect($suppliers)->firstWhere('id', old('supplier_id', $priceBook->supplier_id));
@endphp

@if ($errors->any())
    <div class="nxmc-alert nxmc-reveal" role="alert" style="margin-bottom: 10px;">
        <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
        <div>
            <strong>Price book draft requires correction.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif

<div class="nxmc-editor-grid">
    <aside class="nxmc-editor-rail nxmc-reveal">
        <div class="nxmc-editor-progress">
            <div class="nxmc-editor-progress-head">
                <div>
                    <span class="nxmc-panel-kicker">Commercial header structure</span>
                    <h3>Price book readiness</h3>
                </div>
                <strong class="nxmc-preview-score">02</strong>
            </div>
            <p>Use the price book header to define commercial source, validity, and pricing logic before item-level costs are linked.</p>
            <div class="nxmc-editor-track" aria-hidden="true"><span style="width: 78%;"></span></div>
            <nav class="nxmc-editor-steps" aria-label="Price book form sections">
                <a href="#price-book-identity" class="nxmc-editor-step is-active">
                    <span class="nxmc-editor-step-index">01</span><strong>Identity</strong><span class="nxmc-editor-step-state">Header record</span>
                </a>
                <a href="#price-book-window" class="nxmc-editor-step is-complete">
                    <span class="nxmc-editor-step-index">02</span><strong>Window</strong><span class="nxmc-editor-step-state">Dates & source</span>
                </a>
                <a href="#price-book-governance" class="nxmc-editor-step is-complete">
                    <span class="nxmc-editor-step-index">03</span><strong>Governance</strong><span class="nxmc-editor-step-state">Basis & notes</span>
                </a>
            </nav>
        </div>

        <div class="nxmc-editor-context">
            <span>Commercial source</span>
            <strong>{{ $selectedSupplier?->name ?? 'Internal / mixed suppliers' }}</strong>
            <p>{{ $selectedSupplier?->supplier_code ? 'Supplier code '.$selectedSupplier->supplier_code : 'Use this option for internal or blended sourcing references.' }}</p>
        </div>

        <div class="nxmc-editor-context">
            <span>Current summary</span>
            <strong>{{ old('name', $priceBook->name) ?: 'New price book draft' }}</strong>
            <p>{{ old('currency', $priceBook->currency ?? 'IDR') }} · {{ $statuses[old('status', $priceBook->status ?? 'draft')] ?? 'Draft' }} · {{ $pricingBases[old('pricing_basis', $priceBook->pricing_basis ?? 'supplier_quote')] ?? 'Supplier Quote' }}</p>
        </div>
    </aside>

    <div class="nxmc-editor-body">
        <section id="price-book-identity" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                    <div>
                        <h3>Price book identity</h3>
                        <p>Use a stable commercial header so downstream material pricing can always point back to a clear, governed source record.</p>
                    </div>
                </div>
                <span class="nxmc-section-state">Identity</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field">
                        <label class="nxmc-label" for="code">Price book code</label>
                        <input id="code" class="nxmc-input @error('code') is-invalid @enderror" name="code" value="{{ old('code', $priceBook->code) }}" required>
                        <span class="nxmc-field-hint">Use a durable controlled code that stays stable across revisions.</span>
                        @error('code') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="name">Price book name</label>
                        <input id="name" class="nxmc-input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $priceBook->name) }}" required>
                        <span class="nxmc-field-hint">Human-readable commercial header used by catalog and BOQ pricing workflows.</span>
                        @error('name') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="supplier_id">Supplier</label>
                        <select id="supplier_id" class="nxmc-select @error('supplier_id') is-invalid @enderror" name="supplier_id">
                            <option value="">Internal / mixed suppliers</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $priceBook->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}{{ $supplier->supplier_code ? ' · '.$supplier->supplier_code : '' }}</option>
                            @endforeach
                        </select>
                        <span class="nxmc-field-hint">Choose a supplier source, or keep it internal/mixed when the header is not tied to one vendor.</span>
                        @error('supplier_id') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="currency">Currency</label>
                        <input id="currency" class="nxmc-input @error('currency') is-invalid @enderror" name="currency" value="{{ old('currency', $priceBook->currency ?? 'IDR') }}" maxlength="3" required>
                        <span class="nxmc-field-hint">Use a three-letter currency code such as IDR or USD.</span>
                        @error('currency') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <section id="price-book-window" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3v3M16 3v3M4 10h16"/><rect x="4" y="5" width="16" height="16" rx="2"/></svg></span>
                    <div>
                        <h3>Commercial validity window</h3>
                        <p>Define when this price book becomes usable, when it expires, and how the header should behave in active commercial workflows.</p>
                    </div>
                </div>
                <span class="nxmc-section-state is-complete">Window</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field">
                        <label class="nxmc-label" for="effective_from">Effective from</label>
                        <input id="effective_from" class="nxmc-input @error('effective_from') is-invalid @enderror" type="date" name="effective_from" value="{{ old('effective_from', $priceBook->effective_from?->format('Y-m-d')) }}">
                        <span class="nxmc-field-hint">Start date for commercial validity. Leave blank if the record is open immediately.</span>
                        @error('effective_from') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="effective_until">Effective until</label>
                        <input id="effective_until" class="nxmc-input @error('effective_until') is-invalid @enderror" type="date" name="effective_until" value="{{ old('effective_until', $priceBook->effective_until?->format('Y-m-d')) }}">
                        <span class="nxmc-field-hint">Optional expiry date for the commercial reference window.</span>
                        @error('effective_until') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="status">Status</label>
                        <select id="status" class="nxmc-select @error('status') is-invalid @enderror" name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $priceBook->status ?? 'draft') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="nxmc-field-hint">Draft protects staging. Active releases the header for live usage. Archived preserves history only.</span>
                        @error('status') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="pricing_basis">Pricing basis</label>
                        <select id="pricing_basis" class="nxmc-select @error('pricing_basis') is-invalid @enderror" name="pricing_basis" required>
                            @foreach ($pricingBases as $value => $label)
                                <option value="{{ $value }}" @selected(old('pricing_basis', $priceBook->pricing_basis ?? 'supplier_quote') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="nxmc-field-hint">Describe the commercial logic behind this reference: quote, list price, contract, or internal benchmark.</span>
                        @error('pricing_basis') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <section id="price-book-governance" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
                    <div>
                        <h3>Governance notes</h3>
                        <p>Document the commercial context so later material-level pricing and BOQ interpretation stay aligned with the source strategy.</p>
                    </div>
                </div>
                <span class="nxmc-section-state is-complete">Governance</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label" for="notes">Notes</label>
                        <textarea id="notes" class="nxmc-textarea @error('notes') is-invalid @enderror" name="notes">{{ old('notes', $priceBook->notes) }}</textarea>
                        <span class="nxmc-field-hint">Use notes for commercial assumptions, source caveats, supplier conditions, or internal review context.</span>
                        @error('notes') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <div class="nxmc-editor-actionbar nxmc-reveal">
            <div class="nxmc-action-status is-dirty">
                <strong><span class="nxmc-dirty-dot" aria-hidden="true"></span>Price book governance draft</strong>
                <small>Save the header first. Material price items can be linked after the commercial source record is established.</small>
            </div>
            <div class="nxmc-action-buttons">
                <a href="{{ $cancelUrl }}" class="nxmc-button nxmc-button-secondary">Cancel</a>
                <button type="submit" class="nxmc-button nxmc-button-primary">{{ $submitLabel }}</button>
            </div>
        </div>
    </div>
</div>
