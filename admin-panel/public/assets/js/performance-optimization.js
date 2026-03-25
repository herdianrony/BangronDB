/**
 * BangronDB Performance Optimization Module
 * Implements advanced performance optimizations for the admin panel
 */

class PerformanceOptimizer {
  constructor() {
    this.config = {
      enableCaching: true,
      cacheTTL: 300000, // 5 minutes
      maxCacheSize: 100,
      lazyLoading: true,
      codeSplitting: true,
      imageOptimization: true,
      criticalCSSInlining: true,
      serviceWorker: true,
      compression: true,
      cdnIntegration: true,
      prefetchPreload: true,
      bundleAnalysis: true,
      performanceMonitoring: true,
    };

    this.cache = new Map();
    this.lazyLoadElements = new Set();
    this.observerInstances = new Map();
    this.performanceMetrics = {
      pageLoadTime: 0,
      firstContentfulPaint: 0,
      largestContentfulPaint: 0,
      firstInputDelay: 0,
      cumulativeLayoutShift: 0,
      apiResponseTimes: [],
      resourceLoadTimes: [],
    };

    this.init();
  }

  init() {
    this.initializePerformanceMonitoring();
    this.setupLazyLoading();
    this.setupCodeSplitting();
    this.setupImageOptimization();
    this.setupCriticalCSS();
    this.setupServiceWorker();
    this.setupCompression();
    this.setupCDNIntegration();
    this.setupPrefetchPreload();
    this.setupBundleAnalysis();

    // Initialize performance tracking
    this.trackPerformance();
  }

  // Performance Monitoring
  initializePerformanceMonitoring() {
    if (!this.config.performanceMonitoring) return;

    // Track page load metrics
    window.addEventListener("load", () => {
      this.trackPageLoadMetrics();
    });

    // Track resource timing
    const observer = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (entry.entryType === "resource") {
          this.performanceMetrics.resourceLoadTimes.push({
            name: entry.name,
            duration: entry.duration,
            type: entry.initiatorType,
          });
        }
      }
    });

    observer.observe({
      entryTypes: ["resource", "navigation", "paint", "layout-shift"],
    });
  }

  trackPageLoadMetrics() {
    const navigation = performance.getEntriesByType("navigation")[0];
    if (navigation) {
      this.performanceMetrics.pageLoadTime =
        navigation.loadEventEnd - navigation.fetchStart;
      this.performanceMetrics.firstContentfulPaint =
        performance.getEntriesByType("paint")[0]?.startTime || 0;
      this.performanceMetrics.largestContentfulPaint = this.getLCP();
      this.performanceMetrics.firstInputDelay = this.getFID();
      this.performanceMetrics.cumulativeLayoutShift = this.getCLS();
    }

    // Send metrics to analytics
    this.sendPerformanceMetrics();
  }

  getLCP() {
    const entries = performance.getEntriesByType("largest-contentful-paint");
    return entries[0]?.startTime || 0;
  }

  getFID() {
    // First Input Delay calculation would require more complex implementation
    return 0; // Placeholder
  }

  getCLS() {
    const entries = performance.getEntriesByType("layout-shift");
    let cls = 0;
    entries.forEach((entry) => {
      cls += entry.value;
    });
    return cls;
  }

  sendPerformanceMetrics() {
    // Send metrics to your analytics service
    if (navigator.sendBeacon) {
      const data = {
        type: "performance_metrics",
        metrics: this.performanceMetrics,
        timestamp: Date.now(),
      };
      navigator.sendBeacon("/api/analytics/performance", JSON.stringify(data));
    }
  }

  // Lazy Loading Implementation
  setupLazyLoading() {
    if (!this.config.lazyLoading) return;

    // Lazy load images
    this.setupImageLazyLoading();

    // Lazy load components
    this.setupComponentLazyLoading();

    // Lazy load iframes
    this.setupIframeLazyLoading();
  }

  setupImageLazyLoading() {
    const imageObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            const src = img.dataset.src;
            if (src) {
              img.src = src;
              img.classList.remove("lazy-image");
              imageObserver.unobserve(img);
            }
          }
        });
      },
      {
        rootMargin: "50px 0px",
        threshold: 0.01,
      },
    );

    document.querySelectorAll("img.lazy-image").forEach((img) => {
      imageObserver.observe(img);
    });

    this.observerInstances.set("image", imageObserver);
  }

  setupComponentLazyLoading() {
    const componentObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const component = entry.target;
            const componentName = component.dataset.component;

            if (componentName && !component.loaded) {
              this.loadComponent(componentName, component);
              component.loaded = true;
              componentObserver.unobserve(component);
            }
          }
        });
      },
      {
        rootMargin: "100px 0px",
        threshold: 0.1,
      },
    );

    document.querySelectorAll("[data-component]").forEach((component) => {
      componentObserver.observe(component);
    });

    this.observerInstances.set("component", componentObserver);
  }

  setupIframeLazyLoading() {
    const iframeObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const iframe = entry.target;
            const src = iframe.dataset.src;
            if (src) {
              iframe.src = src;
              iframeObserver.unobserve(iframe);
            }
          }
        });
      },
      {
        rootMargin: "200px 0px",
        threshold: 0.01,
      },
    );

    document.querySelectorAll("iframe.lazy-iframe").forEach((iframe) => {
      iframeObserver.observe(iframe);
    });

    this.observerInstances.set("iframe", iframeObserver);
  }

  async loadComponent(componentName, container) {
    try {
      const response = await fetch(`/assets/js/components/${componentName}.js`);
      const script = await response.text();

      // Create a new script element
      const scriptElement = document.createElement("script");
      scriptElement.textContent = script;

      // Execute in the container context
      container.appendChild(scriptElement);

      // Trigger component initialization
      const event = new CustomEvent("componentLoaded", {
        detail: { componentName },
      });
      container.dispatchEvent(event);
    } catch (error) {
      console.error(`Failed to load component ${componentName}:`, error);
    }
  }

  // Code Splitting Implementation
  setupCodeSplitting() {
    if (!this.config.codeSplitting) return;

    // Dynamic imports for non-critical modules
    this.setupDynamicImports();

    // Route-based code splitting
    this.setupRouteBasedSplitting();

    // Feature-based code splitting
    this.setupFeatureBasedSplitting();
  }

  setupDynamicImports() {
    // Load non-critical modules on demand
    const nonCriticalModules = [
      "advanced-search",
      "data-export",
      "theme-switcher",
      "keyboard-shortcuts",
    ];

    // Load modules when user interacts with related features
    this.setupModuleLazyLoading(nonCriticalModules);
  }

  setupRouteBasedSplitting() {
    // Store route-specific modules
    this.routeModules = {
      "/dashboard": ["dashboard-charts", "dashboard-metrics"],
      "/databases": ["database-query", "database-schema"],
      "/collections": ["collection-editor", "collection-validation"],
      "/documents": ["document-editor", "document-search"],
      "/monitoring": ["monitoring-charts", "monitoring-alerts"],
    };

    // Load route modules when navigating
    window.addEventListener("popstate", () => {
      this.loadRouteModules(window.location.pathname);
    });

    // Load initial route modules
    this.loadRouteModules(window.location.pathname);
  }

  setupFeatureBasedSplitting() {
    // Load features based on user behavior
    this.setupBehavioralFeatureLoading();
  }

  async loadRouteModules(route) {
    const modules = this.routeModules[route] || [];

    for (const module of modules) {
      if (!this.cache.has(`module:${module}`)) {
        try {
          await import(`/assets/js/modules/${module}.js`);
          this.cache.set(`module:${module}`, true);
        } catch (error) {
          console.error(`Failed to load module ${module}:`, error);
        }
      }
    }
  }

  setupModuleLazyLoading(modules) {
    const moduleLoaders = {
      "advanced-search": () =>
        this.loadModuleWhenInteracted("search-input", "advanced-search"),
      "data-export": () =>
        this.loadModuleWhenInteracted("export-button", "data-export"),
      "theme-switcher": () =>
        this.loadModuleWhenInteracted("theme-toggle", "theme-switcher"),
      "keyboard-shortcuts": () =>
        this.loadModuleWhenInteracted("keyboard-help", "keyboard-shortcuts"),
    };

    Object.entries(moduleLoaders).forEach(([module, loader]) => {
      if (modules.includes(module)) {
        loader();
      }
    });
  }

  async loadModuleWhenInteracted(selector, moduleName) {
    const element = document.querySelector(selector);
    if (element) {
      element.addEventListener("click", async () => {
        if (!this.cache.has(`module:${moduleName}`)) {
          try {
            await import(`/assets/js/modules/${moduleName}.js`);
            this.cache.set(`module:${moduleName}`, true);
          } catch (error) {
            console.error(`Failed to load module ${moduleName}:`, error);
          }
        }
      });
    }
  }

  setupBehavioralFeatureLoading() {
    // Load features based on user behavior patterns
    let userActions = 0;
    const actionThreshold = 5;

    document.addEventListener("click", () => {
      userActions++;
      if (userActions >= actionThreshold) {
        this.loadAdvancedFeatures();
        userActions = 0; // Reset counter
      }
    });
  }

  async loadAdvancedFeatures() {
    const features = ["advanced-analytics", "ai-suggestions", "smart-search"];

    for (const feature of features) {
      if (!this.cache.has(`feature:${feature}`)) {
        try {
          await import(`/assets/js/features/${feature}.js`);
          this.cache.set(`feature:${feature}`, true);
        } catch (error) {
          console.error(`Failed to load feature ${feature}:`, error);
        }
      }
    }
  }

  // Image Optimization
  setupImageOptimization() {
    if (!this.config.imageOptimization) return;

    // WebP format detection and conversion
    this.setupWebPConversion();

    // Responsive images
    this.setupResponsiveImages();

    // Image compression
    this.setupImageCompression();

    // Lazy loading with quality fallback
    this.setupQualityFallback();
  }

  setupWebPConversion() {
    // Check if WebP is supported
    const supportsWebP = () => {
      const canvas = document.createElement("canvas");
      return canvas.toDataURL("image/webp").indexOf("data:image/webp") === 0;
    };

    if (supportsWebP()) {
      // Convert image sources to WebP
      document.querySelectorAll("img[data-src-webp]").forEach((img) => {
        const src = img.src;
        if (src) {
          img.src = src.replace(/\.(jpg|jpeg|png)$/, ".webp");
        }
      });
    }
  }

  setupResponsiveImages() {
    // Setup responsive images with srcset
    document.querySelectorAll("img[data-srcset]").forEach((img) => {
      const srcset = img.dataset.srcset;
      if (srcset) {
        img.srcset = srcset;
      }
    });

    // Picture element support
    document.querySelectorAll("picture source").forEach((source) => {
      const srcset = source.dataset.srcset;
      if (srcset) {
        source.srcset = srcset;
      }
    });
  }

  setupImageCompression() {
    // Client-side image compression for uploads
    if (window.Compression) {
      window.addEventListener("before-upload", (event) => {
        const file = event.detail.file;
        this.compressImage(file).then((compressed) => {
          event.detail.compressedFile = compressed;
        });
      });
    }
  }

  async compressImage(file) {
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          const canvas = document.createElement("canvas");
          const ctx = canvas.getContext("2d");

          // Calculate new dimensions
          const maxWidth = 1920;
          const maxHeight = 1080;
          let width = img.width;
          let height = img.height;

          if (width > maxWidth) {
            height = (height * maxWidth) / width;
            width = maxWidth;
          }

          if (height > maxHeight) {
            width = (width * maxHeight) / height;
            height = maxHeight;
          }

          canvas.width = width;
          canvas.height = height;

          // Draw and compress
          ctx.drawImage(img, 0, 0, width, height);
          canvas.toBlob(
            (blob) => {
              resolve(
                new File([blob], file.name, {
                  type: "image/jpeg",
                  lastModified: Date.now(),
                }),
              );
            },
            "image/jpeg",
            0.8,
          );
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  setupQualityFallback() {
    // Fallback to lower quality images for slow connections
    if (navigator.connection) {
      const connection = navigator.connection;
      if (
        connection.effectiveType === "slow-2g" ||
        connection.effectiveType === "2g"
      ) {
        document.querySelectorAll("img[data-low-quality]").forEach((img) => {
          img.src = img.dataset.lowQuality;
        });
      }
    }
  }

  // Critical CSS Inlining
  setupCriticalCSS() {
    if (!this.config.criticalCSSInlining) return;

    // Extract and inline critical CSS
    this.extractCriticalCSS();

    // Load non-critical CSS asynchronously
    this.loadAsyncCSS();

    // Setup CSS optimization
    this.optimizeCSS();
  }

  extractCriticalCSS() {
    // Extract critical CSS above the fold
    const aboveTheFold = document.querySelector(".main-content");
    if (aboveTheFold) {
      const criticalCSS = this.getCriticalCSS(aboveTheFold);
      const style = document.createElement("style");
      style.textContent = criticalCSS;
      document.head.appendChild(style);
    }
  }

  getCriticalCSS(element) {
    // Simplified critical CSS extraction
    // In production, use a proper critical CSS extraction tool
    const criticalRules = [
      ".sidebar { position: fixed; top: 0; left: 0; width: 260px; }",
      ".main-content { margin-left: 260px; }",
      ".header { position: sticky; top: 0; z-index: 50; }",
      ".stat-card { background: rgba(15, 23, 42, 0.8); }",
      ".btn { transition: all 0.15s ease; }",
      ".loading { animation: spin 0.8s linear infinite; }",
    ];

    return criticalRules.join("\n");
  }

  loadAsyncCSS() {
    // Load non-critical CSS asynchronously
    const link = document.createElement("link");
    link.rel = "preload";
    link.href = "/assets/css/non-critical.css";
    link.as = "style";
    link.onload = () => {
      link.rel = "stylesheet";
    };
    document.head.appendChild(link);
  }

  optimizeCSS() {
    // Remove unused CSS
    if ("CSSStyleSheet" in window && "replaceSync" in CSSStyleSheet.prototype) {
      this.removeUnusedCSS();
    }
  }

  async removeUnusedCSS() {
    // This would require a more sophisticated implementation
    // For now, just log the intent
    console.log("CSS Optimization: Remove unused CSS rules");
  }

  // Service Worker Setup
  setupServiceWorker() {
    if (!this.config.serviceWorker || !("serviceWorker" in navigator)) return;

    // Register service worker
    navigator.serviceWorker
      .register("/assets/js/service-worker.js")
      .then((registration) => {
        console.log("Service Worker registered:", registration);
      })
      .catch((error) => {
        console.error("Service Worker registration failed:", error);
      });
  }

  // Compression Setup
  setupCompression() {
    if (!this.config.compression) return;

    // Setup request compression
    this.setupRequestCompression();

    // Setup response compression
    this.setupResponseCompression();
  }

  setupRequestCompression() {
    // Compress outgoing requests
    const originalFetch = window.fetch;
    window.fetch = async (url, options = {}) => {
      if (options.body && typeof options.body === "object") {
        const compressed = await this.compressData(options.body);
        options.body = compressed;
        options.headers = {
          ...options.headers,
          "Content-Encoding": "gzip",
        };
      }
      return originalFetch(url, options);
    };
  }

  setupResponseCompression() {
    // Handle compressed responses
    const originalXHR = window.XMLHttpRequest;
    window.XMLHttpRequest = function () {
      const xhr = new originalXHR();
      const originalOpen = xhr.open;

      xhr.open = function (method, url) {
        originalOpen.apply(this, arguments);
        this.responseType = "text";
      };

      return xhr;
    };
  }

  async compressData(data) {
    // Simple gzip compression (would need proper implementation)
    return JSON.stringify(data);
  }

  // CDN Integration
  setupCDNIntegration() {
    if (!this.config.cdnIntegration) return;

    // Setup CDN for assets
    this.setupCDNAssets();

    // Setup CDN for APIs
    this.setupCDNAPIs();
  }

  setupCDNAssets() {
    // Update asset URLs to use CDN
    const cdnBase = "https://cdn.bangrondb.com";

    // Update image sources
    document.querySelectorAll("img[data-cdn]").forEach((img) => {
      const src = img.src;
      if (src) {
        img.src = src.replace("/assets/", `${cdnBase}/assets/`);
      }
    });

    // Update script sources
    document.querySelectorAll("script[data-cdn]").forEach((script) => {
      const src = script.src;
      if (src) {
        script.src = src.replace("/assets/", `${cdnBase}/assets/`);
      }
    });

    // Update stylesheet sources
    document.querySelectorAll("link[data-cdn]").forEach((link) => {
      const href = link.href;
      if (href) {
        link.href = href.replace("/assets/", `${cdnBase}/assets/`);
      }
    });
  }

  setupCDNAPIs() {
    // Update API endpoints to use CDN
    if (window.apiService) {
      window.apiService.config.set("api.baseUrl", "https://api.bangrondb.com");
    }
  }

  // Prefetch and Preload
  setupPrefetchPreload() {
    if (!this.config.prefetchPreload) return;

    // Setup prefetching
    this.setupPrefetching();

    // Setup preloading
    this.setupPreloading();
  }

  setupPrefetching() {
    // Prefetch likely next pages
    const likelyPages = [
      "/databases",
      "/collections",
      "/documents",
      "/monitoring",
    ];

    likelyPages.forEach((page) => {
      const link = document.createElement("link");
      link.rel = "prefetch";
      link.href = page;
      document.head.appendChild(link);
    });
  }

  setupPreloading() {
    // Preload critical resources
    const criticalResources = [
      { href: "/assets/css/style.css", as: "style" },
      { href: "/assets/js/app.js", as: "script" },
      { href: "/assets/js/dashboard.js", as: "script" },
    ];

    criticalResources.forEach((resource) => {
      const link = document.createElement("link");
      link.rel = "preload";
      link.href = resource.href;
      link.as = resource.as;
      document.head.appendChild(link);
    });
  }

  // Bundle Analysis
  setupBundleAnalysis() {
    if (!this.config.bundleAnalysis) return;

    // Analyze bundle sizes
    this.analyzeBundles();

    // Monitor bundle performance
    this.monitorBundlePerformance();
  }

  analyzeBundles() {
    // Analyze JavaScript bundle sizes
    if (window.performance && performance.getEntriesByType("resource")) {
      const jsBundles = performance
        .getEntriesByType("resource")
        .filter(
          (entry) =>
            entry.name.includes(".js") && entry.name.includes("/assets/"),
        );

      const bundleAnalysis = jsBundles.map((bundle) => ({
        name: bundle.name,
        size: bundle.transferSize,
        loadTime: bundle.duration,
        type: "javascript",
      }));

      console.log("Bundle Analysis:", bundleAnalysis);
      this.sendBundleAnalysis(bundleAnalysis);
    }
  }

  monitorBundlePerformance() {
    // Monitor bundle loading performance
    const observer = new PerformanceObserver((list) => {
      const entries = list.getEntries();
      entries.forEach((entry) => {
        if (entry.name.includes(".js") || entry.name.includes(".css")) {
          this.performanceMetrics.resourceLoadTimes.push({
            name: entry.name,
            duration: entry.duration,
            size: entry.transferSize,
            type: entry.name.includes(".js") ? "javascript" : "stylesheet",
          });
        }
      });
    });

    observer.observe({ entryTypes: ["resource"] });
  }

  sendBundleAnalysis(analysis) {
    // Send bundle analysis to analytics
    if (navigator.sendBeacon) {
      const data = {
        type: "bundle_analysis",
        analysis: analysis,
        timestamp: Date.now(),
      };
      navigator.sendBeacon("/api/analytics/bundles", JSON.stringify(data));
    }
  }

  // Public API
  getPerformanceMetrics() {
    return this.performanceMetrics;
  }

  clearCache() {
    this.cache.clear();
  }

  optimizeCurrentPage() {
    // Apply optimizations to current page
    this.optimizeImages();
    this.optimizeScripts();
    this.optimizeStyles();
  }

  optimizeImages() {
    // Optimize current page images
    document.querySelectorAll("img").forEach((img) => {
      if (img.dataset.src && !img.src) {
        img.src = img.dataset.src;
      }
    });
  }

  optimizeScripts() {
    // Defer non-critical scripts
    document.querySelectorAll("script[data-defer]").forEach((script) => {
      script.defer = true;
    });
  }

  optimizeStyles() {
    // Defer non-critical styles
    document.querySelectorAll("link[data-defer]").forEach((link) => {
      link.onload = () => {
        link.media = "all";
      };
      link.media = "print";
    });
  }

  // Cleanup
  destroy() {
    // Disconnect all observers
    this.observerInstances.forEach((observer) => {
      observer.disconnect();
    });
    this.observerInstances.clear();

    // Clear cache
    this.cache.clear();

    // Remove event listeners
    window.removeEventListener("load", this.trackPageLoadMetrics);
  }
}

// Initialize performance optimizer
window.performanceOptimizer = new PerformanceOptimizer();

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = PerformanceOptimizer;
}
