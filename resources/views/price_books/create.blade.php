@extends('layouts.app')

@section('title', 'Add Price Book')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Price book</div>
                <h2 class="ui-page-title">Add Price Book</h2>
                <p class="ui-page-description">Create a controlled price reference for catalog-based BOQ pricing.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('companies.price-books.store', $company) }}" class="ui-card">
            @csrf
            <div class="ui-card-body">
                @include('price_books.partials.form', ['submitLabel' => 'Create Price Book'])
            </div>
        </form>
    </div>
@endsection
