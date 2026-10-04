(() => {
    const workspace = document.querySelector('[data-sld-workspace]');
    if (!workspace) return;

    const stage = workspace.querySelector('[data-sld-stage]');
    const inner = workspace.querySelector('[data-sld-stage-inner]');
    const label = workspace.querySelector('[data-sld-zoom-label]');
    const shell = workspace.querySelector('[data-sld-drawing-shell]');
    const pages = [...workspace.querySelectorAll('[data-sld-sheet-page]')];
    const sheetTabs = [...workspace.querySelectorAll('[data-sld-sheet-tab]')];
    const reviewTabs = [...workspace.querySelectorAll('[data-sld-review-tab]')];
    const reviewPanels = [...workspace.querySelectorAll('[data-sld-review-panel]')];
    const exportDialog = workspace.querySelector('[data-sld-export-dialog]');

    let zoom = 1;
    let fitMode = 'fit';
    let activeSheet = Math.max(0, pages.findIndex((page) => page.classList.contains('is-active')));
    let dragging = false;
    let moved = false;
    let startX = 0;
    let startY = 0;
    let scrollLeft = 0;
    let scrollTop = 0;

    const showToast = (message, tone = 'success') => {
        workspace.querySelector('.nxsld-sync-toast')?.remove();
        const toast = document.createElement('div');
        toast.className = `nxsld-sync-toast${tone === 'error' ? ' is-error' : ''}`;
        toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');
        toast.textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(() => toast.remove(), 5200);
    };

    const openReviewTab = (key, scroll = true) => {
        const normalized = String(key || 'overview');
        let found = false;
        reviewTabs.forEach((tab) => {
            const active = tab.dataset.sldReviewTab === normalized;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (active) found = true;
        });
        reviewPanels.forEach((panel) => {
            const active = panel.dataset.sldReviewPanel === normalized;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
            panel.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        if (!found) return;
        try { sessionStorage.setItem('nexorin-sld-review-tab', normalized); } catch (_error) {}
        if (scroll) workspace.querySelector('.nxsld-detail-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    reviewTabs.forEach((tab) => tab.addEventListener('click', () => openReviewTab(tab.dataset.sldReviewTab, false)));
    workspace.querySelectorAll('[data-sld-open-tab]').forEach((button) => {
        button.addEventListener('click', () => openReviewTab(button.dataset.sldOpenTab));
    });
    try {
        const storedTab = sessionStorage.getItem('nexorin-sld-review-tab');
        if (storedTab && reviewPanels.some((panel) => panel.dataset.sldReviewPanel === storedTab)) openReviewTab(storedTab, false);
    } catch (_error) {}

    const activePage = () => pages[activeSheet] || pages[0] || null;

    const renderZoom = () => {
        if (!inner || !label) return;
        if (fitMode === 'fit') {
            inner.style.width = '100%';
            label.textContent = 'Fit';
            return;
        }
        if (fitMode === 'width') {
            inner.style.width = '128%';
            label.textContent = 'Width';
            return;
        }
        zoom = Math.min(2.8, Math.max(.4, zoom));
        inner.style.width = `${Math.round(zoom * 100)}%`;
        label.textContent = `${Math.round(zoom * 100)}%`;
    };

    const setZoom = (next, mode = null) => {
        fitMode = mode;
        if (!mode) zoom = next;
        renderZoom();
    };

    const showSheet = (index) => {
        if (!pages.length || !stage) return;
        activeSheet = Math.min(pages.length - 1, Math.max(0, Number(index) || 0));
        pages.forEach((page, pageIndex) => {
            const active = pageIndex === activeSheet;
            page.classList.toggle('is-active', active);
            page.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        sheetTabs.forEach((tab, tabIndex) => {
            const active = tabIndex === activeSheet;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        stage.scrollTo({ left: 0, top: 0, behavior: 'auto' });
        setZoom(1, 'fit');
    };

    sheetTabs.forEach((tab) => tab.addEventListener('click', () => showSheet(tab.dataset.sldSheetTab)));

    workspace.querySelectorAll('[data-sld-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.sldZoom;
            if (action === 'in') setZoom((fitMode ? 1 : zoom) + .12);
            if (action === 'out') setZoom((fitMode ? 1 : zoom) - .12);
            if (action === 'fit' || action === 'reset') setZoom(1, 'fit');
        });
    });
    workspace.querySelector('[data-sld-fit-width]')?.addEventListener('click', () => setZoom(1, 'width'));
    workspace.querySelector('[data-sld-monochrome]')?.addEventListener('click', (event) => {
        const active = stage?.classList.toggle('is-monochrome') || false;
        event.currentTarget.classList.toggle('is-active', active);
    });

    workspace.querySelector('[data-sld-fullscreen]')?.addEventListener('click', () => {
        if (!shell) return;
        const open = shell.classList.toggle('is-focus-mode');
        document.body.classList.toggle('nxsld-focus-open', open);
        if (open) shell.scrollIntoView({ block: 'center' });
    });

    if (stage && inner && label) {
        stage.addEventListener('pointerdown', (event) => {
            if (event.button !== 0 || event.target.closest('button, a')) return;
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
            if (event.key === '0') setZoom(1, 'fit');
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
    }

    const setExportDialog = (open) => {
        if (!exportDialog) return;
        exportDialog.hidden = !open;
        exportDialog.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) exportDialog.querySelector('[data-sld-export-close]')?.focus();
    };
    workspace.querySelectorAll('[data-sld-export-blocked]').forEach((button) => button.addEventListener('click', () => setExportDialog(true)));
    workspace.querySelectorAll('[data-sld-export-close]').forEach((button) => button.addEventListener('click', () => setExportDialog(false)));
    exportDialog?.addEventListener('click', (event) => { if (event.target === exportDialog) setExportDialog(false); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setExportDialog(false); });

    let synchronizing = false;
    workspace.querySelectorAll('[data-sld-sync]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = workspace.dataset.sldSyncUrl;
            if (!url || synchronizing) return;
            synchronizing = true;
            const buttons = [...workspace.querySelectorAll('[data-sld-sync]')];
            buttons.forEach((item) => { item.disabled = true; item.dataset.originalLabel ||= item.textContent.trim(); item.textContent = 'Synchronizing…'; });
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ action: 'accept_source' }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'Engineering synchronization could not be completed.');
                showToast(payload.message || 'Engineering source synchronized.');
                if (payload.reload !== false) window.setTimeout(() => window.location.reload(), 650);
            } catch (error) {
                showToast(error.message || 'Engineering synchronization failed.', 'error');
            } finally {
                synchronizing = false;
                buttons.forEach((item) => { item.disabled = false; item.textContent = item.dataset.originalLabel || 'Synchronize'; });
            }
        });
    });
})();
