/**
 * POS Service Worker — Phase 7 skeleton.
 * IndexedDB offline queue infrastructure is established here.
 * Full fetch interception and sync/conflict resolution are deferred to Phase 16.
 *
 * NOTE: Fetch interception is intentionally disabled. Re-issuing requests from
 * the service worker context strips session cookies in some browsers, causing
 * CSRF token mismatches on the Laravel web-session routes. Phase 16 will
 * implement proper offline queuing using the Background Sync API instead.
 */

const OFFLINE_QUEUE_DB = 'pos_offline_queue';
const OFFLINE_QUEUE_STORE = 'offline_sales';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

async function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(OFFLINE_QUEUE_DB, 1);
        req.onupgradeneeded = (e) => {
            e.target.result.createObjectStore(OFFLINE_QUEUE_STORE, {
                keyPath: 'id',
                autoIncrement: true,
            });
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = () => reject(req.error);
    });
}

async function queueOfflineAction(action) {
    const db = await openDB();
    const tx = db.transaction(OFFLINE_QUEUE_STORE, 'readwrite');
    tx.objectStore(OFFLINE_QUEUE_STORE).add(action);
}
