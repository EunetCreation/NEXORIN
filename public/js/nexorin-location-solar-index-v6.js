(() => {
    'use strict';

    const root = document.querySelector('[data-nx19-page]');
    if (!root) return;

    root.classList.add('is-enhanced');

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const actionEngine = window.NexorinEngineeringAction;
    const nativeSubmit = HTMLFormElement.prototype.submit;
    const mapNode = root.querySelector('[data-nx19-map]');
    const mapFallback = root.querySelector('[data-nx19-map-fallback]');
    const mapReset = root.querySelector('[data-nx19-map-reset]');
    const mapEmpty = root.querySelector('[data-nx19-map-empty]');
    const search = root.querySelector('[data-nx19-search]');
    const filterForm = root.querySelector('[data-nx19-filter]');
    let mapChart = null;

    const stateOrder = {
        no_dataset: 0,
        input_required: 1,
        complete: 2,
        reviewed: 3,
    };

    const stateColors = {
        no_dataset: '#87999f',
        input_required: '#e7b666',
        complete: '#59d7dd',
        reviewed: '#70d4a3',
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const parseJson = (selector, fallback) => {
        const node = document.querySelector(selector);
        if (!node) return fallback;
        try {
            return JSON.parse(node.textContent || '') ?? fallback;
        } catch (_) {
            return fallback;
        }
    };

    const points = parseJson('#nx19-map-points', [])
        .map((item) => ({
            ...item,
            latitude: Number(item.latitude),
            longitude: Number(item.longitude),
        }))
        .filter((item) => Number.isFinite(item.latitude) && Number.isFinite(item.longitude));

    const groupPoints = (items) => {
        const groups = new Map();

        items.forEach((item) => {
            const key = `${item.latitude.toFixed(6)}|${item.longitude.toFixed(6)}`;
            const existing = groups.get(key) || {
                latitude: item.latitude,
                longitude: item.longitude,
                items: [],
                state: item.state || 'no_dataset',
            };
            existing.items.push(item);

            const currentRank = stateOrder[existing.state] ?? 99;
            const itemRank = stateOrder[item.state] ?? 99;
            if (itemRank < currentRank) existing.state = item.state;

            groups.set(key, existing);
        });

        return Array.from(groups.values());
    };

    const groupedPoints = groupPoints(points);

    const tooltipBase = {
        trigger: 'item',
        backgroundColor: 'rgba(4, 14, 20, .96)',
        borderColor: 'rgba(105, 183, 196, .22)',
        borderWidth: 1,
        padding: [8, 10],
        textStyle: {
            color: '#dcebed',
            fontFamily: 'Inter, Segoe UI, Arial, sans-serif',
            fontSize: 10,
            lineHeight: 15,
        },
        extraCssText: 'box-shadow:0 14px 36px rgba(0,0,0,.28);border-radius:7px;max-width:320px;',
    };

    const tooltipFormatter = (params) => {
        const group = params.data?.raw;
        if (!group?.items?.length) return 'Indonesia';

        const heading = group.items.length > 1
            ? `<strong>${group.items.length} projects at this recorded coordinate</strong>`
            : `<strong>${escapeHtml(group.items[0].name || 'Project')}</strong>`;
        const rows = group.items.slice(0, 5).map((item) => {
            const location = [item.city, item.province].filter(Boolean).join(', ') || 'Location text not recorded';
            return `<div style="margin-top:5px">`
                + `<span style="color:#d7e7ea">${escapeHtml(item.project_number || item.name || 'Project')}</span>`
                + `<br><span style="color:#81979f">${escapeHtml(location)}</span>`
                + `<br><span style="color:${stateColors[item.state] || stateColors.no_dataset}">${escapeHtml(item.state_label || 'No Dataset')}</span>`
                + `<span style="color:#607982"> · ${escapeHtml(item.source_type_label || 'Source not available')}</span>`
                + `</div>`;
        }).join('');
        const more = group.items.length > 5
            ? `<div style="margin-top:5px;color:#6f8790">+${group.items.length - 5} more project${group.items.length - 5 === 1 ? '' : 's'}</div>`
            : '';
        const coordinate = `${Number(group.latitude).toFixed(5)}, ${Number(group.longitude).toFixed(5)}`;

        return `${heading}${rows}${more}`
            + `<div style="margin-top:7px;padding-top:6px;border-top:1px solid rgba(132,174,188,.13);color:#748c94">Recorded coordinates&nbsp;&nbsp;<span style="color:#a9bcc2">${coordinate}</span></div>`;
    };

    const createMapSeries = () => Object.keys(stateColors).map((state) => ({
        name: state,
        type: 'effectScatter',
        coordinateSystem: 'geo',
        showEffectOn: 'emphasis',
        rippleEffect: { scale: 2.25, brushType: 'stroke' },
        symbolSize: (value) => Math.max(8, Math.min(17, 8 + ((Number(value?.[2]) || 1) - 1) * 2.5)),
        itemStyle: {
            color: stateColors[state],
            shadowBlur: 12,
            shadowColor: `${stateColors[state]}55`,
        },
        emphasis: { scale: 1.22 },
        label: {
            show: true,
            formatter: (params) => Number(params.value?.[2]) > 1 ? String(params.value[2]) : '',
            color: '#f0ffff',
            fontSize: 8,
            fontWeight: 700,
        },
        data: groupedPoints
            .filter((group) => group.state === state)
            .map((group) => ({
                name: group.items.length > 1
                    ? `${group.items.length} projects`
                    : (group.items[0]?.name || group.items[0]?.project_number || 'Project'),
                value: [group.longitude, group.latitude, group.items.length],
                raw: group,
            })),
    }));

    const renderMap = async () => {
        if (!mapNode || !window.echarts) {
            if (mapFallback) mapFallback.hidden = false;
            return;
        }

        try {
            const response = await fetch(root.dataset.mapUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Map asset returned ${response.status}`);
            const geoJson = await response.json();
            window.echarts.registerMap('nx19-indonesia', geoJson);

            mapChart = window.echarts.init(mapNode, null, { renderer: 'canvas', useDirtyRect: false });
            mapChart.setOption({
                animation: !reducedMotion,
                animationDuration: 480,
                tooltip: { ...tooltipBase, formatter: tooltipFormatter },
                geo: {
                    map: 'nx19-indonesia',
                    roam: true,
                    center: [118.0, -2.6],
                    zoom: 1.08,
                    layoutCenter: ['50%', '51%'],
                    layoutSize: '121%',
                    silent: false,
                    itemStyle: {
                        areaColor: '#102d37',
                        borderColor: 'rgba(93, 166, 180, .56)',
                        borderWidth: .75,
                    },
                    emphasis: {
                        itemStyle: {
                            areaColor: '#153b47',
                            borderColor: 'rgba(106, 210, 220, .74)',
                        },
                        label: { show: false },
                    },
                    select: { disabled: true },
                    label: { show: false },
                },
                series: createMapSeries(),
            });

            mapChart.on('click', (params) => {
                const group = params.data?.raw;
                if (!group?.items?.length) return;

                root.querySelectorAll('[data-nx19-row].is-map-selected')
                    .forEach((row) => row.classList.remove('is-map-selected'));

                const firstVisibleRow = group.items
                    .map((item) => root.querySelector(`[data-nx19-row][data-project-id="${CSS.escape(String(item.id))}"]`))
                    .find(Boolean);

                if (firstVisibleRow) {
                    firstVisibleRow.classList.add('is-map-selected');
                    firstVisibleRow.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'nearest' });
                }
            });

            if (mapEmpty) mapEmpty.style.pointerEvents = 'none';
        } catch (_) {
            if (mapFallback) mapFallback.hidden = false;
        }
    };

    mapReset?.addEventListener('click', () => {
        if (!mapChart) return;
        mapChart.setOption({
            geo: {
                center: [118.0, -2.6],
                zoom: 1.08,
            },
        });
    });

    if ('ResizeObserver' in window && mapNode) {
        const resizeObserver = new ResizeObserver(() => mapChart?.resize());
        resizeObserver.observe(mapNode);
    } else {
        window.addEventListener('resize', () => mapChart?.resize(), { passive: true });
    }

    const showRegistryTransition = () => {
        if (!actionEngine) return { duration: 0 };
        return actionEngine.show({
            kind: 'filter',
            icon: 'filter',
            title: 'Refreshing Solar Resource Registry',
            copy: 'Applying the selected project search and stored dataset-state filter.',
            steps: [
                'Reading the selected registry filters',
                'Applying company and project access scope',
                'Resolving stored solar dataset states',
                'Loading the updated project registry',
            ],
            duration: 620,
            signal: 'NEXORIN registry',
            accent: '#59d7dd',
            secondary: '#7faeff',
            metaRight: 'Stored records only',
        });
    };

    const showStartTransition = () => {
        if (!actionEngine) return { duration: 0 };
        return actionEngine.show({
            kind: 'save',
            icon: 'solar',
            title: 'Starting Solar Dataset',
            copy: 'Creating the persisted project solar profile through the explicit start workflow.',
            steps: [
                'Confirming project and management scope',
                'Creating the project solar dataset record',
                'Recording the dataset-start history event',
                'Opening the Location & Solar workspace',
            ],
            duration: 700,
            signal: 'NEXORIN registry',
            accent: '#59d7dd',
            secondary: '#70d4a3',
            metaRight: 'Explicit mutation',
        });
    };

    filterForm?.addEventListener('submit', (event) => {
        if (filterForm.dataset.nx19Submitting === 'true') return;
        if (!actionEngine) return;

        event.preventDefault();
        filterForm.dataset.nx19Submitting = 'true';
        const { duration } = showRegistryTransition();
        window.setTimeout(() => nativeSubmit.call(filterForm), duration);
    });

    root.querySelectorAll('[data-nx19-start]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.nx19Submitting === 'true') return;
            if (!actionEngine) return;

            event.preventDefault();
            form.dataset.nx19Submitting = 'true';
            const submitter = form.querySelector('button[type="submit"]');
            if (submitter) submitter.disabled = true;
            const { duration } = showStartTransition();
            window.setTimeout(() => nativeSubmit.call(form), duration);
        });
    });

    root.querySelectorAll('[data-nx19-open]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!actionEngine) return;
            actionEngine.navigate(event, link, {
                kind: 'open',
                icon: 'solar',
                title: 'Opening Location & Solar Dataset',
                copy: 'Loading the project’s stored coordinate, source, monthly-resource, and review records.',
                steps: [
                    'Retaining project access scope',
                    'Loading the stored solar profile',
                    'Loading recorded monthly resource rows',
                    'Opening the project dataset workspace',
                ],
                duration: 660,
                signal: 'NEXORIN registry',
                accent: '#59d7dd',
                secondary: '#7faeff',
                metaRight: 'Stored records only',
            });
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || !search) return;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
        event.preventDefault();
        search.focus();
        search.select();
    });

    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        root.querySelectorAll('[data-nx19-surface], .nx19-metrics').forEach((surface) => {
            surface.addEventListener('pointermove', (event) => {
                const rect = surface.getBoundingClientRect();
                surface.style.setProperty('--nx19-x', `${event.clientX - rect.left}px`);
                surface.style.setProperty('--nx19-y', `${event.clientY - rect.top}px`);
            }, { passive: true });
        });
    }

    renderMap();
})();
