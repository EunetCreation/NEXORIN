@extends('layouts.app')

@section('title', 'Customers')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Customers')

@php
    $currentUser = auth()->user();
    $canCreate = $currentUser->hasCompanyPermission($company, 'customer.create');
    $canUpdate = $currentUser->hasCompanyPermission($company, 'customer.update');
@endphp

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">
                    Customer relationship records
                </div>

                <h2 class="ui-page-title">
                    Customer Directory
                </h2>

                <p class="ui-page-description">
                    Manage residential and commercial customers for {{ $company->name }}.
                    Every project remains isolated inside this company workspace.
                </p>
            </div>

            <div class="ui-page-actions">
                <a
                    href="{{ route('companies.projects.index', $company) }}"
                    class="ui-button ui-button-secondary"
                >
                    View Projects
                </a>

                @if ($canCreate)
                    <a
                        href="{{ route('companies.customers.create', $company) }}"
                        class="ui-button ui-button-primary"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>

                        Add Customer
                    </a>
                @endif
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="Customer statistics">
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
                <span class="ui-stat-label">Total Customers</span>
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
                <span class="ui-stat-label">Active Customers</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 19V9l8-5 8 5v10"/>
                            <path d="M8 19v-6h8v6M3 19h18"/>
                        </svg>
                    </span>
                </div>
                <strong class="ui-stat-value">{{ number_format($stats['residential']) }}</strong>
                <span class="ui-stat-label">Residential</span>
            </article>

            <article class="ui-stat-card">
                <div class="ui-stat-head">
                    <span class="ui-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 21V5l8-3 8 3v16"/>
                            <path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/>
                        </svg>
                    </span>
                </div>
                <strong class="ui-stat-value">{{ number_format($stats['commercial']) }}</strong>
                <span class="ui-stat-label">Commercial</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="Customer filters">
            <form
                method="GET"
                action="{{ route('companies.customers.index', $company) }}"
                class="ui-filter-form"
            >
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="customer-search">Search customers</label>
                    <div class="ui-input-wrap">
                        <svg class="ui-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-4-4"/>
                        </svg>
                        <input
                            id="customer-search"
                            class="ui-input ui-input-with-icon"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Name, customer number, email, phone, or city"
                        >
                    </div>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="customer-type">Type</label>
                    <select id="customer-type" class="ui-select" name="type">
                        <option value="">All types</option>
                        <option value="residential" @selected($filters['type'] === 'residential')>Residential</option>
                        <option value="commercial" @selected($filters['type'] === 'commercial')>Commercial</option>
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="customer-status">Status</label>
                    <select id="customer-status" class="ui-select" name="status">
                        <option value="">All statuses</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="customer-sort">Sort by</label>
                    <select id="customer-sort" class="ui-select" name="sort">
                        <option value="created_at" @selected($filters['sort'] === 'created_at')>Created date</option>
                        <option value="name" @selected($filters['sort'] === 'name')>Customer name</option>
                        <option value="customer_number" @selected($filters['sort'] === 'customer_number')>Customer number</option>
                        <option value="type" @selected($filters['sort'] === 'type')>Customer type</option>
                        <option value="status" @selected($filters['sort'] === 'status')>Status</option>
                    </select>
                </div>

                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['status'] || $filters['type'])
                    <a href="{{ route('companies.customers.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
                @endif
            </form>
        </section>

        <section class="ui-table-shell">
            @if ($customers->isEmpty())
                <div class="ui-empty">
                    <div>
                        <div class="ui-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="9" cy="8" r="3"/>
                                <path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/>
                                <path d="M17 8v6M14 11h6"/>
                            </svg>
                        </div>
                        <h3>No customers found</h3>
                        <p>Create the first customer or adjust the current filters.</p>
                        @if ($canCreate)
                            <a href="{{ route('companies.customers.create', $company) }}" class="ui-button ui-button-primary">Add Customer</a>
                        @endif
                    </div>
                </div>
            @else
                <div class="ui-table-scroll">
                    <table class="ui-table ui-customer-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Contact</th>
                                <th>Projects</th>
                                <th>Portal Account</th>
                                <th>Status</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $customer)
                                <tr>
                                    <td>
                                        <div class="ui-company-cell">
                                            <span class="ui-user-avatar">
                                                {{ strtoupper(substr($customer->name, 0, 2)) }}
                                            </span>
                                            <span class="ui-company-copy">
                                                <strong>{{ $customer->name }}</strong>
                                                <span>{{ $customer->customer_number ?: 'Number pending' }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">{{ $customer->type_label }}</span>
                                        <span class="ui-table-secondary">{{ $customer->city ?: 'City not set' }}</span>
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">{{ $customer->email ?: 'No email' }}</span>
                                        <span class="ui-table-secondary">{{ $customer->formatted_phone ?: 'No phone' }}</span>
                                    </td>
                                    <td>
                                        <span class="ui-table-primary">{{ number_format($customer->projects_count) }} total</span>
                                        <span class="ui-table-secondary">{{ number_format($customer->active_projects_count) }} open</span>
                                    </td>
                                    <td>
                                        @if ($customer->portalUser)
                                            <span class="ui-table-primary">{{ $customer->portalUser->name }}</span>
                                            <span class="ui-table-secondary">{{ $customer->portalUser->email }}</span>
                                        @else
                                            <span class="ui-table-secondary">Not linked</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="ui-status ui-status-{{ $customer->status === 'active' ? 'active' : 'inactive' }}">
                                            {{ ucfirst($customer->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="ui-actions">
                                            <a
                                                href="{{ route('companies.customers.show', [$company, $customer]) }}"
                                                class="ui-icon-button"
                                                aria-label="View {{ $customer->name }}"
                                                title="View customer"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/>
                                                    <circle cx="12" cy="12" r="2.5"/>
                                                </svg>
                                            </a>
                                            @if ($canUpdate && $customer->isActive())
                                                <a
                                                    href="{{ route('companies.customers.edit', [$company, $customer]) }}"
                                                    class="ui-icon-button"
                                                    aria-label="Edit {{ $customer->name }}"
                                                    title="Edit customer"
                                                >
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                        <path d="M4 20h4l11-11-4-4L4 16v4z"/>
                                                        <path d="M13.5 6.5l4 4"/>
                                                    </svg>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.pagination', ['paginator' => $customers])
            @endif
        </section>
    </div>
@endsection
