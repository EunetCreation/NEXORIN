@extends('layouts.app')

@section('title', 'Add Supplier')
@section('page-eyebrow', 'Procurement governance')
@section('page-heading', 'Add Supplier')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=58b" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-editor nxpg-page">
        <section class="nxmc-editor-hero nxmc-reveal">
            <div>
                <span class="nxmc-kicker">Controlled supplier intake</span>
                <h2>Create a reliable procurement source record.</h2>
                <p>Define the supplier’s commercial identity, delivery posture, and governance state before the record is used by price books, procurement tracking, or future BOQ pricing logic.</p>
            </div>
            <a href="{{ route('companies.suppliers.index', $company) }}" class="nxmc-button nxmc-button-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                Suppliers
            </a>
        </section>

        <form method="POST" action="{{ route('companies.suppliers.store', $company) }}">
            @csrf
            @include('suppliers.partials.form', ['submitLabel' => 'Create Supplier'])
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=58b" defer></script>
@endpush
