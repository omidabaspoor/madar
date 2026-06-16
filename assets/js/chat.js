/* =============== Chat =============== */
(() => {
  'use strict';
  if (!window.API_MSG) return;
  const listEl = document.getElementById('chatList');
  const bodyEl = document.getElementById('chatBody');
  const form = document.getElementById('chatForm');
  const text = document.getElementById('chatText');
  let active = null, pollTimer = null;
  const esc = (s)=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const letters = (n)=>{const p=n.trim().split(/\s+/);return (p[0]?.[0]||'')+(p[1]?.[0]||'');};

  async function loadContacts() {
    try {
      const d = await api(window.API_MSG + '?action=contacts');
      if (!d.items.length) { listEl.innerHTML = '<div class="empty-state" style="padding:30px">مخاطبی نیست</div>'; return; }
      listEl.innerHTML = d.items.map(c => `
        <div class="chat-item ${active==c.id?'active':''}" data-id="${c.id}" data-name="${esc(c.full_name)}" data-sub="${esc(c.field||'')}">
          <span class="u-ava">${esc(letters(c.full_name))}</span>
          <div style="flex:1;min-width:0">
            <div class="nm">${esc(c.full_name)}</div>
            <div class="lm">${esc(c.last||'بدون پیام')}</div>
          </div>
          ${c.unread>0?`<span class="badge-count" style="background:var(--gold);color:#1a1206;font-weight:800;font-size:.7rem;padding:1px 7px;border-radius:999px">${faNum(c.unread)}</span>`:''}
        </div>`).join('');
      if (window.INIT_WITH && !active) openChat(window.INIT_WITH);
    } catch(e) { listEl.innerHTML = '<div class="empty-state" style="padding:30px">خطا</div>'; }
  }

  async function openChat(id) {
    const item = listEl.querySelector(`.chat-item[data-id="${id}"]`);
    active = parseInt(id);
    document.querySelectorAll('.chat-item').forEach(x=>x.classList.toggle('active', x.dataset.id==id));
    const name = item?.dataset.name || '';
    document.getElementById('chatName').textContent = name;
    document.getElementById('chatSub').textContent = item?.dataset.sub || '';
    document.getElementById('chatAva').textContent = letters(name);
    form.style.display = 'flex';
    await loadMessages(true);
    clearInterval(pollTimer);
    pollTimer = setInterval(()=>loadMessages(false), 5000);
  }

  async function loadMessages(scroll) {
    if (!active) return;
    try {
      const d = await api(`${window.API_MSG}?action=list&with=${active}`);
      const atBottom = bodyEl.scrollHeight - bodyEl.scrollTop - bodyEl.clientHeight < 60;
      if (!d.items.length) {
        bodyEl.innerHTML = '<div class="empty-state" style="margin:auto">هنوز پیامی رد و بدل نشده 🌿</div>';
        return;
      }
      bodyEl.innerHTML = d.items.map(m => `
        <div class="bubble ${m.mine?'me':'them'}">${esc(m.body).replace(/\n/g,'<br>')}<span class="time">${m.time}</span></div>`).join('');
      if (scroll || atBottom) bodyEl.scrollTop = bodyEl.scrollHeight;
    } catch(e) {}
  }

  listEl.addEventListener('click', e=>{ const it=e.target.closest('.chat-item'); if(it) openChat(it.dataset.id); });

  form.addEventListener('submit', async e=>{
    e.preventDefault();
    const body = text.value.trim();
    if (!body || !active) return;
    text.value='';
    bodyEl.insertAdjacentHTML('beforeend', `<div class="bubble me">${esc(body).replace(/\n/g,'<br>')}<span class="time">${faNum(new Date().toLocaleTimeString('fa-IR',{hour:'2-digit',minute:'2-digit'}))}</span></div>`);
    bodyEl.scrollTop = bodyEl.scrollHeight;
    try {
      const fd = new FormData(); fd.append('action','send'); fd.append('with',active); fd.append('body',body);
      await api(window.API_MSG, { method:'POST', body: fd });
    } catch(err){ toast(err.error||'خطا در ارسال','error'); }
  });

  loadContacts();
  setInterval(loadContacts, 15000);
})();
