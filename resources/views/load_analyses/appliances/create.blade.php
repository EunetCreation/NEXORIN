@extends('layouts.app')

@section('title', 'Add Equipment Load')
@section('page-eyebrow', 'Load analysis')
@section('page-heading', 'Equipment Intake')

@push('styles')
    <link href="{{ asset('css/nexorin-load-analysis.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-load-analysis.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="nxla-equipment-page">
        <header class="nxla-equipment-header" data-nxla-reveal>
            <div class="nxla-equipment-header-copy">
                <span class="nxla-eyebrow">{{ $project->project_number }} · Equipment profile</span>
                <h2>Add equipment load</h2>
                <p>Create one clear equipment group and review its live load estimate before adding it to the project demand profile.</p>
            </div>
            <div class="nxla-equipment-header-actions">
                <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}#equipment" class="ui-button ui-button-secondary">Back to Load Analysis</a>
            </div>
        </header>

        <form id="nxlaEquipmentForm" method="POST" action="{{ route('companies.load-analyses.appliances.store', [$company, $project]) }}" data-nxla-equipment-form data-nxla-processing-form data-nxla-processing-target="#nxlaEquipmentShell">
            @csrf
            @include('load_analyses.appliances.partials.form')
        </form>
    </div>
@endsection
