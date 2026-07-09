const CACHE_NAME = 'pelagic-cache-v1.5';

// Install Event
self.addEventListener('install', event => {
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          console.log('Purging old cache:', key);
          return caches.delete(key);
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Interceptor (Empty listener to satisfy PWA requirements while bypassing caching entirely)
self.addEventListener('fetch', event => {
  // Bypassed: Let the browser handle all network requests natively.
  // This completely eliminates any redirect, session, and ERR_FAILED bugs.
});
