@extends('layouts.app')

@section('title', 'Add Equipment Load')
@section('page-eyebrow', 'Load analysis')
@section('page-heading', 'Add Equipment Load')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">{{ $project->project_number }}</div>
                <h2 class="ui-page-title">Add Equipment Load</h2>
                <p class="ui-page-description">Add one appliance or equipment group to the 24-hour demand profile.</p>
            </div>
        </header>

        <section class="ui-card">
            <form method="POST" action="{{ route('companies.load-analyses.appliances.store', [$company, $project]) }}">
                @csrf
                <div class="ui-card-body">
                    @include('load_analyses.appliances.partials.form')
                </div>
            </form>
        </section>
    </div>
@endsection
