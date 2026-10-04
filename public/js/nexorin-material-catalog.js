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

    const navLinks = [...document.querySelectorAll('.nxmc-record-nav a[href^="#"], .nxmc-editor-step[href^="#"], .nxmci-intake-map a[href^="#"], .nxmci-rail-link[href^="#"]')];
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
        const categoryFilter = editorForm.querySelector('[data-nxmci-category-filter]');
        const categoryFilterStatus = editorForm.querySelector('[data-nxmci-category-filter-status]');
        const categorySelector = editorForm.querySelector('.nxmci-category-selector');
        const categoryPicker = editorForm.querySelector('[data-nxmci-category-picker]');
        const categoryTrigger = editorForm.querySelector('[data-nxmci-category-trigger]');
        const categoryTriggerLabel = editorForm.querySelector('[data-nxmci-category-trigger-label]');
        const categoryTriggerMeta = editorForm.querySelector('[data-nxmci-category-trigger-meta]');
        const categoryMenu = editorForm.querySelector('[data-nxmci-category-menu]');
        const categoryMenuCount = editorForm.querySelector('[data-nxmci-category-menu-count]');
        const categoryOptionsRoot = editorForm.querySelector('[data-nxmci-category-options]');
        const contextTitle = editorForm.querySelector('[data-nxmc-category-title]');
        const schemaAuthority = editorForm.querySelector('[data-nxmci-schema-authority]');
        const contextCopy = editorForm.querySelector('[data-nxmc-category-copy]');
        const progressFill = editorForm.querySelector('[data-nxmc-progress-fill]');
        const progressScore = editorForm.querySelector('[data-nxmc-progress-score]');
        const actionStatus = editorForm.querySelector('[data-nxmc-action-status]');
        const actionBar = editorForm.querySelector('.nxmc-editor-actionbar');
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
        const mode = editorForm.dataset.nxmcEditorMode || 'create';
        const createMode = mode === 'create';
        const missingCopy = editorForm.querySelector('[data-nxmci-missing-copy]');
        const requiredDraftNodes = [...editorForm.querySelectorAll('[data-nxmci-required-field]')];
        const previewKicker = editorForm.querySelector('[data-nxmci-preview-kicker]');
        const previewTitle = editorForm.querySelector('[data-nxmci-preview-title]');
        const previewCopy = editorForm.querySelector('[data-nxmci-preview-copy]');
        const previewVisual = editorForm.querySelector('[data-nxmci-preview-visual]');
        const previewArtwork = editorForm.querySelector('[data-nxmci-preview-artwork]');
        const previewImage = editorForm.querySelector('[data-nxmci-preview-image]');
        const previewProfile = editorForm.querySelector('[data-nxmci-preview-profile]');
        const previewBrand = editorForm.querySelector('[data-nxmci-preview-brand]');
        const previewModel = editorForm.querySelector('[data-nxmci-preview-model]');
        const previewSku = editorForm.querySelector('[data-nxmci-preview-sku]');
        const schemaGroupNav = editorForm.querySelector('[data-nxmci-spec-group-nav]');
        const railRequired = editorForm.querySelector('[data-nxmci-rail-required]');
        const railRecorded = editorForm.querySelector('[data-nxmci-rail-recorded]');
        const railMissing = editorForm.querySelector('[data-nxmci-rail-missing]');
        const railConditional = editorForm.querySelector('[data-nxmci-rail-conditional]');
        const railSchemaState = editorForm.querySelector('[data-nxmci-schema-rail-state]');
        const railSchemaCopy = editorForm.querySelector('[data-nxmci-schema-rail-copy]');
        let submitting = false;
        let schemaGroupObserver = null;

        const parsePayload = (selector, fallback = {}) => {
            const node = document.querySelector(selector);
            if (!node) return fallback;
            try { return JSON.parse(node.textContent || '{}'); } catch (error) { return fallback; }
        };

        const schemas = parsePayload('[data-nxmc-specification-schema-payload]', {});
        const values = parsePayload('[data-nxmc-specification-value-payload]', {});
        const rawFieldErrors = parsePayload('[data-nxmc-specification-error-payload]', {});
        const fieldErrors = rawFieldErrors && typeof rawFieldErrors === 'object' && !Array.isArray(rawFieldErrors) ? rawFieldErrors : {};
        schemas.DEFAULT = schemas.DEFAULT || { code:'DEFAULT', title:'Select a component class', description:'Technical fields and units load only after an exact engineering component class is selected. No engineering values are assumed.', required_specs:[], optional_specs:[], conditional_specs:[], required_certificates:[], required_documents:[], groups:[], field_count:0, required_count:0, conditional_count:0, optional_count:0 };

        const cleanCategoryLabel = (value) => String(value || '')
            .replace(/^—\s*/, '')
            .replace(/\s*·\s*select a component below\s*$/i, '')
            .trim();

        const categoryNativeOptions = categorySelect ? [...categorySelect.options] : [];
        const categoryRootOptions = categoryNativeOptions.filter((option) => option.value && !String(option.dataset.parentId || ''));
        const categoryRootMap = new Map(categoryRootOptions.map((option) => [String(option.value), cleanCategoryLabel(option.textContent)]));

        const categoryGroupNameFor = (option) => {
            const parentId = String(option?.dataset?.parentId || '');
            return categoryRootMap.get(parentId) || 'Other engineering components';
        };

        const categorySelectableOptions = categoryNativeOptions.filter(
            (option) => option.value && !option.disabled && String(option.dataset.parentId || '')
        );
        const isCreateIntake = editorForm.dataset.nxmcEditorMode === 'create';
        const categoryPickerOptions = categorySelectableOptions.filter((option) =>
            !isCreateIntake || option.dataset.canonicalClass === '1' || option.selected
        );

        const syncCategoryTrigger = () => {
            if (!categorySelect || !categoryTriggerLabel || !categoryTriggerMeta) return;
            const selected = categorySelect.selectedOptions?.[0];
            if (!selected?.value) {
                categoryTriggerLabel.textContent = 'Select component class';
                categoryTriggerMeta.textContent = 'Choose from the engineering catalog';
                categoryTrigger?.classList.remove('has-selection');
                return;
            }
            categoryTriggerLabel.textContent = cleanCategoryLabel(selected.textContent);
            categoryTriggerMeta.textContent = categoryGroupNameFor(selected);
            categoryTrigger?.classList.add('has-selection');
        };

        const positionCategoryMenu = () => {
            if (!categoryMenu || !categoryTrigger || !categoryPicker) return;
            const rect = categoryTrigger.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            const spaceBelow = Math.max(0, viewportHeight - rect.bottom - 14);
            const spaceAbove = Math.max(0, rect.top - 14);
            const opensAbove = spaceBelow < 210 && spaceAbove > 300;
            const available = Math.max(220, Math.min(340, (opensAbove ? spaceAbove : spaceBelow) - 8));
            categoryPicker.classList.toggle('opens-above', opensAbove);
            categoryMenu.style.setProperty('--nxmci-category-menu-max-height', `${available}px`);
        };

        const setCategoryPickerOpen = (open, { focusFirst = false } = {}) => {
            if (!categoryMenu || !categoryTrigger) return;
            const next = Boolean(open);
            categoryMenu.hidden = !next;
            categoryTrigger.setAttribute('aria-expanded', next ? 'true' : 'false');
            categoryPicker?.classList.toggle('is-open', next);
            if (next) {
                positionCategoryMenu();
            }
            if (next && focusFirst) {
                requestAnimationFrame(() => categoryFilter?.focus());
            }
        };

        const selectCategoryValue = (value) => {
            if (!categorySelect) return;
            categorySelect.value = String(value || '');
            categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
            syncCategoryTrigger();
            if (categoryFilter) categoryFilter.value = '';
            setCategoryPickerOpen(false);
        };

        const renderCategoryPickerOptions = () => {
            if (!categoryOptionsRoot || !categorySelect) return 0;
            const query = String(categoryFilter?.value || '').trim().toLowerCase();
            const groups = new Map();

            categoryPickerOptions.forEach((option) => {
                const label = cleanCategoryLabel(option.textContent);
                const groupName = categoryGroupNameFor(option);
                const searchIndex = `${label} ${groupName} ${String(option.dataset.code || '').replace(/^EPM_/i, '').replaceAll('_', ' ')}`.toLowerCase();
                const matches = !query || searchIndex.includes(query);
                if (!matches) return;
                if (!groups.has(groupName)) groups.set(groupName, []);
                groups.get(groupName).push(option);
            });

            categoryOptionsRoot.replaceChildren();
            let matches = 0;
            groups.forEach((options, groupName) => {
                const group = document.createElement('section');
                group.className = 'nxmci-category-option-group';
                group.setAttribute('aria-label', groupName);

                const heading = document.createElement('div');
                heading.className = 'nxmci-category-option-group-heading';
                const headingLabel = document.createElement('span');
                headingLabel.textContent = groupName;
                const headingCount = document.createElement('small');
                headingCount.textContent = `${options.length}`;
                heading.append(headingLabel, headingCount);
                group.appendChild(heading);

                options.forEach((option) => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'nxmci-category-option';
                    row.dataset.value = option.value;
                    row.setAttribute('role', 'option');
                    row.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                    row.tabIndex = -1;
                    if (option.selected) row.classList.add('is-selected');

                    const label = document.createElement('span');
                    label.textContent = cleanCategoryLabel(option.textContent);
                    const state = document.createElement('span');
                    state.className = 'nxmci-category-option-state';
                    state.setAttribute('aria-hidden', 'true');
                    state.innerHTML = option.selected
                        ? '<svg viewBox="0 0 20 20" fill="none"><path d="m5.2 10.4 3 3 6.6-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                        : '<svg viewBox="0 0 20 20" fill="none"><path d="m7.5 5.5 4.5 4.5-4.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                    row.append(label, state);
                    group.appendChild(row);
                    matches += 1;
                });

                categoryOptionsRoot.appendChild(group);
            });

            if (!matches) {
                const empty = document.createElement('div');
                empty.className = 'nxmci-category-empty';
                const strong = document.createElement('strong');
                strong.textContent = 'No matching component class';
                const span = document.createElement('span');
                span.textContent = 'Try a broader engineering term or another component family.';
                empty.append(strong, span);
                categoryOptionsRoot.appendChild(empty);
            }

            if (categoryMenuCount) {
                categoryMenuCount.textContent = query ? `${matches} matching` : `${categoryPickerOptions.length} available`;
            }
            if (categoryFilterStatus) {
                categoryFilterStatus.textContent = query
                    ? `${matches} matching component ${matches === 1 ? 'class' : 'classes'}. Choose one to load its engineering fields.`
                    : 'Choose the exact component class. It determines the engineering fields shown below.';
            }
            return matches;
        };

        const filterCategoryOptions = () => {
            if (!categorySelect || !categoryFilter) return;
            renderCategoryPickerOptions();
        };

        if (categorySelector && categoryPicker && categoryTrigger && categoryMenu && categoryOptionsRoot && categorySelect) {
            categorySelector.classList.add('is-enhanced');
            syncCategoryTrigger();
            renderCategoryPickerOptions();

            categoryTrigger.addEventListener('click', () => setCategoryPickerOpen(categoryMenu.hidden, { focusFirst: categoryMenu.hidden }));
            categoryTrigger.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    setCategoryPickerOpen(true, { focusFirst: true });
                } else if (event.key === 'Escape') {
                    setCategoryPickerOpen(false);
                }
            });

            categoryFilter?.addEventListener('focus', () => setCategoryPickerOpen(true));
            categoryFilter?.addEventListener('input', () => {
                filterCategoryOptions();
                setCategoryPickerOpen(true);
            });
            categoryFilter?.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setCategoryPickerOpen(true, { focusFirst: true });
                } else if (event.key === 'Escape') {
                    setCategoryPickerOpen(false);
                    categoryTrigger.focus();
                }
            });

            categoryOptionsRoot.addEventListener('click', (event) => {
                const option = event.target.closest('[data-value]');
                if (!option) return;
                selectCategoryValue(option.dataset.value);
            });

            categoryOptionsRoot.addEventListener('keydown', (event) => {
                const current = event.target.closest('[role="option"]');
                if (!current) return;
                const options = [...categoryOptionsRoot.querySelectorAll('[role="option"]')];
                const index = options.indexOf(current);
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    options[Math.min(index + 1, options.length - 1)]?.focus();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (index <= 0) categoryFilter?.focus();
                    else options[index - 1]?.focus();
                } else if (event.key === 'Home') {
                    event.preventDefault();
                    options[0]?.focus();
                } else if (event.key === 'End') {
                    event.preventDefault();
                    options.at(-1)?.focus();
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectCategoryValue(current.dataset.value);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    setCategoryPickerOpen(false);
                    categoryTrigger.focus();
                }
            });

            document.addEventListener('pointerdown', (event) => {
                if (!categorySelector.contains(event.target)) setCategoryPickerOpen(false);
            });
            window.addEventListener('resize', () => {
                if (!categoryMenu.hidden) positionCategoryMenu();
            }, { passive: true });
            window.addEventListener('scroll', () => {
                if (!categoryMenu.hidden) setCategoryPickerOpen(false);
            }, { passive: true });
        } else {
            categoryFilter?.addEventListener('input', filterCategoryOptions);
        }
        filterCategoryOptions();

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

        const premiumSelectControls = new Set();

        const closePremiumSelectMenus = (except = null) => {
            premiumSelectControls.forEach((control) => {
                if (control === except || !control.classList.contains('is-open')) return;
                control.classList.remove('is-open', 'opens-above');
                const trigger = control.querySelector('[data-nxmci-select-trigger]');
                const menu = control.querySelector('[data-nxmci-select-menu]');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
                if (menu) menu.hidden = true;
            });
        };

        const enhancePremiumSelect = (select, config = {}) => {
            if (!select || select.dataset.nxmciPremiumSelectEnhanced === '1' || select.classList.contains('nxmci-category-native')) return null;
            const parent = select.parentElement;
            if (!parent) return null;

            const allOptions = [...select.options].filter((option) => !option.disabled);
            const valueOptions = allOptions.filter((option) => String(option.value) !== '');
            if (!allOptions.length) return null;

            const control = createElement('div', 'nxmci-select-control');
            control.dataset.nxmciSelectControl = '';
            if (select.classList.contains('is-invalid')) control.classList.add('is-invalid');

            const trigger = createElement('button', 'nxmci-select-trigger');
            trigger.type = 'button';
            trigger.dataset.nxmciSelectTrigger = '';
            trigger.id = `${select.id || `nxmci_select_${premiumSelectControls.size + 1}`}_trigger`;
            trigger.setAttribute('aria-haspopup', 'listbox');
            trigger.setAttribute('aria-expanded', 'false');

            const triggerCopy = createElement('span', 'nxmci-select-trigger-copy');
            const triggerLabel = createElement('strong', '', 'Select an option');
            const triggerMeta = createElement('small', '', config.meta || 'Choose a value');
            triggerCopy.append(triggerLabel, triggerMeta);
            const triggerIcon = createElement('span', 'nxmci-select-trigger-icon');
            triggerIcon.setAttribute('aria-hidden', 'true');
            triggerIcon.innerHTML = '<svg viewBox="0 0 20 20" fill="none"><path d="m5.5 7.5 4.5 4.5 4.5-4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            trigger.append(triggerCopy, triggerIcon);

            const menu = createElement('div', 'nxmci-select-menu');
            menu.dataset.nxmciSelectMenu = '';
            menu.hidden = true;
            const menuId = `${trigger.id}_menu`;
            menu.id = menuId;
            menu.setAttribute('role', 'listbox');
            trigger.setAttribute('aria-controls', menuId);

            const contextLabel = config.label || select.closest('.nxmc-field, .nxspec-field')?.querySelector('.nxmc-label')?.textContent?.trim() || 'Select value';
            const menuHead = createElement('div', 'nxmci-select-menu-head');
            menuHead.append(
                createElement('span', '', contextLabel),
                createElement('small', '', `${valueOptions.length || allOptions.length} ${valueOptions.length === 1 ? 'option' : 'options'}`)
            );
            const optionsRoot = createElement('div', 'nxmci-select-options');
            optionsRoot.dataset.nxmciSelectOptions = '';
            menu.append(menuHead, optionsRoot);

            parent.insertBefore(control, select);
            control.append(trigger, menu, select);
            select.classList.add('nxmci-select-native');
            select.dataset.nxmciPremiumSelectEnhanced = '1';
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');

            const associatedLabel = select.id ? editorForm.querySelector(`label[for="${select.id}"]`) : null;
            if (associatedLabel) associatedLabel.htmlFor = trigger.id;

            const syncTrigger = () => {
                const selected = select.selectedOptions?.[0];
                const selectedHasValue = Boolean(selected && String(selected.value) !== '');
                triggerLabel.textContent = selected?.textContent?.trim() || 'Select an option';
                triggerMeta.textContent = selectedHasValue
                    ? (config.selectedMeta || 'Selected value')
                    : (config.meta || 'Choose a value');
                trigger.classList.toggle('has-selection', selectedHasValue);
                optionsRoot.querySelectorAll('[data-value]').forEach((row) => {
                    const active = row.dataset.value === String(select.value || '');
                    row.classList.toggle('is-selected', active);
                    row.setAttribute('aria-selected', active ? 'true' : 'false');
                    const state = row.querySelector('.nxmci-select-option-state');
                    if (state) state.innerHTML = active
                        ? '<svg viewBox="0 0 20 20" fill="none"><path d="m5.2 10.4 3 3 6.6-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                        : '<svg viewBox="0 0 20 20" fill="none"><path d="m7.5 5.5 4.5 4.5-4.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                });
            };

            const chooseValue = (value) => {
                select.value = value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                syncTrigger();
                control.classList.remove('is-open', 'opens-above');
                menu.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus({ preventScroll: true });
            };

            allOptions.forEach((option) => {
                const row = createElement('button', 'nxmci-select-option');
                row.type = 'button';
                row.dataset.value = String(option.value);
                row.setAttribute('role', 'option');
                row.tabIndex = -1;
                const copy = createElement('span', 'nxmci-select-option-copy');
                const isEmptyChoice = String(option.value) === '';
                if (isEmptyChoice) row.classList.add('is-empty-choice');
                copy.appendChild(createElement('strong', '', isEmptyChoice ? 'Not recorded' : (option.textContent?.trim() || 'Option')));
                if (isEmptyChoice) {
                    copy.appendChild(createElement('small', '', 'Leave this technical value unset in the draft.'));
                } else if (option.dataset.description) {
                    copy.appendChild(createElement('small', '', option.dataset.description));
                }
                const state = createElement('span', 'nxmci-select-option-state');
                state.setAttribute('aria-hidden', 'true');
                row.append(copy, state);
                row.addEventListener('click', () => chooseValue(row.dataset.value));
                optionsRoot.appendChild(row);
            });

            const fixedChoice = valueOptions.length === 1 && allOptions.length <= 2 && String(select.value || '') === String(valueOptions[0]?.value || '');
            if (fixedChoice) {
                control.classList.add('is-fixed');
                trigger.disabled = true;
                trigger.setAttribute('aria-disabled', 'true');
                triggerMeta.textContent = config.fixedMeta || 'Fixed by component class';
            }

            const positionMenu = () => {
                if (fixedChoice) return;
                const rect = trigger.getBoundingClientRect();
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                const spaceBelow = Math.max(0, viewportHeight - rect.bottom - 12);
                const spaceAbove = Math.max(0, rect.top - 12);
                const opensAbove = spaceBelow < 180 && spaceAbove > spaceBelow;
                const available = Math.max(150, Math.min(300, (opensAbove ? spaceAbove : spaceBelow) - 8));
                control.classList.toggle('opens-above', opensAbove);
                menu.style.setProperty('--nxmci-select-menu-max-height', `${available}px`);
            };

            const setOpen = (open, { focusSelected = false } = {}) => {
                if (fixedChoice) return;
                const next = Boolean(open);
                if (next) closePremiumSelectMenus(control);
                control.classList.toggle('is-open', next);
                trigger.setAttribute('aria-expanded', next ? 'true' : 'false');
                menu.hidden = !next;
                if (next) {
                    positionMenu();
                    if (focusSelected) requestAnimationFrame(() => {
                        (optionsRoot.querySelector('.is-selected') || optionsRoot.querySelector('.nxmci-select-option'))?.focus();
                    });
                }
            };

            trigger.addEventListener('click', () => setOpen(!control.classList.contains('is-open'), { focusSelected: true }));
            trigger.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    setOpen(true, { focusSelected: true });
                } else if (event.key === 'Escape') {
                    setOpen(false);
                }
            });
            optionsRoot.addEventListener('keydown', (event) => {
                const rows = [...optionsRoot.querySelectorAll('.nxmci-select-option')];
                const current = document.activeElement?.closest?.('.nxmci-select-option');
                if (!rows.length || !current) return;
                const index = rows.indexOf(current);
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    rows[Math.min(index + 1, rows.length - 1)]?.focus();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (index <= 0) trigger.focus(); else rows[index - 1]?.focus();
                } else if (event.key === 'Home') {
                    event.preventDefault();
                    rows[0]?.focus();
                } else if (event.key === 'End') {
                    event.preventDefault();
                    rows.at(-1)?.focus();
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    chooseValue(current.dataset.value);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    setOpen(false);
                    trigger.focus();
                }
            });

            select.addEventListener('change', syncTrigger);
            premiumSelectControls.add(control);
            syncTrigger();
            return control;
        };

        document.addEventListener('pointerdown', (event) => {
            premiumSelectControls.forEach((control) => {
                if (!control.contains(event.target)) {
                    control.classList.remove('is-open', 'opens-above');
                    const trigger = control.querySelector('[data-nxmci-select-trigger]');
                    const menu = control.querySelector('[data-nxmci-select-menu]');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                    if (menu) menu.hidden = true;
                }
            });
        });
        window.addEventListener('resize', () => closePremiumSelectMenus(), { passive: true });
        window.addEventListener('scroll', () => closePremiumSelectMenus(), { passive: true });

        editorForm.querySelectorAll('select.nxmc-select[data-nxmci-premium-select]').forEach((select) => {
            enhancePremiumSelect(select, {
                label: select.dataset.nxmciSelectLabel || undefined,
                meta: select.dataset.nxmciSelectMeta || 'Choose a value',
                selectedMeta: select.dataset.nxmciSelectSelectedMeta || 'Selected value'
            });
        });

        const emptyCatalogImage = 'engineering-catalog-empty-state.webp';

        const classImages = {
            EPM_PV_MOD_STD: 'pv-module-monofacial.webp',
            EPM_PV_MOD_BIF: 'pv-module-bifacial.webp',
            EPM_PV_MOD_BIPV: 'pv-module-bipv.webp',
            EPM_PV_OPT: 'dc-optimizer-mlpe.webp',
            EPM_PV_RSD: 'rapid-shutdown-device.webp',
            EPM_INV_STRING: 'string-inverter.webp',
            EPM_INV_CENTRAL: 'central-inverter.webp',
            EPM_INV_MICRO: 'microinverter.webp',
            EPM_INV_HYBRID: 'hybrid-inverter.webp',
            EPM_PCS_BESS: 'bidirectional-pcs.webp',
            EPM_BAT_CELL: 'battery-cell.webp',
            EPM_BAT_MODULE: 'battery-module-pack.webp',
            EPM_BAT_RACK: 'battery-rack.webp',
            EPM_BAT_CAB: 'battery-cabinet.webp',
            EPM_BAT_CONTAINER: 'containerized-bess.webp',
            EPM_BMS: 'battery-management-system.webp',
            EPM_EMS: 'energy-management-system.webp',
            EPM_DC_CABLE: 'pv-dc-cable.webp',
            EPM_DC_CONN: 'pv-connector.webp',
            EPM_DC_BRANCH: 'pv-branch-connector.webp',
            EPM_DC_FUSE: 'gpv-string-fuse.webp',
            EPM_DC_FUSE_HOLDER: 'dc-fuse-holder.webp',
            EPM_DC_BREAKER: 'dc-circuit-breaker.webp',
            EPM_DC_ISOLATOR: 'pv-dc-switch-disconnector.webp',
            EPM_DC_SPD: 'pv-dc-spd.webp',
            EPM_DC_COMB: 'dc-combiner-box.webp',
            EPM_AC_CABLE: 'lv-ac-power-cable.webp',
            EPM_MCB: 'miniature-circuit-breaker.webp',
            EPM_MCCB: 'mccb.webp',
            EPM_ACB: 'air-circuit-breaker.webp',
            EPM_RCD: 'residual-current-device.webp',
            EPM_RCBO: 'rcbo.webp',
            EPM_AC_SPD: 'ac-spd.webp',
            EPM_AC_ISOLATOR: 'ac-switch-disconnector.webp',
            EPM_CONTACTOR: 'power-contactor.webp',
            EPM_ATS: 'automatic-transfer-switch.webp',
            EPM_STS: 'static-transfer-switch.webp',
            EPM_BUSBAR: 'lv-busbar.webp',
            EPM_BUSDUCT: 'busway-busduct.webp',
            EPM_LV_PANEL: 'lv-panel-assembly.webp',
            EPM_TRANSFORMER: 'transformer.webp',
            EPM_MV_CABLE: 'mv-cable.webp',
            EPM_MV_RMU: 'ring-main-unit.webp',
            EPM_MV_SWG: 'mv-switchgear-panel.webp',
            EPM_VCB: 'vacuum-circuit-breaker.webp',
            EPM_MV_LBS: 'load-break-switch.webp',
            EPM_RELAY: 'protection-relay.webp',
            EPM_CT_METER: 'metering-ct.webp',
            EPM_CT_PROT: 'protection-ct.webp',
            EPM_VT: 'voltage-transformer-pt.webp',
            EPM_METER: 'bidirectional-energy-meter.webp',
            EPM_PQM: 'power-quality-meter.webp',
            EPM_EXPORT_CTRL: 'export-controller.webp',
            EPM_PLC: 'plc-plant-controller.webp',
            EPM_LOGGER: 'data-logger-gateway.webp',
            EPM_NET_SW: 'industrial-ethernet-switch.webp',
            EPM_ROUTER: 'industrial-router-gateway.webp',
            EPM_UPS: 'auxiliary-ups.webp',
            EPM_PYR: 'pyranometer.webp',
            EPM_TEMP_SENS: 'temperature-sensor.webp',
            EPM_ANEMO: 'wind-sensor.webp',
            EPM_WEATHER_STN: 'weather-station.webp',
            EPM_RAIL: 'pv-mounting-rail.webp',
            EPM_MID_CLAMP: 'module-mid-clamp.webp',
            EPM_END_CLAMP: 'module-end-clamp.webp',
            EPM_ROOF_HOOK: 'tile-roof-hook.webp',
            EPM_L_FOOT: 'metal-roof-l-foot.webp',
            EPM_HANGER_BOLT: 'hanger-bolt-attachment.webp',
            EPM_SEAM_CLAMP: 'standing-seam-clamp.webp',
            EPM_BALLAST: 'ballast-tray-block-system.webp',
            EPM_POST: 'ground-mount-post.webp',
            EPM_PILE: 'pile-screw-pile.webp',
            EPM_TRACKER: 'single-axis-tracker.webp',
            EPM_FOUND_CONC: 'concrete-foundation.webp',
            EPM_EARTH_COND: 'protective-bonding-conductor.webp',
            EPM_EARTH_ROD: 'earth-electrode-rod.webp',
            EPM_EARTH_BAR: 'earth-bar.webp',
            EPM_LPS_AIR: 'lightning-air-terminal.webp',
            EPM_LPS_DOWN: 'lightning-down-conductor.webp',
            EPM_TRAY: 'cable-tray.webp',
            EPM_CONDUIT: 'conduit-duct.webp',
            EPM_GLAND: 'cable-gland.webp',
            EPM_LUG: 'cable-lug-terminal.webp',
            EPM_LABEL: 'electrical-safety-label.webp',
            EPM_GENSET: 'generator-set.webp',
            EPM_FIRE_DET: 'fire-detector.webp',
            EPM_FIRE_SUP: 'fire-suppression-system.webp',
            EPM_HVAC_BESS: 'bess-hvac-cooling.webp',
            EPM_E_STOP: 'emergency-stop.webp',
        };

        const categoryIllustrations = {
            DEFAULT: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M46 34h88v58H46z"/><path d="M60 48h60M60 63h60M60 78h36"/><circle cx="130" cy="78" r="10"/></svg>',
            PV_MODULE: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M39 30h102l-10 64H49L39 30Z"/><path d="M56 30 50 94M73 30l-3 64M90 30v64M107 30l3 64M124 30l6 64M43 49h95M41 68h94M40 86h92"/><path d="M73 103h34M90 94v9"/></svg>',
            INVERTER_ON_GRID: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><rect x="50" y="18" width="80" height="88" rx="12"/><rect x="63" y="32" width="54" height="22" rx="5"/><path d="M72 75c8-14 15 14 24 0s16 14 24 0"/><path d="M72 91h36"/></svg>',
            INVERTER_HYBRID_OFFGRID: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><rect x="46" y="20" width="88" height="84" rx="10"/><path d="M61 39h58M61 54h38"/><path d="m72 83 13-17 9 11 15-22"/><circle cx="117" cy="83" r="7"/></svg>',
            BATTERY_ESS: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><rect x="48" y="24" width="84" height="78" rx="8"/><path d="M68 24v-7h44v7"/><path d="M64 43h52M64 60h52M64 77h52"/><path d="M79 94h22M90 84v20"/></svg>',
            DC_ELECTRICAL: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><circle cx="67" cy="63" r="28"/><circle cx="67" cy="63" r="12"/><path d="M95 63h42M112 47v32M126 52v22"/><path d="m56 63 8 8 16-19"/></svg>',
            AC_ELECTRICAL: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><rect x="48" y="22" width="84" height="82" rx="8"/><path d="M62 39h56M62 54h56M62 69h56"/><path d="M69 87h15M96 87h15"/><circle cx="76" cy="87" r="5"/><circle cx="103" cy="87" r="5"/></svg>',
            MOUNTING_STRUCTURE: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M34 82h112M49 82l20-45h42l20 45M61 56h58M55 69h70"/><path d="M67 37v45M111 37v45"/></svg>',
            MONITORING_METERING: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><rect x="48" y="24" width="84" height="78" rx="10"/><circle cx="90" cy="59" r="22"/><path d="M90 59l15-10M68 89h44"/><path d="M58 33h12M110 33h12"/></svg>',
            GROUNDING_LIGHTNING_SAFETY: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M90 16v51M68 67h44M75 78h30M82 89h16"/><path d="m107 24-17 24h14L88 71"/></svg>',
            PANEL_DISTRIBUTION: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><rect x="43" y="18" width="94" height="90" rx="6"/><path d="M58 35h64M58 52h64M58 69h64M58 86h64"/><circle cx="66" cy="35" r="3"/><circle cx="66" cy="52" r="3"/><circle cx="66" cy="69" r="3"/></svg>',
            CIVIL_SITE_WORK: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M32 92h116M47 92l12-31h62l12 31"/><path d="M63 61V39h54v22M77 39V26h26v13"/><path d="M57 76h66"/></svg>',
            FIRE_HSE: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M90 18c7 17-9 20 1 34 7-8 9-15 7-24 15 13 25 27 25 43 0 19-15 34-33 34S57 90 57 71c0-14 8-27 22-39-1 12 2 20 11 26"/><path d="M76 83c5 8 23 8 28 0"/></svg>',
            LEGAL_CERTIFICATION: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M52 22h58l18 18v64H52z"/><path d="M110 22v20h18M68 56h44M68 70h44M68 84h28"/><path d="m104 91 8 8 18-20"/></svg>',
            LOGISTICS_OM: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M36 42h72v44H36zM108 57h20l16 17v12h-36z"/><circle cx="61" cy="92" r="9"/><circle cx="124" cy="92" r="9"/><path d="M47 52h28M47 63h42"/></svg>',
            SUPPLIER_PRICE_QUOTE: '<svg viewBox="0 0 180 126" fill="none" aria-hidden="true"><path d="M47 20h86v86H47z"/><path d="M63 38h54M63 53h54M63 68h31"/><circle cx="107" cy="84" r="15"/><path d="M107 76v16M101 80h10M101 88h10"/></svg>',
        };

        const renderCreateArtwork = (categoryCode, profileCode) => {
            if (!createMode || !previewArtwork) return;

            const hasExactCategory = Boolean(categoryCode) && categoryCode !== 'DEFAULT';
            const classImageFile = hasExactCategory ? classImages[categoryCode] : null;
            const imageFile = classImageFile || (!hasExactCategory ? emptyCatalogImage : null);
            const imageBase = previewVisual?.dataset.nxmciImageBase || '';
            const usePreviewImage = Boolean(previewImage && imageFile && imageBase);
            const useClassImage = Boolean(classImageFile && usePreviewImage);
            const useEmptyCatalogImage = Boolean(!hasExactCategory && usePreviewImage);

            if (previewVisual) {
                previewVisual.classList.toggle('has-class-image', useClassImage);
                previewVisual.classList.toggle('has-preview-image', usePreviewImage);
                previewVisual.classList.toggle('is-empty-catalog-image', useEmptyCatalogImage);
            }
            previewArtwork.hidden = usePreviewImage;

            if (previewImage) {
                previewImage.hidden = !usePreviewImage;
                previewImage.classList.remove('is-loaded');
                if (usePreviewImage) {
                    const normalizedBase = imageBase.endsWith('/') ? imageBase.slice(0, -1) : imageBase;
                    previewImage.src = `${normalizedBase}/${imageFile}`;
                    previewImage.onload = () => previewImage.classList.add('is-loaded');
                    previewImage.onerror = () => {
                        previewImage.hidden = true;
                        previewImage.removeAttribute('src');
                        previewImage.classList.remove('is-loaded');
                        previewArtwork.hidden = false;
                        previewVisual?.classList.remove('has-class-image', 'has-preview-image', 'is-empty-catalog-image');
                    };
                } else {
                    previewImage.removeAttribute('src');
                }
            }

            if (!usePreviewImage) {
                const key = categoryIllustrations[profileCode] ? profileCode : 'DEFAULT';
                previewArtwork.innerHTML = categoryIllustrations[key];
            }
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
            input.setAttribute('aria-label', `${field.label || humanize(field.key)}${field.required ? ' · required technical field' : (field.conditional ? ' · subtype dependent' : ' · optional')}`);
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
            const fieldKind = field.input_type === 'textarea' ? ' is-longform' : '';
            const fieldNode = createElement('div', `nxspec-field ${fieldMode}${fieldKind}`);
            const heading = createElement('div', 'nxspec-field-head');
            const label = createElement('label', 'nxmc-label', field.label || humanize(field.key));
            label.htmlFor = `technical_spec_${field.key}`;
            const singleChoice = field.input_type === 'select' && Object.keys(field.options || {}).length === 1;
            const stateText = createMode && singleChoice
                ? 'Fixed by class'
                : (createMode && field.required ? '' : (field.required ? 'Required' : (field.conditional ? 'Conditional' : 'Optional')));
            heading.appendChild(label);
            if (stateText) {
                const state = createElement('span', `nxspec-field-state ${fieldMode}${createMode && singleChoice ? ' is-fixed' : ''}`, stateText);
                heading.appendChild(state);
            }

            const control = createElement('div', `nxspec-control${field.unit ? ' has-unit' : ''}`);
            const input = createFieldInput(field);
            control.appendChild(input);
            if (createMode && field.input_type === 'select') {
                enhancePremiumSelect(input, {
                    label: field.label || humanize(field.key),
                    meta: 'Choose the documented value',
                    selectedMeta: 'Recorded selection',
                    fixedMeta: 'Fixed by component class'
                });
            }
            if (field.unit) control.appendChild(createElement('span', 'nxspec-unit', field.unit));

            fieldNode.append(heading, control);
            const shouldShowHelper = field.show_helper !== false || (createMode && singleChoice);
            if (shouldShowHelper) {
                fieldNode.classList.add('has-helper');
                const helpCopy = createMode && singleChoice
                    ? `${field.description || 'This value follows the selected component class.'} The value is fixed by the selected class; no typical value is inserted.`
                    : (field.description || 'Record the documented technical value.');
                fieldNode.appendChild(createElement('p', 'nxmc-field-hint', helpCopy));
            }

            if (fieldErrors[field.key]?.length) {
                fieldErrors[field.key].forEach((message) => fieldNode.appendChild(createElement('div', 'nxmc-field-error', message)));
            }

            return fieldNode;
        };

        const updateCreatePreview = () => {
            if (!createMode || !previewTitle || !previewCopy) return;
            const selected = categorySelect?.selectedOptions?.[0];
            const schema = currentSchema();
            const rawLabel = String(selected?.textContent || '').replace(/^—\s*/, '').trim();
            const hasExactCategory = Boolean(selected?.value) && optionCode() !== 'DEFAULT';
            const materialName = String(editorForm.querySelector('#name')?.value || '').trim();
            const brand = String(editorForm.querySelector('#brand')?.value || '').trim();
            const modelValue = String(editorForm.querySelector('#model')?.value || '').trim();
            const sku = String(editorForm.querySelector('#sku')?.value || '').trim();
            const required = Number(schema.required_count || schema.required_specs?.length || 0);
            const conditional = Number(schema.conditional_count || schema.conditional_specs?.length || 0);
            const optional = Number(schema.optional_count || 0);

            if (previewKicker) previewKicker.textContent = hasExactCategory ? 'Live catalog identity' : 'Engineering catalog';
            previewTitle.textContent = hasExactCategory ? (materialName || rawLabel) : 'Select a component class';
            if (previewProfile) previewProfile.textContent = hasExactCategory ? rawLabel : '89 exact classes';
            if (previewBrand) previewBrand.textContent = brand || 'Not recorded';
            if (previewModel) previewModel.textContent = modelValue || 'Not recorded';
            if (previewSku) previewSku.textContent = sku || 'Not recorded';
            previewCopy.textContent = hasExactCategory
                ? `${schema.title || rawLabel} requires ${required} documented technical ${required === 1 ? 'field' : 'fields'}${conditional ? `, plus ${conditional} conditional` : ''}${optional ? ` and ${optional} optional` : ''}. Record only manufacturer-backed data.`
                : 'Choose one of 89 exact engineering classes to load its technical requirements and class preview.';

            if (previewVisual) {
                previewVisual.dataset.profile = optionProfileCode();
                previewVisual.dataset.category = optionCode();
            }
            renderCreateArtwork(optionCode(), optionProfileCode());
        };

        const renderSpecifications = () => {
            collectSpecificationValues();
            const schema = currentSchema();
            const groups = Array.isArray(schema.groups) ? schema.groups : [];
            if (!dynamicRoot) return;
            dynamicRoot.replaceChildren();

            if (contextTitle) contextTitle.textContent = schema.title || 'Technical requirements';
            if (contextCopy) contextCopy.textContent = schema.description || schemas.DEFAULT.description;
            if (profileSpecCount) profileSpecCount.textContent = String(schema.required_count ?? schema.required_specs?.length ?? 0);
            if (profileConditionalCount) profileConditionalCount.textContent = String(schema.conditional_count ?? schema.conditional_specs?.length ?? 0);
            if (profileOptionalCount) profileOptionalCount.textContent = String(schema.optional_count ?? 0);
            if (schemaStatus) schemaStatus.textContent = schema.code === 'DEFAULT' ? 'Select a component class' : (schema.title || 'Engineering data checklist');
            if (schemaAuthority) schemaAuthority.textContent = schema.master_reference ? 'Exact component requirements' : (schema.code === 'DEFAULT' ? 'Select component class' : 'Catalog requirements');
            if (railSchemaState) railSchemaState.textContent = schema.code === 'DEFAULT' ? 'Select component' : 'Class selected';
            if (railConditional) railConditional.textContent = String(schema.conditional_count ?? schema.conditional_specs?.length ?? 0);
            const conditionalCount = Number(schema.conditional_count ?? schema.conditional_specs?.length ?? 0);
            const optionalCount = Number(schema.optional_count ?? 0);
            editorForm.querySelectorAll('[data-nxmci-rail-metric="conditional"]').forEach((node) => { node.hidden = conditionalCount === 0; });
            editorForm.querySelectorAll('[data-nxmci-profile-metric="conditional"]').forEach((node) => { node.hidden = conditionalCount === 0; });
            editorForm.querySelectorAll('[data-nxmci-profile-metric="optional"]').forEach((node) => { node.hidden = optionalCount === 0; });
            if (railSchemaCopy) railSchemaCopy.textContent = schema.code === 'DEFAULT'
                ? 'Select a component class to see the required engineering data and what is still missing.'
                : `${schema.required_count || 0} required engineering ${(schema.required_count || 0) === 1 ? 'field' : 'fields'} for ${schema.title}. Missing values may remain in Draft and must be completed before approval.`;
            updateCreatePreview();

            if (profileSpecCopy) {
                const requiredCount = Number(schema.required_count || schema.required_specs?.length || 0);
                const groupCount = groups.length;
                profileSpecCopy.textContent = requiredCount
                    ? `${requiredCount} required engineering ${requiredCount === 1 ? 'field' : 'fields'} across ${groupCount} ${groupCount === 1 ? 'section' : 'sections'}. Record documented manufacturer or supplier data only.`
                    : (schema.code === 'DEFAULT'
                        ? 'Select a component class to load its exact technical requirements.'
                        : 'No required technical fields are assigned to this profile.');
            }
            const evidence = [...(schema.required_certificates || []), ...(schema.required_documents || []).map(humanize)];
            if (profileEvidenceCopy) profileEvidenceCopy.textContent = evidence.length
                ? evidence.join(' · ')
                : (schema.code === 'DEFAULT' ? 'Evidence requirements load with the selected component class.' : 'No mandatory evidence pack is assigned to this category.');

            if (schemaGroupNav) {
                schemaGroupNav.replaceChildren();
                schemaGroupNav.hidden = groups.length === 0;
            }

            if (!groups.length) {
                const empty = createElement('div', 'nxspec-empty');
                const icon = createElement('span');
                icon.setAttribute('aria-hidden', 'true');
                icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>';
                empty.append(icon, createElement('strong', '', 'Select an engineering component class.'), createElement('p', '', 'NEXORIN will load the technical fields and units for the selected component type.'));
                dynamicRoot.appendChild(empty);
            } else {
                groups.forEach((group, index) => {
                    const section = createElement('section', 'nxspec-group');
                    const groupAccents = [
                        ['#39d1c6', 'rgba(57, 209, 198, .12)'],
                        ['#56b8e8', 'rgba(86, 184, 232, .11)'],
                        ['#66cfc8', 'rgba(102, 207, 200, .10)'],
                    ];
                    const [groupAccent, groupAccentSoft] = groupAccents[index % groupAccents.length];
                    section.style.setProperty('--nxmci-group-accent', groupAccent);
                    section.style.setProperty('--nxmci-group-accent-soft', groupAccentSoft);
                    const groupId = `nxmci-spec-${String(schema.code || 'general').toLowerCase()}-${String(group.key || index).replace(/[^a-z0-9-]/gi, '-').toLowerCase()}`;
                    section.id = groupId;
                    section.dataset.nxmciSpecGroup = group.key || String(index);
                    const header = createElement('header', 'nxspec-group-head');
                    const identity = createElement('div', 'nxspec-group-identity');
                    const indexNode = createElement('span', 'nxspec-group-index', String(index + 1).padStart(2, '0'));
                    const copy = createElement('div');
                    copy.append(createElement('h4', '', group.label || humanize(group.key)), createElement('p', '', group.description || 'Category-specific technical attributes.'));
                    identity.append(indexNode, copy);
                    const fieldCount = group.fields?.length || 0;
                    const requiredInGroup = (group.fields || []).filter((field) => field.required).length;
                    const counter = createElement('span', 'nxspec-group-count', requiredInGroup ? `0 / ${requiredInGroup} recorded` : `${fieldCount} ${fieldCount === 1 ? 'field' : 'fields'}`);
                    counter.dataset.nxmcGroupCounter = group.key || String(index);
                    counter.dataset.requiredCount = String(requiredInGroup);
                    header.append(identity, counter);
                    const grid = createElement('div', 'nxspec-field-grid');
                    const longformCount = (group.fields || []).filter((field) => field.input_type === 'textarea').length;
                    if (fieldCount === 1) section.classList.add('is-single');
                    if (fieldCount > 1 && fieldCount % 2 === 1) section.classList.add('has-odd-count');
                    if (fieldCount <= 2) section.classList.add('is-compact');
                    if (longformCount) section.classList.add('has-longform');
                    if (fieldCount === 2 && longformCount === 1) section.classList.add('is-balanced-pair');
                    (group.fields || []).forEach((field, fieldIndex) => {
                        const fieldNode = createSpecificationField(field);
                        const hasRowPartner = fieldIndex % 2 === 1 || fieldIndex + 1 < fieldCount;
                        if (field.input_type === 'textarea' && hasRowPartner) fieldNode.classList.add('is-paired-longform');
                        if (fieldCount > 1 && fieldCount % 2 === 1 && fieldIndex === fieldCount - 1) fieldNode.classList.add('is-wide');
                        grid.appendChild(fieldNode);
                    });
                    section.append(header, grid);
                    dynamicRoot.appendChild(section);

                    if (schemaGroupNav) {
                        const anchor = createElement('a', 'nxmci-spec-group-link', group.label || humanize(group.key));
                        anchor.href = `#${groupId}`;
                        anchor.dataset.nxmciGroupTarget = groupId;
                        anchor.addEventListener('click', (event) => {
                            event.preventDefault();
                            section.scrollIntoView({ behavior: reducedScroll ? 'auto' : 'smooth', block: 'start' });
                        });
                        schemaGroupNav.appendChild(anchor);
                    }
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

            const setActiveGroup = (section) => {
                if (!schemaGroupNav || !section?.id) return;
                schemaGroupNav.querySelectorAll('[data-nxmci-group-target]').forEach((link) => {
                    link.classList.toggle('is-active', link.dataset.nxmciGroupTarget === section.id);
                });
            };
            schemaGroupObserver?.disconnect();
            const renderedGroups = [...dynamicRoot.querySelectorAll('.nxspec-group')];
            if (renderedGroups.length) {
                setActiveGroup(renderedGroups[0]);
                renderedGroups.forEach((section) => section.addEventListener('focusin', () => setActiveGroup(section)));
                if ('IntersectionObserver' in window) {
                    schemaGroupObserver = new IntersectionObserver((entries) => {
                        const visible = entries
                            .filter((entry) => entry.isIntersecting)
                            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                        if (visible?.target) setActiveGroup(visible.target);
                    }, { rootMargin: '-22% 0px -58% 0px', threshold: [0.05, 0.2, 0.45] });
                    renderedGroups.forEach((section) => schemaGroupObserver.observe(section));
                }
            }
            updateSpecificationProgress();
        };

        const updateSpecificationProgress = () => {
            const requiredInputs = [...(dynamicRoot?.querySelectorAll('[data-nxmc-spec-required="true"]') || [])];
            const completeRequired = requiredInputs.filter(hasInputValue).length;
            const allInputs = [...(dynamicRoot?.querySelectorAll('[data-nxmc-spec-input]') || [])];
            const completeAll = allInputs.filter(hasInputValue).length;
            const percentage = requiredInputs.length ? Math.round((completeRequired / requiredInputs.length) * 100) : (optionCode() === 'DEFAULT' ? 0 : 100);
            if (specProgressFill) specProgressFill.style.width = `${percentage}%`;
            if (specProgressCopy) specProgressCopy.textContent = `${completeRequired} of ${requiredInputs.length} recorded`;
            if (profileCompleteCount) profileCompleteCount.textContent = String(completeAll);
            allInputs.forEach((input) => input.closest('.nxspec-field')?.classList.toggle('is-recorded', hasInputValue(input)));
            if (railRequired) railRequired.textContent = String(requiredInputs.length);
            if (railRecorded) railRecorded.textContent = String(completeRequired);
            if (railMissing) railMissing.textContent = String(Math.max(requiredInputs.length - completeRequired, 0));

            dynamicRoot?.querySelectorAll('.nxspec-group').forEach((section) => {
                const required = [...section.querySelectorAll('[data-nxmc-spec-required="true"]')];
                const recorded = required.filter(hasInputValue).length;
                const counter = section.querySelector('[data-nxmc-group-counter]');
                if (!counter) return;
                if (required.length) {
                    counter.textContent = `${recorded} of ${required.length} recorded`;
                    counter.classList.toggle('is-complete', recorded === required.length);
                    section.classList.toggle('is-complete', recorded === required.length);
                    section.style.setProperty('--nxmci-group-progress', `${Math.round((recorded / required.length) * 100)}%`);
                }
            });
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

        const updateCreateRequiredInputs = () => {
            if (!createMode || !requiredDraftNodes.length) return;
            let complete = 0;
            requiredDraftNodes.forEach((node) => {
                const key = node.dataset.nxmciRequiredField;
                const isComplete = hasValue(`#${key}`);
                node.classList.toggle('is-complete', isComplete);
                complete += isComplete ? 1 : 0;
            });
            const missing = requiredDraftNodes.length - complete;
            if (missingCopy) missingCopy.textContent = missing === 0 ? 'Draft creation inputs are complete.' : `${missing} required ${missing === 1 ? 'input remains' : 'inputs remain'}.`;
            return { complete, total: requiredDraftNodes.length };
        };

        const updateReadiness = () => {
            const state = completionState();
            let complete;
            let total;

            if (createMode) {
                const draft = updateCreateRequiredInputs() || { complete: 0, total: 5 };
                complete = draft.complete;
                total = draft.total;
                const percentage = total > 0 ? Math.round((complete / total) * 100) : 0;
                if (progressFill) progressFill.style.width = `${percentage}%`;
                if (progressScore) progressScore.textContent = `${complete} / ${total}`;
            } else {
                const scoredSections = [state.identity, state.applicability, state.technical];
                complete = scoredSections.reduce((sum, section) => sum + section.complete, 0);
                total = scoredSections.reduce((sum, section) => sum + section.total, 0);
                const percentage = total > 0 ? Math.round((complete / total) * 100) : 0;
                if (progressFill) progressFill.style.width = `${percentage}%`;
                if (progressScore) progressScore.textContent = `${percentage}%`;
            }

            const labels = {
                identity: state.identity.complete === state.identity.total ? 'Complete' : `${state.identity.complete} / ${state.identity.total}`,
                applicability: state.applicability.complete === state.applicability.total
                    ? (createMode ? 'Draft settings ready' : 'Complete')
                    : `${state.applicability.complete} / ${state.applicability.total}`,
                technical: !state.technical.exact
                    ? 'Class required'
                    : (createMode && state.technical.total === 0
                        ? 'No required fields'
                        : (state.technical.complete === state.technical.total ? 'Complete' : `${state.technical.complete} / ${state.technical.total}`)),
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
            actionBar?.classList.toggle('is-dirty', dirty);
            if (actionCopy) actionCopy.textContent = dirty ? 'Unsaved material changes are ready to save.' : 'No unsaved changes detected.';
            if (cancelLink) {
                if (dirty) cancelLink.dataset.confirm = 'Leave this material form and discard the unsaved changes?';
                else delete cancelLink.dataset.confirm;
            }
        };

        editorForm.addEventListener('input', () => { updateCreatePreview(); updateReadiness(); updateDirtyState(); });
        editorForm.addEventListener('change', () => { updateCreatePreview(); updateReadiness(); updateDirtyState(); });
        categorySelect?.addEventListener('change', () => {
            syncCategoryTrigger();
            renderCategoryPickerOptions();
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
            showAction({
                kind: 'save', icon: 'save',
                title: mode === 'edit' ? 'Saving Material Revision' : 'Creating Draft Material',
                copy: mode === 'edit'
                    ? 'NEXORIN is validating identity, applicability, category-specific technical attributes, and catalog governance before recording the material.'
                    : 'NEXORIN is checking material identity, component class, and entered technical values before creating the draft. Approval and evidence remain separate steps.',
                signal: 'NEXORIN material catalog', eyebrow: 'Creating material draft',
                metaLeft: editorForm.querySelector('#sku')?.value || 'New catalog item',
                metaRight: currentSchema().title || editorForm.querySelector('#name')?.value || 'Material identity',
                steps: ['Checking required material identity', 'Loading component requirements', 'Saving entered technical values', mode === 'edit' ? 'Recording the material update' : 'Creating Draft · Needs Review'],
                duration: 1080, accent: '#13aaa2', secondary: '#f3a04f',
            });
        });

        window.addEventListener('beforeunload', (event) => {
            if (submitting || !formChanged()) return;
            event.preventDefault();
            event.returnValue = '';
        });

        updateSpecificationProgress();
        updateCreatePreview();
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
