(() => {
    'use strict';

    const actionEngine = window.NexorinEngineeringAction;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const profile = document.querySelector('[data-nxcus-profile]');
    const editor = document.querySelector('[data-nxcus-editor]');
    const customerName = profile?.dataset.customerName || editor?.dataset.customerName || 'Customer relationship';
    const companyName = editor?.dataset.companyName || 'Company workspace';

    const config = (type, meta = {}) => {
        const presets = {
            directory: { icon: 'portfolio', title: 'Opening Customer Relationship Register', copy: 'Loading customer identities, project exposure, portal ownership, and lifecycle states.', steps: ['Retaining company access context', 'Loading governed customer records', 'Resolving project and portal relationships', 'Opening Customer Relationship Register'] },
            create: { icon: 'portfolio', title: 'Opening Customer Intake', copy: 'Preparing a controlled customer identity, contact, location, and portal-governance workflow.', steps: ['Retaining company workspace context', 'Preparing relationship identity controls', 'Loading eligible portal identities', 'Opening Customer Intake'] },
            open: { icon: 'portfolio', title: 'Opening Customer Relationship', copy: 'Loading identity, project exposure, portal access, and lifecycle governance.', steps: ['Loading customer identity record', 'Resolving linked project exposure', 'Resolving portal ownership state', 'Opening Customer Relationship Cockpit'] },
            edit: { icon: 'edit', title: 'Opening Customer Revision', copy: 'Restoring the saved relationship identity, contact, location, and portal ownership baseline.', steps: ['Loading saved customer identity', 'Restoring communication and location values', 'Resolving portal ownership eligibility', 'Opening Controlled Customer Revision'] },
            projects: { icon: 'portfolio', title: 'Opening Customer Projects', copy: 'Loading company-scoped project records and relationship context.', steps: ['Retaining customer context', 'Loading linked project records', 'Resolving current workflow states', 'Opening Project Register'] },
            project: { icon: 'portfolio', title: 'Opening Customer Project', copy: 'Restoring the selected project identity and engineering workflow state.', steps: ['Retaining customer relationship context', 'Loading the project record', 'Resolving engineering and commercial milestones', 'Opening Project Cockpit'] },
            'project-create': { icon: 'create', title: 'Opening New Project', copy: 'Preparing a project record with the current customer relationship retained.', steps: ['Retaining customer identity', 'Preparing the project brief', 'Loading assignment and system controls', 'Opening Project Creation'] },
            filter: { icon: 'filter', title: 'Filtering Customer Register', copy: 'Applying identity, relationship type, lifecycle, and sorting controls.', steps: ['Reading active customer filters', 'Matching relationship records', 'Resolving project and portal summaries', 'Refreshing Customer Governance Register'] },
            save: { icon: 'save', title: meta.mode === 'create' ? 'Creating Customer Relationship' : 'Saving Customer Revision', copy: meta.mode === 'create' ? 'Generating the customer number and committing the controlled relationship record.' : 'Applying the controlled identity, contact, location, and portal ownership revision.', steps: ['Validating customer identity and classification', 'Checking portal ownership eligibility', 'Preserving project relationship boundaries', meta.mode === 'create' ? 'Creating customer record' : 'Committing customer revision'] },
            archive: { icon: 'verify', title: 'Archiving Customer Relationship', copy: 'Applying the controlled lifecycle transition while preserving projects, portal ownership, and historical records.', steps: ['Validating the archive gate', 'Protecting linked relationship history', 'Restricting new customer selection', 'Completing record retention'] },
        };
        const preset = presets[type] || presets.open;
        return {
            kind: type === 'filter' ? 'filter' : (type === 'save' ? 'save' : 'verify'),
            signal: 'NEXORIN customer governance', eyebrow: 'Controlled relationship orchestration',
            metaLeft: meta.left || customerName, metaRight: meta.right || companyName,
            duration: type === 'filter' ? 820 : 940, accent: type === 'archive' ? '#d45b53' : '#13aaa2', secondary: type === 'projects' || type === 'project' ? '#3978d8' : '#7659ca',
            ...preset,
        };
    };

    const navigate = (event, anchor, type) => actionEngine?.navigate(event, anchor, config(type)) || false;
    document.querySelectorAll('a[data-nxcus-nav]').forEach((anchor) => {
        anchor.addEventListener('click', (event) => navigate(event, anchor, anchor.dataset.nxcusNav || 'open'));
    });

    const filterForm = document.querySelector('[data-nxcus-filter-form]');
    if (filterForm) {
        filterForm.querySelectorAll('[data-nxcus-auto-submit]').forEach((control) => control.addEventListener('change', () => filterForm.requestSubmit()));
        filterForm.addEventListener('submit', () => actionEngine?.show(config('filter')));
    }
    document.querySelectorAll('.nxcus-registry .ui-pagination a').forEach((anchor) => anchor.addEventListener('click', (event) => navigate(event, anchor, 'filter')));

    const registry = document.querySelector('[data-nxcus-registry]');
    if (registry) {
        registry.querySelectorAll('[data-nxcus-row]').forEach((row) => {
            const openRow = (event) => {
                if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
                if (event.target.closest('a,button,input,select,textarea,form')) return;
                const href = row.dataset.href;
                if (!href) return;
                event.preventDefault();
                const anchor = document.createElement('a');
                anchor.href = href;
                const navigationEvent = event.type === 'keydown' ? { button: 0, defaultPrevented: false, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false, preventDefault() {} } : event;
                if (!actionEngine?.navigate(navigationEvent, anchor, config('open'))) window.location.assign(href);
            };
            row.addEventListener('click', openRow);
            row.addEventListener('keydown', openRow);
        });

        const closeMenus = (except = null) => {
            registry.querySelectorAll('[data-nxcus-menu]').forEach((menu) => {
                if (menu === except) return;
                menu.classList.remove('is-open', 'opens-up');
                menu.querySelector('[data-nxcus-menu-toggle]')?.setAttribute('aria-expanded', 'false');
                const panel = menu.querySelector('[data-nxcus-menu-popover]');
                if (panel) panel.hidden = true;
                menu.closest('[data-nxcus-row]')?.classList.remove('has-open-menu');
            });
        };

        registry.querySelectorAll('[data-nxcus-menu]').forEach((menu) => {
            const toggle = menu.querySelector('[data-nxcus-menu-toggle]');
            const panel = menu.querySelector('[data-nxcus-menu-popover]');
            toggle?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const opening = panel?.hidden ?? true;
                closeMenus(menu);
                if (!panel || !opening) return;
                panel.hidden = false;
                menu.classList.add('is-open');
                menu.closest('[data-nxcus-row]')?.classList.add('has-open-menu');
                toggle.setAttribute('aria-expanded', 'true');
                requestAnimationFrame(() => {
                    const triggerRect = toggle.getBoundingClientRect();
                    const panelRect = panel.getBoundingClientRect();
                    const registryRect = registry.getBoundingClientRect();
                    const lowerBoundary = Math.min(window.innerHeight - 12, registryRect.bottom + panelRect.height);
                    const upperBoundary = Math.max(12, registryRect.top);
                    const below = lowerBoundary - triggerRect.bottom - 8;
                    const above = triggerRect.top - upperBoundary - 8;
                    const opensUp = below < panelRect.height && above > below;
                    menu.classList.toggle('opens-up', opensUp);
                    panel.style.maxHeight = `${Math.max(150, Math.min(360, (opensUp ? above : below)))}px`;
                });
            });
        });
        document.addEventListener('click', () => closeMenus());
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenus(); });
    }

    document.querySelectorAll('[data-nxcus-archive-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (form.dataset.nxcusSubmitting === 'true') return;
            form.dataset.nxcusSubmitting = 'true';
            actionEngine?.show(config('archive'));
        });
    });

    const sectionNav = document.querySelector('[data-nxcus-section-nav]');
    const sections = Array.from(document.querySelectorAll('[data-nxcus-section]'));
    sectionNav?.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (event) => {
            const target = document.querySelector(anchor.hash);
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
        });
    });
    if (sectionNav && sections.length && 'IntersectionObserver' in window) {
        const links = new Map(Array.from(sectionNav.querySelectorAll('a[href^="#"]')).map((link) => [link.hash.slice(1), link]));
        const observer = new IntersectionObserver((entries) => {
            const current = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (!current) return;
            links.forEach((link, id) => link.classList.toggle('is-active', id === current.target.id));
        }, { rootMargin: '-24% 0px -66% 0px', threshold: [0.02, 0.18, 0.38] });
        sections.forEach((section) => observer.observe(section));
    }

    const form = document.querySelector('[data-nxcus-customer-form]');
    if (!form || !editor) return;

    const mode = editor.dataset.nxcusMode || 'edit';
    const nameInput = form.querySelector('[data-nxcus-name]');
    const emailInput = form.querySelector('[data-nxcus-email]');
    const phoneInput = form.querySelector('[data-nxcus-phone]');
    const cityInput = form.querySelector('[data-nxcus-city]');
    const provinceInput = form.querySelector('[data-nxcus-province]');
    const portalInput = form.querySelector('[data-nxcus-portal]');
    const submit = form.querySelector('[data-nxcus-submit]');
    const cancel = form.querySelector('[data-nxcus-cancel]');
    const initialSignature = new URLSearchParams(new FormData(form)).toString();
    const initialType = editor.dataset.initialType || form.querySelector('[data-nxcus-type]:checked')?.value || 'residential';
    const initialPortal = editor.dataset.initialPortal || portalInput?.value || '';
    let submitting = false;
    let approvedNavigation = false;
    let sensitiveApproved = false;

    const signature = () => new URLSearchParams(new FormData(form)).toString();
    const isDirty = () => signature() !== initialSignature;
    const currentType = () => form.querySelector('[data-nxcus-type]:checked')?.value || 'residential';
    const setText = (selector, text) => { const node = editor.querySelector(selector); if (node) node.textContent = text; };

    const formatPhonePreview = (value) => {
        const raw = String(value || '').trim();
        if (!raw) return 'Use country code and a readable customer-facing format.';
        const digits = raw.replace(/\D/g, '');
        if (digits.startsWith('62') && digits.length >= 10) {
            const local = digits.slice(2);
            const groups = local.match(/.{1,4}/g) || [];
            return `Normalized preview: +62 ${groups.join('-')}`;
        }
        return `Saved as entered: ${raw}`;
    };

    const update = () => {
        const name = String(nameInput?.value || '').trim();
        const email = String(emailInput?.value || '').trim();
        const phone = String(phoneInput?.value || '').trim();
        const city = String(cityInput?.value || '').trim();
        const province = String(provinceInput?.value || '').trim();
        const type = currentType();
        const portal = portalInput?.value || '';
        const selectedPortal = portalInput?.selectedOptions?.[0];
        const portalName = selectedPortal?.dataset.name || 'No portal account linked';
        const portalEmail = selectedPortal?.dataset.email || 'The customer remains internal until an eligible account is linked.';
        const identityReady = name !== '' && ['residential', 'commercial'].includes(type);
        const contactReady = Boolean(email || phone);
        const locationReady = Boolean(city || province || form.querySelector('[name="address"]')?.value.trim());
        const portalReviewed = true;
        const passed = [identityReady, contactReady, locationReady, portalReviewed].filter(Boolean).length;
        const readiness = passed * 25;
        const dirty = isDirty();

        form.querySelectorAll('[data-nxcus-type]').forEach((input) => input.closest('.nxcus-choice-card')?.classList.toggle('is-selected', input.checked));
        setText('[data-nxcus-preview-name]', name || 'New customer relationship');
        setText('[data-nxcus-preview-contact]', email || phone || 'Contact not assigned');
        setText('[data-nxcus-preview-type]', type === 'commercial' ? 'Commercial' : 'Residential');
        setText('[data-nxcus-preview-portal]', portal ? portalName : 'Not linked');
        setText('[data-nxcus-preview-location]', [city, province].filter(Boolean).join(' · ') || 'Not established');
        setText('[data-nxcus-preview-readiness]', `${readiness}%`);
        setText('[data-nxcus-preview-dirty]', dirty ? 'UNSAVED CHANGES' : (mode === 'create' ? 'NEW DRAFT' : 'SAVED BASELINE'));
        editor.querySelector('[data-nxcus-editor-preview]')?.classList.toggle('has-pending-changes', dirty);
        editor.querySelector('[data-nxcus-editor-preview]')?.classList.toggle('is-commercial', type === 'commercial');

        setText('[data-nxcus-portal-name]', portalName);
        setText('[data-nxcus-portal-email]', portalEmail);
        setText('[data-nxcus-portal-state]', portal ? 'LINKED ON SAVE' : 'INTERNAL ONLY');
        editor.querySelector('[data-nxcus-portal-preview]')?.classList.toggle('is-linked', Boolean(portal));
        setText('[data-nxcus-phone-preview]', formatPhonePreview(phone));

        const checks = { identity: identityReady, contact: contactReady, location: locationReady, portal: portalReviewed };
        Object.entries(checks).forEach(([key, ready]) => form.querySelector(`[data-nxcus-check="${key}"]`)?.classList.toggle('is-ready', ready));
        setText('[data-nxcus-readiness-score]', `${readiness}%`);
        setText('[data-nxcus-readiness-title]', identityReady ? (readiness === 100 ? 'Relationship record ready' : 'Required identity complete') : 'Identity required');
        setText('[data-nxcus-readiness-copy]', !identityReady ? 'Complete the customer name and classification before submission.' : (readiness === 100 ? 'Identity, contact, location, and portal decision are fully reviewed.' : 'The required identity is ready. Contact and location coverage can be improved before saving.'));
        const bar = form.querySelector('[data-nxcus-readiness-bar]');
        if (bar) bar.style.width = `${readiness}%`;
        setText('[data-nxcus-action-state]', dirty ? 'Changes pending' : (mode === 'create' ? 'New draft' : 'Saved baseline'));
        setText('[data-nxcus-action-copy]', dirty ? `${readiness}% relationship readiness · review and save the controlled record.` : (mode === 'create' ? 'Complete the required identity fields to create the customer record.' : 'Modify a field to prepare a controlled relationship revision.'));
        if (submit) submit.disabled = !identityReady || (mode === 'edit' && !dirty);
    };

    form.querySelectorAll('[data-nxcus-autogrow]').forEach((textarea) => {
        const resize = () => { textarea.style.height = 'auto'; textarea.style.height = `${Math.min(220, Math.max(92, textarea.scrollHeight))}px`; };
        textarea.addEventListener('input', resize); resize();
    });
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();

    const confirmDiscard = async () => {
        if (!isDirty() || approvedNavigation) return true;
        return Boolean(await window.NexorinConfirm?.ask?.('Leave this customer form and discard the unsaved relationship changes?', { tone: 'danger', title: 'Discard unsaved customer changes?', kicker: 'Relationship revision protection', confirmLabel: 'Discard changes', control: 'Unsaved identity, contact, location, or portal settings detected', result: 'Current form changes will be discarded' }));
    };

    cancel?.addEventListener('click', async (event) => {
        if (!isDirty()) return;
        event.preventDefault();
        if (await confirmDiscard()) { approvedNavigation = true; window.location.assign(cancel.href); }
    });
    document.addEventListener('click', async (event) => {
        const anchor = event.target.closest('a[href]');
        if (!anchor || anchor === cancel || approvedNavigation || !isDirty()) return;
        const url = new URL(anchor.href, window.location.href);
        if (url.origin !== window.location.origin || anchor.target === '_blank' || anchor.hasAttribute('download') || anchor.hash.startsWith('#')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (await confirmDiscard()) { approvedNavigation = true; window.location.assign(anchor.href); }
    }, true);
    window.addEventListener('beforeunload', (event) => {
        if (submitting || approvedNavigation || !isDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    });

    form.addEventListener('submit', async (event) => {
        if (submitting) return;
        const typeChanged = currentType() !== initialType;
        const portalChanged = String(portalInput?.value || '') !== String(initialPortal);
        if (mode === 'edit' && (typeChanged || portalChanged) && !sensitiveApproved) {
            event.preventDefault();
            const changes = [typeChanged ? 'customer classification' : '', portalChanged ? 'portal ownership' : ''].filter(Boolean).join(' and ');
            const approved = await window.NexorinConfirm?.ask?.(`Save this controlled customer revision affecting ${changes}?`, { tone: 'decision', title: 'Commit relationship governance changes?', kicker: 'Customer access and classification control', confirmLabel: 'Save controlled revision', control: `Sensitive changes: ${changes}`, result: 'Future project and portal relationship behavior will use the revised record' });
            if (!approved) return;
            sensitiveApproved = true;
            form.requestSubmit(submit);
            return;
        }
        submitting = true;
        submit?.setAttribute('aria-busy', 'true');
        actionEngine?.show(config('save', { mode }));
    });
})();
