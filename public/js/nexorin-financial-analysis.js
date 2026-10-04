(() => {
    'use strict';

    if (window.__nexorinPage014FinancialWorkspace) return;
    window.__nexorinPage014FinancialWorkspace = true;

    const page = document.querySelector('[data-nx14-page]');
    if (!page) return;

    const formatIdr = (value) => {
        const number = Number(value);
        if (!Number.isFinite(number)) return 'Not available';
        return `IDR ${Math.round(number).toLocaleString('en-US')}`;
    };

    const compactIdr = (value) => {
        const number = Number(value);
        if (!Number.isFinite(number)) return '—';
        const absolute = Math.abs(number);
        const sign = number < 0 ? '-' : '';
        if (absolute >= 1e12) return `${sign}${(absolute / 1e12).toFixed(1)}T`;
        if (absolute >= 1e9) return `${sign}${(absolute / 1e9).toFixed(1)}B`;
        if (absolute >= 1e6) return `${sign}${(absolute / 1e6).toFixed(1)}M`;
        if (absolute >= 1e3) return `${sign}${(absolute / 1e3).toFixed(0)}K`;
        return `${sign}${Math.round(absolute).toLocaleString('en-US')}`;
    };

    const initCashflowChart = () => {
        const element = document.getElementById('nx14-cashflow-chart');
        const source = document.getElementById('nx14-chart-data');
        if (!element || !source || element.dataset.chartInitialized === '1') return;
        if (!window.echarts) return;

        let payload;
        try {
            payload = JSON.parse(source.textContent || '{}');
        } catch (_) {
            return;
        }

        const cashflows = Array.isArray(payload.cashflows) ? payload.cashflows : [];
        if (!cashflows.length) return;

        const years = cashflows.map((row) => `Year ${Number(row.year) || 0}`);
        const annual = cashflows.map((row) => Number(row.annual) || 0);
        const cumulative = cashflows.map((row) => Number(row.cumulative) || 0);
        const replacementYear = Number(payload.replacementYear) || 0;
        const styles = getComputedStyle(page);
        const cyan = styles.getPropertyValue('--nx14-cyan').trim() || '#59d6de';
        const blue = styles.getPropertyValue('--nx14-blue').trim() || '#78aaff';
        const muted = styles.getPropertyValue('--nx14-muted').trim() || '#718b94';
        const line = 'rgba(142,185,202,.10)';

        const chart = window.echarts.init(element, null, { renderer: 'canvas' });
        element.dataset.chartInitialized = '1';
        element.__nx14Chart = chart;

        chart.setOption({
            animationDuration: 520,
            animationEasing: 'cubicOut',
            grid: { left: 16, right: 16, top: 24, bottom: 24, containLabel: true },
            tooltip: {
                trigger: 'axis',
                appendToBody: true,
                backgroundColor: 'rgba(5, 16, 22, .97)',
                borderColor: 'rgba(89,214,222,.24)',
                borderWidth: 1,
                padding: [9, 10],
                textStyle: { color: '#dcecef', fontSize: 11, fontFamily: 'Inter, Segoe UI, Arial, sans-serif' },
                extraCssText: 'box-shadow:0 14px 34px rgba(0,0,0,.34);border-radius:7px;',
                formatter(params) {
                    if (!Array.isArray(params) || !params.length) return '';
                    const index = params[0].dataIndex;
                    const row = cashflows[index] || {};
                    const replacement = replacementYear > 0 && Number(row.year) === replacementYear
                        ? '<div style="margin-top:5px;padding-top:5px;border-top:1px solid rgba(142,185,202,.12);color:#e6b76c">Replacement assumption applies this year</div>'
                        : '';
                    return [
                        `<div style="font-weight:700;color:#f0f7f8;margin-bottom:5px">Year ${Number(row.year) || 0}</div>`,
                        `<div style="display:flex;justify-content:space-between;gap:22px"><span style="color:#86a0a8">Annual net</span><b>${formatIdr(row.annual)}</b></div>`,
                        `<div style="display:flex;justify-content:space-between;gap:22px;margin-top:3px"><span style="color:#86a0a8">Cumulative</span><b>${formatIdr(row.cumulative)}</b></div>`,
                        replacement,
                    ].join('');
                },
            },
            xAxis: {
                type: 'category',
                data: years,
                boundaryGap: true,
                axisLine: { lineStyle: { color: line } },
                axisTick: { show: false },
                axisLabel: {
                    color: muted,
                    fontSize: 9,
                    interval(index) {
                        if (years.length <= 12) return true;
                        return index === 0 || index === years.length - 1 || index % 4 === 0;
                    },
                },
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                splitLine: { lineStyle: { color: line, type: 'dashed' } },
                axisLabel: {
                    color: muted,
                    fontSize: 9,
                    formatter(value) { return compactIdr(value); },
                },
            },
            series: [
                {
                    name: 'Annual Net Cashflow',
                    type: 'bar',
                    data: annual,
                    barMaxWidth: 18,
                    itemStyle: {
                        color: cyan,
                        opacity: .68,
                        borderRadius(params) {
                            return Number(params.value) >= 0 ? [3, 3, 0, 0] : [0, 0, 3, 3];
                        },
                    },
                    emphasis: { itemStyle: { opacity: .94 } },
                    z: 2,
                },
                {
                    name: 'Cumulative Cashflow',
                    type: 'line',
                    data: cumulative,
                    symbol: 'circle',
                    symbolSize: 5,
                    showSymbol: false,
                    smooth: false,
                    lineStyle: { color: blue, width: 2 },
                    itemStyle: { color: blue },
                    areaStyle: { color: 'rgba(120,170,255,.055)' },
                    z: 3,
                    markLine: replacementYear > 0 ? {
                        silent: true,
                        symbol: 'none',
                        lineStyle: { color: 'rgba(230,183,108,.40)', width: 1, type: 'dashed' },
                        label: { show: true, formatter: 'Replacement', color: '#c8a46d', fontSize: 8, position: 'insideEndTop' },
                        data: [{ xAxis: `Year ${replacementYear}` }],
                    } : undefined,
                },
            ],
        });

        let frame = null;
        const resize = () => {
            if (frame) cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => chart.resize());
        };

        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(resize);
            observer.observe(element);
            element.__nx14ResizeObserver = observer;
        } else {
            window.addEventListener('resize', resize, { passive: true });
        }
    };

    const form = page.querySelector('[data-nx14-form]');
    const editState = page.querySelector('[data-nx14-edit-state]');
    if (form && editState) {
        const initial = new FormData(form);
        const baseline = Array.from(initial.entries()).map(([key, value]) => `${key}:${value}`).join('|');
        const updateEditState = () => {
            const current = Array.from(new FormData(form).entries()).map(([key, value]) => `${key}:${value}`).join('|');
            const changed = current !== baseline;
            editState.textContent = changed ? 'Assumptions changed · rerun required' : 'Saved model inputs';
            editState.classList.toggle('is-dirty', changed);
        };
        form.addEventListener('input', updateEditState);
        form.addEventListener('change', updateEditState);

        form.addEventListener('submit', () => {
            page.querySelectorAll('[data-nx14-run], [data-nx14-run-bottom]').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = 'Calculating…';
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCashflowChart, { once: true });
    } else {
        initCashflowChart();
    }
})();
