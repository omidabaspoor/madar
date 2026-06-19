/* =================================================================
   مَدار · core JS — toast, reveal, modal, api, helpers
   ================================================================= */
(() => {
  'use strict';

  /* ---------- CSRF ---------- */
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
  window.MADAR = { csrf: CSRF };

  /* ---------- Persian digits ---------- */
  const FA = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  window.faNum = (n) => String(n).replace(/\d/g, d => FA[d]);

  /* ---------- Toast ---------- */
  const ICONS = {
    success: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    error:   '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
    info:    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 16v-5M12 8h.01"/></svg>'
  };
  window.toast = (msg, type = 'success', ms = 3200) => {
    const wrap = document.getElementById('toast-wrap');
    if (!wrap) return;
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span class="t-ico">${ICONS[type] || ICONS.info}</span><span>${msg}</span>`;
    wrap.appendChild(el);
    setTimeout(() => {
      el.style.transition = '.3s'; el.style.opacity = '0'; el.style.transform = 'translateY(10px)';
      setTimeout(() => el.remove(), 300);
    }, ms);
  };

  /* ---------- Reveal on scroll ---------- */
  const revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length) {
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
      }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
      revealEls.forEach(el => io.observe(el));
      // ایمنی: اگر تا ۱.۵ ثانیه چیزی نمایان نشد، همه را نمایش بده (جلوگیری از محتوای نامرئی)
      setTimeout(() => { revealEls.forEach(el => el.classList.add('in')); }, 1500);
    } else {
      revealEls.forEach(el => el.classList.add('in'));
    }
  }

  /* ---------- Sticky nav ---------- */
  const nav = document.querySelector('.site-nav');
  if (nav) {
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 20);
    onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
  }
  const navToggle = document.querySelector('.nav-toggle');
  const navLinks = document.querySelector('.nav-links');
  navToggle?.addEventListener('click', () => navLinks.classList.toggle('open'));
  navLinks?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => navLinks.classList.remove('open')));

  /* ---------- Animated counters ---------- */
  const counters = document.querySelectorAll('[data-count]');
  if (counters.length) {
    const cio = new IntersectionObserver((entries) => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        const el = en.target, target = parseFloat(el.dataset.count), suf = el.dataset.suffix || '';
        const pre = el.dataset.prefix || '';
        let cur = 0; const step = target / 45;
        const tick = () => {
          cur += step;
          if (cur >= target) { el.textContent = pre + faNum(target) + suf; }
          else { el.textContent = pre + faNum(Math.floor(cur)) + suf; requestAnimationFrame(tick); }
        };
        tick(); cio.unobserve(el);
      });
    }, { threshold: 0.5 });
    counters.forEach(c => cio.observe(c));
  }

  /* ---------- Modal ---------- */
  window.openModal = (id) => {
    const m = document.getElementById(id);
    if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
  };
  window.closeModal = (el) => {
    const m = el?.closest ? el.closest('.modal-backdrop') : document.getElementById(el);
    if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
  };
  document.addEventListener('click', (e) => {
    if (e.target.classList?.contains('modal-backdrop')) closeModal(e.target);
    const opener = e.target.closest('[data-modal]');
    if (opener) { e.preventDefault(); openModal(opener.dataset.modal); }
    const closer = e.target.closest('[data-close]');
    if (closer) { e.preventDefault(); closeModal(closer); }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.open').forEach(m => closeModal(m));
  });

  /* ---------- API helper ---------- */
  window.api = async (url, { method = 'GET', body = null, json = true } = {}) => {
    const opts = { method, headers: { 'X-CSRF-Token': CSRF } };
    if (body) {
      if (body instanceof FormData) { opts.body = body; }
      else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    }
    const res = await fetch(url, opts);
    let data = null;
    try { data = await res.json(); } catch (_) {}
    if (!res.ok) throw (data || { ok: false, error: 'خطای ارتباط با سرور (' + res.status + ')' });
    return data;
  };

  /* ---------- password toggle ---------- */
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-toggle-pass]');
    if (!t) return;
    const input = document.getElementById(t.dataset.togglePass);
    if (input) {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      t.classList.toggle('showing', show);
    }
  });

  /* ---------- form loading state ---------- */
  document.querySelectorAll('form[data-loading]').forEach(f => {
    f.addEventListener('submit', () => {
      const btn = f.querySelector('button[type="submit"]');
      if (btn) { btn.disabled = true; btn.dataset.label = btn.innerHTML; btn.innerHTML = '<span class="spinner"></span>'; }
    });
  });

  /* ---------- register SW ---------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(document.querySelector('meta[name="sw-url"]')?.content || './sw.js').catch(() => {});
    });
  }

  /* ---------- online/offline indicator ---------- */
  const updateNet = () => {
    if (!navigator.onLine) toast('اتصال اینترنت قطع شد — حالت آفلاین', 'info', 2600);
  };
  window.addEventListener('offline', updateNet);
  window.addEventListener('online', () => toast('دوباره آنلاین شدید', 'success', 2000));
})();

/* ---------- PWA local notifications (review reminders) ---------- */
(() => {
  const canNotify = () => 'Notification' in window && 'serviceWorker' in navigator;
  async function showFromSiteNotification(n){
    if (!canNotify() || Notification.permission !== 'granted') return;
    const key='madar_notif_shown_'+n.id;
    if (localStorage.getItem(key)) return;
    localStorage.setItem(key,'1');
    const reg = await navigator.serviceWorker.ready.catch(()=>null);
    const title = n.title || 'یادآوری مَدار';
    const opts = { body:n.body||'', icon:(window.MADAR_ICON||'/assets/icons/icon-192.png'), badge:(window.MADAR_BADGE||'/assets/icons/favicon-64.png'), data:{url:n.link||'/student/dashboard.php'}, dir:'rtl', lang:'fa' };
    if (reg?.showNotification) reg.showNotification(title, opts); else new Notification(title, opts);
  }
  window.requestMadarNotifications = async () => {
    if (!canNotify()) { toast('مرورگر این دستگاه اعلان وب‌اپ را پشتیبانی نمی‌کند','error'); return false; }
    const perm = await Notification.requestPermission();
    if (perm === 'granted') { toast('اعلان‌های وب‌اپ فعال شد 🔔','success'); document.querySelectorAll('[data-notif-enable]').forEach(b=>b.closest('.notif-permission')?.remove()); return true; }
    toast('برای دریافت یادآوری مرور، باید اجازه اعلان را فعال کنی','info'); return false;
  };
  document.addEventListener('click', e=>{ const b=e.target.closest('[data-notif-enable]'); if(b) window.requestMadarNotifications(); });
  window.addEventListener('load', async ()=>{
    if (!canNotify() || Notification.permission !== 'granted' || !window.NOTIF_URL) return;
    try { const r=await fetch(window.NOTIF_URL); const d=await r.json(); (d.items||[]).filter(n=>!Number(n.is_read) && (n.type==='review'||n.type==='calendar')).slice(0,4).forEach(showFromSiteNotification); } catch(_) {}
  });
})();

/* ---------- PWA install prompt ---------- */
(() => {
  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault(); deferredPrompt = e;
    document.querySelectorAll('[data-pwa-install]').forEach(b=>{ b.disabled=false; b.classList.add('ready'); });
  });
  window.installMadarPWA = async () => {
    if (!deferredPrompt) { toast('اگر دکمه نصب نمایش داده نشد، از منوی مرورگر گزینه نصب یا Add to Home Screen را بزن','info',4200); return false; }
    deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice.catch(()=>null);
    deferredPrompt = null;
    if (choice?.outcome === 'accepted') toast('وب‌اپ مَدار در حال نصب است','success');
    return choice?.outcome === 'accepted';
  };
  document.addEventListener('click', e=>{ if(e.target.closest('[data-pwa-install]')) window.installMadarPWA(); });
})();
