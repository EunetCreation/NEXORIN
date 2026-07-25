(() => {
    'use strict';

    const actionEngine = window.NexorinEngineeringAction;
    const nativeSubmit = HTMLFormElement.prototype.submit;

    const directory = document.querySelector('[data-solar-directory]');

    if (directory) {
        directory.classList.add('is-enhanced');

        const search = directory.querySelector('[data-solar-directory-search]');
        const filterForm = directory.querySelector('[data-solar-directory-filter]');

        document.addEventListener('keydown', (event) => {
            if (event.key === '/' && search && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                event.preventDefault();
                search.focus();
                search.select();
            }
        });

        filterForm?.addEventListener('submit', (event) => {
            if (!actionEngine || filterForm.dataset.solarSubmitting === 'true') return;
            event.preventDefault();
            filterForm.dataset.solarSubmitting = 'true';
            const { duration } = actionEngine.show({
                kind: 'filter',
                icon: 'filter',
                title: 'Refreshing Solar Resource Registry',
                copy: 'The visible project register is being rebuilt from the selected location and dataset filters.',
                steps: [
                    'Reading the selected registry filters',
                    'Querying accessible solar-resource datasets',
                    'Resolving coverage and verification summaries',
                    'Rendering the updated Solar Resource registry',
                ],
                duration: 760,
                signal: 'NEXORIN solar engine',
                accent: '#3978d8',
                secondary: '#f3a04f',
                metaRight: 'Resource context preserved',
            });
            window.setTimeout(() => nativeSubmit.call(filterForm), duration);
        });

        directory.querySelectorAll('.solar-open-button').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (!actionEngine) return;
                actionEngine.navigate(event, link, {
                    kind: 'open',
                    icon: 'solar',
                    title: 'Opening Location & Solar',
                    copy: 'Coordinates, source traceability, monthly irradiance, and verification context are being prepared.',
                    steps: [
                        'Retaining project access and context',
                        'Loading coordinates and source identity',
                        'Resolving the twelve-month resource profile',
                        'Preparing the Location & Solar workspace',
                    ],
                    duration: 820,
                    signal: 'NEXORIN solar engine',
                    accent: '#3978d8',
                    secondary: '#f3a04f',
                    metaRight: 'Resource context preserved',
                });
            });
        });
    }

    const root = document.querySelector('[data-solar-workspace]');
    const form = document.querySelector('[data-solar-form]');

    if (!root || !form) {
        return;
    }

    root.classList.add('is-enhanced');

    const monthNames = [
        '', 'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];
    const monthDays = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const sourceLabels = {
        manual: 'Manual Input',
        reference: 'Reference Dataset',
        api: 'API Import',
    };
    const chartDefinitions = {
        ghi: {
            eyebrow: 'Global horizontal irradiation',
            title: 'Monthly GHI profile',
            unit: 'kWh/m²/day',
            averageUnit: 'kWh/m²/day',
            digits: 2,
        },
        psh: {
            eyebrow: 'Equivalent peak solar resource',
            title: 'Monthly peak sun hours',
            unit: 'hours/day',
            averageUnit: 'PSH/day',
            digits: 2,
        },
        temperature: {
            eyebrow: 'Ambient climate condition',
            title: 'Monthly temperature profile',
            unit: '°C',
            averageUnit: '°C average',
            digits: 1,
        },
    };

    const canManage = root.dataset.canManage === 'true';
    const initialStatus = root.dataset.initialStatus || 'incomplete';
    const hasServerErrors = root.dataset.serverErrors === 'true';
    const submitButtons = [...form.querySelectorAll('[data-solar-submit]')];
    const submitLabels = [...form.querySelectorAll('[data-solar-submit-label]')];
    const verifyButton = root.querySelector('[data-solar-verify]');
    const verifyForm = document.getElementById('solar-verify-form');
    const latitude = form.querySelector('#latitude');
    const longitude = form.querySelector('#longitude');
    const sourceName = form.querySelector('#source_name');
    const sourceOptions = [...form.querySelectorAll('[data-solar-source-option]')];
    const locationButton = root.querySelector('[data-solar-use-location]');
    const locationButtonLabel = root.querySelector('[data-solar-location-button-label]');
    const chart = root.querySelector('[data-solar-chart]');
    const chartMetricButtons = [...root.querySelectorAll('[data-solar-chart-metric]')];
    const monthRows = [...form.querySelectorAll('[data-solar-month-row]')];
    const chartCanvas = root.querySelector('[data-solar-chart-canvas]');
    const chartFallback = root.querySelector('[data-solar-chart-fallback]');
    let activeChartMetric = 'ghi';
    let solarChart = null;
    let isSubmitting = false;
    let isDirty = false;

    const showTransition = (kind = 'save', title = 'Saving Solar Resource Dataset', copy = 'Synchronizing the latest location and irradiance inputs.') => {
        if (!actionEngine) return { duration: 0 };

        const verifying = kind === 'verify';
        return actionEngine.show({
            kind,
            icon: verifying ? 'verify' : 'save',
            title,
            copy,
            steps: verifying
                ? [
                    'Locking the saved source snapshot',
                    'Validating coordinates and source traceability',
                    'Checking all twelve monthly resource records',
                    'Applying the engineering verification gate',
                    'Recording the verification milestone',
                ]
                : [
                    'Capturing edited coordinates and source identity',
                    'Validating monthly irradiance and temperature values',
                    'Refreshing annual resource indicators',
                    'Updating readiness and warnings',
                    'Writing the controlled resource milestone',
                ],
            duration: verifying ? 1040 : 940,
            signal: 'NEXORIN solar engine',
            accent: verifying ? '#7659ca' : '#13aaa2',
            secondary: '#f3a04f',
            metaRight: 'Resource trace preserved',
        });
    };


    const numericValue = (input) => {
        if (!input || String(input.value).trim() === '') {
            return null;
        }

        const value = Number(input.value);
        return Number.isFinite(value) ? value : null;
    };

    const formatNumber = (value, digits = 0) => {
        if (!Number.isFinite(value)) {
            return '0';
        }

        return value.toLocaleString(undefined, {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        });
    };

    const trackedNames = () => [...new Set(
        [...form.querySelectorAll('[data-solar-field]')]
            .filter((field) => field.name && !field.disabled)
            .map((field) => field.name)
    )];

    const getFieldValue = (name) => {
        const fields = [...form.elements].filter((field) => field.name === name && !field.disabled);

        if (!fields.length) {
            return '';
        }

        if (fields[0].type === 'radio') {
            return fields.find((field) => field.checked)?.value || '';
        }

        if (fields[0].type === 'checkbox') {
            return fields.filter((field) => field.checked).map((field) => field.value).sort().join('|');
        }

        return String(fields[0].value ?? '').trim();
    };

    const initialSnapshot = new Map(trackedNames().map((name) => [name, getFieldValue(name)]));

    const changedNames = () => trackedNames().filter((name) => getFieldValue(name) !== (initialSnapshot.get(name) ?? ''));

    const monthlyData = () => monthRows.map((row) => {
        const month = Number(row.dataset.solarMonthRow);
        return {
            month,
            days: monthDays[month] || 30,
            row,
            ghi: numericValue(row.querySelector('[data-solar-month-input="ghi"]')),
            poa: numericValue(row.querySelector('[data-solar-month-input="poa"]')),
            psh: numericValue(row.querySelector('[data-solar-month-input="psh"]')),
            temperature: numericValue(row.querySelector('[data-solar-month-input="temperature"]')),
        };
    });

    const isMonthComplete = (item) => item.ghi !== null && item.psh !== null && item.temperature !== null;

    const calculateState = () => {
        const rows = monthlyData();
        const completeRows = rows.filter(isMonthComplete);
        const completeMonths = completeRows.length;
        const totalDays = completeRows.reduce((total, row) => total + row.days, 0);
        const annualGhi = completeRows.reduce((total, row) => total + (row.ghi * row.days), 0);
        const averagePsh = totalDays > 0
            ? completeRows.reduce((total, row) => total + (row.psh * row.days), 0) / totalDays
            : 0;
        const averageTemperature = totalDays > 0
            ? completeRows.reduce((total, row) => total + (row.temperature * row.days), 0) / totalDays
            : 0;
        const averageGhi = totalDays > 0
            ? completeRows.reduce((total, row) => total + (row.ghi * row.days), 0) / totalDays
            : 0;
        const coordinatesComplete = numericValue(latitude) !== null && numericValue(longitude) !== null;
        const sourceComplete = Boolean(sourceName?.value.trim());
        const readinessPoints = (numericValue(latitude) !== null ? 1 : 0)
            + (numericValue(longitude) !== null ? 1 : 0)
            + (sourceComplete ? 1 : 0)
            + completeMonths;
        const readiness = (readinessPoints / 15) * 100;
        const readyForVerification = completeMonths === 12 && coordinatesComplete && sourceComplete;
        const potential = averagePsh >= 4.5
            ? 'Strong'
            : averagePsh >= 3.5
                ? 'Moderate'
                : averagePsh > 0
                    ? 'Limited'
                    : 'Not Available';

        return {
            rows,
            completeRows,
            completeMonths,
            annualGhi,
            averageGhi,
            averagePsh,
            averageTemperature,
            coordinatesComplete,
            sourceComplete,
            readiness,
            readyForVerification,
            potential,
        };
    };

    const setText = (selector, value) => {
        root.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    };

    const selectedSource = () => sourceOptions.find((option) => option.checked)?.value || 'manual';

    const updateSourceOptions = () => {
        const source = selectedSource();
        sourceOptions.forEach((option) => {
            option.closest('label')?.classList.toggle('is-selected', option.checked);
        });

        setText('[data-solar-summary="source"]', sourceLabels[source] || 'Manual Input');
        setText('[data-solar-rail-source]', sourceLabels[source] || 'Manual Input');
    };

    const updateLocationState = (state) => {
        const status = root.querySelector('[data-solar-location-status]');
        const label = root.querySelector('[data-solar-location-label]');
        const copy = root.querySelector('[data-solar-location-copy]');

        status?.classList.toggle('is-missing', !state.coordinatesComplete);
        if (label) {
            label.textContent = state.coordinatesComplete ? 'Coordinates ready' : 'Coordinates required';
        }
        if (copy) {
            copy.textContent = state.coordinatesComplete
                ? 'Available for solar and map workflows.'
                : 'Add coordinates before verification.';
        }

        const city = form.querySelector('#city')?.value.trim();
        const province = form.querySelector('#province')?.value.trim();
        setText('[data-solar-summary="site"]', [city, province].filter(Boolean).join(', ') || 'Location not set');
    };

    const updateMonthRows = (state) => {
        state.rows.forEach((item) => {
            const values = [item.ghi, item.psh, item.temperature];
            const populated = values.filter((value) => value !== null).length;
            const rowState = populated === 3 ? 'complete' : populated > 0 ? 'partial' : 'empty';
            const badge = item.row.querySelector('[data-solar-row-state]');

            item.row.classList.remove('is-complete', 'is-partial', 'is-empty');
            item.row.classList.add(`is-${rowState}`);
            if (badge) {
                badge.classList.remove('is-complete', 'is-partial', 'is-empty');
                badge.classList.add(`is-${rowState}`);
                const text = badge.querySelector('b');
                if (text) {
                    text.textContent = rowState.charAt(0).toUpperCase() + rowState.slice(1);
                }
            }
        });

        const monthlyState = root.querySelector('[data-solar-monthly-state]');
        if (monthlyState) {
            monthlyState.textContent = `${state.completeMonths}/12 complete`;
            monthlyState.classList.toggle('is-review', state.completeMonths !== 12);
        }

        const profileState = root.querySelector('[data-solar-section-state="profile"]');
        if (profileState) {
            profileState.textContent = state.completeMonths === 12 ? '12 months ready' : `${state.completeMonths} of 12 months`;
            profileState.classList.toggle('is-review', state.completeMonths !== 12);
        }
    };

    const updateFoundationState = (state) => {
        const foundationState = root.querySelector('[data-solar-section-state="foundation"]');
        const complete = state.coordinatesComplete && state.sourceComplete;
        if (foundationState) {
            foundationState.textContent = complete ? 'Ready' : 'Needs input';
            foundationState.classList.toggle('is-review', !complete);
        }
    };

    const updateChecklist = (state) => {
        const checks = {
            coordinates: state.coordinatesComplete,
            source: state.sourceComplete,
            months: state.completeMonths === 12,
        };

        Object.entries(checks).forEach(([key, complete]) => {
            root.querySelector(`[data-solar-check="${key}"]`)?.classList.toggle('is-complete', complete);
        });

        setText('[data-solar-month-check-copy]', `${state.completeMonths}/12 required records`);
    };

    const updateReadiness = (state) => {
        const readiness = Math.max(0, Math.min(100, state.readiness));
        root.querySelectorAll('[data-solar-readiness-ring]').forEach((ring) => {
            ring.style.setProperty('--solar-readiness', readiness.toFixed(1));
        });
        setText('[data-solar-readiness-value]', `${formatNumber(readiness, 0)}%`);
        root.querySelectorAll('[data-solar-progress-bar]').forEach((bar) => {
            bar.style.width = `${readiness}%`;
        });

        setText('[data-solar-kpi="psh"]', formatNumber(state.averagePsh, 2));
        setText('[data-solar-kpi="ghi"]', formatNumber(state.annualGhi, 0));
        setText('[data-solar-kpi="temperature"]', formatNumber(state.averageTemperature, 1));
        setText('[data-solar-kpi="coverage"]', `${formatNumber(readiness, 0)}%`);
        setText('[data-solar-kpi-months]', `${state.completeMonths}/12`);
        setText('[data-solar-potential]', `${state.potential} solar potential`);
        setText('[data-solar-rail-potential]', state.potential);
    };

    const updateVerification = (state) => {
        const verified = initialStatus === 'verified' && !isDirty;
        const ready = state.readyForVerification;
        const title = root.querySelector('[data-solar-verification-title]');
        const copy = root.querySelector('[data-solar-verification-copy]');
        const commandTitle = root.querySelector('[data-solar-command-title]');
        const commandCopy = root.querySelector('[data-solar-command-copy]');
        const navState = root.querySelector('[data-solar-nav-state]');
        const heroState = root.querySelector('[data-solar-hero-state]');
        const statusSummary = root.querySelector('[data-solar-summary="status"]');
        const railVerification = root.querySelector('[data-solar-rail-verification]');

        let label = 'Dataset in progress';

        if (verified) {
            label = 'Verified dataset';
            if (title) title.textContent = 'Engineering dataset verified';
            if (copy) copy.textContent = 'This saved dataset has completed the engineering verification gate.';
            if (commandTitle) commandTitle.textContent = 'Engineering dataset verified';
            if (commandCopy) commandCopy.textContent = 'The saved resource foundation is verified and traceable.';
            if (navState) navState.textContent = 'Verified';
            if (railVerification) railVerification.textContent = 'Verified';
        } else if (ready && !isDirty) {
            label = 'Ready to verify';
            if (title) title.textContent = 'Ready for engineering review';
            if (copy) copy.textContent = 'The saved dataset satisfies all completeness requirements for verification.';
            if (commandTitle) commandTitle.textContent = 'Engineering verification available';
            if (commandCopy) commandCopy.textContent = 'Coordinates, source identity, and all required monthly values are available.';
            if (navState) navState.textContent = 'Ready to verify';
            if (railVerification) railVerification.textContent = 'Pending review';
        } else if (ready && isDirty) {
            label = initialStatus === 'verified' ? 'Verified · changes pending' : 'Ready · save changes';
            if (title) title.textContent = 'Save changes before verification';
            if (copy) copy.textContent = 'The current form is complete, but verification can only use the saved dataset.';
            if (commandTitle) commandTitle.textContent = 'Resource foundation complete';
            if (commandCopy) commandCopy.textContent = 'Save the pending changes to update the engineering verification gate.';
            if (navState) navState.textContent = 'Save to verify';
            if (railVerification) railVerification.textContent = 'Pending save';
        } else {
            label = state.completeMonths > 0 ? 'Dataset in progress' : 'Dataset setup required';
            if (title) title.textContent = 'Verification requirements pending';
            if (copy) copy.textContent = 'Complete coordinates, source name, and all 12 required monthly records.';
            if (commandTitle) commandTitle.textContent = 'Complete the resource foundation';
            if (commandCopy) commandCopy.textContent = 'Readiness updates automatically as location, source, and monthly values are completed.';
            if (navState) navState.textContent = 'In progress';
            if (railVerification) railVerification.textContent = 'Pending';
        }

        if (statusSummary) statusSummary.textContent = label;
        if (heroState) {
            heroState.textContent = label;
            heroState.classList.toggle('is-verified', verified);
            heroState.classList.toggle('is-incomplete', !ready);
        }

        if (verifyButton) {
            verifyButton.disabled = !ready || isDirty;
            verifyButton.title = isDirty
                ? 'Save pending changes before verification.'
                : !ready
                    ? 'Complete verification requirements first.'
                    : 'Verify the saved dataset.';
        }
    };

    const metricValues = (state, metric) => state.rows
        .map((row) => ({ month: row.month, days: row.days, value: row[metric] }))
        .filter((item) => item.value !== null);

    const weightedAverage = (values) => {
        const totalDays = values.reduce((total, item) => total + item.days, 0);
        return totalDays > 0
            ? values.reduce((total, item) => total + item.value * item.days, 0) / totalDays
            : 0;
    };

    const chartTheme = () => {
        const dark = document.documentElement.dataset.theme === 'dark';
        return {
            dark,
            text: dark ? '#dce9ee' : '#2a4054',
            muted: dark ? '#8ca5b1' : '#73859a',
            line: dark ? 'rgba(142, 177, 190, .18)' : 'rgba(124, 154, 171, .20)',
            tooltip: 'rgba(4, 31, 43, .97)',
        };
    };

    const chartPalette = (metric) => metric === 'temperature'
        ? { top: '#f4a156', bottom: '#d66b3d', line: '#f4b46d' }
        : metric === 'psh'
            ? { top: '#7659ca', bottom: '#4f71d8', line: '#9b82e0' }
            : { top: '#20c0b6', bottom: '#2e78d4', line: '#66ded6' };

    const renderEChart = (state, definition, values) => {
        if (!chartCanvas || !window.echarts) {
            if (chartFallback) chartFallback.hidden = false;
            return;
        }
        if (chartFallback) chartFallback.hidden = true;
        if (!solarChart) solarChart = window.echarts.init(chartCanvas, null, { renderer: 'svg', useDirtyRect: false });

        const theme = chartTheme();
        const palette = chartPalette(activeChartMetric);
        const data = state.rows.map((row) => row[activeChartMetric] === null ? null : Number(row[activeChartMetric]));
        const available = data.filter((value) => Number.isFinite(value));
        const average = available.length ? available.reduce((total, value) => total + value, 0) / available.length : 0;
        const minimum = activeChartMetric === 'temperature' && available.length ? Math.min(0, Math.floor(Math.min(...available) - 2)) : 0;

        solarChart.setOption({
            animationDuration: 760,
            animationEasing: 'cubicOut',
            grid: { left: 58, right: 24, top: 34, bottom: 46 },
            tooltip: {
                trigger: 'axis',
                backgroundColor: theme.tooltip,
                borderColor: 'rgba(98, 221, 212, .24)',
                borderWidth: 1,
                padding: [11, 13],
                textStyle: { color: '#eef8fa', fontFamily: 'Inter', fontSize: 12 },
                axisPointer: { type: 'shadow', shadowStyle: { color: theme.dark ? 'rgba(255,255,255,.035)' : 'rgba(19,170,162,.045)' } },
                formatter: (params) => {
                    const point = params?.[0];
                    const value = point?.value;
                    const rendered = Number.isFinite(value) ? Number(value).toFixed(definition.digits) : 'Not available';
                    return `<div style="font-family:Inter;min-width:150px"><div style="font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#83dcd5;margin-bottom:6px">${definition.eyebrow}</div><strong style="font-size:14px">${point?.axisValue || ''}</strong><div style="margin-top:5px;color:#c4d7dd"><span style="color:#65d8cf">●</span> ${rendered}${Number.isFinite(value) ? ` ${definition.unit}` : ''}</div></div>`;
                },
            },
            xAxis: {
                type: 'category',
                data: monthNames.slice(1).map((month) => month.slice(0, 3)),
                axisLine: { lineStyle: { color: theme.line } },
                axisTick: { show: false },
                axisLabel: { color: theme.muted, fontFamily: 'JetBrains Mono', fontSize: 10, margin: 14 },
            },
            yAxis: {
                type: 'value', min: minimum, splitNumber: 4, name: definition.unit,
                nameTextStyle: { color: theme.muted, fontFamily: 'Inter', fontSize: 10, padding: [0, 0, 8, 0] },
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: theme.muted, fontFamily: 'JetBrains Mono', fontSize: 10, formatter: (value) => Number(value).toFixed(activeChartMetric === 'temperature' ? 0 : 1) },
                splitLine: { lineStyle: { color: theme.line, type: 'dashed' } },
            },
            series: [{
                name: definition.title, type: 'bar', data, barMaxWidth: 28, showBackground: true,
                backgroundStyle: { color: theme.dark ? 'rgba(255,255,255,.035)' : 'rgba(121,154,171,.075)', borderRadius: 8 },
                itemStyle: { borderRadius: [8, 8, 3, 3], color: new window.echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: palette.top }, { offset: 1, color: palette.bottom }]) },
                emphasis: { focus: 'series', itemStyle: { shadowBlur: 16, shadowColor: `${palette.top}55` } },
                markLine: available.length ? {
                    silent: true, symbol: 'none',
                    label: { formatter: `Average ${average.toFixed(definition.digits)}`, color: theme.muted, fontFamily: 'JetBrains Mono', fontSize: 9, position: 'insideEndTop' },
                    lineStyle: { color: palette.line, width: 1.2, type: 'dashed', opacity: .8 }, data: [{ yAxis: average }],
                } : undefined,
            }],
        }, true);
        chartCanvas.setAttribute('aria-label', `${definition.title}. ${values.length} of 12 months contain values. Exact values are available in the monthly registry below.`);
    };

    const updateChart = (state) => {
        if (!chart) return;
        const definition = chartDefinitions[activeChartMetric];
        const values = metricValues(state, activeChartMetric);
        chart.dataset.chartMetric = activeChartMetric;
        setText('[data-solar-chart-eyebrow]', definition.eyebrow);
        setText('[data-solar-chart-title]', definition.title);
        setText('[data-solar-chart-unit]', definition.unit);
        renderEChart(state, definition, values);

        const average = weightedAverage(values);
        const peak = values.length ? values.reduce((highest, item) => item.value > highest.value ? item : highest) : null;
        const low = values.length ? values.reduce((lowest, item) => item.value < lowest.value ? item : lowest) : null;
        const spread = peak && low && peak.value !== 0 ? ((peak.value - low.value) / Math.abs(peak.value)) * 100 : 0;
        setText('[data-solar-chart-average]', formatNumber(average, definition.digits));
        setText('[data-solar-chart-average-unit]', definition.averageUnit);
        setText('[data-solar-chart-peak]', peak ? monthNames[peak.month] : 'Not available');
        setText('[data-solar-chart-peak-value]', peak ? `${formatNumber(peak.value, definition.digits)} ${definition.unit}` : 'Complete monthly values');
        setText('[data-solar-chart-low]', low ? monthNames[low.month] : 'Not available');
        setText('[data-solar-chart-low-value]', low ? `${formatNumber(low.value, definition.digits)} ${definition.unit}` : 'Complete monthly values');
        setText('[data-solar-chart-spread]', `${formatNumber(spread, 0)}%`);
    };

    const updateDirtyState = () => {
        const changed = changedNames();
        isDirty = changed.length > 0;
        root.classList.toggle('is-dirty', isDirty);

        const label = isDirty
            ? `${changed.length} field${changed.length === 1 ? '' : 's'} changed`
            : hasServerErrors
                ? 'Review required'
                : 'Saved state';
        const count = isDirty
            ? `${changed.length} change${changed.length === 1 ? '' : 's'}`
            : '0 changes';

        setText('[data-solar-change-label]', label);
        setText('[data-solar-change-count]', count);
        setText('[data-solar-mobile-state]', isDirty ? 'Unsaved changes' : 'Saved state');
        setText('[data-solar-mobile-count]', count);
        submitButtons.forEach((button) => {
            button.disabled = !isDirty || isSubmitting;
        });
        submitLabels.forEach((labelNode) => {
            labelNode.textContent = isSubmitting ? 'Saving…' : isDirty ? 'Save changes' : 'Saved';
        });
    };

    const updateAll = () => {
        updateDirtyState();
        updateSourceOptions();
        const state = calculateState();
        updateLocationState(state);
        updateMonthRows(state);
        updateFoundationState(state);
        updateChecklist(state);
        updateReadiness(state);
        updateVerification(state);
        updateChart(state);
    };

    chartMetricButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeChartMetric = button.dataset.solarChartMetric || 'ghi';
            chartMetricButtons.forEach((item) => item.classList.toggle('is-active', item === button));
            updateChart(calculateState());
        });
    });


    root.querySelectorAll('[data-solar-source-launch]').forEach((button) => {
        button.addEventListener('click', () => {
            const option = form.querySelector(`[data-solar-source-option][value="${button.dataset.solarSourceLaunch}"]`);
            if (option && !option.disabled) {
                option.checked = true;
                option.dispatchEvent(new Event('change', { bubbles: true }));
            }
            root.querySelector('[data-solar-source-panel]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    root.querySelectorAll('[data-solar-autosize]').forEach((textarea) => {
        const resize = () => {
            textarea.style.height = 'auto';
            textarea.style.height = `${Math.min(Math.max(textarea.scrollHeight, 86), 220)}px`;
        };
        textarea.addEventListener('input', resize);
        resize();
    });

    root.querySelectorAll('[data-solar-character-count]').forEach((counter) => {
        const input = form.querySelector(`#${counter.dataset.solarCharacterCount}`);
        if (!input) return;
        const update = () => { counter.textContent = String(input.value.length); };
        input.addEventListener('input', update);
        update();
    });

    if (locationButton && latitude && longitude) {
        locationButton.addEventListener('click', () => {
            if (!navigator.geolocation) {
                if (locationButtonLabel) locationButtonLabel.textContent = 'Geolocation unavailable';
                return;
            }

            locationButton.classList.add('is-loading');
            if (locationButtonLabel) locationButtonLabel.textContent = 'Locating…';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    latitude.value = position.coords.latitude.toFixed(7);
                    longitude.value = position.coords.longitude.toFixed(7);
                    latitude.dispatchEvent(new Event('input', { bubbles: true }));
                    longitude.dispatchEvent(new Event('input', { bubbles: true }));
                    locationButton.classList.remove('is-loading');
                    if (locationButtonLabel) locationButtonLabel.textContent = 'Location captured';
                    window.setTimeout(() => {
                        if (locationButtonLabel) locationButtonLabel.textContent = 'Use current location';
                    }, 1800);
                },
                () => {
                    locationButton.classList.remove('is-loading');
                    if (locationButtonLabel) locationButtonLabel.textContent = 'Location permission denied';
                    window.setTimeout(() => {
                        if (locationButtonLabel) locationButtonLabel.textContent = 'Use current location';
                    }, 2200);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
            );
        });
    }

    form.addEventListener('input', updateAll);
    form.addEventListener('change', updateAll);

    form.addEventListener('submit', (event) => {
        if (form.dataset.solarSubmitting === 'true') return;

        event.preventDefault();
        form.dataset.solarSubmitting = 'true';
        isSubmitting = true;
        submitButtons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
        submitLabels.forEach((label) => { label.textContent = 'Saving…'; });
        const { duration } = showTransition(
            'save',
            'Saving solar resource dataset',
            'Synchronizing coordinates, source identity, monthly resource values, readiness indicators, and audit context.'
        );
        window.setTimeout(() => nativeSubmit.call(form), duration);
    });

    verifyForm?.addEventListener('submit', (event) => {
        if (verifyForm.dataset.solarSubmitting === 'true') return;

        event.preventDefault();
        verifyForm.dataset.solarSubmitting = 'true';
        if (verifyButton) {
            verifyButton.disabled = true;
            verifyButton.setAttribute('aria-busy', 'true');
            const textNode = Array.from(verifyButton.childNodes).find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
            if (textNode) textNode.textContent = 'Verifying… ';
        }
        const { duration } = showTransition(
            'verify',
            'Verifying solar dataset',
            'Locking the saved solar-resource foundation and recording the controlled engineering verification event.'
        );
        window.setTimeout(() => nativeSubmit.call(verifyForm), duration);
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            if (canManage && isDirty && !isSubmitting) {
                form.requestSubmit();
            }
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (isDirty && !isSubmitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    const navLinks = [...root.querySelectorAll('[data-solar-section-link]')];
    const sections = [...root.querySelectorAll('[data-solar-section]')];

    if ('IntersectionObserver' in window && navLinks.length && sections.length) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

            if (!visible) return;
            navLinks.forEach((link) => {
                link.classList.toggle('is-active', link.dataset.solarSectionLink === visible.target.id);
            });
        }, {
            rootMargin: '-20% 0px -62% 0px',
            threshold: [0.08, 0.25, 0.5],
        });

        sections.forEach((section) => observer.observe(section));
    }

    const resizeSolarChart = () => solarChart?.resize();
    window.addEventListener('resize', resizeSolarChart, { passive: true });
    if ('ResizeObserver' in window && chartCanvas) new ResizeObserver(resizeSolarChart).observe(chartCanvas);
    new MutationObserver(() => { if (solarChart) updateChart(calculateState()); })
        .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    updateAll();
})();
