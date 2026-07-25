@extends('layouts.app')

@section('title', 'Edit Supplier')
@section('page-eyebrow', 'Procurement governance')
@section('page-heading', 'Edit Supplier')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=58b" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-editor nxpg-page">
        <section class="nxmc-editor-hero nxmc-reveal">
            <div>
                <span class="nxmc-kicker">Supplier maintenance</span>
                <h2>Refine the governed supplier record.</h2>
                <p>Update the commercial source profile while preserving clarity for price books, procurement readiness, and supplier governance history.</p>
            </div>
            <a href="{{ route('companies.suppliers.show', [$company, $supplier->id]) }}" class="nxmc-button nxmc-button-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                Supplier Record
            </a>
        </section>

        <form method="POST" action="{{ route('companies.suppliers.update', [$company, $supplier->id]) }}">
            @csrf
            @method('PUT')
            @include('suppliers.partials.form', ['submitLabel' => 'Update Supplier'])
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=58b" defer></script>
@endpush
