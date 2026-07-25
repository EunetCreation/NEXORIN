@extends('layouts.app')

@section('title', 'BOM/BOQ')
@section('page-eyebrow', 'Commercial engineering')
@section('page-heading', 'BOM/BOQ')
@section('suppress-global-feedback', true)

@php
    $summary = $bom?->summary ?? [];
    $warnings = $bom?->warnings ?? [];
    $pricingParameters = old() ?: ($bom?->pricing_parameters ?? $pricingDefaults);
    $label = \App\Support\BomBoqDictionary::class;
    $items = $bom?->items ?? collect();
    $hasCalculation = (bool) $calculation?->calculated_at;
    $hasBom = (bool) $bom;
    $isVerified = $calculation?->result_status === 'verified';
    $isPriced = $bom?->pricing_status === 'priced';
    $lineItemCount = $items->count();
    $groupedItems = $items->groupBy(fn ($item) => data_get($item->metadata, 'cost_group', $item->category ?: 'general'));
    $scopeCounts = $items->countBy(fn ($item) => data_get($item->metadata, 'scope_status', 'required'));
    $eligibleItems = $items->filter(function ($item) {
        $scope = data_get($item->metadata, 'scope_status', 'required');
        $state = data_get($item->metadata, 'pricing_state', $item->unit_price_idr > 0 ? 'priced' : 'pending');
        return ! in_array($scope, ['utility', 'owner', 'not_applicable'], true) && $state !== 'excluded_scope';
    });
    $pricedItems = $eligibleItems->filter(fn ($item) => (float) $item->unit_price_idr > 0 || data_get($item->metadata, 'pricing_state') === 'priced');
    $pricingCoverage = $eligibleItems->count() > 0 ? min(100, round(($pricedItems->count() / $eligibleItems->count()) * 100)) : 0;
    $reviewStates = ['expired_price_warning', 'supplier_unavailable_warning', 'margin_review_required', 'discount_review_required', 'tax_review_required', 'commercial_total_mismatch', 'missing_price', 'needs_pricing', 'catalog_candidate'];
    $reviewItemCount = $items->filter(fn ($item) => in_array(data_get($item->metadata, 'pricing_state', 'pending'), $reviewStates, true))->count();
    $minimumMargin = (float) ($summary['min_margin_percent'] ?? $pricingParameters['min_margin_percent'] ?? 10);
    $marginPercent = (float) ($bom?->gross_margin_percent ?? 0);
    $marginPass = $isPriced && $marginPercent >= $minimumMargin;
    $discountPercent = (float) ($pricingParameters['discount_percent'] ?? 0);
    $maximumDiscount = (float) ($summary['max_discount_percent'] ?? $pricingParameters['max_discount_percent'] ?? 10);
    $discountPass = $discountPercent <= $maximumDiscount;
    $commercialReady = $isPriced && $pricingCoverage === 100 && $reviewItemCount === 0 && $marginPass && $discountPass;
    $money = fn ($value, $pending = true) => ((float) $value > 0 ? 'IDR '.number_format((float) $value, 0, ',', '.') : ($pending ? 'Pending' : 'IDR 0'));
    $quantity = function ($value) {
        $number = (float) $value;
        if (abs($number - round($number)) < 0.0001) return number_format($number, 0, '.', ',');
        return rtrim(rtrim(number_format($number, 3, '.', ','), '0'), '.');
    };
    $installedPvKw = $summary['installed_pv_kw'] ?? data_get($calculation?->results, 'installed_pv_kw');
    $projectMetric = $installedPvKw !== null ? number_format((float) $installedPvKw, 2, '.', ',').' kWp' : '—';
    $errorMessages = $errors->all();
    $technicalRefreshRequired = collect($errorMessages)->contains(function ($message) {
        $normalized = strtolower((string) $message);
        return str_contains($normalized, 'source data changed')
            || str_contains($normalized, 'technical calculation')
            || str_contains($normalized, 'recalculate before generating');
    });
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-bom-boq.css') }}?v=56a" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-bom-boq.js') }}?v=55a" defer></script>
@endpush

@section('content')
    <div
        class="nxbom-workspace"
        data-nxbom-workspace
        data-nxbom-project="{{ $project->name }}"
        data-nxbom-project-number="{{ $project->project_number }}"
    >
        <header class="nxbom-workspace-hero" aria-labelledby="nxbom-workspace-title" data-nxbom-reveal>
            <span class="nxbom-hero-grid" aria-hidden="true"></span>
            <span class="nxbom-hero-orb nxbom-hero-orb-one" aria-hidden="true"></span>
            <span class="nxbom-hero-orb nxbom-hero-orb-two" aria-hidden="true"></span>

            <div class="nxbom-workspace-copy">
                <span class="nxbom-kicker">Material and commercial cockpit</span>

                <div class="nxbom-module-lockup">
                    <span class="nxbom-module-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path class="nxbom-icon-main" d="M4.5 6.8 12 3l7.5 3.8v10.4L12 21l-7.5-3.8Z"/>
                            <path class="nxbom-icon-soft" d="m4.8 6.9 7.2 3.7 7.2-3.7M12 10.6V21"/>
                            <path class="nxbom-icon-accent" d="M8.1 8.6 15.8 4.8M15.9 15.5h2.2M15.9 18h2.2"/>
                        </svg>
                    </span>
                    <div class="nxbom-module-copy">
                        <div class="nxbom-module-title-row">
                            <h2 id="nxbom-workspace-title">BOM <em>/</em> BOQ</h2>
                            <span class="nxbom-module-scope">Controlled commercial schedule</span>
                        </div>
                        <p class="nxbom-module-definition">
                            <span>Bill of Materials</span><i aria-hidden="true"></i><span>Bill of Quantities</span>
                        </p>
                    </div>
                </div>

                <div class="nxbom-project-context">
                    <div class="nxbom-project-context-copy">
                        <span>Active project</span>
                        <strong>{{ $project->name }}</strong>
                        <div class="nxbom-project-line">
                            <span>{{ $project->project_number }}</span>
                            <i></i>
                            <span>{{ $project->customer?->name ?? 'Customer unavailable' }}</span>
                        </div>
                    </div>
                    <div class="nxbom-project-context-metrics" aria-label="Project engineering context">
                        <span>
                            <small>System</small>
                            <strong>{{ str($project->proposed_system)->replace('_', ' ')->title() }}</strong>
                        </span>
                        <span>
                            <small>Design</small>
                            <strong>{{ $projectMetric }}</strong>
                        </span>
                    </div>
                </div>

                <p class="nxbom-hero-description">
                    Govern quantities, scope responsibility, source pricing, margin authority, and the customer-safe commercial total from one controlled record.
                </p>

                <div class="nxbom-hero-statuses" aria-label="BOM and BOQ status">
                    <span class="{{ $hasCalculation ? 'is-ready' : 'is-pending' }}"><i></i>{{ $hasCalculation ? ($isVerified ? 'Engineering verified' : 'Engineering calculated') : 'Engineering required' }}</span>
                    <span class="{{ $hasBom ? 'is-ready' : 'is-pending' }}"><i></i>{{ $hasBom ? 'BOM generated' : 'BOM not generated' }}</span>
                    <span class="{{ $isPriced ? 'is-ready' : 'is-pending' }}"><i></i>{{ $isPriced ? 'BOQ priced' : 'Pricing pending' }}</span>
                </div>
            </div>

            <aside class="nxbom-hero-command" aria-label="BOM and BOQ command panel">
                <div class="nxbom-command-topline">
                    <span class="nxbom-command-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4.5 5.5h15v13h-15z"/>
                            <path d="M8 9h8M8 12h8M8 15h4"/>
                            <circle cx="18" cy="18" r="3.2"/>
                            <path d="m16.7 18 1 1 1.8-2"/>
                        </svg>
                    </span>
                    <div>
                        <small>Commercial state</small>
                        <strong>{{ $commercialReady ? 'Release ready' : ($isPriced ? 'Review required' : ($hasBom ? 'Pricing required' : 'Quantity required')) }}</strong>
                    </div>
                    <span class="nxbom-command-state {{ $commercialReady ? 'is-ready' : ($isPriced ? 'is-review' : 'is-pending') }}">
                        {{ $commercialReady ? 'Ready' : ($isPriced ? 'Review' : 'Pending') }}
                    </span>
                </div>

                <div class="nxbom-command-readout">
                    <span>Controlled record</span>
                    <strong>{{ $bom?->bom_number ?? 'No BOM identity assigned' }}</strong>
                    <small>{{ $hasBom ? 'Latest generated material and commercial basis' : 'Generate the schedule to establish a controlled identity' }}</small>
                </div>

                <div class="nxbom-command-metrics" aria-label="Commercial control metrics">
                    <span>
                        <small>Line items</small>
                        <strong>{{ number_format($lineItemCount) }}</strong>
                    </span>
                    <span>
                        <small>Price coverage</small>
                        <strong>{{ $pricingCoverage }}%</strong>
                    </span>
                    <span>
                        <small>Gross margin</small>
                        <strong>{{ $canViewCost && $canViewSelling ? ($isPriced ? number_format($marginPercent, 2).'%' : '—') : 'Restricted' }}</strong>
                    </span>
                </div>

                <nav class="nxbom-hero-actions" aria-label="Related project workspaces">
                    <a href="{{ route('companies.boms.index', $company) }}" data-nxbom-navigate="directory">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 5h14v14H5zM8 9h8M8 12h8M8 15h5"/></svg>
                        <span>Registry</span>
                    </a>
                    <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}" data-nxbom-navigate="technical">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h3M14 12h2M8 16h2M13 16h3"/></svg>
                        <span>Technical</span>
                    </a>
                    <a href="{{ route('companies.financial-analyses.show', [$company, $project]) }}" data-nxbom-navigate="financial">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 19V9M10 19V5M15 19v-7M20 19V8M3.5 19.5h18"/></svg>
                        <span>Financial</span>
                    </a>
                    <a href="{{ route('companies.projects.show', [$company, $project]) }}" data-nxbom-navigate="cockpit">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.5 4.5h6v6h-6zM13.5 4.5h6v6h-6zM4.5 13.5h6v6h-6zM13.5 13.5h6v6h-6z"/></svg>
                        <span>Cockpit</span>
                    </a>
                </nav>
            </aside>
        </header>

        @if (session('success'))
            <div class="nx-feedback nx-feedback--success" role="status" data-alert data-nxbom-reveal>
                <span class="nx-feedback-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg>
                </span>
                <div class="nx-feedback-body">
                    <span class="nx-feedback-kicker">Commercial record synchronized</span>
                    <strong class="nx-feedback-title">BOM/BOQ workspace updated</strong>
                    <p class="nx-feedback-copy">{{ session('success') }}</p>
                </div>
                <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>
                </button>
            </div>
        @endif

        @if (session('error'))
            <div class="nx-feedback nx-feedback--danger" role="alert" data-alert data-nxbom-reveal>
                <span class="nx-feedback-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <div class="nx-feedback-body">
                    <span class="nx-feedback-kicker">Commercial workflow exception</span>
                    <strong class="nx-feedback-title">The requested BOM/BOQ action was blocked</strong>
                    <p class="nx-feedback-copy">{{ session('error') }}</p>
                </div>
                <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="nx-feedback nx-feedback--{{ $technicalRefreshRequired ? 'warning' : 'danger' }}" role="alert" data-alert data-nxbom-reveal>
                <span class="nx-feedback-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <div class="nx-feedback-body">
                    <span class="nx-feedback-kicker">{{ $technicalRefreshRequired ? 'Engineering source out of date' : 'Commercial input review' }}</span>
                    <strong class="nx-feedback-title">{{ $technicalRefreshRequired ? 'Refresh the technical basis before generation' : 'BOM/BOQ could not be processed' }}</strong>
                    <p class="nx-feedback-copy">
                        {{ $technicalRefreshRequired
                            ? 'Source evidence changed after the latest engine run. Recalculate the controlled engineering basis before generating a new material schedule.'
                            : 'Review the controlled inputs below, then repeat the requested commercial action.' }}
                    </p>
                    <ul class="nx-feedback-list">
                        @foreach ($errorMessages as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <div class="nx-feedback-meta">
                        <span><small>Status</small><b>{{ $technicalRefreshRequired ? 'GENERATION BLOCKED' : 'INPUT REVIEW REQUIRED' }}</b></span>
                        <span><small>Required control</small><b>{{ $technicalRefreshRequired ? 'RECALCULATE TECHNICAL BASIS' : 'CORRECT COMMERCIAL INPUTS' }}</b></span>
                    </div>
                </div>
                <div class="nx-feedback-actions">
                    @if ($technicalRefreshRequired)
                        <a class="nx-feedback-action" href="{{ route('companies.technical-calculations.show', [$company, $project]) }}" data-nxbom-navigate="technical">
                            Open Technical Calculation
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                        </a>
                    @endif
                    <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>
                    </button>
                </div>
            </div>
        @endif

        <nav class="nxbom-command-bar" aria-label="BOM and BOQ workspace sections" data-nxbom-command-bar>
            <a href="#nxbom-overview" class="is-active">Overview</a>
            <a href="#nxbom-materials">Material Schedule</a>
            <a href="#nxbom-pricing">Pricing</a>
            <a href="#nxbom-checks">Release Gate</a>
            <a href="#nxbom-audit">Audit</a>
            <span class="nxbom-command-context">{{ $lineItemCount }} line items · {{ $pricingCoverage }}% coverage</span>
        </nav>

        <section id="nxbom-overview" class="nxbom-section nxbom-overview" data-nxbom-reveal>
            <header class="nxbom-section-heading">
                <div>
                    <span>Commercial engineering overview</span>
                    <h3>Scope, price, and release authority</h3>
                    <p>One controlled view of the engineering source, material completeness, commercial coverage, and customer-facing result.</p>
                </div>
                @if ($hasCalculation && $canGenerate)
                    <form method="POST" action="{{ route('companies.boms.generate', [$company, $project]) }}" data-nxbom-action-form="generate">
                        @csrf
                        <button type="submit" class="nxbom-primary-action">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/><path d="m3 6 2 2 3-4"/></svg>
                            {{ $hasBom ? 'Regenerate schedule' : 'Generate BOM & BOQ' }}
                        </button>
                    </form>
                @endif
            </header>

            @if (! $hasCalculation)
                <div class="nxbom-readiness-gate is-blocked">
                    <div class="nxbom-gate-copy">
                        <span class="nxbom-gate-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/></svg>
                        </span>
                        <div>
                            <span class="nxbom-gate-kicker">Source gate blocked</span>
                            <h4>Technical Calculation is required</h4>
                            <p>The material engine cannot establish defensible quantities until the latest electrical design has been calculated.</p>
                        </div>
                    </div>
                    <ol class="nxbom-gate-sequence">
                        <li class="is-complete"><span>01</span><div><strong>Project evidence</strong><small>Load, solar, roof, and recommendation context retained.</small></div></li>
                        <li class="is-active"><span>02</span><div><strong>Technical Calculation</strong><small>Run the electrical design and source snapshot.</small></div></li>
                        <li><span>03</span><div><strong>Material generation</strong><small>Resolve quantities, scope, and traceability.</small></div></li>
                        <li><span>04</span><div><strong>Commercial pricing</strong><small>Apply price books, guards, tax, and discount.</small></div></li>
                    </ol>
                    <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}" class="nxbom-gate-action" data-nxbom-navigate="technical">Open Technical Calculation</a>
                </div>
            @elseif (! $hasBom)
                <div class="nxbom-readiness-gate is-ready">
                    <div class="nxbom-gate-copy">
                        <span class="nxbom-gate-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg>
                        </span>
                        <div>
                            <span class="nxbom-gate-kicker">Engineering basis available</span>
                            <h4>Ready to generate the material schedule</h4>
                            <p>The latest technical result can now be translated into required, conditional, optional, service, owner, and utility scope.</p>
                        </div>
                    </div>
                    <div class="nxbom-source-snapshot">
                        <span><small>Calculation status</small><strong>{{ $calculation->result_status_label }}</strong></span>
                        <span><small>Installed capacity</small><strong>{{ $projectMetric }}</strong></span>
                        <span><small>Calculated at</small><strong>{{ $calculation->calculated_at?->format('d M Y · H:i') }}</strong></span>
                    </div>
                </div>
            @else
                <div class="nxbom-overview-grid">
                    <article class="nxbom-kpi-card is-dark">
                        <span class="nxbom-kpi-label">Commercial total</span>
                        <strong>{{ $isPriced ? $money($bom->grand_price_idr) : 'Not priced' }}</strong>
                        <small>{{ $isPriced ? 'Customer-facing total after tax, discount, and rounding.' : 'Apply commercial parameters to establish the customer total.' }}</small>
                    </article>
                    <article class="nxbom-kpi-card">
                        <span class="nxbom-kpi-label">Price coverage</span>
                        <strong>{{ $pricingCoverage }}%</strong>
                        <span class="nxbom-kpi-track"><i style="width:{{ $pricingCoverage }}%"></i></span>
                        <small>{{ $pricedItems->count() }} of {{ $eligibleItems->count() }} commercially eligible items resolved.</small>
                    </article>
                    <article class="nxbom-kpi-card">
                        <span class="nxbom-kpi-label">Material scope</span>
                        <strong>{{ number_format($lineItemCount) }} <em>lines</em></strong>
                        <small>{{ number_format((int) ($scopeCounts['required'] ?? 0)) }} required · {{ number_format((int) ($scopeCounts['conditional'] ?? 0)) }} conditional · {{ number_format((int) ($scopeCounts['optional'] ?? 0)) }} optional</small>
                    </article>
                    <article class="nxbom-kpi-card {{ $reviewItemCount > 0 ? 'is-warning' : 'is-success' }}">
                        <span class="nxbom-kpi-label">Commercial review</span>
                        <strong>{{ number_format($reviewItemCount) }} <em>{{ \Illuminate\Support\Str::plural('issue', $reviewItemCount) }}</em></strong>
                        <small>{{ $reviewItemCount > 0 ? 'Price, supplier, margin, discount, tax, or catalog attention remains.' : 'No item-level commercial review state is recorded.' }}</small>
                    </article>
                </div>

                <div class="nxbom-flow-card">
                    <div class="nxbom-flow-head">
                        <span>Commercial build-up</span>
                        <strong>Engineering quantity to customer value</strong>
                    </div>
                    <div class="nxbom-flow-line">
                        <span><i>01</i><small>Engineering</small><strong>{{ $projectMetric }}</strong></span>
                        <b>→</b>
                        <span><i>02</i><small>Material cost</small><strong>{{ $canViewCost ? $money($bom->material_cost_subtotal_idr) : 'Restricted' }}</strong></span>
                        <b>→</b>
                        <span><i>03</i><small>Services &amp; overhead</small><strong>{{ $canViewSelling ? $money((float) $bom->labor_cost_idr + (float) $bom->logistics_cost_idr + (float) $bom->other_cost_idr + (float) $bom->contingency_cost_idr) : 'Restricted' }}</strong></span>
                        <b>→</b>
                        <span><i>04</i><small>Tax &amp; discount</small><strong>{{ $canViewSelling ? $money((float) $bom->tax_idr - (float) $bom->discount_idr, false) : 'Restricted' }}</strong></span>
                        <b>→</b>
                        <span class="is-result"><i>05</i><small>Grand total</small><strong>{{ $canViewSelling ? $money($bom->grand_price_idr) : 'Restricted' }}</strong></span>
                    </div>
                </div>
            @endif
        </section>

        <section id="nxbom-materials" class="nxbom-section nxbom-material-section" data-nxbom-reveal>
            <header class="nxbom-section-heading">
                <div>
                    <span>Controlled material schedule</span>
                    <h3>Grouped BOM &amp; BOQ lines</h3>
                    <p>Compact rows keep identity, scope, quantity, price, and review state visible. Engineering derivation and specification remain available on demand.</p>
                </div>
                @if ($hasBom)
                    <div class="nxbom-section-metrics">
                        <span><small>Groups</small><strong>{{ $groupedItems->count() }}</strong></span>
                        <span><small>Lines</small><strong>{{ $lineItemCount }}</strong></span>
                        <span><small>Review</small><strong>{{ $reviewItemCount }}</strong></span>
                    </div>
                @endif
            </header>

            @if (! $hasBom)
                <div class="nxbom-section-empty">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    </span>
                    <div>
                        <h4>Material schedule not generated</h4>
                        <p>{{ $hasCalculation ? 'Generate the BOM and BOQ to resolve grouped quantities and scope responsibility.' : 'Complete Technical Calculation before the material schedule can be evaluated.' }}</p>
                    </div>
                </div>
            @else
                <div class="nxbom-material-toolbar" data-nxbom-material-toolbar>
                    <label class="nxbom-material-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input type="search" placeholder="Search item, code, category, or specification" data-nxbom-item-search autocomplete="off">
                    </label>
                    <label>
                        <span>Scope</span>
                        <select data-nxbom-scope-filter>
                            <option value="all">All scopes</option>
                            <option value="required">Required</option>
                            <option value="conditional">Conditional</option>
                            <option value="optional">Optional</option>
                            <option value="service">Service</option>
                            <option value="utility">Utility</option>
                            <option value="owner">Owner</option>
                        </select>
                    </label>
                    <label>
                        <span>Pricing</span>
                        <select data-nxbom-price-filter>
                            <option value="all">All states</option>
                            <option value="priced">Priced</option>
                            <option value="review">Needs review</option>
                            <option value="excluded">Excluded</option>
                        </select>
                    </label>
                    <button type="button" data-nxbom-expand-groups>Expand groups</button>
                    <span class="nxbom-material-result" data-nxbom-material-result>{{ $lineItemCount }} visible lines</span>
                </div>

                <div class="nxbom-material-groups" data-nxbom-material-groups>
                    @foreach ($groupedItems as $groupKey => $groupItems)
                        @php
                            $groupReviewCount = $groupItems->filter(fn ($item) => in_array(data_get($item->metadata, 'pricing_state', 'pending'), $reviewStates, true))->count();
                            $groupCost = $groupItems->sum('line_cost_idr');
                            $groupPrice = $groupItems->sum('line_price_idr');
                        @endphp
                        <details class="nxbom-material-group" open data-nxbom-group>
                            <summary>
                                <span class="nxbom-group-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="nxbom-group-copy">
                                    <strong>{{ $label::costGroupLabel($groupKey) }}</strong>
                                    <small>{{ $groupItems->count() }} {{ \Illuminate\Support\Str::plural('line', $groupItems->count()) }} · {{ $groupReviewCount }} review</small>
                                </span>
                                <span class="nxbom-group-commercial">
                                    @if ($canViewCost)<small>Cost <b>{{ $money($groupCost) }}</b></small>@endif
                                    @if ($canViewSelling)<small>Price <b>{{ $money($groupPrice) }}</b></small>@endif
                                </span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7 10 5 5 5-5"/></svg>
                            </summary>

                            <div class="nxbom-item-list {{ $canViewCost ? 'has-cost' : 'no-cost' }} {{ $canViewSelling ? 'has-selling' : 'no-selling' }}">
                                <div class="nxbom-item-labels" aria-hidden="true">
                                    <span>Item and scope</span><span>Quantity</span>
                                    @if ($canViewCost)<span>Line cost</span>@endif
                                    @if ($canViewSelling)<span>Line price</span>@endif
                                    <span>State</span>
                                </div>
                                @foreach ($groupItems as $item)
                                    @php
                                        $metadata = $item->metadata ?? [];
                                        $scopeStatus = $metadata['scope_status'] ?? 'required';
                                        $pricingState = $metadata['pricing_state'] ?? ($item->unit_price_idr > 0 ? 'priced' : 'pending');
                                        $priceTrace = $metadata['pricing_source_trace'] ?? [];
                                        $filterPriceState = in_array($pricingState, ['excluded_scope', 'not_applicable'], true)
                                            ? 'excluded'
                                            : (in_array($pricingState, $reviewStates, true) ? 'review' : 'priced');
                                        $searchText = strtolower(implode(' ', [
                                            $item->item_code,
                                            $item->item_name,
                                            $item->description,
                                            $item->category,
                                            $label::categoryLabel($item->category),
                                            $metadata['calculation_basis'] ?? '',
                                            json_encode($item->specification ?? []),
                                        ]));
                                    @endphp
                                    <details
                                        class="nxbom-item-row"
                                        data-nxbom-item
                                        data-scope="{{ $scopeStatus }}"
                                        data-price-state="{{ $filterPriceState }}"
                                        data-search="{{ $searchText }}"
                                    >
                                        <summary>
                                            <span class="nxbom-item-main">
                                                <span class="nxbom-item-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                                <span>
                                                    <strong>{{ $item->item_name }}</strong>
                                                    <small>{{ $label::categoryLabel($item->category) }}@if ($item->item_code) · {{ $item->item_code }}@endif</small>
                                                </span>
                                                <i class="nxbom-scope is-{{ $label::scopeTone($scopeStatus) }}">{{ $label::scopeLabel($scopeStatus) }}</i>
                                            </span>
                                            <span class="nxbom-item-quantity"><strong>{{ $quantity($item->quantity) }}</strong><small>{{ $item->unit }}</small></span>
                                            @if ($canViewCost)<span class="nxbom-item-money"><strong>{{ $money($item->line_cost_idr) }}</strong><small>{{ $money($item->unit_cost_idr) }} / {{ $item->unit }}</small></span>@endif
                                            @if ($canViewSelling)<span class="nxbom-item-money"><strong>{{ $money($item->line_price_idr) }}</strong><small>{{ $money($item->unit_price_idr) }} / {{ $item->unit }}</small></span>@endif
                                            <span class="nxbom-item-state is-{{ $filterPriceState }}"><i></i>{{ $label::pricingStateLabel($pricingState) }}</span>
                                            <svg class="nxbom-item-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7 10 5 5 5-5"/></svg>
                                        </summary>
                                        <div class="nxbom-item-detail">
                                            <div class="nxbom-item-description">
                                                <span>Engineering definition</span>
                                                <p>{{ $item->description ?: 'No additional item description was recorded.' }}</p>
                                                <dl>
                                                    <div><dt>Quantity basis</dt><dd>{{ $metadata['calculation_basis'] ?? 'Generated from the active technical and quantity rule set.' }}</dd></div>
                                                    <div><dt>Material allowance</dt><dd>{{ isset($metadata['waste_factor_percent']) ? number_format((float) $metadata['waste_factor_percent'], 1).'%' : 'No additional allowance recorded' }}</dd></div>
                                                    <div><dt>Price source</dt><dd>{{ ! empty($priceTrace['price_book_code']) ? $priceTrace['price_book_code'].' · '.($priceTrace['supplier_name'] ?? 'Supplier not set') : (($metadata['pricing_source'] ?? null) === 'manual_fallback' ? 'Manual fallback price' : (($metadata['pricing_source'] ?? null) === 'missing_price' ? 'Price source missing' : 'Not recorded')) }}</dd></div>
                                                </dl>
                                            </div>
                                            <div class="nxbom-item-specification">
                                                <span>Technical specification</span>
                                                @if (! empty($item->specification))
                                                    <dl>
                                                        @foreach ($item->specification as $key => $value)
                                                            <div><dt>{{ $label::specLabel($key) }}</dt><dd>{{ $label::value($value) }}</dd></div>
                                                        @endforeach
                                                    </dl>
                                                @else
                                                    <p>No structured specification was recorded for this line.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>

                <div class="nxbom-filter-empty" hidden data-nxbom-filter-empty>
                    <strong>No lines match the active material filters.</strong>
                    <span>Clear the search or select a different scope and pricing state.</span>
                </div>
            @endif
        </section>

        <section id="nxbom-pricing" class="nxbom-section nxbom-pricing-section" data-nxbom-reveal>
            <header class="nxbom-section-heading">
                <div>
                    <span>Commercial control desk</span>
                    <h3>Pricing parameters and live authority context</h3>
                    <p>Apply company price books and controlled fallback parameters without exposing cost data to roles that do not hold commercial authority.</p>
                </div>
                <div class="nxbom-pricing-state {{ $isPriced ? 'is-priced' : '' }}">
                    <i></i>
                    <span>{{ $hasBom ? $label::pricingStateLabel($summary['pricing_status'] ?? $bom->pricing_status) : 'Not evaluated' }}</span>
                </div>
            </header>

            @if (! $hasBom)
                <div class="nxbom-section-empty">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/></svg>
                    </span>
                    <div>
                        <h4>Pricing is not available yet</h4>
                        <p>Generate the material schedule before company price books, fallback values, tax, discount, and margin controls can be applied.</p>
                    </div>
                </div>
            @else
                <div class="nxbom-pricing-layout">
                    <div class="nxbom-pricing-console">
                        <div class="nxbom-pricing-intro">
                            <span>Commercial parameters</span>
                            <strong>{{ $canPrice ? 'Authorized update mode' : 'Read-only commercial context' }}</strong>
                            <p>Final totals are calculated by the existing pricing engine after submission. The interface preserves every current rule and validation limit.</p>
                        </div>

                        @if ($canPrice)
                            <form method="POST" action="{{ route('companies.boms.price', [$company, $project]) }}" class="nxbom-pricing-form" data-nxbom-action-form="price">
                                @csrf
                                <div class="nxbom-pricing-grid">
                                    <label class="nxbom-input-field">
                                        <span>Material markup</span>
                                        <div><input type="number" step="0.01" min="0" max="200" name="material_markup_percent" value="{{ $pricingParameters['material_markup_percent'] ?? 15 }}" data-nxbom-format="percent"><i>%</i></div>
                                        <small data-nxbom-format-output>{{ number_format((float) ($pricingParameters['material_markup_percent'] ?? 15), 2) }}%</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Minimum margin guard</span>
                                        <div><input type="number" step="0.01" min="0" max="100" name="min_margin_percent" value="{{ $pricingParameters['min_margin_percent'] ?? 10 }}" data-nxbom-format="percent"><i>%</i></div>
                                        <small data-nxbom-format-output>{{ number_format((float) ($pricingParameters['min_margin_percent'] ?? 10), 2) }}%</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Maximum discount</span>
                                        <div><input type="number" step="0.01" min="0" max="100" name="max_discount_percent" value="{{ $pricingParameters['max_discount_percent'] ?? 10 }}" data-nxbom-format="percent"><i>%</i></div>
                                        <small data-nxbom-format-output>{{ number_format((float) ($pricingParameters['max_discount_percent'] ?? 10), 2) }}%</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Labor rate per kWp</span>
                                        <div><i>IDR</i><input type="number" step="1" min="0" name="labor_rate_per_kwp_idr" value="{{ $pricingParameters['labor_rate_per_kwp_idr'] ?? 850000 }}" data-nxbom-format="currency"></div>
                                        <small data-nxbom-format-output>IDR {{ number_format((float) ($pricingParameters['labor_rate_per_kwp_idr'] ?? 850000), 0, ',', '.') }}</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Logistics cost</span>
                                        <div><i>IDR</i><input type="number" step="1" min="0" name="logistics_cost_idr" value="{{ $pricingParameters['logistics_cost_idr'] ?? 1500000 }}" data-nxbom-format="currency"></div>
                                        <small data-nxbom-format-output>IDR {{ number_format((float) ($pricingParameters['logistics_cost_idr'] ?? 1500000), 0, ',', '.') }}</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Other project cost</span>
                                        <div><i>IDR</i><input type="number" step="1" min="0" name="other_cost_idr" value="{{ $pricingParameters['other_cost_idr'] ?? 0 }}" data-nxbom-format="currency"></div>
                                        <small data-nxbom-format-output>IDR {{ number_format((float) ($pricingParameters['other_cost_idr'] ?? 0), 0, ',', '.') }}</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Contingency</span>
                                        <div><input type="number" step="0.01" min="0" max="100" name="contingency_percent" value="{{ $pricingParameters['contingency_percent'] ?? 3 }}" data-nxbom-format="percent"><i>%</i></div>
                                        <small data-nxbom-format-output>{{ number_format((float) ($pricingParameters['contingency_percent'] ?? 3), 2) }}%</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Tax rate</span>
                                        <div><input type="number" step="0.01" min="0" max="100" name="tax_percent" value="{{ $pricingParameters['tax_percent'] ?? 11 }}" data-nxbom-format="percent"><i>%</i></div>
                                        <small data-nxbom-format-output>{{ number_format((float) ($pricingParameters['tax_percent'] ?? 11), 2) }}%</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Tax mode</span>
                                        <div class="is-select"><select name="tax_mode"><option value="exclusive" @selected(($pricingParameters['tax_mode'] ?? 'exclusive') === 'exclusive')>Tax exclusive</option><option value="inclusive" @selected(($pricingParameters['tax_mode'] ?? 'exclusive') === 'inclusive')>Tax inclusive</option></select></div>
                                        <small>Controls how tax is applied to the selling total.</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Rounding unit</span>
                                        <div class="is-select"><select name="rounding_unit_idr">@foreach ([1,100,1000,10000] as $unit)<option value="{{ $unit }}" @selected((float) ($pricingParameters['rounding_unit_idr'] ?? 1) === (float) $unit)>IDR {{ number_format($unit, 0, ',', '.') }}</option>@endforeach</select></div>
                                        <small>Applied by the existing commercial engine.</small>
                                    </label>
                                    <label class="nxbom-input-field">
                                        <span>Global discount</span>
                                        <div><input type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ $pricingParameters['discount_percent'] ?? 0 }}" data-nxbom-format="percent"><i>%</i></div>
                                        <small data-nxbom-format-output>{{ number_format((float) ($pricingParameters['discount_percent'] ?? 0), 2) }}%</small>
                                    </label>
                                </div>
                                <label class="nxbom-text-field">
                                    <span>Tax-exempt item codes</span>
                                    <input type="text" name="tax_exempt_item_codes" value="{{ is_array($pricingParameters['tax_exempt_item_codes'] ?? null) ? implode(', ', $pricingParameters['tax_exempt_item_codes']) : ($pricingParameters['tax_exempt_item_codes'] ?? '') }}" placeholder="Optional, comma-separated item codes">
                                    <small>Use only when supported by the project tax basis.</small>
                                </label>
                                <label class="nxbom-text-field">
                                    <span>Pricing note</span>
                                    <textarea name="pricing_note" rows="3" placeholder="Commercial note for internal review or quotation context">{{ $pricingParameters['pricing_note'] ?? '' }}</textarea>
                                </label>
                                <button type="submit" class="nxbom-primary-action is-wide">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg>
                                    Calculate BOQ Price
                                </button>
                            </form>
                        @else
                            <div class="nxbom-readonly-note">Your role can review this commercial result but cannot update pricing parameters.</div>
                        @endif
                    </div>

                    <aside class="nxbom-commercial-summary">
                        <div class="nxbom-summary-head">
                            <span>Established commercial result</span>
                            <strong>{{ $isPriced ? 'Priced' : 'Pending' }}</strong>
                        </div>
                        <div class="nxbom-summary-meter">
                            <div style="--coverage:{{ $pricingCoverage * 3.6 }}deg"><strong>{{ $pricingCoverage }}%</strong><small>price coverage</small></div>
                            <span><small>Review items</small><strong>{{ $reviewItemCount }}</strong></span>
                        </div>
                        <dl class="nxbom-commercial-lines">
                            @if ($canViewCost)
                                <div><dt>Material cost</dt><dd>{{ $money($bom->material_cost_subtotal_idr) }}</dd></div>
                                <div><dt>Grand cost</dt><dd>{{ $money($bom->grand_cost_idr) }}</dd></div>
                            @endif
                            @if ($canViewSelling)
                                <div><dt>Material price</dt><dd>{{ $money($bom->material_price_subtotal_idr) }}</dd></div>
                                <div><dt>Labor</dt><dd>{{ $money($bom->labor_cost_idr) }}</dd></div>
                                <div><dt>Logistics</dt><dd>{{ $money($bom->logistics_cost_idr) }}</dd></div>
                                <div><dt>Other cost</dt><dd>{{ $money($bom->other_cost_idr, false) }}</dd></div>
                                <div><dt>Contingency</dt><dd>{{ $money($bom->contingency_cost_idr) }}</dd></div>
                                <div><dt>Tax</dt><dd>{{ $money($bom->tax_idr) }}</dd></div>
                                <div><dt>Total discount</dt><dd>− {{ $money($bom->discount_idr, false) }}</dd></div>
                                <div class="is-total"><dt>Grand total</dt><dd>{{ $money($bom->grand_price_idr) }}</dd></div>
                            @endif
                            @if ($canViewCost && $canViewSelling)
                                <div class="is-margin"><dt>Gross margin</dt><dd>{{ $money($bom->gross_margin_idr) }} · {{ number_format($marginPercent, 2) }}%</dd></div>
                            @endif
                        </dl>
                        <div class="nxbom-guard-grid">
                            <span class="{{ $marginPass ? 'is-pass' : 'is-review' }}"><i></i><small>Margin guard</small><strong>{{ $marginPass ? 'Passed' : ($isPriced ? 'Review' : 'Pending') }}</strong></span>
                            <span class="{{ $discountPass ? 'is-pass' : 'is-review' }}"><i></i><small>Discount guard</small><strong>{{ $discountPass ? 'Passed' : 'Exceeded' }}</strong></span>
                        </div>
                    </aside>
                </div>
            @endif
        </section>

        <section id="nxbom-checks" class="nxbom-section nxbom-checks-section" data-nxbom-reveal>
            <header class="nxbom-section-heading">
                <div>
                    <span>Commercial release gate</span>
                    <h3>Checks before Financial Analysis</h3>
                    <p>A truthful release gate separates completed evidence from checks that have not yet been evaluated.</p>
                </div>
                <span class="nxbom-release-state {{ $commercialReady ? 'is-ready' : '' }}"><i></i>{{ $commercialReady ? 'Ready for downstream analysis' : 'Release review required' }}</span>
            </header>

            <div class="nxbom-check-layout">
                <div class="nxbom-release-checks">
                    @php
                        $checks = [
                            ['Engineering calculation available', $hasCalculation, $hasCalculation ? ($isVerified ? 'Engineer-verified source basis' : 'Calculated source basis available') : 'Technical Calculation has not been completed'],
                            ['Material schedule generated', $hasBom, $hasBom ? $lineItemCount.' line items resolved' : 'BOM/BOQ has not been generated'],
                            ['Price coverage complete', $hasBom && $pricingCoverage === 100, $hasBom ? $pricingCoverage.'% of eligible lines priced' : 'Pricing has not been evaluated'],
                            ['No unresolved commercial item', $hasBom && $reviewItemCount === 0, $hasBom ? $reviewItemCount.' item-level review states' : 'Item checks have not been evaluated'],
                            ['Margin guard passed', $marginPass, $isPriced ? number_format($marginPercent, 2).'% actual vs '.number_format($minimumMargin, 2).'% minimum' : 'Margin has not been evaluated'],
                            ['Discount within authority', $hasBom && $discountPass, $hasBom ? number_format($discountPercent, 2).'% applied vs '.number_format($maximumDiscount, 2).'% maximum' : 'Discount has not been evaluated'],
                            ['Tax mode established', $isPriced && ! empty($summary['tax_mode'] ?? $pricingParameters['tax_mode'] ?? null), $isPriced ? ucfirst((string) ($summary['tax_mode'] ?? $pricingParameters['tax_mode'] ?? 'exclusive')).' tax basis' : 'Tax has not been evaluated'],
                        ];
                    @endphp
                    @foreach ($checks as [$checkLabel, $passed, $checkDetail])
                        <div class="nxbom-release-check {{ $passed ? 'is-pass' : 'is-pending' }}">
                            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M20 7 9 18l-5-5"/></svg></span>
                            <div><strong>{{ $checkLabel }}</strong><small>{{ $checkDetail }}</small></div>
                        </div>
                    @endforeach
                </div>

                <aside class="nxbom-warning-panel">
                    <div class="nxbom-warning-head">
                        <span>Recorded checks and warnings</span>
                        <strong>{{ $hasBom ? count($warnings) : '—' }}</strong>
                    </div>
                    @if (! $hasBom)
                        <div class="nxbom-warning-empty"><strong>Checks have not been evaluated.</strong><span>Generate the material schedule before BOM/BOQ warnings can be assessed.</span></div>
                    @elseif (count($warnings) === 0)
                        <div class="nxbom-warning-empty is-clear"><strong>No recorded BOM/BOQ issue.</strong><span>The release gate above still governs downstream readiness.</span></div>
                    @else
                        <ul class="nxbom-warning-list">
                            @foreach ($warnings as $warning)
                                <li><i></i><span>{{ is_array($warning) ? ($warning['message'] ?? $warning['title'] ?? json_encode($warning)) : $warning }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </aside>
            </div>
        </section>

        <section id="nxbom-audit" class="nxbom-section nxbom-audit-section" data-nxbom-reveal>
            <header class="nxbom-section-heading">
                <div>
                    <span>Traceability and authority</span>
                    <h3>Commercial engineering audit trail</h3>
                    <p>Key milestones remain visible without allowing repetitive metadata to dominate the workspace.</p>
                </div>
            </header>

            <div class="nxbom-audit-timeline">
                <article class="{{ $calculation?->calculated_at ? 'is-complete' : '' }}">
                    <span><i></i></span>
                    <div><small>Engineering calculated</small><strong>{{ $calculation?->calculated_at?->format('d M Y · H:i') ?? 'Not completed' }}</strong><p>{{ $calculation?->calculator?->name ?? 'Calculation authority not recorded' }}</p></div>
                </article>
                <article class="{{ $calculation?->verified_at ? 'is-complete' : '' }}">
                    <span><i></i></span>
                    <div><small>Engineering verified</small><strong>{{ $calculation?->verified_at?->format('d M Y · H:i') ?? 'Pending verification' }}</strong><p>{{ $calculation?->verifier?->name ?? 'Verification authority not recorded' }}</p></div>
                </article>
                <article class="{{ $bom?->generated_at ? 'is-complete' : '' }}">
                    <span><i></i></span>
                    <div><small>Material schedule generated</small><strong>{{ $bom?->generated_at?->format('d M Y · H:i') ?? 'Not generated' }}</strong><p>{{ $bom?->generator?->name ?? 'Generation authority not recorded' }}</p></div>
                </article>
                <article class="{{ $bom?->priced_at ? 'is-complete' : '' }}">
                    <span><i></i></span>
                    <div><small>Commercial price established</small><strong>{{ $bom?->priced_at?->format('d M Y · H:i') ?? 'Not priced' }}</strong><p>{{ $bom?->pricer?->name ?? 'Pricing authority not recorded' }}</p></div>
                </article>
            </div>
        </section>
    </div>
@endsection
