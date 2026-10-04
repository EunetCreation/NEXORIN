(() => {
    const body = document.body;
    if (!body) return;
    const supported = [
        'page-bom-library-v6','page-bom-workspace-v6','page-company-create-v6',
        'page-company-edit-v6','page-companies-index-v6','page-company-show-v6',
    ];
    if (!supported.some((name) => body.classList.contains(name))) return;

    const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const revealSelectors = [
        '.nxbomlib-kpi-grid > *','.nxbomlib-table-card','.nxbomlib-intelligence','.nxbomlib-analytics > *',
        '.nxbom3-kpis > *','.nxbom3-card','.nxbom3-rail-card',
        '.nxcg-create-head','.nxcg-create-card','.nxcg-card',
        '.nx5-head','.nx5-main > *','.nx5-rail > *',
        '.nx6-header','.nx6-kpis > *','.nx6-registry','.nx6-intel-rail > *','.nx6-intelligence-row > *',
        '.nx7-page-head','.nx7-hero','.nx7-tabs','.nx7-primary > *','.nx7-rail > *',
    ].join(',');

    const nodes = [...document.querySelectorAll(revealSelectors)].filter((el) => !el.closest('[hidden]'));
    nodes.forEach((el, index) => {
        el.classList.add('nxp-reveal');
        el.style.transitionDelay = `${Math.min(index % 7, 6) * 28}ms`;
    });

    if (reduced || !('IntersectionObserver' in window)) {
        nodes.forEach((el) => el.classList.add('is-visible'));
    } else {
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        }, { rootMargin: '0px 0px -4% 0px', threshold: 0.05 });
        nodes.forEach((el) => observer.observe(el));
    }

    const progressSelectors = [
        '.nxbomlib-ready-track i','.nx5-health-bars i','.nx6-progress i','.nx7-progress i',
        '.nx6-onboarding-bar i','.nx6-tier-bar i',
    ].join(',');
    const bars = [...document.querySelectorAll(progressSelectors)];
    bars.forEach((bar) => {
        bar.classList.add('nxp-progress');
        if (!reduced) bar.classList.add('nxp-progress-pending');
    });
    const runBars = (container = document) => {
        container.querySelectorAll?.('.nxp-progress-pending').forEach((bar) => {
            bar.classList.remove('nxp-progress-pending');
            requestAnimationFrame(() => bar.classList.add('nxp-progress-run'));
        });
    };
    if (reduced || !('IntersectionObserver' in window)) {
        runBars();
    } else {
        const sections = [...new Set(bars.map((bar) => bar.closest('article,section,.nx5-card,.nx6-chart-card,.nx7-card') || bar.parentElement).filter(Boolean))];
        const progressObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                runBars(entry.target);
                progressObserver.unobserve(entry.target);
            });
        }, { threshold: 0.18 });
        sections.forEach((section) => progressObserver.observe(section));
    }
})();
