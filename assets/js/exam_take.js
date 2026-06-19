/* =================================================================
   مَدار Exam-Taking Engine — Samurai Dual-Panel & Onboarding Tour
   ================================================================= */
(() => {
  'use strict';
  const env = document.getElementById('examEnv');
  if (!env) return;
  const API = window.API_EXAM_TAKE;
  const attemptId = parseInt(env.dataset.attempt);
  const total = parseInt(env.dataset.total);
  const faNum = (n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const qs = [...document.querySelectorAll('.exam-q')];
  let cur = 0;
  const state = window.EXAM_INIT || {};
  const pending = new Set();
  let submitted = false;

  const ICO={check:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
    chevronLeft:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>'};

  /* =================================================================
     SAMURAI DUAL-PANEL IMAGE / BUBBLE INTERACTION
     ================================================================= */
  const zoomImg  = document.getElementById('bookletImg');
  const zoomTxt  = document.getElementById('zoomLevelText');
  const bArea    = document.getElementById('bookletScrollArea');
  const pdfWrap  = document.getElementById('bookletPdf');
  const pdfCanvas = document.getElementById('bookletPdfCanvas');
  const pdfLoading = document.getElementById('pdfPageLoading');
  const pdfFallback = document.getElementById('bookletPdfFallback');
  const pdfRetry = document.getElementById('bookletPdfRetry');
  const zoomControls = document.getElementById('imageZoomControls');
  const bookletHint = document.getElementById('bookletHint');
  const pdfPageControls = document.getElementById('pdfPageControls');
  const pdfPrevPage = document.getElementById('pdfPrevPage');
  const pdfNextPage = document.getElementById('pdfNextPage');
  const pdfPageText = document.getElementById('pdfPageText');
  let fitZoom = 1;
  let currentZoom = 1;
  let panX = 0, panY = 0;
  const pointers = new Map();
  let dragStart = null;
  let pinchStart = null;
  let lastTapAt = 0;
  let pdfjsPromise = null;
  let pdfDoc = null;
  let pdfSrc = '';
  let pdfPageNo = 1;
  let pdfBaseScale = 1;
  let pdfRenderTask = null;
  let pdfRenderTimer = null;
  let pdfCssW = 0, pdfCssH = 0;

  function clamp(v, min, max){ return Math.max(min, Math.min(max, v)); }
  function viewerRect(){ return bArea?.getBoundingClientRect() || {width:0,height:0,left:0,top:0}; }
  function imgNatural(){ return { w: zoomImg?.naturalWidth || 1000, h: zoomImg?.naturalHeight || 1400 }; }
  function activeContentSize(){
    if (isPdfMode()) return {w: pdfCssW || pdfCanvas?.offsetWidth || 1000, h: pdfCssH || pdfCanvas?.offsetHeight || 1400};
    const n = imgNatural();
    return {w: n.w * currentZoom, h: n.h * currentZoom};
  }
  function isPdfMode(){ return bArea?.dataset.type === 'pdf'; }
  function updatePdfControls(){
    if (!pdfPageControls) return;
    const show = isPdfMode() && !!pdfDoc;
    pdfPageControls.classList.toggle('hidden', !show);
    if (pdfPageText) pdfPageText.textContent = pdfDoc ? `${faNum(pdfPageNo)}/${faNum(pdfDoc.numPages)}` : '…';
    if (pdfPrevPage) pdfPrevPage.disabled = !pdfDoc || pdfPageNo <= 1;
    if (pdfNextPage) pdfNextPage.disabled = !pdfDoc || pdfPageNo >= pdfDoc.numPages;
  }
  function showPdfError(msg='PDF داخل مرورگر آماده نشد. دوباره تلاش کن.'){
    if (pdfLoading) pdfLoading.classList.add('hidden');
    if (pdfFallback) {
      pdfFallback.classList.remove('hidden');
      const b = pdfFallback.querySelector('b'); if (b) b.textContent = msg;
    }
  }
  async function loadPdfJs(){
    if (!pdfjsPromise) {
      pdfjsPromise = import(window.PDFJS_URL || './assets/js/vendor/pdf.min.mjs').then(mod => {
        mod.GlobalWorkerOptions.workerSrc = window.PDFJS_WORKER || './assets/js/vendor/pdf.worker.min.mjs';
        return mod;
      });
    }
    return pdfjsPromise;
  }
  async function loadPdf(src){
    if (!src) return;
    if (pdfSrc === src && pdfDoc) { updatePdfControls(); return; }
    pdfSrc = src; pdfDoc = null; pdfPageNo = 1;
    if (pdfFallback) pdfFallback.classList.add('hidden');
    if (pdfLoading) pdfLoading.classList.remove('hidden');
    try {
      const pdfjs = await loadPdfJs();
      const task = pdfjs.getDocument({ url: src, httpHeaders: {'X-Madar-Viewer':'1'}, withCredentials: true, disableAutoFetch: true, disableStream: false });
      pdfDoc = await task.promise;
      updatePdfControls();
      await renderPdfPage(true);
    } catch (err) {
      console.warn('PDF load failed', err);
      showPdfError('PDF باز نشد. یک‌بار اینترنت/مرورگر را بررسی کن و دوباره تلاش بزن.');
    }
  }
  async function renderPdfPage(resetPan=false){
    if (!pdfDoc || !pdfCanvas || !bArea) return;
    clearTimeout(pdfRenderTimer);
    if (pdfRenderTask) { try { pdfRenderTask.cancel(); } catch(_){} }
    if (pdfLoading) pdfLoading.classList.remove('hidden');
    try {
      const page = await pdfDoc.getPage(pdfPageNo);
      const r = viewerRect();
      const vp1 = page.getViewport({scale:1});
      const pad = r.width < 640 ? 14 : 26;
      pdfBaseScale = Math.max(0.08, Math.min((r.width - pad) / vp1.width, (r.height - pad) / vp1.height));
      if (resetPan || !currentZoom || currentZoom < pdfBaseScale * .7) currentZoom = pdfBaseScale;
      currentZoom = clamp(currentZoom, pdfBaseScale * .75, Math.max(pdfBaseScale * 6, 3));
      const viewport = page.getViewport({scale:currentZoom});
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      pdfCanvas.width = Math.floor(viewport.width * dpr);
      pdfCanvas.height = Math.floor(viewport.height * dpr);
      pdfCanvas.style.width = viewport.width + 'px';
      pdfCanvas.style.height = viewport.height + 'px';
      pdfCssW = viewport.width; pdfCssH = viewport.height;
      const ctx = pdfCanvas.getContext('2d', {alpha:false});
      ctx.setTransform(dpr,0,0,dpr,0,0);
      pdfRenderTask = page.render({canvasContext:ctx, viewport});
      await pdfRenderTask.promise;
      pdfRenderTask = null;
      if (resetPan) { panX = 0; panY = 0; }
      updateZoomTransform();
      updatePdfControls();
      if (pdfLoading) pdfLoading.classList.add('hidden');
    } catch (err) {
      if (String(err?.name || '') === 'RenderingCancelledException') return;
      console.warn('PDF render failed', err);
      showPdfError('نمایش این صفحه PDF با خطا روبه‌رو شد. دوباره تلاش کن.');
    }
  }
  function schedulePdfRender(){
    clearTimeout(pdfRenderTimer);
    pdfRenderTimer = setTimeout(() => renderPdfPage(false), 90);
  }
  function setViewerMode(type, src){
    const pdf = type === 'pdf';
    if (bArea) {
      bArea.dataset.type = pdf ? 'pdf' : 'image';
      bArea.classList.toggle('pdf-mode', pdf);
      bArea.style.cursor = 'grab';
    }
    if (zoomControls) zoomControls.style.display = '';
    pdfPageControls?.classList.toggle('hidden', !pdf);
    if (bookletHint) bookletHint.textContent = pdf ? 'PDF را مثل عکس بکش/زوم کن' : 'با موس/انگشت بکشید';
    if (pdf) {
      if (zoomImg) { zoomImg.classList.add('hidden'); zoomImg.removeAttribute('src'); }
      if (pdfWrap) { pdfWrap.classList.remove('hidden'); pdfWrap.dataset.src = src || ''; }
      loadPdf(src);
    } else {
      if (pdfWrap) pdfWrap.classList.add('hidden');
      if (pdfFallback) pdfFallback.classList.add('hidden');
      pdfDoc = null; pdfSrc = '';
      if (zoomImg) { zoomImg.classList.remove('hidden'); if (src && zoomImg.src !== src) zoomImg.src = src; }
      setTimeout(resetViewer, 40);
    }
  }

  function computeFitZoom(){
    if(!bArea || !zoomImg) return 1;
    const r = viewerRect();
    const n = imgNatural();
    const pad = r.width < 640 ? 18 : 34;
    return Math.min(1, Math.max(0.08, Math.min((r.width - pad) / n.w, (r.height - pad) / n.h)));
  }

  function boundPan(){
    if(!bArea) return;
    const r = viewerRect();
    const size = activeContentSize();
    const w = size.w, h = size.h;
    const slack = 54;
    const maxX = Math.max(slack, (w - r.width) / 2 + slack);
    const maxY = Math.max(slack, (h - r.height) / 2 + slack);
    panX = clamp(panX, -maxX, maxX);
    panY = clamp(panY, -maxY, maxY);
    if (w <= r.width) panX = clamp(panX, -(r.width - w) / 3, (r.width - w) / 3);
    if (h <= r.height) panY = clamp(panY, -(r.height - h) / 3, (r.height - h) / 3);
  }

  function updateZoomTransform() {
    boundPan();
    if (isPdfMode()) {
      if (pdfCanvas) {
        pdfCanvas.style.left = '50%';
        pdfCanvas.style.top = '50%';
        pdfCanvas.style.transformOrigin = 'center center';
        pdfCanvas.style.transform = `translate(-50%, -50%) translate(${panX}px, ${panY}px)`;
      }
      if(zoomTxt) zoomTxt.textContent = `${Math.round((currentZoom / pdfBaseScale) * 100)}%`;
      return;
    }
    if(!zoomImg) return;
    zoomImg.style.left = '50%';
    zoomImg.style.top = '50%';
    zoomImg.style.transformOrigin = 'center center';
    zoomImg.style.transform = `translate(-50%, -50%) translate(${panX}px, ${panY}px) scale(${currentZoom})`;
    if(zoomTxt) zoomTxt.textContent = `${Math.round((currentZoom / fitZoom) * 100)}%`;
  }

  function resetViewer(){
    if (isPdfMode()) { currentZoom = pdfBaseScale || 1; panX = 0; panY = 0; renderPdfPage(true); return; }
    fitZoom = computeFitZoom();
    currentZoom = fitZoom;
    panX = 0; panY = 0;
    updateZoomTransform();
  }

  function zoomAt(nextZoom, cx = null, cy = null){
    if(!bArea) return;
    const old = currentZoom;
    const base = isPdfMode() ? (pdfBaseScale || 1) : fitZoom;
    const minZ = base * 0.75;
    const maxZ = Math.max(base * 7, 3);
    currentZoom = clamp(nextZoom, minZ, maxZ);
    if (cx !== null && cy !== null && old > 0) {
      const r = viewerRect();
      const dx = cx - (r.left + r.width / 2) - panX;
      const dy = cy - (r.top + r.height / 2) - panY;
      const ratio = currentZoom / old;
      panX -= dx * (ratio - 1);
      panY -= dy * (ratio - 1);
    }
    if (isPdfMode()) schedulePdfRender(); else updateZoomTransform();
  }

  document.getElementById('zoomInBtn')?.addEventListener('click', () => zoomAt(currentZoom * 1.18));
  document.getElementById('zoomOutBtn')?.addEventListener('click', () => zoomAt(currentZoom / 1.18));
  document.getElementById('zoomResetBtn')?.addEventListener('click', resetViewer);

  bArea?.addEventListener('wheel', e => {
    e.preventDefault();
    const factor = e.deltaY < 0 ? 1.14 : 1 / 1.14;
    zoomAt(currentZoom * factor, e.clientX, e.clientY);
  }, {passive:false});

  bArea?.addEventListener('pointerdown', e => {
    if(!zoomImg && !isPdfMode()) return;
    e.preventDefault();
    bArea.setPointerCapture?.(e.pointerId);
    pointers.set(e.pointerId, {x:e.clientX, y:e.clientY});
    bArea.classList.add('dragging');

    const now = Date.now();
    if (pointers.size === 1 && now - lastTapAt < 280) {
      const targetZoom = currentZoom > fitZoom * 1.45 ? fitZoom : fitZoom * 2.2;
      zoomAt(targetZoom, e.clientX, e.clientY);
      lastTapAt = 0;
    } else if (pointers.size === 1) {
      lastTapAt = now;
    }

    if (pointers.size === 1) {
      dragStart = {x:e.clientX, y:e.clientY, panX, panY};
      pinchStart = null;
    } else if (pointers.size === 2) {
      const pts = [...pointers.values()];
      const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
      pinchStart = {dist, zoom:currentZoom, panX, panY, cx:(pts[0].x+pts[1].x)/2, cy:(pts[0].y+pts[1].y)/2};
    }
  });

  bArea?.addEventListener('pointermove', e => {
    if(!pointers.has(e.pointerId) || (!zoomImg && !isPdfMode())) return;
    e.preventDefault();
    pointers.set(e.pointerId, {x:e.clientX, y:e.clientY});
    if (pointers.size === 1 && dragStart) {
      panX = dragStart.panX + (e.clientX - dragStart.x);
      panY = dragStart.panY + (e.clientY - dragStart.y);
      updateZoomTransform();
    } else if (pointers.size >= 2 && pinchStart) {
      const pts = [...pointers.values()].slice(0,2);
      const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
      const cx = (pts[0].x + pts[1].x) / 2;
      const cy = (pts[0].y + pts[1].y) / 2;
      panX = pinchStart.panX + (cx - pinchStart.cx);
      panY = pinchStart.panY + (cy - pinchStart.cy);
      zoomAt(pinchStart.zoom * (dist / Math.max(1, pinchStart.dist)), cx, cy);
    }
  });

  function endPointer(e){
    pointers.delete(e.pointerId);
    if (pointers.size === 0) {
      dragStart = null; pinchStart = null;
      bArea?.classList.remove('dragging');
    } else if (pointers.size === 1) {
      const p = [...pointers.values()][0];
      dragStart = {x:p.x, y:p.y, panX, panY};
      pinchStart = null;
    }
  }
  bArea?.addEventListener('pointerup', endPointer);
  bArea?.addEventListener('pointercancel', endPointer);
  bArea?.addEventListener('pointerleave', e => { if (e.pointerType === 'mouse') endPointer(e); });

  zoomImg?.addEventListener('load', resetViewer);
  window.addEventListener('resize', () => setTimeout(resetViewer, 80));
  if (zoomImg?.complete) setTimeout(resetViewer, 40);

  // Multi-Page Sheet Navigator
  const sheetSelect = document.getElementById('sheetPageSelect');
  const nextSheet   = document.getElementById('nextSheetPageBtn');
  const prevSheet   = document.getElementById('prevSheetPageBtn');
  const badgeTitle  = document.getElementById('bookletTitleBadge');

  function goToSheet(index) {
    if(!sheetSelect || !zoomImg) return;
    index = Math.max(0, Math.min(index, sheetSelect.options.length - 1));
    sheetSelect.value = String(index);
    const opt = sheetSelect.options[index]; if(!opt) return;
    const type = opt.dataset.type || (String(opt.dataset.src||'').toLowerCase().includes('.pdf') ? 'pdf' : 'image');
    panX = 0; panY = 0;
    setViewerMode(type, opt.dataset.src || '');
    if(nextSheet) nextSheet.disabled = (index === sheetSelect.options.length - 1);
    if(prevSheet) prevSheet.disabled = (index === 0);
    if(badgeTitle) badgeTitle.innerHTML = `دفترچه‌ی سوالات (ص ${faNum(index+1)} از ${faNum(sheetSelect.options.length)}${type==='pdf'?' · PDF':''})`;
  }

  sheetSelect?.addEventListener('change', e => goToSheet(parseInt(e.target.value)));
  nextSheet?.addEventListener('click', () => goToSheet(parseInt(sheetSelect.value || '0') + 1));
  prevSheet?.addEventListener('click', () => goToSheet(parseInt(sheetSelect.value || '0') - 1));
  pdfPrevPage?.addEventListener('click', () => { if(pdfDoc && pdfPageNo > 1){ pdfPageNo--; renderPdfPage(true); } });
  pdfNextPage?.addEventListener('click', () => { if(pdfDoc && pdfPageNo < pdfDoc.numPages){ pdfPageNo++; renderPdfPage(true); } });
  pdfRetry?.addEventListener('click', () => { const src = pdfWrap?.dataset.src || pdfSrc; if(src){ pdfSrc=''; loadPdf(src); } });
  if (sheetSelect) goToSheet(parseInt(sheetSelect.value || '0'));
  else if (bArea) setViewerMode(bArea.dataset.type || 'image', pdfWrap?.dataset.src || zoomImg?.getAttribute('src') || '');

  // Dual Bubble Sheet interaction
  env.addEventListener('click', e => {
    const bOpt = e.target.closest('.bubble-opt-btn');
    if (bOpt) {
      const parent = bOpt.closest('.bubble-row-item');
      const qid = parent.dataset.q;
      const val = parseInt(bOpt.dataset.opt);
      parent.querySelectorAll('.bubble-opt-btn').forEach(o => {
        o.classList.remove('selected');
        o.style.background = 'var(--surface-1)';
        o.style.color      = 'var(--text-2)';
      });
      bOpt.classList.add('selected');
      bOpt.style.background = 'var(--gold)';
      bOpt.style.color      = '#000';
      const clrBtn = parent.querySelector('.q-clear-btn');
      if (clrBtn) clrBtn.classList.remove('hidden');
      
      setAnswer(qid, val);
      return;
    }

    const bClr = e.target.closest('.q-clear-btn');
    if (bClr) {
      const parent = bClr.closest('.bubble-row-item');
      const qid = parent.dataset.q;
      parent.querySelectorAll('.bubble-opt-btn').forEach(o => {
        o.classList.remove('selected');
        o.style.background = 'var(--surface-1)';
        o.style.color      = 'var(--text-2)';
      });
      bClr.classList.add('hidden');
      setAnswer(qid, null);
      return;
    }

    const bBkm = e.target.closest('.q-bookmark-btn');
    if (bBkm) {
      const parent = bBkm.closest('.bubble-row-item');
      const qid = parent.dataset.q;
      const now = !(state[qid]?.f);
      bBkm.classList.toggle('active', now);
      bBkm.style.background = now ? 'var(--gold)'   : 'var(--surface-1)';
      bBkm.style.color      = now ? '#000'          : 'var(--text-2)';
      setFlag(qid, now);
      return;
    }
  });

  document.getElementById('finishSamuraiExamBtn')?.addEventListener('click', () => {
    openSubmit();
  });

  /* =================================================================
     INTELLIGENT TYPEWRITER ONBOARDING TOUR
     ================================================================= */
  const tourOverlay = document.getElementById('examOnboardingTourOverlay');
  const skipTourBtn = document.getElementById('skipTourBtn');
  const prevTourBtn = document.getElementById('prevTourStepBtn');
  const nextTourBtn = document.getElementById('nextTourStepBtn');
  const tourTitle   = document.getElementById('tourTitle');
  const tourText    = document.getElementById('tourText');
  const tourIco     = document.getElementById('tourIco');
  const tourDots    = document.querySelectorAll('.tour-dot');

  const tourSteps = [
    {
      title: "۱. دفترچه‌ی سوالات کنکور",
      ico: '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14z"/></svg>',
      text: "پنجره‌ی سمت راست دفترچه سوالات کنکور شماست. می‌توانی با موس/انگشت برگه را بکشی تا جابه‌جا شود، یا با چرخش موس (و دکمه‌ها) سوالات را زوم کنی. اگر چندین صفحه باشد، دکمه‌های ورق‌زدن فعال است.",
      targetSelector: ".booklet-viewer-panel"
    },
    {
      title: "۲. پاسخ‌برگ حبابی تعاملی",
      ico: '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
      text: "پنجره‌ی سمت چپ پاسخ‌برگ کنکور شماست. با لمس حباب هر گزینه، پاسخ شما بلافاصله ذخیره می‌گردد. همچنین با دکمه‌ی پرچم 🚩 می‌توانی سوالات شک‌دار را برای مرور پایانی علامت‌گذاری کنی.",
      targetSelector: ".bubble-sheet-panel"
    },
    {
      title: "۳. زمان‌سنج سرور و ثبت نهایی",
      ico: '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
      text: "بالای صفحه، تایمر امنِ متصل به سرور و آمار پاسخ‌داده‌ها قرار دارد. پس از اتمام آزمون، دکمه‌ی طلایی «پایان و ثبت» را بزن تا کارنامه و تراز تخمینی شما صادر شود. موفق باشی! 🏆",
      targetSelector: ".exam-bar"
    }
  ];

  let currTourStep = 0;
  let typeInterval = null;

  function runTourStep(stepIndex) {
    currTourStep = stepIndex;
    const s = tourSteps[currTourStep]; if(!s) return;
    
    if(prevTourBtn) prevTourBtn.disabled = (currTourStep === 0);
    if(nextTourBtn) nextTourBtn.innerHTML = (currTourStep === tourSteps.length - 1) ? '✓ شروع آزمون' : 'گام بعدی ▶';
    
    tourDots?.forEach(d => {
      const active = parseInt(d.dataset.step) === currTourStep;
      d.classList.toggle('active', active);
      d.style.background = active ? 'var(--gold)' : 'var(--surface-2)';
    });

    if(tourIco)   tourIco.innerHTML   = s.ico;
    if(tourTitle) tourTitle.innerHTML = s.title;

    document.querySelectorAll('.booklet-viewer-panel, .bubble-sheet-panel, .exam-bar').forEach(el => {
      el.style.boxShadow   = '';
      el.style.borderColor = 'var(--border-soft)';
    });
    const targetEl = document.querySelector(s.targetSelector);
    if(targetEl) {
      targetEl.style.boxShadow   = '0 0 0 4px var(--gold)';
      targetEl.style.borderColor = 'var(--gold)';
    }

    if(tourText) tourText.innerHTML = '';
    clearInterval(typeInterval);
    let charIdx = 0;
    tourText.innerHTML = ''; 
    typeInterval = setInterval(() => {
      if(charIdx < s.text.length) {
        tourText.innerHTML += s.text.charAt(charIdx);
        charIdx++;
      } else {
        clearInterval(typeInterval);
      }
    }, 18);
  }

  function finishTour() {
    clearInterval(typeInterval);
    if(tourOverlay) tourOverlay.classList.add('hidden');
    document.querySelectorAll('.booklet-viewer-panel, .bubble-sheet-panel, .exam-bar').forEach(el => {
      el.style.boxShadow   = '';
      el.style.borderColor = 'var(--border-soft)';
    });
    localStorage.setItem('madar_exam_tour_shown_' + (window.EXAM_ID_PARAM || 0), '1');
  }

  skipTourBtn?.addEventListener('click', finishTour);
  
  nextTourBtn?.addEventListener('click', () => {
    if (currTourStep === tourSteps.length - 1) {
      finishTour();
    } else {
      runTourStep(currTourStep + 1);
    }
  });

  prevTourBtn?.addEventListener('click', () => {
    runTourStep(currTourStep - 1);
  });

  window.addEventListener('load', () => {
    if (!localStorage.getItem('madar_exam_tour_shown_' + (window.EXAM_ID_PARAM || 0)) && tourOverlay && document.querySelector('.exam-samurai-layout')) {
      tourOverlay.classList.remove('hidden');
      runTourStep(0);
    }
  });

  /* =================================================================
     STANDARD QUESTION ENGINE
     ================================================================= */
  function show(i){
    if(i<0||i>=qs.length||!qs.length) return;
    qs[cur].classList.remove('active');
    cur=i; qs[cur].classList.add('active');
    const pBtn = document.getElementById('prevBtn');
    if(pBtn) pBtn.disabled = cur===0;
    const nextBtn=document.getElementById('nextBtn');
    if(nextBtn) {
      nextBtn.innerHTML = cur===qs.length-1 ? 'پایان و ثبت ' + ICO.check : 'بعدی ' + ICO.chevronLeft;
    }
    window.scrollTo({top:0,behavior:'smooth'});
    updateGridCurrent();
  }
  document.getElementById('prevBtn')?.addEventListener('click',()=>show(cur-1));
  document.getElementById('nextBtn')?.addEventListener('click',()=>{
    if(cur===qs.length-1){ openSubmit(); } else show(cur+1);
  });

  env.addEventListener('click',(e)=>{
    if(document.querySelector('.exam-samurai-layout')) return;
    const opt=e.target.closest('.eq-opt');
    if(opt){
      const q=opt.closest('.exam-q'); const qid=q.dataset.q; const val=parseInt(opt.dataset.opt);
      q.querySelectorAll('.eq-opt').forEach(o=>o.classList.remove('selected'));
      opt.classList.add('selected');
      const clr = q.querySelector('[data-clear]');
      if(clr) clr.classList.remove('hidden');
      setAnswer(qid,val);
      return;
    }
    const clr=e.target.closest('.exam-q [data-clear]');
    if(clr){
      const q=clr.closest('.exam-q'); const qid=q.dataset.q;
      q.querySelectorAll('.eq-opt').forEach(o=>o.classList.remove('selected'));
      clr.classList.add('hidden');
      setAnswer(qid,null);
      return;
    }
    const fl=e.target.closest('.exam-q [data-flag]');
    if(fl){
      const q=fl.closest('.exam-q'); const qid=q.dataset.q;
      const now=!(state[qid]?.f);
      fl.classList.toggle('on',now);
      setFlag(qid,now);
      return;
    }
  });

  function setAnswer(qid,val){
    state[qid]=state[qid]||{}; state[qid].s=val;
    pending.add(qid);
    saveOne(qid);
    refreshCounts(); updateGridCell(qid);
  }
  function setFlag(qid,on){
    state[qid]=state[qid]||{}; state[qid].f=on?1:0;
    pending.add(qid); saveOne(qid); updateGridCell(qid);
  }

  async function saveOne(qid){
    try{
      await api(API,{method:'POST',body:{action:'answer',attempt_id:attemptId,question_id:qid,selected_opt:state[qid]?.s??null}});
      if('f' in (state[qid]||{})) api(API,{method:'POST',body:{action:'flag',attempt_id:attemptId,question_id:qid,flagged:state[qid].f?'1':'0'}}).catch(()=>{});
      pending.delete(qid);
    }catch(e){
      if(e&&e.expired){ handleExpired(); }
    }
  }

  async function sync(){
    if(submitted) return;
    if(pending.size===0){
      try{ const d=await api(API,{method:'POST',body:{action:'sync',attempt_id:attemptId,answers:[]}}); if(d.expired) handleExpired(); else if(d.submitted){submitted=true;} else if(d.remain!=null) applyRemain(d.remain); }catch(e){}
      return;
    }
    const answers=[...pending].map(qid=>({q:parseInt(qid),s:state[qid]?.s??null,f:state[qid]?.f?1:0}));
    try{
      const d=await api(API,{method:'POST',body:{action:'sync',attempt_id:attemptId,answers}});
      if(d.expired){ handleExpired(); return; }
      if(d.submitted){ submitted=true; return; }
      pending.clear();
      if(d.remain!=null) applyRemain(d.remain);
    }catch(e){}
  }
  setInterval(sync,5000);

  function refreshCounts(){
    let answered=0; 
    document.querySelectorAll('.bubble-row-item').forEach(row => {
      const qid = row.dataset.q;
      if (state[qid]?.s) answered++;
    });
    if (answered === 0 && qs.length) {
      qs.forEach(q => { if(state[q.dataset.q]?.s) answered++; });
    }
    const ac = document.getElementById('answeredCount');
    if(ac) ac.textContent=faNum(answered);
  }
  refreshCounts();

  const gridPanel=document.getElementById('qgridPanel');
  const gridOv=document.getElementById('qgridOverlay');
  function openGrid(){ gridPanel?.classList.add('open'); gridOv?.classList.add('open'); updateGridCurrent(); }
  function closeGrid(){ gridPanel?.classList.remove('open'); gridOv?.classList.remove('open'); }
  document.getElementById('gridToggle')?.addEventListener('click',openGrid);
  document.getElementById('gridClose')?.addEventListener('click',closeGrid);
  gridOv?.addEventListener('click',closeGrid);
  document.querySelectorAll('[data-goto]').forEach(c=>c.addEventListener('click',()=>{ show(parseInt(c.dataset.goto)); closeGrid(); }));
  
  function updateGridCell(qid){
    const cell=document.querySelector(`.qg-cell[data-q="${qid}"]`); if(!cell) return;
    cell.classList.toggle('answered', !!state[qid]?.s);
    cell.classList.toggle('flagged', !!state[qid]?.f);
  }

  function updateGridCurrent(){
    if(!qs[cur]) return;
    const qid=qs[cur].dataset.q;
    document.querySelectorAll('.qg-cell').forEach(c=>c.classList.toggle('current', c.dataset.q===qid));
  }

  const timer=document.getElementById('examTimer');
  let remain = timer ? parseInt(timer.dataset.remain) : null;
  function fmt(s){ const m=Math.floor(s/60), ss=s%60; return faNum(String(m).padStart(2,'0'))+':'+faNum(String(ss).padStart(2,'0')); }
  function applyRemain(r){ if(r==null) return; remain=r; renderTimer(); }
  function renderTimer(){
    if(remain==null||!timer) return;
    document.getElementById('timerText').textContent=fmt(Math.max(0,remain));
    timer.classList.toggle('warning', remain<=300 && remain>60);
    timer.classList.toggle('danger', remain<=60);
  }
  if(timer){
    renderTimer();
    setInterval(()=>{ if(remain==null||submitted) return; remain--; if(remain<=0){ remain=0; renderTimer(); autoSubmit('زمان آزمون تمام شد'); } else renderTimer(); },1000);
  }

  function openSubmit(){
    let answered=0,flagged=0; 
    
    const dualRows = document.querySelectorAll('.bubble-row-item');
    if (dualRows.length) {
      dualRows.forEach(row => {
        const qid = row.dataset.q;
        if(state[qid]?.s) answered++;
        if(state[qid]?.f) flagged++;
      });
    } else {
      qs.forEach(q=>{ const st=state[q.dataset.q]; if(st?.s)answered++; if(st?.f)flagged++; });
    }

    const ssa = document.getElementById('ssAnswered');
    const ssb = document.getElementById('ssBlank');
    const ssf = document.getElementById('ssFlagged');
    if(ssa) ssa.textContent=faNum(answered);
    if(ssb) ssb.textContent=faNum(total-answered);
    if(ssf) ssf.textContent=faNum(flagged);
    openModal('submitModal');
  }

  document.getElementById('finishBtn')?.addEventListener('click',openSubmit);
  document.getElementById('finishBtn2')?.addEventListener('click',()=>{ closeGrid(); openSubmit(); });
  document.getElementById('confirmSubmit')?.addEventListener('click',()=>doSubmit());

  async function doSubmit(){
    if(submitted) return;
    const btn=document.getElementById('confirmSubmit');
    if(btn) { btn.disabled=true; btn.innerHTML='<span class="spinner"></span>'; }
    
    let answers = [];
    const dualRows = document.querySelectorAll('.bubble-row-item');
    if (dualRows.length) {
      dualRows.forEach(row => {
        const qid = row.dataset.q;
        answers.push({ q: parseInt(qid), s: state[qid]?.s ?? null, f: state[qid]?.f ? 1 : 0 });
      });
    } else {
      answers = qs.map(q => ({ q: parseInt(q.dataset.q), s: state[q.dataset.q]?.s ?? null, f: state[q.dataset.q]?.f ? 1 : 0 }));
    }

    try{
      const d=await api(API,{method:'POST',body:{action:'submit',attempt_id:attemptId,answers}});
      submitted=true;
      window.removeEventListener('beforeunload',warnLeave);
      location.href=d.redirect;
    }catch(e){ 
      if(btn) { btn.disabled=false; btn.innerHTML='✓ ثبت نهایی و مشاهده کارنامه'; }
      toast(e.error||'خطا در ثبت نهایی، دوباره تلاش کنید','error'); 
    }
  }

  async function autoSubmit(reason){
    if(submitted) return;
    toast(reason+' — در حال ثبت نهایی خودکار…','info',2500);
    await doSubmit();
  }

  function handleExpired(){ 
    if(!submitted){ 
      submitted=true; toast('زمان آزمون به پایان رسید','info'); 
      setTimeout(()=>location.href=window.API_EXAM_TAKE.replace('api/exam_take.php','student/exam_result.php?attempt='+attemptId),800); 
    } 
  }

  function warnLeave(e){ if(!submitted){ e.preventDefault(); e.returnValue=''; } }
  window.addEventListener('beforeunload',warnLeave);

  document.addEventListener('keydown',(e)=>{
    if(document.querySelector('.modal-backdrop.open') || document.querySelector('.exam-samurai-layout')) return;
    if(e.key==='ArrowLeft') show(cur+1);
    else if(e.key==='ArrowRight') show(cur-1);
    else if(['1','2','3','4'].includes(e.key)){ const o=qs[cur]?.querySelector(`.eq-opt[data-opt="${e.key}"]`); if(o) o.click(); }
  });

  show(0);
})();
