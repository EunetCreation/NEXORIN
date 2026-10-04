(() => {
    'use strict';

    const page = document.querySelector('[data-nx13-page]');
    if (!page) return;

    const form = page.querySelector('[data-nx13-filter-form]');
    const autoSubmit = page.querySelector('[data-nx13-auto-submit]');

    if (form && autoSubmit) {
        autoSubmit.addEventListener('change', () => {
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        });
    }

    page.querySelectorAll('[data-nx13-row]').forEach((row) => {
        row.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            const link = row.querySelector('.nx13-open');
            if (link) link.click();
        });
    });
})();
