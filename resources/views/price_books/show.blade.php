@extends('layouts.app')

@section('title', $priceBook->name)

@section('content')
    @php
        $canManagePriceItems = auth()->user()->hasCompanyPermission($company, 'price.cost.update');
        $newPriceBookItem = new \App\Models\PriceBookItem([
            'unit_cost' => 0,
            'availability_status' => 'available',
            'supplier_id' => $priceBook->supplier_id,
        ]);
    @endphp

    <div class="ui-page">
        <section class="ui-detail-hero">
            <div class="ui-detail-avatar">PB</div>
            <div class="ui-detail-copy">
                <h2>{{ $priceBook->name }}</h2>
                <p>{{ $priceBook->code }} · {{ $priceBook->currency }} · {{ $priceBook->status_label }}</p>
            </div>
            <div class="ui-detail-actions">
                <a href="{{ route('companies.price-books.index', $company) }}" class="ui-button ui-button-secondary">Back to Price Books</a>
                @if ($canManagePriceItems)
                    <a href="{{ route('companies.price-books.items.import.form', [$company, $priceBook->id]) }}" class="ui-button ui-button-secondary">Import CSV</a>
                    <a href="{{ route('companies.price-books.edit', [$company, $priceBook->id]) }}" class="ui-button ui-button-primary">Edit Price Book</a>
                @endif
            </div>
        </section>

        <section class="ui-stats-grid">
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">ST</span></div><strong class="ui-stat-value">{{ $priceBook->status_label }}</strong><span class="ui-stat-label">Status</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">IT</span></div><strong class="ui-stat-value">{{ number_format($itemStats['total']) }}</strong><span class="ui-stat-label">Price Items</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">AV</span></div><strong class="ui-stat-value">{{ number_format($itemStats['available']) }}</strong><span class="ui-stat-label">Available</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">EX</span></div><strong class="ui-stat-value">{{ number_format($itemStats['expired']) }}</strong><span class="ui-stat-label">Expired</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">OB</span></div><strong class="ui-stat-value">{{ number_format($itemStats['obsolete']) }}</strong><span class="ui-stat-label">Obsolete</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">LM</span></div><strong class="ui-stat-value">{{ number_format($itemStats['low_margin']) }}</strong><span class="ui-stat-label">Low Margin</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">MS</span></div><strong class="ui-stat-value">{{ number_format($itemStats['missing_cost']) }}</strong><span class="ui-stat-label">Missing Cost</span></article>
            <article class="ui-stat-card"><div class="ui-stat-head"><span class="ui-stat-icon">SP</span></div><strong class="ui-stat-value">{{ $priceBook->supplier?->supplier_code ?? 'Mixed' }}</strong><span class="ui-stat-label">Supplier</span></article>
        </section>

        <div class="ui-detail-grid">
            <section class="ui-card">
                <div class="ui-card-header"><div><h3 class="ui-card-title">Price Book Profile</h3><p class="ui-card-description">Header data used to control price validity and commercial source.</p></div></div>
                <div class="ui-card-body">
                    <div class="ui-info-grid">
                        <div class="ui-info-item"><span>Supplier</span><strong>{{ $priceBook->supplier?->name ?? 'Internal / mixed suppliers' }}</strong></div>
                        <div class="ui-info-item"><span>Pricing Basis</span><strong>{{ str($priceBook->pricing_basis)->replace('_', ' ')->title() }}</strong></div>
                        <div class="ui-info-item"><span>Effective From</span><strong>{{ $priceBook->effective_from?->format('d M Y') ?? 'Not set' }}</strong></div>
                        <div class="ui-info-item"><span>Effective Until</span><strong>{{ $priceBook->effective_until?->format('d M Y') ?? 'Open' }}</strong></div>
                    </div>
                    @if ($priceBook->notes)
                        <p class="ui-card-description" style="margin-top: 18px;">{{ $priceBook->notes }}</p>
                    @endif
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header"><div><h3 class="ui-card-title">Control Actions</h3><p class="ui-card-description">Activate or archive price books before they are connected to live BOQ pricing.</p></div></div>
                <div class="ui-card-body">
                    @if ($canManagePriceItems)
                        <div class="ui-form-actions" style="justify-content: flex-start;">
                            @if ($priceBook->status === 'active')
                                <form method="POST" action="{{ route('companies.price-books.archive', [$company, $priceBook->id]) }}" data-confirm="Archive this price book?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ui-button ui-button-danger" type="submit">Archive Price Book</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('companies.price-books.activate', [$company, $priceBook->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="ui-button ui-button-primary" type="submit">Activate Price Book</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <p class="ui-card-description">You can view this price book, but updating cost references requires cost-price permission.</p>
                    @endif
                </div>
            </section>
        </div>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Price Health Dashboard</h3>
                    <p class="ui-card-description">Quick procurement readiness check for missing cost, expired quote, unavailable item, low margin, and missing selling reference.</p>
                </div>
            </div>
            <div class="ui-card-body">
                <div class="ui-info-grid">
                    <div class="ui-info-item"><span>Healthy Items</span><strong>{{ number_format($itemStats['healthy']) }}</strong></div>
                    <div class="ui-info-item"><span>Expired Quotes</span><strong>{{ number_format($itemStats['expired']) }}</strong></div>
                    <div class="ui-info-item"><span>Obsolete Quotes</span><strong>{{ number_format($itemStats['obsolete']) }}</strong></div>
                    <div class="ui-info-item"><span>Unavailable Items</span><strong>{{ number_format($itemStats['unavailable']) }}</strong></div>
                    <div class="ui-info-item"><span>Low Margin Items</span><strong>{{ number_format($itemStats['low_margin']) }}</strong></div>
                    <div class="ui-info-item"><span>No Selling Reference</span><strong>{{ number_format($itemStats['missing_selling_reference']) }}</strong></div>
                </div>
            </div>
        </section>

        @if ($canManagePriceItems)
            <form method="POST" action="{{ route('companies.price-books.items.store', [$company, $priceBook->id]) }}" class="ui-card">
                @csrf
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Add Price Item</h3>
                        <p class="ui-card-description">Add one active catalog material with supplier cost, availability, lead time, and validity.</p>
                    </div>
                </div>
                <div class="ui-card-body">
                    @if ($materials->isEmpty())
                        <div class="ui-empty ui-empty-compact"><div><h3>No material available to add</h3><p>All active visible materials are already linked to this price book, or the catalog is still empty.</p></div></div>
                    @else
                        @include('price_book_items.partials.form', [
                            'priceBookItem' => $newPriceBookItem,
                            'submitLabel' => 'Add Price Item',
                        ])
                    @endif
                </div>
            </form>
        @endif

        @if ($canManagePriceItems && $priceBook->items->isNotEmpty())
            <form method="POST" action="{{ route('companies.price-books.items.bulk-update', [$company, $priceBook->id]) }}" class="ui-card">
                @csrf
                @method('PATCH')
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Bulk Price Update</h3>
                        <p class="ui-card-description">Update multiple costs, selling references, lead times, availability, and quote statuses in one controlled action.</p>
                    </div>
                </div>
                <div class="ui-card-body">
                    @error('items') <div class="ui-error" style="margin-bottom: 12px;">{{ $message }}</div> @enderror
                    <div class="ui-table-scroll">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Unit Cost</th>
                                    <th>Selling Price</th>
                                    <th>Margin %</th>
                                    <th>MOQ</th>
                                    <th>Lead</th>
                                    <th>Availability</th>
                                    <th>Quote</th>
                                    <th>Quoted</th>
                                    <th>Valid Until</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($priceBook->items as $item)
                                    <tr>
                                        <td>
                                            <div class="ui-table-primary">{{ $item->material?->name ?? 'Material unavailable' }}</div>
                                            <div class="ui-table-secondary">{{ $item->material?->sku ?? 'No SKU' }}</div>
                                        </td>
                                        <td><input class="ui-input" type="number" step="0.01" min="0" name="items[{{ $item->id }}][unit_cost]" value="{{ old('items.'.$item->id.'.unit_cost', $item->unit_cost) }}" required></td>
                                        <td><input class="ui-input" type="number" step="0.01" min="0" name="items[{{ $item->id }}][selling_price]" value="{{ old('items.'.$item->id.'.selling_price', $item->selling_price) }}"></td>
                                        <td><input class="ui-input" type="number" step="0.01" min="0" max="1000" name="items[{{ $item->id }}][margin_percent]" value="{{ old('items.'.$item->id.'.margin_percent', $item->margin_percent) }}"></td>
                                        <td><input class="ui-input" type="number" step="0.001" min="0" name="items[{{ $item->id }}][minimum_order_quantity]" value="{{ old('items.'.$item->id.'.minimum_order_quantity', $item->minimum_order_quantity) }}"></td>
                                        <td><input class="ui-input" type="number" min="0" max="3650" name="items[{{ $item->id }}][lead_time_days]" value="{{ old('items.'.$item->id.'.lead_time_days', $item->lead_time_days) }}"></td>
                                        <td>
                                            <select class="ui-select" name="items[{{ $item->id }}][availability_status]" required>
                                                @foreach ($availabilityStatuses as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('items.'.$item->id.'.availability_status', $item->availability_status) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="ui-select" name="items[{{ $item->id }}][quote_status]" required>
                                                @foreach ($quoteStatuses as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('items.'.$item->id.'.quote_status', $item->quote_status ?? 'active') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input class="ui-input" type="date" name="items[{{ $item->id }}][quoted_at]" value="{{ old('items.'.$item->id.'.quoted_at', $item->quoted_at?->format('Y-m-d')) }}"></td>
                                        <td><input class="ui-input" type="date" name="items[{{ $item->id }}][valid_until]" value="{{ old('items.'.$item->id.'.valid_until', $item->valid_until?->format('Y-m-d')) }}"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="ui-form-actions">
                        <button type="submit" class="ui-button ui-button-primary">Save Bulk Update</button>
                    </div>
                </div>
            </form>
        @endif

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Price Items</h3>
                    <p class="ui-card-description">Material price links used by future catalog-based BOQ pricing, quotation, and procurement checks.</p>
                </div>
            </div>
            <div class="ui-card-body">
                @if ($priceBook->items->isEmpty())
                    <div class="ui-empty ui-empty-compact"><div><h3>No price items yet</h3><p>Add active catalog materials to build a usable supplier or internal price reference.</p></div></div>
                @else
                    <div class="ui-table-scroll">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Supplier</th>
                                    <th>Unit Cost</th>
                                    <th>Selling Reference</th>
                                    <th>MOQ / Lead Time</th>
                                    <th>Availability</th>
                                    <th>Quote Status</th>
                                    <th>Valid Until</th>
                                    @if ($canManagePriceItems)
                                        <th aria-label="Actions"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($priceBook->items as $item)
                                    <tr>
                                        <td>
                                            <div class="ui-table-primary">{{ $item->material?->name ?? 'Material unavailable' }}</div>
                                            <div class="ui-table-secondary">{{ $item->material?->sku ?? 'No SKU' }}{{ $item->material?->category ? ' · '.$item->material->category->name : '' }}{{ $item->supplier_sku ? ' · Supplier SKU '.$item->supplier_sku : '' }}</div>
                                        </td>
                                        <td>{{ $item->supplier?->name ?? $priceBook->supplier?->name ?? 'Internal / mixed' }}</td>
                                        <td>{{ $priceBook->currency }} {{ number_format((float) $item->unit_cost, 0, ',', '.') }}</td>
                                        <td>
                                            <div class="ui-table-primary">{{ $priceBook->currency }} {{ number_format($item->effective_selling_price, 0, ',', '.') }}</div>
                                            <div class="ui-table-secondary">
                                                @if ($item->selling_price !== null)
                                                    Manual selling price
                                                @elseif ($item->margin_percent !== null)
                                                    Margin {{ number_format((float) $item->margin_percent, 2) }}%
                                                @else
                                                    Cost fallback
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="ui-table-primary">MOQ {{ $item->minimum_order_quantity !== null ? number_format((float) $item->minimum_order_quantity, 3) : 'Not set' }}</div>
                                            <div class="ui-table-secondary">{{ $item->lead_time_days !== null ? $item->lead_time_days.' days' : 'Lead time not set' }}</div>
                                        </td>
                                        <td>
                                            @if ($item->availability_status === 'available')
                                                <span class="ui-status ui-status-active">{{ $item->availability_label }}</span>
                                            @elseif (in_array($item->availability_status, ['limited', 'backorder'], true))
                                                <span class="ui-status ui-status-pending">{{ $item->availability_label }}</span>
                                            @else
                                                <span class="ui-status ui-status-inactive">{{ $item->availability_label }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($item->derived_quote_status === 'active')
                                                <span class="ui-status ui-status-active">{{ $item->derived_quote_status_label }}</span>
                                            @elseif ($item->derived_quote_status === 'expired')
                                                <span class="ui-status ui-status-pending">{{ $item->derived_quote_status_label }}</span>
                                            @else
                                                <span class="ui-status ui-status-inactive">{{ $item->derived_quote_status_label }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="ui-table-primary">{{ $item->valid_until?->format('d M Y') ?? 'Open' }}</div>
                                            @if ($item->is_expired)
                                                <div class="ui-table-secondary">Expired</div>
                                            @elseif (! $item->has_selling_reference)
                                                <div class="ui-table-secondary">No selling reference</div>
                                            @else
                                                <div class="ui-table-secondary">Ready</div>
                                            @endif
                                        </td>
                                        @if ($canManagePriceItems)
                                            <td>
                                                <div class="ui-actions">
                                                    <a href="{{ route('companies.price-books.items.edit', [$company, $priceBook->id, $item->id]) }}" class="ui-button ui-button-secondary ui-button-compact">Edit</a>
                                                    <form method="POST" action="{{ route('companies.price-books.items.destroy', [$company, $priceBook->id, $item->id]) }}" data-confirm="Remove this price item?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="ui-button ui-button-danger ui-button-compact">Remove</button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
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
