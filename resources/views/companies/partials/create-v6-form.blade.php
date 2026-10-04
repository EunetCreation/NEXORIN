@php
    $currentStatus = old('status', 'active');
    $currentUser = auth()->user();
@endphp

<div class="nxcg-create-workspace">
    <div class="nxcg-create-column nxcg-create-column-primary">
        <section id="company-identity" class="nxcg-create-panel" data-nxcg-section data-nxcg-reveal>
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Company Basics</span>
                    <h3>Company Identity</h3>
                    <p>Define the canonical name and unique company code used across company-scoped workflows.</p>
                </div>
                <span class="nxcg-section-check" aria-label="Identity completion"></span>
            </header>

            <div class="nxcg-create-grid">
                <label class="nxcg-field">
                    <span>Company Name <b>*</b></span>
                    <input
                        id="name"
                        class="@error('name') is-invalid @enderror"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        maxlength="150"
                        autocomplete="organization"
                        data-nxcg-preview-name
                        data-nxcg-count-source="name"
                        required
                    >
                    <small><span>Official company or organization name.</span><b data-nxcg-count="name">0 / 150</b></small>
                    @error('name')<em>{{ $message }}</em>@enderror
                </label>

                <label class="nxcg-field">
                    <span>Company Code <b>*</b></span>
                    <input
                        id="code"
                        class="nxcg-mono @error('code') is-invalid @enderror"
                        type="text"
                        name="code"
                        value="{{ old('code') }}"
                        maxlength="30"
                        pattern="[A-Z0-9][A-Z0-9_-]*"
                        title="Use uppercase letters, numbers, underscores, or hyphens."
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        data-nxcg-preview-code
                        data-nxcg-original-code=""
                        data-nxcg-count-source="code"
                        required
                    >
                    <small><span>Uppercase letters, numbers, underscores, and hyphens.</span><b data-nxcg-count="code">0 / 30</b></small>
                    @error('code')<em>{{ $message }}</em>@enderror
                </label>
            </div>

            <div class="nxcg-field-policy" aria-label="Company identity validation rules">
                <div>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg>
                    <span><strong>Server validation</strong><small>Company code uniqueness is enforced when the record is submitted.</small></span>
                </div>
                <div>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M7 4v6M17 4v6M5 11h14v9H5z"/></svg>
                    <span><strong>Stable identity</strong><small>Name and code form the basis of the generated company slug.</small></span>
                </div>
            </div>
        </section>

        <section id="company-contact" class="nxcg-create-panel" data-nxcg-section data-nxcg-reveal>
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Contact Details</span>
                    <h3>Company Contact</h3>
                    <p>Add the company-level contact details available for this record.</p>
                </div>
                <span class="nxcg-section-state" data-nxcg-contact-state>Optional</span>
            </header>

            <div class="nxcg-create-grid">
                <label class="nxcg-field">
                    <span>Company Email</span>
                    <input id="email" class="@error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" data-nxcg-preview-email>
                    <small>General business contact email.</small>
                    @error('email')<em>{{ $message }}</em>@enderror
                </label>

                <label class="nxcg-field">
                    <span>Company Phone</span>
                    <input id="phone" class="@error('phone') is-invalid @enderror" type="text" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" data-nxcg-preview-phone>
                    <small>Primary company telephone number.</small>
                    @error('phone')<em>{{ $message }}</em>@enderror
                </label>

                <label class="nxcg-field nxcg-create-span-2">
                    <span>Company Address</span>
                    <textarea id="address" class="@error('address') is-invalid @enderror" name="address" maxlength="2000" autocomplete="street-address" data-nxcg-preview-address data-nxcg-count-source="address">{{ old('address') }}</textarea>
                    <small><span>Registered or primary operating address.</span><b data-nxcg-count="address">0 / 2000</b></small>
                    @error('address')<em>{{ $message }}</em>@enderror
                </label>
            </div>
        </section>

        <section class="nxcg-create-panel nxcg-create-scope" data-nxcg-reveal aria-labelledby="company-scope-title">
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Company Scope</span>
                    <h3 id="company-scope-title">Owned Data Domains</h3>
                    <p>The company record is the ownership boundary for these existing NEXORIN data relationships.</p>
                </div>
                <span class="nxcg-section-state">Model scope</span>
            </header>
            <div class="nxcg-domain-grid">
                <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M18 8v6M15 11h6"/></svg><span><strong>Customers</strong><small>Company-owned customer records</small></span></div>
                <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M3 19h18"/></svg><span><strong>Projects</strong><small>Company-scoped project portfolio</small></span></div>
                <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M13 2 5 14h6l-1 8 8-12h-6z"/></svg><span><strong>Load Analysis</strong><small>Engineering demand records</small></span></div>
                <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 4h12a2 2 0 0 1 2 2v12l-4-2-4 2-4-2-4 2V6a2 2 0 0 1 2-2z"/><path d="M8 8h8M8 12h8"/></svg><span><strong>Material Catalog</strong><small>Company material definitions</small></span></div>
                <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h16M6 7V4h12v3M6 11h12v9H6z"/><path d="M9 14h6"/></svg><span><strong>Suppliers</strong><small>Company supplier records</small></span></div>
                <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg><span><strong>Price Books</strong><small>Company pricing references</small></span></div>
            </div>
            <footer class="nxcg-panel-note">No domain record is generated automatically; these relationships become available after the company exists.</footer>
        </section>

        <section class="nxcg-create-panel nxcg-create-flow-panel" data-nxcg-reveal aria-labelledby="company-create-flow-title">
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Creation Flow</span>
                    <h3 id="company-create-flow-title">Record Provisioning Path</h3>
                    <p>What the existing backend creates immediately after this company record is submitted.</p>
                </div>
                <span class="nxcg-section-state">Server workflow</span>
            </header>
            <div class="nxcg-flow-list">
                <div><b>01</b><span><strong>Create company record</strong><small>Persist the canonical company identity, contact data, and initial status.</small></span></div>
                <div><b>02</b><span><strong>Generate slug</strong><small>Build a unique company slug from Company Name and Company Code on the server.</small></span></div>
                <div><b>03</b><span><strong>Store creator attribution</strong><small>Attach the authenticated Super Admin as the company creator inside the transaction.</small></span></div>
                <div><b>04</b><span><strong>Open company profile</strong><small>Redirect to the created company so membership and downstream setup can continue.</small></span></div>
            </div>
        </section>

        <section class="nxcg-create-panel nxcg-create-rules-panel" data-nxcg-reveal aria-labelledby="company-create-rules-title">
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Record Rules</span>
                    <h3 id="company-create-rules-title">Persistence Contract</h3>
                    <p>Only fields and state transitions supported by the current backend are captured during company creation.</p>
                </div>
                <span class="nxcg-section-state">Backend truth</span>
            </header>
            <div class="nxcg-rules-list">
                <div><i></i><span><strong>Required at creation</strong><small>Company Name, Company Code, and Workspace Status are the minimum fields enforced for submission.</small></span></div>
                <div><i></i><span><strong>Optional metadata</strong><small>Registration Number, Email, Phone, and Address enrich the company profile without blocking creation.</small></span></div>
                <div><i></i><span><strong>Status-gated access</strong><small>Inactive companies remain recorded, but company-scoped operational access stays restricted.</small></span></div>
                <div><i></i><span><strong>No automatic membership</strong><small>Users and company roles are provisioned later through the existing membership workflow.</small></span></div>
            </div>
        </section>
    </div>

    <div class="nxcg-create-column nxcg-create-column-secondary">
        <section class="nxcg-create-preview-card" data-nxcg-reveal aria-labelledby="company-record-preview-title">
            <header>
                <div>
                    <span id="company-record-preview-title">Company Record Preview</span>
                    <small>Live form data</small>
                </div>
                <span class="nxcg-preview-state" data-nxcg-preview-state>Identity incomplete</span>
            </header>

            <div class="nxcg-record-preview-intro">
                <div class="nxcg-record-preview-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 21V7l8-4 8 4v14M8 10h2M14 10h2M8 14h2M14 14h2M8 18h8"/></svg>
                </div>
                <div>
                    <span>Company record</span>
                    <strong data-nxcg-preview-name-output>Not provided</strong>
                    <small class="nxcg-mono" data-nxcg-preview-code-output>Company code not provided</small>
                </div>
            </div>

            <dl class="nxcg-create-preview-meta">
                <div><dt>Registration</dt><dd data-nxcg-create-preview-registration>Not provided</dd></div>
                <div><dt>Email</dt><dd data-nxcg-create-preview-email>Not provided</dd></div>
                <div><dt>Phone</dt><dd data-nxcg-create-preview-phone>Not provided</dd></div>
                <div class="is-address"><dt>Address</dt><dd data-nxcg-create-preview-address>Not provided</dd></div>
                <div><dt>Workspace</dt><dd><span class="nxcg-preview-status" data-nxcg-preview-status-output>{{ ucfirst($currentStatus) }}</span></dd></div>
            </dl>

            <footer>
                <span>Record owner</span>
                <strong>{{ $currentUser?->name ?? 'Authenticated administrator' }}</strong>
                <small>Created by the submitting Super Admin</small>
            </footer>
        </section>

        <section id="company-legal" class="nxcg-create-panel" data-nxcg-section data-nxcg-reveal>
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Legal Registration</span>
                    <h3>Registration Record</h3>
                    <p>Store the legal registration reference when it is available.</p>
                </div>
                <span class="nxcg-section-state">Optional</span>
            </header>

            <div class="nxcg-create-grid nxcg-create-grid-single">
                <label class="nxcg-field">
                    <span>Registration Number</span>
                    <input id="registration_number" class="@error('registration_number') is-invalid @enderror" type="text" name="registration_number" value="{{ old('registration_number') }}" maxlength="100" data-nxcg-create-registration data-nxcg-count-source="registration">
                    <small><span>Company registration identifier supported by the current record.</span><b data-nxcg-count="registration">0 / 100</b></small>
                    @error('registration_number')<em>{{ $message }}</em>@enderror
                </label>
            </div>
        </section>

        <section id="company-access" class="nxcg-create-panel" data-nxcg-section data-nxcg-reveal>
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Workspace Access</span>
                    <h3>Initial Access State</h3>
                    <p>Choose the status persisted on the company record at creation.</p>
                </div>
                <span class="nxcg-section-state is-access" data-nxcg-access-state>{{ strtoupper($currentStatus) }}</span>
            </header>

            <div class="nxcg-status-selector nxcg-create-status-selector" data-nxcg-status-selector>
                <label class="{{ $currentStatus === 'active' ? 'is-selected' : '' }}">
                    <input type="radio" name="status" value="active" @checked($currentStatus === 'active') required>
                    <span class="nxcg-status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg></span>
                    <span><strong>Active Workspace</strong><small>Company-scoped workflows can be used by authorized active members after membership is assigned.</small></span>
                    <i></i>
                </label>
                <label class="{{ $currentStatus === 'inactive' ? 'is-selected' : '' }}">
                    <input type="radio" name="status" value="inactive" @checked($currentStatus === 'inactive') required>
                    <span class="nxcg-status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M8 12h8"/></svg></span>
                    <span><strong>Inactive Workspace</strong><small>The record remains available while company-scoped access is restricted.</small></span>
                    <i></i>
                </label>
            </div>
        </section>

        <section class="nxcg-create-panel nxcg-create-lifecycle" data-nxcg-reveal aria-labelledby="company-lifecycle-title">
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Lifecycle Controls</span>
                    <h3 id="company-lifecycle-title">Record Governance</h3>
                    <p>Creation and archival behavior enforced by the current company workflow.</p>
                </div>
            </header>
            <div class="nxcg-control-list">
                <div><i></i><span><strong>Unique slug</strong><small>Generated server-side from Company Name and Company Code.</small></span></div>
                <div><i></i><span><strong>Creator attribution</strong><small>The authenticated Super Admin is stored as the record creator.</small></span></div>
                <div><i></i><span><strong>Membership separation</strong><small>Company memberships and roles are assigned independently after creation.</small></span></div>
                <div><i></i><span><strong>Controlled archive</strong><small>An active company must be deactivated before it can be soft archived.</small></span></div>
            </div>
        </section>

        <section class="nxcg-create-panel nxcg-create-onboarding-panel" data-nxcg-reveal aria-labelledby="company-onboarding-title">
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Operational Handoff</span>
                    <h3 id="company-onboarding-title">Workspace Enablement</h3>
                    <p>Company access becomes operational through the existing membership and permission flow.</p>
                </div>
                <span class="nxcg-section-state">Post-create</span>
            </header>
            <div class="nxcg-onboarding-list">
                <div>
                    <i>1</i>
                    <span><strong>Assign members</strong><small>Add users to the company through the platform user-management workflow.</small></span>
                </div>
                <div>
                    <i>2</i>
                    <span><strong>Apply company role</strong><small>Grant the company-scoped role that controls access to each operational module.</small></span>
                </div>
                <div>
                    <i>3</i>
                    <span><strong>Select workspace</strong><small>Authorized users resolve the active company context before entering company routes.</small></span>
                </div>
                <div>
                    <i>4</i>
                    <span><strong>Use company modules</strong><small>Customers, Projects, Load Analysis, Catalog, Suppliers, and Price Books follow that context.</small></span>
                </div>
            </div>
        </section>

    </div>

    <aside class="nxcg-create-column nxcg-create-column-rail">
        <section class="nxcg-create-readiness-card" data-nxcg-reveal>
            <header><span>Company Readiness</span><small>Required fields</small></header>
            <div class="nxcg-create-readiness-meter">
                <div class="nxcg-create-gauge" data-nxcg-create-ring style="--nxcg-progress:33">
                    <div class="nxcg-create-gauge-value">
                        <strong data-nxcg-create-percent>33%</strong>
                        <span>Complete</span>
                    </div>
                </div>
                <div class="nxcg-create-readiness-copy">
                    <strong data-nxcg-create-required>1 of 3 required</strong>
                    <span>Required: Company Name, Company Code, and Workspace Status.</span>
                </div>
            </div>

            <div class="nxcg-create-readiness-list">
                <div data-nxcg-readiness="identity"><i></i><span>Company Basics</span><b data-nxcg-readiness-identity>0 / 2</b></div>
                <div data-nxcg-readiness="contact"><i></i><span>Contact Details</span><b data-nxcg-readiness-contact>0 / 3</b></div>
                <div data-nxcg-readiness="legal"><i></i><span>Legal Registration</span><b data-nxcg-readiness-legal>0 / 1</b></div>
                <div data-nxcg-readiness="access" class="is-complete"><i></i><span>Workspace Access</span><b>1 / 1</b></div>
            </div>

            <div class="nxcg-create-next-step">
                <small>Next step</small>
                <strong data-nxcg-next-title>Complete the company identity</strong>
                <a href="#company-identity" data-nxcg-scroll data-nxcg-next-link>Go to Company Basics →</a>
            </div>
        </section>

        <section id="company-review" class="nxcg-create-panel nxcg-create-review-side" data-nxcg-section data-nxcg-reveal>
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Review & Create</span>
                    <h3>Submission Control</h3>
                    <p>Verify the required data before creating the company record.</p>
                </div>
            </header>

            <div class="nxcg-create-review-checks">
                <div data-nxcg-check="name"><span></span><p><strong>Company Name</strong><small>Required</small></p></div>
                <div data-nxcg-check="code"><span></span><p><strong>Company Code</strong><small>Required and unique</small></p></div>
                <div data-nxcg-check="status" class="is-complete"><span></span><p><strong>Workspace Status</strong><small>Required</small></p></div>
                <div class="is-server"><span></span><p><strong>Authorization</strong><small>Super Admin</small></p></div>
            </div>

            <div class="nxcg-create-auth-context">
                <span>Submitting as</span>
                <strong>{{ $currentUser?->name ?? 'Authenticated administrator' }}</strong>
                <small>{{ $currentUser?->email ?? 'Super Admin authorization required' }}</small>
            </div>

            <div class="nxcg-create-review-action">
                <strong data-nxcg-action-state>Company identity incomplete</strong>
                <small data-nxcg-action-copy>Complete Company Name and Company Code before submitting.</small>
                <button type="submit" class="nxcg-button nxcg-button-primary" data-nxcg-submit>
                    Create Company
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </button>
            </div>
        </section>

        <section class="nxcg-create-panel nxcg-create-postcreate-panel" data-nxcg-reveal aria-labelledby="company-postcreate-title">
            <header class="nxcg-create-panel-head">
                <div>
                    <span class="nxcg-section-kicker">After Creation</span>
                    <h3 id="company-postcreate-title">Immediate Follow-up</h3>
                    <p>Operational actions that normally follow once the company record has been created successfully.</p>
                </div>
                <span class="nxcg-section-state">Continuation</span>
            </header>
            <div class="nxcg-rail-followup">
                <div><b>1</b><span><strong>Review company profile</strong><small>Confirm the newly created company profile and status after the redirect completes.</small></span></div>
                <div><b>2</b><span><strong>Assign memberships</strong><small>Link users to the company through the existing user-management and membership workflow.</small></span></div>
                <div><b>3</b><span><strong>Grant company roles</strong><small>Apply role-based access so operational modules become available in the active workspace.</small></span></div>
                <div><b>4</b><span><strong>Start module setup</strong><small>Continue with Customers, Projects, Load Analysis, Catalog, Suppliers, and Price Books as needed.</small></span></div>
            </div>
        </section>

    </aside>
</div>

<section class="nxcg-create-summary" data-nxcg-reveal aria-labelledby="company-validation-summary-title">
    <header class="nxcg-create-panel-head">
        <div>
            <span class="nxcg-section-kicker">Validation Summary</span>
            <h3 id="company-validation-summary-title">Record Completion</h3>
            <p>Completion reflects the fields supported by this company record. Server authorization and validation remain authoritative.</p>
        </div>
        <span class="nxcg-section-state">3 required fields</span>
    </header>

    <div class="nxcg-create-summary-table">
        <div class="nxcg-summary-row nxcg-summary-head">
            <span>Section</span><span>Requirement</span><span>Completion</span><span>Fields</span><span>Status</span>
        </div>
        <div class="nxcg-summary-row" data-nxcg-summary="identity">
            <span>Company Basics</span><span>Required</span><span><i style="--completion:0"></i><b>0%</b></span><span data-nxcg-summary-count="identity">0 / 2</span><span data-nxcg-summary-status="identity">Incomplete</span>
        </div>
        <div class="nxcg-summary-row" data-nxcg-summary="contact">
            <span>Contact Details</span><span>Optional</span><span><i style="--completion:0"></i><b>0%</b></span><span data-nxcg-summary-count="contact">0 / 3</span><span data-nxcg-summary-status="contact">Not started</span>
        </div>
        <div class="nxcg-summary-row" data-nxcg-summary="legal">
            <span>Legal Registration</span><span>Optional</span><span><i style="--completion:0"></i><b>0%</b></span><span data-nxcg-summary-count="legal">0 / 1</span><span data-nxcg-summary-status="legal">Not started</span>
        </div>
        <div class="nxcg-summary-row is-complete" data-nxcg-summary="access">
            <span>Workspace Access</span><span>Required</span><span><i style="--completion:100"></i><b>100%</b></span><span data-nxcg-summary-count="access">1 / 1</span><span data-nxcg-summary-status="access">Complete</span>
        </div>
        <div class="nxcg-summary-row is-complete nxcg-summary-static">
            <span>Submission Authorization</span><span>Required</span><span><i style="--completion:100"></i><b>100%</b></span><span>1 / 1</span><span>Authorized</span>
        </div>
    </div>

    <div class="nxcg-summary-overall">
        <span>Required-field readiness: <strong data-nxcg-summary-overall>33%</strong></span>
        <i><b data-nxcg-summary-overall-bar style="--completion:33"></b></i>
        <small>Company creation remains subject to server-side authorization, uniqueness checks, and validation.</small>
    </div>
</section>
