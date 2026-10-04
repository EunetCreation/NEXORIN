@extends('layouts.app')

@section('title', 'Edit Equipment Load')
@section('page-eyebrow', 'Load analysis')
@section('page-heading', 'Edit Equipment Load')
@section('force-theme', 'dark')
@section('body-class', 'page-load-appliance-create-v6 page-load-appliance-edit-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001016.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.016" rel="stylesheet">
    <link href="{{ asset('css/nexorin-load-appliance-create.css') }}?v=015f" rel="stylesheet">
    <link href="{{ asset('css/nexorin-load-appliance-edit.css') }}?v=016c" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-load-appliance-edit.js') }}?v=016b" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.016" defer></script>
@endpush

@section('content')
<main class="nx15 nx16" data-nx16-page>
    <header class="nx15-head nx16-head">
        <div class="nx15-head-copy nx16-head-copy">
            <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}#equipment" class="nx15-back-link">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12.7 4.3 7 10l5.7 5.7"/></svg>
                <span>Back to Load Analysis</span>
            </a>
            <h2>Edit Equipment Load</h2>
            <strong class="nx16-head-record">{{ $loadAppliance->name }}</strong>
            <p>Update the stored equipment record and review deterministic load changes before saving.</p>
        </div>

        <div class="nx15-head-actions nx16-head-actions">
            <span class="nx15-state-chip nx16-change-state" data-nx16-change-state>No unsaved changes</span>
            <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}#equipment" class="nx15-button is-secondary">Cancel</a>
            <button type="submit" form="nx16EquipmentForm" class="nx15-button is-primary nx16-save-button" data-nx16-submit>
                <span data-nx16-submit-label>Save Equipment</span>
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 4h8l2 2v10H5zM8 4v4h4V4M8 16v-5h4v5"/></svg>
            </button>
        </div>
    </header>

    @if ($errors->any())
        <section class="nx15-validation" role="alert" aria-labelledby="nx16ValidationTitle">
            <div class="nx15-validation-icon" aria-hidden="true">!</div>
            <div>
                <strong id="nx16ValidationTitle">Review the highlighted equipment inputs</strong>
                <p>The stored equipment record was not changed. Correct the fields below and submit again.</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <form
        id="nx16EquipmentForm"
        method="POST"
        action="{{ route('companies.load-analyses.appliances.update', [$company, $project, $loadAppliance]) }}"
        data-nx16-form
        data-stored-name="{{ $loadAppliance->name }}"
        data-stored-category="{{ $loadAppliance->category ?? '' }}"
        data-stored-quantity="{{ $loadAppliance->quantity }}"
        data-stored-power="{{ (float) $loadAppliance->rated_power_w }}"
        data-stored-hours="{{ (float) $loadAppliance->operating_hours_per_day }}"
        data-stored-start="{{ $loadAppliance->start_hour }}"
        data-stored-usage="{{ (float) $loadAppliance->usage_factor }}"
        data-stored-starting="{{ (float) $loadAppliance->starting_multiplier }}"
        data-stored-critical="{{ $loadAppliance->is_critical ? '1' : '0' }}"
        data-stored-notes="{{ $loadAppliance->notes ?? '' }}"
        data-stored-connected="{{ $loadAppliance->connected_load_kw }}"
        data-stored-daily="{{ $loadAppliance->daily_energy_kwh }}"
        data-stored-starting-demand="{{ $loadAppliance->starting_load_kw }}"
    >
        @csrf
        @method('PUT')
        @include('load_analyses.appliances.partials.edit-form')
    </form>
</main>
@endsection
