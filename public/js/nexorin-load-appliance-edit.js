(() => {
    const form = document.querySelector('[data-nx16-form]');
    if (!form) return;

    const page = document.querySelector('[data-nx16-page]');
    const q = (selector) => form.querySelector(selector);
    const qa = (selector) => [...form.querySelectorAll(selector)];
    const pageQuery = (selector) => page?.querySelector(selector) || document.querySelector(selector);

    const refs = {
        name: q('[data-nx16-name]'),
        category: q('[data-nx16-category]'),
        quantity: q('[data-nx16-quantity]'),
        power: q('[data-nx16-power]'),
        hours: q('[data-nx16-hours]'),
        start: q('[data-nx16-start]'),
        usage: q('[data-nx16-usage]'),
        starting: q('[data-nx16-starting]'),
        critical: q('[data-nx16-critical]'),
        notes: q('[data-nx16-notes]'),
        noteCount: q('[data-nx16-note-count]'),
        title: q('[data-nx16-preview-title]'),
        badge: q('[data-nx16-preview-badge]'),
        previewCard: q('[data-nx16-preview-card]'),
        dirtySummary: q('[data-nx16-dirty-summary]'),
        changeState: pageQuery('[data-nx16-change-state]'),
        saveButton: pageQuery('[data-nx16-submit]'),
        saveLabel: pageQuery('[data-nx16-submit-label]'),
        currentConnected: q('[data-nx16-current-connected]'),
        currentDaily: q('[data-nx16-current-daily]'),
        currentStarting: q('[data-nx16-current-starting]'),
        currentCritical: q('[data-nx16-current-critical]'),
        deltaConnected: q('[data-nx16-delta-connected]'),
        deltaDaily: q('[data-nx16-delta-daily]'),
        deltaStarting: q('[data-nx16-delta-starting]'),
        deltaCritical: q('[data-nx16-delta-critical]'),
        currentSchedule: q('[data-nx16-current-schedule]'),
        storedTimeline: qa('[data-nx16-stored-hour]'),
        currentTimeline: qa('[data-nx16-current-hour]'),
    };

    const stored = {
        name: form.dataset.storedName || '',
        category: form.dataset.storedCategory || '',
        quantity: Number.parseFloat(form.dataset.storedQuantity || ''),
        power: Number.parseFloat(form.dataset.storedPower || ''),
        hours: Number.parseFloat(form.dataset.storedHours || ''),
        start: Number.parseFloat(form.dataset.storedStart || ''),
        usage: Number.parseFloat(form.dataset.storedUsage || ''),
        starting: Number.parseFloat(form.dataset.storedStarting || ''),
        critical: form.dataset.storedCritical || '0',
        notes: form.dataset.storedNotes || '',
        connected: Number.parseFloat(form.dataset.storedConnected || ''),
        daily: Number.parseFloat(form.dataset.storedDaily || ''),
        startingDemand: Number.parseFloat(form.dataset.storedStartingDemand || ''),
    };

    const parse = (input) => {
        if (!input || String(input.value).trim() === '') return null;
        const value = Number.parseFloat(input.value);
        return Number.isFinite(value) ? value : null;
    };

    const round4 = (value) => Math.round((value + Number.EPSILON) * 10000) / 10000;
    const numericEqual = (a, b) => Number.isFinite(a) && Number.isFinite(b) && Math.abs(a - b) < 0.0000001;
    const stringValue = (input) => String(input?.value || '').trim();
    const notesValue = () => String(refs.notes?.value || '');

    const format = (value, digits = 2) => new Intl.NumberFormat(undefined, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(value);

    const formatMetric = (value) => {
        const absolute = Math.abs(value);
        const digits = absolute > 0 && absolute < 0.01 ? 4 : (absolute > 0 && absolute < 1 ? 3 : 2);
        return format(value, digits);
    };

    const formatDuration = (value) => {
        const rounded = Math.round(value * 100) / 100;
        return Number.isInteger(rounded) ? String(rounded) : String(rounded).replace(/0+$/, '').replace(/\.$/, '');
    };

    const clock = (value) => {
        const normalized = ((value % 24) + 24) % 24;
        let hour = Math.floor(normalized);
        let minutes = Math.round((normalized - hour) * 60);
        if (minutes === 60) {
            hour = (hour + 1) % 24;
            minutes = 0;
        }
        return `${String(hour).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    };

    const validQuantity = (value) => value !== null && Number.isInteger(value) && value >= 1 && value <= 100000;
    const validPower = (value) => value !== null && value > 0 && value <= 100000000;
    const validHours = (value) => value !== null && value > 0 && value <= 24;
    const validUsage = (value) => value !== null && value > 0 && value <= 1;
    const validStart = (value) => value !== null && Number.isInteger(value) && value >= 0 && value <= 23;
    const validStarting = (value) => value !== null && value >= 1 && value <= 20;

    const getCurrent = () => ({
        name: stringValue(refs.name),
        category: stringValue(refs.category),
        quantity: parse(refs.quantity),
        power: parse(refs.power),
        hours: parse(refs.hours),
        start: parse(refs.start),
        usage: parse(refs.usage),
        starting: parse(refs.starting),
        critical: refs.critical?.value ?? '',
        notes: notesValue(),
    });

    const calculate = (current) => {
        const connectedReady = validQuantity(current.quantity) && validPower(current.power);
        const dailyReady = connectedReady && validHours(current.hours) && validUsage(current.usage);
        const startingReady = connectedReady && validStarting(current.starting);
        const scheduleReady = validStart(current.start) && validHours(current.hours);
        const criticalReady = current.critical === '0' || current.critical === '1';

        const connected = connectedReady ? round4((current.quantity * current.power) / 1000) : null;
        const daily = dailyReady ? round4(connected * current.hours * current.usage) : null;
        const startingDemand = startingReady ? round4(connected * current.starting) : null;

        return {
            connectedReady,
            dailyReady,
            startingReady,
            scheduleReady,
            criticalReady,
            connected,
            daily,
            startingDemand,
        };
    };

    const setMetric = (element, text) => {
        if (!element || element.textContent === text) return;
        element.textContent = text;
        const row = element.closest('[data-nx16-metric]');
        if (!row || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        row.classList.remove('is-updated');
        requestAnimationFrame(() => {
            row.classList.add('is-updated');
            window.setTimeout(() => row.classList.remove('is-updated'), 320);
        });
    };

    const setDelta = (element, current, baseline, unit) => {
        if (!element) return;
        element.classList.remove('is-positive', 'is-negative', 'is-changed', 'is-invalid');

        if (!Number.isFinite(current)) {
            element.textContent = 'Needs valid input';
            element.classList.add('is-invalid');
            return;
        }

        const delta = round4(current - baseline);
        if (Math.abs(delta) < 0.0000001) {
            element.textContent = `0.00 ${unit}`;
            return;
        }

        const sign = delta > 0 ? '+' : '−';
        element.textContent = `${sign}${formatMetric(Math.abs(delta))} ${unit}`;
        element.classList.add(delta > 0 ? 'is-positive' : 'is-negative');
    };

    const resetTimeline = (timeline) => {
        timeline.forEach((cell) => {
            cell.style.setProperty('--nx15-fill', '0%');
            cell.classList.remove('is-active-window', 'is-window-start', 'is-window-end');
        });
    };

    const updateTimeline = (timeline, start, hours) => {
        resetTimeline(timeline);
        if (!validStart(start) || !validHours(hours)) return;

        const allocation = Array(24).fill(0);
        let remainingHours = Math.min(Math.max(hours, 0), 24);
        let position = start % 24;
        let safetyCounter = 0;

        while (remainingHours > 0.000001 && safetyCounter < 48) {
            const hourFloor = Math.floor(position);
            const hourIndex = ((hourFloor % 24) + 24) % 24;
            const fractionIntoHour = position - hourFloor;
            const availableInHour = 1 - fractionIntoHour;
            const allocatedHours = Math.min(remainingHours, availableInHour);

            allocation[hourIndex] = Math.min(allocation[hourIndex] + allocatedHours, 1);
            remainingHours -= allocatedHours;
            position += allocatedHours;
            safetyCounter += 1;
        }

        timeline.forEach((cell) => {
            const raw = cell.dataset.nx16StoredHour ?? cell.dataset.nx16CurrentHour ?? '0';
            const hour = Number.parseInt(raw, 10);
            const fraction = allocation[hour] || 0;
            cell.style.setProperty('--nx15-fill', `${Math.round(fraction * 10000) / 100}%`);
            cell.classList.toggle('is-active-window', fraction > 0.000001);
        });

        const startIndex = ((Math.floor(start) % 24) + 24) % 24;
        const endPosition = start + Math.min(hours, 24);
        const endIndex = ((Math.ceil(endPosition) - 1) % 24 + 24) % 24;
        timeline[startIndex]?.classList.add('is-window-start');
        timeline[endIndex]?.classList.add('is-window-end');
    };

    const scheduleLabel = (start, hours) => {
        if (!validStart(start) || !validHours(hours)) return 'Needs valid schedule';
        if (Math.abs(hours - 24) < 0.000001) return `24 h from ${clock(start)}`;
        const end = start + hours;
        return `${clock(start)}–${clock(end)} · ${formatDuration(hours)} h${end > 24 ? ' · wraps midnight' : ''}`;
    };

    const dirtyMap = (current) => ({
        name: current.name !== stored.name.trim(),
        category: current.category !== stored.category.trim(),
        quantity: !numericEqual(current.quantity, stored.quantity),
        power: !numericEqual(current.power, stored.power),
        hours: !numericEqual(current.hours, stored.hours),
        start: !numericEqual(current.start, stored.start),
        usage: !numericEqual(current.usage, stored.usage),
        starting: !numericEqual(current.starting, stored.starting),
        critical: current.critical !== stored.critical,
        notes: current.notes !== stored.notes,
    });

    const updateFieldStates = (dirty) => {
        Object.entries(dirty).forEach(([key, changed]) => {
            q(`[data-nx16-field="${key}"]`)?.classList.toggle('is-unsaved', changed);
        });
    };

    const updateCategoryChips = () => {
        const current = stringValue(refs.category).toLowerCase();
        qa('[data-nx16-category-chip]').forEach((chip) => {
            const active = String(chip.dataset.nx16CategoryChip || '').toLowerCase() === current && current !== '';
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const updateNotes = () => {
        if (!refs.noteCount || !refs.notes) return;
        refs.noteCount.textContent = `${refs.notes.value.length.toLocaleString()} / 3000`;
    };

    const updateDirtyState = (dirty) => {
        const count = Object.values(dirty).filter(Boolean).length;
        const hasUnsaved = count > 0;

        refs.changeState?.classList.toggle('has-unsaved', hasUnsaved);
        refs.saveButton?.classList.toggle('has-unsaved', hasUnsaved);
        refs.previewCard?.classList.toggle('has-unsaved', hasUnsaved);
        refs.dirtySummary?.classList.toggle('has-unsaved', hasUnsaved);

        if (refs.changeState) {
            refs.changeState.textContent = hasUnsaved
                ? `${count} unsaved ${count === 1 ? 'change' : 'changes'}`
                : 'No unsaved changes';
        }
        if (refs.badge) refs.badge.textContent = hasUnsaved ? 'Unsaved' : 'Stored';

        if (refs.dirtySummary) {
            const title = refs.dirtySummary.querySelector('strong');
            const detail = refs.dirtySummary.querySelector('span');
            if (title) title.textContent = hasUnsaved ? 'Unsaved changes' : 'No unsaved changes';
            if (detail) {
                detail.textContent = hasUnsaved
                    ? `${count} stored ${count === 1 ? 'field differs' : 'fields differ'} from the current form.`
                    : 'Current form values match the stored equipment record.';
            }
        }
    };

    const updatePreview = () => {
        const current = getCurrent();
        const calculated = calculate(current);
        const dirty = dirtyMap(current);

        updateFieldStates(dirty);
        updateDirtyState(dirty);
        updateCategoryChips();
        updateNotes();

        if (refs.title) refs.title.textContent = current.name || 'Equipment name required';

        setMetric(refs.currentConnected, calculated.connectedReady ? `${formatMetric(calculated.connected)} kW` : '—');
        setMetric(refs.currentDaily, calculated.dailyReady ? `${formatMetric(calculated.daily)} kWh/day` : '—');
        setMetric(refs.currentStarting, calculated.startingReady ? `${formatMetric(calculated.startingDemand)} kW` : '—');

        setDelta(refs.deltaConnected, calculated.connected, stored.connected, 'kW');
        setDelta(refs.deltaDaily, calculated.daily, stored.daily, 'kWh/day');
        setDelta(refs.deltaStarting, calculated.startingDemand, stored.startingDemand, 'kW');

        if (refs.currentCritical) {
            if (current.critical === '1') setMetric(refs.currentCritical, 'Critical load');
            else if (current.critical === '0') setMetric(refs.currentCritical, 'Standard load');
            else setMetric(refs.currentCritical, '—');
        }

        if (refs.deltaCritical) {
            refs.deltaCritical.classList.remove('is-changed', 'is-invalid');
            if (!calculated.criticalReady) {
                refs.deltaCritical.textContent = 'Needs classification';
                refs.deltaCritical.classList.add('is-invalid');
            } else if (current.critical === stored.critical) {
                refs.deltaCritical.textContent = 'No change';
            } else {
                refs.deltaCritical.textContent = stored.critical === '1'
                    ? 'Critical → Standard'
                    : 'Standard → Critical';
                refs.deltaCritical.classList.add('is-changed');
            }
        }

        if (refs.currentSchedule) {
            refs.currentSchedule.textContent = scheduleLabel(current.start, current.hours);
        }

        updateTimeline(refs.currentTimeline, current.start, current.hours);
    };

    qa('[data-nx16-category-chip]').forEach((chip) => {
        chip.addEventListener('click', () => {
            if (!refs.category) return;
            refs.category.value = chip.dataset.nx16CategoryChip || '';
            refs.category.dispatchEvent(new Event('input', { bubbles: true }));
            refs.category.focus({ preventScroll: true });
        });
    });

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);

    form.addEventListener('submit', () => {
        document.body.classList.add('is-nx15-submitting');
        if (refs.saveButton) refs.saveButton.disabled = true;
        if (refs.saveLabel) refs.saveLabel.textContent = 'Saving…';
    });

    updateTimeline(refs.storedTimeline, stored.start, stored.hours);
    updatePreview();
})();
