import { precacheAndRoute, cleanupOutdatedCaches, createHandlerBoundToURL } from 'workbox-precaching';
import { registerRoute, NavigationRoute, setCatchHandler } from 'workbox-routing';
import { CacheFirst, NetworkFirst, NetworkOnly, StaleWhileRevalidate } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

/*
 * Cố ý KHÔNG gọi skipWaiting()/clientsClaim().
 *
 * Nếu SW mới giành quyền giữa phiên, cleanupOutdatedCaches() sẽ xoá precache
 * của bản cũ trong khi các document đang mở vẫn trỏ tới tên file hash cũ →
 * CSS 404 → trang mất style rồi nhảy lại. Đợi tất cả tab cũ đóng hẳn rồi mới
 * kích hoạt bản mới là cách an toàn, và người dùng vẫn nhận được bản mới ở
 * lần tải trang kế tiếp.
 */
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) => Promise.all(
            names
                .filter((name) => name === 'awawa-documents' || name === 'awawa-documents-v2')
                .map((name) => caches.delete(name)),
        )),
    );
});

registerRoute(
    new NavigationRoute(new NetworkFirst({
        cacheName: 'awawa-pages',
        networkTimeoutSeconds: 2,
        plugins: [new ExpirationPlugin({ maxEntries: 30, maxAgeSeconds: 24 * 60 * 60 })],
    }), {
        denylist: [/^\/login/, /^\/register/, /^\/auth\//, /^\/api\//, /^\/build\//],
    }),
);

registerRoute(
    ({ request }) => ['style', 'script', 'worker'].includes(request.destination),
    new StaleWhileRevalidate({
        cacheName: 'awawa-assets',
        plugins: [new ExpirationPlugin({ maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 30 })],
    }),
);

registerRoute(
    ({ request }) => request.destination === 'image',
    new CacheFirst({
        cacheName: 'awawa-images',
        plugins: [new ExpirationPlugin({ maxEntries: 120, maxAgeSeconds: 60 * 60 * 24 * 30 })],
    }),
);

registerRoute(
    ({ url }) => url.pathname.includes('/tai-lieu/') && url.pathname.endsWith('/file'),
    // Tài liệu qua Gate theo user: KHÔNG cache để tránh HS B đọc cache private của HS A
    // trên cùng máy, và tránh giữ bản cũ sau khi đổi is_public / xóa file.
    new NetworkOnly(),
);

let offlineHandler = null;

try {
    offlineHandler = createHandlerBoundToURL('/offline.html');
} catch (error) {
    offlineHandler = null;
}

setCatchHandler(async (options) => {
    if (options.request.destination === 'document' && offlineHandler !== null) {
        return offlineHandler(options);
    }

    return Response.error();
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = {};
    }

    const title = data.title || 'awawa';
    const options = {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: data.tag || undefined,
        data: { url: data.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data?.url || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(target);

                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }

            return undefined;
        }),
    );
});
