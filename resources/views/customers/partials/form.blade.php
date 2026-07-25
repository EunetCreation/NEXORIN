@php
    $editing = isset($customer);
    $selectedType = old('type', $customer->type ?? 'residential');
    $selectedPortal = (string) old('portal_user_id', $customer->portal_user_id ?? '');
@endphp

@if ($errors->any())
    <section class="nxcus-validation" role="alert">
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 17h.01"/></svg></span>
        <div><small>CONTROLLED VALIDATION</small><h3>Review the customer record before continuing</h3><p>{{ $errors->count() }} {{ $errors->count() === 1 ? 'field requires' : 'fields require' }} attention.</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </section>
@endif

<section id="customer-identity" class="nxcus-form-section" data-nxcus-section>
    <header class="nxcus-form-section-head">
        <span class="nxcus-form-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/><path d="M17 8v6M14 11h6"/></svg></span>
        <div><small>01 · CUSTOMER IDENTITY</small><h3>Identity &amp; Classification</h3><p>Establish the customer name and the relationship classification used by downstream project and proposal workflows.</p></div>
        <span class="nxcus-required-state">2 REQUIRED</span>
    </header>
    <div class="nxcus-form-grid">
        <label class="nxcus-field is-wide">
            <span>Customer name <b>Required</b></span>
            <input type="text" name="name" value="{{ old('name', $customer->name ?? '') }}" maxlength="150" autocomplete="name" required data-nxcus-name class="@error('name') is-invalid @enderror" placeholder="Full name or registered business name">
            <small>Use the identity that should appear on projects, quotations, and customer-facing documents.</small>
            @error('name')<em>{{ $message }}</em>@enderror
        </label>
        <fieldset class="nxcus-choice-field is-wide">
            <legend>Customer type <b>Required</b></legend>
            <div class="nxcus-choice-grid">
                <label class="nxcus-choice-card {{ $selectedType === 'residential' ? 'is-selected' : '' }}">
                    <input type="radio" name="type" value="residential" @checked($selectedType === 'residential') data-nxcus-type required>
                    <span class="nxcus-choice-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 11 12 4l9 7v9H3z"/><path d="M9 20v-6h6v6"/></svg></span>
                    <span><strong>Residential</strong><small>Household relationship, residential proposal context, and home-site project defaults.</small></span>
                    <i></i>
                </label>
                <label class="nxcus-choice-card {{ $selectedType === 'commercial' ? 'is-selected' : '' }}">
                    <input type="radio" name="type" value="commercial" @checked($selectedType === 'commercial') data-nxcus-type required>
                    <span class="nxcus-choice-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 21V5l8-3 8 3v16"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/></svg></span>
                    <span><strong>Commercial</strong><small>Business relationship, commercial proposal context, and organization-level project defaults.</small></span>
                    <i></i>
                </label>
            </div>
            @error('type')<em>{{ $message }}</em>@enderror
        </fieldset>
    </div>
</section>

<section id="contact-location" class="nxcus-form-section" data-nxcus-section>
    <header class="nxcus-form-section-head">
        <span class="nxcus-form-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="m4 7 8 6 8-6"/></svg></span>
        <div><small>02 · COMMUNICATION &amp; LOCATION</small><h3>Contact &amp; Location</h3><p>Record communication channels and location context without duplicating structured city, province, or postal information.</p></div>
        <span class="nxcus-recommended-state">RECOMMENDED</span>
    </header>
    <div class="nxcus-form-grid">
        <label class="nxcus-field"><span>Email address <b>Optional</b></span><input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" maxlength="255" autocomplete="email" placeholder="customer@example.com" data-nxcus-email class="@error('email') is-invalid @enderror"><small>Primary written communication channel.</small>@error('email')<em>{{ $message }}</em>@enderror</label>
        <label class="nxcus-field"><span>Phone number <b>Optional</b></span><input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" maxlength="30" autocomplete="tel" placeholder="+62 812-3456-7890" data-nxcus-phone class="@error('phone') is-invalid @enderror"><small data-nxcus-phone-preview>Use country code and a readable customer-facing format.</small>@error('phone')<em>{{ $message }}</em>@enderror</label>
        <label class="nxcus-field"><span>City or regency <b>Optional</b></span><input type="text" name="city" value="{{ old('city', $customer->city ?? '') }}" maxlength="100" data-nxcus-city class="@error('city') is-invalid @enderror" placeholder="Pekanbaru"><small>Used by project and relationship search.</small>@error('city')<em>{{ $message }}</em>@enderror</label>
        <label class="nxcus-field"><span>Province <b>Optional</b></span><input type="text" name="province" value="{{ old('province', $customer->province ?? '') }}" maxlength="100" data-nxcus-province class="@error('province') is-invalid @enderror" placeholder="Riau"><small>Regional relationship context.</small>@error('province')<em>{{ $message }}</em>@enderror</label>
        <label class="nxcus-field"><span>Postal code <b>Optional</b></span><input type="text" name="postal_code" value="{{ old('postal_code', $customer->postal_code ?? '') }}" maxlength="20" autocomplete="postal-code" data-nxcus-postal class="@error('postal_code') is-invalid @enderror" placeholder="28111"><small>Structured postal reference.</small>@error('postal_code')<em>{{ $message }}</em>@enderror</label>
        <label class="nxcus-field is-address"><span>Street and additional address <b>Optional</b></span><textarea name="address" maxlength="2000" autocomplete="street-address" data-nxcus-autogrow class="@error('address') is-invalid @enderror" placeholder="Street, building, district, and additional location details">{{ old('address', $customer->address ?? '') }}</textarea><small>Do not repeat city, province, or postal code unless clarification is necessary.</small>@error('address')<em>{{ $message }}</em>@enderror</label>
    </div>
</section>

<section id="portal-access" class="nxcus-form-section" data-nxcus-section>
    <header class="nxcus-form-section-head">
        <span class="nxcus-form-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/><path d="M17 5h4v4M21 5l-7 7"/></svg></span>
        <div><small>03 · PORTAL OWNERSHIP</small><h3>Portal Access Governance</h3><p>Optionally link one active company member with the Customer role to restricted customer-owned project access.</p></div>
        <span class="nxcus-governance-state">CONTROLLED LINK</span>
    </header>
    <div class="nxcus-portal-editor">
        <label class="nxcus-field">
            <span>Customer portal account <b>Optional</b></span>
            <select name="portal_user_id" data-nxcus-portal class="@error('portal_user_id') is-invalid @enderror">
                <option value="">Not linked</option>
                @foreach ($portalUsers as $portalUser)
                    <option value="{{ $portalUser->id }}" data-name="{{ $portalUser->name }}" data-email="{{ $portalUser->email }}" @selected($selectedPortal === (string) $portalUser->id)>{{ $portalUser->name }} · {{ $portalUser->email }}</option>
                @endforeach
            </select>
            <small>Only active company members with the Customer role are eligible.</small>
            @error('portal_user_id')<em>{{ $message }}</em>@enderror
        </label>
        <article class="nxcus-portal-preview" data-nxcus-portal-preview>
            <span><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="16" r="6"/><path d="M9 37c1.2-7.2 5-10.7 11-10.7S29.8 29.8 31 37"/><path d="M35 21.5 41 24v4.6c0 4.3-2 7.5-6 10-4-2.5-6-5.7-6-10V24z"/></svg></span>
            <div><small>PORTAL ACCESS PREVIEW</small><strong data-nxcus-portal-name>{{ $selectedPortal ? ($portalUsers->firstWhere('id', (int) $selectedPortal)?->name ?? 'Selected account') : 'No portal account linked' }}</strong><p data-nxcus-portal-email>{{ $selectedPortal ? ($portalUsers->firstWhere('id', (int) $selectedPortal)?->email ?? 'Eligible Customer-role account') : 'The customer remains internal until an eligible account is linked.' }}</p></div>
            <i data-nxcus-portal-state>{{ $selectedPortal ? 'LINKED ON SAVE' : 'INTERNAL ONLY' }}</i>
        </article>
    </div>
    <div class="nxcus-impact-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="M12 8v5M12 16h.01"/></svg><div><strong>Access impact</strong><p>Changing or removing the portal owner changes the customer-facing identity relationship. Historical projects and records remain preserved.</p></div></div>
</section>

<section id="relationship-notes" class="nxcus-form-section" data-nxcus-section>
    <header class="nxcus-form-section-head">
        <span class="nxcus-form-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
        <div><small>04 · INTERNAL RELATIONSHIP CONTEXT</small><h3>Internal Notes</h3><p>Preserve communication preferences, relationship context, or project considerations that must remain internal.</p></div>
        <span class="nxcus-internal-state">NOT CUSTOMER-FACING</span>
    </header>
    <div class="nxcus-form-grid nxcus-notes-grid"><label class="nxcus-field is-wide"><span>Internal notes <b>Optional</b></span><textarea name="notes" maxlength="5000" data-nxcus-autogrow class="@error('notes') is-invalid @enderror" placeholder="Relevant communication, preferences, governance, or project context">{{ old('notes', $customer->notes ?? '') }}</textarea><small>These notes are excluded from quotations, proposals, and customer-facing reports.</small>@error('notes')<em>{{ $message }}</em>@enderror</label></div>
</section>

<section class="nxcus-readiness" aria-label="Customer record readiness">
    <div><small>RELATIONSHIP READINESS</small><strong data-nxcus-readiness-title>Identity required</strong><p data-nxcus-readiness-copy>Complete the required customer identity controls before submission.</p></div>
    <span><b data-nxcus-readiness-score>25%</b><i><em data-nxcus-readiness-bar style="width:25%"></em></i></span>
    <ul><li data-nxcus-check="identity">Identity and type</li><li data-nxcus-check="contact">Contact channel</li><li data-nxcus-check="location">Location context</li><li data-nxcus-check="portal">Portal decision</li></ul>
</section>

<footer class="nxcus-sticky-actions">
    <div><span data-nxcus-action-state>{{ $editing ? 'Saved baseline' : 'New draft' }}</span><p data-nxcus-action-copy>{{ $editing ? 'Modify a field to prepare a controlled relationship revision.' : 'Complete the required identity fields to create the customer record.' }}</p></div>
    <div>
        <a href="{{ $editing ? route('companies.customers.show', [$company, $customer]) : route('companies.customers.index', $company) }}" class="nxcus-button nxcus-button-secondary" data-nxcus-cancel>Cancel</a>
        <button type="submit" class="nxcus-button nxcus-button-primary" data-nxcus-submit><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>{{ $editing ? 'Save Changes' : 'Create Customer' }}</button>
    </div>
</footer>
