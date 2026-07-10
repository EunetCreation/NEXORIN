@extends('layouts.app')

@section('title', 'Add Supplier')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Supplier management</div>
                <h2 class="ui-page-title">Add Supplier</h2>
                <p class="ui-page-description">Create a supplier record for price book and procurement traceability.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('companies.suppliers.store', $company) }}" class="ui-card">
            @csrf
            <div class="ui-card-body">
                @include('suppliers.partials.form', ['submitLabel' => 'Create Supplier'])
            </div>
        </form>
    </div>
@endsection
