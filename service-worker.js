/* --- PWA: service-worker.js --- */
const CACHE_NAME = 'smm-panel-cache-v1';
const urlsToCache = [
    '/',
    '/index.php',
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/manifest.json',
    '/assets/img/icon-192.png'
];

// Instalar el Service Worker y almacenar recursos
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache);
            })
    );
});

// Lógica para interceptar peticiones (necesario para las notificaciones)
self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                return response || fetch(event.request);
            })
    );
});

// *** Lógica para Notificaciones Push ***
self.addEventListener('push', function(event) {
    const data = event.data.json();
    const title = data.title || 'Notificación SMM Panel';
    const options = {
        body: data.body || 'Tenemos una actualización para ti.',
        icon: 'assets/img/icon-192.png',
        badge: 'assets/img/icon-192.png'
    };
    event.waitUntil(self.registration.showNotification(title, options));
});
