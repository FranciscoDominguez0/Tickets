const CACHE = 'tickets-v4';
const OFFLINE = 'publico/pwa-offline.html';

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.add(OFFLINE))
  );
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k !== CACHE && k.startsWith('tickets-'))
        .map(k => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

// ── Web Push Event: Despierta el celular cuando llega una notificación push
self.addEventListener('push', e => {
  let data = {
    title: 'Sistema de Tickets',
    body: 'Tienes una nueva notificación.',
    icon: 'publico/img/pwa/icon-192.png',
    badge: 'publico/img/pwa/icon-192.png',
    url: 'scp/tickets.php'
  };

  if (e.data) {
    try {
      const parsed = e.data.json();
      data = Object.assign(data, parsed);
    } catch (err) {
      data.body = e.data.text() || data.body;
    }
  }

  const options = {
    body: data.body,
    icon: data.icon,
    badge: data.badge,
    vibrate: [200, 100, 200],
    tag: 'ticket-notif-' + (data.related_id || Date.now()),
    renotify: true,
    data: {
      url: data.url
    }
  };

  e.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// ── Notification Click: Al pulsar la notificación en el celular, enfoca o abre la app
self.addEventListener('notificationclick', e => {
  e.notification.close();

  const urlToOpen = (e.notification.data && e.notification.data.url)
    ? e.notification.data.url
    : 'scp/tickets.php';

  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      // Si ya hay una pestaña o PWA abierta, enfocarla y navegar
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if ('focus' in client) {
          return client.focus().then(() => {
            if ('navigate' in client && urlToOpen) {
              return client.navigate(urlToOpen);
            }
          });
        }
      }
      // Si está cerrada, abrir una nueva ventana con la URL
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  const sameOrigin = url.origin === location.origin;

  // IMPORTANTE: No interceptar peticiones POST (como el login).
  // Interceptar POST en el Service Worker causa que Chrome aborte la petición.
  if (!sameOrigin || e.request.method !== 'GET') return;

  const isNav = e.request.mode === 'navigate';
  const isStatic = /\.(css|js|woff2?|png|jpg|jpeg|gif|svg|ico|webp)(\?.*)?$/i.test(url.pathname);

  if (isStatic) {
    e.respondWith(cacheFirst(e.request));
  } else if (isNav) {
    e.respondWith(networkFirst(e.request));
  }
});

async function cacheFirst(req) {
  const cached = await caches.match(req);
  if (cached) return cached;

  try {
    const net = await fetch(req);
    if (net.ok) {
      const cache = await caches.open(CACHE);
      cache.put(req, net.clone());
    }
    return net;
  } catch {
    return new Response('', { status: 408 });
  }
}

async function networkFirst(req) {
  try {
    const net = await fetch(req);
    return net;
  } catch {
    const cached = await caches.match(OFFLINE);
    if (cached) return cached;
    return new Response(
      '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sin conexión</title><style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:24px;text-align:center}h1{font-size:20px;font-weight:700;margin-bottom:8px}p{color:#94a3b8}</style></head><body><div><h1>Sin conexión</h1><p>No tienes acceso a internet.</p></div></body></html>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
  }
}

