@php
    $categoryOptions = ['Cooling', 'Lighting', 'Office', 'Production', 'Pump', 'Kitchen', 'IT', 'Security', 'Water Heating', 'Entertainment', 'Other'];
    $selectedCategory = old('category', '');
    $selectedCritical = old('is_critical', '');
    $selectedStartHour = old('start_hour', '');
@endphp

<div class="nx15-layout">
    <div class="nx15-main-column">
        <section class="nx15-section is-identity" data-nxl-surface="raised" data-tone="cyan" data-nx15-section>
            <header class="nx15-section-head">
                <div class="nx15-step">01</div>
                <div>
                    <span class="nx15-section-kicker">Equipment identity</span>
                    <h3>Define the load group</h3>
                    <p>Use one clear equipment name and a category that remains understandable in the project load inventory.</p>
                </div>
            </header>

            <div class="nx15-section-body nx15-grid nx15-grid-basics">
                <div class="nx15-field is-wide @error('name') has-error @enderror">
                    <label for="name">Equipment name <b>*</b></label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="160" placeholder="Example: Split AC 1 PK" autocomplete="off" required data-nx15-name @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    <small>Use a specific label that can be recognized later in engineering review.</small>
                    @error('name')<span id="name-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field is-wide @error('category') has-error @enderror">
                    <label for="category">Category <em>Free text</em></label>
                    <input id="category" type="text" name="category" value="{{ $selectedCategory }}" maxlength="100" placeholder="Enter or choose a category" autocomplete="off" data-nx15-category @error('category') aria-invalid="true" aria-describedby="category-error" @enderror>
                    <div class="nx15-category-chips" aria-label="Category quick fill">
                        @foreach ($categoryOptions as $category)
                            <button type="button" class="nx15-category-chip {{ strcasecmp($selectedCategory, $category) === 0 ? 'is-active' : '' }}" data-nx15-category-chip="{{ $category }}" aria-pressed="{{ strcasecmp($selectedCategory, $category) === 0 ? 'true' : 'false' }}">{{ $category }}</button>
                        @endforeach
                    </div>
                    <small>Suggestions are quick-fill labels, not a governed equipment taxonomy.</small>
                    @error('category')<span id="category-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('quantity') has-error @enderror">
                    <label for="quantity">Quantity <b>*</b></label>
                    <div class="nx15-control">
                        <input id="quantity" type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="100000" step="1" inputmode="numeric" required data-nx15-quantity @error('quantity') aria-invalid="true" aria-describedby="quantity-error" @enderror>
                        <span>units</span>
                    </div>
                    <small>Default quantity is 1; change it to match the inventory.</small>
                    @error('quantity')<span id="quantity-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('rated_power_w') has-error @enderror">
                    <label for="rated_power_w">Rated power per unit <b>*</b> <em>User entered</em></label>
                    <div class="nx15-control">
                        <input id="rated_power_w" type="number" name="rated_power_w" value="{{ old('rated_power_w') }}" max="100000000" step="any" inputmode="decimal" placeholder="Enter power" required data-nx15-power @error('rated_power_w') aria-invalid="true" aria-describedby="rated-power-error" @enderror>
                        <span>W</span>
                    </div>
                    <small>User-entered here; not manufacturer-verified.</small>
                    @error('rated_power_w')<span id="rated-power-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="nx15-section is-operating" data-nxl-surface="raised" data-tone="cyan" data-nx15-section>
            <header class="nx15-section-head">
                <div class="nx15-step">02</div>
                <div>
                    <span class="nx15-section-kicker">Operating profile</span>
                    <h3>Set the simplified daily schedule</h3>
                    <p>The current model uses one daily start time, one operating duration, and one usage factor. It does not model weekday/weekend or seasonal schedules here.</p>
                </div>
            </header>

            <div class="nx15-section-body nx15-grid nx15-grid-three">
                <div class="nx15-field @error('operating_hours_per_day') has-error @enderror">
                    <label for="operating_hours_per_day">Operating hours per day <b>*</b> <em>User entered</em></label>
                    <div class="nx15-control">
                        <input id="operating_hours_per_day" type="number" name="operating_hours_per_day" value="{{ old('operating_hours_per_day') }}" max="24" step="any" inputmode="decimal" placeholder="Enter duration" required data-nx15-hours @error('operating_hours_per_day') aria-invalid="true" aria-describedby="hours-error" @enderror>
                        <span>h/day</span>
                    </div>
                    @error('operating_hours_per_day')<span id="hours-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('start_hour') has-error @enderror">
                    <label for="start_hour">Daily start time <b>*</b> <em>User entered</em></label>
                    <select id="start_hour" name="start_hour" required data-nx15-start @error('start_hour') aria-invalid="true" aria-describedby="start-error" @enderror>
                        <option value="">Select start time</option>
                        @for ($hour = 0; $hour < 24; $hour++)
                            <option value="{{ $hour }}" @selected((string) $selectedStartHour === (string) $hour)>{{ sprintf('%02d:00', $hour) }}</option>
                        @endfor
                    </select>
                    @error('start_hour')<span id="start-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('usage_factor') has-error @enderror">
                    <label for="usage_factor">Usage factor <b>*</b> <em>Simplified factor</em></label>
                    <div class="nx15-control">
                        <input id="usage_factor" type="number" name="usage_factor" value="{{ old('usage_factor') }}" max="1" step="any" inputmode="decimal" placeholder="0.00–1.00" required data-nx15-usage @error('usage_factor') aria-invalid="true" aria-describedby="usage-error" @enderror>
                        <span>0–1</span>
                    </div>
                    <small>Not a diversity, coincidence, or measured load factor.</small>
                    @error('usage_factor')<span id="usage-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="nx15-section is-behavior" data-nxl-surface="raised" data-tone="cyan" data-nx15-section>
            <header class="nx15-section-head">
                <div class="nx15-step">03</div>
                <div>
                    <span class="nx15-section-kicker">Load behavior</span>
                    <h3>Capture starting demand and critical classification</h3>
                    <p>User-entered modelling only; not measured inrush/LRA or a formal backup-service classification.</p>
                </div>
            </header>

            <div class="nx15-section-body nx15-grid nx15-grid-two">
                <div class="nx15-field @error('starting_multiplier') has-error @enderror">
                    <label for="starting_multiplier">Starting multiplier <b>*</b> <em>Preliminary assumption</em></label>
                    <div class="nx15-control">
                        <input id="starting_multiplier" type="number" name="starting_multiplier" value="{{ old('starting_multiplier') }}" min="1" max="20" step="any" inputmode="decimal" placeholder="Enter multiplier" required data-nx15-starting @error('starting_multiplier') aria-invalid="true" aria-describedby="starting-error" @enderror>
                        <span>×</span>
                    </div>
                    <small>Connected load × this multiplier. It is not measured inrush current.</small>
                    @error('starting_multiplier')<span id="starting-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>

                <div class="nx15-field @error('is_critical') has-error @enderror">
                    <label for="is_critical">Critical load <b>*</b> <em>User classification</em></label>
                    <select id="is_critical" name="is_critical" required data-nx15-critical @error('is_critical') aria-invalid="true" aria-describedby="critical-error" @enderror>
                        <option value="">Select classification</option>
                        <option value="0" @selected((string) $selectedCritical === '0')>Standard load</option>
                        <option value="1" @selected((string) $selectedCritical === '1')>Critical load</option>
                    </select>
                    <small>Feeds the critical-energy summary; it does not guarantee backup service.</small>
                    @error('is_critical')<span id="critical-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="nx15-section is-notes" data-nxl-surface="raised" data-tone="cyan" data-nx15-section>
            <header class="nx15-section-head">
                <div class="nx15-step">04</div>
                <div>
                    <span class="nx15-section-kicker">Engineering notes</span>
                    <h3>Record the basis for this entry</h3>
                    <p>Record nameplate observations, survey context, and operating assumptions a reviewer should understand later.</p>
                </div>
            </header>

            <div class="nx15-section-body">
                <div class="nx15-field @error('notes') has-error @enderror">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" maxlength="3000" rows="4" placeholder="Example: Rated power copied from observed nameplate; operating hours based on site interview." @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ old('notes') }}</textarea>
                    <div class="nx15-note-footer">
                        <small>Optional. Keep assumptions and source observations explicit.</small>
                        <span data-nx15-note-count>0 / 3000</span>
                    </div>
                    @error('notes')<span id="notes-error" class="nx15-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>
    </div>

    <aside class="nx15-rail" data-nxl-rail>
        <section class="nx15-context-card" data-nxl-surface="raised" data-tone="cyan">
            <div class="nx15-context-eyebrow">
                <span>Project context</span>
                <b>Current Load Analysis</b>
            </div>
            <div class="nx15-context-hero">
                <strong>{{ $project->name }}</strong>
                <p>{{ $project->customer?->name ?: 'Customer not recorded' }}</p>
            </div>
            <div class="nx15-context-tags" aria-label="Current load analysis context">
                <span>{{ $project->proposed_system_label }}</span>
                <span>{{ $analysis->supply_phase_label }}</span>
                <span>{{ $analysis->source_mode_label }}</span>
            </div>
            @if ($project->site_address)
                <div class="nx15-context-site">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 18s5-4.7 5-9a5 5 0 1 0-10 0c0 4.3 5 9 5 9Z"/><circle cx="10" cy="9" r="1.6"/></svg>
                    <span>{{ $project->site_address }}</span>
                </div>
            @endif
        </section>

        <section class="nx15-preview-card" data-nxl-surface="hero" data-tone="cyan" data-nx15-preview-card>
            <header class="nx15-preview-head">
                <div>
                    <span>Current entry estimate</span>
                    <h3 data-nx15-preview-title>New equipment load</h3>
                    <p data-nx15-preview-subtitle>Unsaved browser preview · server validation remains authoritative.</p>
                </div>
                <span class="nx15-preview-badge">Unsaved</span>
            </header>

            <div class="nx15-preview-empty" data-nx15-preview-empty>
                <i aria-hidden="true"></i>
                <div>
                    <strong>Build the equipment model</strong>
                    <span>Add rated power and operating inputs to reveal deterministic load estimates.</span>
                </div>
            </div>

            <article class="nx15-preview-primary" data-nx15-metric="connected">
                <div>
                    <span>Connected load</span>
                    <small>Quantity × rated power</small>
                </div>
                <strong data-nx15-preview-connected>—</strong>
            </article>

            <div class="nx15-preview-secondary">
                <article data-nx15-metric="daily">
                    <div><span>Modeled daily energy</span><small>Hours × usage factor</small></div>
                    <strong data-nx15-preview-daily>—</strong>
                </article>
                <article data-nx15-metric="starting">
                    <div><span>Modeled starting demand</span><small>Entered multiplier</small></div>
                    <strong data-nx15-preview-starting>—</strong>
                </article>
                <article data-nx15-metric="classification">
                    <div><span>Load classification</span><small data-nx15-preview-critical-note>User classification</small></div>
                    <strong data-nx15-preview-critical>—</strong>
                </article>
            </div>

            <section class="nx15-schedule">
                <header>
                    <div>
                        <strong>24-hour schedule</strong>
                        <span>One repeated daily window</span>
                    </div>
                    <b data-nx15-preview-schedule>Awaiting schedule</b>
                </header>
                <div class="nx15-timeline" aria-label="24-hour operating schedule preview">
                    @for ($hour = 0; $hour < 24; $hour++)
                        <span class="nx15-hour" data-nx15-hour="{{ $hour }}" title="{{ sprintf('%02d:00', $hour) }}"></span>
                    @endfor
                </div>
                <div class="nx15-time-scale" aria-hidden="true"><span>00</span><span>06</span><span>12</span><span>18</span><span>24</span></div>
            </section>
        </section>

        <section class="nx15-basis-card" data-nxl-surface="raised" data-tone="cyan">
            <header class="nx15-rail-head">
                <span>Engineering basis</span>
                <strong>How this entry is handled</strong>
            </header>
            <div class="nx15-basis-list" role="list">
                <div role="listitem"><strong>Daily energy</strong><span>Simplified usage factor; no diversity or interval telemetry.</span></div>
                <div role="listitem"><strong>Starting demand</strong><span>Entered multiplier; not measured LRA or inrush.</span></div>
                <div role="listitem"><strong>Schedule</strong><span>One repeated daily window; midnight wrap is supported.</span></div>
            </div>
            <div class="nx15-storage-note">
                <span>Server behavior</span>
                <p>Add Equipment stores only the validated inputs against this project's current Load Analysis. Preview values remain derived.</p>
                <div class="nx15-save-status" data-nx15-completeness aria-live="polite">
                    <span class="nx15-save-dot" aria-hidden="true"></span>
                    <span>Required inputs are incomplete</span>
                </div>
            </div>
        </section>
    </aside>
</div>
