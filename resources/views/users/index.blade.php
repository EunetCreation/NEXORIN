@extends('layouts.app')

@section('title', 'Users')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Users')

@push('styles')
    <link href="{{ asset('css/nexorin-user-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@section('content')
    @php
        $totalUsers = max(1, (int) $stats['total']);
        $activeRatio = (int) round(((int) $stats['active'] / $totalUsers) * 100);
        $inactiveRatio = (int) round(((int) $stats['inactive'] / $totalUsers) * 100);
        $superAdminRatio = (int) round(((int) $stats['super_admins'] / $totalUsers) * 100);
    @endphp

    <div class="nxug-directory" data-nxug-directory>
        <section class="nxug-register-shell">
            <header class="nxug-register-head">
                <div class="nxug-register-copy">
                    <span class="nxug-kicker"><i></i>Identity, security and access control</span>
                    <h2>Identity &amp; Access Governance Register</h2>
                    <p>Review account identity, security posture, platform privilege, company access, and lifecycle state without leaving the administration context.</p>
                </div>

                <div class="nxug-register-actions">
                    <span class="nxug-result-count">{{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} OF {{ $users->total() }}</span>
                    <a href="{{ route('users.create') }}" class="nxug-primary-action" data-nxug-nav="create">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Add User
                    </a>
                </div>
            </header>

            <section class="nxug-stats" aria-label="User governance statistics">
                <article class="nxug-stat nxug-stat--teal">
                    <span class="nxug-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6M15 15c3 0 5 1.5 5 4"/></svg></span>
                    <div><small>REGISTERED ACCOUNTS</small><strong>{{ number_format($stats['total']) }}</strong><span>Identity records under platform governance.</span></div>
                    <i style="--nxug-progress:100%"></i>
                </article>
                <article class="nxug-stat nxug-stat--blue">
                    <span class="nxug-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12l4 4L19 6"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span>
                    <div><small>ACTIVE ACCOUNTS</small><strong>{{ number_format($stats['active']) }}</strong><span>{{ $activeRatio }}% currently permitted to sign in.</span></div>
                    <i style="--nxug-progress:{{ $activeRatio }}%"></i>
                </article>
                <article class="nxug-stat nxug-stat--orange">
                    <span class="nxug-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M8 12h8"/></svg></span>
                    <div><small>INACTIVE ACCOUNTS</small><strong>{{ number_format($stats['inactive']) }}</strong><span>{{ $inactiveRatio }}% blocked from authenticated access.</span></div>
                    <i style="--nxug-progress:{{ $inactiveRatio }}%"></i>
                </article>
                <article class="nxug-stat nxug-stat--violet">
                    <span class="nxug-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3l2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5-3.6-3.5 5-.7z"/></svg></span>
                    <div><small>PLATFORM PRIVILEGE</small><strong>{{ number_format($stats['super_admins']) }}</strong><span>{{ $superAdminRatio }}% hold Super Admin authority.</span></div>
                    <i style="--nxug-progress:{{ $superAdminRatio }}%"></i>
                </article>
            </section>

            <form method="GET" action="{{ route('users.index') }}" class="nxug-filter" data-nxug-filter-form>
                <div class="nxug-field nxug-field--search">
                    <label for="user-search">Search registry</label>
                    <div class="nxug-input-shell">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                        <input id="user-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Name or email address">
                    </div>
                </div>
                <div class="nxug-field">
                    <label for="user-status">Account state</label>
                    <select id="user-status" name="status">
                        <option value="">All states</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="nxug-field">
                    <label for="user-company">Company access</label>
                    <select id="user-company" name="company">
                        <option value="">All companies</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected($filters['company'] === $company->id)>{{ $company->name }}{{ $company->status === 'inactive' ? ' · Inactive' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="nxug-field">
                    <label for="user-role">Effective role</label>
                    <select id="user-role" name="role">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected($filters['role'] === $role->id)>{{ $role->name }} · {{ ucfirst($role->scope) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="nxug-field">
                    <label for="user-sort">Sort register</label>
                    <select id="user-sort" name="sort">
                        <option value="created_at" @selected($filters['sort'] === 'created_at')>Created date</option>
                        <option value="name" @selected($filters['sort'] === 'name')>User name</option>
                        <option value="email" @selected($filters['sort'] === 'email')>Email address</option>
                        <option value="status" @selected($filters['sort'] === 'status')>Account state</option>
                    </select>
                </div>
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                <div class="nxug-filter-actions">
                    <button type="submit" class="nxug-filter-apply">Apply</button>
                    @if ($filters['search'] !== '' || $filters['status'] || $filters['company'] || $filters['role'])
                        <a href="{{ route('users.index') }}" class="nxug-filter-clear" data-nxug-nav="filter">Clear</a>
                    @endif
                </div>
            </form>

            <section class="nxug-registry" data-nxug-registry>
                @if ($users->isEmpty())
                    <div class="nxug-empty">
                        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6M15 15c3 0 5 1.5 5 4"/></svg></span>
                        <h3>No identity records match this view</h3>
                        <p>Adjust the filters or create a controlled NEXORIN account.</p>
                        <a href="{{ route('users.create') }}" class="nxug-primary-action" data-nxug-nav="create">Add User</a>
                    </div>
                @else
                    <div class="nxug-registry-head" aria-hidden="true">
                        <span>User identity</span><span>Security state</span><span>Platform privilege</span><span>Company access</span><span>Account state</span><span>Action</span>
                    </div>
                    <div class="nxug-registry-body">
                        @foreach ($users as $user)
                            @php
                                $primaryMembership = $user->companyMemberships->firstWhere('status', 'active') ?? $user->companyMemberships->first();
                                $primaryRole = $primaryMembership?->roles?->first();
                                $isSuperAdmin = $user->systemRoles->contains('code', 'super_admin');
                                $securityPassed = ($user->email_verified_at ? 1 : 0) + ($user->two_factor_confirmed_at ? 1 : 0);
                            @endphp
                            <article class="nxug-user-row" data-nxug-row data-href="{{ route('users.show', $user) }}" tabindex="0" aria-label="Open {{ $user->name }}">
                                <div class="nxug-user-identity">
                                    <span class="nxug-avatar" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="16" r="6"/><path d="M9 37c1.2-7.2 5-10.7 11-10.7S29.8 29.8 31 37"/><path d="M35 21.5 41 24v4.6c0 4.3-2 7.5-6 10-4-2.5-6-5.7-6-10V24z"/><path d="m32.7 29.2 1.7 1.7 3.3-3.8"/></svg></span>
                                    <div>
                                        <strong title="{{ $user->name }}">{{ $user->name }}</strong>
                                        <span title="{{ $user->email }}">{{ $user->email }}</span>
                                        <small class="nxug-user-meta">
                                            <span>USR-{{ str_pad((string) $user->id, 5, '0', STR_PAD_LEFT) }}</span>
                                            <span class="nxug-identity-security {{ $securityPassed === 2 ? 'is-secure' : ($securityPassed === 1 ? 'is-review' : 'is-risk') }}">{{ $securityPassed }}/2 security</span>
                                        </small>
                                    </div>
                                </div>
                                <div class="nxug-security-cell">
                                    <span class="nxug-security-score {{ $securityPassed === 2 ? 'is-secure' : ($securityPassed === 1 ? 'is-review' : 'is-risk') }}">{{ $securityPassed }}/2 controls</span>
                                    <small>{{ $user->email_verified_at ? 'Email verified' : 'Email unverified' }}</small>
                                    <small>{{ $user->two_factor_confirmed_at ? 'MFA enabled' : 'MFA not enabled' }}</small>
                                </div>
                                <div class="nxug-platform-cell">
                                    @if ($isSuperAdmin)
                                        <span class="nxug-privilege nxug-privilege--super">Super Admin</span>
                                        <small>Platform-wide authority</small>
                                    @else
                                        <span class="nxug-privilege">Company-scoped</span>
                                        <small>Defined by active memberships</small>
                                    @endif
                                </div>
                                <div class="nxug-company-cell">
                                    <strong>{{ number_format($user->active_company_memberships_count) }} active {{ \Illuminate\Support\Str::plural('membership', $user->active_company_memberships_count) }}</strong>
                                    @if ($primaryMembership)
                                        <span>{{ $primaryMembership->company?->name ?? 'Unknown company' }}</span>
                                        <small>{{ $primaryRole?->name ?? 'Role not assigned' }}{{ $user->company_memberships_count > 1 ? ' · +' . ($user->company_memberships_count - 1) . ' more' : '' }}</small>
                                    @else
                                        <span>No company workspace</span><small>Access requires assignment</small>
                                    @endif
                                </div>
                                <div class="nxug-state-cell">
                                    <span class="nxug-account-state nxug-account-state--{{ $user->status }}"><i></i>{{ ucfirst($user->status) }}</span>
                                    <strong>{{ $user->updated_at?->timezone('Asia/Jakarta')->format('d M Y') }}</strong>
                                    <small>Last account update</small>
                                </div>
                                <div class="nxug-row-actions">
                                    <a href="{{ route('users.show', $user) }}" class="nxug-open" data-nxug-nav="open">Open <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg></a>
                                    <div class="nxug-action-menu" data-nxug-menu>
                                        <button type="button" class="nxug-more" aria-label="More actions for {{ $user->name }}" aria-expanded="false" data-nxug-menu-toggle><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg></button>
                                        <div class="nxug-menu-popover" hidden data-nxug-menu-popover>
                                            <a href="{{ route('users.edit', $user) }}" data-nxug-nav="edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg><span><strong>Edit user</strong><small>Identity, security and access</small></span></a>
                                            <form method="POST" action="{{ route('users.status', $user) }}"
                                                data-nxug-status-form
                                                data-confirm="{{ $user->isActive() ? 'Deactivate this user account across all assigned company workspaces?' : 'Activate this user account and restore assigned access?' }}"
                                                data-confirm-title="{{ $user->isActive() ? 'Deactivate ' . $user->name . '?' : 'Activate ' . $user->name . '?' }}"
                                                data-confirm-kicker="Identity lifecycle control"
                                                data-confirm-label="{{ $user->isActive() ? 'Deactivate account' : 'Activate account' }}"
                                                data-confirm-control="{{ $user->isActive() ? 'Sign-in and authenticated requests will be blocked' : 'Assigned workspace access will be restored' }}"
                                                data-confirm-result="Membership and historical records remain retained">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $user->isActive() ? 'inactive' : 'active' }}">
                                                <button type="submit" class="{{ $user->isActive() ? 'is-danger' : 'is-positive' }}" @disabled(auth()->id() === $user->id && $user->isActive())>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">@if($user->isActive())<circle cx="12" cy="12" r="8"/><path d="M8 12h8"/>@else<path d="M5 12l4 4L19 6"/>@endif</svg>
                                                    <span><strong>{{ $user->isActive() ? 'Deactivate user' : 'Activate user' }}</strong><small>{{ auth()->id() === $user->id && $user->isActive() ? 'Current account is protected' : 'Controlled lifecycle transition' }}</small></span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    @include('partials.pagination', ['paginator' => $users])
                @endif
            </section>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-user-governance.js') }}?v=55a" defer></script>
@endpush
