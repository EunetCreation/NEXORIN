@extends('layouts.app')

@section('title', $customer->name)
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Customer Details')

@php
    $currentUser = auth()->user();
    $canUpdate = $currentUser->hasCompanyPermission($company, 'customer.update');
    $canArchive = $currentUser->hasCompanyPermission($company, 'customer.delete');
    $canCreateProject = $currentUser->hasAnyCompanyPermission($company, ['project.create', 'project.create_own']);
@endphp

@section('content')
    <div class="ui-page">
        <section class="ui-detail-hero">
            <div class="ui-detail-avatar">
                {{ strtoupper(substr($customer->name, 0, 2)) }}
            </div>

            <div class="ui-detail-copy">
                <h2>{{ $customer->name }}</h2>
                <p>
                    {{ $customer->customer_number }} · {{ $customer->type_label }} customer · {{ $company->name }}
                </p>
            </div>

            <div class="ui-detail-actions">
                <a href="{{ route('companies.customers.index', $company) }}" class="ui-button ui-button-secondary">Customer Directory</a>

                @if ($canCreateProject && $customer->isActive())
                    <a
                        href="{{ route('companies.projects.create', ['company' => $company, 'customer_id' => $customer->id]) }}"
                        class="ui-button ui-button-secondary"
                    >
                        New Project
                    </a>
                @endif

                @if ($canUpdate && $customer->isActive())
                    <a href="{{ route('companies.customers.edit', [$company, $customer]) }}" class="ui-button ui-button-primary">Edit Customer</a>
                @endif
            </div>
        </section>

        <div class="ui-detail-grid">
            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Customer Profile</h3>
                        <p class="ui-card-description">Contact, classification, and location details.</p>
                    </div>
                    <span class="ui-status ui-status-{{ $customer->status === 'active' ? 'active' : 'inactive' }}">
                        {{ ucfirst($customer->status) }}
                    </span>
                </div>

                <div class="ui-card-body">
                    <dl class="ui-info-grid">
                        <div class="ui-info-item">
                            <dt>Customer Number</dt>
                            <dd>{{ $customer->customer_number }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Customer Type</dt>
                            <dd>{{ $customer->type_label }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Email</dt>
                            <dd>{{ $customer->email ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Phone</dt>
                            <dd>{{ $customer->formatted_phone ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>City</dt>
                            <dd>{{ $customer->city ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item">
                            <dt>Province</dt>
                            <dd>{{ $customer->province ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item ui-info-span-2">
                            <dt>Address</dt>
                            <dd>{{ $customer->address ?: 'Not provided' }}</dd>
                        </div>
                        <div class="ui-info-item ui-info-span-2">
                            <dt>Internal Notes</dt>
                            <dd>{{ $customer->notes ?: 'No internal notes.' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <div class="ui-stack">
                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Portal Access</h3>
                            <p class="ui-card-description">Customer ownership for restricted project access.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        @if ($customer->portalUser)
                            <div class="ui-member-item">
                                <span class="ui-member-avatar">{{ strtoupper(substr($customer->portalUser->name, 0, 2)) }}</span>
                                <span class="ui-member-copy">
                                    <strong>{{ $customer->portalUser->name }}</strong>
                                    <span>{{ $customer->portalUser->email }}</span>
                                </span>
                                <span class="ui-status ui-status-{{ $customer->portalUser->status }}">{{ ucfirst($customer->portalUser->status) }}</span>
                            </div>
                        @else
                            <div class="ui-compact-empty">No customer portal account is linked.</div>
                        @endif
                    </div>
                </section>

                <section class="ui-card">
                    <div class="ui-card-header">
                        <div>
                            <h3 class="ui-card-title">Record Audit</h3>
                            <p class="ui-card-description">Basic creation and update traceability.</p>
                        </div>
                    </div>
                    <div class="ui-card-body">
                        <dl class="ui-info-grid">
                            <div class="ui-info-item">
                                <dt>Created By</dt>
                                <dd>{{ $customer->creator?->name ?? 'System' }}</dd>
                            </div>
                            <div class="ui-info-item">
                                <dt>Created At</dt>
                                <dd>{{ $customer->created_at?->format('d M Y, H:i') }}</dd>
                            </div>
                            <div class="ui-info-item">
                                <dt>Last Updated By</dt>
                                <dd>{{ $customer->updater?->name ?? 'System' }}</dd>
                            </div>
                            <div class="ui-info-item">
                                <dt>Last Updated</dt>
                                <dd>{{ $customer->updated_at?->format('d M Y, H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </div>
        </div>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Recent Projects</h3>
                    <p class="ui-card-description">Latest project records linked to this customer.</p>
                </div>
                <a href="{{ route('companies.projects.index', ['company' => $company, 'search' => $customer->customer_number]) }}" class="ui-button ui-button-secondary ui-button-compact">View All Projects</a>
            </div>

            <div class="ui-card-body">
                @if ($customer->projects->isEmpty())
                    <div class="ui-compact-empty">No projects have been created for this customer.</div>
                @else
                    <div class="ui-record-list">
                        @foreach ($customer->projects as $project)
                            <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="ui-record-item">
                                <span class="ui-record-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M4 19V9l8-5 8 5v10"/>
                                        <path d="M8 19v-6h8v6M3 19h18"/>
                                    </svg>
                                </span>
                                <span class="ui-record-copy">
                                    <strong>{{ $project->name }}</strong>
                                    <span>{{ $project->project_number }} · {{ $project->proposed_system_label }}</span>
                                </span>
                                <span class="ui-status ui-status-project-{{ $project->status }}">{{ $project->status_label }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        @if ($canArchive && $customer->isActive())
            <section class="ui-card ui-danger-zone">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Archive Customer</h3>
                        <p class="ui-card-description">Customers with open projects cannot be archived.</p>
                    </div>
                    <form method="POST" action="{{ route('companies.customers.archive', [$company, $customer]) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="ui-button ui-button-danger"
                            data-confirm="Archive this customer? The customer will no longer be available for new projects."
                        >
                            Archive Customer
                        </button>
                    </form>
                </div>
                <div class="ui-card-body">
                    <p class="ui-muted-copy">
                        This record currently has {{ number_format($customer->current_projects_count) }} non-archived project(s).
                        Complete, cancel, or archive open work before archiving the customer.
                    </p>
                </div>
            </section>
        @endif
    </div>
@endsection
