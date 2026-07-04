@extends('layouts.app')

@section('title', 'Add Company')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Add Company')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">
                    New organization
                </div>

                <h2 class="ui-page-title">
                    Create Company
                </h2>

                <p class="ui-page-description">
                    Register a new organization and prepare its isolated NEXORIN workspace.
                    Users and company roles can be assigned after the company is created.
                </p>
            </div>

            <div class="ui-page-actions">
                <a
                    href="{{ route('companies.index') }}"
                    class="ui-button ui-button-secondary"
                >
                    Back to Companies
                </a>
            </div>
        </header>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Company Information</h3>
                    <p class="ui-card-description">
                        Enter the legal and operational details used throughout the platform.
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('companies.store') }}"
                class="ui-card-body"
            >
                @csrf

                @include('companies.partials.form')
            </form>
        </section>
    </div>
@endsection
