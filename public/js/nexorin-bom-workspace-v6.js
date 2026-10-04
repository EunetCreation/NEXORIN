(() => {
    'use strict';

    const root = document.querySelector('[data-nxbom3]');
    if (!root) return;

    const rows = Array.from(root.querySelectorAll('[data-nxbom3-row]'));
    const search = root.querySelector('[data-nxbom3-search]');
    const category = root.querySelector('[data-nxbom3-category]');
    const state = root.querySelector('[data-nxbom3-state]');
    const reset = root.querySelector('[data-nxbom3-reset]');
    const result = root.querySelector('[data-nxbom3-result]');
    const range = root.querySelector('[data-nxbom3-range]');
    const pagination = root.querySelector('[data-nxbom3-pagination]');
    const pageSize = 14;
    let currentPage = 1;
    let filteredRows = rows;

    const normalize = value => String(value || '').trim().toLowerCase();

    function filterRows() {
        const query = normalize(search?.value);
        const categoryValue = category?.value || 'all';
        const stateValue = state?.value || 'all';
        filteredRows = rows.filter(row => {
            const searchMatch = !query || normalize(row.dataset.search).includes(query);
            const categoryMatch = categoryValue === 'all' || row.dataset.category === categoryValue;
            const stateMatch = stateValue === 'all' || row.dataset.state === stateValue;
            return searchMatch && categoryMatch && stateMatch;
        });
        currentPage = 1;
        renderRows();
    }

    function paginationButton(label, page, disabled = false, active = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.disabled = disabled;
        if (active) button.classList.add('is-active');
        button.addEventListener('click', () => {
            currentPage = page;
            renderRows();
        });
        return button;
    }

    function renderPagination(pageCount) {
        if (!pagination) return;
        pagination.replaceChildren();
        if (pageCount <= 1) return;
        pagination.append(paginationButton('‹', Math.max(1, currentPage - 1), currentPage === 1));
        const visible = new Set([1, pageCount, currentPage, currentPage - 1, currentPage + 1].filter(v => v >= 1 && v <= pageCount));
        let previous = 0;
        [...visible].sort((a, b) => a - b).forEach(page => {
            if (previous && page - previous > 1) {
                const gap = document.createElement('span');
                gap.textContent = '…';
                gap.style.color = '#536f7e';
                gap.style.fontSize = '8px';
                pagination.append(gap);
            }
            pagination.append(paginationButton(String(page), page, false, page === currentPage));
            previous = page;
        });
        pagination.append(paginationButton('›', Math.min(pageCount, currentPage + 1), currentPage === pageCount));
    }

    function renderRows() {
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(filteredRows.length, start + pageSize);
        const visible = new Set(filteredRows.slice(start, end));
        rows.forEach(row => { row.hidden = !visible.has(row); });
        if (result) result.textContent = `${filteredRows.length} ${filteredRows.length === 1 ? 'line' : 'lines'}`;
        if (range) range.textContent = filteredRows.length ? `Showing ${start + 1}–${end} of ${filteredRows.length} items` : 'No matching line items';
        renderPagination(pageCount);
    }

    [search, category, state].forEach(control => {
        if (!control) return;
        control.addEventListener(control === search ? 'input' : 'change', filterRows, { passive: true });
    });
    reset?.addEventListener('click', () => {
        if (search) search.value = '';
        if (category) category.value = 'all';
        if (state) state.value = 'all';
        filterRows();
    });
    root.querySelector('[data-nxbom3-review-filter]')?.addEventListener('click', () => {
        if (state) state.value = 'review';
        filterRows();
    });

    root.querySelectorAll('[data-nxbom3-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            const message = form.dataset.nxbom3Confirm || 'Continue with this controlled action?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });

    const pricingPanel = root.querySelector('[data-nxbom3-pricing-panel]');
    const pricingDialog = pricingPanel?.querySelector('.nxbom3-pricing-dialog');
    let pricingReturnFocus = null;

    const pricingFocusable = () => pricingDialog
        ? Array.from(pricingDialog.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'))
            .filter(element => !element.hidden && element.offsetParent !== null)
        : [];

    function openPricingPanel(trigger) {
        if (!pricingPanel || !pricingDialog) return;
        pricingReturnFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;
        pricingPanel.hidden = false;
        document.documentElement.classList.add('nxbom3-modal-open');
        root.querySelector('.nxbom3-action-menu')?.removeAttribute('open');
        requestAnimationFrame(() => {
            pricingPanel.classList.add('is-open');
            pricingDialog.focus({ preventScroll: true });
        });
    }

    function closePricingPanel() {
        if (!pricingPanel || pricingPanel.hidden) return;
        pricingPanel.classList.remove('is-open');
        document.documentElement.classList.remove('nxbom3-modal-open');
        pricingPanel.hidden = true;
        if (pricingReturnFocus instanceof HTMLElement && document.contains(pricingReturnFocus)) {
            pricingReturnFocus.focus({ preventScroll: true });
        }
        pricingReturnFocus = null;
    }

    root.querySelectorAll('[data-nxbom3-toggle-pricing]').forEach(button => {
        button.addEventListener('click', () => openPricingPanel(button));
    });
    root.querySelectorAll('[data-nxbom3-close-pricing]').forEach(button => button.addEventListener('click', closePricingPanel));
    pricingPanel?.addEventListener('mousedown', event => {
        if (event.target === pricingPanel) closePricingPanel();
    });
    pricingPanel?.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closePricingPanel();
            return;
        }
        if (event.key !== 'Tab' || !pricingDialog) return;
        const focusable = pricingFocusable();
        if (!focusable.length) {
            event.preventDefault();
            pricingDialog.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    // Portal the modal to <body> so transformed/layout ancestors cannot shift the
    // fixed overlay. This keeps the dialog centered against the real viewport and
    // blurs the complete BOM workspace, including the persistent sidebar.
    if (pricingPanel && pricingPanel.parentElement !== document.body) {
        document.body.appendChild(pricingPanel);
    }

    renderRows();

    let chartPayload = {};
    try {
        chartPayload = JSON.parse(root.querySelector('[data-nxbom3-chart-payload]')?.textContent || '{}');
    } catch (_) {
        chartPayload = {};
    }

    const chartNodes = Array.from(root.querySelectorAll('[data-nxbom3-chart]'));
    let chartInstances = [];
    let resizeObserver;

    const compactMoney = value => {
        const n = Number(value || 0);
        if (n >= 1e9) return `Rp ${(n / 1e9).toFixed(2)} B`;
        if (n >= 1e6) return `Rp ${(n / 1e6).toFixed(1)} M`;
        if (n >= 1e3) return `Rp ${(n / 1e3).toFixed(1)} K`;
        return `Rp ${Math.round(n).toLocaleString('id-ID')}`;
    };

    function baseTextStyle() {
        return { color: '#7893a1', fontFamily: 'Inter, Segoe UI, Arial, sans-serif', fontSize: 9 };
    }

    function initComposition(node) {
        const data = Array.isArray(chartPayload.composition) ? chartPayload.composition : [];
        if (!data.length) return null;
        const chart = window.echarts.init(node, null, { renderer: 'canvas' });
        const colors = ['#2e8ef0', '#25c8a8', '#f0ae29', '#786bd8', '#ef5e61', '#58a7b9', '#97b66a', '#6e8592'];
        const total = data.reduce((sum, row) => sum + Number(row.value || 0), 0);
        const centerX = '30%';
        chart.setOption({
            animationDuration: 480,
            tooltip: { trigger: 'item', backgroundColor: '#071c2a', borderColor: '#1c4559', textStyle: { color: '#dcebf0', fontSize: 9 }, formatter: p => `${p.name}<br><b>${compactMoney(p.value)}</b> · ${p.percent}%` },
            color: colors,
            title: {
                text: compactMoney(total),
                subtext: chartPayload.valueLabel || 'Project value',
                left: centerX,
                top: '42%',
                textAlign: 'center',
                itemGap: 4,
                textStyle: { color: '#e5f1f4', fontFamily: 'Inter, Segoe UI, Arial, sans-serif', fontSize: 13, fontWeight: 600, lineHeight: 16 },
                subtextStyle: { color: '#607d8c', fontFamily: 'Inter, Segoe UI, Arial, sans-serif', fontSize: 7, fontWeight: 400, lineHeight: 10 }
            },
            legend: { type: 'scroll', orient: 'vertical', right: 1, top: 'center', width: '42%', itemWidth: 7, itemHeight: 7, itemGap: 8, textStyle: { ...baseTextStyle(), fontSize: 7.5 }, formatter: name => name.length > 19 ? `${name.slice(0, 18)}…` : name },
            series: [{
                type: 'pie', radius: ['49%', '72%'], center: [centerX, '50%'], avoidLabelOverlap: true,
                itemStyle: { borderColor: '#061927', borderWidth: 2 }, label: { show: false }, labelLine: { show: false },
                emphasis: { scale: true, scaleSize: 4 },
                data: data.map(row => ({ name: row.label, value: row.value }))
            }],
            media: [{
                query: { maxWidth: 340 },
                option: {
                    title: { left: '50%' },
                    legend: { show: false },
                    series: [{ center: ['50%', '50%'], radius: ['47%', '70%'] }]
                }
            }]
        });
        return chart;
    }

    function initReadiness(node) {
        const data = Array.isArray(chartPayload.readiness) ? chartPayload.readiness : [];
        if (!data.length) return null;
        const rows = data.slice(0, 8).reverse();
        const chart = window.echarts.init(node, null, { renderer: 'canvas' });
        chart.setOption({
            animationDuration: 420,
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, backgroundColor: '#071c2a', borderColor: '#1c4559', textStyle: { color: '#dcebf0', fontSize: 9 }, formatter: params => { const idx = params?.[0]?.dataIndex ?? 0; const row = rows[idx] || {}; return `${row.label}<br><b>${row.readiness}%</b> priced readiness<br>${row.priced || 0}/${row.eligible || 0} eligible lines`; } },
            grid: { left: 82, right: 22, top: 9, bottom: 9, containLabel: false },
            xAxis: { type: 'value', min: 0, max: 100, show: false },
            yAxis: { type: 'category', data: rows.map(row => row.label), axisLine: { show: false }, axisTick: { show: false }, axisLabel: { color: '#76909d', fontSize: 7, width: 74, overflow: 'truncate', align: 'right', margin: 8 } },
            series: [
                { type: 'bar', data: rows.map(() => 100), barWidth: 7, barGap: '-100%', silent: true, itemStyle: { color: '#173443', borderRadius: 5 } },
                { type: 'bar', data: rows.map(row => ({ value: row.readiness, itemStyle: { color: row.readiness >= 90 ? '#23c6a2' : (row.readiness >= 65 ? '#eba82d' : '#e45d62') } })), barWidth: 7, itemStyle: { borderRadius: 5 }, label: { show: true, position: 'right', formatter: '{c}%', color: '#8aa5b1', fontSize: 7 } }
            ]
        });
        return chart;
    }

    function initMargin(node) {
        const margin = chartPayload.margin || {};
        const actual = Math.max(0, Number(margin.actual || 0));
        const maximum = Math.max(20, Number(margin.maximum || 25), actual + 3);
        const minimum = Math.max(0, Number(margin.minimum || 0));
        const chart = window.echarts.init(node, null, { renderer: 'canvas' });
        chart.setOption({
            animationDuration: 520,
            series: [{
                type: 'gauge', min: 0, max: maximum, startAngle: 205, endAngle: -25, center: ['50%', '56%'], radius: '88%',
                progress: { show: true, width: 13, roundCap: true, itemStyle: { color: actual >= minimum ? '#26c7a7' : '#efac2e' } },
                axisLine: { lineStyle: { width: 13, color: [[Math.min(1, minimum / maximum), '#dd9d28'], [1, '#173443']] } },
                pointer: { show: false }, anchor: { show: false }, axisTick: { show: false }, splitLine: { show: false },
                axisLabel: { distance: -29, color: '#5f7a89', fontSize: 7, formatter: value => (value === 0 || Math.abs(value - maximum) < .6 ? `${Math.round(value)}%` : '') },
                title: { show: true, offsetCenter: [0, '35%'], color: '#607d8c', fontSize: 7 },
                detail: { valueAnimation: true, offsetCenter: [0, '0%'], formatter: value => `${Number(value).toFixed(1)}%`, color: '#e8f3f6', fontSize: 23, fontWeight: 500 },
                data: [{ value: actual, name: 'Projected Margin' }]
            }],
            graphic: [{ type: 'text', left: 7, top: 9, style: { text: `${minimum.toFixed(1)}%\nMinimum`, fill: '#dba43b', font: '7px Inter, Segoe UI, sans-serif', lineHeight: 10 } }]
        });
        return chart;
    }

    function initCharts() {
        if (!window.echarts || chartInstances.length) return;
        chartInstances = chartNodes.map(node => {
            const type = node.dataset.nxbom3Chart;
            let instance = null;
            if (type === 'composition') instance = initComposition(node);
            if (type === 'readiness') instance = initReadiness(node);
            if (type === 'margin') instance = initMargin(node);
            if (instance) node.classList.add('is-echarts-ready');
            return instance;
        }).filter(Boolean);
        if (chartInstances.length && 'ResizeObserver' in window) {
            let frame = 0;
            resizeObserver = new ResizeObserver(() => {
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => chartInstances.forEach(chart => chart.resize()));
            });
            root.querySelector('[data-nxbom3-analytics]') && resizeObserver.observe(root.querySelector('[data-nxbom3-analytics]'));
        }
    }

    let loadingEcharts = false;
    function loadEcharts() {
        if (window.echarts) { initCharts(); return; }
        if (loadingEcharts) return;
        const src = root.dataset.echartsSrc;
        if (!src) return;
        loadingEcharts = true;
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = initCharts;
        script.onerror = () => { loadingEcharts = false; };
        document.head.appendChild(script);
    }

    const analytics = root.querySelector('[data-nxbom3-analytics]');
    if (analytics && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                observer.disconnect();
                loadEcharts();
            }
        }, { rootMargin: '320px 0px' });
        observer.observe(analytics);
    } else {
        loadEcharts();
    }

    window.addEventListener('pagehide', () => {
        document.documentElement.classList.remove('nxbom3-modal-open');
        resizeObserver?.disconnect();
        chartInstances.forEach(chart => chart.dispose());
        chartInstances = [];
    }, { once: true });
})();
