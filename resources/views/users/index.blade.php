@extends('layouts.app')

@section('title', 'Users')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Users')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">
                    Identity and access management
                </div>

                <h2 class="ui-page-title">
                    User Directory
                </h2>

                <p class="ui-page-description">
                    Manage NEXORIN accounts, platform access, company memberships,
                    and role assignments from one administration workspace.
                </p>
            </div>

            <div class="ui-page-actions">
                <a
                    href="{{ route('users.create') }}"
                    class="ui-button ui-button-primary"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.9"
                        aria-hidden="true"
                    >
                        <path d="M12 5v14M5 12h14"/>
                    </svg>

                    Add User
                </a>
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="User statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="9" cy="8" r="3"/>
                            <circle cx="17" cy="9" r="2"/>
                            <path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/>
                            <path d="M15 15c3 0 5 1.5 5 4"/>
                        </svg>
                    </span>
                </div>

                <strong class="ui-stat-value">{{ number_format($stats['total']) }}</strong>
                <span class="ui-stat-label">Total Users</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M5 12l4 4L19 6"/>
                        </svg>
                    </span>
                </div>

                <strong class="ui-stat-value">{{ number_format($stats['active']) }}</strong>
                <span class="ui-stat-label">Active Users</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="8"/>
                            <path d="M8 12h8"/>
                        </svg>
                    </span>
                </div>

                <strong class="ui-stat-value">{{ number_format($stats['inactive']) }}</strong>
                <span class="ui-stat-label">Inactive Users</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M12 3l2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5-3.6-3.5 5-.7z"/>
                        </svg>
                    </span>
                </div>

                <strong class="ui-stat-value">{{ number_format($stats['super_admins']) }}</strong>
                <span class="ui-stat-label">Super Admins</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="User filters">
            <form
                method="GET"
                action="{{ route('users.index') }}"
                class="ui-filter-form"
            >
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="user-search">
                        Search users
                    </label>

                    <div class="ui-input-wrap">
                        <svg class="ui-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-4-4"/>
                        </svg>

                        <input
                            id="user-search"
                            class="ui-input ui-input-with-icon"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Name or email address"
                        >
                    </div>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="user-status">Status</label>
                    <select id="user-status" class="ui-select" name="status">
                        <option value="">All statuses</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="user-company">Company</label>
                    <select id="user-company" class="ui-select" name="company">
                        <option value="">All companies</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected($filters['company'] === $company->id)>
                                {{ $company->name }}{{ $company->status === 'inactive' ? ' · Inactive' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="user-role">Role</label>
                    <select id="user-role" class="ui-select" name="role">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected($filters['role'] === $role->id)>
                                {{ $role->name }} · {{ ucfirst($role->scope) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="user-sort">Sort by</label>
                    <select id="user-sort" class="ui-select" name="sort">
                        <option value="created_at" @selected($filters['sort'] === 'created_at')>Created date</option>
                        <option value="name" @selected($filters['sort'] === 'name')>User name</option>
                        <option value="email" @selected($filters['sort'] === 'email')>Email address</option>
                        <option value="status" @selected($filters['sort'] === 'status')>Status</option>
                    </select>
                </div>

                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">

                <button type="submit" class="ui-button ui-button-secondary">
                    Apply Filters
                </button>

                @if ($filters['search'] !== '' || $filters['status'] || $filters['company'] || $filters['role'])
                    <a href="{{ route('users.index') }}" class="ui-button ui-button-secondary">
                        Clear
                    </a>
                @endif
            </form>
        </section>

        <section class="ui-table-shell">
            @if ($users->isEmpty())
                <div class="ui-empty">
                    <div>
                        <div class="ui-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="9" cy="8" r="3"/>
                                <circle cx="17" cy="9" r="2"/>
                                <path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/>
                                <path d="M15 15c3 0 5 1.5 5 4"/>
                            </svg>
                        </div>

                        <h3>No users found</h3>
                        <p>
                            Create a new user or adjust the current filters to display matching accounts.
                        </p>

                        <a href="{{ route('users.create') }}" class="ui-button ui-button-primary">
                            Add User
                        </a>
                    </div>
                </div>
            @else
                <div class="ui-table-scroll">
                    <table class="ui-table ui-user-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Status</th>
                                <th>Platform access</th>
                                <th>Company access</th>
                                <th>Created</th>
                                <th class="ui-text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <div class="ui-company-cell">
                                            <div class="ui-user-avatar">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>

                                            <div class="ui-company-copy">
                                                <strong>{{ $user->name }}</strong>
                                                <span>{{ $user->email }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ui-status ui-status-{{ $user->status }}">
                                            {{ ucfirst($user->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="ui-role-tags ui-role-tags-left">
                                            @forelse ($user->systemRoles as $role)
                                                <span class="ui-role-tag ui-role-tag-accent">{{ $role->name }}</span>
                                            @empty
                                                <span class="ui-table-secondary">Standard account</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">
                                            {{ number_format($user->active_company_memberships_count) }} active
                                        </span>
                                        <span class="ui-table-secondary">
                                            {{ number_format($user->company_memberships_count) }} total memberships
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">
                                            {{ $user->created_at?->timezone('Asia/Jakarta')->format('d M Y') }}
                                        </span>
                                        <span class="ui-table-secondary">
                                            {{ $user->created_at?->timezone('Asia/Jakarta')->format('H:i') }} WIB
                                        </span>
                                    </td>
                                    <td>
                                        <div class="ui-actions">
                                            <a href="{{ route('users.show', $user) }}" class="ui-icon-button" aria-label="View {{ $user->name }}" title="View user">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/>
                                                    <circle cx="12" cy="12" r="2.5"/>
                                                </svg>
                                            </a>

                                            <a href="{{ route('users.edit', $user) }}" class="ui-icon-button" aria-label="Edit {{ $user->name }}" title="Edit user">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M4 20h4l11-11-4-4L4 16v4z"/>
                                                    <path d="M13.5 6.5l4 4"/>
                                                </svg>
                                            </a>

                                            <form method="POST" action="{{ route('users.status', $user) }}">
                                                @csrf
                                                @method('PATCH')

                                                <input type="hidden" name="status" value="{{ $user->isActive() ? 'inactive' : 'active' }}">

                                                <button
                                                    type="submit"
                                                    class="ui-icon-button"
                                                    aria-label="{{ $user->isActive() ? 'Deactivate' : 'Activate' }} {{ $user->name }}"
                                                    title="{{ auth()->id() === $user->id ? 'You cannot deactivate your own account' : ($user->isActive() ? 'Deactivate user' : 'Activate user') }}"
                                                    @disabled(auth()->id() === $user->id && $user->isActive())
                                                    data-confirm="{{ $user->isActive() ? 'Deactivate this user account?' : 'Activate this user account?' }}"
                                                >
                                                    @if ($user->isActive())
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                            <circle cx="12" cy="12" r="8"/>
                                                            <path d="M8 12h8"/>
                                                        </svg>
                                                    @else
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                            <path d="M5 12l4 4L19 6"/>
                                                        </svg>
                                                    @endif
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.pagination', ['paginator' => $users])
            @endif
        </section>
    </div>
@endsection
