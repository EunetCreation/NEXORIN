@php
    $topbarUser = auth()->user();
    $routeCompany = request()->route('company');

    $topbarCompany = $routeCompany instanceof \App\Models\Company && $routeCompany->isActive()
        ? $routeCompany
        : $topbarUser->activeCompanies()->first();

    $topbarRoleLabel = $topbarUser->isSuperAdmin()
        ? 'Super Admin'
        : ($topbarCompany
            ? ($topbarUser->companyMemberships()
                ->where('company_id', $topbarCompany->getKey())
                ->where('status', 'active')
                ->with('roles')
                ->first()?->roles?->first()?->name ?? 'Company Member')
            : 'System User');

    $canCreateProject = $topbarCompany
        && $topbarUser->hasAnyCompanyPermission($topbarCompany, ['project.create', 'project.create_own']);

    $canSearchProjects = $topbarCompany
        && $topbarUser->hasAnyCompanyPermission($topbarCompany, ['project.view', 'project.view_own']);

    $canViewQuotations = $topbarCompany
        && $topbarUser->hasAnyCompanyPermission($topbarCompany, ['quotation.view', 'quotation.view_own']);

    $openQuotationCount = $canViewQuotations
        ? \App\Models\Quotation::query()
            ->where('company_id', $topbarCompany->getKey())
            ->whereNotIn('status', ['accepted', 'rejected', 'cancelled', 'expired'])
            ->count()
        : 0;
@endphp

<header class="topbar">
    <div class="topbar-container">
        <div class="topbar-heading-group">
            <button id="sidebarToggle" class="topbar-icon-button mobile-menu-button" type="button" aria-label="Open navigation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>

            <div class="page-heading">
                <span class="page-eyebrow">@yield('page-eyebrow', 'Solar engineering workspace')</span>
                <h1>@yield('page-heading', 'Dashboard')</h1>
            </div>
        </div>

        <div class="topbar-actions">
            @if ($canSearchProjects)
                <form action="{{ route('companies.projects.index', $topbarCompany) }}" method="GET" class="topbar-search" role="search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M20 20l-3.5-3.5"/>
                    </svg>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search project or customer"
                        aria-label="Search projects"
                    >
                    <kbd>/</kbd>
                </form>
            @endif

            <button id="themeToggle" class="topbar-icon-button theme-toggle" type="button" aria-label="Change color theme" title="Change color theme">
                <svg class="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/>
                </svg>
                <svg class="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2z"/>
                </svg>
            </button>

            @if ($canViewQuotations)
                <a href="{{ route('companies.quotations.index', $topbarCompany) }}" class="topbar-icon-button notification-button" aria-label="Open quotations">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                        <path d="M10 21h4"/>
                    </svg>
                    @if ($openQuotationCount > 0)
                        <span class="notification-count">{{ min($openQuotationCount, 9) }}{{ $openQuotationCount > 9 ? '+' : '' }}</span>
                    @endif
                </a>
            @endif

            @if ($canCreateProject)
                <a href="{{ route('companies.projects.create', $topbarCompany) }}" class="topbar-primary-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    <span>New Project</span>
                </a>
            @endif

            <details class="profile-menu">
                <summary class="profile-menu-trigger" aria-label="Open user menu">
                    <span class="profile-avatar profile-avatar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>
                        </svg>
                    </span>
                    <span class="profile-copy">
                        <strong>{{ $topbarUser->name }}</strong>
                        <span>{{ $topbarRoleLabel }}</span>
                    </span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path d="M7 10l5 5 5-5"/>
                    </svg>
                </summary>

                <div class="profile-menu-panel">
                    <div class="profile-menu-header">
                        <span class="profile-avatar profile-avatar-icon large" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>
                            </svg>
                        </span>
                        <div>
                            <strong>{{ $topbarUser->name }}</strong>
                            <span>{{ $topbarUser->email }}</span>
                        </div>
                    </div>

                    <div class="profile-workspace-row">
                        <span>Active workspace</span>
                        <strong>{{ $topbarCompany?->name ?? 'System Workspace' }}</strong>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="profile-signout">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/>
                            </svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
