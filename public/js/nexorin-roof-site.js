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
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const root = document.documentElement;
        const actionEngine = window.NexorinEngineeringAction;
        const nativeSubmit = HTMLFormElement.prototype.submit;

        const syncStickyOffset = () => {
            const topbar = document.querySelector('.topbar');
            const height = topbar ? Math.ceil(topbar.getBoundingClientRect().height) : 72;
            root.style.setProperty('--topbar-height', `${height}px`);
        };
        syncStickyOffset();
        window.addEventListener('resize', syncStickyOffset, { passive: true });
        const topbar = document.querySelector('.topbar');
        if (topbar && 'ResizeObserver' in window) new ResizeObserver(syncStickyOffset).observe(topbar);

        const directory = document.querySelector('[data-rss-directory]');
        if (directory) {
            const search = directory.querySelector('[data-rss-directory-search]');
            const filterForm = directory.querySelector('.rss-directory-filter');
            document.addEventListener('keydown', (event) => {
                if (event.key === '/' && search && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                    event.preventDefault();
                    search.focus();
                    search.select();
                }
            });

            filterForm?.addEventListener('submit', (event) => {
                if (!actionEngine || filterForm.dataset.rssSubmitting === 'true') return;
                event.preventDefault();
                filterForm.dataset.rssSubmitting = 'true';
                const { duration } = actionEngine.show({
                    kind: 'filter',
                    icon: 'filter',
                    title: 'Refreshing Roof Survey Registry',
                    copy: 'The visible project register is being rebuilt from the selected roof-readiness filters.',
                    steps: [
                        'Reading the selected registry filters',
                        'Querying accessible roof and site surveys',
                        'Resolving readiness and verification summaries',
                        'Rendering the updated Roof Survey registry',
                    ],
                    duration: 760,
                    signal: 'NEXORIN roof engine',
                    accent: '#3978d8',
                    secondary: '#f3a04f',
                    metaRight: 'Survey context preserved',
                });
                window.setTimeout(() => nativeSubmit.call(filterForm), duration);
            });

            directory.querySelectorAll('.rss-table-action').forEach((link) => {
                link.addEventListener('click', (event) => {
                    if (!actionEngine) return;
                    actionEngine.navigate(event, link, {
                        kind: 'open',
                        icon: 'roof',
                        title: 'Opening Roof & Site Survey',
                        copy: 'Survey foundation, surface geometry, evidence, warnings, and verification context are being prepared.',
                        steps: [
                            'Retaining project access and context',
                            'Loading survey and roof-surface evidence',
                            'Resolving exclusions, shading, and warning gates',
                            'Preparing the Roof & Site Survey workspace',
                        ],
                        duration: 820,
                        signal: 'NEXORIN roof engine',
                        accent: '#3978d8',
                        secondary: '#f3a04f',
                        metaRight: 'Survey context preserved',
                    });
                });
            });
        }

        const createActionOverlay = ({ kind = 'save', title, copy } = {}) => {
            const verifying = kind === 'verify';
            const uploading = /upload/i.test(String(title || ''));
            const actionTitle = title || (verifying ? 'Verifying Roof Survey' : (uploading ? 'Uploading Survey Evidence' : 'Saving Roof Survey'));
            const actionCopy = copy || (verifying
                ? 'Applying the controlled verification gate to the saved engineering dataset.'
                : uploading
                    ? 'Securing the selected evidence file, category, caption, and audit metadata.'
                    : 'Synchronizing the latest roof and site intelligence.');

            if (!actionEngine) return { duration: reducedMotion ? 120 : (verifying ? 1040 : 900) };

            return actionEngine.show({
                kind: uploading ? 'upload' : kind,
                icon: uploading ? 'upload' : (verifying ? 'verify' : 'save'),
                title: actionTitle,
                copy: actionCopy,
                steps: verifying
                    ? [
                        'Locking the saved survey snapshot',
                        'Validating site foundation and surface completeness',
                        'Checking structural review and warning gates',
                        'Applying the engineering verification gate',
                        'Recording the verification milestone',
                    ]
                    : uploading
                        ? [
                            'Validating the selected evidence file',
                            'Securing category and caption metadata',
                            'Linking evidence to the active survey',
                            'Refreshing evidence and audit context',
                        ]
                        : [
                            'Capturing edited survey inputs',
                            'Validating roof surfaces and exclusions',
                            'Refreshing readiness and engineering warnings',
                            'Synchronizing evidence and site context',
                            'Writing the controlled survey milestone',
                        ],
                duration: verifying ? 1060 : (uploading ? 900 : 960),
                signal: 'NEXORIN roof engine',
                accent: verifying ? '#7659ca' : (uploading ? '#3978d8' : '#13aaa2'),
                secondary: '#f3a04f',
                metaRight: 'Survey trace preserved',
            });
        };


        document.querySelectorAll('[data-rss-action-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.rssSubmitting === 'true') return;

                event.preventDefault();
                form.dataset.rssSubmitting = 'true';

                const kind = form.dataset.rssAction || 'save';
                const title = form.dataset.rssActionTitle || (kind === 'verify' ? 'Verifying Roof Survey' : 'Saving Roof Survey');
                const copy = form.dataset.rssActionCopy || (kind === 'verify'
                    ? 'Applying the controlled verification gate to the saved engineering dataset.'
                    : 'Synchronizing survey details, readiness, warnings, and audit history.');
                const { duration } = createActionOverlay({ kind, title, copy });

                const submitter = event.submitter;
                if (submitter) {
                    submitter.disabled = true;
                    const label = submitter.querySelector('[data-rss-submit-label]');
                    if (label) label.textContent = kind === 'verify' ? 'Verifying…' : 'Saving…';
                    else submitter.textContent = kind === 'verify' ? 'Verifying…' : 'Saving…';
                }

                window.setTimeout(() => {
                    nativeSubmit.call(form);
                }, duration);
            });
        });

        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm(form.dataset.confirm || 'Continue?')) event.preventDefault();
            });
        });

        document.querySelectorAll('[data-rss-autosize]').forEach((textarea) => {
            const resize = () => {
                textarea.style.height = 'auto';
                textarea.style.height = `${Math.max(88, textarea.scrollHeight)}px`;
            };
            textarea.addEventListener('input', resize);
            resize();
        });

        const workspace = document.querySelector('[data-rss-workspace]');
        const form = document.querySelector('[data-rss-form]');

        if (workspace && form) {
            const submitButtons = Array.from(document.querySelectorAll('[data-rss-submit]'));
            const submitLabels = Array.from(document.querySelectorAll('[data-rss-submit-label]'));
            const verifyButton = document.querySelector('[data-rss-verify]');
            const verifyForm = document.getElementById('rss-verify-form');
            const trackedFields = Array.from(form.querySelectorAll('[data-rss-field][name]')).filter((field) => !field.disabled);
            const initial = new Map(trackedFields.map((field) => [field.name, String(field.value ?? '').trim()]));
            const surfaceCount = Number.parseInt(workspace.dataset.surfaceCount || '0', 10) || 0;
            const completeSurfaceCount = Number.parseInt(workspace.dataset.completeSurfaceCount || '0', 10) || 0;
            let isSubmitting = false;

            const changedNames = () => trackedFields
                .filter((field) => String(field.value ?? '').trim() !== (initial.get(field.name) ?? ''))
                .map((field) => field.name);

            const calculateLiveState = () => {
                const surveyDate = String(form.querySelector('#survey_date')?.value || '').trim();
                const accessMethod = String(form.querySelector('#access_method')?.value || '').trim();
                const structuralReview = String(form.querySelector('#structural_review_status')?.value || '').trim();
                const points = (surveyDate ? 1 : 0)
                    + (accessMethod ? 1 : 0)
                    + (structuralReview && structuralReview !== 'not_assessed' ? 1 : 0)
                    + (surfaceCount > 0 ? 1 : 0)
                    + (surfaceCount > 0 && surfaceCount === completeSurfaceCount ? 1 : 0);
                const readiness = Math.round((points / 5) * 100);
                return { readiness, ready: points === 5 };
            };

            const updateState = () => {
                const changed = changedNames();
                const dirty = changed.length > 0;
                const live = calculateLiveState();
                const structuralSelect = form.querySelector('#structural_review_status');
                const structuralLabel = structuralSelect?.options[structuralSelect.selectedIndex]?.textContent?.trim() || 'Not assessed';

                workspace.classList.toggle('is-dirty', dirty);
                document.querySelectorAll('[data-rss-change-label]').forEach((node) => {
                    node.textContent = isSubmitting ? 'Saving changes' : dirty ? 'Unsaved changes' : 'Saved state';
                });
                document.querySelectorAll('[data-rss-change-count]').forEach((node) => {
                    node.textContent = isSubmitting ? 'Syncing' : `${changed.length} field${changed.length === 1 ? '' : 's'}`;
                });
                document.querySelectorAll('[data-rss-mobile-state]').forEach((node) => {
                    node.textContent = dirty ? 'Unsaved changes' : 'Saved state';
                });
                document.querySelectorAll('[data-rss-mobile-count]').forEach((node) => {
                    node.textContent = `${changed.length} change${changed.length === 1 ? '' : 's'}`;
                });
                submitButtons.forEach((button) => { button.disabled = !dirty || isSubmitting; });
                submitLabels.forEach((label) => { label.textContent = isSubmitting ? 'Saving…' : dirty ? 'Save changes' : 'Saved'; });

                document.querySelectorAll('[data-rss-readiness-value], [data-rss-rail-readiness], [data-rss-kpi-readiness]').forEach((node) => {
                    node.textContent = `${live.readiness}%`;
                });
                const heroRing = workspace.querySelector('.rss-workspace-ring');
                const railRing = workspace.querySelector('.rss-rail-ring');
                heroRing?.style.setProperty('--rss-readiness', live.readiness);
                railRing?.style.setProperty('--rss-readiness', live.readiness);

                const foundationState = workspace.querySelector('[data-rss-foundation-state]');
                const foundationReady = Boolean(form.querySelector('#survey_date')?.value)
                    && Boolean(form.querySelector('#access_method')?.value)
                    && Boolean(structuralReview && structuralReview !== 'not_assessed');
                if (foundationState) {
                    foundationState.textContent = foundationReady ? 'Ready' : 'Needs input';
                    foundationState.classList.toggle('is-warning', !foundationReady);
                }
                const railStructural = workspace.querySelector('[data-rss-rail-structural]');
                if (railStructural) railStructural.textContent = structuralLabel;

                const navState = workspace.querySelector('[data-rss-nav-state]');
                if (navState) navState.textContent = dirty ? 'Save changes' : live.ready ? 'Ready to verify' : 'In progress';

                if (verifyButton) {
                    verifyButton.disabled = !live.ready || dirty || isSubmitting;
                    verifyButton.title = dirty ? 'Save pending changes before verification.' : !live.ready ? 'Complete the survey before verification.' : 'Verify the saved survey dataset.';
                }
            };

            form.addEventListener('input', updateState);
            form.addEventListener('change', updateState);
            form.addEventListener('submit', () => {
                isSubmitting = true;
                updateState();
            });
            verifyForm?.addEventListener('submit', () => {
                if (verifyButton) {
                    verifyButton.disabled = true;
                    verifyButton.textContent = 'Verifying…';
                }
            });

            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    if (!isSubmitting && changedNames().length > 0) form.requestSubmit();
                }
            });

            window.addEventListener('beforeunload', (event) => {
                if (changedNames().length > 0 && !isSubmitting) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            const navLinks = Array.from(workspace.querySelectorAll('[data-rss-section-link]'));
            const sections = Array.from(workspace.querySelectorAll('[data-rss-section]'));
            if ('IntersectionObserver' in window && navLinks.length && sections.length) {
                const observer = new IntersectionObserver((entries) => {
                    const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                    if (!visible) return;
                    navLinks.forEach((link) => link.classList.toggle('is-active', link.dataset.rssSectionLink === visible.target.id));
                }, { rootMargin: '-20% 0px -62% 0px', threshold: [0.08, 0.25, 0.5] });
                sections.forEach((section) => observer.observe(section));
            }

            updateState();
        }

        const photoInput = document.querySelector('[data-rss-photo-input]');
        const photoPreview = document.querySelector('[data-rss-photo-preview]');
        const fileName = document.querySelector('[data-rss-file-name]');
        const dropzone = document.querySelector('[data-rss-dropzone]');

        const updatePhotoPreview = (file) => {
            if (!file) return;
            if (fileName) fileName.textContent = file.name;
            if (photoPreview && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.addEventListener('load', () => {
                    photoPreview.src = String(reader.result || '');
                    photoPreview.hidden = false;
                });
                reader.readAsDataURL(file);
            }
        };
        photoInput?.addEventListener('change', () => updatePhotoPreview(photoInput.files?.[0]));
        if (dropzone && photoInput) {
            ['dragenter', 'dragover'].forEach((name) => dropzone.addEventListener(name, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            }));
            ['dragleave', 'drop'].forEach((name) => dropzone.addEventListener(name, (event) => {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
            }));
            dropzone.addEventListener('drop', (event) => {
                const file = event.dataTransfer?.files?.[0];
                if (!file) return;
                const transfer = new DataTransfer();
                transfer.items.add(file);
                photoInput.files = transfer.files;
                updatePhotoPreview(file);
            });
        }

        const surfaceEditor = document.querySelector('[data-rss-surface-editor]');
        const surfaceForm = document.querySelector('[data-rss-surface-form]');
        if (surfaceEditor && surfaceForm) {
            const readNumber = (selector) => {
                const value = Number.parseFloat(surfaceForm.querySelector(selector)?.value || '0');
                return Number.isFinite(value) ? value : 0;
            };
            const format = (value, digits = 2) => value.toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });
            const setText = (selector, value) => {
                const node = surfaceEditor.querySelector(selector);
                if (node) node.textContent = value;
            };

            const updateSurfacePreview = () => {
                const gross = Math.max(0, readNumber('[data-rss-metric="gross"]'));
                const exclusions = Math.max(0, readNumber('[data-rss-metric="setback"]'))
                    + Math.max(0, readNumber('[data-rss-metric="obstruction"]'))
                    + Math.max(0, readNumber('[data-rss-metric="walkway"]'));
                const usable = Math.max(0, gross - exclusions);
                const utilization = gross > 0 ? Math.max(0, Math.min(100, (usable / gross) * 100)) : 0;
                const shading = Math.max(0, Math.min(100, readNumber('[data-rss-metric="shading"]')));
                const tilt = Math.max(0, readNumber('[data-rss-metric="tilt"]'));
                const azimuth = Math.max(0, readNumber('[data-rss-metric="azimuth"]'));
                const name = String(surfaceForm.querySelector('#name')?.value || '').trim() || 'New roof surface';

                setText('[data-rss-live-name]', name);
                setText('[data-rss-live-gross]', `${format(gross)} m²`);
                setText('[data-rss-live-usable]', `${format(usable)} m²`);
                setText('[data-rss-live-utilization]', `${format(utilization, 1)}%`);
                setText('[data-rss-live-shading]', `${format(shading, 1)}%`);
                setText('[data-rss-live-tilt]', `${format(tilt, 1)}°`);
                setText('[data-rss-live-azimuth]', `${format(azimuth, 1)}°`);
                const track = surfaceEditor.querySelector('[data-rss-live-track]');
                if (track) track.style.width = `${utilization}%`;
                const note = surfaceEditor.querySelector('[data-rss-live-note]');
                if (note) {
                    note.textContent = gross <= 0
                        ? 'Enter gross area and exclusions to calculate the usable installation area.'
                        : exclusions > gross
                            ? 'Excluded areas exceed the gross area. Reduce setbacks, obstructions, or walkways.'
                            : `${format(exclusions)} m² excluded from the roof plane, leaving ${format(usable)} m² usable.`;
                }
            };

            surfaceForm.addEventListener('input', updateSurfacePreview);
            surfaceForm.addEventListener('change', updateSurfacePreview);
            updateSurfacePreview();
        }
    });
})();
