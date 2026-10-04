(() => {
  const body = document.body;
  if (!body) return;
  const page = [
    'page-bom-library-v6','page-bom-workspace-v6','page-company-create-v6','page-company-edit-v6',
    'page-companies-index-v6','page-company-show-v6','page-customer-create-v6','page-customer-edit-v6','page-customers-index-v6',
    'page-financial-show-v6','page-load-appliance-create-v6'
  ].find((name) => body.classList.contains(name));
  if (!page) return;

  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
  const specs = {
    'page-bom-library-v6': {
      hero: ['.nxbomlib-hero'], raised: ['.nxbomlib-kpi','.nxbomlib-table-card','.nxbomlib-chart-card','.nxbomlib-readiness-card','.nxbomlib-issues-card','.nxbomlib-health-card','.nxbomlib-intelligence>section'],
      rail: ['.nxbomlib-intelligence'], rows: ['.nxbomlib-row'], reveal: ['.nxbomlib-hero','.nxbomlib-kpi-grid>*','.nxbomlib-table-card','.nxbomlib-analytics>*','.nxbomlib-intelligence>section'], progress:['.nxbomlib-ready-track i']
    },
    'page-bom-workspace-v6': {
      hero: ['.nxbom3-page-head'], raised: ['.nxbom3-kpis>*','.nxbom3-table-card','.nxbom3-analytics>article','.nxbom3-commercial','.nxbom3-side-card'],
      rail:['.nxbom3-side-column'], rows:['.nxbom3-table-card tbody tr'], reveal:['.nxbom3-page-head','.nxbom3-kpis>*','.nxbom3-table-card','.nxbom3-analytics>article','.nxbom3-side-card'], progress:[]
    },
    'page-company-create-v6': {
      hero:['.nxcg-create-head'], raised:['.nxcg-create-panel','.nxcg-create-preview-card','.nxcg-create-readiness-card','.nxcg-create-flow-panel','.nxcg-create-rules-panel','.nxcg-create-onboarding-panel','.nxcg-create-review-side','.nxcg-create-postcreate-panel'],
      rail:['.nxcg-create-column-rail'], rows:[], reveal:['.nxcg-create-head','[data-nxcg-reveal]','.nxcg-create-panel','.nxcg-create-preview-card','.nxcg-create-readiness-card','.nxcg-create-flow-panel','.nxcg-create-rules-panel','.nxcg-create-onboarding-panel','.nxcg-create-review-side'], progress:['.nxcg-create-readiness-meter i','.nxcg-create-readiness-meter b']
    },
    'page-company-edit-v6': {
      hero:['.nx5-profile'], raised:['.nx5-card','.nx5-rail-card','.nx5-metric-card','.nx5-stat-box'], rail:['.nx5-rail'], rows:['.nx5-table tbody tr'],
      reveal:['.nx5-head','.nx5-main>.nx5-card','.nx5-rail>.nx5-rail-card'], progress:['.nx5-health-bars i']
    },
    'page-companies-index-v6': {
      hero:['.nx6-header'], raised:['.nx6-kpi','.nx6-registry','.nx6-chart-card','.nx6-health-card','.nx6-intel-rail>section','.nx6-intel-callout'], rail:['.nx6-intel-rail'], rows:['.nx6-table tbody tr'],
      reveal:['.nx6-header','.nx6-kpis>*','.nx6-registry','.nx6-intelligence-row>*','.nx6-intel-rail>*'], progress:['.nx6-progress i','.nx6-onboarding-bar i','.nx6-tier-bar i']
    },
    'page-company-show-v6': {
      hero:['.nx7-hero'], raised:['.nx7-summary-tile','.nx7-card','.nx7-rail-card'], rail:['.nx7-rail'], rows:['.nx7-table tbody tr'],
      reveal:['.nx7-page-head','.nx7-hero','.nx7-summary-tile','.nx7-primary>.nx7-card','.nx7-rail>.nx7-rail-card'], progress:['.nx7-progress i']
    },
    'page-customer-create-v6': {
      hero:['.nxcus8-card--preview'], raised:['.nxcus8-card','.nxcus8-rail-card'], rail:['.nxcus8-rail'], rows:['.nxcus8-contact-row','.nxcus8-doc-row'],
      reveal:['.nxcus8-page-head','.nxcus8-card','.nxcus8-rail-card'], progress:['.nxcus8-timeline-track i','.nxcus8-gauge i','.nxcus8-gauge b']
    },
    'page-customer-edit-v6': {
      hero:['.nxce9-profile'], raised:['.nxce9-card','.nxce9-review'], rail:['.nxce9-rail'], rows:['.nxce9-contact-row','.nxce9-opportunity-row','.nxce9-doc-record'],
      reveal:['.nxce9-head','.nxce9-profile','.nxce9-editor-column>.nxce9-card','.nxce9-review'], progress:['.nxce9-readiness i b']
    },
    'page-customers-index-v6': {
      hero:['.nx10-page-head'], raised:['.nx10-kpi','.nx10-card','.nx10-rail-card','.nx10-directory'], rail:['.nx10-right-rail'], rows:['.nx10-table tbody tr'],
      reveal:['.nx10-page-head','[data-premium-reveal]','.nx10-kpis>*','.nx10-chart-grid>*','.nx10-directory','.nx10-right-rail>*','.nx10-lower-grid>*'], progress:[]
    },
    'page-financial-show-v6': {
      hero:['.nx14-model-card'],
      raised:['.nx14-output-band','.nx14-basis-band','.nx14-rail-card[data-nxl-surface="raised"]'],
      rail:['.nx14-rail'],
      rows:['.nx14-cashflow-table tbody tr'],
      reveal:['.nx14-head','.nx14-output-band','.nx14-basis-band','.nx14-model-card','.nx14-assumptions-card','.nx14-rail-card','.nx14-table-card'],
      progress:[]
    },
    'page-load-appliance-create-v6': {
      hero:['.nx15-preview-card'],
      raised:['.nx15-section','.nx15-context-card','.nx15-basis-card','.nx15-save-card'],
      rail:['.nx15-rail'],
      rows:[],
      reveal:['.nx15-head','.nx15-validation','.nx15-section','.nx15-context-card','.nx15-preview-card','.nx15-basis-card','.nx15-save-card'],
      progress:[]
    }
  }[page];
  if (!specs) return;

  const select = (items=[]) => [...new Set(items.flatMap((selector) => [...document.querySelectorAll(selector)]))].filter((el) => !el.closest('[hidden]'));
  const hero = select(specs.hero);
  const raised = select(specs.raised);
  const rails = select(specs.rail);
  const rows = select(specs.rows);
  const reveal = select(specs.reveal);
  const progress = select(specs.progress);

  hero.forEach((el) => { el.dataset.nxlSurface = 'hero'; });
  raised.forEach((el) => { if (!el.dataset.nxlSurface) el.dataset.nxlSurface = 'raised'; });
  rails.forEach((el) => { el.dataset.nxlRail = ''; });
  rows.forEach((el) => { el.dataset.nxlRow = ''; });

  // Conservative semantic tone assignment. Existing business states remain authoritative.
  const toneRules = [
    ['[class*="readiness"],[class*="health"],[class*="success"],[class*="active"]','green'],
    ['[class*="issue"],[class*="attention"],[class*="warning"],[class*="next-action"],[class*="opportunit"]','amber'],
    ['[class*="governance"],[class*="ownership"],[class*="approval"]','purple'],
    ['[class*="document"],[class*="evidence"],[class*="map"],[class*="region"]','blue']
  ];
  [...hero,...raised].forEach((el) => {
    if (el.dataset.tone && ['green','amber','purple','red','blue','teal','cyan'].includes(el.dataset.tone)) {
      el.dataset.nxlTone = el.dataset.tone === 'teal' ? 'green' : el.dataset.tone;
      return;
    }
    for (const [selector,tone] of toneRules) {
      if (el.matches(selector) || el.querySelector(selector)) { el.dataset.nxlTone = tone; break; }
    }
  });

  // Pointer spotlight only on high-value surfaces, never required for content.
  if (!reduced && window.matchMedia?.('(hover:hover) and (pointer:fine)').matches) {
    [...hero,...raised].forEach((el) => {
      el.addEventListener('pointermove', (event) => {
        const rect = el.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        el.style.setProperty('--nxl-x', `${((event.clientX - rect.left) / rect.width * 100).toFixed(1)}%`);
        el.style.setProperty('--nxl-y', `${((event.clientY - rect.top) / rect.height * 100).toFixed(1)}%`);
      }, { passive:true });
      el.addEventListener('pointerleave', () => {
        el.style.removeProperty('--nxl-x'); el.style.removeProperty('--nxl-y');
      }, { passive:true });
    });
  }

  reveal.forEach((el, index) => {
    el.dataset.nxlReveal = '';
    el.style.setProperty('--nxl-delay', `${Math.min(index % 6,5) * 28}ms`);
  });
  if (reduced || !('IntersectionObserver' in window)) {
    reveal.forEach((el) => el.classList.add('is-nxl-visible'));
  } else {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-nxl-visible');
        io.unobserve(entry.target);
      });
    }, { rootMargin:'0px 0px -4% 0px', threshold:.04 });
    reveal.forEach((el) => io.observe(el));
  }

  progress.forEach((bar) => {
    bar.dataset.nxlProgress = '';
    if (!reduced) bar.classList.add('is-nxl-pending');
  });
  const runProgress = (root=document) => {
    root.querySelectorAll?.('[data-nxl-progress].is-nxl-pending').forEach((bar) => {
      bar.classList.remove('is-nxl-pending');
      requestAnimationFrame(() => bar.classList.add('is-nxl-running'));
    });
  };
  if (reduced || !('IntersectionObserver' in window)) runProgress();
  else {
    const groups = [...new Set(progress.map((bar) => bar.closest('article,section,[data-nxl-surface]') || bar.parentElement).filter(Boolean))];
    const pio = new IntersectionObserver((entries) => entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      runProgress(entry.target); pio.unobserve(entry.target);
    }), { threshold:.12 });
    groups.forEach((group) => pio.observe(group));
  }
})();
