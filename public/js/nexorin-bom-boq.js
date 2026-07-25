(() => {
    'use strict';

    const action = window.NexorinEngineeringAction;
    const showAction = (config) => action?.show?.(config);
    const navigate = (event, anchor, config) => action?.navigate?.(event, anchor, config);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const workspace = document.querySelector('[data-nxbom-workspace]');
    const project = workspace?.dataset.nxbomProject || 'Project';
    const projectNumber = workspace?.dataset.nxbomProjectNumber || 'Project context';

    const reveal = () => {
        const nodes = [...document.querySelectorAll('[data-nxbom-reveal]')];
        nodes.forEach((node, index) => {
            window.setTimeout(() => node.classList.add('is-visible'), reducedMotion ? 0 : Math.min(index * 55, 300));
        });
    };

    const navConfig = (kind, anchor) => {
        const rowProject = anchor.dataset.nxbomProject || anchor.closest('[data-nxbom-row]')?.dataset.nxbomProject || project;
        const rowNumber = anchor.dataset.nxbomProjectNumber || anchor.closest('[data-nxbom-row]')?.dataset.nxbomProjectNumber || projectNumber;

        const configs = {
            open: {
                kind: 'open', icon: 'bom', title: 'Opening BOM & BOQ',
                copy: 'Engineering quantity, scope responsibility, commercial coverage, and release authority are being assembled.',
                signal: 'NEXORIN material orchestration', eyebrow: 'Commercial engineering workspace',
                metaLeft: rowNumber, metaRight: rowProject,
                steps: ['Retaining the effective technical result', 'Loading quantity and scope resolution', 'Resolving commercial traceability', 'Opening the BOM & BOQ workspace'],
                duration: 1020, accent: '#13aaa2', secondary: '#755bc7',
            },
            directory: {
                kind: 'open', icon: 'portfolio', title: 'Opening BOM & BOQ Registry',
                copy: 'Visible projects, engineering readiness, BOM state, and pricing milestones are being restored.',
                signal: 'NEXORIN commercial registry', eyebrow: 'Portfolio handoff',
                metaLeft: 'Company context retained', metaRight: 'Commercial visibility preserved',
                steps: ['Retaining company and access context', 'Loading material schedule milestones', 'Resolving pricing and release states', 'Opening the BOM & BOQ Registry'],
                duration: 920, accent: '#13aaa2', secondary: '#3679d8',
            },
            'technical-directory': {
                kind: 'open', icon: 'technical', title: 'Opening Calculation Registry',
                copy: 'NEXORIN is restoring the visible engineering portfolio and calculation authority.',
                signal: 'NEXORIN technical design engine', eyebrow: 'Engineering handoff',
                metaLeft: 'Company context retained', metaRight: 'Calculation state preserved',
                steps: ['Retaining company and access context', 'Loading calculation records', 'Resolving verification states', 'Opening the Calculation Registry'],
                duration: 920, accent: '#13aaa2', secondary: '#e59835',
            },
            technical: {
                kind: 'open', icon: 'technical', title: 'Opening Technical Calculation',
                copy: 'The effective engineering basis, electrical constraints, warnings, and audit context are being restored.',
                signal: 'NEXORIN technical design engine', eyebrow: 'Upstream engineering handoff',
                metaLeft: projectNumber, metaRight: project,
                steps: ['Retaining project and access context', 'Loading the effective technical result', 'Resolving warnings and source trace', 'Opening Technical Calculation'],
                duration: 980, accent: '#13aaa2', secondary: '#e59835',
            },
            financial: {
                kind: 'open', icon: 'financial', title: 'Opening Financial Analysis',
                copy: 'Commercial totals, project economics, tariff assumptions, and investment metrics are being assembled.',
                signal: 'NEXORIN financial engine', eyebrow: 'Downstream commercial handoff',
                metaLeft: projectNumber, metaRight: 'BOQ context retained',
                steps: ['Retaining the priced commercial basis', 'Loading energy and tariff assumptions', 'Resolving financial metrics', 'Opening Financial Analysis'],
                duration: 1000, accent: '#13aaa2', secondary: '#755bc7',
            },
            cockpit: {
                kind: 'open', icon: 'cockpit', title: 'Opening Project Cockpit',
                copy: 'Project identity, workflow state, engineering outputs, and commercial signals are being assembled.',
                signal: 'NEXORIN project command center', eyebrow: 'Project command sequence',
                metaLeft: projectNumber, metaRight: project,
                steps: ['Loading the project record', 'Resolving the nine-module workflow state', 'Assembling engineering and commercial signals', 'Opening the Project Cockpit'],
                duration: 1000, accent: '#13aaa2', secondary: '#e59835',
            },
        };

        return configs[kind] || configs.open;
    };

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[data-nxbom-navigate], .nxbom-registry-footer a');
        if (!anchor) return;
        const kind = anchor.dataset.nxbomNavigate || 'directory';
        navigate?.(event, anchor, navConfig(kind, anchor));
    });

    document.querySelectorAll('[data-nxbom-row]').forEach((row) => {
        const open = () => row.querySelector('a[data-nxbom-navigate="open"]')?.click();
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

    const filterForm = document.querySelector('[data-nxbom-filter-form]');
    if (filterForm) {
        filterForm.querySelector('[data-nxbom-auto-submit]')?.addEventListener('change', () => filterForm.requestSubmit());
        filterForm.addEventListener('submit', () => {
            if (filterForm.dataset.nxbomSubmitting === 'true') return;
            filterForm.dataset.nxbomSubmitting = 'true';
            showAction({
                kind: 'filter', icon: 'filter', title: 'Filtering BOM & BOQ Registry',
                copy: 'Visible project records are being resolved against the selected search and commercial state.',
                signal: 'NEXORIN commercial registry', eyebrow: 'Portfolio filter sequence',
                metaLeft: 'Company scope retained', metaRight: 'Role visibility preserved',
                steps: ['Reading active registry filters', 'Matching visible project records', 'Resolving engineering and commercial states', 'Refreshing the BOM & BOQ Registry'],
                duration: 860, accent: '#13aaa2', secondary: '#3679d8',
            });
        });
    }

    document.querySelectorAll('[data-nxbom-action-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (form.dataset.nxbomSubmitting === 'true') return;
            form.dataset.nxbomSubmitting = 'true';
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            }

            const type = form.dataset.nxbomActionForm;
            const configs = {
                generate: {
                    kind: 'calculate', icon: 'bom', title: 'Generating BOM & BOQ',
                    copy: 'NEXORIN is translating the latest engineering basis into controlled material, service, scope, and quantity lines.',
                    signal: 'NEXORIN quantity engine', eyebrow: 'Material orchestration sequence',
                    metaLeft: projectNumber, metaRight: project,
                    steps: ['Validating the technical source snapshot', 'Resolving required and conditional scope', 'Calculating quantities and material allowances', 'Recording the BOM & BOQ audit basis'],
                    duration: 1240, accent: '#13aaa2', secondary: '#e59835',
                },
                price: {
                    kind: 'calculate', icon: 'financial', title: 'Calculating BOQ Price',
                    copy: 'Price books, fallback rates, labor, logistics, contingency, tax, discount, and margin authority are being resolved.',
                    signal: 'NEXORIN commercial pricing engine', eyebrow: 'Controlled pricing sequence',
                    metaLeft: projectNumber, metaRight: 'Commercial trace preserved',
                    steps: ['Validating commercial parameters and authority', 'Resolving item price sources and coverage', 'Applying service, tax, discount, and rounding rules', 'Recording customer total and margin checks'],
                    duration: 1260, accent: '#13aaa2', secondary: '#755bc7',
                },
            };
            showAction(configs[type] || configs.generate);
        });
    });

    const materialGroups = document.querySelector('[data-nxbom-material-groups]');
    if (materialGroups) {
        const search = document.querySelector('[data-nxbom-item-search]');
        const scope = document.querySelector('[data-nxbom-scope-filter]');
        const price = document.querySelector('[data-nxbom-price-filter]');
        const output = document.querySelector('[data-nxbom-material-result]');
        const empty = document.querySelector('[data-nxbom-filter-empty]');
        const groups = [...materialGroups.querySelectorAll('[data-nxbom-group]')];
        const items = [...materialGroups.querySelectorAll('[data-nxbom-item]')];

        const applyFilters = () => {
            const term = (search?.value || '').trim().toLowerCase();
            const scopeValue = scope?.value || 'all';
            const priceValue = price?.value || 'all';
            let visible = 0;

            items.forEach((item) => {
                const matchesTerm = !term || (item.dataset.search || '').includes(term);
                const matchesScope = scopeValue === 'all' || item.dataset.scope === scopeValue;
                const matchesPrice = priceValue === 'all' || item.dataset.priceState === priceValue;
                const match = matchesTerm && matchesScope && matchesPrice;
                item.hidden = !match;
                if (match) visible += 1;
            });

            groups.forEach((group) => {
                const hasVisible = [...group.querySelectorAll('[data-nxbom-item]')].some((item) => !item.hidden);
                group.hidden = !hasVisible;
                if (hasVisible && (term || scopeValue !== 'all' || priceValue !== 'all')) group.open = true;
            });

            if (output) output.textContent = `${visible} visible ${visible === 1 ? 'line' : 'lines'}`;
            if (empty) empty.hidden = visible !== 0;
        };

        [search, scope, price].forEach((control) => {
            control?.addEventListener(control === search ? 'input' : 'change', applyFilters);
        });

        document.querySelector('[data-nxbom-expand-groups]')?.addEventListener('click', (event) => {
            const shouldOpen = groups.some((group) => !group.hidden && !group.open);
            groups.forEach((group) => { if (!group.hidden) group.open = shouldOpen; });
            event.currentTarget.textContent = shouldOpen ? 'Collapse groups' : 'Expand groups';
        });
    }

    document.querySelectorAll('[data-nxbom-format]').forEach((input) => {
        const output = input.closest('.nxbom-input-field')?.querySelector('[data-nxbom-format-output]');
        if (!output) return;
        const render = () => {
            const value = Number(input.value || 0);
            output.textContent = input.dataset.nxbomFormat === 'currency'
                ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
                : `${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
        };
        input.addEventListener('input', render);
        render();
    });

    const commandBar = document.querySelector('[data-nxbom-command-bar]');
    if (commandBar) {
        const links = [...commandBar.querySelectorAll('a[href^="#"]')];
        const sections = links.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
        const updateActive = () => {
            const marker = window.scrollY + 170;
            let active = sections[0];
            sections.forEach((section) => {
                if (section.offsetTop <= marker) active = section;
            });
            links.forEach((link) => link.classList.toggle('is-active', link.getAttribute('href') === `#${active?.id}`));
        };
        window.addEventListener('scroll', updateActive, { passive: true });
        updateActive();
    }

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-nxbom-submitting="true"]').forEach((form) => {
            delete form.dataset.nxbomSubmitting;
            form.querySelectorAll('[aria-busy="true"]').forEach((button) => {
                button.removeAttribute('aria-busy');
                button.disabled = false;
            });
        });
    });

    reveal();
})();
