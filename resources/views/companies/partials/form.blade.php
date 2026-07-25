@php
    $editing = isset($company);
    $currentStatus = old('status', $company->status ?? 'active');
@endphp

<section id="company-identity" class="nxcg-editor-section" data-nxcg-section data-nxcg-reveal>
    <header class="nxcg-editor-section-head">
        <div>
            <span class="nxcg-section-kicker">01 · Tenant identity</span>
            <h3>Company Identity</h3>
            <p>Define the organization name, controlled company code, and optional legal registration reference used throughout the platform.</p>
        </div>
        <span class="nxcg-section-state" data-nxcg-identity-state>Identity required</span>
    </header>

    <div class="nxcg-form-grid">
        <label class="nxcg-field">
            <span>Company name <b>*</b></span>
            <input
                id="name"
                class="@error('name') is-invalid @enderror"
                type="text"
                name="name"
                value="{{ old('name', $company->name ?? '') }}"
                maxlength="150"
                autocomplete="organization"
                data-nxcg-preview-name
                required
            >
            <small>Legal or operating name displayed across the company workspace.</small>
            @error('name')<em>{{ $message }}</em>@enderror
        </label>

        <label class="nxcg-field">
            <span>Company code <b>*</b></span>
            <input
                id="code"
                class="nxcg-mono @error('code') is-invalid @enderror"
                type="text"
                name="code"
                value="{{ old('code', $company->code ?? '') }}"
                maxlength="30"
                placeholder="NXR-ENERGY"
                autocomplete="off"
                data-nxcg-preview-code
                data-nxcg-original-code="{{ $company->code ?? '' }}"
                required
            >
            <small>Uppercase letters, numbers, hyphens, or underscores. This identifier participates in tenant references and generated workspace context.</small>
            @error('code')<em>{{ $message }}</em>@enderror
        </label>

        <label class="nxcg-field nxcg-form-span-2">
            <span>Registration number</span>
            <input
                id="registration_number"
                class="@error('registration_number') is-invalid @enderror"
                type="text"
                name="registration_number"
                value="{{ old('registration_number', $company->registration_number ?? '') }}"
                maxlength="100"
                placeholder="Optional legal registration number"
            >
            <small>Optional legal identity reference retained in the company administration record.</small>
            @error('registration_number')<em>{{ $message }}</em>@enderror
        </label>
    </div>

    @if ($editing)
        <div class="nxcg-impact-note" data-nxcg-code-impact hidden>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 18h.01"/></svg></span>
            <div><strong>Company code change detected</strong><p>Saving will regenerate the company slug when required and update future tenant references. Existing database relations remain governed by the backend identifier.</p></div>
        </div>
    @endif
</section>

<section id="company-contact" class="nxcg-editor-section" data-nxcg-section data-nxcg-reveal>
    <header class="nxcg-editor-section-head">
        <div>
            <span class="nxcg-section-kicker">02 · Contact record</span>
            <h3>Contact Information</h3>
            <p>Maintain the primary company email, phone, and operating address used for administration and company-facing communication.</p>
        </div>
        <span class="nxcg-section-state" data-nxcg-contact-state>Optional profile data</span>
    </header>

    <div class="nxcg-form-grid">
        <label class="nxcg-field">
            <span>Company email</span>
            <input
                id="email"
                class="@error('email') is-invalid @enderror"
                type="email"
                name="email"
                value="{{ old('email', $company->email ?? '') }}"
                maxlength="255"
                autocomplete="email"
                placeholder="company@example.com"
                data-nxcg-preview-email
            >
            <small>Primary operational email for this tenant.</small>
            @error('email')<em>{{ $message }}</em>@enderror
        </label>

        <label class="nxcg-field">
            <span>Company phone</span>
            <input
                id="phone"
                class="@error('phone') is-invalid @enderror"
                type="text"
                name="phone"
                value="{{ old('phone', $company->phone ?? '') }}"
                maxlength="30"
                autocomplete="tel"
                placeholder="+62 ..."
                data-nxcg-preview-phone
            >
            <small>Primary operational phone number.</small>
            @error('phone')<em>{{ $message }}</em>@enderror
        </label>

        <label class="nxcg-field nxcg-form-span-2">
            <span>Company address</span>
            <textarea
                id="address"
                class="@error('address') is-invalid @enderror"
                name="address"
                maxlength="2000"
                autocomplete="street-address"
                placeholder="Office address, city, province, and postal code"
                data-nxcg-preview-address
            >{{ old('address', $company->address ?? '') }}</textarea>
            <small>Registered office or primary operating address.</small>
            @error('address')<em>{{ $message }}</em>@enderror
        </label>
    </div>
</section>

<section id="company-access" class="nxcg-editor-section" data-nxcg-section data-nxcg-reveal>
    <header class="nxcg-editor-section-head">
        <div>
            <span class="nxcg-section-kicker">03 · Administrative governance</span>
            <h3>Workspace Access</h3>
            <p>Choose whether company-scoped workflows are available. Active memberships still remain subject to their existing roles and permissions.</p>
        </div>
        <span class="nxcg-section-state is-access" data-nxcg-access-state>{{ strtoupper($currentStatus) }}</span>
    </header>

    <div class="nxcg-status-selector" data-nxcg-status-selector>
        <label class="{{ $currentStatus === 'active' ? 'is-selected' : '' }}">
            <input type="radio" name="status" value="active" @checked($currentStatus === 'active') required>
            <span class="nxcg-status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg></span>
            <span><strong>Active workspace</strong><small>Company members can access company-scoped workflows according to assigned permissions.</small></span>
            <i></i>
        </label>
        <label class="{{ $currentStatus === 'inactive' ? 'is-selected' : '' }}">
            <input type="radio" name="status" value="inactive" @checked($currentStatus === 'inactive') required>
            <span class="nxcg-status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M8 12h8"/></svg></span>
            <span><strong>Inactive workspace</strong><small>Company access is restricted while tenant records, memberships, customers, and projects remain retained.</small></span>
            <i></i>
        </label>
    </div>

    <div class="nxcg-impact-note is-access" data-nxcg-status-impact>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 18h.01"/></svg></span>
        <div>
            <strong data-nxcg-status-impact-title>{{ $currentStatus === 'active' ? 'Workspace access will be available' : 'Workspace access will be restricted' }}</strong>
            <p data-nxcg-status-impact-copy>{{ $currentStatus === 'active' ? 'Active memberships can enter company-scoped workflows according to their assigned roles.' : 'Members will lose company-scoped access until the company is activated again.' }}</p>
        </div>
    </div>
</section>

<aside class="nxcg-editor-preview" data-nxcg-editor-preview>
    <div class="nxcg-editor-preview-copy">
        <span class="nxcg-section-kicker">Live tenant summary</span>
        <h3 data-nxcg-preview-name-output>{{ old('name', $company->name ?? '') ?: 'New company workspace' }}</h3>
        <p>Review the identity and access state before the company record is saved.</p>
    </div>
    <div class="nxcg-editor-preview-grid">
        <span><small>Company code</small><b data-nxcg-preview-code-output>{{ old('code', $company->code ?? '') ?: 'NOT ASSIGNED' }}</b></span>
        <span><small>Workspace access</small><b data-nxcg-preview-status-output>{{ ucfirst($currentStatus) }}</b></span>
        <span><small>Contact coverage</small><b data-nxcg-preview-contact-output>Not provided</b></span>
        <span><small>Form state</small><b data-nxcg-dirty-output>Saved baseline</b></span>
    </div>
</aside>

<footer class="nxcg-editor-actions" data-nxcg-editor-actions>
    <div>
        <span class="nxcg-dirty-dot" data-nxcg-dirty-dot></span>
        <p><strong data-nxcg-action-state>{{ $editing ? 'No unsaved changes' : 'New tenant draft' }}</strong><small data-nxcg-action-copy>{{ $editing ? 'The current form matches the saved company record.' : 'Complete the required identity fields to create the company workspace.' }}</small></p>
    </div>
    <span>
        <a href="{{ $editing ? route('companies.show', $company) : route('companies.index') }}" class="nxcg-button nxcg-button-secondary" data-nxcg-leave-link data-nxcg-navigate="{{ $editing ? 'open' : 'directory' }}">Cancel</a>
        <button type="submit" class="nxcg-button nxcg-button-primary" data-nxcg-submit>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
            {{ $editing ? 'Save Company Changes' : 'Create Company' }}
        </button>
    </span>
</footer>
