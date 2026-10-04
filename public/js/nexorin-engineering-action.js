(() => {
    'use strict';

    if (window.NexorinEngineeringAction) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const root = document.documentElement;
    const FINAL_DWELL = 280;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const icons = {
        calculate: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="m6 15 4-4 3 3 5-7"/><path d="M16 7h2v2"/></svg>',
        save: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>',
        verify: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/></svg>',
        upload: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v11"/><path d="m7 8 5-5 5 5"/><path d="M5 14v6h14v-6"/></svg>',
        filter: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M7 12h10M10 18h4"/></svg>',
        open: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M14 7l5 5-5 5"/><rect x="3" y="4" width="18" height="16" rx="2"/></svg>',
        equipment: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 6h14v12H5z"/><path d="M8 10h8M8 14h5"/><path d="M9 3v3M15 3v3"/></svg>',
        bill: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        project: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6"/><path d="M3 19h18"/></svg>',
        cockpit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 15l3-3 2 2 4-5 2 2"/><path d="M7 8h3"/></svg>',
        portfolio: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h6l2 2h8v10H4z"/><path d="M4 7V5h6l2 2"/></svg>',
        edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4.2-1 10-10a2 2 0 0 0-3-3l-10 10L4 20Z"/><path d="m13.5 7.5 3 3"/></svg>',
        load: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V8M10 19V4M16 19v-7M22 19H2"/><path d="m7 12 3-3 3 2 4-5"/></svg>',
        solar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
        roof: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 12 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/></svg>',
        recommendation: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 18h6M10 22h4"/><path d="M8.5 14.5A6 6 0 1 1 15.5 14.5c-.9.6-1.5 1.4-1.5 2.5h-4c0-1.1-.6-1.9-1.5-2.5Z"/></svg>',
        technical: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15h16l-2-8H6l-2 8z"/><path d="M8 7l-1 8M12 7v8M16 7l1 8M3 19h18"/><path d="M12 15v4"/></svg>',
        bom: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/><path d="m3 6 2 2 3-4"/></svg>',
        financial: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M7 16V9M12 16V5M17 16v-4"/><path d="m5 8 5-4 4 3 5-5"/></svg>',
        quotation: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        auth: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg>',
    };

    const normalizeSteps = (steps) => Array.isArray(steps) && steps.length
        ? steps.map((step) => String(step || '').trim()).filter(Boolean)
        : ['Validating the current request', 'Synchronizing engineering context', 'Preparing the controlled handoff'];

    const normalizeMeta = (value, fallback) => {
        const clean = String(value || '').replace(/\s+/g, ' ').trim();
        return clean || fallback;
    };

    const stageVerb = (step) => {
        const first = String(step || 'Processing').trim().split(/\s+/)[0] || 'Processing';
        return first.replace(/[.:;,]+$/g, '').toUpperCase();
    };

    const remove = () => {
        document.querySelector('.nxeng-action-overlay[data-nxeng-dynamic="true"]')?.remove();
        if (!root.classList.contains('workspace-entry-pending')) {
            root.classList.remove('is-nxeng-processing');
        }
    };

    const show = ({
        kind = 'calculate',
        title = 'Processing engineering workflow',
        copy = 'NEXORIN is applying the latest controlled project inputs.',
        steps = [],
        duration = 960,
        signal = 'NEXORIN orchestration layer',
        metaLeft = 'Project context secured',
        metaRight = 'Traceability preserved',
        accent = '#13aaa2',
        secondary = '#f3a04f',
        icon = kind,
        eyebrow = 'Controlled engineering sequence',
    } = {}) => {
        remove();

        const safeSteps = normalizeSteps(steps);
        const requestedDuration = Math.max(800, Number(duration) || 960);
        const runDuration = reducedMotion ? 180 : requestedDuration;
        const animationDuration = reducedMotion ? 80 : Math.max(320, runDuration - FINAL_DWELL);
        const operationIcon = icons[icon] || icons[kind] || icons.calculate;
        const overlay = document.createElement('div');

        overlay.className = `nxeng-action-overlay is-${kind}`;
        overlay.dataset.nxengDynamic = 'true';
        overlay.setAttribute('aria-hidden', 'false');
        overlay.style.setProperty('--nxeng-action-accent', accent);
        overlay.style.setProperty('--nxeng-action-secondary', secondary);
        overlay.innerHTML = `
            <div class="nxeng-action-ambient" aria-hidden="true"><span></span><span></span><span></span></div>
            <section class="nxeng-action-deck" role="status" aria-live="polite" aria-label="${escapeHtml(title)}">
                <aside class="nxeng-action-instrument" aria-hidden="true">
                    <div class="nxeng-action-brand">
                        <strong>NEXORIN</strong>
                        <span>Engineering orchestration</span>
                    </div>
                    <div class="nxeng-action-orbit">
                        <span class="nxeng-action-core">${operationIcon}</span>
                        <i></i><i></i><i></i>
                    </div>
                    <div class="nxeng-action-readout">
                        <small data-nxeng-sequence>SEQUENCE 01 / ${String(safeSteps.length).padStart(2, '0')}</small>
                        <strong data-nxeng-state>${escapeHtml(stageVerb(safeSteps[0]))}</strong>
                    </div>
                    <div class="nxeng-action-telemetry">
                        <span><small>Context</small><b>${escapeHtml(normalizeMeta(metaLeft, 'Project context secured'))}</b></span>
                        <span><small>Control</small><b>${escapeHtml(normalizeMeta(metaRight, 'Traceability preserved'))}</b></span>
                    </div>
                </aside>

                <div class="nxeng-action-console">
                    <header class="nxeng-action-head">
                        <span class="nxeng-action-signal"><i></i>${escapeHtml(signal)}</span>
                        <span class="nxeng-action-eyebrow">${escapeHtml(eyebrow)}</span>
                        <h2>${escapeHtml(title)}</h2>
                        <p>${escapeHtml(copy)}</p>
                    </header>

                    <div class="nxeng-action-steps">
                        ${safeSteps.map((step, index) => `
                            <div class="${index === 0 ? 'is-active' : ''}">
                                <span>${String(index + 1).padStart(2, '0')}</span>
                                <b>${escapeHtml(step)}</b>
                                <i aria-hidden="true"></i>
                            </div>
                        `).join('')}
                    </div>

                    <footer class="nxeng-action-footer">
                        <div class="nxeng-action-track" aria-hidden="true"><span></span></div>
                        <span data-nxeng-caption>Executing controlled sequence</span>
                    </footer>
                </div>
            </section>`;

        document.body.appendChild(overlay);
        root.classList.add('is-nxeng-processing');
        requestAnimationFrame(() => overlay.classList.add('is-visible'));

        const sequence = overlay.querySelector('[data-nxeng-sequence]');
        const state = overlay.querySelector('[data-nxeng-state]');
        const caption = overlay.querySelector('[data-nxeng-caption]');
        const track = overlay.querySelector('.nxeng-action-track > span');
        const nodes = Array.from(overlay.querySelectorAll('.nxeng-action-steps > div'));
        const start = performance.now();

        const render = (now) => {
            if (!document.body.contains(overlay)) return;

            const elapsed = Math.max(0, now - start);
            const raw = Math.min(1, elapsed / animationDuration);
            const eased = 1 - Math.pow(1 - raw, 2.2);
            const activeIndex = raw >= 1
                ? nodes.length
                : Math.min(nodes.length - 1, Math.floor(raw * nodes.length));

            if (track) track.style.width = `${Math.round(eased * 100)}%`;

            nodes.forEach((node, index) => {
                node.classList.toggle('is-active', index === activeIndex);
                node.classList.toggle('is-complete', index < activeIndex || raw >= 1);
            });

            if (raw >= 1) {
                overlay.classList.add('is-ready');
                if (sequence) sequence.textContent = `SEQUENCE ${String(nodes.length).padStart(2, '0')} / ${String(nodes.length).padStart(2, '0')}`;
                if (state) state.textContent = 'READY';
                if (caption) caption.textContent = 'Controlled handoff ready';
                return;
            }

            const sequenceNumber = Math.min(nodes.length, activeIndex + 1);
            if (sequence) sequence.textContent = `SEQUENCE ${String(sequenceNumber).padStart(2, '0')} / ${String(nodes.length).padStart(2, '0')}`;
            if (state) state.textContent = stageVerb(safeSteps[Math.min(activeIndex, safeSteps.length - 1)]);
            if (caption) caption.textContent = `${safeSteps[Math.min(activeIndex, safeSteps.length - 1)]}`;

            requestAnimationFrame(render);
        };

        requestAnimationFrame(render);
        return { overlay, duration: runDuration, remove };
    };

    const isPlainLeftClick = (event, anchor) => {
        if (event.defaultPrevented || event.button !== 0) return false;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
        if (anchor.target && anchor.target !== '_self') return false;
        if (anchor.hasAttribute('download')) return false;
        const href = anchor.getAttribute('href') || '';
        return href !== '' && !href.startsWith('#') && !href.startsWith('javascript:');
    };

    const navigate = (event, anchor, config = {}) => {
        if (!isPlainLeftClick(event, anchor)) return false;

        // Routine GET navigation must remain native and immediate. Earlier builds
        // blocked every page transition for 0.8-1.3 seconds only to display a
        // decorative orchestration overlay, which made the entire application
        // feel slow even when the destination response was already fast.
        if (config.blockingNavigation !== true) {
            remove();
            return true;
        }

        event.preventDefault();
        const href = anchor.href;
        const { duration } = show(config);
        window.setTimeout(() => { window.location.assign(href); }, duration);
        return true;
    };

    window.addEventListener('pageshow', () => {
        remove();
        document.querySelectorAll('[data-nxla-submitting], [data-solar-submitting], [data-rss-submitting], [data-nx-project-flow-submitting], [data-nxrec-submitting]').forEach((node) => {
            delete node.dataset.nxlaSubmitting;
            delete node.dataset.solarSubmitting;
            delete node.dataset.rssSubmitting;
            delete node.dataset.nxProjectFlowSubmitting;
            delete node.dataset.nxrecSubmitting;
            node.querySelectorAll?.('[aria-busy="true"]').forEach((button) => {
                button.removeAttribute('aria-busy');
                button.disabled = false;
            });
        });
    });

    window.NexorinEngineeringAction = Object.freeze({
        show,
        remove,
        navigate,
        isPlainLeftClick,
        icons: Object.freeze({ ...icons }),
        reducedMotion,
    });
})();
