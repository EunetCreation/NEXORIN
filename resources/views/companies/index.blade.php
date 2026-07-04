@extends('layouts.app')

@section('title', 'Companies')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Companies')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">
                    Multi-company management
                </div>

                <h2 class="ui-page-title">
                    Company Directory
                </h2>

                <p class="ui-page-description">
                    Manage every organization using NEXORIN, review membership activity,
                    and control company access from one secure administration workspace.
                </p>
            </div>

            <div class="ui-page-actions">
                <a
                    href="{{ route('companies.create') }}"
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

                    Add Company
                </a>
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="Company statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M4 21V5l8-3 8 3v16"/>
                            <path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/>
                        </svg>
                    </span>
                </div>

                <strong class="ui-stat-value">
                    {{ number_format($stats['total']) }}
                </strong>
                <span class="ui-stat-label">Total Companies</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M5 12l4 4L19 6"/>
                        </svg>
                    </span>
                </div>

                <strong class="ui-stat-value">
                    {{ number_format($stats['active']) }}
                </strong>
                <span class="ui-stat-label">Active Companies</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="8"/>
                            <path d="M8 12h8"/>
                        </svg>
                    </span>
                </div>

                <strong class="ui-stat-value">
                    {{ number_format($stats['inactive']) }}
                </strong>
                <span class="ui-stat-label">Inactive Companies</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <circle cx="9" cy="8" r="3"/>
                            <circle cx="17" cy="9" r="2"/>
                            <path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/>
                            <path d="M15 15c3 0 5 1.5 5 4"/>
                        </svg>
                    </span>
                </div>

                <strong class="ui-stat-value">
                    {{ number_format($stats['memberships']) }}
                </strong>
                <span class="ui-stat-label">Active Memberships</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="Company filters">
            <form
                method="GET"
                action="{{ route('companies.index') }}"
                class="ui-filter-form"
            >
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="company-search">
                        Search companies
                    </label>

                    <div class="ui-input-wrap">
                        <svg
                            class="ui-input-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-4-4"/>
                        </svg>

                        <input
                            id="company-search"
                            class="ui-input ui-input-with-icon"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Name, code, email, or registration number"
                        >
                    </div>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="company-status">
                        Status
                    </label>

                    <select
                        id="company-status"
                        class="ui-select"
                        name="status"
                    >
                        <option value="">All statuses</option>
                        <option
                            value="active"
                            @selected($filters['status'] === 'active')
                        >
                            Active
                        </option>
                        <option
                            value="inactive"
                            @selected($filters['status'] === 'inactive')
                        >
                            Inactive
                        </option>
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="company-sort">
                        Sort by
                    </label>

                    <select
                        id="company-sort"
                        class="ui-select"
                        name="sort"
                    >
                        <option value="created_at" @selected($filters['sort'] === 'created_at')>
                            Created date
                        </option>
                        <option value="name" @selected($filters['sort'] === 'name')>
                            Company name
                        </option>
                        <option value="code" @selected($filters['sort'] === 'code')>
                            Company code
                        </option>
                        <option value="status" @selected($filters['sort'] === 'status')>
                            Status
                        </option>
                    </select>
                </div>

                <input
                    type="hidden"
                    name="direction"
                    value="{{ $filters['direction'] }}"
                >

                <button
                    type="submit"
                    class="ui-button ui-button-secondary"
                >
                    Apply Filters
                </button>

                @if ($filters['search'] !== '' || $filters['status'])
                    <a
                        href="{{ route('companies.index') }}"
                        class="ui-button ui-button-secondary"
                    >
                        Clear
                    </a>
                @endif
            </form>
        </section>

        <section class="ui-table-shell">
            @if ($companies->isEmpty())
                <div class="ui-empty">
                    <div>
                        <div class="ui-empty-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="M4 21V5l8-3 8 3v16"/>
                                <path d="M8 8h2M14 8h2M8 12h2M14 12h2"/>
                            </svg>
                        </div>

                        <h3>No companies found</h3>
                        <p>
                            Create the first company or adjust the current filters
                            to display matching organizations.
                        </p>

                        <a
                            href="{{ route('companies.create') }}"
                            class="ui-button ui-button-primary"
                        >
                            Add Company
                        </a>
                    </div>
                </div>
            @else
                <div class="ui-table-scroll">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Contact</th>
                                <th>Members</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($companies as $company)
                                <tr>
                                    <td>
                                        <div class="ui-company-cell">
                                            <div class="ui-company-avatar">
                                                {{ strtoupper(substr($company->code, 0, 2)) }}
                                            </div>

                                            <div class="ui-company-copy">
                                                <strong>{{ $company->name }}</strong>
                                                <span>{{ $company->code }} · {{ $company->slug }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="ui-table-primary">
                                            {{ $company->email ?: 'No email provided' }}
                                        </span>
                                        <span class="ui-table-secondary">
                                            {{ $company->phone ?: 'No phone provided' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="ui-table-primary">
                                            {{ number_format($company->active_memberships_count) }} active
                                        </span>
                                        <span class="ui-table-secondary">
                                            Company members
                                        </span>
                                    </td>

                                    <td>
                                        <span class="ui-status ui-status-{{ $company->status }}">
                                            {{ ucfirst($company->status) }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="ui-table-primary">
                                            {{ $company->created_at?->format('d M Y') }}
                                        </span>
                                        <span class="ui-table-secondary">
                                            {{ $company->creator?->name ?? 'System' }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="ui-actions">
                                            <a
                                                href="{{ route('companies.show', $company) }}"
                                                class="ui-icon-button"
                                                aria-label="View {{ $company->name }}"
                                                title="View company"
                                            >
                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/>
                                                    <circle cx="12" cy="12" r="2.5"/>
                                                </svg>
                                            </a>

                                            <a
                                                href="{{ route('companies.edit', $company) }}"
                                                class="ui-icon-button"
                                                aria-label="Edit {{ $company->name }}"
                                                title="Edit company"
                                            >
                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M4 20h4l11-11-4-4L4 16v4z"/>
                                                    <path d="M13.5 6.5l4 4"/>
                                                </svg>
                                            </a>

                                            <form
                                                method="POST"
                                                action="{{ route('companies.status', $company) }}"
                                            >
                                                @csrf
                                                @method('PATCH')

                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="{{ $company->isActive() ? 'inactive' : 'active' }}"
                                                >

                                                <button
                                                    type="submit"
                                                    class="ui-icon-button"
                                                    aria-label="{{ $company->isActive() ? 'Deactivate' : 'Activate' }} {{ $company->name }}"
                                                    title="{{ $company->isActive() ? 'Deactivate company' : 'Activate company' }}"
                                                    data-confirm="{{ $company->isActive() ? 'Deactivate this company?' : 'Activate this company?' }}"
                                                >
                                                    @if ($company->isActive())
                                                        <svg
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="1.8"
                                                            aria-hidden="true"
                                                        >
                                                            <circle cx="12" cy="12" r="8"/>
                                                            <path d="M8 12h8"/>
                                                        </svg>
                                                    @else
                                                        <svg
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="1.8"
                                                            aria-hidden="true"
                                                        >
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

                @include('partials.pagination', [
                    'paginator' => $companies,
                ])
            @endif
        </section>
    </div>
@endsection
