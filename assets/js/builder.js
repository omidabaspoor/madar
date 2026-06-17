/* =================================================================
   Plan Builder — fast presets, drag/drop, copy/paste, special defaults
   ================================================================= */
(() => {
  'use strict';
  const grid = document.getElementById('planGrid');
  if (!grid) return;

  const planId = grid.dataset.plan;
  const form = document.getElementById('taskForm');
  const status = document.getElementById('saveStatus');
  const copyHint = document.getElementById('copyHint');
  const titleInput = document.getElementById('f_title');
  const subjectInput = document.getElementById('f_subject');
  const targetInput = document.getElementById('f_target');
  const unitInput = document.getElementById('f_unit');
  const durInput = document.getElementById('f_dur');
  const prioInput = document.getElementById('f_prio');
  const descInput = document.getElementById('f_desc');

  let copiedTask = null;
  let draggedId = null;
  let titleTouched = false;

  const closeIco = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';
  const copyIco = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';

  const faNumLocal = (n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const esc = (s)=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const dataAttr = (obj)=>esc(JSON.stringify(obj)).replace(/'/g,'&#39;');

  const updateDurationHot = () => durInput?.classList.toggle('duration-hot', ['45','60','90'].includes(String(durInput.value)));
  durInput?.addEventListener('change', updateDurationHot);

  const TYPE_DEFAULTS = {
    study:       { target:'', unit:'درسنامه', dur:90,  title:'مطالعه', priority:'normal', desc:'' },
    test:        { target:40, unit:'تست',     dur:60,  title:'تست', priority:'high', desc:'' },
    review:      { target:'', unit:'مبحث',    dur:45,  title:'مرور', priority:'normal', desc:'' },
    textbook:    { target:20, unit:'صفحه',    dur:60,  title:'کتاب درسی', priority:'high', desc:'خواندن متن کتاب + نکات و شکل‌ها' },
    descriptive: { target:10, unit:'سوال',    dur:60,  title:'سوال تشریحی', priority:'normal', desc:'' },
    reading:     { target:1,  unit:'ساعت',    dur:60,  title:'روزخوانی', priority:'normal', desc:'' },
    exam:        { target:50, unit:'دقیقه',   dur:50,  title:'آزمونک', priority:'high', desc:'' },
    custom:      { target:'', unit:'تست',     dur:60,  title:'تسک دلخواه', priority:'normal', desc:'' },
  };
  const PRESETS = {
    test20:   { type:'test',        target:20, unit:'تست',   dur:45, title:'تست' },
    test40:   { type:'test',        target:40, unit:'تست',   dur:60, title:'تست' },
    test60:   { type:'test',        target:60, unit:'تست',   dur:90, title:'تست' },
    study60:  { type:'study',       target:'', unit:'درسنامه', dur:60, title:'مطالعه' },
    study90:  { type:'study',       target:'', unit:'درسنامه', dur:90, title:'مطالعه' },
    review45: { type:'review',      target:'', unit:'مبحث',  dur:45, title:'مرور' },
    textbook: { type:'textbook',    target:20, unit:'صفحه',  dur:60, title:'کتاب درسی' },
    desc:     { type:'descriptive', target:10, unit:'سوال',  dur:60, title:'سوال تشریحی' },
    reading:  { type:'reading',     target:1,  unit:'ساعت',  dur:60, title:'روزخوانی' },
    exam:     { type:'exam',        target:50, unit:'دقیقه', dur:50, title:'آزمونک' },
  };

  const setStatus = (state, text) => {
    if (!status) return;
    status.className = 'save-status ' + state;
    status.innerHTML = (state==='saving'?'<span class="spinner" style="width:14px;height:14px"></span>':
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>') + ' ' + text;
  };

  function ensureSelectValue(sel, val) {
    if (val === null || val === undefined) val = '';
    val = String(val);
    if (val !== '' && ![...sel.options].some(o => o.value === val)) {
      const opt = document.createElement('option');
      opt.value = val; opt.textContent = faNumLocal(val) + ' دقیقه';
      sel.appendChild(opt);
    }
    sel.value = val;
    if (sel === durInput) updateDurationHot();
  }

  function pillHTML(t) {
    const meta = [];
    if (t.target_count!==null && t.target_count!=='') meta.push(faNumLocal(t.target_count)+' '+(t.target_unit||''));
    if (t.duration_min) meta.push(faNumLocal(t.duration_min)+' دقیقه');
    const typeLabel = (window.TASK_TYPES?.[t.task_type]?.label) || t.type_label || t.task_type;
    const data = {
      id:t.id, title:t.title, description:t.description||'', task_type:t.task_type,
      day_index:t.day_index, unit_index:t.unit_index,
      target_count:t.target_count??'', target_unit:t.target_unit||'تست',
      duration_min:t.duration_min??'', priority:t.priority||'normal', subject_id:t.subject_id??''
    };
    return `<div class="task-pill type-${esc(t.task_type)} ${t.is_done?'done':''}" draggable="true" data-id="${t.id}" data-json='${dataAttr(data)}'>
      <button class="tp-copy" data-copy title="کپی">${copyIco}</button>
      <button class="tp-del" data-del title="حذف">${closeIco}</button>
      <span class="tp-title">${esc(t.title)}</span>
      ${meta.length?`<span class="tp-meta">${meta.join(' · ')}</span>`:''}
      <span class="tp-type">${esc(typeLabel)}</span>
    </div>`;
  }

  function cellTasks(cell) { return cell.querySelector('.cell-tasks'); }
  function findCell(day, unit) { return grid.querySelector(`.cell[data-day="${day}"][data-unit="${unit}"]`); }

  function clearCopyMode() {
    copiedTask = null;
    document.body.classList.remove('copy-mode');
    if (copyHint) copyHint.style.display = 'none';
    document.querySelectorAll('.task-pill.copy-source').forEach(p=>p.classList.remove('copy-source'));
  }
  function setCopyMode(pill) {
    copiedTask = JSON.parse(pill.dataset.json);
    document.body.classList.add('copy-mode');
    if (copyHint) copyHint.style.display = 'inline-flex';
    document.querySelectorAll('.task-pill.copy-source').forEach(p=>p.classList.remove('copy-source'));
    pill.classList.add('copy-source');
    toast('تسک کپی شد؛ حالا خانه مقصد را بزنید', 'info', 2600);
  }

  function setType(type, applyDefaults = true, force = false) {
    document.getElementById('taskType').value = type;
    document.querySelectorAll('.type-opt').forEach(o=>o.classList.toggle('active', o.dataset.type===type));
    if (applyDefaults) applyTypeDefaults(type, force);
  }

  function applyTypeDefaults(type, force = false) {
    const d = TYPE_DEFAULTS[type] || TYPE_DEFAULTS.study;
    targetInput.value = d.target ?? '';
    unitInput.value = d.unit || 'تست';
    ensureSelectValue(durInput, d.dur ?? '');
    prioInput.value = d.priority || 'normal';
    if (d.desc !== undefined) descInput.value = d.desc || '';
    const opt = subjectInput.options[subjectInput.selectedIndex];
    const subj = opt && opt.value ? opt.textContent.trim() : '';
    if (d.title) {
      if (subj && !['reading','exam','custom'].includes(type)) titleInput.value = subj + ' · ' + d.title;
      else titleInput.value = d.title;
      titleTouched = false;
    } else if (force) {
      titleInput.value = '';
      titleTouched = false;
    }
  }

  function applySubjectTitleIfNeeded() {
    if (titleTouched) return;
    const opt = subjectInput.options[subjectInput.selectedIndex];
    const name = opt && opt.value ? opt.textContent.trim() : '';
    const type = document.getElementById('taskType').value;
    const d = TYPE_DEFAULTS[type] || TYPE_DEFAULTS.study;
    if (name && !['reading','exam','custom'].includes(type)) titleInput.value = name + ' · ' + (d.title || 'تسک');
  }

  function applyPreset(key) {
    const p = PRESETS[key]; if (!p) return;
    setType(p.type, false);
    targetInput.value = p.target ?? '';
    unitInput.value = p.unit || 'تست';
    ensureSelectValue(durInput, p.dur ?? '');
    const d = TYPE_DEFAULTS[p.type] || {};
    prioInput.value = d.priority || (p.type === 'test' || p.type === 'exam' ? 'high' : 'normal');
    descInput.value = d.desc || '';
    const opt = subjectInput.options[subjectInput.selectedIndex];
    const subj = opt && opt.value ? opt.textContent.trim() : '';
    if (p.title) {
      titleInput.value = (subj && !['reading','exam','custom'].includes(p.type)) ? (subj + ' · ' + p.title) : p.title;
      titleTouched = false;
    }
  }

  function openNew(day, unit) {
    form.reset();
    titleTouched = false;
    document.getElementById('taskId').value = '';
    document.getElementById('taskDay').value = day;
    document.getElementById('taskUnit').value = unit;
    const defaultType = String(unit) === '8' ? 'reading' : 'test';
    setType(defaultType, true, true);
    document.getElementById('taskModalTitle').textContent = 'افزودن تسک';
    document.getElementById('deleteTaskBtn').style.display = 'none';
    openModal('taskModal');
    setTimeout(()=>titleInput.focus(),200);
  }

  function openEdit(pill) {
    const t = JSON.parse(pill.dataset.json);
    form.reset();
    titleTouched = true;
    document.getElementById('taskId').value = t.id;
    document.getElementById('taskDay').value = t.day_index ?? '';
    document.getElementById('taskUnit').value = t.unit_index ?? '';
    titleInput.value = t.title || '';
    descInput.value = t.description || '';
    targetInput.value = t.target_count===''?'':t.target_count;
    unitInput.value = t.target_unit || 'تست';
    ensureSelectValue(durInput, t.duration_min===''?'':t.duration_min);
    prioInput.value = t.priority || 'normal';
    subjectInput.value = t.subject_id || '';
    setType(t.task_type, false);
    document.getElementById('taskModalTitle').textContent = 'ویرایش تسک';
    document.getElementById('deleteTaskBtn').style.display = '';
    document.getElementById('deleteTaskBtn').dataset.id = t.id;
    openModal('taskModal');
  }

  async function createInCell(data, cell) {
    const payload = {
      action:'create', plan_id:planId,
      title:data.title || '', description:data.description || '', task_type:data.task_type || 'study',
      day_index:cell.dataset.day, unit_index:cell.dataset.unit,
      target_count:data.target_count ?? '', target_unit:data.target_unit || 'تست', duration_min:data.duration_min ?? '',
      subject_id:data.subject_id || '', priority:data.priority || 'normal'
    };
    setStatus('saving','در حال کپی…');
    const d = await api(window.API_TASKS, { method:'POST', body: payload });
    cellTasks(cell).insertAdjacentHTML('beforeend', pillHTML(d.task));
    setStatus('saved','ذخیره شد');
    recalc();
    return d.task;
  }

  async function moveToCell(id, cell) {
    setStatus('saving','در حال جابه‌جایی…');
    const d = await api(window.API_TASKS, { method:'POST', body:{ action:'move', id, day_index:cell.dataset.day, unit_index:cell.dataset.unit } });
    document.querySelector(`.task-pill[data-id="${id}"]`)?.remove();
    cellTasks(cell).insertAdjacentHTML('beforeend', pillHTML(d.task));
    setStatus('saved','جابه‌جا شد');
    recalc();
    return d.task;
  }

  grid.addEventListener('click', async (e) => {
    const copy = e.target.closest('[data-copy]');
    if (copy) { e.stopPropagation(); setCopyMode(copy.closest('.task-pill')); return; }
    const del = e.target.closest('[data-del]');
    if (del) { e.stopPropagation(); deleteTask(del.closest('.task-pill')); return; }
    const pill = e.target.closest('.task-pill');
    if (pill) { e.stopPropagation(); openEdit(pill); return; }
    const cell = e.target.closest('.cell');
    if (!cell) return;
    if (copiedTask) {
      try { await createInCell(copiedTask, cell); toast('تسک در خانه مقصد کپی شد','success',1600); }
      catch(err){ toast(err.error||'خطا در کپی','error'); }
      return;
    }
    openNew(cell.dataset.day, cell.dataset.unit);
  });

  // drag & drop
  grid.addEventListener('dragstart', (e) => {
    const pill = e.target.closest('.task-pill'); if (!pill) return;
    draggedId = pill.dataset.id;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', draggedId);
    setTimeout(()=>pill.classList.add('dragging'),0);
  });
  grid.addEventListener('dragend', () => {
    draggedId = null;
    document.querySelectorAll('.task-pill.dragging').forEach(p=>p.classList.remove('dragging'));
    document.querySelectorAll('.cell.drop-target').forEach(c=>c.classList.remove('drop-target'));
  });
  grid.addEventListener('dragover', (e) => {
    const cell = e.target.closest('.cell'); if (!cell || !draggedId) return;
    e.preventDefault(); e.dataTransfer.dropEffect = 'move';
    document.querySelectorAll('.cell.drop-target').forEach(c=>{ if(c!==cell)c.classList.remove('drop-target'); });
    cell.classList.add('drop-target');
  });
  grid.addEventListener('dragleave', (e) => {
    const cell = e.target.closest('.cell'); if (cell && !cell.contains(e.relatedTarget)) cell.classList.remove('drop-target');
  });
  grid.addEventListener('drop', async (e) => {
    const cell = e.target.closest('.cell'); if (!cell) return;
    e.preventDefault(); cell.classList.remove('drop-target');
    const id = draggedId || e.dataTransfer.getData('text/plain'); if (!id) return;
    try { await moveToCell(id, cell); }
    catch(err){ toast(err.error||'خطا در جابه‌جایی','error'); setStatus('saved','ذخیره خودکار فعال'); }
  });

  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') clearCopyMode(); });
  document.getElementById('typeGrid').addEventListener('click', e=>{
    const o=e.target.closest('.type-opt'); if(o) setType(o.dataset.type, true, true);
  });
  document.getElementById('quickPresets')?.addEventListener('click', e=>{
    const b=e.target.closest('[data-preset]'); if(b) applyPreset(b.dataset.preset);
  });
  titleInput.addEventListener('input', ()=>{ titleTouched = true; });
  subjectInput.addEventListener('change', applySubjectTitleIfNeeded);

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
      if (id) document.querySelector(`.task-pill[data-id="${t.id}"]`)?.remove();
      const cell = findCell(t.day_index, t.unit_index)?.querySelector('.cell-tasks');
      cell?.insertAdjacentHTML('beforeend', pillHTML(t));
      closeModal('taskModal');
      setStatus('saved','ذخیره شد');
      recalc();
      toast(id?'تسک ویرایش شد':'تسک اضافه شد','success',1600);
    } catch(err) {
      setStatus('saved','ذخیره خودکار فعال');
      toast(err.error||'خطا در ذخیره','error');
    }
  });

  document.getElementById('deleteTaskBtn').addEventListener('click', async function(){
    const id = this.dataset.id;
    if (!confirm('این تسک حذف شود؟')) return;
    try {
      await api(window.API_TASKS,{method:'POST',body:{action:'delete',id}});
      document.querySelector(`.task-pill[data-id="${id}"]`)?.remove();
      closeModal('taskModal'); recalc(); toast('تسک حذف شد','success',1400);
    } catch(e){ toast(e.error||'خطا','error'); }
  });
  async function deleteTask(pill){
    if(!pill) return;
    const id = pill.dataset.id;
    if(!confirm('این تسک حذف شود؟')) return;
    try { await api(window.API_TASKS,{method:'POST',body:{action:'delete',id}}); pill.remove(); recalc(); toast('حذف شد','success',1200);}
    catch(e){ toast(e.error||'خطا','error'); }
  }

  document.querySelectorAll('[data-clear-day]').forEach(b=>{
    b.addEventListener('click', async (e)=>{
      e.stopPropagation();
      const day=b.dataset.clearDay;
      if(!confirm('همه تسک‌های این روز پاک شود؟')) return;
      try{ await api(window.API_TASKS,{method:'POST',body:{action:'clear_day',plan_id:planId,day_index:day}});
        grid.querySelectorAll(`.cell[data-day="${day}"] .task-pill`).forEach(p=>p.remove());
        recalc(); toast('روز پاک شد','success',1200);
      }catch(e){ toast(e.error||'خطا','error'); }
    });
  });

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

  document.getElementById('copyWeekBtn')?.addEventListener('click', async function(){
    if(!confirm('تسک‌های این هفته با کپی هفته قبل جایگزین شوند؟')) return;
    try{
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'copy_week',plan_id:planId}});
      toast(faNumLocal(d.copied)+' تسک کپی شد. در حال بارگذاری…','success');
      setTimeout(()=>location.reload(),900);
    }catch(e){ toast(e.error||'خطا','error'); }
  });

  document.getElementById('seedSpecialBtn')?.addEventListener('click', async function(){
    try{
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'seed_special',plan_id:planId}});
      if (d.added > 0) {
        toast(faNumLocal(d.added)+' تسک واحد ویژه اضافه شد','success');
        setTimeout(()=>location.reload(),700);
      } else toast('واحد ویژه از قبل کامل است','info');
    }catch(e){ toast(e.error||'خطا','error'); }
  });

  document.getElementById('copyToStudentBtn')?.addEventListener('click', async function(){
    const sel = document.getElementById('copyTargetStudent');
    const sid = sel?.value || '';
    if (!sid) { toast('دانش‌آموز مقصد را انتخاب کنید','error'); return; }
    const name = sel.options[sel.selectedIndex]?.textContent || 'دانش‌آموز مقصد';
    if (!confirm('کل برنامه این هفته برای «'+name+'» کپی شود؟ برنامه مقصد جایگزین و به پیش‌نویس تبدیل می‌شود.')) return;
    try{
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'copy_to_student',plan_id:planId,student_id:sid}});
      toast(faNumLocal(d.copied)+' تسک برای '+name+' کپی شد','success',2600);
    }catch(e){ toast(e.error||'خطا در کپی برنامه','error'); }
  });

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
