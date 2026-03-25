/**
 * BangronDB Performance Monitoring System
 * Comprehensive real-time performance monitoring and analytics
 */

class PerformanceMonitor {
  constructor() {
    this.config = {
      enableRealTimeMonitoring: true,
      enablePerformanceMetrics: true,
      enableErrorTracking: true,
      enableUserAnalytics: true,
      enableNetworkMonitoring: true,
      enableResourceMonitoring: true,
      enableMemoryMonitoring: true,
      enableJavaScriptProfiling: true,
      enableCustomMetrics: true,
      enableAlerts: true,
      enableReporting: true,
      enablePerformanceBudget: true,
      sampleRate: 0.1, // 10% sample rate
      reportInterval: 30000, // 30 seconds
      alertThresholds: {
        responseTime: 1000, // 1 second
        errorRate: 0.05, // 5%
        memoryUsage: 0.8, // 80%
        cpuUsage: 0.8, // 80%
        resourceLoadTime: 5000, // 5 seconds
      },
    };

    this.metrics = {
      // Core web vitals
      coreWebVitals: {
        lcp: 0, // Largest Contentful Paint
        fid: 0, // First Input Delay
        cls: 0, // Cumulative Layout Shift
        fcp: 0, // First Contentful Paint
        ttfb: 0, // Time to First Byte
      },

      // Performance metrics
      performance: {
        pageLoadTime: 0,
        domReadyTime: 0,
        firstPaint: 0,
        firstContentfulPaint: 0,
        interactiveTime: 0,
        loadEventTime: 0,
      },

      // Network metrics
      network: {
        requests: [],
        responseTimes: [],
        errors: [],
        bandwidth: 0,
        connections: 0,
      },

      // Resource metrics
      resources: {
        loaded: [],
        failed: [],
        size: {},
        type: {},
      },

      // Memory metrics
      memory: {
        used: 0,
        total: 0,
        limit: 0,
        gcCount: 0,
        gcTime: 0,
      },

      // JavaScript metrics
      javascript: {
        executionTime: 0,
        parseTime: 0,
        compileTime: 0,
        eventListeners: 0,
        domNodes: 0,
      },

      // User analytics
      user: {
        interactions: [],
        sessions: [],
        conversions: [],
        errors: [],
        features: [],
      },

      // Custom metrics
      custom: {},

      // System metrics
      system: {
        cpu: 0,
        memory: 0,
        disk: 0,
        network: 0,
      },
    };

    this.alerts = [];
    this.reports = [];
    this.connections = [];
    this.startTime = Date.now();
    this.lastReportTime = Date.now();

    this.init();
  }

  init() {
    this.initializeMonitoring();
    this.setupPerformanceObservers();
    this.setupResourceMonitoring();
    this.setupMemoryMonitoring();
    this.setupNetworkMonitoring();
    this.setupUserAnalytics();
    this.setupAlerts();
    this.setupReporting();
  }

  // Initialize monitoring
  initializeMonitoring() {
    if (!this.config.enableRealTimeMonitoring) return;

    // Initialize performance monitoring
    this.initializePerformanceMonitoring();

    // Initialize error tracking
    if (this.config.enableErrorTracking) {
      this.initializeErrorTracking();
    }

    // Initialize custom metrics
    if (this.config.enableCustomMetrics) {
      this.initializeCustomMetrics();
    }

    // Initialize performance budget
    if (this.config.enablePerformanceBudget) {
      this.initializePerformanceBudget();
    }
  }

  // Initialize performance monitoring
  initializePerformanceMonitoring() {
    // Track page load performance
    window.addEventListener("load", () => {
      this.trackPageLoadPerformance();
    });

    // Track navigation changes
    window.addEventListener("popstate", () => {
      this.trackNavigationChange();
    });

    // Track visibility changes
    document.addEventListener("visibilitychange", () => {
      this.trackVisibilityChange();
    });
  }

  // Track page load performance
  trackPageLoadPerformance() {
    const navigation = performance.getEntriesByType("navigation")[0];
    if (navigation) {
      this.metrics.performance = {
        pageLoadTime: navigation.loadEventEnd - navigation.fetchStart,
        domReadyTime:
          navigation.domContentLoadedEventEnd - navigation.fetchStart,
        firstPaint: performance.getEntriesByType("paint")[0]?.startTime || 0,
        firstContentfulPaint:
          performance.getEntriesByType("paint")[1]?.startTime || 0,
        interactiveTime: navigation.domInteractive - navigation.fetchStart,
        loadEventTime: navigation.loadEventEnd - navigation.fetchStart,
      };

      // Track core web vitals
      this.trackCoreWebVitals();

      // Send performance analytics
      this.sendPerformanceAnalytics();
    }
  }

  // Track core web vitals
  trackCoreWebVitals() {
    // Largest Contentful Paint (LCP)
    const lcpObserver = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      const lastEntry = entries[entries.length - 1];
      this.metrics.coreWebVitals.lcp = lastEntry.startTime;
    });
    lcpObserver.observe({ entryTypes: ["largest-contentful-paint"] });

    // First Input Delay (FID)
    const fidObserver = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      entries.forEach((entry) => {
        this.metrics.coreWebVitals.fid =
          entry.processingStart - entry.startTime;
      });
    });
    fidObserver.observe({ entryTypes: ["first-input"] });

    // Cumulative Layout Shift (CLS)
    const clsObserver = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      entries.forEach((entry) => {
        this.metrics.coreWebVitals.cls += entry.value;
      });
    });
    clsObserver.observe({ entryTypes: ["layout-shift"] });

    // First Contentful Paint (FCP)
    const fcpObserver = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      this.metrics.coreWebVitals.fcp = entries[0].startTime;
    });
    fcpObserver.observe({ entryTypes: ["paint"] });

    // Time to First Byte (TTFB)
    const navigationObserver = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      this.metrics.coreWebVitals.ttfb =
        entries[0].responseStart - entries[0].fetchStart;
    });
    navigationObserver.observe({ entryTypes: ["navigation"] });
  }

  // Setup performance observers
  setupPerformanceObservers() {
    // Resource timing
    const resourceObserver = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      entries.forEach((entry) => {
        this.trackResource(entry);
      });
    });
    resourceObserver.observe({ entryTypes: ["resource"] });

    // Long tasks
    const longTaskObserver = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      entries.forEach((entry) => {
        this.trackLongTask(entry);
      });
    });
    longTaskObserver.observe({ entryTypes: ["longtask"] });

    // Paint timing
    const paintObserver = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      entries.forEach((entry) => {
        this.trackPaint(entry);
      });
    });
    paintObserver.observe({ entryTypes: ["paint"] });
  }

  // Track resource loading
  trackResource(entry) {
    const resource = {
      name: entry.name,
      type: entry.initiatorType,
      duration: entry.duration,
      size: entry.transferSize,
      startTime: entry.startTime,
      timestamp: Date.now(),
    };

    this.metrics.network.requests.push(resource);
    this.metrics.resources.loaded.push(resource);

    // Track by type
    if (!this.metrics.resources.type[entry.initiatorType]) {
      this.metrics.resources.type[entry.initiatorType] = [];
    }
    this.metrics.resources.type[entry.initiatorType].push(resource);

    // Track by size
    if (!this.metrics.resources.size[entry.initiatorType]) {
      this.metrics.resources.size[entry.initiatorType] = 0;
    }
    this.metrics.resources.size[entry.initiatorType] += entry.transferSize;

    // Check for failed resources
    if (entry.duration > this.config.alertThresholds.resourceLoadTime) {
      this.createAlert("resource_load_time", {
        name: entry.name,
        duration: entry.duration,
        threshold: this.config.alertThresholds.resourceLoadTime,
      });
    }
  }

  // Track long tasks
  trackLongTask(entry) {
    const longTask = {
      duration: entry.duration,
      startTime: entry.startTime,
      timestamp: Date.now(),
    };

    this.metrics.javascript.executionTime += entry.duration;

    // Check for long tasks
    if (entry.duration > 100) {
      this.createAlert("long_task", {
        duration: entry.duration,
        startTime: entry.startTime,
      });
    }
  }

  // Track paint events
  trackPaint(entry) {
    const paint = {
      name: entry.name,
      startTime: entry.startTime,
      timestamp: Date.now(),
    };

    this.metrics.performance[entry.name.toLowerCase()] = entry.startTime;
  }

  // Setup resource monitoring
  setupResourceMonitoring() {
    if (!this.config.enableResourceMonitoring) return;

    // Monitor resource loading
    const observer = new PerformanceObserver((entryList) => {
      const entries = entryList.getEntries();
      entries.forEach((entry) => {
        this.trackResource(entry);
      });
    });
    observer.observe({ entryTypes: ["resource"] });
  }

  // Setup memory monitoring
  setupMemoryMonitoring() {
    if (!this.config.enableMemoryMonitoring) return;

    // Monitor memory usage
    setInterval(() => {
      if (performance.memory) {
        this.metrics.memory = {
          used: performance.memory.usedJSHeapSize,
          total: performance.memory.totalJSHeapSize,
          limit: performance.memory.jsHeapSizeLimit,
          gcCount: performance.memory.gc ? performance.memory.gc.count : 0,
          gcTime: performance.memory.gc ? performance.memory.gc.totalTime : 0,
        };

        // Check memory usage
        const memoryUsage =
          performance.memory.usedJSHeapSize /
          performance.memory.jsHeapSizeLimit;
        if (memoryUsage > this.config.alertThresholds.memoryUsage) {
          this.createAlert("memory_usage", {
            usage: memoryUsage,
            used: performance.memory.usedJSHeapSize,
            limit: performance.memory.jsHeapSizeLimit,
          });
        }
      }
    }, 5000);
  }

  // Setup network monitoring
  setupNetworkMonitoring() {
    if (!this.config.enableNetworkMonitoring) return;

    // Monitor network requests
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
      const startTime = performance.now();

      try {
        const response = await originalFetch(...args);
        const endTime = performance.now();

        const request = {
          url: args[0],
          method: args[1]?.method || "GET",
          status: response.status,
          duration: endTime - startTime,
          startTime: startTime,
          timestamp: Date.now(),
        };

        this.metrics.network.requests.push(request);
        this.metrics.network.responseTimes.push(request.duration);

        // Track errors
        if (!response.ok) {
          this.metrics.network.errors.push(request);
          this.createAlert("network_error", request);
        }

        return response;
      } catch (error) {
        const endTime = performance.now();

        const request = {
          url: args[0],
          method: args[1]?.method || "GET",
          status: 0,
          duration: endTime - startTime,
          startTime: startTime,
          timestamp: Date.now(),
          error: error.message,
        };

        this.metrics.network.errors.push(request);
        this.createAlert("network_error", request);

        throw error;
      }
    };

    // Monitor XHR requests
    const originalXHR = window.XMLHttpRequest;
    window.XMLHttpRequest = function () {
      const xhr = new originalXHR();
      const originalOpen = xhr.open;

      xhr.open = function (method, url) {
        originalOpen.apply(this, arguments);

        const startTime = performance.now();

        xhr.addEventListener("load", () => {
          const endTime = performance.now();

          const request = {
            url: url,
            method: method,
            status: xhr.status,
            duration: endTime - startTime,
            startTime: startTime,
            timestamp: Date.now(),
          };

          this.metrics.network.requests.push(request);
          this.metrics.network.responseTimes.push(request.duration);

          if (xhr.status >= 400) {
            this.metrics.network.errors.push(request);
            this.createAlert("network_error", request);
          }
        });

        xhr.addEventListener("error", () => {
          const endTime = performance.now();

          const request = {
            url: url,
            method: method,
            status: 0,
            duration: endTime - startTime,
            startTime: startTime,
            timestamp: Date.now(),
            error: "Network error",
          };

          this.metrics.network.errors.push(request);
          this.createAlert("network_error", request);
        });
      };

      return xhr;
    };
  }

  // Setup user analytics
  setupUserAnalytics() {
    if (!this.config.enableUserAnalytics) return;

    // Track user interactions
    document.addEventListener("click", (event) => {
      this.trackUserInteraction("click", event);
    });

    document.addEventListener("input", (event) => {
      this.trackUserInteraction("input", event);
    });

    document.addEventListener("scroll", (event) => {
      this.trackUserInteraction("scroll", event);
    });

    // Track page visibility
    document.addEventListener("visibilitychange", () => {
      this.trackPageVisibility();
    });
  }

  // Track user interactions
  trackUserInteraction(type, event) {
    const interaction = {
      type: type,
      target: event.target.tagName,
      timestamp: Date.now(),
      page: window.location.pathname,
    };

    this.metrics.user.interactions.push(interaction);

    // Keep only last 1000 interactions
    if (this.metrics.user.interactions.length > 1000) {
      this.metrics.user.interactions.shift();
    }
  }

  // Track page visibility
  trackPageVisibility() {
    const visibility = {
      visible: document.visibilityState === "visible",
      timestamp: Date.now(),
      page: window.location.pathname,
    };

    this.metrics.user.sessions.push(visibility);
  }

  // Setup alerts
  setupAlerts() {
    if (!this.config.enableAlerts) return;

    // Check thresholds periodically
    setInterval(() => {
      this.checkThresholds();
    }, 10000);
  }

  // Check alert thresholds
  checkThresholds() {
    // Check response time
    const avgResponseTime =
      this.metrics.network.responseTimes.length > 0
        ? this.metrics.network.responseTimes.reduce((a, b) => a + b, 0) /
          this.metrics.network.responseTimes.length
        : 0;

    if (avgResponseTime > this.config.alertThresholds.responseTime) {
      this.createAlert("response_time", {
        average: avgResponseTime,
        threshold: this.config.alertThresholds.responseTime,
      });
    }

    // Check error rate
    const errorRate =
      this.metrics.network.requests.length > 0
        ? this.metrics.network.errors.length /
          this.metrics.network.requests.length
        : 0;

    if (errorRate > this.config.alertThresholds.errorRate) {
      this.createAlert("error_rate", {
        rate: errorRate,
        threshold: this.config.alertThresholds.errorRate,
      });
    }
  }

  // Create alert
  createAlert(type, data) {
    const alert = {
      type: type,
      data: data,
      timestamp: Date.now(),
      acknowledged: false,
    };

    this.alerts.push(alert);

    // Keep only last 100 alerts
    if (this.alerts.length > 100) {
      this.alerts.shift();
    }

    // Send alert notification
    this.sendAlert(alert);
  }

  // Send alert
  sendAlert(alert) {
    // Show notification to user
    this.showAlertNotification(alert);

    // Send alert to server
    if (navigator.sendBeacon) {
      navigator.sendBeacon("/api/analytics/alert", JSON.stringify(alert));
    }
  }

  // Show alert notification
  showAlertNotification(alert) {
    const notification = document.createElement("div");
    notification.className = `alert alert-${alert.type}`;
    notification.innerHTML = `
      <div class="alert-content">
        <div class="alert-title">Performance Alert</div>
        <div class="alert-message">${alert.type}: ${JSON.stringify(alert.data)}</div>
        <div class="alert-time">${new Date(alert.timestamp).toLocaleString()}</div>
      </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 10 seconds
    setTimeout(() => {
      notification.remove();
    }, 10000);
  }

  // Setup reporting
  setupReporting() {
    if (!this.config.enableReporting) return;

    // Send periodic reports
    setInterval(() => {
      this.sendReport();
    }, this.config.reportInterval);
  }

  // Send performance report
  sendReport() {
    const report = {
      timestamp: Date.now(),
      duration: Date.now() - this.lastReportTime,
      metrics: this.getMetricsSummary(),
      alerts: this.alerts.filter(
        (alert) => alert.timestamp > this.lastReportTime,
      ),
      user: {
        interactions: this.metrics.user.interactions.filter(
          (i) => i.timestamp > this.lastReportTime,
        ),
        sessions: this.metrics.user.sessions.filter(
          (s) => s.timestamp > this.lastReportTime,
        ),
      },
      network: {
        requests: this.metrics.network.requests.filter(
          (r) => r.timestamp > this.lastReportTime,
        ),
        errors: this.metrics.network.errors.filter(
          (e) => e.timestamp > this.lastReportTime,
        ),
      },
      resources: {
        loaded: this.metrics.resources.loaded.filter(
          (r) => r.timestamp > this.lastReportTime,
        ),
        failed: this.metrics.resources.failed.filter(
          (f) => f.timestamp > this.lastReportTime,
        ),
      },
    };

    this.reports.push(report);

    // Keep only last 10 reports
    if (this.reports.length > 10) {
      this.reports.shift();
    }

    // Send report to server
    if (navigator.sendBeacon) {
      navigator.sendBeacon("/api/analytics/report", JSON.stringify(report));
    }

    this.lastReportTime = Date.now();
  }

  // Get metrics summary
  getMetricsSummary() {
    return {
      coreWebVitals: this.metrics.coreWebVitals,
      performance: this.metrics.performance,
      network: {
        totalRequests: this.metrics.network.requests.length,
        totalErrors: this.metrics.network.errors.length,
        averageResponseTime:
          this.metrics.network.responseTimes.length > 0
            ? this.metrics.network.responseTimes.reduce((a, b) => a + b, 0) /
              this.metrics.network.responseTimes.length
            : 0,
      },
      memory: this.metrics.memory,
      javascript: this.metrics.javascript,
      user: {
        totalInteractions: this.metrics.user.interactions.length,
        totalSessions: this.metrics.user.sessions.length,
      },
    };
  }

  // Send performance analytics
  sendPerformanceAnalytics() {
    const analytics = {
      type: "performance",
      metrics: this.getMetricsSummary(),
      timestamp: Date.now(),
    };

    if (navigator.sendBeacon) {
      navigator.sendBeacon(
        "/api/analytics/performance",
        JSON.stringify(analytics),
      );
    }
  }

  // Initialize error tracking
  initializeErrorTracking() {
    // Track global errors
    window.addEventListener("error", (event) => {
      this.trackError(event.error, "error");
    });

    // Track unhandled promise rejections
    window.addEventListener("unhandledrejection", (event) => {
      this.trackError(event.reason, "unhandled_rejection");
    });
  }

  // Track error
  trackError(error, type) {
    const errorData = {
      type: type,
      message: error.message,
      stack: error.stack,
      timestamp: Date.now(),
      page: window.location.pathname,
      userAgent: navigator.userAgent,
    };

    this.metrics.user.errors.push(errorData);

    // Create error alert
    this.createAlert("error", errorData);

    // Send error analytics
    this.sendErrorAnalytics(errorData);
  }

  // Send error analytics
  sendErrorAnalytics(errorData) {
    if (navigator.sendBeacon) {
      navigator.sendBeacon("/api/analytics/error", JSON.stringify(errorData));
    }
  }

  // Initialize custom metrics
  initializeCustomMetrics() {
    // Allow custom metrics to be registered
    window.addEventListener("custom-metric", (event) => {
      this.trackCustomMetric(
        event.detail.name,
        event.detail.value,
        event.detail.metadata,
      );
    });
  }

  // Track custom metric
  trackCustomMetric(name, value, metadata = {}) {
    if (!this.metrics.custom[name]) {
      this.metrics.custom[name] = [];
    }

    this.metrics.custom[name].push({
      value: value,
      metadata: metadata,
      timestamp: Date.now(),
    });

    // Keep only last 100 values
    if (this.metrics.custom[name].length > 100) {
      this.metrics.custom[name].shift();
    }
  }

  // Initialize performance budget
  initializePerformanceBudget() {
    // Set performance budget
    this.performanceBudget = {
      maxLoadTime: 3000,
      maxResources: 50,
      maxResponseTime: 1000,
      maxMemoryUsage: 0.8,
    };

    // Check performance budget
    setInterval(() => {
      this.checkPerformanceBudget();
    }, 30000);
  }

  // Check performance budget
  checkPerformanceBudget() {
    const loadTime = this.metrics.performance.pageLoadTime;
    const resourceCount = this.metrics.resources.loaded.length;
    const avgResponseTime =
      this.metrics.network.responseTimes.length > 0
        ? this.metrics.network.responseTimes.reduce((a, b) => a + b, 0) /
          this.metrics.network.responseTimes.length
        : 0;
    const memoryUsage = this.metrics.memory.used / this.metrics.memory.limit;

    if (loadTime > this.performanceBudget.maxLoadTime) {
      this.createAlert("performance_budget", {
        type: "load_time",
        value: loadTime,
        budget: this.performanceBudget.maxLoadTime,
      });
    }

    if (resourceCount > this.performanceBudget.maxResources) {
      this.createAlert("performance_budget", {
        type: "resource_count",
        value: resourceCount,
        budget: this.performanceBudget.maxResources,
      });
    }

    if (avgResponseTime > this.performanceBudget.maxResponseTime) {
      this.createAlert("performance_budget", {
        type: "response_time",
        value: avgResponseTime,
        budget: this.performanceBudget.maxResponseTime,
      });
    }

    if (memoryUsage > this.performanceBudget.maxMemoryUsage) {
      this.createAlert("performance_budget", {
        type: "memory_usage",
        value: memoryUsage,
        budget: this.performanceBudget.maxMemoryUsage,
      });
    }
  }

  // Track navigation change
  trackNavigationChange() {
    const navigation = {
      from: window.location.href,
      to: window.location.href,
      timestamp: Date.now(),
      type: "popstate",
    };

    this.metrics.user.sessions.push(navigation);
  }

  // Track visibility change
  trackVisibilityChange() {
    const visibility = {
      visible: document.visibilityState === "visible",
      timestamp: Date.now(),
      page: window.location.pathname,
    };

    this.metrics.user.sessions.push(visibility);
  }

  // Public API
  getMetrics() {
    return this.metrics;
  }

  getAlerts() {
    return this.alerts;
  }

  getReports() {
    return this.reports;
  }

  getCurrentMetrics() {
    return this.getMetricsSummary();
  }

  getAlertSummary() {
    const summary = {
      total: this.alerts.length,
      byType: {},
      recent: this.alerts.filter(
        (alert) => Date.now() - alert.timestamp < 3600000,
      ), // Last hour
    };

    this.alerts.forEach((alert) => {
      if (!summary.byType[alert.type]) {
        summary.byType[alert.type] = 0;
      }
      summary.byType[alert.type]++;
    });

    return summary;
  }

  // Cleanup
  destroy() {
    // Clear all metrics
    this.metrics = {
      coreWebVitals: {},
      performance: {},
      network: { requests: [], responseTimes: [], errors: [] },
      resources: { loaded: [], failed: {}, size: {}, type: {} },
      memory: {},
      javascript: {},
      user: { interactions: [], sessions: [], errors: [], features: [] },
      custom: {},
      system: {},
    };

    // Clear alerts and reports
    this.alerts = [];
    this.reports = [];

    // Clear intervals
    clearInterval(this.monitoringInterval);
    clearInterval(this.reportingInterval);
  }
}

// Initialize performance monitor
window.performanceMonitor = new PerformanceMonitor();

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = PerformanceMonitor;
}
