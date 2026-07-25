@extends('layouts.app')

@section('title', $user->name)
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'User Access Control')

@push('styles')
    <link href="{{ asset('css/nexorin-user-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@section('content')
    @php
        $isSuperAdmin = $user->systemRoles->contains('code', 'super_admin');
        $emailVerified = (bool) $user->email_verified_at;
        $mfaEnabled = (bool) $user->two_factor_confirmed_at;
        $securityScore = ($emailVerified ? 1 : 0) + ($mfaEnabled ? 1 : 0);
    @endphp

    <div class="nxug-profile" data-nxug-profile data-user-name="{{ $user->name }}" data-user-id="{{ $user->id }}">
        <section class="nxug-profile-hero">
            <div class="nxug-profile-grid" aria-hidden="true"></div>
            <div class="nxug-profile-main">
                <span class="nxug-kicker nxug-kicker--light"><i></i>Identity and access control record</span>
                <div class="nxug-profile-identity">
                    <span class="nxug-profile-avatar" aria-hidden="true">
                        <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="27" cy="22" r="8"/>
                            <path d="M12 48c1.6-9.4 7-14 15-14s13.4 4.6 15 14"/>
                            <path d="M47 27.5 55 31v6.2c0 5.8-2.7 10-8 13.3-5.3-3.3-8-7.5-8-13.3V31z"/>
                            <path d="m43.8 38 2.2 2.2 4.5-5"/>
                        </svg>
                    </span>
                    <div class="nxug-profile-identity-copy">
                        <h2>{{ $user->name }}</h2>
                        <p>{{ $user->email }}</p>
                    </div>
                </div>
                <div class="nxug-profile-meta">
                    <span><small>ACCOUNT ID</small><strong>USR-{{ str_pad((string) $user->id, 5, '0', STR_PAD_LEFT) }}</strong></span>
                    <span><small>CREATED</small><strong>{{ $user->created_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong></span>
                    <span><small>LAST UPDATED</small><strong>{{ $user->updated_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong></span>
                </div>
            </div>
            <aside class="nxug-profile-command">
                <div class="nxug-command-head">
                    <div class="nxug-command-copy">
                        <span class="nxug-command-label">ACCESS CONTROL STATE</span>
                        <strong>{{ $isSuperAdmin ? 'Platform-wide authority' : 'Company-scoped account' }}</strong>
                        <small>{{ $isSuperAdmin ? 'Unrestricted administration across all companies.' : 'Effective access is defined by active company memberships.' }}</small>
                    </div>
                    <span class="nxug-account-state nxug-account-state--{{ $user->status }}"><i></i>{{ ucfirst($user->status) }}</span>
                </div>
                <div class="nxug-command-telemetry">
                    <span class="nxug-command-control {{ $securityScore === 2 ? 'is-passed' : 'is-warning' }}"><small>SECURITY COVERAGE</small><strong>{{ $securityScore }} of 2 enabled</strong></span>
                    <span><small>MEMBERSHIPS</small><strong>{{ number_format($user->active_company_memberships_count) }} active</strong></span>
                    <span class="nxug-command-control {{ $emailVerified ? 'is-passed' : 'is-warning' }}"><small>EMAIL IDENTITY</small><strong>{{ $emailVerified ? 'Verified' : 'Unverified' }}</strong></span>
                    <span class="nxug-command-control {{ $mfaEnabled ? 'is-passed' : 'is-warning' }}"><small>MFA PROTECTION</small><strong>{{ $mfaEnabled ? 'Enabled' : 'Not enabled' }}</strong></span>
                </div>
                <div class="nxug-command-actions">
                    <a href="{{ route('users.edit', $user) }}" class="nxug-primary-action" data-nxug-nav="edit">Edit User</a>
                    <a href="{{ route('users.index') }}" class="nxug-ghost-action" data-nxug-nav="registry">Registry</a>
                </div>
            </aside>
        </section>

        <nav class="nxug-section-nav" aria-label="User administration sections" data-nxug-section-nav>
            <a href="#overview" class="is-active">Overview</a>
            <a href="#security">Security</a>
            <a href="#company-access">Company Access</a>
            <a href="#governance">Governance</a>
        </nav>

        <section id="overview" class="nxug-section" data-nxug-section>
            <header class="nxug-section-head">
                <div><span class="nxug-kicker"><i></i>Identity and effective authority</span><h3>Account overview</h3><p>Review the verified identity, effective platform scope, and current workspace coverage.</p></div>
                <span class="nxug-section-badge">CONTROLLED RECORD</span>
            </header>
            <div class="nxug-overview-grid">
                <div class="nxug-metadata-grid">
                    <article><small>FULL NAME</small><strong>{{ $user->name }}</strong><span>Primary identity record</span></article>
                    <article><small>EMAIL ADDRESS</small><strong>{{ $user->email }}</strong><span>{{ $emailVerified ? 'Verified communication identity' : 'Verification still required' }}</span></article>
                    <article><small>ACCOUNT STATE</small><strong>{{ ucfirst($user->status) }}</strong><span>{{ $user->isActive() ? 'Authentication permitted' : 'Authentication blocked' }}</span></article>
                    <article><small>ACCESS MODEL</small><strong>{{ $isSuperAdmin ? 'Super Admin' : 'Company-scoped' }}</strong><span>{{ $isSuperAdmin ? 'All-company platform authority' : 'Defined by active memberships' }}</span></article>
                    <article><small>ACTIVE MEMBERSHIPS</small><strong>{{ number_format($user->active_company_memberships_count) }}</strong><span>{{ number_format($user->company_memberships_count) }} total retained records</span></article>
                    <article><small>SECURITY POSTURE</small><strong>{{ $securityScore === 2 ? 'Protected' : ($securityScore === 1 ? 'Review required' : 'At risk') }}</strong><span>{{ $securityScore }}/2 baseline controls satisfied</span></article>
                </div>
                <article class="nxug-authority-card {{ $isSuperAdmin ? 'is-super' : '' }}">
                    <span class="nxug-authority-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="M9 12l2 2 4-5"/></svg></span>
                    <small>PLATFORM PRIVILEGE</small>
                    <h4>{{ $isSuperAdmin ? 'Super Admin authority' : 'Company-scoped account' }}</h4>
                    <p>{{ $isSuperAdmin ? 'This account has unrestricted administrative access across all NEXORIN companies. Company memberships remain retained for traceability.' : 'This account receives effective access only through active company memberships and their assigned roles.' }}</p>
                    <div class="nxug-authority-tags">
                        @forelse($user->systemRoles as $role)<span>{{ $role->name }}</span>@empty<span>No system-level role</span>@endforelse
                    </div>
                </article>
            </div>
        </section>

        <section id="security" class="nxug-section" data-nxug-section>
            <header class="nxug-section-head">
                <div><span class="nxug-kicker"><i></i>Authentication and account protection</span><h3>Security posture</h3><p>Separate verified identity, additional authentication protection, and lifecycle chronology.</p></div>
                <span class="nxug-section-badge {{ $securityScore < 2 ? 'is-warning' : '' }}">{{ $securityScore }}/2 CONTROLS</span>
            </header>
            <div class="nxug-security-grid">
                <article class="nxug-control-card {{ $emailVerified ? 'is-passed' : 'is-warning' }}">
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/></svg></span>
                    <div><small>EMAIL VERIFICATION</small><strong>{{ $emailVerified ? 'Verified' : 'Not verified' }}</strong><p>{{ $emailVerified ? 'The sign-in identity has a confirmed email record.' : 'The sign-in identity has not completed email verification.' }}</p></div>
                </article>
                <article class="nxug-control-card {{ $mfaEnabled ? 'is-passed' : 'is-warning' }}">
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M12 17h.01"/></svg></span>
                    <div><small>MULTI-FACTOR AUTHENTICATION</small><strong>{{ $mfaEnabled ? 'Enabled' : 'Not enabled' }}</strong><p>{{ $mfaEnabled ? 'An additional authentication control protects this account.' : ($isSuperAdmin ? 'High-privilege account protection is incomplete. Enable MFA as a priority.' : 'Additional account protection is recommended.') }}</p></div>
                </article>
                <article class="nxug-control-card is-neutral">
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                    <div><small>ACCOUNT CHRONOLOGY</small><strong>{{ $user->updated_at?->timezone('Asia/Jakarta')->diffForHumans() }}</strong><p>Latest recorded account update. Session activity is not currently exposed in this administration view.</p></div>
                </article>
            </div>
        </section>

        <section id="company-access" class="nxug-section" data-nxug-section>
            <header class="nxug-section-head">
                <div><span class="nxug-kicker"><i></i>Tenant membership governance</span><h3>Company access</h3><p>{{ number_format($user->active_company_memberships_count) }} active of {{ number_format($user->company_memberships_count) }} retained memberships.</p></div>
                <a href="{{ route('users.edit', $user) }}#company-access" class="nxug-section-link" data-nxug-nav="edit">Manage access</a>
            </header>
            @if($user->companyMemberships->isEmpty())
                <div class="nxug-empty nxug-empty--compact"><h4>No company access</h4><p>This account has not been assigned to a company workspace.</p></div>
            @else
                <div class="nxug-membership-register">
                    <div class="nxug-membership-head"><span>Company workspace</span><span>Effective role</span><span>Membership</span><span>Joined and invited</span></div>
                    @foreach($user->companyMemberships as $membership)
                        <article class="nxug-membership-record">
                            <div class="nxug-membership-company"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h.01M15 17h.01M3 21h18"/></svg></span><div><strong>{{ $membership->company?->name ?? 'Unknown company' }}</strong><small>{{ $membership->company?->code ?? 'N/A' }} · Company workspace: {{ ucfirst($membership->company?->status ?? 'unknown') }}</small></div></div>
                            <div class="nxug-membership-roles">@forelse($membership->roles as $role)<span>{{ $role->name }}</span>@empty<span class="is-empty">No role assigned</span>@endforelse</div>
                            <div><span class="nxug-account-state nxug-account-state--{{ $membership->status }}"><i></i>Membership {{ ucfirst($membership->status) }}</span></div>
                            <div class="nxug-membership-trace"><strong>{{ $membership->joined_at?->timezone('Asia/Jakarta')->format('d M Y') ?? 'Not joined' }}</strong><small>Invited by {{ $membership->inviter?->name ?? 'System' }}</small></div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section id="governance" class="nxug-section" data-nxug-section>
            <header class="nxug-section-head">
                <div><span class="nxug-kicker"><i></i>Account lifecycle governance</span><h3>{{ $user->isActive() ? 'Deactivate user account' : 'Activate user account' }}</h3><p>{{ $user->isActive() ? 'Restrict sign-in and authenticated requests while retaining memberships, roles, and historical records.' : 'Restore sign-in and assigned workspace access under existing role boundaries.' }}</p></div>
                <span class="nxug-section-badge {{ $user->isActive() ? 'is-warning' : '' }}">{{ $user->isActive() ? 'CONTROLLED RESTRICTION' : 'ACCESS RESTORATION' }}</span>
            </header>
            <div class="nxug-governance-grid">
                <div class="nxug-governance-sequence">
                    <article><span>01</span><div><strong>{{ $user->isActive() ? 'Block authentication' : 'Restore authentication' }}</strong><small>{{ $user->isActive() ? 'New logins and authenticated requests will be blocked.' : 'The account can sign in again after activation.' }}</small></div></article>
                    <article><span>02</span><div><strong>Retain access relationships</strong><small>{{ number_format($user->company_memberships_count) }} membership records and role assignments remain preserved.</small></div></article>
                    <article><span>03</span><div><strong>Protect platform continuity</strong><small>Self-deactivation and last-active-Super-Admin safeguards remain enforced by the platform.</small></div></article>
                </div>
                <form method="POST" action="{{ route('users.status', $user) }}" class="nxug-governance-action" data-nxug-status-form
                    data-confirm="{{ $user->isActive() ? 'Deactivate this user account across all assigned company workspaces?' : 'Activate this user account and restore assigned access?' }}"
                    data-confirm-title="{{ $user->isActive() ? 'Deactivate ' . $user->name . '?' : 'Activate ' . $user->name . '?' }}"
                    data-confirm-kicker="Identity lifecycle control"
                    data-confirm-label="{{ $user->isActive() ? 'Deactivate account' : 'Activate account' }}"
                    data-confirm-control="{{ $user->isActive() ? 'Sign-in and authenticated requests will be blocked' : 'Assigned workspace access will be restored' }}"
                    data-confirm-result="Membership, role and historical records remain retained">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $user->isActive() ? 'inactive' : 'active' }}">
                    <span class="nxug-governance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">@if($user->isActive())<circle cx="12" cy="12" r="8"/><path d="M8 12h8"/>@else<path d="M5 12l4 4L19 6"/>@endif</svg></span>
                    <strong>{{ $user->isActive() ? 'Account access is active' : 'Account access is inactive' }}</strong>
                    <p>{{ auth()->id() === $user->id && $user->isActive() ? 'Your current account is protected from self-deactivation.' : ($user->isActive() ? 'Deactivate only after reviewing affected workspace assignments.' : 'Activation restores access under the retained membership configuration.') }}</p>
                    <button type="submit" class="{{ $user->isActive() ? 'is-danger' : 'is-positive' }}" @disabled(auth()->id() === $user->id && $user->isActive())>{{ $user->isActive() ? 'Deactivate User' : 'Activate User' }}</button>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-user-governance.js') }}?v=55a" defer></script>
@endpush
