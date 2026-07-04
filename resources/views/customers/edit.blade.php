@extends('layouts.app')

@section('title', 'Edit Customer')
@section('page-eyebrow', 'Company workspace')
@section('page-heading', 'Edit Customer')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">Customer profile</div>
                <h2 class="ui-page-title">Edit {{ $customer->name }}</h2>
                <p class="ui-page-description">Update customer contact, location, notes, and portal account linkage.</p>
            </div>
            <div class="ui-page-actions">
                <a href="{{ route('companies.customers.show', [$company, $customer]) }}" class="ui-button ui-button-secondary">View Customer</a>
            </div>
        </header>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Customer Information</h3>
                    <p class="ui-card-description">Changes apply immediately to future project records.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('companies.customers.update', [$company, $customer]) }}" class="ui-card-body">
                @csrf
                @method('PUT')
                @include('customers.partials.form', ['customer' => $customer])
            </form>
        </section>
    </div>
@endsection
