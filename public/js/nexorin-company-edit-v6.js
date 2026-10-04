document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-nx5-editor]');
    if (!root) return;

    const form = root.querySelector('[data-nx5-form]');
    if (!form) return;

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    const dirtyBadge = root.querySelector('[data-nx5-dirty-badge]');
    const discardButton = root.querySelector('[data-nx5-discard]');
    const saveButton = root.querySelector('[data-nx5-save]');
    const saveDraftButton = root.querySelector('[data-nx5-save-draft]');
    const draftState = root.querySelector('[data-nx5-draft-state]');
    const nameInput = root.querySelector('[data-nx5-name]');
    const codeInput = root.querySelector('[data-nx5-code]');
    const namePreview = root.querySelector('[data-nx5-preview-name]');
    const codePreview = root.querySelector('[data-nx5-preview-code]');
    const statusSelect = form.querySelector('select[name="status"]');
    const statusText = root.querySelector('[data-nx5-status-text]');
    const statusChip = root.querySelector('[data-nx5-status-chip]');
    const draftUrl = root.dataset.nx5DraftUrl || '';
    const editUrl = root.dataset.nx5EditUrl || '';
    const restoredDraft = root.dataset.nx5RestoredDraft === '1';
    const profileImageInput = root.querySelector('[data-nx5-profile-image-input]');
    const profileImagePreview = root.querySelector('[data-nx5-profile-image-preview]');
    const profileImageEmpty = root.querySelector('[data-nx5-profile-image-empty]');
    const profileImageRemove = root.querySelector('[data-nx5-profile-image-remove]');
    const profileImageRemoveValue = root.querySelector('[data-nx5-profile-image-remove-value]');
    const initialProfileImageSrc = profileImagePreview?.getAttribute('src') || '';
    const initialProfileImageVisible = Boolean(initialProfileImageSrc);

    const snapshot = () => JSON.stringify(Array.from(new FormData(form).entries()).map(([key, value]) => {
        if (value instanceof File) {
            return [key, { name: value.name, size: value.size, type: value.type, lastModified: value.lastModified }];
        }
        return [key, String(value)];
    }));
    let initialState = snapshot();
    let forceDirty = restoredDraft;
    let autosaveTimer = null;
    let draftAbortController = null;
    let submitting = false;
    let regionMap = null;
    let regionGeoReady = false;
    let regionAnchorsReady = false;
    let regionAnchorIndex = new Map();

    const updateDirtyState = () => {
        const dirty = forceDirty || snapshot() !== initialState;
        if (dirtyBadge) dirtyBadge.hidden = !dirty;
        if (discardButton) discardButton.disabled = !dirty;
        if (saveButton) saveButton.disabled = !dirty;
    };

    const refreshPreview = () => {
        if (namePreview && nameInput) namePreview.textContent = nameInput.value.trim() || 'Unnamed Company';
        if (codePreview && codeInput) codePreview.textContent = codeInput.value.trim() || '—';
        const status = statusSelect?.value || 'active';
        const label = status.charAt(0).toUpperCase() + status.slice(1);
        [statusText, statusChip].forEach(node => { if (node) node.textContent = label; });
    };

    const initialsFor = value => value.trim().split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'CO';

    const refreshRecordSummary = row => {
        if (!row) return;
        const title = row.querySelector('[data-nx5-row-title]');
        const status = row.querySelector('.nx5-record-status');

        if (row.classList.contains('nx5-contact-record')) {
            const name = row.querySelector('[name$="[full_name]"]')?.value.trim() || '';
            const role = row.querySelector('[name$="[job_title]"]')?.value.trim() || '';
            const email = row.querySelector('[name$="[email]"]')?.value.trim() || '';
            const phone = row.querySelector('[name$="[phone]"]')?.value.trim() || '';
            const primary = Boolean(row.querySelector('[name$="[is_primary]"]')?.checked);
            const avatar = row.querySelector('[data-nx5-contact-avatar]');
            const roleNode = row.querySelector('[data-nx5-contact-role]');
            const metaNode = row.querySelector('[data-nx5-contact-meta]');
            if (title) title.textContent = name || 'New contact';
            if (avatar) avatar.textContent = initialsFor(name);
            if (roleNode) roleNode.textContent = role || 'Role not recorded';
            if (metaNode) metaNode.textContent = [email, phone].filter(Boolean).join(' · ') || 'Contact details pending';
            if (status) {
                status.textContent = primary ? 'Primary' : 'Contact';
                status.classList.toggle('is-active', primary);
                status.classList.toggle('is-neutral', !primary);
            }
            return;
        }

        if (row.classList.contains('nx5-region-record')) {
            const country = row.querySelector('[name$="[country_name]"]')?.value.trim() || '';
            const region = row.querySelector('[name$="[region_name]"]')?.value.trim() || '';
            const projects = Math.max(0, Number(row.querySelector('[name$="[project_count]"]')?.value || 0));
            const latitudeRaw = row.querySelector('[name$="[latitude]"]')?.value.trim() || '';
            const longitudeRaw = row.querySelector('[name$="[longitude]"]')?.value.trim() || '';
            const latitude = Number(latitudeRaw);
            const longitude = Number(longitudeRaw);
            const exact = latitudeRaw !== '' && longitudeRaw !== '' && Number.isFinite(latitude) && Number.isFinite(longitude);
            const headquarters = Boolean(row.querySelector('[name$="[is_headquarters]"]')?.checked);
            const countryNode = row.querySelector('[data-nx5-region-country]');
            const metaNode = row.querySelector('[data-nx5-region-meta]');
            const dot = row.querySelector('[data-nx5-region-dot]');
            if (title) title.textContent = region || country || 'New served region';
            if (countryNode) countryNode.textContent = country || 'Country not recorded';
            if (metaNode) metaNode.textContent = `${projects} project${projects === 1 ? '' : 's'} · ${exact ? 'Exact coordinates' : 'Regional reference'}`;
            if (dot) {
                dot.classList.toggle('is-exact', exact);
                dot.classList.toggle('is-reference', !exact);
            }
            if (status) {
                status.textContent = headquarters ? 'HQ' : 'Region';
                status.classList.toggle('is-active', headquarters);
                status.classList.toggle('is-neutral', !headquarters);
            }
            return;
        }

        if (row.classList.contains('nx5-document-record')) {
            const recordTitle = row.querySelector('[name$="[title]"]')?.value.trim() || '';
            const state = row.querySelector('[name$="[status]"]')?.value || 'draft';
            if (title) title.textContent = recordTitle || 'New document record';
            const stateNode = row.querySelector('.nx5-doc-status');
            if (stateNode) stateNode.textContent = state.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
            return;
        }

        if (row.classList.contains('nx5-credential-record')) {
            const recordTitle = row.querySelector('[name$="[title]"]')?.value.trim() || '';
            const state = row.querySelector('[name$="[status]"]')?.value || 'pending';
            if (title) title.textContent = recordTitle || 'New credential';
            if (status) {
                status.textContent = state.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
                status.className = `nx5-record-status is-${state}`;
            }
        }
    };

    const reindexRepeater = (name) => {
        const repeater = root.querySelector(`[data-nx5-repeater="${name}"]`);
        if (!repeater) return;
        Array.from(repeater.querySelectorAll('[data-nx5-row]')).forEach((row, index) => {
            row.querySelectorAll('input, select, textarea').forEach(field => {
                const fieldName = field.getAttribute('name');
                if (!fieldName) return;
                field.setAttribute('name', fieldName.replace(/^[^[]+\[\d+\]/, `${name}[${index}]`));
            });
        });
    };

    const setupTokenEditor = editor => {
        const hidden = editor.querySelector('[data-nx5-token-value]');
        const list = editor.querySelector('[data-nx5-token-list]');
        const input = editor.querySelector('[data-nx5-token-input]');
        const add = editor.querySelector('[data-nx5-token-add]');
        const key = editor.getAttribute('data-nx5-token-editor');
        const count = key ? root.querySelector(`[data-nx5-token-count="${key}"]`) : null;
        if (!hidden || !list || !input || !add) return;

        const readTokens = () => hidden.value.split(',').map(value => value.trim()).filter(Boolean);
        const writeTokens = tokens => {
            hidden.value = Array.from(new Set(tokens.map(value => value.trim()).filter(Boolean))).join(', ');
            render();
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        };
        const render = () => {
            const tokens = readTokens();
            list.replaceChildren();
            tokens.forEach((token, index) => {
                const chip = document.createElement('span');
                chip.className = 'nx5-token';
                chip.append(document.createTextNode(token));
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', `Remove ${token}`);
                remove.textContent = '×';
                remove.addEventListener('click', () => writeTokens(tokens.filter((_, tokenIndex) => tokenIndex !== index)));
                chip.append(remove);
                list.append(chip);
            });
            if (count) count.textContent = `${tokens.length} selected`;
        };
        const addToken = () => {
            const value = input.value.trim();
            if (!value) return;
            writeTokens([...readTokens(), value]);
            input.value = '';
            input.focus();
        };
        add.addEventListener('click', addToken);
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                addToken();
            }
        });
        render();
    };

    const syncProfileImageState = ({ src = null, removed = false } = {}) => {
        if (profileImagePreview) {
            if (src) {
                profileImagePreview.src = src;
                profileImagePreview.hidden = false;
            } else if (removed) {
                profileImagePreview.hidden = true;
                profileImagePreview.removeAttribute('src');
            }
        }
        if (profileImageEmpty) profileImageEmpty.hidden = Boolean(src) && !removed;
        if (profileImageRemoveValue) profileImageRemoveValue.value = removed ? '1' : '0';
        if (profileImageRemove) profileImageRemove.hidden = removed || !src;
    };

    const markProfileImageUnavailable = () => {
        if (!profileImagePreview) return;
        profileImagePreview.hidden = true;
        profileImagePreview.classList.add('is-load-error');
        if (profileImageEmpty) {
            profileImageEmpty.hidden = false;
            const title = profileImageEmpty.querySelector('[data-nx5-image-empty-title]');
            const copy = profileImageEmpty.querySelector('[data-nx5-image-empty-copy]');
            if (title) title.textContent = 'Stored image unavailable';
            if (copy) copy.textContent = 'The saved media file could not be loaded. Upload a replacement or remove the stale reference.';
        }
        if (profileImageRemove) profileImageRemove.hidden = false;
    };

    if (profileImagePreview) {
        profileImagePreview.addEventListener('error', markProfileImageUnavailable);
        if (profileImagePreview.complete && profileImagePreview.getAttribute('src') && profileImagePreview.naturalWidth === 0) {
            markProfileImageUnavailable();
        }
    }

    profileImageInput?.addEventListener('change', () => {
        const file = profileImageInput.files?.[0];
        if (!file) return;
        const objectUrl = URL.createObjectURL(file);
        syncProfileImageState({ src: objectUrl, removed: false });
        forceDirty = true;
        updateDirtyState();
        scheduleAutosave();
    });

    profileImageRemove?.addEventListener('click', () => {
        if (profileImageInput) profileImageInput.value = '';
        syncProfileImageState({ removed: true });
        forceDirty = true;
        updateDirtyState();
        scheduleAutosave();
    });

    root.querySelectorAll('[data-nx5-token-editor]').forEach(setupTokenEditor);

    const normalizeRegionKey = value => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();

    const registerRegionAnchors = payload => {
        regionAnchorIndex = new Map();
        const regions = Array.isArray(payload?.regions) ? payload.regions : [];
        regions.forEach(item => {
            const anchor = {
                name: item.name,
                latitude: Number(item.latitude),
                longitude: Number(item.longitude),
                precision: 'REFERENCE_ONLY',
            };
            [item.name, ...(Array.isArray(item.aliases) ? item.aliases : [])]
                .map(normalizeRegionKey)
                .filter(Boolean)
                .forEach(key => regionAnchorIndex.set(key, anchor));
        });
        regionAnchorsReady = regionAnchorIndex.size > 0;
    };

    const findRegionAnchor = record => {
        if (!regionAnchorsReady) return null;
        const regionKey = normalizeRegionKey(record.region);
        if (regionKey && regionAnchorIndex.has(regionKey)) return regionAnchorIndex.get(regionKey);
        const countryKey = normalizeRegionKey(record.country);
        if ((countryKey === 'indonesia' || countryKey === 'idn') && regionKey) {
            for (const [key, anchor] of regionAnchorIndex.entries()) {
                if (key.includes(regionKey) || regionKey.includes(key)) return anchor;
            }
        }
        return null;
    };

    const collectRegionRecords = () => Array.from(root.querySelectorAll('[data-nx5-repeater="regions"] [data-nx5-row]')).map(row => ({
        country: row.querySelector('[name$="[country_name]"]')?.value.trim() || '',
        countryCode: row.querySelector('[name$="[country_code]"]')?.value.trim().toUpperCase() || '',
        region: row.querySelector('[name$="[region_name]"]')?.value.trim() || '',
        projects: Number(row.querySelector('[name$="[project_count]"]')?.value || 0),
        latitude: Number(row.querySelector('[name$="[latitude]"]')?.value || NaN),
        longitude: Number(row.querySelector('[name$="[longitude]"]')?.value || NaN),
        headquarters: Boolean(row.querySelector('[name$="[is_headquarters]"]')?.checked),
    })).filter(item => item.country || item.region);

    const setRegionMapState = ({ tone = 'ready', title = 'Regional map ready', copy = '' } = {}) => {
        const status = root.querySelector('[data-nx5-region-map-status]');
        if (!status) return;
        status.className = `nx5-region-map-status is-${tone}`;
        const dot = status.querySelector('span');
        if (dot) dot.className = `is-${tone}`;
        const strong = status.querySelector('strong');
        if (strong) strong.textContent = title;
        const small = status.querySelector('small');
        if (small) small.textContent = copy;
    };

    const updateRegionLegend = points => {
        const legend = root.querySelector('[data-nx5-region-map-legend]');
        if (!legend) return;
        if (!points.length) {
            legend.hidden = true;
            legend.replaceChildren();
            return;
        }
        legend.hidden = false;
        legend.replaceChildren();
        points.slice(0, 5).forEach(point => {
            const item = document.createElement('div');
            item.className = 'nx5-map-legend-item';
            const marker = document.createElement('span');
            marker.className = point.exact ? 'is-exact' : 'is-reference';
            const content = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = point.name;
            const meta = document.createElement('small');
            const sourceLabel = point.exact ? 'Exact coordinates' : 'Regional reference';
            meta.textContent = `${point.projects} project${point.projects === 1 ? '' : 's'} · ${point.headquarters ? 'HQ · ' : ''}${sourceLabel}`;
            content.append(title, meta);
            item.append(marker, content);
            legend.append(item);
        });
    };

    const islandLabels = [
        { name: 'SUMATRA', value: [101.2, 0.2] },
        { name: 'JAVA', value: [110.5, -7.35] },
        { name: 'KALIMANTAN', value: [114.6, 0.7] },
        { name: 'SULAWESI', value: [121.1, -2.0] },
        { name: 'PAPUA', value: [137.1, -4.3] },
        { name: 'BALI · NUSA TENGGARA', value: [118.2, -8.8] },
        { name: 'MALUKU', value: [128.2, -3.2] },
    ];

    const renderRegionMap = () => {
        if (!regionMap || !regionGeoReady) return;
        const empty = root.querySelector('[data-nx5-region-map-empty]');
        const records = collectRegionRecords();
        const points = records.map(item => {
            const exact = Number.isFinite(item.latitude) && Number.isFinite(item.longitude);
            const anchor = exact ? null : findRegionAnchor(item);
            if (!exact && !anchor) return null;
            return {
                name: item.region || anchor?.name || item.country,
                country: item.country || 'Indonesia',
                projects: Math.max(0, item.projects || 0),
                headquarters: item.headquarters,
                exact,
                latitude: exact ? item.latitude : anchor.latitude,
                longitude: exact ? item.longitude : anchor.longitude,
                value: [exact ? item.longitude : anchor.longitude, exact ? item.latitude : anchor.latitude, Math.max(1, item.projects || 0)],
            };
        }).filter(Boolean);

        const exactPoints = points.filter(point => point.exact);
        const referencePoints = points.filter(point => !point.exact);
        const unresolvedCount = Math.max(0, records.length - points.length);

        if (empty) {
            empty.hidden = points.length > 0 || records.length === 0;
            if (!empty.hidden) empty.textContent = 'This region could not be auto-plotted. Add latitude and longitude for exact placement.';
        }

        if (!records.length) {
            setRegionMapState({ tone: 'ready', title: 'Regional map ready', copy: 'Add a served region to start plotting company coverage.' });
        } else if (unresolvedCount > 0) {
            setRegionMapState({ tone: 'warn', title: `${points.length} of ${records.length} regions plotted`, copy: 'Unresolved regions need exact coordinates.' });
        } else if (referencePoints.length > 0) {
            setRegionMapState({ tone: 'reference', title: `${points.length} region${points.length === 1 ? '' : 's'} plotted`, copy: 'Reference markers are approximate. Add coordinates for exact placement.' });
        } else {
            setRegionMapState({ tone: 'ready', title: `${points.length} exact region${points.length === 1 ? '' : 's'} plotted`, copy: 'All visible markers use saved coordinates.' });
        }

        updateRegionLegend(points);

        regionMap.setOption({
            animation: !reducedMotion,
            animationDuration: reducedMotion ? 0 : 460,
            animationEasing: 'cubicOut',
            tooltip: {
                trigger: 'item',
                backgroundColor: 'rgba(2,16,27,.98)',
                borderColor: 'rgba(78,151,178,.36)',
                borderWidth: 1,
                padding: [7, 9],
                textStyle: { color: '#dff5fb', fontSize: 10, lineHeight: 16 },
                formatter: params => {
                    if (!Array.isArray(params.value)) return params.name || '';
                    const item = params.data || {};
                    const position = item.exact ? 'Exact coordinates' : 'Regional reference location';
                    return `<b>${item.name || params.name}</b><br>${item.country || ''}<br>${item.projects || 0} project${Number(item.projects) === 1 ? '' : 's'}${item.headquarters ? '<br>Headquarters' : ''}<br><span style="color:#7fa8b9">${position}</span>`;
                },
            },
            geo: {
                map: 'nx5-indonesia',
                roam: true,
                center: [117.2, -2.25],
                zoom: 1.04,
                scaleLimit: { min: 1, max: 8 },
                left: 0,
                right: 0,
                top: 0,
                bottom: 0,
                label: { show: false },
                itemStyle: { areaColor: '#0b2a3c', borderColor: '#315f74', borderWidth: .65 },
                emphasis: { itemStyle: { areaColor: '#0f5366', borderColor: '#58cce0' }, label: { show: false } },
                select: { disabled: true, label: { show: false } },
            },
            series: [
                {
                    name: 'Island labels',
                    type: 'scatter',
                    coordinateSystem: 'geo',
                    data: islandLabels,
                    symbolSize: 3,
                    silent: true,
                    itemStyle: { color: 'rgba(0,0,0,0)' },
                    label: {
                        show: true,
                        position: 'top',
                        formatter: params => params.name,
                        color: 'rgba(119,164,181,.62)',
                        fontSize: 7,
                        fontWeight: 700,
                        letterSpacing: 1.2,
                        textBorderColor: 'rgba(2,16,27,.82)',
                        textBorderWidth: 2,
                    },
                    emphasis: { disabled: true },
                    tooltip: { show: false },
                    z: 2,
                },
                {
                    name: 'Exact location',
                    type: 'effectScatter',
                    coordinateSystem: 'geo',
                    data: exactPoints,
                    symbolSize: value => 7 + Math.min(9, Number(value?.[2] || 1) * .55),
                    rippleEffect: { scale: 2.2, brushType: 'stroke' },
                    itemStyle: { color: '#36efc6', shadowBlur: 8, shadowColor: 'rgba(54,239,198,.5)' },
                    z: 4,
                },
                {
                    name: 'Regional reference',
                    type: 'scatter',
                    coordinateSystem: 'geo',
                    data: referencePoints,
                    symbol: 'circle',
                    symbolSize: value => 7 + Math.min(7, Number(value?.[2] || 1) * .45),
                    itemStyle: { color: '#f4c95d', borderColor: '#fff0b7', borderWidth: 1.2, shadowBlur: 7, shadowColor: 'rgba(244,201,93,.36)' },
                    z: 3,
                },
            ],
        }, true);
    };

    root.querySelectorAll('[data-nx5-add-row]').forEach(button => {
        button.addEventListener('click', () => {
            const name = button.getAttribute('data-nx5-add-row');
            const template = root.querySelector(`template[data-nx5-template="${name}"]`);
            const repeater = root.querySelector(`[data-nx5-repeater="${name}"]`);
            if (!name || !template || !repeater) return;
            const index = repeater.querySelectorAll('[data-nx5-row]').length;
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('input, select, textarea').forEach(field => {
                const fieldName = field.getAttribute('name');
                if (fieldName) field.setAttribute('name', fieldName.replace('__NAME__', `${name}[${index}]`));
                if (field.type === 'checkbox') field.checked = false;
                else if (field.tagName === 'SELECT') field.selectedIndex = 0;
                else field.value = '';
            });
            repeater.appendChild(fragment);
            const addedRow = repeater.querySelector('[data-nx5-row]:last-child');
            refreshRecordSummary(addedRow);
            updateDirtyState();
            window.requestAnimationFrame(() => addedRow?.querySelector('input, select, textarea')?.focus({ preventScroll: true }));
            if (name === 'regions') renderRegionMap();
        });
    });

    root.addEventListener('click', event => {
        const removeButton = event.target.closest('[data-nx5-remove-row]');
        if (!removeButton) return;
        const row = removeButton.closest('[data-nx5-row]');
        const repeater = removeButton.closest('[data-nx5-repeater]');
        if (!row || !repeater) return;
        row.remove();
        const name = repeater.getAttribute('data-nx5-repeater');
        if (name) reindexRepeater(name);
        updateDirtyState();
        if (name === 'regions') renderRegionMap();
    });

    const saveDraft = async ({ quiet = false } = {}) => {
        if (!draftUrl) return false;
        if (draftAbortController) draftAbortController.abort();
        draftAbortController = new AbortController();
        const payload = new FormData(form);
        payload.delete('_method');
        payload.delete('profile_image');
        payload.delete('remove_profile_image');
        try {
            if (draftState && !quiet) draftState.lastChild.textContent = ' Saving draft…';
            const response = await fetch(draftUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: payload,
                credentials: 'same-origin',
                signal: draftAbortController.signal,
            });
            if (!response.ok) throw new Error(`Draft save failed (${response.status})`);
            if (draftState) {
                const mediaPending = Boolean(profileImageInput?.files?.length) || profileImageRemoveValue?.value === '1';
                draftState.lastChild.textContent = mediaPending ? ' Draft saved · media pending Save Changes' : ' Draft saved just now';
                draftState.classList.remove('is-error');
            }
            return true;
        } catch (error) {
            if (error?.name === 'AbortError') return false;
            if (draftState) {
                draftState.lastChild.textContent = ' Draft not saved';
                draftState.classList.add('is-error');
            }
            return false;
        }
    };

    const scheduleAutosave = () => {
        window.clearTimeout(autosaveTimer);
        autosaveTimer = window.setTimeout(() => {
            if (forceDirty || snapshot() !== initialState) saveDraft({ quiet: true });
        }, 1800);
    };

    form.addEventListener('input', event => {
        const row = event.target.closest?.('[data-nx5-row]');
        if (row) refreshRecordSummary(row);
        refreshPreview();
        updateDirtyState();
        scheduleAutosave();
        renderRegionMap();
    });
    form.addEventListener('change', event => {
        const row = event.target.closest?.('[data-nx5-row]');
        if (row) refreshRecordSummary(row);
        refreshPreview();
        updateDirtyState();
        scheduleAutosave();
        renderRegionMap();
    });
    form.addEventListener('submit', () => {
        submitting = true;
        window.clearTimeout(autosaveTimer);
        draftAbortController?.abort();
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = 'Saving…';
        }
        if (discardButton) discardButton.disabled = true;
        if (saveDraftButton) saveDraftButton.disabled = true;
        if (draftState) draftState.lastChild.textContent = ' Saving company…';
    });

    discardButton?.addEventListener('click', () => {
        if (restoredDraft && editUrl) {
            window.location.assign(editUrl);
            return;
        }
        form.reset();
        syncProfileImageState({ src: initialProfileImageVisible ? initialProfileImageSrc : null, removed: false });
        if (!initialProfileImageVisible && profileImagePreview) { profileImagePreview.hidden = true; profileImagePreview.removeAttribute('src'); }
        if (profileImageEmpty) profileImageEmpty.hidden = initialProfileImageVisible;
        if (profileImageRemove) profileImageRemove.hidden = !initialProfileImageVisible;
        forceDirty = false;
        initialState = snapshot();
        refreshPreview();
        updateDirtyState();
        renderRegionMap();
    });
    saveDraftButton?.addEventListener('click', () => saveDraft());

    root.querySelectorAll('[data-nx5-jump-target]').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-nx5-jump-target');
            const target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;
            target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'center' });
            target.classList.remove('is-guided-focus');
            window.requestAnimationFrame(() => target.classList.add('is-guided-focus'));
            window.setTimeout(() => target.classList.remove('is-guided-focus'), 1700);
            const addButton = target.querySelector('[data-nx5-add-row]');
            if (addButton && target.querySelectorAll('[data-nx5-row]').length === 0) {
                window.setTimeout(() => addButton.focus({ preventScroll: true }), 350);
            }
        });
    });

    root.querySelectorAll('[data-nx5-spark]').forEach(node => {
        const values = (node.dataset.values || '').split(',').map(value => Number(value)).filter(value => Number.isFinite(value));
        if (!window.echarts || values.length < 2) {
            node.classList.add('is-empty');
            node.textContent = 'No trend history';
            return;
        }
        const chart = window.echarts.init(node, null, { renderer: 'svg' });
        chart.setOption({
            animation: !reducedMotion,
            animationDuration: reducedMotion ? 0 : 420,
            animationEasing: 'cubicOut',
            grid: { left: 0, right: 0, top: 2, bottom: 0 },
            xAxis: { type: 'category', show: false, data: values.map((_, index) => index + 1), boundaryGap: false },
            yAxis: { type: 'value', show: false, scale: true },
            series: [{
                type: 'line',
                data: values,
                symbol: 'none',
                smooth: 0.32,
                lineStyle: { width: 1, color: '#2edca7' },
                areaStyle: { color: 'rgba(46,220,167,.09)' },
            }],
        });
        window.addEventListener('resize', () => chart.resize(), { passive: true });
    });

    const radarNode = root.querySelector('[data-nx5-performance-radar]');
    if (radarNode && window.echarts) {
        const value = key => Math.max(0, Math.min(100, Number(radarNode.dataset[key] || 0)));
        const radar = window.echarts.init(radarNode, null, { renderer: 'svg' });
        radar.setOption({
            animation: !reducedMotion,
            animationDuration: reducedMotion ? 0 : 520,
            animationEasing: 'cubicOut',
            radar: {
                center: ['50%', '48%'], radius: '70%', splitNumber: 3,
                indicator: [{ name: 'Complete', max: 100 }, { name: 'Accurate', max: 100 }, { name: 'Governance', max: 100 }, { name: 'Responsive', max: 100 }],
                axisName: { color: '#6f98aa', fontSize: 7 },
                axisLine: { lineStyle: { color: 'rgba(73,150,173,.25)' } },
                splitLine: { lineStyle: { color: 'rgba(73,150,173,.18)' } },
                splitArea: { areaStyle: { color: ['rgba(9,48,64,.10)', 'rgba(9,48,64,.025)'] } },
            },
            series: [{
                type: 'radar', symbol: 'circle', symbolSize: 3,
                lineStyle: { width: 1.4, color: '#19d9c1' },
                itemStyle: { color: '#19d9c1' },
                areaStyle: { color: 'rgba(25,217,193,.18)' },
                data: [[value('completeness'), value('accuracy'), value('compliance'), value('responsiveness')]],
            }],
        });
        window.addEventListener('resize', () => radar.resize(), { passive: true });
    }

    const mapNode = root.querySelector('[data-nx5-region-map]');
    const mapEmpty = root.querySelector('[data-nx5-region-map-empty]');
    if (mapNode && window.echarts) {
        regionMap = window.echarts.init(mapNode, null, { renderer: 'svg' });
        setRegionMapState({ tone: 'loading', title: 'Loading regional map', copy: 'Preparing Indonesia coverage and company locations.' });

        const geoRequest = fetch(mapNode.dataset.geojsonUrl, { credentials: 'same-origin' })
            .then(response => {
                if (!response.ok) throw new Error(`Map data failed (${response.status})`);
                return response.json();
            })
            .then(geojson => {
                window.echarts.registerMap('nx5-indonesia', geojson);
                regionGeoReady = true;
            });

        const anchorsUrl = mapNode.dataset.regionAnchorsUrl;
        const anchorRequest = anchorsUrl
            ? fetch(anchorsUrl, { credentials: 'same-origin' })
                .then(response => {
                    if (!response.ok) throw new Error(`Region reference data failed (${response.status})`);
                    return response.json();
                })
                .then(registerRegionAnchors)
                .catch(() => { regionAnchorsReady = false; })
            : Promise.resolve();

        Promise.all([geoRequest, anchorRequest])
            .then(() => renderRegionMap())
            .catch(() => {
                if (mapEmpty) {
                    mapEmpty.hidden = false;
                    mapEmpty.textContent = 'Regional map data could not be loaded. Region records remain editable.';
                }
                setRegionMapState({ tone: 'danger', title: 'Map unavailable', copy: 'The local GeoJSON asset could not be loaded.' });
            });

        window.addEventListener('resize', () => regionMap?.resize(), { passive: true });
    } else if (mapNode) {
        setRegionMapState({ tone: 'danger', title: 'Map unavailable', copy: 'Apache ECharts is not available. Region records remain editable.' });
    }

    window.addEventListener('beforeunload', event => {
        if (submitting) return;
        if (forceDirty || snapshot() !== initialState) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    refreshPreview();
    updateDirtyState();
    root.querySelectorAll('[data-nx5-row]').forEach(refreshRecordSummary);
});
