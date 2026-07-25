@extends('layouts.app')

@section('title', 'Edit Company')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Edit Company')

@push('styles')
    <link href="{{ asset('css/nexorin-company-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-company-governance.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div
        class="nxcg-editor"
        data-nxcg-editor
        data-nxcg-mode="edit"
        data-nxcg-company-name="{{ $company->name }}"
        data-nxcg-company-code="{{ $company->code }}"
    >
        <header class="nxcg-editor-head" data-nxcg-reveal>
            <div>
                <span class="nxcg-kicker">Controlled tenant configuration</span>
                <h2>Edit {{ $company->name }}</h2>
                <p>Update identity, contact information, and workspace access. Changes are applied only after this controlled form is saved.</p>
            </div>
            <div class="nxcg-editor-head-actions">
                <span class="nxcg-engine-state"><i></i> Existing tenant context retained</span>
                <a href="{{ route('companies.show', $company) }}" class="nxcg-button nxcg-button-secondary" data-nxcg-navigate="open">View Company</a>
            </div>
        </header>

        @if ($errors->any())
            <section class="nx-feedback nx-feedback--danger" data-alert data-nxcg-reveal>
                <div class="nx-feedback-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 18h.01"/></svg></div>
                <div class="nx-feedback-body">
                    <span class="nx-feedback-kicker">Company update requires attention</span>
                    <strong class="nx-feedback-title">Review the highlighted company fields</strong>
                    <p class="nx-feedback-copy">The tenant configuration cannot be updated until the validation issues below are resolved.</p>
                    <ul class="nx-feedback-list">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            </section>
        @endif

        <nav class="nxcg-section-nav" data-nxcg-section-nav aria-label="Company editor sections" data-nxcg-reveal>
            <a href="#company-identity" class="is-active" data-nxcg-scroll>Identity</a>
            <a href="#company-contact" data-nxcg-scroll>Contact</a>
            <a href="#company-access" data-nxcg-scroll>Workspace Access</a>
        </nav>

        <form
            method="POST"
            action="{{ route('companies.update', $company) }}"
            class="nxcg-editor-form"
            data-nxcg-editor-form
            data-confirm="Save these company changes? Identity and access-state changes will update the controlled tenant workspace."
            data-confirm-title="Apply company configuration changes?"
            data-confirm-label="Save company changes"
            data-confirm-control="Tenant identity and access update"
            data-confirm-result="The saved company workspace will use the revised configuration"
        >
            @csrf
            @method('PUT')
            @include('companies.partials.form', ['company' => $company])
        </form>
    </div>
@endsection
