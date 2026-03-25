/**
 * BangronDB Admin Panel - Performance Configuration
 * Configuration file for all performance optimization modules
 */

// Performance Configuration
const PERFORMANCE_CONFIG = {
  // Performance Optimization Settings
  optimization: {
    enabled: true,
    lazyLoading: {
      enabled: true,
      threshold: 100, // pixels from viewport
      rootMargin: "50px",
    },
    codeSplitting: {
      enabled: true,
      chunkSize: 10000, // bytes
      parallelLoads: 3,
    },
    imageOptimization: {
      enabled: true,
      lazyLoad: true,
      webp: true,
      avif: true,
      quality: 80,
      maxSize: 2048, // pixels
    },
    criticalCSS: {
      enabled: true,
      inline: true,
      cache: true,
    },
    bundleOptimization: {
      enabled: true,
      minify: true,
      gzip: true,
      brotli: true,
    },
  },

  // Caching Strategy Settings
  caching: {
    enabled: true,
    memoryCache: {
      enabled: true,
      maxSize: 50, // MB
      ttl: 300, // seconds
    },
    indexedDB: {
      enabled: true,
      dbName: "BangronDB_Performance",
      version: 1,
      storeNames: ["assets", "api_responses", "queries"],
    },
    cacheStorage: {
      enabled: true,
      strategies: {
        "static-assets": "cache-first",
        "api-data": "network-first",
        queries: "stale-while-revalidate",
      },
      maxAge: 86400, // seconds
      maxEntries: 100,
    },
    localStorage: {
      enabled: true,
      maxSize: 10, // MB
      ttl: 3600, // seconds
    },
    sessionStorage: {
      enabled: true,
      ttl: 1800, // seconds
    },
  },

  // Database Optimization Settings
  database: {
    enabled: true,
    queryCache: {
      enabled: true,
      maxSize: 1000,
      ttl: 600, // seconds
      hashFunction: "sha256",
    },
    connectionPool: {
      enabled: true,
      minConnections: 1,
      maxConnections: 10,
      idleTimeout: 30000,
    },
    queryOptimization: {
      enabled: true,
      slowQueryThreshold: 1000, // milliseconds
      analyzeQueries: true,
      suggestIndexes: true,
    },
    batchProcessing: {
      enabled: true,
      batchSize: 100,
      delay: 100, // milliseconds
    },
  },

  // User Experience Enhancement Settings
  experience: {
    enabled: true,
    theme: {
      enabled: true,
      default: "dark",
      autoDetect: true,
      persistence: true,
    },
    keyboard: {
      enabled: true,
      shortcuts: {
        "ctrl+s": "save",
        "ctrl+z": "undo",
        "ctrl+y": "redo",
        "ctrl+f": "search",
        "ctrl+/": "help",
      },
    },
    tooltips: {
      enabled: true,
      delay: 500,
      duration: 3000,
    },
    loading: {
      enabled: true,
      skeleton: true,
      progress: true,
      animation: true,
    },
    autoSave: {
      enabled: true,
      interval: 30000, // milliseconds
      debounce: 1000,
      indicators: true,
    },
    undoRedo: {
      enabled: true,
      maxHistory: 50,
      debounce: 500,
    },
    voiceSearch: {
      enabled: true,
      language: "id-ID",
      continuous: false,
    },
    predictiveSearch: {
      enabled: true,
      delay: 300,
      minChars: 2,
      maxResults: 10,
    },
    accessibility: {
      enabled: true,
      ariaLabels: true,
      keyboardNavigation: true,
      screenReader: true,
      highContrast: false,
    },
  },

  // Performance Monitoring Settings
  monitoring: {
    enabled: true,
    coreWebVitals: {
      enabled: true,
      lcp: true,
      fid: true,
      cls: true,
      fcp: true,
      ttfb: true,
    },
    resourceTiming: {
      enabled: true,
      include: ["navigation", "resource", "paint"],
      sampleRate: 1.0,
    },
    memoryMonitoring: {
      enabled: true,
      interval: 5000,
      threshold: 0.8, // 80% memory usage
      gcTriggers: true,
    },
    networkMonitoring: {
      enabled: true,
      requests: true,
      connections: true,
      bandwidth: true,
    },
    userAnalytics: {
      enabled: true,
      interactions: true,
      performance: true,
      errorTracking: true,
    },
    alerts: {
      enabled: true,
      thresholds: {
        lcp: 2500, // milliseconds
        fid: 100, // milliseconds
        cls: 0.1, // score
        fcp: 1800, // milliseconds
        ttfb: 800, // milliseconds
        memory: 0.8, // 80%
        errors: 5, // per minute
      },
      notifications: true,
      email: false,
      webhook: false,
    },
    reporting: {
      enabled: true,
      interval: 3600, // seconds
      format: "json",
      retention: 86400, // seconds
    },
  },

  // Service Worker Settings
  serviceWorker: {
    enabled: true,
    scope: "/",
    cacheName: "bangrondb-cache",
    strategies: {
      static: "cache-first",
      api: "network-first",
      dynamic: "stale-while-revalidate",
    },
    precache: [
      "/",
      "/assets/css/style.css",
      "/assets/js/app.js",
      "/assets/js/dashboard.js",
    ],
    backgroundSync: {
      enabled: true,
      maxQueueSize: 10,
    },
    pushNotifications: {
      enabled: false,
    },
  },

  // Performance Budget Settings
  budget: {
    enabled: true,
    resourceSizes: {
      total: 500000, // bytes
      javascript: 200000,
      css: 100000,
      images: 200000,
    },
    requestCounts: {
      total: 50,
      thirdParty: 10,
    },
    loadTime: {
      firstContentfulPaint: 1000,
      largestContentfulPaint: 2500,
      interactive: 3000,
    },
  },

  // Debug Settings
  debug: {
    enabled: false,
    verbose: false,
    logLevel: "info",
    performanceMarkers: true,
    cacheLogging: false,
    queryLogging: false,
  },
};

// Initialize Performance Configuration
class PerformanceConfig {
  constructor() {
    this.config = PERFORMANCE_CONFIG;
    this.initialize();
  }

  initialize() {
    // Load configuration from localStorage if available
    const savedConfig = localStorage.getItem("performance_config");
    if (savedConfig) {
      try {
        const parsed = JSON.parse(savedConfig);
        this.mergeConfig(parsed);
      } catch (e) {
        console.warn("Failed to parse saved performance config:", e);
      }
    }

    // Apply configuration
    this.applyConfiguration();
  }

  mergeConfig(newConfig) {
    this.config = this.deepMerge(this.config, newConfig);
  }

  deepMerge(target, source) {
    const result = { ...target };
    for (const key in source) {
      if (
        source[key] &&
        typeof source[key] === "object" &&
        !Array.isArray(source[key])
      ) {
        result[key] = this.deepMerge(target[key] || {}, source[key]);
      } else {
        result[key] = source[key];
      }
    }
    return result;
  }

  applyConfiguration() {
    // Apply debug settings
    if (this.config.debug.enabled) {
      window.DEBUG_MODE = true;
      window.DEBUG_VERBOSE = this.config.debug.verbose;
      console.log("Performance debug mode enabled");
    }

    // Apply performance budget
    if (this.config.budget.enabled) {
      this.setupPerformanceBudget();
    }

    // Register service worker if enabled
    if (this.config.serviceWorker.enabled && "serviceWorker" in navigator) {
      this.registerServiceWorker();
    }
  }

  setupPerformanceBudget() {
    // Implement performance budget monitoring
    const observer = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        this.checkPerformanceBudget(entry);
      }
    });

    observer.observe({ entryTypes: ["resource", "paint", "navigation"] });
  }

  checkPerformanceBudget(entry) {
    const budget = this.config.budget;

    // Check resource sizes
    if (entry.transferSize && entry.transferSize > budget.resourceSizes.total) {
      console.warn(
        `Resource size exceeded budget: ${entry.transferSize} > ${budget.resourceSizes.total}`,
      );
    }

    // Check load times
    if (entry.duration && entry.duration > budget.loadTime.interactive) {
      console.warn(
        `Load time exceeded budget: ${entry.duration}ms > ${budget.loadTime.interactive}ms`,
      );
    }
  }

  registerServiceWorker() {
    navigator.serviceWorker
      .register("/assets/js/service-worker.js")
      .then((registration) => {
        console.log("Service Worker registered:", registration.scope);
      })
      .catch((error) => {
        console.error("Service Worker registration failed:", error);
      });
  }

  getConfig() {
    return this.config;
  }

  updateConfig(newConfig) {
    this.mergeConfig(newConfig);
    localStorage.setItem("performance_config", JSON.stringify(this.config));
    this.applyConfiguration();
  }

  resetConfig() {
    this.config = JSON.parse(JSON.stringify(PERFORMANCE_CONFIG));
    localStorage.removeItem("performance_config");
    this.applyConfiguration();
  }
}

// Initialize performance configuration
window.performanceConfig = new PerformanceConfig();

// Export configuration
export { PERFORMANCE_CONFIG, PerformanceConfig };
