@extends('layouts.app')

@section('title', 'Edit Equipment Load')
@section('page-eyebrow', 'Load analysis')
@section('page-heading', 'Edit Equipment Load')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">{{ $project->project_number }}</div>
                <h2 class="ui-page-title">Edit {{ $loadAppliance->name }}</h2>
                <p class="ui-page-description">Update demand, operating schedule, and critical-load classification.</p>
            </div>
        </header>

        <section class="ui-card">
            <form method="POST" action="{{ route('companies.load-analyses.appliances.update', [$company, $project, $loadAppliance]) }}">
                @csrf
                @method('PUT')
                <div class="ui-card-body">
                    @include('load_analyses.appliances.partials.form')
                </div>
            </form>
        </section>
    </div>
@endsection
