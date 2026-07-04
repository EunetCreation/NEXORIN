@extends('layouts.app')

@section('title', 'Edit Company')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Edit Company')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">
                    Company configuration
                </div>

                <h2 class="ui-page-title">
                    Edit {{ $company->name }}
                </h2>

                <p class="ui-page-description">
                    Update the company profile, contact information, and platform access status.
                </p>
            </div>

            <div class="ui-page-actions">
                <a
                    href="{{ route('companies.show', $company) }}"
                    class="ui-button ui-button-secondary"
                >
                    View Company
                </a>
            </div>
        </header>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Company Information</h3>
                    <p class="ui-card-description">
                        Changes are applied to the company workspace immediately.
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('companies.update', $company) }}"
                class="ui-card-body"
            >
                @csrf
                @method('PUT')

                @include('companies.partials.form', ['company' => $company])
            </form>
        </section>
    </div>
@endsection
