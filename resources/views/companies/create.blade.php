@extends('layouts.app')

@section('title', 'Add Company')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Add Company')

@push('styles')
    <link href="{{ asset('css/nexorin-company-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-company-governance.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxcg-editor" data-nxcg-editor data-nxcg-mode="create">
        <header class="nxcg-editor-head" data-nxcg-reveal>
            <div>
                <span class="nxcg-kicker">Controlled tenant registration</span>
                <h2>Create Company</h2>
                <p>Register a new organization, establish its isolated workspace identity, and define the initial access state before memberships and company roles are assigned.</p>
            </div>
            <div class="nxcg-editor-head-actions">
                <span class="nxcg-engine-state"><i></i> Tenant provisioning ready</span>
                <a href="{{ route('companies.index') }}" class="nxcg-button nxcg-button-secondary" data-nxcg-navigate="directory">Back to Companies</a>
            </div>
        </header>

        @if ($errors->any())
            <section class="nx-feedback nx-feedback--danger" data-alert data-nxcg-reveal>
                <div class="nx-feedback-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 18h.01"/></svg></div>
                <div class="nx-feedback-body">
                    <span class="nx-feedback-kicker">Company registration requires attention</span>
                    <strong class="nx-feedback-title">Review the highlighted company fields</strong>
                    <p class="nx-feedback-copy">The new tenant cannot be created until the validation issues below are resolved.</p>
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
            action="{{ route('companies.store') }}"
            class="nxcg-editor-form"
            data-nxcg-editor-form
        >
            @csrf
            @include('companies.partials.form')
        </form>
    </div>
@endsection
