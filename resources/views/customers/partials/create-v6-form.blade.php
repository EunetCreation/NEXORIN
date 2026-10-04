@php
    $selectedType = (string) $value('type', '');
    $selectedStage = (string) $value('relationship_stage', 'lead');
    $selectedPortal = (string) $value('portal_user_id', '');
    $selectedOwner = (string) $value('account_owner_user_id', '');
    $selectedParent = (string) $value('parent_customer_id', '');

    $operatingCountries = $value('operating_countries', []);
    if (is_string($operatingCountries)) {
        $operatingCountries = preg_split('/[,
]+/', $operatingCountries) ?: [];
    }
    if (! is_array($operatingCountries)) $operatingCountries = [];
    $operatingCountries = collect($operatingCountries)->map(fn ($country) => trim((string) $country))->filter()->unique()->values()->all();

    $industryTagsValue = $value('industry_tags', '');
    if (is_array($industryTagsValue)) $industryTagsValue = implode(', ', $industryTagsValue);

    $contacts = old('contacts', data_get($draftData, 'contacts', []));
    if (! is_array($contacts)) $contacts = [];

    $hasParentData = $selectedParent !== '' || filled($value('external_parent_name'));
    $parentAssessment = (string) $value('parent_company_assessment', $hasParentData ? 'has_parent' : 'not_assessed');
    if (! in_array($parentAssessment, ['not_assessed', 'no_parent', 'has_parent'], true)) $parentAssessment = 'not_assessed';

    $createOpportunity = filter_var($value('create_opportunity', false), FILTER_VALIDATE_BOOLEAN);
    $opportunityAssessment = (string) $value('opportunity_assessment', $createOpportunity ? 'has_opportunity' : 'not_assessed');
    if (! in_array($opportunityAssessment, ['not_assessed', 'no_opportunity', 'has_opportunity'], true)) $opportunityAssessment = 'not_assessed';

    $disabledAdvanced = ! $customerCrmReady;
    $countryOptions = [
        'Indonesia', 'Singapore', 'Malaysia', 'Thailand', 'Vietnam', 'Philippines', 'Brunei Darussalam',
        'Cambodia', 'Laos', 'Myanmar', 'Timor-Leste', 'Australia', 'Japan', 'South Korea', 'China', 'India',
        'United Arab Emirates', 'Saudi Arabia', 'United States', 'United Kingdom', 'Germany', 'Netherlands',
    ];
@endphp

<div class="nxcus8-workspace">
    <div class="nxcus8-main-grid">
        <section id="basic-information" class="nxcus8-card nxcus8-card--basics" data-section="basic-information">
            <header class="nxcus8-card-head">
                <div><span>Customer Basics</span><small>Authoritative identity and commercial context</small></div>
                <b>Required</b>
            </header>

            <div class="nxcus8-form-grid nxcus8-form-grid--2 nxcus8-basics-grid">
                <label class="nxcus8-field"><span>Legal Entity Name <i>*</i></span><input name="name" value="{{ $value('name') }}" maxlength="150" required autocomplete="organization" placeholder="Enter legal entity name" data-readiness="basic-name"></label>
                <label class="nxcus8-field"><span>Preferred Name</span><input name="preferred_name" value="{{ $value('preferred_name') }}" maxlength="150" placeholder="Enter preferred name" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field"><span>Website</span><input type="url" name="website" value="{{ $value('website') }}" maxlength="255" placeholder="https://company.com" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field"><span>Company Email</span><input type="email" name="email" value="{{ $value('email') }}" maxlength="255" autocomplete="email" placeholder="info@company.com"></label>
                <label class="nxcus8-field"><span>Tax ID / VAT Number</span><input name="tax_id" value="{{ $value('tax_id') }}" maxlength="120" placeholder="Enter tax identifier" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field"><span>Registration Number</span><input name="registration_number" value="{{ $value('registration_number') }}" maxlength="120" placeholder="Enter registration number" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field"><span>Customer Type <i>*</i></span><select name="type" required data-readiness="basic-type"><option value="">Select customer type</option><option value="commercial" @selected($selectedType === 'commercial')>Commercial</option><option value="residential" @selected($selectedType === 'residential')>Residential</option></select></label>
                <label class="nxcus8-field"><span>Company Phone</span><input name="phone" value="{{ $value('phone') }}" maxlength="30" autocomplete="tel" placeholder="+62 812 3456 7890"></label>
                <label class="nxcus8-field"><span>Currency</span><select name="currency_code" @disabled($disabledAdvanced)><option value="">Select currency</option>@foreach(['IDR'=>'IDR — Indonesian Rupiah','USD'=>'USD — US Dollar','SGD'=>'SGD — Singapore Dollar','MYR'=>'MYR — Malaysian Ringgit','AUD'=>'AUD — Australian Dollar','EUR'=>'EUR — Euro'] as $code=>$label)<option value="{{ $code }}" @selected($value('currency_code') === $code)>{{ $label }}</option>@endforeach</select></label>
                <label class="nxcus8-field"><span>Time Zone</span><select name="timezone" @disabled($disabledAdvanced)><option value="">Select time zone</option>@foreach(['Asia/Jakarta'=>'UTC+7 — Jakarta','Asia/Makassar'=>'UTC+8 — Makassar','Asia/Jayapura'=>'UTC+9 — Jayapura','Asia/Singapore'=>'UTC+8 — Singapore','UTC'=>'UTC'] as $tz=>$label)<option value="{{ $tz }}" @selected($value('timezone') === $tz)>{{ $label }}</option>@endforeach</select></label>
            </div>

            <div class="nxcus8-form-grid nxcus8-form-grid--3 nxcus8-basics-metrics">
                <label class="nxcus8-field"><span>Year Established</span><input type="number" name="year_established" value="{{ $value('year_established') }}" min="1800" max="{{ now()->year }}" placeholder="YYYY" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field"><span>Employees (Estimated)</span><input type="number" name="employee_count" value="{{ $value('employee_count') }}" min="0" placeholder="e.g. 250" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field"><span>Revenue (Estimated)</span><span class="nxcus8-money-control"><input type="number" step="0.01" min="0" name="annual_revenue_amount" value="{{ $value('annual_revenue_amount') }}" placeholder="Amount" @disabled($disabledAdvanced)><select name="revenue_currency_code" aria-label="Revenue currency" @disabled($disabledAdvanced)><option value="">—</option>@foreach(['IDR','USD','SGD','MYR','AUD','EUR'] as $code)<option value="{{ $code }}" @selected($value('revenue_currency_code') === $code)>{{ $code }}</option>@endforeach</select></span></label>
            </div>

            <label class="nxcus8-field nxcus8-field--wide nxcus8-description-field"><span>Description</span><textarea name="description" maxlength="4000" rows="2" placeholder="Brief description of the customer and business focus..." @disabled($disabledAdvanced)>{{ $value('description') }}</textarea><small><span data-count-for="description">0</span> / 4,000 characters</small></label>

            <div class="nxcus8-stage" aria-label="Relationship lifecycle">
                @foreach(['lead'=>'Lead','prospect'=>'Prospect','active'=>'Active','on_hold'=>'On Hold','closed'=>'Closed'] as $code=>$label)
                    <label class="{{ $selectedStage === $code ? 'is-selected' : '' }}"><input type="radio" name="relationship_stage" value="{{ $code }}" @checked($selectedStage === $code) @disabled($disabledAdvanced)><span>{{ $label }}</span></label>
                @endforeach
            </div>
        </section>

        <section class="nxcus8-card nxcus8-card--preview" aria-label="Customer preview">
            <header class="nxcus8-card-head"><div><span>Customer Preview</span><small>Live preview from entered data</small></div><b class="is-info">Preview</b></header>
            <figure class="nxcus8-preview-visual">
                <img src="{{ asset('images/customer/nexorin-enterprise-customer-placeholder.webp') }}" alt="Generic enterprise customer placeholder">
            </figure>
            <div class="nxcus8-preview-title"><div><strong data-preview-name>New Customer</strong><span>New Customer</span></div><em>Lifecycle: <b data-preview-stage>Lead</b></em></div>
            <dl class="nxcus8-preview-list"><div><dt>Industry</dt><dd data-preview-industry>Not Recorded</dd></div><div><dt>Headquarters</dt><dd data-preview-location>Not Recorded</dd></div><div><dt>Estimated Revenue</dt><dd data-preview-revenue>Not Recorded</dd></div></dl>
            <div class="nxcus8-preview-metrics">
                <article><span>Relationship Score</span><strong>Not Assessed</strong><small>No scoring methodology configured</small></article>
                <article><span>Onboarding Progress</span><strong data-progress-text>0%</strong><i><b data-progress-bar style="width:0%"></b></i><small><span data-progress-count>0</span> of 8 steps</small></article>
            </div>
        </section>

        <section id="industry-segment" class="nxcus8-card" data-section="industry-segment">
            <header class="nxcus8-card-head"><div><span>Industry Segment</span><small>Business classification for CRM handoff</small></div></header>
            <div class="nxcus8-form-grid">
                <label class="nxcus8-field nxcus8-field--wide"><span>Primary Industry</span><select name="primary_industry" data-readiness="industry" @disabled($disabledAdvanced)><option value="">Select primary industry</option>@foreach(['Energy & Utilities','Manufacturing','Commercial Real Estate','Retail','Hospitality','Healthcare','Education','Agriculture','Mining','Government & Public Sector','Technology','Other'] as $industry)<option value="{{ $industry }}" @selected($value('primary_industry') === $industry)>{{ $industry }}</option>@endforeach</select><small>Select the customer's main operating industry.</small></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Sub-industry</span><input name="sub_industry" value="{{ $value('sub_industry') }}" maxlength="120" placeholder="Enter sub-industry" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Business Model</span><input name="business_model" value="{{ $value('business_model') }}" maxlength="120" placeholder="e.g. Manufacturer, owner-operator" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Industry Tags</span><input name="industry_tags" value="{{ $industryTagsValue }}" maxlength="500" placeholder="Solar, manufacturing, export" @disabled($disabledAdvanced)><small>Add only known customer classification tags.</small></label>
            </div>
        </section>

        <section id="parent-company" class="nxcus8-card" data-section="parent-company">
            <header class="nxcus8-card-head"><div><span>Parent Company</span><small>Optional ownership relationship</small></div></header>
            <div class="nxcus8-assessment" role="radiogroup" aria-label="Parent company assessment">
                @foreach(['not_assessed'=>'Not Assessed','no_parent'=>'No','has_parent'=>'Yes'] as $code=>$label)
                    <label><input type="radio" name="parent_company_assessment" value="{{ $code }}" @checked($parentAssessment === $code) @disabled($disabledAdvanced)><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <div class="nxcus8-assessment-state" data-parent-empty @if($parentAssessment === 'has_parent') hidden @endif>
                <strong>{{ $parentAssessment === 'no_parent' ? 'No parent company recorded' : 'Parent relationship not assessed' }}</strong>
                <span>{{ $parentAssessment === 'no_parent' ? 'This explicit assessment will be stored with the customer profile.' : 'Choose Yes only when a parent or holding company relationship is known.' }}</span>
            </div>
            <div class="nxcus8-form-grid" data-parent-fields>
                <label class="nxcus8-field nxcus8-field--wide"><span>Existing Parent Customer</span><select name="parent_customer_id" @disabled($disabledAdvanced || $parentAssessment !== 'has_parent')><option value="">Not linked to an existing customer</option>@foreach($parentCustomers as $parent)<option value="{{ $parent->id }}" @selected($selectedParent === (string)$parent->id)>{{ $parent->name }}{{ $parent->customer_number ? ' · '.$parent->customer_number : '' }}</option>@endforeach</select></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>External Parent Company</span><input name="external_parent_name" value="{{ $value('external_parent_name') }}" maxlength="190" placeholder="Enter legal entity name" @disabled($disabledAdvanced || $parentAssessment !== 'has_parent')></label>
                <div class="nxcus8-form-grid nxcus8-form-grid--2 nxcus8-nested-grid">
                    <label class="nxcus8-field"><span>Ownership %</span><input type="number" name="ownership_pct" value="{{ $value('ownership_pct') }}" min="0" max="100" step="0.01" placeholder="e.g. 100" @disabled($disabledAdvanced || $parentAssessment !== 'has_parent')></label>
                    <label class="nxcus8-field"><span>Parent Country</span><input name="parent_company_country" value="{{ $value('parent_company_country') }}" maxlength="100" placeholder="Country" @disabled($disabledAdvanced || $parentAssessment !== 'has_parent')></label>
                </div>
            </div>
            <p class="nxcus8-card-note">Link an existing governed customer when available; otherwise record the external legal entity.</p>
        </section>

        <section id="geography" class="nxcus8-card" data-section="geography">
            <header class="nxcus8-card-head"><div><span>Geography</span><small>Headquarters and operating footprint</small></div></header>
            <div class="nxcus8-form-grid nxcus8-form-grid--2">
                <label class="nxcus8-field"><span>Headquarters Country</span><input name="hq_country" value="{{ $value('hq_country') }}" maxlength="100" list="nxcus8CountryOptions" placeholder="Country" data-readiness="geography-country" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field"><span>Headquarters City</span><input name="city" value="{{ $value('city') }}" maxlength="100" placeholder="City" data-readiness="geography-city"></label>
                <div class="nxcus8-field nxcus8-field--wide">
                    <span>Operating Countries</span>
                    <div class="nxcus8-token-control" data-country-token-root>
                        <div class="nxcus8-token-list" data-country-token-list>
                            @foreach($operatingCountries as $country)
                                <button type="button" class="nxcus8-token" data-country-token="{{ $country }}" @disabled($disabledAdvanced)><span>{{ $country }}</span><i aria-hidden="true">×</i></button>
                            @endforeach
                        </div>
                        <input type="hidden" name="operating_countries" value="{{ implode(', ', $operatingCountries) }}" data-country-value>
                        <input type="text" data-country-entry list="nxcus8CountryOptions" maxlength="100" placeholder="Add country" @disabled($disabledAdvanced)>
                    </div>
                    <small>Add only locations explicitly provided or otherwise authoritative.</small>
                </div>
            </div>
            <details class="nxcus8-advanced nxcus8-geography-advanced" @if(filled($value('province')) || filled($value('postal_code')) || filled($value('address'))) open @endif>
                <summary>Address details <span>Optional</span></summary>
                <div class="nxcus8-form-grid nxcus8-form-grid--2">
                    <label class="nxcus8-field"><span>Province / State</span><input name="province" value="{{ $value('province') }}" maxlength="100" placeholder="Province or state"></label>
                    <label class="nxcus8-field"><span>Postal Code</span><input name="postal_code" value="{{ $value('postal_code') }}" maxlength="20" placeholder="Postal code"></label>
                    <label class="nxcus8-field nxcus8-field--wide"><span>Street Address</span><textarea name="address" maxlength="2000" rows="2" placeholder="Street, building, district, and site reference">{{ $value('address') }}</textarea></label>
                </div>
            </details>
            <datalist id="nxcus8CountryOptions">@foreach($countryOptions as $country)<option value="{{ $country }}">@endforeach</datalist>
            <div class="nxcus8-map-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18-6 3V6l6-3 6 3 6-3v15l-6 3z"/><path d="M9 3v15M15 6v15"/></svg><div><strong>Regional Footprint Preview</strong><span data-map-copy>No locations added yet.</span><small>Add verified headquarters or operating regions to display the footprint.</small></div></div>
        </section>

        <section id="contacts" class="nxcus8-card nxcus8-card--contacts" data-section="contacts">
            <header class="nxcus8-card-head"><div><span>Contacts Overview</span><small>Named relationship contacts</small></div></header>
            <div class="nxcus8-contact-list" data-contact-list>
                @foreach($contacts as $index => $contact)
                    <article class="nxcus8-contact-row" data-contact-row>
                        <div class="nxcus8-form-grid nxcus8-form-grid--2">
                            <label class="nxcus8-field nxcus8-field--primary-wide"><span>Full Name</span><input name="contacts[{{ $index }}][full_name]" value="{{ data_get($contact, 'full_name') }}" maxlength="150" placeholder="Contact name" data-contact-name @disabled($disabledAdvanced)></label>
                            <label class="nxcus8-field"><span>Job Title</span><input name="contacts[{{ $index }}][job_title]" value="{{ data_get($contact, 'job_title') }}" maxlength="150" placeholder="Role or title" @disabled($disabledAdvanced)></label>
                            <label class="nxcus8-field nxcus8-field--primary-wide"><span>Email</span><input type="email" name="contacts[{{ $index }}][email]" value="{{ data_get($contact, 'email') }}" maxlength="190" placeholder="name@company.com" @disabled($disabledAdvanced)></label>
                            <label class="nxcus8-field"><span>Phone</span><input name="contacts[{{ $index }}][phone]" value="{{ data_get($contact, 'phone') }}" maxlength="40" placeholder="+62 ..." @disabled($disabledAdvanced)></label>
                        </div>
                        <footer><label><input type="checkbox" name="contacts[{{ $index }}][is_primary]" value="1" @checked(filter_var(data_get($contact, 'is_primary'), FILTER_VALIDATE_BOOLEAN)) @disabled($disabledAdvanced)> Primary contact</label><button type="button" data-remove-contact aria-label="Remove contact" @disabled($disabledAdvanced)>Remove</button></footer>
                    </article>
                @endforeach
            </div>
            <template data-contact-template>
                <article class="nxcus8-contact-row" data-contact-row>
                    <div class="nxcus8-form-grid nxcus8-form-grid--2">
                        <label class="nxcus8-field nxcus8-field--primary-wide"><span>Full Name</span><input data-name-template="contacts[__INDEX__][full_name]" maxlength="150" placeholder="Contact name" data-contact-name></label>
                        <label class="nxcus8-field"><span>Job Title</span><input data-name-template="contacts[__INDEX__][job_title]" maxlength="150" placeholder="Role or title"></label>
                        <label class="nxcus8-field nxcus8-field--primary-wide"><span>Email</span><input type="email" data-name-template="contacts[__INDEX__][email]" maxlength="190" placeholder="name@company.com"></label>
                        <label class="nxcus8-field"><span>Phone</span><input data-name-template="contacts[__INDEX__][phone]" maxlength="40" placeholder="+62 ..."></label>
                    </div>
                    <footer><label><input type="checkbox" data-name-template="contacts[__INDEX__][is_primary]" value="1"> Primary contact</label><button type="button" data-remove-contact>Remove</button></footer>
                </article>
            </template>
            <div class="nxcus8-contact-empty" data-contact-empty @if(count($contacts) > 0) hidden @endif><strong>No contacts added yet</strong><span>Add only named contacts that have been provided or confirmed.</span></div>
            <button type="button" class="nxcus8-inline-cta" data-add-contact @disabled($disabledAdvanced)>Add Contact <span>→</span></button>
        </section>

        <section id="account-owner" class="nxcus8-card" data-section="account-owner">
            <header class="nxcus8-card-head"><div><span>Account Owner</span><small>Company-scoped relationship ownership</small></div></header>
            <div class="nxcus8-form-grid">
                <label class="nxcus8-field nxcus8-field--wide"><span>Account Owner</span><select name="account_owner_user_id" data-readiness="owner" @disabled($disabledAdvanced)><option value="">Select account owner</option>@foreach($accountOwners as $owner)<option value="{{ $owner->id }}" @selected($selectedOwner === (string)$owner->id)>{{ $owner->name }} · {{ $owner->email }}</option>@endforeach</select><small>Only active company members can be assigned.</small></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Sales Team</span><input name="sales_team" value="{{ $value('sales_team') }}" maxlength="120" placeholder="Optional team label" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Region</span><input name="region" value="{{ $value('region') }}" maxlength="120" placeholder="Optional operating region" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Business Unit</span><input name="business_unit" value="{{ $value('business_unit') }}" maxlength="120" placeholder="Optional business unit" @disabled($disabledAdvanced)></label>
            </div>
            <details class="nxcus8-advanced" @if($selectedPortal !== '') open @endif>
                <summary>Portal access <span>Optional</span></summary>
                <label class="nxcus8-field"><span>Customer Portal Account</span><select name="portal_user_id"><option value="">Not linked</option>@foreach($portalUsers as $portalUser)<option value="{{ $portalUser->id }}" @selected($selectedPortal === (string)$portalUser->id)>{{ $portalUser->name }} · {{ $portalUser->email }}</option>@endforeach</select><small>Restricted to active company members with the Customer role.</small></label>
            </details>
        </section>

        <section id="opportunity" class="nxcus8-card" data-section="opportunity">
            <header class="nxcus8-card-head"><div><span>Opportunity Summary</span><small>Optional first commercial opportunity</small></div></header>
            <div class="nxcus8-assessment" role="radiogroup" aria-label="Opportunity assessment">
                @foreach(['not_assessed'=>'Not Assessed','no_opportunity'=>'No','has_opportunity'=>'Yes'] as $code=>$label)
                    <label><input type="radio" name="opportunity_assessment" value="{{ $code }}" @checked($opportunityAssessment === $code) @disabled($disabledAdvanced)><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <div class="nxcus8-assessment-state" data-opportunity-empty @if($opportunityAssessment === 'has_opportunity') hidden @endif>
                <strong>{{ $opportunityAssessment === 'no_opportunity' ? 'No opportunity recorded at intake' : 'Opportunity not assessed' }}</strong>
                <span>{{ $opportunityAssessment === 'no_opportunity' ? 'This explicit assessment is stored separately from an empty opportunity form.' : 'Choose Yes only when a real commercial opportunity exists.' }}</span>
            </div>
            <div class="nxcus8-form-grid nxcus8-form-grid--2" data-opportunity-fields @if($opportunityAssessment !== 'has_opportunity') hidden @endif>
                <label class="nxcus8-field nxcus8-field--primary-wide"><span>Opportunity Name</span><input name="opportunity_name" value="{{ $value('opportunity_name') }}" maxlength="190" placeholder="Enter opportunity name" @disabled($disabledAdvanced || $opportunityAssessment !== 'has_opportunity')></label>
                <label class="nxcus8-field"><span>Opportunity Type</span><select name="opportunity_type" @disabled($disabledAdvanced || $opportunityAssessment !== 'has_opportunity')><option value="">Select type</option>@foreach(['New Solar Project','Expansion / Repowering','BESS / Hybrid','O&M / Service','Consulting / Engineering','Other'] as $type)<option value="{{ $type }}" @selected($value('opportunity_type') === $type)>{{ $type }}</option>@endforeach</select></label>
                <label class="nxcus8-field"><span>Estimated Project Value</span><input type="number" name="opportunity_estimated_value" value="{{ $value('opportunity_estimated_value') }}" min="0" step="0.01" placeholder="0.00" @disabled($disabledAdvanced || $opportunityAssessment !== 'has_opportunity')></label>
                <label class="nxcus8-field"><span>Currency</span><select name="opportunity_currency_code" @disabled($disabledAdvanced || $opportunityAssessment !== 'has_opportunity')><option value="">Customer currency</option>@foreach(['IDR','USD','SGD','MYR','AUD','EUR'] as $code)<option value="{{ $code }}" @selected($value('opportunity_currency_code') === $code)>{{ $code }}</option>@endforeach</select></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Probability <b data-probability-label>{{ $value('opportunity_probability_pct', 10) ?: 10 }}%</b></span><input class="nxcus8-range" type="range" name="opportunity_probability_pct" value="{{ $value('opportunity_probability_pct', 10) ?: 10 }}" min="0" max="100" step="5" @disabled($disabledAdvanced || $opportunityAssessment !== 'has_opportunity')></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Expected Close Date</span><input type="date" name="opportunity_expected_close_date" value="{{ $value('opportunity_expected_close_date') }}" @disabled($disabledAdvanced || $opportunityAssessment !== 'has_opportunity')></label>
            </div>
            <p class="nxcus8-card-note">A confirmed opportunity creates a CRM opportunity record only. It does not create a Project or imply engineering feasibility.</p>
        </section>

        <section class="nxcus8-card" data-section="contracting-model">
            <header class="nxcus8-card-head"><div><span>Contracting Model</span><small>Optional commercial preferences</small></div></header>
            <div class="nxcus8-form-grid">
                <label class="nxcus8-field nxcus8-field--wide"><span>Preferred Contract Type</span><input name="preferred_contract_type" value="{{ $value('preferred_contract_type') }}" maxlength="100" list="nxcus8ContractTypes" placeholder="Select or enter contract type" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Engagement Model</span><input name="engagement_model" value="{{ $value('engagement_model') }}" maxlength="100" list="nxcus8EngagementModels" placeholder="Select or enter engagement model" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Payment Terms</span><input name="payment_terms" value="{{ $value('payment_terms') }}" maxlength="100" placeholder="Record agreed or supplied terms" @disabled($disabledAdvanced)></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Contract Duration</span><input name="contract_duration" value="{{ $value('contract_duration') }}" maxlength="100" placeholder="e.g. 12 months" @disabled($disabledAdvanced)></label>
            </div>
            <datalist id="nxcus8ContractTypes"><option value="EPC"><option value="PPA"><option value="Supply Only"><option value="O&M Service"><option value="Consulting / Engineering"></datalist>
            <datalist id="nxcus8EngagementModels"><option value="Direct Engagement"><option value="Competitive Tender"><option value="Framework Agreement"><option value="Channel / Partner"></datalist>
        </section>

        <section class="nxcus8-card" data-section="relationship-notes">
            <header class="nxcus8-card-head"><div><span>Relationship Notes</span><small>Internal context, not customer-facing</small></div></header>
            <div class="nxcus8-form-grid">
                <label class="nxcus8-field nxcus8-field--wide"><span>Internal Notes</span><textarea name="notes" maxlength="5000" rows="3" placeholder="Add internal notes about this customer...">{{ $value('notes') }}</textarea><small><span data-count-for="notes">0</span> / 5,000 characters</small></label>
                <label class="nxcus8-field nxcus8-field--wide"><span>Key Considerations</span><textarea name="key_considerations" maxlength="3000" rows="3" placeholder="Add key considerations and context..." @disabled($disabledAdvanced)>{{ $value('key_considerations') }}</textarea><small><span data-count-for="key_considerations">0</span> / 3,000 characters</small></label>
            </div>
        </section>

        <section class="nxcus8-card" data-section="documents">
            <header class="nxcus8-card-head"><div><span>Documents</span><small>Private customer governance evidence</small></div></header>
            <div class="nxcus8-doc-list">
                @foreach([
                    'incorporation' => ['Certificate of Incorporation','PDF, DOC, DOCX, PNG, JPG','.pdf,.doc,.docx,.png,.jpg,.jpeg'],
                    'tax_registration' => ['Tax Registration','PDF, PNG, JPG','.pdf,.png,.jpg,.jpeg'],
                    'financial_statements' => ['Financial Statements (Latest)','PDF, XLS, XLSX','.pdf,.xls,.xlsx'],
                    'company_profile' => ['Company Profile','PDF, PPT, PPTX','.pdf,.ppt,.pptx'],
                ] as $key => [$label,$acceptLabel,$accept])
                    <label class="nxcus8-doc-row" data-doc-row="{{ $key }}">
                        <span class="nxcus8-doc-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/><path d="M10 13h5M10 16h5"/></svg></span>
                        <span class="nxcus8-doc-row-meta"><b>{{ $label }}</b><small>{{ $acceptLabel }} · Max 25 MB</small></span>
                        <em data-file-name>No file selected</em>
                        <span class="nxcus8-doc-row-action">Choose file</span>
                        <input type="file" name="documents[{{ $key }}]" accept="{{ $accept }}" data-doc-input="{{ $key }}" @disabled($disabledAdvanced)>
                    </label>
                @endforeach
            </div>

            <div class="nxcus8-doc-quick" aria-label="Quick document upload">
                <div class="nxcus8-doc-quick-head">
                    <div><strong>Quick Upload</strong><small>Assign a category, then drop or browse one file.</small></div>
                    <span>Private</span>
                </div>
                <label class="nxcus8-doc-category">
                    <span>Document category</span>
                    <select data-doc-drop-category aria-label="Document category" @disabled($disabledAdvanced)>
                        <option value="incorporation">Certificate of Incorporation</option>
                        <option value="tax_registration">Tax Registration</option>
                        <option value="financial_statements">Financial Statements</option>
                        <option value="company_profile">Company Profile</option>
                    </select>
                </label>
                <div class="nxcus8-doc-drop" data-doc-drop role="button" tabindex="0" aria-label="Drop a customer document or browse files">
                    <span class="nxcus8-doc-drop-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 15V5M8 9l4-4 4 4"/><path d="M5 14v5h14v-5"/></svg></span>
                    <span class="nxcus8-doc-drop-copy"><strong>Drop a file here</strong><small data-doc-drop-status>PDF, Office document, or image · Max 25 MB</small></span>
                    <button type="button" class="nxcus8-doc-browse" data-doc-browse @disabled($disabledAdvanced)>Browse files</button>
                </div>
                <input type="file" data-doc-drop-input accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg" tabindex="-1" aria-hidden="true" @disabled($disabledAdvanced)>
            </div>
            <div class="nxcus8-doc-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg><span>Private storage with integrity evidence. Uploaded documents remain unapproved until reviewed.</span></div>
        </section>

        <section class="nxcus8-timeline" aria-label="Relationship timeline">
            <header><div><span>Relationship Timeline</span><small>Future milestones remain unclaimed until their own workflows produce evidence.</small></div><em>Pre-customer record</em></header>
            <div class="nxcus8-timeline-track">
                @foreach([['Lead Intake','Current intake'],['First Contact','Initial outreach'],['Qualification','Needs assessment'],['Opportunity Created','Commercial discussion'],['Proposal Sent','Solution proposal'],['Negotiation','Terms & conditions'],['Contract Signed','Agreement finalized'],['Go-Live','Project execution']] as $index => [$title,$copy])
                    <div class="{{ $index === 0 ? 'is-current' : '' }}"><i></i><strong>{{ $title }}</strong><span>{{ $copy }}</span></div>
                @endforeach
            </div>
        </section>

        <section id="review-submit" class="nxcus8-review-submit" data-section="review-submit">
            <div class="nxcus8-review-copy">
                <strong>Review &amp; Submit</strong>
                <p>Creating this customer records relationship identity and the supplied CRM context. It does not create a solar project or an engineering approval state.</p>
                <label class="nxcus8-review-check"><input type="checkbox" name="review_acknowledged" value="1" data-review-ack @checked(old('review_acknowledged'))> <span>I reviewed the customer information entered in this intake.</span></label>
            </div>
            <button type="submit" name="submit_intent" value="create" class="nxcus8-btn nxcus8-btn--primary">Create Customer <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg></button>
        </section>
    </div>

    <aside class="nxcus8-rail" aria-label="Customer readiness and guidance">
        <section class="nxcus8-rail-card nxcus8-readiness-card">
            <header><span>Customer Readiness</span><small>Form completeness only</small></header>
            <div class="nxcus8-gauge" data-gauge style="--progress:0"><div><strong data-gauge-value>0%</strong><span data-gauge-label>Not Started</span><small><b data-gauge-count>0</b> of 8 completed</small></div></div>
            <ol data-readiness-list>@foreach(['Basic Information','Industry Segment','Parent Company','Geography','Contacts','Account Owner','Opportunity','Review & Submit'] as $index => $label)<li data-readiness-item="{{ $index }}"><i></i><span>{{ $index + 1 }}. {{ $label }}</span></li>@endforeach</ol>
        </section>

        <section class="nxcus8-rail-card nxcus8-next-action"><header><span>Next Best Action</span></header><div><b data-next-index>1</b><p><strong data-next-title>Add basic customer information</strong><span data-next-copy>Start with the legal entity name and customer type.</span></p></div><button type="button" class="nxcus8-btn nxcus8-btn--primary nxcus8-btn--full" data-next-action>Start Now <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg></button></section>

        <section class="nxcus8-rail-card"><header><span>Onboarding Checklist</span><small><b data-checklist-count>0</b> of 8 completed</small></header><ul class="nxcus8-mini-checklist">@foreach(['Basic Information','Industry Segment','Parent Company','Geography','Contacts','Account Owner','Opportunity','Review & Submit'] as $index=>$label)<li data-mini-item="{{ $index }}"><i></i>{{ $label }}</li>@endforeach</ul></section>

        <section class="nxcus8-rail-card"><header><span>Recommended Follow-up</span></header><ul class="nxcus8-followup" data-followup-list><li data-followup-key="contacts"><i>1</i><span><strong>Identify Key Contacts</strong><small>Add a named customer contact</small></span></li><li data-followup-key="opportunity"><i>2</i><span><strong>Assess Opportunity</strong><small>Confirm whether a real opportunity exists</small></span></li><li data-followup-key="documents"><i>3</i><span><strong>Attach Available Evidence</strong><small>Add legal or commercial documents when supplied</small></span></li><li data-followup-key="notes"><i>4</i><span><strong>Record Relationship Context</strong><small>Add relevant internal notes if available</small></span></li></ul><div class="nxcus8-followup-empty" data-followup-empty hidden>No additional follow-up is suggested from the current intake state.</div></section>

        <section class="nxcus8-rail-card"><header><span>Compatible Projects</span><small>0</small></header><div class="nxcus8-rail-empty"><strong>No projects linked yet</strong><span>Projects can only be linked after the customer record exists.</span><a href="#review-submit">Create customer first →</a></div></section>

        <section class="nxcus8-rail-card"><header><span>Data Source Hints</span></header><ul class="nxcus8-source-list"><li><i>✓</i><span><strong>Manual customer intake</strong><small>User-entered until supporting evidence is attached</small></span></li><li><i>—</i><span><strong>External enrichment</strong><small>Not connected for this workflow</small></span></li><li><i>—</i><span><strong>Credit bureau</strong><small>Not connected</small></span></li><li><i>—</i><span><strong>Market intelligence</strong><small>Not connected</small></span></li></ul><div class="nxcus8-data-quality"><span>Data Quality</span><em>Not Assessed</em><small>No quality methodology is configured for this intake.</small></div></section>
    </aside>
</div>
