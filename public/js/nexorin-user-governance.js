(() => {
    'use strict';

    const actionEngine = window.NexorinEngineeringAction;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const profile = document.querySelector('[data-nxug-profile]');
    const userName = profile?.dataset.userName || document.querySelector('[data-nxug-editor]')?.dataset.userName || 'User account';

    const actionConfig = (type, meta = {}) => {
        const configs = {
            registry: { icon: 'auth', title: 'Opening Identity Governance Register', copy: 'Loading account lifecycle, security and access relationships.', steps: ['Validating platform administration access', 'Loading identity and security records', 'Resolving company membership context', 'Opening the governance register'] },
            create: { icon: 'auth', title: 'Opening User Provisioning', copy: 'Preparing a controlled identity, credential and access configuration workspace.', steps: ['Validating administrator authority', 'Loading company and role options', 'Preparing security policy controls', 'Opening the user provisioning workspace'] },
            open: { icon: 'auth', title: 'Opening User Access Control', copy: 'Loading identity, security posture, platform privilege and company memberships.', steps: ['Loading the identity record', 'Resolving security control state', 'Assembling effective access context', 'Opening the user access cockpit'] },
            edit: { icon: 'edit', title: 'Opening User Revision', copy: 'Preparing a controlled identity, security and access configuration session.', steps: ['Loading the current account baseline', 'Resolving platform privilege state', 'Loading company membership assignments', 'Opening the controlled editor'] },
            filter: { icon: 'filter', title: 'Filtering Identity Register', copy: 'Applying the selected account, company, role and lifecycle criteria.', steps: ['Validating filter criteria', 'Querying governed identity records', 'Rebuilding access-state summaries', 'Refreshing the user register'] },
            save: { icon: 'save', title: meta.mode === 'create' ? 'Creating User Account' : 'Saving User Revision', copy: meta.mode === 'create' ? 'Provisioning the identity, credential, privilege and company access record.' : 'Applying the controlled identity, credential, privilege and membership changes.', steps: ['Validating identity and credential policy', 'Checking lifecycle and privilege safeguards', 'Synchronizing company memberships and roles', meta.mode === 'create' ? 'Completing account provisioning' : 'Committing the governed revision'] },
            status: { icon: 'auth', title: meta.activating ? 'Activating User Account' : 'Deactivating User Account', copy: meta.activating ? 'Restoring sign-in and assigned workspace access under retained role boundaries.' : 'Blocking sign-in and authenticated requests while retaining access relationships and history.', steps: ['Validating the account lifecycle request', meta.activating ? 'Restoring authentication state' : 'Applying authentication restriction', 'Preserving membership and role relationships', 'Completing the lifecycle transition'] },
        };
        const config = configs[type] || configs.open;
        return { kind: type === 'save' ? 'save' : (type === 'filter' ? 'filter' : 'verify'), signal: 'NEXORIN identity governance', eyebrow: 'Controlled access orchestration', metaLeft: meta.left || userName, metaRight: meta.right || 'Access boundaries preserved', duration: type === 'filter' ? 820 : 980, accent: type === 'status' && !meta.activating ? '#d4675e' : '#13aaa2', secondary: '#3978d8', ...config };
    };

    document.querySelectorAll('[data-nxug-nav]').forEach((link) => {
        link.addEventListener('click', (event) => actionEngine?.navigate(event, link, actionConfig(link.dataset.nxugNav || 'open')));
    });

    const filterForm = document.querySelector('[data-nxug-filter-form]');
    filterForm?.addEventListener('submit', () => actionEngine?.show(actionConfig('filter')));
    document.querySelectorAll('.nxug-registry .ui-pagination a').forEach((link) => {
        link.addEventListener('click', (event) => actionEngine?.navigate(event, link, actionConfig('filter')));
    });

    const registry = document.querySelector('[data-nxug-registry]');
    if (registry) {
        registry.querySelectorAll('[data-nxug-row]').forEach((row) => {
            const navigate = (event) => {
                if (event.target.closest('a, button, form, input, select')) return;
                const href = row.dataset.href;
                if (!href) return;
                if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                const anchor = document.createElement('a'); anchor.href = href;
                actionEngine?.navigate({ ...event, button: 0, defaultPrevented: false, preventDefault() {} }, anchor, actionConfig('open')) || window.location.assign(href);
            };
            row.addEventListener('click', navigate);
            row.addEventListener('keydown', navigate);
        });

        const closeMenus = (except = null) => {
            registry.querySelectorAll('[data-nxug-menu]').forEach((menu) => {
                if (menu === except) return;
                menu.classList.remove('is-open', 'opens-up');
                menu.querySelector('[data-nxug-menu-toggle]')?.setAttribute('aria-expanded', 'false');
                const popover = menu.querySelector('[data-nxug-menu-popover]');
                if (popover) popover.hidden = true;
                menu.closest('[data-nxug-row]')?.classList.remove('has-open-menu');
            });
        };

        registry.querySelectorAll('[data-nxug-menu]').forEach((menu) => {
            const toggle = menu.querySelector('[data-nxug-menu-toggle]');
            const popover = menu.querySelector('[data-nxug-menu-popover]');
            toggle?.addEventListener('click', (event) => {
                event.stopPropagation();
                const opening = popover?.hidden ?? true;
                closeMenus(menu);
                if (!popover || !opening) { if (popover) popover.hidden = true; return; }
                popover.hidden = false;
                menu.classList.add('is-open');
                menu.closest('[data-nxug-row]')?.classList.add('has-open-menu');
                toggle.setAttribute('aria-expanded', 'true');
                requestAnimationFrame(() => {
                    const menuRect = menu.getBoundingClientRect();
                    const popRect = popover.getBoundingClientRect();
                    const shellRect = registry.getBoundingClientRect();
                    const below = Math.min(window.innerHeight, shellRect.bottom) - menuRect.bottom;
                    const above = menuRect.top - Math.max(0, shellRect.top);
                    const opensUp = below < popRect.height + 16 && above > below;
                    menu.classList.toggle('opens-up', opensUp);
                    const available = Math.max(160, (opensUp ? above : below) - 16);
                    popover.style.maxHeight = `${Math.min(360, available)}px`;
                });
            });
        });
        document.addEventListener('click', () => closeMenus());
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenus(); });
    }

    document.querySelectorAll('[data-nxug-status-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (form.dataset.nxugSubmitting === 'true') return;
            form.dataset.nxugSubmitting = 'true';
            actionEngine?.show(actionConfig('status', { activating: form.querySelector('[name="status"]')?.value === 'active' }));
        });
    });

    const sectionNav = document.querySelector('[data-nxug-section-nav]');
    const sections = Array.from(document.querySelectorAll('[data-nxug-section]'));
    sectionNav?.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = document.querySelector(link.hash);
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
        }, { rootMargin: '-22% 0px -68% 0px', threshold: [0.03, 0.18, 0.4] });
        sections.forEach((section) => observer.observe(section));
    }

    const editor = document.querySelector('[data-nxug-editor]');
    if (!editor) return;

    const mode = editor.dataset.nxugMode || 'edit';
    const nameInput = editor.querySelector('[data-nxug-name]');
    const emailInput = editor.querySelector('[data-nxug-email]');
    const passwordInput = editor.querySelector('[data-nxug-password]');
    const confirmInput = editor.querySelector('[data-nxug-password-confirm]');
    const superInput = editor.querySelector('[data-nxug-super-admin]');
    const memberContainer = editor.querySelector('[data-membership-container]');
    const memberTemplate = editor.querySelector('[data-membership-template]');
    const addMember = editor.querySelector('[data-add-membership]');
    const submit = editor.querySelector('[data-nxug-submit]');
    const cancel = editor.querySelector('[data-nxug-cancel]');
    const initialSignature = new URLSearchParams(new FormData(editor)).toString();
    let submitting = false;
    let approvedNavigation = false;

    const signature = () => new URLSearchParams(new FormData(editor)).toString();
    const isDirty = () => signature() !== initialSignature;
    const currentStatus = () => editor.querySelector('[name="status"]:checked')?.value || editor.querySelector('[name="status"]')?.value || 'active';
    const passwordRules = () => {
        const value = passwordInput?.value || '';
        return { length: value.length >= 10, upper: /[A-Z]/.test(value), lower: /[a-z]/.test(value), number: /\d/.test(value), symbol: /[^A-Za-z0-9]/.test(value), match: value !== '' && value === (confirmInput?.value || '') };
    };
    const validCredential = () => mode === 'edit' && !(passwordInput?.value || '') ? true : Object.values(passwordRules()).every(Boolean);

    const updateMemberRows = () => {
        const rows = Array.from(memberContainer?.querySelectorAll('[data-membership-row]') || []);
        rows.forEach((row, index) => {
            const number = row.querySelector('.nxug-membership-editor-head > div > span, [data-membership-number]');
            if (number) number.textContent = String(index + 1).padStart(2, '0');
            const company = row.querySelector('[data-membership-company]');
            const role = row.querySelector('[data-membership-role]');
            const status = row.querySelector('[data-membership-status]');
            const summary = row.querySelector('[data-nxug-membership-summary]');
            if (summary) summary.textContent = `${company?.selectedOptions[0]?.textContent?.trim() || 'Company not selected'} · ${role?.selectedOptions[0]?.textContent?.trim() || 'Role not selected'} · ${status?.value || 'active'}`;
        });
        const output = editor.querySelector('[data-nxug-preview-memberships]'); if (output) output.textContent = String(rows.filter((row) => row.querySelector('[data-membership-company]')?.value).length);
    };

    const bindMemberRow = (row) => {
        row.querySelector('[data-remove-membership]')?.addEventListener('click', () => { row.remove(); updateMemberRows(); update(); });
        row.querySelectorAll('select').forEach((select) => select.addEventListener('change', () => { updateMemberRows(); update(); }));
    };
    memberContainer?.querySelectorAll('[data-membership-row]').forEach(bindMemberRow);
    addMember?.addEventListener('click', () => {
        if (!memberTemplate || !memberContainer) return;
        const index = Number(memberContainer.dataset.nextIndex || 0);
        const wrapper = document.createElement('div'); wrapper.innerHTML = memberTemplate.innerHTML.replaceAll('__INDEX__', String(index));
        const row = wrapper.firstElementChild; if (!row) return;
        memberContainer.appendChild(row); memberContainer.dataset.nextIndex = String(index + 1); bindMemberRow(row); updateMemberRows(); update(); row.querySelector('select')?.focus();
    });

    editor.querySelectorAll('[data-nxug-password-toggle]').forEach((button) => button.addEventListener('click', () => {
        if (!passwordInput) return;
        const show = passwordInput.type === 'password'; passwordInput.type = show ? 'text' : 'password'; button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    }));

    const update = () => {
        const name = String(nameInput?.value || '').trim();
        const email = String(emailInput?.value || '').trim();
        const superAdmin = Boolean(superInput?.checked) || editor.querySelector('input[type="hidden"][name="is_super_admin"][value="1"]') !== null;
        const status = currentStatus();
        const rules = passwordRules();
        const dirty = isDirty();
        const memberships = Array.from(memberContainer?.querySelectorAll('[data-membership-row]') || []);
        const configured = memberships.filter((row) => row.querySelector('[data-membership-company]')?.value && row.querySelector('[data-membership-role]')?.value && row.querySelector('[data-membership-status]')?.value === 'active').length;
        const identityReady = name !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        const credentialReady = validCredential();
        const accessReady = ['active', 'inactive'].includes(status);
        const selectedCompanies = memberships.map((row) => row.querySelector('[data-membership-company]')?.value).filter(Boolean);
        const noDuplicateCompanies = new Set(selectedCompanies).size === selectedCompanies.length;
        memberships.forEach((row) => {
            const companyId = row.querySelector('[data-membership-company]')?.value;
            const duplicate = Boolean(companyId) && selectedCompanies.filter((value) => value === companyId).length > 1;
            row.classList.toggle('is-duplicate', duplicate);
            const summary = row.querySelector('[data-nxug-membership-summary]');
            if (duplicate && summary) summary.textContent = 'Duplicate company assignment · select a different workspace.';
        });
        const membershipReviewed = noDuplicateCompanies && (memberships.length === 0 || configured === memberships.length || (memberships.length === 1 && !memberships[0].querySelector('[data-membership-company]')?.value && !memberships[0].querySelector('[data-membership-role]')?.value));
        const passed = [identityReady, credentialReady, accessReady, membershipReviewed].filter(Boolean).length;
        const score = Math.round((passed / 4) * 100);

        const setText = (selector, value) => { const node = editor.querySelector(selector); if (node) node.textContent = value; };
        setText('[data-nxug-preview-name]', name || 'New user account');
        setText('[data-nxug-preview-email]', email || 'Email not assigned');
        setText('[data-nxug-preview-status]', status[0].toUpperCase() + status.slice(1));
        setText('[data-nxug-preview-privilege]', superAdmin ? 'Super Admin' : 'Company-scoped');
        setText('[data-nxug-preview-memberships]', `${configured} active`);
        setText('[data-nxug-preview-dirty]', dirty ? 'UNSAVED CHANGES' : (mode === 'create' ? 'NEW DRAFT' : 'SAVED BASELINE'));
        editor.querySelector('[data-nxug-editor-preview]')?.classList.toggle('is-super', superAdmin);
        editor.querySelector('[data-nxug-editor-preview]')?.classList.toggle('has-pending-changes', dirty);
        setText('[data-nxug-identity-state]', identityReady ? 'Identity ready' : 'Identity required'); setText('[data-nxug-security-state]', credentialReady ? (mode === 'edit' && !(passwordInput?.value || '') ? 'Credential retained' : 'Policy satisfied') : 'Credential incomplete'); setText('[data-nxug-access-state]', superAdmin ? 'Platform-wide' : 'Company-scoped');
        setText('[data-nxug-readiness-score]', `${score}%`); setText('[data-nxug-readiness-copy]', score === 100 ? 'The access record is ready for controlled submission.' : 'Complete the remaining identity, credential or membership controls.');
        const bar = editor.querySelector('[data-nxug-readiness-bar]'); if (bar) bar.style.width = `${score}%`;
        const checks = { identity: identityReady, credential: credentialReady, access: accessReady, membership: membershipReviewed };
        Object.entries(checks).forEach(([key, ready]) => editor.querySelector(`[data-nxug-check="${key}"]`)?.classList.toggle('is-ready', ready));
        Object.entries(rules).forEach(([key, ready]) => editor.querySelector(`[data-rule="${key}"]`)?.classList.toggle('is-ready', ready));
        setText('[data-nxug-dirty-state]', dirty ? 'Changes pending' : (mode === 'create' ? 'New draft' : 'Saved baseline'));
        setText('[data-nxug-action-state]', dirty ? 'Controlled changes pending' : (mode === 'create' ? 'New account draft' : 'No pending changes'));
        setText('[data-nxug-action-copy]', dirty ? `${score}% ready · review and save the controlled access revision.` : (mode === 'create' ? 'Complete the required fields before creating the account.' : 'Modify a field to prepare a controlled user revision.'));
        setText('[data-nxug-impact-title]', superAdmin ? 'Platform-wide privilege selected' : (status === 'inactive' ? 'Account access will be restricted' : (mode === 'create' ? 'New account provisioning' : 'Review pending changes')));
        setText('[data-nxug-impact-copy]', superAdmin ? 'Saving grants unrestricted administration across every NEXORIN company and requires explicit confirmation.' : (status === 'inactive' ? 'Saving blocks sign-in and authenticated requests while retaining memberships and roles.' : 'Sensitive lifecycle, privilege and password changes are protected by controlled confirmation.'));
        editor.querySelector('.nxug-super-admin-card')?.classList.toggle('is-selected', superAdmin);
        editor.querySelectorAll('.nxug-choice-card').forEach((card) => card.classList.toggle('is-selected', card.querySelector('input')?.checked));
        if (submit) submit.disabled = !identityReady || !credentialReady || !accessReady || !membershipReviewed || (mode === 'edit' && !dirty);
    };

    editor.addEventListener('input', update); editor.addEventListener('change', update);
    updateMemberRows(); update();

    cancel?.addEventListener('click', async (event) => {
        if (!isDirty() || approvedNavigation) return;
        event.preventDefault();
        const approved = await window.NexorinConfirm?.ask?.('Leave this user form and discard the unsaved access changes?', { tone: 'danger', title: 'Discard unsaved user changes?', kicker: 'Identity revision protection', confirmLabel: 'Discard changes', control: 'Unsaved identity or access settings detected', result: 'Current form changes will be discarded' });
        if (approved) { approvedNavigation = true; window.location.assign(cancel.href); }
    });
    document.addEventListener('click', async (event) => {
        const link = event.target.closest('a[href]');
        if (!link || link === cancel || approvedNavigation || !isDirty()) return;
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || link.target === '_blank' || link.hasAttribute('download') || link.hash?.startsWith('#')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const approved = await window.NexorinConfirm?.ask?.('Leave this user form and discard the unsaved access changes?', { tone: 'danger', title: 'Discard unsaved user changes?', kicker: 'Identity revision protection', confirmLabel: 'Discard changes', control: 'Unsaved identity or access settings detected', result: 'Current form changes will be discarded' });
        if (approved) { approvedNavigation = true; window.location.assign(link.href); }
    }, true);
    window.addEventListener('beforeunload', (event) => { if (submitting || approvedNavigation || !isDirty()) return; event.preventDefault(); event.returnValue = ''; });

    editor.addEventListener('submit', async (event) => {
        if (submitting) return;
        const superChanged = Boolean(superInput?.checked) !== Boolean(superInput?.defaultChecked);
        const statusChanged = currentStatus() !== (editor.dataset.initialStatus || currentStatus());
        const passwordChanged = Boolean(passwordInput?.value);
        if ((superChanged || statusChanged || passwordChanged) && editor.dataset.nxugSensitiveApproved !== 'true') {
            event.preventDefault();
            const impacts = [superChanged ? 'platform privilege' : '', statusChanged ? 'account lifecycle' : '', passwordChanged ? 'credential' : ''].filter(Boolean).join(', ');
            const approved = await window.NexorinConfirm?.ask?.(`Save this controlled user revision affecting ${impacts}?`, { tone: superChanged || statusChanged ? 'danger' : 'decision', title: 'Commit sensitive access changes?', kicker: 'Identity governance approval', confirmLabel: 'Save controlled revision', control: `Sensitive changes: ${impacts}`, result: 'Identity, security and membership settings will be updated' });
            if (!approved) return;
            editor.dataset.nxugSensitiveApproved = 'true';
            editor.requestSubmit(submit);
            return;
        }
        submitting = true;
        submit?.setAttribute('aria-busy', 'true');
        actionEngine?.show(actionConfig('save', { mode }));
    });
})();
