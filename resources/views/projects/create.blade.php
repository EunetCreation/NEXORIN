@extends('layouts.app')

@section('title', 'New Project')
@section('page-eyebrow', 'Project command center')
@section('page-heading', 'New Project')

@push('styles')
    <link href="{{ asset('css/nexorin-project-flow.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-project-flow.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div class="project-intake-page" data-project-intake>
        <header class="project-intake-hero" data-reveal>
            <div class="project-intake-heading">
                <a href="{{ route('companies.projects.index', $company) }}" class="project-intake-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6"/>
                    </svg>
                    Project portfolio
                </a>

                <span class="project-intake-kicker">
                    <span></span>
                    Structured PLTS intake
                </span>

                <h2>Build a project record that is ready for engineering.</h2>
                <p>
                    Capture the customer brief, installation site, system direction, and initial ownership in one focused workspace.
                    NEXORIN will generate the project number automatically after submission.
                </p>
            </div>

            <div class="project-intake-hero-status" aria-label="New project workflow status">
                <span class="project-intake-hero-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 15h16l-2-8H6l-2 8z"/>
                        <path d="M8 7l-1 8M12 7v8M16 7l1 8M3 18h18"/>
                    </svg>
                </span>
                <span>
                    <small>Initial workflow state</small>
                    <strong>Draft project</strong>
                    <em>Ready for customer and site intake</em>
                </span>
            </div>
        </header>

        <form
            method="POST"
            action="{{ route('companies.projects.store', $company) }}"
            id="projectIntakeForm"
            class="project-intake-layout"
            data-project-intake-form
        >
            @csrf
            @include('projects.partials.create-form')
        </form>
    </div>
@endsection
