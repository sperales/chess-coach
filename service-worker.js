const CACHE_NAME = 'chess-coach-v0.9.3';
const ASSETS = [
  './',
  './app.php',
  './games.php',
  './import-chesscom.php',
  './analysis-pending.php',
  './review.php',
  './profile.php',
  './assets/css/app.css',
  './assets/js/app.js',
  './assets/js/games.js',
  './assets/js/layout.js',
  './assets/js/chesscom.js',
  './assets/js/analysis_queue.js',
  './assets/js/review.js',
  './assets/icons/favicon.ico',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
  './assets/icons/logo-approved.png',
  './manifest.webmanifest'
];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS).catch(() => null)));
  self.skipWaiting();
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;
  event.respondWith(fetch(req).catch(() => caches.match(req)));
});
