@extends('layouts.app')

@section('title', 'Customers')
@section('page-eyebrow', 'Customer intelligence')
@section('page-heading', 'Customers')
@section('force-theme', 'dark')
@section('body-class', 'page-customers-index-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-customers-index-v6.css') }}?v=010a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}" defer></script>
    <script src="{{ asset('js/nexorin-customers-index-v6.js') }}?v=010a" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@php
    $currentUser = auth()->user();
    $canCreate = $currentUser->hasCompanyPermission($company, 'customer.create');
    $canUpdate = $currentUser->hasCompanyPermission($company, 'customer.update');
    $canArchive = $currentUser->hasCompanyPermission($company, 'customer.delete');

    $stageLabels = [
        'lead' => 'Lead',
        'prospect' => 'Prospect',
        'active' => 'Active Relationship',
        'on_hold' => 'On Hold',
        'closed' => 'Closed',
    ];
    $statusLabels = ['active' => 'Active', 'archived' => 'Archived'];
    $typeLabels = ['residential' => 'Residential', 'commercial' => 'Commercial'];
    $opportunityAssessmentLabels = [
        'not_assessed' => 'Not Assessed',
        'no_opportunity' => 'No Opportunity',
        'has_opportunity' => 'Opportunity Recorded',
    ];

    $compactNumber = static function (float|int|string|null $value, int $decimals = 1): string {
        if ($value === null || $value === '') {
            return 'Not Recorded';
        }

        $numeric = (float) $value;
        $absolute = abs($numeric);
        if ($absolute >= 1_000_000_000_000) {
            return number_format($numeric / 1_000_000_000_000, $decimals).'T';
        }
        if ($absolute >= 1_000_000_000) {
            return number_format($numeric / 1_000_000_000, $decimals).'B';
        }
        if ($absolute >= 1_000_000) {
            return number_format($numeric / 1_000_000, $decimals).'M';
        }
        if ($absolute >= 1_000) {
            return number_format($numeric / 1_000, $decimals).'K';
        }

        return number_format($numeric, $decimals === 0 ? 0 : min($decimals, 1));
    };

    $formatMoney = static function ($value, ?string $currency) use ($compactNumber): string {
        if ($value === null || $value === '') {
            return 'Value Not Recorded';
        }

        $code = strtoupper(trim((string) $currency));
        if ($code === '') {
            return 'Currency Not Recorded · '.$compactNumber($value);
        }

        return $code.' '.$compactNumber($value);
    };

    $opportunityValueSummary = $directoryCapabilities['opportunities']
        ? $openOpportunityCurrencyTotals
            ->take(2)
            ->map(fn ($row) => $formatMoney($row->total_value, $row->currency_code))
            ->implode(' · ')
        : '';

    if ($directoryCapabilities['opportunities'] && $openOpportunityCurrencyTotals->count() > 2) {
        $opportunityValueSummary = 'Values recorded in '.$openOpportunityCurrencyTotals->count().' currencies';
    }
    if ($directoryCapabilities['opportunities'] && $stats['open_opportunities'] > 0 && $opportunityValueSummary === '') {
        $opportunityValueSummary = 'Estimated values not recorded';
    }
    if ($directoryCapabilities['opportunities'] && $stats['open_opportunities'] === 0) {
        $opportunityValueSummary = 'No open opportunity records';
    }

    $totalForCoverage = max(1, (int) $stats['total']);
    $profileGap = $directoryCapabilities['profile'] ? max(0, $stats['total'] - $stats['profiles_recorded']) : null;
    $ownerGap = $directoryCapabilities['profile'] ? max(0, $stats['total'] - $stats['owners_assigned']) : null;
    $evidenceGap = $directoryCapabilities['documents'] ? max(0, $stats['total'] - $stats['customers_with_evidence']) : null;
    $contactGap = $directoryCapabilities['contacts'] ? max(0, $stats['total'] - $stats['primary_contacts_recorded']) : null;
    $portalGap = max(0, $stats['total'] - $stats['portal_linked']);

    $chartPayload = [
        'totalCustomers' => (int) $stats['total'],
        'relationshipStages' => $relationshipStageData,
        'coverage' => $coverageData,
        'projectStatuses' => $projectStatusData,
        'mapPoints' => $projectMapPoints,
        'exactCoordinateCount' => (int) $exactProjectCoordinateCount,
    ];
@endphp

@section('content')
<div
    class="nx10"
    data-nx10-root
    data-map-url="{{ asset('data/indonesia-country.geojson') }}"
    data-reduced-motion="respect"
>
    <script type="application/json" data-nx10-chart-data>@json($chartPayload)</script>

    <header class="nx10-page-head" data-premium-reveal>
        <div class="nx10-page-title">
            <span class="nx10-kicker"><i aria-hidden="true"></i>Customer Intelligence</span>
            <div class="nx10-title-line">
                <h1>Customers Index</h1>
                <span class="nx10-scope-pill">{{ $company->name }}</span>
            </div>
            <p>Authoritative customer relationships, ownership, project exposure, opportunity context, portal linkage, and evidence coverage from the same company-scoped customer record used by Create and Edit Customer.</p>
        </div>
        <div class="nx10-head-actions">
            <a class="nx10-btn nx10-btn--secondary" href="{{ route('companies.projects.index', $company) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M3 19h18"/></svg>
                Projects
            </a>
            @if ($canCreate)
                <a class="nx10-btn nx10-btn--primary" href="{{ route('companies.customers.create', $company) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Add Customer
                </a>
            @endif
        </div>
    </header>

    <section class="nx10-kpis" aria-label="Customer portfolio summary" data-premium-reveal>
        <article class="nx10-kpi is-cyan">
            <div class="nx10-kpi-top"><span>Total Customers</span><i aria-hidden="true">01</i></div>
            <strong>{{ number_format($stats['total']) }}</strong>
            <p>{{ number_format($stats['active']) }} active · {{ number_format($stats['archived']) }} archived</p>
        </article>
        <a class="nx10-kpi is-green {{ $filters['status'] === 'active' ? 'is-selected' : '' }}" href="{{ route('companies.customers.index', ['company' => $company, 'status' => 'active']) }}">
            <div class="nx10-kpi-top"><span>Active Customers</span><i aria-hidden="true">02</i></div>
            <strong>{{ number_format($stats['active']) }}</strong>
            <p>Available for new project relationships</p>
        </a>
        <a class="nx10-kpi is-blue {{ $filters['projects'] === 'open' ? 'is-selected' : '' }}" href="{{ route('companies.customers.index', ['company' => $company, 'projects' => 'open']) }}">
            <div class="nx10-kpi-top"><span>Open Project Exposure</span><i aria-hidden="true">03</i></div>
            <strong>{{ number_format($stats['customers_with_open_projects']) }}</strong>
            <p>{{ number_format($stats['open_projects']) }} linked current {{ \Illuminate\Support\Str::plural('project', $stats['open_projects']) }}</p>
        </a>
        <article class="nx10-kpi is-violet">
            <div class="nx10-kpi-top"><span>Open Opportunities</span><i aria-hidden="true">04</i></div>
            @if ($directoryCapabilities['opportunities'])
                <strong>{{ number_format($stats['open_opportunities']) }}</strong>
                <p>{{ $opportunityValueSummary }}</p>
            @else
                <strong class="is-text">Not Available</strong>
                <p>Opportunity storage is unavailable</p>
            @endif
        </article>
        <a class="nx10-kpi is-amber {{ $filters['portal'] === 'linked' ? 'is-selected' : '' }}" href="{{ route('companies.customers.index', ['company' => $company, 'portal' => 'linked']) }}">
            <div class="nx10-kpi-top"><span>Portal Linkage</span><i aria-hidden="true">05</i></div>
            <strong>{{ number_format($stats['portal_linked']) }}</strong>
            <p>{{ number_format($portalGap) }} without a linked portal account</p>
        </a>
    </section>

    <div class="nx10-intelligence-grid">
        <main class="nx10-main-stack">
            <section class="nx10-chart-grid" aria-label="Customer intelligence" data-premium-reveal>
                <article class="nx10-card nx10-chart-card">
                    <header class="nx10-card-head">
                        <div><span>Relationship context</span><h2>Relationship Lifecycle</h2><p>Distribution of recorded relationship stages. Profile completeness is not treated as a score.</p></div>
                    </header>
                    @if ($directoryCapabilities['profile'] && collect($relationshipStageData)->sum('value') > 0)
                        <div class="nx10-chart" data-nx10-chart="relationship" role="img" aria-label="Customer relationship lifecycle distribution"></div>
                        <div class="nx10-chart-fallback" data-nx10-fallback="relationship">
                            @foreach ($relationshipStageData as $item)
                                <span><i></i>{{ $item['label'] }} <b>{{ number_format($item['value']) }}</b></span>
                            @endforeach
                        </div>
                    @elseif ($directoryCapabilities['profile'])
                        <div class="nx10-empty-compact"><strong>No relationship profiles recorded</strong><span>Add or edit a Customer to record relationship-stage context.</span></div>
                    @else
                        <div class="nx10-empty-compact is-unavailable"><strong>Relationship lifecycle not available</strong><span>Customer profile storage is not available in the current schema.</span></div>
                    @endif
                </article>

                <article class="nx10-card nx10-chart-card">
                    <header class="nx10-card-head">
                        <div><span>Record governance</span><h2>Customer Record Coverage</h2><p>Recorded coverage for profile, ownership, primary contact, portal linkage, and evidence.</p></div>
                    </header>
                    @if ($stats['total'] > 0)
                        <div class="nx10-chart" data-nx10-chart="coverage" role="img" aria-label="Customer record coverage"></div>
                        <div class="nx10-chart-fallback nx10-chart-fallback--coverage" data-nx10-fallback="coverage">
                            @foreach ($coverageData as $item)
                                <span>{{ $item['label'] }} <b>{{ $item['available'] ? number_format((int) $item['value']).' / '.number_format($stats['total']) : 'Not Available' }}</b></span>
                            @endforeach
                        </div>
                    @else
                        <div class="nx10-empty-compact"><strong>No customer records yet</strong><span>Coverage becomes available after the first Customer is created.</span></div>
                    @endif
                </article>

                <article class="nx10-card nx10-chart-card">
                    <header class="nx10-card-head">
                        <div><span>Linked delivery exposure</span><h2>Project Status Context</h2><p>Actual current, non-archived projects linked to this company. This is not a conversion funnel.</p></div>
                    </header>
                    @if ($projectStatusData->sum('value') > 0)
                        <div class="nx10-chart" data-nx10-chart="projects" role="img" aria-label="Current linked project status distribution"></div>
                        <div class="nx10-chart-fallback" data-nx10-fallback="projects">
                            @foreach ($projectStatusData as $item)
                                <span><i></i>{{ $item['label'] }} <b>{{ number_format($item['value']) }}</b></span>
                            @endforeach
                        </div>
                    @else
                        <div class="nx10-empty-compact"><strong>No current linked projects</strong><span>Project exposure will appear when project records are linked to Customers.</span></div>
                    @endif
                </article>
            </section>



        </main>

        <aside class="nx10-right-rail" aria-label="Customer intelligence context" data-premium-reveal>
            <section class="nx10-card nx10-rail-card">
                <header class="nx10-card-head"><div><span>Commercial context</span><h2>Open Opportunities</h2><p>Most recently updated open opportunity records. No synthetic ranking is applied.</p></div></header>
                @if (! $directoryCapabilities['opportunities'])
                    <div class="nx10-rail-empty"><strong>Not Available</strong><span>Opportunity storage is unavailable in the current schema.</span></div>
                @elseif ($openOpportunities->isEmpty())
                    <div class="nx10-rail-empty"><strong>No open opportunities</strong><span>No Customer currently has an open opportunity record.</span></div>
                @else
                    <div class="nx10-opportunity-list">
                        @foreach ($openOpportunities as $opportunity)
                            <article>
                                <div><strong>{{ $opportunity->name }}</strong><span>{{ $opportunity->customer?->name ?? 'Customer record unavailable' }}</span></div>
                                <b>{{ $formatMoney($opportunity->estimated_value, $opportunity->currency_code) }}</b>
                                <small>{{ $opportunity->probability_pct !== null ? number_format($opportunity->probability_pct).'% recorded probability' : 'Probability Not Recorded' }}@if($opportunity->expected_close_date) · Expected {{ $opportunity->expected_close_date->format('M Y') }}@endif</small>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>



        </aside>
    </div>

    <section class="nx10-card nx10-attention-card" data-premium-reveal>
        <header class="nx10-card-head"><div><span>Governance attention</span><h2>Records Requiring Attention</h2><p>Direct counts from current Customer data. These are not recommendations or follow-up tasks.</p></div></header>
        <div class="nx10-attention-list">
            @if ($directoryCapabilities['profile'])
                <a href="{{ route('companies.customers.index', ['company' => $company, 'profile' => 'missing']) }}"><span>Profile Not Recorded</span><strong>{{ number_format($profileGap) }}</strong><small>Customer records without a CRM profile</small></a>
                <a href="{{ route('companies.customers.index', ['company' => $company, 'owner' => 'unassigned']) }}"><span>Account Owner Unassigned</span><strong>{{ number_format($ownerGap) }}</strong><small>Customer records without account ownership</small></a>
            @else
                <div class="is-unavailable"><span>Profile &amp; Ownership</span><strong>Not Available</strong><small>Customer profile storage unavailable</small></div>
            @endif
            @if ($directoryCapabilities['contacts'])
                <div><span>Primary Contact Not Recorded</span><strong>{{ number_format($contactGap) }}</strong><small>Customer records without a primary contact</small></div>
            @else
                <div class="is-unavailable"><span>Primary Contact</span><strong>Not Available</strong><small>Contact storage unavailable</small></div>
            @endif
            <a href="{{ route('companies.customers.index', ['company' => $company, 'projects' => 'open']) }}"><span>Open Project Exposure</span><strong>{{ number_format($stats['customers_with_open_projects']) }}</strong><small>Customers that cannot be archived while open projects remain</small></a>
            @if ($directoryCapabilities['documents'])
                <a href="{{ route('companies.customers.index', ['company' => $company, 'evidence' => 'missing']) }}"><span>No Evidence Recorded</span><strong>{{ number_format($evidenceGap) }}</strong><small>Customer records without evidence documents</small></a>
            @else
                <div class="is-unavailable"><span>Evidence Coverage</span><strong>Not Available</strong><small>Document storage unavailable</small></div>
            @endif
            <a href="{{ route('companies.customers.index', ['company' => $company, 'portal' => 'unlinked']) }}"><span>Portal Not Linked</span><strong>{{ number_format($portalGap) }}</strong><small>Customer records without portal linkage</small></a>
        </div>
    </section>

    <section class="nx10-card nx10-directory" data-premium-reveal>
                <header class="nx10-directory-head">
                    <div>
                        <span>Primary working surface</span>
                        <h2>Customer Directory</h2>
                        <p>Search, filter, and review authoritative customer records. The primary customer identity stays readable first, while wide desktop data uses controlled horizontal scrolling instead of squeezed columns.</p>
                    </div>
                    <div class="nx10-directory-count">
                        @if ($activeFilters > 0)<small>{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</small>@endif
                        <strong>{{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }}</strong>
                        <span>of {{ number_format($customers->total()) }}</span>
                    </div>
                </header>

                <form method="GET" action="{{ route('companies.customers.index', $company) }}" class="nx10-filter" data-nx10-filter-form aria-label="Customer directory filters">
                    <div class="nx10-filter-primary">
                        <label class="nx10-filter-field nx10-filter-field--search">
                            <span>Search</span>
                            <div class="nx10-control">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Customer, number, contact, owner, opportunity…" autocomplete="off">
                            </div>
                        </label>
                        <label class="nx10-filter-field">
                            <span>Record State</span>
                            <select name="status"><option value="">All states</option><option value="active" @selected($filters['status'] === 'active')>Active</option><option value="archived" @selected($filters['status'] === 'archived')>Archived</option></select>
                        </label>
                        <label class="nx10-filter-field">
                            <span>Customer Type</span>
                            <select name="type"><option value="">All types</option><option value="residential" @selected($filters['type'] === 'residential')>Residential</option><option value="commercial" @selected($filters['type'] === 'commercial')>Commercial</option></select>
                        </label>
                        <label class="nx10-filter-field">
                            <span>Relationship Stage</span>
                            <select name="stage" @disabled(! $directoryCapabilities['profile'])>
                                <option value="">{{ $directoryCapabilities['profile'] ? 'All stages' : 'Not Available' }}</option>
                                @if ($directoryCapabilities['profile'])
                                    @foreach ($stageLabels as $value => $label)<option value="{{ $value }}" @selected($filters['stage'] === $value)>{{ $label }}</option>@endforeach
                                @endif
                            </select>
                        </label>
                        <div class="nx10-filter-actions">
                            <button type="button" class="nx10-btn nx10-btn--secondary nx10-more-filter" data-nx10-filter-toggle aria-expanded="{{ $activeFilters > 4 ? 'true' : 'false' }}" aria-controls="nx10-advanced-filters">
                                Additional Filters
                                <span aria-hidden="true">{{ $activeFilters > 4 ? '−' : '+' }}</span>
                            </button>
                            <button type="submit" class="nx10-btn nx10-btn--primary">Apply Filters</button>
                        </div>
                    </div>

                    <div id="nx10-advanced-filters" class="nx10-filter-advanced" data-nx10-advanced-filters @if ($activeFilters <= 4) hidden @endif>
                        <label class="nx10-filter-field"><span>Account Owner</span><select name="owner" @disabled(! $directoryCapabilities['profile'])><option value="">{{ $directoryCapabilities['profile'] ? 'All owners' : 'Not Available' }}</option>@if($directoryCapabilities['profile'])<option value="unassigned" @selected($filters['owner'] === 'unassigned')>Unassigned</option>@foreach($accountOwners as $ownerOption)<option value="{{ $ownerOption->id }}" @selected((string) $filters['owner'] === (string) $ownerOption->id)>{{ $ownerOption->name }}</option>@endforeach @endif</select></label>
                        <label class="nx10-filter-field"><span>Region</span><select name="region" @disabled(! $directoryCapabilities['profile'])><option value="">{{ $directoryCapabilities['profile'] ? 'All regions' : 'Not Available' }}</option>@if($directoryCapabilities['profile'])@foreach($regions as $regionOption)<option value="{{ $regionOption }}" @selected($filters['region'] === $regionOption)>{{ $regionOption }}</option>@endforeach @endif</select></label>
                        <label class="nx10-filter-field"><span>Portal Linkage</span><select name="portal"><option value="">All portal states</option><option value="linked" @selected($filters['portal'] === 'linked')>Linked</option><option value="unlinked" @selected($filters['portal'] === 'unlinked')>Not Linked</option></select></label>
                        <label class="nx10-filter-field"><span>Open Project Exposure</span><select name="projects"><option value="">All exposure</option><option value="open" @selected($filters['projects'] === 'open')>Has Open Projects</option><option value="none" @selected($filters['projects'] === 'none')>No Open Projects</option></select></label>
                        <label class="nx10-filter-field"><span>Customer Profile</span><select name="profile" @disabled(! $directoryCapabilities['profile'])><option value="">{{ $directoryCapabilities['profile'] ? 'All profile states' : 'Not Available' }}</option>@if($directoryCapabilities['profile'])<option value="recorded" @selected($filters['profile'] === 'recorded')>Recorded</option><option value="missing" @selected($filters['profile'] === 'missing')>Not Recorded</option>@endif</select></label>
                        <label class="nx10-filter-field"><span>Evidence</span><select name="evidence" @disabled(! $directoryCapabilities['documents'])><option value="">{{ $directoryCapabilities['documents'] ? 'All evidence states' : 'Not Available' }}</option>@if($directoryCapabilities['documents'])<option value="recorded" @selected($filters['evidence'] === 'recorded')>Recorded</option><option value="missing" @selected($filters['evidence'] === 'missing')>Not Recorded</option>@endif</select></label>
                        <label class="nx10-filter-field"><span>Sort By</span><select name="sort"><option value="created_at" @selected($filters['sort'] === 'created_at')>Created Date</option><option value="updated_at" @selected($filters['sort'] === 'updated_at')>Record Update</option><option value="name" @selected($filters['sort'] === 'name')>Customer Name</option><option value="customer_number" @selected($filters['sort'] === 'customer_number')>Customer Number</option><option value="type" @selected($filters['sort'] === 'type')>Customer Type</option><option value="status" @selected($filters['sort'] === 'status')>Record State</option>@if($directoryCapabilities['profile'])<option value="relationship_stage" @selected($filters['sort'] === 'relationship_stage')>Relationship Stage</option><option value="region" @selected($filters['sort'] === 'region')>Region</option>@endif<option value="active_projects" @selected($filters['sort'] === 'active_projects')>Open Project Exposure</option></select></label>
                        <label class="nx10-filter-field"><span>Direction</span><select name="direction"><option value="desc" @selected($filters['direction'] === 'desc')>Descending</option><option value="asc" @selected($filters['direction'] === 'asc')>Ascending</option></select></label>
                        @if ($activeFilters > 0)
                            <a class="nx10-clear-link" href="{{ route('companies.customers.index', $company) }}">Clear all filters</a>
                        @endif
                    </div>
                </form>

                @if ($customers->isEmpty())
                    <div class="nx10-empty-directory">
                        <span class="nx10-empty-icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"><circle cx="20" cy="17" r="6"/><path d="M8 39c1.5-8 5.5-12 12-12s10.5 4 12 12"/><path d="M34 10h10v10M44 10 32 22"/></svg></span>
                        @if ($stats['total'] === 0)
                            <h3>No customer records yet</h3>
                            <p>Create the first Customer to establish the authoritative relationship record for this company.</p>
                            @if ($canCreate)<a class="nx10-btn nx10-btn--primary" href="{{ route('companies.customers.create', $company) }}">Add Customer</a>@endif
                        @else
                            <h3>No Customers match these filters</h3>
                            <p>The directory contains {{ number_format($stats['total']) }} records, but none match the current search and filter combination.</p>
                            <a class="nx10-btn nx10-btn--secondary" href="{{ route('companies.customers.index', $company) }}">Clear Filters</a>
                        @endif
                    </div>
                @else
                    <div class="nx10-table-scroll" tabindex="0" aria-label="Customer Directory, horizontally scrollable when needed">
                        <table class="nx10-table">
                            <thead><tr><th>Customer</th><th>Relationship</th><th>Region / Industry</th><th>Account Owner</th><th>Project Exposure</th><th>Opportunity Context</th><th>Portal</th><th>Evidence</th><th>Record Update</th><th>State</th><th><span class="sr-only">Actions</span></th></tr></thead>
                            <tbody>
                            @foreach ($customers as $customer)
                                @php
                                    $profile = $directoryCapabilities['profile'] ? $customer->profile : null;
                                    $primaryContact = $directoryCapabilities['contacts'] ? $customer->contacts->first() : null;
                                    $customerStage = $profile?->relationship_stage;
                                    $ownerRecord = $profile?->accountOwner;
                                    $opportunityAssessment = $directoryCapabilities['assessment'] && $profile ? $profile->opportunity_assessment : null;
                                    $openOpportunityCount = $directoryCapabilities['opportunities'] ? (int) ($customer->open_opportunities_count ?? 0) : null;
                                    $documentCount = $directoryCapabilities['documents'] ? (int) ($customer->documents_count ?? 0) : null;
                                    $hasArchiveBlocker = (int) $customer->active_projects_count > 0;
                                    $customerStatus = $statusLabels[$customer->status] ?? 'Recorded';
                                    $customerType = $typeLabels[$customer->type] ?? 'Recorded Type';
                                @endphp
                                <tr class="{{ $customer->status === 'archived' ? 'is-archived' : '' }}">
                                    <td class="nx10-customer-cell">
                                        <a class="nx10-customer-name" href="{{ route('companies.customers.show', [$company, $customer]) }}">{{ $customer->name }}</a>
                                        @if (filled($profile?->preferred_name) && $profile->preferred_name !== $customer->name)<small class="nx10-preferred-name">{{ $profile->preferred_name }}</small>@endif
                                        <span class="nx10-meta-line"><b>{{ $customer->customer_number }}</b><i aria-hidden="true"></i>{{ $customerType }}</span>
                                        @if ($primaryContact)
                                            <span class="nx10-contact-line">{{ $primaryContact->full_name }}@if(filled($primaryContact->email)) · <span title="{{ $primaryContact->email }}">{{ $primaryContact->email }}</span>@endif</span>
                                        @elseif (filled($customer->email))
                                            <span class="nx10-contact-line" title="{{ $customer->email }}">{{ $customer->email }}</span>
                                        @elseif ($directoryCapabilities['contacts'])
                                            <span class="nx10-muted">Primary contact not recorded</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($directoryCapabilities['profile'])
                                            @if ($profile)
                                                <span class="nx10-badge is-stage">{{ $stageLabels[$customerStage] ?? 'Recorded Stage' }}</span>
                                                <small>{{ $directoryCapabilities['assessment'] ? ($opportunityAssessmentLabels[$opportunityAssessment] ?? 'Assessment Recorded') : 'Assessment Not Available' }}</small>
                                            @else
                                                <span class="nx10-state-text">Profile Not Recorded</span>
                                            @endif
                                        @else
                                            <span class="nx10-state-text">Not Available</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($directoryCapabilities['profile'] && $profile)
                                            <strong class="nx10-cell-primary">{{ filled($profile->region) ? $profile->region : 'Region Not Recorded' }}</strong>
                                            <small>{{ filled($profile->primary_industry) ? $profile->primary_industry : 'Industry Not Recorded' }}</small>
                                        @else
                                            <span class="nx10-state-text">{{ $directoryCapabilities['profile'] ? 'Profile Not Recorded' : 'Not Available' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($directoryCapabilities['profile'])
                                            @if ($ownerRecord)
                                                <strong class="nx10-cell-primary">{{ $ownerRecord->name }}</strong><small title="{{ $ownerRecord->email }}">{{ $ownerRecord->email }}</small>
                                            @else
                                                <span class="nx10-badge is-warning">Unassigned</span>
                                            @endif
                                        @else
                                            <span class="nx10-state-text">Not Available</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="nx10-project-count">{{ number_format((int) $customer->active_projects_count) }} open</strong>
                                        <small>{{ number_format((int) $customer->projects_count) }} total linked</small>
                                        @if ($hasArchiveBlocker)<span class="nx10-blocker">Archive blocked by open project</span>@endif
                                    </td>
                                    <td>
                                        @if ($directoryCapabilities['opportunities'])
                                            <strong class="nx10-cell-primary">{{ number_format($openOpportunityCount) }} open</strong>
                                            @if ($directoryCapabilities['assessment'] && $profile)
                                                <small>{{ $opportunityAssessmentLabels[$opportunityAssessment] ?? 'Assessment Recorded' }}</small>
                                            @elseif (! $profile && $directoryCapabilities['profile'])
                                                <small>Profile Not Recorded</small>
                                            @else
                                                <small>Assessment Not Available</small>
                                            @endif
                                        @else
                                            <span class="nx10-state-text">Not Available</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($customer->portalUser)
                                            <span class="nx10-badge is-success">Linked</span><small title="{{ $customer->portalUser->email }}">{{ $customer->portalUser->email }}</small>
                                        @else
                                            <span class="nx10-state-text">Not Linked</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($directoryCapabilities['documents'])
                                            @if ($documentCount > 0)<strong class="nx10-cell-primary">{{ number_format($documentCount) }} recorded</strong><small>Evidence records</small>@else<span class="nx10-state-text">No Evidence</span>@endif
                                        @else
                                            <span class="nx10-state-text">Not Available</span>
                                        @endif
                                    </td>
                                    <td><strong class="nx10-cell-primary">{{ $customer->updated_at->format('M j, Y') }}</strong><small title="{{ $customer->updated_at->toDayDateTimeString() }}">{{ $customer->updated_at->diffForHumans() }}</small></td>
                                    <td><span class="nx10-badge {{ $customer->status === 'active' ? 'is-success' : 'is-neutral' }}">{{ $customerStatus }}</span></td>
                                    <td class="nx10-actions-cell">
                                        <button type="button" class="nx10-menu-trigger" data-nx10-menu-trigger aria-haspopup="menu" aria-expanded="false" aria-label="Actions for {{ $customer->name }}">•••</button>
                                        <div class="nx10-menu" role="menu" data-nx10-menu hidden>
                                            <a role="menuitem" href="{{ route('companies.customers.show', [$company, $customer]) }}">Open Profile</a>
                                            @if ($canUpdate && $customer->status === 'active')<a role="menuitem" href="{{ route('companies.customers.edit', [$company, $customer]) }}">Edit Customer</a>@endif
                                            <a role="menuitem" href="{{ route('companies.projects.index', ['company' => $company, 'search' => $customer->name]) }}">Find Linked Projects</a>
                                            @if ($canArchive && $customer->status === 'active')
                                                @if ($hasArchiveBlocker)
                                                    <span role="menuitem" aria-disabled="true" class="is-disabled" title="Complete, cancel, or archive open projects before archiving this Customer.">Archive Customer · Blocked</span>
                                                @else
                                                    <form method="POST" action="{{ route('companies.customers.archive', [$company, $customer]) }}" data-nx10-archive-form>
                                                        @csrf @method('PATCH')
                                                        <button type="submit" role="menuitem">Archive Customer</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="nx10-pagination-wrap">@include('partials.pagination', ['paginator' => $customers])</div>
                @endif
            </section>

    <section class="nx10-lower-grid" data-premium-reveal>
        <section class="nx10-card nx10-geography">
            <header class="nx10-card-head nx10-card-head--map">
                <div><span>Geographic context</span><h2>Customer-Linked Project Geography</h2><p>Exact markers use stored project coordinates only. Customer locations are not converted into invented map points.</p></div>
                <div class="nx10-map-state"><strong>{{ number_format($exactProjectCoordinateCount) }}</strong><span>Exact Project {{ \Illuminate\Support\Str::plural('Coordinate', $exactProjectCoordinateCount) }}</span></div>
            </header>
            <div class="nx10-map-layout">
                <div class="nx10-map-shell">
                    @if ($exactProjectCoordinateCount > 0)
                        <div class="nx10-map" data-nx10-map role="img" aria-label="Map of exact coordinates for customer-linked projects"></div>
                        <div class="nx10-map-fallback" data-nx10-map-fallback>Map visualization unavailable. Exact coordinate records remain listed by project in the underlying project workspace.</div>
                        @if ($exactProjectCoordinateCount > $projectMapPoints->count())<small class="nx10-map-limit">Showing the {{ number_format($projectMapPoints->count()) }} most recently updated exact coordinate records of {{ number_format($exactProjectCoordinateCount) }}.</small>@endif
                    @else
                        <div class="nx10-empty-map"><strong>No exact project coordinates recorded</strong><span>Regional project context is shown without inventing point coordinates.</span></div>
                    @endif
                </div>
                <aside class="nx10-region-list" aria-label="Project regions">
                    <span>Recorded Project Regions</span>
                    @forelse ($projectRegionData as $regionItem)
                        <div><strong title="{{ $regionItem['label'] }}">{{ $regionItem['label'] }}</strong><b>{{ number_format($regionItem['value']) }}</b></div>
                    @empty
                        <div class="nx10-region-empty">No project province data recorded.</div>
                    @endforelse
                </aside>
            </div>
        </section>

        <section class="nx10-card nx10-chronology-card">
            <header class="nx10-card-head"><div><span>Record chronology</span><h2>Recent Customer Record Updates</h2><p>Latest Customer record timestamps. This is not an activity feed.</p></div></header>
            @if ($recentCustomerUpdates->isEmpty())
                <div class="nx10-rail-empty"><strong>No customer updates</strong><span>No Customer records are available.</span></div>
            @else
                <div class="nx10-update-list nx10-update-list--chronology">
                    @foreach ($recentCustomerUpdates as $updatedCustomer)
                        <a href="{{ route('companies.customers.show', [$company, $updatedCustomer]) }}">
                            <span><strong>{{ $updatedCustomer->name }}</strong><small>{{ $updatedCustomer->customer_number }}</small></span>
                            <time datetime="{{ $updatedCustomer->updated_at->toIso8601String() }}">{{ $updatedCustomer->updated_at->diffForHumans() }}</time>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </section>
</div>
@endsection
