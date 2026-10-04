(() => {
    'use strict';

    const root = document.querySelector('[data-nx20-page]');
    if (!root) return;

    const form = root.querySelector('[data-nx20-form]');
    const canManage = root.dataset.canManage === 'true';
    const hasDataset = root.dataset.hasDataset === 'true';
    const initiallyReviewed = root.dataset.reviewed === 'true';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const actionEngine = window.NexorinEngineeringAction;
    const nativeSubmit = HTMLFormElement.prototype.submit;
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const sourceLabels = { manual: 'Manual entry', reference: 'Reference source', api: 'API-designated source' };
    const stateLabels = { no_dataset: 'No Dataset', input_required: 'Input Required', complete: 'Dataset Complete', reviewed: 'Dataset Reviewed' };
    const stateColors = { no_dataset:'#87999f', input_required:'#e7b666', complete:'#59d7dd', reviewed:'#70d4a3' };

    const q = (selector) => root.querySelector(selector);
    const qa = (selector) => [...root.querySelectorAll(selector)];
    const numberOrNull = (value) => value === '' || value === null || value === undefined ? null : Number(value);
    const format = (value, digits = 1) => Number.isFinite(value)
        ? new Intl.NumberFormat('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(value))
        : 'Not Available';
    const setText = (selector, value) => { const node = q(selector); if (node) node.textContent = value; };

    const monthRows = qa('[data-nx20-month-row]');
    const latitude = q('[data-nx20-latitude]');
    const longitude = q('[data-nx20-longitude]');
    const sourceName = q('[data-nx20-source-name-input]');
    const saveButton = q('[data-nx20-save]');
    const reviewButton = q('[data-nx20-review]');
    const reviewForm = document.getElementById('nx20-review-form');
    const startForms = qa('[data-nx20-start-form]');

    const tracked = form ? [...form.querySelectorAll('input[name], textarea[name], select[name]')].filter(el => !el.disabled) : [];
    const valueOf = (el) => el.type === 'radio' ? (el.checked ? el.value : '') : el.value;
    const initialSnapshot = new Map(tracked.map(el => [el.name + (el.type === 'radio' ? `:${el.value}` : ''), valueOf(el)]));
    const isDirty = () => tracked.some(el => initialSnapshot.get(el.name + (el.type === 'radio' ? `:${el.value}` : '')) !== valueOf(el));

    const readMonths = () => monthRows.map((row, index) => {
        const get = (key) => numberOrNull(row.querySelector(`[data-field="${key}"]`)?.value);
        return { month:index + 1, days:Number(row.dataset.days || 30), ghi:get('ghi'), poa:get('poa'), psh:get('psh'), temperature:get('temperature') };
    });

    const assessPreview = () => {
        if (!hasDataset) return { state:'no_dataset', completeMonths:0, poaMonths:0, annualGhi:null, avgPsh:null, avgTemp:null, missing:['Start the solar dataset'], rows:readMonths() };
        const rows = readMonths();
        const completeRows = rows.filter(row => row.ghi !== null && row.psh !== null && row.temperature !== null);
        const completeMonths = completeRows.length;
        const poaMonths = rows.filter(row => row.poa !== null).length;
        const lat = numberOrNull(latitude?.value);
        const lng = numberOrNull(longitude?.value);
        const coordinatesComplete = Number.isFinite(lat) && Number.isFinite(lng);
        const sourceComplete = Boolean(sourceName?.value.trim());
        const datasetComplete = coordinatesComplete && sourceComplete && completeMonths === 12;
        const dirty = isDirty();
        const state = initiallyReviewed && !dirty && datasetComplete ? 'reviewed' : (datasetComplete ? 'complete' : 'input_required');
        const totalDays = completeRows.reduce((sum,row) => sum + row.days, 0);
        const annualGhi = completeMonths === 12 ? completeRows.reduce((sum,row) => sum + row.ghi * row.days, 0) : null;
        const avgPsh = totalDays ? completeRows.reduce((sum,row) => sum + row.psh * row.days, 0) / totalDays : null;
        const avgTemp = totalDays ? completeRows.reduce((sum,row) => sum + row.temperature * row.days, 0) / totalDays : null;
        const missing = [];
        if (!coordinatesComplete) missing.push((Number.isFinite(lat) || Number.isFinite(lng)) ? 'Complete the coordinate pair' : 'Record the project coordinates');
        if (!sourceComplete) missing.push('Record the source name');
        if (completeMonths < 12) missing.push(`Complete ${12 - completeMonths} remaining monthly record${12 - completeMonths === 1 ? '' : 's'}`);
        return { state, completeMonths, poaMonths, annualGhi, avgPsh, avgTemp, missing, rows, coordinatesComplete, lat, lng, dirty };
    };

    const renderState = () => {
        const state = assessPreview();
        const label = stateLabels[state.state];
        root.classList.toggle('is-dirty', Boolean(state.dirty));
        qa('[data-nx20-state-label],[data-nx20-metric-state]').forEach(node => {
            node.textContent = label;
            node.classList.remove('is-no_dataset','is-input_required','is-complete','is-reviewed');
            node.classList.add(`is-${state.state}`);
        });
        setText('[data-nx20-nav-state]', state.dirty ? `${label} · Unsaved changes` : label);
        setText('[data-nx20-metric-months]', `${state.completeMonths}/12`);
        setText('[data-nx20-monthly-chip]', `${state.completeMonths}/12 complete`);
        setText('[data-nx20-metric-annual-ghi]', state.annualGhi === null ? 'Not Available' : format(state.annualGhi,0));
        setText('[data-nx20-summary-psh]', state.avgPsh === null ? 'Not Available' : `${format(state.avgPsh,2)} h/day`);
        setText('[data-nx20-summary-temperature]', state.avgTemp === null ? 'Not Available' : `${format(state.avgTemp,1)} °C`);
        setText('[data-nx20-summary-poa]', `${state.poaMonths}/12 months`);
        setText('[data-nx20-chart-coverage]', `${state.completeMonths}/12 required months`);
        setText('[data-nx20-metric-source]', sourceLabels[form?.querySelector('input[name="data_source"]:checked')?.value] || 'Manual entry');
        setText('[data-nx20-source-name]', sourceName?.value.trim() || 'Source name not recorded');
        setText('[data-nx20-rail-source]', sourceLabels[form?.querySelector('input[name="data_source"]:checked')?.value] || 'Manual entry');

        const gapList = q('[data-nx20-gap-list]');
        if (gapList) {
            gapList.innerHTML = state.missing.length
                ? state.missing.map(gap => `<span><i></i>${gap}</span>`).join('')
                : '<span class="is-clear"><i></i>No required current inputs are missing.</span>';
        }
        const stateCopy = q('[data-nx20-state-copy]');
        if (stateCopy) stateCopy.textContent = state.state === 'reviewed'
            ? 'Current recorded dataset review exists'
            : (state.state === 'complete' ? 'Eligible for current dataset review' : (state.state === 'no_dataset' ? 'Explicit dataset start required' : 'Additional recorded inputs required'));

        const nextTitle = q('[data-nx20-next-title]');
        const nextCopy = q('[data-nx20-next-copy]');
        if (nextTitle && nextCopy) {
            if (state.state === 'no_dataset') {
                nextTitle.textContent = 'Start the solar dataset';
                nextCopy.textContent = 'Create the persisted dataset before monthly resource values can be recorded.';
            } else if (state.state === 'input_required') {
                nextTitle.textContent = 'Complete the recorded inputs';
                nextCopy.textContent = state.missing[0] || 'Complete the current dataset requirements.';
            } else if (state.state === 'complete') {
                nextTitle.textContent = 'Complete dataset review';
                nextCopy.textContent = 'The current saved dataset is complete enough for the authorized review workflow.';
            } else {
                nextTitle.textContent = 'Continue downstream engineering';
                nextCopy.textContent = 'The current monthly dataset review is recorded. Downstream calculations still use their own freshness and approval gates.';
            }
        }

        monthRows.forEach((row, index) => {
            const data = state.rows[index];
            const complete = data.ghi !== null && data.psh !== null && data.temperature !== null;
            const badge = row.querySelector('[data-nx20-month-state]');
            if (!badge) return;
            const monthStateLabel = complete ? 'Complete' : 'Input Required';
            badge.textContent = monthStateLabel;
            badge.setAttribute('aria-label', `Month state: ${monthStateLabel}`);
            badge.setAttribute('title', monthStateLabel);
            badge.classList.toggle('is-complete', complete);
            badge.classList.toggle('is-missing', !complete);
        });

        if (reviewButton) {
            reviewButton.disabled = state.state !== 'complete' || state.dirty;
            reviewButton.title = state.dirty ? 'Save pending changes before dataset review.' : (state.state !== 'complete' ? 'Complete the current recorded dataset first.' : 'Record the authorized dataset review.');
        }
        if (saveButton) saveButton.disabled = !state.dirty;
        updateMap(state);
        renderChart(state);
    };

    qa('.nx20-source-option input[type="radio"]').forEach(input => input.addEventListener('change', () => {
        qa('.nx20-source-option').forEach(label => label.classList.toggle('is-selected', label.contains(input) && input.checked));
        renderState();
    }));
    tracked.forEach(el => el.addEventListener(el.tagName === 'SELECT' || el.type === 'radio' ? 'change' : 'input', renderState));

    let mapChart = null;
    let mapReady = false;
    const mapNode = q('[data-nx20-map]');
    const mapEmpty = q('[data-nx20-map-empty]');
    const mapFallback = q('[data-nx20-map-fallback]');
    const mapReadout = q('[data-nx20-map-readout]');
    const savedLat = numberOrNull(root.dataset.savedLatitude);
    const savedLng = numberOrNull(root.dataset.savedLongitude);

    const defaultMapView = { center:[118,-2.6], zoom:1.02, layoutCenter:['50%','48%'], layoutSize:'108%' };
    let lastMapFocusKey = null;
    const projectMapView = (lng, lat) => ({
        center:[
            defaultMapView.center[0] + (lng - defaultMapView.center[0]) * .34,
            defaultMapView.center[1] + (lat - defaultMapView.center[1]) * .22,
        ],
        zoom:1.12,
    });

    const baseMapOption = () => ({
        animation:!reducedMotion,
        tooltip:{trigger:'item',backgroundColor:'rgba(4,14,20,.96)',borderColor:'rgba(105,183,196,.22)',borderWidth:1,textStyle:{color:'#dcebed',fontFamily:'Inter, Segoe UI, Arial, sans-serif',fontSize:10},formatter:(params)=>params.seriesType==='scatter'?`${params.name}<br><span style="color:#8aa0a8">${Number(params.value[1]).toFixed(5)}, ${Number(params.value[0]).toFixed(5)}</span>`:'Indonesia'},
        geo:{map:'nx20-indonesia',roam:true,center:defaultMapView.center,zoom:defaultMapView.zoom,layoutCenter:defaultMapView.layoutCenter,layoutSize:defaultMapView.layoutSize,itemStyle:{areaColor:'#102d37',borderColor:'rgba(93,166,180,.56)',borderWidth:.75},emphasis:{itemStyle:{areaColor:'#153b47'},label:{show:false}},label:{show:false}},
        series:[]
    });

    const updateMap = (state = assessPreview()) => {
        const lat = state.lat ?? numberOrNull(latitude?.value);
        const lng = state.lng ?? numberOrNull(longitude?.value);
        const valid = Number.isFinite(lat) && Number.isFinite(lng);
        const unsaved = valid && (lat !== savedLat || lng !== savedLng);
        if (mapReadout) mapReadout.hidden = !valid;
        setText('[data-nx20-map-readout-label]', valid ? (unsaved ? 'Form preview' : 'Recorded point') : 'Recorded point');
        setText('[data-nx20-map-readout-value]', valid ? `${lat.toFixed(5)}, ${lng.toFixed(5)}` : 'Not Recorded');
        if (!mapChart || !mapReady) return;
        if (mapEmpty) mapEmpty.hidden = valid;
        setText('[data-nx20-map-title]', valid ? (unsaved ? 'Unsaved coordinate preview' : 'Saved project coordinate') : 'Coordinate not recorded');
        setText('[data-nx20-map-copy]', valid ? (unsaved ? 'Marker reflects the current form values and is not persisted until Save Dataset.' : 'Map marker reflects the currently saved latitude and longitude.') : 'Record a latitude and longitude pair to place the project marker.');
        setText('[data-nx20-coordinate-state]', valid ? 'Recorded' : ((Number.isFinite(lat)||Number.isFinite(lng)) ? 'Incomplete coordinate pair' : 'Not Recorded'));
        setText('[data-nx20-metric-coordinates]', valid ? 'Recorded' : ((Number.isFinite(lat)||Number.isFinite(lng)) ? 'Incomplete pair' : 'Not Recorded'));
        const focusKey = valid ? `${lat.toFixed(7)}:${lng.toFixed(7)}` : 'none';
        const geoView = valid && focusKey !== lastMapFocusKey ? projectMapView(lng,lat) : null;
        if (focusKey !== lastMapFocusKey) lastMapFocusKey = focusKey;
        mapChart.setOption({
            ...(geoView ? {geo:geoView} : {}),
            series: valid ? [{name:unsaved?'Unsaved coordinate preview':'Recorded project coordinate',type:'scatter',coordinateSystem:'geo',symbol:'circle',symbolSize:20,z:8,itemStyle:{color:unsaved?stateColors.input_required:stateColors.complete,borderColor:'#d7fbfd',borderWidth:2.4,shadowBlur:24,shadowColor:unsaved?'rgba(231,182,102,.38)':'rgba(89,215,221,.46)'},emphasis:{scale:1.06,itemStyle:{borderWidth:3}},data:[{name:unsaved?'Unsaved coordinate preview':'Recorded project coordinate',value:[lng,lat]}]}] : []
        });
    };

    const initMap = async () => {
        if (!mapNode || !window.echarts) { if (mapFallback) mapFallback.hidden = false; return; }
        try {
            const response = await fetch(root.dataset.mapUrl,{credentials:'same-origin'});
            if (!response.ok) throw new Error('map');
            const geo = await response.json();
            window.echarts.registerMap('nx20-indonesia',geo);
            mapChart = window.echarts.init(mapNode,null,{renderer:'canvas',useDirtyRect:false});
            mapChart.setOption(baseMapOption());
            mapReady = true;
            updateMap();
        } catch (_) { if (mapFallback) mapFallback.hidden = false; }
    };
    q('[data-nx20-map-reset]')?.addEventListener('click',()=>{
        if (!mapChart) return;
        const lat = numberOrNull(latitude?.value);
        const lng = numberOrNull(longitude?.value);
        lastMapFocusKey = Number.isFinite(lat) && Number.isFinite(lng) ? `${lat.toFixed(7)}:${lng.toFixed(7)}` : 'none';
        mapChart.setOption({geo:{center:defaultMapView.center,zoom:defaultMapView.zoom}});
    });

    let resourceChart = null;
    let activeMetric = 'ghi';
    const chartNode = q('[data-nx20-chart]');
    const chartEmpty = q('[data-nx20-chart-empty]');
    const chartMeta = {
        ghi:{title:'Monthly GHI',unit:'kWh/m²/day'}, psh:{title:'Monthly Peak Sun Hours',unit:'h/day'}, temperature:{title:'Monthly Ambient Temperature',unit:'°C'}, poa:{title:'Monthly POA',unit:'kWh/m²/day'}
    };
    const renderChart = (state = assessPreview()) => {
        if (!chartNode || !window.echarts) return;
        const values = state.rows.map(row => row[activeMetric]);
        const hasValues = values.some(Number.isFinite);
        if (chartEmpty) chartEmpty.hidden = hasValues;
        chartNode.style.visibility = hasValues ? 'visible' : 'hidden';
        const meta = chartMeta[activeMetric];
        setText('[data-nx20-chart-title]',meta.title);
        if (!hasValues) return;
        if (!resourceChart) resourceChart = window.echarts.init(chartNode,null,{renderer:'svg',useDirtyRect:false});
        resourceChart.setOption({animation:!reducedMotion,grid:{left:42,right:16,top:26,bottom:31},tooltip:{trigger:'axis',backgroundColor:'rgba(4,14,20,.96)',borderColor:'rgba(105,183,196,.22)',borderWidth:1,textStyle:{color:'#dcebed',fontSize:10},valueFormatter:(v)=>`${v} ${meta.unit}`},xAxis:{type:'category',data:monthNames,axisLine:{lineStyle:{color:'rgba(110,158,170,.18)'}},axisTick:{show:false},axisLabel:{color:'#69828a',fontSize:8}},yAxis:{type:'value',name:meta.unit,nameTextStyle:{color:'#57717a',fontSize:7},splitLine:{lineStyle:{color:'rgba(110,158,170,.08)'}},axisLabel:{color:'#617b84',fontSize:7}},series:[{type:'line',smooth:.3,connectNulls:false,symbol:'circle',symbolSize:5,data:values,lineStyle:{width:2,color:'#59d7dd'},itemStyle:{color:'#70d4a3',borderColor:'#07161c',borderWidth:2},areaStyle:{color:new window.echarts.graphic.LinearGradient(0,0,0,1,[{offset:0,color:'rgba(89,215,221,.20)'},{offset:1,color:'rgba(89,215,221,0)'}])}}]},{notMerge:true});
    };
    qa('[data-nx20-chart-metric]').forEach(button => button.addEventListener('click',()=>{activeMetric=button.dataset.nx20ChartMetric;qa('[data-nx20-chart-metric]').forEach(b=>b.classList.toggle('is-active',b===button));renderChart();}));

    q('[data-nx20-geolocate]')?.addEventListener('click',()=>{
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(({coords})=>{ if(latitude) latitude.value=coords.latitude.toFixed(7); if(longitude) longitude.value=coords.longitude.toFixed(7); renderState(); },()=>{} ,{enableHighAccuracy:true,timeout:9000,maximumAge:0});
    });

    const runAction = (kind,title,copy,steps) => actionEngine ? actionEngine.show({kind,icon:kind==='review'?'verify':'save',title,copy,steps,duration:reducedMotion?0:620,signal:'NEXORIN dataset workflow',accent:kind==='review'?'#70d4a3':'#59d7dd',metaRight:'Stored records'}) : {duration:0};
    form?.addEventListener('submit',(event)=>{
        if (!hasDataset || !canManage || form.dataset.submitting==='true') return;
        event.preventDefault(); form.dataset.submitting='true'; if(saveButton){saveButton.disabled=true;saveButton.setAttribute('aria-busy','true');}
        const {duration=0}=runAction('save','Saving Location & Solar Dataset','Recording the current project location, source metadata, and monthly resource values.',['Validating the submitted project fields','Saving the current recorded dataset','Refreshing the deterministic dataset state']);
        window.setTimeout(()=>nativeSubmit.call(form),duration);
    });
    reviewForm?.addEventListener('submit',(event)=>{
        if (reviewForm.dataset.submitting==='true') return;
        event.preventDefault(); reviewForm.dataset.submitting='true'; if(reviewButton){reviewButton.disabled=true;reviewButton.setAttribute('aria-busy','true');}
        const {duration=0}=runAction('review','Recording Dataset Review','Saving the authorized review event for the current complete recorded dataset.',['Confirming the saved dataset is complete','Recording the authorized review event','Refreshing the current dataset state']);
        window.setTimeout(()=>nativeSubmit.call(reviewForm),duration);
    });
    startForms.forEach(startForm=>startForm.addEventListener('submit',(event)=>{ if(startForm.dataset.submitting==='true')return; event.preventDefault();startForm.dataset.submitting='true';const {duration=0}=runAction('save','Starting Solar Dataset','Creating the explicit stored solar dataset for this project.',['Confirming project access','Creating the stored dataset record','Opening the project resource workspace']);window.setTimeout(()=>nativeSubmit.call(startForm),duration); }));

    const sections = qa('[data-nx20-section-link]').map(link => ({link,target:document.getElementById(link.dataset.nx20SectionLink)})).filter(x=>x.target);
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries=>{const visible=entries.filter(e=>e.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];if(!visible)return;sections.forEach(item=>item.link.classList.toggle('is-active',item.target===visible.target));},{rootMargin:'-120px 0px -55% 0px',threshold:[.05,.2,.45]});
        sections.forEach(item=>observer.observe(item.target));
    }
    if ('ResizeObserver' in window) new ResizeObserver(()=>{mapChart?.resize();resourceChart?.resize();}).observe(root);
    else window.addEventListener('resize',()=>{mapChart?.resize();resourceChart?.resize();},{passive:true});

    initMap();
    renderState();
})();
