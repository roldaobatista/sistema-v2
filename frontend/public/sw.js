/**
 * Kalibrium — Service Worker (PWA Offline)
 *
 * Estratégias:
 * - Shell (HTML/CSS/JS): Cache-first, atualiza em background
 * - API Reads (GET): Network-only para dados autenticados
 * - API Writes (POST/PUT/DELETE): Queue offline, sync quando online
 * - Fotos/Uploads: IndexedDB queue, upload em background
 */

const CACHE_NAME = 'kalibrium-v4';
const AUTHENTICATED_API_CACHE_DISABLED = true;

const SHELL_URLS = [
  '/',
  '/index.html',
  '/manifest.json',
  '/offline.html',
];

// ─── INSTALL ──────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(SHELL_URLS).catch(() => undefined);
    })
  );
  self.skipWaiting();
});

// ─── ACTIVATE ─────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    Promise.all([
      caches.keys().then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== CACHE_NAME || key.startsWith('kalibrium-api-'))
            .map((key) => caches.delete(key))
        )
      ),
      self.registration.navigationPreload?.enable() ?? Promise.resolve(),
      clearApiCaches(),
    ])
  );
  self.clients.claim();
});

// ─── FETCH ────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Ignore non-GET requests for caching (writes go to sync queue)
  if (event.request.method !== 'GET') {
    if (isApiRequest(url)) {
      // Tenta enviar normalmente; se falhar (offline), adiciona à fila
      event.respondWith(
        fetch(event.request.clone()).catch(() => handleOfflineWrite(event.request))
      );
      return;
    }
    return;
  }

  // API requests: never cache authenticated tenant/user data in the shared SW cache.
  if (isApiRequest(url)) {
    event.respondWith(networkOnlyApi(event.request));
    return;
  }

  // Navegação (HTML): Network-first para sempre pegar o index.html mais recente
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response.ok) {
            const cloned = response.clone();
            caches.open(CACHE_NAME).then((c) => c.put(event.request, cloned));
          }
          return response;
        })
        .catch(() => caches.match('/index.html').then((r) => r || caches.match('/offline.html')).then((r) => r || new Response('Offline', { status: 503 })))
    );
    return;
  }

  // Assets estáticos (JS/CSS com hash no nome são imutáveis — network-first com cache)
  if (isShellRequest(url)) {
    event.respondWith(
      cacheFirstWithRefresh(event.request).then((response) => {
        if (response.status === 503) {
          return caches.match('/offline.html').then((r) => r || response);
        }
        return response;
      })
    );
    return;
  }

});

// ─── SYNC (Background Sync) ──────────────────────────────────────
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-offline-queue' || event.tag === 'sync-mutations') {
    event.waitUntil(processOfflineQueue());
  }
});

// ─── PUSH NOTIFICATIONS ──────────────────────────────────────────
self.addEventListener('push', (event) => {
  let data = { title: 'Kalibrium', body: 'Nova notificação', url: '/' };

  try {
    if (event.data) {
      data = { ...data, ...event.data.json() };
    }
  } catch (e) {
    data.body = event.data?.text() || data.body;
  }

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      data: { url: data.url },
      vibrate: [200, 100, 200],
      actions: [
        { action: 'open', title: 'Abrir' },
        { action: 'dismiss', title: 'Dispensar' },
      ],
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'dismiss') return;

  const url = event.notification.data?.url || '/';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      const existing = clients.find((c) => c.url.includes(url));
      if (existing) return existing.focus();
      return self.clients.openWindow(url);
    })
  );
});

// ─── MESSAGE (comunicação com o app) ─────────────────────────────
self.addEventListener('message', (event) => {
  const { type } = event.data || {};

  if (type === 'SKIP_WAITING') {
    self.skipWaiting();
    return;
  }

  if (type === 'CACHE_API_DATA') {
    event.ports[0]?.postMessage({
      cached: false,
      reason: 'authenticated-api-cache-disabled',
    });
    return;
  }

  if (type === 'GET_SYNC_STATUS') {
    getOfflineQueueCount().then((count) => {
      event.ports[0]?.postMessage({ pendingCount: count });
    });
    return;
  }

  if (type === 'FORCE_SYNC') {
    self.clients.matchAll().then((clients) => {
      clients.forEach((client) => client.postMessage({ type: 'SYNC_STARTED' }));
    });
    processOfflineQueue();
    return;
  }

  if (type === 'CLEAR_CACHE') {
    clearApiCaches().then(() => {
      event.ports[0]?.postMessage({ cleared: true });
    });
    return;
  }

  if (type === 'CLEANUP_EXPIRED_CACHE') {
    clearApiCaches();
    return;
  }

  if (type === 'GET_LOCAL_STORAGE') {
    // Handled by the app via MessageChannel — SW cannot access localStorage
    return;
  }
});

// ═══════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════

function isApiRequest(url) {
  return url.pathname.startsWith('/api/');
}

function isShellRequest(url) {
  return url.origin === self.location.origin && !isApiRequest(url);
}

async function networkOnlyApi(request) {
  try {
    return await fetch(request);
  } catch {
    return new Response(JSON.stringify({
      error: 'offline',
      message: 'Sem conexão. Dados autenticados não são armazenados no cache local.',
    }), {
      status: 503,
      headers: {
        'Content-Type': 'application/json',
        'Cache-Control': 'no-store',
      },
    });
  }
}

async function clearApiCaches() {
  const keys = await caches.keys();
  await Promise.all(
    keys
      .filter((key) => key.startsWith('kalibrium-api-'))
      .map((key) => caches.delete(key))
  );
}

async function cacheFirstWithRefresh(request) {
  const cache = await caches.open(CACHE_NAME);
  const cached = await cache.match(request);

  const networkFetch = fetch(request).then((response) => {
    if (response.ok) cache.put(request, response.clone());
    return response;
  }).catch(() => null);

  return cached || (await networkFetch) || new Response('Offline', { status: 503 });
}

async function handleOfflineWrite(request) {
  try {
    const body = await request.clone().text();
    await addToOfflineQueue({
      url: request.url,
      method: request.method,
      headers: Object.fromEntries(request.headers.entries()),
      body,
      timestamp: Date.now(),
    });

    // Register background sync
    if ('sync' in self.registration) {
      await self.registration.sync.register('sync-offline-queue');
    }

    return new Response(JSON.stringify({
      message: 'Salvo offline. Será sincronizado quando a conexão for restabelecida.',
      offline: true,
    }), {
      status: 202,
      headers: { 'Content-Type': 'application/json' },
    });
  } catch (err) {
    return new Response(JSON.stringify({ error: 'Falha ao salvar offline' }), {
      status: 500,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

// ─── IndexedDB para fila offline ─────────────────────────────────
// Usa o mesmo DB do app principal (offlineDb.ts) para manter uma única fila unificada.
// O store 'mutation-queue' é compartilhado entre o SW e o syncEngine.

function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open('kalibrium-offline', 2);
    req.onupgradeneeded = (event) => {
      const db = req.result;
      // Criar apenas os stores necessários caso o SW abra o DB antes do app
      if (!db.objectStoreNames.contains('mutation-queue')) {
        const mqStore = db.createObjectStore('mutation-queue', { keyPath: 'id' });
        mqStore.createIndex('by-created', 'created_at');
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

function generateSwId() {
  const now = Date.now();
  const rand = Math.random().toString(36).substring(2, 10);
  return `sw-${now}-${rand}`;
}

async function addToOfflineQueue(data) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('mutation-queue', 'readwrite');
    tx.objectStore('mutation-queue').add({
      id: generateSwId(),
      method: data.method,
      url: data.url,
      body: data.body,
      headers: data.headers,
      created_at: new Date().toISOString(),
      retry_count: 0,
      last_error: null,
      timestamp: data.timestamp,
    });
    tx.oncomplete = resolve;
    tx.onerror = () => reject(tx.error);
  });
}

async function getOfflineQueueCount() {
  try {
    const db = await openDB();
    return new Promise((resolve) => {
      const tx = db.transaction('mutation-queue', 'readonly');
      const req = tx.objectStore('mutation-queue').count();
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => resolve(0);
    });
  } catch {
    return 0;
  }
}

async function getAuthTokenFromClient() {
  try {
    const clients = await self.clients.matchAll();
    if (clients.length === 0) return null;
    return new Promise((resolve) => {
      const channel = new MessageChannel();
      channel.port1.onmessage = (e) => resolve(e.data?.token ?? null);
      clients[0].postMessage({ type: 'GET_AUTH_TOKEN' }, [channel.port2]);
      setTimeout(() => resolve(null), 2000);
    });
  } catch { return null; }
}

async function processOfflineQueue() {
  // Notify start
  const startClients = await self.clients.matchAll();
  startClients.forEach((client) => {
    client.postMessage({ type: 'SYNC_STARTED' });
  });

  const db = await openDB();
  const tx = db.transaction('mutation-queue', 'readonly');
  const store = tx.objectStore('mutation-queue');

  const items = await new Promise((resolve) => {
    const req = store.getAll();
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => resolve([]);
  });

  // Get auth token for replay
  const authToken = await getAuthTokenFromClient();

  for (const item of items) {
    try {
      const fetchUrl = item.url;
      const fetchOptions = { method: item.method };
      const headers = item.headers ? { ...item.headers } : {};

      // Ensure auth token is present
      if (authToken && !headers['Authorization']) {
        headers['Authorization'] = `Bearer ${authToken}`;
      }
      fetchOptions.headers = headers;

      if (item.body) fetchOptions.body = typeof item.body === 'string' ? item.body : JSON.stringify(item.body);

      const response = await fetch(fetchUrl, fetchOptions);

      if (response.ok || response.status < 500) {
        // Remove da fila
        const delTx = db.transaction('mutation-queue', 'readwrite');
        delTx.objectStore('mutation-queue').delete(item.id);
      }
    } catch {
      // Mantém na fila para próxima tentativa
    }
  }

  // Notifica o app
  const remaining = await getOfflineQueueCount();
  const clients = await self.clients.matchAll();
  clients.forEach((client) => {
    client.postMessage({ type: 'SYNC_COMPLETE', remaining });
  });
}

// (Message handlers consolidated above — single listener)
