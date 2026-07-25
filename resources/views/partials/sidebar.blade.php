@php
    $sidebarUser = auth()->user();
    $routeCompany = request()->route('company');

    $sidebarCompany = $routeCompany instanceof \App\Models\Company && $routeCompany->isActive()
        ? $routeCompany
        : $sidebarUser->activeCompanies()->first();

    $membership = $sidebarCompany
        ? $sidebarUser->companyMemberships()
            ->where('company_id', $sidebarCompany->getKey())
            ->where('status', 'active')
            ->with('roles')
            ->first()
        : null;

    $roleLabel = $sidebarUser->isSuperAdmin()
        ? 'Super Admin'
        : ($membership?->roles?->first()?->name ?? 'Company Member');

    $permissions = [
        'customers' => $sidebarCompany && $sidebarUser->hasCompanyPermission($sidebarCompany, 'customer.view'),
        'projects' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['project.view', 'project.view_own']),
        'load' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['load_analysis.view', 'load_analysis.manage', 'load_analysis.view_own', 'load_analysis.manage_own']),
        'solar' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['location_solar.view', 'location_solar.manage', 'location_solar.view_own', 'location_solar.manage_own']),
        'roof' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['roof_site.view', 'roof_site.manage', 'roof_site.view_own', 'roof_site.manage_own']),
        'recommendation' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['recommendation.view', 'recommendation.run', 'recommendation.override', 'recommendation.view_own']),
        'calculation' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['calculation.view', 'calculation.run', 'calculation.revise', 'calculation.verify', 'calculation.view_own']),
        'bom' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['calculation.view', 'calculation.run', 'calculation.revise', 'calculation.view_own']),
        'financial' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['calculation.view', 'price.selling.view', 'quotation.view', 'quotation.create', 'calculation.view_own', 'quotation.view_own']),
        'catalog' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['equipment.view', 'supplier.view', 'price.cost.view']),
        'quotations' => $sidebarCompany && $sidebarUser->hasAnyCompanyPermission($sidebarCompany, ['quotation.view', 'quotation.view_own']),
    ];

    $workflowIsActive = request()->routeIs(
        'companies.load-analyses.*',
        'companies.location-solar.*',
        'companies.roof-site.*',
        'companies.recommendations.*',
        'companies.technical-calculations.*',
        'companies.boms.*',
        'companies.financial-analyses.*'
    );

    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>',
        'customers' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/></svg>',
        'projects' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg>',
        'workflow' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="6" cy="6" r="2"/><circle cx="18" cy="12" r="2"/><circle cx="6" cy="18" r="2"/><path d="M8 6h3a3 3 0 0 1 3 3v0a3 3 0 0 0 3 3h-1"/><path d="M8 18h3a3 3 0 0 0 3-3v0a3 3 0 0 1 3-3h-1"/></svg>',
        'load' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2L5 14h6l-1 8 8-12h-6z"/></svg>',
        'solar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>',
        'roof' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 11.5L12 4l9 7.5"/><path d="M5.5 10.5V20h13v-9.5"/><path d="M8.5 20v-5.5h7V20"/></svg>',
        'recommendation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M6 15l4-4 3 3 5-7"/><path d="M15 7h3v3"/></svg>',
        'calculation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="3" width="14" height="18" rx="3"/><path d="M8 7h8M8 11h3M13 11h3M8 15h3M13 15h3"/></svg>',
        'bom' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="4" rx="1.5"/><rect x="4" y="10" width="16" height="4" rx="1.5"/><rect x="4" y="16" width="16" height="4" rx="1.5"/></svg>',
        'financial' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16M6 16V9M11 16V5M16 16v-4M21 16V7"/></svg>',
        'catalog' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4h12a2 2 0 0 1 2 2v12l-4-2-4 2-4-2-4 2V6a2 2 0 0 1 2-2z"/><path d="M8 8h8M8 12h8"/></svg>',
        'quotation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/></svg>',
        'companies' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V6l8-3 8 3v15"/><path d="M9 9h1M14 9h1M9 13h1M14 13h1M10 21v-4h4v4"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M9 6l6 6-6 6"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/></svg>',
        'account' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>',
        'workspace' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V6l8-3 8 3v15"/><path d="M8 9h2M14 9h2M8 13h2M14 13h2M10 21v-4h4v4"/></svg>',
    ];
@endphp

<aside id="appSidebar" class="sidebar" aria-label="Main navigation">
    <header class="sidebar-header">
        <a href="{{ route('dashboard') }}" class="sidebar-brand" aria-label="NEXORIN dashboard">
            <img src="{{ asset('images/nexorin-mark.png') }}" alt="" class="sidebar-brand-mark">
            <span class="sidebar-brand-copy">
                <strong>NEXORIN</strong>
                <span>Solar Platform</span>
            </span>
        </a>

        <button id="sidebarClose" class="sidebar-close" type="button" aria-label="Close navigation">
            {!! $icons['close'] !!}
        </button>
    </header>

    <div class="workspace-switcher" title="Active workspace">
        <span class="workspace-avatar workspace-avatar-icon" aria-hidden="true">{!! $icons['workspace'] !!}</span>
        <span class="workspace-copy">
            <strong>{{ $sidebarCompany?->name ?? 'System Workspace' }}</strong>
            <span>{{ $roleLabel }}</span>
        </span>
        <span class="workspace-status" aria-label="Workspace active"></span>
    </div>

    <nav class="sidebar-navigation">
        <div class="nav-section">
            <span class="nav-section-label">Workspace</span>

            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="nav-icon">{!! $icons['dashboard'] !!}</span>
                <span>Dashboard</span>
            </a>

            @if ($permissions['projects'])
                <a href="{{ route('companies.projects.index', $sidebarCompany) }}" class="nav-link {{ request()->routeIs('companies.projects.*') ? 'active' : '' }}">
                    <span class="nav-icon">{!! $icons['projects'] !!}</span>
                    <span>Projects</span>
                </a>
            @endif

            @if ($permissions['customers'])
                <a href="{{ route('companies.customers.index', $sidebarCompany) }}" class="nav-link {{ request()->routeIs('companies.customers.*') ? 'active' : '' }}">
                    <span class="nav-icon">{!! $icons['customers'] !!}</span>
                    <span>Customers</span>
                </a>
            @endif
        </div>

        @if (collect($permissions)->only(['load', 'solar', 'roof', 'recommendation', 'calculation', 'bom', 'financial'])->contains(true))
            <details class="nav-cluster" {{ $workflowIsActive ? 'open' : '' }}>
                <summary class="nav-cluster-summary">
                    <span class="nav-icon">{!! $icons['workflow'] !!}</span>
                    <span>Project Workflow</span>
                    <span class="nav-cluster-chevron">{!! $icons['chevron'] !!}</span>
                </summary>

                <div class="nav-cluster-items">
                    @if ($permissions['load'])
                        <a href="{{ route('companies.load-analyses.index', $sidebarCompany) }}" class="nav-sub-link {{ request()->routeIs('companies.load-analyses.*') ? 'active' : '' }}">
                            <span class="nav-icon">{!! $icons['load'] !!}</span><span>Load Analysis</span>
                        </a>
                    @endif

                    @if ($permissions['solar'])
                        <a href="{{ route('companies.location-solar.index', $sidebarCompany) }}" class="nav-sub-link {{ request()->routeIs('companies.location-solar.*') ? 'active' : '' }}">
                            <span class="nav-icon">{!! $icons['solar'] !!}</span><span>Location & Solar</span>
                        </a>
                    @endif

                    @if ($permissions['roof'])
                        <a href="{{ route('companies.roof-site.index', $sidebarCompany) }}" class="nav-sub-link {{ request()->routeIs('companies.roof-site.*') ? 'active' : '' }}">
                            <span class="nav-icon">{!! $icons['roof'] !!}</span><span>Roof & Site Survey</span>
                        </a>
                    @endif

                    @if ($permissions['recommendation'])
                        <a href="{{ route('companies.recommendations.index', $sidebarCompany) }}" class="nav-sub-link {{ request()->routeIs('companies.recommendations.*') ? 'active' : '' }}">
                            <span class="nav-icon">{!! $icons['recommendation'] !!}</span><span>Recommendation</span>
                        </a>
                    @endif

                    @if ($permissions['calculation'])
                        <a href="{{ route('companies.technical-calculations.index', $sidebarCompany) }}" class="nav-sub-link {{ request()->routeIs('companies.technical-calculations.*') ? 'active' : '' }}">
                            <span class="nav-icon">{!! $icons['calculation'] !!}</span><span>Technical Calculation</span>
                        </a>
                    @endif

                    @if ($permissions['bom'])
                        <a href="{{ route('companies.boms.index', $sidebarCompany) }}" class="nav-sub-link {{ request()->routeIs('companies.boms.*') ? 'active' : '' }}">
                            <span class="nav-icon">{!! $icons['bom'] !!}</span><span>BOM / BOQ</span>
                        </a>
                    @endif

                    @if ($permissions['financial'])
                        <a href="{{ route('companies.financial-analyses.index', $sidebarCompany) }}" class="nav-sub-link {{ request()->routeIs('companies.financial-analyses.*') ? 'active' : '' }}">
                            <span class="nav-icon">{!! $icons['financial'] !!}</span><span>Financial Analysis</span>
                        </a>
                    @endif
                </div>
            </details>
        @endif

        @if ($permissions['catalog'] || $permissions['quotations'])
            <div class="nav-section">
                <span class="nav-section-label">Commercial</span>

                @if ($permissions['catalog'])
                    <a href="{{ route('companies.materials.index', $sidebarCompany) }}" class="nav-link {{ request()->routeIs('companies.materials.*', 'companies.suppliers.*', 'companies.price-books.*') ? 'active' : '' }}">
                        <span class="nav-icon">{!! $icons['catalog'] !!}</span>
                        <span>Material Catalog</span>
                    </a>
                @endif

                @if ($permissions['quotations'])
                    <a href="{{ route('companies.quotations.index', $sidebarCompany) }}" class="nav-link {{ request()->routeIs('companies.quotations.*') ? 'active' : '' }}">
                        <span class="nav-icon">{!! $icons['quotation'] !!}</span>
                        <span>Quotations</span>
                    </a>
                @endif
            </div>
        @endif

        @if ($sidebarUser->isSuperAdmin())
            <div class="nav-section">
                <span class="nav-section-label">Administration</span>

                <a href="{{ route('companies.index') }}" class="nav-link {{ request()->routeIs('companies.*') && ! request()->routeIs('companies.projects.*', 'companies.customers.*') ? 'active' : '' }}">
                    <span class="nav-icon">{!! $icons['companies'] !!}</span>
                    <span>Companies</span>
                </a>

                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <span class="nav-icon">{!! $icons['users'] !!}</span>
                    <span>Users</span>
                </a>
            </div>
        @endif
    </nav>

    <footer class="sidebar-footer">
        <div class="sidebar-account">
            <span class="account-avatar account-avatar-icon" aria-hidden="true">{!! $icons['account'] !!}</span>
            <span class="account-copy">
                <strong>{{ $sidebarUser->name }}</strong>
                <span>{{ $roleLabel }}</span>
            </span>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="account-logout" type="submit" aria-label="Sign out" title="Sign out">
                    {!! $icons['logout'] !!}
                </button>
            </form>
        </div>
    </footer>
</aside>
