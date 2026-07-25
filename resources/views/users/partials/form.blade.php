@php
    $editing = isset($user);
    $isSelf = $editing && auth()->id() === $user->id;
    $defaultSuperAdmin = $editing ? $user->systemRoles->contains('code', 'super_admin') : false;
    $isSuperAdmin = (bool) old('is_super_admin', $defaultSuperAdmin);
    $membershipRows = old('memberships');
    if ($membershipRows === null && $editing) {
        $membershipRows = $user->companyMemberships->map(fn ($membership) => [
            'company_id' => $membership->company_id,
            'role_id' => $membership->roles->first()?->id,
            'status' => $membership->status,
        ])->values()->all();
    }
    if ($membershipRows === null || $membershipRows === []) {
        $membershipRows = [['company_id' => '', 'role_id' => '', 'status' => 'active']];
    }
    $selectedStatus = old('status', $user->status ?? 'active');
    $emailVerified = $editing && (bool) $user->email_verified_at;
    $mfaEnabled = $editing && (bool) $user->two_factor_confirmed_at;
    $securityScore = ($emailVerified ? 1 : 0) + ($mfaEnabled ? 1 : 0);
    $configuredMemberships = collect($membershipRows)->filter(fn ($membership) => !empty($membership['company_id']) && !empty($membership['role_id']) && ($membership['status'] ?? 'active') === 'active')->count();
    $previewAccountId = $editing ? 'USR-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT) : 'NEW IDENTITY';
@endphp

@if($errors->any())
    <div class="nx-feedback nx-feedback--danger" role="alert" data-alert>
        <span class="nx-feedback-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
        <div class="nx-feedback-body"><span class="nx-feedback-kicker">Validation control</span><strong class="nx-feedback-title">Review the highlighted user settings</strong><p class="nx-feedback-copy">{{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} require attention before this access record can be saved.</p></div>
        <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7 7 10 10M17 7 7 17"/></svg></button>
    </div>
@endif

<header class="nxug-editor-hero">
    <div class="nxug-editor-hero-copy">
        <span class="nxug-kicker nxug-kicker--light"><i></i>{{ $editing ? 'Controlled identity revision' : 'New identity and access record' }}</span>
        <h2>{{ $editing ? 'Edit ' . $user->name : 'Create User' }}</h2>
        <p>{{ $editing ? 'Update identity, credentials, platform privilege, and company memberships through a controlled administration workflow.' : 'Create a NEXORIN identity, establish authentication credentials, and assign the correct platform and company access.' }}</p>
    </div>
    <aside class="nxug-editor-preview {{ $isSuperAdmin ? 'is-super' : '' }}" data-nxug-editor-preview>
        <div class="nxug-editor-preview-head">
            <span class="nxug-editor-preview-avatar" aria-hidden="true">
                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="27" cy="22" r="8"/>
                    <path d="M12 48c1.6-9.4 7-14 15-14s13.4 4.6 15 14"/>
                    <path d="M47 27.5 55 31v6.2c0 5.8-2.7 10-8 13.3-5.3-3.3-8-7.5-8-13.3V31z"/>
                    <path d="m43.8 38 2.2 2.2 4.5-5"/>
                </svg>
            </span>
            <div class="nxug-editor-preview-identity">
                <div class="nxug-editor-preview-label"><small>IDENTITY PREVIEW</small><span data-nxug-preview-dirty>{{ $editing ? 'SAVED BASELINE' : 'NEW DRAFT' }}</span></div>
                <strong data-nxug-preview-name>{{ old('name', $user->name ?? 'New user account') }}</strong>
                <span data-nxug-preview-email>{{ old('email', $user->email ?? 'Email not assigned') }}</span>
                <em>{{ $previewAccountId }}</em>
            </div>
        </div>
        <div class="nxug-editor-preview-meta">
            <span><small>ACCOUNT</small><strong data-nxug-preview-status>{{ ucfirst($selectedStatus) }}</strong></span>
            <span class="nxug-preview-authority"><small>AUTHORITY</small><strong data-nxug-preview-privilege>{{ $isSuperAdmin ? 'Super Admin' : 'Company-scoped' }}</strong></span>
            <span class="{{ $securityScore === 2 ? 'is-passed' : 'is-warning' }}"><small>SECURITY</small><strong data-nxug-preview-security>{{ $securityScore }} of 2 enabled</strong></span>
            <span><small>MEMBERSHIPS</small><strong data-nxug-preview-memberships>{{ $configuredMemberships }} active</strong></span>
        </div>
    </aside>
</header>

<nav class="nxug-editor-nav" aria-label="User editor sections" data-nxug-section-nav>
    <a href="#identity" class="is-active">Identity</a><a href="#security">Security &amp; Credentials</a><a href="#platform-access">Platform Privilege</a><a href="#company-access">Company Memberships</a>
</nav>

<div class="nxug-editor-layout">
    <main class="nxug-editor-main">
        <section id="identity" class="nxug-form-section" data-nxug-section>
            <header><span>01</span><div><h3>Identity</h3><p>Define the primary account identity used across all platform and company contexts.</p></div><strong data-nxug-identity-state>Identity required</strong></header>
            <div class="nxug-form-grid">
                <div class="nxug-form-field"><label for="name">Full name</label><input id="name" type="text" name="name" value="{{ old('name', $user->name ?? '') }}" maxlength="255" autocomplete="name" required class="@error('name') is-invalid @enderror" data-nxug-name>@error('name')<span class="nxug-error">{{ $message }}</span>@enderror</div>
                <div class="nxug-form-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $user->email ?? '') }}" maxlength="255" autocomplete="email" required class="@error('email') is-invalid @enderror" data-nxug-email>@error('email')<span class="nxug-error">{{ $message }}</span>@enderror</div>
            </div>
        </section>

        <section id="security" class="nxug-form-section" data-nxug-section>
            <header><span>02</span><div><h3>Security &amp; Credentials</h3><p>{{ $editing ? 'Leave both password fields blank to retain the current credential.' : 'Create a temporary credential that satisfies the platform password policy.' }}</p></div><strong data-nxug-security-state>{{ $editing ? 'Credential optional' : 'Credential required' }}</strong></header>
            <div class="nxug-form-grid">
                <div class="nxug-form-field nxug-password-field">
                    <label for="password">{{ $editing ? 'New password' : 'Temporary password' }}</label>
                    <div class="nxug-input-action"><input id="password" type="password" name="password" autocomplete="new-password" {{ $editing ? '' : 'required' }} class="@error('password') is-invalid @enderror" data-nxug-password><button type="button" aria-label="Show password" data-nxug-password-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>
                    @error('password')<span class="nxug-error">{{ $message }}</span>@enderror
                </div>
                <div class="nxug-form-field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" {{ $editing ? '' : 'required' }} data-nxug-password-confirm></div>
            </div>
            <div class="nxug-password-policy" data-nxug-password-policy>
                <span data-rule="length">10+ characters</span><span data-rule="upper">Uppercase</span><span data-rule="lower">Lowercase</span><span data-rule="number">Number</span><span data-rule="symbol">Symbol</span><span data-rule="match">Passwords match</span>
            </div>
        </section>

        <section id="platform-access" class="nxug-form-section" data-nxug-section>
            <header><span>03</span><div><h3>Platform Privilege</h3><p>Separate account lifecycle from system-wide authority. Super Admin access bypasses company membership boundaries.</p></div><strong data-nxug-access-state>{{ $isSuperAdmin ? 'Platform-wide' : 'Company-scoped' }}</strong></header>
            <div class="nxug-access-grid">
                <div class="nxug-status-selector">
                    <span class="nxug-field-label">Account status</span>
                    @if($isSelf)
                        <input type="hidden" name="status" value="active">
                        <label class="nxug-choice-card is-selected is-locked"><input type="radio" checked disabled><span><strong>Active</strong><small>Your current account is protected from self-deactivation.</small></span></label>
                    @else
                        <label class="nxug-choice-card {{ $selectedStatus === 'active' ? 'is-selected' : '' }}"><input type="radio" name="status" value="active" @checked($selectedStatus === 'active')><span><strong>Active</strong><small>Sign-in and assigned company access are permitted.</small></span></label>
                        <label class="nxug-choice-card nxug-choice-card--warning {{ $selectedStatus === 'inactive' ? 'is-selected' : '' }}"><input type="radio" name="status" value="inactive" @checked($selectedStatus === 'inactive')><span><strong>Inactive</strong><small>Sign-in and authenticated requests are blocked; records remain retained.</small></span></label>
                    @endif
                    @error('status')<span class="nxug-error">{{ $message }}</span>@enderror
                </div>
                <div class="nxug-super-admin-card {{ $isSuperAdmin ? 'is-selected' : '' }} {{ $isSelf ? 'is-locked' : '' }}">
                    <input type="hidden" name="is_super_admin" value="0">
                    <label>
                        <input type="checkbox" name="is_super_admin" value="1" @checked($isSuperAdmin) @disabled($isSelf) data-nxug-super-admin>
                        @if($isSelf)<input type="hidden" name="is_super_admin" value="1">@endif
                        <span class="nxug-super-toggle" aria-hidden="true"></span>
                        <span class="nxug-super-copy"><small>PLATFORM-WIDE PRIVILEGE</small><strong>Super Admin</strong><p>Unrestricted administration across every NEXORIN company. Membership assignments remain available for audit traceability.</p></span>
                    </label>
                    <div class="nxug-privilege-impact"><span>Authority</span><strong>{{ $isSuperAdmin ? 'All companies' : 'Membership-defined' }}</strong><span>Safeguard</span><strong>{{ $isSelf ? 'Current account locked' : 'Confirmation required on save' }}</strong></div>
                    @error('is_super_admin')<span class="nxug-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section id="company-access" class="nxug-form-section" data-nxug-section>
            <header><span>04</span><div><h3>Company Memberships</h3><p>Assign at most one membership and one effective company role for each tenant workspace.</p></div><button type="button" class="nxug-add-membership" data-add-membership><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 5v14M5 12h14"/></svg>Add company access</button></header>
            @if($errors->has('memberships'))<span class="nxug-error nxug-error--section">{{ $errors->first('memberships') }}</span>@endif
            <div class="nxug-membership-editor" data-membership-container data-next-index="{{ count($membershipRows) }}">
                @foreach($membershipRows as $index => $membership)
                    <article class="nxug-membership-editor-row" data-membership-row>
                        <div class="nxug-membership-editor-head"><div><span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><strong>Company access assignment</strong><small data-nxug-membership-summary>Configure workspace, role and lifecycle state.</small></div><button type="button" aria-label="Remove company assignment" data-remove-membership><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14"/></svg></button></div>
                        <div class="nxug-membership-editor-fields">
                            <div class="nxug-form-field"><label for="membership-company-{{ $index }}">Company</label><select id="membership-company-{{ $index }}" name="memberships[{{ $index }}][company_id]" class="@error("memberships.$index.company_id") is-invalid @enderror" data-membership-company><option value="">Select company</option>@foreach($companies as $companyOption)<option value="{{ $companyOption->id }}" @selected((string)($membership['company_id'] ?? '') === (string)$companyOption->id)>{{ $companyOption->name }} · {{ $companyOption->code }}{{ $companyOption->status === 'inactive' ? ' · Inactive workspace' : '' }}</option>@endforeach</select>@error("memberships.$index.company_id")<span class="nxug-error">{{ $message }}</span>@enderror</div>
                            <div class="nxug-form-field"><label for="membership-role-{{ $index }}">Company role</label><select id="membership-role-{{ $index }}" name="memberships[{{ $index }}][role_id]" class="@error("memberships.$index.role_id") is-invalid @enderror" data-membership-role><option value="">Select role</option>@foreach($companyRoles as $roleOption)<option value="{{ $roleOption->id }}" @selected((string)($membership['role_id'] ?? '') === (string)$roleOption->id)>{{ $roleOption->name }}</option>@endforeach</select>@error("memberships.$index.role_id")<span class="nxug-error">{{ $message }}</span>@enderror</div>
                            <div class="nxug-form-field"><label for="membership-status-{{ $index }}">Membership status</label><select id="membership-status-{{ $index }}" name="memberships[{{ $index }}][status]" class="@error("memberships.$index.status") is-invalid @enderror" data-membership-status><option value="active" @selected(($membership['status'] ?? 'active') === 'active')>Active</option><option value="inactive" @selected(($membership['status'] ?? 'active') === 'inactive')>Inactive</option></select>@error("memberships.$index.status")<span class="nxug-error">{{ $message }}</span>@enderror</div>
                        </div>
                    </article>
                @endforeach
            </div>
            <template data-membership-template>
                <article class="nxug-membership-editor-row" data-membership-row>
                    <div class="nxug-membership-editor-head"><div><span data-membership-number></span><strong>Company access assignment</strong><small data-nxug-membership-summary>Configure workspace, role and lifecycle state.</small></div><button type="button" aria-label="Remove company assignment" data-remove-membership><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14"/></svg></button></div>
                    <div class="nxug-membership-editor-fields">
                        <div class="nxug-form-field"><label for="membership-company-__INDEX__">Company</label><select id="membership-company-__INDEX__" name="memberships[__INDEX__][company_id]" data-membership-company><option value="">Select company</option>@foreach($companies as $companyOption)<option value="{{ $companyOption->id }}">{{ $companyOption->name }} · {{ $companyOption->code }}{{ $companyOption->status === 'inactive' ? ' · Inactive workspace' : '' }}</option>@endforeach</select></div>
                        <div class="nxug-form-field"><label for="membership-role-__INDEX__">Company role</label><select id="membership-role-__INDEX__" name="memberships[__INDEX__][role_id]" data-membership-role><option value="">Select role</option>@foreach($companyRoles as $roleOption)<option value="{{ $roleOption->id }}">{{ $roleOption->name }}</option>@endforeach</select></div>
                        <div class="nxug-form-field"><label for="membership-status-__INDEX__">Membership status</label><select id="membership-status-__INDEX__" name="memberships[__INDEX__][status]" data-membership-status><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    </div>
                </article>
            </template>
        </section>
    </main>

    <aside class="nxug-editor-rail">
        <article class="nxug-readiness-card"><span class="nxug-kicker"><i></i>Access record readiness</span><strong data-nxug-readiness-score>0%</strong><p data-nxug-readiness-copy>Complete the required identity and credential fields.</p><div><i data-nxug-readiness-bar></i></div><ul><li data-nxug-check="identity">Identity complete</li><li data-nxug-check="credential">Credential policy satisfied</li><li data-nxug-check="access">Account lifecycle selected</li><li data-nxug-check="membership">Membership configuration reviewed</li></ul></article>
        <article class="nxug-impact-card"><small>CONTROLLED IMPACT</small><h4 data-nxug-impact-title>{{ $editing ? 'Review pending changes' : 'New account provisioning' }}</h4><p data-nxug-impact-copy>{{ $editing ? 'Sensitive privilege, lifecycle and password changes require explicit confirmation before they are committed.' : 'The account will be created with the selected lifecycle, privilege and membership configuration.' }}</p><div><span>Form state</span><strong data-nxug-dirty-state>{{ $editing ? 'Saved baseline' : 'New draft' }}</strong></div></article>
    </aside>
</div>

<footer class="nxug-editor-actions" data-nxug-editor-actions>
    <div><strong data-nxug-action-state>{{ $editing ? 'No pending changes' : 'New account draft' }}</strong><span data-nxug-action-copy>{{ $editing ? 'Modify a field to prepare a controlled user revision.' : 'Complete the required fields before creating the account.' }}</span></div>
    <div><a href="{{ $editing ? route('users.show', $user) : route('users.index') }}" class="nxug-cancel" data-nxug-cancel>Cancel</a><button type="submit" class="nxug-save" data-nxug-submit>{{ $editing ? 'Save Changes' : 'Create User' }}</button></div>
</footer>
