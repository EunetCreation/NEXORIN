@php
    $editing = isset($loadAppliance);
@endphp

<div class="ui-form-grid">
    <div class="ui-field">
        <label class="ui-label" for="name">Equipment name</label>
        <input
            id="name"
            class="ui-input @error('name') is-invalid @enderror"
            type="text"
            name="name"
            value="{{ old('name', $loadAppliance->name ?? '') }}"
            maxlength="160"
            placeholder="Example: Split AC 1 PK"
            required
        >
        @error('name')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="category">Category</label>
        <input
            id="category"
            class="ui-input @error('category') is-invalid @enderror"
            type="text"
            name="category"
            value="{{ old('category', $loadAppliance->category ?? '') }}"
            maxlength="100"
            placeholder="Cooling, lighting, production, office"
        >
        @error('category')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="quantity">Quantity</label>
        <input id="quantity" class="ui-input @error('quantity') is-invalid @enderror" type="number" name="quantity" value="{{ old('quantity', $loadAppliance->quantity ?? 1) }}" min="1" max="100000" required>
        @error('quantity')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="rated_power_w">Rated power per unit (W)</label>
        <input id="rated_power_w" class="ui-input @error('rated_power_w') is-invalid @enderror" type="number" name="rated_power_w" value="{{ old('rated_power_w', $loadAppliance->rated_power_w ?? '') }}" min="0.001" step="0.001" required>
        @error('rated_power_w')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="operating_hours_per_day">Operating hours per day</label>
        <input id="operating_hours_per_day" class="ui-input @error('operating_hours_per_day') is-invalid @enderror" type="number" name="operating_hours_per_day" value="{{ old('operating_hours_per_day', $loadAppliance->operating_hours_per_day ?? 1) }}" min="0.01" max="24" step="0.01" required>
        @error('operating_hours_per_day')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="usage_factor">Usage factor</label>
        <input id="usage_factor" class="ui-input @error('usage_factor') is-invalid @enderror" type="number" name="usage_factor" value="{{ old('usage_factor', $loadAppliance->usage_factor ?? 1) }}" min="0.01" max="1" step="0.01" required>
        <div class="ui-help">Use 1.00 for full rated operation. Use a lower value for cycling or partial loading.</div>
        @error('usage_factor')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="start_hour">Daily start hour</label>
        <select id="start_hour" class="ui-select @error('start_hour') is-invalid @enderror" name="start_hour" required>
            @for ($hour = 0; $hour < 24; $hour++)
                <option value="{{ $hour }}" @selected((int) old('start_hour', $loadAppliance->start_hour ?? 8) === $hour)>
                    {{ sprintf('%02d:00', $hour) }}
                </option>
            @endfor
        </select>
        <div class="ui-help">Operation may continue past midnight when the duration crosses 24:00.</div>
        @error('start_hour')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="starting_multiplier">Starting multiplier</label>
        <input id="starting_multiplier" class="ui-input @error('starting_multiplier') is-invalid @enderror" type="number" name="starting_multiplier" value="{{ old('starting_multiplier', $loadAppliance->starting_multiplier ?? 1) }}" min="1" max="20" step="0.01" required>
        <div class="ui-help">Use 1.00 for non-motor loads. Enter the known inrush multiplier for motors or compressors.</div>
        @error('starting_multiplier')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="sort_order">Display order</label>
        <input id="sort_order" class="ui-input @error('sort_order') is-invalid @enderror" type="number" name="sort_order" value="{{ old('sort_order', $loadAppliance->sort_order ?? 0) }}" min="0" step="1">
        @error('sort_order')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field">
        <label class="ui-label" for="is_critical">Critical load</label>
        <select id="is_critical" class="ui-select @error('is_critical') is-invalid @enderror" name="is_critical" required>
            <option value="0" @selected(! (bool) old('is_critical', $loadAppliance->is_critical ?? false))>No</option>
            <option value="1" @selected((bool) old('is_critical', $loadAppliance->is_critical ?? false))>Yes</option>
        </select>
        <div class="ui-help">Critical loads become a battery backup input in the hybrid sizing stage.</div>
        @error('is_critical')<div class="ui-error">{{ $message }}</div>@enderror
    </div>

    <div class="ui-field ui-form-span-2">
        <label class="ui-label" for="notes">Equipment notes</label>
        <textarea id="notes" class="ui-textarea @error('notes') is-invalid @enderror" name="notes" maxlength="3000" placeholder="Operating pattern, nameplate condition, or survey notes">{{ old('notes', $loadAppliance->notes ?? '') }}</textarea>
        @error('notes')<div class="ui-error">{{ $message }}</div>@enderror
    </div>
</div>

<div class="ui-form-actions">
    <a href="{{ route('companies.load-analyses.show', [$company, $project]) }}" class="ui-button ui-button-secondary">Cancel</a>
    <button type="submit" class="ui-button ui-button-primary">{{ $editing ? 'Save Equipment' : 'Add Equipment' }}</button>
</div>
