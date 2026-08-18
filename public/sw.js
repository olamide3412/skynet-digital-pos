const CACHE_NAME = 'skynet-pos-shell-v1';
const STATIC_ASSETS = [
    '/',
    '/favicon.ico',
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('Service worker cache.addAll non-critical warning:', err);
            });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // Only handle GET requests for app shell & static assets
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    // Skip caching for backend API and sync endpoints (IndexedDB handles offline data)
    if (url.pathname.includes('/api/')) return;

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // If response is valid, clone and update cache for static assets
                if (response && response.status === 200 && response.type === 'basic') {
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache).catch(() => {});
                    });
                }
                return response;
            })
            .catch(() => {
                // Network failed — fallback to cached shell
                return caches.match(event.request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    if (event.request.mode === 'navigate') {
                        return caches.match('/');
                    }
                });
            })
    );
});
