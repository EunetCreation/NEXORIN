@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
    <div class="ui-page">
        <section class="ui-detail-hero">
            <div class="ui-detail-avatar">SP</div>
            <div class="ui-detail-copy">
                <h2>{{ $supplier->name }}</h2>
                <p>{{ $supplier->supplier_code }} · {{ $supplier->status_label }}</p>
            </div>
            <div class="ui-detail-actions">
                <a href="{{ route('companies.suppliers.index', $company) }}" class="ui-button ui-button-secondary">Back to Suppliers</a>
                @if (auth()->user()->hasCompanyPermission($company, 'supplier.update'))
                    <a href="{{ route('companies.suppliers.edit', [$company, $supplier->id]) }}" class="ui-button ui-button-primary">Edit Supplier</a>
                @endif
            </div>
        </section>

        <div class="ui-detail-grid">
            <section class="ui-card">
                <div class="ui-card-header"><div><h3 class="ui-card-title">Supplier Profile</h3><p class="ui-card-description">Procurement identity and commercial contact information.</p></div></div>
                <div class="ui-card-body">
                    <div class="ui-info-grid">
                        <div class="ui-info-item"><span>Contact Person</span><strong>{{ $supplier->contact_person ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Email</span><strong>{{ $supplier->email ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Phone</span><strong>{{ $supplier->phone ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Website</span><strong>{{ $supplier->website ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Payment Terms</span><strong>{{ $supplier->payment_terms ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Lead Time</span><strong>{{ $supplier->lead_time_days ? $supplier->lead_time_days . ' days' : '—' }}</strong></div>
                    </div>
                    @if ($supplier->address)
                        <p class="ui-card-description" style="margin-top: 18px;">{{ $supplier->address }}</p>
                    @endif
                    @if ($supplier->notes)
                        <p class="ui-card-description" style="margin-top: 10px;">{{ $supplier->notes }}</p>
                    @endif
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header"><div><h3 class="ui-card-title">Supplier Control</h3><p class="ui-card-description">Status and associated catalog pricing records.</p></div></div>
                <div class="ui-card-body">
                    <div class="ui-info-grid">
                        <div class="ui-info-item"><span>Status</span><strong>{{ $supplier->status_label }}</strong></div>
                        <div class="ui-info-item"><span>Price Books</span><strong>{{ number_format($supplier->priceBooks->count()) }}</strong></div>
                        <div class="ui-info-item"><span>Price Items</span><strong>{{ number_format($supplier->priceBookItems->count()) }}</strong></div>
                        <div class="ui-info-item"><span>Tax Number</span><strong>{{ $supplier->tax_number ?? '—' }}</strong></div>
                    </div>
                    @if (auth()->user()->hasCompanyPermission($company, 'supplier.update'))
                        <div class="ui-form-actions" style="margin-top: 18px; justify-content: flex-start;">
                            @if ($supplier->status === 'active')
                                <form method="POST" action="{{ route('companies.suppliers.archive', [$company, $supplier->id]) }}" data-confirm="Archive this supplier?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ui-button ui-button-danger" type="submit">Archive Supplier</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('companies.suppliers.activate', [$company, $supplier->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="ui-button ui-button-primary" type="submit">Activate Supplier</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <section class="ui-card">
            <div class="ui-card-header"><div><h3 class="ui-card-title">Latest Price Books</h3><p class="ui-card-description">Price books linked to this supplier.</p></div></div>
            <div class="ui-card-body">
                @if (! auth()->user()->hasCompanyPermission($company, 'price.cost.view'))
                    <div class="ui-empty ui-empty-compact"><div><h3>Price books are restricted</h3><p>Viewing supplier price books requires cost-price permission.</p></div></div>
                @elseif ($supplier->priceBooks->isEmpty())
                    <div class="ui-empty ui-empty-compact"><div><h3>No price books yet</h3><p>Create a price book to connect supplier prices to material catalog items.</p></div></div>
                @else
                    <div class="ui-table-scroll">
                        <table class="ui-table">
                            <thead><tr><th>Price Book</th><th>Status</th><th>Effective</th><th>Items</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($supplier->priceBooks as $priceBook)
                                    <tr>
                                        <td><div class="ui-table-primary">{{ $priceBook->name }}</div><div class="ui-table-secondary">{{ $priceBook->code }}</div></td>
                                        <td>{{ $priceBook->status_label }}</td>
                                        <td>{{ $priceBook->effective_from?->format('d M Y') ?? '—' }}</td>
                                        <td>{{ number_format($priceBook->items_count ?? 0) }}</td>
                                        <td><a href="{{ route('companies.price-books.show', [$company, $priceBook->id]) }}" class="ui-button ui-button-secondary ui-button-compact">Open</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
