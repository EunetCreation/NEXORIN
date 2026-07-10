@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Procurement catalog</div>
                <h2 class="ui-page-title">Suppliers</h2>
                <p class="ui-page-description">Track supplier records that will feed company price books, material availability, warranty source, and procurement audit.</p>
            </div>

            <div class="ui-page-actions">
                @if (auth()->user()->hasCompanyPermission($company, 'equipment.view'))
                    <a href="{{ route('companies.materials.index', $company) }}" class="ui-button ui-button-secondary">Material Catalog</a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'price.cost.view'))
                    <a href="{{ route('companies.price-books.index', $company) }}" class="ui-button ui-button-secondary">Price Books</a>
                @endif
                @if (auth()->user()->hasCompanyPermission($company, 'supplier.create'))
                    <a href="{{ route('companies.suppliers.create', $company) }}" class="ui-button ui-button-primary">Add Supplier</a>
                @endif
            </div>
        </header>

        <section class="ui-stats-grid">
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">SP</span></div><strong class="ui-stat-value">{{ number_format($stats['total']) }}</strong><span class="ui-stat-label">Suppliers</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">AC</span></div><strong class="ui-stat-value">{{ number_format($stats['active']) }}</strong><span class="ui-stat-label">Active</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">IN</span></div><strong class="ui-stat-value">{{ number_format($stats['inactive']) }}</strong><span class="ui-stat-label">Inactive</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">BL</span></div><strong class="ui-stat-value">{{ number_format($stats['blacklisted']) }}</strong><span class="ui-stat-label">Blacklisted</span></article>
        </section>

        <section class="ui-toolbar">
            <form method="GET" action="{{ route('companies.suppliers.index', $company) }}" class="ui-filter-form">
                <div class="ui-field ui-field-grow">
                    <label class="ui-label" for="supplier-search">Search suppliers</label>
                    <input id="supplier-search" class="ui-input" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Supplier code, name, contact, email, or phone">
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="supplier-status">Status</label>
                    <select id="supplier-status" class="ui-select" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All status</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        <option value="blacklisted" @selected($filters['status'] === 'blacklisted')>Blacklisted</option>
                    </select>
                </div>
                <button type="submit" class="ui-button ui-button-secondary">Apply Filters</button>
                @if ($filters['search'] !== '' || $filters['status'] !== 'all')
                    <a href="{{ route('companies.suppliers.index', $company) }}" class="ui-button ui-button-secondary">Clear</a>
                @endif
            </form>
        </section>

        <section class="ui-table-shell">
            @if ($suppliers->isEmpty())
                <div class="ui-empty"><div><h3>No suppliers found</h3><p>Create supplier records before building company price books.</p></div></div>
            @else
                <div class="ui-table-scroll">
                    <table class="ui-table">
                        <thead><tr><th>Supplier</th><th>Contact</th><th>Lead Time</th><th>Price Books</th><th>Price Items</th><th>Status</th><th aria-label="Actions"></th></tr></thead>
                        <tbody>
                            @foreach ($suppliers as $supplier)
                                <tr>
                                    <td><div class="ui-table-primary">{{ $supplier->name }}</div><div class="ui-table-secondary">{{ $supplier->supplier_code }}</div></td>
                                    <td><div class="ui-table-primary">{{ $supplier->contact_person ?? '—' }}</div><div class="ui-table-secondary">{{ $supplier->email ?? $supplier->phone ?? 'No contact data' }}</div></td>
                                    <td>{{ $supplier->lead_time_days ? $supplier->lead_time_days . ' days' : '—' }}</td>
                                    <td>{{ number_format($supplier->price_books_count) }}</td>
                                    <td>{{ number_format($supplier->price_book_items_count) }}</td>
                                    <td>
                                        @if ($supplier->status === 'active')
                                            <span class="ui-status ui-status-active">Active</span>
                                        @elseif ($supplier->status === 'blacklisted')
                                            <span class="ui-status ui-status-inactive">Blacklisted</span>
                                        @else
                                            <span class="ui-status ui-status-pending">Inactive</span>
                                        @endif
                                    </td>
                                    <td><div class="ui-actions"><a href="{{ route('companies.suppliers.show', [$company, $supplier->id]) }}" class="ui-button ui-button-secondary ui-button-compact">Open</a></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="ui-table-footer">@include('partials.pagination', ['paginator' => $suppliers])</div>
            @endif
        </section>
    </div>
@endsection
