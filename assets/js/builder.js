/* =============== Plan Builder logic =============== */
(() => {
  'use strict';
  const grid = document.getElementById('planGrid');
  if (!grid) return;
  const planId = grid.dataset.plan;
  const modal = document.getElementById('taskModal');
  const form = document.getElementById('taskForm');
  const status = document.getElementById('saveStatus');

  const setStatus = (state, text) => {
    status.className = 'save-status ' + state;
    status.innerHTML = (state==='saving'?'<span class="spinner" style="width:14px;height:14px"></span>':
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>') + ' ' + text;
  };

  /* ----- icon helper for pills ----- */
  const closeIco = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';

  const faNumLocal = (n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);

  const pillHTML = (t) => {
    const meta = [];
    if (t.target_count!==null && t.target_count!=='') meta.push(faNumLocal(t.target_count)+' '+(t.target_unit||''));
    if (t.duration_min) meta.push(faNumLocal(t.duration_min)+' دقیقه');
    const typeLabel = (window.TASK_TYPES?.[t.task_type]?.label) || t.task_type;
    const data = {
      id:t.id, title:t.title, description:t.description||'', task_type:t.task_type,
      target_count:t.target_count??'', target_unit:t.target_unit||'تست',
      duration_min:t.duration_min??'', priority:t.priority||'normal', subject_id:t.subject_id??''
    };
    return `<div class="task-pill ${t.is_done?'done':''}" data-id="${t.id}" data-json='${JSON.stringify(data).replace(/'/g,"&#39;")}'>
      <button class="tp-del" data-del>${closeIco}</button>
      <span class="tp-title">${esc(t.title)}</span>
      ${meta.length?`<span class="tp-meta">${meta.join(' · ')}</span>`:''}
      <span class="tp-type">${esc(typeLabel)}</span>
    </div>`;
  };
  const esc = (s)=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  /* ----- open modal: new task ----- */
  grid.addEventListener('click', (e) => {
    const del = e.target.closest('[data-del]');
    if (del) { e.stopPropagation(); deleteTask(del.closest('.task-pill')); return; }
    const pill = e.target.closest('.task-pill');
    if (pill) { e.stopPropagation(); openEdit(pill); return; }
    const cell = e.target.closest('.cell');
    if (cell) openNew(cell.dataset.day, cell.dataset.unit);
  });

  function openNew(day, unit) {
    form.reset();
    document.getElementById('taskId').value = '';
    document.getElementById('taskDay').value = day;
    document.getElementById('taskUnit').value = unit;
    setType('study');
    document.getElementById('taskModalTitle').textContent = 'افزودن تسک';
    document.getElementById('deleteTaskBtn').style.display = 'none';
    openModal('taskModal');
    setTimeout(()=>document.getElementById('f_title').focus(),200);
  }
  function openEdit(pill) {
    const t = JSON.parse(pill.dataset.json);
    form.reset();
    document.getElementById('taskId').value = t.id;
    document.getElementById('f_title').value = t.title;
    document.getElementById('f_desc').value = t.description || '';
    document.getElementById('f_target').value = t.target_count===''?'':t.target_count;
    document.getElementById('f_unit').value = t.target_unit || 'تست';
    document.getElementById('f_dur').value = t.duration_min===''?'':t.duration_min;
    document.getElementById('f_prio').value = t.priority || 'normal';
    document.getElementById('f_subject').value = t.subject_id || '';
    setType(t.task_type);
    document.getElementById('taskModalTitle').textContent = 'ویرایش تسک';
    document.getElementById('deleteTaskBtn').style.display = '';
    document.getElementById('deleteTaskBtn').dataset.id = t.id;
    openModal('taskModal');
  }

  function setType(type) {
    document.getElementById('taskType').value = type;
    document.querySelectorAll('.type-opt').forEach(o=>o.classList.toggle('active', o.dataset.type===type));
  }
  document.getElementById('typeGrid').addEventListener('click', e=>{
    const o=e.target.closest('.type-opt'); if(o) setType(o.dataset.type);
  });

  /* ----- submit ----- */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('taskId').value;
    const fd = new FormData(form);
    fd.append('action', id ? 'update' : 'create');
    fd.append('plan_id', planId);
    setStatus('saving','در حال ذخیره…');
    try {
      const d = await api(window.API_TASKS, { method:'POST', body: fd });
      const t = d.task;
      if (id) {
        // remove old pill (could move cell), re-place
        document.querySelector(`.task-pill[data-id="${t.id}"]`)?.remove();
      }
      const cell = grid.querySelector(`.cell[data-day="${t.day_index}"][data-unit="${t.unit_index}"] .cell-tasks`);
      cell.insertAdjacentHTML('beforeend', pillHTML(t));
      closeModal('taskModal');
      setStatus('saved','ذخیره شد');
      recalc();
      toast(id?'تسک ویرایش شد':'تسک اضافه شد','success',1800);
    } catch(err) {
      setStatus('saved','ذخیره خودکار فعال');
      toast(err.error||'خطا در ذخیره','error');
    }
  });

  /* ----- delete ----- */
  document.getElementById('deleteTaskBtn').addEventListener('click', async function(){
    const id = this.dataset.id;
    if (!confirm('این تسک حذف شود؟')) return;
    try {
      await api(window.API_TASKS,{method:'POST',body:{action:'delete',id}});
      document.querySelector(`.task-pill[data-id="${id}"]`)?.remove();
      closeModal('taskModal'); recalc(); toast('تسک حذف شد','success',1600);
    } catch(e){ toast(e.error||'خطا','error'); }
  });
  async function deleteTask(pill){
    if(!pill) return;
    const id = pill.dataset.id;
    if(!confirm('این تسک حذف شود؟')) return;
    try { await api(window.API_TASKS,{method:'POST',body:{action:'delete',id}}); pill.remove(); recalc(); toast('حذف شد','success',1400);}
    catch(e){ toast(e.error||'خطا','error'); }
  }

  /* ----- clear day ----- */
  document.querySelectorAll('[data-clear-day]').forEach(b=>{
    b.addEventListener('click', async (e)=>{
      e.stopPropagation();
      const day=b.dataset.clearDay;
      if(!confirm('همه تسک‌های این روز پاک شود؟')) return;
      try{ await api(window.API_TASKS,{method:'POST',body:{action:'clear_day',plan_id:planId,day_index:day}});
        grid.querySelectorAll(`.cell[data-day="${day}"] .task-pill`).forEach(p=>p.remove());
        recalc(); toast('روز پاک شد','success',1400);
      }catch(e){ toast(e.error||'خطا','error'); }
    });
  });

  /* ----- publish ----- */
  const pubBtn = document.getElementById('publishBtn');
  pubBtn?.addEventListener('click', async ()=>{
    const cur = pubBtn.dataset.status;
    const next = cur==='published' ? 'draft' : 'published';
    try {
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'publish',plan_id:planId,status:next}});
      pubBtn.dataset.status = d.status;
      const badge = document.getElementById('statusBadge');
      if(d.status==='published'){
        pubBtn.innerHTML='بازگشت به پیش‌نویس'; badge.className='badge badge-sage'; badge.textContent='منتشر شده';
        toast('برنامه منتشر شد و به دانش‌آموز اطلاع داده شد','success');
      } else {
        pubBtn.innerHTML='انتشار برنامه'; badge.className='badge badge-gold'; badge.textContent='پیش‌نویس';
        toast('برنامه به پیش‌نویس بازگشت','info');
      }
    } catch(e){ toast(e.error||'خطا','error'); }
  });

  /* ----- copy week ----- */
  document.getElementById('copyWeekBtn')?.addEventListener('click', async function(){
    if(!confirm('تسک‌های این هفته با کپی هفته قبل جایگزین شوند؟')) return;
    try{
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'copy_week',plan_id:planId}});
      toast(faNumLocal(d.copied)+' تسک کپی شد. در حال بارگذاری…','success');
      setTimeout(()=>location.reload(),900);
    }catch(e){ toast(e.error||'خطا','error'); }
  });

  /* ----- recalc summary ----- */
  function recalc(){
    const all = grid.querySelectorAll('.task-pill');
    const done = grid.querySelectorAll('.task-pill.done');
    const total = all.length, dn = done.length;
    const pct = total ? Math.round(dn/total*100) : 0;
    document.getElementById('sumTotal').textContent = faNumLocal(total);
    document.getElementById('sumDone').textContent = faNumLocal(dn);
    document.getElementById('sumPct').textContent = faNumLocal(pct)+'٪';
    document.getElementById('sumBar').style.width = pct+'%';
  }
})();
