@extends('layouts.app')

@section('title', 'Edit Price Book')
@section('page-eyebrow', 'Catalog pricing')
@section('page-heading', 'Edit Price Book')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=58b" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-editor nxpg-page">
        <section class="nxmc-editor-hero nxmc-reveal">
            <div>
                <span class="nxmc-kicker">Commercial header maintenance</span>
                <h2>Refine the governed price book record.</h2>
                <p>Adjust the source, validity, and governance posture while keeping the commercial header readable for future price-item and BOQ workflows.</p>
            </div>
            <a href="{{ route('companies.price-books.show', [$company, $priceBook->id]) }}" class="nxmc-button nxmc-button-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                Price Book Record
            </a>
        </section>

        <form method="POST" action="{{ route('companies.price-books.update', [$company, $priceBook->id]) }}">
            @csrf
            @method('PUT')
            @include('price_books.partials.form', ['submitLabel' => 'Update Price Book'])
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=58b" defer></script>
@endpush
