@extends('layouts.app')

@section('title', $company->name)
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Company Profile')

@push('styles')
    <link href="{{ asset('css/nexorin-company-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-company-governance.js') }}?v=55a" defer></script>
@endpush

@section('content')
    @php
        $membershipTotal = max(1, (int) $company->memberships_count);
        $membershipCoverage = min(100, (int) round(((int) $company->active_memberships_count / $membershipTotal) * 100));
        $roleOptions = $company->memberships
            ->flatMap(fn ($membership) => $membership->roles)
            ->unique('code')
            ->sortBy('name')
            ->values();
        $activeMemberCount = (int) $company->active_memberships_count;
        $inactiveMemberCount = max(0, (int) $company->memberships_count - $activeMemberCount);
    @endphp

    <div
        class="nxcg-profile"
        data-nxcg-profile
        data-nxcg-company-name="{{ $company->name }}"
        data-nxcg-company-code="{{ $company->code }}"
    >
        <section class="nxcg-profile-hero" data-nxcg-reveal>
            <div class="nxcg-profile-ambient" aria-hidden="true"></div>

            <div class="nxcg-profile-main">
                <span class="nxcg-kicker is-light">Company administration cockpit</span>
                <div class="nxcg-profile-title-row">
                    <span class="nxcg-profile-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 21V5l8-3 8 3v16"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/></svg>
                    </span>
                    <div>
                        <h2>{{ $company->name }}</h2>
                        <p>Govern tenant identity, member access, project context, and company lifecycle without exposing data outside this controlled workspace.</p>
                    </div>
                </div>

                <div class="nxcg-profile-identity">
                    <span><small>Company code</small><b>{{ $company->code }}</b></span>
                    <span><small>Workspace slug</small><b>{{ $company->slug }}</b></span>
                    <span><small>Created</small><b>{{ $company->created_at?->timezone('Asia/Jakarta')->format('d M Y') }}</b></span>
                </div>
            </div>

            <aside class="nxcg-profile-control">
                <div class="nxcg-profile-control-head">
                    <span>Workspace control</span>
                    <span class="nxcg-state is-{{ $company->status }}"><i></i>{{ ucfirst($company->status) }}</span>
                </div>

                <div class="nxcg-control-score">
                    <strong>{{ $activeMemberCount }}</strong>
                    <span>active memberships</span>
                    <i><b style="width:{{ max(4, $membershipCoverage) }}%"></b></i>
                </div>

                <div class="nxcg-control-grid">
                    <span><small>Projects</small><b>{{ number_format($company->projects_count) }}</b></span>
                    <span><small>Customers</small><b>{{ number_format($company->customers_count) }}</b></span>
                    <span><small>Total members</small><b>{{ number_format($company->memberships_count) }}</b></span>
                    <span><small>Created by</small><b>{{ $company->creator?->name ?? 'System' }}</b></span>
                </div>

                <div class="nxcg-control-actions">
                    @if ($company->isActive())
                        <a href="{{ route('companies.projects.index', $company) }}" class="nxcg-button nxcg-button-ghost" data-nxcg-navigate="projects">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>Projects
                        </a>
                        <a href="{{ route('companies.customers.index', $company) }}" class="nxcg-button nxcg-button-ghost" data-nxcg-navigate="customers">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/></svg>Customers
                        </a>
                    @endif
                    <a href="{{ route('companies.edit', $company) }}" class="nxcg-button nxcg-button-primary" data-nxcg-navigate="edit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20h4l11-11-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/></svg>Edit Company
                    </a>
                </div>
            </aside>
        </section>

        <nav class="nxcg-section-nav" data-nxcg-section-nav aria-label="Company profile sections" data-nxcg-reveal>
            <a href="#company-overview" class="is-active" data-nxcg-scroll>Overview</a>
            <a href="#company-members" data-nxcg-scroll>Members</a>
            <a href="#company-governance" data-nxcg-scroll>Access & Governance</a>
        </nav>

        <section class="nxcg-profile-stats" aria-label="Company workspace summary" data-nxcg-reveal>
            <article class="nxcg-profile-stat is-teal">
                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/></svg></span>
                <div><small>Active memberships</small><strong>{{ number_format($activeMemberCount) }}</strong><p>{{ $membershipCoverage }}% of recorded memberships currently have active access.</p></div>
            </article>
            <article class="nxcg-profile-stat is-blue">
                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg></span>
                <div><small>Project portfolio</small><strong>{{ number_format($company->projects_count) }}</strong><p>Projects retained inside this isolated company workspace.</p></div>
            </article>
            <article class="nxcg-profile-stat is-violet">
                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/><path d="M15 15c3 0 5 1.5 5 4"/></svg></span>
                <div><small>Customer records</small><strong>{{ number_format($company->customers_count) }}</strong><p>Customer identities governed by this company tenant.</p></div>
            </article>
            <article class="nxcg-profile-stat is-orange">
                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M8 12h8"/></svg></span>
                <div><small>Restricted memberships</small><strong>{{ number_format($inactiveMemberCount) }}</strong><p>Membership records retained without active company access.</p></div>
            </article>
        </section>

        <section id="company-overview" class="nxcg-panel" data-nxcg-section data-nxcg-reveal>
            <header class="nxcg-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Tenant identity record</span>
                    <h3>Company Overview</h3>
                    <p>Legal identity, contact channels, and creation trace presented as a compact administration record.</p>
                </div>
                <span class="nxcg-panel-state">{{ $company->isActive() ? 'ACCESS ENABLED' : 'ACCESS RESTRICTED' }}</span>
            </header>

            <div class="nxcg-overview-grid">
                <article class="nxcg-overview-block">
                    <span class="nxcg-overview-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V5l8-3 8 3v16"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2"/></svg></span>
                    <div><small>Registration number</small><strong>{{ $company->registration_number ?: 'Not provided' }}</strong><p>Legal registration reference retained for administration.</p></div>
                </article>
                <article class="nxcg-overview-block">
                    <span class="nxcg-overview-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="m4 7 8 6 8-6"/></svg></span>
                    <div><small>Company email</small><strong>{{ $company->email ?: 'Not provided' }}</strong><p>Primary operational contact channel.</p></div>
                </article>
                <article class="nxcg-overview-block">
                    <span class="nxcg-overview-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h4l2 5-3 2c1.5 3 3.5 5 6.5 6.5l2-3 4 2v4c0 1.1-.9 2-2 2C10 21.5 2.5 14 2.5 4.5c0-1.1.9-2 2-2z"/></svg></span>
                    <div><small>Company phone</small><strong>{{ $company->formatted_phone ?: 'Not provided' }}</strong><p>Primary phone reference for company operations.</p></div>
                </article>
                <article class="nxcg-overview-block">
                    <span class="nxcg-overview-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12z"/><circle cx="12" cy="9" r="2"/></svg></span>
                    <div><small>Company address</small><strong>{{ $company->address ?: 'Not provided' }}</strong><p>Registered office and operating address.</p></div>
                </article>
                <article class="nxcg-overview-block">
                    <span class="nxcg-overview-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3"/><path d="M5 21c0-4.2 2.8-7 7-7s7 2.8 7 7"/></svg></span>
                    <div><small>Created by</small><strong>{{ $company->creator?->name ?? 'System' }}</strong><p>{{ $company->creator?->email ?? 'Platform-controlled creation event' }}</p></div>
                </article>
                <article class="nxcg-overview-block">
                    <span class="nxcg-overview-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></span>
                    <div><small>Created timestamp</small><strong class="nxcg-mono">{{ $company->created_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong><p>Asia/Jakarta administration time.</p></div>
                </article>
            </div>
        </section>

        <section id="company-members" class="nxcg-panel" data-nxcg-section data-nxcg-reveal>
            <header class="nxcg-panel-head is-members">
                <div>
                    <span class="nxcg-section-kicker">Membership and role visibility</span>
                    <h3>Company Members</h3>
                    <p>{{ number_format($company->active_memberships_count) }} active of {{ number_format($company->memberships_count) }} total memberships. Role assignment remains governed by the existing Users and permissions workflow.</p>
                </div>
                <span class="nxcg-panel-state">{{ number_format($company->memberships_count) }} RECORDS</span>
            </header>

            @if ($company->memberships->isEmpty())
                <div class="nxcg-empty is-compact">
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/></svg></span>
                    <div><h3>No members assigned</h3><p>Membership management remains available through the existing Users and permission workflow.</p></div>
                </div>
            @else
                <div class="nxcg-member-toolbar" data-nxcg-member-toolbar>
                    <label class="nxcg-member-search">
                        <span>Search members</span>
                        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" placeholder="Name or email" data-nxcg-member-search></span>
                    </label>
                    <label>
                        <span>Access state</span>
                        <select data-nxcg-member-status>
                            <option value="all">All states</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>
                    <label>
                        <span>Role</span>
                        <select data-nxcg-member-role>
                            <option value="all">All roles</option>
                            @foreach ($roleOptions as $role)
                                <option value="{{ $role->code }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <strong data-nxcg-member-count>{{ number_format($company->memberships_count) }} visible</strong>
                </div>

                <div class="nxcg-member-columns" aria-hidden="true">
                    <span>Member identity</span><span>Roles</span><span>Access state</span><span>Joined</span>
                </div>

                <div class="nxcg-member-list" data-nxcg-member-list>
                    @foreach ($company->memberships as $membership)
                        @php
                            $roleCodes = $membership->roles->pluck('code')->implode(' ');
                            $roleNames = $membership->roles->pluck('name')->implode(', ');
                        @endphp
                        <article
                            class="nxcg-member-row"
                            data-nxcg-member
                            data-member-search="{{ strtolower(($membership->user?->name ?? '').' '.($membership->user?->email ?? '')) }}"
                            data-member-status="{{ $membership->status }}"
                            data-member-roles="{{ $roleCodes }}"
                        >
                            <div class="nxcg-member-identity">
                                <span aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                        <circle cx="12" cy="8" r="3.25"/>
                                        <path d="M5.5 20c.7-4.15 3.05-6.25 6.5-6.25s5.8 2.1 6.5 6.25"/>
                                        <path d="M18.2 5.2 20 7l-3.8 3.8" opacity=".72"/>
                                    </svg>
                                </span>
                                <div><strong>{{ $membership->user?->name ?? 'Unknown User' }}</strong><small>{{ $membership->user?->email ?? 'No email available' }}</small></div>
                            </div>
                            <div class="nxcg-member-roles">
                                @forelse ($membership->roles as $role)
                                    <span class="is-{{ $role->code }}">{{ $role->name }}</span>
                                @empty
                                    <span>Member</span>
                                @endforelse
                            </div>
                            <div class="nxcg-member-state">
                                <span class="nxcg-state is-{{ $membership->status }}"><i></i>{{ ucfirst($membership->status) }}</span>
                                <small>{{ $membership->isActive() ? 'Company access enabled' : 'Company access restricted' }}</small>
                            </div>
                            <div class="nxcg-member-joined">
                                <strong class="nxcg-mono">{{ $membership->joined_at?->timezone('Asia/Jakarta')->format('d M Y') ?? 'Pending' }}</strong>
                                <small>{{ $membership->hasJoined() ? 'Membership joined' : 'Awaiting join milestone' }}</small>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="nxcg-member-empty" hidden data-nxcg-member-empty>No memberships match the active filters.</div>
            @endif
        </section>

        <section id="company-governance" class="nxcg-panel nxcg-governance" data-nxcg-section data-nxcg-reveal>
            <header class="nxcg-panel-head">
                <div>
                    <span class="nxcg-section-kicker">Access lifecycle and record retention</span>
                    <h3>Access & Governance</h3>
                    <p>Company status controls workspace availability. Archiving remains blocked until access is deactivated, while customers, projects, and membership records are preserved.</p>
                </div>
                <span class="nxcg-panel-state {{ $company->isActive() ? 'is-warning' : 'is-ready' }}">{{ $company->isActive() ? 'ARCHIVE BLOCKED' : 'ARCHIVE AVAILABLE' }}</span>
            </header>

            <div class="nxcg-governance-grid">
                <article class="nxcg-access-card {{ $company->isActive() ? 'is-active' : 'is-inactive' }}">
                    <span class="nxcg-governance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg></span>
                    <div>
                        <small>Workspace access state</small>
                        <h4>{{ $company->isActive() ? 'Company access is active' : 'Company access is restricted' }}</h4>
                        <p>{{ $company->isActive() ? 'Active memberships can access company-scoped NEXORIN workflows according to their assigned roles and permissions.' : 'Company-scoped workspace access is disabled while records remain retained.' }}</p>
                    </div>
                    <p class="nxcg-access-impact"><strong>{{ number_format($activeMemberCount) }}</strong> active memberships {{ $company->isActive() ? 'currently have workspace access.' : 'remain retained while workspace access is restricted.' }}</p>
                    <form
                        method="POST"
                        action="{{ route('companies.status', $company) }}"
                        data-nxcg-status-form
                        data-confirm="{{ $company->isActive() ? 'Deactivate this company? Active memberships will lose access to the company workspace until it is reactivated.' : 'Activate this company? Active memberships will regain access according to their assigned roles.' }}"
                        data-confirm-tone="{{ $company->isActive() ? 'danger' : 'default' }}"
                        data-confirm-title="{{ $company->isActive() ? 'Restrict company workspace access?' : 'Restore company workspace access?' }}"
                        data-confirm-label="{{ $company->isActive() ? 'Deactivate company' : 'Activate company' }}"
                        data-confirm-control="Tenant access transition"
                        data-confirm-result="{{ $company->isActive() ? 'Workspace access will be disabled' : 'Workspace access will be restored' }}"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $company->isActive() ? 'inactive' : 'active' }}">
                        <button type="submit" class="nxcg-button {{ $company->isActive() ? 'nxcg-button-warning' : 'nxcg-button-primary' }}">{{ $company->isActive() ? 'Deactivate Company' : 'Activate Company' }}</button>
                    </form>
                </article>

                <article class="nxcg-retention-card">
                    <span class="nxcg-governance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M6 7l1 14h10l1-14M9 7V4h6v3"/><path d="M9 12h6"/></svg></span>
                    <div>
                        <small>Record retention gate</small>
                        <h4>Archive Company</h4>
                        <p>Archiving hides this tenant from active administration views while preserving database records and historical project context.</p>
                    </div>
                    <div class="nxcg-retention-flow" aria-label="Company archive sequence">
                        <span class="nxcg-retention-step {{ $company->isActive() ? 'is-current' : 'is-complete' }}"><b>01</b>Deactivate workspace</span>
                        <span class="nxcg-retention-step {{ $company->isActive() ? '' : 'is-complete' }}"><b>02</b>Confirm retained records</span>
                        <span class="nxcg-retention-step {{ $company->isActive() ? '' : 'is-current' }}"><b>03</b>Archive company</span>
                    </div>
                    <dl>
                        <div><dt>Access prerequisite</dt><dd>{{ $company->isActive() ? 'Deactivate first' : 'Satisfied' }}</dd></div>
                        <div><dt>Memberships preserved</dt><dd>{{ number_format($company->memberships_count) }}</dd></div>
                        <div><dt>Projects preserved</dt><dd>{{ number_format($company->projects_count) }}</dd></div>
                        <div><dt>Customers preserved</dt><dd>{{ number_format($company->customers_count) }}</dd></div>
                    </dl>
                    <form
                        method="POST"
                        action="{{ route('companies.destroy', $company) }}"
                        data-nxcg-archive-form
                        data-confirm="Archive this company? The tenant will be hidden from active administration views while database records remain preserved."
                        data-confirm-tone="danger"
                        data-confirm-title="Archive this company record?"
                        data-confirm-label="Archive company"
                        data-confirm-control="Soft-delete tenant record"
                        data-confirm-result="Active administration visibility will be removed"
                    >
                        @csrf
                        @method('DELETE')
                        @if ($company->isActive())
                            <span class="nxcg-archive-status is-locked">
                                <strong>Archive locked</strong>
                                <small>Deactivate company access before archiving this tenant record.</small>
                            </span>
                            <button type="submit" class="nxcg-button nxcg-button-danger" disabled>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                Archive locked
                            </button>
                        @else
                            <span class="nxcg-archive-status is-ready">
                                <strong>Archive available</strong>
                                <small>Workspace access is inactive and retained records are ready for acknowledgement.</small>
                            </span>
                            <button type="submit" class="nxcg-button nxcg-button-danger">Archive Company</button>
                        @endif
                    </form>
                </article>
            </div>
        </section>
    </div>
@endsection
