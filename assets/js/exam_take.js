/* =================================================================
   Exam-taking engine — stable, offline-tolerant, auto-saving
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
  // local state mirror: {qid: {s:selected, f:flagged}}
  const state = window.EXAM_INIT || {};
  const pending = new Set();   // qids changed since last sync
  let submitted = false;

  /* ---------- show question ---------- */
  function show(i){
    if(i<0||i>=qs.length) return;
    qs[cur].classList.remove('active');
    cur=i; qs[cur].classList.add('active');
    document.getElementById('prevBtn').disabled = cur===0;
    const nextBtn=document.getElementById('nextBtn');
    nextBtn.innerHTML = cur===qs.length-1 ? 'پایان <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>' : 'بعدی <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>';
    window.scrollTo({top:0,behavior:'smooth'});
    updateGridCurrent();
  }
  document.getElementById('prevBtn').addEventListener('click',()=>show(cur-1));
  document.getElementById('nextBtn').addEventListener('click',()=>{
    if(cur===qs.length-1){ openSubmit(); } else show(cur+1);
  });

  /* ---------- select option ---------- */
  env.addEventListener('click',(e)=>{
    const opt=e.target.closest('.eq-opt');
    if(opt){
      const q=opt.closest('.exam-q'); const qid=q.dataset.q; const val=parseInt(opt.dataset.opt);
      q.querySelectorAll('.eq-opt').forEach(o=>o.classList.remove('selected'));
      opt.classList.add('selected');
      q.querySelector('[data-clear]').classList.remove('hidden');
      setAnswer(qid,val);
      // auto-advance after short delay (feels fast); only if not last
      return;
    }
    const clr=e.target.closest('[data-clear]');
    if(clr){
      const q=clr.closest('.exam-q'); const qid=q.dataset.q;
      q.querySelectorAll('.eq-opt').forEach(o=>o.classList.remove('selected'));
      clr.classList.add('hidden');
      setAnswer(qid,null);
      return;
    }
    const fl=e.target.closest('[data-flag]');
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
    // instant single save (best-effort) + reflect
    saveOne(qid);
    refreshCounts(); updateGridCell(qid);
  }
  function setFlag(qid,on){
    state[qid]=state[qid]||{}; state[qid].f=on?1:0;
    pending.add(qid); saveOne(qid); updateGridCell(qid);
  }

  /* ---------- save one (instant, best-effort) ---------- */
  async function saveOne(qid){
    try{
      await api(API,{method:'POST',body:{action:'answer',attempt_id:attemptId,question_id:qid,selected_opt:state[qid]?.s??null}});
      // also flag if set
      if('f' in (state[qid]||{})) api(API,{method:'POST',body:{action:'flag',attempt_id:attemptId,question_id:qid,flagged:state[qid].f?'1':'0'}}).catch(()=>{});
      pending.delete(qid);
    }catch(e){
      if(e&&e.expired){ handleExpired(); }
      // keep in pending; the 5s sync will retry
    }
  }

  /* ---------- batch sync every 5s (safety net) ---------- */
  async function sync(){
    if(submitted) return;
    if(pending.size===0){ // still ping for time
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
    }catch(e){ /* retry next tick */ }
  }
  setInterval(sync,5000);

  /* ---------- counts ---------- */
  function refreshCounts(){
    let answered=0; qs.forEach(q=>{ if(state[q.dataset.q]?.s) answered++; });
    document.getElementById('answeredCount').textContent=faNum(answered);
  }
  refreshCounts();

  /* ---------- grid ---------- */
  const gridPanel=document.getElementById('qgridPanel');
  const gridOv=document.getElementById('qgridOverlay');
  function openGrid(){ gridPanel.classList.add('open'); gridOv.classList.add('open'); updateGridCurrent(); }
  function closeGrid(){ gridPanel.classList.remove('open'); gridOv.classList.remove('open'); }
  document.getElementById('gridToggle').addEventListener('click',openGrid);
  document.getElementById('gridClose').addEventListener('click',closeGrid);
  gridOv.addEventListener('click',closeGrid);
  document.querySelectorAll('[data-goto]').forEach(c=>c.addEventListener('click',()=>{ show(parseInt(c.dataset.goto)); closeGrid(); }));
  function updateGridCell(qid){
    const cell=document.querySelector(`.qg-cell[data-q="${qid}"]`); if(!cell) return;
    cell.classList.toggle('answered', !!state[qid]?.s);
    cell.classList.toggle('flagged', !!state[qid]?.f);
  }
  function updateGridCurrent(){
    const qid=qs[cur].dataset.q;
    document.querySelectorAll('.qg-cell').forEach(c=>c.classList.toggle('current', c.dataset.q===qid));
  }

  /* ---------- timer (server-synced) ---------- */
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

  /* ---------- submit ---------- */
  function openSubmit(){
    let answered=0,flagged=0; qs.forEach(q=>{ const st=state[q.dataset.q]; if(st?.s)answered++; if(st?.f)flagged++; });
    document.getElementById('ssAnswered').textContent=faNum(answered);
    document.getElementById('ssBlank').textContent=faNum(total-answered);
    document.getElementById('ssFlagged').textContent=faNum(flagged);
    openModal('submitModal');
  }
  document.getElementById('finishBtn').addEventListener('click',openSubmit);
  document.getElementById('finishBtn2').addEventListener('click',()=>{ closeGrid(); openSubmit(); });
  document.getElementById('confirmSubmit').addEventListener('click',()=>doSubmit());

  async function doSubmit(){
    if(submitted) return;
    const btn=document.getElementById('confirmSubmit');
    btn.disabled=true; btn.innerHTML='<span class="spinner"></span>';
    const answers=qs.map(q=>({q:parseInt(q.dataset.q),s:state[q.dataset.q]?.s??null,f:state[q.dataset.q]?.f?1:0}));
    try{
      const d=await api(API,{method:'POST',body:{action:'submit',attempt_id:attemptId,answers}});
      submitted=true;
      window.removeEventListener('beforeunload',warnLeave);
      location.href=d.redirect;
    }catch(e){ btn.disabled=false; btn.innerHTML='ثبت نهایی'; toast(e.error||'خطا در ثبت، دوباره تلاش کن','error'); }
  }
  async function autoSubmit(reason){
    if(submitted) return;
    toast(reason+' — در حال ثبت خودکار…','info',2500);
    await doSubmit();
  }
  function handleExpired(){ if(!submitted){ submitted=true; toast('زمان تمام شد','info'); setTimeout(()=>location.href=window.API_EXAM_TAKE.replace('api/exam_take.php','student/exam_result.php?attempt='+attemptId),800); } }

  /* ---------- warn on leave ---------- */
  function warnLeave(e){ if(!submitted){ e.preventDefault(); e.returnValue=''; } }
  window.addEventListener('beforeunload',warnLeave);

  /* ---------- keyboard ---------- */
  document.addEventListener('keydown',(e)=>{
    if(document.querySelector('.modal-backdrop.open')) return;
    if(e.key==='ArrowLeft') show(cur+1);
    else if(e.key==='ArrowRight') show(cur-1);
    else if(['1','2','3','4'].includes(e.key)){ const o=qs[cur].querySelector(`.eq-opt[data-opt="${e.key}"]`); if(o) o.click(); }
  });

  show(0);
})();
