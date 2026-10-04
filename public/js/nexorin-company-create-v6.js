(() => {
    'use strict';

    const root = document.querySelector('[data-nxcg-create-v6]');
    if (!root) return;

    const form = root.querySelector('[data-nxcg-editor-form]');
    if (!form) return;

    const fields = {
        name: form.querySelector('[name="name"]'),
        code: form.querySelector('[name="code"]'),
        registration: form.querySelector('[name="registration_number"]'),
        email: form.querySelector('[name="email"]'),
        phone: form.querySelector('[name="phone"]'),
        address: form.querySelector('[name="address"]'),
    };
    const statusInputs = Array.from(form.querySelectorAll('[name="status"]'));

    const nodes = {
        percent: form.querySelector('[data-nxcg-create-percent]'),
        gauge: form.querySelector('[data-nxcg-create-ring]'),
        required: form.querySelector('[data-nxcg-create-required]'),
        identityCount: form.querySelector('[data-nxcg-readiness-identity]'),
        contactCount: form.querySelector('[data-nxcg-readiness-contact]'),
        legalCount: form.querySelector('[data-nxcg-readiness-legal]'),
        previewName: form.querySelector('[data-nxcg-preview-name-output]'),
        previewCode: form.querySelector('[data-nxcg-preview-code-output]'),
        previewRegistration: form.querySelector('[data-nxcg-create-preview-registration]'),
        previewEmail: form.querySelector('[data-nxcg-create-preview-email]'),
        previewPhone: form.querySelector('[data-nxcg-create-preview-phone]'),
        previewAddress: form.querySelector('[data-nxcg-create-preview-address]'),
        previewStatus: form.querySelector('[data-nxcg-preview-status-output]'),
        previewState: form.querySelector('[data-nxcg-preview-state]'),
        accessState: form.querySelector('[data-nxcg-access-state]'),
        contactState: form.querySelector('[data-nxcg-contact-state]'),
        nextTitle: form.querySelector('[data-nxcg-next-title]'),
        nextLink: form.querySelector('[data-nxcg-next-link]'),
        overall: form.querySelector('[data-nxcg-summary-overall]'),
        overallBar: form.querySelector('[data-nxcg-summary-overall-bar]'),
        actionState: form.querySelector('[data-nxcg-action-state]'),
        actionCopy: form.querySelector('[data-nxcg-action-copy]'),
    };

    const checks = new Map(Array.from(form.querySelectorAll('[data-nxcg-check]')).map((node) => [node.dataset.nxcgCheck, node]));
    const readinessRows = new Map(Array.from(form.querySelectorAll('[data-nxcg-readiness]')).map((node) => [node.dataset.nxcgReadiness, node]));
    const steps = new Map(Array.from(root.querySelectorAll('[data-nxcg-create-step]')).map((node) => [node.dataset.nxcgCreateStep, node]));
    const summaries = new Map(Array.from(form.querySelectorAll('[data-nxcg-summary]')).map((node) => [node.dataset.nxcgSummary, node]));
    const countSources = Array.from(form.querySelectorAll('[data-nxcg-count-source]'));

    const value = (field) => String(field?.value || '').trim();
    const selectedStatus = () => statusInputs.find((input) => input.checked)?.value || '';
    const setText = (node, next) => { if (node) node.textContent = next; };
    const displayValue = (next) => next || 'Not provided';

    const setSummary = (key, complete, total, requiredSection = false) => {
        const row = summaries.get(key);
        if (!row) return;
        const pct = total ? Math.round((complete / total) * 100) : 0;
        const bar = row.querySelector('span:nth-child(3) i');
        const pctText = row.querySelector('span:nth-child(3) b');
        const count = row.querySelector(`[data-nxcg-summary-count="${key}"]`);
        const status = row.querySelector(`[data-nxcg-summary-status="${key}"]`);
        if (bar) bar.style.setProperty('--completion', String(pct));
        setText(pctText, `${pct}%`);
        setText(count, `${complete} / ${total}`);
        const state = pct === 100 ? 'Complete' : complete > 0 ? 'In progress' : requiredSection ? 'Incomplete' : 'Not started';
        setText(status, state);
        row.classList.toggle('is-complete', pct === 100);
        row.classList.toggle('is-progress', complete > 0 && pct < 100);
    };

    const updateCounts = () => {
        countSources.forEach((field) => {
            const key = field.dataset.nxcgCountSource;
            const target = form.querySelector(`[data-nxcg-count="${key}"]`);
            if (!target) return;
            setText(target, `${String(field.value || '').length} / ${field.maxLength}`);
        });
    };

    const update = () => {
        const identityReady = [value(fields.name), value(fields.code)].filter(Boolean).length;
        const contactReady = [value(fields.email), value(fields.phone), value(fields.address)].filter(Boolean).length;
        const legalReady = value(fields.registration) ? 1 : 0;
        const status = selectedStatus();
        const statusReady = status !== '' ? 1 : 0;
        const requiredReady = identityReady + statusReady;
        const readiness = Math.round((requiredReady / 3) * 100);

        setText(nodes.percent, `${readiness}%`);
        setText(nodes.required, `${requiredReady} of 3 required`);
        setText(nodes.identityCount, `${identityReady} / 2`);
        setText(nodes.contactCount, `${contactReady} / 3`);
        setText(nodes.legalCount, `${legalReady} / 1`);
        if (nodes.gauge) nodes.gauge.style.setProperty('--nxcg-progress', String(readiness));
        setText(nodes.overall, `${readiness}%`);
        if (nodes.overallBar) nodes.overallBar.style.setProperty('--completion', String(readiness));

        setText(nodes.previewName, displayValue(value(fields.name)));
        setText(nodes.previewCode, value(fields.code) || 'Company code not provided');
        setText(nodes.previewRegistration, displayValue(value(fields.registration)));
        setText(nodes.previewEmail, displayValue(value(fields.email)));
        setText(nodes.previewPhone, displayValue(value(fields.phone)));
        setText(nodes.previewAddress, displayValue(value(fields.address)));
        setText(nodes.previewStatus, status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Not selected');
        setText(nodes.accessState, status ? status.toUpperCase() : 'REQUIRED');
        setText(nodes.contactState, contactReady ? `${contactReady} of 3 fields` : 'Optional');
        setText(nodes.previewState, identityReady === 2 ? 'Identity ready' : 'Identity incomplete');
        nodes.previewState?.classList.toggle('is-ready', identityReady === 2);

        statusInputs.forEach((input) => input.closest('label')?.classList.toggle('is-selected', input.checked));

        checks.get('name')?.classList.toggle('is-complete', value(fields.name) !== '');
        checks.get('code')?.classList.toggle('is-complete', value(fields.code) !== '');
        checks.get('status')?.classList.toggle('is-complete', statusReady === 1);

        readinessRows.get('identity')?.classList.toggle('is-complete', identityReady === 2);
        readinessRows.get('contact')?.classList.toggle('is-complete', contactReady === 3);
        readinessRows.get('legal')?.classList.toggle('is-complete', legalReady === 1);
        readinessRows.get('access')?.classList.toggle('is-complete', statusReady === 1);

        steps.get('identity')?.classList.toggle('is-complete', identityReady === 2);
        steps.get('contact')?.classList.toggle('is-complete', contactReady === 3);
        steps.get('legal')?.classList.toggle('is-complete', legalReady === 1);
        steps.get('access')?.classList.toggle('is-complete', statusReady === 1);
        steps.get('review')?.classList.toggle('is-complete', requiredReady === 3);

        setSummary('identity', identityReady, 2, true);
        setSummary('contact', contactReady, 3, false);
        setSummary('legal', legalReady, 1, false);
        setSummary('access', statusReady, 1, true);
        updateCounts();

        if (identityReady < 2) {
            setText(nodes.nextTitle, 'Complete the company identity');
            if (nodes.nextLink) { nodes.nextLink.href = '#company-identity'; nodes.nextLink.textContent = 'Go to Company Basics →'; }
            setText(nodes.actionState, 'Company identity incomplete');
            setText(nodes.actionCopy, 'Complete Company Name and Company Code before submitting.');
        } else if (requiredReady < 3) {
            setText(nodes.nextTitle, 'Confirm workspace access');
            if (nodes.nextLink) { nodes.nextLink.href = '#company-access'; nodes.nextLink.textContent = 'Go to Workspace Access →'; }
            setText(nodes.actionState, 'Workspace status required');
            setText(nodes.actionCopy, 'Choose the initial workspace access state before submitting.');
        } else {
            setText(nodes.nextTitle, 'Required fields are complete');
            if (nodes.nextLink) { nodes.nextLink.href = '#company-review'; nodes.nextLink.textContent = 'Review company record →'; }
            setText(nodes.actionState, 'Ready for server validation');
            setText(nodes.actionCopy, 'Submission will enforce authorization, uniqueness checks, and server-side validation.');
        }
    };

    if (fields.code) {
        fields.code.addEventListener('input', () => {
            const start = fields.code.selectionStart;
            const end = fields.code.selectionEnd;
            const normalized = fields.code.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
            if (normalized !== fields.code.value) {
                fields.code.value = normalized;
                if (start !== null && end !== null) fields.code.setSelectionRange(start, end);
            }
        });
    }

    root.querySelectorAll('[data-nxcg-scroll]').forEach((anchor) => {
        anchor.addEventListener('click', (event) => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        });
    });

    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
})();
