/* =================================================================
   مَدار Exam Builder — Samurai Multi-Mode Studio
   ================================================================= */
(() => {
  'use strict';
  const root = document.getElementById('examBuilder');
  if (!root) return;
  let examId = parseInt(root.dataset.exam) || 0;
  const API = window.API_EXAM;
  const status = document.getElementById('saveStatus');
  const faNum = (n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const esc = (s)=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  let dirty = new Set();
  let metaDirty = false;

  const ICO={check:'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>',
    trash:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>',
    clip:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M21 11l-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 6"/></svg>',
    note:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M14 3v5h5M14 3l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/></svg>',
    close:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
    plus:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    book:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14z"/></svg>'};

  const setStatus=(s,t)=>{
    if (!status) return;
    status.className='save-status '+s;
    status.innerHTML=(s==='saving'?'<span class="spinner" style="width:14px;height:14px"></span>':ICO.check)+' '+t;
  };

  function grow(el){ el.style.height='auto'; el.style.height=el.scrollHeight+'px'; }
  document.querySelectorAll('.q-text').forEach(grow);

  function syncTiming(){
    const v=document.getElementById('m_timing').value;
    root.dataset.timing=v;
    const tf = document.getElementById('totalDurField');
    if(tf) tf.style.display = v==='total'?'':'none';
  }
  document.getElementById('m_timing')?.addEventListener('change',()=>{ syncTiming(); metaDirty=true; });
  syncTiming();

  // شیوه طراحی (Creation mode radio buttons)
  function creationMode() {
    return document.querySelector('input[name="creation_mode"]:checked')?.value || 'standard';
  }

  document.querySelectorAll('.mode-card').forEach(card => {
    card.addEventListener('click', () => {
      const inp = card.querySelector('input[name="creation_mode"]');
      if (inp) inp.checked = true;
      document.querySelectorAll('.mode-card').forEach(c => {
        c.classList.remove('active');
        c.style.borderColor = 'var(--border-soft)';
        c.style.background = 'var(--surface-1)';
      });
      card.classList.add('active');
      card.style.borderColor = card.querySelector('input').value === 'quick_sheet' ? 'var(--gold)' : 'var(--cyan)';
      card.style.background = 'var(--surface-2)';
      root.dataset.mode = card.querySelector('input').value;
      metaDirty = true;
      syncStudioSuites();
    });
  });

  function syncStudioSuites() {
    const mode = root.dataset.mode || 'quick_sheet';
    document.querySelectorAll('.studio-suite').forEach(suite => {
      if (suite.id === 'suiteQuickSheet') suite.classList.toggle('hidden', mode !== 'quick_sheet');
      if (suite.id === 'suiteStandard')   suite.classList.toggle('hidden', mode !== 'standard');
    });
  }
  syncStudioSuites();

  function updateUrl(step = root.dataset.step || '1') {
    if (!examId) return;
    const u = new URL(location.href);
    u.searchParams.set('id', String(examId));
    u.searchParams.set('step', String(step));
    u.searchParams.set('mode', root.dataset.mode || creationMode() || 'quick_sheet');
    history.replaceState(null, '', u.pathname + u.search + u.hash);
  }

  function examType(){ return document.querySelector('input[name="exam_type"]:checked')?.value || 'single'; }

  function metaPayload(){
    return {
      action:'save_meta', id:examId,
      title:document.getElementById('m_title').value,
      description:document.getElementById('m_desc').value,
      exam_type:examType(),
      timing_mode:document.getElementById('m_timing').value,
      duration_min:document.getElementById('m_dur').value,
      start_at:document.getElementById('m_start').value,
      negative_marking:document.getElementById('m_neg').checked?'1':'0',
      show_review:document.getElementById('m_rev').checked?'1':'0',
      shuffle_questions:document.getElementById('m_shuf')?.checked?'1':'0',
      creation_mode:creationMode(),
    };
  }

  async function saveMeta(){
    const title=document.getElementById('m_title').value.trim();
    if(!title){ return false; }
    setStatus('saving','در حال ذخیره…');
    try{
      const d=await api(API,{method:'POST',body:metaPayload()});
      if(!examId && d.id){ examId=d.id; root.dataset.exam=d.id; }
      updateUrl(root.dataset.step || '1');
      metaDirty=false; setStatus('saved','ذخیره شد');
      return true;
    }catch(e){ setStatus('saved','آماده'); return false; }
  }
  document.getElementById('metaForm')?.addEventListener('input',()=>{ metaDirty=true; });

  function goStep(n){
    root.dataset.step=n;
    document.querySelectorAll('.builder-step').forEach(s=>s.classList.toggle('hidden', s.dataset.step!=String(n)));
    document.querySelectorAll('.stepper .step').forEach(b => b.classList.toggle('active', b.dataset.stepTo==String(n)));
    updateUrl(n);
    window.scrollTo({top:0,behavior:'smooth'});
  }

  async function gotoQuestions(){
    const title=document.getElementById('m_title').value.trim();
    if(!title){ toast('لطفاً عنوان آزمون را وارد کن','error'); document.getElementById('m_title').focus(); return; }
    if(!examId || metaDirty){ const ok=await saveMeta(); if(!ok){ toast('ابتدا عنوان آزمون را وارد کن','error'); return; } }
    goStep(2);
  }
  document.getElementById('toStep2Btn')?.addEventListener('click',gotoQuestions);
  document.getElementById('backToStep1')?.addEventListener('click',()=>goStep(1));
  document.querySelectorAll('[data-step-to]').forEach(b=>b.addEventListener('click',async()=>{
    const n=parseInt(b.dataset.stepTo);
    if(n===2){ gotoQuestions(); } else { if(metaDirty) saveMeta(); goStep(1); }
  }));

  /* =================================================================
     SAMURAI STUDIO 1: Multi-Image Quick Sheet & Batch Bubbles
     ================================================================= */
  const sheetInput  = document.getElementById('examSheetInput');
  const thumbsGrid  = document.getElementById('uploadedSheetsThumbsGrid');

  function uploadFormData(url, fd, onProgress){
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', url, true);
      if (window.MADAR?.csrf) xhr.setRequestHeader('X-CSRF-Token', window.MADAR.csrf);
      xhr.responseType = 'text';
      xhr.upload.onprogress = (ev) => {
        if (ev.lengthComputable && onProgress) onProgress(Math.round(ev.loaded / ev.total * 100));
      };
      xhr.onload = () => {
        let data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch(_) { data = {ok:false,error:'پاسخ نامعتبر از سرور'}; }
        if (xhr.status >= 200 && xhr.status < 300) resolve(data);
        else reject(data || {ok:false,error:'خطای آپلود ('+xhr.status+')'});
      };
      xhr.onerror = () => reject({ok:false,error:'ارتباط هنگام آپلود قطع شد'});
      xhr.send(fd);
    });
  }

  function formatSize(bytes){
    bytes = Number(bytes || 0);
    if (!bytes) return '';
    if (bytes >= 1024*1024) return faNum((bytes/(1024*1024)).toFixed(bytes >= 100*1024*1024 ? 0 : 1)) + ' مگابایت';
    return faNum(Math.ceil(bytes/1024)) + ' کیلوبایت';
  }
  function renderSheetThumbs(items = []) {
    if (!thumbsGrid) return;
    thumbsGrid.innerHTML = (items || []).map((it, idx) => {
      const type = it.type || (String(it.rel||'').toLowerCase().endsWith('.pdf') ? 'pdf' : 'image');
      const body = type === 'pdf'
        ? `<div class="sheet-pdf-thumb"><span class="pdf-ico">PDF</span><b>دفترچه PDF</b><small>${formatSize(it.size)}</small></div>`
        : `<img src="${esc(it.url || '')}" alt="صفحه ${idx + 1}">`;
      return `<div class="sheet-thumb-item relative panel ${type==='pdf'?'pdf':''}" data-spath="${esc(it.rel || '')}" data-type="${esc(type)}">
        <span class="sheet-page-badge badge badge-gold">ص${faNum(idx + 1)}</span>
        <button type="button" class="btn btn-ghost btn-sm remove-sheet-item-btn" title="حذف این فایل">×</button>
        ${body}
      </div>`;
    }).join('');
  }

  sheetInput?.addEventListener('change', async (e) => {
    const files = e.target.files; if (!files || !files.length) return;
    root.dataset.mode = 'quick_sheet';
    const quickRadio = document.querySelector('input[name="creation_mode"][value="quick_sheet"]');
    if (quickRadio) quickRadio.checked = true;
    syncStudioSuites();
    if (!examId) {
      const ok = await saveMeta();
      if (!ok || !examId) { toast('ابتدا عنوان آزمون را وارد کن', 'error'); e.target.value=''; return; }
    }
    setStatus('saving','در حال آپلود صفحات…');
    
    const fd = new FormData(); 
    fd.append('action','upload_sheet'); 
    fd.append('exam_id', examId);
    for(let i=0; i<files.length; i++) fd.append('sheet[]', files[i]);

    try {
      const d = await uploadFormData(API, fd, pct => setStatus('saving', 'در حال آپلود… ' + faNum(pct) + '٪'));
      if (d.sheet_items) renderSheetThumbs(d.sheet_items);
      toast('فایل دفترچه با موفقیت آپلود و اضافه شد 📝', 'success');
      setStatus('saved','✓ آپلود شد');
      goStep(2);
    } catch(err) { toast(err.error || 'خطا در آپلود عکس', 'error'); setStatus('saved','آماده'); }
    e.target.value = '';
  });

  thumbsGrid?.addEventListener('click', async e => {
    const bDel = e.target.closest('.remove-sheet-item-btn'); if (!bDel) return;
    const item = bDel.closest('.sheet-thumb-item');
    const path = item.dataset.spath;
    if (!confirm('آیا از حذف این صفحه مطمئنی؟')) return;

    setStatus('saving','در حال حذف…');
    try {
      const d = await api(API, { method: 'POST', body: { action: 'remove_sheet_item', exam_id: examId, sheet_path: path } });
      if (d.sheet_items) renderSheetThumbs(d.sheet_items); else item.remove();
      toast('صفحه حذف شد', 'success');
      setStatus('saved','✓ آماده');
    } catch(err) { toast(err.error || 'خطا در حذف', 'error'); setStatus('saved','آماده'); }
  });

  // Interactive UI for 100 Bubble Clicking Key Grid
  const quickKeyInput  = document.getElementById('quickKeyInput');
  const bubbleStudio   = document.querySelector('.bubble-grid-studio');
  const keyBadge       = document.getElementById('keyQCountBadge');

  function syncKeyInputToBubbles() {
    let kStr = '';
    bubbleStudio?.querySelectorAll('.bg-item').forEach(item => {
      const activeOpt = item.querySelector('.bubble-btn.active')?.dataset.opt || '0';
      kStr += activeOpt;
    });
    if(quickKeyInput) quickKeyInput.value = kStr.replace(/0+$/, ''); // trim trailing untouched zeros
    if(keyBadge) keyBadge.textContent = faNum(kStr.replace(/0+$/, '').length) + ' سوال';
  }

  function syncBubblesToKeyInput() {
    const val = (quickKeyInput?.value || '').replace(/[۱١]/g,'1').replace(/[۲٢]/g,'2').replace(/[۳٣]/g,'3').replace(/[۴٤]/g,'4').replace(/[^1-4]/g, '');
    if(keyBadge) keyBadge.textContent = faNum(val.length) + ' سوال';
    
    // اطمینان از وجود کافی آیتم حباب
    const currItems = bubbleStudio?.querySelectorAll('.bg-item') || [];
    if (val.length > currItems.length) {
      appendNewBubbles(val.length - currItems.length);
    }

    bubbleStudio?.querySelectorAll('.bg-item').forEach((item, idx) => {
      const charVal = val[idx];
      item.querySelectorAll('.bubble-btn').forEach(btn => {
        const isOpt = btn.dataset.opt === charVal;
        btn.classList.toggle('active', isOpt);
        btn.style.background = isOpt ? 'var(--gold)' : 'var(--surface-2)';
        btn.style.color      = isOpt ? '#000'        : 'var(--text-2)';
      });
    });
  }

  quickKeyInput?.addEventListener('input', syncBubblesToKeyInput);

  bubbleStudio?.addEventListener('click', e => {
    const btn = e.target.closest('.bubble-btn'); if (!btn) return;
    const parent = btn.closest('.bg-item');
    parent.querySelectorAll('.bubble-btn').forEach(b => {
      b.classList.remove('active');
      b.style.background = 'var(--surface-2)';
      b.style.color      = 'var(--text-2)';
    });
    btn.classList.add('active');
    btn.style.background = 'var(--gold)';
    btn.style.color      = '#000';
    syncKeyInputToBubbles();
  });

  function appendNewBubbles(count) {
    if (!bubbleStudio) return;
    const startIdx = bubbleStudio.querySelectorAll('.bg-item').length + 1;
    let html = '';
    for (let qi = startIdx; qi < startIdx + count; qi++) {
      html += `<div class="bg-item" data-qnum="${qi}" style="background:var(--surface-1);padding:8px;border-radius:8px;display:flex;flex-direction:column;align-items:center;border:1px solid var(--border-soft)">
        <span style="font-size:.75rem;color:var(--text-3);font-weight:bold;margin-bottom:4px">Q${qi}</span>
        <div class="flex gap-1">
          <button type="button" class="bubble-btn" data-opt="1" style="width:22px;height:22px;border-radius:50%;border:1px solid var(--border-soft);background:var(--surface-2);color:var(--text-2);font-size:.7rem;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center">1</button>
          <button type="button" class="bubble-btn" data-opt="2" style="width:22px;height:22px;border-radius:50%;border:1px solid var(--border-soft);background:var(--surface-2);color:var(--text-2);font-size:.7rem;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center">2</button>
          <button type="button" class="bubble-btn" data-opt="3" style="width:22px;height:22px;border-radius:50%;border:1px solid var(--border-soft);background:var(--surface-2);color:var(--text-2);font-size:.7rem;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center">3</button>
          <button type="button" class="bubble-btn" data-opt="4" style="width:22px;height:22px;border-radius:50%;border:1px solid var(--border-soft);background:var(--surface-2);color:var(--text-2);font-size:.7rem;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center">4</button>
        </div>
      </div>`;
    }
    bubbleStudio.insertAdjacentHTML('beforeend', html);
  }

  document.getElementById('add10BubblesBtn')?.addEventListener('click', () => {
    appendNewBubbles(10);
    bubbleStudio.scrollTop = bubbleStudio.scrollHeight;
  });

  document.getElementById('saveQuickSheetSuiteBtn')?.addEventListener('click', async function() {
    const rawKey = quickKeyInput ? quickKeyInput.value : '';
    const answerKey = rawKey.replace(/[۱١]/g,'1').replace(/[۲٢]/g,'2').replace(/[۳٣]/g,'3').replace(/[۴٤]/g,'4').replace(/[^1-4]/g, '');
    if (!answerKey) { toast('لطفاً پاسخنامه کلیدی را وارد کنید یا روی حباب‌ها کلیک کنید', 'error'); return; }
    if (!examId) {
      const ok = await saveMeta();
      if (!ok || !examId) { toast('ابتدا عنوان آزمون را وارد کن', 'error'); return; }
    }
    
    const oldHtml = this.innerHTML;
    this.disabled = true; this.innerHTML = '<span class="spinner"></span> در حال ساخت سوالات…';
    const sheetPath = thumbsGrid?.querySelector('.sheet-thumb-item')?.dataset.spath || '';

    try {
      const d = await api(API, { method: 'POST', body: { action: 'quick_sheet_generate', exam_id: examId, sheet_path: sheetPath, answer_key: answerKey } });
      toast(`آزمون تصویرمحور با موفقیت ساخته شد (${faNum(d.q_count)} سوال) 🎉`, 'success');
      setTimeout(() => { location.href = `${location.pathname}?id=${examId}&step=2&mode=quick_sheet`; }, 650);
    } catch(err) {
      toast(err.error || 'خطا در ثبت نهایی آزمون', 'error');
      this.disabled = false; this.innerHTML = oldHtml || '✓ ثبت نهایی و ساخت سوالات';
    }
  });

  /* =================================================================
     STANDARD STUDIO: traditional DOM question cards
     ================================================================= */
  function collectQuestions(){
    const out=[];
    document.querySelectorAll('.q-card').forEach(card=>{
      const id=parseInt(card.dataset.question); if(!id) return;
      const correct=card.querySelector('[data-correct]:checked');
      out.push({
        id,
        q_text:card.querySelector('[data-q-text]').value,
        opt1:card.querySelector('[data-opt-text="1"]').value,
        opt2:card.querySelector('[data-opt-text="2"]').value,
        opt3:card.querySelector('[data-opt-text="3"]').value,
        opt4:card.querySelector('[data-opt-text="4"]').value,
        correct_opt:correct?correct.value:1,
        explanation:card.querySelector('[data-q-exp]')?.value||'',
      });
    });
    return out;
  }

  async function autosave(){
    if(!examId || root.dataset.mode !== 'standard') return;
    if(metaDirty){ await saveMeta(); }
    if(dirty.size===0) return;
    const all=collectQuestions();
    const payload=all.filter(q=>dirty.has(q.id));
    if(!payload.length){ return; }
    setStatus('saving','در حال ذخیره…');
    try{
      await api(API,{method:'POST',body:{action:'autosave',exam_id:examId,questions:payload}});
      dirty.clear(); setStatus('saved','✓ ذخیره شد · '+faNum(new Date().toLocaleTimeString('fa-IR',{hour:'2-digit',minute:'2-digit'})));
    }catch(e){ setStatus('saved','ذخیره ناموفق'); }
  }
  setInterval(autosave, 5000);

  root.addEventListener('input',(e)=>{
    const card=e.target.closest('.q-card');
    if(card && root.dataset.mode === 'standard'){
      dirty.add(parseInt(card.dataset.question)); setStatus('saving','تغییرات ذخیره‌نشده…');
      if(e.target.matches('.q-text')) grow(e.target);
    }
  });

  root.addEventListener('change',(e)=>{
    if(e.target.matches('[data-correct]')){
      const card=e.target.closest('.q-card');
      card.querySelectorAll('.q-opt').forEach(o=>o.classList.remove('correct'));
      e.target.closest('.q-opt').classList.add('correct');
      dirty.add(parseInt(card.dataset.question));
    }
  });

  async function addSection(){
    if(!examId){ await saveMeta(); if(!examId){ toast('ابتدا عنوان آزمون را وارد کنید','error'); return; } }
    try{
      const d=await api(API,{method:'POST',body:{action:'add_section',exam_id:examId,name:'درس جدید'}});
      document.getElementById('emptySections')?.remove();
      const html=sectionHTML(d.id,'درس جدید','');
      document.getElementById('sectionsWrap').insertAdjacentHTML('beforeend',html);
      updateCounts();
      const sec=document.querySelector(`.exam-section[data-section="${d.id}"]`);
      sec.querySelector('[data-sec-name]').focus();
      sec.querySelector('[data-sec-name]').select();
    }catch(e){ toast(e.error||'خطا','error'); }
  }
  document.getElementById('addSectionBtn')?.addEventListener('click',addSection);
  document.getElementById('addSectionBtn2')?.addEventListener('click',addSection);

  let secTimer={};
  root.addEventListener('input',(e)=>{
    const sec=e.target.closest('.exam-section'); if(!sec) return;
    if(e.target.matches('[data-sec-name],[data-sec-dur]')){
      const id=sec.dataset.section; clearTimeout(secTimer[id]);
      secTimer[id]=setTimeout(()=>{
        api(API,{method:'POST',body:{action:'update_section',id,name:sec.querySelector('[data-sec-name]').value,duration_min:sec.querySelector('[data-sec-dur]').value}}).catch(()=>{});
      },800);
    }
  });

  root.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-del-section]'); if(!b) return;
    const sec=b.closest('.exam-section');
    if(!confirm('این درس و همه‌ی سوالاتش حذف شود؟')) return;
    try{ await api(API,{method:'POST',body:{action:'delete_section',id:sec.dataset.section}});
      sec.remove(); updateCounts();
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  root.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-add-question]'); if(!b) return;
    const sec=b.closest('.exam-section');
    try{
      const d=await api(API,{method:'POST',body:{action:'add_question',exam_id:examId,section_id:sec.dataset.section}});
      const idx=sec.querySelectorAll('.q-card').length+1;
      const html=questionHTML(d.id,idx);
      sec.querySelector('.questions-wrap').insertAdjacentHTML('beforeend',html);
      renumber(sec); updateCounts();
      const card=sec.querySelector(`.q-card[data-question="${d.id}"]`);
      card.querySelector('.q-text').focus();
      card.scrollIntoView({block:'center',behavior:'smooth'});
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  root.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-del-question]'); if(!b) return;
    const card=b.closest('.q-card'); const sec=card.closest('.exam-section');
    if(!confirm('این سوال حذف شود؟')) return;
    try{ await api(API,{method:'POST',body:{action:'delete_question',id:card.dataset.question}});
      dirty.delete(parseInt(card.dataset.question));
      card.remove(); renumber(sec); updateCounts();
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  root.addEventListener('keydown',(e)=>{
    if(e.key==='Enter' && e.target.matches('[data-opt-text="4"]')){
      e.preventDefault();
      const sec=e.target.closest('.exam-section');
      sec.querySelector('[data-add-question]').click();
    }
  });

  root.addEventListener('change',async(e)=>{
    if(!e.target.matches('[data-q-img]')) return;
    const file=e.target.files[0]; if(!file) return;
    const card=e.target.closest('.q-card');
    const fd=new FormData(); fd.append('action','upload_image'); fd.append('exam_id',examId);
    fd.append('question_id',card.dataset.question); fd.append('image',file);
    try{ const d=await api(API,{method:'POST',body:fd});
      const prev=card.querySelector('[data-q-img-preview]');
      prev.querySelector('img')?.remove();
      prev.insertAdjacentHTML('afterbegin',`<img src="${d.url}" alt="">`);
      prev.classList.remove('hidden');
      toast('عکس اضافه شد','success',1500);
    }catch(err){ toast(err.error||'خطا در آپلود','error'); }
    e.target.value='';
  });

  root.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-q-img-remove]'); if(!b) return;
    const card=b.closest('.q-card');
    try{ await api(API,{method:'POST',body:{action:'remove_image',id:card.dataset.question}});
      const prev=card.querySelector('[data-q-img-preview]'); prev.querySelector('img')?.remove(); prev.classList.add('hidden');
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  document.getElementById('studioPublishBtn')?.addEventListener('click',async function(){
    if(dirty.size||metaDirty) await autosave();
    const cur=this.dataset.status;
    const next=cur==='published'?'draft':'published';
    try{
      const d=await api(API,{method:'POST',body:{action:'set_status',exam_id:examId,status:next}});
      this.dataset.status=d.status;
      if(d.status==='published'){ this.innerHTML='✓ لغو انتشار'; toast('آزمون به‌طور نهایی منتشر شد 🚀','success'); }
      else { this.innerHTML='🚀 انتشار نهایی آزمون'; toast('آزمون به حالت پیش‌نویس بازگشت','info'); }
    }catch(err){ toast(err.error||'خطا در انتشار','error'); }
  });

  function updateCounts(){
    const ts = document.getElementById('totalSec');
    const tq = document.getElementById('totalQ');
    if(ts) ts.textContent=faNum(document.querySelectorAll('.exam-section').length);
    if(tq) tq.textContent=faNum(document.querySelectorAll('.q-card').length);
    document.querySelectorAll('.exam-section').forEach(sec=>{
      const c=sec.querySelectorAll('.q-card').length;
      sec.querySelector('.sec-count').textContent=faNum(c)+' سوال';
    });
  }
  function renumber(sec){ sec.querySelectorAll('.q-card .q-num').forEach((n,i)=>n.textContent=faNum(i+1)); }

  function sectionHTML(id,name,dur){
    return `<div class="exam-section" data-section="${id}">
      <div class="section-head">
        <div class="flex gap-2" style="align-items:center;flex:1">
          <span class="sec-handle">${ICO.book}</span>
          <input class="sec-name-input" data-sec-name value="${esc(name)}" placeholder="نام درس (مثلاً شیمی)">
          <input class="sec-dur-input timing-section" data-sec-dur type="number" min="1" value="${dur||''}" placeholder="دقیقه">
        </div>
        <div class="flex gap-2">
          <span class="badge sec-count">۰ سوال</span>
          <button class="btn btn-ghost btn-sm btn-icon" data-del-section style="color:var(--danger)">${ICO.trash}</button>
        </div>
      </div>
      <div class="questions-wrap"></div>
      <button class="add-q-btn" data-add-question>${ICO.plus} افزودن سوال به این درس</button>
    </div>`;
  }
  function questionHTML(id,num){
    let opts='';
    for(let o=1;o<=4;o++){
      opts+=`<label class="q-opt ${o===1?'correct':''}" data-opt="${o}">
        <input type="radio" name="correct_${id}" value="${o}" data-correct ${o===1?'checked':''}>
        <span class="opt-marker">${faNum(o)}</span>
        <input class="opt-input" data-opt-text="${o}" value="" placeholder="گزینه ${faNum(o)}">
        <span class="opt-correct-badge">${ICO.check}</span>
      </label>`;
    }
    return `<div class="q-card" data-question="${id}">
      <div class="q-top">
        <span class="q-num">${faNum(num)}</span>
        <textarea class="q-text" data-q-text rows="1" placeholder="متن سوال را بنویسید…"></textarea>
        <div class="q-tools">
          <label class="q-img-btn" data-tip="افزودن عکس">${ICO.clip}<input type="file" accept="image/*" data-q-img hidden></label>
          <button class="btn btn-ghost btn-sm btn-icon" data-del-question style="color:var(--danger)">${ICO.trash}</button>
        </div>
      </div>
      <div class="q-img-preview hidden" data-q-img-preview><button class="q-img-remove" data-q-img-remove>${ICO.close}</button></div>
      <div class="q-options">${opts}</div>
      <details class="q-exp"><summary>${ICO.note} پاسخ تشریحی (اختیاری)</summary><textarea class="input" data-q-exp rows="2" placeholder="توضیح پاسخ صحیح…"></textarea></details>
    </div>`;
  }
})();
