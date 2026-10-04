@extends('layouts.app')

@section('title', 'Companies')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Companies')
@section('force-theme', 'dark')
@section('body-class', 'page-companies-index-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-companies-index-v6.css') }}?v=006g" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-companies-index-v6.js') }}?v=006g" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
@php
    $moneyCompact = static function (?float $value): string {
        if ($value === null) return 'Not available';
        if ($value >= 1_000_000_000_000) return 'Rp '.number_format($value / 1_000_000_000_000, 2).'T';
        if ($value >= 1_000_000_000) return 'Rp '.number_format($value / 1_000_000_000, 2).'B';
        if ($value >= 1_000_000) return 'Rp '.number_format($value / 1_000_000, 1).'M';
        return 'Rp '.number_format($value, 0, ',', '.');
    };
    $titleCase = static fn (?string $value, string $fallback = 'Unassigned') => filled($value) ? \Illuminate\Support\Str::headline($value) : $fallback;
    $filterQuery = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->all();
    $activeFilterCount = collect($filters)->except(['sort', 'direction', 'per_page'])->filter(fn ($value) => $value !== null && $value !== '')->count();
    $portfolioTotal = $stats['portfolio']['total'] ?? null;
@endphp

<div class="nx6-page" data-nx6-page data-map-url="{{ asset('data/indonesia-country.geojson') }}">
    <form class="nx6-top-search" method="GET" action="{{ route('companies.index') }}" role="search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search companies, contacts, certifications, or documents…" aria-label="Search company directory">
        @foreach(collect($filterQuery)->except('search') as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        <kbd>/</kbd>
    </form>
    <header class="nx6-header">
        <div>
            <span class="nx6-eyebrow">Company ecosystem intelligence</span>
            <h1>Companies</h1>
            <p>Manage and evaluate your ecosystem of partners, suppliers, and stakeholders.</p>
        </div>
        <div class="nx6-header-actions">
            <a class="nx6-btn nx6-btn-secondary" href="{{ route('companies.export', $filterQuery) }}" data-nx6-export title="Exports the current filtered result only">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 15v5h14v-5"/></svg>
                Export
            </a>
            <a class="nx6-btn nx6-btn-primary" href="{{ route('companies.create') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Add Company
            </a>
        </div>
    </header>

    <section class="nx6-kpis" aria-label="Company directory metrics">
        <article class="nx6-kpi">
            <span>Total Companies</span><strong>{{ number_format($stats['total']) }}</strong>
            <small>Authoritative non-archived company records</small><span class="nx6-kpi-icon is-cyan"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V7l8-3 8 3v14M8 10h2M14 10h2M8 14h2M14 14h2M10 21v-4h4v4"/></svg></span>
        </article>
        <article class="nx6-kpi">
            <span>Active Companies</span><strong>{{ number_format($stats['active']) }}</strong>
            <small>{{ $stats['total'] > 0 ? number_format(($stats['active'] / $stats['total']) * 100, 1) : '0.0' }}% of registered companies</small><span class="nx6-kpi-icon is-green"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l2-6 4 12 2-6h6"/></svg></span>
        </article>
        <article class="nx6-kpi">
            <span>Strategic Partners</span><strong>{{ number_format($stats['strategic']) }}</strong>
            <small>Explicit relationship tier only</small><span class="nx6-kpi-icon is-violet"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.5 6 .9-4.4 4.3 1 6.1-5.3-2.8-5.3 2.8 1-6.1-4.4-4.3 6-.9Z"/></svg></span>
        </article>
        <article class="nx6-kpi is-accent" title="{{ $metricDefinitions['portfolio_value']['basis'] }}">
            <span>Total Portfolio Value</span><strong class="{{ $portfolioTotal === null ? 'is-muted' : '' }}">{{ $moneyCompact($portfolioTotal) }}</strong>
            <small>{{ $portfolioTotal === null ? 'No accepted quotation value available' : number_format($stats['portfolio']['accepted_quotation_count']).' accepted quotation(s) · IDR only' }}</small><span class="nx6-kpi-icon is-amber"><svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="10" cy="6" rx="6" ry="3"/><path d="M4 6v5c0 1.7 2.7 3 6 3h1M4 11v5c0 1.7 2.7 3 6 3h1"/><circle cx="17" cy="16" r="4"/></svg></span>
        </article>
        <article class="nx6-kpi" title="{{ $metricDefinitions['active_projects']['basis'] }}">
            <span>Active Projects</span><strong>{{ number_format($stats['active_projects']) }}</strong>
            <small>Excludes completed, cancelled, and archived projects</small><span class="nx6-kpi-icon is-blue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h7l2 2h9v11H3z"/></svg></span>
        </article>
    </section>

    <div class="nx6-workspace">
        <aside class="nx6-filter-rail" aria-label="Company directory filters">
            <div class="nx6-rail-head"><strong>Filters</strong><a href="{{ route('companies.index') }}">Clear all</a></div>
            <form method="GET" action="{{ route('companies.index') }}" data-nx6-filter-form>
                <label class="nx6-search"><span class="sr-only">Search filters</span><input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search companies…"></label>

                <div class="nx6-filter-group">
                    <span>Company Type</span>
                    <label><input type="radio" name="type" value="" @checked(!$filters['type'])><b>All types</b></label>
                    @forelse($filterOptions['types'] as $type)
                        <label><input type="radio" name="type" value="{{ $type }}" @checked($filters['type'] === $type)><b>{{ $type }}</b></label>
                    @empty
                        <p class="nx6-filter-empty">No company types recorded</p>
                    @endforelse
                </div>

                <div class="nx6-filter-group">
                    <span>Status</span>
                    <label><input type="radio" name="status" value="" @checked(!$filters['status'])><b>All states</b></label>
                    <label><input type="radio" name="status" value="active" @checked($filters['status'] === 'active')><b>Active</b></label>
                    <label><input type="radio" name="status" value="inactive" @checked($filters['status'] === 'inactive')><b>Inactive</b></label>
                </div>

                <div class="nx6-filter-group">
                    <span>Relationship Tier</span>
                    <label><input type="radio" name="tier" value="" @checked(!$filters['tier'])><b>All tiers</b></label>
                    @foreach($filterOptions['tiers'] as $tier)
                        <label><input type="radio" name="tier" value="{{ $tier }}" @checked($filters['tier'] === $tier)><b>{{ $titleCase($tier) }}</b></label>
                    @endforeach
                    <label><input type="radio" name="tier" value="unassigned" @checked($filters['tier'] === 'unassigned')><b>Unassigned</b></label>
                </div>

                <div class="nx6-filter-group">
                    <span>Region</span>
                    <select name="region"><option value="">All regions</option>@foreach($filterOptions['regions'] as $region)<option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>@endforeach</select>
                </div>

                <details class="nx6-advanced" open>
                    <summary>More filters <em>{{ max(0, $activeFilterCount - 4) }}</em></summary>
                    <div class="nx6-filter-group"><span>Specialization</span><select name="specialization"><option value="">All specializations</option>@foreach($filterOptions['specializations'] as $item)<option value="{{ $item }}" @selected($filters['specialization'] === $item)>{{ $item }}</option>@endforeach</select></div>
                    <div class="nx6-filter-group"><span>Certifications</span><select name="certification"><option value="">All certifications</option>@foreach($filterOptions['certifications'] as $item)<option value="{{ $item }}" @selected($filters['certification'] === $item)>{{ $item }}</option>@endforeach</select></div>
                    <div class="nx6-filter-group"><span>Governance Readiness</span><select name="governance_state"><option value="">All states</option>@foreach(['ready'=>'Ready','attention'=>'Attention','expiring'=>'Expiring','incomplete'=>'Incomplete','not_assessed'=>'Not assessed'] as $key=>$label)<option value="{{ $key }}" @selected($filters['governance_state'] === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="nx6-filter-group"><span>Onboarding Progress</span><select name="onboarding_state"><option value="">All states</option><option value="complete" @selected($filters['onboarding_state'] === 'complete')>Complete</option><option value="in_progress" @selected($filters['onboarding_state'] === 'in_progress')>In progress</option></select></div>
                </details>

                <input type="hidden" name="sort" value="{{ $filters['sort'] }}"><input type="hidden" name="direction" value="{{ $filters['direction'] }}"><input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
                <button class="nx6-apply" type="submit">Apply filters @if($activeFilterCount)<span>{{ $activeFilterCount }}</span>@endif</button>
            </form>
            <div class="nx6-framework-note"><b>Governance scope</b><p>{{ $metricDefinitions['governance']['basis'] }}.</p><small>{{ $metricDefinitions['governance']['version'] }}</small></div>
            <div class="nx6-directory-state" aria-label="Directory state">
                <span>Directory State</span>
                <strong>{{ $stats['total'] === 0 ? 'Empty Registry' : number_format($stats['total']).' Registered' }}</strong>
                <p>{{ $stats['total'] === 0 ? 'No authoritative company entities are registered in the current data source.' : 'Authoritative company entities are available in the current directory scope.' }}</p>
            </div>
            <div class="nx6-control-status" aria-label="Directory control status">
                <span>Directory Controls</span>
                <b><i></i>Server-side authorization</b>
                <b><i></i>Filtered export scope</b>
                <b><i></i>User-owned saved views</b>
            </div>
        </aside>

        <div class="nx6-main-stack">
        <main class="nx6-registry">
            <div class="nx6-registry-toolbar">
                <label class="nx6-table-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input form="nx6RegistrySearch" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search companies…"></label>
                <form id="nx6RegistrySearch" method="GET" action="{{ route('companies.index') }}" class="nx6-toolbar-form">
                    @foreach(collect($filterQuery)->except(['search','type','tier','region']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                    <select name="type" aria-label="Filter by company type" onchange="this.form.submit()"><option value="">All Types</option>@foreach($filterOptions['types'] as $type)<option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>@endforeach</select>
                    <select name="tier" aria-label="Filter by relationship tier" onchange="this.form.submit()"><option value="">All Tiers</option>@foreach($filterOptions['tiers'] as $tier)<option value="{{ $tier }}" @selected($filters['tier'] === $tier)>{{ $titleCase($tier) }}</option>@endforeach<option value="unassigned" @selected($filters['tier'] === 'unassigned')>Unassigned</option></select>
                    <select name="region" aria-label="Filter by region" onchange="this.form.submit()"><option value="">All Regions</option>@foreach($filterOptions['regions'] as $region)<option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>@endforeach</select>
                </form>
                <button class="nx6-more-filters" type="button" data-nx6-more-filters><span>More Filters</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg></button>
                <button class="nx6-save-view" type="button" data-nx6-save-open>Save View <span aria-hidden="true">☆</span></button>
                <span class="nx6-result-count">{{ $companies->firstItem() ?? 0 }}–{{ $companies->lastItem() ?? 0 }} of {{ number_format($companies->total()) }}</span>
            </div>

            @if($savedViews->isNotEmpty())
                <div class="nx6-saved-strip"><span>Saved views</span>@foreach($savedViews as $view)<a href="{{ route('companies.index', $view->filters ?? []) }}">{{ $view->name }}@if($view->is_default)<i>Default</i>@endif</a>@endforeach</div>
            @endif

            <div class="nx6-table-wrap">
                <table class="nx6-table">
                    <thead><tr><th>Company</th><th>Type / Specialization</th><th>Tier</th><th>Region</th><th>Profile Compliance</th><th>Portfolio Value</th><th>Active Projects</th><th>Status</th><th>Onboarding</th><th aria-label="Actions"></th></tr></thead>
                    <tbody>
                    @forelse($companies as $company)
                        @php
                            $profile = $company->profile;
                            $image = $profile?->profile_image_path ? asset('storage/'.$profile->profile_image_path) : ($company->logo_path ? asset('storage/'.$company->logo_path) : null);
                            $specializations = collect($profile?->categories_served ?? [])->filter()->take(2);
                            $primaryRegion = $company->regions->first();
                            $gov = $company->directory_governance;
                            $onboarding = $company->directory_onboarding;
                            $tier = $company->relationship?->tier;
                            $portfolioValue = $company->directory_portfolio_value_idr;
                        @endphp
                        <tr>
                            <td><a href="{{ route('companies.show', $company) }}" class="nx6-company-cell">@if($image)<img src="{{ $image }}" alt="" loading="lazy">@else<span class="nx6-company-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 20V7l7-3 7 3v13M9 10h1M14 10h1M9 14h1M14 14h1M10 20v-3h4v3"/></svg></span>@endif<span><strong>{{ $company->name }}</strong><small>{{ $company->code }}@if($company->registration_number) · {{ $company->registration_number }}@endif</small></span></a></td>
                            <td><strong class="nx6-cell-main">{{ $profile?->company_type ?: 'Not classified' }}</strong><small>{{ $specializations->isNotEmpty() ? $specializations->implode(' · ') : 'No specialization recorded' }}</small></td>
                            <td><span class="nx6-tier is-{{ $tier ?: 'unassigned' }}">{{ $titleCase($tier) }}</span></td>
                            <td>@if($primaryRegion)<strong class="nx6-cell-main">{{ $primaryRegion->region_name ?: $primaryRegion->country_name }}</strong><small>{{ $primaryRegion->region_name ? $primaryRegion->country_name : 'Operating region' }}{{ $company->regions->count() > 1 ? ' · +'.($company->regions->count()-1) : '' }}</small>@else<span class="nx6-na">No region</span>@endif</td>
                            <td><span class="nx6-state is-{{ $gov['state'] }}">{{ $gov['label'] }}</span><small title="{{ $gov['basis'] }}">Profile evidence</small></td>
                            <td><strong class="nx6-value {{ $portfolioValue === null ? 'is-na' : '' }}">{{ $moneyCompact($portfolioValue) }}</strong><small>{{ $portfolioValue === null ? 'No accepted quotation' : 'Accepted · IDR' }}</small></td>
                            <td><strong class="nx6-project-count">{{ number_format($company->directory_active_projects) }}</strong><small>Current lifecycle</small></td>
                            <td><span class="nx6-status is-{{ $company->status }}"><i></i>{{ $titleCase($company->status) }}</span></td>
                            <td>@if($onboarding['progress'] !== null)<div class="nx6-progress"><span><b>{{ $onboarding['progress'] }}%</b><small>{{ $onboarding['complete'] }}/{{ $onboarding['total'] }} requirements</small></span><i><b style="width:{{ $onboarding['progress'] }}%"></b></i></div>@else<span class="nx6-na">Not configured</span>@endif</td>
                            <td>
                                <div class="nx6-row-actions">
                                    <a class="nx6-action-icon" href="{{ route('companies.show', $company) }}" aria-label="View {{ $company->name }}" title="View company">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.8 12s3.4-5.2 9.2-5.2S21.2 12 21.2 12 17.8 17.2 12 17.2 2.8 12 2.8 12Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                                    </a>
                                    <a class="nx6-action-icon" href="{{ route('companies.edit', $company) }}" aria-label="Edit {{ $company->name }}" title="Edit company">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.2-1 10-10-3.2-3.2-10 10L4 20Z"/><path d="m13.8 7 3.2 3.2"/></svg>
                                    </a>
                                    <button class="nx6-action-more" type="button" data-nx6-menu aria-haspopup="menu" aria-expanded="false" aria-label="More actions for {{ $company->name }}" title="More actions">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
                                    </button>
                                    <div class="nx6-action-menu" hidden role="menu" aria-label="Actions for {{ $company->name }}">
                                        <a href="{{ route('companies.show', $company) }}" role="menuitem">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.8 12s3.4-5.2 9.2-5.2S21.2 12 21.2 12 17.8 17.2 12 17.2 2.8 12 2.8 12Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                                            <span><strong>View company</strong><small>Open company record</small></span>
                                        </a>
                                        <a href="{{ route('companies.edit', $company) }}" role="menuitem">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.2-1 10-10-3.2-3.2-10 10L4 20Z"/><path d="m13.8 7 3.2 3.2"/></svg>
                                            <span><strong>Edit company</strong><small>Update profile and governance data</small></span>
                                        </a>
                                        <div class="nx6-menu-divider" aria-hidden="true"></div>
                                        <form method="POST" action="{{ route('companies.status', $company) }}">@csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $company->isActive() ? 'inactive' : 'active' }}">
                                            <button type="submit" role="menuitem" data-nx-confirm data-nx-confirm-title="{{ $company->isActive() ? 'Deactivate company?' : 'Activate company?' }}" data-nx-confirm-message="This changes workspace access state for {{ $company->name }}." data-nx-confirm-proceed="{{ $company->isActive() ? 'Deactivate' : 'Activate' }}">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v8"/><path d="M7.2 6.5A7 7 0 1 0 16.8 6.5"/></svg>
                                                <span><strong>{{ $company->isActive() ? 'Deactivate company' : 'Activate company' }}</strong><small>{{ $company->isActive() ? 'Suspend workspace availability' : 'Restore workspace availability' }}</small></span>
                                            </button>
                                        </form>
                                        @unless($company->isActive())
                                            <form method="POST" action="{{ route('companies.destroy', $company) }}">@csrf @method('DELETE')
                                                <button class="is-danger" type="submit" role="menuitem" data-nx-confirm data-nx-confirm-title="Archive company?" data-nx-confirm-message="Archive {{ $company->name }}. This removes it from the active directory while preserving its record history." data-nx-confirm-proceed="Archive company">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M6 7l1 13h10l1-13M9 4h6l1 3H8l1-3Z"/><path d="M9.5 11h5"/></svg>
                                                    <span><strong>Archive company</strong><small>Available after deactivation</small></span>
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="nx6-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V7l7-3 7 3v13M3 20h18"/></svg><strong>{{ $activeFilterCount ? 'No companies match this view' : 'No companies are registered yet' }}</strong><p>{{ $activeFilterCount ? 'Adjust or clear filters. NEXORIN will not fabricate directory records.' : 'Create the first authoritative company entity to begin ecosystem administration.' }}</p>@if(!$activeFilterCount)<a href="{{ route('companies.create') }}">Add Company</a>@endif</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="nx6-pagination"><span>Showing {{ $companies->firstItem() ?? 0 }} to {{ $companies->lastItem() ?? 0 }} of {{ number_format($companies->total()) }} companies</span><div>{{ $companies->onEachSide(1)->links() }}</div><form method="GET" action="{{ route('companies.index') }}">@foreach(collect($filterQuery)->except('per_page') as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<select name="per_page" onchange="this.form.submit()"><option value="10" @selected($filters['per_page']===10)>10 / page</option><option value="25" @selected($filters['per_page']===25)>25 / page</option><option value="50" @selected($filters['per_page']===50)>50 / page</option></select></form></div>
        </main>

        <section class="nx6-intelligence-row" aria-label="Company directory intelligence summary">
            <article class="nx6-chart-card nx6-map-card"><header><div><span>Regional Distribution</span><small>Company service-region reference coverage</small></div><em>{{ $intelligence['regional_distribution']->count() }} regions</em></header><div class="nx6-chart nx6-map" data-nx6-chart="region" data-series='@json($intelligence['regional_distribution'])'></div><div class="nx6-card-foot">Markers are plotted only where Page 005 stored region reference coordinates. They are not exact company locations.</div></article>
            <article class="nx6-chart-card"><header><div><span>Company Segment Mix</span><small>Profile company-type distribution</small></div></header><div class="nx6-chart" data-nx6-chart="segments" data-series='@json($intelligence['segment_mix'])'></div><div class="nx6-card-foot">Unclassified means no company type is recorded.</div></article>
            <article class="nx6-chart-card"><header><div><span>Portfolio Value by Tier</span><small>Accepted quotations · IDR · no FX conversion</small></div></header>@if($portfolioTotal !== null)<div class="nx6-chart" data-nx6-chart="portfolio" data-series='@json($intelligence['portfolio_by_tier'])'></div>@else<div class="nx6-chart-empty"><strong>Portfolio value unavailable</strong><p>No accepted quotation has a comparable IDR commercial grand total.</p></div>@endif<div class="nx6-card-foot">Unassigned tier is preserved rather than silently treating it as Standard.</div></article>
        </section>

        <section class="nx6-intelligence-row nx6-intelligence-row-secondary" aria-label="Company intelligence diagnostics">
            <article class="nx6-chart-card"><header><div><span>Governance Overview</span><small>Company Profile Governance Readiness</small></div></header><div class="nx6-chart" data-nx6-chart="governance" data-series='@json($intelligence['governance_overview'])'></div><div class="nx6-card-foot">This is evidence readiness, not statutory compliance certification.</div></article>
            <article class="nx6-chart-card"><header><div><span>Active Projects by Company</span><small>Top companies by current project lifecycle count</small></div><em>{{ number_format($stats['active_projects']) }} total</em></header>@if($intelligence['active_projects_by_company']->isNotEmpty())<div class="nx6-chart" data-nx6-chart="projects" data-series='@json($intelligence['active_projects_by_company'])'></div>@else<div class="nx6-chart-empty"><strong>No active project exposure</strong><p>Current project states contain no active records.</p></div>@endif<div class="nx6-card-foot">Completed, cancelled, and archived projects are excluded.</div></article>
            <article class="nx6-chart-card nx6-health-card"><header><div><span>Company Health Framework</span><small>Composite score intentionally withheld</small></div><span class="nx6-not-scored">{{ $intelligence['health']['label'] }}</span></header><p class="nx6-health-reason">{{ $intelligence['health']['reason'] }}</p><div class="nx6-health-table"><span>Health Factor</span><span>State</span><span>Authoritative Source</span>@foreach($intelligence['health']['factors'] as $factor)<b>{{ $factor['label'] }}</b><em class="is-{{ \Illuminate\Support\Str::slug($factor['status']) }}">{{ $factor['status'] }}</em><small>{{ $factor['source'] }}</small>@endforeach</div><div class="nx6-card-foot">A score will only appear after a versioned factor/weight model is configured.</div></article>
        </section>
        </div>

        <aside class="nx6-intel-rail">
            <header><span>Company Intelligence</span><small>Derived from current backend evidence</small></header>
            <section><div class="nx6-rail-title"><b>Onboarding Blockers</b><a href="{{ route('companies.index', ['onboarding_state'=>'in_progress']) }}">View all</a></div>@forelse($intelligence['onboarding_blockers']->take(4) as $item)<a class="nx6-intel-item is-warning" href="{{ route('companies.index', ['onboarding_state'=>'in_progress']) }}"><i></i><span><strong>{{ $item['label'] }}</strong><small>{{ number_format($item['count']) }} companies</small></span></a>@empty<div class="nx6-mini-empty">No onboarding blockers from configured requirements.</div>@endforelse</section>
            <section><div class="nx6-rail-title"><b>Key Alerts</b></div>@forelse($intelligence['alerts']->take(4) as $item)<a class="nx6-intel-item is-{{ $item['state'] }}" href="{{ route('companies.index', $item['filter']) }}"><i></i><span><strong>{{ $item['label'] }}</strong><small>{{ number_format($item['count']) }} affected companies</small></span></a>@empty<div class="nx6-mini-empty">No current evidence alerts.</div>@endforelse</section>
            <section><div class="nx6-rail-title"><b>Next Actions</b></div>@forelse($intelligence['next_actions'] as $item)<a class="nx6-intel-item is-action" href="{{ route('companies.index', $item['query']) }}"><i></i><span><strong>{{ $item['label'] }}</strong><small>{{ number_format($item['count']) }} records</small></span></a>@empty<div class="nx6-mini-empty">No derived follow-up actions.</div>@endforelse</section>
            <section class="nx6-context-section">
                <div class="nx6-rail-title"><b>Directory Context</b><span>{{ number_format($companies->count()) }} visible</span></div>
                <div class="nx6-context-grid">
                    <div><span>Registered</span><strong>{{ number_format($stats['total']) }}</strong></div>
                    <div><span>Active</span><strong>{{ number_format($stats['active']) }}</strong></div>
                    <div><span>Active Projects</span><strong>{{ number_format($stats['active_projects']) }}</strong></div>
                    <div><span>Accepted Quotes</span><strong>{{ number_format($stats['portfolio']['accepted_quotation_count'] ?? 0) }}</strong></div>
                </div>
            </section>
            <section class="nx6-current-view">
                <div class="nx6-rail-title"><b>Current View</b><span>{{ number_format($activeFilterCount) }} filters</span></div>
                <dl>
                    <div><dt>Page size</dt><dd>{{ number_format($filters['per_page']) }}</dd></div>
                    <div><dt>Saved views</dt><dd>{{ number_format($savedViews->count()) }}</dd></div>
                    <div><dt>Sort</dt><dd>{{ \Illuminate\Support\Str::headline($filters['sort'] ?? 'name') }}</dd></div>
                    <div><dt>Direction</dt><dd>{{ strtoupper($filters['direction'] ?? 'asc') }}</dd></div>
                </dl>
            </section>
            <div class="nx6-intel-callout"><span>Evidence-controlled directory</span><strong>Governance metrics remain truthful when source data is incomplete.</strong><small>{{ $metricDefinitions['governance']['version'] }}</small></div>
        </aside>
    </div>

    <footer class="nx6-page-footer"><span>© 2026 NEXORIN. Company Directory / Ecosystem Intelligence.</span><span>Evidence-controlled enterprise administration</span></footer>

    <div class="nx6-save-modal" data-nx6-save-modal hidden aria-hidden="true">
        <button class="nx6-save-backdrop" type="button" data-nx6-save-close aria-label="Close save view dialog"></button>
        <form method="POST" action="{{ route('companies.directory-views.store') }}" class="nx6-save-dialog" role="dialog" aria-modal="true" aria-labelledby="nx6SaveTitle">@csrf
            <header><span>Directory preset</span><h2 id="nx6SaveTitle">Save Current View</h2><p>Persist these filters for your administrator account. Saved views are never shared across users.</p></header>
            <label><span>View name</span><input name="name" required maxlength="120" placeholder="e.g. EPC partners requiring review"></label>
            @foreach($filters as $key=>$value)<input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">@endforeach
            <label class="nx6-check"><input type="checkbox" name="is_default" value="1"><span>Make this my default saved view</span></label>
            <footer><button type="button" class="nx6-btn nx6-btn-secondary" data-nx6-save-close>Cancel</button><button type="submit" class="nx6-btn nx6-btn-primary">Save View</button></footer>
        </form>
    </div>
</div>
@endsection
