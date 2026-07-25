@extends('layouts.app')

@section('title', 'Material Catalog')
@section('page-eyebrow', 'Catalog intelligence')
@section('page-heading', 'Material Catalog')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=62a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-material-catalog-refinement.css') }}?v=1" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-directory">
        <section class="nxmc-directory-hero nxmc-reveal">
            <div class="nxmc-directory-copy">
                <span class="nxmc-kicker">Material intelligence registry</span>
                <h2>Engineering materials, governed as evidence.</h2>
                <p>Control material identity, system applicability, catalog ownership, technical readiness, and commercial linkage before an item enters BOM and quotation workflows.</p>
            </div>

            <div class="nxmc-directory-context">
                <span class="nxmc-context-chip"><i aria-hidden="true"></i>Catalog governance active</span>
                @if (auth()->user()->hasCompanyPermission($company, 'supplier.view'))
                    <a href="{{ route('companies.suppliers.index', $company) }}" class="nxmc-context-link" data-nxmc-navigate="suppliers">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20V8l8-5 8 5v12"/><path d="M8 20v-6h8v6M7 10h10"/></svg>
                        Suppliers
                    </a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'price.cost.view'))
                    <a href="{{ route('companies.price-books.index', $company) }}" class="nxmc-context-link" data-nxmc-navigate="prices">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                        Price Books
                    </a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'equipment.create'))
                    <a href="{{ route('companies.materials.create', $company) }}" class="nxmc-button nxmc-button-primary" data-nxmc-navigate="create">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Add Material
                    </a>
                @endif
            </div>
        </section>

        <section class="nxmc-stat-grid nxmc-reveal" aria-label="Material catalog portfolio statistics">
            <article class="nxmc-stat-card">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['total']) }}</strong>
                <span class="nxmc-stat-label">Visible materials</span>
                <span class="nxmc-stat-copy">Global references and company-owned records available to this workspace.</span>
            </article>

            <article class="nxmc-stat-card is-orange">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['active']) }}</strong>
                <span class="nxmc-stat-label">Active records</span>
                <span class="nxmc-stat-copy">Records available for controlled catalog and downstream selection workflows.</span>
            </article>

            <article class="nxmc-stat-card is-blue">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['company']) }}</strong>
                <span class="nxmc-stat-label">Company catalog</span>
                <span class="nxmc-stat-copy">Locally governed materials that can be revised, priced, and standardized.</span>
            </article>

            <article class="nxmc-stat-card is-violet">
                <div class="nxmc-stat-head">
                    <span class="nxmc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg></span>
                    <span class="nxmc-stat-signal" aria-hidden="true"></span>
                </div>
                <strong class="nxmc-stat-value">{{ number_format($stats['global']) }}</strong>
                <span class="nxmc-stat-label">Global references</span>
                <span class="nxmc-stat-copy">Read-only standard records available as cross-company catalog references.</span>
            </article>
        </section>

        <section class="nxmc-portfolio-board nxmc-reveal" aria-label="Professional catalog health">
            <header class="nxmc-portfolio-head">
                <div>
                    <span class="nxmc-section-kicker">Professional catalog architecture</span>
                    <h3>Fifteen governed catalog domains</h3>
                    <p>Each domain carries its own specification, certificate, warranty, manual, and commercial evidence requirements before a product can become BOQ-ready.</p>
                </div>
                <div class="nxmc-health-cluster" aria-label="Catalog health metrics">
                    <div><span>Approved</span><strong>{{ number_format($stats['approved']) }}</strong></div>
                    <div><span>Needs review</span><strong>{{ number_format($stats['needs_review']) }}</strong></div>
                    <div><span>Price linked</span><strong>{{ number_format($stats['priced']) }}</strong></div>
                    <div><span>Parsed evidence</span><strong>{{ number_format($stats['verified_evidence']) }}</strong></div>
                </div>
            </header>

            <div class="nxmc-domain-grid">
                @foreach ($categoryPortfolio as $domain)
                    @php
                        $profile = $professionalProfiles[$domain['code']] ?? null;
                    @endphp
                    <a href="{{ route('companies.materials.index', [$company, 'category' => $domain['id']]) }}" class="nxmc-domain-card" data-nxmc-navigate="directory">
                        <span class="nxmc-domain-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="nxmc-domain-copy">
                            <strong>{{ $domain['name'] }}</strong>
                            <p>{{ $profile['description'] ?? $domain['description'] ?? 'Governed material catalog domain.' }}</p>
                        </div>
                        <div class="nxmc-domain-metrics">
                            <span>{{ number_format($domain['subcategories']) }} subcategories</span>
                            <span>{{ number_format($domain['materials']) }} materials</span>
                        </div>
                        <div class="nxmc-domain-track" aria-hidden="true"><span style="width: {{ $domain['readiness_percent'] }}%;"></span></div>
                        <small>{{ $domain['materials'] > 0 ? $domain['readiness_percent'].'% approved coverage' : 'Ready for material intake' }}</small>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="nxmc-registry nxmc-reveal">
            <header class="nxmc-registry-head">
                <div>
                    <span class="nxmc-section-kicker">Engineering catalog portfolio</span>
                    <h3>Material intelligence registry</h3>
                    <p>Separate record activity, readiness, compliance, catalog ownership, and price coverage so each governance state remains unambiguous.</p>
                </div>
                <span class="nxmc-registry-count">{{ number_format($materials->total()) }} records</span>
            </header>

            <form method="GET" action="{{ route('companies.materials.index', $company) }}" class="nxmc-filter-form" data-nxmc-filter-form>
                <div class="nxmc-field">
                    <label class="nxmc-label" for="material-search">Material identity</label>
                    <input id="material-search" class="nxmc-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="SKU, name, brand, model, or type">
                </div>

                <div class="nxmc-field">
                    <label class="nxmc-label" for="material-category">Category</label>
                    <select id="material-category" class="nxmc-select" name="category" data-nxmc-auto-submit>
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['category'] === (string) $category->id)>{{ $category->parent_id ? '— '.$category->name : $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="nxmc-field">
                    <label class="nxmc-label" for="material-system-type">System</label>
                    <select id="material-system-type" class="nxmc-select" name="system_type" data-nxmc-auto-submit>
                        <option value="all" @selected($filters['system_type'] === 'all')>All systems</option>
                        <option value="on_grid" @selected($filters['system_type'] === 'on_grid')>On-grid</option>
                        <option value="hybrid" @selected($filters['system_type'] === 'hybrid')>Hybrid</option>
                        <option value="off_grid" @selected($filters['system_type'] === 'off_grid')>Off-grid</option>
                    </select>
                </div>

                <div class="nxmc-field">
                    <label class="nxmc-label" for="material-scope">Catalog ownership</label>
                    <select id="material-scope" class="nxmc-select" name="scope" data-nxmc-auto-submit>
                        <option value="all" @selected($filters['scope'] === 'all')>All ownership</option>
                        <option value="global" @selected($filters['scope'] === 'global')>Global reference</option>
                        <option value="company" @selected($filters['scope'] === 'company')>Company catalog</option>
                    </select>
                </div>

                <div class="nxmc-field">
                    <label class="nxmc-label" for="material-status">Record state</label>
                    <select id="material-status" class="nxmc-select" name="status" data-nxmc-auto-submit>
                        <option value="all" @selected($filters['status'] === 'all')>All states</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Archived</option>
                    </select>
                </div>

                <div class="nxmc-field">
                    <label class="nxmc-label" for="material-governance">Governance</label>
                    <select id="material-governance" class="nxmc-select" name="governance" data-nxmc-auto-submit>
                        <option value="all" @selected($filters['governance'] === 'all')>All governance</option>
                        <option value="draft" @selected($filters['governance'] === 'draft')>Draft</option>
                        <option value="needs_review" @selected($filters['governance'] === 'needs_review')>Needs review</option>
                        <option value="approved" @selected($filters['governance'] === 'approved')>Approved</option>
                        <option value="rejected" @selected($filters['governance'] === 'rejected')>Rejected</option>
                    </select>
                </div>

                <div class="nxmc-field">
                    <label class="nxmc-label" for="material-evidence">Evidence coverage</label>
                    <select id="material-evidence" class="nxmc-select" name="evidence" data-nxmc-auto-submit>
                        <option value="all" @selected($filters['evidence'] === 'all')>All evidence</option>
                        <option value="datasheet" @selected($filters['evidence'] === 'datasheet')>Active datasheet</option>
                        <option value="certificate" @selected($filters['evidence'] === 'certificate')>Verified certificate</option>
                        <option value="document" @selected($filters['evidence'] === 'document')>Verified document pack</option>
                        <option value="price" @selected($filters['evidence'] === 'price')>Positive price reference</option>
                    </select>
                </div>

                <div class="nxmc-field">
                    <label class="nxmc-label" for="material-sort">Sort</label>
                    <select id="material-sort" class="nxmc-select" name="sort" data-nxmc-auto-submit>
                        <option value="name" @selected($filters['sort'] === 'name')>Material name</option>
                        <option value="sku" @selected($filters['sort'] === 'sku')>SKU</option>
                        <option value="brand" @selected($filters['sort'] === 'brand')>Brand</option>
                        <option value="created_at" @selected($filters['sort'] === 'created_at')>Created date</option>
                    </select>
                    <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                </div>

                <div class="nxmc-filter-actions">
                    <button type="submit" class="nxmc-button nxmc-button-secondary">Apply</button>
                    @if ($filters['search'] !== '' || $filters['category'] || $filters['system_type'] !== 'all' || $filters['scope'] !== 'all' || $filters['status'] !== 'all' || $filters['governance'] !== 'all' || $filters['evidence'] !== 'all' || $filters['sort'] !== 'name' || $filters['direction'] !== 'asc')
                        <a href="{{ route('companies.materials.index', $company) }}" class="nxmc-button nxmc-button-secondary" data-nxmc-navigate="directory">Clear</a>
                    @endif
                </div>
            </form>

            @if ($materials->isEmpty())
                <div class="nxmc-empty">
                    <div>
                        <span class="nxmc-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                        <h3>No matching material records</h3>
                        <p>Adjust the active catalog filters or create a company material with controlled identity and governance context.</p>
                    </div>
                </div>
            @else
                <div class="nxmc-registry-columns" aria-hidden="true">
                    <span>Material identity</span>
                    <span>Applicability</span>
                    <span>Catalog governance</span>
                    <span>Price coverage</span>
                    <span>Record state</span>
                    <span></span>
                </div>

                <div>
                    @foreach ($materials as $material)
                        @php
                            $catalogState = str($material->catalog_status ?? 'draft')->replace('_', ' ')->title();
                            $complianceState = str($material->compliance_status ?? 'needs_review')->replace('_', ' ')->title();
                            $systems = collect($material->system_types ?? [])->map(fn ($value) => match ($value) {
                                'on_grid' => 'On-grid',
                                'hybrid' => 'Hybrid',
                                'off_grid' => 'Off-grid',
                                default => str($value)->replace('_', ' ')->title()->toString(),
                            });
                        @endphp
                        <article class="nxmc-registry-row" tabindex="0" data-nxmc-row>
                            <div class="nxmc-material-identity">
                                <span class="nxmc-material-glyph" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/><path d="M16 4v4h3"/></svg>
                                </span>
                                <div class="nxmc-material-copy">
                                    <span class="nxmc-material-sku">{{ $material->sku }}</span>
                                    <strong>{{ $material->name }}</strong>
                                    <small>{{ collect([$material->brand, $material->model])->filter()->implode(' · ') ?: 'Generic catalog identity' }}</small>
                                </div>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Category and fit</span>
                                <span class="nxmc-cell-primary">{{ $material->category?->name ?? 'Uncategorized' }}</span>
                                <div class="nxmc-chip-row">
                                    @forelse ($systems as $system)
                                        <span class="nxmc-chip is-teal">{{ $system }}</span>
                                    @empty
                                        <span class="nxmc-chip">General</span>
                                    @endforelse
                                    <span class="nxmc-chip">{{ $material->uom }}</span>
                                </div>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Readiness and compliance</span>
                                <span class="nxmc-cell-primary">{{ $catalogState }}</span>
                                <span class="nxmc-cell-secondary">{{ $complianceState }} · {{ str($material->default_scope_mode)->replace('_', ' ')->title() }}</span>
                                <div class="nxmc-chip-row is-compact">
                                    <span class="nxmc-chip {{ $material->datasheets_count > 0 ? 'is-blue' : '' }}">DS {{ number_format($material->datasheets_count) }}</span>
                                    <span class="nxmc-chip {{ $material->certificates_count > 0 ? 'is-green' : '' }}">CERT {{ number_format($material->certificates_count) }}</span>
                                    <span class="nxmc-chip {{ $material->documents_count > 0 ? 'is-violet' : '' }}">DOC {{ number_format($material->documents_count) }}</span>
                                </div>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Price-book links</span>
                                <span class="nxmc-registry-value">{{ number_format($material->price_book_items_count) }}</span>
                                <span class="nxmc-cell-secondary">{{ $material->price_book_items_count > 0 ? 'Commercial reference linked' : 'Pricing evidence missing' }}</span>
                            </div>

                            <div class="nxmc-registry-cell">
                                <span class="nxmc-cell-label">Ownership and availability</span>
                                <div class="nxmc-chip-row">
                                    <span class="nxmc-chip {{ $material->is_active ? 'is-green' : 'is-red' }}">{{ $material->is_active ? 'Active record' : 'Archived record' }}</span>
                                    <span class="nxmc-chip {{ $material->company_id ? 'is-teal' : '' }}">{{ $material->scope_label }}</span>
                                </div>
                            </div>

                            <div class="nxmc-registry-action">
                                <a href="{{ route('companies.materials.show', [$company, $material->id]) }}" class="nxmc-open-button" data-nxmc-navigate="open" data-nxmc-material="{{ $material->name }}" data-nxmc-sku="{{ $material->sku }}">
                                    Open
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <footer class="nxmc-registry-footer">
                    @include('partials.pagination', ['paginator' => $materials])
                </footer>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=62a" defer></script>
@endpush

