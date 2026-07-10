@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Supplier management</div>
                <h2 class="ui-page-title">Edit Supplier</h2>
                <p class="ui-page-description">Update supplier data used for price books, procurement, and future BOQ source audit.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('companies.suppliers.update', [$company, $supplier->id]) }}" class="ui-card">
            @csrf
            @method('PUT')
            <div class="ui-card-body">
                @include('suppliers.partials.form', ['submitLabel' => 'Update Supplier'])
            </div>
        </form>
    </div>
@endsection
