@extends('layouts.app')

@section('title', $user->name)
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'User Profile')

@section('content')
    <div class="ui-page">
        <section class="ui-detail-hero">
            <div class="ui-detail-avatar ui-user-detail-avatar">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>

            <div class="ui-detail-copy">
                <h2>{{ $user->name }}</h2>
                <p>
                    {{ $user->email }} · Created
                    {{ $user->created_at?->timezone('Asia/Jakarta')->format('d M Y') }}
                </p>
            </div>

            <div class="ui-detail-actions">
                <span class="ui-status ui-status-{{ $user->status }}">
                    {{ ucfirst($user->status) }}
                </span>

                <a href="{{ route('users.edit', $user) }}" class="ui-button ui-button-secondary">
                    Edit User
                </a>
            </div>
        </section>

        <div class="ui-detail-grid">
            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Account Details</h3>
                        <p class="ui-card-description">
                            Identity, verification, security, and account metadata.
                        </p>
                    </div>
                </div>

                <div class="ui-card-body">
                    <dl class="ui-info-grid">
                        <div class="ui-info-item">
                            <dt>Full name</dt>
                            <dd>{{ $user->name }}</dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Email address</dt>
                            <dd>{{ $user->email }}</dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Email verification</dt>
                            <dd>{{ $user->email_verified_at ? 'Verified' : 'Not verified' }}</dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Two-factor authentication</dt>
                            <dd>{{ $user->two_factor_confirmed_at ? 'Enabled' : 'Not enabled' }}</dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Created date</dt>
                            <dd>{{ $user->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Last updated</dt>
                            <dd>{{ $user->updated_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Platform Access</h3>
                        <p class="ui-card-description">
                            System-level privileges assigned to this account.
                        </p>
                    </div>
                </div>

                <div class="ui-card-body">
                    @if ($user->systemRoles->isEmpty())
                        <div class="ui-empty ui-empty-compact">
                            <div>
                                <div class="ui-empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M12 3l2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5-3.6-3.5 5-.7z"/>
                                    </svg>
                                </div>
                                <h3>Standard account</h3>
                                <p>No system-level role is assigned.</p>
                            </div>
                        </div>
                    @else
                        <div class="ui-access-role-list">
                            @foreach ($user->systemRoles as $role)
                                <article class="ui-access-role-card">
                                    <div class="ui-access-role-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M12 3l2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5-3.6-3.5 5-.7z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <strong>{{ $role->name }}</strong>
                                        <span>{{ $role->description ?: 'System-level platform access.' }}</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Company Memberships</h3>
                    <p class="ui-card-description">
                        {{ number_format($user->active_company_memberships_count) }} active of
                        {{ number_format($user->company_memberships_count) }} total memberships.
                    </p>
                </div>
            </div>

            <div class="ui-card-body">
                @if ($user->companyMemberships->isEmpty())
                    <div class="ui-empty ui-empty-compact">
                        <div>
                            <div class="ui-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 21V5l8-3 8 3v16"/>
                                    <path d="M8 8h2M14 8h2M8 12h2M14 12h2"/>
                                </svg>
                            </div>
                            <h3>No company access</h3>
                            <p>This user has not been assigned to a company workspace.</p>
                        </div>
                    </div>
                @else
                    <div class="ui-membership-list">
                        @foreach ($user->companyMemberships as $membership)
                            <article class="ui-membership-card">
                                <div class="ui-membership-company">
                                    <div class="ui-company-avatar">
                                        {{ strtoupper(substr($membership->company?->code ?? 'CO', 0, 2)) }}
                                    </div>

                                    <div>
                                        <strong>{{ $membership->company?->name ?? 'Unknown Company' }}</strong>
                                        <span>
                                            {{ $membership->company?->code ?? 'N/A' }} ·
                                            {{ ucfirst($membership->company?->status ?? 'unknown') }} company
                                        </span>
                                    </div>
                                </div>

                                <div class="ui-role-tags ui-role-tags-left">
                                    @forelse ($membership->roles as $role)
                                        <span class="ui-role-tag ui-role-tag-accent">{{ $role->name }}</span>
                                    @empty
                                        <span class="ui-role-tag">No role</span>
                                    @endforelse
                                </div>

                                <div>
                                    <span class="ui-status ui-status-{{ $membership->status }}">
                                        {{ ucfirst($membership->status) }}
                                    </span>
                                </div>

                                <div class="ui-membership-meta">
                                    <strong>
                                        {{ $membership->joined_at?->timezone('Asia/Jakarta')->format('d M Y') ?? 'Not joined' }}
                                    </strong>
                                    <span>
                                        Invited by {{ $membership->inviter?->name ?? 'System' }}
                                    </span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="ui-card {{ $user->isActive() ? 'ui-danger-zone' : '' }}">
            <div class="ui-card-header ui-status-action-header">
                <div>
                    <h3 class="ui-card-title">
                        {{ $user->isActive() ? 'Deactivate User' : 'Activate User' }}
                    </h3>
                    <p class="ui-card-description">
                        {{ $user->isActive()
                            ? 'Deactivation blocks new logins and immediately blocks authenticated requests.'
                            : 'Activation restores login and assigned company access.' }}
                    </p>
                </div>

                <form method="POST" action="{{ route('users.status', $user) }}">
                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="status" value="{{ $user->isActive() ? 'inactive' : 'active' }}">

                    <button
                        type="submit"
                        class="ui-button {{ $user->isActive() ? 'ui-button-danger' : 'ui-button-primary' }}"
                        @disabled(auth()->id() === $user->id && $user->isActive())
                        data-confirm="{{ $user->isActive() ? 'Deactivate this user account?' : 'Activate this user account?' }}"
                    >
                        {{ $user->isActive() ? 'Deactivate User' : 'Activate User' }}
                    </button>
                </form>
            </div>

            @if (auth()->id() === $user->id && $user->isActive())
                <div class="ui-card-body" style="padding-top: 14px;">
                    <div class="ui-help">You cannot deactivate your own account.</div>
                </div>
            @endif
        </section>
    </div>
@endsection
