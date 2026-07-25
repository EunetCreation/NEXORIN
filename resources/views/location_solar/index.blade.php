@extends('layouts.app')

@section('title', 'Solar Resource Directory')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Solar Resource')

@push('styles')
    <link href="{{ asset('css/nexorin-location-solar.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-location-solar.js') }}?v=55a" defer></script>
@endpush

@php
    $projectCount = max(0, (int) $stats['projects']);
    $startedCount = max(0, (int) $stats['started']);
    $readyCount = max(0, (int) $stats['ready']);
    $verifiedCount = max(0, (int) $stats['verified']);
    $notStartedCount = max(0, $projectCount - $startedCount);
    $incompleteCount = max(0, $startedCount - $readyCount);
    $readyUnverifiedCount = max(0, $readyCount - $verifiedCount);
    $startedCoverage = $projectCount > 0 ? round(($startedCount / $projectCount) * 100) : 0;
    $activeStatus = $filters['status'];
    $statusLinks = [
        'all' => ['label' => 'All projects', 'count' => $projectCount],
        'not_started' => ['label' => 'Not started', 'count' => $notStartedCount],
        'incomplete' => ['label' => 'Needs data', 'count' => $incompleteCount],
        'ready' => ['label' => 'Ready to verify', 'count' => $readyUnverifiedCount],
        'verified' => ['label' => 'Verified', 'count' => $verifiedCount],
    ];
@endphp

@section('content')
    <div class="solar-resource-directory" data-solar-directory>
        <header class="solar-directory-hero solar-directory-hero-compact" aria-labelledby="solar-directory-title">
            <div class="solar-directory-hero-copy">
                <span class="solar-directory-kicker">Controlled solar-resource registry</span>
                <h2 id="solar-directory-title">Location &amp; Solar Resource</h2>
                <p>Coordinates, source traceability, monthly irradiation, readiness, and engineer verification across the active project portfolio.</p>
            </div>

            <div class="solar-directory-context" aria-label="Solar resource registry context">
                <span class="solar-directory-context-chip"><i aria-hidden="true"></i> Solar-resource engine online</span>
                <a href="{{ route('companies.projects.index', $company) }}" class="solar-directory-context-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 19V8l8-5 8 5v11"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/>
                    </svg>
                    Project directory
                </a>
            </div>
        </header>

        <section class="solar-directory-stats" aria-label="Solar dataset statistics">
            <article class="solar-directory-stat">
                <span class="solar-directory-stat-icon is-navy">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3.5 10.8 12 4l8.5 6.8"/>
                        <path d="M6.5 10.2V19h11v-8.8"/>
                        <path d="M8.2 13.2h7.6l.9 3H7.3l.9-3Z" opacity=".88"/>
                        <path d="M10.2 16.2v2.8M13.8 16.2v2.8" opacity=".4"/>
                    </svg>
                </span>
                <div>
                    <span>Active portfolio</span>
                    <strong>{{ number_format($projectCount) }}</strong>
                    <small>Projects available for solar-resource setup</small>
                </div>
            </article>
            <article class="solar-directory-stat">
                <span class="solar-directory-stat-icon is-teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 18.5h16"/>
                        <path d="M6.5 16V8.2" opacity=".4"/>
                        <path d="m6 14.5 3-3.8 2.7 2.1 5.8-6.3"/>
                        <path d="M16.8 6.5h2.7v2.7"/>
                    </svg>
                </span>
                <div>
                    <span>Datasets initiated</span>
                    <strong>{{ number_format($startedCount) }}</strong>
                    <small>{{ $startedCoverage }}% of the active project portfolio</small>
                </div>
            </article>
            <article class="solar-directory-stat">
                <span class="solar-directory-stat-icon is-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 4.5h8l4 4V19.5H6z"/>
                        <path d="M14 4.5v4h4" opacity=".42"/>
                        <path d="M8.5 12.5h5" opacity=".4"/>
                        <circle cx="15.8" cy="15.8" r="3.2"/>
                        <path d="m14.2 15.9 1.1 1.1 2.2-2.4"/>
                    </svg>
                </span>
                <div>
                    <span>Engineering ready</span>
                    <strong>{{ number_format($readyCount) }}</strong>
                    <small>{{ $readyUnverifiedCount }} waiting for verification</small>
                </div>
            </article>
            <article class="solar-directory-stat">
                <span class="solar-directory-stat-icon is-violet">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3.5 18.5 6v5c0 4.4-2.6 7.7-6.5 9.6C8.1 18.7 5.5 15.4 5.5 11V6L12 3.5Z"/>
                        <path d="M12 8.1v1.8M8.2 11.2h1.7M14.1 11.2h1.7M9.8 8.9 11 10.1M14.2 8.9 13 10.1" opacity=".42"/>
                        <path d="m9.2 13.1 1.8 1.8 3.8-4.1"/>
                    </svg>
                </span>
                <div>
                    <span>Verified datasets</span>
                    <strong>{{ number_format($verifiedCount) }}</strong>
                    <small>Approved engineering resource records</small>
                </div>
            </article>
        </section>

        <section class="solar-directory-workspace" id="solar-registry">
            <header class="solar-directory-workspace-head">
                <div>
                    <div class="solar-section-kicker">Dataset registry</div>
                    <h3>Project solar-resource readiness</h3>
                    <p>Find the next project that needs coordinates, source metadata, monthly values, or verification.</p>
                </div>
                <span class="solar-directory-result-count">{{ $projects->total() }} result{{ $projects->total() === 1 ? '' : 's' }}</span>
            </header>

            <nav class="solar-status-tabs" aria-label="Filter projects by solar dataset status">
                @foreach ($statusLinks as $value => $item)
                    <a
                        href="{{ route('companies.location-solar.index', ['company' => $company, 'status' => $value, 'search' => $filters['search'] ?: null]) }}"
                        class="solar-status-tab {{ $activeStatus === $value ? 'is-active' : '' }}"
                        @if ($activeStatus === $value) aria-current="page" @endif
                    >
                        <span>{{ $item['label'] }}</span>
                        <strong>{{ number_format($item['count']) }}</strong>
                    </a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('companies.location-solar.index', $company) }}" class="solar-directory-filter" data-solar-directory-filter>
                <input type="hidden" name="status" value="{{ $activeStatus }}">
                <label class="solar-directory-search" for="solar-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>
                    </svg>
                    <span class="sr-only">Search projects</span>
                    <input
                        id="solar-search"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Search project number, name, customer, or city"
                        data-solar-directory-search
                    >
                    @if ($filters['search'] !== '')
                        <a href="{{ route('companies.location-solar.index', ['company' => $company, 'status' => $activeStatus]) }}" aria-label="Clear search">×</a>
                    @endif
                </label>
                <button type="submit" class="solar-button solar-button-primary">Search registry</button>
            </form>

            @if ($projects->isEmpty())
                <div class="solar-directory-empty">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <circle cx="12" cy="12" r="8"/><path d="M8.5 14.5s1.2 1.5 3.5 1.5 3.5-1.5 3.5-1.5M9 9.5h.01M15 9.5h.01"/>
                        </svg>
                    </span>
                    <h3>No project matches this view</h3>
                    <p>Adjust the status filter or search query to return to the solar-resource registry.</p>
                    <a href="{{ route('companies.location-solar.index', $company) }}" class="solar-button solar-button-primary">Reset registry</a>
                </div>
            @else
                <div class="solar-directory-table-wrap">
                    <table class="solar-directory-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Installation site</th>
                                <th>Source intelligence</th>
                                <th>Readiness path</th>
                                <th>Last activity</th>
                                <th><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                @php
                                    $profile = $project->solarProfile;
                                    $quality = $profile?->quality_status ?? 'not_started';
                                    $stage = match ($quality) {
                                        'verified' => 3,
                                        'estimated', 'imported' => 2,
                                        'incomplete' => 1,
                                        default => 0,
                                    };
                                    $qualityLabel = match ($quality) {
                                        'verified' => 'Verified',
                                        'estimated' => 'Ready to verify',
                                        'imported' => 'Imported · ready',
                                        'incomplete' => 'Needs data',
                                        default => 'Not started',
                                    };
                                    $buildingType = strtolower((string) ($project->building_type ?? ''));
                                    $systemType = strtolower(str_replace('-', '_', (string) ($project->proposed_system ?? 'undecided')));
                                    $projectGlyph = match (true) {
                                        $buildingType === 'commercial' => 'commercial',
                                        $buildingType === 'industrial' => 'industrial',
                                        $buildingType === 'residential' => 'residential',
                                        default => 'project',
                                    };
                                    $systemGlyph = match (true) {
                                        $systemType === 'hybrid' => 'hybrid',
                                        $systemType === 'on_grid' => 'grid',
                                        default => 'undecided',
                                    };
                                    $projectMarkState = match ($quality) {
                                        'verified' => 'verified',
                                        'estimated', 'imported' => 'ready',
                                        'incomplete' => 'incomplete',
                                        default => null,
                                    };
                                @endphp
                                <tr data-solar-directory-row>
                                    <td>
                                        <div class="solar-project-cell">
                                            <span class="solar-project-mark solar-project-mark--{{ $projectGlyph }}" aria-hidden="true">
                                                <span class="solar-project-mark-core">
                                                    @switch($projectGlyph)
                                                        @case('residential')
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M3.5 10.8 12 4l8.5 6.8"/>
                                                                <path d="M6.3 10.1V19h11.4v-8.9"/>
                                                                <path d="M8.3 13.1h7.4l.9 3H7.4l.9-3Z" opacity=".9"/>
                                                                <path d="M10.2 16.1v2.9M13.8 16.1v2.9" opacity=".42"/>
                                                            </svg>
                                                            @break

                                                        @case('commercial')
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M4.5 19.5V7.2L10 4.5l5.5 2.7v12.3"/>
                                                                <path d="M15.5 10.2H19.5V19.5"/>
                                                                <path d="M7.8 9.4h4.4M7.8 12.7h4.4M7.8 16h4.4" opacity=".5"/>
                                                                <path d="M3.5 19.5h17"/>
                                                            </svg>
                                                            @break

                                                        @case('industrial')
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M4 19.5V9.8l5.2 3.1V9.2l5 3.1V6.5h3.6v13Z"/>
                                                                <path d="M3.5 19.5h17"/>
                                                                <path d="M7.2 14.6h2.2M11 14.6h2.2M15.2 14.6h1.6" opacity=".46"/>
                                                            </svg>
                                                            @break

                                                        @default
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                                <rect x="4.8" y="4.8" width="14.4" height="14.4" rx="3.2"/>
                                                                <path d="M8 14.8V9.7h8v5.1"/>
                                                                <path d="M10.4 12.2h3.2" opacity=".42"/>
                                                            </svg>
                                                    @endswitch
                                                </span>

                                                <span class="solar-project-mark-system is-{{ $systemGlyph }}">
                                                    @if ($systemGlyph === 'hybrid')
                                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                                            <rect x="4.3" y="3.6" width="7.4" height="9.2" rx="1.9"/>
                                                            <path d="M6.6 2.3h2.8M8 6.5v3M6.5 8h3"/>
                                                        </svg>
                                                    @elseif ($systemGlyph === 'grid')
                                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M8 2.5v11"/>
                                                            <path d="M5 5.4h6M4 8.3h8M5.5 11.2h5"/>
                                                        </svg>
                                                    @else
                                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M8 3.1a4.9 4.9 0 1 0 0 9.8A4.9 4.9 0 0 0 8 3.1Z" opacity=".42"/>
                                                            <path d="M8 5.1v.2M8 10.7v.2M5.4 8h.2M10.4 8h.2"/>
                                                        </svg>
                                                    @endif
                                                </span>

                                                @if ($projectMarkState)
                                                    <span class="solar-project-mark-status is-{{ $projectMarkState }}">
                                                        @if ($projectMarkState === 'verified')
                                                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="m4 8 2.4 2.4L12 5"/>
                                                            </svg>
                                                        @elseif ($projectMarkState === 'ready')
                                                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M8 2.8 9.1 5.3 11.7 6.4 9.1 7.5 8 10 6.9 7.5 4.3 6.4 6.9 5.3 8 2.8Z"/>
                                                            </svg>
                                                        @else
                                                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                                                <path d="M8 4.25v4.25M8 11.5h.01"/>
                                                            </svg>
                                                        @endif
                                                    </span>
                                                @endif
                                            </span>
                                            <div>
                                                <strong>{{ $project->name }}</strong>
                                                <span>{{ $project->project_number }} · {{ $project->customer->name }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="solar-site-cell">
                                            <strong>{{ $project->city ?: 'Location not set' }}{{ $project->province ? ', '.$project->province : '' }}</strong>
                                            <span class="{{ $project->latitude !== null && $project->longitude !== null ? 'is-ready' : 'is-missing' }}">
                                                <i aria-hidden="true"></i>
                                                {{ $project->latitude !== null && $project->longitude !== null ? number_format((float) $project->latitude, 4).', '.number_format((float) $project->longitude, 4) : 'Coordinates required' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="solar-source-cell">
                                            <span class="solar-quality-badge is-{{ $quality }}">{{ $qualityLabel }}</span>
                                            <small>{{ $profile?->data_source_label ?? 'Choose a source strategy' }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="solar-readiness-path" aria-label="Dataset stage {{ $stage }} of 3">
                                            <span class="{{ $stage >= 1 ? 'is-complete' : '' }}"><i></i><small>Dataset</small></span>
                                            <span class="{{ $stage >= 2 ? 'is-complete' : '' }}"><i></i><small>Ready</small></span>
                                            <span class="{{ $stage >= 3 ? 'is-complete' : '' }}"><i></i><small>Verified</small></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="solar-updated-cell">
                                            <strong>{{ $profile?->updated_at?->diffForHumans() ?? 'No activity' }}</strong>
                                            <span>{{ $profile?->updated_at?->format('d M Y · H:i') ?? 'Dataset has not started' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('companies.location-solar.show', [$company, $project]) }}" class="solar-open-button" aria-label="Open solar data for {{ $project->name }}">
                                            Open workspace
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M5 12h14M13 6l6 6-6 6"/>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <footer class="solar-directory-pagination">
                    @include('partials.pagination', ['paginator' => $projects])
                </footer>
            @endif
        </section>
    </div>
@endsection
