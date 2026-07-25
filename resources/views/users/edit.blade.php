@extends('layouts.app')

@section('title', 'Edit User')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Edit User')

@push('styles')
    <link href="{{ asset('css/nexorin-user-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@section('content')
    <form method="POST" action="{{ route('users.update', $user) }}" class="nxug-editor" data-nxug-editor data-nxug-mode="edit" data-user-name="{{ $user->name }}" data-initial-status="{{ $user->status }}">
        @csrf
        @method('PUT')
        @include('users.partials.form', ['user' => $user])
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-user-governance.js') }}?v=55a" defer></script>
@endpush
