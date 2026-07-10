@extends('layouts.app')

@section('title', $material->name)

@section('content')
    <div class="ui-page">
        <section class="ui-detail-hero">
            <div class="ui-detail-avatar">MT</div>
            <div class="ui-detail-copy">
                <h2>{{ $material->name }}</h2>
                <p>{{ $material->sku }} · {{ $material->scope_label }} · {{ $material->category?->name ?? 'Uncategorized' }}</p>
            </div>
            <div class="ui-detail-actions">
                <a href="{{ route('companies.materials.index', $company) }}" class="ui-button ui-button-secondary">Back to Catalog</a>
                @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                    <a href="{{ route('companies.materials.edit', [$company, $material->id]) }}" class="ui-button ui-button-primary">Edit Material</a>
                @endif
            </div>
        </section>

        <section class="ui-stats-grid" aria-label="Material facts">
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">ST</span></div>
                <strong class="ui-stat-value">{{ str($material->catalog_status ?? 'draft')->replace('_', ' ')->title() }}</strong>
                <span class="ui-stat-label">Catalog Status</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">SY</span></div>
                <strong class="ui-stat-value">{{ $material->system_type_label ?: 'General' }}</strong>
                <span class="ui-stat-label">System Compatibility</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">UN</span></div>
                <strong class="ui-stat-value">{{ $material->uom }}</strong>
                <span class="ui-stat-label">Unit of Measure</span>
            </article>
            <article class="ui-stat-card">
                <div class="ui-stat-head"><span class="ui-stat-icon">PB</span></div>
                <strong class="ui-stat-value">{{ number_format($catalogReadiness['score'] ?? 0) }}%</strong>
                <span class="ui-stat-label">Completeness Score</span>
            </article>
        </section>

        <div class="ui-detail-grid">
            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Technical Profile</h3>
                        <p class="ui-card-description">Core catalog fields for future engineering compatibility and BOQ selection.</p>
                    </div>
                </div>
                <div class="ui-card-body">
                    <div class="ui-info-grid">
                        <div class="ui-info-item"><span>Brand</span><strong>{{ $material->brand ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Model</span><strong>{{ $material->model ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Material Type</span><strong>{{ $material->material_type ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Cost Group</span><strong>{{ $material->cost_group ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Nominal Power</span><strong>{{ $material->nominal_power_wp ? number_format((float) $material->nominal_power_wp, 2, ',', '.') . ' Wp' : '—' }}</strong></div>
                        <div class="ui-info-item"><span>Rated Power</span><strong>{{ $material->rated_power_kw ? number_format((float) $material->rated_power_kw, 3, ',', '.') . ' kW' : '—' }}</strong></div>
                        <div class="ui-info-item"><span>Capacity</span><strong>{{ $material->nominal_capacity_kwh ? number_format((float) $material->nominal_capacity_kwh, 3, ',', '.') . ' kWh' : '—' }}</strong></div>
                        <div class="ui-info-item"><span>Voltage</span><strong>{{ $material->voltage_v ? number_format((float) $material->voltage_v, 2, ',', '.') . ' V' : '—' }}</strong></div>
                        <div class="ui-info-item"><span>Current</span><strong>{{ $material->current_a ? number_format((float) $material->current_a, 2, ',', '.') . ' A' : '—' }}</strong></div>
                        <div class="ui-info-item"><span>IP Rating</span><strong>{{ $material->ip_rating ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Warranty</span><strong>{{ $material->warranty_years ? $material->warranty_years . ' years' : '—' }}</strong></div>
                        <div class="ui-info-item"><span>Default Scope</span><strong>{{ str($material->default_scope_mode)->replace('_', ' ')->title() }}</strong></div>
                    </div>

                    @if ($material->description)
                        <div style="margin-top: 18px;">
                            <h4 class="ui-card-title" style="font-size: 14px;">Description</h4>
                            <p class="ui-card-description">{{ $material->description }}</p>
                        </div>
                    @endif

                    @if ($material->technical_notes)
                        <div style="margin-top: 18px;">
                            <h4 class="ui-card-title" style="font-size: 14px;">Technical Notes</h4>
                            <p class="ui-card-description">{{ $material->technical_notes }}</p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <div>
                        <h3 class="ui-card-title">Governance</h3>
                        <p class="ui-card-description">Catalog control, price links, datasheets, and certification readiness.</p>
                    </div>
                </div>
                <div class="ui-card-body">
                    <div class="ui-info-grid">
                        <div class="ui-info-item"><span>Catalog Scope</span><strong>{{ $material->scope_label }}</strong></div>
                        <div class="ui-info-item"><span>Category</span><strong>{{ $material->category?->name ?? '—' }}</strong></div>
                        <div class="ui-info-item"><span>Datasheets</span><strong>{{ number_format($material->datasheets->count()) }}</strong></div>
                        <div class="ui-info-item"><span>Certificates</span><strong>{{ number_format($material->certificates->count()) }}</strong></div>
                        <div class="ui-info-item"><span>Readiness</span><strong>{{ str($catalogReadiness['status'] ?? 'incomplete')->replace('_', ' ')->title() }}</strong></div>
                        <div class="ui-info-item"><span>Compliance</span><strong>{{ str($material->compliance_status ?? 'needs_review')->replace('_', ' ')->title() }}</strong></div>
                    </div>

                    @if (! empty($catalogReadiness['notes']))
                        <div class="ui-alert ui-alert-warning" style="margin-top: 18px;">
                            <div>
                                <strong>Catalog readiness review required.</strong>
                                <ul style="margin: 8px 0 0 18px;">
                                    @foreach ($catalogReadiness['notes'] as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @if ($canModify)
                        <div class="ui-form-actions" style="margin-top: 18px; justify-content: flex-start;">
                            @if (($catalogReadiness['can_approve'] ?? false) && ($material->catalog_status ?? 'draft') !== 'approved')
                                <form method="POST" action="{{ route('companies.materials.approve', [$company, $material->id]) }}" data-confirm="Approve this material for BOQ and quotation use?">
                                    @csrf
                                    @method('PATCH')
                                    <button class="ui-button ui-button-primary" type="submit">Approve Material</button>
                                </form>
                            @endif

                            @if ($material->is_active)
                                <form method="POST" action="{{ route('companies.materials.archive', [$company, $material->id]) }}" data-confirm="Archive this material item?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ui-button ui-button-danger" type="submit">Archive Material</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('companies.materials.activate', [$company, $material->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="ui-button ui-button-primary" type="submit">Activate Material</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <div class="ui-alert ui-alert-success" style="margin-top: 18px;">
                            <div>This is a global reference item. Create a company-specific material if you need custom pricing, specifications, or availability rules.</div>
                        </div>
                    @endif
                </div>
            </section>
        </div>


        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Technical Specifications</h3>
                    <p class="ui-card-description">Normalized category-specific fields used for engineering checks, datasheet traceability, and BOQ selection.</p>
                </div>
            </div>
            <div class="ui-card-body">
                @if ($material->specs->isEmpty())
                    <div class="ui-empty ui-empty-compact"><div><h3>No normalized specs yet</h3><p>Upload and apply a datasheet, or add specifications through the catalog workflow.</p></div></div>
                @else
                    <div class="ui-table-scroll">
                        <table class="ui-table">
                            <thead><tr><th>Spec</th><th>Value</th><th>Unit</th><th>Filterable</th></tr></thead>
                            <tbody>
                                @foreach ($material->specs as $spec)
                                    <tr>
                                        <td><strong>{{ $spec->spec_label }}</strong><br><span class="ui-muted">{{ $spec->spec_key }}</span></td>
                                        <td>{{ $spec->spec_value ?? '—' }}</td>
                                        <td>{{ $spec->spec_unit ?? '—' }}</td>
                                        <td>{{ $spec->is_filterable ? 'Yes' : 'No' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Datasheets and Parser Review</h3>
                    <p class="ui-card-description">Upload vendor datasheets, extract text-based fields, review parser confidence, then apply validated values to material specs.</p>
                </div>
            </div>
            <div class="ui-card-body">
                @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update'))
                    <form method="POST" action="{{ route('companies.materials.datasheets.store', [$company, $material->id]) }}" enctype="multipart/form-data" class="ui-form-section" style="margin-bottom: 18px;">
                        @csrf
                        <div class="ui-form-grid">
                            <div class="ui-field">
                                <label class="ui-label" for="datasheet_title">Title</label>
                                <input id="datasheet_title" class="ui-input" name="title" value="{{ old('title', $material->name.' Datasheet') }}" required>
                            </div>
                            <div class="ui-field">
                                <label class="ui-label" for="datasheet_file">Datasheet File</label>
                                <input id="datasheet_file" class="ui-input" type="file" name="datasheet_file">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label" for="datasheet_external_url">External URL</label>
                                <input id="datasheet_external_url" class="ui-input" name="external_url" placeholder="https://vendor.example/datasheet.pdf">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label" for="datasheet_revision">Revision</label>
                                <input id="datasheet_revision" class="ui-input" name="revision" placeholder="Rev. A">
                            </div>
                        </div>
                        <div class="ui-form-actions" style="justify-content: flex-start;">
                            <button class="ui-button ui-button-primary" type="submit">Upload and Parse Datasheet</button>
                        </div>
                    </form>
                @endif

                @if ($material->datasheets->isEmpty())
                    <div class="ui-empty ui-empty-compact"><div><h3>No datasheets uploaded</h3><p>Catalog approval requires at least one active parsed datasheet for traceability.</p></div></div>
                @else
                    <div class="ui-table-scroll">
                        <table class="ui-table">
                            <thead><tr><th>Datasheet</th><th>Extraction</th><th>Fields</th><th>Confidence</th><th>Actions</th></tr></thead>
                            <tbody>
                                @foreach ($material->datasheets as $datasheet)
                                    <tr>
                                        <td>
                                            <strong>{{ $datasheet->title }}</strong><br>
                                            <span class="ui-muted">{{ $datasheet->original_filename ?? $datasheet->external_url ?? 'Manual reference' }}</span>
                                        </td>
                                        <td>{{ str($datasheet->extraction_status)->replace('_', ' ')->title() }}</td>
                                        <td>{{ number_format(count($datasheet->parsed_fields ?? [])) }}</td>
                                        <td>{{ $datasheet->parse_confidence !== null ? number_format((float) $datasheet->parse_confidence, 2) . '%' : '—' }}</td>
                                        <td>
                                            @if ($canModify && auth()->user()->hasCompanyPermission($company, 'equipment.update') && ! empty($datasheet->parsed_fields))
                                                <form method="POST" action="{{ route('companies.materials.datasheets.apply', [$company, $material->id, $datasheet->id]) }}" style="display:inline;" data-confirm="Apply parsed fields to material specs?">
                                                    @csrf
                                                    <button class="ui-button ui-button-secondary" type="submit">Apply Specs</button>
                                                </form>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                    @if (! empty($datasheet->parse_warnings))
                                        <tr>
                                            <td colspan="5"><span class="ui-muted">Warnings: {{ implode(' ', $datasheet->parse_warnings) }}</span></td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <section class="ui-card">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-card-title">Price Book References</h3>
                    <p class="ui-card-description">Current links between this material and company price books.</p>
                </div>
            </div>
            <div class="ui-card-body">
                @if (! auth()->user()->hasCompanyPermission($company, 'price.cost.view'))
                    <div class="ui-empty ui-empty-compact"><div><h3>Price references are restricted</h3><p>Viewing cost and price book references requires cost-price permission.</p></div></div>
                @elseif ($material->priceBookItems->isEmpty())
                    <div class="ui-empty ui-empty-compact"><div><h3>No price book links yet</h3><p>Price book item management will connect catalog materials to supplier cost and selling price records.</p></div></div>
                @else
                    <div class="ui-table-scroll">
                        <table class="ui-table">
                            <thead><tr><th>Price Book</th><th>Supplier</th><th>Unit Cost</th><th>Selling Price</th><th>Availability</th><th>Valid Until</th></tr></thead>
                            <tbody>
                                @foreach ($material->priceBookItems as $item)
                                    <tr>
                                        <td>{{ $item->priceBook?->name ?? '—' }}</td>
                                        <td>{{ $item->supplier?->name ?? $item->priceBook?->supplier?->name ?? '—' }}</td>
                                        <td>{{ $item->priceBook?->currency ?? 'IDR' }} {{ number_format((float) $item->unit_cost, 0, ',', '.') }}</td>
                                        <td>{{ $item->selling_price ? ($item->priceBook?->currency ?? 'IDR') . ' ' . number_format((float) $item->selling_price, 0, ',', '.') : 'Auto / not set' }}</td>
                                        <td>{{ str($item->availability_status)->replace('_', ' ')->title() }}</td>
                                        <td>{{ $item->valid_until?->format('d M Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
