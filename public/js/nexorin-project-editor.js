(() => {
    'use strict';

    const root = document.querySelector('[data-project-editor]');
    const form = document.querySelector('[data-project-editor-form]');

    if (!root || !form) {
        return;
    }

    root.classList.add('is-enhanced');

    const originalStatus = root.dataset.originalStatus || '';
    const hasServerErrors = root.dataset.serverErrors === 'true';
    const statusSelect = form.querySelector('#status');
    const statusNote = form.querySelector('[data-editor-status-note]');
    const statusNoteHelp = form.querySelector('[data-editor-status-note-help]');
    const submitButtons = [...form.querySelectorAll('[data-editor-submit]')];
    const submitLabels = [...form.querySelectorAll('[data-editor-submit-label]')];
    const mobileDock = form.querySelector('[data-editor-mobile-dock]');
    const stateTitle = root.querySelector('[data-editor-state-title]');
    const stateCopy = root.querySelector('[data-editor-state-copy]');
    const syncBadge = root.querySelector('[data-editor-sync-badge]');
    const syncLabel = root.querySelector('[data-editor-sync-label]');
    const changeCount = root.querySelector('[data-editor-change-count]');
    const bottomState = root.querySelector('[data-editor-bottom-state]');
    const mobileState = root.querySelector('[data-editor-mobile-state]');
    const mobileCount = root.querySelector('[data-editor-mobile-count]');
    const workflowCopy = root.querySelector('[data-editor-workflow-copy]');
    const railState = root.querySelector('[data-editor-rail-state]');
    const latitudeInput = form.querySelector('#latitude');
    const longitudeInput = form.querySelector('#longitude');
    const locationStatus = form.querySelector('.project-editor-location-status');
    const locationLabel = form.querySelector('[data-editor-location-label]');
    const locationCopy = form.querySelector('[data-editor-location-copy]');
    const locationButton = form.querySelector('[data-editor-use-location]');
    const locationButtonLabel = form.querySelector('[data-editor-location-button-label]');

    let isSubmitting = false;
    let currentDirty = false;
    let initialSnapshot = new Map();

    const headline = (value) => String(value || '')
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

    const getFieldValue = (name) => {
        const fields = [...form.elements].filter((field) => field.name === name && !field.disabled);

        if (!fields.length) {
            return '';
        }

        if (fields[0].type === 'radio') {
            return fields.find((field) => field.checked)?.value || '';
        }

        if (fields[0].type === 'checkbox') {
            return fields.filter((field) => field.checked).map((field) => field.value).sort().join('|');
        }

        return String(fields[0].value ?? '').trim();
    };

    const trackedNames = () => [...new Set(
        [...form.querySelectorAll('[data-editor-field]')]
            .map((field) => field.name)
            .filter(Boolean)
    )];

    const captureSnapshot = () => new Map(trackedNames().map((name) => [name, getFieldValue(name)]));

    const changedFieldNames = () => trackedNames().filter((name) => {
        const initialValue = initialSnapshot.get(name) ?? '';
        return getFieldValue(name) !== initialValue;
    });

    const selectedText = (select, fallback = 'Unassigned', mode = 'full') => {
        if (!select || !select.value) {
            return fallback;
        }

        const text = select.options[select.selectedIndex]?.textContent?.trim() || fallback;

        if (!text.includes('·')) {
            return text;
        }

        const parts = text.split('·').map((part) => part.trim());
        return mode === 'after' ? (parts[1] || parts[0]) : parts[0];
    };

    const setSummary = (key, value) => {
        const target = root.querySelector(`[data-editor-summary="${key}"]`);
        if (target) {
            target.textContent = value || 'Not specified';
            target.title = value || 'Not specified';
        }
    };

    const updateSummaries = () => {
        const name = form.querySelector('#name')?.value.trim() || 'Untitled project';
        const customer = selectedText(form.querySelector('#customer_id'), 'Customer', 'after');
        const building = selectedText(form.querySelector('#building_type'), 'Building');
        const selectedSystem = form.querySelector('input[name="proposed_system"]:checked');
        const system = selectedSystem
            ? selectedSystem.closest('label')?.querySelector('strong')?.textContent?.trim() || headline(selectedSystem.value)
            : 'Undecided';
        const city = form.querySelector('#city')?.value.trim();
        const province = form.querySelector('#province')?.value.trim();
        const location = [city, province].filter(Boolean).join(', ') || 'Location not specified';
        const engineer = selectedText(form.querySelector('#assigned_engineer_id'));
        const sales = selectedText(form.querySelector('#assigned_sales_id'));
        const status = statusSelect ? selectedText(statusSelect, headline(originalStatus)) : headline(originalStatus);

        setSummary('name', name);
        setSummary('customer', customer);
        setSummary('configuration', `${building} · ${system}`);
        setSummary('location', location);
        setSummary('engineer', engineer);
        setSummary('sales', sales);
        setSummary('status', status);
    };

    const updateStatusNoteState = () => {
        if (!statusSelect || !statusNote) {
            return;
        }

        const statusChanged = statusSelect.value !== originalStatus;
        statusNote.disabled = !statusChanged;
        statusNote.setAttribute('aria-disabled', String(!statusChanged));

        if (statusChanged) {
            statusNote.placeholder = `Optional reason for moving to ${headline(statusSelect.value)}`;
            if (statusNoteHelp) {
                statusNoteHelp.textContent = 'This note will be saved with the workflow transition in the audit history.';
            }
            if (workflowCopy) {
                workflowCopy.textContent = `Transition to ${headline(statusSelect.value)} will be recorded when the project is saved.`;
            }
        } else {
            statusNote.placeholder = 'Add an optional handover note';
            if (statusNoteHelp) {
                statusNoteHelp.textContent = 'A note becomes available after selecting a new workflow status.';
            }
            if (workflowCopy) {
                workflowCopy.textContent = 'No workflow transition selected.';
            }
        }
    };

    const updateLocationState = () => {
        if (!locationStatus || !locationLabel || !locationCopy) {
            return;
        }

        const hasLatitude = latitudeInput?.value !== '';
        const hasLongitude = longitudeInput?.value !== '';
        const complete = hasLatitude && hasLongitude;

        locationStatus.classList.toggle('is-missing', !complete);
        locationLabel.textContent = complete ? 'Coordinates ready' : 'Coordinates not set';
        locationCopy.textContent = complete
            ? 'Latitude and longitude are available for solar and map workflows.'
            : 'Add coordinates for more accurate solar-resource and site workflows.';
    };

    const requiredFieldGroups = () => {
        const groups = new Map();

        form.querySelectorAll('[required]').forEach((field) => {
            if (field.disabled || !field.name) {
                return;
            }
            if (!groups.has(field.name)) {
                groups.set(field.name, []);
            }
            groups.get(field.name).push(field);
        });

        return groups;
    };

    const groupIsComplete = (fields) => {
        if (!fields.length) {
            return true;
        }
        if (fields[0].type === 'radio' || fields[0].type === 'checkbox') {
            return fields.some((field) => field.checked);
        }
        return fields.every((field) => String(field.value ?? '').trim() !== '');
    };

    const updateSectionStates = () => {
        form.querySelectorAll('[data-editor-section]').forEach((section) => {
            const state = section.querySelector('[data-editor-section-state]');
            if (!state) {
                return;
            }

            const required = new Map();
            section.querySelectorAll('[required]').forEach((field) => {
                if (field.disabled || !field.name) {
                    return;
                }
                if (!required.has(field.name)) {
                    required.set(field.name, []);
                }
                required.get(field.name).push(field);
            });

            if (!required.size) {
                const optionalHasValue = [...section.querySelectorAll('input, textarea, select')]
                    .some((field) => !field.disabled && String(field.value ?? '').trim() !== '');
                state.textContent = optionalHasValue ? 'Added' : 'Optional';
                state.classList.toggle('is-optional', !optionalHasValue);
                state.classList.remove('is-review');
                return;
            }

            const complete = [...required.values()].every(groupIsComplete);
            state.textContent = complete ? 'Ready' : 'Needs input';
            state.classList.toggle('is-review', !complete);
            state.classList.remove('is-optional');
        });
    };

    const updateDirtyState = () => {
        const changed = changedFieldNames();
        const count = changed.length;
        const dirty = count > 0;
        currentDirty = dirty;

        root.classList.toggle('is-dirty', dirty);
        syncBadge?.classList.toggle('is-dirty', dirty);
        if (!hasServerErrors || dirty) {
            syncBadge?.classList.remove('is-attention');
        }

        if (changeCount) {
            changeCount.textContent = `${count} ${count === 1 ? 'field' : 'fields'}`;
        }

        const canSave = dirty || hasServerErrors;
        submitButtons.forEach((button) => {
            button.disabled = !canSave;
            button.classList.toggle('is-saved', !canSave);
        });

        if (dirty) {
            if (stateTitle) stateTitle.textContent = 'Project revision in progress';
            if (stateCopy) stateCopy.textContent = `${count} ${count === 1 ? 'field has' : 'fields have'} changed in this session.`;
            if (syncLabel) syncLabel.textContent = `${count} unsaved ${count === 1 ? 'change' : 'changes'}`;
            if (bottomState) bottomState.textContent = `${count} unsaved ${count === 1 ? 'change is' : 'changes are'} ready to apply.`;
            if (mobileState) mobileState.textContent = 'Unsaved revision';
            if (mobileCount) mobileCount.textContent = `${count} ${count === 1 ? 'field changed' : 'fields changed'}`;
            if (railState) railState.textContent = `${count} changed`;
            submitLabels.forEach((label) => { label.textContent = 'Save changes'; });
            mobileDock?.classList.add('is-visible');
        } else if (hasServerErrors) {
            if (stateTitle) stateTitle.textContent = 'Review highlighted fields';
            if (stateCopy) stateCopy.textContent = 'Correct the validation issues before saving.';
            if (syncLabel) syncLabel.textContent = 'Needs review';
            if (bottomState) bottomState.textContent = 'Validation review required before saving.';
            if (mobileState) mobileState.textContent = 'Needs review';
            if (mobileCount) mobileCount.textContent = 'Correct highlighted fields';
            if (railState) railState.textContent = 'Review';
            submitLabels.forEach((label) => { label.textContent = 'Review & save'; });
            mobileDock?.classList.add('is-visible');
        } else {
            if (stateTitle) stateTitle.textContent = 'Project data synchronized';
            if (stateCopy) stateCopy.textContent = 'No pending changes in this session.';
            if (syncLabel) syncLabel.textContent = 'Saved state';
            if (bottomState) bottomState.textContent = 'No unsaved changes detected.';
            if (mobileState) mobileState.textContent = 'Saved state';
            if (mobileCount) mobileCount.textContent = 'No pending changes';
            if (railState) railState.textContent = 'Saved';
            submitLabels.forEach((label) => { label.textContent = 'Saved'; });
            mobileDock?.classList.remove('is-visible');
        }
    };

    const resizeTextarea = (textarea) => {
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.max(textarea.dataset.minimumHeight || 0, textarea.scrollHeight)}px`;
    };

    const updateCharacterCount = (field) => {
        const counter = root.querySelector(`[data-editor-character="${field.id}"]`);
        if (counter) {
            counter.textContent = String(field.value.length);
        }
    };

    const refresh = () => {
        updateSummaries();
        updateLocationState();
        updateSectionStates();
        updateDirtyState();
    };

    updateStatusNoteState();

    form.querySelectorAll('[data-editor-auto-resize]').forEach((textarea) => {
        textarea.dataset.minimumHeight = String(textarea.offsetHeight || 105);
        resizeTextarea(textarea);
    });

    form.querySelectorAll('[maxlength]').forEach(updateCharacterCount);

    initialSnapshot = captureSnapshot();
    refresh();

    form.addEventListener('input', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
        }

        if (field.matches('[data-editor-auto-resize]')) {
            resizeTextarea(field);
        }
        if (field.maxLength > -1) {
            updateCharacterCount(field);
        }
        refresh();
    });

    form.addEventListener('change', (event) => {
        if (event.target === statusSelect) {
            updateStatusNoteState();
        }
        refresh();
    });

    if (locationButton && latitudeInput && longitudeInput) {
        locationButton.addEventListener('click', () => {
            if (!navigator.geolocation) {
                if (locationLabel) locationLabel.textContent = 'Location unavailable';
                if (locationCopy) locationCopy.textContent = 'This browser does not provide geolocation access.';
                locationStatus?.classList.add('is-missing');
                return;
            }

            locationButton.disabled = true;
            if (locationButtonLabel) locationButtonLabel.textContent = 'Locating…';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    latitudeInput.value = position.coords.latitude.toFixed(7);
                    longitudeInput.value = position.coords.longitude.toFixed(7);
                    latitudeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    longitudeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    locationButton.disabled = false;
                    if (locationButtonLabel) locationButtonLabel.textContent = 'Location captured';
                    window.setTimeout(() => {
                        if (locationButtonLabel) locationButtonLabel.textContent = 'Refresh current location';
                    }, 1800);
                },
                (error) => {
                    locationButton.disabled = false;
                    if (locationButtonLabel) locationButtonLabel.textContent = 'Try current location again';
                    if (locationLabel) locationLabel.textContent = 'Location permission required';
                    if (locationCopy) locationCopy.textContent = error.message || 'Allow browser location access or enter coordinates manually.';
                    locationStatus?.classList.add('is-missing');
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
            );
        });
    }

    const revealItems = [...root.querySelectorAll('[data-editor-reveal]')];
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -30px' });
        revealItems.forEach((item) => observer.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    }

    form.addEventListener('invalid', (event) => {
        const section = event.target.closest('[data-editor-section]');
        section?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, true);

    form.addEventListener('submit', (event) => {
        if (!currentDirty && !hasServerErrors) {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }

        isSubmitting = true;
        root.classList.add('is-submitting');
        submitButtons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
        submitLabels.forEach((label) => {
            label.textContent = 'Saving…';
        });
    });

    document.addEventListener('keydown', (event) => {
        const quickSave = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's';
        if (!quickSave) {
            return;
        }
        event.preventDefault();
        if (!isSubmitting && (currentDirty || hasServerErrors)) {
            form.requestSubmit();
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (window.__nexorinProjectNavigationApproved || isSubmitting || changedFieldNames().length === 0) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });
})();
