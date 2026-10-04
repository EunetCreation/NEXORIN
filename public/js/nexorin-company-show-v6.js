(() => {
    'use strict';

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const root = document.querySelector('[data-nx7-profile]');
    if (!root || typeof window.echarts === 'undefined') return;

    const charts = new Map();
    const observers = [];

    const parseJson = (value, fallback = []) => {
        try { return value ? JSON.parse(value) : fallback; } catch (_) { return fallback; }
    };

    const compactIdr = (value) => {
        const number = Number(value);
        if (!Number.isFinite(number)) return 'Not Available';
        if (Math.abs(number) >= 1e12) return `Rp ${(number / 1e12).toFixed(2)} T`;
        if (Math.abs(number) >= 1e9) return `Rp ${(number / 1e9).toFixed(2)} B`;
        if (Math.abs(number) >= 1e6) return `Rp ${(number / 1e6).toFixed(1)} M`;
        return `Rp ${Math.round(number).toLocaleString('id-ID')}`;
    };

    const createChart = (element, optionFactory) => {
        if (!element || charts.has(element)) return null;
        const chart = window.echarts.init(element, null, { renderer: 'canvas' });
        charts.set(element, chart);

        const apply = () => {
            const option = optionFactory(chart, element);
            if (option) chart.setOption(option, true);
        };
        apply();

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(() => {
                chart.resize();
                apply();
            });
            observer.observe(element);
            observers.push(observer);
        } else {
            window.addEventListener('resize', () => {
                chart.resize();
                apply();
            }, { passive: true });
        }
        return chart;
    };

    // Reuses the Page 006 V7 strategy: center copy is positioned from the live canvas dimensions.
    const syncDonutCenter = (chart, element, total) => {
        const x = Math.round(chart.getWidth() * 0.5);
        const y = Math.round(chart.getHeight() * 0.49);
        chart.setOption({
            graphic: [{
                id: 'nx7-donut-center',
                type: 'group',
                x,
                y,
                silent: true,
                bounding: 'raw',
                children: [
                    { type: 'text', x: 0, y: -7, style: { text: String(total), align: 'center', verticalAlign: 'middle', font: '700 16px Inter, sans-serif', fill: '#e9f7f9' } },
                    { type: 'text', x: 0, y: 8, style: { text: total === 1 ? 'ACTIVE PROJECT' : 'ACTIVE PROJECTS', align: 'center', verticalAlign: 'middle', font: '600 6px Inter, sans-serif', fill: '#658590' } },
                ],
            }],
        }, { lazyUpdate: true });
    };

    const lifecycle = parseJson(root.dataset.lifecycle);
    const lifecycleEl = root.querySelector('[data-nx7-chart="lifecycle"]');
    if (lifecycleEl && lifecycle.length) {
        const total = lifecycle.reduce((sum, row) => sum + Number(row.value || 0), 0);
        const colors = { draft: '#36c9c1', survey: '#46a8d1', engineering: '#8e82e6', quotation: '#dfb356', approved: '#40d290' };
        createChart(lifecycleEl, (chart, element) => {
            const option = {
                animation: !reducedMotion,
                animationDuration: reducedMotion ? 0 : 520,
                animationEasing: 'cubicOut',
                tooltip: {
                    trigger: 'item',
                    backgroundColor: 'rgba(6,19,28,.96)', borderColor: 'rgba(80,146,165,.25)',
                    textStyle: { color: '#d9ecef', fontSize: 9 },
                    formatter: ({ name, value }) => `${name}<br><b>${value}</b> project${Number(value) === 1 ? '' : 's'}`,
                },
                series: [{
                    type: 'pie', radius: ['64%', '80%'], center: ['50%', '49%'],
                    silent: false, avoidLabelOverlap: true, label: { show: false }, labelLine: { show: false },
                    itemStyle: { borderColor: '#0a1c27', borderWidth: 2 },
                    emphasis: { scale: true, scaleSize: 2 },
                    data: lifecycle.map(row => ({ name: row.label, value: Number(row.value || 0), itemStyle: { color: colors[row.key] || '#5c8290' } })),
                }],
            };
            requestAnimationFrame(() => syncDonutCenter(chart, element, total));
            return option;
        });
    }

    const financialTrend = parseJson(root.dataset.financialTrend);
    const financialEl = root.querySelector('[data-nx7-chart="financial"]');
    if (financialEl && financialTrend.length >= 2) {
        const currency = root.dataset.financialCurrency || '';
        createChart(financialEl, () => ({
            animation: !reducedMotion,
                animationDuration: reducedMotion ? 0 : 460,
                animationEasing: 'cubicOut',
            grid: { left: 8, right: 8, top: 11, bottom: 18, containLabel: true },
            tooltip: {
                trigger: 'axis', backgroundColor: 'rgba(6,19,28,.96)', borderColor: 'rgba(80,146,165,.25)',
                textStyle: { color: '#d9ecef', fontSize: 8 },
                formatter: (items) => {
                    const item = items?.[0];
                    if (!item) return '';
                    const value = Number(item.value);
                    return `${item.axisValue}<br><b>${currency === 'IDR' ? compactIdr(value) : `${currency} ${value.toLocaleString()}`}</b>`;
                },
            },
            xAxis: { type: 'category', boundaryGap: false, data: financialTrend.map(row => row.label), axisLine: { lineStyle: { color: 'rgba(88,137,154,.15)' } }, axisTick: { show: false }, axisLabel: { color: '#5f7f8b', fontSize: 7 } },
            yAxis: { type: 'value', splitNumber: 3, axisLine: { show: false }, axisTick: { show: false }, splitLine: { lineStyle: { color: 'rgba(88,137,154,.08)' } }, axisLabel: { color: '#557580', fontSize: 6, formatter: (value) => currency === 'IDR' ? compactIdr(value).replace('Rp ', '') : Number(value).toLocaleString() } },
            series: [{
                name: 'Annual Revenue', type: 'line', smooth: .28, showSymbol: true, symbolSize: 5,
                data: financialTrend.map(row => Number(row.annual_revenue || 0)),
                lineStyle: { width: 2, color: '#2bcac4' }, itemStyle: { color: '#4edbd0', borderColor: '#0a1c27', borderWidth: 2 },
                areaStyle: { color: { type: 'linear', x: 0, y: 0, x2: 0, y2: 1, colorStops: [{ offset: 0, color: 'rgba(43,202,196,.19)' }, { offset: 1, color: 'rgba(43,202,196,0)' }] } },
            }],
        }));
    }

    const portfolio = parseJson(root.dataset.portfolio);
    const portfolioEl = root.querySelector('[data-nx7-chart="portfolio"]');
    if (portfolioEl && portfolio.length) {
        const maxValue = Math.max(...portfolio.map(row => Number(row.value || 0)), 1);
        createChart(portfolioEl, () => ({
            animation: !reducedMotion,
                animationDuration: reducedMotion ? 0 : 440,
                animationEasing: 'cubicOut',
            grid: { left: 4, right: 8, top: 3, bottom: 2, containLabel: true },
            tooltip: {
                trigger: 'item', backgroundColor: 'rgba(6,19,28,.96)', borderColor: 'rgba(80,146,165,.25)',
                textStyle: { color: '#d9ecef', fontSize: 8 },
                formatter: ({ data }) => `${data.fullName}<br>${data.quote || 'Quotation'}<br><b>${compactIdr(data.value)}</b>`,
            },
            xAxis: { type: 'value', max: maxValue * 1.06, show: false },
            yAxis: { type: 'category', inverse: true, data: portfolio.map(row => row.project_number || row.project_name), axisLine: { show: false }, axisTick: { show: false }, axisLabel: { color: '#718f9b', fontSize: 6.5, width: 76, overflow: 'truncate' } },
            series: [{
                type: 'bar', barWidth: 7, showBackground: true,
                backgroundStyle: { color: 'rgba(75,124,141,.08)', borderRadius: 5 },
                itemStyle: { color: '#36b9c4', borderRadius: 5 },
                label: { show: true, position: 'right', color: '#7598a3', fontSize: 6, formatter: ({ value }) => compactIdr(value).replace('Rp ', '') },
                data: portfolio.map(row => ({ value: Number(row.value || 0), fullName: row.project_name, quote: row.quotation_number })),
            }],
        }));
    }

    const regionRows = parseJson(root.dataset.regions);
    const regionEl = root.querySelector('[data-nx7-chart="regions"]');
    if (regionEl) {
        const mapUrl = root.dataset.mapUrl;
        fetch(mapUrl, { credentials: 'same-origin' })
            .then(response => response.ok ? response.json() : Promise.reject(new Error(`Map response ${response.status}`)))
            .then(geojson => {
                if (!geojson || charts.has(regionEl)) return;
                window.echarts.registerMap('nx7-indonesia', geojson);
                createChart(regionEl, () => ({
                    animation: !reducedMotion,
                animationDuration: reducedMotion ? 0 : 520,
                animationEasing: 'cubicOut',
                    tooltip: {
                        trigger: 'item', backgroundColor: 'rgba(6,19,28,.97)', borderColor: 'rgba(80,146,165,.25)',
                        textStyle: { color: '#d9ecef', fontSize: 8 },
                        formatter: (params) => {
                            if (params.seriesType !== 'scatter') return params.name || 'Indonesia';
                            const data = params.data || {};
                            return `<b>${data.name}</b><br>${data.country || 'Country not recorded'}<br>Service Region · Reference Coordinate<br>${Number(data.value?.[1]).toFixed(5)}, ${Number(data.value?.[0]).toFixed(5)}`;
                        },
                    },
                    geo: {
                        map: 'nx7-indonesia', roam: true, silent: false, zoom: 1.05,
                        itemStyle: { areaColor: '#153846', borderColor: '#438092', borderWidth: .6 },
                        emphasis: { itemStyle: { areaColor: '#1a4655' }, label: { show: false } },
                        select: { disabled: true }, label: { show: false },
                    },
                    series: regionRows.length ? [{
                        type: 'scatter', coordinateSystem: 'geo', symbolSize: 7,
                        itemStyle: { color: '#42d4c7', shadowBlur: 8, shadowColor: 'rgba(66,212,199,.25)' },
                        data: regionRows.map(row => ({ name: row.region_name, country: row.country_name, value: [Number(row.longitude), Number(row.latitude)] })),
                    }] : [],
                }));
            })
            .catch(() => {
                regionEl.dataset.mapState = 'unavailable';
                regionEl.innerHTML = '<div style="height:100%;display:grid;place-items:center;color:#607d88;font:600 8px Inter,sans-serif">Indonesia map asset unavailable</div>';
            });
    }

    const tabs = [...root.querySelectorAll('[data-nx7-tabs] a')];
    const sections = tabs.map(tab => document.querySelector(tab.getAttribute('href'))).filter(Boolean);
    tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(item => item.classList.toggle('is-active', item === tab));
    }));
    if (typeof IntersectionObserver !== 'undefined' && sections.length) {
        const navObserver = new IntersectionObserver(entries => {
            const visible = entries.filter(entry => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (!visible) return;
            tabs.forEach(tab => tab.classList.toggle('is-active', tab.getAttribute('href') === `#${visible.target.id}`));
        }, { rootMargin: '-110px 0px -72% 0px', threshold: [0, .12, .35] });
        sections.forEach(section => navObserver.observe(section));
        observers.push(navObserver);
    }

    window.addEventListener('pagehide', () => {
        observers.forEach(observer => observer.disconnect?.());
        charts.forEach(chart => chart.dispose());
        charts.clear();
    }, { once: true });
})();
