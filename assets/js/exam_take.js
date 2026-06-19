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
  let currentZoom = 1.0;

  document.getElementById('zoomInBtn')?.addEventListener('click', () => {
    currentZoom = Math.min(2.5, currentZoom + 0.15);
    updateZoomTransform();
  });

  document.getElementById('zoomOutBtn')?.addEventListener('click', () => {
    currentZoom = Math.max(0.6, currentZoom - 0.15);
    updateZoomTransform();
  });

  document.getElementById('zoomResetBtn')?.addEventListener('click', () => {
    currentZoom = 1.0;
    panX = 0; panY = 0;
    updateZoomTransform();
  });

  // Pan & Pan Dragging Logic
  let panX = 0, panY = 0, isDragging = false, startX, startY;
  const bArea = document.getElementById('bookletScrollArea');

  bArea?.addEventListener('mousedown', e => {
    isDragging = true; bArea.style.cursor = 'grabbing';
    startX = e.clientX - panX; startY = e.clientY - panY;
  });

  window.addEventListener('mousemove', e => {
    if(!isDragging || !zoomImg) return;
    panX = e.clientX - startX; panY = e.clientY - startY;
    updateZoomTransform();
  });

  window.addEventListener('mouseup', () => {
    isDragging = false; if(bArea) bArea.style.cursor = 'grab';
  });

  bArea?.addEventListener('touchstart', e => {
    if(!e.touches || e.touches.length !== 1) return;
    isDragging = true;
    startX = e.touches[0].clientX - panX; startY = e.touches[0].clientY - panY;
  }, {passive:true});

  window.addEventListener('touchmove', e => {
    if(!isDragging || !zoomImg || !e.touches || e.touches.length !== 1) return;
    panX = e.touches[0].clientX - startX; panY = e.touches[0].clientY - startY;
    updateZoomTransform();
  }, {passive:true});

  window.addEventListener('touchend', () => { isDragging = false; });

  bArea?.addEventListener('wheel', e => {
    e.preventDefault();
    if(e.deltaY < 0) {
      currentZoom = Math.min(3.5, currentZoom + 0.15);
    } else {
      currentZoom = Math.max(0.5, currentZoom - 0.15);
    }
    updateZoomTransform();
  }, {passive:false});

  function updateZoomTransform() {
    if(!zoomImg) return;
    zoomImg.style.transform = `translate(${panX}px, ${panY}px) scale(${currentZoom})`;
    if(zoomTxt) zoomTxt.textContent = `${Math.round(currentZoom * 100)}%`;
  }

  // Multi-Page Sheet Navigator
  const sheetSelect = document.getElementById('sheetPageSelect');
  const nextSheet   = document.getElementById('nextSheetPageBtn');
  const prevSheet   = document.getElementById('prevSheetPageBtn');
  const badgeTitle  = document.getElementById('bookletTitleBadge');

  function goToSheet(index) {
    if(!sheetSelect) return;
    sheetSelect.value = String(index);
    const opt = sheetSelect.options[index]; if(!opt) return;
    zoomImg.src = opt.dataset.src;
    panX = 0; panY = 0; currentZoom = 1.0; updateZoomTransform();
    
    if(nextSheet) nextSheet.disabled = (index === sheetSelect.options.length - 1);
    if(prevSheet) prevSheet.disabled = (index === 0);
    if(badgeTitle) badgeTitle.innerHTML = ` دفترچه‌ی سوالات (ص ${faNum(index+1)} از ${faNum(sheetSelect.options.length)})`;
  }

  sheetSelect?.addEventListener('change', e => goToSheet(parseInt(e.target.value)));
  nextSheet?.addEventListener('click', () => goToSheet(parseInt(sheetSelect.value) + 1));
  prevSheet?.addEventListener('click', () => goToSheet(parseInt(sheetSelect.value) - 1));

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
