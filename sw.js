/* مَدار Service Worker — network-first برای CSS/JS و صفحات، cache-first برای عکس/فونت */
const VERSION = 'madar-v4';
const STATIC_CACHE = 'static-' + VERSION;
const PAGE_CACHE = 'pages-' + VERSION;

// مسیر پایه را از محل ثبت SW استخراج کن
const SCOPE = self.registration.scope.replace(/\/$/, '');
const OFFLINE_URL = SCOPE + '/offline.php';

const STATIC_ASSETS = [
  SCOPE + '/assets/css/app.css',
  SCOPE + '/assets/js/app.js',
  SCOPE + '/assets/img/logo.png',
  OFFLINE_URL,
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(STATIC_CACHE).then(c => c.addAll(STATIC_ASSETS).catch(()=>{})).then(()=>self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => !k.endsWith(VERSION)).map(k => caches.delete(k))
    )).then(()=>self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // درخواست‌های API: همیشه شبکه
  if (url.pathname.includes('/api/')) {
    e.respondWith(fetch(req).catch(()=>new Response(JSON.stringify({ok:false,error:'آفلاین'}),{headers:{'Content-Type':'application/json'}})));
    return;
  }

  // PDFهای بزرگ دفترچه و Range requestهای مرورگر PDF Viewer نباید وارد Cache API شوند.
  if (req.headers.has('range') || /\.pdf$/i.test(url.pathname)) {
    e.respondWith(fetch(req));
    return;
  }

  // CSS/JS: network-first (همیشه تازه وقتی آنلاین، کش وقتی آفلاین)
  if (/\.(css|js)$/.test(url.pathname)) {
    e.respondWith(
      fetch(req).then(res => {
        const copy = res.clone();
        caches.open(STATIC_CACHE).then(c => c.put(req, copy));
        return res;
      }).catch(() => caches.match(req))
    );
    return;
  }
  // عکس/فونت: cache-first (تغییر نمی‌کنند، سریع)
  if (/\.(svg|png|jpg|jpeg|webp|gif|woff2?|ttf)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then(cached => cached || fetch(req).then(res => {
        const copy = res.clone();
        caches.open(STATIC_CACHE).then(c => c.put(req, copy));
        return res;
      }).catch(()=>cached))
    );
    return;
  }

  // صفحات HTML: network-first با fallback آفلاین
  e.respondWith(
    fetch(req).then(res => {
      const copy = res.clone();
      caches.open(PAGE_CACHE).then(c => c.put(req, copy));
      return res;
    }).catch(() => caches.match(req).then(cached => cached || caches.match(OFFLINE_URL)))
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = event.notification?.data?.url || SCOPE + '/student/dashboard.php';
  const url = target.startsWith('http') ? target : SCOPE + '/' + String(target).replace(/^\//,'');
  event.waitUntil(clients.matchAll({type:'window', includeUncontrolled:true}).then(list=>{
    for (const c of list) { if ('focus' in c) return c.focus().then(()=>c.navigate ? c.navigate(url) : c); }
    return clients.openWindow(url);
  }));
});
