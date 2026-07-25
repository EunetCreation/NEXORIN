@php
    $selectedCustomerId = old(
        'customer_id',
        request()->query('customer_id', ($ownedCustomer ?? null)?->id ?? '')
    );

    $selectedCustomer = $canCreateCompanyProject
        ? $customers->first(fn ($customerOption) => (string) $customerOption->id === (string) $selectedCustomerId)
        : ($ownedCustomer ?? null);

    $hasConfirmedBuildingType = session()->hasOldInput('building_type');
    $hasConfirmedSystem = session()->hasOldInput('proposed_system');
    $selectedBuildingType = old('building_type', 'residential');
    $selectedSystem = old('proposed_system', 'undecided');
    $requiredFieldIds = ['customer_id', 'name', 'building_type', 'proposed_system', 'site_address'];
@endphp

<div class="project-intake-main">
    <nav class="project-intake-section-nav" aria-label="Project intake sections" data-intake-section-nav>
        <a href="#project-identity" class="is-active" data-intake-nav="project-identity">
            <span>01</span>
            Identity
        </a>
        <a href="#project-location" data-intake-nav="project-location">
            <span>02</span>
            Site &amp; location
        </a>
        <a href="#project-team" data-intake-nav="project-team">
            <span>03</span>
            Team
        </a>
        <a href="#project-notes" data-intake-nav="project-notes">
            <span>04</span>
            Notes
        </a>
    </nav>

    <section id="project-identity" class="project-intake-card" data-intake-section data-reveal>
        <header class="project-intake-card-header">
            <span class="project-intake-section-number">01</span>
            <span class="project-intake-section-icon tone-teal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M4 21V6l8-3 8 3v15"/>
                    <path d="M8 10h2M14 10h2M8 14h2M14 14h2M10 21v-4h4v4"/>
                </svg>
            </span>
            <div>
                <span>Project identity</span>
                <h3>Define the commercial brief</h3>
                <p>Connect the project to a customer and establish its initial technical direction.</p>
            </div>
            <span class="project-intake-section-state" data-section-state="project-identity">Required</span>
        </header>

        <div class="project-intake-card-body">
            <div class="project-intake-field project-intake-span-2">
                <label for="customer_id">
                    Customer
                    <span aria-hidden="true">*</span>
                </label>

                @if ($canCreateCompanyProject)
                    <div class="project-intake-control-wrap">
                        <span class="project-intake-control-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="9" cy="8" r="4"/>
                                <path d="M2.5 21a6.5 6.5 0 0 1 13 0M17 11h4M19 9v4"/>
                            </svg>
                        </span>
                        <select
                            id="customer_id"
                            class="project-intake-select @error('customer_id') is-invalid @enderror"
                            name="customer_id"
                            required
                            data-summary-source="customer"
                            data-initial-value="{{ $selectedCustomerId }}"
                        >
                            <option value="">Select an active customer</option>
                            @foreach ($customers as $customerOption)
                                <option
                                    value="{{ $customerOption->id }}"
                                    data-name="{{ $customerOption->name }}"
                                    data-number="{{ $customerOption->customer_number }}"
                                    data-type="{{ $customerOption->type_label }}"
                                    data-location="{{ collect([$customerOption->city, $customerOption->province])->filter()->join(', ') }}"
                                    data-address="{{ $customerOption->address }}"
                                    data-postal="{{ $customerOption->postal_code }}"
                                    @selected((string) $selectedCustomerId === (string) $customerOption->id)
                                >
                                    {{ $customerOption->customer_number }} · {{ $customerOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <small class="project-intake-help">Only active customers in {{ $company->name }} are available.</small>
                @else
                    <input type="hidden" id="customer_id" name="customer_id" value="{{ $ownedCustomer->id }}" data-summary-source="customer">
                    <div
                        class="project-intake-locked-customer"
                        data-customer-name="{{ $ownedCustomer->name }}"
                        data-customer-number="{{ $ownedCustomer->customer_number }}"
                    >
                        <span class="project-intake-locked-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>
                            </svg>
                        </span>
                        <span>
                            <small>Customer portal owner</small>
                            <strong>{{ $ownedCustomer->name }}</strong>
                            <em>{{ $ownedCustomer->customer_number }}</em>
                        </span>
                        <span class="project-intake-lock-badge">Locked</span>
                    </div>
                @endif

                @error('customer_id')<div class="project-intake-error">{{ $message }}</div>@enderror
            </div>

            <div
                class="project-intake-customer-preview project-intake-span-2"
                data-customer-preview
                @if (! $selectedCustomer) hidden @endif
            >
                <span class="project-intake-customer-preview-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 21V6l8-3 8 3v15"/>
                        <path d="M8 10h2M14 10h2M8 14h2M14 14h2M10 21v-4h4v4"/>
                    </svg>
                </span>
                <span class="project-intake-customer-preview-copy">
                    <small>Selected customer</small>
                    <strong data-customer-preview-name>{{ $selectedCustomer?->name ?? 'Customer name' }}</strong>
                    <em data-customer-preview-meta>
                        {{ $selectedCustomer ? collect([$selectedCustomer->customer_number, $selectedCustomer->type_label])->filter()->join(' · ') : 'Customer profile' }}
                    </em>
                </span>
                <span class="project-intake-customer-preview-location" data-customer-preview-location>
                    {{ $selectedCustomer ? collect([$selectedCustomer->city, $selectedCustomer->province])->filter()->join(', ') : '' }}
                </span>
            </div>

            <div class="project-intake-field project-intake-span-2">
                <label for="name">
                    Project name
                    <span aria-hidden="true">*</span>
                </label>
                <div class="project-intake-control-wrap">
                    <span class="project-intake-control-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 15h16l-2-8H6l-2 8z"/>
                            <path d="M8 7l-1 8M12 7v8M16 7l1 8M3 18h18"/>
                        </svg>
                    </span>
                    <input
                        id="name"
                        class="project-intake-input @error('name') is-invalid @enderror"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        maxlength="180"
                        autocomplete="off"
                        placeholder="Example: Pekanbaru Commercial Rooftop"
                        required
                        data-summary-source="name"
                    >
                    <span class="project-intake-character-count"><span data-character-current="name">{{ strlen(old('name', '')) }}</span>/180</span>
                </div>
                @error('name')<div class="project-intake-error">{{ $message }}</div>@enderror
            </div>

            <fieldset class="project-intake-choice-field project-intake-span-2" data-choice-confirmed="{{ $hasConfirmedBuildingType ? 'true' : 'false' }}">
                <legend>
                    Building type
                    <span aria-hidden="true">*</span>
                </legend>
                <div class="project-intake-choice-grid two-columns" data-choice-group="building_type">
                    <label class="project-intake-choice-card">
                        <input type="radio" name="building_type" value="residential" @checked($selectedBuildingType === 'residential') required>
                        <span class="project-intake-choice-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M3 11l9-7 9 7"/>
                                <path d="M5 10v10h14V10M9 20v-6h6v6"/>
                            </svg>
                        </span>
                        <span>
                            <strong>Residential</strong>
                            <small>Homes and residential properties</small>
                        </span>
                        <span class="project-intake-choice-check">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 12l4 4 8-9"/></svg>
                        </span>
                    </label>

                    <label class="project-intake-choice-card">
                        <input type="radio" name="building_type" value="commercial" @checked($selectedBuildingType === 'commercial') required>
                        <span class="project-intake-choice-icon tone-blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 21V5h16v16"/>
                                <path d="M8 9h2M14 9h2M8 13h2M14 13h2M9 21v-4h6v4"/>
                            </svg>
                        </span>
                        <span>
                            <strong>Commercial</strong>
                            <small>Business, industrial, and public sites</small>
                        </span>
                        <span class="project-intake-choice-check">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 12l4 4 8-9"/></svg>
                        </span>
                    </label>
                </div>
                @error('building_type')<div class="project-intake-error">{{ $message }}</div>@enderror
            </fieldset>

            <fieldset class="project-intake-choice-field project-intake-span-2" data-choice-confirmed="{{ $hasConfirmedSystem ? 'true' : 'false' }}">
                <legend>
                    Initial system direction
                    <span aria-hidden="true">*</span>
                </legend>
                <div class="project-intake-choice-grid three-columns" data-choice-group="proposed_system">
                    <label class="project-intake-choice-card">
                        <input type="radio" name="proposed_system" value="undecided" @checked($selectedSystem === 'undecided') required>
                        <span class="project-intake-choice-icon tone-amber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M9.8 9a2.3 2.3 0 1 1 3.6 1.9c-.9.6-1.4 1-1.4 2.1M12 17h.01"/>
                            </svg>
                        </span>
                        <span>
                            <strong>Undecided</strong>
                            <small>Let engineering refine it</small>
                        </span>
                        <span class="project-intake-choice-check">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 12l4 4 8-9"/></svg>
                        </span>
                    </label>

                    <label class="project-intake-choice-card">
                        <input type="radio" name="proposed_system" value="on_grid" @checked($selectedSystem === 'on_grid') required>
                        <span class="project-intake-choice-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 15h16l-2-8H6l-2 8zM12 15v6M8 21h8"/>
                            </svg>
                        </span>
                        <span>
                            <strong>On-grid</strong>
                            <small>Grid-connected PV system</small>
                        </span>
                        <span class="project-intake-choice-check">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 12l4 4 8-9"/></svg>
                        </span>
                    </label>

                    <label class="project-intake-choice-card">
                        <input type="radio" name="proposed_system" value="hybrid" @checked($selectedSystem === 'hybrid') required>
                        <span class="project-intake-choice-icon tone-purple">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 14h8l-2-7H6l-2 7zM7 14v4"/>
                                <rect x="14" y="8" width="6" height="10" rx="1"/>
                                <path d="M16 11h2M12 12h2M12 16h2"/>
                            </svg>
                        </span>
                        <span>
                            <strong>Hybrid</strong>
                            <small>PV, storage, and grid</small>
                        </span>
                        <span class="project-intake-choice-check">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 12l4 4 8-9"/></svg>
                        </span>
                    </label>
                </div>
                <small class="project-intake-help">The recommendation engine can refine this direction during engineering.</small>
                @error('proposed_system')<div class="project-intake-error">{{ $message }}</div>@enderror
            </fieldset>
        </div>
    </section>

    <section id="project-location" class="project-intake-card" data-intake-section data-reveal>
        <header class="project-intake-card-header">
            <span class="project-intake-section-number">02</span>
            <span class="project-intake-section-icon tone-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0z"/>
                    <circle cx="12" cy="10" r="2.5"/>
                </svg>
            </span>
            <div>
                <span>Site &amp; location</span>
                <h3>Place the installation accurately</h3>
                <p>Record the site address and optional coordinates for solar and survey workflows.</p>
            </div>
            <span class="project-intake-section-state" data-section-state="project-location">Required</span>
        </header>

        <div class="project-intake-card-body">
            <div class="project-intake-field project-intake-span-2">
                <label for="site_address">
                    Project site address
                    <span aria-hidden="true">*</span>
                </label>
                <div class="project-intake-textarea-wrap">
                    <textarea
                        id="site_address"
                        class="project-intake-textarea @error('site_address') is-invalid @enderror"
                        name="site_address"
                        rows="3"
                        maxlength="2000"
                        autocomplete="street-address"
                        placeholder="Complete installation site address"
                        required
                        data-auto-resize
                        data-summary-source="address"
                    >{{ old('site_address') }}</textarea>
                    <span class="project-intake-character-count"><span data-character-current="site_address">{{ strlen(old('site_address', '')) }}</span>/2000</span>
                </div>
                @error('site_address')<div class="project-intake-error">{{ $message }}</div>@enderror
            </div>

            <div class="project-intake-field">
                <label for="city">City or regency</label>
                <input id="city" class="project-intake-input @error('city') is-invalid @enderror" type="text" name="city" value="{{ old('city') }}" maxlength="100" autocomplete="address-level2" placeholder="Example: Pekanbaru" data-summary-source="city">
                @error('city')<div class="project-intake-error">{{ $message }}</div>@enderror
            </div>

            <div class="project-intake-field">
                <label for="province">Province</label>
                <input id="province" class="project-intake-input @error('province') is-invalid @enderror" type="text" name="province" value="{{ old('province') }}" maxlength="100" autocomplete="address-level1" placeholder="Example: Riau" data-summary-source="province">
                @error('province')<div class="project-intake-error">{{ $message }}</div>@enderror
            </div>

            <div class="project-intake-field">
                <label for="postal_code">Postal code</label>
                <input id="postal_code" class="project-intake-input @error('postal_code') is-invalid @enderror" type="text" name="postal_code" value="{{ old('postal_code') }}" maxlength="20" autocomplete="postal-code" placeholder="Example: 28111">
                @error('postal_code')<div class="project-intake-error">{{ $message }}</div>@enderror
            </div>

            <div class="project-intake-location-actions">
                <span>
                    <small>Coordinate tools</small>
                    <strong>Optional precision</strong>
                </span>
                <button type="button" class="project-intake-location-button" data-use-location>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
                        <circle cx="12" cy="12" r="8"/>
                    </svg>
                    <span data-location-button-label>Use current location</span>
                </button>
            </div>

            <div class="project-intake-coordinate-panel project-intake-span-2">
                <div class="project-intake-coordinate-heading">
                    <span class="project-intake-coordinate-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/>
                            <path d="M9 3v15M15 6v15"/>
                        </svg>
                    </span>
                    <span>
                        <strong>Geographic coordinates</strong>
                        <small>Improve solar resource matching and site verification.</small>
                    </span>
                    <span class="project-intake-coordinate-status" data-location-status>Not set</span>
                </div>

                <div class="project-intake-coordinate-grid">
                    <div class="project-intake-field">
                        <label for="latitude">Latitude</label>
                        <input id="latitude" class="project-intake-input @error('latitude') is-invalid @enderror" type="number" name="latitude" value="{{ old('latitude') }}" min="-90" max="90" step="0.0000001" placeholder="Example: 0.5070670" data-summary-source="latitude">
                        @error('latitude')<div class="project-intake-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="project-intake-field">
                        <label for="longitude">Longitude</label>
                        <input id="longitude" class="project-intake-input @error('longitude') is-invalid @enderror" type="number" name="longitude" value="{{ old('longitude') }}" min="-180" max="180" step="0.0000001" placeholder="Example: 101.4477790" data-summary-source="longitude">
                        @error('longitude')<div class="project-intake-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="project-team" class="project-intake-card" data-intake-section data-reveal>
        <header class="project-intake-card-header">
            <span class="project-intake-section-number">03</span>
            <span class="project-intake-section-icon tone-purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="8" cy="8" r="3"/>
                    <circle cx="17" cy="9" r="2.5"/>
                    <path d="M2.5 20a5.5 5.5 0 0 1 11 0M13 20a4 4 0 0 1 8 0"/>
                </svg>
            </span>
            <div>
                <span>Team assignment</span>
                <h3>Set initial ownership</h3>
                <p>Assign engineering and commercial owners now, or leave them open for later planning.</p>
            </div>
            <span class="project-intake-section-state is-optional" data-section-state="project-team">Optional</span>
        </header>

        <div class="project-intake-card-body">
            @if ($canCreateCompanyProject)
                <div class="project-intake-field">
                    <label for="assigned_engineer_id">Assigned engineer</label>
                    <div class="project-intake-control-wrap">
                        <span class="project-intake-control-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>
                                <path d="M17 4l3 3-3 3"/>
                            </svg>
                        </span>
                        <select id="assigned_engineer_id" class="project-intake-select @error('assigned_engineer_id') is-invalid @enderror" name="assigned_engineer_id" data-summary-source="engineer">
                            <option value="">Unassigned</option>
                            @foreach ($engineers as $engineer)
                                <option value="{{ $engineer->id }}" @selected((string) old('assigned_engineer_id', '') === (string) $engineer->id)>
                                    {{ $engineer->name }} · {{ $engineer->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('assigned_engineer_id')<div class="project-intake-error">{{ $message }}</div>@enderror
                </div>

                <div class="project-intake-field">
                    <label for="assigned_sales_id">Assigned sales</label>
                    <div class="project-intake-control-wrap">
                        <span class="project-intake-control-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="9" cy="8" r="4"/>
                                <path d="M2.5 21a6.5 6.5 0 0 1 13 0M16 6h5M18.5 3.5v5"/>
                            </svg>
                        </span>
                        <select id="assigned_sales_id" class="project-intake-select @error('assigned_sales_id') is-invalid @enderror" name="assigned_sales_id" data-summary-source="sales">
                            <option value="">Unassigned</option>
                            @foreach ($salesUsers as $salesUser)
                                <option value="{{ $salesUser->id }}" @selected((string) old('assigned_sales_id', '') === (string) $salesUser->id)>
                                    {{ $salesUser->name }} · {{ $salesUser->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('assigned_sales_id')<div class="project-intake-error">{{ $message }}</div>@enderror
                </div>
            @else
                <div class="project-intake-team-locked project-intake-span-2">
                    <span class="project-intake-team-locked-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <rect x="5" y="10" width="14" height="10" rx="2"/>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        </svg>
                    </span>
                    <span>
                        <strong>Team assignment is managed by the company workspace.</strong>
                        <small>Your project can be assigned after the initial record is created.</small>
                    </span>
                </div>
            @endif
        </div>
    </section>

    <section id="project-notes" class="project-intake-card" data-intake-section data-reveal>
        <header class="project-intake-card-header">
            <span class="project-intake-section-number">04</span>
            <span class="project-intake-section-icon tone-amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M5 3h11l3 3v15H5z"/>
                    <path d="M16 3v4h4M8 12h8M8 16h6"/>
                </svg>
            </span>
            <div>
                <span>Project notes</span>
                <h3>Preserve the early project context</h3>
                <p>Record goals, constraints, expectations, and survey notes for the team.</p>
            </div>
            <span class="project-intake-section-state is-optional" data-section-state="project-notes">Optional</span>
        </header>

        <div class="project-intake-card-body">
            <div class="project-intake-field project-intake-span-2">
                <label for="description">Project notes</label>
                <div class="project-intake-textarea-wrap">
                    <textarea
                        id="description"
                        class="project-intake-textarea @error('description') is-invalid @enderror"
                        name="description"
                        rows="4"
                        maxlength="5000"
                        placeholder="Project goals, constraints, customer expectations, or survey context"
                        data-auto-resize
                    >{{ old('description') }}</textarea>
                    <span class="project-intake-character-count"><span data-character-current="description">{{ strlen(old('description', '')) }}</span>/5000</span>
                </div>
                @error('description')<div class="project-intake-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </section>
</div>

<aside class="project-intake-summary" data-intake-summary data-reveal>
    <div class="project-intake-summary-card">
        <header class="project-intake-summary-header">
            <span class="project-intake-summary-kicker">Live intake summary</span>
            <h3>Project readiness</h3>
            <p>Complete the essential project brief before creating the workspace record.</p>
        </header>

        <div class="project-intake-readiness">
            <div class="project-intake-readiness-ring" style="--intake-progress: 0" data-intake-ring>
                <span>
                    <strong data-intake-progress>0</strong><em>%</em>
                    <small>ready</small>
                </span>
            </div>
            <div>
                <strong data-intake-progress-label>Start with the customer</strong>
                <span><b data-required-complete>0</b> of {{ count($requiredFieldIds) }} essential fields complete</span>
            </div>
        </div>

        <div class="project-intake-summary-list">
            <div data-summary-row="customer" class="{{ $selectedCustomer ? 'is-ready' : 'is-missing' }}">
                <span>Customer</span>
                <strong data-summary-value="customer">{{ $selectedCustomer?->name ?? 'Not selected' }}</strong>
                <em data-summary-status="customer">{{ $selectedCustomer ? 'Ready' : 'Missing' }}</em>
            </div>
            <div data-summary-row="name" class="{{ old('name') ? 'is-ready' : 'is-missing' }}">
                <span>Project</span>
                <strong data-summary-value="name">{{ old('name') ?: 'Untitled project' }}</strong>
                <em data-summary-status="name">{{ old('name') ? 'Ready' : 'Missing' }}</em>
            </div>
            <div data-summary-row="building_type" class="{{ $hasConfirmedBuildingType ? '' : 'is-default' }}">
                <span>Building</span>
                <strong data-summary-value="building_type">{{ ucfirst($selectedBuildingType) }}</strong>
                <em data-summary-status="building_type">{{ $hasConfirmedBuildingType ? 'Confirmed' : 'Default' }}</em>
            </div>
            <div data-summary-row="proposed_system" class="{{ $hasConfirmedSystem ? '' : 'is-default' }}">
                <span>System</span>
                <strong data-summary-value="proposed_system">{{ $selectedSystem === 'on_grid' ? 'On-grid' : ucfirst($selectedSystem) }}</strong>
                <em data-summary-status="proposed_system">{{ $hasConfirmedSystem ? 'Confirmed' : 'Default' }}</em>
            </div>
            <div data-summary-row="location" class="{{ old('site_address') ? 'is-ready' : 'is-missing' }}">
                <span>Location</span>
                <strong data-summary-value="location">{{ old('city') ?: old('site_address', 'Not specified') }}</strong>
                <em data-summary-status="location">{{ old('site_address') ? 'Ready' : 'Missing' }}</em>
            </div>
            <div data-summary-row="engineer">
                <span>Engineer</span>
                <strong data-summary-value="engineer">Unassigned</strong>
            </div>
            <div data-summary-row="sales">
                <span>Sales</span>
                <strong data-summary-value="sales">Unassigned</strong>
            </div>
        </div>

        <div class="project-intake-workflow-preview">
            <span class="project-intake-summary-kicker">What happens next</span>
            <ol>
                <li class="is-current">
                    <span>01</span>
                    <div><strong>Draft created</strong><small>Project number generated automatically</small></div>
                </li>
                <li>
                    <span>02</span>
                    <div><strong>Customer brief</strong><small>Load analysis and site data can begin</small></div>
                </li>
                <li>
                    <span>03</span>
                    <div><strong>Engineering workflow</strong><small>Recommendation, calculation, and BOM/BOQ</small></div>
                </li>
            </ol>
        </div>

        <div class="project-intake-summary-actions">
            <a href="{{ route('companies.projects.index', $company) }}" class="project-intake-cancel">Cancel</a>
            <button type="submit" class="project-intake-submit" data-project-intake-submit disabled aria-disabled="true">
                <span class="project-intake-submit-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                </span>
                <span data-submit-label>Complete required fields</span>
                <svg class="project-intake-submit-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M5 12h14M13 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <p class="project-intake-submit-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg>
            Your project will be created as a draft. Existing workflow and permissions remain unchanged.
        </p>
    </div>
</aside>

<div class="project-intake-mobile-actions" data-intake-mobile-actions>
    <span>
        <strong data-mobile-progress>0%</strong>
        <small>Project readiness</small>
    </span>
    <button type="submit" data-project-intake-submit-mobile disabled aria-disabled="true">Complete required fields</button>
</div>
