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
        const directory = document.querySelector('[data-nxcg-directory]');
        const profile = document.querySelector('[data-nxcg-profile]');
        const editor = document.querySelector('[data-nxcg-editor]');
        if (!directory && !profile && !editor) return;

        const actionEngine = window.NexorinEngineeringAction;
        const confirmEngine = window.NexorinConfirm;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const companyName = profile?.dataset.nxcgCompanyName || editor?.dataset.nxcgCompanyName || 'Company workspace';
        const companyCode = profile?.dataset.nxcgCompanyCode || editor?.dataset.nxcgCompanyCode || 'Tenant context';

        const revealNodes = Array.from(document.querySelectorAll('[data-nxcg-reveal]'));
        revealNodes.forEach((node) => node.classList.add('nxcg-reveal'));
        if (reducedMotion || !('IntersectionObserver' in window)) {
            revealNodes.forEach((node) => node.classList.add('is-visible'));
        } else {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.05, rootMargin: '0px 0px -3% 0px' });
            revealNodes.forEach((node) => observer.observe(node));
        }

        document.querySelectorAll('[data-nxcg-card]').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                card.style.setProperty('--mouse-x', `${event.clientX - bounds.left}px`);
                card.style.setProperty('--mouse-y', `${event.clientY - bounds.top}px`);
            });
        });

        const navConfig = (kind, anchor) => {
            const rowName = anchor?.dataset?.nxcgCompany || companyName;
            const rowCode = anchor?.dataset?.nxcgCompanyCode || companyCode;
            const configs = {
                open: {
                    kind: 'open', icon: 'portfolio', title: 'Opening Company Administration',
                    copy: 'Tenant identity, membership visibility, workspace access, and governance controls are being restored.',
                    signal: 'NEXORIN platform administration', eyebrow: 'Company governance handoff',
                    metaLeft: rowCode, metaRight: rowName,
                    steps: ['Retaining platform administrator context', 'Loading tenant identity and membership records', 'Resolving workspace access and governance state', 'Opening Company Administration'],
                    duration: 940, accent: '#13aaa2', secondary: '#7659ca',
                },
                directory: {
                    kind: 'open', icon: 'portfolio', title: 'Opening Company Register',
                    copy: 'Registered tenants, membership coverage, and workspace access states are being resolved.',
                    signal: 'NEXORIN company governance register', eyebrow: 'Platform administration handoff',
                    metaLeft: 'Platform scope retained', metaRight: 'Tenant access preserved',
                    steps: ['Retaining administrator access context', 'Loading registered companies', 'Resolving membership and access states', 'Opening Company Governance Register'],
                    duration: 860, accent: '#13aaa2', secondary: '#7659ca',
                },
                create: {
                    kind: 'create', icon: 'portfolio', title: 'Opening Company Registration',
                    copy: 'The controlled tenant identity and initial workspace-access form are being prepared.',
                    signal: 'NEXORIN tenant provisioning', eyebrow: 'New company workspace handoff',
                    metaLeft: 'Platform administrator', metaRight: 'Isolated tenant context',
                    steps: ['Retaining platform administration context', 'Preparing tenant identity controls', 'Loading access-state governance', 'Opening Company Registration'],
                    duration: 900, accent: '#13aaa2', secondary: '#f3a04f',
                },
                edit: {
                    kind: 'edit', icon: 'portfolio', title: 'Opening Company Configuration',
                    copy: 'Saved tenant identity, contact information, and workspace access state are being restored for controlled revision.',
                    signal: 'NEXORIN company governance', eyebrow: 'Tenant configuration handoff',
                    metaLeft: rowCode, metaRight: rowName,
                    steps: ['Retaining current company context', 'Loading saved identity and contact values', 'Resolving workspace access state', 'Opening Company Configuration'],
                    duration: 920, accent: '#13aaa2', secondary: '#f3a04f',
                },
                projects: {
                    kind: 'open', icon: 'portfolio', title: 'Opening Company Projects',
                    copy: 'Company-scoped project records and engineering workflow states are being restored.',
                    signal: 'NEXORIN project command center', eyebrow: 'Company project handoff',
                    metaLeft: companyCode, metaRight: companyName,
                    steps: ['Retaining company access context', 'Loading visible project records', 'Resolving workflow milestones', 'Opening Company Projects'],
                    duration: 920, accent: '#13aaa2', secondary: '#3978d8',
                },
                customers: {
                    kind: 'open', icon: 'portfolio', title: 'Opening Company Customers',
                    copy: 'Customer identities and company-scoped project relationships are being restored.',
                    signal: 'NEXORIN customer registry', eyebrow: 'Company customer handoff',
                    metaLeft: companyCode, metaRight: companyName,
                    steps: ['Retaining company access context', 'Loading customer records', 'Resolving project relationships', 'Opening Company Customers'],
                    duration: 900, accent: '#13aaa2', secondary: '#7659ca',
                },
            };
            return configs[kind] || configs.open;
        };

        const navigate = (event, anchor, config) => actionEngine?.navigate(event, anchor, config) || false;
        document.addEventListener('click', (event) => {
            const anchor = event.target.closest('a[data-nxcg-navigate], .nxcg-registry-footer a');
            if (!anchor) return;
            navigate(event, anchor, navConfig(anchor.dataset.nxcgNavigate || (anchor.closest('.nxcg-registry-footer') ? 'directory' : 'open'), anchor));
        });

        document.querySelectorAll('[data-nxcg-row]').forEach((row) => {
            const open = () => row.querySelector('a[data-nxcg-navigate="open"]')?.click();
            row.addEventListener('click', (event) => {
                if (event.target.closest('a,button,input,select,textarea,form,details,summary')) return;
                open();
            });
            row.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                open();
            });
        });

        const closeActionMenu = (details) => {
            details.removeAttribute('open');
            details.classList.remove('is-drop-up', 'is-drop-down');
            details.style.removeProperty('--nxcg-action-menu-max-height');
            details.closest('.nxcg-company-row')?.classList.remove('is-menu-open');
        };

        const placeActionMenu = (details) => {
            const panel = details.querySelector(':scope > div');
            const summary = details.querySelector(':scope > summary');
            const registry = details.closest('.nxcg-registry');
            if (!panel || !summary || window.matchMedia('(max-width: 620px)').matches) return;

            details.classList.remove('is-drop-up', 'is-drop-down');
            panel.style.maxHeight = '';

            const summaryRect = summary.getBoundingClientRect();
            const registryRect = registry?.getBoundingClientRect();
            const lowerBoundary = Math.min(window.innerHeight - 12, registryRect?.bottom ?? window.innerHeight - 12);
            const upperBoundary = Math.max(12, registryRect?.top ?? 12);
            const panelHeight = Math.max(panel.scrollHeight, 88);
            const spaceBelow = Math.max(0, lowerBoundary - summaryRect.bottom - 8);
            const spaceAbove = Math.max(0, summaryRect.top - upperBoundary - 8);
            const openUp = spaceBelow < panelHeight && spaceAbove > spaceBelow;
            const available = Math.max(88, (openUp ? spaceAbove : spaceBelow));

            details.classList.add(openUp ? 'is-drop-up' : 'is-drop-down');
            details.style.setProperty('--nxcg-action-menu-max-height', `${available}px`);
        };

        document.addEventListener('click', (event) => {
            if (event.target.closest('.nxcg-action-menu')) return;
            document.querySelectorAll('.nxcg-action-menu[open]').forEach(closeActionMenu);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.nxcg-action-menu[open]').forEach(closeActionMenu);
        });
        document.querySelectorAll('.nxcg-action-menu').forEach((details) => {
            details.addEventListener('toggle', () => {
                const row = details.closest('.nxcg-company-row');
                if (!details.open) {
                    details.classList.remove('is-drop-up', 'is-drop-down');
                    details.style.removeProperty('--nxcg-action-menu-max-height');
                    row?.classList.remove('is-menu-open');
                    return;
                }

                document.querySelectorAll('.nxcg-action-menu[open]').forEach((candidate) => {
                    if (candidate !== details) closeActionMenu(candidate);
                });
                row?.classList.add('is-menu-open');
                window.requestAnimationFrame(() => placeActionMenu(details));
            });
        });
        window.addEventListener('resize', () => {
            document.querySelectorAll('.nxcg-action-menu[open]').forEach(placeActionMenu);
        }, { passive: true });

        const filterForm = document.querySelector('[data-nxcg-filter-form]');
        if (filterForm) {
            filterForm.querySelectorAll('[data-nxcg-auto-submit]').forEach((control) => {
                control.addEventListener('change', () => filterForm.requestSubmit());
            });
            filterForm.addEventListener('submit', () => {
                if (filterForm.dataset.nxcgSubmitting === 'true') return;
                filterForm.dataset.nxcgSubmitting = 'true';
                actionEngine?.show({
                    kind: 'filter', icon: 'filter', title: 'Filtering Company Register',
                    copy: 'Registered tenants are being matched against the active identity, access-state, and sorting controls.',
                    signal: 'NEXORIN company governance register', eyebrow: 'Tenant portfolio filter sequence',
                    metaLeft: 'Platform scope retained', metaRight: 'Access governance preserved',
                    steps: ['Reading active company filters', 'Matching registered tenant records', 'Resolving membership and access states', 'Refreshing Company Governance Register'],
                    duration: 820, accent: '#13aaa2', secondary: '#7659ca',
                });
            });
        }

        const sectionNav = document.querySelector('[data-nxcg-section-nav]');
        const sections = Array.from(document.querySelectorAll('[data-nxcg-section]'));
        document.querySelectorAll('[data-nxcg-scroll]').forEach((anchor) => {
            anchor.addEventListener('click', (event) => {
                const target = document.querySelector(anchor.getAttribute('href'));
                if (!target) return;
                event.preventDefault();
                target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
            });
        });
        if (sectionNav && sections.length && 'IntersectionObserver' in window) {
            const links = new Map(Array.from(sectionNav.querySelectorAll('a[href^="#"]')).map((link) => [link.hash.slice(1), link]));
            const sectionObserver = new IntersectionObserver((entries) => {
                const current = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                if (!current) return;
                links.forEach((link, id) => link.classList.toggle('is-active', id === current.target.id));
            }, { rootMargin: '-22% 0px -66% 0px', threshold: [0.02, 0.16, 0.35] });
            sections.forEach((section) => sectionObserver.observe(section));
        }

        const memberList = document.querySelector('[data-nxcg-member-list]');
        if (memberList) {
            const items = Array.from(memberList.querySelectorAll('[data-nxcg-member]'));
            const search = document.querySelector('[data-nxcg-member-search]');
            const status = document.querySelector('[data-nxcg-member-status]');
            const role = document.querySelector('[data-nxcg-member-role]');
            const count = document.querySelector('[data-nxcg-member-count]');
            const empty = document.querySelector('[data-nxcg-member-empty]');
            const apply = () => {
                const query = String(search?.value || '').trim().toLowerCase();
                const statusValue = status?.value || 'all';
                const roleValue = role?.value || 'all';
                let visible = 0;
                items.forEach((item) => {
                    const matchesSearch = !query || item.dataset.memberSearch.includes(query);
                    const matchesStatus = statusValue === 'all' || item.dataset.memberStatus === statusValue;
                    const roles = String(item.dataset.memberRoles || '').split(/\s+/).filter(Boolean);
                    const matchesRole = roleValue === 'all' || roles.includes(roleValue);
                    const show = matchesSearch && matchesStatus && matchesRole;
                    item.hidden = !show;
                    if (show) visible += 1;
                });
                if (count) count.textContent = `${visible} visible`;
                if (empty) empty.hidden = visible !== 0;
            };
            search?.addEventListener('input', apply);
            status?.addEventListener('change', apply);
            role?.addEventListener('change', apply);
        }

        const statusActionConfig = (form) => {
            const targetStatus = form.querySelector('[name="status"]')?.value;
            const activating = targetStatus === 'active';
            return {
                kind: activating ? 'verify' : 'save', icon: 'auth',
                title: activating ? 'Activating Company Workspace' : 'Restricting Company Workspace',
                copy: activating
                    ? 'Company-scoped access is being restored while existing role and permission boundaries remain enforced.'
                    : 'Company-scoped access is being disabled while tenant records and historical context remain preserved.',
                signal: 'NEXORIN tenant access control', eyebrow: 'Company lifecycle transition',
                metaLeft: companyCode, metaRight: companyName,
                steps: activating
                    ? ['Validating the company record', 'Restoring workspace access state', 'Retaining membership permission boundaries', 'Completing company activation']
                    : ['Validating the company record', 'Restricting workspace access state', 'Preserving tenant records and history', 'Completing company deactivation'],
                duration: 980, accent: activating ? '#13aaa2' : '#f3a04f', secondary: activating ? '#3978d8' : '#cf514a',
            };
        };

        document.querySelectorAll('[data-nxcg-status-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                if (form.dataset.nxcgSubmitting === 'true') return;
                form.dataset.nxcgSubmitting = 'true';
                actionEngine?.show(statusActionConfig(form));
            });
        });
        document.querySelectorAll('[data-nxcg-archive-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                if (form.dataset.nxcgSubmitting === 'true') return;
                form.dataset.nxcgSubmitting = 'true';
                actionEngine?.show({
                    kind: 'save', icon: 'auth', title: 'Archiving Company Record',
                    copy: 'The tenant is being removed from active administration views while database records and historical context remain preserved.',
                    signal: 'NEXORIN record retention control', eyebrow: 'Company archive sequence',
                    metaLeft: companyCode, metaRight: companyName,
                    steps: ['Validating inactive company state', 'Preserving tenant relationships and history', 'Applying soft-delete archive state', 'Returning to Company Governance Register'],
                    duration: 1000, accent: '#cf514a', secondary: '#f3a04f',
                });
            });
        });

        const editorForm = document.querySelector('[data-nxcg-editor-form]');
        if (editorForm) {
            const mode = editor?.dataset.nxcgMode || 'edit';
            const fields = {
                name: editorForm.querySelector('[data-nxcg-preview-name]'),
                code: editorForm.querySelector('[data-nxcg-preview-code]'),
                email: editorForm.querySelector('[data-nxcg-preview-email]'),
                phone: editorForm.querySelector('[data-nxcg-preview-phone]'),
                address: editorForm.querySelector('[data-nxcg-preview-address]'),
            };
            const outputs = {
                name: editorForm.querySelector('[data-nxcg-preview-name-output]'),
                code: editorForm.querySelector('[data-nxcg-preview-code-output]'),
                status: editorForm.querySelector('[data-nxcg-preview-status-output]'),
                contact: editorForm.querySelector('[data-nxcg-preview-contact-output]'),
                dirty: editorForm.querySelector('[data-nxcg-dirty-output]'),
            };
            const identityState = editorForm.querySelector('[data-nxcg-identity-state]');
            const contactState = editorForm.querySelector('[data-nxcg-contact-state]');
            const accessState = editorForm.querySelector('[data-nxcg-access-state]');
            const codeImpact = editorForm.querySelector('[data-nxcg-code-impact]');
            const statusImpactTitle = editorForm.querySelector('[data-nxcg-status-impact-title]');
            const statusImpactCopy = editorForm.querySelector('[data-nxcg-status-impact-copy]');
            const actionBar = editorForm.querySelector('[data-nxcg-editor-actions]');
            const actionState = editorForm.querySelector('[data-nxcg-action-state]');
            const actionCopy = editorForm.querySelector('[data-nxcg-action-copy]');
            const submitButton = editorForm.querySelector('[data-nxcg-submit]');
            const initialData = new FormData(editorForm);
            let submitting = false;
            let navigationApproved = false;

            const normalize = (data) => [...data.entries()]
                .filter(([key]) => key !== '_token' && key !== '_method')
                .map(([key, value]) => `${key}:${String(value)}`)
                .sort()
                .join('|');
            const initialSignature = normalize(initialData);
            const hasChanged = () => normalize(new FormData(editorForm)) !== initialSignature;
            const selectedStatus = () => editorForm.querySelector('[name="status"]:checked')?.value || 'active';

            const update = () => {
                if (fields.code) {
                    const caret = fields.code.selectionStart;
                    const next = fields.code.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
                    if (next !== fields.code.value) {
                        fields.code.value = next;
                        try { fields.code.setSelectionRange(caret, caret); } catch (_) { /* no-op */ }
                    }
                }

                const name = String(fields.name?.value || '').trim();
                const code = String(fields.code?.value || '').trim();
                const status = selectedStatus();
                const contactCount = [fields.email?.value, fields.phone?.value, fields.address?.value].filter((value) => String(value || '').trim() !== '').length;
                const dirty = hasChanged();

                if (outputs.name) outputs.name.textContent = name || 'New company workspace';
                if (outputs.code) outputs.code.textContent = code || 'NOT ASSIGNED';
                if (outputs.status) outputs.status.textContent = status === 'active' ? 'Active' : 'Inactive';
                if (outputs.contact) outputs.contact.textContent = contactCount ? `${contactCount}/3 provided` : 'Not provided';
                if (outputs.dirty) outputs.dirty.textContent = dirty ? 'Changes pending' : (mode === 'create' ? 'New draft' : 'Saved baseline');

                if (identityState) {
                    const readyIdentity = name !== '' && code !== '';
                    identityState.textContent = readyIdentity ? 'Identity ready' : 'Identity required';
                    identityState.classList.toggle('is-ready', readyIdentity);
                }
                if (contactState) {
                    contactState.textContent = contactCount ? `${contactCount}/3 contact fields` : 'Optional profile data';
                    contactState.classList.toggle('is-ready', contactCount > 0);
                }
                if (accessState) {
                    accessState.textContent = status.toUpperCase();
                    accessState.classList.toggle('is-warning', status === 'inactive');
                }
                editorForm.querySelectorAll('[data-nxcg-status-selector] label').forEach((label) => {
                    label.classList.toggle('is-selected', label.querySelector('input')?.checked === true);
                });

                if (statusImpactTitle) statusImpactTitle.textContent = status === 'active' ? 'Workspace access will be available' : 'Workspace access will be restricted';
                if (statusImpactCopy) statusImpactCopy.textContent = status === 'active'
                    ? 'Active memberships can enter company-scoped workflows according to their assigned roles.'
                    : 'Members will lose company-scoped access until the company is activated again.';

                if (codeImpact) {
                    const originalCode = fields.code?.dataset.nxcgOriginalCode || '';
                    codeImpact.hidden = mode !== 'edit' || code === originalCode;
                }

                if (actionBar) {
                    actionBar.classList.toggle('is-dirty', dirty);
                    actionBar.classList.toggle('is-ready', !dirty && mode === 'edit');
                }
                if (actionState) actionState.textContent = dirty ? 'Unsaved company changes' : (mode === 'create' ? 'New tenant draft' : 'No unsaved changes');
                if (actionCopy) actionCopy.textContent = dirty
                    ? 'Save or discard the current tenant configuration before leaving this page.'
                    : (mode === 'create' ? 'Complete the required identity fields to create the company workspace.' : 'The current form matches the saved company record.');
                if (submitButton) submitButton.disabled = name === '' || code === '';
            };

            editorForm.addEventListener('input', update);
            editorForm.addEventListener('change', update);
            update();

            editorForm.addEventListener('submit', () => {
                submitting = true;
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.setAttribute('aria-busy', 'true');
                }
                actionEngine?.show({
                    kind: mode === 'create' ? 'create' : 'save', icon: 'portfolio',
                    title: mode === 'create' ? 'Creating Company Workspace' : 'Saving Company Configuration',
                    copy: mode === 'create'
                        ? 'The tenant identity, contact record, and initial workspace-access state are being validated and registered.'
                        : 'The revised tenant identity, contact record, and workspace-access state are being validated and committed.',
                    signal: 'NEXORIN company governance', eyebrow: mode === 'create' ? 'Tenant provisioning sequence' : 'Tenant configuration sequence',
                    metaLeft: String(fields.code?.value || companyCode), metaRight: String(fields.name?.value || companyName),
                    steps: mode === 'create'
                        ? ['Validating company identity and uniqueness', 'Registering contact and legal references', 'Applying initial workspace-access state', 'Opening Company Administration']
                        : ['Validating revised company values', 'Updating identity and contact records', 'Applying workspace-access configuration', 'Opening Company Administration'],
                    duration: 1060, accent: '#13aaa2', secondary: '#f3a04f',
                });
            });

            window.addEventListener('beforeunload', (event) => {
                if (submitting || navigationApproved || !hasChanged()) return;
                event.preventDefault();
                event.returnValue = '';
            });

            editor.querySelectorAll('a[href]').forEach((link) => {
                link.addEventListener('click', async (event) => {
                    const href = link.getAttribute('href') || '';
                    if (href.startsWith('#') || link.target === '_blank' || submitting || !hasChanged()) return;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    const approved = await confirmEngine?.ask?.('Leave this company form? The unsaved tenant configuration will be discarded.', {
                        tone: 'danger', title: 'Discard unsaved company changes?', confirmLabel: 'Leave page',
                        control: 'Unsaved tenant configuration detected', result: 'Current company changes will be discarded',
                    });
                    if (!approved) return;
                    navigationApproved = true;
                    const config = navConfig(link.dataset.nxcgNavigate || 'directory', link);
                    const handoff = actionEngine?.show(config);
                    window.setTimeout(() => window.location.assign(link.href), handoff?.duration || 0);
                }, true);
            });
        }

        window.addEventListener('pageshow', () => {
            actionEngine?.remove?.();
            document.querySelectorAll('[data-nxcg-submitting]').forEach((node) => delete node.dataset.nxcgSubmitting);
            document.querySelectorAll('[aria-busy="true"]').forEach((button) => {
                button.removeAttribute('aria-busy');
                button.disabled = false;
            });
        });
    });
})();
