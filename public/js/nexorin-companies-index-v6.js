(() => {
    'use strict';

    const page = document.querySelector('[data-nx6-page]');
    if (!page) return;

    const charts = new Map();
    const observers = new Map();
    const parse = (el) => {
        try { return JSON.parse(el.dataset.series || '[]'); } catch (_) { return []; }
    };
    const text = '#8aa6b5';
    const dim = '#557487';
    const line = 'rgba(84,139,163,.14)';
    const palette = ['#2aa9ed', '#24c6b1', '#8d77e8', '#e6a63b', '#e76d68', '#688493'];

    const tooltip = {
        trigger: 'item',
        confine: true,
        backgroundColor: 'rgba(3,16,26,.96)',
        borderColor: 'rgba(80,142,169,.32)',
        borderWidth: 1,
        textStyle: { color: '#d8e7ef', fontSize: 10 },
        extraCssText: 'box-shadow:0 10px 30px rgba(0,0,0,.35);border-radius:5px;'
    };

    function createChart(el, option, onResize = null) {
        if (!window.echarts || !el || charts.has(el)) return null;
        const chart = window.echarts.init(el, null, { renderer: 'canvas' });
        chart.setOption(option, { notMerge: true });
        charts.set(el, chart);

        const syncAfterResize = () => {
            chart.resize();
            if (typeof onResize === 'function') onResize(chart, el);
        };

        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(syncAfterResize);
            observer.observe(el);
            observers.set(el, observer);
        } else {
            window.addEventListener('resize', syncAfterResize, { passive: true });
            observers.set(el, { disconnect: () => window.removeEventListener('resize', syncAfterResize) });
        }

        if (typeof onResize === 'function') onResize(chart, el);
        return chart;
    }

    function syncDonutCenter(chart, el, total, centerX = 0.31, centerY = 0.52) {
        if (!chart || chart.isDisposed()) return;
        const x = Math.round(chart.getWidth() * centerX);
        const y = Math.round(chart.getHeight() * centerY);
        chart.setOption({
            graphic: [{
                id: 'nx6-donut-center',
                type: 'group',
                x,
                y,
                silent: true,
                bounding: 'raw',
                children: [
                    {
                        type: 'text',
                        x: 0,
                        y: -7,
                        style: {
                            text: total.toLocaleString(),
                            fill: '#eaf6fb',
                            font: '700 14px "JetBrains Mono", monospace',
                            align: 'center',
                            verticalAlign: 'middle'
                        }
                    },
                    {
                        type: 'text',
                        x: 0,
                        y: 10,
                        style: {
                            text: total === 1 ? 'Company' : 'Companies',
                            fill: '#557487',
                            font: '500 8px Inter, sans-serif',
                            align: 'center',
                            verticalAlign: 'middle'
                        }
                    }
                ]
            }]
        }, { lazyUpdate: true });
    }

    function initSegments(el) {
        const data = parse(el).filter((item) => Number(item.value) > 0);
        if (!data.length) return emptyChart(el, 'No segment data');
        const total = data.reduce((sum, item) => sum + Number(item.value || 0), 0);
        createChart(el, {
            animationDuration: 420,
            color: palette,
            tooltip: { ...tooltip, formatter: (p) => `${escapeHtml(p.name)}<br><b>${Number(p.value).toLocaleString()}</b> companies · ${p.percent}%` },
            legend: { orient: 'vertical', right: 4, top: 'middle', itemWidth: 6, itemHeight: 6, textStyle: { color: text, fontSize: 8 }, formatter: (name) => name.length > 18 ? `${name.slice(0, 17)}…` : name },
            series: [{ type: 'pie', radius: ['48%', '70%'], center: ['31%', '52%'], minAngle: 3, avoidLabelOverlap: true, itemStyle: { borderColor: '#061725', borderWidth: 1 }, label: { show: false }, data: data.map((item) => ({ name: item.label, value: item.value })) }]
        }, (chart, chartEl) => syncDonutCenter(chart, chartEl, total, 0.31, 0.52));
    }

    function initGovernance(el) {
        const raw = parse(el);
        const colors = { ready: '#28d19c', attention: '#edae35', expiring: '#ef8c3d', incomplete: '#ef5f66', not_assessed: '#637d8e' };
        const data = raw.filter((item) => Number(item.value) > 0);
        if (!data.length) return emptyChart(el, 'No governance evidence');
        const total = data.reduce((sum, item) => sum + Number(item.value || 0), 0);
        createChart(el, {
            color: data.map((item) => colors[item.state] || '#688493'),
            tooltip: { ...tooltip, formatter: (p) => `${escapeHtml(p.name)}<br><b>${Number(p.value).toLocaleString()}</b> companies · ${p.percent}%` },
            legend: { right: 4, top: 'middle', orient: 'vertical', itemWidth: 6, itemHeight: 6, textStyle: { color: text, fontSize: 8 } },
            series: [{ type: 'pie', radius: ['49%', '70%'], center: ['31%', '52%'], label: { show: false }, itemStyle: { borderColor: '#061725', borderWidth: 1 }, data: data.map((item) => ({ name: item.label, value: item.value })) }]
        }, (chart, chartEl) => syncDonutCenter(chart, chartEl, total, 0.31, 0.52));
    }

    function initPortfolio(el) {
        const data = parse(el).filter((item) => Number(item.value) > 0).sort((a, b) => Number(b.value) - Number(a.value));
        if (!data.length) return emptyChart(el, 'No accepted portfolio value');
        createChart(el, {
            grid: { left: 56, right: 14, top: 8, bottom: 22 },
            tooltip: { ...tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (items) => { const p = items[0]; return `${escapeHtml(p.name)}<br><b>${formatIDR(p.value)}</b>`; } },
            xAxis: { type: 'value', axisLabel: { color: dim, fontSize: 7, formatter: compactIDR }, splitLine: { lineStyle: { color: line } }, axisLine: { show: false }, axisTick: { show: false } },
            yAxis: { type: 'category', inverse: true, data: data.map((item) => item.label), axisLabel: { color: text, fontSize: 7, width: 48, overflow: 'truncate' }, axisLine: { show: false }, axisTick: { show: false } },
            series: [{ type: 'bar', data: data.map((item) => item.value), barWidth: 8, itemStyle: { borderRadius: [0, 3, 3, 0], color: (p) => palette[p.dataIndex % palette.length] } }]
        });
    }

    function initProjects(el) {
        const data = parse(el).filter((item) => Number(item.value) > 0).sort((a, b) => Number(b.value) - Number(a.value));
        if (!data.length) return emptyChart(el, 'No active projects');
        createChart(el, {
            grid: { left: 62, right: 14, top: 8, bottom: 20 },
            tooltip: { ...tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (items) => `${escapeHtml(items[0].name)}<br><b>${Number(items[0].value).toLocaleString()}</b> active projects` },
            xAxis: { type: 'value', minInterval: 1, axisLabel: { color: dim, fontSize: 7 }, splitLine: { lineStyle: { color: line } }, axisLine: { show: false }, axisTick: { show: false } },
            yAxis: { type: 'category', inverse: true, data: data.map((item) => item.label), axisLabel: { color: text, fontSize: 7, width: 55, overflow: 'truncate' }, axisLine: { show: false }, axisTick: { show: false } },
            series: [{ type: 'bar', barWidth: 8, data: data.map((item) => item.value), itemStyle: { color: '#2aa9ed', borderRadius: [0, 3, 3, 0] } }]
        });
    }

    async function initRegion(el) {
        const regions = parse(el);
        try {
            const response = await fetch(page.dataset.mapUrl, { headers: { Accept: 'application/geo+json,application/json' } });
            if (!response.ok) throw new Error(`Map HTTP ${response.status}`);
            const geo = await response.json();
            window.echarts.registerMap('nx6-indonesia', geo);
            const points = regions
                .filter((item) => Number.isFinite(Number(item.latitude)) && Number.isFinite(Number(item.longitude)))
                .map((item) => ({ name: item.region, value: [Number(item.longitude), Number(item.latitude), Number(item.companies)] }));
            createChart(el, {
                geo: { map: 'nx6-indonesia', roam: true, zoom: 1.13, center: [118.0, -2.8], silent: false, itemStyle: { areaColor: '#12354a', borderColor: '#4d7790', borderWidth: .7 }, emphasis: { itemStyle: { areaColor: '#17475f' }, label: { show: false } }, label: { show: false } },
                tooltip: { ...tooltip, formatter: (p) => p.seriesType === 'effectScatter' ? `${escapeHtml(p.name)}<br><b>${Number(p.value[2]).toLocaleString()}</b> companies with recorded coverage` : 'Indonesia' },
                series: points.length ? [{ name: 'Company coverage', type: 'effectScatter', coordinateSystem: 'geo', data: points, symbolSize: (value) => Math.max(5, Math.min(13, 4 + Math.sqrt(Number(value[2] || 0)) * 2)), showEffectOn: 'render', rippleEffect: { scale: 2.3, brushType: 'stroke' }, itemStyle: { color: '#20d6dc', shadowBlur: 10, shadowColor: 'rgba(32,214,220,.65)' } }] : []
            });
            if (!points.length) overlayEmpty(el, 'No region reference coordinates recorded');
        } catch (error) {
            emptyChart(el, 'Map data unavailable');
            el.dataset.mapError = error instanceof Error ? error.message : 'Map load failed';
        }
    }

    function emptyChart(el, message) {
        createChart(el, { graphic: [{ type: 'group', left: 'center', top: 'middle', children: [
            { type: 'text', style: { text: message, fill: '#688493', font: '600 9px Inter, sans-serif', textAlign: 'center' } },
            { type: 'text', top: 15, style: { text: 'Truthful empty state', fill: '#425f70', font: '7px Inter, sans-serif', textAlign: 'center' } }
        ]}] });
    }

    function overlayEmpty(el, message) {
        const marker = document.createElement('span');
        marker.className = 'nx6-map-empty-overlay';
        marker.textContent = message;
        el.appendChild(marker);
    }

    function compactIDR(value) {
        const n = Number(value || 0);
        if (n >= 1e12) return `Rp${(n / 1e12).toFixed(1)}T`;
        if (n >= 1e9) return `Rp${(n / 1e9).toFixed(1)}B`;
        if (n >= 1e6) return `Rp${(n / 1e6).toFixed(0)}M`;
        return `Rp${n.toLocaleString('id-ID')}`;
    }
    function formatIDR(value) { return `Rp ${Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 })}`; }
    function escapeHtml(value) { return String(value ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c])); }

    function initCharts() {
        page.querySelectorAll('[data-nx6-chart]').forEach((el) => {
            switch (el.dataset.nx6Chart) {
                case 'region': initRegion(el); break;
                case 'segments': initSegments(el); break;
                case 'portfolio': initPortfolio(el); break;
                case 'governance': initGovernance(el); break;
                case 'projects': initProjects(el); break;
            }
        });
    }

    function initSaveView() {
        const modal = page.querySelector('[data-nx6-save-modal]');
        const open = page.querySelector('[data-nx6-save-open]');
        if (!modal || !open) return;
        let priorFocus = null;
        const close = () => {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.removeProperty('overflow');
            priorFocus?.focus?.();
        };
        const show = () => {
            priorFocus = document.activeElement;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            modal.querySelector('input[name="name"]')?.focus();
        };
        open.addEventListener('click', show);
        modal.querySelectorAll('[data-nx6-save-close]').forEach((el) => el.addEventListener('click', close));
        modal.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    }

    function initRowMenus() {
        const menus = () => page.querySelectorAll('.nx6-action-menu:not([hidden])');

        const closeMenu = (menu) => {
            if (!menu) return;
            menu.hidden = true;
            menu.style.removeProperty('top');
            menu.style.removeProperty('left');
            const owner = menu.closest('.nx6-row-actions')?.querySelector('[data-nx6-menu]');
            owner?.setAttribute('aria-expanded', 'false');
        };

        const closeAll = (except = null) => {
            menus().forEach((menu) => { if (menu !== except) closeMenu(menu); });
        };

        const positionMenu = (trigger, menu) => {
            const rect = trigger.getBoundingClientRect();
            const gap = 7;
            const viewportPad = 10;
            menu.hidden = false;
            menu.style.visibility = 'hidden';
            menu.style.top = '0px';
            menu.style.left = '0px';

            const menuRect = menu.getBoundingClientRect();
            let left = rect.right - menuRect.width;
            left = Math.max(viewportPad, Math.min(left, window.innerWidth - menuRect.width - viewportPad));

            let top = rect.bottom + gap;
            if (top + menuRect.height > window.innerHeight - viewportPad) {
                top = Math.max(viewportPad, rect.top - menuRect.height - gap);
            }

            menu.style.left = `${Math.round(left)}px`;
            menu.style.top = `${Math.round(top)}px`;
            menu.style.visibility = '';
        };

        page.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-nx6-menu]');
            if (trigger) {
                const menu = trigger.parentElement?.querySelector('.nx6-action-menu');
                if (!menu) return;
                const wasOpen = !menu.hidden;
                closeAll(menu);
                if (wasOpen) {
                    closeMenu(menu);
                } else {
                    positionMenu(trigger, menu);
                    trigger.setAttribute('aria-expanded', 'true');
                }
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            if (!event.target.closest('.nx6-action-menu')) closeAll();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeAll();
        });
        window.addEventListener('resize', () => closeAll(), { passive: true });
        window.addEventListener('scroll', () => closeAll(), { passive: true, capture: true });
    }


    function initMoreFilters() {
        const trigger = page.querySelector('[data-nx6-more-filters]');
        const rail = page.querySelector('.nx6-filter-rail');
        if (!trigger || !rail) return;
        trigger.addEventListener('click', () => {
            rail.scrollTo({ top: Math.max(0, rail.querySelector('.nx6-advanced')?.offsetTop - 16 || 0), behavior: 'smooth' });
            const first = rail.querySelector('.nx6-advanced select');
            window.setTimeout(() => first?.focus(), 220);
        });
    }

    function initExportState() {
        const exportLink = page.querySelector('[data-nx6-export]');
        if (!exportLink) return;
        exportLink.addEventListener('click', () => {
            exportLink.classList.add('is-processing');
            const original = exportLink.lastChild?.textContent;
            if (exportLink.lastChild) exportLink.lastChild.textContent = ' Exporting…';
            window.setTimeout(() => {
                exportLink.classList.remove('is-processing');
                if (original && exportLink.lastChild) exportLink.lastChild.textContent = original;
            }, 2500);
        });
    }

    function teardown() {
        observers.forEach((observer) => observer.disconnect());
        observers.clear();
        charts.forEach((chart) => { if (!chart.isDisposed()) chart.dispose(); });
        charts.clear();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCharts, { once: true });
    else initCharts();
    initSaveView();
    initRowMenus();
    initMoreFilters();
    initExportState();
    window.addEventListener('pagehide', teardown, { once: true });
})();
