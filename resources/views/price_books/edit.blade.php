@extends('layouts.app')

@section('title', 'Edit Price Book')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Price book</div>
                <h2 class="ui-page-title">Edit Price Book</h2>
                <p class="ui-page-description">Update the price book header and status while preserving price item audit visibility.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('companies.price-books.update', [$company, $priceBook->id]) }}" class="ui-card">
            @csrf
            @method('PUT')
            <div class="ui-card-body">
                @include('price_books.partials.form', ['submitLabel' => 'Update Price Book'])
            </div>
        </form>
    </div>
@endsection
