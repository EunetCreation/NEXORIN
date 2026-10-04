@extends('layouts.app')

@section('title', $company->name)
@section('page-eyebrow', 'Platform administration')
@section('page-heading', 'Company Profile')
@section('force-theme', 'dark')
@section('body-class', 'page-company-show-v6')
@section('disable-remote-fonts', '1')

@push('styles')
    <link href="{{ asset('css/nexorin-company-show-v6.css') }}?v=007b" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0" defer></script>
    <script src="{{ asset('js/nexorin-company-show-v6.js') }}?v=007b" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
@php
    $identity = $workspace['identity'];
    $relationship = $workspace['relationship'];
    $onboarding = $workspace['onboarding'];
    $governance = $workspace['governance'];
    $health = $workspace['health'];
    $financial = $workspace['financial'];
    $portfolio = $workspace['acceptedPortfolio'];
    $regions = $workspace['regions'];
    $counts = $workspace['counts'];

    $formatMoney = static function (?float $value, ?string $currency): string {
        if ($value === null) return 'Not Available';
        $currency = strtoupper((string) $currency);
        if ($currency === 'IDR') {
            if (abs($value) >= 1_000_000_000_000) return 'Rp '.number_format($value / 1_000_000_000_000, 2).' T';
            if (abs($value) >= 1_000_000_000) return 'Rp '.number_format($value / 1_000_000_000, 2).' B';
            if (abs($value) >= 1_000_000) return 'Rp '.number_format($value / 1_000_000, 1).' M';
            return 'Rp '.number_format($value, 0, ',', '.');
        }
        return ($currency !== '' ? $currency.' ' : '').number_format($value, 2);
    };

    $primaryContacts = collect($workspace['contacts'])->where('is_primary', true)->values();
    $displayContacts = $primaryContacts->take(3);
    $latestFinancial = $financial['latest'];
    $regionNames = collect($regions['items'])->pluck('region_name')->filter()->take(4)->implode(', ');
    $focusMarkets = collect($identity['focus_markets'])->filter()->take(4)->implode(', ');
@endphp

<div
    class="nx7"
    data-nx7-profile
    data-map-url="{{ asset('data/indonesia-country.geojson') }}"
    data-lifecycle="{{ json_encode($workspace['activeProjectLifecycle'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
    data-financial-trend="{{ json_encode($financial['trend'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
    data-financial-currency="{{ $latestFinancial['currency'] ?? '' }}"
    data-regions="{{ json_encode($regions['map_points'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
    data-portfolio="{{ json_encode($workspace['recentAcceptedPortfolio'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
>
    <header class="nx7-page-head">
        <div>
            <nav class="nx7-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('companies.index') }}">Companies</a>
                <span aria-hidden="true">›</span>
                <span>{{ $company->name }}</span>
            </nav>
            <div class="nx7-title-line">
                <h2>Company Profile</h2>
                <span class="nx7-status nx7-status--{{ $identity['status_state'] }}"><i></i>{{ $identity['status_label'] }}</span>
            </div>
            <p>Authoritative company profile and operating context.</p>
        </div>
        <div class="nx7-page-actions">
            <a class="nx7-btn nx7-btn--ghost" href="{{ route('companies.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                Back to Companies
            </a>
            <a class="nx7-btn nx7-btn--primary" href="{{ route('companies.edit', $company) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></svg>
                Edit Company
            </a>
        </div>
    </header>

    <div class="nx7-layout">
        <main class="nx7-primary">
            <section class="nx7-hero" id="overview">
                <div class="nx7-hero-core">
                    <div class="nx7-hero-identity">
                        <div class="nx7-brand-media {{ $identity['image_url'] ? 'has-image' : 'is-empty' }}">
                            @if($identity['image_url'])
                                <img src="{{ $identity['image_url'] }}" alt="{{ $company->name }} corporate media">
                            @else
                                <svg viewBox="0 0 42 42" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M8 34V12l13-6 13 6v22"/><path d="M14 34V18h14v16M18 22h6M18 27h6"/></svg>
                                <span>Corporate media<br>not recorded</span>
                            @endif
                        </div>
                        <div class="nx7-identity-copy">
                            <div class="nx7-identity-kicker">
                                <span>{{ $identity['code'] ?: 'Company Profile' }}</span>
                                @if($relationship['tier'] === 'strategic')
                                    <b class="nx7-tier nx7-tier--strategic">Strategic</b>
                                @elseif($relationship['tier'])
                                    <b class="nx7-tier">{{ $relationship['tier_label'] }}</b>
                                @endif
                            </div>
                            <h3>{{ $identity['name'] }}</h3>
                            <p class="nx7-legal-name">{{ $identity['legal_name'] }}</p>
                            <p class="nx7-role-line">{{ collect($identity['specializations'])->implode(' · ') ?: 'Specialization not recorded' }}</p>
                        </div>
                    </div>

                    <div class="nx7-hero-facts">
                        <div><svg viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12a7 7 0 1 0-14 0c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/></svg><span><small>Headquarters</small><strong>{{ $identity['headquarters'] }}</strong></span></div>
                        <div><svg viewBox="0 0 24 24"><path d="M4 20V8h16v12M8 8V4h8v4"/></svg><span><small>Company Type</small><strong>{{ $identity['company_type'] }}</strong></span></div>
                        <div><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span><small>Established</small><strong>{{ $identity['year_established'] ?: 'Not recorded' }}</strong></span></div>
                        <div><svg viewBox="0 0 24 24"><path d="M7 20v-2a5 5 0 0 1 10 0v2"/><circle cx="12" cy="8" r="3"/></svg><span><small>Team Size</small><strong>{{ $identity['team_size'] ? number_format($identity['team_size']).' people' : 'Not recorded' }}</strong></span></div>
                        <div><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.4 4 5.4 4 9s-1.5 6.6-4 9c-2.5-2.4-4-5.4-4-9s1.5-6.6 4-9Z"/></svg><span><small>Website</small><strong>@if($identity['website'])<a href="{{ $identity['website'] }}" target="_blank" rel="noopener noreferrer">{{ $identity['website'] }}</a>@else Not recorded @endif</strong></span></div>
                    </div>

                    <div class="nx7-hero-bands">
                        <div><span>Regions Served</span><strong>{{ $regionNames ?: 'Not recorded' }}</strong></div>
                        <div><span>Focus Markets</span><strong>{{ $focusMarkets ?: 'Not recorded' }}</strong></div>
                    </div>
                </div>

                <div class="nx7-summary-grid">
                    <article class="nx7-summary-tile">
                        <div class="nx7-summary-icon"><svg viewBox="0 0 24 24"><path d="m12 3 7 3v5c0 4.6-2.7 8-7 10-4.3-2-7-5.4-7-10V6l7-3Z"/><path d="m9 12 2 2 4-5"/></svg></div>
                        <span>Governance Readiness</span>
                        <strong>{{ $governance['label'] }}</strong>
                        <small>{{ $governance['framework'] }}</small>
                    </article>
                    <article class="nx7-summary-tile nx7-summary-tile--progress">
                        <div class="nx7-summary-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.4 2.4L16 9"/></svg></div>
                        <span>Onboarding Status</span>
                        <strong>{{ $onboarding['progress'] !== null ? $onboarding['progress'].'%' : $onboarding['label'] }}</strong>
                        <small>{{ $onboarding['total'] > 0 ? $onboarding['complete'].' of '.$onboarding['total'].' requirements complete' : 'No active requirement set' }}</small>
                        @if($onboarding['progress'] !== null)<div class="nx7-mini-progress"><i style="--nx7-progress:{{ $onboarding['progress'] }}%"></i></div>@endif
                    </article>
                    <article class="nx7-summary-tile">
                        <div class="nx7-summary-icon"><svg viewBox="0 0 24 24"><path d="M4 8h16v11H4zM8 8V5h8v3"/></svg></div>
                        <span>Active Projects</span>
                        <strong>{{ number_format($workspace['activeProjectCount']) }}</strong>
                        <small>Shared active lifecycle definition</small>
                    </article>
                    <article class="nx7-summary-tile">
                        <div class="nx7-summary-icon"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg></div>
                        <span>Accepted Portfolio</span>
                        <strong>{{ $formatMoney($portfolio['company_value'], 'IDR') }}</strong>
                        <small>{{ $portfolio['company_value'] !== null ? 'Accepted quotation basis' : 'No accepted IDR quotation value' }}</small>
                    </article>
                </div>
            </section>

            <nav class="nx7-tabs" aria-label="Company profile sections" data-nx7-tabs>
                <a href="#overview" class="is-active">Overview</a>
                <a href="#projects">Projects</a>
                <a href="#documents">Documents</a>
                <a href="#credentials">Certifications</a>
                <a href="#contacts">Contacts</a>
                <a href="#activity">Activities</a>
                <a href="#governance">Risk &amp; Governance</a>
            </nav>

            <section class="nx7-grid nx7-grid--intelligence">
                <article class="nx7-card nx7-card--health">
                    <header class="nx7-card-head"><div><span>Company Health</span><h4>Profile Evidence</h4></div><b class="nx7-state-tag nx7-state-tag--neutral">{{ $health['label'] }}</b></header>
                    <div class="nx7-health-body">
                        <div class="nx7-health-instrument" aria-label="Company health is not scored">
                            <strong>—</strong><span>Not Scored</span><small>No versioned scoring framework</small>
                        </div>
                        <div class="nx7-factor-list">
                            @foreach($health['factors'] as $factor)
                                <div><i class="{{ $factor['available'] ? 'is-available' : '' }}"></i><span>{{ $factor['label'] }}</span><b>{{ $factor['available'] ? 'Available' : 'Missing' }}</b></div>
                            @endforeach
                        </div>
                    </div>
                    <footer>{{ $workspace['definitions']['health'] }}</footer>
                </article>

                <article class="nx7-card nx7-card--financial">
                    <header class="nx7-card-head"><div><span>Key Financial Snapshot</span><h4>{{ $latestFinancial ? ($latestFinancial['period_end']?->format('M Y') ?? 'Persisted snapshot') : 'Commercial evidence' }}</h4></div><b class="nx7-state-tag nx7-state-tag--neutral">{{ $latestFinancial ? 'Recorded' : 'Not Available' }}</b></header>
                    @if($latestFinancial)
                        <div class="nx7-financial-summary">
                            <div><span>Annual Revenue</span><strong>{{ $formatMoney($latestFinancial['annual_revenue'], $latestFinancial['currency']) }}</strong></div>
                            <div><span>Net Profit</span><strong>{{ $formatMoney($latestFinancial['net_profit'], $latestFinancial['currency']) }}</strong></div>
                            <div><span>EBITDA Margin</span><strong>{{ $latestFinancial['ebitda_margin'] !== null ? number_format($latestFinancial['ebitda_margin'], 1).'%' : 'Not recorded' }}</strong></div>
                            <div><span>Current Ratio</span><strong>{{ $latestFinancial['current_ratio'] !== null ? number_format($latestFinancial['current_ratio'], 2) : 'Not recorded' }}</strong></div>
                        </div>
                        @if($financial['trend_available'])
                            <div class="nx7-chart nx7-chart--financial" data-nx7-chart="financial" aria-label="Historical annual revenue chart"></div>
                        @else
                            <div class="nx7-evidence-empty"><svg viewBox="0 0 24 24"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg><span><strong>Trend not available</strong><small>{{ $financial['basis'] }}</small></span></div>
                        @endif
                    @else
                        <div class="nx7-financial-empty">
                            <div><span>Annual Revenue</span><strong>Not recorded</strong></div>
                            <div><span>Net Profit</span><strong>Not recorded</strong></div>
                            <div><span>EBITDA Margin</span><strong>Not recorded</strong></div>
                        </div>
                        <div class="nx7-evidence-empty"><svg viewBox="0 0 24 24"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg><span><strong>No financial snapshot recorded</strong><small>Historical charts require comparable persisted snapshots.</small></span></div>
                    @endif
                    <footer>{{ $financial['basis'] }}</footer>
                </article>

                <article class="nx7-card nx7-card--capabilities">
                    <header class="nx7-card-head"><div><span>Partner Capabilities</span><h4>Evidence-Based Profile</h4></div><b class="nx7-state-tag nx7-state-tag--neutral">No synthetic score</b></header>
                    @if(count($workspace['capabilities']))
                        <div class="nx7-capability-list">
                            @foreach($workspace['capabilities'] as $capability)
                                <div><span>{{ $capability['label'] }}</span><small>{{ $capability['source'] }}</small></div>
                            @endforeach
                        </div>
                    @else
                        <div class="nx7-capability-evidence">
                            <div><span>Specialization</span><b>Not recorded</b></div>
                            <div><span>Focus Markets</span><b>{{ count($identity['focus_markets']) ? count($identity['focus_markets']).' recorded' : 'Not recorded' }}</b></div>
                            <div><span>Credentials</span><b>{{ count($workspace['credentials']) ? count($workspace['credentials']).' recorded' : 'Not recorded' }}</b></div>
                            <div><span>Service Regions</span><b>{{ count($regions['items']) ? count($regions['items']).' recorded' : 'Not recorded' }}</b></div>
                            <div><span>Relationship Tier</span><b>{{ $relationship['tier_label'] }}</b></div>
                        </div>
                    @endif
                    <footer>Capability percentages are not generated without a defined methodology and evidence framework.</footer>
                </article>
            </section>

            <section class="nx7-grid nx7-grid--analysis">
                <article class="nx7-card nx7-card--map">
                    <header class="nx7-card-head"><div><span>Regional Presence</span><h4>Service Coverage</h4></div><b class="nx7-state-tag">{{ count($regions['items']) }} region{{ count($regions['items']) === 1 ? '' : 's' }}</b></header>
                    <div class="nx7-map-wrap">
                        <div class="nx7-chart nx7-chart--map" data-nx7-chart="regions" aria-label="Indonesia company service coverage map"></div>
                        @if(count($regions['items']) === 0)
                            <div class="nx7-map-empty"><strong>No service region recorded</strong><span>Indonesia is shown as geographic context only. No company marker is inferred.</span></div>
                        @elseif(count($regions['map_points']) === 0)
                            <div class="nx7-map-empty"><strong>No reference coordinates recorded</strong><span>Service regions are listed without map markers.</span></div>
                        @endif
                    </div>
                    @if(count($regions['items']))
                        <div class="nx7-region-strip">
                            @foreach(collect($regions['items'])->take(5) as $region)
                                <span><i class="{{ $region['latitude'] !== null ? 'has-coordinate' : '' }}"></i>{{ $region['region_name'] }}<small>{{ $region['coordinate_type'] }}</small></span>
                            @endforeach
                        </div>
                    @endif
                    <footer>{{ $workspace['definitions']['regional_presence'] }}</footer>
                </article>

                <article class="nx7-card nx7-card--involvement" id="projects">
                    <header class="nx7-card-head"><div><span>Project Involvement</span><h4>Active Lifecycle</h4></div><b class="nx7-state-tag">{{ number_format($workspace['activeProjectCount']) }} active</b></header>
                    @if($workspace['activeProjectCount'] > 0)
                        <div class="nx7-chart nx7-chart--donut" data-nx7-chart="lifecycle" aria-label="Active project lifecycle distribution"></div>
                        <div class="nx7-legend-list">
                            @foreach($workspace['activeProjectLifecycle'] as $row)
                                <span><i data-status="{{ $row['key'] }}"></i>{{ $row['label'] }}<b>{{ $row['value'] }}</b></span>
                            @endforeach
                        </div>
                    @else
                        <div class="nx7-zero-projects">
                            <div class="nx7-zero-ring"><strong>0</strong><span>Active Projects</span></div>
                            <div class="nx7-zero-projects__copy"><strong>No active project involvement</strong><span>No current project matches the shared active lifecycle definition.</span><small>Archived/completed records are not converted into active work.</small></div>
                        </div>
                    @endif
                    <footer>{{ $workspace['definitions']['active_projects'] }}</footer>
                </article>
            </section>

            <section class="nx7-grid nx7-grid--portfolio-row">
                <article class="nx7-card nx7-card--portfolio">
                    <header class="nx7-card-head"><div><span>Portfolio Value</span><h4>Accepted Quotation Basis</h4></div><b class="nx7-state-tag">IDR only</b></header>
                    <div class="nx7-portfolio-total"><span>Total Contract Value</span><strong>{{ $formatMoney($portfolio['company_value'], 'IDR') }}</strong><small>{{ $portfolio['accepted_quotation_count'] }} accepted value record{{ $portfolio['accepted_quotation_count'] === 1 ? '' : 's' }} included</small></div>
                    @if(count($workspace['recentAcceptedPortfolio']) > 0)
                        <div class="nx7-chart nx7-chart--portfolio" data-nx7-chart="portfolio" aria-label="Recent accepted quotation value chart"></div>
                    @else
                        <div class="nx7-evidence-empty nx7-evidence-empty--small"><svg viewBox="0 0 24 24"><path d="M4 18h16M6 15l4-4 3 2 5-7"/></svg><span><strong>Portfolio analysis not available</strong><small>No accepted IDR quotation has a numeric commercial grand total.</small></span></div>
                    @endif
                    <footer>{{ $workspace['definitions']['portfolio'] }}</footer>
                </article>

                <article class="nx7-card nx7-card--records" id="credentials">
                    <header class="nx7-card-head"><div><span>Certifications</span><h4>Credentials &amp; Evidence</h4></div><b class="nx7-state-tag">{{ count($workspace['credentials']) }}</b></header>
                    <div class="nx7-record-list">
                        @forelse(collect($workspace['credentials'])->take(4) as $credential)
                            <div class="nx7-record-row">
                                <span class="nx7-record-icon">C</span>
                                <div><strong>{{ $credential['title'] }}</strong><small>{{ $credential['kind'] }}{{ $credential['issuer'] ? ' · '.$credential['issuer'] : '' }}</small></div>
                                <div class="nx7-record-meta"><span>{{ $credential['expires_at']?->format('M d, Y') ?: 'No expiry recorded' }}</span><b class="nx7-evidence-state nx7-evidence-state--{{ $credential['expiry_state'] }}">{{ $credential['expiry_status'] }}</b></div>
                            </div>
                        @empty
                            <div class="nx7-evidence-empty nx7-evidence-empty--small"><svg viewBox="0 0 24 24"><path d="M7 3h10v18l-5-3-5 3V3Z"/></svg><span><strong>No credentials recorded</strong><small>No certification status is inferred without persisted evidence.</small></span></div>
                        @endforelse
                    </div>
                    <footer>Credential validity reflects recorded dates and evidence state only.</footer>
                </article>

                <article class="nx7-card nx7-card--records" id="contacts">
                    <header class="nx7-card-head"><div><span>Primary Contacts</span><h4>Company People</h4></div><b class="nx7-state-tag">{{ $primaryContacts->count() }} primary</b></header>
                    <div class="nx7-contact-list">
                        @forelse($displayContacts as $contact)
                            <div class="nx7-contact-row">
                                <span class="nx7-contact-avatar">{{ collect(preg_split('/\s+/', $contact['name']))->filter()->take(2)->map(fn($part) => mb_substr($part, 0, 1))->implode('') }}</span>
                                <div><strong>{{ $contact['name'] }}</strong><small>{{ $contact['role'] }}</small></div>
                                <div class="nx7-contact-meta">@if($contact['email'])<a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>@else<span>Email not recorded</span>@endif @if($contact['phone'])<small>{{ $contact['phone'] }}</small>@endif</div>
                            </div>
                        @empty
                            <div class="nx7-evidence-empty nx7-evidence-empty--small"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M4 20v-2a5 5 0 0 1 10 0v2M16 11h4M18 9v4"/></svg><span><strong>No primary contact recorded</strong><small>Contact details are not inferred from the company account.</small></span></div>
                        @endforelse
                    </div>
                    <footer>Contact visibility follows the company administration authorization boundary.</footer>
                </article>
            </section>

            <section class="nx7-grid nx7-grid--bottom">
                <article class="nx7-card nx7-card--documents" id="documents">
                    <header class="nx7-card-head"><div><span>Document Library</span><h4>Governed Records</h4></div><b class="nx7-state-tag">{{ count($workspace['documents']) }} records</b></header>
                    @if(count($workspace['documents']) > 0)
                        <div class="nx7-doc-grid">
                            @foreach(collect($workspace['documents'])->take(6) as $document)
                                <div class="nx7-doc-item"><span class="nx7-doc-icon">{{ strtoupper(pathinfo($document['file_name'] ?? '', PATHINFO_EXTENSION) ?: 'DOC') }}</span><div><strong>{{ $document['title'] }}</strong><small>{{ $document['category'] }} · {{ $document['issued_at']?->format('M d, Y') ?: 'Date not recorded' }}</small></div><b>{{ $document['status'] }}</b></div>
                            @endforeach
                        </div>
                    @else
                        <div class="nx7-evidence-empty"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5"/></svg><span><strong>No governed documents recorded</strong><small>No download control is exposed without an authorized stored-file workflow.</small></span></div>
                    @endif
                    <footer>Document metadata is presented without exposing internal storage paths.</footer>
                </article>

                <article class="nx7-card nx7-card--recent-projects">
                    <header class="nx7-card-head"><div><span>Recent Projects</span><h4>Current Project Records</h4></div>@if($company->isActive())<a href="{{ route('companies.projects.index', $company) }}">View all →</a>@endif</header>
                    @if($workspace['recentProjects']->isNotEmpty())
                        <div class="nx7-table-wrap"><table><thead><tr><th>Project</th><th>System Type</th><th>Location</th><th>Status</th></tr></thead><tbody>
                        @foreach($workspace['recentProjects'] as $project)
                            <tr><td><strong>{{ $project->project_number ?: 'No project number' }}</strong><span>{{ $project->name }}</span></td><td>{{ $project->proposed_system_label }}</td><td>{{ collect([$project->city, $project->province])->filter()->implode(', ') ?: 'Not recorded' }}</td><td><span class="nx7-project-status nx7-project-status--{{ $project->status }}">{{ $project->status_label }}</span></td></tr>
                        @endforeach
                        </tbody></table></div>
                    @else
                        <div class="nx7-evidence-empty"><svg viewBox="0 0 24 24"><path d="M4 8h16v11H4zM8 8V5h8v3"/></svg><span><strong>No current project records</strong><small>Archived records are not loaded into the first-view project list.</small></span></div>
                    @endif
                </article>
            </section>
        </main>

        <aside class="nx7-rail">
            <article class="nx7-rail-card nx7-rail-card--actions">
                <header><span>Actions</span><h4>Company Actions</h4></header>
                <div class="nx7-action-stack">
                    <a href="{{ route('companies.edit', $company) }}"><svg viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4Z"/></svg><span><strong>Edit Company</strong><small>Update company profile</small></span></a>
                    @if($company->isActive())
                        <a href="{{ route('companies.projects.index', $company) }}"><svg viewBox="0 0 24 24"><path d="M4 8h16v11H4zM8 8V5h8v3"/></svg><span><strong>View Projects</strong><small>{{ number_format($counts['projects']) }} current records</small></span></a>
                        <a href="{{ route('companies.customers.index', $company) }}"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M4 20v-2a5 5 0 0 1 10 0v2M16 11h4M18 9v4"/></svg><span><strong>View Customers</strong><small>{{ number_format($counts['customers']) }} current records</small></span></a>
                    @endif
                    <form method="POST" action="{{ route('companies.status', $company) }}" data-nx-confirm="{{ $company->isActive() ? 'Deactivate this company? Company-scoped operational routes will be restricted until the company is reactivated.' : 'Activate this company? Company-scoped operational routes will become available again.' }}" data-nx-confirm-tone="{{ $company->isActive() ? 'danger' : 'default' }}" data-nx-confirm-title="{{ $company->isActive() ? 'Deactivate company access?' : 'Activate company access?' }}" data-nx-confirm-label="{{ $company->isActive() ? 'Deactivate company' : 'Activate company' }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $company->isActive() ? 'inactive' : 'active' }}">
                        <button type="submit"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span><strong>{{ $company->isActive() ? 'Deactivate Company' : 'Activate Company' }}</strong><small>Controlled lifecycle transition</small></span></button>
                    </form>
                    @if(!$company->isActive())
                        <form method="POST" action="{{ route('companies.destroy', $company) }}" data-nx-confirm="Archive this company? The record will be soft-deleted from active administration while retained records remain in the database." data-nx-confirm-tone="danger" data-nx-confirm-title="Archive company record?" data-nx-confirm-label="Archive company">
                            @csrf @method('DELETE')
                            <button type="submit" class="is-danger"><svg viewBox="0 0 24 24"><path d="M4 7h16M6 7l1 13h10l1-13M9 4h6l1 3H8z"/></svg><span><strong>Archive Company</strong><small>Soft-delete retained record</small></span></button>
                        </form>
                    @endif
                </div>
            </article>

            <article class="nx7-rail-card" id="governance">
                <header><span>Key Alerts</span><h4>Deterministic Evidence</h4></header>
                <div class="nx7-alert-list">
                    @forelse($workspace['alerts'] as $alert)
                        <div class="nx7-alert nx7-alert--{{ $alert['severity'] }}"><i></i><span><strong>{{ $alert['title'] }}</strong><small>{{ $alert['detail'] }}</small></span></div>
                    @empty
                        <div class="nx7-side-empty"><strong>No deterministic blocker surfaced</strong><span>This is not a statutory compliance certification.</span></div>
                    @endforelse
                </div>
            </article>

            <article class="nx7-rail-card" id="activity">
                <header><span>Recent Activity</span><h4>Auditable Events</h4></header>
                @if(count($workspace['recentActivity']))
                    <div class="nx7-activity-mini">
                        @foreach(collect($workspace['recentActivity'])->take(5) as $activity)
                            <div><i></i><span><strong>{{ $activity['title'] }}</strong><small>{{ $activity['detail'] }}</small><time>{{ $activity['event_at']?->format('M d, Y · H:i') }}</time></span></div>
                        @endforeach
                    </div>
                @else
                    <div class="nx7-side-empty"><strong>No auditable recent activity recorded.</strong><span>Generic timestamps are not converted into business events.</span></div>
                @endif
            </article>

            <article class="nx7-rail-card nx7-rail-card--onboarding">
                <header><span>Onboarding</span><h4>Profile Readiness</h4></header>
                @if($onboarding['progress'] !== null)
                    <div class="nx7-progress-head"><strong>{{ $onboarding['progress'] }}%</strong><span>{{ $onboarding['label'] }}</span></div>
                    <div class="nx7-progress"><i style="--nx7-progress: {{ $onboarding['progress'] }}%"></i></div>
                    <div class="nx7-check-list">
                        @foreach(collect($onboarding['items'])->take(5) as $item)
                            <span class="{{ $item['complete'] ? 'is-complete' : '' }}"><i>{{ $item['complete'] ? '✓' : '–' }}</i>{{ $item['label'] }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="nx7-side-empty"><strong>Onboarding not configured</strong><span>No active requirement set applies to this company.</span></div>
                @endif
            </article>

            <article class="nx7-rail-card nx7-rail-card--governance">
                <header><span>Governance Readiness</span><h4>Company Profile Governance</h4></header>
                <div class="nx7-governance-state nx7-governance-state--{{ $governance['state'] }}"><i></i><strong>{{ $governance['label'] }}</strong></div>
                <p>{{ $governance['basis'] }}</p>
                <dl><div><dt>Framework</dt><dd>{{ $governance['framework'] }}</dd></div><div><dt>Version</dt><dd>{{ $governance['framework_version'] }}</dd></div><div><dt>Relationship</dt><dd>{{ $relationship['tier_label'] }}</dd></div></dl>
            </article>
        </aside>
    </div>
</div>
@endsection
