@extends('layouts.app')

@section('title', 'Price Books')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Catalog pricing</div>
                <h2 class="ui-page-title">Price Books</h2>
                <p class="ui-page-description">Manage supplier or internal price references that will later drive catalog-based BOQ pricing.</p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('companies.materials.index', $company) }}" class="ui-button ui-button-secondary">Material Catalog</a>
                <a href="{{ route('companies.suppliers.index', $company) }}" class="ui-button ui-button-secondary">Suppliers</a>
                @if (auth()->user()->hasCompanyPermission($company, 'price.cost.update'))
                    <a href="{{ route('companies.price-books.create', $company) }}" class="ui-button ui-button-primary">Add Price Book</a>
                @endif
            </div>
        </header>

        <section class="ui-stats-grid">
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">PB</span></div><strong class="ui-stat-value">{{ number_format($stats['total']) }}</strong><span class="ui-stat-label">Price Books</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">AC</span></div><strong class="ui-stat-value">{{ number_format($stats['active']) }}</strong><span class="ui-stat-label">Active</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">DR</span></div><strong class="ui-stat-value">{{ number_format($stats['draft']) }}</strong><span class="ui-stat-label">Draft</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">AR</span></div><strong class="ui-stat-value">{{ number_format($stats['archived']) }}</strong><span class="ui-stat-label">Archived</span></article>
        </section>

        <section class="ui-toolbar">
            <form method="GET" action="{{ route('companies.price-books.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="price-book-search">Search price books</label>
                    <input id="price-book-search" class="ui-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Price book code or name">
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="price-book-supplier">Supplier</label>
                    <select id="price-book-supplier" class="ui-select" name="supplier">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) $filters['supplier'] === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="price-book-status">Status</label>
                    <select id="price-book-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All status</option>
                        <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                    </select>
                </div>
                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>
                @if ($filters['search'] !== '' || $filters['status'] !== 'all' || $filters['supplier'])
                    <a href="{{ route('companies.price-books.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
                @endif
            </form>
        </section>

        <section class="ui-table-shell">
            @if ($priceBooks->isEmpty())
                <div class="ui-empty"><div><h3>No price books found</h3><p>Create a draft price book to start linking catalog materials with cost references.</p></div></div>
            @else
                <div class="ui-table-scroll">
                    <table class="ui-table">
                        <thead><tr><th>Price Book</th><th>Supplier</th><th>Currency</th><th>Effective</th><th>Items</th><th>Status</th><th aria-label="Actions"></th></tr></thead>
                        <tbody>
                            @foreach ($priceBooks as $priceBook)
                                <tr>
                                    <td><div class="ui-table-primary">{{ $priceBook->name }}</div><div class="ui-table-secondary">{{ $priceBook->code }}</div></td>
                                    <td>{{ $priceBook->supplier?->name ?? 'Internal / Mixed' }}</td>
                                    <td>{{ $priceBook->currency }}</td>
                                    <td>{{ $priceBook->effective_from?->format('d M Y') ?? '—' }} – {{ $priceBook->effective_until?->format('d M Y') ?? 'open' }}</td>
                                    <td>{{ number_format($priceBook->items_count) }}</td>
                                    <td>
                                        @if ($priceBook->status === 'active')
                                            <span class="ui-status ui-status-active">Active</span>
                                        @elseif ($priceBook->status === 'archived')
                                            <span class="ui-status ui-status-inactive">Archived</span>
                                        @else
                                            <span class="ui-status ui-status-pending">Draft</span>
                                        @endif
                                    </td>
                                    <td><div class="ui-actions"><a href="{{ route('companies.price-books.show', [$company, $priceBook->id]) }}" class="ui-button ui-button-secondary ui-button-compact">Open</a></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="ui-table-footer">@include('partials.pagination', ['paginator' => $priceBooks])</div>
            @endif
        </section>
    </div>
@endsection
