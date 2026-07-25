(() => {
    'use strict';

    if (window.__nexorinProjectFlow41A) return;
    window.__nexorinProjectFlow41A = true;

    const engine = window.NexorinEngineeringAction;
    if (!engine) return;

    const dashboard = document.querySelector('.dashboard-page');
    const directory = document.querySelector('.project-directory-page');
    const cockpit = document.querySelector('[data-cockpit-root]');
    const intake = document.querySelector('[data-project-intake]');
    const editor = document.querySelector('[data-project-editor]');
    const relevantPage = dashboard || directory || cockpit || intake || editor;
    const nativeSubmit = HTMLFormElement.prototype.submit;
    const askConfirm = (message, options = {}) => window.NexorinConfirm?.ask
        ? window.NexorinConfirm.ask(message, options)
        : Promise.resolve(window.confirm(message));
    const intakeForm = document.querySelector('[data-project-intake-form]');

    if (!relevantPage) return;

    const serializeForm = (form) => form
        ? [...new FormData(form).entries()]
            .filter(([key]) => key !== '_token')
            .map(([key, value]) => `${key}:${String(value)}`)
            .sort()
            .join('|')
        : '';
    const intakeBaseline = serializeForm(intakeForm);
    const intakeHasChanged = () => Boolean(intakeForm) && serializeForm(intakeForm) !== intakeBaseline;

    const normalizedPath = (href) => {
        try {
            return new URL(href, window.location.href).pathname.replace(/\/+$/, '');
        } catch (_) {
            return '';
        }
    };

    const cleanText = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    const normalizeMetric = (value) => cleanText(value).replace(/(\d),(\d)/g, '$1.$2');

    const projectIdentity = (source) => {
        const row = source?.closest?.('[data-row-href], [data-project-module]') || source;
        const projectNumber = cleanText(
            row?.dataset?.projectNumber
            || cockpit?.dataset?.projectNumber
            || row?.querySelector?.('.project-registry-primary-copy small')?.textContent
            || ''
        );
        const projectName = cleanText(
            row?.dataset?.projectName
            || cockpit?.dataset?.projectName
            || row?.querySelector?.('.project-registry-primary-copy strong')?.textContent
            || row?.querySelector?.('.project-cell-copy strong')?.textContent
            || ''
        );

        return { projectNumber, projectName };
    };

    const moduleProfiles = [
        {
            key: 'load', pattern: /\/projects\/[^/]+\/load-analysis$/i, index: '02', icon: 'load',
            title: 'Opening Load Analysis',
            copy: 'Demand profile, equipment schedules, bill evidence, and validation context are being prepared.',
            signal: 'NEXORIN load intelligence', accent: '#3978d8',
            steps: ['Retaining the active project context', 'Loading demand and equipment intelligence', 'Resolving bill history and validation signals', 'Preparing the Load Analysis workspace'],
        },
        {
            key: 'solar', pattern: /\/projects\/[^/]+\/location-solar-data$/i, index: '03', icon: 'solar',
            title: 'Opening Location & Solar',
            copy: 'Coordinates, source traceability, monthly irradiance, and verification context are being prepared.',
            signal: 'NEXORIN solar resource', accent: '#d89b35', secondary: '#63d7c9',
            steps: ['Retaining the active project context', 'Loading coordinates and source identity', 'Resolving the twelve-month resource profile', 'Preparing the Location & Solar workspace'],
        },
        {
            key: 'roof', pattern: /\/projects\/[^/]+\/roof-site-survey$/i, index: '04', icon: 'roof',
            title: 'Opening Roof & Site Survey',
            copy: 'Survey foundation, roof geometry, evidence, warnings, and verification context are being prepared.',
            signal: 'NEXORIN site intelligence', accent: '#13aaa2',
            steps: ['Retaining the active project context', 'Loading survey and roof-surface evidence', 'Resolving exclusions, shading, and warning gates', 'Preparing the Roof & Site Survey workspace'],
        },
        {
            key: 'recommendation', pattern: /\/projects\/[^/]+\/system-recommendation$/i, index: '05', icon: 'recommendation',
            title: 'Opening System Recommendation',
            copy: 'Source readiness, constraints, alternative scores, and the decision audit trail are being prepared.',
            signal: 'NEXORIN decision engine', accent: '#13aaa2', secondary: '#f3a04f',
            steps: ['Retaining the active project context', 'Loading source readiness and decision inputs', 'Resolving constraints and alternative score traces', 'Preparing the System Recommendation workspace'],
        },
        {
            key: 'technical', pattern: /\/projects\/[^/]+\/technical-calculation$/i, index: '06', icon: 'technical',
            title: 'Opening Technical Calculation',
            copy: 'PV array, inverter, string, cable, and battery design context are being assembled.',
            signal: 'NEXORIN technical engine', accent: '#3978d8', secondary: '#f3a04f',
            steps: ['Retaining the approved planning basis', 'Loading selected equipment and site constraints', 'Resolving electrical design evidence', 'Preparing the Technical Calculation workspace'],
        },
        {
            key: 'bom', pattern: /\/projects\/[^/]+\/bom$/i, index: '07', icon: 'bom',
            title: 'Opening BOM & BOQ',
            copy: 'Selected components, engineering quantities, supplier basis, and pricing context are being prepared.',
            signal: 'NEXORIN quantity engine', accent: '#7659ca', secondary: '#f3a04f',
            steps: ['Retaining the verified technical configuration', 'Loading selected equipment and quantities', 'Resolving supplier and price-basis context', 'Preparing the BOM & BOQ workspace'],
        },
        {
            key: 'financial', pattern: /\/projects\/[^/]+\/financial-analysis$/i, index: '08', icon: 'financial',
            title: 'Opening Financial Analysis',
            copy: 'Capital cost, tariff assumptions, savings, and cash-flow evidence are being prepared.',
            signal: 'NEXORIN financial engine', accent: '#0f9b8f', secondary: '#f3a04f',
            steps: ['Retaining the active commercial basis', 'Loading capital and operating assumptions', 'Resolving savings and cash-flow context', 'Preparing the Financial Analysis workspace'],
        },
        {
            key: 'quotation', pattern: /\/projects\/[^/]+\/quotation$/i, index: '09', icon: 'quotation',
            title: 'Opening Quotation Workspace',
            copy: 'Customer-facing offer, approval state, report context, and commercial trace are being prepared.',
            signal: 'NEXORIN commercial engine', accent: '#7659ca', secondary: '#f3a04f',
            steps: ['Retaining the approved project basis', 'Loading quotation and approval context', 'Resolving customer-facing report data', 'Preparing the Quotation workspace'],
        },
    ];

    const moduleDestination = (path, source) => {
        const profile = moduleProfiles.find((candidate) => candidate.pattern.test(path));
        if (!profile) return null;

        const identity = projectIdentity(source);
        const state = cleanText(source?.dataset?.projectModuleState || '');
        const metric = normalizeMetric(source?.dataset?.projectModuleMetric || '');
        const rightMeta = [state, metric].filter(Boolean).join(' · ') || 'Controlled module handoff';
        const leftMeta = [identity.projectNumber, `Step ${profile.index}`].filter(Boolean).join(' · ');

        return {
            kind: 'open',
            icon: profile.icon,
            title: profile.title,
            copy: profile.copy,
            signal: profile.signal,
            metaLeft: leftMeta || `Step ${profile.index}`,
            metaRight: rightMeta,
            duration: 880,
            steps: profile.steps,
            accent: profile.accent,
            secondary: profile.secondary || '#f3a04f',
            eyebrow: `Project workflow · Step ${profile.index}`,
        };
    };

    const projectDestination = (href, source = null) => {
        const path = normalizedPath(href);
        const identity = projectIdentity(source);

        if (/\/projects\/create$/i.test(path)) {
            return {
                kind: 'open', icon: 'project', title: 'Preparing New Project',
                copy: 'A controlled intake workspace is being prepared for the customer, site, ownership, and initial system brief.',
                signal: 'NEXORIN project intake', metaLeft: 'New controlled record', metaRight: 'Draft context protected',
                duration: 820, accent: '#13aaa2', secondary: '#f3a04f', eyebrow: 'Project command sequence',
                steps: ['Securing the company workspace', 'Preparing customer and site controls', 'Initializing the project brief', 'Opening the New Project workspace'],
            };
        }

        if (/\/projects\/[^/]+\/edit$/i.test(path)) {
            return {
                kind: 'open', icon: 'edit', title: 'Opening Project Editor',
                copy: 'The current project snapshot, ownership context, and controlled revision history are being prepared.',
                signal: 'NEXORIN revision control',
                metaLeft: identity.projectNumber || 'Existing project record',
                metaRight: 'Audit context preserved',
                duration: 840, accent: '#3978d8', secondary: '#f3a04f', eyebrow: 'Controlled project revision',
                steps: ['Loading the current project snapshot', 'Securing ownership and workflow context', 'Preparing revision controls', 'Opening the Project Editor'],
            };
        }

        if (/\/projects\/[^/]+$/i.test(path)) {
            return {
                kind: 'open', icon: 'cockpit', title: 'Opening Project Cockpit',
                copy: 'Project identity, workflow state, engineering outputs, warnings, and commercial signals are being assembled.',
                signal: 'NEXORIN project command center',
                metaLeft: identity.projectNumber || 'Project context',
                metaRight: identity.projectName || 'Decision context preserved',
                duration: 880, accent: '#13aaa2', secondary: '#f3a04f', eyebrow: 'Project command sequence',
                steps: ['Loading the project record', 'Resolving the nine-module workflow state', 'Assembling engineering and commercial signals', 'Opening the Project Cockpit'],
            };
        }

        if (/\/projects$/i.test(path)) {
            return {
                kind: 'open', icon: 'portfolio', title: 'Opening Project Portfolio',
                copy: 'The visible project register, workflow signals, filters, and access scope are being synchronized.',
                signal: 'NEXORIN portfolio command', metaLeft: 'Company workspace', metaRight: 'Access scope preserved',
                duration: 780, accent: '#13aaa2', secondary: '#f3a04f', eyebrow: 'Portfolio command sequence',
                steps: ['Securing company access context', 'Loading the project registry', 'Resolving workflow and ownership signals', 'Opening the Project Portfolio'],
            };
        }

        return cockpit ? moduleDestination(path, source) : null;
    };

    const canInterceptAnchor = (event, anchor) => {
        if (!anchor || event.defaultPrevented || event.button !== 0) return false;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
        if (anchor.target && anchor.target !== '_self') return false;
        if (anchor.hasAttribute('download')) return false;
        const raw = anchor.getAttribute('href') || '';
        if (!raw || raw.startsWith('#') || raw.startsWith('javascript:') || raw.startsWith('mailto:') || raw.startsWith('tel:')) return false;
        return true;
    };

    const navigateTo = (href, config) => {
        const { duration } = engine.show(config);
        window.setTimeout(() => { window.location.href = href; }, duration);
    };

    document.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, select, textarea, label')) return;
        const row = event.target.closest('[data-row-href]');
        if (!row || !(dashboard?.contains(row) || directory?.contains(row))) return;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const href = row.dataset.rowHref;
        const config = projectDestination(href, row);
        if (!href || !config) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        navigateTo(href, config);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const row = event.target.closest('[data-row-href]');
        if (!row || !(dashboard?.contains(row) || directory?.contains(row))) return;

        const href = row.dataset.rowHref;
        const config = projectDestination(href, row);
        if (!href || !config) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        navigateTo(href, config);
    }, true);

    document.addEventListener('click', async (event) => {
        const anchor = event.target.closest('a[href]');
        if (!canInterceptAnchor(event, anchor)) return;
        if (!relevantPage.contains(anchor) && !anchor.closest('.topbar, .sidebar')) return;

        const config = projectDestination(anchor.href, anchor);
        if (!config) return;

        if (editor?.classList.contains('is-dirty')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const confirmed = await askConfirm(
                'Leave this page? Your unsaved project revision will be lost.',
                {
                    tone: 'danger',
                    title: 'Leave the project editor?',
                    confirmLabel: 'Leave editor',
                    control: 'Unsaved project revision detected',
                    result: 'Current revision changes will be discarded',
                },
            );
            if (!confirmed) return;
        }

        if (intake && !intake.contains(anchor) && intakeHasChanged()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const confirmed = await askConfirm(
                'Leave this page? Your unsaved project intake will be lost.',
                {
                    tone: 'danger',
                    title: 'Leave the unfinished project intake?',
                    confirmLabel: 'Leave page',
                    control: 'Unsaved project evidence detected',
                    result: 'Current intake changes will be discarded',
                },
            );
            if (!confirmed) return;
        }

        window.__nexorinProjectNavigationApproved = true;
        event.preventDefault();
        navigateTo(anchor.href, config);
    });

    const bindForm = (form, config) => {
        if (!form) return;
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || !form.checkValidity() || form.dataset.nxProjectFlowSubmitting === 'true') return;

            event.preventDefault();
            form.dataset.nxProjectFlowSubmitting = 'true';
            window.__nexorinProjectNavigationApproved = true;

            const submitter = event.submitter || form.querySelector('[type="submit"]');
            form.querySelectorAll('[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });

            if (submitter) {
                const label = submitter.querySelector('[data-editor-submit-label], [data-project-submit-label], span');
                if (label && !label.querySelector('svg')) label.textContent = 'Processing…';
            }

            const { duration } = engine.show(config);
            window.setTimeout(() => nativeSubmit.call(form), duration);
        });
    };

    bindForm(document.querySelector('[data-project-intake-form]'), {
        kind: 'save', icon: 'project', title: 'Creating Project Record',
        copy: 'Customer, site, ownership, and system-direction inputs are being validated before the controlled project record is created.',
        signal: 'NEXORIN project intake', metaLeft: 'Project number generated securely', metaRight: 'Draft audit context created',
        duration: 1060, accent: '#13aaa2', secondary: '#f3a04f', eyebrow: 'Controlled record creation',
        steps: ['Validating customer and site identity', 'Checking ownership and system direction', 'Creating the controlled project record', 'Preparing the Project Cockpit'],
    });

    bindForm(document.querySelector('[data-project-editor-form]'), {
        kind: 'save', icon: 'edit', title: 'Saving Project Revision',
        copy: 'The edited fields are being validated and committed to the protected project record without losing workflow context.',
        signal: 'NEXORIN revision control',
        metaLeft: cockpit?.dataset?.projectNumber || editor?.dataset?.projectNumber || 'Existing project record',
        metaRight: 'Audit context preserved',
        duration: 1020, accent: '#3978d8', secondary: '#f3a04f', eyebrow: 'Controlled project revision',
        steps: ['Validating revised project fields', 'Protecting ownership and access context', 'Updating the controlled project record', 'Synchronizing the Project Cockpit'],
    });

    const filterForm = directory?.querySelector('.project-filter-toolbar');
    bindForm(filterForm, {
        kind: 'filter', icon: 'filter', title: 'Refreshing Project Portfolio',
        copy: 'The selected filters are being applied to rebuild the visible project register and workflow signals.',
        signal: 'NEXORIN portfolio query', metaLeft: 'Current filters applied', metaRight: 'Access scope preserved',
        duration: 760, accent: '#3978d8', secondary: '#f3a04f', eyebrow: 'Portfolio query sequence',
        steps: ['Reading the selected project criteria', 'Querying accessible project records', 'Resolving workflow and ownership signals', 'Rendering the updated portfolio'],
    });
})();
