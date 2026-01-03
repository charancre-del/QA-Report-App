/**
 * Chroma QA Reports - Service Worker
 *
 * Enables offline functionality and caching
 *
 * @package ChromaQAReports
 */

const CACHE_NAME = 'cqa-reports-v1';
const STATIC_CACHE = 'cqa-static-v1';
const DYNAMIC_CACHE = 'cqa-dynamic-v1';

// Static assets to cache on install
const STATIC_ASSETS = [
    '/wp-content/plugins/chroma-qa-reports/admin/css/admin-styles.css',
    '/wp-content/plugins/chroma-qa-reports/admin/js/admin-scripts.js',
    '/wp-content/plugins/chroma-qa-reports/admin/js/report-wizard.js',
    '/wp-content/plugins/chroma-qa-reports/admin/js/keyboard-nav.js',
    '/wp-content/plugins/chroma-qa-reports/admin/js/offline-manager.js',
    '/wp-content/plugins/chroma-qa-reports/assets/images/icon-192.png',
];

// Install event - cache static assets
self.addEventListener('install', event => {
    // console.log('[SW] Installing service worker...');

    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                // console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean old caches
self.addEventListener('activate', event => {
    // console.log('[SW] Activating service worker...');

    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
                    .map(key => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip external requests
    if (url.origin !== location.origin) {
        return;
    }

    // Handle API requests differently
    if (url.pathname.includes('/wp-json/cqa/')) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // Static assets - cache first
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(event.request));
        return;
    }

    // HTML pages - network first with cache fallback
    if (event.request.headers.get('accept').includes('text/html')) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // Default - stale while revalidate
    event.respondWith(staleWhileRevalidate(event.request));
});

// Check if request is for static asset
function isStaticAsset(pathname) {
    return /\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2)$/.test(pathname);
}

// Cache first strategy
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        return new Response('Offline', { status: 503 });
    }
}

// Network first strategy
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }

        // Return offline page for HTML requests
        if (request.headers.get('accept').includes('text/html')) {
            return caches.match('/wp-content/plugins/chroma-qa-reports/offline.html');
        }

        return new Response('Offline', { status: 503 });
    }
}

// Stale while revalidate strategy
async function staleWhileRevalidate(request) {
    const cached = await caches.match(request);

    const fetchPromise = fetch(request).then(response => {
        if (response.ok) {
            const cache = caches.open(DYNAMIC_CACHE);
            cache.then(c => c.put(request, response.clone()));
        }
        return response;
    }).catch(() => null);

    return cached || fetchPromise;
}

// Background sync for offline reports
self.addEventListener('sync', event => {
    // console.log('[SW] Background sync triggered:', event.tag);

    if (event.tag === 'sync-draft-reports') {
        event.waitUntil(syncDraftReports());
    }

    if (event.tag === 'sync-photos') {
        event.waitUntil(syncPhotos());
    }
});

// Sync draft reports
async function syncDraftReports() {
    try {
        const db = await openDB();
        const drafts = await db.getAll('drafts');

        for (const draft of drafts) {
            const response = await fetch('/wp-json/cqa/v1/reports/drafts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(draft)
            });

            if (response.ok) {
                await db.delete('drafts', draft.localId);
                // console.log('[SW] Synced draft:', draft.localId);
            }
        }

        // Notify clients
        self.clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({ type: 'SYNC_COMPLETE', success: true });
            });
        });
    } catch (error) {
        console.error('[SW] Sync failed:', error);
    }
}

// Sync photos
async function syncPhotos() {
    try {
        const db = await openDB();
        const photos = await db.getAll('pending-photos');

        for (const photo of photos) {
            const formData = new FormData();
            formData.append('file', photo.blob, photo.filename);
            formData.append('report_id', photo.reportId);
            formData.append('section_key', photo.sectionKey);
            formData.append('caption', photo.caption);

            const response = await fetch('/wp-json/cqa/v1/photos', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                await db.delete('pending-photos', photo.localId);
                // console.log('[SW] Synced photo:', photo.localId);
            }
        }
    } catch (error) {
        console.error('[SW] Photo sync failed:', error);
    }
}

// Open IndexedDB
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('cqa-offline', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            const db = request.result;
            resolve({
                getAll: (store) => {
                    return new Promise((res, rej) => {
                        const tx = db.transaction(store, 'readonly');
                        const data = [];
                        tx.objectStore(store).openCursor().onsuccess = e => {
                            const cursor = e.target.result;
                            if (cursor) {
                                data.push(cursor.value);
                                cursor.continue();
                            } else {
                                res(data);
                            }
                        };
                    });
                },
                delete: (store, key) => {
                    return new Promise((res, rej) => {
                        const tx = db.transaction(store, 'readwrite');
                        tx.objectStore(store).delete(key);
                        tx.oncomplete = () => res();
                    });
                }
            });
        };

        request.onupgradeneeded = e => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('drafts')) {
                db.createObjectStore('drafts', { keyPath: 'localId' });
            }
            if (!db.objectStoreNames.contains('pending-photos')) {
                db.createObjectStore('pending-photos', { keyPath: 'localId' });
            }
        };
    });
}

// Push notification handling
self.addEventListener('push', event => {
    const data = event.data ? event.data.json() : {};

    const options = {
        body: data.body || 'New notification',
        icon: '/wp-content/plugins/chroma-qa-reports/assets/images/icon-192.png',
        badge: '/wp-content/plugins/chroma-qa-reports/assets/images/badge-72.png',
        data: data.url || '/',
        actions: [
            { action: 'view', title: 'View' },
            { action: 'dismiss', title: 'Dismiss' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'QA Reports', options)
    );
});

// Notification click handling
self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'view' || !event.action) {
        event.waitUntil(
            clients.openWindow(event.notification.data)
        );
    }
});

// console.log('[SW] Service worker loaded');
