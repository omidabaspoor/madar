/* =============== Student interactions =============== */
(() => {
  'use strict';
  const faNum = (n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  let pendingRow = null; // row waiting for amount confirmation

  /* ---- apply a toggle result to the row ---- */
  function applyResult(row, d) {
    const cb = row.querySelector('[data-toggle-task]');
    row.classList.toggle('done', d.is_done==1);
    if (cb) cb.checked = d.is_done==1;
    row.dataset.done = d.done_count;
    // update visible progress text "x/y"
    const prog = row.querySelector('.st-prog-count');
    if (prog && d.target!=null) prog.textContent = faNum(d.done_count)+'/'+faNum(d.target)+' '+(prog.dataset.unit||'');
    updateProgress();
  }

  async function sendToggle(row, payload) {
    row.style.opacity = '.5';
    try {
      const d = await api(window.API_TASKS, { method:'POST', body: { action:'toggle', id: row.dataset.id, ...payload } });
      row.style.opacity = '';
      applyResult(row, d);
      if (d.is_done==1) { toast('آفرین! ادامه بده 💪','success',1800); confetti(); }
      return d;
    } catch(err) {
      row.style.opacity='';
      const cb = row.querySelector('[data-toggle-task]'); if (cb) cb.checked = row.classList.contains('done');
      toast(err.error||'خطا در ثبت','error');
    }
  }

  /* ---- checkbox change ---- */
  document.addEventListener('change', (e) => {
    const cb = e.target.closest('[data-toggle-task]');
    if (!cb) return;
    const row = cb.closest('.s-task');
    const target = parseInt(row.dataset.target) || 0;

    if (cb.checked) {
      // checking = mark done. If it has a target, ask how many (optional), but completion is guaranteed.
      if (target > 1) {
        pendingRow = row;
        const m = document.getElementById('amountModal');
        document.getElementById('amTitle').textContent = row.querySelector('.st-title')?.textContent.trim() || 'تسک';
        document.getElementById('amTarget').textContent = faNum(target);
        const cur = parseInt(row.dataset.done) || target;
        const inp = document.getElementById('amCount');
        inp.max = target; inp.value = cur || target;
        document.getElementById('amRange').max = target;
        document.getElementById('amRange').value = cur || target;
        cb.checked = false; // will be set true on confirm
        openModal('amountModal');
        setTimeout(()=>inp.focus(), 200);
      } else {
        sendToggle(row, { done: 1 });
      }
    } else {
      // unchecking = mark not done
      sendToggle(row, { done: 0, done_count: 0 });
    }
  });

  /* ---- amount modal: sync range<->number ---- */
  const amRange = document.getElementById('amRange');
  const amCount = document.getElementById('amCount');
  amRange?.addEventListener('input', ()=>{ amCount.value = amRange.value; });
  amCount?.addEventListener('input', ()=>{ amRange.value = amCount.value; });

  document.getElementById('amConfirm')?.addEventListener('click', async ()=>{
    if (!pendingRow) return;
    const val = Math.max(0, parseInt(amCount.value) || 0);
    closeModal('amountModal');
    await sendToggle(pendingRow, { done: 1, done_count: val });
    pendingRow = null;
  });
  document.getElementById('amFull')?.addEventListener('click', async ()=>{
    if (!pendingRow) return;
    const target = parseInt(pendingRow.dataset.target) || 0;
    closeModal('amountModal');
    await sendToggle(pendingRow, { done: 1, done_count: target });
    pendingRow = null;
  });
  // if user closes modal without confirming, leave task unchecked
  document.getElementById('amountModal')?.addEventListener('click', (e)=>{
    if (e.target.classList.contains('modal-backdrop') || e.target.closest('[data-close]')) {
      if (pendingRow) { const cb=pendingRow.querySelector('[data-toggle-task]'); if(cb) cb.checked = pendingRow.classList.contains('done'); }
      pendingRow = null;
    }
  });

  /* ---- recompute header progress ---- */
  function updateProgress() {
    const tasks = document.querySelectorAll('.s-task');
    if (!tasks.length) return;
    const done = document.querySelectorAll('.s-task.done').length;
    const pct = Math.round(done/tasks.length*100);
    const bar = document.querySelector('.greet-progress .progress > span');
    if (bar) { bar.style.width = pct+'%';
      const lbl = bar.closest('.greet-progress')?.querySelector('.between span:last-child');
      if (lbl) lbl.textContent = faNum(pct)+'٪ · '+faNum(done)+'/'+faNum(tasks.length);
    }
  }

  /* ---- note modal ---- */
  document.addEventListener('click', (e) => {
    const nb = e.target.closest('[data-note]');
    if (!nb) return;
    const id = nb.dataset.note;
    const row = document.querySelector(`.s-task[data-id="${id}"]`);
    document.getElementById('noteTaskId').value = id;
    document.getElementById('noteText').value = row?.querySelector('.st-note-text')?.dataset.raw || '';
    openModal('noteModal');
    setTimeout(()=>document.getElementById('noteText').focus(),200);
  });
  document.getElementById('saveNoteBtn')?.addEventListener('click', async function(){
    const id = document.getElementById('noteTaskId').value;
    const note = document.getElementById('noteText').value;
    try {
      await api(window.API_TASKS,{method:'POST',body:{action:'note',id,student_note:note}});
      closeModal('noteModal'); toast('یادداشت ذخیره شد','success',1600);
      setTimeout(()=>location.reload(),700);
    } catch(e){ toast(e.error||'خطا','error'); }
  });

  /* ---- mood ---- */
  document.getElementById('moodRow')?.addEventListener('click', async (e)=>{
    const b = e.target.closest('.mood-btn'); if(!b) return;
    document.querySelectorAll('.mood-btn').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    try { await api(window.API_MOOD,{method:'POST',body:{mood:b.dataset.mood}}); toast('حالت ثبت شد 🌿','success',1400); }
    catch(e){}
  });

  /* ---- day tabs (plan page) ---- */
  document.querySelectorAll('.day-tab').forEach(tab=>{
    tab.addEventListener('click', ()=>{
      document.querySelectorAll('.day-tab').forEach(t=>t.classList.remove('active'));
      tab.classList.add('active');
      const day = tab.dataset.day;
      document.querySelectorAll('[data-day-panel]').forEach(p=>{
        p.classList.toggle('hidden', p.dataset.dayPanel !== day);
      });
    });
  });

  /* ---- tiny confetti ---- */
  function confetti(){
    const colors=['#cbac80','#6b8872','#e0c595','#8aa791'];
    for(let i=0;i<14;i++){
      const c=document.createElement('div');
      c.style.cssText=`position:fixed;width:8px;height:8px;border-radius:2px;z-index:9999;pointer-events:none;background:${colors[i%4]};left:${50+(Math.random()-.5)*30}%;top:60%`;
      document.body.appendChild(c);
      const dx=(Math.random()-.5)*400, dy=-(Math.random()*350+150), rot=Math.random()*720;
      c.animate([{transform:'translate(0,0) rotate(0)',opacity:1},{transform:`translate(${dx}px,${dy}px) rotate(${rot}deg)`,opacity:0}],{duration:900+Math.random()*500,easing:'cubic-bezier(.2,.6,.4,1)'}).onfinish=()=>c.remove();
    }
  }
})();
