@php
    $currentUser = auth()->user();

    $routeCompany = request()->route('company');

    $currentCompany = $routeCompany instanceof \App\Models\Company
        && $routeCompany->isActive()
            ? $routeCompany
            : $currentUser
                ->activeCompanies()
                ->first();

    $currentMembership = $currentCompany
        ? $currentUser
            ->companyMemberships()
            ->where('company_id', $currentCompany->getKey())
            ->where('status', 'active')
            ->with('roles')
            ->first()
        : null;

    $currentRole = $currentUser->isSuperAdmin()
        ? 'Super Admin'
        : $currentMembership
            ?->roles
            ?->first()
            ?->name ?? 'Member';

    $canViewCustomers = $currentCompany
        && $currentUser->hasCompanyPermission(
            $currentCompany,
            'customer.view'
        );

    $canViewProjects = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            ['project.view', 'project.view_own']
        );

    $canViewLoadAnalysis = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            [
                'load_analysis.view',
                'load_analysis.manage',
                'load_analysis.view_own',
                'load_analysis.manage_own',
            ]
        );

    $canViewLocationSolar = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            [
                'location_solar.view',
                'location_solar.manage',
                'location_solar.view_own',
                'location_solar.manage_own',
            ]
        );

    $canViewRoofSite = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            [
                'roof_site.view',
                'roof_site.manage',
                'roof_site.view_own',
                'roof_site.manage_own',
            ]
        );

    $canViewRecommendations = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            [
                'recommendation.view',
                'recommendation.run',
                'recommendation.override',
                'recommendation.view_own',
            ]
        );

    $canViewTechnicalCalculations = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            [
                'calculation.view',
                'calculation.run',
                'calculation.revise',
                'calculation.verify',
                'calculation.view_own',
            ]
        );

    $canViewBoms = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            [
                'calculation.view',
                'calculation.run',
                'calculation.revise',
                'calculation.view_own',
            ]
        );

    $canViewFinancialAnalyses = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            [
                'calculation.view',
                'price.selling.view',
                'quotation.view',
                'quotation.create',
                'calculation.view_own',
                'quotation.view_own',
            ]
        );

    $canViewQuotations = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            ['quotation.view', 'quotation.view_own']
        );

    $canManageCompany = $currentCompany
        && $currentUser->hasAnyCompanyPermission(
            $currentCompany,
            [
                'company.update',
                'company.settings.manage',
                'member.view',
                'equipment.view',
                'supplier.view',
                'price.cost.view',
            ]
        );
@endphp

<style>
    .sidebar {
        position: fixed;
        z-index: 100;
        inset: 0 auto 0 0;
        width: var(--sidebar-width);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        color: var(--white);
        background:
            radial-gradient(
                circle at 10% 0%,
                rgba(52, 214, 203, 0.16),
                transparent 24%
            ),
            linear-gradient(
                180deg,
                var(--navy-950) 0%,
                #041b2a 55%,
                #031522 100%
            );
        box-shadow:
            20px 0 55px rgba(3, 21, 34, 0.16);
        transition: transform 220ms ease;
    }

    .sidebar::after {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 180px;
        height: 180px;
        content: "";
        pointer-events: none;
        opacity: 0.22;
        background:
            radial-gradient(
                circle,
                rgba(22, 190, 179, 0.55) 0%,
                transparent 68%
            );
        transform: translate(45%, 42%);
    }

    .sidebar-header {
        min-height: 88px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .brand-mark {
        position: relative;
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        display: grid;
        place-items: center;
        overflow: hidden;
        border: 1px solid rgba(52, 214, 203, 0.30);
        border-radius: 15px;
        background:
            linear-gradient(
                145deg,
                rgba(22, 190, 179, 0.22),
                rgba(7, 40, 61, 0.55)
            );
        box-shadow:
            inset 0 0 18px rgba(52, 214, 203, 0.10),
            0 10px 25px rgba(0, 0, 0, 0.18);
    }

    .brand-mark::before {
        position: absolute;
        width: 17px;
        height: 17px;
        content: "";
        border-radius: 50%;
        background: var(--teal-400);
        box-shadow:
            0 0 0 7px rgba(52, 214, 203, 0.10),
            0 0 22px rgba(52, 214, 203, 0.72);
    }

    .brand-mark::after {
        position: absolute;
        right: 7px;
        bottom: 7px;
        width: 7px;
        height: 7px;
        content: "";
        border-radius: 50%;
        background: var(--amber-500);
        box-shadow: 0 0 12px rgba(245, 168, 0, 0.75);
    }

    .brand-copy strong {
        display: block;
        font-family: "Space Grotesk", sans-serif;
        font-size: 20px;
        font-weight: 700;
        letter-spacing: 0.055em;
    }

    .brand-copy span {
        display: block;
        margin-top: 3px;
        color: rgba(255, 255, 255, 0.48);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.13em;
        text-transform: uppercase;
    }

    .sidebar-close {
        width: 38px;
        height: 38px;
        display: none;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 11px;
        color: rgba(255, 255, 255, 0.76);
        cursor: pointer;
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar-company {
        margin: 20px 18px 6px;
        padding: 15px;
        border: 1px solid rgba(52, 214, 203, 0.13);
        border-radius: 17px;
        background:
            linear-gradient(
                135deg,
                rgba(52, 214, 203, 0.10),
                rgba(255, 255, 255, 0.035)
            );
    }

    .company-label {
        margin-bottom: 9px;
        color: rgba(255, 255, 255, 0.44);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .company-row {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .company-symbol {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        color: var(--navy-950);
        font-family: "Space Grotesk", sans-serif;
        font-size: 12px;
        font-weight: 700;
        background:
            linear-gradient(
                135deg,
                var(--teal-400),
                var(--teal-500)
            );
    }

    .company-copy {
        min-width: 0;
    }

    .company-copy strong {
        display: block;
        overflow: hidden;
        color: rgba(255, 255, 255, 0.92);
        font-size: 12px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .company-copy span {
        display: block;
        margin-top: 4px;
        color: rgba(255, 255, 255, 0.46);
        font-size: 10px;
    }

    .sidebar-navigation {
        flex: 1;
        overflow-y: auto;
        padding: 14px 14px 24px;
        scrollbar-width: thin;
        scrollbar-color:
            rgba(255, 255, 255, 0.12)
            transparent;
    }

    .navigation-section + .navigation-section {
        margin-top: 24px;
    }

    .navigation-title {
        margin: 0 10px 10px;
        color: rgba(255, 255, 255, 0.35);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .navigation-list {
        display: grid;
        gap: 5px;
    }

    .navigation-link {
        min-height: 45px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 13px;
        border: 1px solid transparent;
        border-radius: 13px;
        color: rgba(255, 255, 255, 0.62);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition:
            color 170ms ease,
            border-color 170ms ease,
            background 170ms ease,
            transform 170ms ease;
    }

    .navigation-link:hover {
        color: var(--white);
        border-color: rgba(255, 255, 255, 0.06);
        background: rgba(255, 255, 255, 0.055);
        transform: translateX(2px);
    }

    .navigation-link.active {
        color: var(--white);
        border-color: rgba(52, 214, 203, 0.18);
        background:
            linear-gradient(
                90deg,
                rgba(22, 190, 179, 0.18),
                rgba(22, 190, 179, 0.06)
            );
        box-shadow:
            inset 3px 0 0 var(--teal-400);
    }

    .navigation-icon {
        width: 19px;
        height: 19px;
        flex: 0 0 19px;
        color: currentColor;
    }

    .navigation-text {
        flex: 1;
    }

    .navigation-badge {
        padding: 4px 7px;
        border-radius: 999px;
        color: rgba(255, 255, 255, 0.54);
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.06em;
        background: rgba(255, 255, 255, 0.07);
        text-transform: uppercase;
    }

    .navigation-badge.live {
        color: var(--teal-400);
        background: rgba(52, 214, 203, 0.10);
    }

    .navigation-link.active .navigation-badge {
        color: var(--teal-400);
        background: rgba(52, 214, 203, 0.10);
    }

    .sidebar-footer {
        position: relative;
        z-index: 2;
        padding: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.07);
    }

    .account-card {
        padding: 13px;
        border: 1px solid rgba(255, 255, 255, 0.075);
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.045);
    }

    .account-row {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .account-avatar {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        color: var(--white);
        font-family: "Space Grotesk", sans-serif;
        font-size: 14px;
        font-weight: 700;
        background:
            linear-gradient(
                135deg,
                var(--navy-700),
                var(--teal-500)
            );
    }

    .account-copy {
        min-width: 0;
        flex: 1;
    }

    .account-copy strong {
        display: block;
        overflow: hidden;
        color: rgba(255, 255, 255, 0.90);
        font-size: 11px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .account-copy span {
        display: block;
        margin-top: 3px;
        overflow: hidden;
        color: rgba(255, 255, 255, 0.42);
        font-size: 9px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .logout-form {
        margin-top: 11px;
    }

    .logout-button {
        width: 100%;
        min-height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 1px solid rgba(255, 255, 255, 0.09);
        border-radius: 11px;
        color: rgba(255, 255, 255, 0.63);
        font-size: 10px;
        font-weight: 700;
        cursor: pointer;
        background: rgba(255, 255, 255, 0.035);
        transition:
            color 170ms ease,
            border-color 170ms ease,
            background 170ms ease;
    }

    .logout-button:hover {
        color: var(--white);
        border-color: rgba(52, 214, 203, 0.20);
        background: rgba(22, 190, 179, 0.10);
    }

    .logout-button svg {
        width: 15px;
        height: 15px;
    }

    @media (max-width: 1100px) {
        .sidebar {
            transform: translateX(-100%);
        }

        body.sidebar-open .sidebar {
            transform: translateX(0);
        }

        .sidebar-close {
            display: grid;
        }
    }
</style>

<aside
    id="appSidebar"
    class="sidebar"
    aria-label="Main navigation"
>
    <header class="sidebar-header">
        <a
            href="{{ route('dashboard') }}"
            class="sidebar-brand"
        >
            <span
                class="brand-mark"
                aria-hidden="true"
            ></span>

            <span class="brand-copy">
                <strong>NEXORIN</strong>
                <span>Solar Intelligence</span>
            </span>
        </a>

        <button
            id="sidebarClose"
            class="sidebar-close"
            type="button"
            aria-label="Close navigation"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                aria-hidden="true"
            >
                <path d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>
    </header>

    <section class="sidebar-company">
        <div class="company-label">
            Active workspace
        </div>

        <div class="company-row">
            <div class="company-symbol">
                {{ strtoupper(substr($currentCompany?->code ?? 'NX', 0, 2)) }}
            </div>

            <div class="company-copy">
                <strong>
                    {{ $currentCompany?->name ?? 'System Workspace' }}
                </strong>

                <span>
                    {{ $currentRole }}
                </span>
            </div>
        </div>
    </section>

    <nav class="sidebar-navigation">
        <section class="navigation-section">
            <h2 class="navigation-title">
                Workspace
            </h2>

            <div class="navigation-list">
                <a
                    href="{{ route('dashboard') }}"
                    class="navigation-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                >
                    <svg
                        class="navigation-icon"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        aria-hidden="true"
                    >
                        <rect x="3" y="3" width="7" height="7" rx="2"/>
                        <rect x="14" y="3" width="7" height="7" rx="2"/>
                        <rect x="3" y="14" width="7" height="7" rx="2"/>
                        <rect x="14" y="14" width="7" height="7" rx="2"/>
                    </svg>

                    <span class="navigation-text">
                        Dashboard
                    </span>
                </a>

                @if($canViewCustomers)
                    <a
                        href="{{ route('companies.customers.index', $currentCompany) }}"
                        class="navigation-link {{ request()->routeIs('companies.customers.*') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <circle cx="9" cy="8" r="3"/>
                            <circle cx="17" cy="9" r="2"/>
                            <path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/>
                            <path d="M15 15c3 0 5 1.5 5 4"/>
                        </svg>

                        <span class="navigation-text">
                            Customers
                        </span>

                        <span class="navigation-badge live">
                            Live
                        </span>
                    </a>
                @endif

                @if($canViewProjects)
                    <a
                        href="{{ route('companies.projects.index', $currentCompany) }}"
                        class="navigation-link {{ request()->routeIs('companies.projects.*') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <path d="M4 19V9l8-5 8 5v10"/>
                            <path d="M8 19v-6h8v6"/>
                            <path d="M3 19h18"/>
                        </svg>

                        <span class="navigation-text">
                            Projects
                        </span>

                        <span class="navigation-badge live">
                            Live
                        </span>
                    </a>
                @endif

                @if($canViewLoadAnalysis)
                    <a
                        href="{{ route('companies.load-analyses.index', $currentCompany) }}"
                        class="navigation-link {{ request()->routeIs('companies.load-analyses.*') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 3v2M12 19v2M3 12h2M19 12h2"/>
                            <path d="M5.6 5.6L7 7M17 17l1.4 1.4M18.4 5.6L17 7M7 17l-1.4 1.4"/>
                        </svg>

                        <span class="navigation-text">
                            Load Analysis
                        </span>

                        <span class="navigation-badge live">
                            Live
                        </span>
                    </a>
                @endif

                @if($canViewLocationSolar)
                    <a
                        href="{{ route('companies.location-solar.index', $currentCompany) }}"
                        class="navigation-link {{ request()->routeIs('companies.location-solar.*') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <circle cx="8" cy="8" r="3"/>
                            <path d="M8 2v2M8 12v2M2 8h2M12 8h2M3.8 3.8l1.4 1.4M10.8 10.8l1.4 1.4"/>
                            <path d="M14 21s5-4.2 5-8a5 5 0 0 0-10 0c0 3.8 5 8 5 8z"/>
                            <circle cx="14" cy="13" r="1.5"/>
                        </svg>

                        <span class="navigation-text">
                            Location & Solar
                        </span>

                        <span class="navigation-badge live">
                            Live
                        </span>
                    </a>
                @endif

                @if($canViewRoofSite)
                    <a
                        href="{{ route('companies.roof-site.index', $currentCompany) }}"
                        class="navigation-link {{ request()->routeIs('companies.roof-site.*') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <path d="M3 12l9-7 9 7"/>
                            <path d="M5 11v9h14v-9"/>
                            <path d="M9 20v-6h6v6"/>
                            <path d="M16.5 5.5l2-2M18.5 5.5l-2-2"/>
                        </svg>

                        <span class="navigation-text">
                            Roof & Site Survey
                        </span>

                        <span class="navigation-badge live">
                            Live
                        </span>
                    </a>
                @endif

                @if($canViewRecommendations)
                    <a
                        href="{{ route('companies.recommendations.index', $currentCompany) }}"
                        class="navigation-link {{ request()->routeIs('companies.recommendations.*') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <path d="M4 17l5-5 4 3 7-8"/>
                            <path d="M15 7h5v5"/>
                            <path d="M4 21h16"/>
                        </svg>

                        <span class="navigation-text">
                            System Recommendation
                        </span>

                        <span class="navigation-badge live">
                            Live
                        </span>
                    </a>
                @endif


            @if ($canViewTechnicalCalculations)
                <a
                    href="{{ route('companies.technical-calculations.index', $currentCompany) }}"
                    class="navigation-link {{ request()->routeIs('companies.technical-calculations.*') ? 'active' : '' }}"
                >
                    <span class="navigation-icon">∑</span>
                    <span>Technical Calculation</span>
                    <span class="navigation-badge live">LIVE</span>
                </a>
            @endif

            @if ($canViewBoms)
                <a
                    href="{{ route('companies.boms.index', $currentCompany) }}"
                    class="navigation-link {{ request()->routeIs('companies.boms.*') ? 'active' : '' }}"
                >
                    <span class="navigation-icon">☷</span>
                    <span class="navigation-text">BOM/BOQ</span>
                    <span class="navigation-badge live">LIVE</span>
                </a>
            @endif

            @if ($canViewFinancialAnalyses)
                <a
                    href="{{ route('companies.financial-analyses.index', $currentCompany) }}"
                    class="navigation-link {{ request()->routeIs('companies.financial-analyses.*') ? 'active' : '' }}"
                >
                    <span class="navigation-icon">₿</span>
                    <span class="navigation-text">Financial Analysis</span>
                    <span class="navigation-badge live">LIVE</span>
                </a>
            @endif

                @if($canViewQuotations)
                    <a
                        href="{{ route('companies.quotations.index', $currentCompany) }}"
                        class="navigation-link {{ request()->routeIs('companies.quotations.*') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <path d="M5 3h11l3 3v15H5z"/>
                            <path d="M16 3v4h4M8 12h8M8 16h6"/>
                        </svg>

                        <span class="navigation-text">
                            Quotations
                        </span>

                        <span class="navigation-badge live">
                            LIVE
                        </span>
                    </a>
                @endif
            </div>
        </section>

        @if(! $currentUser->isSuperAdmin() && $canManageCompany)
            <section class="navigation-section">
                <h2 class="navigation-title">
                    Company Administration
                </h2>

                <div class="navigation-list">
                    <a
                        href="#"
                        class="navigation-link"
                        onclick="return false;"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21h-4v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3v-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V3h4v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1v4h-.1a1.7 1.7 0 0 0-1.5 1z"/>
                        </svg>

                        <span class="navigation-text">
                            Company Settings
                        </span>

                        <span class="navigation-badge">
                            Soon
                        </span>
                    </a>
                </div>
            </section>
        @endif

        @if($currentUser->isSuperAdmin())
            <section class="navigation-section">
                <h2 class="navigation-title">
                    Administration
                </h2>

                <div class="navigation-list">
                    <a
                        href="{{ route('companies.index') }}"
                        class="navigation-link {{ request()->routeIs('companies.index', 'companies.create', 'companies.show', 'companies.edit') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <path d="M4 21V5l8-3 8 3v16"/>
                            <path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/>
                        </svg>

                        <span class="navigation-text">
                            Companies
                        </span>

                        <span class="navigation-badge live">
                            Live
                        </span>
                    </a>

                    <a
                        href="{{ route('users.index') }}"
                        class="navigation-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <circle cx="9" cy="8" r="3"/>
                            <circle cx="17" cy="9" r="2"/>
                            <path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/>
                            <path d="M15 15c3 0 5 1.5 5 4"/>
                        </svg>

                        <span class="navigation-text">
                            Users
                        </span>

                        <span class="navigation-badge live">
                            Live
                        </span>
                    </a>

                    <a
                        href="#"
                        class="navigation-link"
                        onclick="return false;"
                    >
                        <svg
                            class="navigation-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21h-4v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3v-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V3h4v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1v4h-.1a1.7 1.7 0 0 0-1.5 1z"/>
                        </svg>

                        <span class="navigation-text">
                            System Settings
                        </span>

                        <span class="navigation-badge">
                            Soon
                        </span>
                    </a>
                </div>
            </section>
        @endif
    </nav>

    <footer class="sidebar-footer">
        <div class="account-card">
            <div class="account-row">
                <div class="account-avatar">
                    {{ strtoupper(substr($currentUser->name, 0, 1)) }}
                </div>

                <div class="account-copy">
                    <strong>
                        {{ $currentUser->name }}
                    </strong>

                    <span>
                        {{ $currentUser->email }}
                    </span>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('logout') }}"
                class="logout-form"
            >
                @csrf

                <button
                    type="submit"
                    class="logout-button"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        aria-hidden="true"
                    >
                        <path d="M10 4H5v16h5"/>
                        <path d="M14 8l4 4-4 4M18 12H9"/>
                    </svg>

                    Sign Out
                </button>
            </form>
        </div>
    </footer>
</aside>