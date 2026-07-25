(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    ready(() => {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const root = document.documentElement;
        const actionEngine = window.NexorinEngineeringAction;
        const nativeSubmit = HTMLFormElement.prototype.submit;
        const chartRegistry = new Map();
        const inspectorHour = document.querySelector('[data-nxla-inspector-hour]');
        const inspectorEnergy = document.querySelector('[data-nxla-inspector-energy]');
        const inspectorCritical = document.querySelector('[data-nxla-inspector-critical]');
        const inspectorPeriod = document.querySelector('[data-nxla-inspector-period]');
        const inspectorAction = document.querySelector('[data-nxla-view-hour-equipment]');
        let selectedChartHour = Number.parseInt(inspectorAction?.dataset.hour || '0', 10) || 0;
        let persistedChartHour = null;

        const directory = document.querySelector('.nxla-directory');
        if (directory && actionEngine) {
            const filterForm = directory.querySelector('.nxla-filter-bar');
            filterForm?.addEventListener('submit', (event) => {
                if (filterForm.dataset.nxlaSubmitting === 'true') return;
                event.preventDefault();
                filterForm.dataset.nxlaSubmitting = 'true';
                const { duration } = actionEngine.show({
                    kind: 'filter',
                    icon: 'filter',
                    title: 'Refreshing Load Analysis Portfolio',
                    copy: 'The visible project register is being rebuilt from the selected demand-profile filters.',
                    steps: [
                        'Reading the selected portfolio filters',
                        'Querying accessible load-analysis records',
                        'Resolving readiness and validation summaries',
                        'Rendering the updated Load Analysis portfolio',
                    ],
                    duration: 760,
                    signal: 'NEXORIN load engine',
                    accent: '#3978d8',
                    secondary: '#f3a04f',
                    metaRight: 'Portfolio context preserved',
                });
                window.setTimeout(() => nativeSubmit.call(filterForm), duration);
            });

            directory.querySelectorAll('.nxla-registry-row, .nxla-stat-card[href], .nxla-context-link').forEach((link) => {
                link.addEventListener('click', (event) => {
                    actionEngine.navigate(event, link, {
                        kind: 'open',
                        icon: link.classList.contains('nxla-registry-row') ? 'load' : 'portfolio',
                        title: link.classList.contains('nxla-registry-row') ? 'Opening Load Analysis' : 'Opening Engineering Register',
                        copy: 'Project demand intelligence, equipment schedules, and validation context are being prepared.',
                        steps: [
                            'Retaining project access and context',
                            'Loading demand, equipment, and bill intelligence',
                            'Resolving readiness and validation signals',
                            'Preparing the Load Analysis workspace',
                        ],
                        duration: 820,
                        signal: 'NEXORIN load engine',
                        accent: '#3978d8',
                        secondary: '#f3a04f',
                        metaRight: 'Demand context preserved',
                    });
                });
            });
        }

        const isDarkTheme = () => root.dataset.theme === 'dark';

        const readChartData = (node) => {
            const sourceId = node?.dataset.source;
            const source = sourceId ? document.getElementById(sourceId) : null;
            if (!source) return null;

            try {
                return JSON.parse(source.textContent || '{}');
            } catch (error) {
                console.error(`Unable to parse chart data from #${sourceId}.`, error);
                return null;
            }
        };

        const chartPalette = () => isDarkTheme()
            ? {
                text: '#d9e8ef', muted: '#8ea7b5', grid: 'rgba(135, 168, 183, .16)',
                axis: 'rgba(143, 173, 187, .28)', tooltip: 'rgba(5, 22, 32, .98)',
                teal: '#2dd8cb', tealDeep: '#0d8f8a', tealSoft: 'rgba(45, 216, 203, .10)',
                blue: '#6ea2e7', amber: '#f0b94f', violet: '#9d83e4', slate: '#7893a4',
            }
            : {
                text: '#173047', muted: '#74869b', grid: 'rgba(148, 167, 181, .18)',
                axis: 'rgba(135, 155, 169, .30)', tooltip: 'rgba(7, 32, 46, .98)',
                teal: '#18bdb3', tealDeep: '#0b8c87', tealSoft: 'rgba(24, 189, 179, .08)',
                blue: '#5b8fd0', amber: '#e7a52f', violet: '#8266cf', slate: '#829bad',
            };

        const gradient = (top, bottom) => new window.echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: top },
            { offset: 1, color: bottom },
        ]);

        const tooltipMarkup = ({ eyebrow, title, rows, badge }) => `
            <div class="nxla-chart-tooltip">
                <div class="nxla-chart-tooltip-head">
                    <div><span>${eyebrow}</span><strong>${title}</strong></div>
                    ${badge ? `<em>${badge}</em>` : ''}
                </div>
                <div class="nxla-chart-tooltip-grid">
                    ${rows.map((row) => `<div><span><i style="--tooltip-color:${row.color}"></i>${row.label}</span><strong>${row.value}</strong></div>`).join('')}
                </div>
            </div>`;

        const setProfileInspectorFromRow = (row, persist = false) => {
            if (!row) return;
            const hour = Number.parseInt(row.hour || '0', 10) || 0;
            selectedChartHour = hour;
            if (persist) persistedChartHour = hour;

            if (inspectorHour) inspectorHour.textContent = row.window || '—';
            if (inspectorEnergy) inspectorEnergy.textContent = `${Number(row.energy || 0).toFixed(3)} kWh`;
            if (inspectorCritical) inspectorCritical.textContent = `${Number(row.critical || 0).toFixed(3)} kWh`;
            if (inspectorPeriod) inspectorPeriod.textContent = `${row.period || '—'}${row.isPeak ? ` · ${row.peakLabel || 'Peak'}` : ''}`;
            if (inspectorAction) inspectorAction.dataset.hour = String(hour);
        };

        const makeHourlyOption = (data) => {
            const palette = chartPalette();
            const rows = Array.isArray(data.rows) ? data.rows : [];
            const labels = rows.map((row) => row.hour);
            const dayAreas = [];
            const start = Number(data.dayStart);
            const end = Number(data.dayEnd);
            const labelFor = (hour) => String(((hour % 24) + 24) % 24).padStart(2, '0');
            const range = (from, to, color) => [{ xAxis: labelFor(from), itemStyle: { color } }, { xAxis: labelFor(to) }];

            if (Number.isFinite(start) && Number.isFinite(end) && start !== end) {
                if (start < end) dayAreas.push(range(start, Math.max(start, end - 1), palette.tealSoft));
                else {
                    dayAreas.push(range(start, 23, palette.tealSoft));
                    dayAreas.push(range(0, Math.max(0, end - 1), palette.tealSoft));
                }
            }
            if (Number.isFinite(data.peakStart) && Number.isFinite(data.peakEnd)) {
                dayAreas.push(range(data.peakStart, data.peakEnd, isDarkTheme() ? 'rgba(240,185,79,.09)' : 'rgba(231,165,47,.08)'));
            }

            return {
                animation: !reducedMotion,
                animationDuration: 760,
                animationEasing: 'cubicOut',
                grid: { top: 28, right: 18, bottom: 42, left: 52, containLabel: false },
                tooltip: {
                    trigger: 'axis',
                    confine: true,
                    backgroundColor: 'transparent',
                    borderWidth: 0,
                    padding: 0,
                    axisPointer: { type: 'shadow', shadowStyle: { color: isDarkTheme() ? 'rgba(61, 219, 206, .08)' : 'rgba(15, 174, 167, .07)' } },
                    formatter: (params) => {
                        const index = params?.[0]?.dataIndex ?? 0;
                        const row = rows[index] || {};
                        return tooltipMarkup({
                            eyebrow: row.period || 'Demand interval',
                            title: row.window || row.hour || '—',
                            badge: row.isPeak ? (row.peakLabel || 'Peak') : '',
                            rows: [
                                { label: 'Modeled demand', value: `${Number(row.energy || 0).toFixed(3)} kWh`, color: palette.teal },
                                { label: 'Critical demand', value: `${Number(row.critical || 0).toFixed(3)} kWh`, color: palette.amber },
                            ],
                        });
                    },
                },
                xAxis: {
                    type: 'category',
                    data: labels,
                    boundaryGap: true,
                    axisTick: { show: false },
                    axisLine: { lineStyle: { color: palette.axis } },
                    axisLabel: { color: palette.muted, fontFamily: '"JetBrains Mono", monospace', fontSize: 10, margin: 13, interval: 0 },
                },
                yAxis: {
                    type: 'value',
                    min: 0,
                    max: Number(data.maximum) > 0 ? Number(data.maximum) : null,
                    splitNumber: 4,
                    name: 'kWh',
                    nameGap: 12,
                    nameTextStyle: { color: palette.muted, fontFamily: '"JetBrains Mono", monospace', fontSize: 10, fontWeight: 700, align: 'right' },
                    axisTick: { show: false },
                    axisLine: { show: false },
                    axisLabel: { color: palette.muted, fontFamily: '"JetBrains Mono", monospace', fontSize: 10, formatter: (value) => Number(value).toFixed(value >= 10 ? 0 : 2) },
                    splitLine: { lineStyle: { color: palette.grid, type: 'dashed' } },
                },
                series: [
                    {
                        name: 'Standard demand',
                        type: 'bar',
                        stack: 'demand',
                        barWidth: '54%',
                        data: rows.map((row) => Number(row.standard || 0)),
                        itemStyle: {
                            color: gradient(isDarkTheme() ? '#35dfd2' : '#25cfc3', isDarkTheme() ? '#0f978f' : '#0a8b86'),
                            borderRadius: [5, 5, 2, 2],
                            shadowBlur: isDarkTheme() ? 0 : 8,
                            shadowColor: 'rgba(15, 174, 167, .12)',
                        },
                        emphasis: { itemStyle: { shadowBlur: 16, shadowColor: 'rgba(24, 189, 179, .25)' } },
                        markArea: { silent: true, label: { show: false }, data: dayAreas },
                        markLine: {
                            silent: true,
                            symbol: ['none', 'none'],
                            lineStyle: { color: palette.slate, width: 1, type: 'dashed', opacity: .72 },
                            label: { show: false },
                            data: [{ yAxis: Number(data.average || 0) }],
                        },
                    },
                    {
                        name: 'Critical demand',
                        type: 'bar',
                        stack: 'demand',
                        barWidth: '54%',
                        data: rows.map((row) => Number(row.critical || 0)),
                        itemStyle: {
                            color: gradient(isDarkTheme() ? '#f8c966' : '#f4bd52', isDarkTheme() ? '#d6982b' : '#dc941f'),
                            borderRadius: [6, 6, 1, 1],
                        },
                        emphasis: { itemStyle: { shadowBlur: 14, shadowColor: 'rgba(231,165,47,.28)' } },
                    },
                ],
            };
        };

        const makeHeroSignatureOption = (data) => {
            const palette = chartPalette();
            const rows = Array.isArray(data.rows) ? data.rows : [];
            const labels = rows.map((row) => row.hour);
            const labelFor = (hour) => String(((hour % 24) + 24) % 24).padStart(2, '0');
            const dayAreas = [];
            const start = Number(data.dayStart);
            const end = Number(data.dayEnd);
            const range = (from, to, color) => [{ xAxis: labelFor(from), itemStyle: { color } }, { xAxis: labelFor(to) }];

            if (Number.isFinite(start) && Number.isFinite(end) && start !== end) {
                if (start < end) dayAreas.push(range(start, Math.max(start, end - 1), isDarkTheme() ? 'rgba(45,216,203,.045)' : 'rgba(24,189,179,.045)'));
                else {
                    dayAreas.push(range(start, 23, isDarkTheme() ? 'rgba(45,216,203,.045)' : 'rgba(24,189,179,.045)'));
                    dayAreas.push(range(0, Math.max(0, end - 1), isDarkTheme() ? 'rgba(45,216,203,.045)' : 'rgba(24,189,179,.045)'));
                }
            }

            if (Number.isFinite(data.peakStart) && Number.isFinite(data.peakEnd)) {
                dayAreas.push(range(data.peakStart, data.peakEnd, isDarkTheme() ? 'rgba(240,185,79,.075)' : 'rgba(231,165,47,.065)'));
            }

            const total = rows.map((row) => Number(row.energy || 0));
            const critical = rows.map((row) => Number(row.critical || 0));
            const maxValue = Math.max(...total, 0);
            const peakIndex = total.indexOf(maxValue);

            return {
                animation: !reducedMotion,
                animationDuration: 820,
                animationEasing: 'cubicOut',
                grid: { top: 8, right: 4, bottom: 7, left: 4 },
                tooltip: {
                    trigger: 'axis',
                    confine: true,
                    backgroundColor: 'transparent',
                    borderWidth: 0,
                    padding: 0,
                    axisPointer: {
                        type: 'line',
                        lineStyle: { color: 'rgba(130, 218, 211, .38)', width: 1 },
                        label: { show: false },
                    },
                    formatter: (params) => {
                        const index = params?.[0]?.dataIndex ?? 0;
                        const row = rows[index] || {};
                        return tooltipMarkup({
                            eyebrow: row.period || 'Demand interval',
                            title: row.window || row.hour || '—',
                            badge: row.isPeak ? (row.peakLabel || 'Peak') : '',
                            rows: [
                                { label: 'Total demand', value: `${Number(row.energy || 0).toFixed(3)} kWh`, color: palette.teal },
                                { label: 'Critical contribution', value: `${Number(row.critical || 0).toFixed(3)} kWh`, color: palette.amber },
                            ],
                        });
                    },
                },
                xAxis: {
                    type: 'category',
                    data: labels,
                    boundaryGap: false,
                    axisTick: { show: false },
                    axisLine: { show: false },
                    axisLabel: { show: false },
                    splitLine: { show: false },
                },
                yAxis: {
                    type: 'value',
                    min: 0,
                    max: Number(data.maximum) > 0 ? Number(data.maximum) : null,
                    splitNumber: 2,
                    axisTick: { show: false },
                    axisLine: { show: false },
                    axisLabel: { show: false },
                    splitLine: { lineStyle: { color: isDarkTheme() ? 'rgba(126,171,185,.105)' : 'rgba(126,154,171,.10)', width: 1 } },
                },
                series: [
                    {
                        name: 'Total demand',
                        type: 'line',
                        smooth: .32,
                        smoothMonotone: 'x',
                        showSymbol: false,
                        symbol: 'circle',
                        symbolSize: 6,
                        data: total,
                        lineStyle: {
                            color: palette.teal,
                            width: 2.6,
                            shadowBlur: isDarkTheme() ? 10 : 7,
                            shadowColor: isDarkTheme() ? 'rgba(45,216,203,.28)' : 'rgba(24,189,179,.20)',
                        },
                        areaStyle: {
                            color: gradient(
                                isDarkTheme() ? 'rgba(45,216,203,.34)' : 'rgba(24,189,179,.26)',
                                isDarkTheme() ? 'rgba(45,216,203,.015)' : 'rgba(24,189,179,.01)'
                            ),
                        },
                        emphasis: {
                            focus: 'series',
                            itemStyle: { color: '#dffffb', borderColor: palette.teal, borderWidth: 3 },
                        },
                        markArea: { silent: true, label: { show: false }, data: dayAreas },
                        markPoint: peakIndex >= 0 && maxValue > 0 ? {
                            silent: true,
                            symbol: 'circle',
                            symbolSize: 7,
                            label: { show: false },
                            itemStyle: {
                                color: '#dffffb',
                                borderColor: palette.teal,
                                borderWidth: 2,
                                shadowBlur: 10,
                                shadowColor: 'rgba(45,216,203,.34)',
                            },
                            data: [{ coord: [labels[peakIndex], maxValue] }],
                        } : undefined,
                    },
                    {
                        name: 'Critical contribution',
                        type: 'line',
                        smooth: .28,
                        smoothMonotone: 'x',
                        showSymbol: false,
                        data: critical,
                        lineStyle: { color: palette.amber, width: 1.7, type: 'dashed', opacity: .96 },
                        emphasis: { focus: 'series' },
                    },
                ],
            };
        };

        const makeBillOption = (data) => {
            const palette = chartPalette();
            const rows = Array.isArray(data.rows) ? data.rows : [];
            return {
                animation: !reducedMotion,
                animationDuration: 780,
                animationEasing: 'cubicOut',
                grid: { top: 34, right: 20, bottom: 40, left: 54 },
                tooltip: {
                    trigger: 'axis',
                    confine: true,
                    backgroundColor: 'transparent',
                    borderWidth: 0,
                    padding: 0,
                    axisPointer: { type: 'shadow', shadowStyle: { color: isDarkTheme() ? 'rgba(61,219,206,.08)' : 'rgba(15,174,167,.06)' } },
                    formatter: (params) => {
                        const index = params?.[0]?.dataIndex ?? 0;
                        const row = rows[index] || {};
                        const badge = row.isHighest ? 'Highest' : (row.isLowest ? 'Lowest' : 'Recorded');
                        return tooltipMarkup({
                            eyebrow: 'Electricity bill',
                            title: row.monthFull || row.month || '—',
                            badge,
                            rows: [{ label: 'Consumption', value: `${Number(row.energy || 0).toLocaleString(undefined, { maximumFractionDigits: 3 })} kWh`, color: row.isHighest ? palette.amber : (row.isLowest ? palette.blue : palette.teal) }],
                        });
                    },
                },
                xAxis: {
                    type: 'category',
                    data: rows.map((row) => row.month),
                    axisTick: { show: false },
                    axisLine: { lineStyle: { color: palette.axis } },
                    axisLabel: { color: palette.muted, fontFamily: '"JetBrains Mono", monospace', fontSize: 10, fontWeight: 700, margin: 13, interval: 0 },
                },
                yAxis: {
                    type: 'value',
                    min: 0,
                    splitNumber: 4,
                    name: 'kWh',
                    nameGap: 12,
                    nameTextStyle: { color: palette.muted, fontFamily: '"JetBrains Mono", monospace', fontSize: 10, fontWeight: 700, align: 'right' },
                    axisTick: { show: false },
                    axisLine: { show: false },
                    axisLabel: { color: palette.muted, fontFamily: '"JetBrains Mono", monospace', fontSize: 10, formatter: (value) => Number(value).toLocaleString() },
                    splitLine: { lineStyle: { color: palette.grid, type: 'dashed' } },
                },
                series: [
                    {
                        name: 'Monthly energy',
                        type: 'bar',
                        barWidth: '46%',
                        data: rows.map((row) => ({
                            value: Number(row.energy || 0),
                            itemStyle: {
                                color: row.isHighest
                                    ? gradient(isDarkTheme() ? '#f7ca66' : '#f2bb4a', isDarkTheme() ? '#d99b2f' : '#dd941e')
                                    : row.isLowest
                                        ? gradient(isDarkTheme() ? '#8bb3dc' : '#86abd0', isDarkTheme() ? '#547da9' : '#5d84ab')
                                        : gradient(isDarkTheme() ? '#35dfd2' : '#26cfc4', isDarkTheme() ? '#10978f' : '#0a8c86'),
                                borderRadius: [8, 8, 3, 3],
                                shadowBlur: isDarkTheme() ? 0 : 10,
                                shadowColor: row.isHighest ? 'rgba(231,165,47,.16)' : 'rgba(15,174,167,.12)',
                            },
                        })),
                        emphasis: { itemStyle: { shadowBlur: 18, shadowColor: 'rgba(24,189,179,.24)' } },
                        markLine: data.average === null ? undefined : {
                            silent: true,
                            symbol: ['none', 'none'],
                            lineStyle: { color: palette.slate, width: 1, type: 'dashed', opacity: .76 },
                            label: { show: false },
                            data: [{ yAxis: Number(data.average) }],
                        },
                    },
                ],
            };
        };

        const mountEchart = (node) => {
            if (!node || !window.echarts || chartRegistry.has(node)) return chartRegistry.get(node) || null;
            const data = readChartData(node);
            if (!data) return null;
            if (node.clientWidth < 40 || node.clientHeight < 40) return null;

            const type = node.dataset.nxlaEchart;
            const chart = window.echarts.init(node, null, { renderer: 'svg', useDirtyRect: false });
            const applyOption = () => chart.setOption(type === 'bills' ? makeBillOption(data) : (type === 'hero-signature' ? makeHeroSignatureOption(data) : makeHourlyOption(data)), true);
            applyOption();
            node.closest('[data-nxla-echart-shell]')?.classList.add('is-ready');

            if (type === 'hourly') {
                const rows = Array.isArray(data.rows) ? data.rows : [];
                chart.on('mouseover', (params) => setProfileInspectorFromRow(rows[params.dataIndex]));
                chart.on('click', (params) => {
                    const row = rows[params.dataIndex];
                    setProfileInspectorFromRow(row, true);
                    chart.dispatchAction({ type: 'downplay' });
                    chart.dispatchAction({ type: 'highlight', seriesIndex: [0, 1], dataIndex: params.dataIndex });
                });
                chart.getZr().on('globalout', () => {
                    if (persistedChartHour === null) return;
                    const index = rows.findIndex((row) => Number.parseInt(row.hour || '0', 10) === persistedChartHour);
                    if (index >= 0) setProfileInspectorFromRow(rows[index]);
                });
            }

            const observer = 'ResizeObserver' in window ? new ResizeObserver(() => chart.resize()) : null;
            observer?.observe(node);
            chartRegistry.set(node, { chart, applyOption, observer });
            return chartRegistry.get(node);
        };

        const mountPanelCharts = (scope = document) => {
            scope.querySelectorAll?.('[data-nxla-echart]').forEach((node) => {
                const entry = mountEchart(node);
                if (entry) requestAnimationFrame(() => entry.chart.resize());
            });
        };

        const refreshChartThemes = () => {
            chartRegistry.forEach((entry) => entry.applyOption());
        };

        const syncStickyOffset = () => {
            const topbar = document.querySelector('.topbar');
            const topbarHeight = topbar ? Math.ceil(topbar.getBoundingClientRect().height) : 72;
            const spacing = window.innerWidth <= 920 ? 6 : 10;
            const offset = topbarHeight + spacing;
            root.style.setProperty('--nxla-sticky-top', `${offset}px`);
            root.style.scrollPaddingTop = `${offset + 64}px`;
        };

        syncStickyOffset();
        window.addEventListener('resize', syncStickyOffset, { passive: true });
        const topbar = document.querySelector('.topbar');
        if (topbar && 'ResizeObserver' in window) {
            new ResizeObserver(syncStickyOffset).observe(topbar);
        }

        const revealNodes = Array.from(document.querySelectorAll('[data-nxla-reveal]'));
        if ('IntersectionObserver' in window && !reducedMotion) {
            const revealObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: .08 });
            revealNodes.forEach((node) => revealObserver.observe(node));
        } else {
            revealNodes.forEach((node) => node.classList.add('is-visible'));
        }

        const animateIntegerCounter = (node) => {
            const target = Number(node.dataset.nxlaCounter || node.textContent || 0);
            if (!Number.isFinite(target) || reducedMotion) return;
            const duration = 650;
            const start = performance.now();
            const formatter = new Intl.NumberFormat();
            const tick = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                node.textContent = formatter.format(Math.round(target * eased));
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        };
        document.querySelectorAll('[data-nxla-counter]').forEach(animateIntegerCounter);

        const animateMetricValue = (node) => {
            const target = Number.parseFloat(node.dataset.nxlaValue || '');
            const decimals = Number.parseInt(node.dataset.decimals || '0', 10);
            if (!Number.isFinite(target) || reducedMotion) return;
            const duration = 760;
            const start = performance.now();
            const formatter = new Intl.NumberFormat(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });
            const tick = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 4);
                node.textContent = formatter.format(target * eased);
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        };
        document.querySelectorAll('[data-nxla-value]').forEach(animateMetricValue);

        const chartNodes = Array.from(document.querySelectorAll('[data-nxla-echart-shell]'));
        if ('IntersectionObserver' in window && !reducedMotion) {
            const chartObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: .12 });
            chartNodes.forEach((node) => chartObserver.observe(node));
        } else {
            chartNodes.forEach((node) => node.classList.add('is-visible'));
        }

        let activateTab = null;
        const tabList = document.querySelector('[data-nxla-tabs]');
        if (tabList) {
            const tabs = Array.from(tabList.querySelectorAll('[data-nxla-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-nxla-panel]'));

            activateTab = (name, updateHash = true) => {
                const fallback = tabs[0]?.dataset.nxlaTab;
                const resolved = tabs.some((tab) => tab.dataset.nxlaTab === name) ? name : fallback;

                tabs.forEach((tab) => {
                    const active = tab.dataset.nxlaTab === resolved;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                });

                panels.forEach((panel) => {
                    const active = panel.dataset.nxlaPanel === resolved;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                    if (active) {
                        requestAnimationFrame(() => {
                            mountPanelCharts(panel);
                            panel.querySelectorAll('[data-nxla-echart]').forEach((node) => chartRegistry.get(node)?.chart.resize());
                        });
                    }
                });

                if (updateHash && resolved) history.replaceState(null, '', `#${resolved}`);
            };

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => activateTab(tab.dataset.nxlaTab));
                tab.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    let nextIndex = index;
                    if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
                    if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
                    if (event.key === 'Home') nextIndex = 0;
                    if (event.key === 'End') nextIndex = tabs.length - 1;
                    tabs[nextIndex]?.focus();
                    activateTab(tabs[nextIndex]?.dataset.nxlaTab);
                });
            });

            activateTab(window.location.hash.replace('#', '') || tabs[0]?.dataset.nxlaTab, false);

            document.querySelectorAll('[data-nxla-go-tab]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    const name = link.dataset.nxlaGoTab;
                    if (!name) return;
                    event.preventDefault();
                    activateTab(name);
                    const stickyTop = Number.parseFloat(getComputedStyle(root).getPropertyValue('--nxla-sticky-top')) || 82;
                    const targetTop = tabList.getBoundingClientRect().top + window.scrollY - stickyTop - 8;
                    window.scrollTo({ top: Math.max(0, targetTop), behavior: reducedMotion ? 'auto' : 'smooth' });
                });
            });
        }

        const equipmentSearch = document.querySelector('[data-nxla-equipment-search]');
        const equipmentCategory = document.querySelector('[data-nxla-equipment-category]');
        const equipmentCritical = document.querySelector('[data-nxla-equipment-critical]');
        const equipmentRows = Array.from(document.querySelectorAll('[data-nxla-equipment-row]'));
        const equipmentEmpty = document.querySelector('[data-nxla-equipment-empty]');
        const timeFilter = document.querySelector('[data-nxla-time-filter]');
        const timeFilterLabel = document.querySelector('[data-nxla-time-filter-label]');
        const clearTimeFilter = document.querySelector('[data-nxla-clear-time-filter]');
        let selectedHourFilter = null;

        const rowIsActiveAtHour = (row, hour) => {
            const start = Number.parseFloat(row.dataset.startHour || '0') || 0;
            const duration = Math.min(Math.max(Number.parseFloat(row.dataset.duration || '0') || 0, 0), 24);
            if (duration <= 0) return false;

            let remaining = duration;
            let position = start;
            let guard = 0;
            while (remaining > .000001 && guard < 48) {
                const floor = Math.floor(position);
                const slot = ((floor % 24) + 24) % 24;
                const available = 1 - (position - floor);
                const allocated = Math.min(remaining, available);
                if (slot === hour && allocated > 0) return true;
                remaining -= allocated;
                position += allocated;
                guard += 1;
            }
            return false;
        };

        const filterEquipment = () => {
            if (!equipmentRows.length) return;
            const query = String(equipmentSearch?.value || '').trim().toLowerCase();
            const category = String(equipmentCategory?.value || '').toLowerCase();
            const criticalOnly = Boolean(equipmentCritical?.checked);
            let visible = 0;

            equipmentRows.forEach((row) => {
                const matches = (!query || String(row.dataset.name || '').includes(query))
                    && (!category || String(row.dataset.category || '') === category)
                    && (!criticalOnly || row.dataset.critical === '1')
                    && (selectedHourFilter === null || rowIsActiveAtHour(row, selectedHourFilter));
                row.hidden = !matches;
                if (matches) visible += 1;
            });

            if (equipmentEmpty) equipmentEmpty.hidden = visible !== 0;
            if (timeFilter) timeFilter.hidden = selectedHourFilter === null;
            if (timeFilterLabel && selectedHourFilter !== null) {
                timeFilterLabel.textContent = `${String(selectedHourFilter).padStart(2, '0')}:00`;
            }
        };

        equipmentSearch?.addEventListener('input', filterEquipment);
        equipmentCategory?.addEventListener('change', filterEquipment);
        equipmentCritical?.addEventListener('change', filterEquipment);
        clearTimeFilter?.addEventListener('click', () => {
            selectedHourFilter = null;
            filterEquipment();
        });
        filterEquipment();

        inspectorAction?.addEventListener('click', () => {
            selectedHourFilter = Number.parseInt(inspectorAction.dataset.hour || String(selectedChartHour), 10) || 0;
            if (activateTab) activateTab('equipment');
            filterEquipment();
            if (tabList) {
                const stickyTop = Number.parseFloat(getComputedStyle(root).getPropertyValue('--nxla-sticky-top')) || 82;
                const targetTop = tabList.getBoundingClientRect().top + window.scrollY - stickyTop - 8;
                window.scrollTo({ top: Math.max(0, targetTop), behavior: reducedMotion ? 'auto' : 'smooth' });
            }
        });

        mountPanelCharts(document);

        if ('MutationObserver' in window) {
            new MutationObserver((mutations) => {
                if (mutations.some((mutation) => mutation.attributeName === 'data-theme')) refreshChartThemes();
            }).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
        }

        const createProcessingOverlay = (target, form, submit) => {
            const isSettings = form.matches('[data-nxla-settings-form]');
            const isBill = Boolean(form.querySelector('[name="billing_month"]'));
            const isEquipment = Boolean(form.closest('[data-nxla-equipment-form]'));
            const steps = isSettings
                ? ['Reading equipment profile', 'Rebuilding hourly demand', 'Comparing bill history', 'Finalizing validation', 'Writing the load-analysis milestone']
                : isBill
                    ? ['Validating the monthly record', 'Updating consumption trend', 'Refreshing historical fit', 'Saving bill history']
                    : isEquipment
                        ? ['Validating equipment identity', 'Computing connected load', 'Building the operating schedule', 'Updating the demand curve', 'Writing the equipment milestone']
                        : ['Validating current inputs', 'Calculating engineering results', 'Refreshing readiness indicators', 'Saving the controlled dataset'];

            const kind = isSettings ? 'calculate' : (isBill ? 'bill' : (isEquipment ? 'equipment' : 'save'));
            const title = target?.dataset.processingLabel
                || (isSettings ? 'Recalculating Load Analysis' : (isBill ? 'Saving Electricity Bill' : (isEquipment ? 'Saving Equipment Profile' : 'Saving Load Analysis')));
            const copy = isSettings
                ? 'The latest equipment, schedule, tariff, and bill evidence is being converted into a controlled demand profile.'
                : isBill
                    ? 'The monthly consumption record is being validated against the modeled demand history.'
                    : isEquipment
                        ? 'Equipment power, quantity, usage, criticality, and schedule are being synchronized with the project profile.'
                        : 'The latest electrical-demand inputs are being synchronized with the project record.';

            if (!actionEngine) return { duration: 0 };

            return actionEngine.show({
                kind,
                icon: kind,
                title,
                copy,
                steps,
                duration: isSettings ? 1120 : (isEquipment ? 980 : (isBill ? 820 : 880)),
                signal: 'NEXORIN load engine',
                accent: isBill ? '#3978d8' : '#13aaa2',
                secondary: '#f3a04f',
                metaRight: 'Demand trace preserved',
            });
        };

        document.querySelectorAll('[data-nxla-processing-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.nxlaSubmitting === 'true') return;

                event.preventDefault();
                form.dataset.nxlaSubmitting = 'true';

                const targetSelector = form.dataset.nxlaProcessingTarget;
                const target = targetSelector ? document.querySelector(targetSelector) : form.closest('.nxla-processing') || form.closest('.nxla-equipment-shell') || form;
                const submit = event.submitter || form.querySelector('[type="submit"]');

                form.querySelectorAll('[type="submit"]').forEach((button) => {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    if (button === submit) {
                        const label = button.querySelector('[data-nxla-submit-label]');
                        if (label) label.textContent = button.dataset.processingText || 'Processing…';
                        else if (!button.querySelector('svg')) button.textContent = button.dataset.processingText || 'Processing…';
                    }
                });

                const { duration } = createProcessingOverlay(target, form, submit);
                window.setTimeout(() => nativeSubmit.call(form), duration);
            });
        });


        const settingsForm = document.querySelector('[data-nxla-settings-form]');
        if (settingsForm) {
            const state = document.querySelector('[data-nxla-settings-state]');
            const initial = new FormData(settingsForm);
            const baseline = Array.from(initial.entries()).map(([key, value]) => `${key}:${value}`).join('|');
            const sourceMode = settingsForm.querySelector('[name="source_mode"]');
            const supplyPhase = settingsForm.querySelector('[name="supply_phase"]');
            const billingDays = settingsForm.querySelector('[name="billing_days"]');
            const dayStart = settingsForm.querySelector('[name="daytime_start_hour"]');
            const dayEnd = settingsForm.querySelector('[name="daytime_end_hour"]');
            const summaryMethod = document.querySelector('[data-nxla-summary-method]');
            const summarySupply = document.querySelector('[data-nxla-summary-supply]');
            const summaryBilling = document.querySelector('[data-nxla-summary-billing]');
            const summaryDaytime = document.querySelector('[data-nxla-summary-daytime]');

            const formatHour = (value) => `${String(Number.parseInt(value || '0', 10) || 0).padStart(2, '0')}:00`;
            const updateSettingsPreview = () => {
                const current = Array.from(new FormData(settingsForm).entries()).map(([key, value]) => `${key}:${value}`).join('|');
                const dirty = current !== baseline;
                if (state) {
                    state.textContent = dirty ? 'Unsaved changes' : 'Saved';
                    state.classList.toggle('is-warning', dirty);
                    state.classList.toggle('is-muted', !dirty);
                }
                if (summaryMethod && sourceMode) summaryMethod.textContent = sourceMode.options[sourceMode.selectedIndex]?.text || sourceMode.value;
                if (summarySupply && supplyPhase) summarySupply.textContent = supplyPhase.options[supplyPhase.selectedIndex]?.text || supplyPhase.value;
                if (summaryBilling && billingDays) summaryBilling.textContent = `${billingDays.value || 0} days`;
                if (summaryDaytime && dayStart && dayEnd) summaryDaytime.textContent = `${formatHour(dayStart.value)}–${formatHour(dayEnd.value)}`;
            };

            settingsForm.addEventListener('input', updateSettingsPreview);
            settingsForm.addEventListener('change', updateSettingsPreview);
            updateSettingsPreview();
        }

        const billForm = document.querySelector('[data-nxla-bill-form]');
        if (billForm) {
            const energyInput = billForm.querySelector('[name="energy_kwh"]');
            const amountInput = billForm.querySelector('[name="bill_amount"]');
            const previewShell = document.querySelector('[data-nxla-bill-rate-preview-shell]');
            const previewValue = document.querySelector('[data-nxla-bill-rate-preview]');
            const previewContext = document.querySelector('[data-nxla-bill-rate-context]');
            const historicalRate = Number.parseFloat(previewShell?.dataset.historicalRate || '');
            const currency = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

            const updateBillRatePreview = () => {
                const energy = Number.parseFloat(energyInput?.value || '');
                const amount = Number.parseFloat(amountInput?.value || '');

                if (Number.isFinite(energy) && energy > 0 && Number.isFinite(amount) && amount >= 0) {
                    const rate = amount / energy;
                    if (previewValue) previewValue.textContent = `Rp ${currency.format(rate)} / kWh`;

                    if (previewContext) {
                        if (Number.isFinite(historicalRate) && historicalRate > 0) {
                            const variance = ((rate - historicalRate) / historicalRate) * 100;
                            const direction = Math.abs(variance) < .5
                                ? 'Aligned with the current historical rate.'
                                : `${Math.abs(variance).toFixed(1)}% ${variance > 0 ? 'above' : 'below'} the current historical rate.`;
                            previewContext.textContent = direction;
                        } else {
                            previewContext.textContent = 'Calculated from the values currently entered.';
                        }
                    }
                    previewShell?.classList.add('has-value');
                    return;
                }

                if (previewValue) previewValue.textContent = Number.isFinite(energy) && energy > 0 ? 'Add bill amount' : 'Waiting for values';
                if (previewContext) previewContext.textContent = 'Enter energy and bill amount to calculate.';
                previewShell?.classList.remove('has-value');
            };

            billForm.addEventListener('input', updateBillRatePreview);
            billForm.addEventListener('change', updateBillRatePreview);
            updateBillRatePreview();
        }

        const equipmentForm = document.querySelector('[data-nxla-equipment-form]');
        if (!equipmentForm) return;

        const ref = (selector) => equipmentForm.querySelector(selector);
        const refs = {
            name: ref('[data-nxla-name]'),
            category: ref('[data-nxla-category]'),
            quantity: ref('[data-nxla-quantity]'),
            power: ref('[data-nxla-power]'),
            hours: ref('[data-nxla-hours]'),
            start: ref('[data-nxla-start]'),
            usage: ref('[data-nxla-usage]'),
            usageRange: ref('[data-nxla-usage-range]'),
            starting: ref('[data-nxla-starting]'),
            critical: ref('[data-nxla-critical]'),
            title: ref('[data-nxla-preview-title]'),
            subtitle: ref('[data-nxla-preview-subtitle]'),
            connected: ref('[data-nxla-preview-connected]'),
            daily: ref('[data-nxla-preview-daily]'),
            startingDemand: ref('[data-nxla-preview-starting]'),
            schedule: ref('[data-nxla-preview-schedule]'),
            readable: ref('[data-nxla-readable]'),
            usageBadge: ref('[data-nxla-usage-badge]'),
            criticalNote: ref('[data-nxla-critical-note]'),
            timeline: Array.from(equipmentForm.querySelectorAll('[data-nxla-hour]')),
        };

        const number = (input, fallback = 0) => {
            const value = Number.parseFloat(input?.value || '');
            return Number.isFinite(value) ? value : fallback;
        };
        const format = (value, digits = 2) => new Intl.NumberFormat(undefined, {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        }).format(Number.isFinite(value) ? value : 0);
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

        const syncChips = () => {
            const current = String(refs.category?.value || '').toLowerCase();
            equipmentForm.querySelectorAll('[data-nxla-category-chip]').forEach((chip) => {
                chip.classList.toggle('is-active', String(chip.dataset.nxlaCategoryChip || '').toLowerCase() === current);
            });
        };

        const updateEquipmentPreview = () => {
            const name = refs.name?.value?.trim() || 'New equipment profile';
            const category = refs.category?.value?.trim() || 'General load';
            const quantity = Math.max(number(refs.quantity), 0);
            const power = Math.max(number(refs.power), 0);
            const hours = Math.min(Math.max(number(refs.hours), 0), 24);
            const start = Math.max(Math.floor(number(refs.start)), 0) % 24;
            const usage = Math.min(Math.max(number(refs.usage), .01), 1);
            const starting = Math.max(number(refs.starting, 1), 1);
            const critical = refs.critical?.value === '1';

            const connected = quantity * power / 1000;
            const daily = connected * hours * usage;
            const startingDemand = connected * starting;
            const end = start + hours;
            const usagePercent = Math.round(usage * 100);

            if (refs.title) refs.title.textContent = name;
            if (refs.subtitle) refs.subtitle.textContent = `${category} · ${quantity || 0} unit${quantity === 1 ? '' : 's'} · ${usagePercent}% operating factor`;
            if (refs.connected) refs.connected.textContent = `${format(connected)} kW`;
            if (refs.daily) refs.daily.textContent = `${format(daily)} kWh`;
            if (refs.startingDemand) refs.startingDemand.textContent = `${format(startingDemand)} kW`;
            if (refs.schedule) refs.schedule.textContent = `${clock(start)}–${clock(end)}`;
            if (refs.usageBadge) refs.usageBadge.textContent = `${usagePercent}%`;
            if (refs.readable) refs.readable.textContent = `${name} is modeled as ${quantity || 0} unit${quantity === 1 ? '' : 's'} at ${format(power, 0)} W each. It operates from ${clock(start)} to ${clock(end)} for ${format(hours, 1)} hour${hours === 1 ? '' : 's'} per day at a ${usagePercent}% usage factor. Estimated connected load is ${format(connected)} kW, daily energy is ${format(daily)} kWh, and starting demand is ${format(startingDemand)} kW.`;
            if (refs.criticalNote) {
                refs.criticalNote.textContent = critical
                    ? 'Critical load. This equipment should remain powered during an outage and will influence hybrid battery sizing.'
                    : 'Standard load. This equipment contributes to the demand profile but is not currently a battery-backup priority.';
            }

            refs.timeline.forEach((cell) => {
                const hour = Number(cell.dataset.nxlaHour || 0);
                let active = false;
                let remaining = hours;
                let position = start;
                let guard = 0;
                while (remaining > .000001 && guard < 48) {
                    const floor = Math.floor(position);
                    const slot = ((floor % 24) + 24) % 24;
                    const available = 1 - (position - floor);
                    const allocated = Math.min(remaining, available);
                    if (slot === hour && allocated > 0) active = true;
                    remaining -= allocated;
                    position += allocated;
                    guard += 1;
                }
                cell.classList.toggle('is-active', active);
            });
            syncChips();
        };

        refs.usageRange?.addEventListener('input', () => {
            if (refs.usage) refs.usage.value = (Number(refs.usageRange.value || 1) / 100).toFixed(2);
            updateEquipmentPreview();
        });
        refs.usage?.addEventListener('input', () => {
            if (refs.usageRange) refs.usageRange.value = String(Math.round(Math.min(Math.max(number(refs.usage), .01), 1) * 100));
            updateEquipmentPreview();
        });
        equipmentForm.querySelectorAll('[data-nxla-category-chip]').forEach((chip) => {
            chip.addEventListener('click', () => {
                if (refs.category) refs.category.value = chip.dataset.nxlaCategoryChip || '';
                updateEquipmentPreview();
            });
        });
        equipmentForm.addEventListener('input', updateEquipmentPreview);
        equipmentForm.addEventListener('change', updateEquipmentPreview);
        updateEquipmentPreview();
    });
})();
