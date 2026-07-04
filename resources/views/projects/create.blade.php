@extends('layouts.app')

@section('title', 'New Project')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'New Project')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">PLTS project intake</div>
                <h2 class="ui-page-title">Create Project</h2>
                <p class="ui-page-description">
                    Start a structured PLTS project record. The system will generate a unique project number automatically.
                </p>
            </div>
            <div class="ui-page-actions">
                <a href="{{ route('companies.projects.index', $company) }}" class="ui-button ui-button-secondary">Back to Projects</a>
            </div>
        </header>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Project Information</h3>
                    <p class="ui-card-description">Define the customer, site, project type, and initial team assignment.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('companies.projects.store', $company) }}" class="ui-card-body">
                @csrf
                @include('projects.partials.form')
            </form>
        </section>
    </div>
@endsection
