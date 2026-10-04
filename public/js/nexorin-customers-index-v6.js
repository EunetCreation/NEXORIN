(() => {
    'use strict';

    const root = document.querySelector('[data-nx10-root]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const charts = [];
    const chartSource = root.querySelector('[data-nx10-chart-data]');
    let data = {};

    try {
        data = JSON.parse(chartSource?.textContent || '{}');
    } catch (_) {
        data = {};
    }

    const palette = ['#25c7c6', '#3a8fd8', '#8c72d8', '#d7a54a', '#53ba8f', '#738ca0'];
    const axisColor = 'rgba(130, 160, 177, .22)';
    const labelColor = '#7896a6';
    const textColor = '#b7ccd5';

    const escapeHtml = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const tooltipShell = (eyebrow, title, rows = []) => {
        const safeRows = Array.isArray(rows) ? rows.filter(Boolean) : [];
        const rowsMarkup = safeRows.map(row => `
            <div class="nx10-tooltip__row">
                <span>${escapeHtml(row.label)}</span>
                <strong>${escapeHtml(row.value)}</strong>
            </div>`).join('');

        return `
            <div class="nx10-tooltip">
                ${eyebrow ? `<small class="nx10-tooltip__eyebrow">${escapeHtml(eyebrow)}</small>` : ''}
                <strong class="nx10-tooltip__title">${escapeHtml(title)}</strong>
                ${rowsMarkup ? `<div class="nx10-tooltip__grid">${rowsMarkup}</div>` : ''}
            </div>`;
    };

    const tooltipBase = {
        renderMode: 'html',
        className: 'nx10-echart-tooltip-wrap',
        appendToBody: true,
        confine: true,
        backgroundColor: 'transparent',
        borderWidth: 0,
        padding: 0,
        textStyle: { color: '#d4e5eb', fontSize: 11, fontFamily: 'Inter, "Segoe UI", Arial, sans-serif' },
        transitionDuration: reducedMotion ? 0 : 0.08,
        extraCssText: 'box-shadow:none; background:transparent; border:none;'
    };

    const initChart = (selector, optionFactory) => {
        const element = root.querySelector(selector);
        if (!element || !window.echarts || element.dataset.ready === 'true') return null;
        try {
            const chart = window.echarts.init(element, null, { renderer: 'canvas' });
            chart.setOption(optionFactory());
            element.dataset.ready = 'true';
            const key = element.dataset.nx10Chart;
            root.querySelector(`[data-nx10-fallback="${key}"]`)?.setAttribute('hidden', 'hidden');
            charts.push(chart);
            return chart;
        } catch (_) {
            return null;
        }
    };

    const relationship = Array.isArray(data.relationshipStages) ? data.relationshipStages.filter(item => Number(item.value) > 0) : [];
    if (relationship.length) {
        initChart('[data-nx10-chart="relationship"]', () => ({
            animation: !reducedMotion,
            animationDuration: 450,
            tooltip: {
                ...tooltipBase,
                trigger: 'item',
                formatter: params => tooltipShell('Relationship stage', params.name, [
                    { label: 'Customer records', value: Number(params.value).toLocaleString() }
                ])
            },
            color: palette,
            series: [{
                type: 'pie',
                radius: ['59%', '78%'],
                center: ['50%', '48%'],
                avoidLabelOverlap: true,
                itemStyle: { borderWidth: 2, borderColor: '#071a28', borderRadius: 4 },
                label: { show: false },
                emphasis: { scaleSize: 4, label: { show: false } },
                data: relationship.map(item => ({ name: item.label, value: Number(item.value) }))
            }],
            graphic: [{
                type: 'group', left: 'center', top: '39%', children: [
                    { type: 'text', style: { text: Number(data.totalCustomers || 0).toLocaleString(), fill: '#d4e5eb', fontSize: 24, fontWeight: 700, textAlign: 'center' } },
                    { type: 'text', top: 30, style: { text: 'CUSTOMERS', fill: '#678697', fontSize: 9, fontWeight: 600, textAlign: 'center' } }
                ]
            }]
        }));
    }

    const coverage = Array.isArray(data.coverage) ? data.coverage : [];
    if (coverage.length && Number(data.totalCustomers) > 0) {
        initChart('[data-nx10-chart="coverage"]', () => ({
            animation: !reducedMotion,
            animationDuration: 420,
            grid: { left: 8, right: 38, top: 6, bottom: 4, containLabel: true },
            tooltip: {
                ...tooltipBase,
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: params => {
                    const point = params?.[0];
                    const source = coverage[point?.dataIndex ?? 0];
                    if (!source) return '';
                    if (!source.available) {
                        return tooltipShell('Record governance', source.label || 'Coverage', [
                            { label: 'Availability', value: 'Not Available' }
                        ]);
                    }
                    return tooltipShell('Record governance', source.label, [
                        { label: 'Covered records', value: Number(source.value || 0).toLocaleString() },
                        { label: 'Company total', value: Number(data.totalCustomers).toLocaleString() }
                    ]);
                }
            },
            xAxis: { type: 'value', max: Number(data.totalCustomers), axisLabel: { color: labelColor, fontSize: 9 }, splitLine: { lineStyle: { color: axisColor } }, axisLine: { show: false }, axisTick: { show: false } },
            yAxis: { type: 'category', inverse: true, data: coverage.map(item => item.label), axisLabel: { color: textColor, fontSize: 9, width: 90, overflow: 'truncate' }, axisLine: { show: false }, axisTick: { show: false } },
            series: [{
                type: 'bar', barWidth: 8,
                itemStyle: { borderRadius: 6 },
                data: coverage.map((item, index) => ({ value: item.available ? Number(item.value || 0) : 0, itemStyle: { color: item.available ? palette[index % palette.length] : 'rgba(91,113,126,.20)' } })),
                label: { show: true, position: 'right', color: '#8fa9b6', fontSize: 9, formatter: params => coverage[params.dataIndex]?.available ? `${params.value}/${data.totalCustomers}` : 'N/A' }
            }]
        }));
    }

    const projectStatuses = Array.isArray(data.projectStatuses) ? data.projectStatuses.filter(item => Number(item.value) > 0) : [];
    if (projectStatuses.length) {
        initChart('[data-nx10-chart="projects"]', () => ({
            animation: !reducedMotion,
            animationDuration: 420,
            grid: { left: 8, right: 34, top: 6, bottom: 4, containLabel: true },
            tooltip: {
                ...tooltipBase,
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: params => tooltipShell('Linked delivery exposure', params?.[0]?.name || 'Project state', [
                    { label: 'Current project records', value: Number(params?.[0]?.value || 0).toLocaleString() }
                ])
            },
            xAxis: { type: 'value', minInterval: 1, axisLabel: { color: labelColor, fontSize: 9, precision: 0 }, splitLine: { lineStyle: { color: axisColor } }, axisLine: { show: false }, axisTick: { show: false } },
            yAxis: { type: 'category', inverse: true, data: projectStatuses.map(item => item.label), axisLabel: { color: textColor, fontSize: 9, width: 88, overflow: 'truncate' }, axisLine: { show: false }, axisTick: { show: false } },
            series: [{ type: 'bar', barWidth: 8, data: projectStatuses.map((item, index) => ({ value: Number(item.value), itemStyle: { color: palette[index % palette.length], borderRadius: 6 } })), label: { show: true, position: 'right', color: '#8fa9b6', fontSize: 9 } }]
        }));
    }

    const mapElement = root.querySelector('[data-nx10-map]');
    const mapFallback = root.querySelector('[data-nx10-map-fallback]');
    const mapPoints = Array.isArray(data.mapPoints) ? data.mapPoints : [];
    if (mapElement && window.echarts && mapPoints.length) {
        fetch(root.dataset.mapUrl, { credentials: 'same-origin' })
            .then(response => {
                if (!response.ok) throw new Error('Map unavailable');
                return response.json();
            })
            .then(geojson => {
                window.echarts.registerMap('nx10-indonesia', geojson);
                const chart = window.echarts.init(mapElement, null, { renderer: 'canvas' });
                chart.setOption({
                    animation: !reducedMotion,
                    animationDuration: 500,
                    tooltip: {
                        ...tooltipBase,
                        trigger: 'item',
                        formatter: params => {
                            const point = params.data || {};
                            const project = point.project || 'Project';
                            const customer = point.customer || 'Customer Not Recorded';
                            const location = [point.city, point.province].filter(Boolean).join(', ') || 'Location name not recorded';
                            return tooltipShell('Exact project coordinate', project, [
                                { label: 'Customer', value: customer },
                                { label: 'Location', value: location },
                                { label: 'Project status', value: point.status || 'Recorded Project' },
                                { label: 'Coordinates', value: `${Number(point.latitude).toFixed(5)}, ${Number(point.longitude).toFixed(5)}` }
                            ]);
                        }
                    },
                    geo: {
                        map: 'nx10-indonesia', roam: true, zoom: 1.07,
                        itemStyle: { areaColor: '#0a2638', borderColor: '#174963', borderWidth: .7 },
                        emphasis: { itemStyle: { areaColor: '#10334a' }, label: { show: false } },
                        select: { disabled: true }, silent: false
                    },
                    series: [{
                        type: 'scatter', coordinateSystem: 'geo', symbolSize: 8,
                        itemStyle: { color: '#29c5c5', borderColor: '#b8f1ee', borderWidth: 1, shadowBlur: 8, shadowColor: 'rgba(41,197,197,.35)' },
                        emphasis: { scale: 1.5 },
                        data: mapPoints.map(point => ({
                            name: point.project,
                            value: [Number(point.longitude), Number(point.latitude), 1],
                            ...point
                        }))
                    }]
                });
                mapElement.dataset.ready = 'true';
                mapFallback?.setAttribute('hidden', 'hidden');
                charts.push(chart);
            })
            .catch(() => {
                mapFallback?.removeAttribute('hidden');
            });
    }

    const advanced = root.querySelector('[data-nx10-advanced-filters]');
    const toggle = root.querySelector('[data-nx10-filter-toggle]');
    toggle?.addEventListener('click', () => {
        const willOpen = advanced?.hasAttribute('hidden');
        if (!advanced) return;
        if (willOpen) advanced.removeAttribute('hidden'); else advanced.setAttribute('hidden', 'hidden');
        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        const marker = toggle.querySelector('span[aria-hidden="true"]');
        if (marker) marker.textContent = willOpen ? '−' : '+';
    });

    const closeMenus = (except = null) => {
        root.querySelectorAll('[data-nx10-menu]').forEach(menu => {
            if (menu === except) return;
            menu.hidden = true;
            menu.closest('.nx10-actions-cell')?.querySelector('[data-nx10-menu-trigger]')?.setAttribute('aria-expanded', 'false');
        });
    };

    root.querySelectorAll('[data-nx10-menu-trigger]').forEach(trigger => {
        trigger.addEventListener('click', event => {
            event.stopPropagation();
            const menu = trigger.parentElement?.querySelector('[data-nx10-menu]');
            if (!menu) return;
            const willOpen = menu.hidden;
            closeMenus(willOpen ? menu : null);
            menu.hidden = !willOpen;
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) menu.querySelector('a,button,[role="menuitem"]:not([aria-disabled="true"])')?.focus({ preventScroll: true });
        });
    });
    document.addEventListener('click', () => closeMenus());
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeMenus();
    });

    root.querySelectorAll('[data-nx10-archive-form]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.confirm('Archive this Customer? This action is only allowed when no open linked projects remain.')) {
                event.preventDefault();
            }
        });
    });

    const resize = () => charts.forEach(chart => {
        try { chart.resize(); } catch (_) { /* no-op */ }
    });
    let resizeFrame = null;
    window.addEventListener('resize', () => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(resize);
    }, { passive: true });

    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(() => {
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(resize);
        });
        observer.observe(root);
    }
})();
