@php
    $topbarUser = auth()->user();
    $routeCompany = request()->route('company');

    $topbarCompany = $routeCompany instanceof \App\Models\Company && $routeCompany->isActive()
        ? $routeCompany
        : $topbarUser->activeCompanies()->first();

    $topbarMembership = $topbarCompany
        ? $topbarUser->membershipForCompany($topbarCompany)
        : null;

    $topbarRoleLabel = $topbarUser->isSuperAdmin()
        ? 'Super Admin'
        : ($topbarCompany
            ? ($topbarMembership?->roles?->first()?->name ?? 'Company Member')
            : 'System User');

    $canCreateProject = $topbarCompany
        && $topbarUser->hasAnyCompanyPermission($topbarCompany, ['project.create', 'project.create_own']);

    $canSearchProjects = $topbarCompany
        && $topbarUser->hasAnyCompanyPermission($topbarCompany, ['project.view', 'project.view_own']);

    $canViewQuotations = $topbarCompany
        && $topbarUser->hasAnyCompanyPermission($topbarCompany, ['quotation.view', 'quotation.view_own']);

    $topbarProjects = collect();
    $topbarQuotationProjects = collect();
    $openQuotationCount = 0;

    if ($topbarCompany && $canSearchProjects) {
        $visibleProjectQuery = \App\Models\Project::query()
            ->where('company_id', $topbarCompany->getKey())
            ->whereNull('archived_at');

        if (! $topbarUser->hasCompanyPermission($topbarCompany, 'project.view')) {
            $visibleProjectQuery->ownedBy($topbarUser);
        }

        $topbarProjects = (clone $visibleProjectQuery)
            ->with('customer:id,name')
            ->latest('updated_at')
            ->take(6)
            ->get();

        if ($canViewQuotations) {
            $topbarQuotationProjects = (clone $visibleProjectQuery)
                ->whereHas('quotation')
                ->with(['customer:id,name', 'quotation'])
                ->latest('updated_at')
                ->take(4)
                ->get();

            $openQuotationCount = (clone $visibleProjectQuery)
                ->whereHas('quotation', fn ($query) => $query
                    ->whereNotIn('status', ['accepted', 'rejected', 'cancelled', 'expired']))
                ->count();
        }
    }
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
                <div class="topbar-search-shell">
                    <form
                        action="{{ route('companies.projects.index', $topbarCompany) }}"
                        method="GET"
                        id="globalSearchForm"
                        class="topbar-search"
                        role="search"
                        autocomplete="off"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-3.5-3.5"/>
                        </svg>
                        <input
                            id="globalSearchInput"
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search project or customer"
                            aria-label="Search projects, customers, and quotations"
                            aria-controls="commandPalette"
                            aria-expanded="false"
                        >
                        <kbd>/</kbd>
                    </form>

                    <div id="commandPalette" class="command-palette" role="dialog" aria-label="Workspace search" hidden>
                        <div class="command-palette-header">
                            <div>
                                <span>Workspace search</span>
                                <strong>Jump directly to a record</strong>
                            </div>
                            <span class="command-palette-esc">ESC</span>
                        </div>

                        <div class="command-palette-results" data-command-results>
                            <div class="command-group">
                                <span class="command-group-label">Projects</span>
                                @forelse ($topbarProjects as $searchProject)
                                    <a
                                        href="{{ route('companies.projects.show', [$topbarCompany, $searchProject]) }}"
                                        class="command-item"
                                        data-command-item
                                        data-search-value="{{ str($searchProject->name.' '.$searchProject->project_number.' '.($searchProject->customer?->name ?? ''))->lower() }}"
                                    >
                                        <span class="command-item-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 18h18"/>
                                            </svg>
                                        </span>
                                        <span class="command-item-copy">
                                            <strong>{{ $searchProject->name }}</strong>
                                            <small>{{ $searchProject->customer?->name ?? 'No customer' }} · {{ $searchProject->status_label }}</small>
                                        </span>
                                        <span class="command-item-arrow">↗</span>
                                    </a>
                                @empty
                                    <span class="command-empty">No visible projects yet.</span>
                                @endforelse
                            </div>

                            @if ($canViewQuotations && $topbarQuotationProjects->isNotEmpty())
                                <div class="command-group">
                                    <span class="command-group-label">Quotations</span>
                                    @foreach ($topbarQuotationProjects as $quotationProject)
                                        <a
                                            href="{{ route('companies.quotations.show', [$topbarCompany, $quotationProject]) }}"
                                            class="command-item"
                                            data-command-item
                                            data-search-value="{{ str(($quotationProject->quotation?->quote_number ?? 'draft quotation').' '.$quotationProject->name.' '.($quotationProject->customer?->name ?? ''))->lower() }}"
                                        >
                                            <span class="command-item-icon tone-purple">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/>
                                                </svg>
                                            </span>
                                            <span class="command-item-copy">
                                                <strong>{{ $quotationProject->quotation?->quote_number ?: 'Draft quotation' }}</strong>
                                                <small>{{ $quotationProject->name }}</small>
                                            </span>
                                            <span class="command-item-arrow">↗</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            <div class="command-no-results" data-command-empty hidden>
                                <span>No matching workspace record</span>
                                <small>Press Enter to search the full project list.</small>
                            </div>
                        </div>

                        <div class="command-palette-footer">
                            <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
                            <span><kbd>Enter</kbd> open</span>
                            <span><kbd>/</kbd> focus</span>
                        </div>
                    </div>
                </div>
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
                <button
                    id="notificationToggle"
                    class="topbar-icon-button notification-button"
                    type="button"
                    aria-label="Open workspace notifications"
                    aria-controls="notificationDrawer"
                    aria-expanded="false"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                        <path d="M10 21h4"/>
                    </svg>
                    @if ($openQuotationCount > 0)
                        <span class="notification-count">{{ min($openQuotationCount, 9) }}{{ $openQuotationCount > 9 ? '+' : '' }}</span>
                    @endif
                </button>
            @endif

            @if ($canCreateProject && ! request()->routeIs('companies.projects.create'))
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

@if ($canViewQuotations)
        <button id="notificationBackdrop" class="notification-backdrop" type="button" aria-label="Close notifications"></button>
        <aside id="notificationDrawer" class="notification-drawer" aria-hidden="true" aria-label="Workspace notifications">
            <header class="notification-drawer-header">
                <div>
                    <span>Workspace signals</span>
                    <strong>Notifications</strong>
                </div>
                <button id="notificationClose" type="button" aria-label="Close notifications">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </header>

            <div class="notification-summary">
                <span class="notification-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/></svg>
                </span>
                <div>
                    <strong>{{ $openQuotationCount }} open quotation{{ $openQuotationCount === 1 ? '' : 's' }}</strong>
                    <span>Commercial records that are still active in this workspace.</span>
                </div>
            </div>

            <div class="notification-feed">
                @forelse ($topbarQuotationProjects as $notificationProject)
                    <a href="{{ route('companies.quotations.show', [$topbarCompany, $notificationProject]) }}" class="notification-item">
                        <span class="notification-item-dot"></span>
                        <span class="notification-item-copy">
                            <strong>{{ $notificationProject->quotation?->quote_number ?: 'Draft quotation' }}</strong>
                            <span>{{ $notificationProject->name }}</span>
                            <small>{{ $notificationProject->quotation?->status_label ?? 'Draft' }} · {{ $notificationProject->updated_at->diffForHumans() }}</small>
                        </span>
                        <span class="notification-item-arrow">↗</span>
                    </a>
                @empty
                    <div class="notification-empty">
                        <strong>No active quotation signals</strong>
                        <span>New quotation activity will appear here.</span>
                    </div>
                @endforelse
            </div>

            <a href="{{ route('companies.quotations.index', $topbarCompany) }}" class="notification-drawer-action">
                View all quotations
                <span>→</span>
            </a>
        </aside>
@endif
