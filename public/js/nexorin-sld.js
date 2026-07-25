(() => {
    const workspace = document.querySelector('[data-sld-workspace]');
    if (!workspace) return;

    const stage = workspace.querySelector('[data-sld-stage]');
    const inner = workspace.querySelector('[data-sld-stage-inner]');
    const label = workspace.querySelector('[data-sld-zoom-label]');
    const shell = workspace.querySelector('[data-sld-drawing-shell]');
    const pages = [...workspace.querySelectorAll('[data-sld-sheet-page]')];
    const tabs = [...workspace.querySelectorAll('[data-sld-sheet-tab]')];
    if (!stage || !inner || !label) return;

    let zoom = 1;
    let fitMode = true;
    let activeSheet = Math.max(0, pages.findIndex((page) => page.classList.contains('is-active')));
    let dragging = false;
    let moved = false;
    let startX = 0;
    let startY = 0;
    let scrollLeft = 0;
    let scrollTop = 0;

    const setInspector = (node) => {
        const title = workspace.querySelector('[data-sld-inspector-title]');
        const subtitle = workspace.querySelector('[data-sld-inspector-subtitle]');
        const rating = workspace.querySelector('[data-sld-inspector-rating]');
        const role = workspace.querySelector('[data-sld-inspector-role]');
        const state = workspace.querySelector('[data-sld-inspector-state]');
        if (!node) {
            if (title) title.textContent = 'Select a symbol';
            if (subtitle) subtitle.textContent = 'Choose an item on the active drawing sheet to inspect its source.';
            if (rating) rating.textContent = '—';
            if (role) role.textContent = '—';
            if (state) state.textContent = 'READY';
            return;
        }
        if (title) title.textContent = node.dataset.sldTitle || 'Selected component';
        if (subtitle) subtitle.textContent = node.dataset.sldSubtitle || 'No component description available.';
        if (rating) rating.textContent = node.dataset.sldRating || '—';
        if (role) role.textContent = (node.dataset.sldKind || node.dataset.sldNode || '—').replace(/_/g, ' ');
        if (state) {
            const normalized = node.dataset.sldState || 'review';
            state.textContent = normalized.toUpperCase();
            state.classList.toggle('is-review', normalized === 'review');
            state.classList.toggle('is-attention', normalized === 'attention');
        }
    };

    const selectNode = (node) => {
        workspace.querySelectorAll('[data-sld-node]').forEach((item) => item.classList.toggle('is-selected', item === node));
        setInspector(node);
    };

    const bindNodes = () => {
        workspace.querySelectorAll('[data-sld-node]').forEach((node) => {
            node.addEventListener('click', (event) => {
                if (moved || !node.closest('[data-sld-sheet-page]')?.classList.contains('is-active')) return;
                event.stopPropagation();
                selectNode(node);
            });
            node.addEventListener('keydown', (event) => {
                if ((event.key === 'Enter' || event.key === ' ') && node.closest('[data-sld-sheet-page]')?.classList.contains('is-active')) {
                    event.preventDefault();
                    selectNode(node);
                }
            });
        });
    };

    const activePage = () => pages[activeSheet] || pages[0] || null;

    const selectFirstOnActiveSheet = () => {
        const first = activePage()?.querySelector('[data-sld-node]') || null;
        selectNode(first);
    };

    const renderZoom = () => {
        if (fitMode) {
            inner.style.width = '100%';
            label.textContent = 'Fit';
            return;
        }
        zoom = Math.min(2.4, Math.max(.45, zoom));
        inner.style.width = `${Math.round(zoom * 100)}%`;
        label.textContent = `${Math.round(zoom * 100)}%`;
    };

    const setZoom = (next, fit = false) => {
        fitMode = fit;
        if (!fit) zoom = next;
        renderZoom();
    };

    const showSheet = (index) => {
        if (!pages.length) return;
        activeSheet = Math.min(pages.length - 1, Math.max(0, Number(index) || 0));
        pages.forEach((page, pageIndex) => {
            const active = pageIndex === activeSheet;
            page.classList.toggle('is-active', active);
            page.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        tabs.forEach((tab, tabIndex) => {
            const active = tabIndex === activeSheet;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        stage.scrollTo({ left: 0, top: 0, behavior: 'auto' });
        setZoom(1, true);
        selectFirstOnActiveSheet();
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => showSheet(tab.dataset.sldSheetTab)));
    bindNodes();
    selectFirstOnActiveSheet();

    workspace.querySelectorAll('[data-sld-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.sldZoom;
            if (action === 'in') setZoom((fitMode ? 1 : zoom) + .12);
            if (action === 'out') setZoom((fitMode ? 1 : zoom) - .12);
            if (action === 'fit' || action === 'reset') setZoom(1, true);
        });
    });

    workspace.querySelector('[data-sld-fullscreen]')?.addEventListener('click', async () => {
        if (!shell) return;
        const open = shell.classList.toggle('is-focus-mode');
        document.body.classList.toggle('nxsld-focus-open', open);
        if (open) shell.scrollIntoView({ block: 'center' });
    });

    stage.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;
        dragging = true;
        moved = false;
        startX = event.clientX;
        startY = event.clientY;
        scrollLeft = stage.scrollLeft;
        scrollTop = stage.scrollTop;
        stage.classList.add('is-dragging');
        stage.setPointerCapture?.(event.pointerId);
    });

    stage.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        const dx = event.clientX - startX;
        const dy = event.clientY - startY;
        if (Math.abs(dx) + Math.abs(dy) > 4) moved = true;
        stage.scrollLeft = scrollLeft - dx;
        stage.scrollTop = scrollTop - dy;
    });

    const stopDragging = (event) => {
        if (!dragging) return;
        dragging = false;
        stage.classList.remove('is-dragging');
        stage.releasePointerCapture?.(event.pointerId);
        window.setTimeout(() => { moved = false; }, 0);
    };
    stage.addEventListener('pointerup', stopDragging);
    stage.addEventListener('pointercancel', stopDragging);
    stage.addEventListener('pointerleave', (event) => { if (dragging) stopDragging(event); });

    stage.addEventListener('keydown', (event) => {
        if (event.key === '+' || event.key === '=') setZoom((fitMode ? 1 : zoom) + .12);
        if (event.key === '-') setZoom((fitMode ? 1 : zoom) - .12);
        if (event.key === '0') setZoom(1, true);
        if (event.key === 'ArrowLeft') stage.scrollLeft -= 70;
        if (event.key === 'ArrowRight') stage.scrollLeft += 70;
        if (event.key === 'ArrowUp') stage.scrollTop -= 70;
        if (event.key === 'ArrowDown') stage.scrollTop += 70;
        if (event.key === 'PageUp' && pages.length > 1) showSheet(activeSheet - 1);
        if (event.key === 'PageDown' && pages.length > 1) showSheet(activeSheet + 1);
        if (['+', '=', '-', '0', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'PageUp', 'PageDown'].includes(event.key)) event.preventDefault();
    });

    window.addEventListener('resize', () => { if (fitMode) renderZoom(); });
    renderZoom();
})();
