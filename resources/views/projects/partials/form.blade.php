@php
    $editing = isset($project);
    $selectedCustomerId = old(
        'customer_id',
        $project->customer_id ?? request()->query('customer_id', ($ownedCustomer ?? null)?->id ?? '')
    );
@endphp

<div class="ui-form-grid">
    <div class="ui-field ui-form-span-2">
        <label class="ui-label" for="customer_id">Customer</label>

        @if ($canCreateCompanyProject)
            <select id="customer_id" class="ui-select @error('customer_id') is-invalid @enderror" name="customer_id" required>
                <option value="">Select a customer</option>
                @foreach ($customers as $customerOption)
                    <option value="{{ $customerOption->id }}" @selected((string) $selectedCustomerId === (string) $customerOption->id)>
                        {{ $customerOption->customer_number }} · {{ $customerOption->name }}
                    </option>
                @endforeach
            </select>
            <div class="ui-help">Only active customers in {{ $company->name }} are available.</div>
        @else
            <input type="hidden" name="customer_id" value="{{ $ownedCustomer->id }}">
            <div class="ui-locked-field">
                <span class="ui-locked-label">Customer portal owner</span>
                <strong>{{ $ownedCustomer->name }}</strong>
                <small>{{ $ownedCustomer->customer_number }}</small>
            </div>
        @endif

        @error('customer_id')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="name">Project name</label>
        <input
            id="name"
            class="ui-input @error('name') is-invalid @enderror"
            type="text"
            name="name"
            value="{{ old('name', $project->name ?? '') }}"
            maxlength="180"
            placeholder="Example: Residence Rooftop PV Project"
            required
        >
        @error('name')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="building_type">Building type</label>
        <select id="building_type" class="ui-select @error('building_type') is-invalid @enderror" name="building_type" required>
            <option value="residential" @selected(old('building_type', $project->building_type ?? 'residential') === 'residential')>Residential</option>
            <option value="commercial" @selected(old('building_type', $project->building_type ?? 'residential') === 'commercial')>Commercial</option>
        </select>
        @error('building_type')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="proposed_system">Initial system direction</label>
        <select id="proposed_system" class="ui-select @error('proposed_system') is-invalid @enderror" name="proposed_system" required>
            <option value="undecided" @selected(old('proposed_system', $project->proposed_system ?? 'undecided') === 'undecided')>Undecided</option>
            <option value="on_grid" @selected(old('proposed_system', $project->proposed_system ?? 'undecided') === 'on_grid')>On-grid</option>
            <option value="hybrid" @selected(old('proposed_system', $project->proposed_system ?? 'undecided') === 'hybrid')>Hybrid</option>
        </select>
        <div class="ui-help">The recommendation engine can refine this choice later.</div>
        @error('proposed_system')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="postal_code">Postal code</label>
        <input
            id="postal_code"
            class="ui-input @error('postal_code') is-invalid @enderror"
            type="text"
            name="postal_code"
            value="{{ old('postal_code', $project->postal_code ?? '') }}"
            maxlength="20"
            autocomplete="postal-code"
        >
        @error('postal_code')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field ui-form-span-2">
        <label class="ui-label" for="site_address">Project site address</label>
        <textarea
            id="site_address"
            class="ui-textarea @error('site_address') is-invalid @enderror"
            name="site_address"
            maxlength="2000"
            autocomplete="street-address"
            placeholder="Complete installation site address"
            required
        >{{ old('site_address', $project->site_address ?? '') }}</textarea>
        @error('site_address')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="city">City or regency</label>
        <input id="city" class="ui-input @error('city') is-invalid @enderror" type="text" name="city" value="{{ old('city', $project->city ?? '') }}" maxlength="100">
        @error('city')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="province">Province</label>
        <input id="province" class="ui-input @error('province') is-invalid @enderror" type="text" name="province" value="{{ old('province', $project->province ?? '') }}" maxlength="100">
        @error('province')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="latitude">Latitude</label>
        <input
            id="latitude"
            class="ui-input @error('latitude') is-invalid @enderror"
            type="number"
            name="latitude"
            value="{{ old('latitude', $project->latitude ?? '') }}"
            min="-90"
            max="90"
            step="0.0000001"
            placeholder="0.5070670"
        >
        @error('latitude')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="longitude">Longitude</label>
        <input
            id="longitude"
            class="ui-input @error('longitude') is-invalid @enderror"
            type="number"
            name="longitude"
            value="{{ old('longitude', $project->longitude ?? '') }}"
            min="-180"
            max="180"
            step="0.0000001"
            placeholder="101.4477790"
        >
        @error('longitude')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    @if ($canCreateCompanyProject)
        <div class="ui-field">
            <label class="ui-label" for="assigned_engineer_id">Assigned engineer</label>
            <select id="assigned_engineer_id" class="ui-select @error('assigned_engineer_id') is-invalid @enderror" name="assigned_engineer_id">
                <option value="">Unassigned</option>
                @foreach ($engineers as $engineer)
                    <option value="{{ $engineer->id }}" @selected((string) old('assigned_engineer_id', $project->assigned_engineer_id ?? '') === (string) $engineer->id)>
                        {{ $engineer->name }} · {{ $engineer->email }}
                    </option>
                @endforeach
            </select>
            @error('assigned_engineer_id')<div class="ui-error">{{ $message }}</div>@enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="assigned_sales_id">Assigned sales</label>
            <select id="assigned_sales_id" class="ui-select @error('assigned_sales_id') is-invalid @enderror" name="assigned_sales_id">
                <option value="">Unassigned</option>
                @foreach ($salesUsers as $salesUser)
                    <option value="{{ $salesUser->id }}" @selected((string) old('assigned_sales_id', $project->assigned_sales_id ?? '') === (string) $salesUser->id)>
                        {{ $salesUser->name }} · {{ $salesUser->email }}
                    </option>
                @endforeach
            </select>
            @error('assigned_sales_id')<div class="ui-error">{{ $message }}</div>@enderror
        </div>
    @endif

    @if ($editing)
        <div class="ui-field">
            <label class="ui-label" for="status">Workflow status</label>
            <select id="status" class="ui-select @error('status') is-invalid @enderror" name="status" required>
                @foreach ($allowedStatuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected(old('status', $project->status) === $statusOption)>
                        {{ ucfirst($statusOption) }}
                    </option>
                @endforeach
            </select>
            <div class="ui-help">Only valid next workflow statuses are displayed.</div>
            @error('status')<div class="ui-error">{{ $message }}</div>@enderror
        </div>

        <div class="ui-field">
            <label class="ui-label" for="status_note">Status change note</label>
            <input
                id="status_note"
                class="ui-input @error('status_note') is-invalid @enderror"
                type="text"
                name="status_note"
                value="{{ old('status_note') }}"
                maxlength="1000"
                placeholder="Optional reason or handover note"
            >
            @error('status_note')<div class="ui-error">{{ $message }}</div>@enderror
        </div>
    @endif

    <div class="ui-field ui-form-span-2">
        <label class="ui-label" for="description">Project notes</label>
        <textarea
            id="description"
            class="ui-textarea @error('description') is-invalid @enderror"
            name="description"
            maxlength="5000"
            placeholder="Project goals, constraints, customer expectations, or survey context"
        >{{ old('description', $project->description ?? '') }}</textarea>
        @error('description')<div class="ui-error">{{ $message }}</div>@enderror
    </div>
</div>

<div class="ui-form-actions">
    <a
        href="{{ $editing ? route('companies.projects.show', [$company, $project]) : route('companies.projects.index', $company) }}"
        class="ui-button ui-button-secondary"
    >
        Cancel
    </a>

    <button type="submit" class="ui-button ui-button-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path d="M5 4h12l2 2v14H5z"/>
            <path d="M8 4v6h8V4M8 20v-6h8v6"/>
        </svg>
        {{ $editing ? 'Save Project' : 'Create Project' }}
    </button>
</div>
