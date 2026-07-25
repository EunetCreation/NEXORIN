(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    ready(() => {
        const directory = document.querySelector('[data-nxrec-directory]');
        const workspace = document.querySelector('[data-nxrec-workspace]');

        if (!directory && !workspace) return;

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const finePointer = window.matchMedia('(pointer: fine)').matches;
        const root = document.documentElement;
        const nativeSubmit = HTMLFormElement.prototype.submit;
        const actionEngine = window.NexorinEngineeringAction;

        workspace?.closest('.content-container')?.classList.add('has-nxrec-workspace');

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const syncTopbarOffset = () => {
            const topbar = document.querySelector('.topbar');
            const height = topbar ? Math.ceil(topbar.getBoundingClientRect().height) : 72;
            root.style.setProperty('--topbar-height', `${height}px`);
        };

        syncTopbarOffset();
        window.addEventListener('resize', syncTopbarOffset, { passive: true });
        const topbar = document.querySelector('.topbar');
        if (topbar && 'ResizeObserver' in window) new ResizeObserver(syncTopbarOffset).observe(topbar);

        const actionConfigs = {
            run: {
                kind: 'calculate', icon: 'recommendation',
                title: 'Calculating System Recommendation',
                copy: 'The latest load, solar, roof, and operational targets are being evaluated through the controlled decision model.',
                steps: [
                    'Locking the current source snapshot',
                    'Validating source completeness and hard constraints',
                    'Applying the weighted alternative criteria',
                    'Comparing on-grid and hybrid suitability',
                    'Writing the decision trace and audit milestone',
                ],
                duration: 1240, signal: 'NEXORIN decision engine', accent: '#13aaa2', secondary: '#f3a04f',
                metaLeft: 'Latest project evidence', metaRight: 'Decision trace protected',
                eyebrow: 'Controlled recommendation sequence',
            },
            rerun: {
                kind: 'calculate', icon: 'recommendation',
                title: 'Recalculating System Recommendation',
                copy: 'The active result is being rebuilt from the latest source snapshot while the previous decision remains protected in audit history.',
                steps: [
                    'Capturing the latest source and target snapshot',
                    'Reapplying hard constraints and model weights',
                    'Rebuilding both alternative score traces',
                    'Selecting the leading planning basis',
                    'Preserving the previous decision milestone',
                ],
                duration: 1300, signal: 'NEXORIN decision engine', accent: '#13aaa2', secondary: '#f3a04f',
                metaLeft: 'Latest source snapshot', metaRight: 'Previous decision preserved',
                eyebrow: 'Controlled recommendation sequence',
            },
            override: {
                kind: 'verify', icon: 'edit',
                title: 'Applying Engineer Override',
                copy: 'The final planning basis is being updated under engineer authority without modifying the original model score trace.',
                steps: [
                    'Validating the selected final system',
                    'Checking the technical justification',
                    'Securing the controlled override authority',
                    'Retaining the original engine result',
                    'Recording the authority milestone',
                ],
                duration: 1120, signal: 'NEXORIN decision governance', accent: '#7659ca', secondary: '#f3a04f',
                metaLeft: 'Engineer authority', metaRight: 'Original score trace retained',
                eyebrow: 'Governed decision sequence',
            },
            restore: {
                kind: 'verify', icon: 'verify',
                title: 'Restoring Engine-Selected Result',
                copy: 'The active override is being released and the preserved model decision is being restored as the final planning basis.',
                steps: [
                    'Reading the preserved engine decision',
                    'Releasing the active engineer override',
                    'Restoring the model-selected alternative',
                    'Validating the final planning basis',
                    'Recording the restoration milestone',
                ],
                duration: 1080, signal: 'NEXORIN decision governance', accent: '#7659ca', secondary: '#f3a04f',
                metaLeft: 'Preserved engine result', metaRight: 'Restoration recorded',
                eyebrow: 'Governed decision sequence',
            },
            filter: {
                kind: 'filter', icon: 'filter',
                title: 'Refreshing Recommendation Registry',
                copy: 'The selected filters are being applied to rebuild the visible project decisions, statuses, and score summaries.',
                steps: [
                    'Reading the selected registry filters',
                    'Querying accessible project decisions',
                    'Resolving score and decision status summaries',
                    'Rendering the updated registry',
                ],
                duration: 780, signal: 'NEXORIN decision registry', accent: '#3978d8', secondary: '#f3a04f',
                metaLeft: 'Current filters applied', metaRight: 'Access scope preserved',
                eyebrow: 'Decision registry sequence',
            },
            open: {
                kind: 'open', icon: 'recommendation',
                title: 'Opening System Recommendation',
                copy: 'Source readiness, decision inputs, alternative scores, and immutable audit context are being prepared.',
                steps: [
                    'Resolving project access and context',
                    'Loading source readiness and decision inputs',
                    'Resolving alternative score and audit intelligence',
                    'Opening the System Recommendation workspace',
                ],
                duration: 880, signal: 'NEXORIN decision engine', accent: '#13aaa2', secondary: '#f3a04f',
                metaLeft: 'Project decision context', metaRight: 'Audit context preserved',
                eyebrow: 'Project workflow · Step 05',
            },
            source: {
                kind: 'open', icon: 'open',
                title: 'Opening Source Engineering Module',
                copy: 'The engineering evidence supporting the active recommendation is being loaded in its source workspace.',
                steps: [
                    'Retaining the current decision context',
                    'Resolving the selected source module',
                    'Loading the latest engineering dataset',
                    'Opening the source workspace',
                ],
                duration: 820, signal: 'NEXORIN source trace', accent: '#3978d8', secondary: '#f3a04f',
                metaLeft: 'Recommendation context retained', metaRight: 'Source trace preserved',
                eyebrow: 'Controlled source handoff',
            },
        };

        const createActionOverlay = (kind = 'run', source = null) => {
            const base = actionConfigs[kind] || actionConfigs.run;
            if (!actionEngine) return { duration: reducedMotion ? 90 : base.duration };

            const sourceRow = source?.closest?.('[data-row-href]');
            const projectNumber = workspace?.querySelector('.nxrec-hero-meta span')?.textContent?.trim()
                || sourceRow?.dataset?.projectNumber
                || sourceRow?.querySelector?.('.nxrec-project-id')?.textContent?.trim()
                || '';
            const sourceLabel = source?.querySelector?.('.nxrec-source-copy small')?.textContent?.trim()
                || source?.textContent?.trim()
                || '';
            const config = { ...base };

            if (projectNumber) config.metaLeft = `${projectNumber} · ${base.metaLeft}`;

            if (kind === 'source' && source?.href) {
                const path = new URL(source.href, window.location.href).pathname;
                const profiles = [
                    {
                        match: /\/load-analysis$/i,
                        icon: 'load',
                        title: 'Opening Load Analysis',
                        copy: 'Demand profile, equipment schedules, bill evidence, and validation context are being prepared.',
                        signal: 'NEXORIN load intelligence',
                        accent: '#3978d8',
                        eyebrow: 'Controlled source handoff · Load',
                        steps: ['Retaining the active decision context', 'Loading demand and equipment intelligence', 'Resolving bill history and validation signals', 'Opening the Load Analysis workspace'],
                    },
                    {
                        match: /\/location-solar-data$/i,
                        icon: 'solar',
                        title: 'Opening Location & Solar',
                        copy: 'Coordinates, source traceability, monthly irradiance, and verification context are being prepared.',
                        signal: 'NEXORIN solar resource',
                        accent: '#d89b35',
                        eyebrow: 'Controlled source handoff · Solar',
                        steps: ['Retaining the active decision context', 'Loading coordinates and source identity', 'Resolving the twelve-month resource profile', 'Opening the Location & Solar workspace'],
                    },
                    {
                        match: /\/roof-site-survey$/i,
                        icon: 'roof',
                        title: 'Opening Roof & Site Survey',
                        copy: 'Survey foundation, roof geometry, evidence, warnings, and verification context are being prepared.',
                        signal: 'NEXORIN site intelligence',
                        accent: '#13aaa2',
                        eyebrow: 'Controlled source handoff · Site',
                        steps: ['Retaining the active decision context', 'Loading survey and roof-surface evidence', 'Resolving exclusions, shading, and warning gates', 'Opening the Roof & Site Survey workspace'],
                    },
                ];
                const profile = profiles.find((item) => item.match.test(path));
                if (profile) Object.assign(config, profile);
            }

            if (kind === 'source' && sourceLabel) config.metaRight = `${sourceLabel.replace(/\s+/g, ' ')} · Source trace preserved`;

            return actionEngine.show(config);
        };

        const confirmationIcons = {
            rerun: actionEngine?.icons?.calculate || '',
            restore: actionEngine?.icons?.verify || '',
        };

        const confirmConfigs = {
            rerun: {
                title: 'Recalculate the active recommendation?',
                copy: 'The decision model will rebuild the result from the latest Load, Solar, Roof, and operational-target snapshot.',
                points: [
                    'Current engine scores and the source snapshot will be replaced.',
                    'Any active engineer override will be released.',
                    'The existing decision remains preserved in audit history.',
                ],
                proceed: 'Re-run recommendation',
                targetLabel: 'Latest source and target snapshot',
            },
            restore: {
                title: 'Restore the engine-selected result?',
                copy: 'The engineer override will be released and the preserved model decision will become final again.',
                points: [
                    'Underlying engine scores remain unchanged.',
                    'The override justification stays visible in history.',
                    'A restoration event will be recorded for audit.',
                ],
                proceed: 'Restore engine result',
                targetLabel: 'Engine-selected result',
            },
        };

        let activeConfirm = null;

        const closeConfirm = () => {
            if (!activeConfirm) return;
            activeConfirm.classList.remove('is-visible');
            const element = activeConfirm;
            activeConfirm = null;
            window.setTimeout(() => element.remove(), 220);
        };

        const showConfirm = (mode, form, submitter) => {
            const config = confirmConfigs[mode];
            if (!config) return;

            document.querySelector('.nxrec-confirm-overlay')?.remove();
            const currentResult = form.dataset.nxrecCurrentResult || 'Current decision';
            const targetEyebrow = mode === 'restore' ? 'Restored planning basis' : 'New run basis';
            const targetResult = mode === 'restore'
                ? (form.dataset.nxrecEngineResult || 'Engine-selected result')
                : 'Latest Load, Solar, Roof, and target snapshot';

            const overlay = document.createElement('div');
            overlay.className = `nxrec-confirm-overlay is-${mode}`;
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'nxrec-confirm-title');
            overlay.innerHTML = `
                <div class="nxrec-confirm-card">
                    <div class="nxrec-confirm-head">
                        <span class="nxrec-confirm-icon">${mode === 'restore' ? confirmationIcons.restore : confirmationIcons.rerun}</span>
                        <div>
                            <h3 id="nxrec-confirm-title">${escapeHtml(config.title)}</h3>
                            <p>${escapeHtml(config.copy)}</p>
                        </div>
                    </div>
                    <div class="nxrec-confirm-transition" aria-label="Decision transition">
                        <span><small>Current planning basis</small><strong>${escapeHtml(currentResult)}</strong></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                        <span><small>${escapeHtml(targetEyebrow)}</small><strong>${escapeHtml(targetResult)}</strong></span>
                    </div>
                    <ul class="nxrec-confirm-points">${config.points.map((point) => `<li>${escapeHtml(point)}</li>`).join('')}</ul>
                    <div class="nxrec-confirm-actions">
                        <button type="button" class="nxrec-confirm-cancel">Cancel</button>
                        <button type="button" class="nxrec-confirm-proceed">${escapeHtml(config.proceed)}</button>
                    </div>
                </div>`;

            document.body.appendChild(overlay);
            activeConfirm = overlay;
            requestAnimationFrame(() => overlay.classList.add('is-visible'));

            const cancel = overlay.querySelector('.nxrec-confirm-cancel');
            const proceed = overlay.querySelector('.nxrec-confirm-proceed');
            cancel?.focus();

            cancel?.addEventListener('click', closeConfirm, { once: true });
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) closeConfirm();
            });
            proceed?.addEventListener('click', () => {
                form.dataset.nxrecConfirmed = 'true';
                closeConfirm();
                window.setTimeout(() => {
                    if (typeof form.requestSubmit === 'function') {
                        submitter ? form.requestSubmit(submitter) : form.requestSubmit();
                    } else {
                        nativeSubmit.call(form);
                    }
                }, reducedMotion ? 0 : 70);
            }, { once: true });
        };

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && activeConfirm) closeConfirm();
        });

        document.querySelectorAll('[data-nxrec-action-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.nxrecSubmitting === 'true') return;

                const submitter = event.submitter;
                const confirmMode = form.dataset.nxrecConfirm;

                if (confirmMode && form.dataset.nxrecConfirmed !== 'true') {
                    event.preventDefault();
                    showConfirm(confirmMode, form, submitter);
                    return;
                }

                event.preventDefault();
                form.dataset.nxrecSubmitting = 'true';

                const kind = form.dataset.nxrecAction || 'run';
                const { duration } = createActionOverlay(kind);

                if (submitter) {
                    submitter.disabled = true;
                    submitter.setAttribute('aria-busy', 'true');
                    const label = submitter.querySelector('[data-nxrec-submit-label]');
                    if (label) {
                        label.textContent = kind === 'override'
                            ? 'Applying override…'
                            : (kind === 'restore'
                                ? 'Restoring…'
                                : (kind === 'filter' ? 'Refreshing…' : 'Calculating…'));
                    }
                }

                window.setTimeout(() => nativeSubmit.call(form), duration);
            });
        });

        document.querySelectorAll('[data-nxrec-auto-submit]').forEach((control) => {
            control.addEventListener('change', () => {
                const form = control.closest('form');
                if (!form) return;
                typeof form.requestSubmit === 'function' ? form.requestSubmit() : nativeSubmit.call(form);
            });
        });

        const navigateWithOverlay = (href, kind = 'open', source = null) => {
            if (!href) return;
            const { duration } = createActionOverlay(kind, source);
            window.setTimeout(() => { window.location.href = href; }, duration);
        };

        if (directory) {
            const search = directory.querySelector('[data-nxrec-directory-search]');
            document.addEventListener('keydown', (event) => {
                const activeTag = document.activeElement?.tagName;
                if (event.key === '/' && search && !['INPUT', 'TEXTAREA', 'SELECT'].includes(activeTag)) {
                    event.preventDefault();
                    search.focus();
                    search.select();
                }
            });

            document.addEventListener('click', (event) => {
                const row = event.target.closest('[data-nxrec-directory] [data-row-href]');
                if (!row || event.target.closest('a, button, input, select, textarea')) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                navigateWithOverlay(row.dataset.rowHref, 'open', row);
            }, true);

            document.addEventListener('keydown', (event) => {
                const row = event.target.closest?.('[data-nxrec-directory] [data-row-href]');
                if (!row || !['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                navigateWithOverlay(row.dataset.rowHref, 'open', row);
            }, true);

            directory.querySelectorAll('[data-nxrec-open]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
                    event.preventDefault();
                    event.stopPropagation();
                    navigateWithOverlay(link.href, 'open', link);
                });
            });
        }

        if (workspace) {
            workspace.querySelectorAll('[data-nxrec-source]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
                    event.preventDefault();
                    navigateWithOverlay(link.href, 'source', link);
                });
            });

            const nav = workspace.querySelector('[data-nxrec-section-nav]');
            const navLinks = nav ? Array.from(nav.querySelectorAll('a[href^="#"]')) : [];
            const sections = navLinks
                .map((link) => document.querySelector(link.getAttribute('href')))
                .filter(Boolean);

            const setActiveSection = (id) => {
                navLinks.forEach((link) => link.classList.toggle('is-active', link.getAttribute('href') === `#${id}`));
            };

            navLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    const target = document.querySelector(link.getAttribute('href'));
                    if (!target) return;
                    event.preventDefault();
                    setActiveSection(target.id);

                    const topbarHeight = Number.parseFloat(getComputedStyle(root).getPropertyValue('--topbar-height')) || 72;
                    const navHeight = nav ? Math.ceil(nav.getBoundingClientRect().height) : 48;
                    const destination = Math.max(0, window.scrollY + target.getBoundingClientRect().top - topbarHeight - navHeight - 22);
                    window.scrollTo({ top: destination, behavior: reducedMotion ? 'auto' : 'smooth' });
                    history.replaceState(null, '', `#${target.id}`);
                });
            });

            if (nav) {
                let navThreshold = nav.getBoundingClientRect().top + window.scrollY;
                const syncNavState = () => {
                    const topbarHeight = Number.parseFloat(getComputedStyle(root).getPropertyValue('--topbar-height')) || 72;
                    nav.classList.toggle('is-stuck', window.scrollY > navThreshold - topbarHeight - 7);
                };
                window.addEventListener('scroll', syncNavState, { passive: true });
                window.addEventListener('resize', () => {
                    if (!nav.classList.contains('is-stuck')) navThreshold = nav.getBoundingClientRect().top + window.scrollY;
                    syncNavState();
                }, { passive: true });
                syncNavState();
            }

            if ('IntersectionObserver' in window && sections.length) {
                const sectionObserver = new IntersectionObserver((entries) => {
                    const visible = entries
                        .filter((entry) => entry.isIntersecting)
                        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                    if (visible?.target?.id) setActiveSection(visible.target.id);
                }, { rootMargin: '-22% 0px -68% 0px', threshold: [0.01, 0.12, 0.35] });
                sections.forEach((section) => sectionObserver.observe(section));
            }

            const backupInput = workspace.querySelector('[data-nxrec-backup]');
            const selfInput = workspace.querySelector('[data-nxrec-self]');
            const objectiveInput = workspace.querySelector('[data-nxrec-objective]');
            const backupPreview = workspace.querySelector('[data-nxrec-preview-backup]');
            const selfPreview = workspace.querySelector('[data-nxrec-preview-self]');
            const objectivePreview = workspace.querySelector('[data-nxrec-preview-objective]');

            const syncPreview = () => {
                if (backupPreview && backupInput) {
                    const value = Number.parseFloat(backupInput.value || '0');
                    backupPreview.textContent = `${Number.isFinite(value) ? value.toFixed(1) : '0.0'} h`;
                }
                if (selfPreview && selfInput) {
                    const value = Number.parseFloat(selfInput.value || '0');
                    selfPreview.textContent = `${Number.isFinite(value) ? Math.round(value) : 0}%`;
                }
                if (objectivePreview && objectiveInput) {
                    objectivePreview.textContent = objectiveInput.options[objectiveInput.selectedIndex]?.text || 'Balanced performance';
                }
            };

            [backupInput, selfInput, objectiveInput].filter(Boolean).forEach((input) => {
                input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'input', syncPreview);
            });
            syncPreview();

            workspace.querySelectorAll('[data-nxrec-autosize]').forEach((textarea) => {
                const resize = () => {
                    const minimumHeight = 82;
                    const maximumHeight = 132;
                    textarea.style.height = 'auto';
                    const nextHeight = Math.min(maximumHeight, Math.max(minimumHeight, textarea.scrollHeight));
                    textarea.style.height = `${nextHeight}px`;
                    textarea.style.overflowY = textarea.scrollHeight > maximumHeight ? 'auto' : 'hidden';
                };
                textarea.addEventListener('input', resize);
                resize();
            });

            workspace.querySelectorAll('[data-nxrec-countable]').forEach((field) => {
                const target = field.closest('.nxrec-control')?.querySelector('[data-nxrec-count]');
                const sync = () => { if (target) target.textContent = String(field.value.length); };
                field.addEventListener('input', sync);
                sync();
            });

            const runForm = workspace.querySelector('form[data-nxrec-action="run"], form[data-nxrec-action="rerun"]');
            document.addEventListener('keydown', (event) => {
                if (!(event.ctrlKey || event.metaKey) || event.key !== 'Enter' || !runForm) return;
                if (document.activeElement?.tagName === 'TEXTAREA') return;
                event.preventDefault();
                typeof runForm.requestSubmit === 'function' ? runForm.requestSubmit() : nativeSubmit.call(runForm);
            });
        }

        document.querySelectorAll('[data-nxrec-tooltip]').forEach((trigger) => {
            let tooltip = null;

            const position = () => {
                if (!tooltip) return;
                const rect = trigger.getBoundingClientRect();
                const tooltipRect = tooltip.getBoundingClientRect();
                const left = Math.min(window.innerWidth - tooltipRect.width - 12, Math.max(12, rect.left + rect.width / 2 - tooltipRect.width / 2));
                const top = rect.bottom + tooltipRect.height + 12 > window.innerHeight
                    ? rect.top - tooltipRect.height - 9
                    : rect.bottom + 9;
                tooltip.style.left = `${left}px`;
                tooltip.style.top = `${top}px`;
            };

            const show = () => {
                if (tooltip) return;
                tooltip = document.createElement('div');
                tooltip.className = 'nxrec-tooltip';
                tooltip.textContent = trigger.dataset.nxrecTooltip || '';
                document.body.appendChild(tooltip);
                position();
                requestAnimationFrame(() => tooltip?.classList.add('is-visible'));
            };

            const hide = () => {
                if (!tooltip) return;
                const current = tooltip;
                tooltip = null;
                current.classList.remove('is-visible');
                window.setTimeout(() => current.remove(), 160);
            };

            trigger.addEventListener('mouseenter', show);
            trigger.addEventListener('mouseleave', hide);
            trigger.addEventListener('focus', show);
            trigger.addEventListener('blur', hide);
            window.addEventListener('scroll', position, { passive: true });
            window.addEventListener('resize', position, { passive: true });
        });

        if (!reducedMotion && finePointer) {
            document.querySelectorAll('.nxrec-workspace [data-premium-card], .nxrec-directory [data-premium-card]').forEach((card) => {
                card.addEventListener('pointermove', (event) => {
                    const bounds = card.getBoundingClientRect();
                    card.style.setProperty('--mouse-x', `${event.clientX - bounds.left}px`);
                    card.style.setProperty('--mouse-y', `${event.clientY - bounds.top}px`);
                });
            });
        }
    });
})();
