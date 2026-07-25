(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    };

    ready(() => {
        const directory = document.querySelector('[data-nxfa-directory]');
        const workspace = document.querySelector('[data-nxfa-workspace]');
        if (!directory && !workspace) return;

        const actionEngine = window.NexorinEngineeringAction;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const project = document.querySelector('[data-nxfa-project-name]')?.textContent?.trim() || 'Financial Analysis';
        const projectNumber = document.querySelector('[data-nxfa-project-number]')?.textContent?.trim() || 'Project context';

        const revealNodes = Array.from(document.querySelectorAll('[data-nxfa-reveal]'));
        revealNodes.forEach((node) => node.classList.add('nxfa-reveal'));
        if (reducedMotion || !('IntersectionObserver' in window)) {
            revealNodes.forEach((node) => node.classList.add('is-visible'));
        } else {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.07, rootMargin: '0px 0px -3% 0px' });
            revealNodes.forEach((node) => observer.observe(node));
        }

        document.querySelectorAll('[data-nxfa-card]').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                card.style.setProperty('--mouse-x', `${event.clientX - bounds.left}px`);
                card.style.setProperty('--mouse-y', `${event.clientY - bounds.top}px`);
            });
        });

        const showAction = (config) => actionEngine?.show(config) || null;
        const navigate = (event, anchor, config) => actionEngine?.navigate(event, anchor, config) || false;

        const navConfig = (kind, anchor) => {
            const rowProject = anchor?.dataset?.nxfaProject || project;
            const rowNumber = anchor?.dataset?.nxfaProjectNumber || projectNumber;
            const configs = {
                open: {
                    kind: 'open', icon: 'financial', title: 'Opening Financial Analysis',
                    copy: 'The priced commercial basis, energy yield, financial assumptions, and investment decision context are being assembled.',
                    signal: 'NEXORIN financial decision engine', eyebrow: 'Investment analysis handoff',
                    metaLeft: rowNumber, metaRight: rowProject,
                    steps: ['Retaining the priced BOQ basis', 'Loading energy and tariff assumptions', 'Resolving investment metrics and cashflow', 'Opening the Financial Decision Cockpit'],
                    duration: 1040, accent: '#13aaa2', secondary: '#7659ca',
                },
                directory: {
                    kind: 'open', icon: 'portfolio', title: 'Opening Financial Registry',
                    copy: 'Visible projects, commercial readiness, investment metrics, and analysis milestones are being restored.',
                    signal: 'NEXORIN financial registry', eyebrow: 'Portfolio handoff',
                    metaLeft: 'Company context retained', metaRight: 'Financial state preserved',
                    steps: ['Retaining company and access context', 'Loading visible project economics', 'Resolving calculation and review states', 'Opening the Financial Registry'],
                    duration: 920, accent: '#13aaa2', secondary: '#7659ca',
                },
                bom: {
                    kind: 'open', icon: 'bom', title: 'Opening BOM & BOQ',
                    copy: 'The engineering quantity basis, commercial pricing trace, and customer total are being restored.',
                    signal: 'NEXORIN material orchestration', eyebrow: 'Commercial basis handoff',
                    metaLeft: rowNumber, metaRight: 'Financial context retained',
                    steps: ['Retaining project and access context', 'Loading material and pricing records', 'Resolving commercial coverage and totals', 'Opening the BOM & BOQ workspace'],
                    duration: 980, accent: '#13aaa2', secondary: '#e59835',
                },
                technical: {
                    kind: 'open', icon: 'technical', title: 'Opening Technical Calculation',
                    copy: 'The latest project evidence, electrical design basis, warnings, and verification context are being restored.',
                    signal: 'NEXORIN technical design engine', eyebrow: 'Engineering source handoff',
                    metaLeft: rowNumber, metaRight: rowProject,
                    steps: ['Retaining project and access context', 'Loading the effective technical basis', 'Resolving warnings and source trace', 'Opening Technical Calculation'],
                    duration: 980, accent: '#13aaa2', secondary: '#e59835',
                },
                quotation: {
                    kind: 'open', icon: 'quotation', title: 'Opening Quotation Workspace',
                    copy: 'Financial results, customer pricing, release controls, and report context are being assembled.',
                    signal: 'NEXORIN quotation workspace', eyebrow: 'Customer release handoff',
                    metaLeft: rowNumber, metaRight: 'Financial decision retained',
                    steps: ['Retaining customer and project identity', 'Loading commercial and financial results', 'Resolving release context', 'Opening the Quotation Workspace'],
                    duration: 1000, accent: '#13aaa2', secondary: '#7659ca',
                },
            };
            return configs[kind] || configs.open;
        };

        document.addEventListener('click', (event) => {
            const anchor = event.target.closest('a[data-nxfa-navigate], .nxfa-registry-footer a');
            if (!anchor) return;
            const kind = anchor.dataset.nxfaNavigate || 'directory';
            navigate(event, anchor, navConfig(kind, anchor));
        });

        document.querySelectorAll('[data-nxfa-row]').forEach((row) => {
            const open = () => row.querySelector('a[data-nxfa-navigate="open"]')?.click();
            row.addEventListener('click', (event) => {
                if (event.target.closest('a,button,input,select,textarea,form')) return;
                open();
            });
            row.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                open();
            });
        });

        const filterForm = document.querySelector('[data-nxfa-filter-form]');
        if (filterForm) {
            filterForm.querySelector('[data-nxfa-auto-submit]')?.addEventListener('change', () => filterForm.requestSubmit());
            filterForm.addEventListener('submit', () => {
                if (filterForm.dataset.nxfaSubmitting === 'true') return;
                filterForm.dataset.nxfaSubmitting = 'true';
                showAction({
                    kind: 'filter', icon: 'filter', title: 'Filtering Financial Registry',
                    copy: 'Visible project records are being resolved against the selected search and financial calculation state.',
                    signal: 'NEXORIN financial registry', eyebrow: 'Portfolio filter sequence',
                    metaLeft: 'Company scope retained', metaRight: 'Role visibility preserved',
                    steps: ['Reading active registry filters', 'Matching visible project records', 'Resolving source and financial states', 'Refreshing the Financial Registry'],
                    duration: 860, accent: '#13aaa2', secondary: '#7659ca',
                });
            });
        }

        const analysisForm = document.querySelector('[data-nxfa-analysis-form]');
        if (analysisForm) {
            const button = analysisForm.querySelector('button[type="submit"]');
            const dirtyState = analysisForm.querySelector('[data-nxfa-dirty-state]');
            let dirty = false;
            const markDirty = () => {
                dirty = true;
                dirtyState?.classList.add('is-dirty');
                if (dirtyState) dirtyState.textContent = 'Assumptions changed · rerun required';
            };
            analysisForm.querySelectorAll('input,textarea,select').forEach((control) => {
                control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', markDirty);
            });
            analysisForm.addEventListener('submit', () => {
                if (analysisForm.dataset.nxfaSubmitting === 'true') return;
                analysisForm.dataset.nxfaSubmitting = 'true';
                if (button) {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
                showAction({
                    kind: 'calculate', icon: 'financial', title: 'Calculating Financial Analysis',
                    copy: 'Tariff assumptions, project CAPEX, annual savings, replacement events, and discounted cashflow are being resolved.',
                    signal: 'NEXORIN financial decision engine', eyebrow: 'Investment analysis sequence',
                    metaLeft: projectNumber, metaRight: project,
                    steps: ['Validating technical and priced BOQ sources', 'Resolving tariff, O&M, degradation, and replacement assumptions', 'Calculating cashflow, NPV, IRR, payback, ROI, and LCOE', 'Recording the financial analysis snapshot'],
                    duration: 1320, accent: '#13aaa2', secondary: '#7659ca',
                });
            });
        }

        const formatCurrency = (value, decimals = 0) => {
            const number = Number(value || 0);
            const sign = number < 0 ? '−' : '';
            return `IDR ${sign}${Math.abs(number).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            })}`;
        };
        const formatPercent = (value) => `${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;

        document.querySelectorAll('[data-nxfa-format]').forEach((input) => {
            const output = input.closest('.nxfa-input-field')?.querySelector('[data-nxfa-format-output]');
            if (!output) return;
            const render = () => {
                const value = input.value;
                const kind = input.dataset.nxfaFormat;
                if (value === '') {
                    output.textContent = kind === 'currency' ? 'Uses the current BOQ total when blank.' : 'Automatic when blank.';
                    return;
                }
                if (kind === 'currency') output.textContent = formatCurrency(value, 0);
                else if (kind === 'percent') output.textContent = formatPercent(value);
                else if (kind === 'years') output.textContent = `${Number(value).toLocaleString('en-US')} years`;
                else if (kind === 'year') output.textContent = Number(value) === 0 ? 'No replacement event' : `Replacement in year ${Number(value).toLocaleString('en-US')}`;
                else if (kind === 'tariff') output.textContent = `${formatCurrency(value, 2)} / kWh`;
            };
            input.addEventListener('input', render);
            render();
        });

        const cashflowToggle = document.querySelector('[data-nxfa-cashflow-toggle]');
        if (cashflowToggle) {
            const extraRows = [...document.querySelectorAll('[data-nxfa-cashflow-extra]')];
            let expanded = false;
            cashflowToggle.addEventListener('click', () => {
                expanded = !expanded;
                extraRows.forEach((row) => { row.hidden = !expanded; });
                cashflowToggle.textContent = expanded ? 'Show first 10 years' : `Show years 11–${cashflowToggle.dataset.total || ''}`;
                cashflowToggle.setAttribute('aria-expanded', String(expanded));
            });
        }

        const commandBar = document.querySelector('[data-nxfa-command-bar]');
        if (commandBar) {
            const links = [...commandBar.querySelectorAll('a[href^="#"]')];
            const sections = links.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
            const updateActive = () => {
                const marker = window.scrollY + 175;
                let active = sections[0];
                sections.forEach((section) => { if (section.offsetTop <= marker) active = section; });
                links.forEach((link) => link.classList.toggle('is-active', link.getAttribute('href') === `#${active?.id}`));
            };
            window.addEventListener('scroll', updateActive, { passive: true });
            updateActive();
        }

        const readChartPayload = () => {
            const node = document.getElementById('nxfa-chart-data');
            if (!node) return null;
            try { return JSON.parse(node.textContent || '{}'); } catch (error) { return null; }
        };

        const compactIDR = (value) => {
            const number = Number(value || 0);
            const absolute = Math.abs(number);
            const sign = number < 0 ? '−' : '';
            if (absolute >= 1e9) return `IDR ${sign}${(absolute / 1e9).toFixed(1)}B`;
            if (absolute >= 1e6) return `IDR ${sign}${(absolute / 1e6).toFixed(1)}M`;
            if (absolute >= 1e3) return `IDR ${sign}${(absolute / 1e3).toFixed(0)}K`;
            return `IDR ${sign}${absolute.toFixed(0)}`;
        };

        const initCharts = () => {
            const payload = readChartPayload();
            if (!payload || !window.echarts) return;
            const instances = [];
            const rootStyle = getComputedStyle(document.documentElement);
            const text = rootStyle.getPropertyValue('--text-primary').trim() || '#23394a';
            const muted = rootStyle.getPropertyValue('--text-secondary').trim() || '#74879a';
            const grid = 'rgba(125, 151, 166, .17)';

            const cashflowNode = document.querySelector('[data-nxfa-cashflow-chart]');
            if (cashflowNode && Array.isArray(payload.cashflows) && payload.cashflows.length) {
                const chart = window.echarts.init(cashflowNode, null, { renderer: 'svg', useDirtyRect: false });
                chart.setOption({
                    animation: !reducedMotion,
                    animationDuration: 760,
                    animationEasing: 'cubicOut',
                    grid: { left: 70, right: 68, top: 38, bottom: 44 },
                    tooltip: {
                        trigger: 'axis',
                        confine: true,
                        showDelay: 0,
                        hideDelay: 0,
                        transitionDuration: 0,
                        enterable: false,
                        alwaysShowContent: false,
                        backgroundColor: 'rgba(3,28,40,.97)',
                        borderWidth: 0,
                        padding: [10,12],
                        extraCssText: 'border-radius:10px;box-shadow:0 16px 38px rgba(3,28,40,.24);opacity:1;',
                        textStyle: { color: '#e8f5f6', fontFamily: 'Inter', fontSize: 11 },
                        formatter: (params) => {
                            const year = params?.[0]?.axisValueLabel || '';
                            return [`<b>Year ${year}</b>`, ...params.map((item) => `${item.marker}${item.seriesName}: ${compactIDR(item.value)}`)].join('<br>');
                        },
                    },
                    legend: { show: false },
                    xAxis: { type: 'category', data: payload.cashflows.map((row) => String(row.year)), boundaryGap: true, axisLine: { lineStyle: { color: grid } }, axisTick: { show: false }, axisLabel: { color: muted, fontFamily: 'JetBrains Mono', fontSize: 8, interval: payload.cashflows.length > 20 ? 2 : 1 } },
                    yAxis: [
                        { type: 'value', axisLabel: { color: muted, fontFamily: 'JetBrains Mono', fontSize: 8, formatter: compactIDR }, splitLine: { lineStyle: { color: grid, type: 'dashed' } }, axisLine: { show: false }, axisTick: { show: false } },
                        { type: 'value', axisLabel: { color: muted, fontFamily: 'JetBrains Mono', fontSize: 8, formatter: compactIDR }, splitLine: { show: false }, axisLine: { show: false }, axisTick: { show: false } },
                    ],
                    series: [
                        {
                            name: 'Annual net cashflow', type: 'bar', barMaxWidth: 18,
                            data: payload.cashflows.map((row) => row.net),
                            itemStyle: {
                                borderRadius: [4,4,0,0],
                                color: (params) => Number(params.value) >= 0 ? '#3978d8' : '#cf514a',
                            },
                            markPoint: payload.replacementYear > 0 ? {
                                symbol: 'circle',
                                symbolSize: 10,
                                label: {
                                    show: true,
                                    position: 'top',
                                    distance: 8,
                                    formatter: `Replacement Y${payload.replacementYear}`,
                                    color: '#9a5a10',
                                    backgroundColor: 'rgba(255,246,235,.98)',
                                    borderColor: 'rgba(243,160,79,.48)',
                                    borderWidth: 1,
                                    borderRadius: 6,
                                    padding: [4,6],
                                    fontFamily: 'JetBrains Mono',
                                    fontSize: 8,
                                    fontWeight: 700,
                                },
                                itemStyle: { color: '#f3a04f' },
                                data: [{ coord: [String(payload.replacementYear), payload.cashflows[Math.max(0, payload.replacementYear - 1)]?.net || 0] }],
                            } : undefined,
                        },
                        {
                            name: 'Cumulative cashflow', type: 'line', yAxisIndex: 1, smooth: .28, symbol: 'circle', symbolSize: 4,
                            data: payload.cashflows.map((row) => row.cumulative),
                            lineStyle: { width: 2.5, color: '#13aaa2' }, itemStyle: { color: '#13aaa2' },
                            areaStyle: { color: new window.echarts.graphic.LinearGradient(0,0,0,1,[{ offset: 0, color: 'rgba(19,170,162,.18)' },{ offset: 1, color: 'rgba(19,170,162,.01)' }]) },
                            markLine: { silent: true, symbol: 'none', lineStyle: { color: 'rgba(116,135,154,.48)', type: 'dashed' }, label: { show: false }, data: [{ yAxis: 0 }] },
                            markPoint: payload.breakEvenYear ? { symbol: 'pin', symbolSize: 34, label: { color: '#fff', fontFamily: 'JetBrains Mono', fontSize: 8, formatter: `Y${payload.breakEvenYear}` }, itemStyle: { color: '#1b966e' }, data: [{ coord: [String(payload.breakEvenYear), payload.cashflows[Math.max(0, payload.breakEvenYear - 1)]?.cumulative || 0] }] } : undefined,
                        },
                    ],
                });
                chart.getZr().on('globalout', () => chart.dispatchAction({ type: 'hideTip' }));
                instances.push(chart);
            }

            const benchmarkNode = document.querySelector('[data-nxfa-benchmark-chart]');
            if (benchmarkNode && payload.benchmark) {
                const chart = window.echarts.init(benchmarkNode, null, { renderer: 'svg', useDirtyRect: false });
                chart.setOption({
                    animation: !reducedMotion,
                    animationDuration: 680,
                    grid: { left: 82, right: 34, top: 28, bottom: 50 },
                    tooltip: {
                        trigger: 'axis',
                        axisPointer: { type: 'shadow' },
                        confine: true,
                        showDelay: 0,
                        hideDelay: 0,
                        transitionDuration: 0,
                        enterable: false,
                        alwaysShowContent: false,
                        backgroundColor: 'rgba(3,28,40,.97)',
                        borderWidth: 0,
                        padding: [10,12],
                        extraCssText: 'border-radius:10px;box-shadow:0 16px 38px rgba(3,28,40,.24);opacity:1;',
                        textStyle: { color: '#e8f5f6', fontFamily: 'Inter', fontSize: 11 },
                        formatter: (params) => `${params?.[0]?.name || ''}<br>${params?.[0]?.marker || ''}${formatCurrency(params?.[0]?.value || 0, 2)} / kWh`,
                    },
                    xAxis: {
                        type: 'value',
                        name: 'IDR per kWh',
                        nameLocation: 'middle',
                        nameGap: 30,
                        nameTextStyle: { color: muted, fontFamily: 'Inter', fontSize: 8, fontWeight: 650 },
                        splitNumber: 4,
                        min: 0,
                        axisLabel: {
                            color: muted,
                            fontFamily: 'JetBrains Mono',
                            fontSize: 8,
                            margin: 10,
                            formatter: (value) => {
                                const number = Number(value || 0);
                                if (Math.abs(number) >= 1000) return `${(number / 1000).toFixed(number % 1000 === 0 ? 0 : 1)}K`;
                                return Number(number.toFixed(0)).toLocaleString('en-US');
                            },
                        },
                        splitLine: { lineStyle: { color: grid, type: 'dashed' } },
                        axisLine: { show: false },
                        axisTick: { show: false },
                    },
                    yAxis: { type: 'category', data: ['Electricity tariff', 'LCOE'], axisLabel: { color: text, fontFamily: 'Inter', fontSize: 9 }, axisLine: { show: false }, axisTick: { show: false } },
                    series: [{
                        type: 'bar', barWidth: 24,
                        data: [
                            { value: payload.benchmark.tariff, itemStyle: { color: '#13aaa2', borderRadius: [0,6,6,0] } },
                            { value: payload.benchmark.lcoe, itemStyle: { color: payload.benchmark.lcoe <= payload.benchmark.tariff ? '#3978d8' : '#cf514a', borderRadius: [0,6,6,0] } },
                        ],
                        label: { show: true, position: 'right', color: muted, fontFamily: 'JetBrains Mono', fontSize: 8, formatter: (params) => `IDR ${Number(params.value).toLocaleString('en-US', { maximumFractionDigits: 0 })}` },
                    }],
                });
                chart.getZr().on('globalout', () => chart.dispatchAction({ type: 'hideTip' }));
                instances.push(chart);
            }

            let resizeTimer;
            const resize = () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => instances.forEach((chart) => chart.resize()), 80);
            };
            window.addEventListener('resize', resize, { passive: true });
            document.addEventListener('nexorin:theme-change', resize);
        };

        initCharts();

        window.addEventListener('pageshow', () => {
            document.querySelectorAll('[data-nxfa-submitting="true"]').forEach((form) => {
                delete form.dataset.nxfaSubmitting;
                form.querySelectorAll('[aria-busy="true"]').forEach((button) => {
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                });
            });
        });
    });
})();
