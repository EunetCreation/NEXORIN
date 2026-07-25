@extends('layouts.app')

@section('title', 'Add Material')
@section('page-eyebrow', 'Catalog governance')
@section('page-heading', 'Add Material')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=63a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-material-catalog-refinement.css') }}?v=1" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-editor">
        <section class="nxmc-editor-hero nxmc-reveal">
            <div>
                <span class="nxmc-kicker">Controlled material intake</span>
                <h2>Create a traceable catalog record.</h2>
                <p>Define stable identity, applicability, technical attributes, and governance context before the material enters datasheet, pricing, BOM, and quotation workflows.</p>
            </div>
            <a href="{{ route('companies.materials.index', $company) }}" class="nxmc-button nxmc-button-secondary" data-nxmc-navigate="directory">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                Material Catalog
            </a>
        </section>

        <form method="POST" action="{{ route('companies.materials.store', $company) }}" data-nxmc-editor-form data-nxmc-editor-mode="create">
            @csrf
            @include('materials.partials.form', ['submitLabel' => 'Create Material'])
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=63a" defer></script>
@endpush

