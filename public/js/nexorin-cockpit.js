(() => {
    'use strict';

    const root = document.querySelector('[data-cockpit-root]');

    if (!root) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const tabButtons = [...root.querySelectorAll('[data-cockpit-tab]')];
    const panels = [...root.querySelectorAll('[data-cockpit-panel]')];
    const openTabButtons = [...root.querySelectorAll('[data-cockpit-open-tab]')];
    const cockpitTabs = root.querySelector('.nx-cockpit-tabs');

    const getTopbarHeight = () => {
        const raw = window.getComputedStyle(document.documentElement)
            .getPropertyValue('--topbar-height');
        const parsed = Number.parseFloat(raw);

        return Number.isFinite(parsed) ? parsed : 72;
    };

    /**
     * Open one cockpit panel and keep tab state accessible.
     *
     * @param {string} tabName
     * @param {{ focus?: boolean, updateHash?: boolean, scroll?: boolean }} options
     */
    const openTab = (tabName, options = {}) => {
        const targetButton = tabButtons.find((button) => button.dataset.cockpitTab === tabName);
        const targetPanel = panels.find((panel) => panel.dataset.cockpitPanel === tabName);

        if (!targetButton || !targetPanel) {
            return;
        }

        tabButtons.forEach((button) => {
            const active = button === targetButton;
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.tabIndex = active ? 0 : -1;
        });

        panels.forEach((panel) => {
            const active = panel === targetPanel;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
            panel.classList.remove('is-entering');
        });

        if (!reduceMotion) {
            window.requestAnimationFrame(() => {
                targetPanel.classList.add('is-entering');
            });
        }

        if (options.updateHash !== false) {
            const url = new URL(window.location.href);
            url.hash = tabName;
            window.history.replaceState({}, '', url);
        }

        if (options.focus) {
            targetButton.focus({ preventScroll: true });
        }

        if (options.scroll && cockpitTabs) {
            const targetTop = window.scrollY
                + cockpitTabs.getBoundingClientRect().top
                - getTopbarHeight()
                - 10;

            window.scrollTo({
                top: Math.max(0, targetTop),
                behavior: reduceMotion ? 'auto' : 'smooth',
            });
        }
    };

    tabButtons.forEach((button, index) => {
        button.addEventListener('click', () => {
            openTab(button.dataset.cockpitTab || 'overview');
        });

        button.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            event.preventDefault();

            let nextIndex = index;

            if (event.key === 'ArrowRight') {
                nextIndex = (index + 1) % tabButtons.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (index - 1 + tabButtons.length) % tabButtons.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabButtons.length - 1;
            }

            const nextButton = tabButtons[nextIndex];
            openTab(nextButton.dataset.cockpitTab || 'overview', { focus: true });
        });
    });

    openTabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openTab(button.dataset.cockpitOpenTab || 'overview', { scroll: true });
        });
    });

    const requestedTab = window.location.hash.replace('#', '');
    const validRequestedTab = tabButtons.some((button) => button.dataset.cockpitTab === requestedTab);

    openTab(validRequestedTab ? requestedTab : 'overview', {
        updateHash: validRequestedTab,
    });


    /**
     * Elevate the sticky cockpit navigation only after it reaches the topbar.
     */
    if (cockpitTabs) {
        let stickyFrame = null;

        const updateStickyState = () => {
            stickyFrame = null;
            const stickyTop = getTopbarHeight() + 10;
            cockpitTabs.classList.toggle(
                'is-stuck',
                cockpitTabs.getBoundingClientRect().top <= stickyTop + 1
            );
        };

        const requestStickyUpdate = () => {
            if (stickyFrame !== null) {
                return;
            }

            stickyFrame = window.requestAnimationFrame(updateStickyState);
        };

        updateStickyState();
        window.addEventListener('scroll', requestStickyUpdate, { passive: true });
        window.addEventListener('resize', requestStickyUpdate, { passive: true });
    }

    /**
     * Warning severity filters.
     */
    const warningFilterButtons = [...root.querySelectorAll('[data-warning-filter]')];
    const warningCards = [...root.querySelectorAll('[data-warning-severity]')];

    warningFilterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.warningFilter || 'all';

            warningFilterButtons.forEach((item) => {
                item.classList.toggle('is-active', item === button);
            });

            warningCards.forEach((card) => {
                card.hidden = filter !== 'all' && card.dataset.warningSeverity !== filter;
            });
        });
    });

    /**
     * Cashflow values stay compact until a year is selected.
     */
    const cashflowInspectors = [...root.querySelectorAll('[data-cashflow-inspector]')];

    const closeCashflowInspectors = (except = null) => {
        cashflowInspectors.forEach((button) => {
            if (button !== except) {
                button.setAttribute('aria-expanded', 'false');
            }
        });
    };

    cashflowInspectors.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = button.getAttribute('aria-expanded') !== 'true';
            closeCashflowInspectors(button);
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    document.addEventListener('click', () => closeCashflowInspectors());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeCashflowInspectors();
        }
    });

    /**
     * Reports & Audit uses a compact local navigator without changing the
     * primary cockpit hash or business route.
     */
    const reportJumpButtons = [...root.querySelectorAll('[data-report-target]')];

    reportJumpButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.reportTarget;
            const target = targetId ? document.getElementById(targetId) : null;

            if (!target) {
                return;
            }

            reportJumpButtons.forEach((item) => {
                item.classList.toggle('is-active', item === button);
            });

            const targetTop = window.scrollY
                + target.getBoundingClientRect().top
                - getTopbarHeight()
                - (cockpitTabs?.offsetHeight || 56)
                - 18;

            window.scrollTo({
                top: Math.max(0, targetTop),
                behavior: reduceMotion ? 'auto' : 'smooth',
            });
        });
    });

    /**
     * Premium pointer highlight, limited to fine pointing devices.
     */
    if (!reduceMotion && window.matchMedia('(pointer: fine)').matches) {
        root.querySelectorAll('[data-cockpit-glow]').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                const x = event.clientX - bounds.left;
                const y = event.clientY - bounds.top;

                card.style.setProperty('--cockpit-x', `${x}px`);
                card.style.setProperty('--cockpit-y', `${y}px`);
            });
        });
    }

    /**
     * Animate module completion only after layout is ready.
     */
    root.querySelectorAll('[data-cockpit-progress]').forEach((bar) => {
        const value = Math.min(100, Math.max(0, Number(bar.dataset.cockpitProgress || 0)));

        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                bar.style.width = `${value}%`;
            });
        });
    });
})();
