@php
    $categoryOptions = ['Cooling', 'Lighting', 'Office', 'Production', 'Pump', 'Kitchen', 'IT', 'Security', 'Water Heating', 'Entertainment', 'Other'];
    $compactNumber = static function ($value, int $precision = 4): string {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = number_format((float) $value, $precision, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    };

    $selectedCategory = (string) old('category', $loadAppliance->category ?? '');
    $selectedCritical = (string) old('is_critical', $loadAppliance->is_critical ? '1' : '0');
    $selectedStartHour = (string) old('start_hour', $loadAppliance->start_hour);
@endphp

<div class="nx15-layout nx16-layout">
    <div class="nx15-main-column">
        <section class="nx15-section is-identity" data-nxl-surface="raised" data-tone="cyan" data-nx15-section>
            <header class="nx15-section-head">
                <div class="nx15-step">01</div>
                <div>
                    <span class="nx15-section-kicker">Equipment identity</span>
                    <h3>Stored equipment details</h3>
                    <p>Stored values are loaded below; edits remain unsaved until Save Equipment succeeds.</p>
                </div>
            </header>

            <div class="nx15-section-body nx15-grid nx15-grid-basics">
                <div class="nx15-field is-wide @error('name') has-error @enderror" data-nx16-field="name">
                    <label for="name">Equipment name <b>*</b></label>
                    <input id="name" type="text" name="name" value="{{ old('name', $loadAppliance->name) }}" maxlength="160" autocomplete="off" required data-nx16-name @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    <small>Stored: {{ $loadAppliance->name }}</small>
                    @error('name')<span id="name-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field is-wide @error('category') has-error @enderror" data-nx16-field="category">
                    <label for="category">Category <em>Stored · free text</em></label>
                    <input id="category" type="text" name="category" value="{{ $selectedCategory }}" maxlength="100" placeholder="Enter or choose a category" autocomplete="off" data-nx16-category @error('category') aria-invalid="true" aria-describedby="category-error" @enderror>
                    <div class="nx15-category-chips" aria-label="Category quick fill">
                        @foreach ($categoryOptions as $category)
                            <button type="button" class="nx15-category-chip {{ strcasecmp($selectedCategory, $category) === 0 ? 'is-active' : '' }}" data-nx16-category-chip="{{ $category }}" aria-pressed="{{ strcasecmp($selectedCategory, $category) === 0 ? 'true' : 'false' }}">{{ $category }}</button>
                        @endforeach
                    </div>
                    <small>Stored category: {{ $loadAppliance->category ?: 'Not recorded' }}. Suggestions are quick-fill labels, not a governed taxonomy.</small>
                    @error('category')<span id="category-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('quantity') has-error @enderror" data-nx16-field="quantity">
                    <label for="quantity">Quantity <b>*</b></label>
                    <div class="nx15-control">
                        <input id="quantity" type="number" name="quantity" value="{{ old('quantity', $loadAppliance->quantity) }}" min="1" max="100000" step="1" inputmode="numeric" required data-nx16-quantity @error('quantity') aria-invalid="true" aria-describedby="quantity-error" @enderror>
                        <span>units</span>
                    </div>
                    <small>Stored: {{ number_format($loadAppliance->quantity) }} {{ $loadAppliance->quantity === 1 ? 'unit' : 'units' }}.</small>
                    @error('quantity')<span id="quantity-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('rated_power_w') has-error @enderror" data-nx16-field="power">
                    <label for="rated_power_w">Rated power per unit <b>*</b> <em>Stored · user entered</em></label>
                    <div class="nx15-control">
                        <input id="rated_power_w" type="number" name="rated_power_w" value="{{ old('rated_power_w', $compactNumber($loadAppliance->rated_power_w, 3)) }}" max="100000000" step="any" inputmode="decimal" required data-nx16-power @error('rated_power_w') aria-invalid="true" aria-describedby="rated-power-error" @enderror>
                        <span>W</span>
                    </div>
                    <small>Stored: {{ number_format((float) $loadAppliance->rated_power_w, ((float) $loadAppliance->rated_power_w == (int) $loadAppliance->rated_power_w) ? 0 : 3) }} W/unit. Not manufacturer-verified by this workflow.</small>
                    @error('rated_power_w')<span id="rated-power-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="nx15-section is-operating" data-nxl-surface="raised" data-tone="cyan" data-nx15-section>
            <header class="nx15-section-head">
                <div class="nx15-step">02</div>
                <div>
                    <span class="nx15-section-kicker">Operating profile</span>
                    <h3>Daily operating window</h3>
                    <p>One daily start time, duration, and simplified Usage factor. Midnight wrap remains deterministic.</p>
                </div>
            </header>

            <div class="nx15-section-body nx15-grid nx15-grid-three">
                <div class="nx15-field @error('operating_hours_per_day') has-error @enderror" data-nx16-field="hours">
                    <label for="operating_hours_per_day">Operating hours per day <b>*</b></label>
                    <div class="nx15-control">
                        <input id="operating_hours_per_day" type="number" name="operating_hours_per_day" value="{{ old('operating_hours_per_day', $compactNumber($loadAppliance->operating_hours_per_day, 2)) }}" max="24" step="any" inputmode="decimal" required data-nx16-hours @error('operating_hours_per_day') aria-invalid="true" aria-describedby="hours-error" @enderror>
                        <span>h/day</span>
                    </div>
                    <small>Stored: {{ $compactNumber($loadAppliance->operating_hours_per_day, 2) }} h/day.</small>
                    @error('operating_hours_per_day')<span id="hours-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('start_hour') has-error @enderror" data-nx16-field="start">
                    <label for="start_hour">Daily start time <b>*</b></label>
                    <select id="start_hour" name="start_hour" required data-nx16-start @error('start_hour') aria-invalid="true" aria-describedby="start-error" @enderror>
                        @for ($hour = 0; $hour < 24; $hour++)
                            <option value="{{ $hour }}" @selected($selectedStartHour === (string) $hour)>{{ sprintf('%02d:00', $hour) }}</option>
                        @endfor
                    </select>
                    <small>Stored: {{ sprintf('%02d:00', $loadAppliance->start_hour) }}. One repeated daily start time only.</small>
                    @error('start_hour')<span id="start-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('usage_factor') has-error @enderror" data-nx16-field="usage">
                    <label for="usage_factor">Usage factor <b>*</b> <em>Stored · simplified factor</em></label>
                    <div class="nx15-control">
                        <input id="usage_factor" type="number" name="usage_factor" value="{{ old('usage_factor', $compactNumber($loadAppliance->usage_factor, 4)) }}" max="1" step="any" inputmode="decimal" required data-nx16-usage @error('usage_factor') aria-invalid="true" aria-describedby="usage-error" @enderror>
                        <span>0–1</span>
                    </div>
                    <small>Stored: {{ $compactNumber($loadAppliance->usage_factor, 4) }}. Not diversity, coincidence, or measured load factor.</small>
                    @error('usage_factor')<span id="usage-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="nx15-section is-behavior" data-nxl-surface="raised" data-tone="cyan" data-nx15-section>
            <header class="nx15-section-head">
                <div class="nx15-step">03</div>
                <div>
                    <span class="nx15-section-kicker">Load behavior</span>
                    <h3>Starting behavior &amp; classification</h3>
                    <p>User-entered modelling only. Starting multiplier is not measured inrush/LRA; Critical load does not guarantee backup service.</p>
                </div>
            </header>

            <div class="nx15-section-body nx15-grid nx15-grid-two">
                <div class="nx15-field @error('starting_multiplier') has-error @enderror" data-nx16-field="starting">
                    <label for="starting_multiplier">Starting multiplier <b>*</b> <em>Stored · preliminary assumption</em></label>
                    <div class="nx15-control">
                        <input id="starting_multiplier" type="number" name="starting_multiplier" value="{{ old('starting_multiplier', $compactNumber($loadAppliance->starting_multiplier, 2)) }}" min="1" max="20" step="any" inputmode="decimal" required data-nx16-starting @error('starting_multiplier') aria-invalid="true" aria-describedby="starting-error" @enderror>
                        <span>×</span>
                    </div>
                    <small>Stored: {{ $compactNumber($loadAppliance->starting_multiplier, 2) }}×. Connected load × multiplier; not measured inrush current.</small>
                    @error('starting_multiplier')<span id="starting-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('is_critical') has-error @enderror" data-nx16-field="critical">
                    <label for="is_critical">Critical load <b>*</b> <em>Stored · user classification</em></label>
                    <select id="is_critical" name="is_critical" required data-nx16-critical @error('is_critical') aria-invalid="true" aria-describedby="critical-error" @enderror>
                        <option value="0" @selected($selectedCritical === '0')>Standard load</option>
                        <option value="1" @selected($selectedCritical === '1')>Critical load</option>
                    </select>
                    <small>Stored: {{ $loadAppliance->is_critical ? 'Critical load' : 'Standard load' }}. This classification only contributes to the current critical-load summary.</small>
                    @error('is_critical')<span id="critical-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="nx15-section is-notes" data-nxl-surface="raised" data-tone="cyan" data-nx15-section>
            <header class="nx15-section-head">
                <div class="nx15-step">04</div>
                <div>
                    <span class="nx15-section-kicker">Engineering notes</span>
                    <h3>Recorded operating basis</h3>
                    <p>Record survey observations or operating assumptions without implying evidence this workflow does not store.</p>
                </div>
            </header>

            <div class="nx15-section-body">
                <div class="nx15-field @error('notes') has-error @enderror" data-nx16-field="notes">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" maxlength="3000" rows="4" placeholder="Record survey observations or operating assumptions." data-nx16-notes @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ old('notes', $loadAppliance->notes) }}</textarea>
                    <div class="nx15-note-footer">
                        <small>Optional. Saving replaces the current stored notes with this value.</small>
                        <span data-nx16-note-count>0 / 3000</span>
                    </div>
                    @error('notes')<span id="notes-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>
    </div>

    <aside class="nx15-rail nx16-rail" data-nxl-rail>
        <section class="nx15-context-card nx16-context-card" data-nxl-surface="raised" data-tone="cyan">
            <div class="nx15-context-eyebrow">
                <span>Project context</span>
                <b>Current Load Analysis</b>
            </div>
            <div class="nx15-context-hero">
                <strong>{{ $project->name }}</strong>
                <p>{{ $project->customer?->name ?: 'Customer not recorded' }}</p>
            </div>
            <div class="nx15-context-tags" aria-label="Current Load Analysis context">
                <span>{{ $project->proposed_system_label }}</span>
                <span>{{ $analysis->supply_phase_label }}</span>
                <span>{{ $analysis->source_mode_label }}</span>
            </div>
            <div class="nx16-record-context">
                <span>Existing equipment record</span>
                <strong>{{ $loadAppliance->name }}</strong>
                <small>{{ $loadAppliance->category ?: 'Category not recorded' }} · {{ $loadAppliance->is_critical ? 'Critical load' : 'Standard load' }}</small>
            </div>
            @if ($project->site_address)
                <div class="nx15-context-site">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 18s5-4.7 5-9a5 5 0 1 0-10 0c0 4.3 5 9 5 9Z"/><circle cx="10" cy="9" r="1.6"/></svg>
                    <span>{{ $project->site_address }}</span>
                </div>
            @endif
        </section>

        <section class="nx16-compare-card" data-nxl-surface="hero" data-tone="cyan" data-nx16-preview-card>
            <header class="nx16-compare-head">
                <div>
                    <span>Stored vs unsaved estimate</span>
                    <h3 data-nx16-preview-title>{{ $loadAppliance->name }}</h3>
                    <p>Current form values are browser-derived and remain unsaved until server validation succeeds.</p>
                </div>
                <b data-nx16-preview-badge>Stored</b>
            </header>

            <div class="nx16-dirty-summary" data-nx16-dirty-summary aria-live="polite">
                <i aria-hidden="true"></i>
                <div>
                    <strong>No unsaved changes</strong>
                    <span>Current form values match the stored equipment record.</span>
                </div>
            </div>

            <div class="nx16-compare-metrics">
                <article data-nx16-metric="connected">
                    <div class="nx16-metric-label"><strong>Connected load</strong><span>Quantity × rated power</span></div>
                    <div class="nx16-value"><span>Stored</span><b>{{ number_format($loadAppliance->connected_load_kw, 2) }} kW</b></div>
                    <div class="nx16-value"><span>Current form</span><b data-nx16-current-connected>{{ number_format($loadAppliance->connected_load_kw, 2) }} kW</b></div>
                    <div class="nx16-delta" data-nx16-delta-connected>0.00 kW</div>
                </article>
                <article data-nx16-metric="daily">
                    <div class="nx16-metric-label"><strong>Modeled daily energy</strong><span>Hours × Usage factor</span></div>
                    <div class="nx16-value"><span>Stored</span><b>{{ number_format($loadAppliance->daily_energy_kwh, 2) }} kWh/day</b></div>
                    <div class="nx16-value"><span>Current form</span><b data-nx16-current-daily>{{ number_format($loadAppliance->daily_energy_kwh, 2) }} kWh/day</b></div>
                    <div class="nx16-delta" data-nx16-delta-daily>0.00 kWh/day</div>
                </article>
                <article data-nx16-metric="starting">
                    <div class="nx16-metric-label"><strong>Starting demand</strong><span>Entered multiplier</span></div>
                    <div class="nx16-value"><span>Stored</span><b>{{ number_format($loadAppliance->starting_load_kw, 2) }} kW</b></div>
                    <div class="nx16-value"><span>Current form</span><b data-nx16-current-starting>{{ number_format($loadAppliance->starting_load_kw, 2) }} kW</b></div>
                    <div class="nx16-delta" data-nx16-delta-starting>0.00 kW</div>
                </article>
                <article data-nx16-metric="classification">
                    <div class="nx16-metric-label"><strong>Load classification</strong><span>User classification only</span></div>
                    <div class="nx16-value"><span>Stored</span><b>{{ $loadAppliance->is_critical ? 'Critical load' : 'Standard load' }}</b></div>
                    <div class="nx16-value"><span>Current form</span><b data-nx16-current-critical>{{ $loadAppliance->is_critical ? 'Critical load' : 'Standard load' }}</b></div>
                    <div class="nx16-delta" data-nx16-delta-critical>No change</div>
                </article>
            </div>

            <div class="nx16-schedules">
                <section class="nx16-schedule-block">
                    <header>
                        <div><strong>Stored schedule</strong><span>Current stored daily window</span></div>
                        <b>{{ $loadAppliance->schedule_label }}</b>
                    </header>
                    <div class="nx15-timeline" aria-label="Stored 24-hour operating schedule">
                        @for ($hour = 0; $hour < 24; $hour++)
                            <span class="nx15-hour" data-nx16-stored-hour="{{ $hour }}" title="{{ sprintf('%02d:00', $hour) }}"></span>
                        @endfor
                    </div>
                    <div class="nx15-time-scale" aria-hidden="true"><span>00</span><span>06</span><span>12</span><span>18</span><span>24</span></div>
                </section>

                <section class="nx16-schedule-block is-current">
                    <header>
                        <div><strong>Current form schedule</strong><span>Unsaved daily window</span></div>
                        <b data-nx16-current-schedule>{{ $loadAppliance->schedule_label }}</b>
                    </header>
                    <div class="nx15-timeline" aria-label="Current unsaved 24-hour operating schedule">
                        @for ($hour = 0; $hour < 24; $hour++)
                            <span class="nx15-hour" data-nx16-current-hour="{{ $hour }}" title="{{ sprintf('%02d:00', $hour) }}"></span>
                        @endfor
                    </div>
                    <div class="nx15-time-scale" aria-hidden="true"><span>00</span><span>06</span><span>12</span><span>18</span><span>24</span></div>
                </section>
            </div>
        </section>

        <section class="nx15-basis-card nx16-basis-card" data-nxl-surface="raised" data-tone="cyan">
            <header class="nx15-rail-head">
                <span>Engineering basis</span>
                <strong>What Save Equipment changes</strong>
            </header>
            <div class="nx15-basis-list" role="list">
                <div role="listitem"><strong>Stored inputs</strong><span>Only validated appliance fields are updated; project and Load Analysis identity stay fixed.</span></div>
                <div role="listitem"><strong>Derived values</strong><span>Connected load, daily energy, and starting demand remain calculated values; preview metrics are not persisted.</span></div>
                <div role="listitem"><strong>Downstream freshness</strong><span>The parent Load Analysis timestamp changes so existing recommendation and technical-calculation stale checks can react.</span></div>
            </div>
            <div class="nx15-storage-note">
                <span>Current limitation</span>
                <p>This workflow edits one current equipment record. It does not provide immutable appliance revision history or concurrent-edit conflict detection.</p>
                <div class="nx15-save-status is-complete" data-nx16-save-status aria-live="polite">
                    <span class="nx15-save-dot" aria-hidden="true"></span>
                    <span>Current stored record loaded</span>
                </div>
            </div>
        </section>
    </aside>
</div>
