/**
 * NEXORIN Page 022 — Edit Material (Persisted Material Mutation Workspace)
 * Works alongside nexorin-material-catalog.js to provide:
 * - Component class artwork resolution from /images/material-classes/*.webp
 * - Live 6-column primary engineering parameter readout updates
 * - Category-switch stale vs preserved technical key analysis + restore button
 * - Governance vs Administrative mutation classification & live Change Impact rail
 * - Technical specification filter tabs (All / Required / Missing / Modified)
 * - Reset to Persisted Baseline
 */
(() => {
    'use strict';

    const workspace = document.querySelector('[data-nxmce-workspace]');
    const form = document.getElementById('nxmceEditForm');
    if (!workspace || !form) return;

    const parseJson = (selector, fallback = {}) => {
        const el = document.querySelector(selector);
        if (!el) return fallback;
        try {
            return JSON.parse(el.textContent || '{}');
        } catch {
            return fallback;
        }
    };

    const schemas = parseJson('[data-nxmc-specification-schema-payload]', {});
    const baseline = parseJson('[data-nxmce-baseline-payload]', {});
    const baselineSpecs = (baseline && typeof baseline.specs === 'object' && baseline.specs) ? baseline.specs : {};
    const appliedSpecKeys = new Set(Array.isArray(baseline.applied_spec_keys) ? baseline.applied_spec_keys : []);

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

    const categorySelect = form.querySelector('#material_category_id');
    const visualFrame = form.querySelector('[data-nxmce-class-visual]');
    const visualImage = form.querySelector('[data-nxmce-class-image]');
    const visualCode = form.querySelector('[data-nxmce-visual-code]');
    const readoutRoot = form.querySelector('[data-nxmce-key-readout]');
    const dynamicSpecRoot = form.querySelector('[data-nxmc-dynamic-specifications]');
    const switchBanner = form.querySelector('[data-nxmce-category-switch-banner]');
    const switchSummary = form.querySelector('[data-nxmce-switch-summary]');
    const switchPreservedCount = form.querySelector('[data-nxmce-switch-preserved-count]');
    const switchPreservedList = form.querySelector('[data-nxmce-switch-preserved-list]');
    const switchStaleCount = form.querySelector('[data-nxmce-switch-stale-count]');
    const switchStaleList = form.querySelector('[data-nxmce-switch-stale-list]');
    const restoreClassBtn = form.querySelector('[data-nxmce-restore-class]');

    let activeSpecFilter = 'all';

    const norm = (v) => String(v ?? '').trim();
    const humanizeKey = (key) => String(key || '').replaceAll('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());

    const allKnownFieldLabels = new Map();
    Object.values(schemas || {}).forEach((sc) => {
        (sc?.groups || []).forEach((grp) => {
            (grp?.fields || []).forEach((f) => {
                if (f?.key && f?.label) {
                    allKnownFieldLabels.set(String(f.key), String(f.label));
                }
            });
        });
    });
    const labelForKey = (key) => allKnownFieldLabels.get(String(key)) || humanizeKey(key);
    const normArr = (arr) => [...(arr || [])].map((x) => norm(x)).filter(Boolean).sort().join('|');

    const getSelectedOption = () => categorySelect?.selectedOptions?.[0] || null;
    const getCategoryCode = () => getSelectedOption()?.dataset?.code || 'DEFAULT';
    const getProfileCode = () => getSelectedOption()?.dataset?.profileCode || 'DEFAULT';
    const getActiveSchema = () => schemas[getCategoryCode()] || schemas[getProfileCode()] || schemas.DEFAULT || { groups: [] };

    const cleanSelectedClass = () => norm(getSelectedOption()?.textContent?.replace(/^—\s*/, '')?.replace(/\s*\(Current Legacy Profile\)$/i, '')) || baseline.category_name || 'Component Class';

    const updateClassVisual = () => {
        if (!visualFrame || !visualImage) return;
        const code = getCategoryCode();
        const schema = getActiveSchema();
        const base = (visualFrame.dataset.nxmceImageBase || '/images/material-classes').replace(/\/$/, '');
        const file = classImages[code] || 'engineering-catalog-empty-state.webp';
        visualImage.src = `${base}/${file}`;
        if (visualCode) {
            visualCode.textContent = cleanSelectedClass() || 'Engineering Class';
        }
    };

    const updateKeyReadout = () => {
        if (!readoutRoot) return;
        const schema = getActiveSchema();
        const allFields = (schema.groups || []).flatMap((g) => g.fields || []);
        const conciseRequired = allFields.filter((f) => f.required && (f.unit || f.input_type === 'number'));
        const otherRequired = allFields.filter((f) => f.required && !(f.unit || f.input_type === 'number') && f.input_type !== 'textarea');
        let topFields = [...conciseRequired, ...otherRequired].slice(0, 6);
        if (!topFields.length) topFields = allFields.filter((f) => f.input_type !== 'textarea').slice(0, 6);

        readoutRoot.replaceChildren();
        if (!topFields.length) {
            const cell = document.createElement('div');
            cell.className = 'nxmce-readout-cell';
            cell.innerHTML = '<small>Component schema</small><strong class="is-empty">Select an exact component class</strong>';
            readoutRoot.appendChild(cell);
            return;
        }

        topFields.forEach((f) => {
            const input = dynamicSpecRoot?.querySelector(`[data-nxmc-spec-input="${f.key}"]`);
            const val = norm(input ? input.value : baselineSpecs[f.key]);
            const unit = norm(f.unit);
            const cell = document.createElement('div');
            cell.className = 'nxmce-readout-cell';
            cell.dataset.nxmceReadoutKey = f.key;
            const small = document.createElement('small');
            small.textContent = f.label || f.key;
            const strong = document.createElement('strong');
            if (val !== '') {
                strong.className = 'nxmc-mono';
                strong.textContent = unit ? `${val} ${unit}` : val;
            } else {
                strong.className = 'is-empty';
                strong.textContent = 'Not recorded';
            }
            cell.append(small, strong);
            readoutRoot.appendChild(cell);
        });
    };

    const applySpecFilter = (mode) => {
        activeSpecFilter = mode || 'all';
        form.querySelectorAll('[data-nxmce-spec-filter]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.nxmceSpecFilter === activeSpecFilter);
        });

        if (!dynamicSpecRoot) return;
        dynamicSpecRoot.querySelectorAll('.nxspec-field').forEach((fieldNode) => {
            const inp = fieldNode.querySelector('[data-nxmc-spec-input]');
            const key = inp?.dataset?.nxmcSpecInput || '';
            const isReq = inp?.dataset?.nxmcSpecRequired === 'true';
            const curVal = norm(inp?.value);
            const baseVal = norm(baselineSpecs[key]);
            const isRecorded = curVal !== '';
            const isModified = curVal !== baseVal;

            let visible = true;
            if (activeSpecFilter === 'required') visible = isReq;
            else if (activeSpecFilter === 'missing') visible = isReq && !isRecorded;
            else if (activeSpecFilter === 'modified') visible = isModified;

            fieldNode.hidden = !visible;
        });

        balanceSpecFieldGrids();
    };

    const balanceSpecFieldGrids = () => {
        if (!dynamicSpecRoot) return;
        dynamicSpecRoot.querySelectorAll('.nxspec-group').forEach((grp) => {
            const allFields = [...grp.querySelectorAll('.nxspec-field')];
            const visibleFields = allFields.filter((f) => !f.hidden);
            grp.hidden = visibleFields.length === 0;
            allFields.forEach((f) => {
                f.classList.remove('is-span-full');
                if (visibleFields.length % 2 === 0) {
                    f.classList.remove('is-wide');
                }
            });
            if (visibleFields.length % 2 === 1) {
                visibleFields[visibleFields.length - 1].classList.add('is-span-full');
            }
        });
    };

    const evaluateMutationState = () => {
        const schema = getActiveSchema();
        const schemaFields = (schema.groups || []).flatMap((g) => g.fields || []);
        const validKeysMap = new Map(schemaFields.map((f) => [String(f.key), f]));


        dynamicSpecRoot?.querySelectorAll('.nxspec-field').forEach((fieldNode) => {
            const ta = fieldNode.querySelector('textarea');
            if (ta) {
                const unitBadge = fieldNode.querySelector('.nxspec-unit');
                if (unitBadge) unitBadge.remove();
                ta.style.height = 'auto';
                ta.style.height = `${Math.max(54, ta.scrollHeight + 2)}px`;
            }
        });

        // Decorate spec fields for modified state
        const modifiedSpecLabels = [];
        let modifiedAppliedDatasheetKeys = 0;

        dynamicSpecRoot?.querySelectorAll('.nxspec-field').forEach((fieldNode) => {
            const inp = fieldNode.querySelector('[data-nxmc-spec-input]');
            if (!inp) return;
            const key = inp.dataset.nxmcSpecInput;
            const curVal = norm(inp.value);
            const baseVal = norm(baselineSpecs[key]);
            const isMod = curVal !== baseVal;
            fieldNode.classList.toggle('is-modified', isMod);
            const stateBadge = fieldNode.querySelector('.nxspec-field-state');
            if (stateBadge) {
                if (isMod) {
                    stateBadge.textContent = curVal !== '' ? 'Modified' : 'Cleared';
                } else {
                    const isReq = inp.dataset.nxmcSpecRequired === 'true';
                    stateBadge.textContent = curVal !== '' ? 'Recorded' : (isReq ? 'Required' : 'Optional');
                }
            }
            if (isMod) {
                const fDef = validKeysMap.get(key);
                modifiedSpecLabels.push(fDef?.label || key);
                if (appliedSpecKeys.has(key)) modifiedAppliedDatasheetKeys++;
            }
        });

        // Category switch stale vs preserved analysis
        const currentCategoryId = norm(categorySelect?.value);
        const baselineCategoryId = norm(baseline.material_category_id);
        const categorySwitched = currentCategoryId !== baselineCategoryId;
        const preservedKeys = [];
        const staleRemovedKeys = [];

        Object.entries(baselineSpecs).forEach(([k, v]) => {
            if (norm(v) === '') return;
            if (validKeysMap.has(k)) {
                preservedKeys.push(validKeysMap.get(k)?.label || labelForKey(k));
            } else {
                staleRemovedKeys.push(`${labelForKey(k)} (${v})`);
            }
        });

        const summarizeLabels = (arr, limit = 6) => {
            if (!arr.length) return 'None';
            if (arr.length <= limit) return arr.join(' · ');
            return `${arr.slice(0, limit).join(' · ')} (+${arr.length - limit} more)`;
        };

        if (switchBanner) {
            switchBanner.hidden = !categorySwitched;
            if (categorySwitched) {
                if (switchSummary) {
                    switchSummary.textContent = staleRemovedKeys.length
                        ? `Switching to ${schema.title || 'the selected class'} removes ${staleRemovedKeys.length} incompatible specification(s) on save and resets catalog approval to Needs Review.`
                        : `Switching to ${schema.title || 'the selected class'} updates the active engineering schema and requires catalog re-review.`;
                }
                if (switchPreservedCount) switchPreservedCount.textContent = String(preservedKeys.length);
                if (switchPreservedList) switchPreservedList.textContent = summarizeLabels(preservedKeys, 6);
                if (switchStaleCount) switchStaleCount.textContent = String(staleRemovedKeys.length);
                if (switchStaleList) switchStaleList.textContent = summarizeLabels(staleRemovedKeys, 6);
            }
        }

        // Compare base fields
        const changedGovernance = [];
        const changedAdmin = [];

        const checkScalar = (selector, baseKey, label, isGov) => {
            const el = form.querySelector(selector);
            if (!el) return;
            const cur = el.type === 'checkbox' ? Boolean(el.checked) : norm(el.value);
            const base = el.type === 'checkbox' ? Boolean(baseline[baseKey]) : norm(baseline[baseKey]);
            if (cur !== base) {
                if (isGov) changedGovernance.push(label);
                else changedAdmin.push(label);
            }
        };

        checkScalar('#sku', 'sku', 'Catalog SKU', true);
        checkScalar('#name', 'name', 'Material Name', true);
        checkScalar('#brand', 'brand', 'Manufacturer / Brand', true);
        checkScalar('#model', 'model', 'Model / Part Number', true);
        checkScalar('#material_category_id', 'material_category_id', 'Component Class', true);
        checkScalar('#uom', 'uom', 'Unit of Measure', true);
        checkScalar('#default_scope_mode', 'default_scope_mode', 'Default Scope Mode', true);
        checkScalar('#is_active', 'is_active', 'Active Availability', true);
        checkScalar('#technical_notes', 'technical_notes', 'Technical Notes', true);

        checkScalar('#material_type', 'material_type', 'Material Type', false);
        checkScalar('#cost_group', 'cost_group', 'Cost Group', false);
        checkScalar('#description', 'description', 'Catalog Description', false);

        const getChecked = (name) => [...form.querySelectorAll(`input[name="${name}"]:checked`)].map((c) => c.value);
        if (normArr(getChecked('system_types[]')) !== normArr(baseline.system_types)) {
            changedGovernance.push('System Applicability');
        }
        if (normArr(getChecked('project_scales[]')) !== normArr(baseline.project_scales)) {
            changedGovernance.push('Project Scale Suitability');
        }
        if (normArr(getChecked('suitable_roof_types[]')) !== normArr(baseline.suitable_roof_types)) {
            changedGovernance.push('Roof & Site Suitability');
        }

        if (modifiedSpecLabels.length > 0) {
            changedGovernance.push(`Technical Specs (${modifiedSpecLabels.length})`);
        }
        if (categorySwitched && staleRemovedKeys.length > 0) {
            changedGovernance.push(`Stale Specs Dropped (${staleRemovedKeys.length})`);
        }

        const totalModified = changedGovernance.length + changedAdmin.length;
        const hasGovChange = changedGovernance.length > 0;
        const hasAdminOnly = !hasGovChange && changedAdmin.length > 0;
        const wasApproved = baseline.catalog_status === 'approved';

        // Sync Header & Live Badges
        const liveSignal = workspace.querySelector('[data-nxmce-live-signal]');
        const liveSignalText = workspace.querySelector('[data-nxmce-live-signal-text]');
        const headlineName = workspace.querySelector('[data-nxmce-headline-name]');
        const headlineBrand = workspace.querySelector('[data-nxmce-headline-brand]');
        const headlineSku = workspace.querySelector('[data-nxmce-headline-sku]');
        const modelBadge = workspace.querySelector('[data-nxmce-model-badge]');
        const descCounter = workspace.querySelector('[data-nxmce-desc-counter]');
        const notesCounter = workspace.querySelector('[data-nxmce-notes-counter]');

        const nameInput = form.querySelector('#name');
        const brandInput = form.querySelector('#brand');
        const skuInput = form.querySelector('#sku');
        const modelInput = form.querySelector('#model');
        const descInput = form.querySelector('#description');
        const notesInput = form.querySelector('#technical_notes');

        if (headlineName && nameInput) headlineName.textContent = norm(nameInput.value) || baseline.name;
        if (headlineBrand && brandInput) headlineBrand.textContent = norm(brandInput.value) || 'Manufacturer not recorded';
        if (headlineSku && skuInput) headlineSku.textContent = `SKU: ${norm(skuInput.value) || baseline.sku}`;
        if (modelBadge && modelInput) {
            const hasModel = norm(modelInput.value) !== '';
            modelBadge.textContent = hasModel ? 'Recorded' : 'Not recorded';
            modelBadge.className = `nxmce-inline-status ${hasModel ? 'is-recorded' : 'is-missing'}`;
        }
        if (descCounter && descInput) descCounter.textContent = `${descInput.value.length} / 2000 characters`;
        if (notesCounter && notesInput) notesCounter.textContent = `${notesInput.value.length} / 2000 characters`;

        // Sync Reset buttons
        workspace.querySelectorAll('[data-nxmce-reset-form]').forEach((btn) => {
            btn.disabled = totalModified === 0;
        });

        // Sync Change Impact Rail & Action Bar
        const impactBadge = workspace.querySelector('[data-nxmce-impact-badge]');
        const impactSummary = workspace.querySelector('[data-nxmce-impact-summary]');
        const impactCount = workspace.querySelector('[data-nxmce-impact-count]');
        const impactBasis = workspace.querySelector('[data-nxmce-impact-basis]');
        const impactApproval = workspace.querySelector('[data-nxmce-impact-approval]');
        const impactSchema = workspace.querySelector('[data-nxmce-impact-schema]');
        const impactEvidence = workspace.querySelector('[data-nxmce-impact-evidence]');
        const changedBox = workspace.querySelector('[data-nxmce-changed-fields-box]');
        const changedList = workspace.querySelector('[data-nxmce-changed-fields-list]');
        const footerTitle = workspace.querySelector('[data-nxmce-footer-title]');
        const footerConsequence = workspace.querySelector('[data-nxmce-footer-consequence]');

        if (impactCount) {
            impactCount.textContent = `${totalModified} ${totalModified === 1 ? 'change group' : 'change groups'} modified`;
        }

        const selectedClassTitle = cleanSelectedClass();
        if (impactSchema) {
            impactSchema.textContent = categorySwitched
                ? `Switching to ${selectedClassTitle} (${staleRemovedKeys.length} stale removed)`
                : `Same component class (${selectedClassTitle})`;
        }

        if (totalModified === 0) {
            if (liveSignal) liveSignal.className = 'nxmce-status-badge is-neutral';
            if (liveSignalText) liveSignalText.textContent = 'No unsaved changes';
            if (impactBadge) {
                impactBadge.className = 'nxmce-impact-badge is-clean';
                impactBadge.textContent = 'No persisted changes';
            }
            if (impactSummary) {
                impactSummary.textContent = 'The form currently matches the persisted catalog record. Modify any field to inspect approval and evidence consequences before saving.';
            }
            if (impactBasis) impactBasis.textContent = 'Unchanged';
            if (impactApproval) {
                impactApproval.textContent = wasApproved
                    ? 'Approved state preserved while clean'
                    : `Record is currently ${baseline.catalog_status_label || 'Needs Review'}`;
            }
            if (impactEvidence) {
                impactEvidence.textContent = baseline.has_evidence
                    ? 'Existing evidence preserved · No review triggered while clean'
                    : 'No linked evidence recorded';
            }
            if (footerTitle) footerTitle.textContent = 'Controlled material record · No unsaved changes';
            if (footerConsequence) {
                footerConsequence.className = 'nxmce-consequence-pill is-neutral';
                footerConsequence.textContent = 'Governance state unchanged';
            }
            if (changedBox) changedBox.hidden = true;
        } else if (hasGovChange) {
            if (liveSignal) liveSignal.className = 'nxmce-status-badge is-amber';
            if (liveSignalText) liveSignalText.textContent = wasApproved ? 'Approval will reset' : 'Engineering changes';
            if (impactBadge) {
                impactBadge.className = 'nxmce-impact-badge is-warning';
                impactBadge.textContent = wasApproved ? 'Approval invalidated' : 'Review required';
            }
            if (impactSummary) {
                impactSummary.textContent = wasApproved
                    ? 'Identity, component class, applicability, or technical parameters have changed. Saving will reset Catalog Status and Compliance to Needs Review.'
                    : 'Engineering parameters or identity have changed. Saving updates the material and keeps it in Needs Review until verified.';
            }
            if (impactBasis) impactBasis.textContent = 'Engineering / identity basis modified';
            if (impactApproval) {
                impactApproval.textContent = wasApproved
                    ? 'Resets Approved → Needs Review on save'
                    : 'Requires catalog review before approval';
            }
            if (impactEvidence) {
                if (modifiedAppliedDatasheetKeys > 0) {
                    impactEvidence.textContent = `Evidence preserved · ${modifiedAppliedDatasheetKeys} parser-applied field(s) manually edited (locks automatic parser rollback)`;
                } else if (baseline.has_evidence) {
                    impactEvidence.textContent = 'Evidence preserved · Linked datasheets/certificates require re-verification against edited values';
                } else {
                    impactEvidence.textContent = 'No linked evidence recorded';
                }
            }
            if (footerTitle) footerTitle.textContent = `Unsaved engineering mutation (${totalModified} modified)`;
            if (footerConsequence) {
                footerConsequence.className = 'nxmce-consequence-pill is-amber';
                footerConsequence.textContent = wasApproved ? 'Resets Approval to Needs Review' : 'Requires Catalog Review';
            }
        } else if (hasAdminOnly) {
            if (liveSignal) liveSignal.className = 'nxmce-status-badge is-teal';
            if (liveSignalText) liveSignalText.textContent = 'Administrative edit';
            if (impactBadge) {
                impactBadge.className = 'nxmce-impact-badge is-info';
                impactBadge.textContent = 'Administrative only';
            }
            if (impactSummary) {
                impactSummary.textContent = 'Only administrative/commercial organizers (Material Type, Cost Group, or Description) were modified. Existing catalog approval and compliance status will be preserved.';
            }
            if (impactBasis) impactBasis.textContent = 'Unchanged (administrative metadata only)';
            if (impactApproval) {
                impactApproval.textContent = wasApproved
                    ? 'Approved status preserved on save'
                    : `Remains ${baseline.catalog_status_label || 'Needs Review'}`;
            }
            if (impactEvidence) {
                impactEvidence.textContent = baseline.has_evidence
                    ? 'Existing evidence preserved and unaffected'
                    : 'No linked evidence recorded';
            }
            if (footerTitle) footerTitle.textContent = `Unsaved administrative edit (${totalModified} modified)`;
            if (footerConsequence) {
                footerConsequence.className = 'nxmce-consequence-pill is-blue';
                footerConsequence.textContent = 'Preserves Governance State';
            }
        }

        if (changedBox && changedList && totalModified > 0) {
            changedBox.hidden = false;
            changedList.replaceChildren();
            changedGovernance.forEach((lbl) => {
                const chip = document.createElement('span');
                chip.className = 'nxmce-changed-chip is-governance';
                chip.textContent = `${lbl} · Review`;
                changedList.appendChild(chip);
            });
            changedAdmin.forEach((lbl) => {
                const chip = document.createElement('span');
                chip.className = 'nxmce-changed-chip is-admin';
                chip.textContent = `${lbl} · Admin`;
                changedList.appendChild(chip);
            });
        }

        const visualSku = workspace.querySelector('[data-nxmce-visual-sku]');
        if (visualSku) visualSku.textContent = curSku || 'Not recorded';
        const visualUom = workspace.querySelector('[data-nxmce-visual-uom]');
        if (visualUom) visualUom.textContent = curUom || 'pcs';

        updateKeyReadout();
        if (activeSpecFilter !== 'all') {
            applySpecFilter(activeSpecFilter);
        } else {
            balanceSpecFieldGrids();
        }
    };

    // Bind filter buttons
    form.querySelectorAll('[data-nxmce-spec-filter]').forEach((btn) => {
        btn.addEventListener('click', () => applySpecFilter(btn.dataset.nxmceSpecFilter || 'all'));
    });

    // Restore original category button
    restoreClassBtn?.addEventListener('click', () => {
        if (!categorySelect) return;
        categorySelect.value = String(baseline.material_category_id || '');
        categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Reset entire form to persisted baseline
    const resetToBaseline = () => {
        const setVal = (sel, val) => {
            const el = form.querySelector(sel);
            if (!el) return;
            el.value = String(val ?? '');
            el.dispatchEvent(new Event('change', { bubbles: true }));
        };

        setVal('#sku', baseline.sku);
        setVal('#name', baseline.name);
        setVal('#brand', baseline.brand);
        setVal('#model', baseline.model);
        setVal('#uom', baseline.uom);
        setVal('#material_type', baseline.material_type);
        setVal('#cost_group', baseline.cost_group);
        setVal('#default_scope_mode', baseline.default_scope_mode);
        setVal('#description', baseline.description);
        setVal('#technical_notes', baseline.technical_notes);

        const activeCheck = form.querySelector('#is_active');
        if (activeCheck) activeCheck.checked = Boolean(baseline.is_active);

        const resetCheckGroup = (name, arr) => {
            const allowed = new Set((arr || []).map(String));
            form.querySelectorAll(`input[name="${name}"]`).forEach((box) => {
                box.checked = allowed.has(String(box.value));
            });
        };
        resetCheckGroup('system_types[]', baseline.system_types);
        resetCheckGroup('project_scales[]', baseline.project_scales);
        resetCheckGroup('suitable_roof_types[]', baseline.suitable_roof_types);

        if (categorySelect && String(categorySelect.value) !== String(baseline.material_category_id)) {
            categorySelect.value = String(baseline.material_category_id || '');
            categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Restore persisted spec values
        setTimeout(() => {
            dynamicSpecRoot?.querySelectorAll('[data-nxmc-spec-input]').forEach((inp) => {
                const k = inp.dataset.nxmcSpecInput;
                inp.value = String(baselineSpecs[k] ?? '');
                inp.dispatchEvent(new Event('input', { bubbles: true }));
            });
            applySpecFilter('all');
            evaluateMutationState();
        }, 10);
    };

    workspace.querySelectorAll('[data-nxmce-reset-form]').forEach((btn) => {
        btn.addEventListener('click', resetToBaseline);
    });

    form.addEventListener('input', () => requestAnimationFrame(evaluateMutationState));
    form.addEventListener('change', () => requestAnimationFrame(() => {
        updateClassVisual();
        evaluateMutationState();
    }));


    const autoResizeTextarea = (ta) => {
        if (!ta) return;
        ta.style.height = 'auto';
        ta.style.height = `${Math.max(84, ta.scrollHeight + 2)}px`;
    };
    const descTa = form.querySelector('#description');
    const notesTa = form.querySelector('#technical_notes');
    [descTa, notesTa].forEach((ta) => {
        if (!ta) return;
        autoResizeTextarea(ta);
        ta.addEventListener('input', () => autoResizeTextarea(ta));
    });

    // Initial run after nexorin-material-catalog.js mounts dynamic specs
    setTimeout(() => {
        updateClassVisual();
        evaluateMutationState();
    }, 30);
})();
