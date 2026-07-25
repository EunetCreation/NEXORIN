@extends('layouts.app')

@section('title', 'Add Customer')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Add Customer')

@push('styles')
    <link href="{{ asset('css/nexorin-customer-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@section('content')
    <div class="nxcus-editor" data-nxcus-editor data-nxcus-mode="create" data-company-name="{{ $company->name }}">
        <section class="nxcus-editor-hero" data-nxcus-reveal>
            <div>
                <span class="nxcus-kicker"><i></i>Controlled customer intake</span>
                <h2>Create Customer Relationship</h2>
                <p>Establish identity, classification, contact, location, and optional portal ownership inside {{ $company->name }}.</p>
                <div class="nxcus-editor-hero-meta">
                    <span><small>Customer number</small><strong>AUTO-GENERATED</strong></span>
                    <span><small>Company scope</small><strong>{{ $company->code }}</strong></span>
                    <span><small>Record lifecycle</small><strong>Active on creation</strong></span>
                </div>
            </div>
            <aside class="nxcus-editor-preview" data-nxcus-editor-preview>
                <span class="nxcus-editor-preview-icon" aria-hidden="true"><svg class="icon-residential" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 23 24 9l17 14v19H7z"/><circle cx="24" cy="24" r="5"/><path d="M14 41c1.2-7 4.5-10.5 10-10.5S32.8 34 34 41"/></svg><svg class="icon-commercial" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 42V11l15-5 15 5v31"/><path d="M16 16h4M28 16h4M16 23h4M28 23h4M16 30h4M28 30h4M20 42V35h8v7"/></svg></span>
                <div class="nxcus-editor-preview-copy"><small>RELATIONSHIP PREVIEW</small><strong data-nxcus-preview-name>New customer relationship</strong><span data-nxcus-preview-contact>Contact not assigned</span></div>
                <div class="nxcus-editor-preview-grid"><span><small>Type</small><strong data-nxcus-preview-type>Residential</strong></span><span><small>Portal</small><strong data-nxcus-preview-portal>Not linked</strong></span><span><small>Location</small><strong data-nxcus-preview-location>Not established</strong></span><span><small>Readiness</small><strong data-nxcus-preview-readiness>25%</strong></span></div>
                <span class="nxcus-dirty-state" data-nxcus-preview-dirty>NEW DRAFT</span>
            </aside>
        </section>

        <nav class="nxcus-editor-nav" data-nxcus-section-nav aria-label="Customer intake sections"><a href="#customer-identity" class="is-active">Identity</a><a href="#contact-location">Contact &amp; Location</a><a href="#portal-access">Portal Access</a><a href="#relationship-notes">Internal Notes</a></nav>

        <form method="POST" action="{{ route('companies.customers.store', $company) }}" class="nxcus-editor-form" data-nxcus-customer-form>
            @csrf
            @include('customers.partials.form')
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-customer-governance.js') }}?v=55a" defer></script>
@endpush
