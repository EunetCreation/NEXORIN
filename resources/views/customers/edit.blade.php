@extends('layouts.app')

@section('title', 'Edit Customer')
@section('page-eyebrow', 'Customer / CRM governance')
@section('page-heading', 'Edit Customer')
@section('force-theme', 'dark')
@section('body-class', 'page-customer-edit-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-customer-edit-v6.css') }}?v=009lux6" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-customer-edit-v6.js') }}?v=009lux6" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
@php
    $profile = $customer->profile;
    $value = static function (string $key, mixed $default = '') use ($customer, $profile) {
        $fallback = match ($key) {
            'name','type','email','phone','address','city','province','postal_code','notes','portal_user_id' => data_get($customer, $key, $default),
            default => data_get($profile, $key, $default),
        };
        return old($key, $fallback);
    };

    $contacts = old('contacts', $customerCrmReady ? $customer->contacts->sortBy('sort_order')->values()->map(fn ($contact) => [
        'id' => $contact->id, 'full_name' => $contact->full_name, 'job_title' => $contact->job_title,
        'email' => $contact->email, 'phone' => $contact->phone, 'is_primary' => $contact->is_primary, '_remove' => false,
    ])->all() : []);
    if (! is_array($contacts)) $contacts = [];

    $opportunities = old('opportunities', $customerCrmReady ? $customer->opportunities->where('status', 'open')->values()->map(fn ($opportunity) => [
        'id' => $opportunity->id, 'name' => $opportunity->name, 'opportunity_type' => $opportunity->opportunity_type,
        'estimated_value' => $opportunity->estimated_value, 'currency_code' => $opportunity->currency_code,
        'probability_pct' => $opportunity->probability_pct, 'expected_close_date' => $opportunity->expected_close_date?->format('Y-m-d'),
    ])->all() : []);
    if (! is_array($opportunities)) $opportunities = [];

    $industryTags = old('industry_tags', $profile?->industry_tags ?? []);
    if (is_array($industryTags)) $industryTags = implode(', ', $industryTags);
    $operatingCountries = old('operating_countries', $profile?->operating_countries ?? []);
    if (is_array($operatingCountries)) $operatingCountries = implode(', ', $operatingCountries);

    $parentAssessment = old('parent_company_assessment', $profile?->parent_company_assessment ?? 'not_assessed');
    $opportunityAssessment = old('opportunity_assessment', $profile?->opportunity_assessment ?? 'not_assessed');
    $openOpportunityCount = $customerCrmReady ? (int) ($customer->open_opportunities_count ?? $customer->opportunities->where('status', 'open')->count()) : 0;
    $documentCount = $customerCrmReady ? (int) ($customer->documents_count ?? $customer->documents->count()) : 0;
    $hasCustomerDocuments = $customerCrmReady && $customer->documents->isNotEmpty();
    $documentCategoryLabels = [
        'incorporation' => 'Certificate of Incorporation',
        'tax_registration' => 'Tax Registration',
        'financial_statements' => 'Financial Statements',
        'company_profile' => 'Company Profile',
    ];

    $readinessChecks = [
        ['Identity', filled($customer->name) && filled($customer->type)],
        ['Contact', filled($customer->email) || filled($customer->phone) || count($contacts) > 0],
        ['Industry', filled($profile?->primary_industry)],
        ['Geography', filled($customer->city) || filled($profile?->hq_country)],
        ['Ownership', filled($profile?->account_owner_user_id)],
        ['Commercial context', $opportunityAssessment !== 'not_assessed'],
    ];
    $readinessDone = collect($readinessChecks)->where(fn ($item) => $item[1])->count();
    $nextEditAction = collect([
        ['done' => $readinessChecks[0][1], 'anchor' => 'identity', 'title' => 'Complete customer identity', 'copy' => 'Record the customer name and relationship type before relying on downstream context.'],
        ['done' => $readinessChecks[1][1], 'anchor' => 'contacts', 'title' => 'Record a customer contact', 'copy' => 'Add a reachable named contact or a recorded customer email or phone number.'],
        ['done' => $readinessChecks[2][1], 'anchor' => 'engagement', 'title' => 'Complete industry context', 'copy' => 'Record the primary industry so commercial and project handoff context is explicit.'],
        ['done' => $readinessChecks[3][1], 'anchor' => 'engagement', 'title' => 'Complete customer geography', 'copy' => 'Record the customer city or headquarters country for location context.'],
        ['done' => $readinessChecks[4][1], 'anchor' => 'ownership', 'title' => 'Assign account ownership', 'copy' => 'Assign a company-scoped account owner for controlled relationship responsibility.'],
        ['done' => $readinessChecks[5][1], 'anchor' => 'opportunity', 'title' => 'Assess commercial opportunity', 'copy' => 'Record whether a commercial opportunity is known instead of inferring one from missing data.'],
    ])->first(fn ($item) => ! $item['done']) ?? [
        'anchor' => 'review',
        'title' => 'Review recorded changes',
        'copy' => 'Core customer context is recorded. Review the current changes before saving.',
    ];
    $disabledAdvanced = ! $customerCrmReady;
@endphp

<div class="nxce9" data-nxce9 data-crm-ready="{{ $customerCrmReady ? 'true' : 'false' }}" data-open-opportunities="{{ $openOpportunityCount }}" data-validation-errors="{{ $errors->any() ? 'true' : 'false' }}">
    <header class="nxce9-head">
        <div class="nxce9-head-copy">
            <nav class="nxce9-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('companies.customers.index', $company) }}">Customers</a><span>/</span>
                <a href="{{ route('companies.customers.show', [$company, $customer]) }}">{{ $customer->name }}</a><span>/</span><b>Edit Customer</b>
            </nav>
            <div class="nxce9-title-line">
                <h2>Edit Customer</h2>
                <span class="nxce9-save-state" data-save-state data-state="saved"><i></i><span>Saved baseline · {{ $customer->updated_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? 'Not Recorded' }}</span></span>
            </div>
        </div>
        <div class="nxce9-head-actions">
            <a class="nxce9-btn nxce9-btn--ghost" href="{{ route('companies.customers.show', [$company, $customer]) }}" data-discard>Discard Changes</a>
            <button class="nxce9-btn nxce9-btn--primary" type="submit" form="customerEditForm" data-save-top @disabled(! $customerCrmReady)>Save Changes</button>
        </div>
    </header>

    @unless ($customerCrmReady)
        <section class="nxce9-alert nxce9-alert--warning" role="status"><strong>Customer editing temporarily unavailable</strong><span>This relationship record cannot be edited because its extended customer data storage is currently unavailable. Contact an administrator before making changes.</span></section>
    @endunless

    @if ($errors->any())
        <section class="nxce9-alert nxce9-alert--error" role="alert">
            <div><strong>Customer update not saved</strong><span>Review {{ $errors->count() }} {{ $errors->count() === 1 ? 'issue' : 'issues' }} below.</span></div>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <nav class="nxce9-tabs" aria-label="Edit customer sections" data-section-nav>
        @foreach ([['identity','Profile'],['contacts','Contacts'],['ownership','Ownership'],['opportunity','Opportunity'],['engagement','Engagement'],['documents','Documents'],['review','Review']] as $index => [$anchor,$label])
            <button type="button" data-nav-anchor="{{ $anchor }}" class="{{ $index === 0 ? 'is-active' : '' }}">{{ $label }}</button>
        @endforeach
    </nav>

    <form id="customerEditForm" method="POST" action="{{ route('companies.customers.update', [$company, $customer]) }}" enctype="multipart/form-data" data-customer-edit-form>
        @csrf
        @method('PUT')
        <input type="hidden" name="baseline_updated_at" value="{{ old('baseline_updated_at', $customer->updated_at?->toIso8601String()) }}">
        <input type="hidden" name="contacts_sync" value="1">

        <div class="nxce9-top-zone" data-top-zone>
            <main class="nxce9-main nxce9-main--top">
                <section id="identity" class="nxce9-card nxce9-profile" data-tone="cyan" data-section="identity" data-profile-card>
                    <header class="nxce9-card-head">
                        <div><span>Customer Profile</span><small>Authoritative relationship identity</small></div>
                        <span class="nxce9-badge nxce9-badge--{{ $customer->status === 'active' ? 'ok' : 'muted' }}">{{ $customer->status === 'active' ? 'Active' : ($customer->status === 'archived' ? 'Archived' : 'Not Available') }}</span>
                    </header>
                    <div class="nxce9-profile-intro">
                        <div class="nxce9-avatar" aria-hidden="true"><img src="{{ asset('images/customer/nexorin-enterprise-customer-placeholder.webp') }}" alt=""></div>
                        <div class="nxce9-profile-copy">
                            <strong data-preview-name>{{ $customer->name }}</strong>
                            <span>{{ $customer->customer_number }} · {{ $customer->type_label }}</span>
                        </div>
                        <dl class="nxce9-profile-meta">
                            <div><dt>Projects</dt><dd>{{ (int) ($customer->projects_count ?? 0) }}</dd></div>
                            <div><dt>Contacts</dt><dd>{{ count(collect($contacts)->reject(fn ($row) => filter_var(data_get($row, '_remove', false), FILTER_VALIDATE_BOOLEAN))) }}</dd></div>
                            <div><dt>Open Opportunities</dt><dd>{{ $openOpportunityCount }}</dd></div>
                            <div><dt>Documents</dt><dd>{{ $documentCount }}</dd></div>
                        </dl>
                    </div>
                    <div class="nxce9-grid nxce9-grid--3">
                        <label class="nxce9-field nxce9-field--span2"><span>Legal Customer Name <b>*</b></span><textarea name="name" rows="1" maxlength="150" required data-preview-name-input data-autogrow>{{ $value('name') }}</textarea></label>
                        <label class="nxce9-field"><span>Preferred Name</span><textarea name="preferred_name" rows="1" maxlength="150" data-autogrow>{{ $value('preferred_name') }}</textarea></label>
                        <label class="nxce9-field"><span>Customer Type <b>*</b></span><select name="type" required><option value="commercial" @selected($value('type') === 'commercial')>Commercial</option><option value="residential" @selected($value('type') === 'residential')>Residential</option></select></label>
                        <label class="nxce9-field"><span>Relationship Stage</span><select name="relationship_stage" @disabled($disabledAdvanced)>@foreach(['lead'=>'Lead','prospect'=>'Prospect','active'=>'Active','on_hold'=>'On Hold','closed'=>'Closed'] as $key=>$label)<option value="{{ $key }}" @selected($value('relationship_stage','lead') === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label class="nxce9-field nxce9-field--span2"><span>Website</span><input type="url" name="website" value="{{ $value('website') }}" maxlength="255" placeholder="https://example.com" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field nxce9-field--span2"><span>Email</span><input type="email" name="email" value="{{ $value('email') }}" maxlength="255"></label>
                        <label class="nxce9-field"><span>Phone</span><input name="phone" value="{{ $value('phone') }}" maxlength="30"></label>
                        <label class="nxce9-field"><span>Tax / VAT Number</span><input name="tax_id" value="{{ $value('tax_id') }}" maxlength="120" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field nxce9-field--span2"><span>Registration Number</span><input name="registration_number" value="{{ $value('registration_number') }}" maxlength="120" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field"><span>Currency</span><input name="currency_code" value="{{ $value('currency_code') }}" maxlength="3" placeholder="IDR" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field"><span>Timezone</span><input name="timezone" value="{{ $value('timezone') }}" placeholder="Asia/Jakarta" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field"><span>Established</span><input type="number" name="year_established" value="{{ $value('year_established') }}" min="1800" max="{{ now()->year }}" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field"><span>Employees</span><input type="number" name="employee_count" value="{{ $value('employee_count') }}" min="0" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field nxce9-field--span2"><span>Annual Revenue</span><div class="nxce9-money"><input type="number" step="0.01" name="annual_revenue_amount" value="{{ $value('annual_revenue_amount') }}" min="0" @disabled($disabledAdvanced)><input name="revenue_currency_code" value="{{ $value('revenue_currency_code', $value('currency_code')) }}" maxlength="3" aria-label="Revenue currency" @disabled($disabledAdvanced)></div></label>
                        <label class="nxce9-field nxce9-field--full nxce9-field--business-description"><span>Business Description</span><textarea name="description" rows="3" maxlength="4000" data-business-description @disabled($disabledAdvanced)>{{ $value('description') }}</textarea></label>
                    </div>
                </section>

                <div class="nxce9-lower-flow">
            <div class="nxce9-editor-columns">
                <div class="nxce9-editor-column" data-editor-column="primary">
                    <section id="contacts" class="nxce9-card" data-tone="blue" data-section="contacts">
                <header class="nxce9-card-head"><div><span>Key Contacts</span><small>Stable contact records; removal is explicit</small></div><button type="button" class="nxce9-link-btn" data-add-contact @disabled($disabledAdvanced)>+ Add Contact</button></header>
                <div class="nxce9-repeater" data-contact-list>
                    @forelse ($contacts as $index => $contact)
                        <div class="nxce9-contact-row {{ filter_var(data_get($contact, '_remove', false), FILTER_VALIDATE_BOOLEAN) ? 'is-removing' : '' }}" data-contact-row data-existing="{{ filled(data_get($contact,'id')) ? 'true' : 'false' }}">
                            <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ data_get($contact,'id') }}">
                            <input type="hidden" name="contacts[{{ $index }}][_remove]" value="{{ filter_var(data_get($contact,'_remove',false), FILTER_VALIDATE_BOOLEAN) ? 1 : 0 }}" data-remove-flag>
                            <label class="nxce9-field"><span>Name <b>*</b></span><textarea name="contacts[{{ $index }}][full_name]" rows="1" maxlength="150" data-autogrow>{{ data_get($contact,'full_name') }}</textarea></label>
                            <label class="nxce9-field"><span>Job Title</span><textarea name="contacts[{{ $index }}][job_title]" rows="1" maxlength="150" data-autogrow>{{ data_get($contact,'job_title') }}</textarea></label>
                            <label class="nxce9-field"><span>Email</span><input type="email" name="contacts[{{ $index }}][email]" value="{{ data_get($contact,'email') }}" maxlength="190"></label>
                            <label class="nxce9-field"><span>Phone</span><input name="contacts[{{ $index }}][phone]" value="{{ data_get($contact,'phone') }}" maxlength="40"></label>
                            <label class="nxce9-primary-check"><input type="checkbox" name="contacts[{{ $index }}][is_primary]" value="1" data-primary-contact @checked(filter_var(data_get($contact,'is_primary',false), FILTER_VALIDATE_BOOLEAN))><span>Primary</span></label>
                            <button type="button" class="nxce9-row-action" data-remove-contact>{{ filter_var(data_get($contact,'_remove',false), FILTER_VALIDATE_BOOLEAN) ? 'Undo' : 'Remove' }}</button>
                        </div>
                    @empty
                        <div class="nxce9-empty" data-contact-empty><strong>No contacts recorded</strong><span>Add a named contact only when authoritative relationship information is available.</span></div>
                    @endforelse
                </div>
                    </section>

                    <section id="opportunity" class="nxce9-card" data-tone="amber" data-section="opportunity">
                <header class="nxce9-card-head"><div><span>Opportunities</span><small>Only real open records are editable here</small></div><button type="button" class="nxce9-link-btn" data-add-opportunity @disabled($disabledAdvanced)>+ Add Opportunity</button></header>
                <fieldset class="nxce9-assessment" @disabled($disabledAdvanced)>
                    <legend>Opportunity Assessment</legend>
                    <div class="nxce9-segmented" data-opportunity-assessment>@foreach(['not_assessed'=>'Not Assessed','no_opportunity'=>'No','has_opportunity'=>'Yes'] as $key=>$label)<label><input type="radio" name="opportunity_assessment" value="{{ $key }}" @checked($opportunityAssessment === $key) @disabled($openOpportunityCount > 0 && $key !== 'has_opportunity')><span>{{ $label }}</span></label>@endforeach</div>
                    @if($openOpportunityCount > 0)<small>{{ $openOpportunityCount }} open {{ Str::plural('opportunity', $openOpportunityCount) }} already exist; closing them requires their controlled workflow.</small>@else<small>No opportunity is inferred from missing data.</small>@endif
                </fieldset>
                <div class="nxce9-repeater nxce9-opportunity-list" data-opportunity-list>
                    @forelse($opportunities as $index => $opportunity)
                        <div class="nxce9-opportunity-row" data-opportunity-row data-existing="{{ filled(data_get($opportunity,'id')) ? 'true' : 'false' }}">
                            <input type="hidden" name="opportunities[{{ $index }}][id]" value="{{ data_get($opportunity,'id') }}">
                            <label class="nxce9-field nxce9-field--wide"><span>Opportunity Name <b>*</b></span><textarea name="opportunities[{{ $index }}][name]" rows="1" maxlength="190" data-autogrow>{{ data_get($opportunity,'name') }}</textarea></label>
                            <label class="nxce9-field"><span>Type</span><input name="opportunities[{{ $index }}][opportunity_type]" value="{{ data_get($opportunity,'opportunity_type') }}" maxlength="80"></label>
                            <label class="nxce9-field"><span>Estimated Value</span><input type="number" step="0.01" min="0" name="opportunities[{{ $index }}][estimated_value]" value="{{ data_get($opportunity,'estimated_value') }}"></label>
                            <label class="nxce9-field"><span>Currency</span><input name="opportunities[{{ $index }}][currency_code]" value="{{ data_get($opportunity,'currency_code') }}" maxlength="3"></label>
                            <label class="nxce9-field"><span>Probability</span><div class="nxce9-suffix"><input type="number" min="0" max="100" name="opportunities[{{ $index }}][probability_pct]" value="{{ data_get($opportunity,'probability_pct') }}"><span>%</span></div></label>
                            <label class="nxce9-field"><span>Expected Close</span><input type="date" name="opportunities[{{ $index }}][expected_close_date]" value="{{ data_get($opportunity,'expected_close_date') }}"></label>
                            @if(blank(data_get($opportunity,'id')))<button type="button" class="nxce9-row-action" data-remove-new-opportunity>Remove</button>@else<span class="nxce9-record-id">Record #{{ data_get($opportunity,'id') }}</span>@endif
                        </div>
                    @empty
                        <div class="nxce9-empty" data-opportunity-empty><strong>No open opportunities recorded</strong><span>Keep the assessment as Not Assessed or No unless a real opportunity is known.</span></div>
                    @endforelse
                </div>
                    </section>

                    <section id="commercial-engagement" class="nxce9-card" data-tone="amber" data-luxury-reveal>
                <header class="nxce9-card-head"><div><span>Commercial Engagement</span><small>Contract preferences and recorded relationship notes</small></div></header>
                <div class="nxce9-grid nxce9-grid--2">
                    <label class="nxce9-field"><span>Preferred Contract Type</span><input name="preferred_contract_type" value="{{ $value('preferred_contract_type') }}" maxlength="100" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field"><span>Engagement Model</span><input name="engagement_model" value="{{ $value('engagement_model') }}" maxlength="100" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field"><span>Payment Terms</span><input name="payment_terms" value="{{ $value('payment_terms') }}" maxlength="100" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field"><span>Contract Duration</span><input name="contract_duration" value="{{ $value('contract_duration') }}" maxlength="100" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field nxce9-field--full"><span>Internal Notes</span><textarea name="notes" rows="3" maxlength="5000">{{ $value('notes') }}</textarea></label>
                    <label class="nxce9-field nxce9-field--full"><span>Key Considerations</span><textarea name="key_considerations" rows="3" maxlength="3000" @disabled($disabledAdvanced)>{{ $value('key_considerations') }}</textarea></label>
                </div>
                    </section>

                    <section id="review" class="nxce9-review" data-tone="green" data-section="review">
                <div><span>Review &amp; Save</span><p>Saving updates the customer relationship record and its recorded commercial context. It does not create an engineering approval, project revision, or verified customer status.</p><label><input type="checkbox" name="review_acknowledged" value="1" data-review-ack @checked(old('review_acknowledged'))><b>I reviewed these changes before saving.</b></label></div>
                <div class="nxce9-review-actions"><a class="nxce9-btn nxce9-btn--ghost" href="{{ route('companies.customers.show', [$company, $customer]) }}" data-discard>Discard</a><button type="submit" class="nxce9-btn nxce9-btn--primary" data-save-bottom @disabled(! $customerCrmReady)>Save Changes</button></div>
            </section>

                </div>
                <div class="nxce9-editor-column" data-editor-column="context">
                    <section id="ownership" class="nxce9-card" data-tone="purple" data-section="ownership">
                <header class="nxce9-card-head"><div><span>Ownership &amp; Account Governance</span><small>Company-scoped users and explicit parent assessment</small></div></header>
                <div class="nxce9-grid nxce9-grid--2">
                    <fieldset class="nxce9-assessment nxce9-field--full" @disabled($disabledAdvanced)>
                        <legend>Parent Company</legend>
                        <div class="nxce9-segmented" data-parent-assessment>@foreach(['not_assessed'=>'Not Assessed','no_parent'=>'No','has_parent'=>'Yes'] as $key=>$label)<label><input type="radio" name="parent_company_assessment" value="{{ $key }}" @checked($parentAssessment === $key)><span>{{ $label }}</span></label>@endforeach</div>
                        <small>“No” and “Not Assessed” remain distinct states.</small>
                    </fieldset>
                    <div class="nxce9-grid nxce9-grid--2 nxce9-field--full" data-parent-fields>
                        <label class="nxce9-field"><span>Existing Parent Customer</span><select name="parent_customer_id" @disabled($disabledAdvanced)><option value="">Not selected</option>@foreach($parentCustomers as $parent)<option value="{{ $parent->id }}" @selected((string) old('parent_customer_id', $profile?->parent_customer_id) === (string) $parent->id)>{{ $parent->name }} · {{ $parent->customer_number }}</option>@endforeach</select></label>
                        <label class="nxce9-field"><span>External Parent Company</span><input name="external_parent_name" value="{{ $value('external_parent_name') }}" maxlength="190" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field"><span>Parent Country</span><input name="parent_company_country" value="{{ $value('parent_company_country') }}" maxlength="100" @disabled($disabledAdvanced)></label>
                        <label class="nxce9-field"><span>Ownership</span><div class="nxce9-suffix"><input type="number" step="0.01" min="0" max="100" name="ownership_pct" value="{{ $value('ownership_pct') }}" @disabled($disabledAdvanced)><span>%</span></div></label>
                    </div>
                    <label class="nxce9-field"><span>Account Owner</span><select name="account_owner_user_id" @disabled($disabledAdvanced)><option value="">Not Assigned</option>@foreach($accountOwners as $owner)<option value="{{ $owner->id }}" @selected((string) $value('account_owner_user_id') === (string) $owner->id)>{{ $owner->name }} · {{ $owner->email }}</option>@endforeach</select></label>
                    <label class="nxce9-field"><span>Portal Account</span><select name="portal_user_id"><option value="">Not Linked</option>@foreach($portalUsers as $portalUser)<option value="{{ $portalUser->id }}" @selected((string) $value('portal_user_id') === (string) $portalUser->id)>{{ $portalUser->name }} · {{ $portalUser->email }}</option>@endforeach</select></label>
                    <label class="nxce9-field"><span>Sales Team</span><input name="sales_team" value="{{ $value('sales_team') }}" maxlength="120" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field"><span>Region</span><input name="region" value="{{ $value('region') }}" maxlength="120" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field nxce9-field--full"><span>Business Unit</span><input name="business_unit" value="{{ $value('business_unit') }}" maxlength="120" @disabled($disabledAdvanced)></label>
                </div>
                    </section>

                    <section id="engagement" class="nxce9-card" data-tone="teal" data-section="engagement">
                <header class="nxce9-card-head"><div><span>Industry &amp; Geography</span><small>Customer market and location context for commercial and project handoff</small></div></header>
                <div class="nxce9-grid nxce9-grid--3">
                    <label class="nxce9-field"><span>Primary Industry</span><input name="primary_industry" value="{{ $value('primary_industry') }}" maxlength="120" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field"><span>Sub-industry</span><input name="sub_industry" value="{{ $value('sub_industry') }}" maxlength="120" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field"><span>Business Model</span><input name="business_model" value="{{ $value('business_model') }}" maxlength="120" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field nxce9-field--full"><span>Industry Tags</span><input name="industry_tags" value="{{ $industryTags }}" placeholder="Solar EPC, Manufacturing, Industrial" @disabled($disabledAdvanced)><small>Comma-separated; saved as structured tags.</small></label>
                    <label class="nxce9-field"><span>Headquarters Country</span><input name="hq_country" value="{{ $value('hq_country') }}" maxlength="100" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field"><span>City</span><input name="city" value="{{ $value('city') }}" maxlength="100"></label>
                    <label class="nxce9-field"><span>Province</span><input name="province" value="{{ $value('province') }}" maxlength="100"></label>
                    <label class="nxce9-field"><span>Postal Code</span><input name="postal_code" value="{{ $value('postal_code') }}" maxlength="20"></label>
                    <label class="nxce9-field nxce9-field--span2"><span>Operating Countries</span><input name="operating_countries" value="{{ $operatingCountries }}" placeholder="Indonesia, Singapore" @disabled($disabledAdvanced)></label>
                    <label class="nxce9-field nxce9-field--full"><span>Address</span><textarea name="address" rows="2" maxlength="2000">{{ $value('address') }}</textarea></label>
                </div>
                    </section>

                    <section id="documents" class="nxce9-card nxce9-documents-card" data-tone="blue" data-section="documents">
                <header class="nxce9-card-head">
                    <div><span>Documents &amp; Evidence</span><small>Existing customer evidence and supporting documents</small></div>
                    <span class="nxce9-badge nxce9-badge--muted">{{ $documentCount }} {{ $documentCount === 1 ? 'Evidence Record' : 'Evidence Records' }}</span>
                </header>

                <div class="nxce9-documents-layout {{ $hasCustomerDocuments ? 'has-evidence' : 'is-empty' }}">
                    <div class="nxce9-documents-existing">
                    @if($hasCustomerDocuments)
                        <div class="nxce9-doc-existing">
                        @foreach($customer->documents as $document)
                            @php
                                $documentCategoryLabel = $documentCategoryLabels[$document->category] ?? 'Supporting Document';
                                $documentStatusLabel = $document->status === 'recorded' ? 'Recorded' : 'Not Available';
                            @endphp
                            <article class="nxce9-doc-record">
                                <i aria-hidden="true">DOC</i>
                                <span class="nxce9-doc-record-copy">
                                    <strong>{{ $document->title }}</strong>
                                    <small class="nxce9-doc-record-meta">{{ $documentCategoryLabel }} · {{ $document->created_at?->timezone('Asia/Jakarta')->format('d M Y') ?? 'Date Not Recorded' }} · {{ number_format($document->size_bytes / 1024, 0) }} KB</small>
                                    <small class="nxce9-doc-record-file" title="{{ $document->original_name }}">{{ $document->original_name }}</small>
                                </span>
                                <em>{{ $documentStatusLabel }}</em>
                            </article>
                        @endforeach
                        </div>
                    @else
                        <div class="nxce9-doc-empty" role="status">
                            <i aria-hidden="true">+</i>
                            <div><strong>No evidence recorded yet</strong><span>Add the first supporting document below. Uploading a file records evidence; it does not approve or verify it.</span></div>
                        </div>
                    @endif
                    </div>

                    <div class="nxce9-documents-upload-panel">
                        <div class="nxce9-doc-upload" data-doc-uploader>
                            <label class="nxce9-doc-category">
                                <span>Document Category</span>
                                <select data-doc-category @disabled($disabledAdvanced)>
                                    <option value="">Select document category</option>
                                    @foreach($documentCategoryLabels as $categoryValue => $categoryLabel)
                                        <option value="{{ $categoryValue }}">{{ $categoryLabel }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <button type="button" class="nxce9-drop" data-doc-drop data-doc-browse @disabled($disabledAdvanced)>
                                <i aria-hidden="true">↑</i>
                                <span class="nxce9-drop-copy"><strong data-doc-title>Drop a file here</strong><small data-doc-status>Select a category, then add a supported file · Max 25 MB</small></span>
                                <span class="nxce9-drop-action" data-doc-action>Browse</span>
                            </button>

                            <input type="file" name="documents[incorporation]" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" data-doc-input="incorporation" data-doc-format="PDF, DOC/DOCX, PNG/JPG" hidden @disabled($disabledAdvanced)>
                            <input type="file" name="documents[tax_registration]" accept=".pdf,.png,.jpg,.jpeg" data-doc-input="tax_registration" data-doc-format="PDF, PNG/JPG" hidden @disabled($disabledAdvanced)>
                            <input type="file" name="documents[financial_statements]" accept=".pdf,.xls,.xlsx" data-doc-input="financial_statements" data-doc-format="PDF, XLS/XLSX" hidden @disabled($disabledAdvanced)>
                            <input type="file" name="documents[company_profile]" accept=".pdf,.ppt,.pptx" data-doc-input="company_profile" data-doc-format="PDF, PPT/PPTX" hidden @disabled($disabledAdvanced)>

                            <section class="nxce9-doc-staged" data-doc-staged-list hidden aria-live="polite">
                                <header><span>Ready to Add</span><strong data-doc-staged-count>0 files</strong></header>
                                <div data-doc-staged-items></div>
                            </section>
                        </div>
                        <p class="nxce9-rail-note nxce9-doc-note">New uploads are added as separate evidence records. Uploading does not change an evidence record to Approved or Verified.</p>
                    </div>
                </div>
            </section>
                </div>
            </div>
                </div>

            </main>

            <aside class="nxce9-rail" aria-label="Customer edit context" data-luxury-rail>
                <section class="nxce9-rail-card nxce9-next-action" data-rail-chapter="action">
                    <header><span>Next Required Action</span><small>Recorded data readiness</small></header>
                    <div class="nxce9-next-action-copy"><i aria-hidden="true"></i><div><strong>{{ $nextEditAction['title'] }}</strong><p>{{ $nextEditAction['copy'] }}</p></div></div>
                    <a class="nxce9-rail-action" href="#{{ $nextEditAction['anchor'] }}" data-rail-jump="{{ $nextEditAction['anchor'] }}">Open section <span>→</span></a>
                </section>

                <section class="nxce9-rail-card nxce9-context-card" data-rail-chapter="context">
                    <header><span>Customer Context</span><small>Saved record</small></header>
                    <div class="nxce9-context"><img src="{{ asset('images/customer/nexorin-enterprise-customer-placeholder.webp') }}" alt=""><div><strong>{{ $customer->name }}</strong><span>{{ $customer->customer_number }}</span><small>{{ $customer->type_label }} · {{ $customer->status === 'active' ? 'Active' : ($customer->status === 'archived' ? 'Archived' : 'Not Available') }}</small></div></div>
                </section>

                <section class="nxce9-rail-card" data-rail-chapter="readiness">
                    <header><span>Record Readiness</span><small>Saved data completeness</small></header>
                    <div class="nxce9-readiness" style="--nx9-readiness: {{ count($readinessChecks) ? round(($readinessDone / count($readinessChecks)) * 100) : 0 }}%"><div><strong>{{ $readinessDone }} of {{ count($readinessChecks) }}</strong><span>context groups recorded</span></div><i aria-hidden="true"><b></b></i></div>
                    <ul class="nxce9-checklist">@foreach($readinessChecks as [$label,$done])<li class="{{ $done ? 'is-done' : '' }}"><i>{{ $done ? '✓' : '—' }}</i><span>{{ $label }}</span></li>@endforeach</ul>
                    <p class="nxce9-rail-note">Completeness is not a customer score and does not imply engineering or credit verification.</p>
                </section>

                <section class="nxce9-rail-card" data-rail-chapter="ownership">
                    <header><span>Ownership &amp; Access</span></header>
                    <dl class="nxce9-facts">
                        <div><dt>Account Owner</dt><dd>{{ $profile?->accountOwner?->name ?? 'Not Assigned' }}</dd></div>
                        <div><dt>Portal Account</dt><dd>{{ $customer->portalUser?->name ?? 'Not Linked' }}</dd></div>
                        <div><dt>Parent Company</dt><dd>{{ match($profile?->parent_company_assessment ?? 'not_assessed') {'has_parent' => ($profile?->parentCustomer?->name ?? $profile?->external_parent_name ?? 'Recorded'), 'no_parent' => 'No', default => 'Not Assessed'} }}</dd></div>
                    </dl>
                </section>

                <section class="nxce9-rail-card" data-rail-chapter="commercial">
                    <header><span>Commercial Handoff</span></header>
                    <dl class="nxce9-facts">
                        <div><dt>Opportunity Assessment</dt><dd>{{ match($profile?->opportunity_assessment ?? 'not_assessed') {'has_opportunity'=>'Yes','no_opportunity'=>'No',default=>'Not Assessed'} }}</dd></div>
                        <div><dt>Open Opportunities</dt><dd>{{ $openOpportunityCount }}</dd></div>
                        <div><dt>Linked Projects</dt><dd>{{ (int) ($customer->projects_count ?? 0) }}</dd></div>
                    </dl>
                </section>

                <section class="nxce9-rail-card" data-rail-chapter="audit">
                    <header><span>Saved Change Context</span></header>
                    <dl class="nxce9-facts">
                        <div><dt>Last Updated</dt><dd>{{ $customer->updated_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') ?? 'Not Recorded' }}</dd></div>
                        <div><dt>Updated By</dt><dd>{{ $customer->updater?->name ?? 'Not Recorded' }}</dd></div>
                        <div><dt>Latest Review</dt><dd>{{ $profile?->review_acknowledged_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') ?? 'Not Recorded' }}</dd></div>
                        <div><dt>Reviewed By</dt><dd>{{ $profile?->reviewAcknowledgedBy?->name ?? 'Not Recorded' }}</dd></div>
                    </dl>
                    <p class="nxce9-rail-note">No customer activity history is available for this record.</p>
                </section>
            </aside>
        </div>

    </form>

    <template data-contact-template>
        <div class="nxce9-contact-row" data-contact-row data-existing="false">
            <input type="hidden" data-name="contacts[__INDEX__][id]" value=""><input type="hidden" data-name="contacts[__INDEX__][_remove]" value="0" data-remove-flag>
            <label class="nxce9-field"><span>Name <b>*</b></span><textarea rows="1" data-name="contacts[__INDEX__][full_name]" maxlength="150" data-autogrow></textarea></label>
            <label class="nxce9-field"><span>Job Title</span><textarea rows="1" data-name="contacts[__INDEX__][job_title]" maxlength="150" data-autogrow></textarea></label>
            <label class="nxce9-field"><span>Email</span><input type="email" data-name="contacts[__INDEX__][email]" maxlength="190"></label>
            <label class="nxce9-field"><span>Phone</span><input data-name="contacts[__INDEX__][phone]" maxlength="40"></label>
            <label class="nxce9-primary-check"><input type="checkbox" data-name="contacts[__INDEX__][is_primary]" value="1" data-primary-contact><span>Primary</span></label>
            <button type="button" class="nxce9-row-action" data-remove-contact>Remove</button>
        </div>
    </template>
    <template data-opportunity-template>
        <div class="nxce9-opportunity-row" data-opportunity-row data-existing="false">
            <input type="hidden" data-name="opportunities[__INDEX__][id]" value="">
            <label class="nxce9-field nxce9-field--wide"><span>Opportunity Name <b>*</b></span><textarea rows="1" data-name="opportunities[__INDEX__][name]" maxlength="190" data-autogrow></textarea></label>
            <label class="nxce9-field"><span>Type</span><input data-name="opportunities[__INDEX__][opportunity_type]" maxlength="80"></label>
            <label class="nxce9-field"><span>Estimated Value</span><input type="number" step="0.01" min="0" data-name="opportunities[__INDEX__][estimated_value]"></label>
            <label class="nxce9-field"><span>Currency</span><input data-name="opportunities[__INDEX__][currency_code]" maxlength="3"></label>
            <label class="nxce9-field"><span>Probability</span><div class="nxce9-suffix"><input type="number" min="0" max="100" data-name="opportunities[__INDEX__][probability_pct]"><span>%</span></div></label>
            <label class="nxce9-field"><span>Expected Close</span><input type="date" data-name="opportunities[__INDEX__][expected_close_date]"></label>
            <button type="button" class="nxce9-row-action" data-remove-new-opportunity>Remove</button>
        </div>
    </template>
</div>
@endsection
