@extends('layouts.app')

@section('title', 'Suppliers')
@section('page-eyebrow', 'Procurement governance')
@section('page-heading', 'Suppliers')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=58b" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-directory nxpg-page">
        <section class="nxmc-directory-hero nxmc-reveal">
            <div class="nxmc-directory-copy">
                <span class="nxmc-kicker">Supplier intelligence</span>
                <h2>Approved vendor records for controlled procurement.</h2>
                <p>Build a governed supplier network that feeds price books, catalog availability, procurement lead times, warranty traceability, and future BOQ commercial validation without losing audit clarity.</p>
            </div>

            <div class="nxmc-directory-context">
                <span class="nxmc-context-chip"><i aria-hidden="true"></i>Procurement chain active</span>
                @if (auth()->user()->hasCompanyPermission($company, 'equipment.view'))
                    <a href="{{ route('companies.materials.index', $company) }}" class="nxmc-context-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                        Material Catalog
                    </a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'price.cost.view'))
                    <a href="{{ route('companies.price-books.index', $company) }}" class="nxmc-context-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                        Price Books
                    </a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'supplier.create'))
                    <a href="{{ route('companies.suppliers.create', $company) }}" class="nxmc-button nxmc-button-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Add Supplier
                    </a>
                @endif
            </div>
        </section>

        <section class="nxmc-stat-grid nxmc-reveal" aria-label="Supplier governance statistics">
            <article class="nxmc-stat-card">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V8l8-5 8 5v12"/><path d="M8 20v-6h8v6M7 10h10"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['total']) }}</strong>
                <span class="nxmc-stat-label">Registered suppliers</span>
                <span class="nxmc-stat-copy">Commercial entities available to feed company pricing and procurement records.</span>
            </article>

            <article class="nxmc-stat-card is-orange">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['active']) }}</strong>
                <span class="nxmc-stat-label">Approved for intake</span>
                <span class="nxmc-stat-copy">Suppliers currently active for price books, quote references, and controlled sourcing.</span>
            </article>

            <article class="nxmc-stat-card is-blue">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h10M4 17h7"/><path d="M18 14v6M15 17h6"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['inactive']) }}</strong>
                <span class="nxmc-stat-label">Paused records</span>
                <span class="nxmc-stat-copy">Supplier records retained for history but not active for new commercial linkage.</span>
            </article>

            <article class="nxmc-stat-card is-violet">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['blacklisted']) }}</strong>
                <span class="nxmc-stat-label">Risk flagged</span>
                <span class="nxmc-stat-copy">Blacklisted suppliers stay visible for governance while remaining blocked from normal sourcing.</span>
            </article>
        </section>

        <section class="nxpg-flow-board nxmc-reveal" aria-label="Supplier workflow guidance">
            <header class="nxpg-flow-head">
                <div>
                    <span class="nxmc-section-kicker">Commercial workflow</span>
                    <h3>Supplier records should lead the pricing chain.</h3>
                    <p>Use supplier governance first, then establish price books, then connect catalog materials so BOM and quotation logic inherit traceable sourcing context.</p>
                </div>
                <div class="nxpg-flow-summary">
                    <span>Step 01</span>
                    <strong>Supplier → Price Book → Catalog → BOQ</strong>
                </div>
            </header>
            <div class="nxpg-flow-grid">
                <article class="nxpg-flow-card">
                    <span class="nxpg-flow-index">01</span>
                    <div class="nxpg-flow-copy">
                        <strong>Register supplier identity</strong>
                        <p>Record legal name, contact channels, lead time, payment terms, tax data, and risk status.</p>
                    </div>
                </article>
                <article class="nxpg-flow-card">
                    <span class="nxpg-flow-index">02</span>
                    <div class="nxpg-flow-copy">
                        <strong>Open controlled price sources</strong>
                        <p>Connect each qualified supplier to price books so costs remain date-bound and commercially auditable.</p>
                    </div>
                </article>
                <article class="nxpg-flow-card">
                    <span class="nxpg-flow-index">03</span>
                    <div class="nxpg-flow-copy">
                        <strong>Feed catalog and BOQ pricing</strong>
                        <p>Use governed supplier references to strengthen catalog readiness and keep BOM totals commercially explainable.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="nxmc-registry nxmc-reveal">
            <header class="nxmc-registry-head">
                <div>
                    <span class="nxmc-section-kicker">Approved supplier network</span>
                    <h3>Supplier intelligence registry</h3>
                    <p>Keep procurement identity, commercial contact, lead time, price-source usage, and governance state readable at a glance.</p>
                </div>
                <span class="nxmc-registry-count">{{ number_format($suppliers->total()) }} records</span>
            </header>

            <form method="GET" action="{{ route('companies.suppliers.index', $company) }}" class="nxmc-filter-form nxpg-filter-form">
                <div class="nxmc-field">
                    <label class="nxmc-label" for="supplier-search">Supplier identity</label>
                    <input id="supplier-search" class="nxmc-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Supplier code, name, contact, email, or phone">
                </div>
                <div class="nxmc-field">
                    <label class="nxmc-label" for="supplier-status">Governance state</label>
                    <select id="supplier-status" class="nxmc-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All states</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        <option value="blacklisted" @selected($filters['status'] === 'blacklisted')>Blacklisted</option>
                    </select>
                </div>
                <div class="nxmc-filter-actions nxpg-filter-actions">
                    <button type="submit" class="nxmc-button nxmc-button-secondary">Apply Filters</button>
                    @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                        <a href="{{ route('companies.suppliers.index', $company) }}" class="nxmc-button nxmc-button-secondary">Clear</a>
                    @endif
                </div>
            </form>

            @if ($suppliers->isEmpty())
                <div class="nxmc-empty nxpg-empty">
                    <div>
                        <span class="nxmc-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V8l8-5 8 5v12"/><path d="M8 20v-6h8v6M7 10h10"/></svg></span>
                        @if ($stats['total'] === 0)
                            <h3>Build your approved supplier network.</h3>
                            <p>Register suppliers before creating price books, commercial source references, and procurement-backed catalog coverage.</p>
                            <div class="nxpg-empty-actions">
                                @if (auth()->user()->hasCompanyPermission($company, 'supplier.create'))
                                    <a href="{{ route('companies.suppliers.create', $company) }}" class="nxmc-button nxmc-button-primary">Add First Supplier</a>
                                @endif
                                @if (auth()->user()->hasCompanyPermission($company, 'price.cost.view'))
                                    <a href="{{ route('companies.price-books.index', $company) }}" class="nxmc-button nxmc-button-secondary">Open Price Books</a>
                                @endif
                            </div>
                            <div class="nxpg-empty-checklist" aria-label="Suggested onboarding steps">
                                <span>Register</span>
                                <span>Verify</span>
                                <span>Activate</span>
                            </div>
                        @else
                            <h3>No supplier matches the current filter.</h3>
                            <p>Try a broader search or clear the governance filter to reopen the full procurement directory.</p>
                            <div class="nxpg-empty-actions">
                                <a href="{{ route('companies.suppliers.index', $company) }}" class="nxmc-button nxmc-button-secondary">Reset Filters</a>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="nxmc-registry-columns nxpg-supplier-columns" role="presentation">
                    <span>Supplier</span>
                    <span>Commercial readiness</span>
                    <span>Price sources</span>
                    <span>Governance</span>
                    <span aria-hidden="true"></span>
                </div>

                <div>
                    @foreach ($suppliers as $supplier)
                        <div class="nxmc-registry-row nxpg-supplier-row">
                            <div class="nxmc-material-identity">
                                <span class="nxmc-material-glyph" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V8l8-5 8 5v12"/><path d="M8 20v-6h8v6M7 10h10"/></svg></span>
                                <div class="nxmc-material-copy">
                                    <strong>{{ $supplier->name }}</strong>
                                    <small>{{ $supplier->supplier_code }}{{ $supplier->contact_person ? ' · '.$supplier->contact_person : '' }}</small>
                                </div>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Contact and delivery</span>
                                <span class="nxmc-cell-primary">{{ $supplier->email ?? $supplier->phone ?? 'No contact channel registered' }}</span>
                                <span class="nxmc-cell-secondary">{{ $supplier->lead_time_days ? $supplier->lead_time_days.' day lead time' : 'Lead time not yet defined' }}{{ $supplier->payment_terms ? ' · '.$supplier->payment_terms : '' }}</span>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Connected records</span>
                                <div class="nxmc-chip-row">
                                    <span class="nxmc-chip is-teal">{{ number_format($supplier->price_books_count) }} price books</span>
                                    <span class="nxmc-chip">{{ number_format($supplier->price_book_items_count) }} price items</span>
                                </div>
                                <span class="nxmc-cell-secondary">{{ $supplier->tax_number ? 'Tax '.$supplier->tax_number : 'Tax number not registered' }}</span>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Control state</span>
                                <div class="nxmc-chip-row">
                                    @if ($supplier->status === 'active')
                                        <span class="nxmc-chip is-green">Active sourcing</span>
                                    @elseif ($supplier->status === 'blacklisted')
                                        <span class="nxmc-chip is-red">Blacklisted</span>
                                    @else
                                        <span class="nxmc-chip is-orange">Inactive</span>
                                    @endif
                                    @if ($supplier->website)
                                        <span class="nxmc-chip">Website linked</span>
                                    @endif
                                </div>
                                <span class="nxmc-cell-secondary">{{ $supplier->address ? \Illuminate\Support\Str::limit($supplier->address, 88) : 'Address not yet recorded' }}</span>
                            </div>

                            <div class="nxmc-registry-action">
                                <a href="{{ route('companies.suppliers.show', [$company, $supplier->id]) }}" class="nxmc-open-button">
                                    Open
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="nxmc-registry-footer">
                    @include('partials.pagination', ['paginator' => $suppliers])
                </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=58b" defer></script>
@endpush
