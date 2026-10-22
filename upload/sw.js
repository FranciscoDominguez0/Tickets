const CACHE = 'tickets-v6';
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

  let targetUrl = '/upload/scp/index.php';
  if (e.notification.data && e.notification.data.url) {
    targetUrl = e.notification.data.url;
  }

  // Asegurar que siempre sea una URL absoluta (obligatorio por especificación W3C para clients.openWindow)
  try {
    targetUrl = new URL(targetUrl, self.location.origin).href;
  } catch (err) {
    targetUrl = self.location.origin + '/upload/scp/index.php';
  }

  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async clientList => {
      // 1. Si ya hay una pestaña con la URL exacta, enfocarla
      for (const client of clientList) {
        if (client.url === targetUrl && 'focus' in client) {
          return client.focus();
        }
      }

      // 2. Si hay una pestaña abierta de la misma app/origen, enfocarla y navegarla al ticket
      for (const client of clientList) {
        if ('focus' in client && client.url.startsWith(self.location.origin)) {
          await client.focus();
          if ('navigate' in client) {
            return client.navigate(targetUrl);
          }
          break;
        }
      }

      // 3. Si la app o navegador estaban cerrados, abrir ventana con la URL del ticket
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
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

