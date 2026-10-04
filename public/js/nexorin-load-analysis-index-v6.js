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
        const page = document.querySelector('[data-nx17-page]');
        if (!page) return;

        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        const finePointer = window.matchMedia?.('(hover:hover) and (pointer:fine)').matches ?? false;
        const reveal = [...page.querySelectorAll('[data-nx17-reveal]')];

        if (reduced || !('IntersectionObserver' in window)) {
            reveal.forEach((node) => node.classList.add('is-visible'));
        } else {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.05, rootMargin: '0px 0px -3% 0px' });

            reveal.forEach((node) => observer.observe(node));
        }

        if (!reduced && finePointer) {
            page.querySelectorAll('[data-nx17-surface]').forEach((surface) => {
                surface.addEventListener('pointermove', (event) => {
                    const rect = surface.getBoundingClientRect();
                    if (!rect.width || !rect.height) return;
                    surface.style.setProperty('--nx17-x', `${((event.clientX - rect.left) / rect.width * 100).toFixed(1)}%`);
                    surface.style.setProperty('--nx17-y', `${((event.clientY - rect.top) / rect.height * 100).toFixed(1)}%`);
                }, { passive: true });
                surface.addEventListener('pointerleave', () => {
                    surface.style.removeProperty('--nx17-x');
                    surface.style.removeProperty('--nx17-y');
                }, { passive: true });
            });
        }

        const filterForm = page.querySelector('[data-nx17-filter-form]');
        filterForm?.addEventListener('change', () => filterForm.classList.add('is-dirty'));
        filterForm?.addEventListener('input', () => filterForm.classList.add('is-dirty'));
    });
})();
