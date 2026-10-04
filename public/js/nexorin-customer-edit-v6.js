(() => {
    'use strict';

    const root = document.querySelector('[data-nxce9]');
    if (!root) return;
    const form = root.querySelector('[data-customer-edit-form]');
    if (!form) return;

    const saveState = root.querySelector('[data-save-state]');
    const review = form.querySelector('[data-review-ack]');
    const crmReady = root.dataset.crmReady === 'true';
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    let submitting = false;
    const topZone = root.querySelector('[data-top-zone]');
    const profileCard = root.querySelector('[data-profile-card]');
    const rightRail = root.querySelector('.nxce9-rail');
    const businessDescription = root.querySelector('[data-business-description]');
    const desktopRail = window.matchMedia('(min-width: 1051px)');
    let balanceFrame = 0;

    // Page 009 luxury interaction layer. Content remains visible without JavaScript.
    const luxurySurfaces = [...root.querySelectorAll('.nxce9-card, .nxce9-review')];
    luxurySurfaces.forEach((surface, index) => {
        surface.dataset.luxuryReveal = '';
        surface.style.setProperty('--nx9-reveal-order', String(index));
    });

    root.classList.add('js-luxury-ready');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        luxurySurfaces.forEach((surface) => surface.classList.add('is-luxury-visible'));
    } else {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-luxury-visible');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });
        luxurySurfaces.forEach((surface) => revealObserver.observe(surface));
    }

    if (!reduceMotion) {
        luxurySurfaces.forEach((surface) => {
            surface.addEventListener('pointermove', (event) => {
                if (event.pointerType === 'touch') return;
                const rect = surface.getBoundingClientRect();
                surface.style.setProperty('--nx9-spot-x', `${event.clientX - rect.left}px`);
                surface.style.setProperty('--nx9-spot-y', `${event.clientY - rect.top}px`);
            }, { passive: true });
            surface.addEventListener('pointerleave', () => {
                surface.style.removeProperty('--nx9-spot-x');
                surface.style.removeProperty('--nx9-spot-y');
            }, { passive: true });
        });

        rightRail?.addEventListener('pointermove', (event) => {
            if (event.pointerType === 'touch') return;
            const rect = rightRail.getBoundingClientRect();
            rightRail.style.setProperty('--nx9-rail-x', `${event.clientX - rect.left}px`);
            rightRail.style.setProperty('--nx9-rail-y', `${event.clientY - rect.top}px`);
        }, { passive: true });
    }

    const balanceBusinessDescription = () => {
        if (!businessDescription) return;
        window.cancelAnimationFrame(balanceFrame);
        balanceFrame = window.requestAnimationFrame(() => {
            // The V6 edit layout now uses a full editor grid beside a continuous intelligence rail.
            // Do not stretch Business Description to match the entire rail height.
            businessDescription.style.minHeight = '';
        });
    };


    const normalizedSnapshot = () => {
        const rows = [];
        const data = new FormData(form);
        for (const [key, value] of data.entries()) {
            if (key === '_token') continue;
            rows.push([key, value instanceof File ? `${value.name}:${value.size}:${value.lastModified}` : String(value)]);
        }
        return JSON.stringify(rows);
    };
    let initialSnapshot = '';

    const setState = (state) => {
        const dirty = state === 'dirty';
        root.classList.toggle('is-dirty', dirty);
        if (!saveState) return;
        saveState.dataset.state = state;
        const text = saveState.querySelector('span');
        if (!text) return;
        if (state === 'dirty') text.textContent = 'Unsaved changes';
        if (state === 'saving') text.textContent = 'Saving changes…';
    };

    const autoGrow = (field) => {
        if (!field?.matches?.('[data-autogrow]')) return;
        field.style.height = 'auto';
        const targetHeight = Math.max(40, Math.min(field.scrollHeight, 116));
        field.style.height = `${targetHeight}px`;
        field.style.overflowY = field.scrollHeight > 116 ? 'auto' : 'hidden';
    };
    form.querySelectorAll('[data-autogrow]').forEach(autoGrow);
    form.addEventListener('input', (event) => autoGrow(event.target));

    balanceBusinessDescription();
    window.addEventListener('resize', balanceBusinessDescription, { passive: true });
    document.fonts?.ready?.then(balanceBusinessDescription);
    rightRail?.querySelectorAll('img').forEach((image) => {
        if (!image.complete) image.addEventListener('load', balanceBusinessDescription, { once: true });
    });

    form.querySelectorAll('input:not([type="hidden"]):not([type="password"])').forEach((field) => {
        if (field.value) field.title = field.value;
        field.addEventListener('input', () => field.title = field.value || '');
    });

    const updateDirty = () => {
        if (submitting) return;
        if (normalizedSnapshot() !== initialSnapshot) setState('dirty');
        else {
            root.classList.remove('is-dirty');
            if (saveState) saveState.dataset.state = 'saved';
        }
    };

    form.addEventListener('input', updateDirty);
    form.addEventListener('change', updateDirty);

    const scrollToSection = (id) => {
        const section = document.getElementById(id);
        if (!section) return;
        section.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    };

    const navButtons = [...root.querySelectorAll('[data-nav-anchor]')];

    root.querySelectorAll('[data-rail-jump]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const targetId = link.dataset.railJump;
            navButtons.forEach((item) => {
                const active = item.dataset.navAnchor === targetId;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-current', active ? 'page' : 'false');
            });
            scrollToSection(targetId);
        });
    });

    navButtons.forEach((button, index) => {
        button.setAttribute('aria-current', index === 0 ? 'page' : 'false');
        button.addEventListener('click', () => {
            navButtons.forEach((item) => {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-current', active ? 'page' : 'false');
            });
            scrollToSection(button.dataset.navAnchor);
        });
    });

    if ('IntersectionObserver' in window) {
        const sections = [...root.querySelectorAll('[data-section]')];
        const sectionObserver = new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (!visible?.target?.id) return;
            navButtons.forEach((item) => {
                const active = item.dataset.navAnchor === visible.target.id;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-current', active ? 'page' : 'false');
            });
        }, { rootMargin: '-18% 0px -68% 0px', threshold: [0.01, 0.15, 0.35] });
        sections.forEach((section) => sectionObserver.observe(section));
    }

    const nextIndex = (selector, token) => {
        let max = -1;
        root.querySelectorAll(selector).forEach((node) => {
            node.querySelectorAll('[name]').forEach((field) => {
                const match = field.name.match(new RegExp(`${token}\\[(\\d+)\\]`));
                if (match) max = Math.max(max, Number(match[1]));
            });
        });
        return max + 1;
    };

    const hydrateTemplate = (template, index) => {
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[data-name]').forEach((field) => {
            field.name = field.dataset.name.replace('__INDEX__', String(index));
            field.removeAttribute('data-name');
        });
        return fragment;
    };

    const contactList = root.querySelector('[data-contact-list]');
    const contactTemplate = root.querySelector('[data-contact-template]');
    const contactEmpty = () => contactList?.querySelector('[data-contact-empty]');
    root.querySelector('[data-add-contact]')?.addEventListener('click', () => {
        if (!contactList || !contactTemplate || !crmReady) return;
        contactEmpty()?.remove();
        const index = nextIndex('[data-contact-row]', 'contacts');
        contactList.appendChild(hydrateTemplate(contactTemplate, index));
        const addedContact = contactList.querySelector('[data-contact-row]:last-child');
        addedContact?.classList.add('is-new');
        if (addedContact && !reduceMotion) window.setTimeout(() => addedContact.classList.remove('is-new'), 360);
        addedContact?.querySelector(':is(input,textarea):not([type="hidden"])')?.focus();
        updateDirty();
    });

    root.addEventListener('click', (event) => {
        const removeContact = event.target.closest('[data-remove-contact]');
        if (removeContact) {
            const row = removeContact.closest('[data-contact-row]');
            if (!row) return;
            if (row.dataset.existing === 'true') {
                const flag = row.querySelector('[data-remove-flag]');
                const removing = flag?.value !== '1';
                if (flag) flag.value = removing ? '1' : '0';
                row.classList.toggle('is-removing', removing);
                removeContact.textContent = removing ? 'Undo' : 'Remove';
            } else {
                row.remove();
            }
            updateDirty();
        }

        const removeOpportunity = event.target.closest('[data-remove-new-opportunity]');
        if (removeOpportunity) {
            removeOpportunity.closest('[data-opportunity-row]')?.remove();
            updateDirty();
        }
    });

    root.addEventListener('change', (event) => {
        if (!event.target.matches('[data-primary-contact]') || !event.target.checked) return;
        root.querySelectorAll('[data-primary-contact]').forEach((checkbox) => {
            if (checkbox !== event.target) checkbox.checked = false;
        });
        updateDirty();
    });

    const opportunityList = root.querySelector('[data-opportunity-list]');
    const opportunityTemplate = root.querySelector('[data-opportunity-template]');
    root.querySelector('[data-add-opportunity]')?.addEventListener('click', () => {
        if (!opportunityList || !opportunityTemplate || !crmReady) return;
        opportunityList.querySelector('[data-opportunity-empty]')?.remove();
        const yes = form.querySelector('input[name="opportunity_assessment"][value="has_opportunity"]');
        if (yes && !yes.disabled) yes.checked = true;
        const index = nextIndex('[data-opportunity-row]', 'opportunities');
        opportunityList.appendChild(hydrateTemplate(opportunityTemplate, index));
        const addedOpportunity = opportunityList.querySelector('[data-opportunity-row]:last-child');
        addedOpportunity?.classList.add('is-new');
        if (addedOpportunity && !reduceMotion) window.setTimeout(() => addedOpportunity.classList.remove('is-new'), 360);
        addedOpportunity?.querySelector(':is(input,textarea):not([type="hidden"])')?.focus();
        updateDirty();
    });

    const parentFields = [...root.querySelectorAll('[data-parent-fields] input, [data-parent-fields] select')];
    const syncParentFields = () => {
        const value = form.querySelector('input[name="parent_company_assessment"]:checked')?.value || 'not_assessed';
        parentFields.forEach((field) => field.disabled = !crmReady || value !== 'has_parent');
    };
    form.querySelectorAll('input[name="parent_company_assessment"]').forEach((radio) => radio.addEventListener('change', syncParentFields));
    syncParentFields();
    form.querySelectorAll('[data-autogrow]').forEach(autoGrow);
    initialSnapshot = normalizedSnapshot();
    if (root.dataset.validationErrors === 'true') setState('dirty');

    const category = root.querySelector('[data-doc-category]');
    const drop = root.querySelector('[data-doc-drop]');
    const docTitle = root.querySelector('[data-doc-title]');
    const docStatus = root.querySelector('[data-doc-status]');
    const docAction = root.querySelector('[data-doc-action]');
    const stagedList = root.querySelector('[data-doc-staged-list]');
    const stagedItems = root.querySelector('[data-doc-staged-items]');
    const stagedCount = root.querySelector('[data-doc-staged-count]');
    const documentInputs = [...root.querySelectorAll('[data-doc-input]')];

    const currentDocInput = () => {
        const value = category?.value || '';
        if (!value) return null;
        return root.querySelector(`[data-doc-input="${CSS.escape(value)}"]`);
    };

    const categoryLabel = (value) => {
        if (!value || !category) return 'Supporting Document';
        return category.querySelector(`option[value="${CSS.escape(value)}"]`)?.textContent?.trim() || 'Supporting Document';
    };

    const formatFileSize = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
        if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        return `${(bytes / 1024 / 1024).toFixed(bytes >= 10 * 1024 * 1024 ? 0 : 1)} MB`;
    };

    const validateDocumentFile = (input, file) => {
        if (!input || !file) return null;
        if (file.size > 25 * 1024 * 1024) return 'File exceeds the 25 MB upload limit.';
        const accepted = (input.accept || '')
            .split(',')
            .map((value) => value.trim().toLowerCase())
            .filter((value) => value.startsWith('.'));
        const lowerName = file.name.toLowerCase();
        if (accepted.length && !accepted.some((extension) => lowerName.endsWith(extension))) {
            return `File type is not supported for ${categoryLabel(input.dataset.docInput)}.`;
        }
        return null;
    };

    const showDocumentError = (fileName, message) => {
        drop?.classList.remove('has-file', 'is-category-required');
        drop?.classList.add('is-error');
        if (docTitle) docTitle.textContent = fileName || 'File not added';
        if (docStatus) docStatus.textContent = message;
        if (docAction) docAction.textContent = 'Choose Again';
    };

    const renderStagedUploads = () => {
        if (!stagedList || !stagedItems || !stagedCount) return;
        const staged = documentInputs
            .map((input) => ({ input, file: input.files?.[0] }))
            .filter((item) => item.file);

        stagedItems.replaceChildren();
        stagedList.hidden = staged.length === 0;
        stagedCount.textContent = `${staged.length} ${staged.length === 1 ? 'file' : 'files'}`;

        staged.forEach(({ input, file }) => {
            const row = document.createElement('div');
            row.className = 'nxce9-doc-staged-row';

            const icon = document.createElement('i');
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = 'DOC';

            const copy = document.createElement('span');
            const name = document.createElement('strong');
            name.textContent = file.name;
            name.title = file.name;
            const meta = document.createElement('small');
            meta.textContent = `${categoryLabel(input.dataset.docInput)} · ${formatFileSize(file.size)} · Ready to add on save`;
            copy.append(name, meta);

            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'nxce9-doc-staged-remove';
            clear.dataset.docClearCategory = input.dataset.docInput || '';
            clear.textContent = 'Remove';
            clear.setAttribute('aria-label', `Remove ${file.name} from pending uploads`);

            row.append(icon, copy, clear);
            stagedItems.appendChild(row);
        });
    };

    const syncCurrentDocumentState = () => {
        if (!docTitle || !docStatus || !docAction) return;
        const input = currentDocInput();
        const file = input?.files?.[0];

        drop?.classList.remove('is-category-required', 'has-file', 'is-error');
        if (!category?.value) {
            docTitle.textContent = 'Drop a file here';
            docStatus.textContent = 'Select a category, then add a supported file · Max 25 MB';
            docAction.textContent = 'Browse';
            return;
        }

        if (file) {
            drop?.classList.add('has-file');
            docTitle.textContent = file.name;
            docStatus.textContent = `${categoryLabel(category.value)} · ${formatFileSize(file.size)} · Ready to add on save`;
            docAction.textContent = 'Replace';
            return;
        }

        docTitle.textContent = 'Drop a file here';
        docStatus.textContent = `${input?.dataset.docFormat || 'Supported file'} · Max 25 MB`;
        docAction.textContent = 'Browse';
    };

    const requireDocumentCategory = () => {
        if (category?.value) return true;
        drop?.classList.add('is-category-required');
        if (docStatus) docStatus.textContent = 'Select a document category before choosing a file.';
        category?.focus();
        return false;
    };

    const openPicker = () => {
        if (!requireDocumentCategory()) return;
        currentDocInput()?.click();
    };

    drop?.addEventListener('click', (event) => {
        event.preventDefault();
        openPicker();
    });

    ['dragenter', 'dragover'].forEach((name) => drop?.addEventListener(name, (event) => {
        event.preventDefault();
        if (category?.value) drop.classList.add('is-drag');
    }));
    ['dragleave', 'drop'].forEach((name) => drop?.addEventListener(name, (event) => {
        event.preventDefault();
        drop.classList.remove('is-drag');
    }));

    drop?.addEventListener('drop', (event) => {
        const file = event.dataTransfer?.files?.[0];
        if (!file || !requireDocumentCategory()) return;
        const input = currentDocInput();
        if (!input) return;
        const validationMessage = validateDocumentFile(input, file);
        if (validationMessage) {
            showDocumentError(file.name, validationMessage);
            return;
        }
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    category?.addEventListener('change', syncCurrentDocumentState);

    documentInputs.forEach((input) => input.addEventListener('change', () => {
        const file = input.files?.[0];
        const validationMessage = validateDocumentFile(input, file);
        if (file && validationMessage) {
            input.value = '';
            renderStagedUploads();
            showDocumentError(file.name, validationMessage);
            updateDirty();
            return;
        }
        renderStagedUploads();
        syncCurrentDocumentState();
        updateDirty();
    }));

    stagedItems?.addEventListener('click', (event) => {
        const clear = event.target.closest('[data-doc-clear-category]');
        if (!clear) return;
        const input = root.querySelector(`[data-doc-input="${CSS.escape(clear.dataset.docClearCategory || '')}"]`);
        if (!input) return;
        input.value = '';
        renderStagedUploads();
        syncCurrentDocumentState();
        updateDirty();
    });

    renderStagedUploads();
    syncCurrentDocumentState();

    root.querySelectorAll('[data-discard]').forEach((link) => link.addEventListener('click', (event) => {
        if (!root.classList.contains('is-dirty')) return;
        const confirmed = window.NexorinConfirm ? window.NexorinConfirm('Discard unsaved customer changes?') : window.confirm('Discard unsaved customer changes?');
        if (!confirmed) event.preventDefault();
    }));

    form.addEventListener('submit', (event) => {
        if (!crmReady) {
            event.preventDefault();
            return;
        }
        if (!review?.checked) {
            event.preventDefault();
            scrollToSection('review');
            review?.focus();
            return;
        }
        submitting = true;
        setState('saving');
        form.querySelectorAll('[data-save-top], [data-save-bottom]').forEach((button) => {
            button.disabled = true;
            button.textContent = 'Saving…';
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (!submitting && root.classList.contains('is-dirty')) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
})();
