@extends('layouts.app')

@section('title', 'Create Material')
@section('page-eyebrow', 'Material catalog')
@section('page-heading', 'Create Material')
@section('force-theme', 'dark')
@section('body-class', 'page-material-create-v6')

@push('styles')
    <link href="{{ asset('css/nexorin-material-catalog.css') }}?v=63a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-material-catalog-refinement.css') }}?v=1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-material-create.css') }}?v=13" rel="stylesheet">
@endpush

@section('content')
    <div class="nxmc-editor nxmci-create">
        <section class="nxmc-editor-hero nxmc-reveal">
            <div>
                <span class="nxmc-kicker">Material catalog intake</span>
                <h2>Register a controlled engineering material record.</h2>
                <p>Create a company catalog draft with exact identity, engineering classification, and manufacturer-backed technical data. Supporting evidence is attached after creation, while the preview follows the selected engineering component class.</p>
            </div>
            <div class="nxmci-hero-actions">
                <span class="nxmci-hero-state"><i></i>Draft on creation</span>
                <a href="{{ route('companies.materials.index', $company) }}" class="nxmc-button nxmc-button-secondary" data-nxmc-navigate="directory">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    Material Catalog
                </a>
            </div>
        </section>

        <form method="POST" action="{{ route('companies.materials.store', $company) }}" data-nxmc-editor-form data-nxmc-editor-mode="create">
            @csrf
            @include('materials.partials.form', ['submitLabel' => 'Create Draft Material'])
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-material-catalog.js') }}?v=76" defer></script>
@endpush

