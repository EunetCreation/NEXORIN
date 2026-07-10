@extends('layouts.app')

@section('title', 'Edit Material')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Material catalog</div>
                <h2 class="ui-page-title">Edit Material</h2>
                <p class="ui-page-description">Update the company-specific catalog item while preserving future BOQ and price-book traceability.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('companies.materials.update', [$company, $material->id]) }}" class="ui-card">
            @csrf
            @method('PUT')
            <div class="ui-card-body">
                @include('materials.partials.form', ['submitLabel' => 'Update Material'])
            </div>
        </form>
    </div>
@endsection
