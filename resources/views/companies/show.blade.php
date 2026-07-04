@extends('layouts.app')

@section('title', $company->name)
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Company Profile')

@section('content')
    <div class="ui-page">
        <section class="ui-detail-hero">
            <div class="ui-detail-avatar">
                {{ strtoupper(substr($company->code, 0, 2)) }}
            </div>

            <div class="ui-detail-copy">
                <h2>{{ $company->name }}</h2>

                <p>
                    {{ $company->code }} · {{ $company->slug }} ·
                    Created
                    {{ $company->created_at?->timezone('Asia/Jakarta')->format('d M Y') }}
                </p>
            </div>

            <div class="ui-detail-actions">
                <span class="ui-status ui-status-{{ $company->status }}">
                    {{ ucfirst($company->status) }}
                </span>

                @if ($company->isActive())
                    <a
                        href="{{ route('companies.customers.index', $company) }}"
                        class="ui-button ui-button-secondary"
                    >
                        Customers ({{ number_format($company->customers_count) }})
                    </a>

                    <a
                        href="{{ route('companies.projects.index', $company) }}"
                        class="ui-button ui-button-secondary"
                    >
                        Projects ({{ number_format($company->projects_count) }})
                    </a>
                @endif

                <a
                    href="{{ route('companies.edit', $company) }}"
                    class="ui-button ui-button-secondary"
                >
                    Edit Company
                </a>
            </div>
        </section>

        <div class="ui-detail-grid">
            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">
                            Company Details
                        </h3>

                        <p class="ui-card-description">
                            Identity, contact information, and workspace metadata.
                        </p>
                    </div>
                </div>

                <div class="ui-card-body">
                    <dl class="ui-info-grid">
                        <div class="ui-info-item">
                            <dt>Company code</dt>
                            <dd>{{ $company->code }}</dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Registration number</dt>
                            <dd>
                                {{ $company->registration_number ?: 'Not provided' }}
                            </dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Email</dt>
                            <dd>
                                {{ $company->email ?: 'Not provided' }}
                            </dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Phone</dt>
                            <dd>
                                {{ $company->formatted_phone ?: 'Not provided' }}
                            </dd>
                        </div>

                        <div class="ui-info-item ui-form-span-2">
                            <dt>Address</dt>
                            <dd>
                                {{ $company->address ?: 'Not provided' }}
                            </dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Created by</dt>
                            <dd>
                                {{ $company->creator?->name ?? 'System' }}
                            </dd>
                        </div>

                        <div class="ui-info-item">
                            <dt>Created date</dt>
                            <dd>
                                {{ $company->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">
                            Company Members
                        </h3>

                        <p class="ui-card-description">
                            {{ number_format($company->active_memberships_count) }}
                            active of
                            {{ number_format($company->memberships_count) }}
                            total memberships.
                        </p>
                    </div>
                </div>

                <div class="ui-card-body">
                    @if ($company->memberships->isEmpty())
                        <div
                            class="ui-empty"
                            style="min-height: 220px; padding: 20px;"
                        >
                            <div>
                                <div class="ui-empty-icon">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        aria-hidden="true"
                                    >
                                        <circle cx="9" cy="8" r="3"/>
                                        <path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/>
                                    </svg>
                                </div>

                                <h3>No members assigned</h3>

                                <p>
                                    Membership management will be available
                                    in the Users module.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="ui-member-list">
                            @foreach ($company->memberships->take(8) as $membership)
                                <div class="ui-member-item">
                                    <div class="ui-member-avatar">
                                        {{ strtoupper(substr($membership->user?->name ?? 'U', 0, 1)) }}
                                    </div>

                                    <div class="ui-member-copy">
                                        <strong>
                                            {{ $membership->user?->name ?? 'Unknown User' }}
                                        </strong>

                                        <span>
                                            {{ $membership->user?->email ?? 'No email available' }}
                                        </span>
                                    </div>

                                    <div class="ui-role-tags">
                                        @forelse ($membership->roles as $role)
                                            <span class="ui-role-tag">
                                                {{ $role->name }}
                                            </span>
                                        @empty
                                            <span class="ui-role-tag">
                                                Member
                                            </span>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <section class="ui-card ui-danger-zone">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">
                        Archive Company
                    </h3>

                    <p class="ui-card-description">
                        Archiving removes the company from active administration
                        views while preserving its database records.
                        The company must be inactive first.
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('companies.destroy', $company) }}"
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="ui-button ui-button-danger"
                        @disabled($company->isActive())
                        data-confirm="Archive this company? This action will hide it from active administration views."
                    >
                        Archive Company
                    </button>
                </form>
            </div>

            @if ($company->isActive())
                <div
                    class="ui-card-body"
                    style="padding-top: 14px;"
                >
                    <div class="ui-help">
                        Deactivate the company before using the archive action.
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection