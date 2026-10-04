(() => {
    'use strict';

    if (window.__nexorinDashboardV6) return;
    window.__nexorinDashboardV6 = true;

    const root = document.querySelector('[data-nx12-root]');
    if (!root) return;

    const dataNode = root.querySelector('[data-nx12-chart-data]');
    let payload = { systems: [], engineering: [], quotations: [], mapPoints: [] };

    try {
        payload = JSON.parse(dataNode?.textContent || '{}');
    } catch (_) {
        // The server-rendered data list remains visible when chart payload parsing fails.
    }

    const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
    const palette = {
        cyan: '#59d6de',
        blue: '#76a9ff',
        green: '#6ed0a1',
        amber: '#e8bb72',
        red: '#ef8290',
        purple: '#a998f6',
        text: '#d7e7ea',
        muted: '#789099',
        grid: 'rgba(132,178,192,.10)',
        tooltip: 'rgba(4,16,22,.97)',
        tooltipBorder: 'rgba(111,191,202,.24)',
    };

    const charts = [];
    const chartReady = () => typeof window.echarts !== 'undefined';
    const asArray = value => Array.isArray(value) ? value : [];
    const integer = value => Number.isFinite(Number(value)) ? Number(value) : 0;

    const tooltipBase = {
        trigger: 'item',
        confine: true,
        backgroundColor: palette.tooltip,
        borderColor: palette.tooltipBorder,
        borderWidth: 1,
        padding: [8, 10],
        textStyle: { color: palette.text, fontFamily: 'Inter, Segoe UI, Arial, sans-serif', fontSize: 10 },
        extraCssText: 'box-shadow:0 14px 32px rgba(0,0,0,.28);border-radius:7px;backdrop-filter:blur(8px);',
    };

    const initSystemChart = () => {
        const element = root.querySelector('[data-nx12-system-chart]');
        if (!element || !chartReady()) return;

        const rows = asArray(payload.systems).filter(item => integer(item.value) > 0);
        if (rows.length < 2) return;
        const total = rows.reduce((sum, item) => sum + integer(item.value), 0);
        const colorByKey = { on_grid: palette.cyan, hybrid: palette.purple, undecided: palette.amber };

        const chart = window.echarts.init(element, null, { renderer: 'canvas' });
        chart.setOption({
            animation: !prefersReducedMotion,
            animationDuration: 500,
            tooltip: {
                ...tooltipBase,
                formatter: params => `<strong>${params.name}</strong><br><span style="color:#789099">Project records</span>&nbsp;&nbsp;${integer(params.value)}`,
            },
            legend: {
                bottom: 7,
                left: 'center',
                icon: 'circle',
                itemWidth: 6,
                itemHeight: 6,
                itemGap: 14,
                textStyle: { color: '#718991', fontSize: 8.5, fontFamily: 'Inter, Segoe UI, Arial, sans-serif' },
            },
            graphic: [
                { type: 'text', left: 'center', top: '34%', style: { text: String(total), fill: '#e4f0f2', font: '650 23px Inter, Segoe UI, Arial, sans-serif', textAlign: 'center' } },
                { type: 'text', left: 'center', top: '49%', style: { text: 'projects', fill: '#6c858e', font: '8px Inter, Segoe UI, Arial, sans-serif', textAlign: 'center' } },
            ],
            series: [{
                type: 'pie',
                radius: ['54%', '72%'],
                center: ['50%', '42%'],
                itemStyle: { borderColor: '#091821', borderWidth: 2 },
                label: { show: false },
                emphasis: { scaleSize: 3 },
                data: rows.map(item => ({ value: integer(item.value), name: item.label, itemStyle: { color: colorByKey[item.key] || palette.blue } })),
            }],
        });
        charts.push(chart);
    };

    const initEngineeringChart = () => {
        const element = root.querySelector('[data-nx12-engineering-chart]');
        if (!element || !chartReady()) return;

        const rows = asArray(payload.engineering).filter(item => integer(item.value) > 0);
        if (!rows.length) return;
        const total = rows.reduce((sum, item) => sum + integer(item.value), 0);
        const colorByKey = { stale: palette.amber, preliminary: palette.purple, detailed: palette.blue, verified: palette.green };

        const chart = window.echarts.init(element, null, { renderer: 'canvas' });
        chart.setOption({
            animation: !prefersReducedMotion,
            animationDuration: 540,
            tooltip: {
                ...tooltipBase,
                formatter: params => `<strong>${params.name}</strong><br><span style="color:#789099">Stored records</span>&nbsp;&nbsp;${integer(params.value)}`,
            },
            legend: {
                bottom: 7,
                left: 'center',
                icon: 'circle',
                itemWidth: 6,
                itemHeight: 6,
                itemGap: 12,
                textStyle: { color: '#718991', fontSize: 8, fontFamily: 'Inter, Segoe UI, Arial, sans-serif' },
            },
            graphic: [
                { type: 'text', left: 'center', top: '37%', style: { text: String(total), fill: '#e4f0f2', font: '650 24px Inter, Segoe UI, Arial, sans-serif', textAlign: 'center' } },
                { type: 'text', left: 'center', top: '51%', style: { text: 'records', fill: '#6c858e', font: '8px Inter, Segoe UI, Arial, sans-serif', textAlign: 'center' } },
            ],
            series: [{
                type: 'pie',
                radius: ['56%', '73%'],
                center: ['50%', '44%'],
                avoidLabelOverlap: true,
                silent: false,
                itemStyle: { borderColor: '#091821', borderWidth: 2 },
                label: { show: false },
                emphasis: { scaleSize: 3 },
                data: rows.map(item => ({ value: integer(item.value), name: item.label, itemStyle: { color: colorByKey[item.key] || palette.cyan } })),
            }],
        });
        charts.push(chart);
    };

    const initQuotationChart = () => {
        const element = root.querySelector('[data-nx12-quotation-chart]');
        if (!element || !chartReady()) return;

        const rows = asArray(payload.quotations).filter(item => integer(item.value) > 0);
        if (!rows.length) return;

        const chart = window.echarts.init(element, null, { renderer: 'canvas' });
        chart.setOption({
            animation: !prefersReducedMotion,
            animationDuration: 420,
            grid: { left: 5, right: 5, top: 14, bottom: 20, containLabel: true },
            tooltip: {
                ...tooltipBase,
                formatter: params => `<strong>${params.name}</strong><br><span style="color:#789099">Quotation records</span>&nbsp;&nbsp;${integer(params.value)}`,
            },
            xAxis: {
                type: 'category',
                data: rows.map(item => item.label),
                axisLine: { lineStyle: { color: 'rgba(132,178,192,.10)' } },
                axisTick: { show: false },
                axisLabel: { color: '#687f87', fontSize: 7.5, interval: 0, width: 48, overflow: 'truncate', fontFamily: 'Inter, Segoe UI, Arial, sans-serif' },
            },
            yAxis: { type: 'value', show: false, minInterval: 1 },
            series: [{
                type: 'bar',
                data: rows.map((item, index) => ({
                    value: integer(item.value),
                    itemStyle: { color: [palette.purple, palette.blue, palette.cyan, palette.green, palette.amber, palette.red][index % 6], borderRadius: [4, 4, 0, 0] },
                })),
                barMaxWidth: 12,
                emphasis: { focus: 'self' },
            }],
        });
        charts.push(chart);
    };

    const initMap = async () => {
        const element = root.querySelector('[data-nx12-map]');
        if (!element || !chartReady()) return;

        const points = asArray(payload.mapPoints)
            .map(item => ({
                ...item,
                latitude: Number(item.latitude),
                longitude: Number(item.longitude),
            }))
            .filter(item => Number.isFinite(item.latitude) && Number.isFinite(item.longitude));

        if (!points.length) return;

        // Exact-coordinate grouping preserves stored coordinates while preventing coincident
        // projects from visually disappearing beneath a single marker.
        const coordinateGroups = new Map();
        points.forEach(item => {
            const key = `${item.latitude.toFixed(6)}|${item.longitude.toFixed(6)}`;
            const group = coordinateGroups.get(key) || { latitude: item.latitude, longitude: item.longitude, items: [] };
            group.items.push(item);
            coordinateGroups.set(key, group);
        });
        const plottedPoints = Array.from(coordinateGroups.values());

        try {
            const response = await fetch(root.dataset.mapUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Map asset returned ${response.status}`);
            const geoJson = await response.json();
            window.echarts.registerMap('nx12-indonesia', geoJson);

            const chart = window.echarts.init(element, null, { renderer: 'canvas' });
            chart.setOption({
                animation: !prefersReducedMotion,
                animationDuration: 560,
                tooltip: {
                    ...tooltipBase,
                    formatter: params => {
                        const group = params.data?.raw;
                        if (!group?.items?.length) return params.name || 'Project';
                        const primary = group.items[0];
                        const lat = Number(group.latitude).toFixed(5);
                        const lng = Number(group.longitude).toFixed(5);
                        const heading = group.items.length > 1
                            ? `<strong>${group.items.length} projects at this exact coordinate</strong>`
                            : `<strong>${escapeHtml(primary.name || 'Project')}</strong>`;
                        const projectLines = group.items.slice(0, 4).map(item =>
                            `<br><span style="color:#9db2b8">${escapeHtml(item.name || item.project_number || 'Project')}</span>` +
                            ` <span style="color:#6f8790">· ${escapeHtml(humanize(item.status || 'not recorded'))}</span>`
                        ).join('');
                        const more = group.items.length > 4 ? `<br><span style="color:#6f8790">+${group.items.length - 4} more</span>` : '';
                        return `${heading}${projectLines}${more}<br><span style="color:#789099">Exact coordinate</span>&nbsp;&nbsp;${lat}, ${lng}`;
                    },
                },
                geo: {
                    map: 'nx12-indonesia',
                    roam: true,
                    zoom: 1.08,
                    layoutCenter: ['50%', '50%'],
                    layoutSize: '126%',
                    itemStyle: {
                        areaColor: '#112b33',
                        borderColor: 'rgba(101,172,185,.48)',
                        borderWidth: .7,
                    },
                    emphasis: {
                        itemStyle: { areaColor: '#163944', borderColor: 'rgba(107,205,214,.68)' },
                        label: { show: false },
                    },
                    select: { disabled: true },
                    label: { show: false },
                },
                series: [{
                    type: 'effectScatter',
                    coordinateSystem: 'geo',
                    showEffectOn: 'emphasis',
                    rippleEffect: { scale: 2.3, brushType: 'stroke' },
                    symbolSize: value => Math.max(8, Math.min(18, 8 + ((Number(value?.[2]) || 1) - 1) * 3)),
                    itemStyle: { color: palette.cyan, shadowBlur: 12, shadowColor: 'rgba(89,214,222,.4)' },
                    emphasis: { scale: 1.28 },
                    label: {
                        show: true,
                        formatter: params => Number(params.value?.[2]) > 1 ? String(params.value[2]) : '',
                        color: '#eaffff',
                        fontSize: 8,
                        fontWeight: 700,
                    },
                    data: plottedPoints.map(group => ({
                        name: group.items.length > 1 ? `${group.items.length} projects` : (group.items[0]?.name || group.items[0]?.project_number || 'Project'),
                        value: [group.longitude, group.latitude, group.items.length],
                        raw: group,
                    })),
                }],
            });
            charts.push(chart);
        } catch (_) {
            element.hidden = true;
            const fallback = root.querySelector('[data-nx12-map-fallback]');
            if (fallback) fallback.hidden = false;
        }
    };

    function humanize(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    const initRowNavigation = () => {
        root.querySelectorAll('[data-row-href]').forEach(row => {
            row.addEventListener('click', event => {
                if (event.target.closest('a,button,input,select,textarea')) return;
                window.location.assign(row.dataset.rowHref);
            });
            row.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                if (event.target.closest('a,button,input,select,textarea')) return;
                event.preventDefault();
                window.location.assign(row.dataset.rowHref);
            });
        });
    };

    const resizeAll = () => charts.forEach(chart => chart?.resize?.());

    const start = async () => {
        initRowNavigation();
        if (!chartReady()) return;
        initSystemChart();
        initEngineeringChart();
        initQuotationChart();
        await initMap();

        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(resizeAll);
            observer.observe(root);
        } else {
            window.addEventListener('resize', resizeAll, { passive: true });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
