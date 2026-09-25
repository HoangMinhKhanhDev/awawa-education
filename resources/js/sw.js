import { precacheAndRoute, cleanupOutdatedCaches, createHandlerBoundToURL } from 'workbox-precaching';
import { registerRoute, NavigationRoute, setCatchHandler } from 'workbox-routing';
import { CacheFirst, NetworkFirst, StaleWhileRevalidate } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';
import { clientsClaim } from 'workbox-core';

self.skipWaiting();
clientsClaim();

precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

registerRoute(
    new NavigationRoute(new NetworkFirst({ cacheName: 'awawa-pages', networkTimeoutSeconds: 4 }), {
        denylist: [/^\/login/, /^\/register/, /^\/auth\//, /^\/api\//, /^\/build\//],
    }),
);

registerRoute(
    ({ request }) => ['style', 'script', 'worker'].includes(request.destination),
    new StaleWhileRevalidate({
        cacheName: 'awawa-assets',
        plugins: [new ExpirationPlugin({ maxEntries: 80, maxAgeSeconds: 60 * 60 * 24 * 30 })],
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
    ({ url }) => url.pathname.startsWith('/storage/'),
    new CacheFirst({
        cacheName: 'awawa-documents',
        plugins: [new ExpirationPlugin({ maxEntries: 80, maxAgeSeconds: 60 * 60 * 24 * 14 })],
    }),
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
