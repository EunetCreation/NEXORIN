(() => {
    'use strict';

    const root = document.querySelector('.nxmc-directory, .nxmc-record, .nxmc-editor');
    if (!root) return;

    const actionEngine = window.NexorinEngineeringAction;
    const showAction = (config) => actionEngine?.show(config);
    const navigate = (event, anchor, config) => actionEngine?.navigate(event, anchor, config) || false;

    const revealNodes = [...document.querySelectorAll('.nxmc-reveal')];
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.08 });
        revealNodes.forEach((node) => observer.observe(node));
    } else {
        revealNodes.forEach((node) => node.classList.add('is-visible'));
    }

    const configForLink = (anchor) => {
        const kind = anchor.dataset.nxmcNavigate || 'open';
        const material = anchor.dataset.nxmcMaterial || document.querySelector('[data-nxmc-material-name]')?.textContent?.trim() || 'Material catalog';
        const sku = anchor.dataset.nxmcSku || document.querySelector('[data-nxmc-material-sku]')?.textContent?.trim() || 'Catalog context';
        const configs = {
            open: {
                kind: 'open', icon: 'equipment', title: 'Opening Material Intelligence Record',
                copy: 'Identity, technical specifications, datasheet evidence, price references, and catalog governance are being assembled.',
                signal: 'NEXORIN material intelligence', eyebrow: 'Catalog evidence handoff', metaLeft: sku, metaRight: material,
                steps: ['Retaining catalog ownership and access context', 'Loading technical and applicability evidence', 'Resolving datasheet, price, and compliance state', 'Opening the material intelligence record'],
                duration: 980, accent: '#13aaa2', secondary: '#3978d8',
            },
            directory: {
                kind: 'open', icon: 'portfolio', title: 'Opening Material Catalog',
                copy: 'Visible materials, catalog scope, supplier links, and governance states are being restored.',
                signal: 'NEXORIN catalog registry', eyebrow: 'Material portfolio handoff', metaLeft: 'Company scope retained', metaRight: 'Catalog visibility preserved',
                steps: ['Retaining company and access context', 'Loading visible material records', 'Resolving price and governance coverage', 'Opening the material catalog'],
                duration: 900, accent: '#13aaa2', secondary: '#7659ca',
            },
            create: {
                kind: 'open', icon: 'equipment', title: 'Opening Material Intake',
                copy: 'NEXORIN is preparing a controlled company material record with engineering and governance context.',
                signal: 'NEXORIN catalog intake', eyebrow: 'Material creation sequence', metaLeft: 'Company catalog', metaRight: 'Draft governance enabled',
                steps: ['Retaining company ownership context', 'Loading category and applicability controls', 'Preparing technical input governance', 'Opening material intake'],
                duration: 900, accent: '#13aaa2', secondary: '#f3a04f',
            },
            edit: {
                kind: 'open', icon: 'edit', title: 'Opening Material Revision',
                copy: 'The current material identity, applicability, technical basis, and audit context are being retained.',
                signal: 'NEXORIN catalog governance', eyebrow: 'Controlled record revision', metaLeft: sku, metaRight: material,
                steps: ['Retaining the current material record', 'Loading technical and scope fields', 'Resolving governance and audit context', 'Opening material revision'],
                duration: 920, accent: '#13aaa2', secondary: '#f3a04f',
            },
            suppliers: {
                kind: 'open', icon: 'portfolio', title: 'Opening Supplier Registry',
                copy: 'Supplier identity, procurement context, and material relationships are being restored.',
                signal: 'NEXORIN procurement registry', eyebrow: 'Supplier portfolio handoff', metaLeft: 'Company procurement', metaRight: 'Catalog context retained',
                steps: ['Retaining company access context', 'Loading supplier records', 'Resolving catalog relationships', 'Opening supplier registry'],
                duration: 880, accent: '#3978d8', secondary: '#13aaa2',
            },
            prices: {
                kind: 'open', icon: 'financial', title: 'Opening Price Books',
                copy: 'Supplier cost, selling price, validity, and material-link context are being restored.',
                signal: 'NEXORIN commercial catalog', eyebrow: 'Price governance handoff', metaLeft: 'Cost authority retained', metaRight: 'Price trace preserved',
                steps: ['Retaining commercial access context', 'Loading visible price books', 'Resolving supplier and material links', 'Opening price-book workspace'],
                duration: 900, accent: '#7659ca', secondary: '#f3a04f',
            },
        };
        return configs[kind] || configs.open;
    };

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[data-nxmc-navigate]');
        if (!anchor || anchor.getAttribute('href')?.startsWith('#')) return;
        navigate(event, anchor, configForLink(anchor));
    });

    document.querySelectorAll('[data-nxmc-row]').forEach((row) => {
        const open = () => row.querySelector('a[data-nxmc-navigate="open"]')?.click();
        row.addEventListener('click', (event) => {
            if (event.target.closest('a,button,input,select,textarea,form')) return;
            open();
        });
        row.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            open();
        });
    });

    const filterForm = document.querySelector('[data-nxmc-filter-form]');
    if (filterForm) {
        filterForm.querySelectorAll('[data-nxmc-auto-submit]').forEach((field) => field.addEventListener('change', () => filterForm.requestSubmit()));
        filterForm.addEventListener('submit', () => {
            if (filterForm.dataset.nxmcSubmitting === 'true') return;
            filterForm.dataset.nxmcSubmitting = 'true';
            showAction({
                kind: 'filter', icon: 'filter', title: 'Filtering Material Catalog',
                copy: 'Visible records are being resolved against identity, category, system, scope, and governance filters.',
                signal: 'NEXORIN catalog control', eyebrow: 'Material registry filter sequence', metaLeft: 'Company visibility retained', metaRight: 'Catalog scope preserved',
                steps: ['Reading the active material filters', 'Matching visible catalog records', 'Resolving governance and price-link states', 'Refreshing the material registry'],
                duration: 850, accent: '#13aaa2', secondary: '#3978d8',
            });
        });
    }

    const recordRoot = document.querySelector('[data-nxmc-tab-workspace]');
    const recordTabLinks = [...(recordRoot?.querySelectorAll('[data-nxmc-section-tab][href^="#"]') || [])];
    const recordPanels = [...(recordRoot?.querySelectorAll('[data-nxmc-tab-panel]') || [])];
    const showAllButton = recordRoot?.querySelector('[data-nxmc-show-all]');
    const reducedScroll = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const elementFromHash = (hash) => {
        const id = String(hash || '').replace(/^#/, '');
        return id ? document.getElementById(id) : null;
    };
    const activateRecordPanel = (panelId, { scroll = true, replace = true } = {}) => {
        if (!recordRoot || !recordPanels.length) return false;
        const panel = recordPanels.find((node) => node.id === panelId) || recordPanels[0];
        recordRoot.classList.add('nxmc-tab-mode-ready', 'nxmc-tab-mode-compact');
        recordPanels.forEach((node) => {
            node.classList.toggle('is-tab-active', node === panel);
            if (node === panel) node.classList.add('is-visible');
        });
        recordTabLinks.forEach((link) => {
            const active = link.getAttribute('href') === `#${panel.id}`;
            link.classList.toggle('is-active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        if (showAllButton) showAllButton.setAttribute('aria-pressed', 'false');
        if (replace && window.history?.replaceState) window.history.replaceState(null, '', `#${panel.id}`);
        if (scroll) panel.scrollIntoView({ behavior: reducedScroll ? 'auto' : 'smooth', block: 'start' });
        return true;
    };
    const showFullRecord = () => {
        if (!recordRoot) return;
        recordRoot.classList.add('nxmc-tab-mode-ready');
        recordRoot.classList.remove('nxmc-tab-mode-compact');
        recordPanels.forEach((node) => node.classList.add('is-tab-active', 'is-visible'));
        recordTabLinks.forEach((link) => link.setAttribute('aria-selected', 'false'));
        showAllButton?.setAttribute('aria-pressed', 'true');
    };
    if (recordRoot && recordPanels.length) {
        const hashTarget = elementFromHash(window.location.hash);
        const initialPanel = hashTarget?.closest('[data-nxmc-tab-panel]') || recordPanels[0];
        activateRecordPanel(initialPanel.id, { scroll: false, replace: false });
        hashTarget?.closest('details')?.setAttribute('open', '');
        showAllButton?.addEventListener('click', () => showFullRecord());
        window.addEventListener('hashchange', () => {
            const target = elementFromHash(window.location.hash);
            const panel = target?.closest('[data-nxmc-tab-panel]');
            if (!panel) return;
            activateRecordPanel(panel.id, { scroll: true, replace: false });
            target.closest('details')?.setAttribute('open', '');
        });
        recordRoot.querySelectorAll('a[href^="#"]').forEach((anchor) => {
            if (recordTabLinks.includes(anchor)) return;
            anchor.addEventListener('click', (event) => {
                const target = elementFromHash(anchor.getAttribute('href'));
                const panel = target?.closest('[data-nxmc-tab-panel]');
                if (!panel) return;
                event.preventDefault();
                activateRecordPanel(panel.id, { scroll: false });
                target.closest('details')?.setAttribute('open', '');
                target.scrollIntoView({ behavior: reducedScroll ? 'auto' : 'smooth', block: 'start' });
            });
        });
    }

    const navLinks = [...document.querySelectorAll('.nxmc-record-nav a[href^="#"], .nxmc-editor-step[href^="#"]')];
    const sections = navLinks.map((link) => elementFromHash(link.getAttribute('href'))).filter(Boolean);
    navLinks.forEach((link) => link.addEventListener('click', (event) => {
        const target = elementFromHash(link.getAttribute('href'));
        if (!target) return;
        if (recordTabLinks.includes(link)) {
            event.preventDefault();
            activateRecordPanel(target.id, { scroll: true });
            return;
        }
        event.preventDefault();
        target.scrollIntoView({ behavior: reducedScroll ? 'auto' : 'smooth', block: 'start' });
    }));
    if (sections.length && 'IntersectionObserver' in window) {
        const sectionObserver = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (!visible) return;
            navLinks.forEach((link) => {
                const active = link.getAttribute('href') === `#${visible.target.id}`;
                link.classList.toggle('is-active', active);
                if (active) link.setAttribute('aria-current', 'location');
                else link.removeAttribute('aria-current');
            });
        }, { rootMargin: '-20% 0px -65% 0px', threshold: [0.01, 0.2, 0.5] });
        sections.forEach((section) => sectionObserver.observe(section));
    }

    document.querySelectorAll('[data-parser-review-workspace]').forEach((workspace) => {
        const rows = [...workspace.querySelectorAll('[data-parser-review-row]')];
        const filters = [...workspace.querySelectorAll('[data-parser-review-filter]')];
        const selects = [...workspace.querySelectorAll('select[name^="decisions["][name$="][action]"]')];
        const visibleCopy = workspace.querySelector('[data-parser-review-visible]');
        const footnote = workspace.querySelector('[data-parser-review-footnote]');

        const updateSummary = () => {
            const selected = { accept: 0, keep: 0, reject: 0 };
            selects.forEach((select) => { selected[select.value] = (selected[select.value] || 0) + 1; });
            if (visibleCopy) visibleCopy.textContent = `${rows.filter((row) => !row.hidden).length} of ${rows.length} fields shown`;
            if (footnote) footnote.innerHTML = `Selected decisions: <strong>${selected.accept} accept</strong>, ${selected.keep} keep, ${selected.reject} reject. Applying fields moves technical governance to <strong>Needs Review</strong>; it never approves the material automatically.`;
        };

        const applyFilter = (filter) => {
            rows.forEach((row) => {
                row.hidden = filter !== 'all' && row.dataset.parserStatus !== filter;
            });
            filters.forEach((button) => button.classList.toggle('is-active', button.dataset.parserReviewFilter === filter));
            updateSummary();
        };

        filters.forEach((button) => button.addEventListener('click', () => applyFilter(button.dataset.parserReviewFilter || 'all')));
        selects.forEach((select) => select.addEventListener('change', updateSummary));
        workspace.querySelector('[data-parser-accept-matches]')?.addEventListener('click', () => {
            rows.filter((row) => row.dataset.parserStatus === 'match').forEach((row) => {
                const select = row.querySelector('select[name$="][action]"]:not(:disabled)');
                if (select) select.value = 'accept';
            });
            updateSummary();
        });
        updateSummary();
    });

    const editorForm = document.querySelector('[data-nxmc-editor-form]');
    if (editorForm) {
        const categorySelect = editorForm.querySelector('#material_category_id');
        const contextTitle = editorForm.querySelector('[data-nxmc-category-title]');
        const contextCopy = editorForm.querySelector('[data-nxmc-category-copy]');
        const progressFill = editorForm.querySelector('[data-nxmc-progress-fill]');
        const progressScore = editorForm.querySelector('[data-nxmc-progress-score]');
        const actionStatus = editorForm.querySelector('[data-nxmc-action-status]');
        const actionCopy = editorForm.querySelector('[data-nxmc-action-copy]');
        const cancelLink = editorForm.querySelector('.nxmc-editor-actionbar a');
        const dynamicRoot = editorForm.querySelector('[data-nxmc-dynamic-specifications]');
        const profileSpecCopy = editorForm.querySelector('[data-nxmc-profile-specs]');
        const profileEvidenceCopy = editorForm.querySelector('[data-nxmc-profile-evidence]');
        const profileSpecCount = editorForm.querySelector('[data-nxmc-profile-spec-count]');
        const profileConditionalCount = editorForm.querySelector('[data-nxmc-profile-conditional-count]');
        const profileOptionalCount = editorForm.querySelector('[data-nxmc-profile-optional-count]');
        const profileCompleteCount = editorForm.querySelector('[data-nxmc-profile-complete-count]');
        const schemaStatus = editorForm.querySelector('[data-nxmc-schema-status]');
        const specProgressFill = editorForm.querySelector('[data-nxmc-spec-progress-fill]');
        const specProgressCopy = editorForm.querySelector('[data-nxmc-spec-progress-copy]');
        let submitting = false;

        const parsePayload = (selector, fallback = {}) => {
            const node = document.querySelector(selector);
            if (!node) return fallback;
            try { return JSON.parse(node.textContent || '{}'); } catch (error) { return fallback; }
        };

        const schemas = parsePayload('[data-nxmc-specification-schema-payload]', {});
        const values = parsePayload('[data-nxmc-specification-value-payload]', {});
        const fieldErrors = parsePayload('[data-nxmc-specification-error-payload]', {});
        schemas.DEFAULT = schemas.DEFAULT || { code:'DEFAULT', title:'General Material', description:'Select a professional subcategory to load its technical schema.', required_specs:[], optional_specs:[], required_certificates:[], required_documents:['datasheet'], groups:[], required_count:0, conditional_count:0, optional_count:0 };

        const optionCode = () => categorySelect?.selectedOptions?.[0]?.dataset.code || 'DEFAULT';
        const optionProfileCode = () => categorySelect?.selectedOptions?.[0]?.dataset.profileCode || 'DEFAULT';
        const currentSchema = () => schemas[optionCode()] || schemas[optionProfileCode()] || schemas.DEFAULT;
        const hasInputValue = (input) => input && String(input.value ?? '').trim() !== '';
        const humanize = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

        const collectSpecificationValues = () => {
            dynamicRoot?.querySelectorAll('[data-nxmc-spec-input]').forEach((input) => {
                values[input.dataset.nxmcSpecInput] = input.value;
            });
        };

        const createElement = (tag, className, text) => {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        };

        const createFieldInput = (field) => {
            const id = `technical_spec_${field.key}`;
            let input;

            if (field.input_type === 'select') {
                input = createElement('select', 'nxmc-select');
                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = `Select ${String(field.label || field.key).toLowerCase()}`;
                input.appendChild(blank);
                Object.entries(field.options || {}).forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    input.appendChild(option);
                });
                const validOptions = Object.keys(field.options || {});
                const currentValue = String(values[field.key] ?? '');
                if (validOptions.length === 1 && !validOptions.includes(currentValue)) {
                    input.value = validOptions[0];
                    values[field.key] = input.value;
                }
            } else if (field.input_type === 'textarea') {
                input = createElement('textarea', 'nxmc-textarea nxspec-textarea');
            } else {
                input = createElement('input', 'nxmc-input');
                input.type = ['number', 'date'].includes(field.input_type) ? field.input_type : 'text';
            }

            input.id = id;
            input.name = `technical_specs[${field.key}]`;
            input.dataset.nxmcSpecInput = field.key;
            input.dataset.nxmcSpecRequired = field.required ? 'true' : 'false';
            input.setAttribute('aria-label', `${field.label || humanize(field.key)}${field.required ? ' · required for catalog approval' : (field.conditional ? ' · subtype dependent' : ' · optional')}`);
            input.value = values[field.key] ?? '';
            if (field.placeholder) input.placeholder = field.placeholder;
            if (field.step !== null && field.step !== undefined && input.type === 'number') input.step = field.step;
            if (field.min !== null && field.min !== undefined && input.type === 'number') input.min = field.min;
            if (field.max !== null && field.max !== undefined && input.type === 'number') input.max = field.max;
            if (fieldErrors[field.key]?.length) input.classList.add('is-invalid');

            return input;
        };

        const createSpecificationField = (field) => {
            const fieldMode = field.required ? 'is-required' : (field.conditional ? 'is-conditional' : 'is-optional');
            const fieldNode = createElement('div', `nxspec-field ${fieldMode}`);
            const heading = createElement('div', 'nxspec-field-head');
            const label = createElement('label', 'nxmc-label', field.label || humanize(field.key));
            label.htmlFor = `technical_spec_${field.key}`;
            const stateText = field.required ? 'Approval required' : (field.conditional ? 'Subtype dependent' : 'Optional');
            const state = createElement('span', `nxspec-field-state ${fieldMode}`, stateText);
            heading.append(label, state);

            const control = createElement('div', `nxspec-control${field.unit ? ' has-unit' : ''}`);
            const input = createFieldInput(field);
            control.appendChild(input);
            if (field.unit) control.appendChild(createElement('span', 'nxspec-unit', field.unit));

            const help = createElement('p', 'nxmc-field-hint', field.description || 'Governed category-specific technical field.');
            fieldNode.append(heading, control, help);

            if (fieldErrors[field.key]?.length) {
                fieldErrors[field.key].forEach((message) => fieldNode.appendChild(createElement('div', 'nxmc-field-error', message)));
            }

            return fieldNode;
        };

        const renderSpecifications = () => {
            collectSpecificationValues();
            const schema = currentSchema();
            const groups = Array.isArray(schema.groups) ? schema.groups : [];
            if (!dynamicRoot) return;
            dynamicRoot.replaceChildren();

            if (contextTitle) contextTitle.textContent = schema.title || 'Professional material schema';
            if (contextCopy) contextCopy.textContent = schema.description || schemas.DEFAULT.description;
            if (profileSpecCount) profileSpecCount.textContent = String(schema.required_count ?? schema.required_specs?.length ?? 0);
            if (profileConditionalCount) profileConditionalCount.textContent = String(schema.conditional_count ?? schema.conditional_specs?.length ?? 0);
            if (profileOptionalCount) profileOptionalCount.textContent = String(schema.optional_count ?? 0);
            if (schemaStatus) schemaStatus.textContent = schema.code === 'DEFAULT' ? 'Select a professional subcategory' : `${schema.title} schema loaded`;

            const fieldLabels = new Map(
                groups.flatMap((group) => (group.fields || []).map((field) => [field.key, field.label || humanize(field.key)]))
            );
            const requiredLabels = (schema.required_specs || []).slice(0, 10).map((key) => fieldLabels.get(key) || humanize(key));
            if (profileSpecCopy) profileSpecCopy.textContent = requiredLabels.length ? requiredLabels.join(' · ') + ((schema.required_specs || []).length > 10 ? ` · +${schema.required_specs.length - 10} more` : '') : 'No mandatory fields are assigned to this general profile.';
            const evidence = [...(schema.required_certificates || []), ...(schema.required_documents || []).map(humanize)];
            if (profileEvidenceCopy) profileEvidenceCopy.textContent = evidence.length ? evidence.join(' · ') : 'No mandatory evidence pack is assigned to this category.';

            if (!groups.length) {
                const empty = createElement('div', 'nxspec-empty');
                const icon = createElement('span');
                icon.setAttribute('aria-hidden', 'true');
                icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>';
                empty.append(icon, createElement('strong', '', 'Select a professional subcategory.'), createElement('p', '', 'NEXORIN will load exact technical fields and units for the selected component type.'));
                dynamicRoot.appendChild(empty);
            } else {
                groups.forEach((group, index) => {
                    const section = createElement('section', 'nxspec-group');
                    const header = createElement('header', 'nxspec-group-head');
                    const identity = createElement('div', 'nxspec-group-identity');
                    const indexNode = createElement('span', 'nxspec-group-index', String(index + 1).padStart(2, '0'));
                    const copy = createElement('div');
                    copy.append(createElement('h4', '', group.label || humanize(group.key)), createElement('p', '', group.description || 'Category-specific technical attributes.'));
                    identity.append(indexNode, copy);
                    const fieldCount = group.fields?.length || 0;
                    const counter = createElement('span', 'nxspec-group-count', `${fieldCount} ${fieldCount === 1 ? 'field' : 'fields'}`);
                    header.append(identity, counter);
                    const grid = createElement('div', 'nxspec-field-grid');
                    if (fieldCount <= 2) section.classList.add('is-compact');
                    (group.fields || []).forEach((field) => grid.appendChild(createSpecificationField(field)));
                    section.append(header, grid);
                    dynamicRoot.appendChild(section);
                });
            }

            dynamicRoot.querySelectorAll('[data-nxmc-spec-input]').forEach((input) => {
                input.addEventListener('input', () => {
                    values[input.dataset.nxmcSpecInput] = input.value;
                    updateSpecificationProgress();
                    updateReadiness();
                    updateDirtyState();
                });
                input.addEventListener('change', () => {
                    values[input.dataset.nxmcSpecInput] = input.value;
                    updateSpecificationProgress();
                    updateReadiness();
                    updateDirtyState();
                });
            });
            updateSpecificationProgress();
        };

        const updateSpecificationProgress = () => {
            const requiredInputs = [...(dynamicRoot?.querySelectorAll('[data-nxmc-spec-required="true"]') || [])];
            const completeRequired = requiredInputs.filter(hasInputValue).length;
            const allInputs = [...(dynamicRoot?.querySelectorAll('[data-nxmc-spec-input]') || [])];
            const completeAll = allInputs.filter(hasInputValue).length;
            const percentage = requiredInputs.length ? Math.round((completeRequired / requiredInputs.length) * 100) : (optionCode() === 'DEFAULT' ? 0 : 100);
            if (specProgressFill) specProgressFill.style.width = `${percentage}%`;
            if (specProgressCopy) specProgressCopy.textContent = `${completeRequired} / ${requiredInputs.length} required`;
            if (profileCompleteCount) profileCompleteCount.textContent = String(completeAll);
        };

        renderSpecifications();
        const initialData = new FormData(editorForm);

        const hasValue = (selector) => {
            const field = editorForm.querySelector(selector);
            if (!field) return false;
            if (field.type === 'checkbox') return field.checked;
            return String(field.value || '').trim() !== '';
        };

        const completionState = () => {
            const identitySelectors = ['#sku', '#name', '#material_category_id', '#uom'];
            const identityComplete = identitySelectors.filter(hasValue).length;
            const scopeComplete = hasValue('#default_scope_mode') ? 1 : 0;
            const requiredTechnical = [...(dynamicRoot?.querySelectorAll('[data-nxmc-spec-required="true"]') || [])];
            const technicalCompleteCount = requiredTechnical.filter(hasInputValue).length;
            const governanceAdded = hasValue('#description') || hasValue('#technical_notes');

            return {
                identity: { complete: identityComplete, total: identitySelectors.length },
                applicability: { complete: scopeComplete, total: 1 },
                technical: { complete: technicalCompleteCount, total: requiredTechnical.length, exact: optionCode() !== 'DEFAULT' },
                governance: { added: governanceAdded },
            };
        };

        const updateReadiness = () => {
            const state = completionState();
            const scoredSections = [state.identity, state.applicability, state.technical];
            const complete = scoredSections.reduce((sum, section) => sum + section.complete, 0);
            const total = scoredSections.reduce((sum, section) => sum + section.total, 0);
            const percentage = total > 0 ? Math.round((complete / total) * 100) : 0;
            if (progressFill) progressFill.style.width = `${percentage}%`;
            if (progressScore) progressScore.textContent = `${percentage}%`;

            const labels = {
                identity: state.identity.complete === state.identity.total ? 'Complete' : `${state.identity.complete} / ${state.identity.total}`,
                applicability: state.applicability.complete === state.applicability.total ? 'Complete' : `${state.applicability.complete} / ${state.applicability.total}`,
                technical: !state.technical.exact ? 'Select subtype' : (state.technical.complete === state.technical.total ? 'Complete' : `${state.technical.complete} / ${state.technical.total}`),
                governance: state.governance.added ? 'Added' : 'Optional',
            };

            Object.entries(labels).forEach(([key, label]) => {
                const isComplete = key === 'governance'
                    ? state.governance.added
                    : (state[key].total > 0 && state[key].complete === state[key].total);
                editorForm.querySelectorAll(`[data-nxmc-section-state="${key}"]`).forEach((node) => {
                    node.textContent = label;
                    node.classList.toggle('is-complete', isComplete);
                    node.classList.toggle('is-optional', key === 'governance' && !state.governance.added);
                    node.closest('.nxmc-editor-step')?.classList.toggle('is-complete', isComplete);
                });
            });
        };

        const formChanged = () => {
            const current = new FormData(editorForm);
            const normalize = (data) => [...data.entries()].map(([key, value]) => [key, value instanceof File ? value.name : String(value)]).sort((a, b) => `${a[0]}:${a[1]}`.localeCompare(`${b[0]}:${b[1]}`));
            return JSON.stringify(normalize(current)) !== JSON.stringify(normalize(initialData));
        };

        const updateDirtyState = () => {
            const dirty = formChanged();
            actionStatus?.classList.toggle('is-dirty', dirty);
            if (actionCopy) actionCopy.textContent = dirty ? 'Unsaved material changes are ready for controlled submission.' : 'No unsaved changes detected.';
            if (cancelLink) {
                if (dirty) cancelLink.dataset.confirm = 'Leave this material form and discard the unsaved changes?';
                else delete cancelLink.dataset.confirm;
            }
        };

        editorForm.addEventListener('input', () => { updateReadiness(); updateDirtyState(); });
        editorForm.addEventListener('change', () => { updateReadiness(); updateDirtyState(); });
        categorySelect?.addEventListener('change', () => {
            renderSpecifications();
            updateReadiness();
            updateDirtyState();
        });
        cancelLink?.addEventListener('click', () => {
            if (cancelLink.dataset.nxConfirmApproved === 'true' || !formChanged()) submitting = true;
        });

        editorForm.addEventListener('submit', () => {
            if (submitting) return;
            collectSpecificationValues();
            submitting = true;
            editorForm.dataset.nxmcSubmitting = 'true';
            const mode = editorForm.dataset.nxmcEditorMode || 'create';
            showAction({
                kind: 'save', icon: 'save',
                title: mode === 'edit' ? 'Saving Material Revision' : 'Creating Material Record',
                copy: 'NEXORIN is validating identity, applicability, category-specific technical attributes, and catalog governance before recording the material.',
                signal: 'NEXORIN catalog governance', eyebrow: 'Controlled material write sequence',
                metaLeft: editorForm.querySelector('#sku')?.value || 'New catalog item',
                metaRight: currentSchema().title || editorForm.querySelector('#name')?.value || 'Material identity',
                steps: ['Validating required material identity', 'Resolving the professional component schema', 'Synchronizing normalized technical specifications', mode === 'edit' ? 'Recording the governed material revision' : 'Creating the company material record'],
                duration: 1080, accent: '#13aaa2', secondary: '#f3a04f',
            });
        });

        window.addEventListener('beforeunload', (event) => {
            if (submitting || !formChanged()) return;
            event.preventDefault();
            event.returnValue = '';
        });

        updateSpecificationProgress();
        updateReadiness();
        updateDirtyState();
    }

    const validateDatasheetUpload = (form) => {
        const input = form.querySelector('[data-nxmc-datasheet-file]');
        const errorNode = form.querySelector('[data-nxmc-upload-client-error]');
        if (!input) return true;

        input.setCustomValidity('');
        if (errorNode) {
            errorNode.hidden = true;
            errorNode.textContent = '';
        }

        const file = input.files?.[0];
        const maximumBytes = Number(form.dataset.nxmcUploadMaxBytes || 0);
        if (!file || !Number.isFinite(maximumBytes) || maximumBytes <= 0 || file.size <= maximumBytes) return true;

        const maximumLabel = form.dataset.nxmcUploadMaxLabel || `${Math.floor(maximumBytes / 1024)} KB`;
        const actualLabel = file.size >= 1024 * 1024
            ? `${(file.size / 1024 / 1024).toFixed(2)} MB`
            : `${Math.ceil(file.size / 1024)} KB`;
        const message = `The selected datasheet is ${actualLabel}, above the active server limit of ${maximumLabel}. Increase the PHP upload limit or use a smaller file before submitting.`;

        input.setCustomValidity(message);
        if (errorNode) {
            errorNode.hidden = false;
            errorNode.textContent = message;
        }
        input.reportValidity();
        input.focus();

        return false;
    };

    document.querySelectorAll('form[data-nxmc-action]').forEach((form) => {
        const datasheetInput = form.querySelector('[data-nxmc-datasheet-file]');
        datasheetInput?.addEventListener('change', () => validateDatasheetUpload(form));

        form.addEventListener('submit', (event) => {
            if (form.hasAttribute('data-confirm') && form.dataset.nxConfirmApproved !== 'true') return;
            if (form.dataset.nxmcSubmitting === 'true') return;
            const action = form.dataset.nxmcAction;
            if (action === 'datasheet' && !validateDatasheetUpload(form)) {
                event.preventDefault();
                return;
            }
            form.dataset.nxmcSubmitting = 'true';
            const configs = {
                approve: { kind:'verify', icon:'verify', title:'Approving Material for BOQ Use', copy:'Required technical specifications, datasheet evidence, certificates, and governance state are being verified.', steps:['Checking catalog readiness','Reviewing technical and document evidence','Recording approval authority','Releasing the material for controlled BOQ use'], accent:'#13aaa2', secondary:'#f3a04f' },
                archive: { kind:'save', icon:'equipment', title:'Archiving Material Record', copy:'The material is being removed from new selection while its historical trace remains preserved.', steps:['Retaining audit history','Securing existing project references','Changing record availability','Recording the archive state'], accent:'#cf514a', secondary:'#f3a04f' },
                activate: { kind:'verify', icon:'equipment', title:'Activating Material Record', copy:'The company material is being restored to the active catalog portfolio.', steps:['Checking company ownership','Restoring active record state','Resolving catalog visibility','Refreshing governance context'], accent:'#13aaa2', secondary:'#3978d8' },
                datasheet: { kind:'upload', icon:'upload', title:'Uploading and Parsing Datasheet', copy:'Vendor evidence is being stored, parsed, and prepared for controlled review before technical values are applied.', steps:['Validating the datasheet source','Securing the vendor evidence file','Extracting normalized technical fields','Preparing parser review context'], accent:'#3978d8', secondary:'#13aaa2' },
                apply: { kind:'verify', icon:'verify', title:'Applying Parsed Specifications', copy:'Reviewed datasheet values are being normalized into the material technical record.', steps:['Loading parsed vendor fields','Validating normalized values','Updating material specifications','Recording datasheet traceability'], accent:'#13aaa2', secondary:'#7659ca' },
                datasheetArchive: { kind:'save', icon:'upload', title:'Archiving Datasheet Evidence', copy:'The selected datasheet is being removed from active evidence while its historical trace remains preserved.', steps:['Retaining parser history','Securing existing material values','Changing evidence availability','Recording the archive state'], accent:'#cf514a', secondary:'#f3a04f' },
                certificate: { kind:'upload', icon:'verify', title:'Registering Certificate Evidence', copy:'Certificate identity, issuer, validity, and source evidence are being secured for compliance review.', steps:['Validating certificate metadata','Securing the evidence source','Opening verification workflow','Refreshing compliance coverage'], accent:'#3978d8', secondary:'#13aaa2' },
                certificateVerify: { kind:'verify', icon:'verify', title:'Verifying Certificate Evidence', copy:'The certificate is being checked against the material profile and recorded in the compliance audit.', steps:['Checking certificate ownership','Reviewing validity and issuer','Recording verification authority','Refreshing catalog readiness'], accent:'#13aaa2', secondary:'#3978d8' },
                certificateArchive: { kind:'save', icon:'verify', title:'Archiving Certificate Evidence', copy:'The certificate is being removed from active compliance coverage while its audit trace remains preserved.', steps:['Retaining verification history','Changing evidence availability','Recalculating compliance coverage','Recording archive state'], accent:'#cf514a', secondary:'#f3a04f' },
                document: { kind:'upload', icon:'upload', title:'Registering Supporting Document', copy:'Warranty, manual, protocol, drawing, or service evidence is being secured for professional catalog governance.', steps:['Validating document metadata','Securing the evidence source','Opening document verification','Refreshing the evidence pack'], accent:'#7659ca', secondary:'#13aaa2' },
                documentVerify: { kind:'verify', icon:'verify', title:'Verifying Supporting Document', copy:'The document is being checked and added to the governed professional evidence pack.', steps:['Checking document ownership','Reviewing revision and validity','Recording verification authority','Refreshing catalog readiness'], accent:'#13aaa2', secondary:'#7659ca' },
                documentArchive: { kind:'save', icon:'upload', title:'Archiving Supporting Document', copy:'The supporting document is being removed from active evidence while its audit history remains preserved.', steps:['Retaining document history','Changing active evidence state','Recalculating document coverage','Recording archive state'], accent:'#cf514a', secondary:'#f3a04f' },
                specification: { kind:'save', icon:'save', title:'Saving Normalized Specification', copy:'The technical value is being normalized into the professional material profile and smart-filter context.', steps:['Validating specification key and value','Linking the category profile','Updating material readiness','Recording the governed technical field'], accent:'#13aaa2', secondary:'#3978d8' },
                specificationDelete: { kind:'save', icon:'save', title:'Removing Normalized Specification', copy:'The selected field is being removed and catalog readiness will be recalculated.', steps:['Retaining material audit context','Removing the normalized value','Recalculating profile coverage','Refreshing catalog governance'], accent:'#cf514a', secondary:'#f3a04f' },
            };
            showAction({ duration:1020, signal:'NEXORIN material governance', eyebrow:'Controlled catalog action', metaLeft:document.querySelector('[data-nxmc-material-sku]')?.textContent?.trim() || 'Material record', metaRight:document.querySelector('[data-nxmc-material-name]')?.textContent?.trim() || 'Catalog context', ...(configs[action] || configs.datasheet) });
        });
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-nxmc-submitting="true"]').forEach((node) => delete node.dataset.nxmcSubmitting);
    });
})();
