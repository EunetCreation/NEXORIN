(() => {
    'use strict';

    const root = document.querySelector('[data-nx11-root]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const charts = [];


    // Page 011 luxury interaction layer: restrained pointer light and surface focus.
    // It changes presentation only; no business state or engineering/customer truth is derived here.
    if (!reducedMotion) {
        const luxurySurfaces = root.querySelectorAll('.nx11-v9-hero, .nx11-v9-card, .nx11-v9-rail');
        luxurySurfaces.forEach(surface => {
            const updateSpotlight = event => {
                const rect = surface.getBoundingClientRect();
                if (!rect.width || !rect.height) return;
                const x = Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100));
                const y = Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100));
                surface.style.setProperty('--nx11-lux-x', `${x.toFixed(1)}%`);
                surface.style.setProperty('--nx11-lux-y', `${y.toFixed(1)}%`);
                surface.dataset.nx11LuxActive = 'true';
            };
            const clearSpotlight = () => {
                surface.style.setProperty('--nx11-lux-x', '50%');
                surface.style.setProperty('--nx11-lux-y', '20%');
                delete surface.dataset.nx11LuxActive;
            };
            surface.addEventListener('pointermove', updateSpotlight, { passive: true });
            surface.addEventListener('pointerleave', clearSpotlight, { passive: true });
        });
    }

    const escapeHtml = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    let projectData = [];
    try {
        const parsed = JSON.parse(root.dataset.projectChart || '[]');
        projectData = Array.isArray(parsed)
            ? parsed.filter(item => item && Number(item.value) > 0 && (item.label || item.name))
            : [];
    } catch (_) {
        projectData = [];
    }

    const projectChartElement = root.querySelector('[data-nx11-project-chart]');
    const projectChartFallback = root.querySelector('[data-nx11-chart-fallback]');
    const projectChartCenter = root.querySelector('[data-nx11-chart-center]');
    const showChartFallback = () => {
        if (projectChartElement) projectChartElement.hidden = true;
        if (projectChartCenter) projectChartCenter.hidden = true;
        if (projectChartFallback) projectChartFallback.hidden = false;
    };
    if (projectChartElement && projectData.length && window.echarts) {
        try {
            const chart = window.echarts.init(projectChartElement, null, { renderer: 'canvas' });
            chart.setOption({
                animation: !reducedMotion,
                animationDuration: reducedMotion ? 0 : 420,
                color: ['#20c4c6', '#3f8fd6', '#8d74da', '#d0a049', '#51b98d', '#70889c'],
                tooltip: {
                    trigger: 'item',
                    renderMode: 'html',
                    appendToBody: true,
                    confine: true,
                    backgroundColor: 'transparent',
                    borderWidth: 0,
                    padding: 0,
                    className: 'nx11-echart-tooltip-wrap',
                    transitionDuration: reducedMotion ? 0 : 0.08,
                    extraCssText: 'box-shadow:none;background:transparent;border:none;',
                    formatter: params => `
                        <div class="nx11-tooltip">
                            <small>Project portfolio</small>
                            <strong>${escapeHtml(params.name)}</strong>
                            <div><span>Linked projects</span><b>${Number(params.value || 0).toLocaleString()}</b></div>
                        </div>`
                },
                series: [{
                    type: 'pie',
                    radius: ['66%', '82%'],
                    center: ['50%', '50%'],
                    itemStyle: {
                        borderWidth: 2,
                        borderColor: '#071925',
                        borderRadius: 4
                    },
                    label: { show: false },
                    emphasis: { scaleSize: 3, label: { show: false } },
                    data: projectData.map(item => ({ name: String(item.label || item.name), value: Number(item.value) }))
                }]
            });

            projectChartElement.dataset.ready = 'true';
            charts.push(chart);
        } catch (_) {
            projectChartElement.dataset.ready = 'false';
            showChartFallback();
        }
    } else if (projectChartElement && projectData.length && !window.echarts) {
        showChartFallback();
    }

    const resizeCharts = () => charts.forEach(chart => {
        try { chart.resize(); } catch (_) { /* no-op */ }
    });

    if ('ResizeObserver' in window && projectChartElement && charts.length) {
        const observer = new ResizeObserver(resizeCharts);
        observer.observe(projectChartElement);
    }
    window.addEventListener('resize', resizeCharts, { passive: true });

    root.querySelectorAll('[data-nx11-archive-form]').forEach(form => {
        form.addEventListener('submit', async event => {
            if (form.dataset.confirmed === 'true') return;
            event.preventDefault();

            const customerName = form.dataset.customerName || 'this customer';
            const ask = window.NexorinConfirm?.ask;
            const approved = ask
                ? await ask(`Archive ${customerName}?`, {
                    tone: 'danger',
                    title: 'Archive customer relationship?',
                    kicker: 'Customer lifecycle control',
                    confirmLabel: 'Archive customer',
                    control: 'Open-project blockers are checked server-side before archive',
                    result: 'The customer record remains preserved for historical audit'
                })
                : window.confirm(`Archive ${customerName}?`);

            if (!approved) return;
            form.dataset.confirmed = 'true';
            form.requestSubmit();
        });
    });
})();
