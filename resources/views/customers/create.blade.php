@extends('layouts.app')

@section('title', 'Add Customer')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Add Customer')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">New customer record</div>
                <h2 class="ui-page-title">Create Customer</h2>
                <p class="ui-page-description">
                    Add a residential or commercial customer to {{ $company->name }}.
                    A unique customer number will be generated automatically.
                </p>
            </div>
            <div class="ui-page-actions">
                <a href="{{ route('companies.customers.index', $company) }}" class="ui-button ui-button-secondary">Back to Customers</a>
            </div>
        </header>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Customer Information</h3>
                    <p class="ui-card-description">Enter customer contact, location, and portal access details.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('companies.customers.store', $company) }}" class="ui-card-body">
                @csrf
                @include('customers.partials.form')
            </form>
        </section>
    </div>
@endsection
