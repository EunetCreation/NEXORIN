@extends('layouts.app')

@section('title', 'Edit Customer')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Edit Customer')

@push('styles')
    <link href="{{ asset('css/nexorin-customer-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@section('content')
    <div class="nxcus-editor" data-nxcus-editor data-nxcus-mode="edit" data-company-name="{{ $company->name }}" data-customer-name="{{ $customer->name }}" data-initial-type="{{ $customer->type }}" data-initial-portal="{{ $customer->portal_user_id ?: '' }}">
        <section class="nxcus-editor-hero" data-nxcus-reveal>
            <div>
                <span class="nxcus-kicker"><i></i>Controlled relationship revision</span>
                <h2>Edit {{ $customer->name }}</h2>
                <p>Revise identity, contact, location, and portal ownership. Saved changes affect future relationship use while existing project snapshots remain preserved.</p>
                <div class="nxcus-editor-hero-meta"><span><small>Customer number</small><strong>{{ $customer->customer_number }}</strong></span><span><small>Current type</small><strong>{{ $customer->type_label }}</strong></span><span><small>Latest update</small><strong>{{ $customer->updated_at?->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</strong></span></div>
            </div>
            <aside class="nxcus-editor-preview {{ $customer->portal_user_id ? 'has-portal' : '' }}" data-nxcus-editor-preview>
                <span class="nxcus-editor-preview-icon" aria-hidden="true"><svg class="icon-residential" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 23 24 9l17 14v19H7z"/><circle cx="24" cy="24" r="5"/><path d="M14 41c1.2-7 4.5-10.5 10-10.5S32.8 34 34 41"/></svg><svg class="icon-commercial" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 42V11l15-5 15 5v31"/><path d="M16 16h4M28 16h4M16 23h4M28 23h4M16 30h4M28 30h4M20 42V35h8v7"/></svg></span>
                <div class="nxcus-editor-preview-copy"><small>RELATIONSHIP PREVIEW</small><strong data-nxcus-preview-name>{{ $customer->name }}</strong><span data-nxcus-preview-contact>{{ $customer->email ?: ($customer->formatted_phone ?: 'Contact not assigned') }}</span></div>
                <div class="nxcus-editor-preview-grid"><span><small>Type</small><strong data-nxcus-preview-type>{{ $customer->type_label }}</strong></span><span><small>Portal</small><strong data-nxcus-preview-portal>{{ $customer->portalUser?->name ?? 'Not linked' }}</strong></span><span><small>Location</small><strong data-nxcus-preview-location>{{ $customer->city ?: 'Not established' }}</strong></span><span><small>Readiness</small><strong data-nxcus-preview-readiness>Saved</strong></span></div>
                <span class="nxcus-dirty-state" data-nxcus-preview-dirty>SAVED BASELINE</span>
            </aside>
        </section>

        <nav class="nxcus-editor-nav" data-nxcus-section-nav aria-label="Customer revision sections"><a href="#customer-identity" class="is-active">Identity</a><a href="#contact-location">Contact &amp; Location</a><a href="#portal-access">Portal Access</a><a href="#relationship-notes">Internal Notes</a></nav>

        <form method="POST" action="{{ route('companies.customers.update', [$company, $customer]) }}" class="nxcus-editor-form" data-nxcus-customer-form>
            @csrf @method('PUT')
            @include('customers.partials.form', ['customer' => $customer])
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-customer-governance.js') }}?v=55a" defer></script>
@endpush
