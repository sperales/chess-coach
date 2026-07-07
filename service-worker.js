const CACHE_NAME = 'chess-coach-v1.1.0';
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
  './assets/js/dashboard.js',
  './assets/js/games.js',
  './assets/js/layout.js',
  './assets/js/chesscom.js',
  './assets/js/analysis_queue.js',
  './assets/js/review.js',
  './assets/pieces/bb.png',
  './assets/pieces/bk.png',
  './assets/pieces/bn.png',
  './assets/pieces/bp.png',
  './assets/pieces/bq.png',
  './assets/pieces/br.png',
  './assets/pieces/wb.png',
  './assets/pieces/wk.png',
  './assets/pieces/wn.png',
  './assets/pieces/wp.png',
  './assets/pieces/wq.png',
  './assets/pieces/wr.png',
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
