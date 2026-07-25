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
        const directory = document.querySelector('[data-nxtc-directory]');
        const workspace = document.querySelector('[data-nxtc-workspace]');
        if (!directory && !workspace) return;

        const actionEngine = window.NexorinEngineeringAction;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const revealNodes = Array.from(document.querySelectorAll('[data-nxtc-reveal]'));
        revealNodes.forEach((node) => node.classList.add('nxtc-reveal'));
        if (reducedMotion || !('IntersectionObserver' in window)) {
            revealNodes.forEach((node) => node.classList.add('is-visible'));
        } else {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -3% 0px' });
            revealNodes.forEach((node) => observer.observe(node));
        }

        document.querySelectorAll('[data-nxtc-card]').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                card.style.setProperty('--mouse-x', `${event.clientX - bounds.left}px`);
                card.style.setProperty('--mouse-y', `${event.clientY - bounds.top}px`);
            });
        });

        const showAction = (config) => {
            if (!actionEngine) return null;
            return actionEngine.show(config);
        };

        const navigate = (event, anchor, config) => {
            if (!actionEngine) return false;
            return actionEngine.navigate(event, anchor, config);
        };

        const configForLink = (anchor) => {
            const kind = anchor.dataset.nxtcNavigate || 'open';
            const project = anchor.dataset.nxtcProject || document.querySelector('[data-nxtc-project-name]')?.textContent?.trim() || 'Technical Calculation';
            const projectNumber = anchor.dataset.nxtcProjectNumber || document.querySelector('[data-nxtc-project-number]')?.textContent?.trim() || 'Project context';

            const configs = {
                open: {
                    kind: 'open',
                    icon: 'technical',
                    title: 'Opening Technical Calculation',
                    copy: 'Project evidence, equipment assumptions, electrical constraints, and audit context are being assembled.',
                    signal: 'NEXORIN technical design engine',
                    eyebrow: 'Electrical design workspace',
                    metaLeft: projectNumber,
                    metaRight: project,
                    steps: [
                        'Retaining the approved planning basis',
                        'Loading source evidence and equipment assumptions',
                        'Resolving calculation, warning, and audit context',
                        'Opening the Technical Calculation workspace',
                    ],
                    duration: 1040,
                    accent: '#17aaa3',
                    secondary: '#e59835',
                },
                projects: {
                    kind: 'open',
                    icon: 'portfolio',
                    title: 'Opening Project Portfolio',
                    copy: 'Project identity, ownership, workflow progress, and engineering readiness are being restored.',
                    signal: 'NEXORIN project portfolio',
                    eyebrow: 'Portfolio handoff',
                    metaLeft: 'Company context retained',
                    metaRight: 'Project visibility preserved',
                    steps: [
                        'Retaining company and access context',
                        'Loading visible project records',
                        'Resolving workflow and engineering states',
                        'Opening the Project Portfolio',
                    ],
                    duration: 920,
                    accent: '#17aaa3',
                    secondary: '#e59835',
                },
                directory: {
                    kind: 'open',
                    icon: 'portfolio',
                    title: 'Opening Calculation Registry',
                    copy: 'NEXORIN is restoring the visible engineering portfolio, filters, and calculation milestones.',
                    signal: 'NEXORIN calculation registry',
                    eyebrow: 'Portfolio handoff',
                    metaLeft: 'Company context retained',
                    metaRight: 'Registry state preserved',
                    steps: [
                        'Retaining company and access context',
                        'Loading project calculation milestones',
                        'Synchronizing verification states',
                        'Opening the calculation registry',
                    ],
                    duration: 920,
                    accent: '#17aaa3',
                    secondary: '#3679d8',
                },
                cockpit: {
                    kind: 'open',
                    icon: 'cockpit',
                    title: 'Opening Project Cockpit',
                    copy: 'Project identity, workflow state, engineering outputs, and commercial signals are being assembled.',
                    signal: 'NEXORIN project command center',
                    eyebrow: 'Project command sequence',
                    metaLeft: projectNumber,
                    metaRight: project,
                    steps: [
                        'Loading the project record',
                        'Resolving the nine-module workflow state',
                        'Assembling engineering and commercial signals',
                        'Opening the Project Cockpit',
                    ],
                    duration: 1000,
                    accent: '#17aaa3',
                    secondary: '#e59835',
                },
                bom: {
                    kind: 'open',
                    icon: 'bom',
                    title: 'Opening BOM & BOQ',
                    copy: 'The latest technical result, effective overrides, quantity basis, and commercial trace are being retained.',
                    signal: 'NEXORIN material orchestration',
                    eyebrow: 'Downstream engineering handoff',
                    metaLeft: projectNumber,
                    metaRight: 'Technical basis retained',
                    steps: [
                        'Retaining the effective technical result',
                        'Loading selected equipment and quantities',
                        'Resolving traceability and pricing context',
                        'Opening the BOM & BOQ workspace',
                    ],
                    duration: 1000,
                    accent: '#17aaa3',
                    secondary: '#755bc7',
                },
            };

            return configs[kind] || configs.open;
        };

        document.addEventListener('click', (event) => {
            const anchor = event.target.closest('a[data-nxtc-navigate], .nxtc-registry-footer a');
            if (!anchor) return;
            const config = anchor.matches('[data-nxtc-navigate]')
                ? configForLink(anchor)
                : configForLink({ dataset: { nxtcNavigate: 'directory' } });
            navigate(event, anchor, config);
        });

        document.querySelectorAll('[data-nxtc-row]').forEach((row) => {
            row.addEventListener('click', (event) => {
                if (event.target.closest('a,button,input,select,textarea,form')) return;
                const anchor = row.querySelector('a[data-nxtc-navigate="open"]');
                if (!anchor) return;
                anchor.click();
            });
            row.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                row.querySelector('a[data-nxtc-navigate="open"]')?.click();
            });
        });

        const filterForm = document.querySelector('[data-nxtc-filter-form]');
        if (filterForm) {
            filterForm.querySelector('[data-nxtc-auto-submit]')?.addEventListener('change', () => filterForm.requestSubmit());
            filterForm.addEventListener('submit', () => {
                if (filterForm.dataset.nxtcSubmitting === 'true') return;
                filterForm.dataset.nxtcSubmitting = 'true';
                showAction({
                    kind: 'filter',
                    icon: 'filter',
                    title: 'Filtering Calculation Registry',
                    copy: 'Visible project records are being resolved against the selected search and calculation state.',
                    signal: 'NEXORIN registry control',
                    eyebrow: 'Portfolio filter sequence',
                    metaLeft: 'Company scope retained',
                    metaRight: 'Role visibility preserved',
                    steps: [
                        'Reading the active registry filters',
                        'Matching visible project records',
                        'Resolving calculation and verification states',
                        'Refreshing the calculation registry',
                    ],
                    duration: 860,
                    accent: '#17aaa3',
                    secondary: '#3679d8',
                });
            });
        }

        const formConfigs = {
            run: {
                kind: 'calculate',
                icon: 'technical',
                title: 'Running Technical Calculation',
                copy: 'NEXORIN is resolving PV, inverter, string, battery, cable, protection, warning, and traceability outputs.',
                signal: 'NEXORIN technical design engine',
                eyebrow: 'Controlled electrical calculation',
                steps: [
                    'Validating source evidence and engineering inputs',
                    'Resolving PV, inverter, and energy sizing',
                    'Checking string, MPPT, cable, and protection limits',
                    'Recording warnings, formulas, and the source snapshot',
                ],
                duration: 1280,
                accent: '#17aaa3',
                secondary: '#e59835',
            },
            verify: {
                kind: 'verify',
                icon: 'verify',
                title: 'Verifying Technical Calculation',
                copy: 'The current result, source freshness, warnings, and effective values are being secured as an engineer-verified basis.',
                signal: 'NEXORIN engineering authority',
                eyebrow: 'Verification control sequence',
                steps: [
                    'Checking result availability and source freshness',
                    'Reviewing warning and override state',
                    'Recording engineer verification authority',
                    'Locking the verified calculation basis',
                ],
                duration: 1050,
                accent: '#1c956e',
                secondary: '#17aaa3',
            },
            override: {
                kind: 'save',
                icon: 'edit',
                title: 'Applying Engineer Override',
                copy: 'The selected effective value, original engine result, reason, and downstream trace are being preserved.',
                signal: 'NEXORIN controlled revision',
                eyebrow: 'Engineer override sequence',
                steps: [
                    'Validating the selected result field',
                    'Preserving the original engine value',
                    'Applying the documented effective value',
                    'Updating downstream engineering traceability',
                ],
                duration: 1040,
                accent: '#e59835',
                secondary: '#17aaa3',
            },
            reset: {
                kind: 'save',
                icon: 'edit',
                title: 'Restoring Engine Result',
                copy: 'The active override is being retired and the preserved engine value restored as the effective result.',
                signal: 'NEXORIN controlled revision',
                eyebrow: 'Override reset sequence',
                steps: [
                    'Locating the active override record',
                    'Restoring the preserved engine result',
                    'Updating formula and downstream effective values',
                    'Recording the reset in the audit trail',
                ],
                duration: 980,
                accent: '#e59835',
                secondary: '#3679d8',
            },
        };

        document.querySelectorAll('form[data-nxtc-action-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.nxtcSubmitting === 'true') {
                    event.preventDefault();
                    return;
                }
                form.dataset.nxtcSubmitting = 'true';
                const button = event.submitter || form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
                const action = form.dataset.nxtcAction || 'run';
                const config = { ...(formConfigs[action] || formConfigs.run) };
                config.metaLeft = form.dataset.nxtcProjectNumber || document.querySelector('[data-nxtc-project-number]')?.textContent?.trim() || 'Project context';
                config.metaRight = form.dataset.nxtcProject || document.querySelector('[data-nxtc-project-name]')?.textContent?.trim() || 'Traceability preserved';
                showAction(config);
            });
        });

        const nav = document.querySelector('[data-nxtc-section-nav]');
        if (nav) {
            const links = Array.from(nav.querySelectorAll('a[href^="#"]'));
            const sections = links.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
            links.forEach((link) => {
                link.addEventListener('click', (event) => {
                    const target = document.querySelector(link.getAttribute('href'));
                    if (!target) return;
                    event.preventDefault();
                    target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
                });
            });
            if ('IntersectionObserver' in window && sections.length) {
                const sectionObserver = new IntersectionObserver((entries) => {
                    const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                    if (!visible) return;
                    links.forEach((link) => link.classList.toggle('is-active', link.getAttribute('href') === `#${visible.target.id}`));
                }, { rootMargin: '-18% 0px -68% 0px', threshold: [0, .15, .4] });
                sections.forEach((section) => sectionObserver.observe(section));
            }
        }

        const calculationForm = document.querySelector('[data-nxtc-calculation-form]');
        const revisionButton = document.querySelector('[data-nxtc-enable-revision]');
        if (calculationForm && revisionButton) {
            revisionButton.addEventListener('click', () => {
                calculationForm.querySelectorAll('[data-nxtc-lockable]').forEach((field) => { field.disabled = false; });
                calculationForm.dataset.nxtcLocked = 'false';
                document.querySelector('[data-nxtc-lock-banner]')?.remove();
                revisionButton.remove();
                calculationForm.querySelector('details')?.setAttribute('open', '');
                calculationForm.querySelector('input,select,textarea')?.focus({ preventScroll: true });
            });
        }

        if (calculationForm) {
            let dirty = false;
            calculationForm.addEventListener('input', () => { dirty = true; });
            calculationForm.addEventListener('change', () => { dirty = true; });
            calculationForm.addEventListener('submit', () => { dirty = false; });
            window.addEventListener('beforeunload', (event) => {
                if (!dirty) return;
                event.preventDefault();
                event.returnValue = '';
            });
        }

        window.addEventListener('pageshow', () => {
            document.querySelectorAll('[data-nxtc-submitting="true"]').forEach((form) => {
                form.dataset.nxtcSubmitting = 'false';
                form.querySelectorAll('[aria-busy="true"]').forEach((button) => {
                    button.removeAttribute('aria-busy');
                    button.disabled = false;
                });
            });
        });
    });
})();
