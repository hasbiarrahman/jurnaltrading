const CACHE_NAME = 'pelagic-cache-v1.4';
const ASSETS = [
  '/css/dashboard.css',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png'
];

// Install Event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('Caching PWA static assets...');
      return cache.addAll(ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            console.log('Clearing old cache:', key);
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Interceptor
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // ONLY intercept requests for our predefined static assets.
  // All other requests (HTML pages, APIs, logins, logs, dynamic views)
  // are completely ignored by the Service Worker so the browser handles them natively.
  // This prevents any session redirection conflicts, expired CSRF tokens, and ERR_FAILED bugs.
  if (ASSETS.includes(url.pathname)) {
    event.respondWith(
      caches.match(event.request).then(cachedResponse => {
        return cachedResponse || fetch(event.request);
      })
    );
  }
});
