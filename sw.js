/**
 * Service Worker — VatanParvar Yaypan PWA
 *
 * Strategiyalar:
 *   - Static assets (images, fonts): Cache First
 *   - HTML sahifalar: Network First (fallback to cache)
 *   - API/POST: Network Only (cache qilmaymiz)
 */
const CACHE_VERSION = 'vp-v6';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const PAGES_CACHE   = `${CACHE_VERSION}-pages`;

const STATIC_ASSETS = [
  '/',
  '/assets/images/logo.svg',
  '/assets/images/banner.svg',
  '/assets/images/icon-512.svg',
  '/manifest.json',
];

// Install
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
      .catch(err => console.warn('SW install error', err))
  );
});

// Activate — eski cache'larni o'chirish
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => !k.startsWith(CACHE_VERSION)).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Fetch
self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);

  // Faqat GET so'rovlar
  if (req.method !== 'GET') return;
  // Tashqi domenlar — passthrough
  if (url.origin !== location.origin) return;
  // Admin/User panel — har doim freshhh
  if (url.pathname.startsWith('/admin/') || url.pathname.startsWith('/api/') ||
      url.pathname.startsWith('/telegram/')) return;
  // Test sahifasi — cache qilmaymiz
  if (url.pathname.startsWith('/user/test.php')) return;

  // Static assets — Cache First
  if (/\.(?:png|jpg|jpeg|webp|svg|gif|ico|woff2?|ttf|css|js)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then(cached => cached || fetch(req).then(res => {
        if (res && res.ok) {
          const clone = res.clone();
          caches.open(STATIC_CACHE).then(c => c.put(req, clone));
        }
        return res;
      }))
    );
    return;
  }

  // HTML sahifalar — Network First, fallback to cache
  if (req.headers.get('accept')?.includes('text/html')) {
    e.respondWith(
      fetch(req).then(res => {
        if (res && res.ok) {
          const clone = res.clone();
          caches.open(PAGES_CACHE).then(c => c.put(req, clone));
        }
        return res;
      }).catch(() => caches.match(req).then(cached => cached || caches.match('/')))
    );
  }
});

// Push notification (kelajak uchun tayyor)
self.addEventListener('push', e => {
  const data = e.data ? e.data.json() : {title: 'VP Yaypan', body: 'Yangi xabar'};
  e.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/assets/images/logo.svg',
      badge: '/assets/images/logo.svg',
      data: data.url || '/',
    })
  );
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(clients.openWindow(e.notification.data || '/'));
});
