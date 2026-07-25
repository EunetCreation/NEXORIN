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
        const directory = document.querySelector('[data-nxq-directory]');
        const workspace = document.querySelector('[data-nxq-workspace]');
        if (!directory && !workspace) return;

        const actionEngine = window.NexorinEngineeringAction;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const project = workspace?.dataset.nxqProjectName || 'Quotation workspace';
        const projectNumber = workspace?.dataset.nxqProjectNumber || 'Project context';

        const revealNodes = Array.from(document.querySelectorAll('[data-nxq-reveal]'));
        revealNodes.forEach((node) => node.classList.add('nxq-reveal'));

        if (reducedMotion || !('IntersectionObserver' in window)) {
            revealNodes.forEach((node) => node.classList.add('is-visible'));
        } else {
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                });
            }, { threshold: 0.06, rootMargin: '0px 0px -3% 0px' });
            revealNodes.forEach((node) => revealObserver.observe(node));
        }

        document.querySelectorAll('[data-nxq-card]').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                card.style.setProperty('--mouse-x', `${event.clientX - bounds.left}px`);
                card.style.setProperty('--mouse-y', `${event.clientY - bounds.top}px`);
            });
        });

        const showAction = (config) => actionEngine?.show(config) || null;
        const navigate = (event, anchor, config) => actionEngine?.navigate(event, anchor, config) || false;

        const navConfig = (kind, anchor) => {
            const rowProject = anchor?.dataset?.nxqProject || project;
            const rowNumber = anchor?.dataset?.nxqProjectNumber || projectNumber;
            const configs = {
                open: {
                    kind: 'open', icon: 'quotation', title: 'Opening Quotation Workspace',
                    copy: 'Customer identity, priced scope, financial snapshot, revision controls, and document-release state are being assembled.',
                    signal: 'NEXORIN customer release desk', eyebrow: 'Quotation workspace handoff',
                    metaLeft: rowNumber, metaRight: rowProject,
                    steps: ['Retaining project and customer identity', 'Loading priced scope and financial snapshot', 'Resolving revision and release controls', 'Opening the Quotation Command Deck'],
                    duration: 1020, accent: '#13aaa2', secondary: '#7659ca',
                },
                directory: {
                    kind: 'open', icon: 'portfolio', title: 'Opening Quotation Registry',
                    copy: 'Visible customer projects, release readiness, controlled revisions, and quotation milestones are being restored.',
                    signal: 'NEXORIN quotation registry', eyebrow: 'Customer proposal portfolio handoff',
                    metaLeft: 'Company context retained', metaRight: 'Customer release state preserved',
                    steps: ['Retaining company and access context', 'Loading visible customer projects', 'Resolving quotation lifecycle states', 'Opening the Quotation Registry'],
                    duration: 920, accent: '#13aaa2', secondary: '#7659ca',
                },
                financial: {
                    kind: 'open', icon: 'financial', title: 'Opening Financial Analysis',
                    copy: 'The current investment model, customer-safe metrics, and financial decision context are being restored.',
                    signal: 'NEXORIN financial decision engine', eyebrow: 'Financial source handoff',
                    metaLeft: rowNumber, metaRight: rowProject,
                    steps: ['Retaining project and access context', 'Loading the active financial assumptions', 'Resolving investment results and source trace', 'Opening Financial Analysis'],
                    duration: 980, accent: '#13aaa2', secondary: '#7659ca',
                },
                bom: {
                    kind: 'open', icon: 'bom', title: 'Opening BOM & BOQ',
                    copy: 'The engineering quantity basis, commercial pricing trace, and approved customer total are being restored.',
                    signal: 'NEXORIN material orchestration', eyebrow: 'Commercial source handoff',
                    metaLeft: rowNumber, metaRight: rowProject,
                    steps: ['Retaining project and access context', 'Loading material and pricing records', 'Resolving commercial coverage and totals', 'Opening BOM & BOQ'],
                    duration: 980, accent: '#13aaa2', secondary: '#e59835',
                },
            };
            return configs[kind] || configs.open;
        };

        document.addEventListener('click', (event) => {
            const anchor = event.target.closest('a[data-nxq-navigate], .nxq-registry-footer a');
            if (!anchor) return;
            navigate(event, anchor, navConfig(anchor.dataset.nxqNavigate || 'directory', anchor));
        });

        document.querySelectorAll('[data-nxq-row]').forEach((row) => {
            const open = () => row.querySelector('a[data-nxq-navigate="open"]')?.click();
            row.addEventListener('click', (event) => {
                if (event.target.closest('a,button,input,select,textarea,form,details,summary')) return;
                open();
            });
            row.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                open();
            });
        });

        const filterForm = document.querySelector('[data-nxq-filter-form]');
        if (filterForm) {
            filterForm.querySelector('[data-nxq-auto-submit]')?.addEventListener('change', () => filterForm.requestSubmit());
            filterForm.addEventListener('submit', () => {
                if (filterForm.dataset.nxqSubmitting === 'true') return;
                filterForm.dataset.nxqSubmitting = 'true';
                showAction({
                    kind: 'filter', icon: 'filter', title: 'Filtering Quotation Registry',
                    copy: 'Visible customer projects are being resolved against the selected search and quotation lifecycle state.',
                    signal: 'NEXORIN quotation registry', eyebrow: 'Portfolio filter sequence',
                    metaLeft: 'Company scope retained', metaRight: 'Role visibility preserved',
                    steps: ['Reading active registry filters', 'Matching visible customer projects', 'Resolving release and lifecycle states', 'Refreshing the Quotation Registry'],
                    duration: 860, accent: '#13aaa2', secondary: '#7659ca',
                });
            });
        }

        document.querySelectorAll('[data-nxq-scroll]').forEach((anchor) => {
            anchor.addEventListener('click', (event) => {
                const target = document.querySelector(anchor.getAttribute('href'));
                if (!target) return;
                event.preventDefault();
                target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
            });
        });

        const sectionNav = document.querySelector('[data-nxq-section-nav]');
        const sections = Array.from(document.querySelectorAll('[data-nxq-section]'));
        if (sectionNav && sections.length && 'IntersectionObserver' in window) {
            const navLinks = new Map(Array.from(sectionNav.querySelectorAll('a[href^="#"]')).map((link) => [link.getAttribute('href').slice(1), link]));
            const sectionObserver = new IntersectionObserver((entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                if (!visible) return;
                navLinks.forEach((link, id) => link.classList.toggle('is-active', id === visible.target.id));
            }, { rootMargin: '-24% 0px -63% 0px', threshold: [0.02, 0.15, 0.35] });
            sections.forEach((section) => sectionObserver.observe(section));
        }

        const decisionPanel = document.querySelector('[data-nxq-decision-panel]');
        if (decisionPanel) {
            const buttons = Array.from(decisionPanel.querySelectorAll('[data-nxq-decision]'));
            const forms = Array.from(decisionPanel.querySelectorAll('[data-nxq-decision-form]'));
            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const key = button.dataset.nxqDecision;
                    const isAlreadyActive = button.classList.contains('is-active');
                    buttons.forEach((candidate) => candidate.classList.remove('is-active'));
                    forms.forEach((form) => { form.hidden = true; });
                    if (isAlreadyActive) return;
                    button.classList.add('is-active');
                    const form = decisionPanel.querySelector(`[data-nxq-decision-form="${CSS.escape(key)}"]`);
                    if (form) {
                        form.hidden = false;
                        form.querySelector('textarea,input,select')?.focus({ preventScroll: true });
                        form.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'nearest' });
                    }
                });
            });
        }

        const generateForm = document.querySelector('[data-nxq-generate-form]');
        if (generateForm) {
            const dirtyState = document.querySelector('[data-nxq-dirty-state]');
            let dirty = false;
            const markDirty = () => {
                dirty = true;
                dirtyState?.classList.add('is-dirty');
                if (dirtyState) dirtyState.textContent = 'Unsaved quotation changes';
            };
            generateForm.querySelectorAll('input,select,textarea').forEach((control) => {
                control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', markDirty);
            });
            window.addEventListener('beforeunload', (event) => {
                if (!dirty || generateForm.dataset.nxqSubmitting === 'true') return;
                event.preventDefault();
                event.returnValue = '';
            });
            generateForm.addEventListener('submit', () => {
                if (generateForm.dataset.nxqSubmitting === 'true') return;
                generateForm.dataset.nxqSubmitting = 'true';
                const button = generateForm.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
                showAction({
                    kind: 'calculate', icon: 'quotation', title: 'Generating Quotation Revision',
                    copy: 'The priced BOQ, customer-safe financial summary, commercial terms, and revision-controlled proposal snapshot are being assembled.',
                    signal: 'NEXORIN customer release desk', eyebrow: 'Quotation generation sequence',
                    metaLeft: projectNumber, metaRight: project,
                    steps: ['Validating priced BOQ and Financial Analysis sources', 'Building the customer-safe technical and commercial scope', 'Recording terms, warnings, and the controlled revision snapshot', 'Opening the updated Quotation Command Deck'],
                    duration: 1260, accent: '#13aaa2', secondary: '#7659ca',
                });
            });
        }

        const actionConfigs = {
            approve: {
                kind: 'verify', icon: 'auth', title: 'Approving Quotation Snapshot',
                copy: 'The working revision, source snapshot, warnings, and customer-safe document state are being verified for controlled approval.',
                steps: ['Validating the active working revision', 'Checking source fingerprint and release warnings', 'Recording the approval authority and snapshot state', 'Returning to the Quotation Command Deck'],
                secondary: '#f3a04f',
            },
            send: {
                kind: 'save', icon: 'quotation', title: 'Sending Approved Quotation',
                copy: 'The approved client PDF, delivery channel, recipient, and controlled customer milestone are being recorded.',
                steps: ['Validating the approved and current client PDF', 'Resolving the delivery channel and recipient', 'Recording the sent customer milestone', 'Opening the customer response stage'],
                secondary: '#7659ca',
            },
            accept: {
                kind: 'verify', icon: 'auth', title: 'Recording Customer Acceptance',
                copy: 'The accepted revision, customer note, actor, and project lifecycle transition are being recorded.',
                steps: ['Validating the active sent revision', 'Recording the customer acceptance note', 'Updating the controlled quotation event', 'Opening the accepted customer state'],
                secondary: '#13aaa2',
            },
            reject: {
                kind: 'save', icon: 'quotation', title: 'Recording Customer Rejection',
                copy: 'The rejected revision, required reason, actor, and terminal quotation event are being recorded.',
                steps: ['Validating the active sent revision', 'Recording the customer rejection reason', 'Updating the controlled quotation event', 'Opening the rejected customer state'],
                secondary: '#b74a43',
            },
            cancel: {
                kind: 'save', icon: 'quotation', title: 'Cancelling Active Quotation',
                copy: 'The active revision, cancellation reason, actor, and terminal quotation state are being recorded.',
                steps: ['Validating cancellation authority and lifecycle state', 'Recording the cancellation context', 'Closing customer response actions', 'Opening the cancelled quotation state'],
                secondary: '#b74a43',
            },
        };

        document.querySelectorAll('[data-nxq-action-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                if (form.dataset.nxqSubmitting === 'true') return;
                form.dataset.nxqSubmitting = 'true';
                const button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
                const key = form.dataset.nxqActionForm;
                const preset = actionConfigs[key] || actionConfigs.approve;
                showAction({
                    ...preset,
                    signal: 'NEXORIN customer release desk',
                    eyebrow: 'Controlled quotation lifecycle sequence',
                    metaLeft: projectNumber,
                    metaRight: project,
                    duration: 1100,
                    accent: '#13aaa2',
                });
            });
        });

        const actionMenus = Array.from(document.querySelectorAll('.nxq-action-menu'));
        const syncActionMenuState = () => {
            const deck = document.querySelector('.nxq-command-deck');
            if (deck) deck.classList.toggle('has-open-menu', actionMenus.some((menu) => menu.open));
        };

        actionMenus.forEach((menu) => {
            menu.addEventListener('toggle', () => {
                if (menu.open) {
                    actionMenus.forEach((other) => {
                        if (other !== menu) other.removeAttribute('open');
                    });
                }
                syncActionMenuState();
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('.nxq-action-menu')) return;
            document.querySelectorAll('.nxq-action-menu[open]').forEach((menu) => menu.removeAttribute('open'));
            syncActionMenuState();
        });

        window.addEventListener('pageshow', () => {
            document.querySelectorAll('[data-nxq-submitting="true"]').forEach((node) => {
                delete node.dataset.nxqSubmitting;
                node.querySelectorAll('[aria-busy="true"]').forEach((button) => {
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                });
            });
        });
    });
})();
