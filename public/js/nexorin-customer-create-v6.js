(() => {
    'use strict';

    const root = document.querySelector('[data-nxcus8]');
    const form = document.querySelector('[data-customer-form]');
    if (!root || !form) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const crmReady = root.dataset.crmReady === 'true';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const draftUrl = root.dataset.draftUrl || '';
    const draftStatus = root.querySelector('[data-draft-status]');
    const draftStatusCopy = root.querySelector('[data-draft-status-copy]');
    const saveDraftButton = root.querySelector('[data-save-draft]');
    const reviewAck = form.querySelector('[data-review-ack]');
    const probability = form.querySelector('input[name="opportunity_probability_pct"]');
    const contactList = form.querySelector('[data-contact-list]');
    const contactTemplate = form.querySelector('[data-contact-template]');
    const contactEmpty = form.querySelector('[data-contact-empty]');
    const addContact = form.querySelector('[data-add-contact]');
    const parentFields = form.querySelector('[data-parent-fields]');
    const parentEmpty = form.querySelector('[data-parent-empty]');
    const opportunityFields = form.querySelector('[data-opportunity-fields]');
    const opportunityEmpty = form.querySelector('[data-opportunity-empty]');
    const countryValue = form.querySelector('[data-country-value]');
    const countryEntry = form.querySelector('[data-country-entry]');
    const countryList = form.querySelector('[data-country-token-list]');
    const docDrop = form.querySelector('[data-doc-drop]');
    const docDropInput = form.querySelector('[data-doc-drop-input]');
    const docDropCategory = form.querySelector('[data-doc-drop-category]');
    const docDropBrowse = form.querySelector('[data-doc-browse]');
    const docDropStatus = form.querySelector('[data-doc-drop-status]');

    let draftTimer = null;
    let draftController = null;
    let submitting = false;
    let allowNavigation = false;
    let scrollTicking = false;
    let programmaticStep = null;
    let programmaticStepUntil = 0;

    const q = (name) => form.querySelector(`[name="${CSS.escape(name)}"]`);
    const text = (name) => String(q(name)?.value || '').trim();
    const filled = (name) => text(name) !== '';
    const selected = (name, fallback = '') => form.querySelector(`input[name="${CSS.escape(name)}"]:checked`)?.value || fallback;
    const scrollToSection = (id) => document.getElementById(id)?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });

    const stepMeta = [
        ['basic-information', 'Add basic customer information', 'Start with the legal entity name and customer type.'],
        ['industry-segment', 'Classify the industry segment', 'Record the primary industry from known customer information.'],
        ['parent-company', 'Assess parent-company status', 'Confirm whether a parent company relationship exists.'],
        ['geography', 'Record headquarters geography', 'Add the country and city or authoritative address context.'],
        ['contacts', 'Identify key contacts', 'Add a named contact with an email or phone number.'],
        ['account-owner', 'Assign the account owner', 'Choose an active member of this company workspace.'],
        ['opportunity', 'Assess opportunity status', 'Confirm whether a real commercial opportunity exists.'],
        ['review-submit', 'Review the customer intake', 'Review the entered information before creating the customer record.'],
    ];

    const setControlsEnabled = (container, enabled) => {
        if (!container) return;
        container.querySelectorAll('input, select, textarea').forEach((control) => {
            control.disabled = !crmReady || !enabled;
        });
        container.classList.toggle('is-inactive', !enabled);
    };

    const syncAssessments = () => {
        const parentAssessment = selected('parent_company_assessment', 'not_assessed');
        const hasParent = parentAssessment === 'has_parent';
        setControlsEnabled(parentFields, hasParent);
        if (parentEmpty) {
            parentEmpty.hidden = hasParent;
            const title = parentEmpty.querySelector('strong');
            const copy = parentEmpty.querySelector('span');
            if (title) title.textContent = parentAssessment === 'no_parent' ? 'No parent company recorded' : 'Parent relationship not assessed';
            if (copy) copy.textContent = parentAssessment === 'no_parent'
                ? 'This explicit assessment will be stored with the customer profile.'
                : 'Choose Yes only when a parent or holding company relationship is known.';
        }

        const opportunityAssessment = selected('opportunity_assessment', 'not_assessed');
        const hasOpportunity = opportunityAssessment === 'has_opportunity';
        if (opportunityFields) opportunityFields.hidden = !hasOpportunity;
        setControlsEnabled(opportunityFields, hasOpportunity);
        if (opportunityEmpty) {
            opportunityEmpty.hidden = hasOpportunity;
            const title = opportunityEmpty.querySelector('strong');
            const copy = opportunityEmpty.querySelector('span');
            if (title) title.textContent = opportunityAssessment === 'no_opportunity' ? 'No opportunity recorded at intake' : 'Opportunity not assessed';
            if (copy) copy.textContent = opportunityAssessment === 'no_opportunity'
                ? 'This explicit assessment is stored separately from an empty opportunity form.'
                : 'Choose Yes only when a real commercial opportunity exists.';
        }
    };

    const formatMoney = () => {
        const amount = Number(text('annual_revenue_amount'));
        if (!Number.isFinite(amount) || amount <= 0) return 'Not Recorded';
        const currency = text('revenue_currency_code') || text('currency_code') || '';
        try {
            return `${new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(amount)}${currency ? ` ${currency}` : ''}`;
        } catch (_) {
            return `${amount}${currency ? ` ${currency}` : ''}`;
        }
    };

    const contactRows = () => Array.from(form.querySelectorAll('[data-contact-row]'));
    const contactComplete = () => contactRows().some((row) => {
        const name = String(row.querySelector('[data-contact-name]')?.value || '').trim();
        const email = String(row.querySelector('input[type="email"]')?.value || '').trim();
        const phone = String(row.querySelector('input[name*="[phone]"]')?.value || '').trim();
        return name !== '' && (email !== '' || phone !== '');
    });

    const parentComplete = () => {
        const assessment = selected('parent_company_assessment', 'not_assessed');
        if (assessment === 'no_parent') return true;
        if (assessment !== 'has_parent') return false;
        return filled('parent_customer_id') || filled('external_parent_name');
    };

    const opportunityComplete = () => {
        const assessment = selected('opportunity_assessment', 'not_assessed');
        if (assessment === 'no_opportunity') return true;
        if (assessment !== 'has_opportunity') return false;
        return filled('opportunity_name');
    };

    const stepStates = () => [
        filled('name') && filled('type'),
        filled('primary_industry'),
        parentComplete(),
        filled('hq_country') && (filled('city') || filled('address')),
        contactComplete(),
        filled('account_owner_user_id'),
        opportunityComplete(),
        Boolean(reviewAck?.checked),
    ];

    const updatePreview = () => {
        const displayName = text('preferred_name') || text('name') || 'New Customer';
        const stage = selected('relationship_stage', 'lead');
        const stageLabel = ({ lead: 'Lead', prospect: 'Prospect', active: 'Active', on_hold: 'On Hold', closed: 'Closed' })[stage] || 'Lead';
        root.querySelector('[data-preview-name]').textContent = displayName;
        root.querySelector('[data-preview-stage]').textContent = stageLabel;
        root.querySelector('[data-preview-industry]').textContent = text('primary_industry') || 'Not Recorded';
        root.querySelector('[data-preview-location]').textContent = [text('city'), text('hq_country')].filter(Boolean).join(', ') || 'Not Recorded';
        root.querySelector('[data-preview-revenue]').textContent = formatMoney();

        const mapCopy = root.querySelector('[data-map-copy]');
        if (mapCopy) {
            const countries = String(countryValue?.value || '').trim();
            mapCopy.textContent = [text('city'), text('hq_country'), countries].filter(Boolean).join(' · ') || 'No locations added yet.';
        }
    };

    const documentsSelected = () => Array.from(form.querySelectorAll('[data-doc-input]')).some((input) => input.files?.length);

    const updateFollowup = () => {
        const states = {
            contacts: contactComplete(),
            opportunity: opportunityComplete(),
            documents: documentsSelected(),
            notes: filled('notes') || filled('key_considerations'),
        };
        let visible = 0;
        root.querySelectorAll('[data-followup-key]').forEach((item) => {
            const complete = Boolean(states[item.dataset.followupKey]);
            item.hidden = complete;
            if (!complete) visible += 1;
        });
        const empty = root.querySelector('[data-followup-empty]');
        if (empty) empty.hidden = visible !== 0;
    };

    const updateReadiness = () => {
        const states = stepStates();
        const complete = states.filter(Boolean).length;
        const progress = Math.round((complete / states.length) * 100);
        const label = complete === 0 ? 'Not Started' : complete === states.length ? 'Ready to Create' : 'In Progress';

        const gauge = root.querySelector('[data-gauge]');
        if (gauge) gauge.style.setProperty('--progress', progress);
        root.querySelector('[data-gauge-value]').textContent = `${progress}%`;
        root.querySelector('[data-gauge-label]').textContent = label;
        root.querySelector('[data-gauge-count]').textContent = String(complete);
        root.querySelector('[data-progress-text]').textContent = `${progress}%`;
        root.querySelector('[data-progress-count]').textContent = String(complete);
        const progressBar = root.querySelector('[data-progress-bar]');
        if (progressBar) progressBar.style.width = `${progress}%`;
        root.querySelector('[data-checklist-count]').textContent = String(complete);

        states.forEach((state, index) => {
            root.querySelector(`[data-readiness-item="${index}"]`)?.classList.toggle('is-complete', state);
            root.querySelector(`[data-mini-item="${index}"]`)?.classList.toggle('is-complete', state);
            root.querySelector(`[data-step-anchor="${stepMeta[index][0]}"]`)?.classList.toggle('is-complete', state);
        });

        const nextIndex = states.findIndex((state) => !state);
        const resolvedIndex = nextIndex === -1 ? 7 : nextIndex;
        root.querySelector('[data-next-index]').textContent = String(resolvedIndex + 1);
        root.querySelector('[data-next-title]').textContent = stepMeta[resolvedIndex][1];
        root.querySelector('[data-next-copy]').textContent = stepMeta[resolvedIndex][2];
        root.querySelector('[data-next-action]').dataset.target = stepMeta[resolvedIndex][0];
        updateFollowup();
    };

    const updateCounters = () => {
        form.querySelectorAll('textarea[maxlength]').forEach((textarea) => {
            const counter = root.querySelector(`[data-count-for="${CSS.escape(textarea.name)}"]`);
            if (counter) counter.textContent = new Intl.NumberFormat('en-US').format(textarea.value.length);
        });
    };

    const autoGrow = (textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement)) return;
        const min = textarea.name === 'description' ? 58 : 62;
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.max(min, Math.min(textarea.scrollHeight, 150))}px`;
    };

    const updateAll = () => {
        syncAssessments();
        updatePreview();
        updateReadiness();
        updateCounters();
        form.querySelectorAll('textarea').forEach(autoGrow);
        if (probability) {
            const label = root.querySelector('[data-probability-label]');
            if (label) label.textContent = `${probability.value || 0}%`;
        }
    };

    /* Contacts */
    const renumberContacts = () => {
        contactRows().forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((control) => {
                control.name = control.name.replace(/contacts\[\d+\]/, `contacts[${index}]`);
            });
        });
        if (contactEmpty) contactEmpty.hidden = contactRows().length > 0;
    };

    const bindContactRow = (row) => {
        row.querySelector('[data-remove-contact]')?.addEventListener('click', () => {
            row.remove();
            renumberContacts();
            invalidateReview();
            updateAll();
            queueDraft();
        });
        row.querySelector('input[name*="[is_primary]"]')?.addEventListener('change', (event) => {
            if (!event.target.checked) return;
            contactRows().forEach((other) => {
                const check = other.querySelector('input[name*="[is_primary]"]');
                if (check && check !== event.target) check.checked = false;
            });
            invalidateReview();
            queueDraft();
        });
    };
    contactRows().forEach(bindContactRow);

    addContact?.addEventListener('click', () => {
        if (!contactTemplate || contactRows().length >= 8) return;
        const fragment = contactTemplate.content.cloneNode(true);
        const index = contactRows().length;
        fragment.querySelectorAll('[data-name-template]').forEach((control) => {
            control.name = control.dataset.nameTemplate.replace('__INDEX__', String(index));
            control.removeAttribute('data-name-template');
        });
        contactList.appendChild(fragment);
        const inserted = contactRows().at(-1);
        bindContactRow(inserted);
        renumberContacts();
        invalidateReview();
        inserted.querySelector('[data-contact-name]')?.focus();
        updateAll();
        queueDraft();
    });

    /* Operating-country token input */
    const countrySet = new Set(
        String(countryValue?.value || '')
            .split(',')
            .map((value) => value.trim())
            .filter(Boolean)
    );

    const renderCountries = () => {
        if (!countryList || !countryValue) return;
        countryList.innerHTML = '';
        [...countrySet].forEach((country) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'nxcus8-token';
            button.dataset.countryToken = country;
            button.disabled = !crmReady;
            const label = document.createElement('span');
            label.textContent = country;
            const remove = document.createElement('i');
            remove.setAttribute('aria-hidden', 'true');
            remove.textContent = '×';
            button.append(label, remove);
            button.addEventListener('click', () => {
                countrySet.delete(country);
                renderCountries();
                invalidateReview();
                updateAll();
                queueDraft();
            });
            countryList.appendChild(button);
        });
        countryValue.value = [...countrySet].join(', ');
    };

    const addCountry = (raw) => {
        const country = String(raw || '').trim().replace(/^,+|,+$/g, '').trim();
        if (!country || countrySet.has(country) || countrySet.size >= 30) return false;
        countrySet.add(country);
        renderCountries();
        if (countryEntry) countryEntry.value = '';
        invalidateReview();
        updateAll();
        queueDraft();
        return true;
    };

    countryEntry?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addCountry(countryEntry.value);
        }
    });
    countryEntry?.addEventListener('blur', () => addCountry(countryEntry.value));
    renderCountries();

    /* File rows and category-aware drag/drop */
    const fileExtension = (file) => `.${String(file?.name || '').split('.').pop()?.toLowerCase() || ''}`;
    const fileAllowedFor = (input, file) => {
        const accepted = String(input?.accept || '').split(',').map((item) => item.trim().toLowerCase()).filter(Boolean);
        return accepted.length === 0 || accepted.includes(fileExtension(file));
    };

    const updateFileRow = (input) => {
        const row = input.closest('[data-doc-row]');
        const label = row?.querySelector('[data-file-name]');
        if (label) label.textContent = input.files?.[0]?.name || 'No file selected';
    };

    form.querySelectorAll('[data-doc-input]').forEach((input) => input.addEventListener('change', () => {
        updateFileRow(input);
        invalidateReview();
        updateAll();
    }));

    const assignDroppedFile = (file) => {
        const category = docDropCategory?.value;
        const target = form.querySelector(`[data-doc-input="${CSS.escape(category || '')}"]`);
        const status = docDropStatus;
        if (!file || !target) return;
        if (!fileAllowedFor(target, file)) {
            if (status) status.textContent = 'That file type is not allowed for the selected category.';
            return;
        }
        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            target.files = transfer.files;
            updateFileRow(target);
            if (status) status.textContent = `Assigned to ${docDropCategory.options[docDropCategory.selectedIndex]?.text || 'selected category'}.`;
            invalidateReview();
            updateAll();
        } catch (_) {
            if (status) status.textContent = 'Use the category upload row if your browser blocks drag-and-drop assignment.';
        }
    };

    const openDocumentPicker = () => {
        if (!crmReady || docDropInput?.disabled) return;
        docDropInput?.click();
    };
    docDrop?.addEventListener('click', (event) => {
        if (event.target.closest('button')) return;
        openDocumentPicker();
    });
    docDrop?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        openDocumentPicker();
    });
    docDropBrowse?.addEventListener('click', openDocumentPicker);
    docDropInput?.addEventListener('change', () => assignDroppedFile(docDropInput.files?.[0]));
    ['dragenter', 'dragover'].forEach((type) => docDrop?.addEventListener(type, (event) => {
        event.preventDefault();
        docDrop.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((type) => docDrop?.addEventListener(type, (event) => {
        event.preventDefault();
        docDrop.classList.remove('is-dragging');
    }));
    docDrop?.addEventListener('drop', (event) => assignDroppedFile(event.dataTransfer?.files?.[0]));

    /* Step navigation: deterministic by document position, not intersection ratio. */
    const stepButtons = new Map(Array.from(root.querySelectorAll('[data-step-anchor]')).map((button) => [button.dataset.stepAnchor, button]));
    const sections = stepMeta.map(([id]) => document.getElementById(id)).filter(Boolean);

    const setActiveStep = (id) => {
        stepButtons.forEach((button, key) => {
            const active = key === id;
            button.classList.toggle('is-active', active);
            if (active) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        });
    };

    const resolveActiveStep = () => {
        scrollTicking = false;
        if (programmaticStep && performance.now() < programmaticStepUntil) {
            setActiveStep(programmaticStep);
            return;
        }
        programmaticStep = null;
        if (window.scrollY < 36) {
            setActiveStep('basic-information');
            return;
        }
        const activationLine = Math.min(190, Math.max(125, window.innerHeight * .22));
        let active = sections[0]?.id || 'basic-information';
        let activeTop = Number.NEGATIVE_INFINITY;
        for (const section of sections) {
            const top = section.getBoundingClientRect().top;
            if (top > activationLine) break;
            /* Multiple cards can share one visual grid row. Treat that row as the first
             * lifecycle step unless the user explicitly navigated to a sibling card. */
            if (top > activeTop + 2) {
                active = section.id;
                activeTop = top;
            }
        }
        setActiveStep(active);
    };

    window.addEventListener('scroll', () => {
        if (scrollTicking) return;
        scrollTicking = true;
        requestAnimationFrame(resolveActiveStep);
    }, { passive: true });
    window.addEventListener('resize', resolveActiveStep);

    const navigateToStep = (id) => {
        if (!id) return;
        programmaticStep = id;
        programmaticStepUntil = performance.now() + (reducedMotion ? 180 : 850);
        setActiveStep(id);
        scrollToSection(id);
    };

    root.querySelectorAll('[data-step-anchor]').forEach((button) => button.addEventListener('click', () => navigateToStep(button.dataset.stepAnchor)));
    root.querySelector('[data-next-section]')?.addEventListener('click', (event) => navigateToStep(event.currentTarget.dataset.nextSection));
    root.querySelector('[data-next-action]')?.addEventListener('click', (event) => navigateToStep(event.currentTarget.dataset.target || 'basic-information'));

    /* Drafts */
    const assignNested = (target, name, value) => {
        const parts = name.replace(/\]/g, '').split('[');
        if (parts.length === 1) {
            target[parts[0]] = value;
            return;
        }
        let cursor = target;
        parts.forEach((part, index) => {
            const last = index === parts.length - 1;
            const nextIsIndex = /^\d+$/.test(parts[index + 1] || '');
            if (last) {
                cursor[part] = value;
                return;
            }
            if (!(part in cursor)) cursor[part] = nextIsIndex ? [] : {};
            cursor = cursor[part];
        });
    };

    const draftPayload = () => {
        const payload = {};
        const data = new FormData(form);
        data.delete('_token');
        data.delete('submit_intent');
        data.delete('review_acknowledged');
        for (const [name, value] of data.entries()) {
            if (value instanceof File) continue;
            assignNested(payload, name, value);
        }
        return payload;
    };

    const setDraftState = (state, message) => {
        if (!draftStatus) return;
        draftStatus.classList.toggle('is-saving', state === 'saving');
        draftStatus.classList.toggle('is-error', state === 'error');
        if (draftStatusCopy) draftStatusCopy.textContent = message;
    };

    const saveDraft = async () => {
        if (!crmReady || !draftUrl) return;
        draftController?.abort();
        draftController = new AbortController();
        setDraftState('saving', 'Saving draft…');
        try {
            const response = await fetch(draftUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: draftController.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ payload: draftPayload() }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Draft save failed.');
            const when = result.saved_at ? new Date(result.saved_at) : new Date();
            const time = Number.isNaN(when.getTime()) ? 'just now' : when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            setDraftState('saved', `Draft saved at ${time}`);
        } catch (error) {
            if (error.name === 'AbortError') return;
            setDraftState('error', error.message || 'Draft not saved');
        }
    };

    const queueDraft = () => {
        if (!crmReady) return;
        window.clearTimeout(draftTimer);
        draftTimer = window.setTimeout(saveDraft, 1400);
    };
    saveDraftButton?.addEventListener('click', saveDraft);

    const invalidateReview = (target = null) => {
        if (!reviewAck?.checked || target === reviewAck) return;
        reviewAck.checked = false;
    };

    form.addEventListener('input', (event) => {
        invalidateReview(event.target);
        if (event.target instanceof HTMLTextAreaElement) autoGrow(event.target);
        updateAll();
        queueDraft();
    });
    form.addEventListener('change', (event) => {
        invalidateReview(event.target);
        updateAll();
        queueDraft();
    });
    reviewAck?.addEventListener('change', updateAll);

    /* Unsaved-change guard */
    const signature = () => new URLSearchParams(new FormData(form)).toString();
    const initialSignature = signature();
    const isDirty = () => signature() !== initialSignature;

    window.addEventListener('beforeunload', (event) => {
        if (submitting || allowNavigation || !isDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    });

    document.addEventListener('click', async (event) => {
        const anchor = event.target.closest('a[href]');
        if (!anchor || allowNavigation || !isDirty() || anchor.hash) return;
        const url = new URL(anchor.href, location.href);
        if (url.origin !== location.origin || anchor.target === '_blank' || anchor.hasAttribute('download')) return;
        event.preventDefault();
        const approved = window.NexorinConfirm?.ask
            ? await window.NexorinConfirm.ask('Leave this customer form with unsaved changes?', {
                tone: 'danger',
                title: 'Leave customer intake?',
                kicker: 'Unsaved customer data',
                confirmLabel: 'Leave page',
                control: 'Unsaved form changes detected',
                result: 'Unsaved changes may be lost unless the draft was saved',
            })
            : window.confirm('Leave this customer form with unsaved changes?');
        if (approved) {
            allowNavigation = true;
            location.assign(anchor.href);
        }
    }, true);

    form.addEventListener('submit', (event) => {
        submitting = true;
        const requiredName = q('name');
        const requiredType = q('type');
        if (!requiredName?.value.trim() || !requiredType?.value) {
            event.preventDefault();
            submitting = false;
            requiredName?.focus();
            form.reportValidity();
        }
    });

    form.querySelectorAll('input[name="relationship_stage"]').forEach((radio) => radio.addEventListener('change', () => {
        radio.closest('.nxcus8-stage')?.querySelectorAll('label').forEach((label) => label.classList.toggle('is-selected', label.contains(radio) && radio.checked));
        updateAll();
    }));

    updateAll();
    resolveActiveStep();
})();
