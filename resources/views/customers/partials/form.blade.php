@php
    $editing = isset($customer);
@endphp

<div class="ui-form-grid">
    <div class="ui-field">
        <label class="ui-label" for="name">Customer name</label>
        <input
            id="name"
            class="ui-input @error('name') is-invalid @enderror"
            type="text"
            name="name"
            value="{{ old('name', $customer->name ?? '') }}"
            maxlength="150"
            autocomplete="name"
            required
        >
        @error('name')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="type">Customer type</label>
        <select id="type" class="ui-select @error('type') is-invalid @enderror" name="type" required>
            <option value="residential" @selected(old('type', $customer->type ?? 'residential') === 'residential')>Residential</option>
            <option value="commercial" @selected(old('type', $customer->type ?? 'residential') === 'commercial')>Commercial</option>
        </select>
        @error('type')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="email">Email address</label>
        <input
            id="email"
            class="ui-input @error('email') is-invalid @enderror"
            type="email"
            name="email"
            value="{{ old('email', $customer->email ?? '') }}"
            maxlength="255"
            autocomplete="email"
            placeholder="customer@example.com"
        >
        @error('email')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="phone">Phone number</label>
        <input
            id="phone"
            class="ui-input @error('phone') is-invalid @enderror"
            type="text"
            name="phone"
            value="{{ old('phone', $customer->phone ?? '') }}"
            maxlength="30"
            autocomplete="tel"
            placeholder="+62 ..."
        >
        @error('phone')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="city">City or regency</label>
        <input
            id="city"
            class="ui-input @error('city') is-invalid @enderror"
            type="text"
            name="city"
            value="{{ old('city', $customer->city ?? '') }}"
            maxlength="100"
        >
        @error('city')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="province">Province</label>
        <input
            id="province"
            class="ui-input @error('province') is-invalid @enderror"
            type="text"
            name="province"
            value="{{ old('province', $customer->province ?? '') }}"
            maxlength="100"
        >
        @error('province')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="postal_code">Postal code</label>
        <input
            id="postal_code"
            class="ui-input @error('postal_code') is-invalid @enderror"
            type="text"
            name="postal_code"
            value="{{ old('postal_code', $customer->postal_code ?? '') }}"
            maxlength="20"
            autocomplete="postal-code"
        >
        @error('postal_code')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="portal_user_id">Customer portal account</label>
        <select id="portal_user_id" class="ui-select @error('portal_user_id') is-invalid @enderror" name="portal_user_id">
            <option value="">Not linked</option>
            @foreach ($portalUsers as $portalUser)
                <option
                    value="{{ $portalUser->id }}"
                    @selected((string) old('portal_user_id', $customer->portal_user_id ?? '') === (string) $portalUser->id)
                >
                    {{ $portalUser->name }} · {{ $portalUser->email }}
                </option>
            @endforeach
        </select>
        <div class="ui-help">Only active company members with the Customer role can be linked.</div>
        @error('portal_user_id')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field ui-form-span-2">
        <label class="ui-label" for="address">Customer address</label>
        <textarea
            id="address"
            class="ui-textarea @error('address') is-invalid @enderror"
            name="address"
            maxlength="2000"
            autocomplete="street-address"
            placeholder="Street, district, city, province, and postal code"
        >{{ old('address', $customer->address ?? '') }}</textarea>
        @error('address')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field ui-form-span-2">
        <label class="ui-label" for="notes">Internal notes</label>
        <textarea
            id="notes"
            class="ui-textarea @error('notes') is-invalid @enderror"
            name="notes"
            maxlength="5000"
            placeholder="Relevant communication, preferences, or project context"
        >{{ old('notes', $customer->notes ?? '') }}</textarea>
        <div class="ui-help">Internal notes are not shown in customer-facing reports.</div>
        @error('notes')<div class="ui-error">{{ $message }}</div>@enderror
    </div>
</div>

<div class="ui-form-actions">
    <a
        href="{{ $editing ? route('companies.customers.show', [$company, $customer]) : route('companies.customers.index', $company) }}"
        class="ui-button ui-button-secondary"
    >
        Cancel
    </a>

    <button type="submit" class="ui-button ui-button-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path d="M5 4h12l2 2v14H5z"/>
            <path d="M8 4v6h8V4M8 20v-6h8v6"/>
        </svg>
        {{ $editing ? 'Save Changes' : 'Create Customer' }}
    </button>
</div>
