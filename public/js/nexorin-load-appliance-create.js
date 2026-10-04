(() => {
    const form = document.querySelector('[data-nx15-form]');
    if (!form) return;

    const q = (selector) => form.querySelector(selector);
    const qa = (selector) => [...form.querySelectorAll(selector)];
    const refs = {
        name: q('[data-nx15-name]'),
        category: q('[data-nx15-category]'),
        quantity: q('[data-nx15-quantity]'),
        power: q('[data-nx15-power]'),
        hours: q('[data-nx15-hours]'),
        start: q('[data-nx15-start]'),
        usage: q('[data-nx15-usage]'),
        starting: q('[data-nx15-starting]'),
        critical: q('[data-nx15-critical]'),
        title: q('[data-nx15-preview-title]'),
        subtitle: q('[data-nx15-preview-subtitle]'),
        connected: q('[data-nx15-preview-connected]'),
        daily: q('[data-nx15-preview-daily]'),
        startingDemand: q('[data-nx15-preview-starting]'),
        criticalValue: q('[data-nx15-preview-critical]'),
        criticalNote: q('[data-nx15-preview-critical-note]'),
        schedule: q('[data-nx15-preview-schedule]'),
        completeness: q('[data-nx15-completeness]'),
        notes: q('#notes'),
        noteCount: q('[data-nx15-note-count]'),
        timeline: qa('[data-nx15-hour]'),
        metricConnected: q('[data-nx15-metric="connected"]'),
        metricDaily: q('[data-nx15-metric="daily"]'),
        metricStarting: q('[data-nx15-metric="starting"]'),
        metricClassification: q('[data-nx15-metric="classification"]'),
        previewCard: q('[data-nx15-preview-card]'),
        previewEmpty: q('[data-nx15-preview-empty]'),
    };

    const parse = (input) => {
        if (!input || String(input.value).trim() === '') return null;
        const value = Number.parseFloat(input.value);
        return Number.isFinite(value) ? value : null;
    };

    const format = (value, digits = 2) => new Intl.NumberFormat(undefined, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(value);

    const round4 = (value) => Math.round((value + Number.EPSILON) * 10000) / 10000;

    const formatMetric = (value) => {
        const absolute = Math.abs(value);
        const digits = absolute > 0 && absolute < 0.01 ? 4 : (absolute > 0 && absolute < 1 ? 3 : 2);
        return format(value, digits);
    };

    const formatDuration = (value) => {
        const rounded = Math.round(value * 100) / 100;
        return Number.isInteger(rounded) ? String(rounded) : String(rounded).replace(/0+$/, '').replace(/\.$/, '');
    };

    const acknowledgeValue = (element, text) => {
        if (!element) return;
        if (element.textContent === text) return;

        element.textContent = text;
        const metric = element.closest('[data-nx15-metric]');
        if (!metric || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        metric.classList.remove('is-updated');
        requestAnimationFrame(() => {
            metric.classList.add('is-updated');
            window.setTimeout(() => metric.classList.remove('is-updated'), 320);
        });
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

    const resetTimeline = () => {
        refs.timeline.forEach((cell) => {
            cell.style.setProperty('--nx15-fill', '0%');
            cell.classList.remove('is-active-window', 'is-window-start', 'is-window-end');
        });
    };

    const updateTimeline = (start, hours) => {
        resetTimeline();
        if (!validStart(start) || !validHours(hours)) return;

        const allocation = Array(24).fill(0);
        let remainingHours = Math.min(Math.max(hours, 0), 24);
        let position = start % 24;
        let safetyCounter = 0;

        // This mirrors LoadAnalysis::calculateHourlyProfile(): each active hour
        // receives connected load × usage factor × the allocated fraction of hour.
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

        refs.timeline.forEach((cell) => {
            const hour = Number.parseInt(cell.dataset.nx15Hour || '0', 10);
            const fraction = allocation[hour] || 0;
            cell.style.setProperty('--nx15-fill', `${Math.round(fraction * 10000) / 100}%`);
            cell.classList.toggle('is-active-window', fraction > 0.000001);
        });

        const startIndex = ((Math.floor(start) % 24) + 24) % 24;
        const endPosition = start + Math.min(hours, 24);
        const endIndex = ((Math.ceil(endPosition) - 1) % 24 + 24) % 24;
        refs.timeline[startIndex]?.classList.add('is-window-start');
        refs.timeline[endIndex]?.classList.add('is-window-end');
    };

    const syncCategoryChips = () => {
        const current = String(refs.category?.value || '').trim().toLowerCase();
        qa('[data-nx15-category-chip]').forEach((chip) => {
            const active = String(chip.dataset.nx15CategoryChip || '').toLowerCase() === current && current !== '';
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const updateNotes = () => {
        if (!refs.noteCount || !refs.notes) return;
        refs.noteCount.textContent = `${refs.notes.value.length.toLocaleString()} / 3000`;
    };

    const updatePreview = () => {
        const name = String(refs.name?.value || '').trim();
        const category = String(refs.category?.value || '').trim();
        const quantity = parse(refs.quantity);
        const power = parse(refs.power);
        const hours = parse(refs.hours);
        const start = parse(refs.start);
        const usage = parse(refs.usage);
        const starting = parse(refs.starting);
        const critical = refs.critical?.value ?? '';

        const connectedReady = validQuantity(quantity) && validPower(power);
        const dailyReady = connectedReady && validHours(hours) && validUsage(usage);
        const startingReady = connectedReady && validStarting(starting);
        const scheduleReady = validStart(start) && validHours(hours);
        const criticalReady = critical === '0' || critical === '1';

        // Mirror LoadAppliance accessors: connected load is rounded to 4 decimals
        // before daily-energy and starting-demand calculations are derived.
        const connected = connectedReady ? round4((quantity * power) / 1000) : null;
        const daily = dailyReady ? round4(connected * hours * usage) : null;
        const startingDemand = startingReady ? round4(connected * starting) : null;

        if (refs.title) refs.title.textContent = name || 'New equipment load';
        if (refs.subtitle) {
            const pieces = [];
            if (category) pieces.push(category);
            if (validQuantity(quantity)) pieces.push(`${quantity.toLocaleString()} unit${quantity === 1 ? '' : 's'}`);
            if (validUsage(usage)) pieces.push(`Usage factor ${usage < 0.01 ? format(usage, 4) : format(usage, 2)}`);
            refs.subtitle.textContent = pieces.length
                ? pieces.join(' · ')
                : 'Complete the required inputs to calculate this unsaved preview.';
        }

        acknowledgeValue(refs.connected, connectedReady ? `${formatMetric(connected)} kW` : '—');
        refs.metricConnected?.classList.toggle('has-value', connectedReady);

        acknowledgeValue(refs.daily, dailyReady ? `${formatMetric(daily)} kWh/day` : '—');
        refs.metricDaily?.classList.toggle('has-value', dailyReady);

        acknowledgeValue(refs.startingDemand, startingReady ? `${formatMetric(startingDemand)} kW` : '—');
        refs.metricStarting?.classList.toggle('has-value', startingReady);

        refs.metricClassification?.classList.remove('has-value', 'is-critical', 'is-standard');
        if (critical === '1') {
            refs.metricClassification?.classList.add('has-value', 'is-critical');
            acknowledgeValue(refs.criticalValue, 'Critical load');
            if (refs.criticalNote) refs.criticalNote.textContent = 'Included in critical-energy summary';
        } else if (critical === '0') {
            refs.metricClassification?.classList.add('has-value', 'is-standard');
            acknowledgeValue(refs.criticalValue, 'Standard load');
            if (refs.criticalNote) refs.criticalNote.textContent = 'Standard demand profile';
        } else {
            acknowledgeValue(refs.criticalValue, '—');
            if (refs.criticalNote) refs.criticalNote.textContent = 'User classification';
        }

        const anyPreviewResult = connectedReady || dailyReady || startingReady || scheduleReady || criticalReady;
        refs.previewCard?.classList.toggle('is-populated', connectedReady);
        refs.previewCard?.classList.toggle('is-modelled', anyPreviewResult);
        refs.previewCard?.classList.toggle('has-connected', connectedReady);
        refs.previewCard?.classList.toggle('has-daily', dailyReady);
        refs.previewCard?.classList.toggle('has-starting', startingReady);
        refs.previewCard?.classList.toggle('has-classification', criticalReady);
        refs.previewCard?.classList.toggle('has-schedule', scheduleReady);
        refs.previewEmpty?.toggleAttribute('hidden', anyPreviewResult);

        if (refs.schedule) {
            refs.schedule.classList.toggle('has-value', scheduleReady);
            if (!scheduleReady) {
                refs.schedule.textContent = 'Awaiting schedule';
            } else if (Math.abs(hours - 24) < 0.000001) {
                refs.schedule.textContent = `24 h from ${clock(start)}`;
            } else {
                const end = start + hours;
                const wrapsMidnight = end > 24;
                refs.schedule.textContent = `${clock(start)}–${clock(end)} · ${formatDuration(hours)} h${wrapsMidnight ? ' · wraps midnight' : ''}`;
            }
        }
        updateTimeline(start, hours);

        const requiredComplete = name !== ''
            && connectedReady
            && dailyReady
            && startingReady
            && scheduleReady
            && criticalReady;

        refs.previewCard?.classList.toggle('is-complete', requiredComplete);

        if (refs.completeness) {
            refs.completeness.classList.toggle('is-complete', requiredComplete);
            const text = refs.completeness.querySelector('span:last-child');
            if (text) {
                text.textContent = requiredComplete
                    ? 'Required inputs complete · server validation still applies'
                    : 'Required inputs are incomplete';
            }
        }

        syncCategoryChips();
        updateNotes();
    };

    qa('[data-nx15-category-chip]').forEach((chip) => {
        chip.addEventListener('click', () => {
            if (!refs.category) return;
            refs.category.value = chip.dataset.nx15CategoryChip || '';
            refs.category.dispatchEvent(new Event('input', { bubbles: true }));
            refs.category.focus({ preventScroll: true });
        });
    });

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);

    form.addEventListener('submit', () => {
        document.body.classList.add('is-nx15-submitting');
        form.querySelectorAll('[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            const label = button.querySelector('[data-nx15-submit-label]');
            if (label) label.textContent = 'Adding Equipment…';
        });
    });

    const firstInvalid = form.querySelector('[aria-invalid="true"]');
    if (firstInvalid) {
        requestAnimationFrame(() => firstInvalid.focus({ preventScroll: false }));
    }

    updatePreview();
})();
