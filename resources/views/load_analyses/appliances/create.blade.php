@extends('layouts.app')

@section('title', 'Add Equipment Load')
@section('page-eyebrow', 'Load analysis')
@section('page-heading', 'Add Equipment Load')
@section('force-theme', 'dark')
@section('body-class', 'page-load-appliance-create-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001015.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.015" rel="stylesheet">
    <link href="{{ asset('css/nexorin-load-appliance-create.css') }}?v=015f" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-load-appliance-create.js') }}?v=015f" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.015" defer></script>
@endpush

@section('content')
<main class="nx15" data-nx15-page>
    <header class="nx15-head">
        <div class="nx15-head-copy">
            <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}#equipment" class="nx15-back-link">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12.7 4.3 7 10l5.7 5.7"/></svg>
                <span>Back to Load Analysis</span>
            </a>
            <span class="nx15-kicker">Equipment load intake · unsaved entry</span>
            <h2>Add Equipment Load</h2>
            <p>Add one equipment or repeated load group to the current project Load Analysis. The preview is calculated from this unsaved entry and is not stored engineering authority.</p>
            <div class="nx15-head-meta" aria-label="Project references">
                <span class="nx15-mono">{{ $project->project_number ?: 'Project number not recorded' }}</span>
                <i aria-hidden="true"></i>
                <span>{{ $project->name }}</span>
                @if ($project->customer)
                    <i aria-hidden="true"></i>
                    <span>{{ $project->customer->name }}</span>
                @endif
            </div>
        </div>

        <div class="nx15-head-actions">
            <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}#equipment" class="nx15-button is-secondary">Cancel</a>
            <button type="submit" form="nx15EquipmentForm" class="nx15-button is-primary" data-nx15-submit>
                <span data-nx15-submit-label>Add Equipment</span>
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12"/></svg>
            </button>
        </div>
    </header>

    @if ($errors->any())
        <section class="nx15-validation" role="alert" aria-labelledby="nx15ValidationTitle">
            <div class="nx15-validation-icon" aria-hidden="true">!</div>
            <div>
                <strong id="nx15ValidationTitle">Review the highlighted equipment inputs</strong>
                <p>The entry was not saved. Correct the fields below and submit again.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <form id="nx15EquipmentForm" method="POST" action="{{ route('companies.load-analyses.appliances.store', [$company, $project]) }}" data-nx15-form>
        @csrf
        @include('load_analyses.appliances.partials.create-form')
    </form>
</main>
@endsection
