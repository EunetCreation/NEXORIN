@extends('layouts.app')

@section('title', 'Create Customer')
@section('page-eyebrow', 'Customer / CRM handoff')
@section('page-heading', 'Create Customer')
@section('force-theme', 'dark')
@section('body-class', 'page-customer-create-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-customer-create-v6.css') }}?v=008b" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-customer-create-v6.js') }}?v=008b" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
    @php
        $draftData = is_array($customerDraftData ?? null) ? $customerDraftData : [];
        $value = static fn (string $key, mixed $default = '') => old($key, data_get($draftData, $key, $default));
        $draftSavedAt = $customerDraft?->saved_at ?? $customerDraft?->updated_at;
    @endphp

    <div
        class="nxcus8"
        data-nxcus8
        data-draft-url="{{ route('companies.customers.create-draft.store', $company) }}"
        data-crm-ready="{{ $customerCrmReady ? 'true' : 'false' }}"
        data-company-name="{{ $company->name }}"
    >
        <header class="nxcus8-page-head">
            <div class="nxcus8-title-wrap">
                <div class="nxcus8-title-line">
                    <h2>Create Customer</h2>
                    <span class="nxcus8-autosave" data-draft-status>
                        <i aria-hidden="true"></i>
                        <span data-draft-status-copy>{{ $draftSavedAt ? 'Draft saved '.$draftSavedAt->diffForHumans() : 'Draft not saved yet' }}</span>
                    </span>
                </div>
            </div>
            <div class="nxcus8-head-actions">
                <button type="button" class="nxcus8-btn nxcus8-btn--ghost" data-save-draft @disabled(! $customerCrmReady)>Save Draft</button>
                <button type="submit" form="customerCreateForm" name="submit_intent" value="save_exit" class="nxcus8-btn nxcus8-btn--ghost">Save &amp; Exit</button>
                <button type="button" class="nxcus8-btn nxcus8-btn--primary" data-next-section="industry-segment">
                    Next: Industry Segment
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </button>
            </div>
        </header>

        @unless ($customerCrmReady)
            <section class="nxcus8-system-warning" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 17h.01"/></svg>
                <div>
                    <strong>Customer intake migration required</strong>
                    <p>Core customer creation remains available. Advanced CRM handoff fields stay disabled until the Page 008 migrations are applied.</p>
                </div>
            </section>
        @endunless

        @if ($errors->any())
            <section class="nxcus8-validation" role="alert">
                <div>
                    <span>Customer intake validation</span>
                    <strong>Review {{ $errors->count() }} {{ $errors->count() === 1 ? 'field' : 'fields' }} before saving.</strong>
                </div>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </section>
        @endif

        <nav class="nxcus8-stepper" aria-label="Customer onboarding sections" data-stepper>
            @foreach ([
                ['basic-information', 'Basic Information'],
                ['industry-segment', 'Industry Segment'],
                ['parent-company', 'Parent Company'],
                ['geography', 'Geography'],
                ['contacts', 'Contacts'],
                ['account-owner', 'Account Owner'],
                ['opportunity', 'Opportunity'],
                ['review-submit', 'Review & Submit'],
            ] as $index => [$anchor, $label])
                <button type="button" data-step-anchor="{{ $anchor }}" class="{{ $index === 0 ? 'is-active' : '' }}" aria-current="{{ $index === 0 ? 'step' : 'false' }}">
                    <span>{{ $index + 1 }}</span><b>{{ $label }}</b>
                </button>
            @endforeach
        </nav>

        <form id="customerCreateForm" method="POST" action="{{ route('companies.customers.store', $company) }}" enctype="multipart/form-data" data-customer-form novalidate>
            @csrf
            @include('customers.partials.create-v6-form', ['value' => $value, 'draftData' => $draftData])
        </form>
    </div>
@endsection
