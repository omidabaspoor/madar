/* =================================================================
   Exam Builder — fast question entry + 5s autosave
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
  let dirty = new Set();        // question ids with unsaved changes
  let metaDirty = false;

  const ICO={check:'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>',
    trash:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>',
    clip:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M21 11l-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 6"/></svg>',
    note:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M14 3v5h5M14 3l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/></svg>',
    close:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
    plus:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    book:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14z"/></svg>'};

  const setStatus=(s,t)=>{ status.className='save-status '+s;
    status.innerHTML=(s==='saving'?'<span class="spinner" style="width:14px;height:14px"></span>':ICO.check)+' '+t; };

  /* ---- auto-grow textareas ---- */
  function grow(el){ el.style.height='auto'; el.style.height=el.scrollHeight+'px'; }
  document.querySelectorAll('.q-text').forEach(grow);

  /* ---- timing mode display ---- */
  function syncTiming(){
    const v=document.getElementById('m_timing').value;
    root.dataset.timing=v;
    document.getElementById('totalDurField').style.display = v==='total'?'':'none';
  }
  document.getElementById('m_timing').addEventListener('change',()=>{ syncTiming(); metaDirty=true; });
  syncTiming();

  /* ---- type cards (radio) ---- */
  document.querySelectorAll('.type-card').forEach(card=>{
    card.addEventListener('click',()=>{
      document.querySelectorAll('.type-card').forEach(c=>c.classList.remove('active'));
      card.classList.add('active'); metaDirty=true;
    });
  });
  function examType(){ return document.querySelector('input[name="exam_type"]:checked')?.value || 'single'; }

  /* ---- META autosave (debounced) ---- */
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
      shuffle_questions:document.getElementById('m_shuf').checked?'1':'0',
    };
  }
  async function saveMeta(){
    const title=document.getElementById('m_title').value.trim();
    if(!title){ return false; }
    setStatus('saving','در حال ذخیره…');
    try{
      const d=await api(API,{method:'POST',body:metaPayload()});
      if(!examId && d.id){ examId=d.id; root.dataset.exam=d.id;
        history.replaceState(null,'',location.pathname+'?id='+examId);
      }
      metaDirty=false; setStatus('saved','ذخیره شد');
      return true;
    }catch(e){ setStatus('saved','ذخیره خودکار فعال'); return false; }
  }
  document.getElementById('metaForm').addEventListener('input',()=>{ metaDirty=true; });

  /* ---- stepper navigation ---- */
  function goStep(n){
    root.dataset.step=n;
    document.querySelectorAll('.builder-step').forEach(s=>s.classList.toggle('hidden', s.dataset.step!=String(n)));
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

  /* ---- collect all question data ---- */
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
  /* ---- 5-second autosave of dirty questions ---- */
  async function autosave(){
    if(!examId) return;
    if(metaDirty){ await saveMeta(); }
    if(dirty.size===0) return;
    const all=collectQuestions();
    const payload=all.filter(q=>dirty.has(q.id));
    if(!payload.length){ return; }
    setStatus('saving','در حال ذخیره…');
    try{
      await api(API,{method:'POST',body:{action:'autosave',exam_id:examId,questions:payload}});
      dirty.clear(); setStatus('saved','همه‌چیز ذخیره شد · '+faNum(new Date().toLocaleTimeString('fa-IR',{hour:'2-digit',minute:'2-digit',second:'2-digit'})));
    }catch(e){ setStatus('saved','ذخیره ناموفق — دوباره تلاش می‌شود'); }
  }
  setInterval(autosave, 5000);
  window.addEventListener('beforeunload',(e)=>{ if(dirty.size>0||metaDirty){ e.preventDefault(); e.returnValue=''; } });

  /* ---- mark dirty on any question edit ---- */
  root.addEventListener('input',(e)=>{
    const card=e.target.closest('.q-card');
    if(card){ dirty.add(parseInt(card.dataset.question)); setStatus('saving','تغییرات ذخیره نشده…');
      if(e.target.matches('.q-text')) grow(e.target);
    }
  });

  /* ---- correct option toggle ---- */
  root.addEventListener('change',(e)=>{
    if(e.target.matches('[data-correct]')){
      const card=e.target.closest('.q-card');
      card.querySelectorAll('.q-opt').forEach(o=>o.classList.remove('correct'));
      e.target.closest('.q-opt').classList.add('correct');
      dirty.add(parseInt(card.dataset.question));
    }
  });

  /* ---- add section (both buttons) ---- */
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

  /* ---- section name/duration save ---- */
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

  /* ---- delete section ---- */
  root.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-del-section]'); if(!b) return;
    const sec=b.closest('.exam-section');
    if(!confirm('این درس و همه‌ی سوالاتش حذف شود؟')) return;
    try{ await api(API,{method:'POST',body:{action:'delete_section',id:sec.dataset.section}});
      sec.remove(); updateCounts();
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  /* ---- add question ---- */
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

  /* ---- delete question ---- */
  root.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-del-question]'); if(!b) return;
    const card=b.closest('.q-card'); const sec=card.closest('.exam-section');
    if(!confirm('این سوال حذف شود؟')) return;
    try{ await api(API,{method:'POST',body:{action:'delete_question',id:card.dataset.question}});
      dirty.delete(parseInt(card.dataset.question));
      card.remove(); renumber(sec); updateCounts();
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  /* ---- Enter in last option => add new question (fast entry) ---- */
  root.addEventListener('keydown',(e)=>{
    if(e.key==='Enter' && e.target.matches('[data-opt-text="4"]')){
      e.preventDefault();
      const sec=e.target.closest('.exam-section');
      sec.querySelector('[data-add-question]').click();
    }
  });

  /* ---- image upload ---- */
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

  /* ---- publish ---- */
  document.getElementById('publishBtn').addEventListener('click',async function(){
    if(dirty.size||metaDirty) await autosave();
    const cur=this.dataset.status;
    const next=cur==='published'?'draft':'published';
    try{
      const d=await api(API,{method:'POST',body:{action:'set_status',exam_id:examId,status:next}});
      this.dataset.status=d.status;
      if(d.status==='published'){ this.innerHTML='پیش‌نویس کن'; toast('آزمون منتشر شد و به دانش‌آموزان اطلاع داده شد ✅','success'); }
      else { this.innerHTML='انتشار آزمون'; toast('آزمون به پیش‌نویس بازگشت','info'); }
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  /* ---- helpers: counts/renumber ---- */
  function updateCounts(){
    document.getElementById('totalSec').textContent=faNum(document.querySelectorAll('.exam-section').length);
    document.getElementById('totalQ').textContent=faNum(document.querySelectorAll('.q-card').length);
    document.querySelectorAll('.exam-section').forEach(sec=>{
      const c=sec.querySelectorAll('.q-card').length;
      sec.querySelector('.sec-count').textContent=faNum(c)+' سوال';
    });
  }
  function renumber(sec){ sec.querySelectorAll('.q-card .q-num').forEach((n,i)=>n.textContent=faNum(i+1)); }

  /* ---- HTML templates (mirror of PHP) ---- */
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
