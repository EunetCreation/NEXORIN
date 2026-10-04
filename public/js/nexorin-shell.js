/**
 * NEXORIN premium application shell interactions.
 *
 * Presentation only. Existing routes, permissions, forms, calculations,
 * and project workflow remain unchanged.
 */
(() => {
    'use strict';

    const root = document.documentElement;
    const body = document.body;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Controlled confirmation dialog shared by every authenticated workspace.
     * Falls back to the browser dialog only when the shared layout is absent.
     */
    const confirmOverlay = document.getElementById('nxConfirmOverlay');
    const confirmCard = document.getElementById('nxConfirmCard');
    const confirmTitle = confirmOverlay?.querySelector('[data-nx-confirm-title]');
    const confirmMessage = confirmOverlay?.querySelector('[data-nx-confirm-message]');
    const confirmKicker = confirmOverlay?.querySelector('[data-nx-confirm-kicker]');
    const confirmControl = confirmOverlay?.querySelector('[data-nx-confirm-control]');
    const confirmResult = confirmOverlay?.querySelector('[data-nx-confirm-result]');
    const confirmCancel = confirmOverlay?.querySelector('[data-nx-confirm-cancel]');
    const confirmProceed = confirmOverlay?.querySelector('[data-nx-confirm-proceed]');
    const confirmWarningIcon = confirmOverlay?.querySelector('[data-nx-confirm-icon="warning"]');
    const confirmDecisionIcon = confirmOverlay?.querySelector('[data-nx-confirm-icon="decision"]');
    let confirmResolver = null;
    let confirmLastFocus = null;
    let confirmHideTimer = null;

    const inferConfirmTone = (message = '') => {
        const value = message.toLowerCase();
        if (/(delete|remove|archive|deactivate|reject|cancel|revoke|lost)/.test(value)) return 'danger';
        if (/(approve|accept|activate|send|apply|commit|verify)/.test(value)) return 'decision';
        return 'warning';
    };

    const inferConfirmTitle = (tone) => {
        if (tone === 'danger') return 'Confirm controlled change';
        if (tone === 'decision') return 'Authorize this decision';
        return 'Confirm this workflow action';
    };

    const finishConfirm = (approved) => {
        if (!confirmOverlay || !confirmResolver) return;
        const resolve = confirmResolver;
        confirmResolver = null;
        confirmOverlay.classList.remove('is-visible');
        confirmOverlay.setAttribute('aria-hidden', 'true');
        body.classList.remove('nx-confirm-open');

        confirmHideTimer = window.setTimeout(() => {
            confirmOverlay.hidden = true;
            confirmCard?.classList.remove('is-danger', 'is-decision');
            confirmLastFocus?.focus?.({ preventScroll: true });
            confirmLastFocus = null;
        }, reduceMotion ? 0 : 190);

        resolve(Boolean(approved));
    };

    const askForConfirmation = (message, options = {}) => {
        if (!confirmOverlay || !confirmCard || !confirmProceed || !confirmCancel) {
            return Promise.resolve(window.confirm(message || 'Continue with this action?'));
        }

        if (confirmResolver) finishConfirm(false);

        if (confirmHideTimer) {
            window.clearTimeout(confirmHideTimer);
            confirmHideTimer = null;
        }

        const tone = options.tone || inferConfirmTone(message);
        confirmLastFocus = document.activeElement;
        confirmCard.classList.toggle('is-danger', tone === 'danger');
        confirmCard.classList.toggle('is-decision', tone === 'decision');
        if (confirmTitle) confirmTitle.textContent = options.title || inferConfirmTitle(tone);
        if (confirmMessage) confirmMessage.textContent = message || 'Continue with this controlled workflow action?';
        if (confirmKicker) confirmKicker.textContent = options.kicker || (tone === 'danger' ? 'State change control' : tone === 'decision' ? 'Engineering authority' : 'Controlled decision');
        if (confirmControl) confirmControl.textContent = options.control || 'Explicit approval required';
        if (confirmResult) confirmResult.textContent = options.result || (tone === 'danger' ? 'The selected record state will change' : 'Workflow continues after confirmation');
        confirmCancel.textContent = options.cancelLabel || 'Keep current state';
        confirmProceed.textContent = options.confirmLabel || (tone === 'danger' ? 'Confirm change' : 'Continue');
        if (confirmWarningIcon) confirmWarningIcon.hidden = tone === 'decision';
        if (confirmDecisionIcon) confirmDecisionIcon.hidden = tone !== 'decision';

        confirmOverlay.hidden = false;
        confirmOverlay.setAttribute('aria-hidden', 'false');
        body.classList.add('nx-confirm-open');
        window.requestAnimationFrame(() => {
            confirmOverlay.classList.add('is-visible');
            confirmCancel.focus({ preventScroll: true });
        });

        return new Promise((resolve) => {
            confirmResolver = resolve;
        });
    };

    window.NexorinConfirm = Object.freeze({ ask: askForConfirmation });

    confirmCancel?.addEventListener('click', () => finishConfirm(false));
    confirmProceed?.addEventListener('click', () => finishConfirm(true));
    confirmOverlay?.addEventListener('click', (event) => {
        if (event.target === confirmOverlay) finishConfirm(false);
    });
    document.addEventListener('keydown', (event) => {
        if (!confirmResolver) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            finishConfirm(false);
            return;
        }
        if (event.key !== 'Tab' || !confirmOverlay) return;
        const focusable = [...confirmOverlay.querySelectorAll('button:not([disabled])')];
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    root.classList.add('motion-ready');

    /**
     * Secure handoff after a successful login submit.
     */
    const workspaceEntryOverlay = document.getElementById('workspaceEntryOverlay');

    if (root.classList.contains('workspace-entry-pending') && workspaceEntryOverlay) {
        workspaceEntryOverlay.setAttribute('aria-hidden', 'false');
        workspaceEntryOverlay.classList.add('is-visible');
        root.classList.add('is-nxeng-processing');

        const steps = Array.from(workspaceEntryOverlay.querySelectorAll('[data-workspace-entry-steps] > div'));
        const sequence = workspaceEntryOverlay.querySelector('[data-workspace-entry-sequence]');
        const state = workspaceEntryOverlay.querySelector('[data-workspace-entry-state]');
        const status = workspaceEntryOverlay.querySelector('[data-workspace-entry-status]');
        const progress = workspaceEntryOverlay.querySelector('[data-workspace-entry-progress]');
        const caption = workspaceEntryOverlay.querySelector('[data-workspace-entry-caption]');
        const messages = [
            'Establishing the authenticated session and preparing the engineering command center.',
            'Resolving company, role, permission, and workspace context.',
            'Loading project intelligence, engineering signals, and commercial activity.',
            'The NEXORIN command center is ready.',
        ];
        const stageLabels = ['AUTHENTICATING', 'RESOLVING ACCESS', 'LOADING SIGNALS', 'READY'];
        const totalDuration = reduceMotion ? 0 : 180;
        const animationDuration = reduceMotion ? 1 : 150;
        const startTime = performance.now();

        const renderEntry = (now) => {
            if (!document.body.contains(workspaceEntryOverlay)) return;
            const raw = Math.min(1, Math.max(0, now - startTime) / animationDuration);
            const eased = 1 - Math.pow(1 - raw, 2.2);
            const activeIndex = raw >= 1 ? steps.length : Math.min(steps.length - 1, Math.floor(raw * steps.length));

            if (progress) progress.style.width = `${Math.round(eased * 100)}%`;
            steps.forEach((step, index) => {
                step.classList.toggle('is-active', index === activeIndex);
                step.classList.toggle('is-complete', index < activeIndex || raw >= 1);
            });

            if (raw >= 1) {
                workspaceEntryOverlay.classList.add('is-ready');
                if (sequence) sequence.textContent = `SEQUENCE ${String(steps.length).padStart(2, '0')} / ${String(steps.length).padStart(2, '0')}`;
                if (state) state.textContent = 'READY';
                if (status) status.textContent = messages[messages.length - 1];
                if (caption) caption.textContent = 'Secure workspace handoff ready';
                return;
            }

            const stage = Math.min(steps.length - 1, activeIndex);
            if (sequence) sequence.textContent = `SEQUENCE ${String(stage + 1).padStart(2, '0')} / ${String(steps.length).padStart(2, '0')}`;
            if (state) state.textContent = stageLabels[stage];
            if (status) status.textContent = messages[stage];
            if (caption) caption.textContent = steps[stage]?.querySelector('b')?.textContent || messages[stage];
            requestAnimationFrame(renderEntry);
        };

        requestAnimationFrame(renderEntry);

        window.setTimeout(() => {
            root.classList.add('workspace-entry-ready');
            workspaceEntryOverlay.classList.remove('is-visible');

            window.setTimeout(() => {
                root.classList.remove('workspace-entry-pending', 'workspace-entry-ready', 'is-nxeng-processing');
                workspaceEntryOverlay.setAttribute('aria-hidden', 'true');

                try {
                    sessionStorage.removeItem('nexorin-auth-transition');
                } catch (error) {
                    // Storage access is optional.
                }
            }, reduceMotion ? 20 : 120);
        }, totalDuration);
    }

    /**
     * Mobile sidebar.
     */
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    const openSidebar = () => body.classList.add('sidebar-open');
    const closeSidebar = () => body.classList.remove('sidebar-open');

    sidebarToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1100) {
            closeSidebar();
        }
    });

    /**
     * Light and dark theme.
     */
    const themeToggle = document.getElementById('themeToggle');

    themeToggle?.addEventListener('click', () => {
        if (root.dataset.themeLocked === 'true') return;
        const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        root.dataset.theme = nextTheme;
        localStorage.setItem('nexorin-theme', nextTheme);
    });

    /**
     * Search command palette.
     */
    const searchForm = document.getElementById('globalSearchForm');
    const projectSearch = document.getElementById('globalSearchInput');
    const commandPalette = document.getElementById('commandPalette');
    const commandEmpty = commandPalette?.querySelector('[data-command-empty]');
    let commandIndex = -1;

    const visibleCommandItems = () => commandPalette
        ? [...commandPalette.querySelectorAll('[data-command-item]')]
            .filter((item) => !item.hidden)
        : [];

    const setCommandIndex = (nextIndex) => {
        const items = visibleCommandItems();
        items.forEach((item) => item.classList.remove('is-active'));

        if (items.length === 0) {
            commandIndex = -1;
            return;
        }

        commandIndex = (nextIndex + items.length) % items.length;
        items[commandIndex].classList.add('is-active');
        items[commandIndex].scrollIntoView({ block: 'nearest' });
    };

    const openCommandPalette = () => {
        if (!commandPalette || !projectSearch) {
            return;
        }

        commandPalette.hidden = false;
        projectSearch.setAttribute('aria-expanded', 'true');
        window.requestAnimationFrame(() => commandPalette.classList.add('is-open'));
        setCommandIndex(0);
    };

    const closeCommandPalette = () => {
        if (!commandPalette || !projectSearch) {
            return;
        }

        commandPalette.classList.remove('is-open');
        projectSearch.setAttribute('aria-expanded', 'false');
        commandIndex = -1;

        window.setTimeout(() => {
            if (!commandPalette.classList.contains('is-open')) {
                commandPalette.hidden = true;
            }
        }, reduceMotion ? 0 : 180);
    };

    const filterCommands = () => {
        if (!commandPalette || !projectSearch) {
            return;
        }

        const query = projectSearch.value.trim().toLowerCase();
        const items = [...commandPalette.querySelectorAll('[data-command-item]')];
        let visibleCount = 0;

        items.forEach((item) => {
            const value = item.dataset.searchValue || '';
            item.hidden = query !== '' && !value.includes(query);
            item.classList.remove('is-active');

            if (!item.hidden) {
                visibleCount += 1;
            }
        });

        if (commandEmpty) {
            commandEmpty.hidden = visibleCount > 0;
        }

        setCommandIndex(0);
    };

    projectSearch?.addEventListener('focus', openCommandPalette);
    projectSearch?.addEventListener('click', openCommandPalette);
    projectSearch?.addEventListener('input', () => {
        openCommandPalette();
        filterCommands();
    });

    searchForm?.addEventListener('keydown', (event) => {
        if (!commandPalette || commandPalette.hidden) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setCommandIndex(commandIndex + 1);
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setCommandIndex(commandIndex - 1);
        }

        if (event.key === 'Enter') {
            const activeItem = visibleCommandItems()[commandIndex];

            if (activeItem) {
                event.preventDefault();
                activeItem.click();
            }
        }
    });

    /**
     * Notification drawer.
     */
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationDrawer = document.getElementById('notificationDrawer');
    const notificationBackdrop = document.getElementById('notificationBackdrop');
    const notificationClose = document.getElementById('notificationClose');

    const openNotifications = () => {
        if (!notificationDrawer) {
            return;
        }

        body.classList.add('notifications-open');
        notificationDrawer.setAttribute('aria-hidden', 'false');
        notificationToggle?.setAttribute('aria-expanded', 'true');
    };

    const closeNotifications = () => {
        body.classList.remove('notifications-open');
        notificationDrawer?.setAttribute('aria-hidden', 'true');
        notificationToggle?.setAttribute('aria-expanded', 'false');
    };

    notificationToggle?.addEventListener('click', openNotifications);
    notificationBackdrop?.addEventListener('click', closeNotifications);
    notificationClose?.addEventListener('click', closeNotifications);

    /**
     * Global keyboard and outside-click behavior.
     */
    window.addEventListener('keydown', (event) => {
        const activeTag = document.activeElement?.tagName?.toLowerCase();
        const isTyping = ['input', 'textarea', 'select'].includes(activeTag);

        if (event.key === '/' && !isTyping && projectSearch) {
            event.preventDefault();
            projectSearch.focus();
            openCommandPalette();
        }

        if (event.key === 'Escape') {
            closeSidebar();
            closeCommandPalette();
            closeNotifications();
            projectSearch?.blur();
        }
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.profile-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });

        if (commandPalette && !event.target.closest('.topbar-search-shell')) {
            closeCommandPalette();
        }
    });

    /**
     * Reveal sections when they enter the viewport.
     */
    const revealItems = document.querySelectorAll('[data-reveal]');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    } else {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -28px',
        });

        revealItems.forEach((item) => revealObserver.observe(item));
    }

    /**
     * Animate numeric values without altering server-rendered values.
     */
    const counters = document.querySelectorAll('[data-counter]');

    const animateCounter = (element) => {
        const target = Number.parseFloat(element.dataset.counter ?? '0');

        if (!Number.isFinite(target) || reduceMotion) {
            return;
        }

        const hasDecimal = !Number.isInteger(target);
        const duration = 760;
        const startedAt = performance.now();

        const update = (timestamp) => {
            const elapsed = Math.min(1, (timestamp - startedAt) / duration);
            const eased = 1 - Math.pow(1 - elapsed, 4);
            const value = target * eased;

            element.textContent = hasDecimal
                ? value.toFixed(1)
                : Math.round(value).toLocaleString('en-US');

            if (elapsed < 1) {
                window.requestAnimationFrame(update);
            }
        };

        window.requestAnimationFrame(update);
    };

    if ('IntersectionObserver' in window && !reduceMotion) {
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                animateCounter(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.65 });

        counters.forEach((counter) => counterObserver.observe(counter));
    }

    /**
     * Animate project data-readiness bars.
     */
    const progressBars = document.querySelectorAll('[data-progress]');

    progressBars.forEach((bar, index) => {
        const value = Math.max(0, Math.min(100, Number(bar.dataset.progress ?? 0)));
        const delay = reduceMotion ? 0 : index * 55;

        window.setTimeout(() => {
            bar.style.width = `${value}%`;
        }, delay);
    });

    /**
     * Clickable work-queue rows. Native links remain fully functional.
     */
    document.querySelectorAll('[data-row-href]').forEach((row) => {
        const openRow = () => {
            const href = row.dataset.rowHref;
            if (href) {
                window.location.href = href;
            }
        };

        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, select, textarea')) {
                return;
            }
            openRow();
        });

        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openRow();
            }
        });
    });

    /**
     * Subtle pointer-following highlight.
     */
    if (!reduceMotion && window.matchMedia('(pointer: fine)').matches) {
        document.querySelectorAll('[data-premium-card]').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                card.style.setProperty('--mouse-x', `${event.clientX - bounds.left}px`);
                card.style.setProperty('--mouse-y', `${event.clientY - bounds.top}px`);
            });
        });
    }

    /**
     * Project directory controls.
     *
     * Select controls submit immediately, while search remains explicit so
     * users can finish typing before requesting a filtered result set.
     */
    const projectDirectoryForm = document.getElementById('projectDirectoryFilters');

    projectDirectoryForm?.querySelectorAll('[data-project-auto-submit]').forEach((control) => {
        control.addEventListener('change', () => {
            if (typeof projectDirectoryForm.requestSubmit === 'function') {
                projectDirectoryForm.requestSubmit();
                return;
            }

            projectDirectoryForm.submit();
        });
    });

    const projectSortDirection = document.querySelector('[data-project-sort-direction]');
    const projectSortDirectionInput = document.getElementById('project-sort-direction');

    projectSortDirection?.addEventListener('click', () => {
        if (!projectDirectoryForm || !projectSortDirectionInput) {
            return;
        }

        projectSortDirectionInput.value = projectSortDirectionInput.value === 'asc'
            ? 'desc'
            : 'asc';

        if (typeof projectDirectoryForm.requestSubmit === 'function') {
            projectDirectoryForm.requestSubmit();
            return;
        }

        projectDirectoryForm.submit();
    });

    const clearProjectSearch = document.querySelector('[data-project-clear-search]');
    const projectDirectorySearch = document.getElementById('project-search');

    clearProjectSearch?.addEventListener('click', () => {
        if (!projectDirectoryForm || !projectDirectorySearch) {
            return;
        }

        projectDirectorySearch.value = '';

        if (typeof projectDirectoryForm.requestSubmit === 'function') {
            projectDirectoryForm.requestSubmit();
            return;
        }

        projectDirectoryForm.submit();
    });

    /**
     * Premium project intake workspace.
     * Keeps the existing project creation request and route unchanged while
     * adding live readiness, section navigation, location tools, and safe
     * submission feedback.
     */
    const projectIntakeForm = document.querySelector('[data-project-intake-form]');

    if (projectIntakeForm) {
        const intakeRoot = projectIntakeForm.closest('[data-project-intake]');
        const sectionLinks = [...projectIntakeForm.querySelectorAll('[data-intake-nav]')];
        const sections = [...projectIntakeForm.querySelectorAll('[data-intake-section]')];
        const readinessRing = projectIntakeForm.querySelector('[data-intake-ring]');
        const readinessValue = projectIntakeForm.querySelector('[data-intake-progress]');
        const readinessLabel = projectIntakeForm.querySelector('[data-intake-progress-label]');
        const requiredComplete = projectIntakeForm.querySelector('[data-required-complete]');
        const mobileProgress = projectIntakeForm.querySelector('[data-mobile-progress]');
        const submitButtons = [...projectIntakeForm.querySelectorAll('[data-project-intake-submit]')];
        const mobileSubmit = projectIntakeForm.querySelector('[data-project-intake-submit-mobile]');
        const customerSelect = projectIntakeForm.querySelector('#customer_id');
        const customerPreview = projectIntakeForm.querySelector('[data-customer-preview]');
        const latitudeInput = projectIntakeForm.querySelector('#latitude');
        const longitudeInput = projectIntakeForm.querySelector('#longitude');
        const locationStatus = projectIntakeForm.querySelector('[data-location-status]');
        const useLocationButton = projectIntakeForm.querySelector('[data-use-location]');
        const locationButtonLabel = projectIntakeForm.querySelector('[data-location-button-label]');
        const choiceFieldsets = [...projectIntakeForm.querySelectorAll('[data-choice-confirmed]')];
        const confirmedChoices = new Map(
            choiceFieldsets.map((fieldset) => [
                fieldset.querySelector('input[type="radio"]')?.name,
                fieldset.dataset.choiceConfirmed === 'true',
            ]).filter(([name]) => Boolean(name))
        );
        const initialFormData = new FormData(projectIntakeForm);
        let formSubmitting = false;
        let firstInvalidHandled = false;

        const summaryValue = (key, value = 'Not specified', state = 'ready') => {
            const target = projectIntakeForm.querySelector(`[data-summary-value="${key}"]`);
            const row = projectIntakeForm.querySelector(`[data-summary-row="${key}"]`);
            const status = projectIntakeForm.querySelector(`[data-summary-status="${key}"]`);

            if (target) {
                target.textContent = value;
                target.title = value;
            }

            if (row) {
                row.classList.toggle('is-missing', state === 'missing');
                row.classList.toggle('is-default', state === 'default');
                row.classList.toggle('is-ready', state === 'ready');
            }

            if (status) {
                status.textContent = state === 'missing'
                    ? 'Missing'
                    : state === 'default'
                        ? 'Default'
                        : state === 'confirmed'
                            ? 'Confirmed'
                            : 'Ready';
            }
        };

        const selectedRadioValue = (name) => projectIntakeForm.querySelector(`input[name="${name}"]:checked`)?.value || '';

        const readableValue = (value) => ({
            residential: 'Residential',
            commercial: 'Commercial',
            undecided: 'Undecided',
            on_grid: 'On-grid',
            hybrid: 'Hybrid',
        })[value] || value || 'Not specified';

        const selectDisplayName = (select, fallback = 'Unassigned') => {
            const option = select?.selectedOptions?.[0];

            if (!option || !option.value) {
                return fallback;
            }

            return option.textContent.split('·')[0].trim() || fallback;
        };

        const requiredChecks = [
            () => Boolean(customerSelect?.value),
            () => Boolean(projectIntakeForm.querySelector('#name')?.value.trim()),
            () => Boolean(selectedRadioValue('building_type')) && confirmedChoices.get('building_type') === true,
            () => Boolean(selectedRadioValue('proposed_system')) && confirmedChoices.get('proposed_system') === true,
            () => Boolean(projectIntakeForm.querySelector('#site_address')?.value.trim()),
        ];

        const sectionCompletion = {
            'project-identity': () => requiredChecks.slice(0, 4).every((check) => check()),
            'project-location': () => requiredChecks[4](),
        };

        const updateSectionStates = () => {
            Object.entries(sectionCompletion).forEach(([id, check]) => {
                const state = projectIntakeForm.querySelector(`[data-section-state="${id}"]`);

                if (!state) {
                    return;
                }

                const complete = check();
                state.classList.toggle('is-complete', complete);
                state.textContent = complete ? 'Complete' : 'Required';
            });
        };

        const updateCustomerPreview = () => {
            const lockedCustomer = projectIntakeForm.querySelector('[data-customer-name]');

            if (lockedCustomer || customerSelect?.tagName !== 'SELECT') {
                if (customerPreview) {
                    customerPreview.hidden = true;
                }

                const lockedName = lockedCustomer?.dataset.customerName || 'Customer';
                summaryValue('customer', lockedName, 'ready');
                return;
            }

            if (!customerPreview || !customerSelect) {
                return;
            }

            const option = customerSelect.options[customerSelect.selectedIndex];

            if (!option || !customerSelect.value) {
                customerPreview.hidden = true;
                summaryValue('customer', 'Not selected', 'missing');
                return;
            }

            const customerName = option.dataset.name || option.textContent.trim();
            const customerNumber = option.dataset.number || '';
            const customerType = option.dataset.type || 'Customer';
            const customerLocation = option.dataset.location || '';

            customerPreview.hidden = false;
            customerPreview.querySelector('[data-customer-preview-name]').textContent = customerName;
            customerPreview.querySelector('[data-customer-preview-meta]').textContent = [customerNumber, customerType].filter(Boolean).join(' · ');
            customerPreview.querySelector('[data-customer-preview-location]').textContent = customerLocation || 'Location not recorded';
            summaryValue('customer', customerName, 'ready');
        };

        const updateLocationStatus = () => {
            if (!locationStatus) {
                return;
            }

            const hasLatitude = latitudeInput?.value !== '';
            const hasLongitude = longitudeInput?.value !== '';
            const ready = hasLatitude && hasLongitude;

            locationStatus.classList.toggle('is-ready', ready);
            locationStatus.classList.remove('is-error');
            locationStatus.textContent = ready ? 'Coordinates ready' : 'Not set';
        };

        const updateSummary = () => {
            const name = projectIntakeForm.querySelector('#name')?.value.trim();
            const city = projectIntakeForm.querySelector('#city')?.value.trim();
            const province = projectIntakeForm.querySelector('#province')?.value.trim();
            const address = projectIntakeForm.querySelector('#site_address')?.value.trim();
            const engineer = projectIntakeForm.querySelector('#assigned_engineer_id');
            const sales = projectIntakeForm.querySelector('#assigned_sales_id');
            const buildingType = readableValue(selectedRadioValue('building_type'));
            const proposedSystem = readableValue(selectedRadioValue('proposed_system'));
            const location = [city, province].filter(Boolean).join(', ') || address || 'Not specified';

            summaryValue('name', name || 'Untitled project', name ? 'ready' : 'missing');
            summaryValue(
                'building_type',
                buildingType,
                confirmedChoices.get('building_type') === true ? 'confirmed' : 'default'
            );
            summaryValue(
                'proposed_system',
                proposedSystem,
                confirmedChoices.get('proposed_system') === true ? 'confirmed' : 'default'
            );
            summaryValue('location', location, address ? 'ready' : 'missing');
            summaryValue('engineer', selectDisplayName(engineer), 'ready');
            summaryValue('sales', selectDisplayName(sales), 'ready');

            updateCustomerPreview();
            updateLocationStatus();
        };

        const updateReadiness = () => {
            const complete = requiredChecks.filter((check) => check()).length;
            const remaining = requiredChecks.length - complete;
            const percentage = Math.round((complete / requiredChecks.length) * 100);
            const ready = remaining === 0;

            if (readinessRing) {
                readinessRing.style.setProperty('--intake-progress', percentage);
            }

            if (readinessValue) {
                readinessValue.textContent = String(percentage);
            }

            if (requiredComplete) {
                requiredComplete.textContent = String(complete);
            }

            if (mobileProgress) {
                mobileProgress.textContent = `${percentage}%`;
            }

            if (readinessLabel) {
                readinessLabel.textContent = ready
                    ? 'Project brief is ready'
                    : remaining === requiredChecks.length
                        ? 'Start with the customer brief'
                        : `${remaining} required ${remaining === 1 ? 'detail' : 'details'} remaining`;
            }

            submitButtons.forEach((button) => {
                if (formSubmitting) {
                    return;
                }

                button.disabled = !ready;
                button.setAttribute('aria-disabled', ready ? 'false' : 'true');
                const label = button.querySelector('[data-submit-label]');

                if (label) {
                    label.textContent = ready
                        ? 'Create project'
                        : `Complete ${remaining} required ${remaining === 1 ? 'field' : 'fields'}`;
                }
            });

            if (mobileSubmit && !formSubmitting) {
                mobileSubmit.disabled = !ready;
                mobileSubmit.setAttribute('aria-disabled', ready ? 'false' : 'true');
                mobileSubmit.textContent = ready
                    ? 'Create project'
                    : `${remaining} required ${remaining === 1 ? 'field' : 'fields'} left`;
            }

            updateSectionStates();
            updateSummary();
        };

        const resizeTextarea = (textarea) => {
            textarea.style.height = 'auto';
            textarea.style.height = `${Math.max(textarea.scrollHeight, 96)}px`;
        };

        projectIntakeForm.querySelectorAll('[data-auto-resize]').forEach((textarea) => {
            resizeTextarea(textarea);
            textarea.addEventListener('input', () => resizeTextarea(textarea));
        });

        projectIntakeForm.querySelectorAll('[maxlength]').forEach((field) => {
            const counter = projectIntakeForm.querySelector(`[data-character-current="${field.id}"]`);

            if (!counter) {
                return;
            }

            const updateCounter = () => {
                counter.textContent = String(field.value.length);
            };

            field.addEventListener('input', updateCounter);
            updateCounter();
        });

        if (customerSelect?.tagName === 'SELECT') {
            const initialCustomerValue = customerSelect.dataset.initialValue || '';

            if (initialCustomerValue && customerSelect.value !== initialCustomerValue) {
                const initialOption = [...customerSelect.options].find((option) => option.value === initialCustomerValue);

                if (initialOption) {
                    customerSelect.value = initialCustomerValue;
                }
            }
        }

        choiceFieldsets.forEach((fieldset) => {
            fieldset.querySelectorAll('input[type="radio"]').forEach((radio) => {
                const confirmChoice = () => {
                    confirmedChoices.set(radio.name, true);
                    fieldset.dataset.choiceConfirmed = 'true';
                    updateReadiness();
                };

                radio.addEventListener('click', confirmChoice);
                radio.addEventListener('change', confirmChoice);
            });
        });

        projectIntakeForm.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', updateReadiness);
            field.addEventListener('change', updateReadiness);
        });

        projectIntakeForm.querySelectorAll('.project-intake-card').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const rect = card.getBoundingClientRect();
                card.style.setProperty('--mouse-x', `${event.clientX - rect.left}px`);
                card.style.setProperty('--mouse-y', `${event.clientY - rect.top}px`);
            });
        });

        if ('IntersectionObserver' in window && sections.length) {
            const sectionObserver = new IntersectionObserver((entries) => {
                const visibleEntry = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

                if (!visibleEntry) {
                    return;
                }

                sectionLinks.forEach((link) => {
                    link.classList.toggle('is-active', link.dataset.intakeNav === visibleEntry.target.id);
                });
            }, {
                rootMargin: '-24% 0px -58% 0px',
                threshold: [0.12, 0.3, 0.55],
            });

            sections.forEach((section) => sectionObserver.observe(section));
        }

        sectionLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                const target = document.getElementById(link.dataset.intakeNav);

                if (!target) {
                    return;
                }

                event.preventDefault();
                const topbar = document.querySelector('.topbar');
                const sectionNav = projectIntakeForm.querySelector('[data-intake-section-nav]');
                const offset = (topbar?.offsetHeight || 72) + (sectionNav?.offsetHeight || 54) + 24;
                const targetTop = target.getBoundingClientRect().top + window.scrollY - offset;

                window.scrollTo({
                    top: Math.max(0, targetTop),
                    behavior: reduceMotion ? 'auto' : 'smooth',
                });
            });
        });

        useLocationButton?.addEventListener('click', () => {
            if (!navigator.geolocation || !latitudeInput || !longitudeInput) {
                if (locationStatus) {
                    locationStatus.textContent = 'Location unavailable';
                    locationStatus.classList.add('is-error');
                }
                return;
            }

            useLocationButton.disabled = true;
            if (locationButtonLabel) locationButtonLabel.textContent = 'Locating…';

            if (locationStatus) {
                locationStatus.textContent = 'Locating…';
                locationStatus.classList.remove('is-ready', 'is-error');
            }

            navigator.geolocation.getCurrentPosition((position) => {
                latitudeInput.value = position.coords.latitude.toFixed(7);
                longitudeInput.value = position.coords.longitude.toFixed(7);
                useLocationButton.disabled = false;
                if (locationButtonLabel) locationButtonLabel.textContent = 'Use current location';
                updateReadiness();
            }, () => {
                useLocationButton.disabled = false;
                if (locationButtonLabel) locationButtonLabel.textContent = 'Use current location';

                if (locationStatus) {
                    locationStatus.textContent = 'Permission denied';
                    locationStatus.classList.add('is-error');
                }
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000,
            });
        });

        mobileSubmit?.addEventListener('click', () => {
            if (typeof projectIntakeForm.requestSubmit === 'function') {
                projectIntakeForm.requestSubmit();
                return;
            }

            projectIntakeForm.submit();
        });

        projectIntakeForm.addEventListener('invalid', (event) => {
            if (firstInvalidHandled) {
                return;
            }

            firstInvalidHandled = true;
            window.setTimeout(() => {
                firstInvalidHandled = false;
            }, 300);

            const card = event.target.closest('[data-intake-section]');
            card?.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
        }, true);

        projectIntakeForm.addEventListener('submit', (event) => {
            const complete = requiredChecks.filter((check) => check()).length;

            if (complete !== requiredChecks.length) {
                event.preventDefault();
                updateReadiness();

                const firstMissing = [
                    customerSelect,
                    projectIntakeForm.querySelector('#name'),
                    confirmedChoices.get('building_type') === true ? null : projectIntakeForm.querySelector('input[name="building_type"]'),
                    confirmedChoices.get('proposed_system') === true ? null : projectIntakeForm.querySelector('input[name="proposed_system"]'),
                    projectIntakeForm.querySelector('#site_address'),
                ].find((field, index) => field && !requiredChecks[index]());

                const card = firstMissing?.closest('[data-intake-section]');
                card?.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
                window.setTimeout(() => firstMissing?.focus?.(), reduceMotion ? 0 : 360);
                return;
            }

            formSubmitting = true;

            submitButtons.forEach((button) => {
                button.disabled = true;
                button.classList.add('is-loading');
                const label = button.querySelector('[data-submit-label]');
                if (label) {
                    label.textContent = 'Creating project…';
                }
            });

            if (mobileSubmit) {
                mobileSubmit.disabled = true;
                mobileSubmit.textContent = 'Creating…';
            }
        });

        const formHasChanged = () => {
            const currentData = new FormData(projectIntakeForm);
            const normalize = (data) => [...data.entries()]
                .filter(([key]) => key !== '_token')
                .map(([key, value]) => `${key}:${String(value)}`)
                .sort()
                .join('|');

            return normalize(currentData) !== normalize(initialFormData);
        };

        window.addEventListener('beforeunload', (event) => {
            if (window.__nexorinProjectNavigationApproved || formSubmitting || !formHasChanged()) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });

        intakeRoot?.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', async (event) => {
                const href = link.getAttribute('href') || '';

                if (href.startsWith('#') || formSubmitting || !formHasChanged() || link.target === '_blank') {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                const approved = await askForConfirmation(
                    'Leave this page? Your unsaved project intake will be lost.',
                    {
                        tone: 'danger',
                        title: 'Leave the unfinished project intake?',
                        confirmLabel: 'Leave page',
                        control: 'Unsaved project evidence detected',
                        result: 'Current intake changes will be discarded',
                    },
                );

                if (approved) {
                    window.__nexorinProjectNavigationApproved = true;
                    window.location.assign(link.href);
                }
            });
        });

        updateReadiness();
        window.requestAnimationFrame(updateReadiness);

        const firstError = projectIntakeForm.querySelector('.project-intake-error');
        firstError?.closest('[data-intake-section]')?.scrollIntoView({
            behavior: reduceMotion ? 'auto' : 'smooth',
            block: 'center',
        });
    }

    /**
     * Shared alerts and controlled-action confirmation.
     */
    const dismissAlert = (alert) => {
        if (!alert || alert.classList.contains('is-leaving')) return;
        alert.classList.add('is-leaving');
        window.setTimeout(() => alert.remove(), reduceMotion ? 0 : 190);
    };

    document.querySelectorAll('[data-alert-close]').forEach((button) => {
        button.addEventListener('click', () => dismissAlert(button.closest('[data-alert]')));
    });

    document.querySelectorAll('[data-alert-autodismiss]').forEach((alert) => {
        const duration = Number(alert.dataset.alertAutodismiss || 0);
        if (!Number.isFinite(duration) || duration <= 0) return;

        let timer = null;
        const stop = () => {
            if (timer !== null) window.clearTimeout(timer);
            timer = null;
        };
        const start = () => {
            stop();
            timer = window.setTimeout(() => dismissAlert(alert), duration);
        };

        alert.addEventListener('pointerenter', stop);
        alert.addEventListener('pointerleave', start);
        alert.addEventListener('focusin', stop);
        alert.addEventListener('focusout', (event) => {
            if (!alert.contains(event.relatedTarget)) start();
        });
        start();
    });

    const confirmOptionsFrom = (element) => ({
        tone: element.dataset.confirmTone,
        title: element.dataset.confirmTitle,
        kicker: element.dataset.confirmKicker,
        confirmLabel: element.dataset.confirmLabel,
        cancelLabel: element.dataset.confirmCancelLabel,
        control: element.dataset.confirmControl,
        result: element.dataset.confirmResult,
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-confirm]');
        if (!form) return;
        if (form.dataset.nxConfirmApproved === 'true') {
            delete form.dataset.nxConfirmApproved;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter || form.querySelector('[type="submit"]');
        const approved = await askForConfirmation(
            form.dataset.confirm || 'Continue with this action?',
            confirmOptionsFrom(form),
        );
        if (!approved) return;

        form.dataset.nxConfirmApproved = 'true';
        if (typeof form.requestSubmit === 'function') {
            if (submitter) form.requestSubmit(submitter);
            else form.requestSubmit();
        } else HTMLFormElement.prototype.submit.call(form);
    }, true);

    document.addEventListener('click', async (event) => {
        const element = event.target.closest('[data-confirm]');
        if (!element || element.matches('form') || element.closest('form[data-confirm]')) return;
        if (element.dataset.nxConfirmApproved === 'true') {
            delete element.dataset.nxConfirmApproved;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const approved = await askForConfirmation(
            element.dataset.confirm || 'Continue with this action?',
            confirmOptionsFrom(element),
        );
        if (!approved) return;

        if (element.matches('a[href]')) {
            window.location.assign(element.href);
            return;
        }

        element.dataset.nxConfirmApproved = 'true';
        if (element.matches('button[type="submit"], input[type="submit"]') && element.form) {
            element.form.requestSubmit(element);
        } else {
            element.click();
        }
    }, true);
})();
