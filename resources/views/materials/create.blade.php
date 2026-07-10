@extends('layouts.app')

@section('title', 'Add Material')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Material catalog</div>
                <h2 class="ui-page-title">Add Material</h2>
                <p class="ui-page-description">Create a company-specific catalog item that can later be connected to BOQ selection and price books.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('companies.materials.store', $company) }}" class="ui-card">
            @csrf
            <div class="ui-card-body">
                @include('materials.partials.form', ['submitLabel' => 'Create Material'])
            </div>
        </form>
    </div>
@endsection
