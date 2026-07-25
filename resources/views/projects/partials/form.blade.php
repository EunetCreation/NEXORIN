@php
    $editing = isset($project);
    $selectedCustomerId = old(
        'customer_id',
        $project->customer_id ?? request()->query('customer_id', ($ownedCustomer ?? null)?->id ?? '')
    );
    $selectedStatus = old('status', $project->status ?? 'draft');
@endphp

<section class="project-editor-section" id="project-editor-identity" data-editor-section data-editor-reveal>
    <header class="project-editor-section-head">
        <div class="project-editor-section-number">01</div>
        <span class="project-editor-section-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M4 5h16v14H4z"/>
                <path d="M8 9h8M8 13h5M8 17h3"/>
            </svg>
        </span>
        <div>
            <span>Project identity</span>
            <h3>Define the commercial and engineering brief</h3>
            <p>Keep the customer, project name, building type, and initial system direction aligned.</p>
        </div>
        <span class="project-editor-section-state" data-editor-section-state>Ready</span>
    </header>

    <div class="project-editor-section-body project-editor-grid">
        <div class="project-editor-field project-editor-span-2">
            <label for="customer_id">
                Customer
                <span aria-hidden="true">*</span>
            </label>

            @if ($canCreateCompanyProject)
                <div class="project-editor-control-wrap">
                    <span class="project-editor-control-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M17 8h4M19 6v4"/></svg>
                    </span>
                    <select
                        id="customer_id"
                        class="project-editor-select project-editor-control-with-icon @error('customer_id') is-invalid @enderror"
                        name="customer_id"
                        required
                        data-editor-field
                        data-editor-summary-source="customer"
                    >
                        <option value="">Select a customer</option>
                        @foreach ($customers as $customerOption)
                            <option value="{{ $customerOption->id }}" @selected((string) $selectedCustomerId === (string) $customerOption->id)>
                                {{ $customerOption->customer_number }} · {{ $customerOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <small class="project-editor-help">Only active customers in {{ $company->name }} are available.</small>
            @else
                <input type="hidden" name="customer_id" value="{{ $ownedCustomer->id }}" data-editor-field>
                <div class="project-editor-locked-field">
                    <span>Customer portal owner</span>
                    <strong>{{ $ownedCustomer->name }}</strong>
                    <small>{{ $ownedCustomer->customer_number }}</small>
                </div>
            @endif

            @error('customer_id')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>

        <div class="project-editor-field">
            <label for="name">
                Project name
                <span aria-hidden="true">*</span>
            </label>
            <div class="project-editor-control-wrap">
                <span class="project-editor-control-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 18h18"/></svg>
                </span>
                <input
                    id="name"
                    class="project-editor-input project-editor-control-with-icon @error('name') is-invalid @enderror"
                    type="text"
                    name="name"
                    value="{{ old('name', $project->name ?? '') }}"
                    maxlength="180"
                    autocomplete="off"
                    placeholder="Example: Residence Rooftop PV Project"
                    required
                    data-editor-field
                    data-editor-summary-source="name"
                >
                <span class="project-editor-character-count"><span data-editor-character="name">{{ strlen(old('name', $project->name ?? '')) }}</span>/180</span>
            </div>
            @error('name')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>

        <div class="project-editor-field">
            <label for="building_type">
                Building type
                <span aria-hidden="true">*</span>
            </label>
            <select
                id="building_type"
                class="project-editor-select @error('building_type') is-invalid @enderror"
                name="building_type"
                required
                data-editor-field
                data-editor-summary-source="building"
            >
                <option value="residential" @selected(old('building_type', $project->building_type ?? 'residential') === 'residential')>Residential</option>
                <option value="commercial" @selected(old('building_type', $project->building_type ?? 'residential') === 'commercial')>Commercial</option>
            </select>
            <small class="project-editor-help">Controls the default scale and downstream engineering assumptions.</small>
            @error('building_type')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>

        <div class="project-editor-field project-editor-span-2">
            <label for="proposed_system">
                Initial system direction
                <span aria-hidden="true">*</span>
            </label>
            <div class="project-editor-system-selector" data-editor-system-selector>
                <label>
                    <input type="radio" name="proposed_system" value="undecided" @checked(old('proposed_system', $project->proposed_system ?? 'undecided') === 'undecided') required data-editor-field data-editor-summary-source="system">
                    <span class="project-editor-system-option tone-amber">
                        <span class="project-editor-system-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.3 2.3 0 1 1 3.6 1.9c-.9.6-1.4 1-1.4 2.1M12 17h.01"/></svg>
                        </span>
                        <span><strong>Undecided</strong><small>Let engineering refine it</small></span>
                        <span class="project-editor-system-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 12l4 4 8-9"/></svg></span>
                    </span>
                </label>
                <label>
                    <input type="radio" name="proposed_system" value="on_grid" @checked(old('proposed_system', $project->proposed_system ?? 'undecided') === 'on_grid') required data-editor-field data-editor-summary-source="system">
                    <span class="project-editor-system-option">
                        <span class="project-editor-system-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 14h8l-2-7H6l-2 7zM7 14v5M15 5v14M12 8h6M12 12h6M12 16h6"/></svg>
                        </span>
                        <span><strong>On-grid</strong><small>Grid-connected PV system</small></span>
                        <span class="project-editor-system-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 12l4 4 8-9"/></svg></span>
                    </span>
                </label>
                <label>
                    <input type="radio" name="proposed_system" value="hybrid" @checked(old('proposed_system', $project->proposed_system ?? 'undecided') === 'hybrid') required data-editor-field data-editor-summary-source="system">
                    <span class="project-editor-system-option tone-purple">
                        <span class="project-editor-system-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 14h8l-2-7H6l-2 7zM7 14v4"/><rect x="14" y="8" width="6" height="10" rx="1"/><path d="M16 11h2M12 12h2M12 16h2"/></svg>
                        </span>
                        <span><strong>Hybrid</strong><small>PV, storage, and grid</small></span>
                        <span class="project-editor-system-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 12l4 4 8-9"/></svg></span>
                    </span>
                </label>
            </div>
            <small class="project-editor-help">The recommendation engine can refine this direction during engineering.</small>
            @error('proposed_system')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

<section class="project-editor-section" id="project-editor-location" data-editor-section data-editor-reveal>
    <header class="project-editor-section-head">
        <div class="project-editor-section-number">02</div>
        <span class="project-editor-section-icon tone-blue" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/></svg>
        </span>
        <div>
            <span>Site and location</span>
            <h3>Anchor the project to the installation site</h3>
            <p>Maintain an accurate address and optional coordinates for solar, survey, and logistics workflows.</p>
        </div>
        <span class="project-editor-section-state" data-editor-section-state>Ready</span>
    </header>

    <div class="project-editor-section-body project-editor-grid">
        <div class="project-editor-field project-editor-span-2">
            <label for="site_address">
                Project site address
                <span aria-hidden="true">*</span>
            </label>
            <div class="project-editor-textarea-wrap">
                <textarea
                    id="site_address"
                    class="project-editor-textarea project-editor-address @error('site_address') is-invalid @enderror"
                    name="site_address"
                    rows="3"
                    maxlength="2000"
                    autocomplete="street-address"
                    placeholder="Complete installation site address"
                    required
                    data-editor-field
                    data-editor-auto-resize
                >{{ old('site_address', $project->site_address ?? '') }}</textarea>
                <span class="project-editor-character-count"><span data-editor-character="site_address">{{ strlen(old('site_address', $project->site_address ?? '')) }}</span>/2000</span>
            </div>
            @error('site_address')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>

        <div class="project-editor-field">
            <label for="city">City or regency</label>
            <input id="city" class="project-editor-input @error('city') is-invalid @enderror" type="text" name="city" value="{{ old('city', $project->city ?? '') }}" maxlength="100" autocomplete="address-level2" data-editor-field data-editor-summary-source="city">
            @error('city')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>

        <div class="project-editor-field">
            <label for="province">Province</label>
            <input id="province" class="project-editor-input @error('province') is-invalid @enderror" type="text" name="province" value="{{ old('province', $project->province ?? '') }}" maxlength="100" autocomplete="address-level1" data-editor-field data-editor-summary-source="province">
            @error('province')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>

        <div class="project-editor-field">
            <label for="postal_code">Postal code</label>
            <input id="postal_code" class="project-editor-input @error('postal_code') is-invalid @enderror" type="text" name="postal_code" value="{{ old('postal_code', $project->postal_code ?? '') }}" maxlength="20" autocomplete="postal-code" data-editor-field>
            @error('postal_code')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>

        <div class="project-editor-location-status">
            <span class="project-editor-location-status-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </span>
            <div>
                <strong data-editor-location-label>Coordinates ready</strong>
                <small data-editor-location-copy>Latitude and longitude support solar and map workflows.</small>
            </div>
        </div>

        <div class="project-editor-coordinate-group project-editor-span-2">
            <div class="project-editor-coordinate-head">
                <div>
                    <span>Geographic coordinates</span>
                    <small>Optional, but recommended for solar-resource accuracy.</small>
                </div>
                <button type="button" class="project-editor-location-button" data-editor-use-location>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>
                    <span data-editor-location-button-label>Use current location</span>
                </button>
            </div>
            <div class="project-editor-coordinate-grid">
                <div class="project-editor-field">
                    <label for="latitude">Latitude</label>
                    <input id="latitude" class="project-editor-input @error('latitude') is-invalid @enderror" type="number" name="latitude" value="{{ old('latitude', $project->latitude ?? '') }}" min="-90" max="90" step="0.0000001" placeholder="0.5070670" inputmode="decimal" data-editor-field>
                    @error('latitude')<div class="project-editor-error">{{ $message }}</div>@enderror
                </div>
                <div class="project-editor-field">
                    <label for="longitude">Longitude</label>
                    <input id="longitude" class="project-editor-input @error('longitude') is-invalid @enderror" type="number" name="longitude" value="{{ old('longitude', $project->longitude ?? '') }}" min="-180" max="180" step="0.0000001" placeholder="101.4477790" inputmode="decimal" data-editor-field>
                    @error('longitude')<div class="project-editor-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</section>

<section class="project-editor-section" id="project-editor-workflow" data-editor-section data-editor-reveal>
    <header class="project-editor-section-head">
        <div class="project-editor-section-number">03</div>
        <span class="project-editor-section-icon tone-purple" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M4 20v-2a5 5 0 0 1 10 0v2M16 8h4M18 6v4M16 15h4"/></svg>
        </span>
        <div>
            <span>Team and workflow</span>
            <h3>Control ownership and the next project state</h3>
            <p>Assign accountable owners and document meaningful workflow transitions for the audit trail.</p>
        </div>
        <span class="project-editor-section-state" data-editor-section-state>Ready</span>
    </header>

    <div class="project-editor-section-body project-editor-grid">
        @if ($canCreateCompanyProject)
            <div class="project-editor-field">
                <label for="assigned_engineer_id">Assigned engineer</label>
                <select id="assigned_engineer_id" class="project-editor-select @error('assigned_engineer_id') is-invalid @enderror" name="assigned_engineer_id" data-editor-field data-editor-summary-source="engineer">
                    <option value="">Unassigned</option>
                    @foreach ($engineers as $engineer)
                        <option value="{{ $engineer->id }}" @selected((string) old('assigned_engineer_id', $project->assigned_engineer_id ?? '') === (string) $engineer->id)>
                            {{ $engineer->name }} · {{ $engineer->email }}
                        </option>
                    @endforeach
                </select>
                <small class="project-editor-help">Technical owner responsible for engineering review and decisions.</small>
                @error('assigned_engineer_id')<div class="project-editor-error">{{ $message }}</div>@enderror
            </div>

            <div class="project-editor-field">
                <label for="assigned_sales_id">Assigned sales</label>
                <select id="assigned_sales_id" class="project-editor-select @error('assigned_sales_id') is-invalid @enderror" name="assigned_sales_id" data-editor-field data-editor-summary-source="sales">
                    <option value="">Unassigned</option>
                    @foreach ($salesUsers as $salesUser)
                        <option value="{{ $salesUser->id }}" @selected((string) old('assigned_sales_id', $project->assigned_sales_id ?? '') === (string) $salesUser->id)>
                            {{ $salesUser->name }} · {{ $salesUser->email }}
                        </option>
                    @endforeach
                </select>
                <small class="project-editor-help">Commercial owner responsible for customer and quotation coordination.</small>
                @error('assigned_sales_id')<div class="project-editor-error">{{ $message }}</div>@enderror
            </div>
        @endif

        @if ($editing)
            <div class="project-editor-workflow-panel project-editor-span-2">
                <div class="project-editor-workflow-panel-head">
                    <span class="project-editor-workflow-panel-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h10M10 3l4 4-4 4M20 17H10M14 13l-4 4 4 4"/></svg>
                    </span>
                    <div>
                        <strong>Workflow transition control</strong>
                        <p>Status changes are recorded in the project audit history and can affect available project actions.</p>
                    </div>
                </div>

                <div class="project-editor-workflow-grid">
                    <div class="project-editor-field">
                        <label for="status">
                            Workflow status
                            <span aria-hidden="true">*</span>
                        </label>
                        <select id="status" class="project-editor-select @error('status') is-invalid @enderror" name="status" required data-editor-field data-editor-summary-source="status" data-editor-original-status="{{ $project->status }}">
                            @foreach ($allowedStatuses as $statusOption)
                                <option value="{{ $statusOption }}" @selected($selectedStatus === $statusOption)>{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        <small class="project-editor-help">Only valid next workflow statuses are displayed.</small>
                        @error('status')<div class="project-editor-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="project-editor-field">
                        <label for="status_note">Status change note</label>
                        <input id="status_note" class="project-editor-input @error('status_note') is-invalid @enderror" type="text" name="status_note" value="{{ old('status_note') }}" maxlength="1000" placeholder="Add an optional handover note" data-editor-field data-editor-status-note>
                        <small class="project-editor-help" data-editor-status-note-help>Available after selecting a new workflow status.</small>
                        @error('status_note')<div class="project-editor-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>

<section class="project-editor-section" id="project-editor-notes" data-editor-section data-editor-reveal>
    <header class="project-editor-section-head">
        <div class="project-editor-section-number">04</div>
        <span class="project-editor-section-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
        </span>
        <div>
            <span>Project notes</span>
            <h3>Preserve context for the next reviewer</h3>
            <p>Record goals, constraints, customer expectations, survey context, and important engineering assumptions.</p>
        </div>
        <span class="project-editor-section-state" data-editor-section-state>Optional</span>
    </header>

    <div class="project-editor-section-body">
        <div class="project-editor-field">
            <label for="description">Project notes</label>
            <div class="project-editor-textarea-wrap">
                <textarea id="description" class="project-editor-textarea project-editor-notes @error('description') is-invalid @enderror" name="description" rows="5" maxlength="5000" placeholder="Project goals, constraints, customer expectations, or survey context" data-editor-field data-editor-auto-resize>{{ old('description', $project->description ?? '') }}</textarea>
                <span class="project-editor-character-count"><span data-editor-character="description">{{ strlen(old('description', $project->description ?? '')) }}</span>/5000</span>
            </div>
            @error('description')<div class="project-editor-error">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

<section class="project-editor-final-actions" data-editor-reveal>
    <div>
        <span>Revision checkpoint</span>
        <strong data-editor-bottom-state>No unsaved changes detected.</strong>
        <p>Use the sticky Revision Control to save. Workflow transitions remain traceable in the audit history.</p>
    </div>
    <div class="project-editor-final-buttons">
        <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="project-editor-cancel-button">Discard and return</a>
    </div>
</section>
