@php
    $parsedFields = collect($datasheet->parsed_fields ?? []);
    $extractionState = str($datasheet->extraction_status ?? 'pending')->replace('_', ' ')->title();
    $categoryMeta = data_get($datasheet->extraction_meta, 'category', []);
    $categoryBlocked = (bool) data_get($categoryMeta, 'blocked', false);
    $rootMatch = data_get($categoryMeta, 'match', 'uncertain');
    $exactMatch = data_get($categoryMeta, 'exact_match', 'not_applicable');
    $reviewReason = data_get($categoryMeta, 'review_reason', 'root_not_confirmed');
    $expectedRootLabel = data_get($categoryMeta, 'expected_root_label', str(data_get($categoryMeta, 'expected_root', $specificationSchema['root_code'] ?? 'material'))->replace('_', ' ')->title());
    $detectedRootLabel = data_get($categoryMeta, 'detected_root')
        ? data_get($categoryMeta, 'detected_root_label', str(data_get($categoryMeta, 'detected_root'))->replace('_', ' ')->title())
        : null;
    $expectedExactLabel = data_get($categoryMeta, 'expected_exact_label');
    $detectedExactLabel = data_get($categoryMeta, 'detected_exact_label');
    $variantMeta = data_get($datasheet->extraction_meta, 'variant', []);
    $variantBlocked = (bool) data_get($variantMeta, 'blocked', false);
    $availableVariants = collect(data_get($variantMeta, 'available_variants', []));
    $selectedVariant = data_get($variantMeta, 'selected_variant');
    $applicationBlocked = $categoryBlocked || $variantBlocked;
    $canReviewDatasheet = $canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update');
    $hasAppliedApplication = $datasheet->applications->contains('status', 'applied');
    $extractionMethod = (string) ($datasheet->extraction_method ?? 'manual');
    $degradedExtraction = $extractionMethod === 'native_pdf_fallback' || (bool) data_get($datasheet->extraction_meta, 'extraction.degraded', false);
    $reviewCounts = [
        'all' => $parsedFields->count(),
        'new' => $parsedFields->where('status', 'new')->count(),
        'match' => $parsedFields->where('status', 'match')->count(),
        'conflict' => $parsedFields->where('status', 'conflict')->count(),
        'low_confidence' => $parsedFields->where('status', 'low_confidence')->count(),
    ];
    $defaultAcceptedCount = $parsedFields->filter(function ($field) {
        $status = $field['status'] ?? 'new';
        $action = $field['review_action'] ?? $field['suggested_action'] ?? ($status === 'new' ? 'accept' : 'keep');
        return ! in_array($status, ['conflict', 'low_confidence'], true) && $action === 'accept';
    })->count();

    $categoryTitle = match (true) {
        $reviewReason === 'exact_component_mismatch' => 'Component mismatch blocked',
        $reviewReason === 'root_mismatch' => 'Category mismatch blocked',
        $exactMatch === 'matched' => 'Exact component confirmed',
        $rootMatch === 'matched' => 'Professional category confirmed',
        default => 'Category not confirmed',
    };
    $categoryTone = $categoryBlocked ? 'is-blocked' : (($rootMatch === 'matched' || $exactMatch === 'matched') ? 'is-matched' : 'is-uncertain');

    $nextActionTitle = match (true) {
        $reviewReason === 'exact_component_mismatch' => 'Use a matching material record or matching datasheet',
        $reviewReason === 'root_mismatch' => 'Replace the source document before review',
        $variantBlocked => 'Select an exact STC variant and reparse',
        $degradedExtraction && ! $hasAppliedApplication => 'Improve PDF extraction, then reparse',
        $parsedFields->isEmpty() => 'No safe technical candidates are ready',
        ! $hasAppliedApplication => 'Review candidates field by field',
        default => 'Parser values are applied to the material record',
    };
    $nextActionCopy = match (true) {
        $reviewReason === 'exact_component_mismatch' => 'The document names a different exact component subtype. Applying values is blocked to protect the engineering record.',
        $reviewReason === 'root_mismatch' => 'The source belongs to another professional material category. No technical value can be applied.',
        $variantBlocked => 'Choose one of the detected power columns in the reparse control. Variant-specific electrical values remain blocked until then.',
        $degradedExtraction && ! $hasAppliedApplication => 'The native PDF fallback recovered limited structure. Configure pdftotext for the web runtime, then use Reparse before accepting engineering values.',
        $parsedFields->isEmpty() => 'Open the source and parser warnings. A different document or manual technical entry may be required.',
        ! $hasAppliedApplication => 'Open the review workspace, compare existing and parsed values, choose Accept, Keep, or Reject, then apply only reviewed fields.',
        default => 'Continue with certificates, supporting documents, price-book linkage, and catalog approval. Parser application never approves the material automatically.',
    };
@endphp

<article class="nxmc-evidence-card nxmc-parser-card {{ $applicationBlocked ? 'is-blocked' : '' }}">
    <div class="nxmc-evidence-main nxmc-parser-card-head">
        <div class="nxmc-evidence-title">
            <span class="nxmc-parser-record-kicker">Parsed vendor evidence</span>
            <strong>{{ $datasheet->title }}</strong>
            <small>{{ $datasheet->original_filename ?? $datasheet->external_url ?? 'Manual reference' }}{{ $datasheet->revision ? ' · '.$datasheet->revision : '' }}</small>
            <div class="nxmc-parser-meta-line">
                <span class="{{ $degradedExtraction ? 'is-warning' : 'is-ready' }}">{{ $datasheet->extraction_method ? str($datasheet->extraction_method)->replace('_', ' ')->title() : 'Manual evidence' }}</span>
                @if ($datasheet->page_count)<span>{{ number_format($datasheet->page_count) }} page{{ $datasheet->page_count === 1 ? '' : 's' }}</span>@endif
                @if ($datasheet->sha256)<span title="{{ $datasheet->sha256 }}">SHA-256 {{ substr($datasheet->sha256, 0, 10) }}…</span>@endif
            </div>
        </div>
        <div class="nxmc-evidence-metric"><span>Extraction</span><strong>{{ $extractionState }}</strong></div>
        <div class="nxmc-evidence-metric"><span>Parsed fields</span><strong>{{ number_format($parsedFields->count()) }}</strong></div>
        <div class="nxmc-evidence-metric"><span>Confidence</span><strong>{{ $datasheet->parse_confidence !== null ? number_format((float) $datasheet->parse_confidence, 2, '.', ',').'%' : '—' }}</strong></div>
        <div class="nxmc-evidence-actions">
            @if (filled($datasheet->file_path) || filled($datasheet->external_url))
                <a class="nxmc-button nxmc-button-secondary nxmc-button-compact" href="{{ route('companies.materials.datasheets.source', [$company, $material->id, $datasheet->id]) }}" target="_blank" rel="noopener">Open source</a>
            @endif
            @if ($canReviewDatasheet && filled($datasheet->file_path) && ! $hasAppliedApplication)
                <form method="POST" action="{{ route('companies.materials.datasheets.reparse', [$company, $material->id, $datasheet->id]) }}" class="nxmc-parser-reparse-form" data-confirm="Reparse this source with the current schema-aware parser?">
                    @csrf
                    @if ($availableVariants->isNotEmpty())
                        <label class="sr-only" for="datasheet_variant_{{ $datasheet->id }}">Product variant</label>
                        <select id="datasheet_variant_{{ $datasheet->id }}" class="nxmc-select nxmc-parser-variant-select" name="variant_power_wp" aria-label="Select the exact product power for this datasheet">
                            <option value="">Auto match variant</option>
                            @foreach ($availableVariants as $variantPower)
                                <option value="{{ $variantPower }}" @selected((string) $selectedVariant === (string) $variantPower)>{{ rtrim(rtrim(number_format((float) $variantPower, 2, '.', ''), '0'), '.') }} Wp</option>
                            @endforeach
                        </select>
                    @endif
                    <button class="nxmc-button nxmc-button-secondary nxmc-button-compact" type="submit">{{ $variantBlocked ? 'Select and reparse' : 'Reparse' }}</button>
                </form>
            @endif
            @if ($canReviewDatasheet && $datasheet->is_active && ! $hasAppliedApplication)
                <form method="POST" action="{{ route('companies.materials.datasheets.archive', [$company, $material->id, $datasheet->id]) }}" data-confirm="Archive this datasheet evidence?" data-nxmc-action="datasheetArchive">
                    @csrf
                    @method('DELETE')
                    <button class="nxmc-button nxmc-button-danger nxmc-button-compact" type="submit">Archive</button>
                </form>
            @endif
        </div>
    </div>

    @if ($degradedExtraction)
        <div class="nxmc-parser-quality is-degraded">
            <div>
                <span>Extraction quality</span>
                <strong>Native PDF fallback is incomplete for complex tables</strong>
            </div>
            <p>The web runtime recovered {{ number_format((int) ($datasheet->page_count ?? 0)) }} page{{ (int) ($datasheet->page_count ?? 0) === 1 ? '' : 's' }} without pdftotext. Configure <code>NEXORIN_PDFTOTEXT_BINARY</code>, restart the web server, then reparse this stored source.</p>
        </div>
    @endif

    <div class="nxmc-parser-category {{ $categoryTone }}">
        <div>
            <span>Document category control</span>
            <strong>{{ $categoryTitle }}</strong>
        </div>
        <p>
            @if ($reviewReason === 'exact_component_mismatch')
                Material expects <strong>{{ $expectedExactLabel ?? $expectedRootLabel }}</strong>, while the document indicates <strong>{{ $detectedExactLabel ?? 'another component subtype' }}</strong>. Technical application is blocked.
            @elseif ($reviewReason === 'root_mismatch')
                Material expects <strong>{{ $expectedRootLabel }}</strong>, while the document indicates <strong>{{ $detectedRootLabel ?? 'another professional category' }}</strong>. Technical application is blocked.
            @elseif ($exactMatch === 'matched')
                <strong>{{ $expectedExactLabel ?? $expectedRootLabel }}</strong> is confirmed at {{ number_format((float) data_get($categoryMeta, 'exact_score', data_get($categoryMeta, 'score', 0)), 2) }}% confidence.
            @elseif ($rootMatch === 'matched')
                The document supports <strong>{{ $expectedRootLabel }}</strong>. The exact subtype <strong>{{ $expectedExactLabel ?? 'is not constrained' }}</strong> was not explicitly confirmed, so review remains required.
            @else
                Expected <strong>{{ $expectedExactLabel ?? $expectedRootLabel }}</strong>. No conflicting root category was detected, but the extracted text was not strong enough to confirm the document category.
            @endif
        </p>
    </div>

    @if ((bool) data_get($variantMeta, 'detected', false))
        <div class="nxmc-parser-variant {{ $variantBlocked ? 'is-blocked' : 'is-selected' }}">
            <div>
                <span>Multi-variant STC control</span>
                <strong>
                    @if ($selectedVariant !== null)
                        {{ rtrim(rtrim(number_format((float) $selectedVariant, 2, '.', ''), '0'), '.') }} Wp selected
                    @else
                        Exact variant required
                    @endif
                </strong>
            </div>
            <p>
                Detected {{ $availableVariants->map(fn ($power) => rtrim(rtrim(number_format((float) $power, 2, '.', ''), '0'), '.').' Wp')->implode(', ') }}.
                @if ($selectedVariant !== null)
                    Selection source: {{ str(data_get($variantMeta, 'selection_source', 'review'))->replace('_', ' ')->title() }}. Electrical candidates come only from this STC column.
                @else
                    Choose the material's exact power in the reparse control. Applying technical values remains blocked until a column is selected.
                @endif
            </p>
        </div>
    @endif

    <div class="nxmc-parser-next {{ $applicationBlocked ? 'is-blocked' : ($hasAppliedApplication ? 'is-complete' : 'is-action') }}">
        <span class="nxmc-parser-next-index">Next</span>
        <div>
            <strong>{{ $nextActionTitle }}</strong>
            <p>{{ $nextActionCopy }}</p>
        </div>
        @if (! $applicationBlocked && ! $hasAppliedApplication && $parsedFields->isNotEmpty())
            <a href="#parser-review-{{ $datasheet->id }}" class="nxmc-button nxmc-button-primary nxmc-button-compact">Open review</a>
        @elseif ($hasAppliedApplication)
            <a href="#material-evidence" class="nxmc-button nxmc-button-secondary nxmc-button-compact">Continue evidence</a>
        @endif
    </div>

    @if ($parsedFields->isNotEmpty() || ! empty($datasheet->parse_warnings))
        <details id="parser-review-{{ $datasheet->id }}" class="nxmc-evidence-details" {{ in_array($datasheet->extraction_status, ['category_mismatch', 'variant_selection_required'], true) ? 'open' : '' }}>
            <summary>Open field-by-field parser review</summary>
            <div class="nxmc-evidence-detail-body">
                @if (! empty($datasheet->parse_warnings))
                    <div class="nxmc-warning-strip nxmc-parser-warning-list">
                        @foreach ($datasheet->parse_warnings as $warning)
                            <span>{{ $warning }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($parsedFields->isNotEmpty())
                    <form method="POST" action="{{ route('companies.materials.datasheets.apply', [$company, $material->id, $datasheet->id]) }}" class="nxmc-parser-review-form" data-parser-review-workspace
                        data-confirm="Apply the selected parser decisions to this material? Accept updates only; Keep and Reject remain unchanged. The record will remain in Needs Review until governance is completed."
                        data-confirm-tone="decision" data-confirm-title="Apply reviewed datasheet decisions?" data-confirm-kicker="Datasheet governance"
                        data-confirm-label="Apply selected decisions" data-confirm-cancel-label="Cancel"
                        data-confirm-control="Accept updates only; Keep and Reject remain unchanged"
                        data-confirm-result="Selected technical values will update the catalog; material approval is still separate"
                        data-nxmc-action="apply">
                        @csrf
                        <div class="nxmc-parser-review-head">
                            <div>
                                <span class="nxmc-panel-kicker">Controlled review workspace</span>
                                <h4>Parsed candidate comparison</h4>
                                <p>Accept a safe candidate, keep the current catalog value, or reject the parser proposal. Conflicts are never applied automatically.</p>
                            </div>
                            <div class="nxmc-parser-review-tools">
                                <div class="nxmc-parser-legend" aria-label="Parser field status legend">
                                    <button type="button" class="is-active" data-parser-review-filter="all">All <b>{{ $reviewCounts['all'] }}</b></button>
                                    <button type="button" class="is-new" data-parser-review-filter="new">New <b>{{ $reviewCounts['new'] }}</b></button>
                                    <button type="button" class="is-match" data-parser-review-filter="match">Match <b>{{ $reviewCounts['match'] }}</b></button>
                                    <button type="button" class="is-conflict" data-parser-review-filter="conflict">Conflict <b>{{ $reviewCounts['conflict'] }}</b></button>
                                    <button type="button" class="is-low" data-parser-review-filter="low_confidence">Low <b>{{ $reviewCounts['low_confidence'] }}</b></button>
                                </div>
                                <button type="button" class="nxmc-button nxmc-button-secondary nxmc-button-compact" data-parser-accept-matches @disabled(!$canReviewDatasheet || $applicationBlocked || $hasAppliedApplication)>Accept matches</button>
                            </div>
                        </div>

                        <div class="nxmc-parser-review-summary" aria-live="polite">
                            <span data-parser-review-visible>{{ $reviewCounts['all'] }} fields shown</span>
                            <span>Initial suggestion: <strong>{{ $defaultAcceptedCount }} accept</strong></span>
                            <span>Confidence is parser match confidence, not engineering approval.</span>
                        </div>

                        <div class="nxmc-parser-table-wrap">
                            <table class="nxmc-parser-table">
                                <thead>
                                    <tr>
                                        <th>Technical field</th>
                                        <th>Existing catalog</th>
                                        <th>Parsed candidate</th>
                                        <th>Trace</th>
                                        <th>Review action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($parsedFields as $key => $field)
                                        @php
                                            $fieldStatus = $field['status'] ?? 'new';
                                            $defaultAction = $field['review_action'] ?? $field['suggested_action'] ?? ($fieldStatus === 'new' ? 'accept' : 'keep');
                                            if (in_array($fieldStatus, ['conflict', 'low_confidence'], true) && empty($field['review_action'])) $defaultAction = 'keep';
                                        @endphp
                                        <tr class="nxmc-parser-row is-{{ str($fieldStatus)->replace('_', '-') }}" data-parser-review-row data-parser-status="{{ $fieldStatus }}">
                                            <td data-label="Technical field">
                                                <strong>{{ $field['label'] ?? str($key)->replace('_', ' ')->title() }}</strong>
                                                <code>{{ $key }}</code>
                                                <span class="nxmc-parser-status">{{ str($fieldStatus)->replace('_', ' ')->title() }}</span>
                                            </td>
                                            <td data-label="Existing catalog">
                                                <span class="nxmc-parser-value {{ empty($field['existing_value']) ? 'is-empty' : '' }}">
                                                    {{ $field['existing_value'] ?? 'Not set' }}{{ ! empty($field['existing_unit']) ? ' '.$field['existing_unit'] : '' }}
                                                </span>
                                            </td>
                                            <td data-label="Parsed candidate">
                                                <div class="nxmc-parser-candidate-input">
                                                    <input class="nxmc-input" name="decisions[{{ $key }}][value]" value="{{ old('decisions.'.$key.'.value', $field['reviewed_value'] ?? $field['value'] ?? '') }}" aria-label="Reviewed value for {{ $field['label'] ?? $key }}" @disabled(!$canReviewDatasheet || $applicationBlocked || $hasAppliedApplication)>
                                                    <input class="nxmc-input nxmc-parser-unit-input" name="decisions[{{ $key }}][unit]" value="{{ old('decisions.'.$key.'.unit', $field['reviewed_unit'] ?? $field['unit'] ?? '') }}" aria-label="Reviewed unit for {{ $field['label'] ?? $key }}" @disabled(!$canReviewDatasheet || $applicationBlocked || $hasAppliedApplication)>
                                                </div>
                                                @if (! empty($field['raw_value']))
                                                    <small>Raw: {{ $field['raw_value'] }}{{ ! empty($field['raw_unit']) ? ' '.$field['raw_unit'] : '' }}</small>
                                                @endif
                                                @if (! empty($field['validation_warnings']))
                                                    <small class="nxmc-parser-field-warning">{{ implode(' ', $field['validation_warnings']) }}</small>
                                                @endif
                                            </td>
                                            <td data-label="Trace">
                                                <strong>{{ number_format((float) ($field['confidence'] ?? 0), 2) }}%</strong>
                                                <small>Page {{ $field['source_page'] ?? '—' }} · {{ $field['matched_alias'] ?? 'schema match' }}</small>
                                                @if (! empty($field['source_excerpt']))
                                                    <details class="nxmc-parser-source"><summary>Source excerpt</summary><p>{{ $field['source_excerpt'] }}</p></details>
                                                @endif
                                            </td>
                                            <td data-label="Review action">
                                                <select class="nxmc-select" name="decisions[{{ $key }}][action]" aria-label="Review action for {{ $field['label'] ?? $key }}" @disabled(!$canReviewDatasheet || $applicationBlocked || $hasAppliedApplication)>
                                                    <option value="accept" @selected(old('decisions.'.$key.'.action', $defaultAction) === 'accept')>Accept candidate</option>
                                                    <option value="keep" @selected(old('decisions.'.$key.'.action', $defaultAction) === 'keep')>Keep existing</option>
                                                    <option value="reject" @selected(old('decisions.'.$key.'.action', $defaultAction) === 'reject')>Reject candidate</option>
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($canReviewDatasheet)
                            <div class="nxmc-parser-review-actions">
                                <p data-parser-review-footnote>Applying fields always moves technical governance to <strong>Needs Review</strong>. It never approves the material automatically.</p>
                                <button class="nxmc-button nxmc-button-primary" type="submit" @disabled(!$canReviewDatasheet || $applicationBlocked || $hasAppliedApplication)>
                                    {{ $hasAppliedApplication ? 'Rollback before reapplying' : 'Apply selected decisions' }}
                                </button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        </details>
    @endif

    @if ($datasheet->applications->isNotEmpty())
        <div class="nxmc-parser-history">
            <div class="nxmc-parser-history-head">
                <span>Parser application history</span>
                <strong>{{ $datasheet->applications->count() }} revision{{ $datasheet->applications->count() === 1 ? '' : 's' }}</strong>
            </div>
            @foreach ($datasheet->applications as $application)
                @php
                    $acceptedDecisions = collect($application->field_decisions)->where('action', 'accept');
                    $correctedDecisions = $acceptedDecisions->where('manually_corrected', true);
                @endphp
                <div class="nxmc-parser-history-row">
                    <div class="nxmc-parser-history-copy">
                        <strong>#PAR-{{ str_pad((string) $application->id, 5, '0', STR_PAD_LEFT) }}</strong>
                        <small>
                            {{ $acceptedDecisions->count() }} applied field{{ $acceptedDecisions->count() === 1 ? '' : 's' }} · {{ $application->applied_at?->format('d M Y H:i') }} · {{ $application->applier?->name ?? 'System user' }}{{ $correctedDecisions->isNotEmpty() ? ' · '.$correctedDecisions->count().' manually corrected' : '' }}
                            @if ($application->status === 'rolled_back')
                                · Rolled back {{ $application->rolled_back_at?->format('d M Y H:i') }} by {{ $application->rollbackActor?->name ?? 'System user' }}
                            @endif
                        </small>
                        @if ($application->status === 'rolled_back' && filled($application->rollback_note))
                            <small class="nxmc-parser-rollback-note">{{ $application->rollback_note }}</small>
                        @endif
                        @if ($acceptedDecisions->isNotEmpty())
                            <details class="nxmc-parser-history-trace">
                                <summary>Open field trace</summary>
                                <div class="nxmc-parser-history-trace-grid">
                                    @foreach ($acceptedDecisions as $key => $decision)
                                        <div>
                                            <strong>{{ $decision['label'] ?? str($key)->replace('_', ' ')->title() }}</strong>
                                            <span>{{ $decision['value'] ?? '—' }}{{ filled($decision['unit'] ?? null) ? ' '.$decision['unit'] : '' }}</span>
                                            <small>Page {{ $decision['source_page'] ?? '—' }} · {{ isset($decision['confidence']) ? number_format((float) $decision['confidence'], 2).'%' : 'manual trace' }}{{ ! empty($decision['manually_corrected']) ? ' · Corrected' : '' }}</small>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>
                    <span class="nxmc-parser-history-status is-{{ $application->status }}">{{ str($application->status)->replace('_', ' ')->title() }}</span>
                    @if ($canReviewDatasheet && $application->status === 'applied')
                        <form method="POST" action="{{ route('companies.materials.datasheets.applications.rollback', [$company, $material->id, $datasheet->id, $application->id]) }}" data-confirm="Rollback this parser application? Newer manual changes will block unsafe rollback.">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="rollback_note" value="Rolled back from material datasheet review workspace.">
                            <button class="nxmc-button nxmc-button-secondary nxmc-button-compact" type="submit">Rollback</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</article>
