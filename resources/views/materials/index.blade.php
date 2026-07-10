@extends('layouts.app')

@section('title', 'Material Catalog')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Catalog foundation</div>
                <h2 class="ui-page-title">Material Catalog</h2>
                <p class="ui-page-description">
                    Manage real PV materials, system suitability, technical attributes, catalog scope mode, and future price book connection.
                </p>
            </div>

            <div class="ui-page-actions">
                @if (auth()->user()->hasCompanyPermission($company, 'supplier.view'))
                    <a href="{{ route('companies.suppliers.index', $company) }}" class="ui-button ui-button-secondary">Suppliers</a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'price.cost.view'))
                    <a href="{{ route('companies.price-books.index', $company) }}" class="ui-button ui-button-secondary">Price Books</a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'equipment.create'))
                    <a href="{{ route('companies.materials.create', $company) }}" class="ui-button ui-button-primary">Add Material</a>
                @endif
            </div>
        </header>

        <section class="ui-stats-grid" aria-label="Material catalog statistics">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">MT</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['total']) }}</strong>
                <span class="ui-stat-label">Visible Materials</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">AC</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['active']) }}</strong>
                <span class="ui-stat-label">Active Items</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">CO</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['company']) }}</strong>
                <span class="ui-stat-label">Company Catalog</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">GL</span></div>
                <strong class="ui-stat-value">{{ number_format($stats['global']) }}</strong>
                <span class="ui-stat-label">Global Reference</span>
            </article>
        </section>

        <section class="ui-toolbar" aria-label="Material filters">
            <form method="GET" action="{{ route('companies.materials.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="material-search">Search materials</label>
                    <input id="material-search" class="ui-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="SKU, name, brand, model, or type">
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="material-category">Category</label>
                    <select id="material-category" class="ui-select" name="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['category'] === (string) $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="material-system-type">System</label>
                    <select id="material-system-type" class="ui-select" name="system_type">
                        <option value="all" @selected($filters['system_type'] === 'all')>All systems</option>
                        <option value="on_grid" @selected($filters['system_type'] === 'on_grid')>On-grid</option>
                        <option value="hybrid" @selected($filters['system_type'] === 'hybrid')>Hybrid</option>
                        <option value="off_grid" @selected($filters['system_type'] === 'off_grid')>Off-grid</option>
                    </select>
                </div>

                <div class="ui-field">
                    <label class="ui-label" for="material-scope">Catalog Scope</label>
                    <select id="material-scope" class="ui-select" name="scope">
                        <option value="all" @selected($filters['scope'] === 'all')>All scopes</option>
                        <option value="global" @selected($filters['scope'] === 'global')>Global only</option>
                        <option value="company" @selected($filters['scope'] === 'company')>Company only</option>
                    </select>
                </div>

                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>

                @if ($filters['search'] !== '' || $filters['category'] || $filters['system_type'] !== 'all' || $filters['scope'] !== 'all' || $filters['status'] !== 'all')
                    <a href="{{ route('companies.materials.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
                @endif
            </form>
        </section>

        <section class="ui-table-shell">
            @if ($materials->isEmpty())
                <div class="ui-empty">
                    <div>
                        <h3>No materials found</h3>
                        <p>Create company-specific catalog items or adjust the filters.</p>
                    </div>
                </div>
            @else
                <div class="ui-table-scroll">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Category</th>
                                <th>System Fit</th>
                                <th>Unit</th>
                                <th>Scope Mode</th>
                                <th>Catalog</th>
                                <th>Price Links</th>
                                <th>Status</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($materials as $material)
                                <tr>
                                    <td>
                                        <div class="ui-table-primary">{{ $material->name }}</div>
                                        <div class="ui-table-secondary">{{ $material->sku }} · {{ collect([$material->brand, $material->model])->filter()->implode(' ') ?: 'Generic' }}</div>
                                    </td>
                                    <td>{{ $material->category?->name ?? 'Uncategorized' }}</td>
                                    <td>{{ $material->system_type_label ?: 'General' }}</td>
                                    <td>{{ $material->uom }}</td>
                                    <td>{{ str($material->default_scope_mode)->replace('_', ' ')->title() }}</td>
                                    <td>{{ $material->scope_label }}</td>
                                    <td>{{ number_format($material->price_book_items_count) }}</td>
                                    <td>
                                        @if ($material->is_active)
                                            <span class="ui-status ui-status-active">Active</span>
                                        @else
                                            <span class="ui-status ui-status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="ui-actions">
                                            <a href="{{ route('companies.materials.show', [$company, $material->id]) }}" class="ui-button ui-button-secondary ui-button-compact">Open</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="ui-table-footer">
                    @include('partials.pagination', ['paginator' => $materials])
                </div>
            @endif
        </section>
    </div>
@endsection
