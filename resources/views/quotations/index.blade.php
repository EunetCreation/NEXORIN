@extends('layouts.app')

@section('title', 'Quotations')
@section('page-eyebrow', 'Business output')
@section('page-heading', 'Quotations')

@php
    $money = function ($value): string {
        if ($value === null) {
            return '—';
        }

        $amount = (float) $value;
        $sign = $amount < 0 ? '−' : '';

        return 'IDR '.$sign.number_format(abs($amount), 0, '.', ',');
    };
    $portfolioRate = $stats['projects'] > 0 ? 100 : 0;
    $readyRate = $stats['projects'] > 0 ? min(100, round(($stats['ready'] / $stats['projects']) * 100)) : 0;
    $draftRate = $stats['projects'] > 0 ? min(100, round(($stats['draft'] / $stats['projects']) * 100)) : 0;
    $issuedRate = $stats['projects'] > 0 ? min(100, round(($stats['issued'] / $stats['projects']) * 100)) : 0;
    $activeFilters = ($filters['search'] !== '' ? 1 : 0) + ($filters['status'] !== 'all' ? 1 : 0);
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-quotation.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-quotation.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxq-directory" data-nxq-directory>
        <header class="nxq-directory-hero" data-nxq-reveal>
            <div class="nxq-directory-copy">
                <span class="nxq-kicker">Controlled customer release registry</span>
                <h2>Quotations</h2>
                <p>
                    Convert approved engineering, priced commercial scope, and reviewed financial output into revision-controlled customer proposals. Internal cost, margin, and supplier trace remain protected.
                </p>
            </div>

            <div class="nxq-directory-context" aria-label="Quotation registry context">
                <span class="nxq-context-chip"><i></i> Customer release desk online</span>
                <a
                    href="{{ route('companies.financial-analyses.index', $company) }}"
                    class="nxq-context-link"
                    data-nxq-navigate="financial"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg>
                    Financial Analysis
                </a>
            </div>
        </header>

        <section class="nxq-stat-grid" aria-label="Quotation portfolio statistics">
            <a
                href="{{ route('companies.quotations.index', $company) }}"
                class="nxq-stat-card {{ $filters['status'] === 'all' ? 'is-selected' : '' }}"
                data-nxq-card
                data-nxq-reveal
                data-nxq-navigate="directory"
            >
                <span class="nxq-stat-topline">
                    <span class="nxq-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V8l8-5 8 5v11"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg></span>
                    <span class="nxq-stat-state">Portfolio</span>
                </span>
                <span class="nxq-stat-body">
                    <strong class="nxq-stat-value">{{ number_format($stats['projects']) }}</strong>
                    <span>Available projects</span>
                    <small>Current customer projects visible to your role and company access.</small>
                </span>
                <span class="nxq-stat-progress"><span style="width:{{ $portfolioRate }}%"></span></span>
            </a>

            <article class="nxq-stat-card is-orange" data-nxq-card data-nxq-reveal>
                <span class="nxq-stat-topline">
                    <span class="nxq-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/><path d="m15 16 2 2 4-5"/></svg></span>
                    <span class="nxq-stat-state">Release basis</span>
                </span>
                <span class="nxq-stat-body">
                    <strong class="nxq-stat-value">{{ number_format($stats['ready']) }}</strong>
                    <span>Ready to generate</span>
                    <small>{{ $readyRate }}% have a priced BOQ and calculated financial basis.</small>
                </span>
                <span class="nxq-stat-progress"><span style="width:{{ $readyRate }}%"></span></span>
            </article>

            <a
                href="{{ route('companies.quotations.index', ['company' => $company, 'status' => 'draft']) }}"
                class="nxq-stat-card is-blue {{ $filters['status'] === 'draft' ? 'is-selected' : '' }}"
                data-nxq-card
                data-nxq-reveal
                data-nxq-navigate="directory"
            >
                <span class="nxq-stat-topline">
                    <span class="nxq-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                    <span class="nxq-stat-state">Working revision</span>
                </span>
                <span class="nxq-stat-body">
                    <strong class="nxq-stat-value">{{ number_format($stats['draft']) }}</strong>
                    <span>Draft quotations</span>
                    <small>{{ $draftRate }}% are being prepared before client approval and issue.</small>
                </span>
                <span class="nxq-stat-progress"><span style="width:{{ $draftRate }}%"></span></span>
            </a>

            <article
                class="nxq-stat-card is-violet {{ in_array($filters['status'], ['sent', 'issued'], true) ? 'is-selected' : '' }}"
                data-nxq-card
                data-nxq-reveal
            >
                <span class="nxq-stat-topline">
                    <span class="nxq-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>
                    <span class="nxq-stat-state">Customer milestone</span>
                </span>
                <span class="nxq-stat-body">
                    <strong class="nxq-stat-value">{{ number_format($stats['issued']) }}</strong>
                    <span>Issued or sent</span>
                    <small>{{ $issuedRate }}% have entered a controlled customer delivery state.</small>
                </span>
                <span class="nxq-stat-progress"><span style="width:{{ $issuedRate }}%"></span></span>
            </article>
        </section>

        <section class="nxq-registry" data-nxq-reveal data-nxq-card>
            <header class="nxq-registry-header">
                <div>
                    <span class="nxq-registry-kicker">Revision and release portfolio</span>
                    <h3>Customer proposal register</h3>
                    <p>Review generation readiness, controlled revision state, customer total, validity, and the latest quotation milestone without exposing internal commercial data.</p>
                </div>
                <div class="nxq-registry-summary">
                    @if ($activeFilters > 0)
                        <span class="nxq-filter-count">{{ $activeFilters }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilters) }}</span>
                    @endif
                    <span class="nxq-result-range">{{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }}</span>
                </div>
            </header>

            <form
                class="nxq-filter-toolbar"
                method="GET"
                action="{{ route('companies.quotations.index', $company) }}"
                data-nxq-filter-form
                aria-label="Quotation registry filters"
            >
                <div class="nxq-filter-field is-search">
                    <label for="quotation-search">Search registry</label>
                    <div class="nxq-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="quotation-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Project, customer, city, or number" autocomplete="off">
                        @if ($filters['search'] !== '')
                            <a
                                href="{{ route('companies.quotations.index', ['company' => $company, 'status' => $filters['status'] !== 'all' ? $filters['status'] : null]) }}"
                                class="nxq-filter-clear"
                                aria-label="Clear search"
                                data-nxq-navigate="directory"
                            >×</a>
                        @endif
                    </div>
                </div>

                <div class="nxq-filter-field">
                    <label for="quotation-status">Lifecycle state</label>
                    <div class="nxq-filter-control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                        <select id="quotation-status" name="status" data-nxq-auto-submit>
                            <option value="all" @selected($filters['status'] === 'all')>All projects</option>
                            <option value="not_generated" @selected($filters['status'] === 'not_generated')>Not generated</option>
                            <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                            <option value="internal_review" @selected($filters['status'] === 'internal_review')>Internal review</option>
                            <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                            <option value="sent" @selected($filters['status'] === 'sent')>Sent</option>
                            <option value="issued" @selected($filters['status'] === 'issued')>Legacy issued</option>
                            <option value="accepted" @selected($filters['status'] === 'accepted')>Accepted</option>
                            <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                            <option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option>
                            <option value="expired" @selected($filters['status'] === 'expired')>Expired</option>
                        </select>
                    </div>
                </div>

                <div class="nxq-filter-actions">
                    <button class="nxq-button nxq-button-primary" type="submit">Apply filters</button>
                    @if ($activeFilters > 0)
                        <a href="{{ route('companies.quotations.index', $company) }}" class="nxq-button nxq-button-secondary" data-nxq-navigate="directory">Clear</a>
                    @endif
                </div>
            </form>

            @if ($projects->isEmpty())
                <div class="nxq-empty">
                    <span class="nxq-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                    <div>
                        <h3>No matching quotation records</h3>
                        <p>Adjust the active filters or complete the priced BOQ and Financial Analysis needed to prepare a customer quotation.</p>
                    </div>
                </div>
            @else
                <div class="nxq-registry-columns" aria-hidden="true">
                    <span>Project and customer</span>
                    <span>Commercial readiness</span>
                    <span>Quotation revision</span>
                    <span>Customer investment</span>
                    <span>Latest controlled event</span>
                    <span>Action</span>
                </div>

                <div class="nxq-registry-list">
                    @foreach ($projects as $project)
                        @php
                            $quotation = $project->quotation;
                            $bomReady = $project->billOfMaterial?->pricing_status === 'priced' && (float) ($project->billOfMaterial?->grand_price_idr ?? 0) > 0;
                            $financialReady = $project->financialAnalysis?->status === 'calculated';
                            $ready = $bomReady && $financialReady;
                            $isExpired = $quotation?->shouldBeConsideredExpired() ?? false;
                            $lifecycle = $quotation ? ($isExpired ? 'expired' : $quotation->status) : 'not_generated';
                            $lifecycleLabel = $quotation ? ($isExpired ? 'Expired' : $quotation->status_label) : 'Not generated';
                            $hasHistoricalRelease = (bool) ($quotation?->issued_at || in_array($lifecycle, ['accepted', 'rejected', 'cancelled', 'expired'], true));
                            $commercialStateLabel = $ready ? 'Current sources ready' : ($hasHistoricalRelease ? 'Historical release' : 'Source required');
                            $commercialStateMeta = $ready
                                ? 'Priced BOQ · Financial calculated'
                                : ($hasHistoricalRelease
                                    ? 'Current BOQ or financial source requires refresh'
                                    : (($bomReady ? 'Priced BOQ' : 'BOQ pending').' · '.($financialReady ? 'Financial calculated' : 'Financial pending')));
                            $revisionDisplay = $quotation
                                ? (((int) $quotation->revision_number === 0)
                                    ? $quotation->quote_number.' · Initial issue'
                                    : $quotation->version_display)
                                : 'No controlled revision';
                            $total = $quotation
                                ? ($quotation->commercial_summary['grand_total_idr'] ?? 0)
                                : ($project->billOfMaterial?->grand_price_idr ?: null);
                            $latestLabel = 'Awaiting quotation generation';
                            $latestAt = null;

                            if ($quotation?->accepted_at) {
                                $latestLabel = 'Customer accepted';
                                $latestAt = $quotation->accepted_at;
                            } elseif ($quotation?->rejected_at) {
                                $latestLabel = 'Customer rejected';
                                $latestAt = $quotation->rejected_at;
                            } elseif ($quotation?->cancelled_at) {
                                $latestLabel = 'Quotation cancelled';
                                $latestAt = $quotation->cancelled_at;
                            } elseif ($isExpired && $quotation?->valid_until) {
                                $latestLabel = 'Validity expired';
                                $latestAt = $quotation->valid_until;
                            } elseif ($quotation?->issued_at) {
                                $latestLabel = 'Sent to customer';
                                $latestAt = $quotation->issued_at;
                            } elseif ($quotation?->client_proposal_pdf_generated_at) {
                                $latestLabel = 'Client PDF generated';
                                $latestAt = $quotation->client_proposal_pdf_generated_at;
                            } elseif ($quotation?->approved_at) {
                                $latestLabel = 'Snapshot approved';
                                $latestAt = $quotation->approved_at;
                            } elseif ($quotation) {
                                $latestLabel = 'Working revision generated';
                                $latestAt = $quotation->updated_at;
                            }
                        @endphp

                        <article
                            class="nxq-registry-row is-{{ str_replace('_', '-', $lifecycle) }}"
                            tabindex="0"
                            data-nxq-row
                            data-nxq-project="{{ $project->name }}"
                            data-nxq-project-number="{{ $project->project_number }}"
                        >
                            <div class="nxq-project-cell">
                                <span class="nxq-project-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8l8-5 8 5v11"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg></span>
                                <span class="nxq-project-copy">
                                    <strong>{{ $project->name }}</strong>
                                    <span>{{ $project->project_number }}</span>
                                    <small>{{ $project->customer?->name ?? 'Customer unavailable' }}{{ $project->city ? ' · '.$project->city : '' }}</small>
                                </span>
                            </div>

                            <div class="nxq-state-cell">
                                <span class="nxq-cell-kicker">Commercial readiness</span>
                                <strong class="nxq-state-title {{ $ready ? 'is-ready' : ($hasHistoricalRelease ? 'is-warning' : 'is-blocked') }}">{{ $commercialStateLabel }}</strong>
                                <span class="nxq-state-meta">{{ $commercialStateMeta }}</span>
                            </div>

                            <div class="nxq-state-cell">
                                <span class="nxq-cell-kicker">Quotation revision</span>
                                <span class="nxq-status nxq-status--{{ str_replace('_', '-', $lifecycle) }}"><i></i>{{ $lifecycleLabel }}</span>
                                <span class="nxq-state-meta">{{ $revisionDisplay }}</span>
                            </div>

                            <div class="nxq-total-cell">
                                <span class="nxq-cell-kicker">Customer investment</span>
                                <strong>{{ $money($total) }}</strong>
                                <span>{{ $quotation?->valid_until ? 'Valid until '.$quotation->valid_until->format('d M Y') : 'Validity not established' }}</span>
                            </div>

                            <div class="nxq-milestone-cell">
                                <span class="nxq-cell-kicker">Latest controlled event</span>
                                <strong>{{ $latestLabel }}</strong>
                                <span>{{ $latestAt?->format('d M Y · H:i') ?? 'No milestone recorded' }}</span>
                            </div>

                            <div class="nxq-row-action">
                                <a
                                    href="{{ route('companies.quotations.show', [$company, $project]) }}"
                                    class="nxq-open-button"
                                    data-nxq-navigate="open"
                                    data-nxq-project="{{ $project->name }}"
                                    data-nxq-project-number="{{ $project->project_number }}"
                                >
                                    Open
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <footer class="nxq-registry-footer">
                    @include('partials.pagination', ['paginator' => $projects])
                </footer>
            @endif
        </section>
    </div>
@endsection
