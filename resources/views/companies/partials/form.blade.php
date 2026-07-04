@php
    $editing = isset($company);
@endphp

<div class="ui-form-grid">
    <div class="ui-field">
        <label class="ui-label" for="name">
            Company name
        </label>

        <input
            id="name"
            class="ui-input @error('name') is-invalid @enderror"
            type="text"
            name="name"
            value="{{ old('name', $company->name ?? '') }}"
            maxlength="150"
            autocomplete="organization"
            required
        >

        @error('name')
            <div class="ui-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="code">
            Company code
        </label>

        <input
            id="code"
            class="ui-input @error('code') is-invalid @enderror"
            type="text"
            name="code"
            value="{{ old('code', $company->code ?? '') }}"
            maxlength="30"
            placeholder="Example: NXR-ENERGY"
            autocomplete="off"
            required
        >

        <div class="ui-help">
            Use uppercase letters, numbers, hyphens, or underscores.
        </div>

        @error('code')
            <div class="ui-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="registration_number">
            Registration number
        </label>

        <input
            id="registration_number"
            class="ui-input @error('registration_number') is-invalid @enderror"
            type="text"
            name="registration_number"
            value="{{ old('registration_number', $company->registration_number ?? '') }}"
            maxlength="100"
            placeholder="Optional legal registration number"
        >

        @error('registration_number')
            <div class="ui-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="status">
            Company status
        </label>

        <select
            id="status"
            class="ui-select @error('status') is-invalid @enderror"
            name="status"
            required
        >
            <option
                value="active"
                @selected(old('status', $company->status ?? 'active') === 'active')
            >
                Active
            </option>
            <option
                value="inactive"
                @selected(old('status', $company->status ?? 'active') === 'inactive')
            >
                Inactive
            </option>
        </select>

        @error('status')
            <div class="ui-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="email">
            Company email
        </label>

        <input
            id="email"
            class="ui-input @error('email') is-invalid @enderror"
            type="email"
            name="email"
            value="{{ old('email', $company->email ?? '') }}"
            maxlength="255"
            autocomplete="email"
            placeholder="company@example.com"
        >

        @error('email')
            <div class="ui-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="phone">
            Company phone
        </label>

        <input
            id="phone"
            class="ui-input @error('phone') is-invalid @enderror"
            type="text"
            name="phone"
            value="{{ old('phone', $company->phone ?? '') }}"
            maxlength="30"
            autocomplete="tel"
            placeholder="+62 ..."
        >

        @error('phone')
            <div class="ui-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="ui-field ui-form-span-2">
        <label class="ui-label" for="address">
            Company address
        </label>

        <textarea
            id="address"
            class="ui-textarea @error('address') is-invalid @enderror"
            name="address"
            maxlength="2000"
            autocomplete="street-address"
            placeholder="Office address, city, province, and postal code"
        >{{ old('address', $company->address ?? '') }}</textarea>

        @error('address')
            <div class="ui-error">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="ui-form-actions">
    <a
        href="{{ $editing ? route('companies.show', $company) : route('companies.index') }}"
        class="ui-button ui-button-secondary"
    >
        Cancel
    </a>

    <button
        type="submit"
        class="ui-button ui-button-primary"
    >
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.9"
            aria-hidden="true"
        >
            <path d="M5 4h12l2 2v14H5z"/>
            <path d="M8 4v6h8V4M8 20v-6h8v6"/>
        </svg>

        {{ $editing ? 'Save Changes' : 'Create Company' }}
    </button>
</div>
