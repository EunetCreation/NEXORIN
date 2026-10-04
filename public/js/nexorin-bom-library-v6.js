(() => {
    'use strict';

    const root = document.querySelector('[data-nxbom-library]');
    if (!root) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const charts = [];

    root.querySelectorAll('[data-nxbom-row]').forEach((row) => {
        const open = () => {
            const url = row.dataset.openUrl;
            if (url) window.location.assign(url);
        };

        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, select, textarea, form')) return;
            open();
        });

        row.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            open();
        });
    });

    root.querySelectorAll('[data-nxbom-scroll-target]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = document.getElementById(link.dataset.nxbomScrollTarget || '');
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
        });
    });

    const filterForm = root.querySelector('[data-nxbom-filter-form]');
    filterForm?.querySelectorAll('[data-nxbom-auto-submit]').forEach((control) => {
        control.addEventListener('change', () => filterForm.requestSubmit());
    });

    filterForm?.addEventListener('submit', () => {
        filterForm.setAttribute('aria-busy', 'true');
        filterForm.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });

    const chartDataNode = root.querySelector('[data-nxbom-chart-data]');
    const analytics = root.querySelector('[data-nxbom-analytics]');
    const echartsSrc = root.dataset.echartsSrc;

    if (!chartDataNode || !analytics || !echartsSrc) return;

    let payload = null;
    try {
        payload = JSON.parse(chartDataNode.textContent || '{}');
    } catch (error) {
        console.warn('NEXORIN BOM chart payload could not be parsed.', error);
        return;
    }

    const palette = ['#18b9a5', '#2a83c8', '#e1a53c', '#d35f50', '#806bc9', '#6e8997', '#45b978', '#bd7d36'];
    const statusColors = {
        not_ready: '#647d8b',
        ready: '#3b8fd6',
        generated: '#e4a43a',
        priced: '#31cc83',
        released: '#31cc83',
        locked: '#8b70df',
    };
    const scopeColors = ['#1cae89', '#d69a31', '#4477bd', '#6c8290'];

    const money = (value) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(Number(value || 0));

    const donutBase = (data, colors = palette) => ({
        animation: !reducedMotion,
        animationDuration: 360,
        color: colors,
        tooltip: {
            trigger: 'item',
            confine: true,
            backgroundColor: 'rgba(3, 17, 28, .97)',
            borderColor: 'rgba(75, 151, 177, .34)',
            textStyle: { color: '#dcecf1', fontSize: 10 },
        },
        series: [{
            type: 'pie',
            radius: ['57%', '77%'],
            center: ['50%', '50%'],
            clockwise: true,
            minAngle: 4,
            padAngle: 1.4,
            itemStyle: {
                borderColor: '#061725',
                borderWidth: 2,
                borderRadius: 2,
            },
            label: { show: false },
            emphasis: { scaleSize: 4, label: { show: false } },
            data,
        }],
    });

    const createCategoryChart = (node) => {
        const data = Array.isArray(payload.category) ? payload.category : [];
        const chart = window.echarts.init(node, null, { renderer: 'canvas' });
        const option = donutBase(data);
        option.tooltip.formatter = (item) => `<strong>${item.name}</strong><br>${Number(item.value).toLocaleString('en-US')} lines · ${item.percent}%`;
        chart.setOption(option);
        return chart;
    };

    const createCostChart = (node) => {
        const data = Array.isArray(payload.cost) ? payload.cost : [];
        const chart = window.echarts.init(node, null, { renderer: 'canvas' });
        const option = donutBase(data);
        option.tooltip.formatter = (item) => `<strong>${item.name}</strong><br>${money(item.value)} · ${item.percent}%<br>${Number(item.data?.lines || 0).toLocaleString('en-US')} lines`;
        chart.setOption(option);
        return chart;
    };

    const createStatusChart = (node) => {
        const data = Array.isArray(payload.status) ? payload.status : [];
        const chart = window.echarts.init(node, null, { renderer: 'canvas' });
        const option = donutBase(data.map((item) => ({
            ...item,
            itemStyle: { color: statusColors[item.key] || '#65808f' },
        })), Object.values(statusColors));
        option.series[0].radius = ['54%', '75%'];
        option.tooltip.formatter = ({ name, value, percent }) => `<strong>${name}</strong><br>${Number(value).toLocaleString('en-US')} projects · ${percent}%`;
        chart.setOption(option);
        return chart;
    };

    const createScopeChart = (node) => {
        const data = Array.isArray(payload.scope) ? payload.scope : [];
        const chart = window.echarts.init(node, null, { renderer: 'canvas' });
        chart.setOption({
            animation: !reducedMotion,
            animationDuration: 360,
            color: scopeColors,
            grid: { left: 10, right: 8, top: 12, bottom: 28, containLabel: true },
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                confine: true,
                backgroundColor: 'rgba(3, 17, 28, .97)',
                borderColor: 'rgba(75, 151, 177, .34)',
                textStyle: { color: '#dcecf1', fontSize: 10 },
                formatter: (items) => {
                    const item = items?.[0];
                    return item ? `<strong>${item.name}</strong><br>${Number(item.value).toLocaleString('en-US')} scope rows` : '';
                },
            },
            xAxis: {
                type: 'category',
                data: data.map((item) => item.name),
                axisTick: { show: false },
                axisLine: { lineStyle: { color: 'rgba(100, 145, 165, .20)' } },
                axisLabel: { color: '#66808e', fontSize: 7, interval: 0, overflow: 'truncate', width: 68 },
            },
            yAxis: {
                type: 'value',
                splitNumber: 3,
                axisLabel: { color: '#5c7482', fontSize: 7 },
                splitLine: { lineStyle: { color: 'rgba(87, 133, 153, .10)' } },
            },
            series: [{
                type: 'bar',
                barMaxWidth: 28,
                data: data.map((item, index) => ({
                    value: item.value,
                    itemStyle: { color: scopeColors[index % scopeColors.length], borderRadius: [2, 2, 0, 0] },
                })),
            }],
        });
        return chart;
    };

    const factories = {
        category: createCategoryChart,
        cost: createCostChart,
        status: createStatusChart,
        scope: createScopeChart,
    };

    const renderCharts = () => {
        if (!window.echarts || analytics.dataset.chartsReady === 'true') return;
        analytics.dataset.chartsReady = 'true';

        analytics.querySelectorAll('[data-nxbom-chart]').forEach((node) => {
            const factory = factories[node.dataset.nxbomChart];
            if (!factory) return;
            const chart = factory(node);
            node.classList.add('is-rendered');
            node.closest('.nxbomlib-chart-shell')?.classList.add('is-rendered');
            charts.push(chart);
        });

        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(() => charts.forEach((chart) => chart.resize()));
            observer.observe(analytics);
        } else {
            window.addEventListener('resize', () => charts.forEach((chart) => chart.resize()), { passive: true });
        }
    };

    let loadPromise = null;
    const loadEcharts = () => {
        if (window.echarts) {
            renderCharts();
            return Promise.resolve();
        }
        if (loadPromise) return loadPromise;

        loadPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = echartsSrc;
            script.async = true;
            script.dataset.nxbomEcharts = 'true';
            script.addEventListener('load', () => {
                renderCharts();
                resolve();
            }, { once: true });
            script.addEventListener('error', () => reject(new Error('ECharts bundle failed to load.')), { once: true });
            document.head.appendChild(script);
        }).catch((error) => {
            console.warn('NEXORIN BOM charts remain in accessible fallback mode.', error);
        });

        return loadPromise;
    };

    // ECharts is ~1.1 MB. Load it only when analytics approaches the viewport;
    // the accessible HTML fallbacks remain visible before that point.
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            observer.disconnect();
            loadEcharts();
        }, { rootMargin: '220px 0px' });
        observer.observe(analytics);
    } else {
        loadEcharts();
    }
})();
