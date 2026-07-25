@extends('layouts.app')

@section('title', $customer->name)
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Customer Details')

@push('styles')
    <link href="{{ asset('css/nexorin-customer-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@php
    $currentUser = auth()->user();
    $canUpdate = $currentUser->hasCompanyPermission($company, 'customer.update');
    $canArchive = $currentUser->hasCompanyPermission($company, 'customer.delete');
    $canCreateProject = $currentUser->hasAnyCompanyPermission($company, ['project.create', 'project.create_own']);
    $blockingProjectsQuery = $customer->projects()->whereNull('archived_at')->whereNotIn('status', ['completed', 'cancelled']);
    $blockingProjectCount = (clone $blockingProjectsQuery)->count();
    $blockingProjects = $blockingProjectsQuery->latest('updated_at')->limit(4)->get();
    $portalLinked = (bool) $customer->portalUser;
    $location = collect([$customer->city, $customer->province, $customer->postal_code])->filter()->join(' · ');
@endphp

@section('content')
    <div class="nxcus-workspace" data-nxcus-profile data-customer-name="{{ $customer->name }}">
        <section class="nxcus-hero" data-nxcus-reveal>
            <div class="nxcus-hero-main">
                <span class="nxcus-kicker"><i></i>Customer relationship command deck</span>
                <div class="nxcus-hero-identity">
                    <span class="nxcus-hero-icon is-{{ $customer->type }}" aria-hidden="true">
                        @if ($customer->type === 'commercial')
                            <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 57V15l20-7 20 7v42"/><path d="M22 22h5M37 22h5M22 31h5M37 31h5M22 40h5M37 40h5M27 57V48h10v9"/></svg>
                        @else
                            <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 30 32 11l24 19v27H8z"/><circle cx="32" cy="31" r="7"/><path d="M18 56c1.7-10 6.5-15 14-15s12.3 5 14 15"/></svg>
                        @endif
                    </span>
                    <div>
                        <h2>{{ $customer->name }}</h2>
                        <p>{{ $customer->type_label }} relationship inside {{ $company->name }}.</p>
                    </div>
                </div>
                <div class="nxcus-hero-meta">
                    <span><small>Customer ID</small><strong>{{ $customer->customer_number }}</strong></span>
                    <span><small>Location</small><strong>{{ $location ?: 'Not established' }}</strong></span>
                    <span><small>Latest update</small><strong>{{ $customer->updated_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong></span>
                </div>
            </div>

            <aside class="nxcus-hero-control">
                <header>
                    <div>
                        <span>RELATIONSHIP CONTROL STATE</span>
                        <h3>{{ $customer->isActive() ? 'Active customer relationship' : 'Archived customer record' }}</h3>
                        <p>{{ $customer->isActive() ? 'Identity remains available for governed project and portal relationships.' : 'Historical relationships remain retained while new project selection is disabled.' }}</p>
                    </div>
                    <span class="nxcus-status is-{{ $customer->status }}"><i></i>{{ ucfirst($customer->status) }}</span>
                </header>
                <div class="nxcus-control-grid">
                    <span><small>Customer type</small><strong>{{ $customer->type_label }}</strong></span>
                    <span class="{{ $portalLinked ? 'is-positive' : 'is-review' }}"><small>Portal access</small><strong>{{ $portalLinked ? 'Linked' : 'Not linked' }}</strong></span>
                    <span><small>Project records</small><strong>{{ number_format($customer->projects_count) }}</strong></span>
                    <span class="{{ $blockingProjectCount > 0 ? 'is-review' : 'is-positive' }}"><small>Archive gate</small><strong>{{ $blockingProjectCount > 0 ? $blockingProjectCount . ' blocker(s)' : 'Available' }}</strong></span>
                </div>
                <div class="nxcus-hero-actions">
                    <a href="{{ route('companies.customers.index', $company) }}" class="nxcus-button nxcus-button-secondary" data-nxcus-nav="directory">Registry</a>
                    @if ($canCreateProject && $customer->isActive())
                        <a href="{{ route('companies.projects.create', ['company' => $company, 'customer_id' => $customer->id]) }}" class="nxcus-button nxcus-button-secondary" data-nxcus-nav="project-create">New Project</a>
                    @endif
                    @if ($canUpdate && $customer->isActive())
                        <a href="{{ route('companies.customers.edit', [$company, $customer]) }}" class="nxcus-button nxcus-button-primary" data-nxcus-nav="edit">Edit Customer</a>
                    @endif
                </div>
            </aside>
        </section>

        <nav class="nxcus-section-nav" data-nxcus-section-nav aria-label="Customer relationship sections">
            <a href="#customer-overview" class="is-active">Overview</a>
            <a href="#customer-projects">Projects</a>
            <a href="#customer-portal">Portal Access</a>
            <a href="#customer-governance">Audit &amp; Governance</a>
        </nav>

        <section id="customer-overview" class="nxcus-panel" data-nxcus-section data-nxcus-reveal>
            <header class="nxcus-panel-head">
                <div><span class="nxcus-section-kicker">Relationship identity and contact basis</span><h3>Customer Overview</h3><p>Customer classification, communication channels, location, and internal relationship context.</p></div>
                <span class="nxcus-panel-state">{{ $customer->isActive() ? 'CURRENT RECORD' : 'HISTORICAL RECORD' }}</span>
            </header>
            <div class="nxcus-overview-grid">
                <article><small>Customer number</small><strong>{{ $customer->customer_number }}</strong><p>Company-scoped relationship identifier.</p></article>
                <article><small>Relationship type</small><strong>{{ $customer->type_label }}</strong><p>Classification used by project and proposal workflows.</p></article>
                <article><small>Email address</small><strong>{{ $customer->email ?: 'Not provided' }}</strong><p>Primary written communication channel.</p></article>
                <article><small>Phone number</small><strong>{{ $customer->formatted_phone ?: 'Not provided' }}</strong><p>Normalized customer contact number.</p></article>
                <article><small>City or regency</small><strong>{{ $customer->city ?: 'Not provided' }}</strong><p>{{ $customer->province ?: 'Province not provided' }}{{ $customer->postal_code ? ' · ' . $customer->postal_code : '' }}</p></article>
                <article class="is-wide"><small>Customer address</small><strong>{{ $customer->address ?: 'Not provided' }}</strong><p>Street, district, building, and additional location context.</p></article>
                <article class="is-wide"><small>Internal relationship notes</small><strong>{{ $customer->notes ?: 'No internal notes.' }}</strong><p>Internal context is excluded from customer-facing reports.</p></article>
            </div>
        </section>

        <section id="customer-projects" class="nxcus-panel" data-nxcus-section data-nxcus-reveal>
            <header class="nxcus-panel-head">
                <div><span class="nxcus-section-kicker">Project relationship exposure</span><h3>Linked Project Portfolio</h3><p>Latest engineering and commercial records connected to this customer identity.</p></div>
                <a href="{{ route('companies.projects.index', ['company' => $company, 'search' => $customer->customer_number]) }}" class="nxcus-button nxcus-button-secondary" data-nxcus-nav="projects">View all projects</a>
            </header>
            @if ($customer->projects->isEmpty())
                <div class="nxcus-compact-empty"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10M8 19v-6h8v6"/></svg></span><div><strong>No linked projects</strong><p>Create the first project when the customer relationship is ready.</p></div>@if ($canCreateProject && $customer->isActive())<a href="{{ route('companies.projects.create', ['company' => $company, 'customer_id' => $customer->id]) }}" class="nxcus-button nxcus-button-primary" data-nxcus-nav="project-create">New Project</a>@endif</div>
            @else
                <div class="nxcus-project-list">
                    @foreach ($customer->projects as $project)
                        <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="nxcus-project-row" data-nxcus-nav="project">
                            <span class="nxcus-project-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10M8 19v-6h8v6"/></svg></span>
                            <span class="nxcus-project-copy"><strong>{{ $project->name }}</strong><small>{{ $project->project_number }} · {{ $project->proposed_system_label }}</small></span>
                            <span class="nxcus-project-role"><small>Engineer</small><strong>{{ $project->assignedEngineer?->name ?? 'Not assigned' }}</strong></span>
                            <span class="nxcus-project-role"><small>Sales</small><strong>{{ $project->assignedSales?->name ?? 'Not assigned' }}</strong></span>
                            <span class="nxcus-project-status is-{{ $project->status }}">{{ $project->status_label }}</span>
                            <svg class="nxcus-project-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section id="customer-portal" class="nxcus-panel" data-nxcus-section data-nxcus-reveal>
            <header class="nxcus-panel-head">
                <div><span class="nxcus-section-kicker">Restricted customer-facing access</span><h3>Portal Access Governance</h3><p>One Customer-role account may own restricted project access for this customer record.</p></div>
                @if ($canUpdate && $customer->isActive())<a href="{{ route('companies.customers.edit', [$company, $customer]) }}#portal-access" class="nxcus-button nxcus-button-secondary" data-nxcus-nav="edit">Manage portal access</a>@endif
            </header>
            @if ($customer->portalUser)
                <article class="nxcus-portal-record">
                    <span class="nxcus-portal-icon"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="16" r="6"/><path d="M9 37c1.2-7.2 5-10.7 11-10.7S29.8 29.8 31 37"/><path d="M35 21.5 41 24v4.6c0 4.3-2 7.5-6 10-4-2.5-6-5.7-6-10V24z"/><path d="m32.7 29.2 1.7 1.7 3.3-3.8"/></svg></span>
                    <div class="nxcus-portal-copy"><small>LINKED PORTAL IDENTITY</small><strong>{{ $customer->portalUser->name }}</strong><span>{{ $customer->portalUser->email }}</span></div>
                    <div class="nxcus-portal-metric"><small>Account state</small><strong class="is-{{ $customer->portalUser->status }}">{{ ucfirst($customer->portalUser->status) }}</strong></div>
                    <div class="nxcus-portal-metric"><small>Access boundary</small><strong>Customer-owned projects</strong></div>
                    <span class="nxcus-portal-badge"><i></i>Linked</span>
                </article>
            @else
                <div class="nxcus-portal-empty"><span><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="20" cy="17" r="6"/><path d="M8 39c1.5-8 5.5-12 12-12s10.5 4 12 12"/><path d="M34 15h10M39 10v10"/></svg></span><div><strong>No portal identity linked</strong><p>The customer record remains internal. Link an active company member with the Customer role when restricted project access is required.</p></div>@if ($canUpdate && $customer->isActive())<a href="{{ route('companies.customers.edit', [$company, $customer]) }}#portal-access" class="nxcus-button nxcus-button-primary" data-nxcus-nav="edit">Link portal account</a>@endif</div>
            @endif
        </section>

        <section id="customer-governance" class="nxcus-panel" data-nxcus-section data-nxcus-reveal>
            <header class="nxcus-panel-head"><div><span class="nxcus-section-kicker">Record trace and lifecycle protection</span><h3>Audit &amp; Archive Governance</h3><p>Creation trace, latest controlled update, archive blockers, and record-preservation impact.</p></div></header>
            <div class="nxcus-governance-grid">
                <article class="nxcus-audit-card">
                    <h4>Relationship audit trace</h4>
                    <div class="nxcus-audit-timeline">
                        <span><i></i><small>Created</small><strong>{{ $customer->created_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong><p>{{ $customer->creator?->name ?? 'System provisioned' }}</p></span>
                        <span><i></i><small>Last updated</small><strong>{{ $customer->updated_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong><p>{{ $customer->updater?->name ?? 'System updated' }}</p></span>
                        <span><i></i><small>Portal ownership</small><strong>{{ $portalLinked ? 'Linked' : 'Not linked' }}</strong><p>{{ $portalLinked ? $customer->portalUser->email : 'No customer-facing account assigned' }}</p></span>
                    </div>
                </article>

                <article class="nxcus-archive-card {{ $blockingProjectCount > 0 ? 'is-locked' : 'is-available' }}">
                    <header><span class="nxcus-archive-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">@if($blockingProjectCount > 0)<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>@else<path d="M4 7h16v13H4zM7 4h10l2 3H5zM9 11h6"/>@endif</svg></span><div><small>CUSTOMER LIFECYCLE CONTROL</small><h4>{{ $blockingProjectCount > 0 ? 'Archive locked' : 'Archive available' }}</h4><p>{{ $blockingProjectCount > 0 ? 'Resolve every open project before archiving this relationship.' : 'No active project currently blocks controlled archive.' }}</p></div></header>
                    <div class="nxcus-archive-flow"><span class="{{ $blockingProjectCount > 0 ? 'is-active' : 'is-ready' }}"><b>01</b><strong>Resolve open work</strong><small>{{ $blockingProjectCount > 0 ? $blockingProjectCount . ' blocking ' . \Illuminate\Support\Str::plural('project', $blockingProjectCount) : 'No blocking projects' }}</small></span><span class="{{ $blockingProjectCount > 0 ? '' : 'is-ready' }}"><b>02</b><strong>Preserve relationships</strong><small>{{ $blockingProjectCount > 0 ? 'Pending blocker resolution' : 'Preservation confirmed' }}</small></span><span class="{{ $blockingProjectCount > 0 ? 'is-locked' : 'is-ready' }}"><b>03</b><strong>Archive customer</strong><small>{{ $blockingProjectCount > 0 ? 'Available after blockers are resolved' : 'Controlled archive available' }}</small></span></div>
                    @if ($blockingProjectCount > 0)
                        <div class="nxcus-blockers"><strong>{{ $blockingProjectCount }} blocking {{ \Illuminate\Support\Str::plural('project', $blockingProjectCount) }}</strong>@foreach($blockingProjects as $project)<a href="{{ route('companies.projects.show', [$company, $project]) }}" data-nxcus-nav="project"><span>{{ $project->name }}</span><small><span>{{ $project->project_number }} · {{ $project->status_label }}</span><b>Open Project →</b></small></a>@endforeach @if($blockingProjectCount > $blockingProjects->count())<small>+ {{ $blockingProjectCount - $blockingProjects->count() }} additional blocking {{ \Illuminate\Support\Str::plural('project', $blockingProjectCount - $blockingProjects->count()) }}</small>@endif</div>
                    @endif
                    <div class="nxcus-retention"><span><small>Portal relationship</small><strong>Will be preserved for audit</strong></span><span><small>Project records</small><strong>Will be preserved</strong></span><span><small>Historical documents</small><strong>Will be preserved</strong></span></div>
                    @if ($canArchive && $customer->isActive())
                        <form method="POST" action="{{ route('companies.customers.archive', [$company, $customer]) }}" data-nxcus-archive-form data-confirm="Archive this customer relationship?" data-confirm-title="Archive {{ $customer->name }}?" data-confirm-kicker="Customer relationship retention" data-confirm-label="Archive customer" data-confirm-control="New project selection and customer administration will be disabled" data-confirm-result="Projects, portal ownership and historical records remain retained">
                            @csrf @method('PATCH')
                            <button type="submit" class="nxcus-archive-action" @disabled($blockingProjectCount > 0)><span class="nxcus-archive-action-content"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">@if($blockingProjectCount > 0)<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>@else<path d="M4 7h16v13H4zM7 4h10l2 3H5zM9 11h6"/>@endif</svg><span>{{ $blockingProjectCount > 0 ? 'Archive locked' : 'Archive customer' }}</span></span></button>
                        </form>
                    @endif
                </article>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-customer-governance.js') }}?v=55a" defer></script>
@endpush
