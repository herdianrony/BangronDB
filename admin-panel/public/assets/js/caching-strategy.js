/**
 * BangronDB Advanced Caching Strategy
 * Implements multi-layered caching with intelligent cache management
 */

class AdvancedCachingStrategy {
  constructor() {
    this.config = {
      enableMemoryCache: true,
      enableIndexedDB: true,
      enableServiceWorker: true,
      enableCacheStorage: true,
      enableLocalStorage: true,
      enableSessionStorage: true,
      defaultTTL: 300000, // 5 minutes
      maxMemoryCacheSize: 100,
      maxIndexedDBSize: 50 * 1024 * 1024, // 50MB
      compression: true,
      encryption: false,
      cachePreloading: true,
      cacheInvalidation: true,
      cacheAnalytics: true,
    };

    this.memoryCache = new Map();
    this.indexedDB = null;
    this.cacheStorage = null;
    this.localCache = null;
    this.sessionCache = null;
    this.cacheMetrics = {
      hits: 0,
      misses: 0,
      evictions: 0,
      saves: 0,
      hitsByType: {},
      missesByType: {},
      cacheSize: 0,
      avgResponseTime: 0,
    };

    this.init();
  }

  init() {
    this.initializeCaches();
    this.setupCachePreloading();
    this.setupCacheInvalidation();
    this.setupCacheAnalytics();
  }

  // Initialize all cache layers
  async initializeCaches() {
    try {
      // Initialize memory cache
      if (this.config.enableMemoryCache) {
        this.memoryCache = new Map();
      }

      // Initialize IndexedDB cache
      if (this.config.enableIndexedDB) {
        await this.initializeIndexedDB();
      }

      // Initialize CacheStorage
      if (this.config.enableCacheStorage && "caches" in window) {
        this.cacheStorage = caches;
      }

      // Initialize localStorage cache
      if (this.config.enableLocalStorage) {
        this.localCache = this.createLocalStorageAdapter();
      }

      // Initialize sessionStorage cache
      if (this.config.enableSessionStorage) {
        this.sessionCache = this.createSessionStorageAdapter();
      }

      console.log("Advanced caching strategy initialized");
    } catch (error) {
      console.error("Failed to initialize caching strategy:", error);
    }
  }

  // Initialize IndexedDB
  async initializeIndexedDB() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open("BangronDBCache", 1);

      request.onerror = () => {
        console.error("Failed to open IndexedDB");
        reject(request.error);
      };

      request.onsuccess = () => {
        this.indexedDB = request.result;
        resolve();
      };

      request.onupgradeneeded = (event) => {
        const db = event.target.result;

        // Create object stores
        if (!db.objectStoreNames.contains("apiCache")) {
          const apiStore = db.createObjectStore("apiCache", { keyPath: "url" });
          apiStore.createIndex("timestamp", "timestamp", { unique: false });
          apiStore.createIndex("ttl", "ttl", { unique: false });
        }

        if (!db.objectStoreNames.contains("staticCache")) {
          const staticStore = db.createObjectStore("staticCache", {
            keyPath: "url",
          });
          staticStore.createIndex("timestamp", "timestamp", { unique: false });
        }

        if (!db.objectStoreNames.contains("userData")) {
          const userStore = db.createObjectStore("userData", {
            keyPath: "key",
          });
          userStore.createIndex("timestamp", "timestamp", { unique: false });
        }
      };
    });
  }

  // Create localStorage adapter
  createLocalStorageAdapter() {
    return {
      get: (key) => {
        try {
          const item = localStorage.getItem(`cache_${key}`);
          return item ? JSON.parse(item) : null;
        } catch (error) {
          console.error("localStorage get error:", error);
          return null;
        }
      },

      set: (key, value, ttl = this.config.defaultTTL) => {
        try {
          const cacheItem = {
            value: value,
            timestamp: Date.now(),
            ttl: ttl,
          };
          localStorage.setItem(`cache_${key}`, JSON.stringify(cacheItem));
          return true;
        } catch (error) {
          console.error("localStorage set error:", error);
          return false;
        }
      },

      remove: (key) => {
        try {
          localStorage.removeItem(`cache_${key}`);
          return true;
        } catch (error) {
          console.error("localStorage remove error:", error);
          return false;
        }
      },

      clear: () => {
        try {
          // Remove only cache items
          for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith("cache_")) {
              localStorage.removeItem(key);
            }
          }
          return true;
        } catch (error) {
          console.error("localStorage clear error:", error);
          return false;
        }
      },
    };
  }

  // Create sessionStorage adapter
  createSessionStorageAdapter() {
    return {
      get: (key) => {
        try {
          const item = sessionStorage.getItem(`cache_${key}`);
          return item ? JSON.parse(item) : null;
        } catch (error) {
          console.error("sessionStorage get error:", error);
          return null;
        }
      },

      set: (key, value, ttl = this.config.defaultTTL) => {
        try {
          const cacheItem = {
            value: value,
            timestamp: Date.now(),
            ttl: ttl,
          };
          sessionStorage.setItem(`cache_${key}`, JSON.stringify(cacheItem));
          return true;
        } catch (error) {
          console.error("sessionStorage set error:", error);
          return false;
        }
      },

      remove: (key) => {
        try {
          sessionStorage.removeItem(`cache_${key}`);
          return true;
        } catch (error) {
          console.error("sessionStorage remove error:", error);
          return false;
        }
      },

      clear: () => {
        try {
          // Remove only cache items
          for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key && key.startsWith("cache_")) {
              sessionStorage.removeItem(key);
            }
          }
          return true;
        } catch (error) {
          console.error("sessionStorage clear error:", error);
          return false;
        }
      },
    };
  }

  // Get data from cache with multiple fallback layers
  async get(key, options = {}) {
    const startTime = performance.now();
    const cacheLayers = this.getCacheLayers(
      options.priority || [
        "memory",
        "indexeddb",
        "cachestorage",
        "local",
        "session",
      ],
    );

    // Try each cache layer in order
    for (const layer of cacheLayers) {
      try {
        const result = await this.getFromLayer(layer, key);
        if (result !== null) {
          // Cache hit
          this.cacheMetrics.hits++;
          this.cacheMetrics.hitsByType[layer] =
            (this.cacheMetrics.hitsByType[layer] || 0) + 1;

          const responseTime = performance.now() - startTime;
          this.updateAverageResponseTime(responseTime);

          console.log(`Cache hit from ${layer}:`, key);
          return result;
        }
      } catch (error) {
        console.error(`Cache get error from ${layer}:`, error);
      }
    }

    // Cache miss
    this.cacheMetrics.misses++;
    this.cacheMetrics.missesByType[cacheLayers.join(",")] =
      (this.cacheMetrics.missesByType[cacheLayers.join(",")] || 0) + 1;

    const responseTime = performance.now() - startTime;
    this.updateAverageResponseTime(responseTime);

    console.log(`Cache miss:`, key);
    return null;
  }

  // Get data from specific cache layer
  async getFromLayer(layer, key) {
    switch (layer) {
      case "memory":
        return this.getFromMemory(key);
      case "indexeddb":
        return this.getFromIndexedDB(key);
      case "cachestorage":
        return this.getFromCacheStorage(key);
      case "local":
        return this.localCache ? this.localCache.get(key) : null;
      case "session":
        return this.sessionCache ? this.sessionCache.get(key) : null;
      default:
        return null;
    }
  }

  // Get from memory cache
  getFromMemory(key) {
    const item = this.memoryCache.get(key);
    if (item) {
      // Check if item is expired
      if (Date.now() - item.timestamp > item.ttl) {
        this.memoryCache.delete(key);
        return null;
      }
      return item.value;
    }
    return null;
  }

  // Get from IndexedDB
  async getFromIndexedDB(key) {
    if (!this.indexedDB) return null;

    return new Promise((resolve) => {
      const transaction = this.indexedDB.transaction(
        ["apiCache", "staticCache", "userData"],
        "readonly",
      );
      const stores = ["apiCache", "staticCache", "userData"];

      let result = null;

      stores.forEach((storeName) => {
        const store = transaction.objectStore(storeName);
        const request = store.get(key);

        request.onsuccess = () => {
          if (request.result) {
            // Check if item is expired
            if (Date.now() - request.result.timestamp > request.result.ttl) {
              // Remove expired item
              this.removeFromIndexedDB(key, storeName);
              return;
            }
            result = request.result.value;
          }
        };
      });

      transaction.oncomplete = () => {
        resolve(result);
      };
    });
  }

  // Get from CacheStorage
  async getFromCacheStorage(key) {
    if (!this.cacheStorage) return null;

    try {
      const cache = await this.cacheStorage.open("bangrondb-api");
      const response = await cache.match(key);

      if (response) {
        const data = await response.json();
        return data;
      }
    } catch (error) {
      console.error("CacheStorage get error:", error);
    }

    return null;
  }

  // Set data to cache with multiple layers
  async set(key, value, options = {}) {
    const cacheLayers = options.layers || ["memory", "indexeddb", "local"];
    const ttl = options.ttl || this.config.defaultTTL;

    const result = {
      success: false,
      layers: [],
    };

    // Set to each specified cache layer
    for (const layer of cacheLayers) {
      try {
        const success = await this.setToLayer(layer, key, value, ttl);
        if (success) {
          result.layers.push(layer);
          result.success = true;
        }
      } catch (error) {
        console.error(`Cache set error from ${layer}:`, error);
      }
    }

    if (result.success) {
      this.cacheMetrics.saves++;
      this.cacheMetrics.cacheSize += this.estimateSize(value);
    }

    return result;
  }

  // Set data to specific cache layer
  async setToLayer(layer, key, value, ttl) {
    switch (layer) {
      case "memory":
        return this.setToMemory(key, value, ttl);
      case "indexeddb":
        return this.setToIndexedDB(key, value, ttl);
      case "cachestorage":
        return this.setToCacheStorage(key, value, ttl);
      case "local":
        return this.localCache ? this.localCache.set(key, value, ttl) : false;
      case "session":
        return this.sessionCache
          ? this.sessionCache.set(key, value, ttl)
          : false;
      default:
        return false;
    }
  }

  // Set to memory cache
  setToMemory(key, value, ttl) {
    try {
      this.memoryCache.set(key, {
        value: value,
        timestamp: Date.now(),
        ttl: ttl,
      });

      // Check memory cache size and evict if necessary
      if (this.memoryCache.size > this.config.maxMemoryCacheSize) {
        this.evictFromMemory();
      }

      return true;
    } catch (error) {
      console.error("Memory cache set error:", error);
      return false;
    }
  }

  // Set to IndexedDB
  async setToIndexedDB(key, value, ttl) {
    if (!this.indexedDB) return false;

    return new Promise((resolve) => {
      const transaction = this.indexedDB.transaction(
        ["apiCache", "staticCache", "userData"],
        "readwrite",
      );
      const cacheItem = {
        url: key,
        value: value,
        timestamp: Date.now(),
        ttl: ttl,
      };

      // Determine which store to use based on key pattern
      let storeName = "apiCache";
      if (key.startsWith("/assets/")) {
        storeName = "staticCache";
      } else if (key.startsWith("user_")) {
        storeName = "userData";
      }

      const store = transaction.objectStore(storeName);
      const request = store.put(cacheItem);

      request.onsuccess = () => {
        resolve(true);
      };

      request.onerror = () => {
        console.error("IndexedDB set error:", request.error);
        resolve(false);
      };
    });
  }

  // Set to CacheStorage
  async setToCacheStorage(key, value, ttl) {
    if (!this.cacheStorage) return false;

    try {
      const cache = await this.cacheStorage.open("bangrondb-api");
      const response = new Response(JSON.stringify(value));

      await cache.put(key, response);
      return true;
    } catch (error) {
      console.error("CacheStorage set error:", error);
      return false;
    }
  }

  // Remove data from cache
  async remove(key, options = {}) {
    const cacheLayers = options.layers || [
      "memory",
      "indexeddb",
      "cachestorage",
      "local",
      "session",
    ];

    const result = {
      success: false,
      layers: [],
    };

    // Remove from each cache layer
    for (const layer of cacheLayers) {
      try {
        const success = await this.removeFromLayer(layer, key);
        if (success) {
          result.layers.push(layer);
          result.success = true;
        }
      } catch (error) {
        console.error(`Cache remove error from ${layer}:`, error);
      }
    }

    return result;
  }

  // Remove from specific cache layer
  async removeFromLayer(layer, key) {
    switch (layer) {
      case "memory":
        return this.removeFromMemory(key);
      case "indexeddb":
        return this.removeFromIndexedDB(key);
      case "cachestorage":
        return this.removeFromCacheStorage(key);
      case "local":
        return this.localCache ? this.localCache.remove(key) : false;
      case "session":
        return this.sessionCache ? this.sessionCache.remove(key) : false;
      default:
        return false;
    }
  }

  // Remove from memory cache
  removeFromMemory(key) {
    const deleted = this.memoryCache.delete(key);
    return deleted;
  }

  // Remove from IndexedDB
  async removeFromIndexedDB(key, storeName = null) {
    if (!this.indexedDB) return false;

    return new Promise((resolve) => {
      const transaction = this.indexedDB.transaction(
        ["apiCache", "staticCache", "userData"],
        "readwrite",
      );

      // Determine which store to use
      if (!storeName) {
        storeName = "apiCache";
        if (key.startsWith("/assets/")) {
          storeName = "staticCache";
        } else if (key.startsWith("user_")) {
          storeName = "userData";
        }
      }

      const store = transaction.objectStore(storeName);
      const request = store.delete(key);

      request.onsuccess = () => {
        resolve(true);
      };

      request.onerror = () => {
        console.error("IndexedDB delete error:", request.error);
        resolve(false);
      };
    });
  }

  // Remove from CacheStorage
  async removeFromCacheStorage(key) {
    if (!this.cacheStorage) return false;

    try {
      const cache = await this.cacheStorage.open("bangrondb-api");
      await cache.delete(key);
      return true;
    } catch (error) {
      console.error("CacheStorage delete error:", error);
      return false;
    }
  }

  // Evict items from memory cache
  evictFromMemory() {
    // Remove oldest items first
    const entries = Array.from(this.memoryCache.entries());
    entries.sort((a, b) => a[1].timestamp - b[1].timestamp);

    const itemsToRemove = Math.floor(this.config.maxMemoryCacheSize * 0.2); // Remove 20%
    for (let i = 0; i < itemsToRemove; i++) {
      this.memoryCache.delete(entries[i][0]);
    }

    this.cacheMetrics.evictions += itemsToRemove;
    console.log(`Evicted ${itemsToRemove} items from memory cache`);
  }

  // Clear all caches
  async clear(options = {}) {
    const cacheLayers = options.layers || [
      "memory",
      "indexeddb",
      "cachestorage",
      "local",
      "session",
    ];

    const result = {
      success: false,
      layers: [],
    };

    // Clear each cache layer
    for (const layer of cacheLayers) {
      try {
        const success = await this.clearLayer(layer);
        if (success) {
          result.layers.push(layer);
          result.success = true;
        }
      } catch (error) {
        console.error(`Cache clear error from ${layer}:`, error);
      }
    }

    if (result.success) {
      this.cacheMetrics.cacheSize = 0;
    }

    return result;
  }

  // Clear specific cache layer
  async clearLayer(layer) {
    switch (layer) {
      case "memory":
        return this.clearMemory();
      case "indexeddb":
        return this.clearIndexedDB();
      case "cachestorage":
        return this.clearCacheStorage();
      case "local":
        return this.localCache ? this.localCache.clear() : false;
      case "session":
        return this.sessionCache ? this.sessionCache.clear() : false;
      default:
        return false;
    }
  }

  // Clear memory cache
  clearMemory() {
    this.memoryCache.clear();
    return true;
  }

  // Clear IndexedDB
  async clearIndexedDB() {
    if (!this.indexedDB) return false;

    return new Promise((resolve) => {
      const transaction = this.indexedDB.transaction(
        ["apiCache", "staticCache", "userData"],
        "readwrite",
      );

      ["apiCache", "staticCache", "userData"].forEach((storeName) => {
        const store = transaction.objectStore(storeName);
        store.clear();
      });

      transaction.oncomplete = () => {
        resolve(true);
      };

      transaction.onerror = () => {
        console.error("IndexedDB clear error:", transaction.error);
        resolve(false);
      };
    });
  }

  // Clear CacheStorage
  async clearCacheStorage() {
    if (!this.cacheStorage) return false;

    try {
      await this.cacheStorage.delete("bangrondb-api");
      return true;
    } catch (error) {
      console.error("CacheStorage clear error:", error);
      return false;
    }
  }

  // Cache preloading
  setupCachePreloading() {
    if (!this.config.cachePreloading) return;

    // Preload critical resources
    this.preloadCriticalResources();

    // Preload user data
    this.preloadUserData();

    // Preload API data
    this.preloadAPIData();
  }

  // Preload critical resources
  preloadCriticalResources() {
    const criticalResources = [
      "/assets/css/style.css",
      "/assets/js/app.js",
      "/assets/js/dashboard.js",
      "/assets/js/api-service.js",
    ];

    criticalResources.forEach((url) => {
      this.fetchAndCache(url, { ttl: 3600000 }); // 1 hour
    });
  }

  // Preload user data
  preloadUserData() {
    const userKey = "user_profile";
    this.get(userKey).then((data) => {
      if (!data) {
        // Fetch and cache user profile
        window.apiService.getUser().then((userData) => {
          this.set(userKey, userData, { ttl: 1800000 }); // 30 minutes
        });
      }
    });
  }

  // Preload API data
  preloadAPIData() {
    const dashboardKey = "dashboard_data";
    this.get(dashboardKey).then((data) => {
      if (!data) {
        // Fetch and cache dashboard data
        window.apiService.getDashboardData().then((dashboardData) => {
          this.set(dashboardKey, dashboardData, { ttl: 300000 }); // 5 minutes
        });
      }
    });
  }

  // Fetch and cache resource
  async fetchAndCache(url, options = {}) {
    try {
      const response = await fetch(url);
      if (response.ok) {
        const data = await response.json();
        await this.set(url, data, options);
        return data;
      }
    } catch (error) {
      console.error(`Failed to fetch and cache ${url}:`, error);
    }
    return null;
  }

  // Cache invalidation
  setupCacheInvalidation() {
    if (!this.config.cacheInvalidation) return;

    // Setup periodic cleanup
    setInterval(() => {
      this.cleanupExpiredItems();
    }, 60000); // Every minute

    // Setup event-based invalidation
    this.setupEventBasedInvalidation();
  }

  // Cleanup expired items
  async cleanupExpiredItems() {
    const now = Date.now();

    // Clean memory cache
    for (const [key, item] of this.memoryCache.entries()) {
      if (now - item.timestamp > item.ttl) {
        this.memoryCache.delete(key);
      }
    }

    // Clean IndexedDB
    if (this.indexedDB) {
      const transaction = this.indexedDB.transaction(
        ["apiCache", "staticCache", "userData"],
        "readwrite",
      );

      ["apiCache", "staticCache", "userData"].forEach((storeName) => {
        const store = transaction.objectStore(storeName);
        const index = store.index("timestamp");
        const range = IDBKeyRange.upperBound(now);

        const request = index.openCursor(range);
        request.onsuccess = (event) => {
          const cursor = event.target.result;
          if (cursor) {
            if (now - cursor.value.timestamp > cursor.value.ttl) {
              cursor.delete();
            }
            cursor.continue();
          }
        };
      });
    }

    console.log("Cache cleanup completed");
  }

  // Setup event-based invalidation
  setupEventBasedInvalidation() {
    // Listen for user login/logout
    window.addEventListener("user-login", () => {
      this.clear(["memory", "session"]);
    });

    window.addEventListener("user-logout", () => {
      this.clear(["memory", "indexeddb", "session"]);
    });

    // Listen for data updates
    window.addEventListener("data-updated", (event) => {
      const { key, type } = event.detail;
      this.invalidateRelatedCache(key, type);
    });
  }

  // Invalidate related cache items
  async invalidateRelatedCache(key, type) {
    const relatedKeys = this.getRelatedCacheKeys(key, type);

    for (const relatedKey of relatedKeys) {
      await this.remove(relatedKey);
    }

    console.log("Invalidated related cache items:", relatedKeys);
  }

  // Get related cache keys
  getRelatedCacheKeys(key, type) {
    const relatedKeys = [];

    switch (type) {
      case "user":
        relatedKeys.push("user_profile", "user_permissions", "user_activity");
        break;
      case "dashboard":
        relatedKeys.push(
          "dashboard_data",
          "dashboard_metrics",
          "dashboard_charts",
        );
        break;
      case "database":
        relatedKeys.push(
          "database_list",
          "database_schema",
          "database_metrics",
        );
        break;
      case "collection":
        relatedKeys.push(
          "collection_list",
          "collection_schema",
          "collection_data",
        );
        break;
      default:
        relatedKeys.push(key);
    }

    return relatedKeys;
  }

  // Cache analytics
  setupCacheAnalytics() {
    if (!this.config.cacheAnalytics) return;

    // Track cache performance
    setInterval(() => {
      this.sendCacheAnalytics();
    }, 300000); // Every 5 minutes
  }

  // Send cache analytics
  async sendCacheAnalytics() {
    const analytics = {
      type: "cache_analytics",
      metrics: this.cacheMetrics,
      cacheSize: this.cacheMetrics.cacheSize,
      hitRate: this.getHitRate(),
      timestamp: Date.now(),
    };

    if (navigator.sendBeacon) {
      navigator.sendBeacon("/api/analytics/cache", JSON.stringify(analytics));
    }
  }

  // Get cache hit rate
  getHitRate() {
    const total = this.cacheMetrics.hits + this.cacheMetrics.misses;
    return total > 0 ? this.cacheMetrics.hits / total : 0;
  }

  // Update average response time
  updateAverageResponseTime(responseTime) {
    const current = this.cacheMetrics.avgResponseTime;
    const count = this.cacheMetrics.hits + this.cacheMetrics.misses;

    if (count === 0) {
      this.cacheMetrics.avgResponseTime = responseTime;
    } else {
      this.cacheMetrics.avgResponseTime =
        (current * (count - 1) + responseTime) / count;
    }
  }

  // Estimate size of cached data
  estimateSize(data) {
    return JSON.stringify(data).length * 2; // Rough estimate
  }

  // Get cache layers in priority order
  getCacheLayers(priority) {
    const allLayers = [
      "memory",
      "indexeddb",
      "cachestorage",
      "local",
      "session",
    ];
    return priority.filter((layer) => allLayers.includes(layer));
  }

  // Get cache statistics
  getCacheStats() {
    return {
      metrics: this.cacheMetrics,
      hitRate: this.getHitRate(),
      cacheSize: this.cacheMetrics.cacheSize,
      avgResponseTime: this.cacheMetrics.avgResponseTime,
      memoryCacheSize: this.memoryCache.size,
      indexedDBAvailable: !!this.indexedDB,
      cacheStorageAvailable: !!this.cacheStorage,
    };
  }

  // Get cache layer status
  getCacheLayerStatus() {
    return {
      memory: {
        available: this.config.enableMemoryCache,
        size: this.memoryCache.size,
        maxSize: this.config.maxMemoryCacheSize,
      },
      indexeddb: {
        available: this.config.enableIndexedDB,
        size: this.getIndexedDBSize(),
        maxSize: this.config.maxIndexedDBSize,
      },
      cachestorage: {
        available: this.config.enableCacheStorage,
        size: this.getCacheStorageSize(),
      },
      localstorage: {
        available: this.config.enableLocalStorage,
        size: this.getLocalStorageSize(),
      },
      sessionStorage: {
        available: this.config.enableSessionStorage,
        size: this.getSessionStorageSize(),
      },
    };
  }

  // Get IndexedDB size
  getIndexedDBSize() {
    // This would require a more sophisticated implementation
    return 0;
  }

  // Get CacheStorage size
  async getCacheStorageSize() {
    if (!this.cacheStorage) return 0;

    try {
      const cache = await this.cacheStorage.open("bangrondb-api");
      const keys = await cache.keys();
      return keys.length;
    } catch (error) {
      console.error("Failed to get CacheStorage size:", error);
      return 0;
    }
  }

  // Get localStorage size
  getLocalStorageSize() {
    let size = 0;
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key && key.startsWith("cache_")) {
        size += localStorage.getItem(key).length;
      }
    }
    return size;
  }

  // Get sessionStorage size
  getSessionStorageSize() {
    let size = 0;
    for (let i = 0; i < sessionStorage.length; i++) {
      const key = sessionStorage.key(i);
      if (key && key.startsWith("cache_")) {
        size += sessionStorage.getItem(key).length;
      }
    }
    return size;
  }

  // Cleanup
  destroy() {
    // Clear all caches
    this.clear();

    // Reset metrics
    this.cacheMetrics = {
      hits: 0,
      misses: 0,
      evictions: 0,
      saves: 0,
      hitsByType: {},
      missesByType: {},
      cacheSize: 0,
      avgResponseTime: 0,
    };
  }
}

// Initialize advanced caching strategy
window.advancedCaching = new AdvancedCachingStrategy();

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = AdvancedCachingStrategy;
}
