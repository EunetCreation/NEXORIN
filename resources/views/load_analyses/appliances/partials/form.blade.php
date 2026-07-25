@php
    $editing = isset($loadAppliance);
    $categoryOptions = ['Cooling', 'Lighting', 'Office', 'Production', 'Pump', 'Kitchen', 'IT', 'Security', 'Water Heating', 'Entertainment', 'Other'];
    $selectedCategory = old('category', $loadAppliance->category ?? '');
@endphp

<div id="nxlaEquipmentShell" class="nxla-equipment-grid nxla-processing" data-processing-label="Calculating load…">
    <div class="nxla-equipment-main">
        <section class="nxla-card nxla-form-section" data-nxla-reveal>
            <header class="nxla-form-section-head">
                <div>
                    <h3>Equipment identity</h3>
                    <p>Use a clear name and consistent category so the profile remains easy to review.</p>
                </div>
                <span class="nxla-form-step">01</span>
            </header>
            <div class="nxla-form-section-body">
                <div class="nxla-form-grid">
                    <div class="ui-field nxla-form-span">
                        <label class="ui-label" for="name">Equipment name</label>
                        <input id="name" class="ui-input @error('name') is-invalid @enderror" type="text" name="name" value="{{ old('name', $loadAppliance->name ?? '') }}" maxlength="160" placeholder="Example: Split AC 1 PK" required data-nxla-name>
                        <div class="ui-help">Use a specific label that both engineering and commercial teams can understand.</div>
                        @error('name')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ui-field nxla-form-span">
                        <label class="ui-label" for="category">Category</label>
                        <input id="category" class="ui-input @error('category') is-invalid @enderror" type="text" name="category" list="nxlaCategoryOptions" value="{{ $selectedCategory }}" maxlength="100" placeholder="Cooling, lighting, production, office" data-nxla-category>
                        <datalist id="nxlaCategoryOptions">
                            @foreach ($categoryOptions as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>
                        <div class="nxla-category-chips">
                            @foreach ($categoryOptions as $category)
                                <button type="button" class="nxla-category-chip {{ strcasecmp($selectedCategory, $category) === 0 ? 'is-active' : '' }}" data-nxla-category-chip="{{ $category }}">{{ $category }}</button>
                            @endforeach
                        </div>
                        @error('category')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ui-field">
                        <label class="ui-label" for="quantity">Quantity</label>
                        <input id="quantity" class="ui-input @error('quantity') is-invalid @enderror" type="number" name="quantity" value="{{ old('quantity', $loadAppliance->quantity ?? 1) }}" min="1" max="100000" required data-nxla-quantity>
                        @error('quantity')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ui-field">
                        <label class="ui-label" for="rated_power_w">Rated power per unit (W)</label>
                        <input id="rated_power_w" class="ui-input @error('rated_power_w') is-invalid @enderror" type="number" name="rated_power_w" value="{{ old('rated_power_w', $loadAppliance->rated_power_w ?? '') }}" min="0.001" step="0.001" required data-nxla-power>
                        @error('rated_power_w')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="nxla-card nxla-form-section" data-nxla-reveal>
            <header class="nxla-form-section-head">
                <div>
                    <h3>Operating behavior</h3>
                    <p>Schedule and usage inputs determine the hourly curve and daily energy.</p>
                </div>
                <span class="nxla-form-step">02</span>
            </header>
            <div class="nxla-form-section-body">
                <div class="nxla-form-grid">
                    <div class="ui-field">
                        <label class="ui-label" for="operating_hours_per_day">Operating hours per day</label>
                        <input id="operating_hours_per_day" class="ui-input @error('operating_hours_per_day') is-invalid @enderror" type="number" name="operating_hours_per_day" value="{{ old('operating_hours_per_day', $loadAppliance->operating_hours_per_day ?? 1) }}" min="0.01" max="24" step="0.01" required data-nxla-hours>
                        @error('operating_hours_per_day')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ui-field">
                        <label class="ui-label" for="start_hour">Daily start hour</label>
                        <select id="start_hour" class="ui-select @error('start_hour') is-invalid @enderror" name="start_hour" required data-nxla-start>
                            @for ($hour = 0; $hour < 24; $hour++)
                                <option value="{{ $hour }}" @selected((int) old('start_hour', $loadAppliance->start_hour ?? 8) === $hour)>{{ sprintf('%02d:00', $hour) }}</option>
                            @endfor
                        </select>
                        @error('start_hour')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ui-field nxla-form-span">
                        <label class="ui-label" for="usage_factor">Usage factor</label>
                        <div class="nxla-usage-row">
                            <input id="usage_factor_range" class="nxla-range" type="range" min="1" max="100" step="1" value="{{ (int) round((float) old('usage_factor', $loadAppliance->usage_factor ?? 1) * 100) }}" data-nxla-usage-range>
                            <span class="nxla-usage-badge" data-nxla-usage-badge>{{ (int) round((float) old('usage_factor', $loadAppliance->usage_factor ?? 1) * 100) }}%</span>
                        </div>
                        <input id="usage_factor" class="ui-input @error('usage_factor') is-invalid @enderror" style="margin-top:10px;" type="number" name="usage_factor" value="{{ old('usage_factor', $loadAppliance->usage_factor ?? 1) }}" min="0.01" max="1" step="0.01" required data-nxla-usage>
                        <div class="ui-help">Use 1.00 for full rated operation. Reduce it when the equipment cycles or normally runs below full load.</div>
                        @error('usage_factor')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="nxla-card nxla-form-section" data-nxla-reveal>
            <header class="nxla-form-section-head">
                <div>
                    <h3>Electrical detail and criticality</h3>
                    <p>Capture starting demand and backup relevance without cluttering the core form.</p>
                </div>
                <span class="nxla-form-step">03</span>
            </header>
            <div class="nxla-form-section-body">
                <div class="nxla-form-grid">
                    <div class="ui-field">
                        <label class="ui-label" for="starting_multiplier">Starting multiplier</label>
                        <input id="starting_multiplier" class="ui-input @error('starting_multiplier') is-invalid @enderror" type="number" name="starting_multiplier" value="{{ old('starting_multiplier', $loadAppliance->starting_multiplier ?? 1) }}" min="1" max="20" step="0.01" required data-nxla-starting>
                        <div class="ui-help">Use 1.00 for non-motor loads. Increase it for compressors, pumps, and motor inrush.</div>
                        @error('starting_multiplier')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ui-field">
                        <label class="ui-label" for="is_critical">Critical load</label>
                        <select id="is_critical" class="ui-select @error('is_critical') is-invalid @enderror" name="is_critical" required data-nxla-critical>
                            <option value="0" @selected(! (bool) old('is_critical', $loadAppliance->is_critical ?? false))>No</option>
                            <option value="1" @selected((bool) old('is_critical', $loadAppliance->is_critical ?? false))>Yes</option>
                        </select>
                        @error('is_critical')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="ui-field">
                        <label class="ui-label" for="sort_order">Display order</label>
                        <input id="sort_order" class="ui-input @error('sort_order') is-invalid @enderror" type="number" name="sort_order" value="{{ old('sort_order', $loadAppliance->sort_order ?? 0) }}" min="0" step="1">
                        @error('sort_order')<div class="ui-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="nxla-critical-note" data-nxla-critical-note>
                        Standard load. This equipment contributes to the demand profile but is not currently a battery-backup priority.
                    </div>
                </div>
            </div>
        </section>

        <section class="nxla-card nxla-form-section" data-nxla-reveal>
            <header class="nxla-form-section-head">
                <div>
                    <h3>Engineering notes</h3>
                    <p>Record the assumptions another reviewer should understand later.</p>
                </div>
                <span class="nxla-form-step">04</span>
            </header>
            <div class="nxla-form-section-body">
                <div class="ui-field">
                    <label class="ui-label" for="notes">Equipment notes</label>
                    <textarea id="notes" class="ui-textarea @error('notes') is-invalid @enderror" name="notes" maxlength="3000" placeholder="Operating pattern, nameplate condition, or survey notes">{{ old('notes', $loadAppliance->notes ?? '') }}</textarea>
                    @error('notes')<div class="ui-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <div class="nxla-form-actions">
            <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}#equipment" class="ui-button ui-button-secondary">Cancel</a>
            <button type="submit" class="ui-button ui-button-primary" data-processing-text="Calculating load…">{{ $editing ? 'Save Equipment' : 'Add Equipment' }}</button>
        </div>
    </div>

    <aside class="nxla-preview">
        <section class="nxla-preview-head">
            <span>Live engineering preview</span>
            <h3 data-nxla-preview-title>{{ $editing ? $loadAppliance->name : 'New equipment profile' }}</h3>
            <p data-nxla-preview-subtitle>Enter quantity, power, and schedule to generate the estimate.</p>
        </section>

        <div class="nxla-preview-grid">
            <article class="nxla-preview-stat"><span>Connected load</span><strong data-nxla-preview-connected>0.00 kW</strong></article>
            <article class="nxla-preview-stat"><span>Daily energy</span><strong data-nxla-preview-daily>0.00 kWh</strong></article>
            <article class="nxla-preview-stat"><span>Starting demand</span><strong data-nxla-preview-starting>0.00 kW</strong></article>
            <article class="nxla-preview-stat"><span>Schedule</span><strong data-nxla-preview-schedule>08:00–09:00</strong></article>
        </div>

        <section class="nxla-timeline">
            <div class="nxla-timeline-head">
                <strong>Operating timeline</strong>
                <span>24-hour view</span>
            </div>
            <div class="nxla-timeline-grid">
                @for ($hour = 0; $hour < 24; $hour++)
                    <span class="nxla-timeline-cell" data-nxla-hour="{{ $hour }}" title="{{ sprintf('%02d:00', $hour) }}"></span>
                @endfor
            </div>
        </section>

        <section class="nxla-readable">
            <strong>Readable interpretation</strong>
            <p data-nxla-readable>This equipment will be summarized as soon as the core inputs are available.</p>
        </section>

        <div class="nxla-preview-actions" aria-label="Equipment form actions">
            <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}#equipment" class="ui-button ui-button-secondary">Cancel</a>
            <button type="submit" class="ui-button ui-button-primary" data-processing-text="Calculating load…">{{ $editing ? 'Save Equipment' : 'Add Equipment' }}</button>
        </div>
    </aside>
</div>
