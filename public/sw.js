// Service worker Sedapur: cache aset statis hasil build Vite dan halaman offline.
// Halaman yang berisi data user, /sesi/token, /api/*, dan request non-GET tidak pernah di-cache.

const CACHE_NAME = "sedapur-static-v1";
const OFFLINE_URL = "/offline.html";
const PRECACHE_URLS = [
    OFFLINE_URL,
    "/manifest.webmanifest",
    "/icon.svg",
    "/icons/icon-192.png",
    "/icons/icon-512.png",
    "/icons/icon-maskable-512.png",
    "/apple-touch-icon.png",
];
const PRECACHE_SET = new Set(PRECACHE_URLS);

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key.startsWith("sedapur-static-") && key !== CACHE_NAME)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener("fetch", (event) => {
    const { request } = event;
    if (request.method !== "GET") return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Halaman HTML selalu langsung ke jaringan; bila offline tampilkan halaman offline statis.
    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match(OFFLINE_URL).then((cached) => cached ?? Response.error()),
            ),
        );
        return;
    }

    // Aset hasil build Vite (/build/assets/*) memakai hash di nama file: aman cache-first.
    if (url.pathname.startsWith("/build/assets/")) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ??
                    fetch(request).then((response) => {
                        if (response.ok && response.type === "basic") {
                            const copy = response.clone();
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
        return;
    }

    // Ikon, manifest, dan halaman offline: sajikan dari cache sambil perbarui di latar belakang.
    if (PRECACHE_SET.has(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request)
                    .then((response) => {
                        if (response.ok && response.type === "basic") {
                            const copy = response.clone();
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    })
                    .catch(() => cached);

                return cached ?? network;
            }),
        );
    }
});
