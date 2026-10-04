@extends('layouts.app')

@section('title', 'BOM / BOQ')
@section('page-eyebrow', 'Procurement engineering')
@section('page-heading', 'BOM / BOQ')
@section('force-theme', 'dark')
@section('body-class', 'page-bom-workspace-v6')
@section('disable-remote-fonts', 'true')
@section('suppress-global-feedback', true)

@php
    $summary = (array) ($bom?->summary ?? []);
    $warnings = collect($bom?->warnings ?? []);
    $items = collect($bom?->items ?? [])->sortBy(fn ($item) => [$item->sort_order ?? 9999, $item->id])->values();
    $dictionary = \App\Support\BomBoqDictionary::class;
    $hasCalculation = (bool) $calculation?->calculated_at;
    $hasBom = (bool) $bom;
    $isVerified = $calculation?->result_status === 'verified';
    $isPriced = $bom?->pricing_status === 'priced';
    $pricingParameters = old() ?: (array) ($bom?->pricing_parameters ?? $pricingDefaults);
    $minimumMargin = (float) ($summary['min_margin_percent'] ?? $pricingParameters['min_margin_percent'] ?? 10);
    $maximumDiscount = (float) ($summary['max_discount_percent'] ?? $pricingParameters['max_discount_percent'] ?? 10);
    $marginPercent = (float) ($bom?->gross_margin_percent ?? 0);
    $discountPercent = (float) ($pricingParameters['discount_percent'] ?? 0);

    $excludedScopes = ['utility', 'owner', 'not_applicable', 'catalog_candidate'];
    $reviewStates = [
        'expired_price_warning', 'supplier_unavailable_warning', 'margin_review_required',
        'discount_review_required', 'tax_review_required', 'commercial_total_mismatch',
        'missing_price', 'needs_pricing', 'pending',
    ];
    $eligibleItems = $items->filter(function ($item) {
        return (bool) data_get($item->metadata, 'include_in_boq', false)
            && (bool) data_get($item->metadata, 'included_in_base_price', false);
    });
    $pricedItems = $eligibleItems->filter(fn ($item) => (float) $item->unit_price_idr > 0 || data_get($item->metadata, 'pricing_state') === 'priced');
    $pricingCoverage = $eligibleItems->count() > 0 ? (int) round(($pricedItems->count() / $eligibleItems->count()) * 100) : 0;
    $reviewItems = $items->filter(fn ($item) => in_array(data_get($item->metadata, 'pricing_state', 'pending'), $reviewStates, true));
    $catalogCandidates = $items->filter(fn ($item) => data_get($item->metadata, 'scope_status') === 'catalog_candidate' || data_get($item->metadata, 'pricing_state') === 'catalog_candidate');
    $supplierLinkedItems = $items->filter(fn ($item) => filled(data_get($item->metadata, 'pricing_source_trace.supplier_id')) || filled(data_get($item->metadata, 'pricing_source_trace.supplier_name')));
    $manualFallbackItems = $items->filter(fn ($item) => data_get($item->metadata, 'pricing_source') === 'manual_fallback');

    $lineCount = $items->count();
    $chargeableCount = (int) ($summary['chargeable_item_count'] ?? $eligibleItems->count());
    $materialCost = (float) ($bom?->material_cost_subtotal_idr ?? $eligibleItems->sum('line_cost_idr'));
    $materialPrice = (float) ($bom?->material_price_subtotal_idr ?? $eligibleItems->sum('line_price_idr'));
    $grandCost = (float) ($bom?->grand_cost_idr ?? 0);
    $grandPrice = (float) ($bom?->grand_price_idr ?? 0);
    $otherCost = $grandCost > 0 && $materialCost > 0
        ? max(0, $grandCost - $materialCost)
        : (float) (($bom?->labor_cost_idr ?? 0) + ($bom?->logistics_cost_idr ?? 0) + ($bom?->other_cost_idr ?? 0));
    $grossMargin = (float) ($bom?->gross_margin_idr ?? 0);
    $tax = (float) ($bom?->tax_idr ?? 0);
    $discount = (float) ($bom?->discount_idr ?? 0);

    $money = static function ($value, bool $allowZero = true): string {
        $amount = (float) ($value ?? 0);
        if (! $allowZero && $amount <= 0) return 'Pending';
        if ($amount >= 1_000_000_000) return 'Rp '.number_format($amount / 1_000_000_000, 2).' B';
        if ($amount >= 1_000_000) return 'Rp '.number_format($amount / 1_000_000, 2).' M';
        if ($amount >= 1_000) return 'Rp '.number_format($amount / 1_000, 1).' K';
        return 'Rp '.number_format($amount, 0, ',', '.');
    };
    $moneyFull = static fn ($value): string => 'IDR '.number_format((float) ($value ?? 0), 0, ',', '.');
    $quantity = static function ($value): string {
        $number = (float) $value;
        return abs($number - round($number)) < .0001
            ? number_format($number, 0, '.', ',')
            : rtrim(rtrim(number_format($number, 3, '.', ','), '0'), '.');
    };

    $categoryStats = $items->groupBy(fn ($item) => $item->category ?: 'other')->map(function ($group, $category) use ($dictionary, $excludedScopes, $reviewStates, $canViewCost) {
        $eligible = $group->filter(function ($item) {
            return (bool) data_get($item->metadata, 'include_in_boq', false)
                && (bool) data_get($item->metadata, 'included_in_base_price', false);
        });
        $priced = $eligible->filter(fn ($item) => (float) $item->unit_price_idr > 0 || data_get($item->metadata, 'pricing_state') === 'priced');
        $review = $group->filter(fn ($item) => in_array(data_get($item->metadata, 'pricing_state', 'pending'), $reviewStates, true));
        return [
            'key' => $category,
            'label' => $dictionary::categoryLabel($category),
            'lines' => $group->count(),
            'eligible' => $eligible->count(),
            'priced' => $priced->count(),
            'review' => $review->count(),
            'readiness' => $eligible->count() > 0 ? (int) round(($priced->count() / $eligible->count()) * 100) : 100,
            'value' => (float) $eligible->sum($canViewCost ? 'line_cost_idr' : 'line_price_idr'),
        ];
    })->sortByDesc('value')->values();

    $positiveCostRows = $categoryStats->filter(fn ($row) => $row['value'] > 0)->values();
    $costComposition = $positiveCostRows->take(7)->values();
    $remainingCost = (float) $positiveCostRows->slice(7)->sum('value');
    if ($remainingCost > 0) {
        $costComposition->push(['key' => 'other', 'label' => 'Other Categories', 'lines' => 0, 'eligible' => 0, 'priced' => 0, 'review' => 0, 'readiness' => 100, 'value' => $remainingCost]);
    }
    $unresolvedGroups = $reviewItems->groupBy(fn ($item) => data_get($item->metadata, 'pricing_state', 'pending'))
        ->map(fn ($group, $state) => ['state' => $state, 'label' => $dictionary::pricingStateLabel($state), 'count' => $group->count()])
        ->sortByDesc('count')->values();

    $approval = $project->activeReleaseApproval;
    $marginPass = $isPriced && $marginPercent >= $minimumMargin;
    $discountPass = $discountPercent <= $maximumDiscount;
    $commercialReady = $isPriced && $pricingCoverage === 100 && $reviewItems->isEmpty() && $marginPass && $discountPass;
    $statusLabel = $hasBom ? strtoupper(str_replace('_', ' ', (string) ($bom->status ?? 'draft'))) : 'NOT GENERATED';
    $systemType = $summary['system_type'] ?? $project->system_type ?? '—';
    $installedPvKw = $summary['installed_pv_kw'] ?? data_get($calculation?->results, 'installed_pv_kw');
    $lastUpdated = $bom?->updated_at ?? $calculation?->updated_at ?? $project->updated_at;
    $chartPayload = [
        'composition' => $costComposition,
        'readiness' => $categoryStats->take(9)->values(),
        'margin' => [
            'actual' => round($marginPercent, 2),
            'minimum' => round($minimumMargin, 2),
            'maximum' => 25,
            'projectedValue' => $grossMargin,
        ],
        'currencyLabel' => 'IDR',
        'valueLabel' => $canViewCost ? 'Direct cost' : 'Commercial value',
    ];
@endphp

@push('styles')
    <link href="{{ asset('css/nexorin-bom-workspace-v6.css') }}?v=003.2" rel="stylesheet">
    <link href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1" rel="stylesheet">
    <link href="{{ asset('css/nexorin-luxury-system-v2.css') }}?v=2.0" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/nexorin-bom-workspace-v6.js') }}?v=003.2" defer></script>
    <script src="{{ asset('js/nexorin-luxury-system-v2.js') }}?v=2.0" defer></script>
@endpush

@section('content')
<div class="nxbom3" data-nxbom3 data-echarts-src="{{ asset('vendor/echarts/echarts.min.js') }}?v=6.1.0">
    <script type="application/json" data-nxbom3-chart-payload>@json($chartPayload)</script>

    <nav class="nxbom3-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('companies.projects.index', $company) }}">Projects</a><i>/</i>
        <a href="{{ route('companies.projects.show', [$company, $project]) }}">{{ $project->name }}</a><i>/</i>
        <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}">Engineering</a><i>/</i>
        <span>BOM / BOQ</span>
    </nav>

    <header class="nxbom3-page-head">
        <div class="nxbom3-title-wrap">
            <div class="nxbom3-title-line">
                <h2>{{ $project->name }} – BOM / BOQ</h2>
                @if ($bom)<span class="nxbom3-version">{{ $bom->bom_number }}</span>@endif
                <span class="nxbom3-state {{ $isPriced ? 'is-priced' : '' }}">{{ $statusLabel }}</span>
            </div>
            <div class="nxbom3-meta">
                <span><svg viewBox="0 0 24 24"><path d="M5 5h14v14H5zM9 3v4m6-4v4M8 11h8M8 15h5"/></svg>Project ID: {{ $project->project_number }}</span>
                <span><svg viewBox="0 0 24 24"><path d="M4 18h16M7 15l3-6 3 4 4-8"/></svg>{{ $installedPvKw !== null ? number_format((float) $installedPvKw, 2).' kWp' : 'Capacity pending' }}</span>
                <span><svg viewBox="0 0 24 24"><path d="M12 21s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>{{ $project->city ?: ($project->province ?: 'Location not recorded') }}</span>
                <span><svg viewBox="0 0 24 24"><path d="M12 8v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>Updated {{ $lastUpdated?->format('M d, Y H:i') ?? '—' }}</span>
            </div>
        </div>

        <div class="nxbom3-head-actions">
            <details class="nxbom3-action-menu">
                <summary>Actions <svg viewBox="0 0 24 24"><path d="m8 10 4 4 4-4"/></svg></summary>
                <div>
                    <a href="{{ route('companies.technical-calculations.show', [$company, $project]) }}">Open Technical Calculation</a>
                    @if ($hasCalculation && $canGenerate)
                        <form method="POST" action="{{ route('companies.boms.generate', [$company, $project]) }}" data-nxbom3-confirm="Regenerate BOM / BOQ from the current verified technical calculation?">
                            @csrf
                            <button type="submit">{{ $hasBom ? 'Regenerate BOM / BOQ' : 'Generate BOM / BOQ' }}</button>
                        </form>
                    @endif
                    @if ($canPrice && $hasBom)
                        <button type="button" data-nxbom3-toggle-pricing>Pricing Parameters</button>
                    @endif
                </div>
            </details>
            @if ($hasCalculation && $canGenerate)
                <form method="POST" action="{{ route('companies.boms.generate', [$company, $project]) }}" data-nxbom3-confirm="Regenerate BOM / BOQ from the current verified technical calculation?">
                    @csrf
                    <button class="nxbom3-primary" type="submit">
                        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>{{ $hasBom ? 'Regenerate' : 'Generate BOM' }}
                    </button>
                </form>
            @endif
        </div>
    </header>

    @if (session('success') || session('error') || $errors->any())
        <section class="nxbom3-feedback {{ session('error') || $errors->any() ? 'is-error' : 'is-success' }}" role="status">
            <strong>{{ session('error') ? 'Workflow requires attention' : ($errors->any() ? 'Validation requires attention' : 'Workspace updated') }}</strong>
            <span>{{ session('success') ?? session('error') ?? $errors->first() }}</span>
        </section>
    @endif

    <section class="nxbom3-kpis" aria-label="BOM commercial indicators">
        <article><small>Total BOQ Value</small><strong>{{ $canViewSelling && $isPriced ? $money($grandPrice) : 'Restricted' }}</strong><span>{{ $canViewSelling && $isPriced ? $moneyFull($grandPrice) : 'Selling price follows assigned role' }}</span></article>
        <article><small>Direct Material {{ $canViewCost ? 'Cost' : 'Value' }}</small><strong>{{ $canViewCost ? $money($materialCost) : ($canViewSelling ? $money($materialPrice) : 'Restricted') }}</strong><span>{{ $canViewCost ? number_format($grandCost > 0 ? ($materialCost / $grandCost) * 100 : 0, 1).'%' : 'Role-scoped visibility' }} of current commercial basis</span></article>
        <article><small>BOQ Line Items</small><strong>{{ number_format($chargeableCount) }}</strong><span>{{ number_format($lineCount) }} generated scope rows</span></article>
        <article><small>Install. & Other Cost</small><strong>{{ $canViewCost ? $money($otherCost) : 'Restricted' }}</strong><span>{{ $canViewCost && $grandCost > 0 ? number_format(($otherCost / $grandCost) * 100, 1).'%' : '—' }} of total cost</span></article>
        <article><small>Target Margin</small><strong>{{ number_format($minimumMargin, 1) }}%</strong><span>{{ $isPriced ? $money($grossMargin) : 'Pricing pending' }}</span></article>
        <article class="nxbom3-kpi-readiness">
            <div class="nxbom3-ring" style="--p:{{ $pricingCoverage }}"><span>{{ $pricingCoverage }}%</span></div>
            <div><small>Pricing Readiness</small><strong>{{ $pricingCoverage === 100 ? 'Ready' : 'In Review' }}</strong><span>{{ $pricedItems->count() }}/{{ $eligibleItems->count() }} eligible lines priced</span></div>
        </article>
    </section>

    <nav class="nxbom3-tabs" aria-label="BOM workspace sections">
        <a class="is-active" href="#line-items">BOM / BOQ</a>
        <a href="#cost-composition">Cost Composition</a>
        <a href="#catalog-candidates">Catalog Candidates</a>
        <a href="#readiness">Readiness</a>
        <a href="#release-approval">Release Approval</a>
        <a href="#margin-impact">Margin Impact</a>
        <a href="#commercial-summary">History & Summary</a>
    </nav>

    <section class="nxbom3-toolbar" id="line-items">
        <label class="nxbom3-search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" placeholder="Search line items..." data-nxbom3-search></label>
        <label><span class="sr-only">Category</span><select data-nxbom3-category><option value="all">All Categories</option>@foreach($categoryStats as $row)<option value="{{ $row['key'] }}">{{ $row['label'] }}</option>@endforeach</select></label>
        <label><span class="sr-only">Pricing state</span><select data-nxbom3-state><option value="all">All Pricing States</option><option value="priced">Priced</option><option value="review">Needs Review</option><option value="excluded">Excluded / Internal</option></select></label>
        <div class="nxbom3-toolbar-spacer"></div>
        <span class="nxbom3-result" data-nxbom3-result>{{ $lineCount }} lines</span>
        <button type="button" class="nxbom3-columns" data-nxbom3-reset>Reset filters</button>
    </section>

    <div class="nxbom3-workspace-grid">
        <main class="nxbom3-main-column">
            <section class="nxbom3-table-card" aria-labelledby="nxbom3-line-title">
                <header><div><strong id="nxbom3-line-title">Line Items <em>({{ $lineCount }})</em></strong><span class="is-ready">● Priced {{ $pricedItems->count() }}</span><span class="is-review">● Review {{ $reviewItems->count() }}</span><span>● Internal / excluded {{ $lineCount - $eligibleItems->count() }}</span></div><small>14 rows per page</small></header>
                <div class="nxbom3-table-scroll">
                    <table>
                        <thead><tr><th>#</th><th>Code</th><th>Description</th><th>Category</th><th class="is-num">Qty</th><th>Unit</th>@if($canViewCost)<th class="is-num">Unit Cost</th><th class="is-num">Total Cost</th>@elseif($canViewSelling)<th class="is-num">Unit Price</th><th class="is-num">Total Price</th>@endif<th>Pricing</th><th>Readiness</th></tr></thead>
                        <tbody data-nxbom3-rows>
                        @foreach ($items as $index => $item)
                            @php
                                $meta = (array) ($item->metadata ?? []);
                                $pricingState = data_get($meta, 'pricing_state', (float) $item->unit_price_idr > 0 ? 'priced' : 'pending');
                                $scope = data_get($meta, 'scope_status', 'required');
                                $category = $item->category ?: 'other';
                                $isChargeableLine = (bool) data_get($meta, 'include_in_boq', false) && (bool) data_get($meta, 'included_in_base_price', false);
                                $filterState = ! $isChargeableLine || in_array($pricingState, ['excluded_scope', 'not_applicable', 'catalog_candidate', 'summary_cost'], true)
                                    ? 'excluded'
                                    : (in_array($pricingState, $reviewStates, true) ? 'review' : 'priced');
                                $ready = $filterState === 'priced';
                            @endphp
                            <tr data-nxbom3-row data-search="{{ strtolower($item->item_code.' '.$item->item_name.' '.$item->description.' '.$dictionary::categoryLabel($category)) }}" data-category="{{ $category }}" data-state="{{ $filterState }}">
                                <td>{{ $index + 1 }}</td>
                                <td><strong>{{ $item->item_code }}</strong></td>
                                <td><strong>{{ $item->item_name }}</strong><small>{{ $item->description ?: (data_get($meta, 'calculation_basis') ?: 'Generated engineering line') }}</small></td>
                                <td>{{ $dictionary::categoryLabel($category) }}</td>
                                <td class="is-num">{{ $quantity($item->quantity) }}</td>
                                <td>{{ $item->unit }}</td>
                                @if($canViewCost)
                                    <td class="is-num">{{ (float)$item->unit_cost_idr > 0 ? $moneyFull($item->unit_cost_idr) : '—' }}</td>
                                    <td class="is-num">{{ (float)$item->line_cost_idr > 0 ? $moneyFull($item->line_cost_idr) : '—' }}</td>
                                @elseif($canViewSelling)
                                    <td class="is-num">{{ (float)$item->unit_price_idr > 0 ? $moneyFull($item->unit_price_idr) : '—' }}</td>
                                    <td class="is-num">{{ (float)$item->line_price_idr > 0 ? $moneyFull($item->line_price_idr) : '—' }}</td>
                                @endif
                                <td><span class="nxbom3-pill is-{{ $filterState }}"><i></i>{{ $dictionary::pricingStateLabel($pricingState) }}</span></td>
                                <td><span class="nxbom3-ready {{ $ready ? 'is-ready' : ($filterState === 'review' ? 'is-partial' : 'is-internal') }}"><i></i>{{ $ready ? 'Ready' : ($filterState === 'review' ? 'Review' : 'Internal') }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <footer class="nxbom3-table-footer"><span data-nxbom3-range>Showing 1–{{ min(14,$lineCount) }} of {{ $lineCount }} items</span><div class="nxbom3-pagination" data-nxbom3-pagination></div><span>14 / page</span></footer>
            </section>

            <section class="nxbom3-analytics" data-nxbom3-analytics>
                <article id="cost-composition"><header><span>Cost Composition ({{ $canViewCost ? 'IDR Cost' : 'IDR Value' }})</span></header><div class="nxbom3-chart" data-nxbom3-chart="composition"><div class="nxbom3-chart-fallback"><strong>{{ $money($canViewCost ? $grandCost : $grandPrice) }}</strong><span>{{ $canViewCost ? 'Total project cost' : 'Total BOQ value' }}</span></div></div><a href="#commercial-summary">View cost breakdown →</a></article>
                <article id="readiness"><header><span>Pricing Readiness by Category</span></header><div class="nxbom3-chart" data-nxbom3-chart="readiness"><div class="nxbom3-readiness-fallback">@foreach($categoryStats->take(7) as $row)<p><span>{{ $row['label'] }}</span><i><b style="width:{{ $row['readiness'] }}%"></b></i><strong>{{ $row['readiness'] }}%</strong></p>@endforeach</div></div><a href="#line-items">Review line readiness →</a></article>
                <article id="margin-impact"><header><span>Margin Impact</span></header><div class="nxbom3-chart" data-nxbom3-chart="margin"><div class="nxbom3-margin-fallback"><strong>{{ number_format($marginPercent,1) }}%</strong><span>Projected margin</span></div></div><div class="nxbom3-margin-caption"><strong>{{ $isPriced ? $money($grossMargin) : 'Pending' }}</strong><span>Projected margin · minimum {{ number_format($minimumMargin,1) }}%</span></div><a href="#commercial-summary">View margin analysis →</a></article>
            </section>

            <section class="nxbom3-commercial" id="commercial-summary">
                <header><span>Commercial Summary</span><small>{{ $commercialReady ? 'Commercial controls passed' : 'Commercial review remains' }}</small></header>
                <div>
                    <span><small>Total BOQ Value</small><strong>{{ $canViewSelling && $isPriced ? $money($grandPrice) : 'Restricted' }}</strong></span>
                    <span><small>Total Landed Cost</small><strong>{{ $canViewCost ? $money($grandCost) : 'Restricted' }}</strong></span>
                    <span><small>Tax & Duties</small><strong>{{ $canViewCost ? $money($tax) : 'Restricted' }}</strong><em>{{ number_format((float)($pricingParameters['tax_percent'] ?? 0),1) }}%</em></span>
                    <span><small>Discount</small><strong>{{ $canViewSelling ? $money($discount) : 'Restricted' }}</strong><em>{{ number_format($discountPercent,1) }}%</em></span>
                    <span><small>Target Margin</small><strong>{{ $isPriced ? $money($grossMargin) : 'Pending' }}</strong><em>{{ number_format($marginPercent,1) }}%</em></span>
                    <span><small>Pricing Basis</small><strong>{{ $manualFallbackItems->count() > 0 ? 'Fallback + rules' : 'Company price sources' }}</strong><em>{{ $manualFallbackItems->count() }} fallback lines</em></span>
                    <span><small>System</small><strong>{{ ucwords(str_replace('_',' ', (string) $systemType)) }}</strong><em>{{ $isVerified ? 'Verified engineering' : 'Engineering review' }}</em></span>
                </div>
            </section>

            @if($canPrice && $hasBom)
            <div class="nxbom3-pricing-modal" data-nxbom3-pricing-panel hidden>
                <section class="nxbom3-pricing-dialog" role="dialog" aria-modal="true" aria-labelledby="nxbom3-pricing-title" tabindex="-1">
                    <header class="nxbom3-pricing-header">
                        <div>
                            <span class="nxbom3-pricing-eyebrow">Controlled commercial calculation</span>
                            <h3 id="nxbom3-pricing-title">Pricing Parameters</h3>
                            <p>Update the commercial assumptions for this BOM / BOQ. Applying changes recalculates the current pricing state.</p>
                        </div>
                        <button type="button" class="nxbom3-pricing-close" data-nxbom3-close-pricing aria-label="Close pricing parameters">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>
                        </button>
                    </header>

                    <div class="nxbom3-pricing-context" aria-label="Current pricing context">
                        <span><small>BOM</small><strong>{{ $bom->bom_number }}</strong></span>
                        <span><small>Eligible lines</small><strong>{{ $eligibleItems->count() }}</strong></span>
                        <span><small>Pricing coverage</small><strong>{{ $pricingCoverage }}%</strong></span>
                        <span><small>Current basis</small><strong>{{ $manualFallbackItems->count() > 0 ? 'Fallback + rules' : 'Company price sources' }}</strong></span>
                    </div>

                    <form method="POST" action="{{ route('companies.boms.price', [$company, $project]) }}">
                        @csrf
                        <div class="nxbom3-pricing-form-scroll">
                            <fieldset class="nxbom3-pricing-group">
                                <legend>Commercial strategy</legend>
                                <p>Controls how material cost is converted into the commercial BOQ value and protects the project margin.</p>
                                <div class="nxbom3-pricing-grid">
                                    <label><span>Material markup <em>%</em></span><input type="number" name="material_markup_percent" min="0" max="100" step="0.01" value="{{ $pricingParameters['material_markup_percent'] ?? 15 }}"></label>
                                    <label><span>Minimum margin <em>%</em></span><input type="number" name="min_margin_percent" min="0" max="100" step="0.01" value="{{ $pricingParameters['min_margin_percent'] ?? 10 }}"></label>
                                    <label><span>Maximum discount <em>%</em></span><input type="number" name="max_discount_percent" min="0" max="100" step="0.01" value="{{ $pricingParameters['max_discount_percent'] ?? 10 }}"></label>
                                    <label><span>Applied discount <em>%</em></span><input type="number" name="discount_percent" min="0" max="100" step="0.01" value="{{ $pricingParameters['discount_percent'] ?? 0 }}"></label>
                                </div>
                            </fieldset>

                            <fieldset class="nxbom3-pricing-group">
                                <legend>Delivery &amp; project costs</legend>
                                <p>Project-level additions remain separate from direct material cost so the commercial summary stays auditable.</p>
                                <div class="nxbom3-pricing-grid">
                                    <label><span>Labor rate / kWp <em>IDR</em></span><input type="number" name="labor_rate_per_kwp_idr" min="0" step="1" value="{{ $pricingParameters['labor_rate_per_kwp_idr'] ?? 0 }}"></label>
                                    <label><span>Logistics cost <em>IDR</em></span><input type="number" name="logistics_cost_idr" min="0" step="1" value="{{ $pricingParameters['logistics_cost_idr'] ?? 0 }}"></label>
                                    <label><span>Other cost <em>IDR</em></span><input type="number" name="other_cost_idr" min="0" step="1" value="{{ $pricingParameters['other_cost_idr'] ?? 0 }}"></label>
                                    <label><span>Contingency <em>%</em></span><input type="number" name="contingency_percent" min="0" max="100" step="0.01" value="{{ $pricingParameters['contingency_percent'] ?? 0 }}"></label>
                                </div>
                            </fieldset>

                            <fieldset class="nxbom3-pricing-group">
                                <legend>Tax &amp; governance</legend>
                                <p>Tax handling is persisted with the pricing parameters and applied by the existing pricing service.</p>
                                <div class="nxbom3-pricing-grid">
                                    <label><span>Tax <em>%</em></span><input type="number" name="tax_percent" min="0" max="100" step="0.01" value="{{ $pricingParameters['tax_percent'] ?? 0 }}"></label>
                                    <label><span>Tax mode</span><select name="tax_mode"><option value="exclusive" @selected(($pricingParameters['tax_mode'] ?? 'exclusive') === 'exclusive')>Exclusive</option><option value="inclusive" @selected(($pricingParameters['tax_mode'] ?? '') === 'inclusive')>Inclusive</option></select></label>
                                    <label class="is-wide"><span>Pricing note <em>Optional</em></span><textarea name="pricing_note" rows="3" placeholder="Document the pricing basis, exception, or commercial assumption...">{{ $pricingParameters['pricing_note'] ?? '' }}</textarea></label>
                                </div>
                            </fieldset>
                        </div>

                        <footer class="nxbom3-pricing-footer">
                            <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg>Recalculation updates this BOM / BOQ using the existing pricing engine.</span>
                            <div><button type="button" data-nxbom3-close-pricing>Cancel</button><button type="submit" class="nxbom3-primary">Apply &amp; Recalculate</button></div>
                        </footer>
                    </form>
                </section>
            </div>
            @endif
        </main>

        <aside class="nxbom3-side-column">
            <section class="nxbom3-side-card" id="catalog-candidates">
                <header><span>Unresolved Items</span><strong>{{ $reviewItems->count() }}</strong></header>
                <div class="nxbom3-unresolved">
                    @forelse($unresolvedGroups->take(5) as $row)
                        <p><b>{{ $row['count'] }}</b><span>{{ $row['label'] }}</span></p>
                    @empty
                        <div class="nxbom3-empty"><strong>No item-level pricing exceptions.</strong><span>Current chargeable lines are priced without recorded warning states.</span></div>
                    @endforelse
                </div>
                <a href="#line-items" data-nxbom3-review-filter>View unresolved lines</a>
            </section>

            <section class="nxbom3-side-card nxbom3-candidates">
                <header><span>Catalog Candidates</span><strong>{{ $catalogCandidates->count() }}</strong></header>
                <div>
                    @forelse($catalogCandidates->take(3) as $candidate)
                        <article><span class="nxbom3-candidate-mark"><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4zM8 3v4m8-4v4M8 12h8"/></svg></span><div><b>{{ $candidate->item_code }}</b><strong>{{ $candidate->item_name }}</strong><small>{{ $dictionary::categoryLabel($candidate->category) }} · {{ $dictionary::scopeLabel(data_get($candidate->metadata,'scope_status')) }}</small></div></article>
                    @empty
                        <div class="nxbom3-empty"><strong>No catalog candidates.</strong><span>The generated BOM has no internal selection candidates.</span></div>
                    @endforelse
                </div>
                <span class="nxbom3-side-note">Candidates are displayed only when generated by the quantity/scope engine.</span>
            </section>

            <section class="nxbom3-side-card" id="release-approval">
                <header><span>Release Approval</span><strong>{{ $approval ? '1' : '0' }}</strong></header>
                @if($approval)
                    <div class="nxbom3-approval is-active"><i>1</i><div><strong>Active client release approval</strong><span>{{ $approval->approver?->name ?? 'Authorized approver' }}</span></div><b>{{ $approval->release_status ? ucwords(str_replace('_',' ', $approval->release_status)) : 'Active' }}</b></div>
                    <p class="nxbom3-side-note">Approved {{ $approval->approved_at?->format('M d, Y H:i') ?? '—' }}. Release state is sourced from ProjectReleaseApproval.</p>
                @else
                    <div class="nxbom3-approval"><i>1</i><div><strong>No active release approval</strong><span>Client release remains unapproved in the project release-control model.</span></div><b>Pending</b></div>
                @endif
            </section>

            <section class="nxbom3-side-card nxbom3-suppliers">
                <header><span>Supplier Association</span><strong>{{ $supplierLinkedItems->count() }}</strong></header>
                @if($supplierLinkedItems->isNotEmpty())
                    @foreach($supplierLinkedItems->groupBy(fn($item) => data_get($item->metadata,'pricing_source_trace.supplier_name','Supplier'))->take(5) as $supplier => $supplierItems)
                        <p><span><strong>{{ $supplier }}</strong><small>{{ $supplierItems->count() }} linked line(s)</small></span><b>{{ $money($supplierItems->sum($canViewCost ? 'line_cost_idr' : 'line_price_idr')) }}</b></p>
                    @endforeach
                @else
                    <div class="nxbom3-empty"><strong>No supplier-linked price source recorded.</strong><span>{{ $manualFallbackItems->count() }} generated line(s) currently use controlled fallback pricing; no supplier is fabricated.</span></div>
                @endif
            </section>

            <section class="nxbom3-side-card nxbom3-integrity">
                <header><span>Data Integrity</span><strong>{{ $warnings->count() }}</strong></header>
                <p><span>Technical calculation</span><b class="{{ $isVerified ? 'is-ok' : '' }}">{{ $isVerified ? 'Verified' : 'Review' }}</b></p>
                <p><span>Pricing coverage</span><b class="{{ $pricingCoverage === 100 ? 'is-ok' : '' }}">{{ $pricingCoverage }}%</b></p>
                <p><span>Margin guard</span><b class="{{ $marginPass ? 'is-ok' : '' }}">{{ $marginPass ? 'Passed' : ($isPriced ? 'Review' : 'Pending') }}</b></p>
                <p><span>Discount guard</span><b class="{{ $discountPass ? 'is-ok' : '' }}">{{ $discountPass ? 'Passed' : 'Review' }}</b></p>
            </section>
        </aside>
    </div>
</div>
@endsection
