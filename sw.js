const CACHE_NAME = 'tamcc-deli-v1';
const urlsToCache = [
  '/',
  '/index.php',
  '/menu.php',
  '/assets/css/global.css',
  '/assets/js/script.js',
  'https://cdn.jsdelivr.net/gh/WordPress/WordPress@master/wp-includes/css/dashicons.min.css'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
});