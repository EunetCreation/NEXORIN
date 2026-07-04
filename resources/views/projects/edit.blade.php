@extends('layouts.app')

@section('title', 'Edit Project')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Edit Project')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Project workflow control</div>
                <h2 class="ui-page-title">Edit {{ $project->name }}</h2>
                <p class="ui-page-description">
                    Update project information, team assignments, and workflow status. Status changes are recorded in the audit history.
                </p>
            </div>
            <div class="ui-page-actions">
                <a href="{{ route('companies.projects.show', [$company, $project]) }}" class="ui-button ui-button-secondary">View Project</a>
            </div>
        </header>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Project Information</h3>
                    <p class="ui-card-description">Project number {{ $project->project_number }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('companies.projects.update', [$company, $project]) }}" class="ui-card-body">
                @csrf
                @method('PUT')
                @include('projects.partials.form', ['project' => $project])
            </form>
        </section>
    </div>
@endsection
