@extends('layouts.app')

@section('title', 'Add User')
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Add User')

@push('styles')
    <link href="{{ asset('css/nexorin-user-governance.css') }}?v=55a" rel="stylesheet">
@endpush

@section('content')
    <form method="POST" action="{{ route('users.store') }}" class="nxug-editor" data-nxug-editor data-nxug-mode="create" data-initial-status="active">
        @csrf
        @include('users.partials.form')
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/nexorin-user-governance.js') }}?v=55a" defer></script>
@endpush
