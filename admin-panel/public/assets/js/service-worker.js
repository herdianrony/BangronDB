/**
 * BangronDB Service Worker
 * Advanced caching and network optimization strategies
 */

const CACHE_NAME = "bangrondb-v2.0.0";
const API_CACHE_NAME = "bangrondb-api-v1.0.0";
const STATIC_CACHE_NAME = "bangrondb-static-v2.0.0";
const DYNAMIC_CACHE_NAME = "bangrondb-dynamic-v1.0.0";

// Cache URLs
const STATIC_URLS = [
  "/",
  "/index.php",
  "/assets/css/style.css",
  "/assets/css/dashboard.css",
  "/assets/css/database-management.css",
  "/assets/css/collection-management.css",
  "/assets/css/monitoring.css",
  "/assets/js/app.js",
  "/assets/js/dashboard.js",
  "/assets/js/dashboard-config.js",
  "/assets/js/dashboard-utils.js",
  "/assets/js/api-service.js",
  "/assets/js/performance-optimization.js",
  "/assets/js/monitoring.js",
  "/assets/js/database-management.js",
  "/assets/js/collection-management.js",
  "/views/layouts/main.latte",
  "/views/dashboard/index.latte",
  "/views/databases/index.latte",
  "/views/collections/settings.latte",
  "/views/documents/index.latte",
  "/views/monitoring/index.latte",
];

const API_URLS = [
  "/api/health",
  "/api/dashboard",
  "/api/databases",
  "/api/collections",
  "/api/users",
  "/api/audit",
  "/api/metrics",
  "/api/search",
];

// Cache strategies
const CACHE_STRATEGIES = {
  // Cache first for static assets
  CACHE_FIRST: "cache-first",

  // Network first for API calls
  NETWORK_FIRST: "network-first",

  // Stale while revalidate for frequently accessed data
  STALE_WHILE_REVALIDATE: "stale-while-revalidate",

  // Network only for critical operations
  NETWORK_ONLY: "network-only",

  // Cache only for offline support
  CACHE_ONLY: "cache-only",
};

// Install event
self.addEventListener("install", (event) => {
  console.log("Service Worker installing...");

  // Force waiting service worker to activate
  self.skipWaiting();

  event.waitUntil(
    caches
      .open(STATIC_CACHE_NAME)
      .then((cache) => {
        console.log("Caching static assets...");
        return cache.addAll(STATIC_URLS);
      })
      .then(() => {
        console.log("Static assets cached successfully");
      })
      .catch((error) => {
        console.error("Failed to cache static assets:", error);
      }),
  );
});

// Activate event
self.addEventListener("activate", (event) => {
  console.log("Service Worker activating...");

  // Clean up old caches
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (
              cacheName !== STATIC_CACHE_NAME &&
              cacheName !== API_CACHE_NAME &&
              cacheName !== DYNAMIC_CACHE_NAME
            ) {
              console.log("Deleting old cache:", cacheName);
              return caches.delete(cacheName);
            }
          }),
        );
      })
      .then(() => {
        // Claim clients for immediate activation
        return self.clients.claim();
      }),
  );
});

// Fetch event with intelligent caching strategies
self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);

  // Don't cache chrome-extension:// URLs
  if (url.protocol === "chrome-extension:") {
    return;
  }

  // Handle different request types
  if (event.request.url.includes("/api/")) {
    // API requests - use network first strategy
    event.respondWith(handleAPIRequest(event.request));
  } else if (event.request.url.includes("/assets/")) {
    // Static assets - use cache first strategy
    event.respondWith(handleStaticAssetRequest(event.request));
  } else if (event.request.url.includes("/views/")) {
    // View templates - use stale while revalidate
    event.respondWith(handleViewRequest(event.request));
  } else {
    // Other requests - use network first with fallback
    event.respondWith(handleGenericRequest(event.request));
  }
});

// Handle API requests with network first strategy
async function handleAPIRequest(request) {
  const cache = await caches.open(API_CACHE_NAME);

  try {
    // Try network first
    const networkResponse = await fetch(request);

    // Clone response for caching
    const clonedResponse = networkResponse.clone();

    // Cache successful API responses
    if (networkResponse.ok) {
      cache.put(request, clonedResponse);
    }

    return networkResponse;
  } catch (error) {
    // Fallback to cache if network fails
    console.log("Network request failed, trying cache:", error);

    try {
      const cachedResponse = await cache.match(request);
      if (cachedResponse) {
        return cachedResponse;
      }
    } catch (cacheError) {
      console.error("Cache access failed:", cacheError);
    }

    // Return error response
    return new Response("Network error", {
      status: 503,
      statusText: "Service Unavailable",
    });
  }
}

// Handle static asset requests with cache first strategy
async function handleStaticAssetRequest(request) {
  const cache = await caches.open(STATIC_CACHE_NAME);

  try {
    // Try cache first
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    // If not in cache, fetch from network
    const networkResponse = await fetch(request);

    // Cache the response for future use
    cache.put(request, networkResponse.clone());

    return networkResponse;
  } catch (error) {
    console.error("Failed to fetch static asset:", error);
    return new Response("Asset not found", {
      status: 404,
      statusText: "Not Found",
    });
  }
}

// Handle view requests with stale while revalidate strategy
async function handleViewRequest(request) {
  const cache = await caches.open(DYNAMIC_CACHE_NAME);

  try {
    // Try to get from cache first
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      // Return cached response immediately
      const fetchPromise = fetch(request).then((networkResponse) => {
        // Update cache with fresh response
        cache.put(request, networkResponse.clone());
        return networkResponse;
      });

      // Return cached response and update in background
      return cachedResponse;
    }

    // If not in cache, fetch from network
    const networkResponse = await fetch(request);

    // Cache the response
    cache.put(request, networkResponse.clone());

    return networkResponse;
  } catch (error) {
    console.error("Failed to fetch view:", error);
    return new Response("View not found", {
      status: 404,
      statusText: "Not Found",
    });
  }
}

// Handle generic requests with network first strategy
async function handleGenericRequest(request) {
  const cache = await caches.open(DYNAMIC_CACHE_NAME);

  try {
    // Try network first
    const networkResponse = await fetch(request);

    // Clone response for caching
    const clonedResponse = networkResponse.clone();

    // Cache the response
    cache.put(request, clonedResponse);

    return networkResponse;
  } catch (error) {
    // Fallback to cache
    console.log("Network request failed, trying cache:", error);

    try {
      const cachedResponse = await cache.match(request);
      if (cachedResponse) {
        return cachedResponse;
      }
    } catch (cacheError) {
      console.error("Cache access failed:", cacheError);
    }

    // Return error response
    return new Response("Network error", {
      status: 503,
      statusText: "Service Unavailable",
    });
  }
}

// Background sync
self.addEventListener("sync", (event) => {
  console.log("Background sync triggered:", event.tag);

  if (event.tag === "sync-api-data") {
    event.waitUntil(syncAPIData());
  } else if (event.tag === "sync-user-actions") {
    event.waitUntil(syncUserActions());
  }
});

// Sync API data
async function syncAPIData() {
  try {
    // Get pending API calls from IndexedDB
    const pendingCalls = await getPendingAPICalls();

    // Execute pending calls
    for (const call of pendingCalls) {
      try {
        const response = await fetch(call.url, call.options);
        if (response.ok) {
          // Remove successful call from pending list
          await removePendingAPICall(call.id);
        }
      } catch (error) {
        console.error("Failed to sync API call:", call, error);
      }
    }
  } catch (error) {
    console.error("Background sync failed:", error);
  }
}

// Sync user actions
async function syncUserActions() {
  try {
    // Get pending user actions from IndexedDB
    const pendingActions = await getPendingUserActions();

    // Execute pending actions
    for (const action of pendingActions) {
      try {
        const response = await fetch(action.url, action.options);
        if (response.ok) {
          // Remove successful action from pending list
          await removePendingUserAction(action.id);
        }
      } catch (error) {
        console.error("Failed to sync user action:", action, error);
      }
    }
  } catch (error) {
    console.error("Background sync failed:", error);
  }
}

// Push notifications
self.addEventListener("push", (event) => {
  console.log("Push notification received:", event);

  if (event.data) {
    const data = event.data.json();

    const options = {
      body: data.body,
      icon: "/assets/images/icon-192x192.png",
      badge: "/assets/images/badge-72x72.png",
      tag: data.tag,
      requireInteraction: data.requireInteraction || false,
      actions: data.actions || [],
    };

    event.waitUntil(self.registration.showNotification(data.title, options));
  }
});

// Handle notification clicks
self.addEventListener("notificationclick", (event) => {
  console.log("Notification clicked:", event);

  event.notification.close();

  if (event.action) {
    // Handle action button clicks
    handleNotificationAction(event.action, event.notification.data);
  } else {
    // Handle notification click
    if (event.notification.data && event.notification.data.url) {
      event.waitUntil(clients.openWindow(event.notification.data.url));
    }
  }
});

// Handle notification actions
async function handleNotificationAction(action, data) {
  console.log("Notification action:", action, data);

  // Perform action-specific logic
  switch (action) {
    case "refresh":
      // Refresh data
      await refreshData();
      break;
    case "view":
      // Open specific page
      if (data.url) {
        await clients.openWindow(data.url);
      }
      break;
    case "dismiss":
      // Dismiss notification
      break;
  }
}

// Refresh data
async function refreshData() {
  try {
    // Trigger data refresh
    const clients = await self.clients.matchAll();
    clients.forEach((client) => {
      client.postMessage({
        type: "refresh-data",
      });
    });
  } catch (error) {
    console.error("Failed to refresh data:", error);
  }
}

// IndexedDB helpers (would need proper implementation)
async function getPendingAPICalls() {
  // Return pending API calls from IndexedDB
  return [];
}

async function removePendingAPICall(id) {
  // Remove API call from IndexedDB
}

async function getPendingUserActions() {
  // Return pending user actions from IndexedDB
  return [];
}

async function removePendingUserAction(id) {
  // Remove user action from IndexedDB
}

// Handle messages from client
self.addEventListener("message", (event) => {
  console.log("Service Worker message:", event.data);

  if (event.data && event.data.type) {
    switch (event.data.type) {
      case "skip-waiting":
        self.skipWaiting();
        break;
      case "cache-bust":
        bustCache();
        break;
      case "clear-cache":
        clearCache();
        break;
      case "update-cache":
        updateCache(event.data.urls);
        break;
    }
  }
});

// Cache busting
async function bustCache() {
  const cacheNames = await caches.keys();
  await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
  console.log("Cache busted successfully");
}

// Clear cache
async function clearCache() {
  const cacheNames = await caches.keys();
  await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
  console.log("Cache cleared successfully");
}

// Update cache with new URLs
async function updateCache(urls) {
  const cache = await caches.open(STATIC_CACHE_NAME);
  await cache.addAll(urls);
  console.log("Cache updated successfully");
}

// Handle client claiming
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "claim") {
    self.clients.claim();
  }
});

// Export for debugging
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    CACHE_STRATEGIES,
    handleAPIRequest,
    handleStaticAssetRequest,
    handleViewRequest,
    handleGenericRequest,
  };
}
