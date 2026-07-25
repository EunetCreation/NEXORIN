@extends('layouts.app')

@section('title', 'Roof & Site Survey')
@section('page-eyebrow', 'Engineering workspace')
@section('page-heading', 'Roof & Site Survey')

@push('styles')
    <link href="{{ asset('css/nexorin-roof-site.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-roof-site.js') }}?v=55a" defer></script>
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
    <div class="rss-directory" data-rss-directory>
        <header class="rss-directory-hero rss-directory-hero-compact" aria-labelledby="rss-directory-title">
            <div class="rss-directory-hero-copy">
                <span class="rss-kicker">Controlled site-survey registry</span>
                <h2 id="rss-directory-title">Roof &amp; Site Survey</h2>
                <p>Roof geometry, usable area, access, safety, evidence, readiness, and engineer verification across the active project portfolio.</p>
            </div>

            <div class="rss-directory-context" aria-label="Roof survey registry context">
                <span class="rss-directory-context-chip"><i aria-hidden="true"></i> Survey engine online</span>
                <a href="{{ route('companies.projects.index', $company) }}" class="rss-directory-context-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 19V8l8-5 8 5v11"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/>
                    </svg>
                    Project directory
                </a>
            </div>
        </header>

        <section class="rss-directory-stats" aria-label="Roof survey statistics">
            <article class="rss-directory-stat">
                <span class="rss-directory-stat-icon is-navy" aria-hidden="true">
                    <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                        <rect class="i-soft" x="4.3" y="6.1" width="13.5" height="13.2" rx="2.7"/>
                        <path class="i-soft" d="M7 4.2h12.7v12.9"/>
                        <path class="i-main" d="m7.4 13.1 4-3.1 4 3.1M8.7 12.5v3.7h5.4v-3.7"/>
                        <circle class="i-fill" cx="18.2" cy="5.8" r="1.25"/>
                    </svg>
                </span>
                <div><span>Active portfolio</span><strong>{{ number_format($projectCount) }}</strong><small>Projects available for roof survey setup</small></div>
            </article>
            <article class="rss-directory-stat">
                <span class="rss-directory-stat-icon is-teal" aria-hidden="true">
                    <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                        <rect class="i-main" x="6" y="4.6" width="11.6" height="14.8" rx="2.7"/>
                        <path class="i-soft" d="M9.2 4.6V3.2h5.2v1.4M8.8 9h5.8"/>
                        <path class="i-main" d="M8.8 13.6h2l1.15-3.2 1.9 5.3 1.1-2.1h2"/>
                        <circle class="i-fill" cx="18.3" cy="7" r="1.25"/>
                    </svg>
                </span>
                <div><span>Surveys initiated</span><strong>{{ number_format($startedCount) }}</strong><small>{{ $startedCoverage }}% of the active project portfolio</small></div>
            </article>
            <article class="rss-directory-stat">
                <span class="rss-directory-stat-icon is-blue" aria-hidden="true">
                    <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                        <path class="i-main" d="m4.6 17.5 3.5-8.9h7.8l3.5 8.9Z"/>
                        <path class="i-soft" d="M7.4 13.2h9.2M9.2 8.6l-1.8 8.9M14.8 8.6l1.8 8.9"/>
                        <path class="i-accent" d="m15.1 6.4 1.2 1.2 2.6-2.8"/>
                        <circle class="i-fill" cx="17.5" cy="6.2" r="1.15"/>
                    </svg>
                </span>
                <div><span>Engineering ready</span><strong>{{ number_format($readyCount) }}</strong><small>{{ $readyUnverifiedCount }} waiting for verification</small></div>
            </article>
            <article class="rss-directory-stat">
                <span class="rss-directory-stat-icon is-violet" aria-hidden="true">
                    <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                        <circle class="i-main" cx="12" cy="10.2" r="5.4"/>
                        <path class="i-accent" d="m9.4 10.4 1.7 1.7 3.6-3.8"/>
                        <path class="i-soft" d="m8.4 14.2-1 6.2 4.6-2.1 4.6 2.1-1-6.2"/>
                        <circle class="i-fill" cx="18.1" cy="5.7" r="1.15"/>
                    </svg>
                </span>
                <div><span>Verified surveys</span><strong>{{ number_format($verifiedCount) }}</strong><small>Approved roof and site records</small></div>
            </article>
        </section>

        <section class="rss-directory-workspace" id="rss-registry">
            <header class="rss-directory-workspace-head">
                <div>
                    <div class="rss-section-kicker">Survey registry</div>
                    <h3>Project roof and site readiness</h3>
                    <p>Find the next project that needs survey details, roof surfaces, evidence, or verification.</p>
                </div>
                <span class="rss-directory-result-count">{{ $projects->total() }} result{{ $projects->total() === 1 ? '' : 's' }}</span>
            </header>

            <nav class="rss-status-tabs" aria-label="Filter projects by roof survey status">
                @foreach ($statusLinks as $value => $item)
                    <a
                        href="{{ route('companies.roof-site.index', ['company' => $company, 'status' => $value, 'search' => $filters['search'] ?: null]) }}"
                        class="rss-status-tab {{ $activeStatus === $value ? 'is-active' : '' }}"
                        @if ($activeStatus === $value) aria-current="page" @endif
                    >
                        <span>{{ $item['label'] }}</span><strong>{{ number_format($item['count']) }}</strong>
                    </a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('companies.roof-site.index', $company) }}" class="rss-directory-filter">
                <input type="hidden" name="status" value="{{ $activeStatus }}">
                <label class="rss-directory-search" for="rss-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
                    <span class="sr-only">Search projects</span>
                    <input id="rss-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search project number, name, customer, or city" data-rss-directory-search>
                    @if ($filters['search'] !== '')
                        <a href="{{ route('companies.roof-site.index', ['company' => $company, 'status' => $activeStatus]) }}" aria-label="Clear search">×</a>
                    @endif
                </label>
                <button type="submit" class="rss-button rss-button-primary">Search registry</button>
            </form>

            @if ($projects->isEmpty())
                <div class="rss-directory-empty">
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M8.5 14.5s1.2 1.5 3.5 1.5 3.5-1.5 3.5-1.5M9 9.5h.01M15 9.5h.01"/></svg></span>
                    <h3>No project matches this view</h3>
                    <p>Adjust the status filter or search query to return to the roof-survey registry.</p>
                    <a href="{{ route('companies.roof-site.index', $company) }}" class="rss-button rss-button-primary">Reset registry</a>
                </div>
            @else
                <div class="rss-directory-table-wrap">
                    <table class="rss-directory-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Installation site</th>
                                <th>Survey intelligence</th>
                                <th>Readiness path</th>
                                <th>Last activity</th>
                                <th><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                @php
                                    $survey = $project->roofSiteSurvey;
                                    $quality = $survey?->quality_status ?? 'not_started';
                                    $stage = match ($quality) {
                                        'verified' => 3,
                                        'ready' => 2,
                                        'incomplete' => 1,
                                        default => 0,
                                    };
                                    $qualityLabel = match ($quality) {
                                        'verified' => 'Verified',
                                        'ready' => 'Ready to verify',
                                        'incomplete' => 'Needs data',
                                        default => 'Not started',
                                    };
                                    $buildingType = strtolower((string) ($project->building_type ?? ''));
                                    $systemType = strtolower(str_replace('-', '_', (string) ($project->proposed_system ?? 'undecided')));
                                    $buildingGlyph = match (true) {
                                        $buildingType === 'commercial' => 'commercial',
                                        $buildingType === 'industrial' => 'industrial',
                                        $buildingType === 'residential' => 'residential',
                                        default => 'site',
                                    };
                                    $systemGlyph = match (true) {
                                        $systemType === 'hybrid' => 'hybrid',
                                        $systemType === 'on_grid' => 'grid',
                                        default => 'undecided',
                                    };
                                @endphp
                                <tr data-rss-directory-row>
                                    <td>
                                        <div class="rss-project-cell">
                                            <span class="rss-project-mark rss-project-mark--{{ $buildingGlyph }}" aria-hidden="true">
                                                <span class="rss-project-mark-core">
                                                    @switch($buildingGlyph)
                                                        @case('commercial')
                                                            <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                                                                <path class="i-main" d="M5.1 19.2V7.2h9.7v12M14.8 10.3h4.1v8.9M4 19.2h16"/>
                                                                <path class="i-soft" d="M8 9.8h4M8 13h4M8 16.2h4"/>
                                                                <path class="i-accent" d="m7.1 7.2 2.8-2 2.9 2"/>
                                                            </svg>
                                                            @break
                                                        @case('industrial')
                                                            <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                                                                <path class="i-main" d="M4.2 19.3V10l5 2.9V9.3l4.8 2.9V6.6h3.8v12.7M3.7 19.3h16.6"/>
                                                                <path class="i-soft" d="M7.1 15h2M10.8 15h2M14.7 15h1.8"/>
                                                                <path class="i-accent" d="M16 4.3h2.9"/>
                                                            </svg>
                                                            @break
                                                        @case('residential')
                                                            <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                                                                <path class="i-main" d="m3.8 10.5 8.2-6.2 8.2 6.2M6.4 10v9.2h11.2V10"/>
                                                                <path class="i-accent" d="m8.1 12.7 3.8-1.35 4 1.35-.8 3H8.9Z"/>
                                                                <path class="i-soft" d="M12 11.35v4.35M9.2 19.2v-2.4h5.6v2.4"/>
                                                            </svg>
                                                            @break
                                                        @default
                                                            <svg class="rss-icon-art" viewBox="0 0 24 24" fill="none">
                                                                <path class="i-main" d="M12 20s5.2-4.5 5.2-9.2a5.2 5.2 0 1 0-10.4 0C6.8 15.5 12 20 12 20Z"/>
                                                                <path class="i-accent" d="m9.3 11.7 2.7-2.1 2.7 2.1M10.3 11.1v2.4h3.4v-2.4"/>
                                                                <circle class="i-fill" cx="12" cy="6.1" r="1.05"/>
                                                            </svg>
                                                    @endswitch
                                                </span>
                                                <span class="rss-project-mark-system is-{{ $systemGlyph }}">
                                                    @if ($systemGlyph === 'hybrid')
                                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4.4" y="3.7" width="7.2" height="9" rx="1.8"/><path d="M6.7 2.4h2.6M8 6.5v3M6.5 8h3"/></svg>
                                                    @elseif ($systemGlyph === 'grid')
                                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2.5v11M5 5.4h6M4 8.3h8M5.5 11.2h5"/></svg>
                                                    @else
                                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3a5 5 0 1 0 0 10A5 5 0 0 0 8 3Z" opacity=".42"/><path d="M8 5.4v.2M8 10.4v.2M5.5 8h.2M10.3 8h.2"/></svg>
                                                    @endif
                                                </span>
                                                @if ($quality === 'verified')
                                                    <i class="is-verified"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m4 8 2.3 2.3L12 5"/></svg></i>
                                                @elseif ($quality === 'ready')
                                                    <i class="is-ready"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m8 2.8 1.1 2.5 2.6 1.1-2.6 1.1L8 10 6.9 7.5 4.3 6.4l2.6-1.1L8 2.8Z"/></svg></i>
                                                @elseif ($quality === 'incomplete')
                                                    <i class="is-incomplete"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 4.2v4.4M8 11.5h.01"/></svg></i>
                                                @endif
                                            </span>
                                            <div>
                                                <strong>{{ $project->name }}</strong>
                                                <span>{{ $project->project_number }} · {{ $project->customer->name }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="rss-site-cell">
                                            <strong>{{ $project->city ?: 'Location incomplete' }}{{ $project->province ? ', '.$project->province : '' }}</strong>
                                            <span><i aria-hidden="true"></i>{{ $project->latitude !== null && $project->longitude !== null ? number_format((float) $project->latitude, 4).', '.number_format((float) $project->longitude, 4) : 'Coordinates not recorded' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="rss-quality-badge is-{{ $quality }}">{{ $qualityLabel }}</span>
                                        <small>{{ $survey?->survey_date?->format('d M Y') ?? 'Survey date not recorded' }}</small>
                                    </td>
                                    <td>
                                        <div class="rss-readiness-path">
                                            <span class="{{ $stage >= 1 ? 'is-complete' : '' }}"><i></i><b>Dataset</b></span>
                                            <span class="{{ $stage >= 2 ? 'is-complete' : '' }}"><i></i><b>Ready</b></span>
                                            <span class="{{ $stage >= 3 ? 'is-complete' : '' }}"><i></i><b>Verified</b></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="rss-activity-cell">
                                            <strong>{{ $survey?->updated_at?->diffForHumans() ?? 'Not started' }}</strong>
                                            <span>{{ $survey?->updated_at?->format('d M Y · H:i') ?? 'No survey activity' }}</span>
                                        </div>
                                    </td>
                                    <td><a href="{{ route('companies.roof-site.show', [$company, $project]) }}" class="rss-table-action">Open workspace</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="rss-directory-footer">
                    @include('partials.pagination', ['paginator' => $projects])
                </div>
            @endif
        </section>
    </div>
@endsection
