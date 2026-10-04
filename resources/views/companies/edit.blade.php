@extends('layouts.app')

@section('title', 'Edit Company')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Edit Company')
@section('force-theme', 'dark')
@section('body-class', 'page-company-edit-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-company-edit-v6.css') }}?v=005q" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-company-edit-v6.js') }}?v=005q" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
@php
    $profile = $company->profile;
    $profileImagePath = $profile?->profile_image_path;
    $identityImageUrl = $profileImagePath ? asset('storage/'.$profileImagePath) : ($company->logo_path ? asset('storage/'.$company->logo_path) : null);
    $identityImageKind = $profileImagePath ? 'profile' : ($company->logo_path ? 'logo' : 'empty');
    $latestFinancialSnapshot = $company->relationLoaded('financialSnapshots') ? $company->financialSnapshots->first() : null;
    $restoreDraft = request()->boolean('restore_draft') && $companyDraft;
    $draftPayload = $restoreDraft ? ($companyDraft->payload ?? []) : [];
    $readValue = static fn (string $key, mixed $fallback) => old($key, data_get($draftPayload, $key, $fallback));
    $asCsv = static function (mixed $value): string {
        if (is_array($value)) {
            return collect($value)->filter(fn ($item) => filled($item))->implode(', ');
        }
        return is_string($value) ? $value : '';
    };

    $baseValues = [
        'name' => $readValue('name', $company->name),
        'code' => $readValue('code', $company->code),
        'registration_number' => $readValue('registration_number', $company->registration_number),
        'email' => $readValue('email', $company->email),
        'phone' => $readValue('phone', $company->phone),
        'address' => $readValue('address', $company->address),
        'status' => $readValue('status', $company->status),
        'legal_name' => $readValue('legal_name', $profile?->legal_name),
        'company_type' => $readValue('company_type', $profile?->company_type),
        'year_established' => $readValue('year_established', $profile?->year_established),
        'website' => $readValue('website', $profile?->website),
        'hq_country' => $readValue('hq_country', $profile?->hq_country),
        'hq_city' => $readValue('hq_city', $profile?->hq_city),
        'description' => $readValue('description', $profile?->description),
        'data_confidence' => $readValue('data_confidence', $profile?->data_confidence ?? 'medium'),
        'categories_served' => $asCsv($readValue('categories_served', $profile?->categories_served ?? [])),
        'focus_markets' => $asCsv($readValue('focus_markets', $profile?->focus_markets ?? [])),
        'team_size' => $readValue('team_size', $profile?->team_size),
        'parent_company_name' => $readValue('parent_company_name', $profile?->parent_company_name),
        'parent_company_country' => $readValue('parent_company_country', $profile?->parent_company_country),
        'annual_revenue_usd' => $readValue('annual_revenue_usd', $profile?->annual_revenue_usd),
        'ebitda_margin' => $readValue('ebitda_margin', $profile?->ebitda_margin),
        'current_ratio' => $readValue('current_ratio', $profile?->current_ratio),
        'total_assets_usd' => $readValue('total_assets_usd', $profile?->total_assets_usd),
        'net_profit_usd' => $readValue('net_profit_usd', $profile?->net_profit_usd),
        'debt_to_equity' => $readValue('debt_to_equity', $profile?->debt_to_equity),
        'performance_score' => $readValue('performance_score', $profile?->performance_score),
        'last_reviewed_at' => $readValue('last_reviewed_at', optional($profile?->last_reviewed_at)->format('Y-m-d')),
        'financial_period_end' => $readValue('financial_period_end', optional($latestFinancialSnapshot?->period_end)->format('Y-m-d')),
    ];

    $filled = collect($baseValues)->filter(fn($value) => filled($value))->count();
    $completeness = (int) round(($filled / max(count($baseValues), 1)) * 100);

    $contacts = collect(old('contacts', data_get($draftPayload, 'contacts', $company->contacts->map(fn($item) => [
        'full_name' => $item->full_name,
        'job_title' => $item->job_title,
        'email' => $item->email,
        'phone' => $item->phone,
        'is_primary' => (bool) $item->is_primary,
    ])->all())));

    $regions = collect(old('regions', data_get($draftPayload, 'regions', $company->regions->map(fn($item) => [
        'country_name' => $item->country_name,
        'country_code' => $item->country_code,
        'region_name' => $item->region_name,
        'latitude' => $item->latitude,
        'longitude' => $item->longitude,
        'project_count' => $item->project_count,
        'is_headquarters' => (bool) $item->is_headquarters,
    ])->all())));

    $documents = collect(old('documents', data_get($draftPayload, 'documents', $dashboard['libraryDocuments']->map(fn($item) => [
        'category' => $item->category,
        'title' => $item->title,
        'file_name' => $item->file_name,
        'file_size_kb' => $item->file_size_kb,
        'issued_at' => optional($item->issued_at)->format('Y-m-d'),
        'expires_at' => optional($item->expires_at)->format('Y-m-d'),
        'status' => $item->status,
    ])->all())));

    $registrations = collect(old('registrations', data_get($draftPayload, 'registrations', $dashboard['registrations']->map(fn($item) => [
        'title' => $item->title,
        'reference_number' => $item->reference_number,
        'issuing_authority' => $item->issuing_authority,
        'issued_at' => optional($item->issued_at)->format('Y-m-d'),
        'expires_at' => optional($item->expires_at)->format('Y-m-d'),
        'status' => $item->status,
    ])->all())));

    $certifications = collect(old('certifications', data_get($draftPayload, 'certifications', $dashboard['certifications']->map(fn($item) => [
        'title' => $item->title,
        'reference_number' => $item->reference_number,
        'issuing_authority' => $item->issuing_authority,
        'issued_at' => optional($item->issued_at)->format('Y-m-d'),
        'expires_at' => optional($item->expires_at)->format('Y-m-d'),
        'status' => $item->status,
    ])->all())));

    $insurances = collect(old('insurances', data_get($draftPayload, 'insurances', $dashboard['insurances']->map(fn($item) => [
        'title' => $item->title,
        'reference_number' => $item->reference_number,
        'policy_provider' => $item->policy_provider,
        'coverage_amount_usd' => $item->coverage_amount_usd,
        'issued_at' => optional($item->issued_at)->format('Y-m-d'),
        'expires_at' => optional($item->expires_at)->format('Y-m-d'),
        'status' => $item->status,
    ])->all())));

    $money = static fn ($value) => filled($value) ? '$' . number_format((float) $value, abs($value) >= 100 ? 1 : 2) : 'Not recorded';
@endphp

<div class="nx5" data-nx5-editor data-nx5-draft-url="{{ route('companies.draft.store', $company) }}" data-nx5-edit-url="{{ route('companies.edit', $company) }}" data-nx5-restored-draft="{{ $restoreDraft ? '1' : '0' }}">
    <header class="nx5-head">
        <div class="nx5-head-main">
            <nav class="nx5-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('companies.index') }}">Companies</a><i>›</i>
                <a href="{{ route('companies.show', $company) }}">{{ $company->name }}</a><i>›</i>
                <span>Edit Company</span>
            </nav>
            <div class="nx5-title-row">
                <h2>Edit Company</h2>
                <span class="nx5-autosave" data-nx5-draft-state><i></i>
                    @if($companyDraft)
                        Draft saved {{ $companyDraft->updated_at?->diffForHumans() }}
                    @else
                        No saved draft
                    @endif
                </span>
                @if($companyDraft && ! $restoreDraft)
                    <a class="nx5-restore-draft" href="{{ route('companies.edit', ['company' => $company, 'restore_draft' => 1]) }}">Restore Draft</a>
                @endif
                <span class="nx5-unsaved" data-nx5-dirty-badge hidden>Unsaved changes</span>
            </div>
            <div class="nx5-subline">
                <span>Last updated: <b>{{ $company->updated_at?->format('M d, Y h:i A') ?? 'Not available' }}</b></span>
                <a href="{{ route('companies.show', $company) }}">View company record →</a>
            </div>
        </div>
        <div class="nx5-head-actions">
            <button class="nx5-btn nx5-btn-ghost" type="button" data-nx5-discard disabled>Discard Changes</button>
            <button class="nx5-btn nx5-btn-ghost" type="button" data-nx5-save-draft>Save Draft</button>
            <button class="nx5-btn nx5-btn-primary" type="submit" form="companyEditForm" data-nx5-save disabled>Save Changes</button>
        </div>
    </header>

    @if($errors->any())
        <section class="nx5-alert" role="alert">
            <strong>Company update requires attention</strong>
            <span>Review the server-side validation messages below.</span>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    @if(isset($companyExtensionReady) && ! $companyExtensionReady)
        <section class="nx5-alert nx5-alert-info" role="status">
            <strong>Advanced company profile storage is pending</strong>
            <span>The page remains available, but extended company fields will stay read-only until the latest database migrations are applied.</span>
        </section>
    @endif

    <form id="companyEditForm" method="POST" action="{{ route('companies.update', $company) }}" enctype="multipart/form-data" data-nx5-form>
        @csrf
        @method('PUT')

        <div class="nx5-layout">
            <main class="nx5-main">
                <section class="nx5-card nx5-profile" id="company-profile">
                    <header class="nx5-card-head">
                        <div>
                            <h3>Company Profile</h3>
                            <p>Core corporate identity, operational fit, and commercial profile.</p>
                        </div>
                        <div class="nx5-inline-metrics">
                            <span class="nx5-data-chip">Data confidence · {{ Str::headline($baseValues['data_confidence']) }}</span>
                            <span class="nx5-data-chip">Profile completeness · {{ $completeness }}%</span>
                        </div>
                    </header>

                    <div class="nx5-profile-top">
                        <aside class="nx5-identity-tile">
                            <div class="nx5-company-media is-{{ $identityImageKind }}" data-nx5-company-media>
                                <img
                                    src="{{ $identityImageUrl ?: '' }}"
                                    alt="{{ $company->name }} corporate media"
                                    data-nx5-profile-image-preview
                                    data-nx5-image-kind="{{ $identityImageKind }}"
                                    @if(!$identityImageUrl) hidden @endif
                                >
                                <div class="nx5-company-media-empty" data-nx5-profile-image-empty @if($identityImageUrl) hidden @endif>
                                    <span aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55"><path d="M4 19V8.5L12 4l8 4.5V19"/><path d="M8 19v-5h8v5M8 10h.01M12 10h.01M16 10h.01"/></svg>
                                    </span>
                                    <strong data-nx5-image-empty-title>No profile image</strong>
                                    <small data-nx5-image-empty-copy>Upload a corporate image or brand visual for this company.</small>
                                </div>
                                <div class="nx5-company-media-actions">
                                    <label class="nx5-media-action">
                                        <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" data-nx5-profile-image-input>
                                        <span>{{ $identityImageUrl ? 'Change Image' : 'Upload Image' }}</span>
                                    </label>
                                    <input type="hidden" name="remove_profile_image" value="0" data-nx5-profile-image-remove-value>
                                    <button type="button" class="nx5-media-action nx5-media-action-danger" data-nx5-profile-image-remove @if(!$profileImagePath) hidden @endif>Remove</button>
                                </div>
                                <div class="nx5-media-caption">
                                    <span>{{ $profileImagePath ? 'Company profile image' : ($company->logo_path ? 'Company logo fallback' : 'Corporate media') }}</span>
                                    <small>JPG, PNG, or WebP · max 5 MB</small>
                                </div>
                            </div>
                            <div class="nx5-identity-copy">
                                <small>Company profile</small>
                                <strong data-nx5-preview-name>{{ $baseValues['name'] }}</strong>
                                <span data-nx5-preview-code>{{ $baseValues['code'] }}</span>
                            </div>
                            <dl>
                                <div><dt>Record ID</dt><dd>#{{ $company->getKey() }}</dd></div>
                                <div><dt>Created</dt><dd>{{ $company->created_at?->format('M d, Y') ?? '—' }}</dd></div>
                                <div><dt>Status</dt><dd class="{{ $baseValues['status'] === 'active' ? 'is-good' : 'is-warn' }}" data-nx5-status-text>{{ ucfirst($baseValues['status']) }}</dd></div>
                            </dl>
                        </aside>

                        <div class="nx5-fields nx5-fields-profile">
                            <label class="nx5-field">
                                <span>Company Name <b>*</b></span>
                                <input type="text" name="name" value="{{ $baseValues['name'] }}" maxlength="150" required data-nx5-name>
                            </label>
                            <label class="nx5-field">
                                <span>Legal Name</span>
                                <input type="text" name="legal_name" value="{{ $baseValues['legal_name'] }}" maxlength="190">
                            </label>
                            <label class="nx5-field">
                                <span>Company Type</span>
                                <input type="text" name="company_type" value="{{ $baseValues['company_type'] }}" maxlength="100" placeholder="Private Limited Company">
                            </label>
                            <label class="nx5-field">
                                <span>Company Code <b>*</b></span>
                                <input type="text" name="code" value="{{ $baseValues['code'] }}" maxlength="30" required data-nx5-code class="nx5-mono">
                            </label>
                            <label class="nx5-field">
                                <span>Year Established</span>
                                <input type="number" name="year_established" value="{{ $baseValues['year_established'] }}" min="1900" max="{{ now()->year }}">
                            </label>
                            <label class="nx5-field">
                                <span>HQ Country</span>
                                <input type="text" name="hq_country" value="{{ $baseValues['hq_country'] }}" maxlength="100">
                            </label>
                            <label class="nx5-field">
                                <span>HQ City</span>
                                <input type="text" name="hq_city" value="{{ $baseValues['hq_city'] }}" maxlength="120">
                            </label>
                            <label class="nx5-field nx5-field-span-2">
                                <span>Website</span>
                                <input type="url" name="website" value="{{ $baseValues['website'] }}" maxlength="255" placeholder="https://">
                            </label>
                            <label class="nx5-field nx5-field-span-2">
                                <span>Company Email</span>
                                <input type="email" name="email" value="{{ $baseValues['email'] }}" maxlength="190">
                            </label>
                            <label class="nx5-field">
                                <span>Data Confidence</span>
                                <select name="data_confidence">
                                    @foreach(['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $key => $label)
                                        <option value="{{ $key }}" @selected($baseValues['data_confidence'] === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="nx5-fields nx5-fields-secondary">
                        <label class="nx5-field nx5-field-span-2">
                            <span>Company Description</span>
                            <textarea name="description" rows="4" maxlength="4000">{{ $baseValues['description'] }}</textarea>
                        </label>
                        <label class="nx5-field">
                            <span>Company Phone</span>
                            <input type="text" name="phone" value="{{ $baseValues['phone'] }}" maxlength="30">
                        </label>
                        <label class="nx5-field">
                            <span>Registration Number</span>
                            <input type="text" name="registration_number" value="{{ $baseValues['registration_number'] }}" maxlength="100">
                        </label>
                        <label class="nx5-field">
                            <span>Status <b>*</b></span>
                            <select name="status">
                                <option value="active" @selected($baseValues['status'] === 'active')>Active</option>
                                <option value="inactive" @selected($baseValues['status'] === 'inactive')>Inactive</option>
                            </select>
                        </label>
                        <label class="nx5-field nx5-field-span-2">
                            <span>Company Address</span>
                            <textarea name="address" rows="3" maxlength="2000">{{ $baseValues['address'] }}</textarea>
                        </label>
                    </div>
                </section>

                <div class="nx5-card-grid nx5-four nx5-profile-facts">
                    <section class="nx5-card nx5-pill-card">
                        <header class="nx5-card-head"><div><h3>Categories Served</h3><p>Operating categories and services.</p></div><span class="nx5-count-label" data-nx5-token-count="categories_served">0 selected</span></header>
                        <div class="nx5-token-editor" data-nx5-token-editor="categories_served">
                            <input type="hidden" name="categories_served" value="{{ $baseValues['categories_served'] }}" data-nx5-token-value>
                            <div class="nx5-token-list" data-nx5-token-list></div>
                            <div class="nx5-token-add"><input type="text" data-nx5-token-input placeholder="Add category"><button type="button" data-nx5-token-add>+ Add Category</button></div>
                        </div>
                    </section>
                    <section class="nx5-card nx5-pill-card">
                        <header class="nx5-card-head"><div><h3>Focus Markets</h3><p>Primary commercial market segments.</p></div><span class="nx5-count-label" data-nx5-token-count="focus_markets">0 selected</span></header>
                        <div class="nx5-token-editor" data-nx5-token-editor="focus_markets">
                            <input type="hidden" name="focus_markets" value="{{ $baseValues['focus_markets'] }}" data-nx5-token-value>
                            <div class="nx5-token-list" data-nx5-token-list></div>
                            <div class="nx5-token-add"><input type="text" data-nx5-token-input placeholder="Add market"><button type="button" data-nx5-token-add>+ Add Market</button></div>
                        </div>
                    </section>
                    <section class="nx5-card nx5-metric-card">
                        <header class="nx5-card-head"><div><h3>Team Size</h3><p>Current staffing estimate.</p></div></header>
                        <label class="nx5-inline-stat-edit"><span>Team Size</span><strong><input type="number" name="team_size" value="{{ $baseValues['team_size'] }}" min="1" max="200000"></strong><small>Employees</small></label>
                    </section>
                    <section class="nx5-card nx5-metric-card">
                        <header class="nx5-card-head"><div><h3>Parent Company</h3><p>Holding or ownership context.</p></div></header>
                        <div class="nx5-parent-compact">
                            <label><span>Parent</span><input type="text" name="parent_company_name" value="{{ $baseValues['parent_company_name'] }}" placeholder="Parent company name"></label>
                            <label><span>Country</span><input type="text" name="parent_company_country" value="{{ $baseValues['parent_company_country'] }}" placeholder="Country"></label>
                        </div>
                    </section>
                </div>

                <div class="nx5-card-grid nx5-three nx5-credential-grid" id="company-credentials">
                    <section class="nx5-card">
                        <header class="nx5-card-head"><div><h3>Registrations &amp; Licenses</h3><p>Legal registrations and permits.</p></div><button class="nx5-add-inline" type="button" data-nx5-add-row="registrations">+ Add Registration</button></header>
                        <div class="nx5-repeater nx5-credential-repeater" data-nx5-repeater="registrations" data-empty-label="No registrations recorded yet.">
                            @foreach($registrations as $index => $row)
                                @php
                                    $registrationTitle = $row['title'] ?? '';
                                    $registrationMeta = collect([$row['reference_number'] ?? null, filled($row['expires_at'] ?? null) ? 'Valid until '.\Illuminate\Support\Carbon::parse($row['expires_at'])->format('M d, Y') : null])->filter()->implode(' · ');
                                @endphp
                                <details class="nx5-credential-record" data-nx5-row @if(blank($registrationTitle)) open @endif>
                                    <summary><div><strong data-nx5-row-title>{{ $registrationTitle ?: 'New registration' }}</strong><small>{{ $registrationMeta ?: 'Registration details pending' }}</small></div><span class="nx5-record-status is-{{ $row['status'] ?? 'pending' }}">{{ Str::headline($row['status'] ?? 'pending') }}</span></summary>
                                    <div class="nx5-credential-editor nx5-repeat-fields nx5-repeat-grid-2">
                                        <label class="nx5-field"><span>Title</span><input type="text" name="registrations[{{ $index }}][title]" value="{{ $registrationTitle }}" data-nx5-title-source></label>
                                        <label class="nx5-field"><span>Reference</span><input type="text" name="registrations[{{ $index }}][reference_number]" value="{{ $row['reference_number'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Authority</span><input type="text" name="registrations[{{ $index }}][issuing_authority]" value="{{ $row['issuing_authority'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Status</span><select name="registrations[{{ $index }}][status]">@foreach(['verified','active','pending','expired'] as $status)<option value="{{ $status }}" @selected(($row['status'] ?? 'pending') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                                        <label class="nx5-field"><span>Issued</span><input type="date" name="registrations[{{ $index }}][issued_at]" value="{{ $row['issued_at'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Expires</span><input type="date" name="registrations[{{ $index }}][expires_at]" value="{{ $row['expires_at'] ?? '' }}"></label>
                                        <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>

                    <section class="nx5-card">
                        <header class="nx5-card-head"><div><h3>Certifications</h3><p>Standards, quality, and operational certifications.</p></div><button class="nx5-add-inline" type="button" data-nx5-add-row="certifications">+ Add Certification</button></header>
                        <div class="nx5-repeater nx5-credential-repeater" data-nx5-repeater="certifications" data-empty-label="No certifications recorded yet.">
                            @foreach($certifications as $index => $row)
                                @php
                                    $certificationTitle = $row['title'] ?? '';
                                    $certificationMeta = collect([$row['reference_number'] ?? null, filled($row['expires_at'] ?? null) ? 'Valid until '.\Illuminate\Support\Carbon::parse($row['expires_at'])->format('M d, Y') : null])->filter()->implode(' · ');
                                @endphp
                                <details class="nx5-credential-record" data-nx5-row @if(blank($certificationTitle)) open @endif>
                                    <summary><div><strong data-nx5-row-title>{{ $certificationTitle ?: 'New certification' }}</strong><small>{{ $certificationMeta ?: 'Certification details pending' }}</small></div><span class="nx5-record-status is-{{ $row['status'] ?? 'pending' }}">{{ Str::headline($row['status'] ?? 'pending') }}</span></summary>
                                    <div class="nx5-credential-editor nx5-repeat-fields nx5-repeat-grid-2">
                                        <label class="nx5-field"><span>Title</span><input type="text" name="certifications[{{ $index }}][title]" value="{{ $certificationTitle }}" data-nx5-title-source></label>
                                        <label class="nx5-field"><span>Reference</span><input type="text" name="certifications[{{ $index }}][reference_number]" value="{{ $row['reference_number'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Authority</span><input type="text" name="certifications[{{ $index }}][issuing_authority]" value="{{ $row['issuing_authority'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Status</span><select name="certifications[{{ $index }}][status]">@foreach(['verified','active','pending','expired'] as $status)<option value="{{ $status }}" @selected(($row['status'] ?? 'pending') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                                        <label class="nx5-field"><span>Issued</span><input type="date" name="certifications[{{ $index }}][issued_at]" value="{{ $row['issued_at'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Expires</span><input type="date" name="certifications[{{ $index }}][expires_at]" value="{{ $row['expires_at'] ?? '' }}"></label>
                                        <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>

                    <section class="nx5-card">
                        <header class="nx5-card-head"><div><h3>Insurance</h3><p>Liability, coverage, and protection policies.</p></div><button class="nx5-add-inline" type="button" data-nx5-add-row="insurances">+ Add Insurance</button></header>
                        <div class="nx5-repeater nx5-credential-repeater" data-nx5-repeater="insurances" data-empty-label="No insurance policies recorded yet.">
                            @foreach($insurances as $index => $row)
                                @php
                                    $insuranceTitle = $row['title'] ?? '';
                                    $insuranceCoverage = filled($row['coverage_amount_usd'] ?? null) ? '$'.number_format((float) $row['coverage_amount_usd'], 0) : null;
                                    $insuranceMeta = collect([$row['reference_number'] ?? null, $insuranceCoverage, filled($row['expires_at'] ?? null) ? 'Valid until '.\Illuminate\Support\Carbon::parse($row['expires_at'])->format('M d, Y') : null])->filter()->implode(' · ');
                                @endphp
                                <details class="nx5-credential-record" data-nx5-row @if(blank($insuranceTitle)) open @endif>
                                    <summary><div><strong data-nx5-row-title>{{ $insuranceTitle ?: 'New insurance policy' }}</strong><small>{{ $insuranceMeta ?: 'Insurance details pending' }}</small></div><span class="nx5-record-status is-{{ $row['status'] ?? 'pending' }}">{{ Str::headline($row['status'] ?? 'pending') }}</span></summary>
                                    <div class="nx5-credential-editor nx5-repeat-fields nx5-repeat-grid-2">
                                        <label class="nx5-field"><span>Policy</span><input type="text" name="insurances[{{ $index }}][title]" value="{{ $insuranceTitle }}" data-nx5-title-source></label>
                                        <label class="nx5-field"><span>Policy Number</span><input type="text" name="insurances[{{ $index }}][reference_number]" value="{{ $row['reference_number'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Provider</span><input type="text" name="insurances[{{ $index }}][policy_provider]" value="{{ $row['policy_provider'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Coverage (USD)</span><input type="number" step="0.01" name="insurances[{{ $index }}][coverage_amount_usd]" value="{{ $row['coverage_amount_usd'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Status</span><select name="insurances[{{ $index }}][status]">@foreach(['verified','active','pending','expired'] as $status)<option value="{{ $status }}" @selected(($row['status'] ?? 'pending') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                                        <label class="nx5-field"><span>Expires</span><input type="date" name="insurances[{{ $index }}][expires_at]" value="{{ $row['expires_at'] ?? '' }}"></label>
                                        <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                </div>

                <section class="nx5-card" id="company-financial">
                    <header class="nx5-card-head"><div><h3>Financial Indicators</h3><p>Latest available company financial profile.</p></div><div class="nx5-financial-head"><label><span>Period End</span><input type="date" name="financial_period_end" value="{{ $baseValues['financial_period_end'] }}"></label><span class="nx5-data-chip">{{ $latestFinancialSnapshot?->period_end?->format('Y') ? 'Latest FY'.$latestFinancialSnapshot->period_end->format('Y') : 'No financial history yet' }}</span></div></header>
                    <div class="nx5-financial-grid">
                        <label class="nx5-stat-box">
                            <span>Annual Revenue (USD)</span>
                            <strong>{{ $money($baseValues['annual_revenue_usd']) }}</strong>
                            <input type="number" step="0.01" name="annual_revenue_usd" value="{{ $baseValues['annual_revenue_usd'] }}">
                            <div class="nx5-mini-trend" data-nx5-spark data-values="{{ $dashboard['financialTrend']['annual_revenue_usd']->implode(',') }}"></div>
                        </label>
                        <label class="nx5-stat-box">
                            <span>EBITDA Margin</span>
                            <strong>{{ filled($baseValues['ebitda_margin']) ? $baseValues['ebitda_margin'].'%' : 'Not recorded' }}</strong>
                            <input type="number" step="0.01" name="ebitda_margin" value="{{ $baseValues['ebitda_margin'] }}">
                            <div class="nx5-mini-trend" data-nx5-spark data-values="{{ $dashboard['financialTrend']['ebitda_margin']->implode(',') }}"></div>
                        </label>
                        <label class="nx5-stat-box">
                            <span>Current Ratio</span>
                            <strong>{{ filled($baseValues['current_ratio']) ? $baseValues['current_ratio'].'x' : 'Not recorded' }}</strong>
                            <input type="number" step="0.01" name="current_ratio" value="{{ $baseValues['current_ratio'] }}">
                            <div class="nx5-mini-trend" data-nx5-spark data-values="{{ $dashboard['financialTrend']['current_ratio']->implode(',') }}"></div>
                        </label>
                        <label class="nx5-stat-box">
                            <span>Total Assets (USD)</span>
                            <strong>{{ $money($baseValues['total_assets_usd']) }}</strong>
                            <input type="number" step="0.01" name="total_assets_usd" value="{{ $baseValues['total_assets_usd'] }}">
                            <div class="nx5-mini-trend" data-nx5-spark data-values="{{ $dashboard['financialTrend']['total_assets_usd']->implode(',') }}"></div>
                        </label>
                        <label class="nx5-stat-box">
                            <span>Net Profit (USD)</span>
                            <strong>{{ $money($baseValues['net_profit_usd']) }}</strong>
                            <input type="number" step="0.01" name="net_profit_usd" value="{{ $baseValues['net_profit_usd'] }}">
                            <div class="nx5-mini-trend" data-nx5-spark data-values="{{ $dashboard['financialTrend']['net_profit_usd']->implode(',') }}"></div>
                        </label>
                        <label class="nx5-stat-box">
                            <span>Debt to Equity</span>
                            <strong>{{ filled($baseValues['debt_to_equity']) ? $baseValues['debt_to_equity'].'x' : 'Not recorded' }}</strong>
                            <input type="number" step="0.01" name="debt_to_equity" value="{{ $baseValues['debt_to_equity'] }}">
                            <div class="nx5-mini-trend" data-nx5-spark data-values="{{ $dashboard['financialTrend']['debt_to_equity']->implode(',') }}"></div>
                        </label>
                    </div>
                    <div class="nx5-fin-docs">
                        <div class="nx5-fin-docs-label">Financial Documents</div>
                        @forelse($dashboard['financialDocuments']->take(3) as $document)
                            <article class="nx5-document-chip">
                                <span class="nx5-doc-icon">PDF</span>
                                <div><strong>{{ $document->title }}</strong><small>{{ $document->file_name ?: 'Document record' }} · {{ $document->issued_at?->format('M d, Y') ?? 'No issue date' }}</small></div>
                            </article>
                        @empty
                            <article class="nx5-document-chip is-empty"><div><strong>No financial documents recorded</strong><small>Add a Financial category record in Document Library.</small></div></article>
                        @endforelse
                        <button class="nx5-document-upload" type="button" data-nx5-add-row="documents">+ Add financial document</button>
                    </div>
                </section>

                <div class="nx5-card-grid nx5-two-uneven nx5-contacts-regions-row">
                    <section class="nx5-card nx5-contacts-card" id="company-contacts">
                        <header class="nx5-card-head"><div><h3>Key Contacts</h3><p>Operational and executive contact coverage.</p></div><button class="nx5-add-inline" type="button" data-nx5-add-row="contacts">+ Add Contact</button></header>
                        <div class="nx5-repeater nx5-contact-repeater" data-nx5-repeater="contacts" data-empty-label="No contacts recorded yet. Add the primary operational contact.">
                            @foreach($contacts as $index => $row)
                                @php
                                    $contactName = $row['full_name'] ?? '';
                                    $contactRole = $row['job_title'] ?? '';
                                    $contactMeta = collect([$row['email'] ?? null, $row['phone'] ?? null])->filter()->implode(' · ');
                                    $contactInitials = collect(preg_split('/\s+/', trim($contactName)) ?: [])->filter()->take(2)->map(fn($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
                                @endphp
                                <details class="nx5-contact-record" data-nx5-row @if(blank($contactName)) open @endif>
                                    <summary>
                                        <span class="nx5-contact-avatar" data-nx5-contact-avatar>{{ $contactInitials ?: 'CO' }}</span>
                                        <div class="nx5-contact-summary-copy">
                                            <strong data-nx5-row-title>{{ $contactName ?: 'New contact' }}</strong>
                                            <small data-nx5-contact-role>{{ $contactRole ?: 'Role not recorded' }}</small>
                                        </div>
                                        <span class="nx5-contact-summary-meta" data-nx5-contact-meta>{{ $contactMeta ?: 'Contact details pending' }}</span>
                                        <span class="nx5-record-status {{ ($row['is_primary'] ?? false) ? 'is-active' : 'is-neutral' }}" data-nx5-contact-primary>{{ ($row['is_primary'] ?? false) ? 'Primary' : 'Contact' }}</span>
                                    </summary>
                                    <div class="nx5-contact-editor nx5-repeat-fields nx5-contact-fields">
                                        <label class="nx5-field"><span>Full Name</span><input type="text" name="contacts[{{ $index }}][full_name]" value="{{ $contactName }}" data-nx5-title-source></label>
                                        <label class="nx5-field"><span>Role</span><input type="text" name="contacts[{{ $index }}][job_title]" value="{{ $contactRole }}"></label>
                                        <label class="nx5-field"><span>Email</span><input type="email" name="contacts[{{ $index }}][email]" value="{{ $row['email'] ?? '' }}"></label>
                                        <label class="nx5-field"><span>Phone</span><input type="text" name="contacts[{{ $index }}][phone]" value="{{ $row['phone'] ?? '' }}"></label>
                                        <label class="nx5-checkbox-field"><input type="checkbox" name="contacts[{{ $index }}][is_primary]" value="1" @checked((bool) ($row['is_primary'] ?? false))><span>Primary contact</span></label>
                                        <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>

                    <section class="nx5-card nx5-regions-card" id="company-regions">
                        <header class="nx5-card-head"><div><h3>Regions Served</h3><p>Jurisdictions and commercial coverage.</p></div><button class="nx5-add-inline" type="button" data-nx5-add-row="regions">+ Add Region</button></header>
                        <div class="nx5-region-stage">
                            <div class="nx5-region-visual">
                                <div class="nx5-region-map" data-nx5-region-map data-geojson-url="{{ asset('data/indonesia-country.geojson') }}" data-region-anchors-url="{{ asset('data/indonesia-region-anchors.json') }}"></div>
                                <div class="nx5-region-map-status" data-nx5-region-map-status><span class="is-loading"></span><strong>Loading regional map</strong><small>Preparing Indonesia coverage and company locations.</small></div>
                                <div class="nx5-region-map-legend" data-nx5-region-map-legend hidden></div>
                                <div class="nx5-region-map-empty" data-nx5-region-map-empty hidden></div>
                            </div>
                            <div class="nx5-repeater nx5-region-repeater" data-nx5-repeater="regions" data-empty-label="No served regions recorded yet. Add a region to start mapping coverage.">
                                @foreach($regions as $index => $row)
                                    @php
                                        $regionName = $row['region_name'] ?? '';
                                        $regionCountry = $row['country_name'] ?? '';
                                        $regionProjects = (int) ($row['project_count'] ?? 0);
                                        $regionExact = filled($row['latitude'] ?? null) && filled($row['longitude'] ?? null);
                                    @endphp
                                    <details class="nx5-region-record" data-nx5-row @if(blank($regionName) && blank($regionCountry)) open @endif>
                                        <summary>
                                            <span class="nx5-region-dot {{ $regionExact ? 'is-exact' : 'is-reference' }}" data-nx5-region-dot></span>
                                            <div>
                                                <strong data-nx5-row-title>{{ $regionName ?: ($regionCountry ?: 'New served region') }}</strong>
                                                <small data-nx5-region-country>{{ $regionCountry ?: 'Country not recorded' }}</small>
                                            </div>
                                            <span class="nx5-region-summary-meta" data-nx5-region-meta>{{ $regionProjects }} {{ Str::plural('project', $regionProjects) }} · {{ $regionExact ? 'Exact coordinates' : 'Regional reference' }}</span>
                                            <span class="nx5-record-status {{ ($row['is_headquarters'] ?? false) ? 'is-active' : 'is-neutral' }}" data-nx5-region-hq>{{ ($row['is_headquarters'] ?? false) ? 'HQ' : 'Region' }}</span>
                                        </summary>
                                        <div class="nx5-region-editor nx5-repeat-fields nx5-region-fields">
                                            <label class="nx5-field"><span>Country</span><input type="text" name="regions[{{ $index }}][country_name]" value="{{ $regionCountry }}"></label>
                                            <label class="nx5-field"><span>Region / City</span><input type="text" name="regions[{{ $index }}][region_name]" value="{{ $regionName }}" data-nx5-title-source></label>
                                            <label class="nx5-field"><span>Projects</span><input type="number" name="regions[{{ $index }}][project_count]" value="{{ $row['project_count'] ?? '' }}" min="0"></label>
                                            <label class="nx5-checkbox-field"><input type="checkbox" name="regions[{{ $index }}][is_headquarters]" value="1" @checked((bool) ($row['is_headquarters'] ?? false))><span>Headquarters</span></label>
                                            <details class="nx5-region-advanced" @if($regionExact) open @endif>
                                                <summary>Map coordinates</summary>
                                                <div>
                                                    <label class="nx5-field"><span>ISO3</span><input type="text" name="regions[{{ $index }}][country_code]" value="{{ $row['country_code'] ?? '' }}" maxlength="3" placeholder="IDN"></label>
                                                    <label class="nx5-field"><span>Latitude</span><input type="number" step="0.0000001" name="regions[{{ $index }}][latitude]" value="{{ $row['latitude'] ?? '' }}"></label>
                                                    <label class="nx5-field"><span>Longitude</span><input type="number" step="0.0000001" name="regions[{{ $index }}][longitude]" value="{{ $row['longitude'] ?? '' }}"></label>
                                                </div>
                                            </details>
                                            <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>

                <div class="nx5-card-grid nx5-performance-projects-row">
                    <section class="nx5-card nx5-performance-card" id="company-performance">
                        <header class="nx5-card-head"><div><h3>Company Performance</h3><p>Operational and record stewardship signals.</p></div><span class="nx5-data-chip">Data confidence · {{ Str::headline($baseValues['data_confidence']) }}</span></header>
                        <div class="nx5-performance-summary nx5-performance-summary-compact">
                            <div class="nx5-performance-viz">
                                <div class="nx5-performance-radar" data-nx5-performance-radar data-completeness="{{ $dashboard['profileHealth']['completeness'] }}" data-accuracy="{{ $dashboard['profileHealth']['accuracy'] }}" data-compliance="{{ $dashboard['profileHealth']['compliance'] }}" data-responsiveness="{{ $dashboard['profileHealth']['responsiveness'] }}"></div>
                            </div>
                            <div class="nx5-performance-score"><span>Overall Score</span><strong>{{ filled($baseValues['performance_score']) ? $baseValues['performance_score'] : $dashboard['profileHealth']['score'] }}</strong><small>/100</small></div>
                            <div class="nx5-performance-kpis">
                                <div><span>Performance</span><strong>{{ filled($baseValues['performance_score']) ? $baseValues['performance_score'] : $dashboard['profileHealth']['score'] }}<small>/100</small></strong></div>
                                <div><span>Members</span><strong>{{ $company->active_memberships_count }}</strong></div>
                                <div><span>Customers</span><strong>{{ $company->customers_count }}</strong></div>
                                <div><span>Projects</span><strong>{{ $company->projects_count }}</strong></div>
                            </div>
                            <details class="nx5-compact-editor">
                                <summary>Edit performance data</summary>
                                <div>
                                    <label class="nx5-field"><span>Performance Score</span><input type="number" min="0" max="100" name="performance_score" value="{{ $baseValues['performance_score'] }}"></label>
                                    <label class="nx5-field"><span>Last Reviewed</span><input type="date" name="last_reviewed_at" value="{{ $baseValues['last_reviewed_at'] }}"></label>
                                </div>
                            </details>
                        </div>
                    </section>

                    <section class="nx5-card nx5-projects-card" id="company-projects">
                        <header class="nx5-card-head"><div><h3>Linked Projects ({{ $recentProjects->count() }})</h3><p>Current company portfolio and operational load.</p></div><span class="nx5-data-chip">Live portfolio</span></header>
                        <div class="nx5-table-shell">
                            <table class="nx5-table">
                                <thead><tr><th>Project</th><th>Customer</th><th>System Type</th><th>Location</th><th>Status</th><th>Updated</th></tr></thead>
                                <tbody>
                                @forelse($recentProjects as $project)
                                    <tr>
                                        <td><strong>{{ $project->name }}</strong><small>{{ $project->project_number }}</small></td>
                                        <td>{{ $project->customer?->name ?? 'No customer' }}</td>
                                        <td>{{ $project->proposed_system ?: 'Not defined' }}</td>
                                        <td>{{ collect([$project->city, $project->province])->filter()->implode(', ') ?: 'Not defined' }}</td>
                                        <td><span class="nx5-pill">{{ $project->status_label ?? Str::headline((string) $project->status) }}</span></td>
                                        <td>{{ $project->updated_at?->format('M d, Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="nx5-empty-cell">No active projects are linked to this company yet.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <section class="nx5-card nx5-document-library-card" id="company-documents">
                    <header class="nx5-card-head"><div><h3>Document Library</h3><p>General, financial, legal, and technical records.</p></div><div class="nx5-inline-metrics"><span class="nx5-data-chip">{{ $dashboard['libraryDocuments']->count() }} records</span><button class="nx5-add-inline" type="button" data-nx5-add-row="documents">+ Add Document</button></div></header>
                    <div class="nx5-repeater nx5-document-repeater nx5-document-library-grid" data-nx5-repeater="documents" data-empty-label="No documents recorded yet. Add a governed document record when available.">
                        @foreach($documents as $index => $row)
                            @php
                                $docTitle = $row['title'] ?? '';
                                $docFile = $row['file_name'] ?? '';
                                $docSize = filled($row['file_size_kb'] ?? null) ? number_format(((float) $row['file_size_kb']) / 1024, 1).' MB' : 'Size not recorded';
                                $docIssued = filled($row['issued_at'] ?? null) ? \Illuminate\Support\Carbon::parse($row['issued_at'])->format('M d, Y') : 'Date not recorded';
                            @endphp
                            <details class="nx5-document-record" data-nx5-row @if(blank($docTitle)) open @endif>
                                <summary>
                                    <span class="nx5-doc-icon">PDF</span>
                                    <div><strong data-nx5-row-title>{{ $docTitle ?: 'New document record' }}</strong><small>{{ $docFile ?: Str::headline($row['category'] ?? 'general') }} · {{ $docSize }} · {{ $docIssued }}</small></div>
                                    <span class="nx5-doc-status">{{ Str::headline($row['status'] ?? 'draft') }}</span>
                                </summary>
                                <div class="nx5-document-editor nx5-repeat-fields nx5-repeat-grid-3">
                                    <label class="nx5-field"><span>Document Title</span><input type="text" name="documents[{{ $index }}][title]" value="{{ $docTitle }}" data-nx5-title-source></label>
                                    <label class="nx5-field"><span>Category</span><select name="documents[{{ $index }}][category]">@foreach(['general','financial','legal','technical'] as $category)<option value="{{ $category }}" @selected(($row['category'] ?? 'general') === $category)>{{ Str::headline($category) }}</option>@endforeach</select></label>
                                    <label class="nx5-field"><span>Status</span><select name="documents[{{ $index }}][status]">@foreach(['draft','active','archived'] as $status)<option value="{{ $status }}" @selected(($row['status'] ?? 'draft') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                                    <label class="nx5-field"><span>File Name</span><input type="text" name="documents[{{ $index }}][file_name]" value="{{ $docFile }}"></label>
                                    <label class="nx5-field"><span>Size (KB)</span><input type="number" name="documents[{{ $index }}][file_size_kb]" value="{{ $row['file_size_kb'] ?? '' }}" min="0"></label>
                                    <label class="nx5-field"><span>Issued</span><input type="date" name="documents[{{ $index }}][issued_at]" value="{{ $row['issued_at'] ?? '' }}"></label>
                                    <label class="nx5-field"><span>Expires</span><input type="date" name="documents[{{ $index }}][expires_at]" value="{{ $row['expires_at'] ?? '' }}"></label>
                                    <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
                                </div>
                            </details>
                        @endforeach
                        <button class="nx5-document-upload-card" type="button" data-nx5-add-row="documents"><span>＋</span><strong>Add Document</strong><small>Create a governed document record</small></button>
                    </div>
                    <div class="nx5-provenance-list nx5-provenance-inline">
                        <div><span>Created by</span><strong>{{ $company->creator?->name ?? 'System' }}</strong></div>
                        <div><span>Created at</span><strong>{{ $company->created_at?->format('M d, Y h:i A') ?? '—' }}</strong></div>
                        <div><span>Updated at</span><strong>{{ $company->updated_at?->format('M d, Y h:i A') ?? '—' }}</strong></div>
                        <div><span>Workspace</span><strong>{{ ucfirst($baseValues['status']) }}</strong></div>
                    </div>
                </section>
            </main>

            <aside class="nx5-rail">
                <section class="nx5-card nx5-rail-card nx5-health-card">
                    <header class="nx5-card-head"><div><h3>Profile Health</h3><p>Composite quality signal.</p></div></header>
                    <div class="nx5-health-gauge" style="--score: {{ $dashboard['profileHealth']['score'] }}">
                        <div>
                            <strong>{{ $dashboard['profileHealth']['score'] }}</strong>
                            <span>/100</span>
                            <em>{{ $dashboard['profileHealth']['score'] >= 85 ? 'Very Good' : ($dashboard['profileHealth']['score'] >= 70 ? 'Good' : 'Needs work') }}</em>
                        </div>
                    </div>
                    <ul class="nx5-health-bars">
                        @foreach(['Completeness' => $dashboard['profileHealth']['completeness'], 'Accuracy' => $dashboard['profileHealth']['accuracy'], 'Compliance' => $dashboard['profileHealth']['compliance'], 'Responsiveness' => $dashboard['profileHealth']['responsiveness']] as $label => $score)
                            <li><span>{{ $label }}</span><div><i style="width: {{ $score }}%"></i></div><b>{{ $score }}%</b></li>
                        @endforeach
                    </ul>
                    <small class="nx5-footnote">Last review: {{ $company->updated_at?->format('M d, Y') ?? 'Not available' }}</small>
                </section>

                <section class="nx5-card nx5-rail-card">
                    <header class="nx5-card-head"><div><h3>Compliance &amp; Risk</h3><p>Credential watchlist.</p></div></header>
                    <ul class="nx5-rail-list">
                        @forelse($dashboard['complianceItems'] as $item)
                            <li>
                                <span class="nx5-tone nx5-tone-{{ $item['tone'] }}"></span>
                                <div><strong>{{ $item['title'] }}</strong><small>{{ $item['meta'] }}</small></div>
                                <b>{{ $item['status'] }}</b>
                            </li>
                        @empty
                            <li class="is-empty">No credential records available.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="nx5-card nx5-rail-card">
                    <header class="nx5-card-head"><div><h3>Pending Reviews</h3><p>Records needing action.</p></div></header>
                    <ul class="nx5-rail-list">
                        @forelse($dashboard['pendingReviews'] as $item)
                            <li>
                                <span class="nx5-tone nx5-tone-{{ $item['tone'] }}"></span>
                                <div><strong>{{ $item['title'] }}</strong><small>Review queue</small></div>
                                <b>{{ $item['status'] }}</b>
                            </li>
                        @empty
                            <li class="is-empty">No pending reviews at the moment.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="nx5-card nx5-rail-card">
                    <header class="nx5-card-head"><div><h3>Suggested Actions</h3><p>Improvement opportunities.</p></div></header>
                    <ul class="nx5-action-list">
                        @forelse($dashboard['suggestedActions'] as $item)
                            <li>
                                <div><strong>{{ $item['title'] }}</strong><p>{{ $item['copy'] }}</p></div>
                                <button type="button" data-nx5-jump-target="{{ $item['target'] ?? '' }}">{{ $item['action'] }}</button>
                            </li>
                        @empty
                            <li class="is-empty">No guided actions are required at the moment.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="nx5-card nx5-rail-card">
                    <header class="nx5-card-head"><div><h3>Recent Activity</h3><p>Latest profile-related events.</p></div></header>
                    <ul class="nx5-activity-list">
                        @foreach($dashboard['recentActivity'] as $item)
                            <li>
                                <span></span>
                                <div><strong>{{ $item['title'] }}</strong><small>{{ $item['meta'] }}</small></div>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="nx5-card nx5-rail-card">
                    <header class="nx5-card-head"><div><h3>Data Confidence Guide</h3><p>Quality label reference.</p></div></header>
                    <ul class="nx5-guide-list">
                        <li><span class="is-high">High</span><small>Data is current and verified.</small></li>
                        <li><span class="is-medium">Medium</span><small>Data is partially verified.</small></li>
                        <li><span class="is-low">Low</span><small>Data is pending validation.</small></li>
                    </ul>
                </section>

                <section class="nx5-card nx5-rail-card nx5-rail-anchor" aria-label="Company edit workspace navigator">
                    <header class="nx5-card-head"><div><h3>Workspace Navigator</h3><p>Jump to governed profile sections.</p></div></header>
                    <div class="nx5-record-coverage">
                        <div><span>Credentials</span><strong>{{ number_format($registrations->count() + $certifications->count() + $insurances->count()) }}</strong></div>
                        <div><span>Contacts</span><strong>{{ number_format($contacts->count()) }}</strong></div>
                        <div><span>Regions</span><strong>{{ number_format($regions->count()) }}</strong></div>
                        <div><span>Documents</span><strong>{{ number_format($documents->count()) }}</strong></div>
                    </div>
                    <nav class="nx5-workspace-nav" aria-label="Edit company sections">
                        <a href="#company-profile"><span>Company Profile</span><small>Identity and legal record</small></a>
                        <a href="#company-credentials"><span>Credentials</span><small>Registration, certification, insurance</small></a>
                        <a href="#company-financial"><span>Financial Indicators</span><small>Persisted financial snapshot</small></a>
                        <a href="#company-contacts"><span>Contacts</span><small>Operational contact coverage</small></a>
                        <a href="#company-regions"><span>Regions Served</span><small>Service coverage references</small></a>
                        <a href="#company-performance"><span>Performance</span><small>Record stewardship signals</small></a>
                        <a href="#company-projects"><span>Linked Projects</span><small>{{ number_format($recentProjects->count()) }} current records</small></a>
                        <a href="#company-documents"><span>Document Library</span><small>{{ number_format($dashboard['libraryDocuments']->count()) }} governed records</small></a>
                    </nav>
                </section>
            </aside>
        </div>
    </form>

    <template data-nx5-template="registrations">
        <details class="nx5-credential-record" data-nx5-row open>
            <summary><div><strong data-nx5-row-title>New registration</strong><small>Registration details pending</small></div><span class="nx5-record-status is-pending">Pending</span></summary>
            <div class="nx5-credential-editor nx5-repeat-fields nx5-repeat-grid-2">
                <label class="nx5-field"><span>Title</span><input type="text" name="__NAME__[title]" data-nx5-title-source></label>
                <label class="nx5-field"><span>Reference</span><input type="text" name="__NAME__[reference_number]"></label>
                <label class="nx5-field"><span>Authority</span><input type="text" name="__NAME__[issuing_authority]"></label>
                <label class="nx5-field"><span>Status</span><select name="__NAME__[status]"><option value="verified">Verified</option><option value="active">Active</option><option value="pending" selected>Pending</option><option value="expired">Expired</option></select></label>
                <label class="nx5-field"><span>Issued</span><input type="date" name="__NAME__[issued_at]"></label>
                <label class="nx5-field"><span>Expires</span><input type="date" name="__NAME__[expires_at]"></label>
                <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
            </div>
        </details>
    </template>

    <template data-nx5-template="certifications">
        <details class="nx5-credential-record" data-nx5-row open>
            <summary><div><strong data-nx5-row-title>New certification</strong><small>Certification details pending</small></div><span class="nx5-record-status is-pending">Pending</span></summary>
            <div class="nx5-credential-editor nx5-repeat-fields nx5-repeat-grid-2">
                <label class="nx5-field"><span>Title</span><input type="text" name="__NAME__[title]" data-nx5-title-source></label>
                <label class="nx5-field"><span>Reference</span><input type="text" name="__NAME__[reference_number]"></label>
                <label class="nx5-field"><span>Authority</span><input type="text" name="__NAME__[issuing_authority]"></label>
                <label class="nx5-field"><span>Status</span><select name="__NAME__[status]"><option value="verified">Verified</option><option value="active">Active</option><option value="pending" selected>Pending</option><option value="expired">Expired</option></select></label>
                <label class="nx5-field"><span>Issued</span><input type="date" name="__NAME__[issued_at]"></label>
                <label class="nx5-field"><span>Expires</span><input type="date" name="__NAME__[expires_at]"></label>
                <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
            </div>
        </details>
    </template>

    <template data-nx5-template="insurances">
        <details class="nx5-credential-record" data-nx5-row open>
            <summary><div><strong data-nx5-row-title>New insurance policy</strong><small>Insurance details pending</small></div><span class="nx5-record-status is-pending">Pending</span></summary>
            <div class="nx5-credential-editor nx5-repeat-fields nx5-repeat-grid-2">
                <label class="nx5-field"><span>Policy</span><input type="text" name="__NAME__[title]" data-nx5-title-source></label>
                <label class="nx5-field"><span>Policy Number</span><input type="text" name="__NAME__[reference_number]"></label>
                <label class="nx5-field"><span>Provider</span><input type="text" name="__NAME__[policy_provider]"></label>
                <label class="nx5-field"><span>Coverage (USD)</span><input type="number" step="0.01" name="__NAME__[coverage_amount_usd]"></label>
                <label class="nx5-field"><span>Status</span><select name="__NAME__[status]"><option value="verified">Verified</option><option value="active">Active</option><option value="pending" selected>Pending</option><option value="expired">Expired</option></select></label>
                <label class="nx5-field"><span>Expires</span><input type="date" name="__NAME__[expires_at]"></label>
                <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
            </div>
        </details>
    </template>

    <template data-nx5-template="contacts">
        <details class="nx5-contact-record" data-nx5-row open>
            <summary>
                <span class="nx5-contact-avatar" data-nx5-contact-avatar>CO</span>
                <div class="nx5-contact-summary-copy"><strong data-nx5-row-title>New contact</strong><small data-nx5-contact-role>Role not recorded</small></div>
                <span class="nx5-contact-summary-meta" data-nx5-contact-meta>Contact details pending</span>
                <span class="nx5-record-status is-neutral" data-nx5-contact-primary>Contact</span>
            </summary>
            <div class="nx5-contact-editor nx5-repeat-fields nx5-contact-fields">
                <label class="nx5-field"><span>Full Name</span><input type="text" name="__NAME__[full_name]" data-nx5-title-source></label>
                <label class="nx5-field"><span>Role</span><input type="text" name="__NAME__[job_title]"></label>
                <label class="nx5-field"><span>Email</span><input type="email" name="__NAME__[email]"></label>
                <label class="nx5-field"><span>Phone</span><input type="text" name="__NAME__[phone]"></label>
                <label class="nx5-checkbox-field"><input type="checkbox" name="__NAME__[is_primary]" value="1"><span>Primary contact</span></label>
                <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
            </div>
        </details>
    </template>

    <template data-nx5-template="regions">
        <details class="nx5-region-record" data-nx5-row open>
            <summary>
                <span class="nx5-region-dot is-reference" data-nx5-region-dot></span>
                <div><strong data-nx5-row-title>New served region</strong><small data-nx5-region-country>Country not recorded</small></div>
                <span class="nx5-region-summary-meta" data-nx5-region-meta>0 projects · Regional reference</span>
                <span class="nx5-record-status is-neutral" data-nx5-region-hq>Region</span>
            </summary>
            <div class="nx5-region-editor nx5-repeat-fields nx5-region-fields">
                <label class="nx5-field"><span>Country</span><input type="text" name="__NAME__[country_name]"></label>
                <label class="nx5-field"><span>Region / City</span><input type="text" name="__NAME__[region_name]" data-nx5-title-source></label>
                <label class="nx5-field"><span>Projects</span><input type="number" name="__NAME__[project_count]" min="0"></label>
                <label class="nx5-checkbox-field"><input type="checkbox" name="__NAME__[is_headquarters]" value="1"><span>Headquarters</span></label>
                <details class="nx5-region-advanced">
                    <summary>Map coordinates</summary>
                    <div>
                        <label class="nx5-field"><span>ISO3</span><input type="text" name="__NAME__[country_code]" maxlength="3" placeholder="IDN"></label>
                        <label class="nx5-field"><span>Latitude</span><input type="number" step="0.0000001" name="__NAME__[latitude]"></label>
                        <label class="nx5-field"><span>Longitude</span><input type="number" step="0.0000001" name="__NAME__[longitude]"></label>
                    </div>
                </details>
                <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
            </div>
        </details>
    </template>

    <template data-nx5-template="documents">
        <details class="nx5-document-record" data-nx5-row open>
            <summary>
                <span class="nx5-doc-icon">PDF</span>
                <div><strong data-nx5-row-title>New document record</strong><small>Draft · metadata pending</small></div>
                <span class="nx5-doc-status">Draft</span>
            </summary>
            <div class="nx5-document-editor nx5-repeat-fields nx5-repeat-grid-3">
                <label class="nx5-field"><span>Document Title</span><input type="text" name="__NAME__[title]" data-nx5-title-source></label>
                <label class="nx5-field"><span>Category</span><select name="__NAME__[category]"><option value="general">General</option><option value="financial">Financial</option><option value="legal">Legal</option><option value="technical">Technical</option></select></label>
                <label class="nx5-field"><span>Status</span><select name="__NAME__[status]"><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></label>
                <label class="nx5-field"><span>File Name</span><input type="text" name="__NAME__[file_name]"></label>
                <label class="nx5-field"><span>Size (KB)</span><input type="number" name="__NAME__[file_size_kb]" min="0"></label>
                <label class="nx5-field"><span>Issued</span><input type="date" name="__NAME__[issued_at]"></label>
                <label class="nx5-field"><span>Expires</span><input type="date" name="__NAME__[expires_at]"></label>
                <button class="nx5-row-remove" type="button" data-nx5-remove-row>Remove</button>
            </div>
        </details>
    </template>
</div>
@endsection
