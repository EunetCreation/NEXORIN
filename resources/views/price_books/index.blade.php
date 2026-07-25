@extends('layouts.app')

@section('title', 'Price Books')
@section('page-eyebrow', 'Catalog pricing')
@section('page-heading', 'Price Books')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=58b" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-directory nxpg-page">
        <section class="nxmc-directory-hero nxmc-reveal">
            <div class="nxmc-directory-copy">
                <span class="nxmc-kicker">Commercial cost governance</span>
                <h2>Price books that can defend every catalog cost reference.</h2>
                <p>Organize supplier or internal cost references by source, effective date, currency, and control state so later catalog pricing and BOQ calculations inherit a clear commercial audit trail.</p>
            </div>

            <div class="nxmc-directory-context">
                <span class="nxmc-context-chip"><i aria-hidden="true"></i>Pricing control active</span>
                @if (auth()->user()->hasCompanyPermission($company, 'equipment.view'))
                    <a href="{{ route('companies.materials.index', $company) }}" class="nxmc-context-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                        Material Catalog
                    </a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'supplier.view'))
                    <a href="{{ route('companies.suppliers.index', $company) }}" class="nxmc-context-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20V8l8-5 8 5v12"/><path d="M8 20v-6h8v6M7 10h10"/></svg>
                        Suppliers
                    </a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'price.cost.update'))
                    <a href="{{ route('companies.price-books.create', $company) }}" class="nxmc-button nxmc-button-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Add Price Book
                    </a>
                @endif
            </div>
        </section>

        <section class="nxmc-stat-grid nxmc-reveal" aria-label="Price book governance statistics">
            <article class="nxmc-stat-card">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['total']) }}</strong>
                <span class="nxmc-stat-label">Controlled price books</span>
                <span class="nxmc-stat-copy">Header records that structure supplier or internal price evidence for the company catalog.</span>
            </article>

            <article class="nxmc-stat-card is-orange">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['active']) }}</strong>
                <span class="nxmc-stat-label">Live commercial sources</span>
                <span class="nxmc-stat-copy">Active price books ready to support pricing workflows and downstream commercial checks.</span>
            </article>

            <article class="nxmc-stat-card is-blue">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h16"/><path d="M12 4v16"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['draft']) }}</strong>
                <span class="nxmc-stat-label">Draft staging</span>
                <span class="nxmc-stat-copy">Draft price books can be prepared safely before being released into active commercial use.</span>
            </article>

            <article class="nxmc-stat-card is-violet">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 5h14v14H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['archived']) }}</strong>
                <span class="nxmc-stat-label">Archived references</span>
                <span class="nxmc-stat-copy">Retained commercial evidence kept outside current pricing operations while preserving history.</span>
            </article>
        </section>

        <section class="nxpg-flow-board nxmc-reveal" aria-label="Price book workflow guidance">
            <header class="nxpg-flow-head">
                <div>
                    <span class="nxmc-section-kicker">Pricing chain</span>
                    <h3>Price books should sit between suppliers and catalog pricing.</h3>
                    <p>Separate source control from material linkage so future BOQ pricing can trace where each commercial figure came from, when it was valid, and under what pricing basis.</p>
                </div>
                <div class="nxpg-flow-summary">
                    <span>Step 02</span>
                    <strong>Source → Window → Material Link → BOQ</strong>
                </div>
            </header>
            <div class="nxpg-flow-grid">
                <article class="nxpg-flow-card">
                    <span class="nxpg-flow-index">01</span>
                    <div class="nxpg-flow-copy">
                        <strong>Select a commercial source</strong>
                        <p>Choose a supplier or internal/mixed source so the cost origin stays explicit from the start.</p>
                    </div>
                </article>
                <article class="nxpg-flow-card">
                    <span class="nxpg-flow-index">02</span>
                    <div class="nxpg-flow-copy">
                        <strong>Set effective control dates</strong>
                        <p>Protect pricing validity by defining the effective window, status, currency, and pricing basis.</p>
                    </div>
                </article>
                <article class="nxpg-flow-card">
                    <span class="nxpg-flow-index">03</span>
                    <div class="nxpg-flow-copy">
                        <strong>Link catalog materials later</strong>
                        <p>After the header is governed, material-level price items can attach with auditable cost coverage.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="nxmc-registry nxmc-reveal">
            <header class="nxmc-registry-head">
                <div>
                    <span class="nxmc-section-kicker">Company commercial references</span>
                    <h3>Price book registry</h3>
                    <p>Read price book purpose, source, effective coverage, item linkage, and governance state without opening every record.</p>
                </div>
                <span class="nxmc-registry-count">{{ number_format($priceBooks->total()) }} records</span>
            </header>

            <form method="GET" action="{{ route('companies.price-books.index', $company) }}" class="nxmc-filter-form nxpg-filter-form nxpg-filter-form-pricebooks">
                <div class="nxmc-field">
                    <label class="nxmc-label" for="price-book-search">Price book identity</label>
                    <input id="price-book-search" class="nxmc-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Price book code or name">
                </div>
                <div class="nxmc-field">
                    <label class="nxmc-label" for="price-book-supplier">Supplier source</label>
                    <select id="price-book-supplier" class="nxmc-select" name="supplier">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) $filters['supplier'] === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="nxmc-field">
                    <label class="nxmc-label" for="price-book-status">Governance state</label>
                    <select id="price-book-status" class="nxmc-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All states</option>
                        <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                    </select>
                </div>
                <div class="nxmc-filter-actions nxpg-filter-actions">
                    <button type="submit" class="nxmc-button nxmc-button-secondary">Apply Filters</button>
                    @if ($filters['search'] !== '' || $filters['status'] !== 'all' || $filters['supplier'])
                        <a href="{{ route('companies.price-books.index', $company) }}" class="nxmc-button nxmc-button-secondary">Clear</a>
                    @endif
                </div>
            </form>

            @if ($priceBooks->isEmpty())
                <div class="nxmc-empty nxpg-empty">
                    <div>
                        <span class="nxmc-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                        @if ($stats['total'] === 0)
                            <h3>Start with your first controlled price source.</h3>
                            <p>Create a draft price book to hold supplier or internal pricing before catalog items are linked into future BOQ and quotation workflows.</p>
                            <div class="nxpg-empty-actions">
                                @if (auth()->user()->hasCompanyPermission($company, 'price.cost.update'))
                                    <a href="{{ route('companies.price-books.create', $company) }}" class="nxmc-button nxmc-button-primary">Create Price Book</a>
                                @endif
                                @if (auth()->user()->hasCompanyPermission($company, 'supplier.view'))
                                    <a href="{{ route('companies.suppliers.index', $company) }}" class="nxmc-button nxmc-button-secondary">Review Suppliers</a>
                                @endif
                            </div>
                            <div class="nxpg-empty-checklist" aria-label="Suggested onboarding steps">
                                <span>Source</span>
                                <span>Control</span>
                                <span>Link Later</span>
                            </div>
                        @else
                            <h3>No price book matches the current filter.</h3>
                            <p>Try another supplier, broaden the status filter, or reset the search to restore the commercial registry.</p>
                            <div class="nxpg-empty-actions">
                                <a href="{{ route('companies.price-books.index', $company) }}" class="nxmc-button nxmc-button-secondary">Reset Filters</a>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="nxmc-registry-columns nxpg-pricebook-columns" role="presentation">
                    <span>Price book</span>
                    <span>Source and currency</span>
                    <span>Validity window</span>
                    <span>Coverage</span>
                    <span>Governance</span>
                    <span aria-hidden="true"></span>
                </div>

                <div>
                    @foreach ($priceBooks as $priceBook)
                        <div class="nxmc-registry-row nxpg-pricebook-row">
                            <div class="nxmc-material-identity">
                                <span class="nxmc-material-glyph" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                                <div class="nxmc-material-copy">
                                    <strong>{{ $priceBook->name }}</strong>
                                    <small>{{ $priceBook->code }}</small>
                                </div>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Commercial source</span>
                                <span class="nxmc-cell-primary">{{ $priceBook->supplier?->name ?? 'Internal / mixed suppliers' }}</span>
                                <span class="nxmc-cell-secondary">{{ $priceBook->currency }} · {{ $priceBook->supplier?->supplier_code ?? 'Mixed source' }}</span>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Effective control</span>
                                <span class="nxmc-cell-primary">{{ $priceBook->effective_from?->format('d M Y') ?? 'Open from start' }}</span>
                                <span class="nxmc-cell-secondary">Until {{ $priceBook->effective_until?->format('d M Y') ?? 'open ended' }}</span>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Linked coverage</span>
                                <div class="nxmc-chip-row">
                                    <span class="nxmc-chip is-teal">{{ number_format($priceBook->items_count) }} items</span>
                                    @if ($priceBook->items_count > 0)
                                        <span class="nxmc-chip is-green">Catalog linked</span>
                                    @else
                                        <span class="nxmc-chip is-orange">Awaiting items</span>
                                    @endif
                                </div>
                                <span class="nxmc-cell-secondary">Use the header first, then link material price items for full BOQ coverage.</span>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Control state</span>
                                <div class="nxmc-chip-row">
                                    @if ($priceBook->status === 'active')
                                        <span class="nxmc-chip is-green">Active</span>
                                    @elseif ($priceBook->status === 'archived')
                                        <span class="nxmc-chip is-red">Archived</span>
                                    @else
                                        <span class="nxmc-chip is-orange">Draft</span>
                                    @endif
                                    <span class="nxmc-chip">{{ $priceBook->currency }}</span>
                                </div>
                                <span class="nxmc-cell-secondary">Commercial header ready for controlled downstream pricing decisions.</span>
                            </div>

                            <div class="nxmc-registry-action">
                                <a href="{{ route('companies.price-books.show', [$company, $priceBook->id]) }}" class="nxmc-open-button">
                                    Open
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="nxmc-registry-footer">
                    @include('partials.pagination', ['paginator' => $priceBooks])
                </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=58b" defer></script>
@endpush
