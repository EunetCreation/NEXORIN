@extends('layouts.app')

@section('title', 'Edit User')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Edit User')

@section('content')
    <div class="ui-page">
        <header class="ui-page-header">
            <div>
                <div class="ui-page-kicker">
                    Account configuration
                </div>

                <h2 class="ui-page-title">
                    Edit {{ $user->name }}
                </h2>

                <p class="ui-page-description">
                    Update account details, platform privileges, and company role assignments.
                </p>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('users.show', $user) }}" class="ui-button ui-button-secondary">
                    View User
                </a>
            </div>
        </header>

        <section class="ui-card">
            <form method="POST" action="{{ route('users.update', $user) }}" class="ui-card-body">
                @csrf
                @method('PUT')

                @include('users.partials.form', ['user' => $user])
            </form>
        </section>
    </div>
@endsection
