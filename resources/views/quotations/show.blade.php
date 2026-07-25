@extends('layouts.app')

@section('title', 'Quotation')
@section('page-eyebrow', 'Business output')
@section('page-heading', 'Quotation')

@php
    $money = function ($value, int $decimals = 0): string {
        if ($value === null) {
            return '—';
        }

        $amount = (float) $value;
        $sign = $amount < 0 ? '−' : '';

        return 'IDR '.$sign.number_format(abs($amount), $decimals, '.', ',');
    };
    $number = fn ($value, int $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, '.', ',');
    $quantity = function ($value, ?string $unit): string {
        $amount = (float) $value;
        $normalizedUnit = strtolower(trim((string) $unit));
        $discreteUnits = ['pc', 'pcs', 'piece', 'pieces', 'unit', 'units', 'set', 'sets', 'lot', 'lots', 'job', 'jobs', 'ea', 'each', 'package', 'packages'];

        if (in_array($normalizedUnit, $discreteUnits, true) && abs($amount - round($amount)) < 0.0005) {
            return number_format($amount, 0, '.', ',');
        }

        $formatted = number_format($amount, 3, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        if (! str_contains($formatted, '.')) {
            $formatted .= '.00';
        } elseif (strlen(substr(strrchr($formatted, '.'), 1)) === 1) {
            $formatted .= '0';
        }

        return $formatted;
    };
    $commercial = $quotation?->commercial_summary ?? [];
    $technical = $quotation?->technical_summary ?? [];
    $financial = $quotation?->financial_summary ?? [];
    $terms = $quotation?->terms ?? [];
    $bomReady = $bom?->pricing_status === 'priced' && (float) ($bom?->grand_price_idr ?? 0) > 0;
    $financialReady = $analysis?->status === 'calculated';
    $sourceReady = $bomReady && $financialReady;
    $isExpired = $quotation?->shouldBeConsideredExpired() ?? false;
    $lifecycle = $quotation ? ($isExpired ? 'expired' : $quotation->status) : 'not_generated';
    $lifecycleLabel = $quotation ? ($isExpired ? 'Expired' : $quotation->status_label) : 'Not generated';
    $hasSnapshot = $quotation?->hasSnapshotControlledClientProposal() ?? false;
    $hasCurrentPdf = $quotation?->hasCurrentClientProposalPdf() ?? false;
    $clientApproved = $quotation?->isApprovedForClient() ?? false;
    $warningCount = count($quotation?->warnings ?? []);
    $releaseChecks = [
        $bomReady,
        $financialReady,
        $hasSnapshot,
        $warningCount === 0,
        $clientApproved,
        $hasCurrentPdf,
    ];
    $releaseScore = collect($releaseChecks)->filter()->count();
    $releasePercent = (int) round(($releaseScore / count($releaseChecks)) * 100);
    $hasHistoricalCustomerRelease = (bool) ($quotation?->issued_at || $quotation?->accepted_at || $quotation?->rejected_at || $quotation?->cancelled_at);
    $currentReleaseNeedsRefresh = $hasHistoricalCustomerRelease && $releaseScore < count($releaseChecks);
    $revisionDisplay = $quotation
        ? (((int) $quotation->revision_number === 0)
            ? $quotation->quote_number.' · Initial issue'
            : $quotation->version_display)
        : 'Not generated';
    $primaryActionTarget = $canGenerate ? '#release-desk' : ($canApprove || $canIssue ? '#release-control' : (($canAccept || $canReject || $canCancel) ? '#customer-decision' : '#client-preview'));
    $primaryActionLabel = $canGenerate
        ? ($quotation ? 'Prepare draft revision' : 'Generate quotation')
        : ($canApprove ? 'Review and approve' : ($canIssue ? 'Send quotation' : (($canAccept || $canReject || $canCancel) ? 'Record customer decision' : 'Review client preview')));
    $canGenerateClientPdf = $quotation && $clientApproved && ! $isExpired && $hasSnapshot;
    $canPreviewClient = (bool) $quotation;
    $currentMilestone = 'Awaiting quotation generation';
    $currentMilestoneAt = null;

    if ($quotation?->accepted_at) {
        $currentMilestone = 'Customer accepted quotation';
        $currentMilestoneAt = $quotation->accepted_at;
    } elseif ($quotation?->rejected_at) {
        $currentMilestone = 'Customer rejected quotation';
        $currentMilestoneAt = $quotation->rejected_at;
    } elseif ($quotation?->cancelled_at) {
        $currentMilestone = 'Quotation cancelled';
        $currentMilestoneAt = $quotation->cancelled_at;
    } elseif ($isExpired && $quotation?->valid_until) {
        $currentMilestone = 'Quotation validity expired';
        $currentMilestoneAt = $quotation->valid_until;
    } elseif ($quotation?->issued_at) {
        $currentMilestone = 'Sent to customer';
        $currentMilestoneAt = $quotation->issued_at;
    } elseif ($quotation?->client_proposal_pdf_generated_at) {
        $currentMilestone = 'Snapshot-safe client PDF generated';
        $currentMilestoneAt = $quotation->client_proposal_pdf_generated_at;
    } elseif ($quotation?->approved_at) {
        $currentMilestone = 'Client snapshot approved';
        $currentMilestoneAt = $quotation->approved_at;
    } elseif ($quotation) {
        $currentMilestone = 'Working revision generated';
        $currentMilestoneAt = $quotation->updated_at;
    }

    $categoryResolver = function (?string $category): string {
        $key = strtolower(str_replace(['-', ' '], '_', (string) $category));

        return match (true) {
            str_contains($key, 'module'), str_contains($key, 'inverter'), str_contains($key, 'battery'), str_contains($key, 'power_conversion') => 'Main equipment',
            str_contains($key, 'mount'), str_contains($key, 'rail'), str_contains($key, 'clamp'), str_contains($key, 'structur'), str_contains($key, 'roof') => 'Mounting and structural BOS',
            str_contains($key, 'dc'), str_contains($key, 'ac'), str_contains($key, 'cable'), str_contains($key, 'electric'), str_contains($key, 'ground'), str_contains($key, 'protect'), str_contains($key, 'breaker') => 'Electrical BOS and protection',
            str_contains($key, 'labor'), str_contains($key, 'install'), str_contains($key, 'engineering'), str_contains($key, 'test'), str_contains($key, 'commission') => 'Engineering and installation',
            str_contains($key, 'logistic'), str_contains($key, 'contingency'), str_contains($key, 'other') => 'Logistics and allowances',
            str_contains($key, 'tax'), str_contains($key, 'discount'), str_contains($key, 'commercial') => 'Commercial adjustments',
            default => 'Project scope and materials',
        };
    };
    $groupOrder = [
        'Main equipment',
        'Mounting and structural BOS',
        'Electrical BOS and protection',
        'Project scope and materials',
        'Engineering and installation',
        'Logistics and allowances',
        'Commercial adjustments',
    ];
    $groupedItems = collect();

    if ($quotation) {
        foreach ($quotation->items as $item) {
            $group = $categoryResolver($item->category);
            $current = $groupedItems->get($group, collect());
            $current->push($item);
            $groupedItems->put($group, $current);
        }

        $groupedItems = $groupedItems->sortBy(function ($items, $group) use ($groupOrder) {
            $position = array_search($group, $groupOrder, true);

            return $position === false ? 99 : $position;
        });
    }
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-quotation.css') }}?v=55a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-quotation.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div
        class="nxq-workspace"
        data-nxq-workspace
        data-nxq-project-name="{{ $project->name }}"
        data-nxq-project-number="{{ $project->project_number }}"
    >
        @if ($errors->any())
            <div class="nx-feedback nx-feedback--danger" role="alert" data-alert>
                <span class="nx-feedback-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <div class="nx-feedback-body">
                    <span class="nx-feedback-kicker">Quotation validation</span>
                    <strong class="nx-feedback-title">Review the controlled release inputs</strong>
                    <p class="nx-feedback-copy">The quotation action could not continue until the following items are resolved.</p>
                    <ul class="nx-feedback-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>
                </button>
            </div>
        @endif

        <header class="nxq-command-deck" data-nxq-reveal>
            <div class="nxq-command-copy">
                <span class="nxq-kicker">Customer proposal command deck</span>
                <div class="nxq-command-title-row">
                    <div>
                        <h2>{{ $quotation?->quote_number ?? 'Quotation workspace' }}</h2>
                        <p>{{ $project->name }}</p>
                    </div>
                    <span class="nxq-status nxq-status--{{ str_replace('_', '-', $lifecycle) }}"><i></i>{{ $lifecycleLabel }}</span>
                </div>
                <p class="nxq-command-description">
                    Prepare a revision-controlled customer proposal from the approved technical, commercial, and financial basis while keeping cost, margin, and supplier trace protected.
                </p>
                <div class="nxq-command-identity" aria-label="Quotation identity">
                    <span><small>Project</small><strong>{{ $project->project_number }}</strong></span>
                    <span><small>Customer</small><strong>{{ $project->customer?->name ?? 'Customer unavailable' }}</strong></span>
                    <span><small>Revision</small><strong>{{ $revisionDisplay }}</strong></span>
                </div>
            </div>

            <aside class="nxq-command-control" aria-label="Quotation release state">
                <div class="nxq-release-score">
                    <span>{{ $currentReleaseNeedsRefresh ? 'Current release readiness' : 'Release control' }}</span>
                    <strong>{{ $releaseScore }}/{{ count($releaseChecks) }}</strong>
                    <small>{{ $currentReleaseNeedsRefresh ? 'current gates passed · sent revision retained' : 'controlled gates passed' }}</small>
                    <div aria-hidden="true"><span style="width:{{ $releasePercent }}%"></span></div>
                    @if ($currentReleaseNeedsRefresh)
                        <p class="nxq-release-context">The sent milestone remains recorded. Refresh the current BOQ or financial basis before releasing another revision.</p>
                    @endif
                </div>
                <div class="nxq-command-telemetry">
                    <span><small>Current milestone</small><strong>{{ $currentMilestone }}</strong></span>
                    <span><small>Recorded at</small><strong>{{ $currentMilestoneAt?->format('d M Y · H:i') ?? 'No milestone' }}</strong></span>
                </div>
                <div class="nxq-command-actions">
                    <a class="nxq-button nxq-button-primary" href="{{ $primaryActionTarget }}" data-nxq-scroll>{{ $primaryActionLabel }}</a>
                    <details class="nxq-action-menu">
                        <summary class="nxq-button nxq-button-ghost">
                            Documents
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="nxq-action-menu-panel">
                            @if ($canGenerateClientPdf)
                                <a href="{{ route('companies.quotations.pdf', [$company, $project]) }}" data-nxq-download>
                                    <span>Approved client proposal PDF</span>
                                    <small>Generate from the controlled snapshot</small>
                                </a>
                            @else
                                <span class="is-disabled">
                                    <span>Approved client proposal PDF</span>
                                    <small>Approval, current snapshot, and validity required</small>
                                </span>
                            @endif
                            <a href="{{ route('companies.projects.report.client-proposal.preview', [$company, $project]) }}" target="_blank" rel="noopener">
                                <span>Project proposal preview</span>
                                <small>Open the general customer-facing project preview</small>
                            </a>
                            <a href="{{ route('companies.projects.report.client-proposal.pdf', [$company, $project]) }}" data-nxq-download>
                                <span>Project proposal PDF</span>
                                <small>Download the general project proposal report</small>
                            </a>
                            <a href="{{ route('companies.projects.report.premium.pdf', [$company, $project]) }}" data-nxq-download>
                                <span>Internal engineering report</span>
                                <small>Controlled internal technical document</small>
                            </a>
                        </div>
                    </details>
                </div>
            </aside>
        </header>

        @if ($quotation && ! empty($quotation->warnings))
            <div class="nx-feedback nx-feedback--warning" role="alert" data-alert data-nxq-reveal>
                <span class="nx-feedback-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <div class="nx-feedback-body">
                    <span class="nx-feedback-kicker">Customer release warning</span>
                    <strong class="nx-feedback-title">Resolve quotation blockers before approval</strong>
                    <p class="nx-feedback-copy">The working revision can be reviewed, but the following controlled warnings prevent a client-safe release.</p>
                    <ul class="nx-feedback-list">
                        @foreach ($quotation->warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>
                </button>
            </div>
        @endif

        <nav class="nxq-section-nav" aria-label="Quotation workspace sections" data-nxq-section-nav>
            <a href="#overview" class="is-active">Overview</a>
            <a href="#release-desk">Release desk</a>
            <a href="#client-preview">Client preview</a>
            <a href="#scope-pricing">Scope &amp; pricing</a>
            <a href="#commercial-terms">Commercial terms</a>
            <a href="#customer-decision">Customer decision</a>
            <a href="#audit">Audit</a>
        </nav>

        <section id="overview" class="nxq-overview-grid" data-nxq-section data-nxq-reveal>
            <article class="nxq-verdict-card is-{{ str_replace('_', '-', $lifecycle) }}">
                <span class="nxq-card-kicker">Controlled lifecycle</span>
                <strong>{{ $lifecycleLabel }}</strong>
                <p>{{ $quotation ? 'The active record is '.$quotation->version_display.'. Customer release actions follow the controlled lifecycle and snapshot gates below.' : 'No quotation revision exists yet. Complete the commercial source basis and generate the first controlled draft.' }}</p>
                <div class="nxq-verdict-meta">
                    <span><small>Warnings</small><b>{{ $warningCount }}</b></span>
                    <span><small>Release gates</small><b>{{ $releaseScore }}/{{ count($releaseChecks) }}</b></span>
                </div>
            </article>

            <article class="nxq-metric-card is-teal">
                <span>Customer investment</span>
                <strong>{{ $quotation ? $money($commercial['grand_total_idr'] ?? 0) : ($bomReady ? $money($bom->grand_price_idr) : '—') }}</strong>
                <small>{{ $quotation ? 'Controlled quotation selling total' : ($bomReady ? 'Available from the priced BOQ' : 'Awaiting priced BOQ') }}</small>
            </article>

            <article class="nxq-metric-card is-blue">
                <span>System capacity</span>
                <strong>{{ $quotation && ($technical['installed_pv_kw'] ?? null) !== null ? $number($technical['installed_pv_kw'], 2).' kWp' : '—' }}</strong>
                <small>{{ $quotation ? 'Customer-safe technical snapshot' : 'Awaiting quotation snapshot' }}</small>
            </article>

            <article class="nxq-metric-card is-orange">
                <span>Simple payback</span>
                <strong>{{ $quotation && ($financial['simple_payback_years'] ?? null) !== null ? $number($financial['simple_payback_years'], 2).' years' : '—' }}</strong>
                <small>{{ $quotation ? 'Financial estimate from the active snapshot' : 'Awaiting financial basis' }}</small>
            </article>

            <article class="nxq-metric-card is-violet">
                <span>Validity</span>
                <strong>{{ $quotation?->valid_until?->format('d M Y') ?? '—' }}</strong>
                <small>{{ $isExpired ? 'Expired · generate a new revision' : ($quotation?->valid_until ? $quotation->valid_until->diffForHumans() : 'Not established') }}</small>
            </article>
        </section>

        <section id="release-desk" class="nxq-section" data-nxq-section data-nxq-reveal>
            <header class="nxq-section-header">
                <div>
                    <span class="nxq-card-kicker">Controlled document preparation</span>
                    <h3>Quotation release desk</h3>
                    <p>Separate the commercial source basis, working revision, approved client snapshot, generated PDF, and customer delivery milestone so every state remains traceable.</p>
                </div>
                <span class="nxq-section-state {{ $sourceReady ? 'is-ready' : 'is-blocked' }}">{{ $sourceReady ? 'SOURCE READY' : 'SOURCE BLOCKED' }}</span>
            </header>

            <div class="nxq-gate-grid">
                <article class="nxq-gate {{ $bomReady ? 'is-passed' : 'is-blocked' }}">
                    <span class="nxq-gate-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                    <div><small>Commercial source</small><strong>{{ $bomReady ? 'Priced BOQ available' : 'Priced BOQ required' }}</strong><p>{{ $bomReady ? $money($bom->grand_price_idr).' customer total basis' : 'Calculate BOM/BOQ pricing before generating a quotation.' }}</p></div>
                    <span class="nxq-gate-state">{{ $bomReady ? 'PASS' : 'BLOCKED' }}</span>
                </article>

                <article class="nxq-gate {{ $financialReady ? 'is-passed' : 'is-blocked' }}">
                    <span class="nxq-gate-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg></span>
                    <div><small>Financial source</small><strong>{{ $financialReady ? 'Financial analysis calculated' : 'Financial analysis required' }}</strong><p>{{ $financialReady ? 'Customer-safe investment metrics can be snapshotted.' : 'Complete Financial Analysis before generating a quotation.' }}</p></div>
                    <span class="nxq-gate-state">{{ $financialReady ? 'PASS' : 'BLOCKED' }}</span>
                </article>

                <article class="nxq-gate {{ $hasSnapshot ? 'is-passed' : 'is-pending' }}">
                    <span class="nxq-gate-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                    <div><small>Controlled revision</small><strong>{{ $hasSnapshot ? 'Snapshot recorded' : 'Snapshot not generated' }}</strong><p>{{ $hasSnapshot ? 'Hash '.substr((string) $quotation->snapshot_hash, 0, 12).' anchors this revision.' : 'Generate a draft to create a controlled client snapshot.' }}</p></div>
                    <span class="nxq-gate-state">{{ $hasSnapshot ? 'PASS' : 'PENDING' }}</span>
                </article>

                <article class="nxq-gate {{ $warningCount === 0 && $quotation ? 'is-passed' : ($quotation ? 'is-blocked' : 'is-pending') }}">
                    <span class="nxq-gate-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
                    <div><small>Release review</small><strong>{{ ! $quotation ? 'Review not evaluated' : ($warningCount === 0 ? 'No release warnings' : $warningCount.' warning'.($warningCount === 1 ? '' : 's').' require action') }}</strong><p>{{ ! $quotation ? 'Generate a draft before evaluating customer release warnings.' : ($warningCount === 0 ? 'The active snapshot can proceed to approval review.' : 'Resolve warnings and regenerate before approval.') }}</p></div>
                    <span class="nxq-gate-state">{{ ! $quotation ? 'PENDING' : ($warningCount === 0 ? 'PASS' : 'BLOCKED') }}</span>
                </article>

                <article class="nxq-gate {{ $clientApproved ? 'is-passed' : ($quotation ? 'is-pending' : 'is-pending') }}">
                    <span class="nxq-gate-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span>
                    <div><small>Client approval</small><strong>{{ $clientApproved ? 'Approved client snapshot' : 'Approval pending' }}</strong><p>{{ $clientApproved ? 'Approved by '.($quotation->approver?->name ?? 'System').' on '.$quotation->approved_at?->format('d M Y · H:i').'.' : 'Internal approval is required before client PDF generation.' }}</p></div>
                    <span class="nxq-gate-state">{{ $clientApproved ? 'PASS' : 'PENDING' }}</span>
                </article>

                <article class="nxq-gate {{ $hasCurrentPdf ? 'is-passed' : ($clientApproved ? 'is-pending' : 'is-blocked') }}">
                    <span class="nxq-gate-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/><path d="M9 15h6M9 18h4"/></svg></span>
                    <div><small>Customer document</small><strong>{{ $hasCurrentPdf ? 'Current client PDF available' : ($clientApproved ? 'Client PDF generation required' : 'Approval required before PDF') }}</strong><p>{{ $hasCurrentPdf ? 'Generated '.$quotation->client_proposal_pdf_generated_at?->format('d M Y · H:i').'.' : 'A current snapshot-safe PDF is required before sending.' }}</p></div>
                    <span class="nxq-gate-state">{{ $hasCurrentPdf ? 'PASS' : ($clientApproved ? 'PENDING' : 'BLOCKED') }}</span>
                </article>
            </div>

            <div class="nxq-release-layout">
                <article class="nxq-release-form-card">
                    <header>
                        <div><span class="nxq-card-kicker">Working revision</span><h4>{{ $quotation ? 'Prepare the next quotation revision' : 'Generate the first quotation draft' }}</h4></div>
                        <span data-nxq-dirty-state>{{ $quotation ? 'Current revision '.$quotation->revision_number : 'No revision yet' }}</span>
                    </header>

                    @if (! $bomReady)
                        <div class="nxq-blocked-state">
                            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                            <div><h4>Priced BOM/BOQ required</h4><p>Complete commercial pricing before generating a customer proposal.</p></div>
                            <a href="{{ route('companies.boms.show', [$company, $project]) }}" class="nxq-button nxq-button-secondary" data-nxq-navigate="bom">Open BOM &amp; BOQ</a>
                        </div>
                    @elseif (! $financialReady)
                        <div class="nxq-blocked-state">
                            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/></svg></span>
                            <div><h4>Financial Analysis required</h4><p>Calculate the financial basis before generating a customer-safe quotation.</p></div>
                            <a href="{{ route('companies.financial-analyses.show', [$company, $project]) }}" class="nxq-button nxq-button-secondary" data-nxq-navigate="financial">Open Financial Analysis</a>
                        </div>
                    @elseif ($canGenerate)
                        <form class="nxq-release-form" method="POST" action="{{ route('companies.quotations.generate', [$company, $project]) }}" data-nxq-generate-form data-nxq-protect-dirty>
                            @csrf
                            <div class="nxq-form-grid">
                                <div class="nxq-field is-wide">
                                    <label for="quotation-title">Quotation title</label>
                                    <input id="quotation-title" type="text" name="quotation_title" value="{{ old('quotation_title', $parameters['quotation_title']) }}" maxlength="180">
                                    <small>Customer-facing proposal title stored in the controlled revision.</small>
                                </div>
                                <div class="nxq-field">
                                    <label for="quotation-validity">Validity period</label>
                                    <div class="nxq-input-suffix"><input id="quotation-validity" type="number" min="1" max="90" name="validity_days" value="{{ old('validity_days', $parameters['validity_days']) }}"><span>days</span></div>
                                </div>
                                <div class="nxq-field">
                                    <label for="quotation-detail">Customer scope detail</label>
                                    <select id="quotation-detail" name="include_item_detail">
                                        <option value="1" @selected(old('include_item_detail', $parameters['include_item_detail']) == true)>Full item schedule</option>
                                        <option value="0" @selected(old('include_item_detail', $parameters['include_item_detail']) == false)>Package summary only</option>
                                    </select>
                                </div>
                                <div class="nxq-field is-wide"><label for="payment-terms">Payment terms</label><textarea id="payment-terms" name="payment_terms" rows="3">{{ old('payment_terms', $parameters['payment_terms']) }}</textarea></div>
                                <div class="nxq-field is-wide"><label for="delivery-terms">Delivery terms</label><textarea id="delivery-terms" name="delivery_terms" rows="3">{{ old('delivery_terms', $parameters['delivery_terms']) }}</textarea></div>
                                <div class="nxq-field is-wide"><label for="warranty-terms">Warranty terms</label><textarea id="warranty-terms" name="warranty_terms" rows="3">{{ old('warranty_terms', $parameters['warranty_terms']) }}</textarea></div>
                                <div class="nxq-field is-wide"><label for="quotation-exclusions">Exclusions</label><textarea id="quotation-exclusions" name="exclusions" rows="3">{{ old('exclusions', $parameters['exclusions']) }}</textarea></div>
                                <div class="nxq-field is-wide"><label for="quotation-note">Customer note</label><textarea id="quotation-note" name="quotation_note" rows="3">{{ old('quotation_note', $parameters['quotation_note']) }}</textarea></div>
                            </div>
                            <footer class="nxq-form-actions">
                                <span><small>Next revision</small><strong>{{ $quotation ? 'Rev. '.((int) $quotation->revision_number + 1) : 'Rev. 0' }}</strong></span>
                                <button class="nxq-button nxq-button-primary" type="submit">{{ $quotation ? 'Generate revised draft' : 'Generate quotation draft' }}</button>
                            </footer>
                        </form>
                    @else
                        <div class="nxq-blocked-state">
                            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                            <div><h4>View-only quotation access</h4><p>Your role can inspect this customer proposal, but cannot generate or revise it.</p></div>
                        </div>
                    @endif
                </article>

                <aside id="release-control" class="nxq-release-control">
                    <header><span class="nxq-card-kicker">Approval and delivery</span><h4>Controlled release actions</h4></header>

                    @if ($canApprove)
                        <form method="POST" action="{{ route('companies.quotations.approve', [$company, $project]) }}" class="nxq-control-form" data-nxq-action-form="approve">
                            @csrf
                            @method('PATCH')
                            <div class="nxq-field"><label for="approval-notes">Approval notes</label><textarea id="approval-notes" name="approval_notes" rows="3" placeholder="Internal approval note, optional"></textarea></div>
                            <button class="nxq-button nxq-button-primary" type="submit" data-confirm="Approve this quotation snapshot? The revision will become client-safe." data-confirm-title="Approve client snapshot" data-confirm-kicker="Controlled customer release" data-confirm-control="Snapshot approval required" data-confirm-result="This revision becomes eligible for client PDF generation">Approve quotation snapshot</button>
                        </form>
                    @elseif ($canIssue)
                        <form method="POST" action="{{ route('companies.quotations.issue', [$company, $project]) }}" class="nxq-control-form" data-nxq-action-form="send">
                            @csrf
                            @method('PATCH')
                            <div class="nxq-field"><label for="delivery-method">Delivery method</label><select id="delivery-method" name="delivery_method"><option value="manual">Manual</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="download">Downloaded</option><option value="portal">Client Portal</option><option value="other">Other</option></select></div>
                            <div class="nxq-field"><label for="sent-to">Recipient</label><input id="sent-to" name="sent_to" value="{{ old('sent_to', $project->customer?->email ?: $project->customer?->phone) }}" placeholder="Email, phone, or contact name"></div>
                            <div class="nxq-field"><label for="delivery-notes">Delivery notes</label><textarea id="delivery-notes" name="delivery_notes" rows="3" placeholder="Optional delivery note"></textarea></div>
                            @if (! $hasCurrentPdf)
                                <p class="nxq-control-warning">Generate the current approved client PDF before sending.</p>
                            @endif
                            <button class="nxq-button nxq-button-primary" type="submit" data-confirm="Send this approved quotation?" data-confirm-title="Send approved quotation" data-confirm-kicker="Customer delivery decision" data-confirm-control="Approved PDF required" data-confirm-result="The current revision enters the customer response stage">Send quotation</button>
                        </form>
                    @elseif ($quotation)
                        <div class="nxq-control-summary">
                            <span class="nxq-status nxq-status--{{ str_replace('_', '-', $lifecycle) }}"><i></i>{{ $lifecycleLabel }}</span>
                            <p>{{ $currentMilestone }}</p>
                            <small>{{ $currentMilestoneAt?->format('d M Y · H:i') ?? 'No release milestone recorded' }}</small>
                        </div>
                    @else
                        <div class="nxq-control-summary"><span class="nxq-status nxq-status--not-generated"><i></i>Not generated</span><p>Generate the first draft to unlock approval and customer delivery controls.</p></div>
                    @endif
                </aside>
            </div>
        </section>

        <section id="client-preview" class="nxq-section" data-nxq-section data-nxq-reveal>
            <header class="nxq-section-header">
                <div>
                    <span class="nxq-card-kicker">Customer-safe document view</span>
                    <h3>Client proposal preview</h3>
                    <p>Present the selected system, customer investment, project scope, financial estimate, and commercial conditions without exposing internal cost or margin data.</p>
                </div>
                <span class="nxq-section-state {{ $quotation ? 'is-ready' : 'is-pending' }}">{{ $quotation ? strtoupper($quotation->version_display) : 'AWAITING DRAFT' }}</span>
            </header>

            @if (! $quotation)
                <div class="nxq-preview-empty">
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                    <div><h4>No customer proposal revision yet</h4><p>Generate a draft after the priced BOQ and Financial Analysis are complete. The client preview will then use the controlled quotation snapshot.</p></div>
                </div>
            @else
                <article class="nxq-client-document">
                    <header class="nxq-client-document-head">
                        <div class="nxq-client-brand"><span>NEXORIN</span><small>Solar engineering and quotation platform</small></div>
                        <div class="nxq-client-document-id"><small>Quotation</small><strong>{{ $quotation->version_display }}</strong><span>{{ $quotation->status_label }}</span></div>
                    </header>

                    <div class="nxq-client-intro">
                        <div>
                            <span class="nxq-card-kicker">Proposal prepared for</span>
                            <h4>{{ $quotation->customer_snapshot['name'] ?? $project->customer?->name }}</h4>
                            <p>{{ $quotation->project_snapshot['name'] ?? $project->name }}</p>
                        </div>
                        <dl>
                            <div><dt>Site</dt><dd>{{ $quotation->project_snapshot['site_address'] ?? $project->site_address ?? '—' }}</dd></div>
                            <div><dt>Valid until</dt><dd>{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</dd></div>
                            <div><dt>Prepared by</dt><dd>{{ $quotation->preparer?->name ?? 'NEXORIN Engineering' }}</dd></div>
                        </dl>
                    </div>

                    <div class="nxq-client-solution-grid">
                        <article><small>Selected solution</small><strong>{{ str($technical['system_type'] ?? $project->proposed_system ?? 'Solar PV')->replace('_', ' ')->title() }}</strong><span>Controlled engineering configuration</span></article>
                        <article><small>Installed capacity</small><strong>{{ ($technical['installed_pv_kw'] ?? null) !== null ? $number($technical['installed_pv_kw'], 2).' kWp' : '—' }}</strong><span>{{ (int) ($technical['module_count'] ?? 0) > 0 ? number_format((int) $technical['module_count']).' PV modules' : 'Module count unavailable' }}</span></article>
                        <article><small>Annual energy</small><strong>{{ ($technical['annual_energy_kwh'] ?? null) !== null ? $number($technical['annual_energy_kwh'], 0).' kWh' : '—' }}</strong><span>Estimated first-year production</span></article>
                        <article><small>Simple payback</small><strong>{{ ($financial['simple_payback_years'] ?? null) !== null ? $number($financial['simple_payback_years'], 2).' years' : '—' }}</strong><span>Estimate under the active assumptions</span></article>
                    </div>

                    <div class="nxq-client-investment">
                        <div><span class="nxq-card-kicker">Total customer investment</span><strong>{{ $money($commercial['grand_total_idr'] ?? 0) }}</strong><p>{{ $commercial['tax_note'] ?? 'Tax treatment follows the approved BOQ commercial basis.' }}</p></div>
                        <div class="nxq-client-privacy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg><span><strong>Customer-safe scope</strong><small>Internal cost, margin, supplier trace, and protected pricing assumptions are excluded.</small></span></div>
                    </div>
                </article>
            @endif
        </section>

        <section id="scope-pricing" class="nxq-section" data-nxq-section data-nxq-reveal>
            <header class="nxq-section-header">
                <div>
                    <span class="nxq-card-kicker">Customer scope and investment schedule</span>
                    <h3>Scope &amp; pricing</h3>
                    <p>Group the customer-facing material and service schedule into readable commercial sections while retaining each controlled line item and approved selling value.</p>
                </div>
                <span class="nxq-section-state {{ $quotation ? 'is-ready' : 'is-pending' }}">{{ $quotation ? number_format($quotation->items->count()).' LINES' : 'NO SCHEDULE' }}</span>
            </header>

            @if (! $quotation)
                <div class="nxq-preview-empty"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><div><h4>Customer scope has not been generated</h4><p>Create the quotation draft to snapshot the customer-safe material, service, allowance, and tax schedule.</p></div></div>
            @else
                <div class="nxq-scope-groups">
                    @foreach ($groupedItems as $group => $items)
                        @php($groupSubtotal = $items->sum(fn ($item) => (float) $item->line_price_idr))
                        <details class="nxq-scope-group" {{ $loop->first ? 'open' : '' }}>
                            <summary>
                                <span><strong>{{ $group }}</strong><small>{{ number_format($items->count()) }} line {{ \Illuminate\Support\Str::plural('item', $items->count()) }}</small></span>
                                <span><strong>{{ $money($groupSubtotal) }}</strong><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></span>
                            </summary>
                            <div class="nxq-scope-table" role="table" aria-label="{{ $group }} quotation items">
                                <div class="nxq-scope-head" role="row"><span>Customer scope</span><span>Quantity</span><span>Unit price</span><span>Line total</span></div>
                                @foreach ($items as $item)
                                    <article class="nxq-scope-row" role="row">
                                        <div class="nxq-scope-item"><strong>{{ $item->item_name }}</strong><span>{{ $item->item_code ?: str($item->category)->replace('_', ' ')->title() }}</span>@if ($item->description)<details><summary>Scope detail</summary><p>{{ $item->description }}</p></details>@endif</div>
                                        <span class="nxq-scope-quantity"><b>{{ $quantity($item->quantity, $item->unit) }}</b> {{ $item->unit }}</span>
                                        <span>{{ $money($item->unit_price_idr) }}</span>
                                        <strong class="{{ (float) $item->line_price_idr < 0 ? 'is-negative' : '' }}">{{ $money($item->line_price_idr) }}</strong>
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>

                <article class="nxq-grand-total">
                    <div><span class="nxq-card-kicker">Approved customer selling total</span><h4>Total investment</h4><p>{{ $commercial['tax_note'] ?? 'Tax treatment follows the approved BOQ pricing basis.' }}</p></div>
                    <div><strong>{{ $money($commercial['grand_total_idr'] ?? 0) }}</strong><span>Valid until {{ $quotation->valid_until?->format('d M Y') ?? 'not established' }}</span></div>
                </article>
            @endif
        </section>

        <section id="commercial-terms" class="nxq-section" data-nxq-section data-nxq-reveal>
            <header class="nxq-section-header">
                <div>
                    <span class="nxq-card-kicker">Controlled commercial conditions</span>
                    <h3>Commercial terms</h3>
                    <p>Keep customer conditions readable by default while preserving the complete approved wording inside expandable commercial clauses.</p>
                </div>
                <span class="nxq-section-state {{ $quotation ? 'is-ready' : 'is-pending' }}">{{ $quotation ? 'CONTROLLED SNAPSHOT' : 'AWAITING DRAFT' }}</span>
            </header>

            @if (! $quotation)
                <div class="nxq-preview-empty"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span><div><h4>No commercial terms snapshot</h4><p>Payment, delivery, warranty, exclusions, and customer notes will be recorded when a quotation draft is generated.</p></div></div>
            @else
                <div class="nxq-terms-grid">
                    @foreach ([
                        ['Payment terms', $terms['payment_terms'] ?? '—', 'payment'],
                        ['Delivery terms', $terms['delivery_terms'] ?? '—', 'delivery'],
                        ['Warranty terms', $terms['warranty_terms'] ?? '—', 'warranty'],
                        ['Exclusions', $terms['exclusions'] ?? '—', 'exclusions'],
                        ['Customer note', $terms['quotation_note'] ?? 'No additional customer note.', 'note'],
                    ] as [$label, $copy, $icon])
                        <details class="nxq-term-card {{ $icon === 'note' ? 'is-note' : '' }}" {{ $loop->first ? 'open' : '' }}>
                            <summary><span class="nxq-term-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span><span><strong>{{ $label }}</strong><small>Controlled customer condition</small></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                            <p>{{ $copy }}</p>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>

        <section id="customer-decision" class="nxq-section" data-nxq-section data-nxq-reveal>
            <header class="nxq-section-header">
                <div>
                    <span class="nxq-card-kicker">Controlled customer response</span>
                    <h3>Customer decision</h3>
                    <p>Expose one terminal decision form at a time and record the actor, reason, timestamp, and associated quotation revision through the existing controlled lifecycle.</p>
                </div>
                <span class="nxq-section-state {{ ($canAccept || $canReject || $canCancel) ? 'is-ready' : 'is-pending' }}">{{ ($canAccept || $canReject || $canCancel) ? 'ACTION AVAILABLE' : strtoupper($lifecycleLabel) }}</span>
            </header>

            @if ($canAccept || $canReject || $canCancel)
                <div class="nxq-decision-panel" data-nxq-decision-panel>
                    <div class="nxq-decision-current">
                        <span class="nxq-status nxq-status--{{ str_replace('_', '-', $lifecycle) }}"><i></i>{{ $lifecycleLabel }}</span>
                        <div><strong>Awaiting a controlled customer response</strong><p>Select one available action. The corresponding form will open without exposing competing terminal actions.</p></div>
                    </div>
                    <div class="nxq-decision-actions" role="tablist" aria-label="Customer decision actions">
                        @if ($canAccept)<button type="button" role="tab" data-nxq-decision="accept"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/></svg>Record acceptance</button>@endif
                        @if ($canReject)<button type="button" role="tab" data-nxq-decision="reject" class="is-warning"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6 6 18"/></svg>Record rejection</button>@endif
                        @if ($canCancel)<button type="button" role="tab" data-nxq-decision="cancel" class="is-danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 7h12M9 7V4h6v3M8 7l1 13h6l1-13"/></svg>Cancel quotation</button>@endif
                    </div>
                    <div class="nxq-decision-forms">
                        @if ($canAccept)
                            <form method="POST" action="{{ route('companies.quotations.accept', [$company, $project]) }}" data-nxq-decision-form="accept" data-nxq-action-form="accept" hidden>
                                @csrf
                                @method('PATCH')
                                <div class="nxq-field"><label for="acceptance-notes">Acceptance notes</label><textarea id="acceptance-notes" name="acceptance_notes" rows="3" placeholder="Optional customer acceptance note"></textarea></div>
                                <button class="nxq-button nxq-button-primary" type="submit" data-confirm="Record this quotation as accepted?" data-confirm-title="Record customer acceptance" data-confirm-kicker="Terminal customer decision" data-confirm-control="Accepted revision: {{ $quotation->version_display }}" data-confirm-result="The project may move to the accepted stage">Confirm acceptance</button>
                            </form>
                        @endif
                        @if ($canReject)
                            <form method="POST" action="{{ route('companies.quotations.reject', [$company, $project]) }}" data-nxq-decision-form="reject" data-nxq-action-form="reject" hidden>
                                @csrf
                                @method('PATCH')
                                <div class="nxq-field"><label for="rejection-reason">Rejection reason</label><textarea id="rejection-reason" name="rejection_reason" rows="3" placeholder="Required customer rejection reason" required></textarea></div>
                                <button class="nxq-button nxq-button-warning" type="submit" data-confirm="Record this quotation as rejected?" data-confirm-title="Record customer rejection" data-confirm-kicker="Terminal customer decision" data-confirm-control="Rejection reason required" data-confirm-result="The active customer revision becomes rejected">Confirm rejection</button>
                            </form>
                        @endif
                        @if ($canCancel)
                            <form method="POST" action="{{ route('companies.quotations.cancel', [$company, $project]) }}" data-nxq-decision-form="cancel" data-nxq-action-form="cancel" hidden>
                                @csrf
                                @method('PATCH')
                                <div class="nxq-field"><label for="cancellation-reason">Cancellation reason</label><textarea id="cancellation-reason" name="cancellation_reason" rows="3" placeholder="Optional internal cancellation reason"></textarea></div>
                                <button class="nxq-button nxq-button-danger" type="submit" data-confirm="Cancel this quotation?" data-confirm-title="Cancel active quotation" data-confirm-kicker="Destructive lifecycle decision" data-confirm-control="Current revision: {{ $quotation->version_display }}" data-confirm-result="The quotation becomes unavailable for customer acceptance">Confirm cancellation</button>
                            </form>
                        @endif
                    </div>
                </div>
            @elseif ($quotation)
                <div class="nxq-decision-closed">
                    <span class="nxq-status nxq-status--{{ str_replace('_', '-', $lifecycle) }}"><i></i>{{ $lifecycleLabel }}</span>
                    <div><h4>{{ $currentMilestone }}</h4><p>{{ $currentMilestoneAt?->format('d M Y · H:i') ?? 'No customer response action is currently available.' }}</p></div>
                </div>
            @else
                <div class="nxq-preview-empty"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg></span><div><h4>Customer decision is not available</h4><p>Generate, approve, create the current PDF, and send the quotation before recording a customer response.</p></div></div>
            @endif
        </section>

        <section id="audit" class="nxq-section" data-nxq-section data-nxq-reveal>
            <header class="nxq-section-header">
                <div>
                    <span class="nxq-card-kicker">Revision and lifecycle trace</span>
                    <h3>Audit &amp; document governance</h3>
                    <p>Separate revision history, customer lifecycle events, current snapshot identity, and generated-document state for a complete quotation record.</p>
                </div>
                <span class="nxq-section-state {{ $quotation ? 'is-ready' : 'is-pending' }}">{{ $quotation ? 'TRACE RECORDED' : 'NO TRACE' }}</span>
            </header>

            @if (! $quotation)
                <div class="nxq-preview-empty"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><div><h4>No quotation audit record</h4><p>The audit timeline begins when the first controlled quotation revision is generated.</p></div></div>
            @else
                <div class="nxq-audit-grid">
                    <article class="nxq-audit-summary">
                        <span class="nxq-card-kicker">Active controlled record</span>
                        <dl>
                            <div><dt>Quotation</dt><dd>{{ $quotation->version_display }}</dd></div>
                            <div><dt>Lifecycle</dt><dd>{{ $lifecycleLabel }}</dd></div>
                            <div><dt>Snapshot hash</dt><dd>{{ $quotation->snapshot_hash ? substr($quotation->snapshot_hash, 0, 16) : 'Missing' }}</dd></div>
                            <div><dt>Source fingerprint</dt><dd>{{ $quotation->source_fingerprint ? substr($quotation->source_fingerprint, 0, 16) : 'Missing' }}</dd></div>
                            <div><dt>Client PDF</dt><dd>{{ $hasCurrentPdf ? 'Current snapshot' : 'Generation required' }}</dd></div>
                            <div><dt>Valid until</dt><dd>{{ $quotation->valid_until?->format('d M Y') ?? 'Not set' }}</dd></div>
                        </dl>
                    </article>

                    <article class="nxq-timeline-card">
                        <header><span class="nxq-card-kicker">Lifecycle events</span><strong>{{ number_format($quotation->events->count()) }} events</strong></header>
                        @if ($quotation->events->isEmpty())
                            <p class="nxq-timeline-empty">No lifecycle events recorded.</p>
                        @else
                            <ol class="nxq-timeline">
                                @foreach ($quotation->events as $event)
                                    <li><span></span><div><strong>{{ $event->event_label }}</strong><p>{{ $event->notes ?: 'Controlled quotation lifecycle event.' }}</p><small>{{ $event->actor?->name ?? 'System' }} · {{ $event->event_at?->format('d M Y · H:i') }}{{ $event->channel ? ' · '.str($event->channel)->title() : '' }}{{ $event->recipient ? ' · '.$event->recipient : '' }}</small></div></li>
                                @endforeach
                            </ol>
                        @endif
                    </article>

                    <article class="nxq-timeline-card">
                        <header><span class="nxq-card-kicker">Revision history</span><strong>{{ number_format($quotation->versions->count()) }} revisions</strong></header>
                        @if ($quotation->versions->isEmpty())
                            <p class="nxq-timeline-empty">No archived revisions recorded.</p>
                        @else
                            <ol class="nxq-timeline is-revisions">
                                @foreach ($quotation->versions as $version)
                                    <li class="{{ (int) $version->revision_number === (int) $quotation->revision_number ? 'is-current' : '' }}"><span></span><div><strong>{{ $version->version_label ?? ('Rev. '.$version->revision_number) }}</strong><p>{{ str($version->status)->replace('_', ' ')->title() }}{{ $version->snapshot_hash ? ' · Snapshot '.substr($version->snapshot_hash, 0, 10) : '' }}</p><small>{{ $version->created_at?->format('d M Y · H:i') }}</small></div></li>
                                @endforeach
                            </ol>
                        @endif
                    </article>
                </div>
            @endif
        </section>

        <footer class="nxq-workspace-footer">
            <a href="{{ route('companies.quotations.index', $company) }}" class="nxq-button nxq-button-secondary" data-nxq-navigate="directory">Back to quotation registry</a>
            <a href="{{ route('companies.financial-analyses.show', [$company, $project]) }}" class="nxq-button nxq-button-secondary" data-nxq-navigate="financial">Open Financial Analysis</a>
            <a href="{{ route('companies.boms.show', [$company, $project]) }}" class="nxq-button nxq-button-secondary" data-nxq-navigate="bom">Open BOM &amp; BOQ</a>
        </footer>
    </div>
@endsection
