@extends('layouts.app')

@section('title', 'Create Company')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Create Company')
@section('force-theme', 'dark')
@section('body-class', 'page-company-create-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-company-governance.css') }}?v=55a" rel="stylesheet">
    <link href="{{ asset('css/nexorin-company-create-v6.css') }}?v=004i" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-company-create-v6.js') }}?v=004i" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
    <div class="nxcg-editor nxcg-create-v6" data-nxcg-editor data-nxcg-mode="create" data-nxcg-create-v6>
        <header class="nxcg-create-head" data-nxcg-reveal>
            <div class="nxcg-create-head-copy">
                <div class="nxcg-create-title-row">
                    <h2>Create Company</h2>
                    <span class="nxcg-create-security-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg>
                        Super Admin
                    </span>
                </div>
                <p>Create the company record that anchors company-scoped workflows in NEXORIN. Memberships and company roles remain assigned separately after creation.</p>
            </div>

            <div class="nxcg-create-head-actions">
                <a href="{{ route('companies.index') }}" class="nxcg-button nxcg-button-secondary" data-nxcg-leave-link data-nxcg-navigate="directory">Discard</a>
                <button type="submit" form="companyCreateForm" class="nxcg-button nxcg-button-primary nxcg-create-submit" data-nxcg-submit>
                    Create Company
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </button>
            </div>
        </header>

        @if ($errors->any())
            <section class="nx-feedback nx-feedback--danger nxcg-create-validation-alert" data-alert data-nxcg-reveal role="alert">
                <div class="nx-feedback-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 18h.01"/></svg></div>
                <div class="nx-feedback-body">
                    <span class="nx-feedback-kicker">Company registration requires attention</span>
                    <strong class="nx-feedback-title">Review the highlighted fields</strong>
                    <p class="nx-feedback-copy">The company record was not created because server-side validation did not pass.</p>
                    <ul class="nx-feedback-list">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            </section>
        @endif

        <nav class="nxcg-create-steps" aria-label="Company creation sections" data-nxcg-section-nav data-nxcg-reveal>
            <a href="#company-identity" class="is-active" data-nxcg-scroll data-nxcg-create-step="identity"><span>1</span><b>Company Basics</b></a>
            <i aria-hidden="true"></i>
            <a href="#company-contact" data-nxcg-scroll data-nxcg-create-step="contact"><span>2</span><b>Contact Details</b></a>
            <i aria-hidden="true"></i>
            <a href="#company-legal" data-nxcg-scroll data-nxcg-create-step="legal"><span>3</span><b>Legal Registration</b></a>
            <i aria-hidden="true"></i>
            <a href="#company-access" data-nxcg-scroll data-nxcg-create-step="access"><span>4</span><b>Workspace Access</b></a>
            <i aria-hidden="true"></i>
            <a href="#company-review" data-nxcg-scroll data-nxcg-create-step="review"><span>5</span><b>Review & Create</b></a>
        </nav>

        <form
            id="companyCreateForm"
            method="POST"
            action="{{ route('companies.store') }}"
            class="nxcg-editor-form nxcg-create-form"
            data-nxcg-editor-form
        >
            @csrf
            @include('companies.partials.create-v6-form')
        </form>
    </div>
@endsection
