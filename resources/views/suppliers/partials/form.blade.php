@php
    $cancelUrl = $supplier->exists
        ? route('companies.suppliers.show', [$company, $supplier->id])
        : route('companies.suppliers.index', $company);
@endphp

@if ($errors->any())
    <div class="nxmc-alert nxmc-reveal" role="alert" style="margin-bottom: 10px;">
        <span class="nxmc-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
        <div>
            <strong>Supplier record requires correction.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif

<div class="nxmc-editor-grid">
    <aside class="nxmc-editor-rail nxmc-reveal">
        <div class="nxmc-editor-progress">
            <div class="nxmc-editor-progress-head">
                <div>
                    <span class="nxmc-panel-kicker">Procurement form structure</span>
                    <h3>Supplier readiness</h3>
                </div>
                <strong class="nxmc-preview-score">01</strong>
            </div>
            <p>Structure the supplier record so future price books and procurement decisions inherit complete commercial context.</p>
            <div class="nxmc-editor-track" aria-hidden="true"><span style="width: 72%;"></span></div>
            <nav class="nxmc-editor-steps" aria-label="Supplier form sections">
                <a href="#supplier-identity" class="nxmc-editor-step is-active">
                    <span class="nxmc-editor-step-index">01</span><strong>Identity</strong><span class="nxmc-editor-step-state">Core record</span>
                </a>
                <a href="#supplier-commercial" class="nxmc-editor-step is-complete">
                    <span class="nxmc-editor-step-index">02</span><strong>Commercial</strong><span class="nxmc-editor-step-state">Terms & lead</span>
                </a>
                <a href="#supplier-governance" class="nxmc-editor-step is-complete">
                    <span class="nxmc-editor-step-index">03</span><strong>Governance</strong><span class="nxmc-editor-step-state">Status & notes</span>
                </a>
            </nav>
        </div>

        <div class="nxmc-editor-context">
            <span>Record intent</span>
            <strong>Suppliers govern the commercial origin of price books.</strong>
            <p>A clean supplier record should tell the team who the source is, how they are contacted, what the delivery posture looks like, and whether the vendor is allowed for active sourcing.</p>
        </div>

        <div class="nxmc-editor-context">
            <span>Current summary</span>
            <strong>{{ old('name', $supplier->name) ?: 'New supplier draft' }}</strong>
            <p>{{ old('supplier_code', $supplier->supplier_code) ?: 'Supplier code pending' }} · {{ ucfirst(old('status', $supplier->status ?? 'active')) }}</p>
        </div>
    </aside>

    <div class="nxmc-editor-body">
        <section id="supplier-identity" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V8l8-5 8 5v12"/><path d="M8 20v-6h8v6M7 10h10"/></svg></span>
                    <div>
                        <h3>Supplier identity</h3>
                        <p>Use stable commercial identity values so the supplier can be recognized cleanly in price books, procurement checks, and future traceability logs.</p>
                    </div>
                </div>
                <span class="nxmc-section-state">Identity</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field">
                        <label class="nxmc-label" for="supplier_code">Supplier code</label>
                        <input id="supplier_code" class="nxmc-input @error('supplier_code') is-invalid @enderror" name="supplier_code" value="{{ old('supplier_code', $supplier->supplier_code) }}" required>
                        <span class="nxmc-field-hint">Use a durable internal reference code for sourcing and price-book linkage.</span>
                        @error('supplier_code') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="name">Supplier name</label>
                        <input id="name" class="nxmc-input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $supplier->name) }}" required>
                        <span class="nxmc-field-hint">Use the official supplier or business name that will appear in commercial references.</span>
                        @error('name') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="contact_person">Contact person</label>
                        <input id="contact_person" class="nxmc-input @error('contact_person') is-invalid @enderror" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}">
                        <span class="nxmc-field-hint">Primary commercial or account contact for quotation follow-up.</span>
                        @error('contact_person') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="email">Email</label>
                        <input id="email" class="nxmc-input @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email', $supplier->email) }}">
                        <span class="nxmc-field-hint">Commercial inbox used for supplier quote requests and confirmations.</span>
                        @error('email') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="phone">Phone</label>
                        <input id="phone" class="nxmc-input @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $supplier->phone) }}">
                        <span class="nxmc-field-hint">Direct phone or mobile number for procurement escalation.</span>
                        @error('phone') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="website">Website</label>
                        <input id="website" class="nxmc-input @error('website') is-invalid @enderror" name="website" value="{{ old('website', $supplier->website) }}" placeholder="https://example.com">
                        <span class="nxmc-field-hint">Optional public profile for supplier verification or document source context.</span>
                        @error('website') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <section id="supplier-commercial" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg></span>
                    <div>
                        <h3>Commercial and logistics profile</h3>
                        <p>Capture the terms that affect sourcing behavior, delivery expectations, compliance traceability, and commercial comparison across vendors.</p>
                    </div>
                </div>
                <span class="nxmc-section-state is-complete">Commercial</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field">
                        <label class="nxmc-label" for="lead_time_days">Lead time (days)</label>
                        <input id="lead_time_days" class="nxmc-input @error('lead_time_days') is-invalid @enderror" type="number" min="0" max="3650" name="lead_time_days" value="{{ old('lead_time_days', $supplier->lead_time_days) }}">
                        <span class="nxmc-field-hint">Useful for procurement planning, delivery commitments, and schedule risk awareness.</span>
                        @error('lead_time_days') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="payment_terms">Payment terms</label>
                        <input id="payment_terms" class="nxmc-input @error('payment_terms') is-invalid @enderror" name="payment_terms" value="{{ old('payment_terms', $supplier->payment_terms) }}" placeholder="30% DP, 70% before delivery">
                        <span class="nxmc-field-hint">Document how the commercial settlement is usually structured.</span>
                        @error('payment_terms') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="tax_number">Tax number</label>
                        <input id="tax_number" class="nxmc-input @error('tax_number') is-invalid @enderror" name="tax_number" value="{{ old('tax_number', $supplier->tax_number) }}">
                        <span class="nxmc-field-hint">Tax registration helps formalize commercial compliance and invoice handling.</span>
                        @error('tax_number') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field">
                        <label class="nxmc-label" for="status">Supplier status</label>
                        <select id="status" class="nxmc-select @error('status') is-invalid @enderror" name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $supplier->status ?? 'active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="nxmc-field-hint">Active suppliers remain eligible for ongoing sourcing. Other states preserve governance history.</span>
                        @error('status') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label" for="address">Address</label>
                        <textarea id="address" class="nxmc-textarea @error('address') is-invalid @enderror" name="address">{{ old('address', $supplier->address) }}</textarea>
                        <span class="nxmc-field-hint">Operational or billing address used for procurement, verification, and logistics reference.</span>
                        @error('address') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <section id="supplier-governance" class="nxmc-form-section nxmc-reveal">
            <header class="nxmc-form-section-head">
                <div class="nxmc-form-section-title">
                    <span class="nxmc-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
                    <div>
                        <h3>Governance notes</h3>
                        <p>Keep a concise governance narrative that explains supplier concerns, sourcing limitations, or onboarding context for the team.</p>
                    </div>
                </div>
                <span class="nxmc-section-state is-complete">Governance</span>
            </header>
            <div class="nxmc-form-section-body">
                <div class="nxmc-form-grid">
                    <div class="nxmc-field nxmc-form-span-2">
                        <label class="nxmc-label" for="notes">Notes</label>
                        <textarea id="notes" class="nxmc-textarea @error('notes') is-invalid @enderror" name="notes">{{ old('notes', $supplier->notes) }}</textarea>
                        <span class="nxmc-field-hint">Use notes for warranty concerns, sourcing nuance, preferred channels, or commercial caution.</span>
                        @error('notes') <div class="nxmc-field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>

        <div class="nxmc-editor-actionbar nxmc-reveal">
            <div class="nxmc-action-status is-dirty">
                <strong><span class="nxmc-dirty-dot" aria-hidden="true"></span>Supplier governance draft</strong>
                <small>Save this supplier to make it available for controlled price-book and procurement workflows.</small>
            </div>
            <div class="nxmc-action-buttons">
                <a href="{{ $cancelUrl }}" class="nxmc-button nxmc-button-secondary">Cancel</a>
                <button type="submit" class="nxmc-button nxmc-button-primary">{{ $submitLabel }}</button>
            </div>
        </div>
    </div>
</div>
