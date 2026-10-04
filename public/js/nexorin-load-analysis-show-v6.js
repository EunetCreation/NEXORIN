(() => {
    'use strict';

    const page = document.querySelector('[data-nx18-page]');
    if (!page) return;

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    page.classList.add('is-enhanced');

    const parseJson = (id, fallback) => {
        const node = document.getElementById(id);
        if (!node) return fallback;
        try {
            return JSON.parse(node.textContent || '');
        } catch (error) {
            console.warn(`[NEXORIN Page 018] Could not parse ${id}.`, error);
            return fallback;
        }
    };

    const showPanel = (name, focusTab = false) => {
        const tabs = [...page.querySelectorAll('[data-nx18-tab]')];
        const panels = [...page.querySelectorAll('[data-nx18-panel]')];
        const tab = tabs.find((item) => item.dataset.nx18Tab === name);
        const panel = panels.find((item) => item.dataset.nx18Panel === name);

        if (!tab || !panel) return;

        tabs.forEach((item) => {
            const active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });

        panels.forEach((item) => {
            const active = item === panel;
            item.hidden = !active;
            item.classList.toggle('is-active', active);
        });

        if (focusTab) tab.focus({ preventScroll: true });
        window.setTimeout(() => window.dispatchEvent(new Event('resize')), reducedMotion ? 0 : 40);
    };

    const tabList = page.querySelector('[data-nx18-tabs]');
    if (tabList) {
        const tabs = [...tabList.querySelectorAll('[data-nx18-tab]')];
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => showPanel(tab.dataset.nx18Tab));
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();

                let targetIndex = index;
                if (event.key === 'ArrowLeft') targetIndex = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'ArrowRight') targetIndex = (index + 1) % tabs.length;
                if (event.key === 'Home') targetIndex = 0;
                if (event.key === 'End') targetIndex = tabs.length - 1;

                showPanel(tabs[targetIndex].dataset.nx18Tab, true);
            });
        });
    }

    page.querySelectorAll('[data-nx18-open-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.nx18OpenTab;
            showPanel(target);
            const tab = page.querySelector(`[data-nx18-tab="${CSS.escape(target)}"]`);
            tab?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'center' });
        });
    });

    const tooltipStyle = {
        backgroundColor: 'rgba(5, 15, 27, 0.96)',
        borderColor: 'rgba(120, 180, 207, 0.28)',
        borderWidth: 1,
        textStyle: {
            color: '#e9f5fb',
            fontFamily: 'Inter, Segoe UI, Arial, sans-serif',
            fontSize: 10,
        },
        extraCssText: 'box-shadow:0 16px 34px rgba(0,0,0,.32);border-radius:9px;padding:8px 10px;',
    };

    const axisLabel = {
        color: '#718b9d',
        fontFamily: 'Inter, Segoe UI, Arial, sans-serif',
        fontSize: 8,
    };

    const charts = [];

    const registerChart = (element, option) => {
        if (!element || !window.echarts) return null;
        const existing = window.echarts.getInstanceByDom(element);
        const chart = existing || window.echarts.init(element, null, { renderer: 'canvas' });
        chart.setOption(option, { notMerge: true, lazyUpdate: false });
        charts.push(chart);
        return chart;
    };

    const profileData = parseJson('nx18-profile-data', null);
    const profileElement = page.querySelector('[data-nx18-chart="profile"]');
    if (profileElement && profileData && Array.isArray(profileData.total)) {
        const labels = Array.isArray(profileData.hours)
            ? profileData.hours
            : Array.from({ length: 24 }, (_, hour) => `${String(hour).padStart(2, '0')}:00`);
        const critical = Array.isArray(profileData.critical) ? profileData.critical : Array(24).fill(0);
        const dayStart = Number(profileData.daytime_start ?? 6);
        const dayEnd = Number(profileData.daytime_end ?? 18);

        registerChart(profileElement, {
            animation: !reducedMotion,
            animationDuration: 520,
            animationEasing: 'cubicOut',
            grid: { left: 46, right: 17, top: 36, bottom: 34, containLabel: false },
            tooltip: {
                ...tooltipStyle,
                trigger: 'axis',
                axisPointer: { type: 'line', lineStyle: { color: 'rgba(142, 190, 211, .24)' } },
                formatter(params) {
                    const list = Array.isArray(params) ? params : [];
                    const hour = list[0]?.axisValueLabel ?? '';
                    const lines = list.map((item) => `${item.marker}${item.seriesName}: <strong>${Number(item.value).toFixed(2)} kW</strong>`);
                    return `<div style="font-weight:700;margin-bottom:4px">${hour}</div>${lines.join('<br>')}<div style="margin-top:5px;color:#7f98a9">Repeated daily equipment model</div>`;
                },
            },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: labels,
                axisLine: { lineStyle: { color: 'rgba(142, 180, 205, .12)' } },
                axisTick: { show: false },
                axisLabel: {
                    ...axisLabel,
                    interval: (index) => index % 3 === 0,
                    formatter(value) { return String(value).replace(':00', ''); },
                },
            },
            yAxis: {
                type: 'value',
                min: 0,
                name: 'kW',
                nameTextStyle: { ...axisLabel, padding: [0, 0, 3, -12] },
                splitLine: { lineStyle: { color: 'rgba(142, 180, 205, .075)' } },
                axisLabel: { ...axisLabel, formatter: (value) => Number(value).toFixed(value >= 10 ? 0 : 1) },
            },
            series: [
                {
                    name: 'Total modeled load',
                    type: 'line',
                    data: profileData.total,
                    smooth: 0.25,
                    symbol: 'none',
                    lineStyle: { width: 2, color: '#32d5da' },
                    areaStyle: {
                        opacity: 1,
                        color: {
                            type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
                            colorStops: [
                                { offset: 0, color: 'rgba(50, 213, 218, .24)' },
                                { offset: 1, color: 'rgba(50, 213, 218, .012)' },
                            ],
                        },
                    },
                    markArea: dayStart < dayEnd ? {
                        silent: true,
                        itemStyle: { color: 'rgba(244, 188, 104, .035)' },
                        data: [[{ xAxis: labels[Math.max(0, Math.min(23, dayStart))] }, { xAxis: labels[Math.max(0, Math.min(23, dayEnd - 1))] }]],
                    } : undefined,
                    z: 2,
                },
                {
                    name: 'Critical-tagged portion',
                    type: 'line',
                    data: critical,
                    smooth: 0.25,
                    symbol: 'none',
                    lineStyle: { width: 1.45, color: '#a98cff' },
                    areaStyle: { color: 'rgba(169, 140, 255, .035)' },
                    z: 3,
                },
            ],
        });
    }

    const billData = parseJson('nx18-bill-data', null);
    const billElements = page.querySelectorAll('[data-nx18-chart="bills"], [data-nx18-chart="bills-large"]');
    if (billElements.length && billData && Array.isArray(billData.energy)) {
        const labels = Array.isArray(billData.labels) ? billData.labels : [];
        const peaks = Array.isArray(billData.peak) ? billData.peak : [];

        const compactMonth = (value) => {
            const match = String(value ?? '').match(/^([A-Za-z]{3})\s+(\d{4})$/);
            return match ? `${match[1]} '${match[2].slice(2)}` : String(value ?? '');
        };

        const option = {
            animation: !reducedMotion,
            animationDuration: 480,
            animationEasing: 'cubicOut',
            grid: { left: 45, right: 15, top: 25, bottom: 36 },
            tooltip: {
                ...tooltipStyle,
                trigger: 'axis',
                axisPointer: { type: 'shadow', shadowStyle: { color: 'rgba(90, 154, 183, .06)' } },
                formatter(params) {
                    const item = Array.isArray(params) ? params[0] : params;
                    const index = item?.dataIndex ?? 0;
                    const peak = peaks[index];
                    return [
                        `<div style="font-weight:700;margin-bottom:4px">${labels[index] ?? ''}</div>`,
                        `${item?.marker ?? ''}Recorded energy: <strong>${Number(item?.value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 1 })} kWh</strong>`,
                        peak === null || peak === undefined ? '<span style="color:#718b9d">Recorded peak demand: Not Recorded</span>' : `Recorded peak demand: <strong>${Number(peak).toFixed(2)} kW</strong>`,
                    ].join('<br>');
                },
            },
            xAxis: {
                type: 'category',
                data: labels,
                axisTick: { show: false },
                axisLine: { lineStyle: { color: 'rgba(142, 180, 205, .12)' } },
                axisLabel: { ...axisLabel, interval: 0, rotate: 0, margin: 9, formatter: compactMonth },
            },
            yAxis: {
                type: 'value',
                min: 0,
                name: 'kWh',
                nameTextStyle: { ...axisLabel, padding: [0, 0, 3, -12] },
                splitLine: { lineStyle: { color: 'rgba(142, 180, 205, .075)' } },
                axisLabel: { ...axisLabel, formatter: (value) => Number(value).toLocaleString() },
            },
            series: [{
                name: 'Recorded energy',
                type: 'bar',
                data: billData.energy,
                barMaxWidth: 26,
                itemStyle: {
                    borderRadius: [4, 4, 1, 1],
                    color: {
                        type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
                        colorStops: [
                            { offset: 0, color: '#5fdde0' },
                            { offset: 1, color: '#178e9a' },
                        ],
                    },
                },
            }],
        };

        billElements.forEach((element) => {
            const isLarge = element.dataset.nx18Chart === 'bills-large';
            const chartOption = {
                ...option,
                grid: { ...option.grid, bottom: isLarge ? 34 : 30 },
                xAxis: {
                    ...option.xAxis,
                    axisLabel: {
                        ...option.xAxis.axisLabel,
                        interval: isLarge ? 0 : (index) => index % 2 === 0,
                        formatter: isLarge ? compactMonth : (value) => String(value ?? '').slice(0, 3),
                    },
                },
            };
            registerChart(element, chartOption);
        });
    }

    if (charts.length) {
        const resizeCharts = () => charts.forEach((chart) => chart && !chart.isDisposed() && chart.resize());
        window.addEventListener('resize', resizeCharts, { passive: true });
        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(resizeCharts);
            page.querySelectorAll('[data-nx18-chart]').forEach((element) => observer.observe(element));
        }
    }

    page.querySelectorAll('[data-nx18-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.nx18Confirm || 'Remove this record?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const billEnergy = page.querySelector('[data-nx18-bill-energy]');
    const billAmount = page.querySelector('[data-nx18-bill-amount]');
    const ratePreview = page.querySelector('[data-nx18-rate-preview]');
    if (billEnergy && billAmount && ratePreview) {
        const updateRatePreview = () => {
            const energy = Number(billEnergy.value);
            const amount = Number(billAmount.value);
            if (Number.isFinite(energy) && energy > 0 && Number.isFinite(amount) && amount >= 0 && billAmount.value !== '') {
                const rate = amount / energy;
                ratePreview.innerHTML = `Unsaved effective rate preview: <strong>Rp ${rate.toLocaleString('id-ID', { maximumFractionDigits: 2 })}/kWh</strong>`;
            } else {
                ratePreview.textContent = 'Enter both energy and bill amount to preview the unsaved effective rate.';
            }
        };
        billEnergy.addEventListener('input', updateRatePreview);
        billAmount.addEventListener('input', updateRatePreview);
        updateRatePreview();
    }

    const revealNodes = [...page.querySelectorAll('[data-nx18-reveal]')];
    if (reducedMotion || !('IntersectionObserver' in window)) {
        revealNodes.forEach((node) => node.classList.add('is-visible'));
    } else {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.04, rootMargin: '0px 0px 40px 0px' });
        revealNodes.forEach((node) => revealObserver.observe(node));
    }
})();
