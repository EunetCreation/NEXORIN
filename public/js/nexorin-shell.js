/**
 * NEXORIN application shell interactions.
 *
 * This file only controls presentation behavior. Existing routes, forms,
 * permissions, and project workflow remain unchanged.
 */
(() => {
    'use strict';

    const root = document.documentElement;
    const body = document.body;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    root.classList.add('motion-ready');

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
     * Light and dark theme. The selected value is stored in the browser.
     */
    const themeToggle = document.getElementById('themeToggle');

    themeToggle?.addEventListener('click', () => {
        const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        root.dataset.theme = nextTheme;
        localStorage.setItem('nexorin-theme', nextTheme);
    });

    /**
     * Press slash to focus the project search field.
     */
    const projectSearch = document.querySelector('.topbar-search input[type="search"]');

    window.addEventListener('keydown', (event) => {
        const activeTag = document.activeElement?.tagName?.toLowerCase();
        const isTyping = ['input', 'textarea', 'select'].includes(activeTag);

        if (event.key === '/' && !isTyping && projectSearch) {
            event.preventDefault();
            projectSearch.focus();
        }

        if (event.key === 'Escape') {
            closeSidebar();
            projectSearch?.blur();
        }
    });

    /**
     * Close profile menus when clicking outside.
     */
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.profile-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    /**
     * Reveal dashboard sections when they enter the viewport.
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
     * Animate numeric values without altering the server-rendered final value.
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
     * Animate project completion bars after the table becomes visible.
     */
    const progressBars = document.querySelectorAll('[data-progress]');

    const fillProgressBars = () => {
        progressBars.forEach((bar, index) => {
            const value = Math.max(0, Math.min(100, Number(bar.dataset.progress ?? 0)));
            const delay = reduceMotion ? 0 : index * 55;

            window.setTimeout(() => {
                bar.style.width = `${value}%`;
            }, delay);
        });
    };

    if (progressBars.length > 0) {
        window.requestAnimationFrame(fillProgressBars);
    }

    /**
     * Add a subtle pointer-following highlight to premium cards.
     */
    if (!reduceMotion && window.matchMedia('(pointer: fine)').matches) {
        document.querySelectorAll('[data-premium-card]').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                const x = event.clientX - bounds.left;
                const y = event.clientY - bounds.top;

                card.style.setProperty('--mouse-x', `${x}px`);
                card.style.setProperty('--mouse-y', `${y}px`);
            });
        });
    }

    /**
     * Shared alerts and destructive-action confirmation.
     */
    document.querySelectorAll('[data-alert-close]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('[data-alert]')?.remove();
        });
    });

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.dataset.confirm || 'Continue with this action?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
})();
