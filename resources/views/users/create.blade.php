@extends('layouts.app')

@section('title', 'Add User')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Add User')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">
                    New account
                </div>

                <h2 class="ui-page-title">
                    Create User
                </h2>

                <p class="ui-page-description">
                    Create a NEXORIN account, define platform access, and assign company memberships.
                </p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('users.index') }}" class="ui-button ui-button-secondary">
                    Back to Users
                </a>
            </div>
        </header>

        <section class="ui-card">
            <form method="POST" action="{{ route('users.store') }}" class="ui-card-body">
                @csrf

                @include('users.partials.form')
            </form>
        </section>
    </div>
@endsection
