@extends('layouts.app')

@section('title', 'Customers')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Customers')

@push('styles')
    <link href="{{ asset('css/nexorin-customer-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@php
    $currentUser = auth()->user();
    $canCreate = $currentUser->hasCompanyPermission($company, 'customer.create');
    $canUpdate = $currentUser->hasCompanyPermission($company, 'customer.update');
    $canArchive = $currentUser->hasCompanyPermission($company, 'customer.delete');
    $totalCustomers = max(1, (int) $stats['total']);
    $activeRatio = (int) round(((int) $stats['active'] / $totalCustomers) * 100);
    $residentialRatio = (int) round(((int) $stats['residential'] / $totalCustomers) * 100);
    $commercialRatio = (int) round(((int) $stats['commercial'] / $totalCustomers) * 100);
    $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] ? 1 : 0) + ($filters['type'] ? 1 : 0);
@endphp

@section('content')
    <div class="nxcus-directory" data-nxcus-directory>
        <header class="nxcus-directory-head" data-nxcus-reveal>
            <div>
                <span class="nxcus-kicker"><i></i>Customer relationship and portal governance</span>
                <h2>Customer Relationship Register</h2>
                <p>Control customer identity, project exposure, portal ownership, and record lifecycle inside {{ $company->name }}.</p>
            </div>

            <div class="nxcus-directory-actions">
                <span class="nxcus-engine-state"><i></i> Relationship control online</span>
                <a href="{{ route('companies.projects.index', $company) }}" class="nxcus-button nxcus-button-secondary" data-nxcus-nav="projects">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M3 19h18"/></svg>
                    Projects
                </a>
                @if ($canCreate)
                    <a href="{{ route('companies.customers.create', $company) }}" class="nxcus-button nxcus-button-primary" data-nxcus-nav="create">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Add Customer
                    </a>
                @endif
            </div>
        </header>

        <section class="nxcus-stats" aria-label="Customer relationship summary" data-nxcus-reveal>
            <article class="nxcus-stat is-teal">
                <span class="nxcus-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6M15 15c3 0 5 1.5 5 4"/></svg></span>
                <div><small>RELATIONSHIP PORTFOLIO</small><strong>{{ number_format($stats['total']) }}</strong><span>Registered customers</span><p>Customer records governed inside this company workspace.</p></div>
                <i><b style="width:100%"></b></i>
            </article>
            <a href="{{ route('companies.customers.index', ['company' => $company, 'status' => 'active']) }}" class="nxcus-stat is-green {{ $filters['status'] === 'active' ? 'is-selected' : '' }}" data-nxcus-nav="filter">
                <span class="nxcus-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12l4 4L19 6"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span>
                <div><small>ACTIVE RECORDS</small><strong>{{ number_format($stats['active']) }}</strong><span>Available customers</span><p>{{ $activeRatio }}% can be selected for new project relationships.</p></div>
                <i><b style="width:{{ max(4, $activeRatio) }}%"></b></i>
            </a>
            <a href="{{ route('companies.customers.index', ['company' => $company, 'type' => 'residential']) }}" class="nxcus-stat is-blue {{ $filters['type'] === 'residential' ? 'is-selected' : '' }}" data-nxcus-nav="filter">
                <span class="nxcus-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M3 19h18"/></svg></span>
                <div><small>RESIDENTIAL RELATIONSHIPS</small><strong>{{ number_format($stats['residential']) }}</strong><span>Residential customers</span><p>{{ $residentialRatio }}% of the governed relationship portfolio.</p></div>
                <i><b style="width:{{ max(4, $residentialRatio) }}%"></b></i>
            </a>
            <a href="{{ route('companies.customers.index', ['company' => $company, 'type' => 'commercial']) }}" class="nxcus-stat is-violet {{ $filters['type'] === 'commercial' ? 'is-selected' : '' }}" data-nxcus-nav="filter">
                <span class="nxcus-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 21V5l8-3 8 3v16"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/></svg></span>
                <div><small>COMMERCIAL RELATIONSHIPS</small><strong>{{ number_format($stats['commercial']) }}</strong><span>Commercial customers</span><p>{{ $commercialRatio }}% of the governed relationship portfolio.</p></div>
                <i><b style="width:{{ max(4, $commercialRatio) }}%"></b></i>
            </a>
        </section>

        <section class="nxcus-registry" data-nxcus-registry data-nxcus-reveal>
            <header class="nxcus-registry-head">
                <div>
                    <span class="nxcus-section-kicker">Identity, projects and customer access</span>
                    <h3>Customer Governance Register</h3>
                    <p>Review relationship type, contact coverage, project exposure, portal ownership, and lifecycle state without horizontal scrolling.</p>
                </div>
                <div class="nxcus-registry-summary">
                    @if ($activeFilters > 0)<span>{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>@endif
                    <b>{{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }} OF {{ $customers->total() }}</b>
                </div>
            </header>

            <form method="GET" action="{{ route('companies.customers.index', $company) }}" class="nxcus-filter" data-nxcus-filter-form aria-label="Customer registry filters">
                <label class="nxcus-filter-field is-search">
                    <span>Search registry</span>
                    <span class="nxcus-filter-control"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Name, number, email, phone, or city" autocomplete="off"></span>
                </label>
                <label class="nxcus-filter-field">
                    <span>Relationship type</span>
                    <span class="nxcus-filter-control"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10M8 19v-6h8v6"/></svg><select name="type" data-nxcus-auto-submit><option value="">All types</option><option value="residential" @selected($filters['type'] === 'residential')>Residential</option><option value="commercial" @selected($filters['type'] === 'commercial')>Commercial</option></select></span>
                </label>
                <label class="nxcus-filter-field">
                    <span>Record state</span>
                    <span class="nxcus-filter-control"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg><select name="status" data-nxcus-auto-submit><option value="">All states</option><option value="active" @selected($filters['status'] === 'active')>Active</option><option value="archived" @selected($filters['status'] === 'archived')>Archived</option></select></span>
                </label>
                <label class="nxcus-filter-field">
                    <span>Sort register</span>
                    <span class="nxcus-filter-control"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 6h12M8 12h8M8 18h4"/><path d="M4 4v16m0 0-2-2m2 2 2-2"/></svg><select name="sort" data-nxcus-auto-submit><option value="created_at" @selected($filters['sort'] === 'created_at')>Created date</option><option value="name" @selected($filters['sort'] === 'name')>Customer name</option><option value="customer_number" @selected($filters['sort'] === 'customer_number')>Customer number</option><option value="type" @selected($filters['sort'] === 'type')>Relationship type</option><option value="status" @selected($filters['sort'] === 'status')>Record state</option></select></span>
                </label>
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                <div class="nxcus-filter-actions"><button type="submit" class="nxcus-button nxcus-button-primary">Apply</button>@if ($activeFilters > 0)<a href="{{ route('companies.customers.index', $company) }}" class="nxcus-button nxcus-button-secondary" data-nxcus-nav="filter">Clear</a>@endif</div>
            </form>

            @if ($customers->isEmpty())
                <div class="nxcus-empty">
                    <span><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="20" cy="17" r="6"/><path d="M8 39c1.5-8 5.5-12 12-12s10.5 4 12 12"/><path d="M34 10h10v10M44 10 32 22"/></svg></span>
                    <h3>No customer records match this view</h3>
                    <p>Adjust the active filters or create a controlled customer relationship.</p>
                    @if ($canCreate)<a href="{{ route('companies.customers.create', $company) }}" class="nxcus-button nxcus-button-primary" data-nxcus-nav="create">Add Customer</a>@endif
                </div>
            @else
                <div class="nxcus-registry-columns" aria-hidden="true"><span>Customer identity</span><span>Relationship</span><span>Project exposure</span><span>Portal access</span><span>Record state</span><span>Action</span></div>
                <div class="nxcus-registry-body">
                    @foreach ($customers as $customer)
                        <article class="nxcus-customer-row" data-nxcus-row data-href="{{ route('companies.customers.show', [$company, $customer]) }}" tabindex="0" aria-label="Open {{ $customer->name }}">
                            <div class="nxcus-identity">
                                <span class="nxcus-identity-icon is-{{ $customer->type }}" aria-hidden="true">
                                    @if ($customer->type === 'commercial')
                                        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 42V11l15-5 15 5v31"/><path d="M16 16h4M28 16h4M16 23h4M28 23h4M16 30h4M28 30h4M20 42V35h8v7"/></svg>
                                    @else
                                        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 23 24 9l17 14v19H7z"/><circle cx="24" cy="24" r="5"/><path d="M14 41c1.2-7 4.5-10.5 10-10.5S32.8 34 34 41"/></svg>
                                    @endif
                                </span>
                                <div><strong title="{{ $customer->name }}">{{ $customer->name }}</strong><span>{{ $customer->customer_number ?: 'Number pending' }}</span><small>{{ $customer->email ?: ($customer->formatted_phone ?: 'Contact not provided') }}</small><div class="nxcus-identity-meta" aria-label="Compact relationship context"><span class="is-type">{{ $customer->type_label }}</span><span class="is-location">{{ $customer->city ?: 'Location not set' }}</span><span class="is-portal {{ $customer->portalUser ? 'is-linked' : '' }}">{{ $customer->portalUser ? 'Portal linked' : 'Portal not linked' }}</span></div></div>
                            </div>
                            <div class="nxcus-relationship"><span class="nxcus-type is-{{ $customer->type }}">{{ $customer->type_label }}</span><strong>{{ $customer->city ?: 'Location not set' }}</strong><small>{{ collect([$customer->province, $customer->postal_code])->filter()->join(' · ') ?: 'Location profile incomplete' }}</small></div>
                            <div class="nxcus-projects"><strong>{{ number_format($customer->projects_count) }} {{ \Illuminate\Support\Str::plural('project', $customer->projects_count) }}</strong><span class="{{ $customer->active_projects_count > 0 ? 'is-warning' : 'is-clear' }}">{{ number_format($customer->active_projects_count) }} open</span><small>{{ $customer->active_projects_count > 0 ? 'Archive gate blocked by active work' : 'No active project blocker' }}</small></div>
                            <div class="nxcus-portal">
                                @if ($customer->portalUser)
                                    <span class="nxcus-portal-state is-linked"><i></i>Linked</span><strong title="{{ $customer->portalUser->name }}">{{ $customer->portalUser->name }}</strong><small title="{{ $customer->portalUser->email }}">{{ $customer->portalUser->email }}</small>
                                @else
                                    <span class="nxcus-portal-state"><i></i>Not linked</span><strong>Portal access unavailable</strong><small>Link a Customer-role account when required</small>
                                @endif
                            </div>
                            <div class="nxcus-record-state"><span class="nxcus-status is-{{ $customer->status }}"><i></i>{{ ucfirst($customer->status) }}</span><strong>{{ $customer->updated_at?->timezone('Asia/Jakarta')->format('d M Y') }}</strong><small>Latest relationship update</small></div>
                            <div class="nxcus-row-actions">
                                <a href="{{ route('companies.customers.show', [$company, $customer]) }}" class="nxcus-open" data-nxcus-nav="open">Open <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg></a>
                                <div class="nxcus-action-menu" data-nxcus-menu>
                                    <button type="button" class="nxcus-more" data-nxcus-menu-toggle aria-expanded="false" aria-label="More actions for {{ $customer->name }}"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg></button>
                                    <div class="nxcus-menu-popover" data-nxcus-menu-popover hidden>
                                        @if ($canUpdate && $customer->isActive())
                                            <a href="{{ route('companies.customers.edit', [$company, $customer]) }}" data-nxcus-nav="edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg><span><strong>Edit customer</strong><small>Identity, contact and portal access</small></span></a>
                                        @endif
                                        <a href="{{ route('companies.projects.index', ['company' => $company, 'search' => $customer->customer_number]) }}" data-nxcus-nav="projects"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10M8 19v-6h8v6"/></svg><span><strong>View projects</strong><small>{{ number_format($customer->projects_count) }} linked records</small></span></a>
                                        @if ($canArchive && $customer->isActive())
                                            <form method="POST" action="{{ route('companies.customers.archive', [$company, $customer]) }}" data-nxcus-archive-form data-confirm="Archive this customer relationship?" data-confirm-title="Archive {{ $customer->name }}?" data-confirm-kicker="Customer relationship retention" data-confirm-label="Archive customer" data-confirm-control="New project selection and customer administration will be disabled" data-confirm-result="Projects, portal ownership and historical records remain retained">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="is-danger" @disabled($customer->active_projects_count > 0)><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v13H4zM7 4h10l2 3H5zM9 11h6"/></svg><span><strong>{{ $customer->active_projects_count > 0 ? 'Archive locked' : 'Archive customer' }}</strong><small>{{ $customer->active_projects_count > 0 ? $customer->active_projects_count . ' open project(s) block archiving' : 'Controlled record retention' }}</small></span></button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                @include('partials.pagination', ['paginator' => $customers])
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-customer-governance.js') }}?v=55a" defer></script>
@endpush
