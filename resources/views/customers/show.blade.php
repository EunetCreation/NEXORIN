@extends('layouts.app')

@section('title', $customer->name)
@section('page-eyebrow', 'Customer profile')
@section('page-heading', $customer->name)
@section('force-theme', 'dark')
@section('body-class', 'page-customer-show-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-customer-show-v6.css') }}?v=011v11" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001011.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-premium-motion-v1.css') }}?v=1.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}" defer></script>
    <script src="{{ asset('js/nexorin-customer-show-v6.js') }}?v=011v11" defer></script>
    <script src="{{ asset('js/nexorin-premium-motion-v1.js') }}?v=1.0" defer></script>
@endpush

@php
    $currentUser = auth()->user();
    $canUpdate = $currentUser->hasCompanyPermission($company, 'customer.update');
    $canArchive = $currentUser->hasCompanyPermission($company, 'customer.delete');
    $canCreateProject = $currentUser->hasAnyCompanyPermission($company, ['project.create', 'project.create_own']);

    $profile = $profileReady ? $customer->profile : null;
    $primaryContact = $contactsReady ? $customer->contacts->firstWhere('is_primary', true) : null;
    $portalLinked = (bool) $customer->portalUser;
    $location = collect([$customer->city, $customer->province, $customer->postal_code])->filter()->join(' · ');
    $customerInitials = collect(preg_split('/\s+/', trim((string) $customer->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => function_exists('mb_substr') ? mb_strtoupper(mb_substr((string) $part, 0, 1)) : strtoupper(substr((string) $part, 0, 1)))
        ->implode('');
    $customerInitials = $customerInitials !== '' ? $customerInitials : 'CU';
    $customerAvatarAsset = $customer->type === 'commercial'
        ? asset('images/customer/nexorin-enterprise-customer-placeholder.webp')
        : asset('images/customer/nexorin-professional-customer-avatar.webp');

    $stageLabels = [
        'lead' => 'Lead',
        'prospect' => 'Prospect',
        'active' => 'Active Relationship',
        'on_hold' => 'On Hold',
        'closed' => 'Closed',
    ];
    $stageOrder = ['lead', 'prospect', 'active', 'on_hold', 'closed'];
    $stageLabel = $profile ? ($stageLabels[$profile->relationship_stage] ?? 'Not Recorded') : 'Not Recorded';

    $projectStatusLabels = [
        'draft' => 'Draft',
        'survey' => 'Survey',
        'engineering' => 'Engineering',
        'quotation' => 'Quotation',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
    $projectStatusColors = [
        'draft' => '#6f8095',
        'survey' => '#6b77e8',
        'engineering' => '#13b7c8',
        'quotation' => '#e5a72f',
        'approved' => '#2abf8c',
        'completed' => '#17a673',
        'cancelled' => '#d45d68',
    ];

    $assessmentLabels = [
        'not_assessed' => 'Not Assessed',
        'no_parent' => 'No Parent Company Recorded',
        'has_parent' => 'Parent Company Recorded',
        'no_opportunity' => 'No Opportunity Recorded',
        'has_opportunity' => 'Opportunity Recorded',
    ];

    $documentCategoryLabels = [
        'incorporation' => 'Incorporation',
        'tax_registration' => 'Tax Registration',
        'financial_statements' => 'Financial Statements',
        'company_profile' => 'Company Profile',
    ];
    $documentStatusLabels = [
        'recorded' => 'Recorded',
        'review_required' => 'Review Required',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ];

    $opportunityStatusLabels = [
        'open' => 'Open',
        'won' => 'Won',
        'lost' => 'Lost',
        'closed' => 'Closed',
    ];

    $compactNumber = static function (float|int|string|null $value, int $decimals = 1): string {
        if ($value === null || $value === '') {
            return 'Not Recorded';
        }
        $numeric = (float) $value;
        $absolute = abs($numeric);
        foreach ([[1_000_000_000_000, 'T'], [1_000_000_000, 'B'], [1_000_000, 'M'], [1_000, 'K']] as [$divisor, $suffix]) {
            if ($absolute >= $divisor) {
                return number_format($numeric / $divisor, $decimals).$suffix;
            }
        }
        return number_format($numeric, $decimals === 0 ? 0 : $decimals);
    };

    $formatMoney = static function ($value, ?string $currency) use ($compactNumber): string {
        if ($value === null || $value === '') {
            return 'Value Not Recorded';
        }
        $code = strtoupper(trim((string) $currency));
        return $code !== '' ? $code.' '.$compactNumber($value) : 'Currency Not Recorded · '.$compactNumber($value);
    };

    $pipelineTotals = $openOpportunityCurrencyTotals->map(fn ($row) => [
        'currency' => filled($row->currency_code) ? strtoupper((string) $row->currency_code) : null,
        'value' => (float) $row->total_value,
        'count' => (int) $row->opportunity_count,
    ])->values();
    $pipelineHeadline = $pipelineTotals->count() === 0
        ? 'Not Recorded'
        : ($pipelineTotals->count() === 1
            ? $formatMoney($pipelineTotals->first()['value'], $pipelineTotals->first()['currency'])
            : 'Mixed Currencies');

    $parentName = null;
    if ($profile) {
        $parentName = $profile->parentCustomer?->name ?: $profile->external_parent_name;
    }

    $recordGaps = collect();
    if (! $profileReady) {
        $recordGaps->push('Customer profile details are not available.');
    } elseif (! $profile) {
        $recordGaps->push('Customer profile has not been recorded.');
    } else {
        if (! $profile->accountOwner) $recordGaps->push('Account owner is not assigned.');
        if (! filled($profile->primary_industry)) $recordGaps->push('Primary industry is not recorded.');
        if (! filled($profile->region)) $recordGaps->push('Relationship region is not recorded.');
        if (! $assessmentReady) {
            $recordGaps->push('Customer assessment details are not available.');
        } else {
            if (($profile->opportunity_assessment ?? 'not_assessed') === 'not_assessed') $recordGaps->push('Opportunity assessment is not completed.');
            if (! $profile->review_acknowledged_at) $recordGaps->push('Customer review has not been acknowledged.');
        }
    }
    if ($contactsReady && $customer->contacts_count === 0) $recordGaps->push('No customer contact is recorded.');
    if (! $portalLinked) $recordGaps->push('Portal access is not linked.');

    $commercialPreferenceValues = $profile ? [
        $profile->preferred_contract_type,
        $profile->engagement_model,
        $profile->payment_terms,
        $profile->contract_duration,
        $profile->sales_team,
        $profile->currency_code,
        $profile->key_considerations,
    ] : [];
    $hasCommercialPreferences = collect($commercialPreferenceValues)->contains(fn ($value) => filled($value));

    $profileStateLabel = ! $profileReady ? 'Not Available' : ($profile ? 'Recorded' : 'Not Recorded');
    $reviewStateLabel = ! $assessmentReady ? 'Not Available' : ($profile?->review_acknowledged_at ? 'Acknowledged' : 'Not Recorded');
    $archiveStateLabel = $customer->blocking_projects_count > 0 ? 'Blocked' : 'Available';

    $nextAction = null;
    if (! $customer->isActive()) {
        $nextAction = ['title' => 'Archived relationship', 'copy' => 'This customer is retained for historical governance. New relationship actions are disabled.', 'anchor' => null, 'tone' => 'neutral'];
    } elseif (! $profileReady || ! $profile) {
        $nextAction = ['title' => 'Complete customer profile', 'copy' => 'Complete the customer profile before relying on relationship insights.', 'anchor' => 'identity', 'tone' => 'review'];
    } elseif (! $profile->accountOwner) {
        $nextAction = ['title' => 'Assign account owner', 'copy' => 'Relationship ownership is not recorded for this customer.', 'anchor' => 'ownership', 'tone' => 'review'];
    } elseif (! $assessmentReady) {
        $nextAction = ['title' => 'Assessment not available', 'copy' => 'Review assessment details are not available for this customer record.', 'anchor' => null, 'tone' => 'neutral'];
    } elseif (($profile->opportunity_assessment ?? 'not_assessed') === 'not_assessed') {
        $nextAction = ['title' => 'Complete opportunity assessment', 'copy' => 'The opportunity state remains Not Assessed.', 'anchor' => 'opportunity', 'tone' => 'review'];
    } elseif (! $profile->review_acknowledged_at) {
        $nextAction = ['title' => 'Acknowledge customer review', 'copy' => 'The current customer record does not yet contain review acknowledgement evidence.', 'anchor' => 'review', 'tone' => 'review'];
    } elseif ($customer->blocking_projects_count > 0) {
        $nextAction = ['title' => 'Open projects remain active', 'copy' => $customer->blocking_projects_count.' project '.($customer->blocking_projects_count === 1 ? 'record is' : 'records are').' blocking customer archive.', 'anchor' => null, 'tone' => 'active'];
    } else {
        $nextAction = ['title' => 'Relationship record current', 'copy' => 'No recorded customer information gap currently requires action.', 'anchor' => null, 'tone' => 'positive'];
    }

    $hasExposureData = $customer->projects_count > 0
        || ($opportunitiesReady && $customer->open_opportunities_count > 0)
        || ($documentsReady && $customer->documents_count > 0);

    $missingProfileFields = collect();
    if (! $profile) {
        $missingProfileFields->push('Primary industry', 'Business model', 'Relationship stage', 'Account owner', 'Website');
    } else {
        if (! filled($profile->primary_industry)) $missingProfileFields->push('Primary industry');
        if (! filled($profile->business_model)) $missingProfileFields->push('Business model');
        if (! filled($profile->relationship_stage)) $missingProfileFields->push('Relationship stage');
        if (! $profile->accountOwner) $missingProfileFields->push('Account owner');
        if (! filled($profile->website)) $missingProfileFields->push('Website');
    }

    $hasAnnualRevenue = $profile && $profile->annual_revenue_amount !== null;

    $projectChartData = collect($projectStatusLabels)
        ->map(function ($label, $status) use ($projectStatusCounts, $projectStatusColors) {
            $count = (int) ($projectStatusCounts[$status] ?? 0);
            return $count > 0 ? ['name' => $label, 'value' => $count, 'itemStyle' => ['color' => $projectStatusColors[$status]]] : null;
        })
        ->filter()
        ->values();
@endphp

@section('content')
<div class="nx11 nx11-v9" data-nx11-root data-project-chart='@json($projectChartData)'>
    <header class="nx11-v9-pagehead" data-nx-motion="reveal">
        <div class="nx11-v9-pagecopy">
            <div class="nx11-breadcrumb"><a href="{{ route('companies.customers.index', $company) }}">Customers</a><span>/</span><span>{{ $customer->name }}</span></div>
            <h1>Customer 360</h1>
            <p>Customer profile, relationship context, commercial exposure, and governance.</p>
        </div>
        <div class="nx11-v9-actions">
            @if ($canCreateProject && $customer->isActive())
                <a class="nx11-v9-action is-secondary" href="{{ route('companies.projects.create', ['company' => $company, 'customer_id' => $customer->id]) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span>New Project</span>
                </a>
            @endif
            @if ($canUpdate && $customer->isActive())
                <a class="nx11-v9-action is-primary" href="{{ route('companies.customers.edit', [$company, $customer]) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 20 4.2-1 9.7-9.7-3.2-3.2L5 15.8 4 20Z"/><path d="m13.7 7.1 3.2 3.2"/></svg>
                    <span>Edit Customer</span>
                </a>
            @endif
        </div>
    </header>

    <div class="nx11-v9-layout">
        <main class="nx11-v9-main">
            <section class="nx11-v9-hero" data-nx-motion="reveal">
                <div class="nx11-v9-hero-identity">
                    <div class="nx11-v9-avatar" aria-hidden="true">
                        <img src="{{ $customerAvatarAsset }}" alt="" loading="eager" decoding="async">
                    </div>
                    <div class="nx11-v9-identity-copy">
                        <div class="nx11-v9-statusline">
                            <span class="nx11-v9-status is-{{ $customer->status }}"><i></i>{{ $customer->isActive() ? 'Active Customer' : 'Archived Customer' }}</span>
                            @if($profile && filled($profile->relationship_stage))
                                <span class="nx11-v9-stage-pill">{{ $stageLabel }}</span>
                            @endif
                        </div>
                        <h2>{{ $customer->name }}</h2>
                        <p>{{ $profile?->preferred_name ?: $customer->type_label.' customer' }}</p>
                        <div class="nx11-v9-identity-tags">
                            <span class="is-id">{{ $customer->customer_number }}</span>
                            @if($location)<span>{{ $location }}</span>@endif
                            @if(filled($profile?->primary_industry))<span>{{ $profile->primary_industry }}</span>@endif
                        </div>
                    </div>
                </div>

                <dl class="nx11-v9-hero-facts">
                    <div>
                        <dt>Account Owner</dt>
                        <dd>{{ $profile?->accountOwner?->name ?: 'Not Assigned' }}</dd>
                        <small>{{ $profile?->accountOwner?->email ?: 'Ownership not recorded' }}</small>
                    </div>
                    <div>
                        <dt>Primary Contact</dt>
                        <dd>{{ $primaryContact?->full_name ?: $customer->name }}</dd>
                        <small>{{ $primaryContact?->job_title ?: ($customer->email ?: 'Role not recorded') }}</small>
                    </div>
                    <div>
                        <dt>Headquarters</dt>
                        <dd>{{ $profile?->hq_country ?: ($customer->province ?: 'Not Recorded') }}</dd>
                        <small>{{ $location ?: 'Location detail not recorded' }}</small>
                    </div>
                    <div>
                        <dt>{{ $hasAnnualRevenue ? 'Annual Revenue' : 'Customer Type' }}</dt>
                        <dd>{{ $hasAnnualRevenue ? $formatMoney($profile->annual_revenue_amount, $profile->revenue_currency_code ?: $profile->currency_code) : $customer->type_label }}</dd>
                        <small>{{ $hasAnnualRevenue ? ($profile->year_established ? 'Established '.$profile->year_established : 'Recorded customer profile') : (filled($profile?->primary_industry) ? $profile->primary_industry : 'Industry not recorded') }}</small>
                    </div>
                </dl>

                <aside class="nx11-v9-relationship">
                    <span class="nx11-v9-eyebrow">Relationship</span>
                    @if($profile && filled($profile->relationship_stage))
                        <strong>{{ $stageLabel }}</strong>
                        <small>Current recorded stage</small>
                    @else
                        <strong class="is-empty">Stage not recorded</strong>
                        <small>Add a relationship stage when it is known.</small>
                    @endif
                    <div class="nx11-v9-relationship-meta">
                        <div><span>Profile</span><b>{{ $profileStateLabel }}</b></div>
                        <div><span>Review</span><b>{{ $reviewStateLabel }}</b></div>
                        <div><span>Portal</span><b class="{{ $portalLinked ? 'is-positive' : '' }}">{{ $portalLinked ? 'Linked' : 'Not Linked' }}</b></div>
                    </div>
                </aside>

                <div class="nx11-v9-summary" aria-label="Customer relationship summary">
                    <article>
                        <span>Open Opportunities</span>
                        <strong>{{ $opportunitiesReady ? number_format($customer->open_opportunities_count) : '—' }}</strong>
                        <small>{{ $pipelineTotals->isNotEmpty() ? $pipelineHeadline : 'No open commercial records' }}</small>
                    </article>
                    <article>
                        <span>Linked Projects</span>
                        <strong>{{ number_format($customer->projects_count) }}</strong>
                        <small>{{ number_format($customer->current_projects_count) }} current project{{ $customer->current_projects_count === 1 ? '' : 's' }}</small>
                    </article>
                    <article>
                        <span>Customer Evidence</span>
                        <strong>{{ $documentsReady ? number_format($customer->documents_count) : '—' }}</strong>
                        <small>{{ $documentsReady ? 'Recorded documents' : 'Not Available' }}</small>
                    </article>
                    <article>
                        <span>Portal Access</span>
                        <strong class="{{ $portalLinked ? 'is-positive' : 'is-muted' }}">{{ $portalLinked ? 'Linked' : 'Not Linked' }}</strong>
                        <small>{{ $portalLinked ? 'Customer-facing access' : 'No portal identity' }}</small>
                    </article>
                </div>
            </section>

            <div class="nx11-v9-overview-grid {{ $profile && filled($profile->relationship_stage) ? 'has-stage' : 'is-profile-only' }}">
                <section class="nx11-v9-card nx11-v9-account" data-nx-motion="reveal">
                    <header class="nx11-v9-card-head">
                        <div><span>Account Overview</span><h3>Business &amp; Relationship Profile</h3></div>
                        @if($canUpdate && $customer->isActive())<a href="{{ route('companies.customers.edit', [$company, $customer]) }}#identity">Edit profile</a>@endif
                    </header>

                    <div class="nx11-v9-profile-list">
                        <div><span>Customer Type</span><strong>{{ $customer->type_label }}</strong></div>
                        <div><span>Location</span><strong>{{ $location ?: 'Not Recorded' }}</strong></div>
                        @if(filled($profile?->primary_industry))<div><span>Primary Industry</span><strong>{{ $profile->primary_industry }}</strong></div>@endif
                        @if(filled($profile?->business_model))<div><span>Business Model</span><strong>{{ $profile->business_model }}</strong></div>@endif
                        @if($parentName)<div><span>Parent Company</span><strong>{{ $parentName }}</strong></div>@endif
                        @if($profile?->website)<div><span>Website</span><strong><a href="{{ $profile->website }}" target="_blank" rel="noopener noreferrer">{{ $profile->website }}</a></strong></div>@endif
                    </div>

                    @if($missingProfileFields->isNotEmpty())
                        <div class="nx11-v9-gap-strip">
                            <div>
                                <span>Profile incomplete</span>
                                <p>{{ $missingProfileFields->count() }} relationship or business field{{ $missingProfileFields->count() === 1 ? '' : 's' }} still need recorded information.</p>
                            </div>
                            <div class="nx11-v9-gap-fields">
                                <span>Missing</span>
                                <p>{{ $missingProfileFields->take(5)->join(' · ') }}</p>
                            </div>
                        </div>
                    @endif

                    @if($profile?->description)
                        <div class="nx11-v9-description"><span>Business Description</span><p>{{ $profile->description }}</p></div>
                    @endif
                </section>

                @if($profile && filled($profile->relationship_stage))
                    <section class="nx11-v9-card nx11-v9-stage" data-nx-motion="reveal">
                        <header class="nx11-v9-card-head">
                            <div><span>Relationship Stage</span><h3>Current Customer Stage</h3></div>
                        </header>
                        <div class="nx11-v9-stage-content">
                            <div class="nx11-v9-stage-state">
                                <strong>{{ $stageLabel }}</strong>
                                <p>Current relationship stage recorded in the customer profile.</p>
                            </div>
                            <div class="nx11-v9-stage-track" aria-label="Relationship stage sequence">
                                @foreach($stageOrder as $stage)
                                    @php $current = $profile->relationship_stage === $stage; @endphp
                                    <div class="{{ $current ? 'is-current' : '' }}">
                                        <i></i>
                                        <span>{{ $stageLabels[$stage] }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @if($canUpdate && $customer->isActive() && $profileReady)
                                <a href="{{ route('companies.customers.edit', [$company, $customer]) }}#identity">Review relationship stage →</a>
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            <section class="nx11-v9-card nx11-v9-exposure" data-nx-motion="reveal">
                <header class="nx11-v9-card-head">
                    <div><span>Commercial Exposure</span><h3>Projects, Opportunities &amp; Evidence</h3></div>
                </header>
                <div class="nx11-v9-exposure-grid">
                    <article class="is-projects">
                        <div class="nx11-v9-exposure-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19V8l8-4 8 4v11H4Z"/><path d="M9 19v-5h6v5"/></svg></div>
                        <div class="nx11-v9-exposure-copy"><span>Project Portfolio</span><strong>{{ number_format($customer->projects_count) }} linked project{{ $customer->projects_count === 1 ? '' : 's' }}</strong><p>{{ $customer->projects_count > 0 ? number_format($customer->current_projects_count).' current project'.($customer->current_projects_count === 1 ? '' : 's').' are linked to this customer.' : 'Project exposure appears when a project references this customer.' }}</p></div>
                        @if($canCreateProject && $customer->isActive())<a href="{{ route('companies.projects.create', ['company'=>$company,'customer_id'=>$customer->id]) }}">{{ $customer->projects_count > 0 ? 'New project' : 'Create project' }} →</a>@endif
                    </article>
                    <article class="is-opportunities">
                        <div class="nx11-v9-exposure-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/></svg></div>
                        <div class="nx11-v9-exposure-copy"><span>Commercial Opportunities</span><strong>{{ $opportunitiesReady ? number_format($customer->open_opportunities_count).' open' : 'Not Available' }}</strong><p>{{ ! $opportunitiesReady ? 'Opportunity tracking is not available for this customer record.' : ($customer->open_opportunities_count > 0 ? ($pipelineTotals->count() === 1 ? $pipelineHeadline.' recorded in open opportunities.' : 'Open opportunity values are kept separate by currency.') : 'Commercial pipeline appears when an opportunity is recorded.') }}</p></div>
                        @if($canUpdate && $customer->isActive())<a href="{{ route('companies.customers.edit', [$company, $customer]) }}#opportunities">{{ $opportunitiesReady && $customer->open_opportunities_count > 0 ? 'Manage opportunities' : 'Add opportunity' }} →</a>@endif
                    </article>
                    <article class="is-evidence">
                        <div class="nx11-v9-exposure-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 3h7l4 4v14H7V3Z"/><path d="M14 3v5h5M10 13h5M10 17h5"/></svg></div>
                        <div class="nx11-v9-exposure-copy"><span>Customer Evidence</span><strong>{{ $documentsReady ? number_format($customer->documents_count).' recorded' : 'Not Available' }}</strong><p>{{ ! $documentsReady ? 'Document tracking is not available for this customer record.' : ($customer->documents_count > 0 ? 'Recorded customer documents are available for governance review.' : 'Documents appear here when customer evidence is received.') }}</p></div>
                        @if($canUpdate && $customer->isActive())<a href="{{ route('companies.customers.edit', [$company, $customer]) }}#documents">{{ $documentsReady && $customer->documents_count > 0 ? 'Manage evidence' : 'Add evidence' }} →</a>@endif
                    </article>
                </div>
            </section>

            @if($customer->projects->isNotEmpty())
                <section class="nx11-v9-card nx11-v9-project-records" data-nx-motion="reveal">
                    <header class="nx11-v9-card-head"><div><span>Linked Projects</span><h3>Recent Engineering &amp; Commercial Records</h3></div></header>
                    <div class="nx11-project-list">@foreach($customer->projects as $project)<a href="{{ route('companies.projects.show', [$company, $project]) }}"><div><strong>{{ $project->name }}</strong><small>{{ $project->project_number }} · {{ $project->proposed_system_label }}</small></div><div><span>{{ $project->city ?: 'City Not Recorded' }}{{ $project->province ? ' · '.$project->province : '' }}</span><small>{{ $project->latitude !== null && $project->longitude !== null ? 'Exact · '.number_format((float) $project->latitude, 7, '.', '').', '.number_format((float) $project->longitude, 7, '.', '') : 'Project Coordinate Not Recorded' }}</small></div><div><b class="is-{{ $project->status }}">{{ $project->status_label }}</b><small>{{ $project->assignedEngineer?->name ?: 'Engineer Not Assigned' }}</small></div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/></svg></a>@endforeach</div>
                </section>
            @endif

            <section class="nx11-v9-card nx11-v9-governance" data-nx-motion="reveal">
                <header class="nx11-v9-card-head"><div><span>Governance &amp; Audit</span><h3>Relationship Trace &amp; Archive Control</h3></div></header>
                <div class="nx11-v9-governance-body">
                    <div class="nx11-v9-audit-track">
                        <article><i></i><span>Created</span><strong>{{ $customer->created_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong><small>{{ $customer->creator?->name ?: 'System Provisioned' }}</small></article>
                        <article><i></i><span>Last Update</span><strong>{{ $customer->updated_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong><small>{{ $customer->updater?->name ?: 'System Updated' }}</small></article>
                        <article><i></i><span>Review Evidence</span><strong>{{ ! $assessmentReady ? 'Not Available' : ($profile?->review_acknowledged_at ? $profile->review_acknowledged_at->timezone('Asia/Jakarta')->format('d M Y · H:i') : 'Not Recorded') }}</strong><small>{{ $profile?->reviewAcknowledgedBy?->name ?: 'Reviewer Not Recorded' }}</small></article>
                    </div>
                    <aside class="nx11-v9-archive {{ $customer->blocking_projects_count > 0 ? 'is-blocked' : 'is-available' }}">
                        <div><span>Archive Control</span><strong>{{ $customer->blocking_projects_count > 0 ? 'Blocked' : 'Available' }}</strong><p>{{ $customer->blocking_projects_count > 0 ? 'Resolve open project blockers before archive.' : 'No active project prevents controlled archive.' }}</p></div>
                        @if($canArchive && $customer->isActive())
                            <form method="POST" action="{{ route('companies.customers.archive', [$company, $customer]) }}" data-nx11-archive-form data-customer-name="{{ $customer->name }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="nx11-v9-archive-action" @disabled($customer->blocking_projects_count > 0)>{{ $customer->blocking_projects_count > 0 ? 'Archive blocked' : 'Archive customer' }}</button>
                            </form>
                        @endif
                    </aside>
                </div>
            </section>
        </main>

        <aside class="nx11-v9-rail" data-nx-motion="reveal">
            <section class="nx11-v9-rail-card nx11-v9-next is-{{ $nextAction['tone'] }}">
                <div class="nx11-v9-rail-kicker"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.1 4.4L19 8l-3.5 3.4.8 4.8L12 14l-4.3 2.2.8-4.8L5 8l4.9-.6L12 3Z"/></svg><span>Next Required Action</span></div>
                <h3>{{ $nextAction['title'] }}</h3>
                <p>{{ $nextAction['copy'] }}</p>
                @if($nextAction['anchor'] && $canUpdate && $customer->isActive())<a href="{{ route('companies.customers.edit', [$company, $customer]) }}#{{ $nextAction['anchor'] }}">Complete action →</a>@endif
            </section>

            <section class="nx11-v9-rail-card">
                <header><span>Account Snapshot</span><h3>Relationship Control</h3></header>
                <div class="nx11-v9-rail-list">
                    <div><span>Stage</span><strong>{{ $stageLabel }}</strong></div>
                    <div><span>Owner</span><strong>{{ $profile?->accountOwner?->name ?: 'Not Assigned' }}</strong></div>
                    <div><span>Review</span><strong>{{ $reviewStateLabel }}</strong></div>
                    <div><span>Portal</span><strong class="{{ $portalLinked ? 'is-positive' : '' }}">{{ $portalLinked ? 'Linked' : 'Not Linked' }}</strong></div>
                    <div><span>Archive</span><strong class="{{ $customer->blocking_projects_count > 0 ? 'is-warning' : 'is-positive' }}">{{ $archiveStateLabel }}</strong></div>
                </div>
            </section>

            @if($contactsReady && $customer->contacts->isNotEmpty())
                <section class="nx11-v9-rail-card">
                    <header><span>Key Contacts</span><h3>Primary Customer Contact</h3></header>
                    @foreach($customer->contacts->take(3) as $contact)
                        <article class="nx11-v9-contact">
                            <div class="nx11-v9-contact-avatar" aria-hidden="true"><img src="{{ asset('images/customer/nexorin-professional-customer-avatar.webp') }}" alt="" loading="lazy" decoding="async"></div>
                            <div><strong>{{ $contact->full_name }}</strong><small>{{ $contact->job_title ?: 'Role Not Recorded' }}</small><p>{{ $contact->email ?: 'Email Not Recorded' }}</p></div>
                            @if($contact->is_primary)<span>Primary</span>@endif
                        </article>
                    @endforeach
                    @if($canUpdate && $customer->isActive())<a class="nx11-v9-rail-link" href="{{ route('companies.customers.edit', [$company, $customer]) }}#contacts">Manage contacts →</a>@endif
                </section>
            @endif

            <section class="nx11-v9-rail-card">
                <header><span>Information Coverage</span><h3>{{ $recordGaps->isEmpty() ? 'Customer information current' : $recordGaps->count().' items need attention' }}</h3></header>
                @if($recordGaps->isEmpty())
                    <div class="nx11-v9-positive-note">Available customer information is current.</div>
                @else
                    <div class="nx11-v9-gap-list">@foreach($recordGaps->take(5) as $gap)<div><i></i><span>{{ $gap }}</span></div>@endforeach</div>
                @endif
            </section>

            @if(filled($customer->notes) || filled($profile?->key_considerations))
                <section class="nx11-v9-rail-card">
                    <header><span>Internal Notes</span><h3>Relationship Context</h3></header>
                    @if(filled($customer->notes))<div class="nx11-v9-note"><span>Customer Notes</span><p>{{ $customer->notes }}</p></div>@endif
                    @if(filled($profile?->key_considerations))<div class="nx11-v9-note"><span>Key Considerations</span><p>{{ $profile->key_considerations }}</p></div>@endif
                </section>
            @endif

            @if($documentsReady && $customer->documents->isNotEmpty())
                <section class="nx11-v9-rail-card">
                    <header><span>Recent Evidence</span><h3>Customer Documents</h3></header>
                    <div class="nx11-v9-rail-docs">@foreach($customer->documents->take(4) as $document)<article><span>{{ $documentCategoryLabels[$document->category] ?? 'Customer Document' }}</span><strong>{{ $document->title }}</strong><small>{{ $document->created_at?->format('d M Y') }} · {{ $documentStatusLabels[$document->status] ?? 'Recorded' }}</small></article>@endforeach</div>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
