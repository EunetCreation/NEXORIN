@extends('layouts.app')

@section('title', 'Companies')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Companies')

@push('styles')
    <link href="{{ asset('css/nexorin-company-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-company-governance.js') }}?v=55a" defer></script>
@endpush

@section('content')
    @php
        $totalCompanies = max(1, (int) $stats['total']);
        $activeRate = min(100, (int) round(((int) $stats['active'] / $totalCompanies) * 100));
        $inactiveRate = min(100, (int) round(((int) $stats['inactive'] / $totalCompanies) * 100));
        $membershipRate = min(100, max(6, (int) $stats['memberships'] * 8));
        $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] ? 1 : 0);
    @endphp

    <div class="nxcg-directory" data-nxcg-directory>
        <header class="nxcg-directory-head" data-nxcg-reveal>
            <div class="nxcg-directory-copy">
                <span class="nxcg-kicker">Multi-company governance register</span>
                <h2>Company Administration</h2>
                <p>Control tenant identity, membership activity, workspace access, and lifecycle state from one compact administration register.</p>
            </div>

            <div class="nxcg-directory-actions">
                <span class="nxcg-engine-state"><i></i> Platform control online</span>
                <a
                    href="{{ route('companies.create') }}"
                    class="nxcg-button nxcg-button-primary"
                    data-nxcg-navigate="create"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Add Company
                </a>
            </div>
        </header>

        <section class="nxcg-stats" aria-label="Company administration summary" data-nxcg-reveal>
            <article class="nxcg-stat is-teal" data-nxcg-card>
                <span class="nxcg-stat-top">
                    <span class="nxcg-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 21V5l8-3 8 3v16"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/></svg></span>
                    <small>Portfolio</small>
                </span>
                <strong>{{ number_format($stats['total']) }}</strong>
                <span>Registered companies</span>
                <p>Tenant records governed by platform administration.</p>
                <i class="nxcg-stat-track"><b style="width:100%"></b></i>
            </article>

            <a
                href="{{ route('companies.index', ['status' => 'active']) }}"
                class="nxcg-stat is-green {{ $filters['status'] === 'active' ? 'is-selected' : '' }}"
                data-nxcg-card
                data-nxcg-navigate="directory"
            >
                <span class="nxcg-stat-top">
                    <span class="nxcg-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg></span>
                    <small>Workspace access</small>
                </span>
                <strong>{{ number_format($stats['active']) }}</strong>
                <span>Active companies</span>
                <p>{{ $activeRate }}% of registered tenants currently have active access.</p>
                <i class="nxcg-stat-track"><b style="width:{{ $activeRate }}%"></b></i>
            </a>

            <a
                href="{{ route('companies.index', ['status' => 'inactive']) }}"
                class="nxcg-stat is-orange {{ $filters['status'] === 'inactive' ? 'is-selected' : '' }}"
                data-nxcg-card
                data-nxcg-navigate="directory"
            >
                <span class="nxcg-stat-top">
                    <span class="nxcg-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M8 12h8"/></svg></span>
                    <small>Restricted access</small>
                </span>
                <strong>{{ number_format($stats['inactive']) }}</strong>
                <span>Inactive companies</span>
                <p>{{ $inactiveRate }}% are retained with workspace access disabled.</p>
                <i class="nxcg-stat-track"><b style="width:{{ max(4, $inactiveRate) }}%"></b></i>
            </a>

            <article class="nxcg-stat is-violet" data-nxcg-card>
                <span class="nxcg-stat-top">
                    <span class="nxcg-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6"/><path d="M15 15c3 0 5 1.5 5 4"/></svg></span>
                    <small>Membership control</small>
                </span>
                <strong>{{ number_format($stats['memberships']) }}</strong>
                <span>Active memberships</span>
                <p>Current user-to-company access relationships across the platform.</p>
                <i class="nxcg-stat-track"><b style="width:{{ $membershipRate }}%"></b></i>
            </article>
        </section>

        <section class="nxcg-registry" data-nxcg-reveal data-nxcg-card>
            <header class="nxcg-registry-head">
                <div>
                    <span class="nxcg-section-kicker">Tenant identity and access control</span>
                    <h3>Company Governance Register</h3>
                    <p>Review contact identity, active membership coverage, access state, and creation trace without leaving the administration context.</p>
                </div>
                <div class="nxcg-registry-summary">
                    @if ($activeFilters > 0)
                        <span>{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                    @endif
                    <b>{{ $companies->firstItem() ?? 0 }}–{{ $companies->lastItem() ?? 0 }} of {{ $companies->total() }}</b>
                </div>
            </header>

            <form
                method="GET"
                action="{{ route('companies.index') }}"
                class="nxcg-filter"
                data-nxcg-filter-form
                aria-label="Company registry filters"
            >
                <label class="nxcg-filter-field is-search">
                    <span>Search registry</span>
                    <span class="nxcg-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Company, code, email, or registration" autocomplete="off">
                    </span>
                </label>

                <label class="nxcg-filter-field">
                    <span>Access state</span>
                    <span class="nxcg-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg>
                        <select name="status" data-nxcg-auto-submit>
                            <option value="">All states</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </span>
                </label>

                <label class="nxcg-filter-field">
                    <span>Sort register</span>
                    <span class="nxcg-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 6h12M8 12h8M8 18h4"/><path d="M4 4v16m0 0-2-2m2 2 2-2"/></svg>
                        <select name="sort" data-nxcg-auto-submit>
                            <option value="created_at" @selected($filters['sort'] === 'created_at')>Created date</option>
                            <option value="name" @selected($filters['sort'] === 'name')>Company name</option>
                            <option value="code" @selected($filters['sort'] === 'code')>Company code</option>
                            <option value="status" @selected($filters['sort'] === 'status')>Access state</option>
                        </select>
                    </span>
                </label>

                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">

                <div class="nxcg-filter-actions">
                    <button type="submit" class="nxcg-button nxcg-button-primary">Apply</button>
                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.index') }}" class="nxcg-button nxcg-button-secondary" data-nxcg-navigate="directory">Clear</a>
                    @endif
                </div>
            </form>

            @if ($companies->isEmpty())
                <div class="nxcg-empty">
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 21V5l8-3 8 3v16"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2"/></svg></span>
                    <div>
                        <h3>No matching companies</h3>
                        <p>Adjust the active filters or create a company to establish a new isolated NEXORIN workspace.</p>
                    </div>
                    <a href="{{ route('companies.create') }}" class="nxcg-button nxcg-button-primary" data-nxcg-navigate="create">Add Company</a>
                </div>
            @else
                <div class="nxcg-registry-columns" aria-hidden="true">
                    <span>Company identity</span>
                    <span>Contact</span>
                    <span>Membership</span>
                    <span>Workspace access</span>
                    <span>Creation trace</span>
                    <span>Action</span>
                </div>

                <div class="nxcg-company-list">
                    @foreach ($companies as $company)
                        <article
                            class="nxcg-company-row {{ $company->isActive() ? 'is-active' : 'is-inactive' }}"
                            tabindex="0"
                            data-nxcg-row
                            data-nxcg-card
                        >
                            <div class="nxcg-company-identity">
                                <span class="nxcg-company-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V5l8-3 8 3v16"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/></svg></span>
                                <span>
                                    <strong>{{ $company->name }}</strong>
                                    <small class="nxcg-mono">{{ $company->code }}</small>
                                    <em>{{ $company->slug }}</em>
                                </span>
                            </div>

                            <div class="nxcg-row-cell">
                                <span class="nxcg-row-label">Contact</span>
                                <strong>{{ $company->email ?: 'Email not provided' }}</strong>
                                <small>{{ $company->formatted_phone ?: 'Phone not provided' }}</small>
                            </div>

                            <div class="nxcg-row-cell">
                                <span class="nxcg-row-label">Membership</span>
                                <strong class="nxcg-mono">{{ number_format($company->active_memberships_count) }} active</strong>
                                <small>Members with workspace access</small>
                            </div>

                            <div class="nxcg-row-cell">
                                <span class="nxcg-row-label">Workspace access</span>
                                <span class="nxcg-state is-{{ $company->status }}"><i></i>{{ ucfirst($company->status) }}</span>
                                <small>{{ $company->isActive() ? 'Workspace access available' : 'Workspace access restricted' }}</small>
                            </div>

                            <div class="nxcg-row-cell">
                                <span class="nxcg-row-label">Creation trace</span>
                                <strong class="nxcg-mono">{{ $company->created_at?->timezone('Asia/Jakarta')->format('d M Y') }}</strong>
                                <small>Created by {{ $company->creator?->name ?? 'System provisioned' }}</small>
                            </div>

                            <div class="nxcg-row-actions">
                                <a
                                    href="{{ route('companies.show', $company) }}"
                                    class="nxcg-open"
                                    data-nxcg-navigate="open"
                                    data-nxcg-company="{{ $company->name }}"
                                    data-nxcg-company-code="{{ $company->code }}"
                                >
                                    Open
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                                </a>

                                <details class="nxcg-action-menu">
                                    <summary aria-label="Manage {{ $company->name }}" title="Company actions"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg></summary>
                                    <div>
                                        <a href="{{ route('companies.edit', $company) }}" data-nxcg-navigate="edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20h4l11-11-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/></svg>Edit company</a>
                                        <form
                                            method="POST"
                                            action="{{ route('companies.status', $company) }}"
                                            data-nxcg-status-form
                                            data-confirm="{{ $company->isActive() ? 'Deactivate this company? Company members will lose workspace access until the company is reactivated.' : 'Activate this company? Company members with active memberships will regain workspace access.' }}"
                                            data-confirm-tone="{{ $company->isActive() ? 'danger' : 'default' }}"
                                            data-confirm-title="{{ $company->isActive() ? 'Restrict company workspace access?' : 'Restore company workspace access?' }}"
                                            data-confirm-label="{{ $company->isActive() ? 'Deactivate company' : 'Activate company' }}"
                                            data-confirm-control="Tenant access transition"
                                            data-confirm-result="{{ $company->isActive() ? 'Workspace access will be disabled' : 'Workspace access will be restored' }}"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $company->isActive() ? 'inactive' : 'active' }}">
                                            <button type="submit" class="{{ $company->isActive() ? 'is-danger' : '' }}">
                                                @if ($company->isActive())
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M8 12h8"/></svg>Deactivate access
                                                @else
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 12 4 4L19 6"/></svg>Activate access
                                                @endif
                                            </button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        </article>
                    @endforeach
                </div>

                <footer class="nxcg-registry-footer">
                    @include('partials.pagination', ['paginator' => $companies])
                </footer>
            @endif
        </section>
    </div>
@endsection
