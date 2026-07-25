@extends('layouts.app')

@section('title', 'Add Price Book')
@section('page-eyebrow', 'Catalog pricing')
@section('page-heading', 'Add Price Book')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=58b" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-editor nxpg-page">
        <section class="nxmc-editor-hero nxmc-reveal">
            <div>
                <span class="nxmc-kicker">Controlled price source intake</span>
                <h2>Create a governed commercial header first.</h2>
                <p>Define the source, validity window, pricing basis, and governance status before material-level cost items are attached to the price book.</p>
            </div>
            <a href="{{ route('companies.price-books.index', $company) }}" class="nxmc-button nxmc-button-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                Price Books
            </a>
        </section>

        <form method="POST" action="{{ route('companies.price-books.store', $company) }}">
            @csrf
            @include('price_books.partials.form', ['submitLabel' => 'Create Price Book'])
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=58b" defer></script>
@endpush
