const CACHE_NAME = 'pelagic-cache-v1.3';
const ASSETS = [
  '/login',
  '/css/dashboard.css',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png'
];

// Install Event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('Caching primary PWA assets...');
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

  // Exclude non-GET requests, APIs, and auth endpoints from Service Worker interception.
  // By NOT calling event.respondWith(), the browser handles these natively.
  // This is critical for POST requests (like logins) to allow redirects to function without ERR_FAILED.
  if (
    event.request.method !== 'GET' || 
    url.pathname.includes('/api/') || 
    url.pathname.includes('/login') ||
    url.pathname.includes('/logout')
  ) {
    return;
  }

  // Cache-first for core static assets
  if (ASSETS.includes(url.pathname)) {
    event.respondWith(
      caches.match(event.request).then(cachedResponse => {
        return cachedResponse || fetch(event.request);
      })
    );
  } else {
    // Network-first for dynamic dashboard pages and views
    event.respondWith(
      fetch(event.request)
        .then(networkResponse => {
          // If response is valid, clone it and cache it for offline support
          if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return networkResponse;
        })
        .catch(() => {
          // Offline fallback
          return caches.match(event.request).then(cachedResponse => {
            return cachedResponse || caches.match('/login');
          });
        })
    );
  }
});
