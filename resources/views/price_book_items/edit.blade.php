@extends('layouts.app')

@section('title', 'Edit Price Item')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Price book item</div>
                <h2 class="ui-page-title">Edit Price Item</h2>
                <p class="ui-page-description">Update supplier cost, selling reference, availability, and validity for {{ $priceBook->name }}.</p>
            </div>
            <div class="ui-page-actions">
                <a href="{{ route('companies.price-books.show', [$company, $priceBook->id]) }}" class="ui-button ui-button-secondary">Back to Price Book</a>
            </div>
        </header>

        <section class="ui-card">
            <div class="ui-card-body">
                <div class="ui-info-grid">
                    <div class="ui-info-item"><span>Price Book</span><strong>{{ $priceBook->name }}</strong></div>
                    <div class="ui-info-item"><span>Currency</span><strong>{{ $priceBook->currency }}</strong></div>
                    <div class="ui-info-item"><span>Material</span><strong>{{ $priceBookItem->material?->name ?? 'Material unavailable' }}</strong></div>
                    <div class="ui-info-item"><span>Current Availability</span><strong>{{ $priceBookItem->availability_label }}</strong></div>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('companies.price-books.items.update', [$company, $priceBook->id, $priceBookItem->id]) }}" class="ui-card">
            @csrf
            @method('PUT')
            <div class="ui-card-body">
                @include('price_book_items.partials.form', ['submitLabel' => 'Update Price Item'])
            </div>
        </form>
    </div>
@endsection
