const CACHE_NAME = 'tamcc-deli-v2';
const urlsToCache = [
  '/',
  '/index.php',
  '/menu.php',
  '/cart.php',
  '/assets/css/global.css',
  '/assets/css/kiosk.css',
  '/assets/js/script.js',
  'https://cdn.jsdelivr.net/gh/WordPress/WordPress@master/wp-includes/css/dashicons.min.css',
  '/assets/images/ta-logo-1536x512.png',
  '/assets/images/About-Us.png',
  '/offline.html' // we'll create this page
];

// Install event – cache static assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
      .then(() => self.skipWaiting())
  );
});

// Activate event – clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME)
          .map(key => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event – network-first for HTML, cache-first for static assets
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);

  // Skip non-GET requests and requests to external domains (e.g., Stripe)
  if (request.method !== 'GET' || url.origin !== location.origin) {
    event.respondWith(fetch(request));
    return;
  }

  // Determine if the request is for an HTML page
  const isHtml = request.headers.get('accept').includes('text/html');

  if (isHtml) {
    // Network-first strategy for HTML pages
    event.respondWith(
      fetch(request)
        .then(response => {
          // Cache the fresh copy
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(request, responseClone);
          });
          return response;
        })
        .catch(() => {
          // Network failed – try cache, then fallback to offline page
          return caches.match(request).then(cached => {
            if (cached) return cached;
            // Fallback to a basic offline page
            return caches.match('/offline.html');
          });
        })
    );
  } else {
    // Cache-first for static assets (CSS, JS, images)
    event.respondWith(
      caches.match(request)
        .then(response => {
          if (response) return response;
          return fetch(request).then(networkResponse => {
            // Cache fresh copy for future use
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(request, responseClone);
            });
            return networkResponse;
          });
        })
    );
  }
});