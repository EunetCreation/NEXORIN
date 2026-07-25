@extends('layouts.app')

@section('title', 'Edit Material')
@section('page-eyebrow', 'Catalog governance')
@section('page-heading', 'Edit Material')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=63a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-material-catalog-refinement.css') }}?v=1" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-editor">
        <section class="nxmc-editor-hero nxmc-reveal">
            <div>
                <span class="nxmc-kicker">Controlled material revision</span>
                <h2>Refine the catalog basis.</h2>
                <p>Update the company material while preserving its stable identity, applicability, technical trace, and downstream price-book and BOQ governance.</p>
            </div>
            <a href="{{ route('companies.materials.show', [$company, $material->id]) }}" class="nxmc-button nxmc-button-secondary" data-nxmc-navigate="open" data-nxmc-material="{{ $material->name }}" data-nxmc-sku="{{ $material->sku }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                Material Record
            </a>
        </section>

        <form method="POST" action="{{ route('companies.materials.update', [$company, $material->id]) }}" data-nxmc-editor-form data-nxmc-editor-mode="edit">
            @csrf
            @method('PUT')
            @include('materials.partials.form', ['submitLabel' => 'Update Material'])
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=63a" defer></script>
@endpush

