const CACHE_NAME = 'tamcc-deli-v1';
const urlsToCache = [
  '/tamccdeli/',
  '/tamccdeli/index.php',
  '/tamccdeli/menu.php',
  '/tamccdeli/assets/css/global.css',
  '/tamccdeli/assets/js/script.js',
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